<?php

namespace App\Services\Sorteo;

use App\Helpers\TelefonoParaguayHelper;
use App\Models\Auditoria;
use App\Models\Cliente;
use App\Models\Cobro;
use App\Models\Cupon;
use App\Models\FacturaInterna;
use App\Models\FacturaInternaDetalle;
use App\Models\PromoAjuste;
use App\Models\Servicio;
use App\Models\SorteoCampana;
use App\Models\SorteoGanador;
use App\Models\SorteoPremioCategoria;
use App\Models\SorteoResena;
use App\Models\SorteoTicket;
use App\Models\User;
use Carbon\Carbon;
use DomainException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class SorteoService
{
    /** @var array<string, mixed>|null */
    private ?array $lote = null;

    public function evaluarCobro(Cobro $cobro, ?int $usuarioId = null): void
    {
        $campana = SorteoCampana::activa();
        if (! $campana) {
            return;
        }
        $cliente = $cobro->relationLoaded('cliente') ? $cobro->cliente : Cliente::find($cobro->cliente_id);
        if (! $cliente) {
            return;
        }
        $fecha = Carbon::parse($cobro->fecha_pago)->timezone($campana->timezone);
        $cobroId = $fecha->lte($campana->limite()) ? (int) $cobro->id : null;
        $this->sincronizarTickets($campana, $cliente, $usuarioId, $cobroId);
    }

    public function anularPorCobro(Cobro $cobro, ?int $usuarioId = null): void
    {
        $campana = SorteoCampana::activa();
        if (! $campana) {
            $campana = SorteoTicket::query()->where('cobro_id', $cobro->id)->first()?->campana;
        }
        if (! $campana) {
            return;
        }
        $cliente = Cliente::find($cobro->cliente_id);
        if (! $cliente) {
            return;
        }
        $this->quitarResenaDelCobro($campana, (int) $cliente->cliente_id, (int) $cobro->id);
        $this->sincronizarTickets($campana, $cliente, $usuarioId, null, (int) $cobro->id);
    }

    /**
     * La reseña marcada en caja pertenece a ese cobro. Al eliminarlo, el cupón extra se va.
     * La que el cliente envió por la web se conserva.
     */
    private function quitarResenaDelCobro(SorteoCampana $campana, int $clienteId, int $cobroId): void
    {
        $resena = SorteoResena::query()
            ->where('campana_id', $campana->id)
            ->where('cliente_id', $clienteId)
            ->where('cobro_id', $cobroId)
            ->first();
        if (! $resena) {
            return;
        }
        if ($resena->enviada_at !== null) {
            $resena->cobro_id = null;
            $resena->save();

            return;
        }
        $resena->delete();
    }

    public function evaluarFacturaRecienEmitida(FacturaInterna $factura, ?int $usuarioId = null): void
    {
        $campana = SorteoCampana::activa();
        if (! $campana || $factura->tipo_factura !== FacturaInterna::TIPO_SERVICIO) {
            return;
        }
        if (! $this->facturaEsDelPeriodo($campana, $factura)) {
            return;
        }
        $cliente = $factura->relationLoaded('cliente') ? $factura->cliente : Cliente::find($factura->cliente_id);
        if (! $cliente) {
            return;
        }
        $this->sincronizarTickets($campana, $cliente, $usuarioId);
    }

    public function alActivarApp(Cliente $cliente, ?int $usuarioId = null): void
    {
        $this->otorgarBienvenida($cliente, $usuarioId);
        $campana = SorteoCampana::activa();
        if (! $campana) {
            return;
        }
        $this->sincronizarTickets($campana, $cliente, $usuarioId);
    }

    public function guardarResena(SorteoCampana $campana, Cliente $cliente, string $telefono, ?int $usuarioId, ?int $cobroId = null): SorteoResena
    {
        $telefono = TelefonoParaguayHelper::paraGuardar($telefono);
        if ($telefono !== '' && $cliente->telefono !== $telefono) {
            $cliente->telefono = $telefono;
            $cliente->save();
        }
        $resena = SorteoResena::query()->firstOrNew([
            'campana_id' => $campana->id,
            'cliente_id' => $cliente->cliente_id,
        ]);
        $nueva = ! $resena->exists;
        $resena->telefono = $telefono;
        if ($cobroId) {
            $resena->cobro_id = $cobroId;
        }
        if ($resena->marcada_at === null && ($nueva || $usuarioId !== null)) {
            $resena->marcada_por = $usuarioId;
            $resena->marcada_at = now();
        }
        $resena->save();
        $this->auditar($usuarioId, 'sorteo_resenas', $nueva ? 'created' : 'updated', (int) $resena->id, [
            'cliente_id' => $cliente->cliente_id,
            'telefono' => $telefono,
        ]);
        if ($campana->activa) {
            $this->sincronizarTickets($campana, $cliente, $usuarioId, $cobroId);
        }

        return $resena;
    }

    public function otorgarBienvenida(Cliente $cliente, ?int $usuarioId = null): bool
    {
        $ajuste = PromoAjuste::bienvenida();
        if (! $ajuste || ! $ajuste->activo) {
            return false;
        }
        $existe = Cupon::query()
            ->where('cliente_id', $cliente->cliente_id)
            ->where('tipo', Cupon::TIPO_BIENVENIDA)
            ->exists();
        if ($existe) {
            return false;
        }
        $cupon = Cupon::create([
            'cliente_id' => $cliente->cliente_id,
            'tipo' => Cupon::TIPO_BIENVENIDA,
            'porcentaje' => $ajuste->porcentaje,
            'ventana' => '',
            'estado' => Cupon::PENDIENTE,
            'created_by' => $usuarioId,
        ]);
        $this->auditar($usuarioId, 'cupones', 'created', (int) $cupon->id, ['tipo' => Cupon::TIPO_BIENVENIDA]);

        return true;
    }

    /**
     * @param  float  $subtotal
     * @param  float  $totalImpuestos
     * @param  float  $total
     */
    public function aplicarCuponesEnEmision(FacturaInterna $factura, float &$subtotal, float &$totalImpuestos, float &$total, ?int $impuestoId): void
    {
        if ($factura->tipo_factura !== FacturaInterna::TIPO_SERVICIO) {
            return;
        }
        $mes = Carbon::parse($factura->fecha_emision)->format('Y-m');
        $premios = Cupon::query()
            ->where('cliente_id', $factura->cliente_id)
            ->where('estado', Cupon::PENDIENTE)
            ->where('ventana', $mes)
            ->where('tipo', 'like', 'sorteo_%')
            ->orderBy('id')
            ->get();
        foreach ($premios as $cupon) {
            $this->descontar($factura, $cupon, $subtotal, $totalImpuestos, $total, $impuestoId, false);
        }
        $ajuste = PromoAjuste::bienvenida();
        if ($ajuste && $ajuste->activo && $total > 0.5) {
            $bienvenida = Cupon::query()
                ->where('cliente_id', $factura->cliente_id)
                ->where('tipo', Cupon::TIPO_BIENVENIDA)
                ->where('estado', Cupon::PENDIENTE)
                ->first();
            if ($bienvenida) {
                $this->descontar($factura, $bienvenida, $subtotal, $totalImpuestos, $total, $impuestoId, false);
            }
        }
    }

    public function aplicarCupon(Cupon $cupon, FacturaInterna $factura): void
    {
        $subtotal = (float) $factura->subtotal;
        $impuestos = (float) $factura->total_impuestos;
        $total = (float) $factura->total;
        $this->descontar($factura, $cupon, $subtotal, $impuestos, $total, null, true);
    }

    /**
     * @param  list<int>  $clienteIds
     * @return array{cargados: int, avisos: list<string>}
     */
    public function cargarGanadores(SorteoCampana $campana, SorteoPremioCategoria $categoria, array $clienteIds, ?int $usuarioId): array
    {
        $clienteIds = array_values(array_unique(array_filter(array_map('intval', $clienteIds))));
        $ya = SorteoGanador::query()
            ->where('campana_id', $campana->id)
            ->where('categoria_id', $categoria->id)
            ->count();
        if ($ya + count($clienteIds) > (int) $categoria->cupo) {
            throw new \DomainException('El cupo de '.$categoria->nombre.' es '.$categoria->cupo.'.');
        }
        $cargados = 0;
        $avisos = [];
        foreach ($clienteIds as $clienteId) {
            $cliente = Cliente::find($clienteId);
            if (! $cliente) {
                $avisos[] = 'Cliente '.$clienteId.' no existe.';
                continue;
            }
            if (! $campana->permite_multiples_premios) {
                $otro = SorteoGanador::query()
                    ->where('campana_id', $campana->id)
                    ->where('cliente_id', $clienteId)
                    ->exists();
                if ($otro) {
                    $avisos[] = trim($cliente->nombre.' '.$cliente->apellido).' ya tiene un premio en esta campaña.';
                    continue;
                }
            }
            $existe = SorteoGanador::query()
                ->where('campana_id', $campana->id)
                ->where('cliente_id', $clienteId)
                ->where('categoria_id', $categoria->id)
                ->exists();
            if ($existe) {
                continue;
            }
            $ganador = SorteoGanador::create([
                'campana_id' => $campana->id,
                'cliente_id' => $clienteId,
                'categoria_id' => $categoria->id,
                'created_by' => $usuarioId,
            ]);
            $this->auditar($usuarioId, 'sorteo_ganadores', 'created', (int) $ganador->id, [
                'cliente_id' => $clienteId,
                'categoria' => $categoria->codigo,
            ]);
            $cargados++;
            if ($categoria->tieneDescuento()) {
                foreach ($categoria->ventanas_emision ?? [] as $ventana) {
                    $ventana = (string) $ventana;
                    if (! preg_match('/^\d{4}-\d{2}$/', $ventana)) {
                        continue;
                    }
                    $cupon = Cupon::query()->firstOrCreate(
                        [
                            'cliente_id' => $clienteId,
                            'tipo' => 'sorteo_'.$categoria->codigo,
                            'ventana' => $ventana,
                        ],
                        [
                            'porcentaje' => $categoria->porcentaje,
                            'campana_id' => $campana->id,
                            'ganador_id' => $ganador->id,
                            'estado' => Cupon::PENDIENTE,
                            'created_by' => $usuarioId,
                        ]
                    );
                    $abierta = $this->facturaAbiertaDeVentana($clienteId, $ventana);
                    if ($abierta && $cupon->estado === Cupon::PENDIENTE) {
                        $this->aplicarCupon($cupon, $abierta);
                    } elseif (! $abierta) {
                        $avisos[] = 'Cupón '.$ventana.' de '.trim($cliente->nombre.' '.$cliente->apellido).' queda pendiente hasta emitir esa factura.';
                    }
                }
            }
        }

        return ['cargados' => $cargados, 'avisos' => $avisos];
    }

    /**
     * @return array{tickets: int, ya: int, anticipos: int, bienvenida: int}
     */
    public function previsualizar(SorteoCampana $campana, bool $tickets, bool $bienvenida): array
    {
        $nuevos = 0;
        $ya = 0;
        $anticipados = 0;
        $aptos = 0;
        if ($tickets) {
            $clientes = $this->clientesActivos();
            $this->prepararLote($campana, $clientes->pluck('cliente_id')->all());
            try {
                foreach ($clientes as $cliente) {
                    $fila = $this->clasificar($campana, $cliente);
                    if ($fila['nivel'] === 'no') {
                        continue;
                    }
                    $aptos++;
                    if ($fila['anticipado']) {
                        $anticipados++;
                    }
                    $activo = SorteoTicket::query()
                        ->where('campana_id', $campana->id)
                        ->where('cliente_id', $cliente->cliente_id)
                        ->where('origen', SorteoTicket::ORIGEN_PAGO)
                        ->where('estado', SorteoTicket::ACTIVO)
                        ->exists();
                    if ($activo) {
                        $ya++;
                    } else {
                        $nuevos++;
                    }
                }
            } finally {
                $this->lote = null;
            }
        }

        return [
            'tickets' => $nuevos,
            'ya' => $ya,
            'aptos' => $aptos,
            'anticipados' => $anticipados,
            'anticipos' => $anticipados,
            'bienvenida' => $bienvenida ? $this->contarBienvenidaNueva() : 0,
        ];
    }

    /**
     * @return array{tickets: int, bienvenida: int}
     */
    public function ejecutarBackfill(SorteoCampana $campana, bool $tickets, bool $bienvenida): array
    {
        $creados = 0;
        if ($tickets && $campana->activa) {
            $clientes = $this->clientesActivos();
            $this->prepararLote($campana, $clientes->pluck('cliente_id')->all());
            foreach ($clientes as $cliente) {
                $antes = SorteoTicket::query()
                    ->where('campana_id', $campana->id)
                    ->where('cliente_id', $cliente->cliente_id)
                    ->where('origen', SorteoTicket::ORIGEN_PAGO)
                    ->where('estado', SorteoTicket::ACTIVO)
                    ->exists();
                $this->sincronizarTickets($campana, $cliente, null);
                $despues = SorteoTicket::query()
                    ->where('campana_id', $campana->id)
                    ->where('cliente_id', $cliente->cliente_id)
                    ->where('origen', SorteoTicket::ORIGEN_PAGO)
                    ->where('estado', SorteoTicket::ACTIVO)
                    ->exists();
                if (! $antes && $despues) {
                    $creados++;
                }
            }
            $this->lote = null;
        }
        $bien = 0;
        if ($bienvenida) {
            Cliente::query()->where('app_activa', true)->orderBy('cliente_id')->chunk(200, function ($clientes) use (&$bien) {
                foreach ($clientes as $cliente) {
                    if ($this->otorgarBienvenida($cliente)) {
                        $bien++;
                    }
                }
            });
        }

        return ['tickets' => $creados, 'bienvenida' => $bien];
    }

    /**
     * @return array{nivel: string, motivo: ?string, puntual_participacion: bool, puntual_100: bool, anticipado: bool, mes_faltante: ?string, etiqueta: string}
     */
    public function clasificar(SorteoCampana $campana, Cliente $cliente, ?int $excluirCobroId = null): array
    {
        $facturasPeriodo = $this->facturasDelPeriodo($campana, $cliente->cliente_id);
        $anticipado = false;
        $puntualParticipacion = false;
        $cobertura = $facturasPeriodo->isEmpty()
            ? []
            : $this->cobertura($cliente->cliente_id, $facturasPeriodo, $campana->limite(), $excluirCobroId);

        $gate = $this->participaPorPago($campana, $cliente->cliente_id, $excluirCobroId);
        $apto = $gate['ok'];
        $motivo = $apto ? null : $gate['motivo'];
        if ($apto && $facturasPeriodo->isEmpty()) {
            $anticipado = $this->pagoAnticipado($campana, $cliente->cliente_id, $excluirCobroId);
            $puntualParticipacion = $anticipado;
        }

        if (! $anticipado && $facturasPeriodo->isNotEmpty()) {
            $puntualParticipacion = $apto && $facturasPeriodo->every(function (FacturaInterna $f) use ($campana, $cobertura) {
                $fecha = $cobertura[$f->id]['fecha'] ?? null;

                return $fecha instanceof Carbon && $this->esPuntual($campana, $f, $fecha);
            });
        }
        $mesFaltante = $puntualParticipacion
            ? $this->mesQueFalta($campana, $cliente->cliente_id, $anticipado)
            : null;
        $puntual100 = $puntualParticipacion && $mesFaltante === null && ($campana->meses_puntualidad_100 ?? []) !== [];

        $nivel = 'no';
        if ($apto && $puntual100) {
            $nivel = '100';
        } elseif ($apto && $anticipado) {
            $nivel = 'anticipado';
        } elseif ($apto && $puntualParticipacion) {
            $nivel = '50';
        } elseif ($apto) {
            $nivel = 'apto';
        }

        $clase = [
            'nivel' => $nivel,
            'motivo' => $motivo,
            'puntual_participacion' => $puntualParticipacion,
            'puntual_100' => $puntual100,
            'anticipado' => $anticipado && $apto,
            'mes_faltante' => $nivel !== 'no' && $nivel !== '100' ? $mesFaltante : null,
        ];
        $clase['etiqueta'] = $this->etiqueta($clase);

        return $clase;
    }

    /**
     * @return array{filas: list<array<string, mixed>>, totales: array<string, int>}
     */
    public function listado(SorteoCampana $campana, ?string $buscar, string $filtro): array
    {
        $ids = $this->idsListado($campana, $buscar);
        $this->prepararLote($campana, $ids);
        $clientes = Cliente::query()->whereIn('cliente_id', $ids)->orderBy('nombre')->orderBy('apellido')->get();
        $tickets = SorteoTicket::query()->where('campana_id', $campana->id)->whereIn('cliente_id', $ids)->get()->groupBy('cliente_id');
        $resenas = SorteoResena::query()->where('campana_id', $campana->id)->whereIn('cliente_id', $ids)->get()->keyBy('cliente_id');

        $filas = [];
        $totales = ['100' => 0, '50' => 0, 'aptos' => 0, 'no' => 0, 'clientes' => 0, 'con_app' => 0, 'participando' => 0, 'resena' => 0];
        foreach ($clientes as $cliente) {
            $clase = $this->clasificar($campana, $cliente);
            $totales['clientes']++;
            if ($this->appATiempo($campana, $cliente)) {
                $totales['con_app']++;
            }
            if ($clase['nivel'] === '100') {
                $totales['100']++;
                $totales['50']++;
                $totales['aptos']++;
            } elseif ($clase['nivel'] === '50' || $clase['nivel'] === 'anticipado') {
                $totales['50']++;
                $totales['aptos']++;
            } elseif ($clase['nivel'] === 'apto') {
                $totales['aptos']++;
            } else {
                $totales['no']++;
            }
            $delCliente = $tickets->get($cliente->cliente_id, collect());
            $pago = $delCliente->first(fn (SorteoTicket $t) => $t->origen === SorteoTicket::ORIGEN_PAGO && $t->estado === SorteoTicket::ACTIVO);
            $appTicket = $delCliente->first(fn (SorteoTicket $t) => $t->origen === SorteoTicket::ORIGEN_APP && $t->estado === SorteoTicket::ACTIVO);
            $extra = $delCliente->first(fn (SorteoTicket $t) => $t->origen === SorteoTicket::ORIGEN_RESENA && $t->estado === SorteoTicket::ACTIVO);
            $resena = $resenas->get($cliente->cliente_id);
            $participando = $pago !== null || $appTicket !== null || $extra !== null;
            $resenaPendiente = $resena !== null && $resena->enviada_at !== null && $resena->marcada_at === null;
            if ($participando) {
                $totales['participando']++;
            }
            if ($resenaPendiente) {
                $totales['resena']++;
            }
            if (! $this->pasaFiltro($filtro, $clase['nivel'], $participando, $resenaPendiente)) {
                continue;
            }
            $filas[] = [
                'cliente_id' => $cliente->cliente_id,
                'nombre' => trim($cliente->nombre.' '.$cliente->apellido),
                'cedula' => $cliente->cedula,
                'telefono' => $resena?->telefono ?? $cliente->telefono,
                'nivel' => $clase['nivel'],
                'motivo' => $clase['motivo'],
                'etiqueta' => $clase['etiqueta'],
                'mes_faltante' => $clase['mes_faltante'],
                'ticket' => $pago?->numeroFormateado(),
                'app' => $appTicket?->numeroFormateado(),
                'extra' => $extra?->numeroFormateado(),
                'cupones' => ($pago ? 1 : 0) + ($appTicket ? 1 : 0) + ($extra ? 1 : 0),
                'resena' => $resena !== null,
                'nombre_google' => $resena?->nombre_google,
                'enviada_at' => $resena?->enviada_at?->timezone($campana->timezone)->format('d/m/Y H:i'),
                'acreditada' => $resena?->marcada_at !== null,
            ];
        }
        $this->lote = null;

        return ['filas' => $filas, 'totales' => $totales];
    }

    /**
     * El QR del recibo sale solo si el cliente participa: sin saldo y con un pago hasta el corte.
     */
    public function muestraQrRecibo(Cobro $cobro): bool
    {
        $campana = SorteoCampana::activa();
        $cliente = $cobro->cliente_id
            ? ($cobro->relationLoaded('cliente') ? $cobro->cliente : Cliente::find($cobro->cliente_id))
            : null;
        if (! $campana || ! $cliente) {
            return false;
        }

        return $this->clasificar($campana, $cliente)['nivel'] !== 'no';
    }

    /**
     * @return array<string, mixed>|null
     */
    public function bloqueRecibo(Cobro $cobro): ?array
    {
        $campana = SorteoCampana::activa();
        if (! $campana || ! $campana->imprimir_en_recibo) {
            return null;
        }
        $pago = SorteoTicket::query()
            ->where('campana_id', $campana->id)
            ->where('cobro_id', $cobro->id)
            ->where('origen', SorteoTicket::ORIGEN_PAGO)
            ->where('estado', SorteoTicket::ACTIVO)
            ->first();
        if (! $pago) {
            return null;
        }
        $app = SorteoTicket::query()
            ->where('campana_id', $campana->id)
            ->where('cliente_id', $pago->cliente_id)
            ->where('origen', SorteoTicket::ORIGEN_APP)
            ->where('estado', SorteoTicket::ACTIVO)
            ->first();
        $extra = SorteoTicket::query()
            ->where('campana_id', $campana->id)
            ->where('cliente_id', $pago->cliente_id)
            ->where('origen', SorteoTicket::ORIGEN_RESENA)
            ->where('estado', SorteoTicket::ACTIVO)
            ->first();
        $resena = $extra
            ? SorteoResena::query()->where('campana_id', $campana->id)->where('cliente_id', $pago->cliente_id)->first()
            : null;
        $cliente = $cobro->relationLoaded('cliente') ? $cobro->cliente : Cliente::find($pago->cliente_id);

        return [
            'texto' => Str::ascii($campana->texto_ticket),
            'fecha_sorteo' => $campana->fecha_sorteo?->format('d/m/Y'),
            'numero' => $pago->numeroFormateado(),
            'app' => $app?->numeroFormateado(),
            'extra' => $extra?->numeroFormateado(),
            'nombre' => Str::ascii(trim(($cliente->nombre ?? '').' '.($cliente->apellido ?? ''))),
            'cedula' => Str::ascii((string) ($cliente->cedula ?? '')),
            'fecha' => $cobro->fecha_pago?->timezone($campana->timezone)->format('d/m/Y H:i'),
            'telefono' => $resena?->telefono,
        ];
    }

    /**
     * Datos que ve el cliente en interplus.com.py/sorteo/{id}. Sin cédula, deuda ni celular completo.
     *
     * @return array<string, mixed>
     */
    public function fichaPublica(Cliente $cliente): array
    {
        $campana = SorteoCampana::activa();
        $clase = $campana ? $this->clasificar($campana, $cliente) : null;
        $participa = $clase !== null && $clase['nivel'] !== 'no';
        $origenes = collect();
        $resena = null;
        if ($campana) {
            $origenes = SorteoTicket::query()
                ->where('campana_id', $campana->id)
                ->where('cliente_id', $cliente->cliente_id)
                ->where('estado', SorteoTicket::ACTIVO)
                ->pluck('origen');
            $resena = SorteoResena::query()
                ->where('campana_id', $campana->id)
                ->where('cliente_id', $cliente->cliente_id)
                ->first();
        }

        return [
            'nombre' => $this->nombrePublico($cliente),
            'participa' => $participa,
            'etiqueta' => $clase['etiqueta'] ?? null,
            'cupones' => $origenes->count(),
            'resena_acreditada' => $origenes->contains(SorteoTicket::ORIGEN_RESENA),
            'falta_celular' => ! $this->datosEnviados($resena),
            'pide_cedula' => ! $this->datosEnviados($resena) && $this->digitosCedula($cliente) !== null,
            'celular_oculto' => $this->datosEnviados($resena) ? $this->celularOculto($resena->telefono ?: $cliente->telefono) : null,
            'google_usuario' => $this->datosEnviados($resena) ? $resena->nombre_google : null,
            'app_activa' => $this->clienteTieneApp($cliente),
            'campana' => $campana ? $this->campanaPublica($campana) : null,
        ];
    }

    /**
     * Un solo envío del cliente: celular y nombre de Google. No acredita el cupón de reseña.
     *
     * @return array<string, mixed>
     */
    public function guardarCelularPublico(Cliente $cliente, string $telefono, ?string $cedulaUltimos, string $nombreGoogle): array
    {
        $campana = SorteoCampana::activa();
        if (! $campana) {
            throw new DomainException('No hay un sorteo activo en este momento.');
        }
        $nombre = trim(preg_replace('/\s+/', ' ', $nombreGoogle) ?? '');
        if (mb_strlen($nombre) < 2 || mb_strlen($nombre) > 80) {
            throw new DomainException('Escribí el nombre que aparece en tu reseña de Google.');
        }
        if (! TelefonoParaguayHelper::esMovilParaguay($telefono)) {
            throw new DomainException('Escribí un celular de Paraguay, por ejemplo 0981123456.');
        }
        $digitos = $this->digitosCedula($cliente);
        if ($digitos !== null) {
            $dados = preg_replace('/\D/', '', (string) $cedulaUltimos) ?? '';
            if (strlen($dados) !== 4 || ! hash_equals($digitos, $dados)) {
                throw new DomainException('Los últimos 4 números de la cédula no coinciden.');
            }
        }

        $guardado = TelefonoParaguayHelper::paraGuardar($telefono);
        DB::transaction(function () use ($campana, $cliente, $guardado, $nombre) {
            $resena = SorteoResena::query()
                ->where('campana_id', $campana->id)
                ->where('cliente_id', $cliente->cliente_id)
                ->lockForUpdate()
                ->first();
            if ($this->datosEnviados($resena)) {
                throw new DomainException('Ya enviaste tus datos para este sorteo.');
            }
            if (! $resena) {
                $resena = new SorteoResena([
                    'campana_id' => $campana->id,
                    'cliente_id' => $cliente->cliente_id,
                ]);
            }
            $resena->telefono = $guardado;
            $resena->nombre_google = $nombre;
            $resena->enviada_at = now();
            $resena->save();
            if (! TelefonoParaguayHelper::esMovilParaguay($cliente->telefono)) {
                $cliente->telefono = $guardado;
                $cliente->save();
            }
            $this->auditar(null, 'sorteo_resenas', 'enviada', (int) $resena->id, [
                'cliente_id' => $cliente->cliente_id,
                'nombre_google' => $nombre,
            ]);
        });

        return $this->fichaPublica($cliente->fresh());
    }

    /**
     * El operador verificó la reseña. Acredita el cupón extra una sola vez.
     */
    public function acreditarResena(SorteoCampana $campana, Cliente $cliente, ?int $usuarioId): void
    {
        $resena = SorteoResena::query()
            ->where('campana_id', $campana->id)
            ->where('cliente_id', $cliente->cliente_id)
            ->first();
        if (! $this->datosEnviados($resena)) {
            throw new DomainException('Este cliente todavía no envió sus datos.');
        }
        if ($resena->marcada_at !== null) {
            throw new DomainException('El cupón de reseña ya fue acreditado.');
        }
        $resena->marcada_por = $usuarioId;
        $resena->marcada_at = now();
        $resena->save();
        $this->auditar($usuarioId, 'sorteo_resenas', 'acreditada', (int) $resena->id, [
            'cliente_id' => $cliente->cliente_id,
        ]);
        if ($campana->activa) {
            $this->sincronizarTickets($campana, $cliente->fresh(), $usuarioId);
        }
    }

    private function datosEnviados(?SorteoResena $resena): bool
    {
        return $resena !== null && $resena->enviada_at !== null;
    }

    private function sincronizarTickets(SorteoCampana $campana, Cliente $cliente, ?int $usuarioId, ?int $cobroId = null, ?int $excluirCobroId = null): void
    {
        if (! $campana->activa) {
            return;
        }
        $clase = $this->clasificar($campana, $cliente, $excluirCobroId);
        $apto = $clase['nivel'] !== 'no';
        $cobroReferencia = $cobroId;
        if ($apto && ! $cobroReferencia) {
            $cobroReferencia = $this->cobroQueCubre($campana, $cliente->cliente_id, $excluirCobroId);
        }
        $this->asegurarTicket($campana, $cliente->cliente_id, SorteoTicket::ORIGEN_PAGO, $apto, $usuarioId, $cobroReferencia);
        $conApp = $apto && $campana->exige_app && $this->appATiempo($campana, $cliente);
        $this->asegurarTicket($campana, $cliente->cliente_id, SorteoTicket::ORIGEN_APP, $conApp, $usuarioId, null);
        $resenaVerificada = SorteoResena::query()
            ->where('campana_id', $campana->id)
            ->where('cliente_id', $cliente->cliente_id)
            ->whereNotNull('marcada_at')
            ->exists();
        $this->asegurarTicket($campana, $cliente->cliente_id, SorteoTicket::ORIGEN_RESENA, $apto && $resenaVerificada, $usuarioId, null);
    }

    private function asegurarTicket(SorteoCampana $campana, int $clienteId, string $origen, bool $activo, ?int $usuarioId, ?int $cobroId): void
    {
        DB::transaction(function () use ($campana, $clienteId, $origen, $activo, $usuarioId, $cobroId) {
            SorteoCampana::query()->where('id', $campana->id)->lockForUpdate()->first();
            $ticket = SorteoTicket::query()
                ->where('campana_id', $campana->id)
                ->where('cliente_id', $clienteId)
                ->where('origen', $origen)
                ->lockForUpdate()
                ->first();
            if (! $activo) {
                if ($ticket && $ticket->estado === SorteoTicket::ACTIVO) {
                    $ticket->update([
                        'estado' => SorteoTicket::ANULADO,
                        'anulado_por' => $usuarioId,
                        'anulado_at' => now(),
                    ]);
                    $this->auditar($usuarioId, 'sorteo_tickets', 'anulado', (int) $ticket->id, ['origen' => $origen]);
                }

                return;
            }
            if ($ticket) {
                $reactivado = $ticket->estado !== SorteoTicket::ACTIVO;
                $ticket->estado = SorteoTicket::ACTIVO;
                $ticket->anulado_por = null;
                $ticket->anulado_at = null;
                if ($origen === SorteoTicket::ORIGEN_PAGO && $cobroId) {
                    $ticket->cobro_id = $cobroId;
                }
                $ticket->save();
                if ($reactivado) {
                    $this->auditar($usuarioId, 'sorteo_tickets', 'reactivado', (int) $ticket->id, ['origen' => $origen]);
                }

                return;
            }
            $numero = ((int) SorteoTicket::query()->max('numero')) + 1;
            $ticket = SorteoTicket::create([
                'campana_id' => $campana->id,
                'cliente_id' => $clienteId,
                'numero' => $numero,
                'origen' => $origen,
                'cobro_id' => $origen === SorteoTicket::ORIGEN_PAGO ? $cobroId : null,
                'estado' => SorteoTicket::ACTIVO,
                'created_by' => $usuarioId,
            ]);
            $this->auditar($usuarioId, 'sorteo_tickets', 'created', (int) $ticket->id, [
                'numero' => $numero,
                'origen' => $origen,
            ]);
        });
    }

    private function descontar(FacturaInterna $factura, Cupon $cupon, float &$subtotal, float &$totalImpuestos, float &$total, ?int $impuestoId, bool $persistirFactura): void
    {
        DB::transaction(function () use ($factura, $cupon, &$subtotal, &$totalImpuestos, &$total, $impuestoId, $persistirFactura) {
            $fresco = Cupon::query()->where('id', $cupon->id)->lockForUpdate()->first();
            if (! $fresco || $fresco->estado !== Cupon::PENDIENTE) {
                throw new \DomainException('El cupón ya fue canjeado.');
            }
            if ($total <= 0.009) {
                return;
            }
            $monto = round($total * ((float) $fresco->porcentaje) / 100, 2);
            $monto = min($monto, round($total, 2));
            if ($monto <= 0.009) {
                return;
            }
            $descSubtotal = $subtotal;
            $descImpuesto = $totalImpuestos;
            if ($monto < $total - 0.009) {
                $ratio = $monto / $total;
                $descSubtotal = round($subtotal * $ratio, 2);
                $descImpuesto = round($totalImpuestos * $ratio, 2);
                $ajuste = round($monto - ($descSubtotal + $descImpuesto), 2);
                if (abs($ajuste) >= 0.01) {
                    $descImpuesto = round($descImpuesto + $ajuste, 2);
                }
            }
            FacturaInternaDetalle::create([
                'factura_interna_id' => $factura->id,
                'impuesto_id' => $impuestoId,
                'servicio_id' => null,
                'descripcion' => 'Cupon '.rtrim(rtrim(number_format((float) $fresco->porcentaje, 2, '.', ''), '0'), '.').'%',
                'cantidad' => 1,
                'precio_unitario' => -$monto,
                'subtotal' => -$descSubtotal,
                'porcentaje_impuesto' => 0,
                'monto_impuesto' => -$descImpuesto,
                'total' => -$monto,
            ]);
            $subtotal = max(0, round($subtotal - $descSubtotal, 2));
            $totalImpuestos = max(0, round($totalImpuestos - $descImpuesto, 2));
            $total = max(0, round($total - $monto, 2));
            $fresco->update([
                'estado' => Cupon::CANJEADO,
                'factura_interna_id' => $factura->id,
                'canjeado_at' => now(),
            ]);
            $cupon->estado = Cupon::CANJEADO;
            $cupon->factura_interna_id = $factura->id;
            if ($persistirFactura) {
                $factura->update([
                    'subtotal' => $subtotal,
                    'total_impuestos' => $totalImpuestos,
                    'total' => $total,
                    'estado' => $total <= 0.009 ? 'pagada' : $factura->estado,
                ]);
            }
            $this->auditar($fresco->created_by, 'cupones', 'canjeado', (int) $fresco->id, [
                'factura_interna_id' => $factura->id,
                'monto' => $monto,
            ]);
        });
    }

    private function facturaAbiertaDeVentana(int $clienteId, string $ventana): ?FacturaInterna
    {
        $inicio = Carbon::createFromFormat('Y-m', $ventana)->startOfMonth()->toDateString();
        $fin = Carbon::createFromFormat('Y-m', $ventana)->endOfMonth()->toDateString();

        return FacturaInterna::query()
            ->where('cliente_id', $clienteId)
            ->where('tipo_factura', FacturaInterna::TIPO_SERVICIO)
            ->whereDate('fecha_emision', '>=', $inicio)
            ->whereDate('fecha_emision', '<=', $fin)
            ->whereIn('estado', ['pendiente', 'emitida'])
            ->where('total', '>', 0)
            ->orderBy('id')
            ->first();
    }

    /**
     * @return Collection<int, FacturaInterna>
     */
    private function facturasDelPeriodo(SorteoCampana $campana, int $clienteId): Collection
    {
        if ($this->lote !== null) {
            return $this->lote['periodo'][$clienteId] ?? collect();
        }

        return FacturaInterna::query()
            ->where('cliente_id', $clienteId)
            ->where('tipo_factura', FacturaInterna::TIPO_SERVICIO)
            ->whereDate('periodo_desde', '>=', $campana->periodo_uso_desde->toDateString())
            ->whereDate('periodo_desde', '<=', $campana->periodo_uso_hasta->toDateString())
            ->orderBy('id')
            ->get();
    }

    private function facturaEsDelPeriodo(SorteoCampana $campana, FacturaInterna $factura): bool
    {
        if (! $factura->periodo_desde) {
            return false;
        }
        $desde = $factura->periodo_desde->toDateString();

        return $desde >= $campana->periodo_uso_desde->toDateString()
            && $desde <= $campana->periodo_uso_hasta->toDateString();
    }

    /**
     * @param  Collection<int, FacturaInterna>  $facturas
     * @return array<int, array{cubierta: bool, fecha: ?Carbon}>
     */
    private function cobertura(int $clienteId, Collection $facturas, ?Carbon $limite, ?int $excluirCobroId): array
    {
        if ($facturas->isEmpty()) {
            return [];
        }
        $ids = $facturas->pluck('id')->all();
        if ($this->lote !== null && $excluirCobroId === null) {
            $lineas = collect();
            $pivotes = collect();
            foreach ($ids as $id) {
                if (isset($this->lote['lineas'][$id])) {
                    $lineas[$id] = collect([(object) ['total' => $this->lote['lineas'][$id]]]);
                }
                $pivotes = $pivotes->concat(collect($this->lote['pivotes'][$id] ?? []));
            }
        } else {
            $lineas = FacturaInternaDetalle::query()
                ->whereIn('factura_interna_id', $ids)
                ->where('descripcion', 'Saldo a favor aplicado')
                ->get()
                ->groupBy('factura_interna_id');
            $pivotes = DB::table('cobro_factura_interna as p')
                ->join('cobros as c', 'c.id', '=', 'p.cobro_id')
                ->whereIn('p.factura_interna_id', $ids)
                ->when($excluirCobroId, fn ($q) => $q->where('p.cobro_id', '!=', $excluirCobroId))
                ->get(['p.factura_interna_id', 'p.monto', 'c.fecha_pago', 'c.id as cobro_id']);
        }

        $pool = $this->excesosCliente($clienteId, $excluirCobroId);
        $resultado = [];
        foreach ($facturas as $factura) {
            $saldoLinea = round((float) ($lineas->get($factura->id)?->sum(fn ($d) => abs((float) $d->total)) ?? 0), 2);
            $objetivo = round((float) $factura->total + $saldoLinea, 2);
            $eventos = [];
            foreach ($pivotes->where('factura_interna_id', $factura->id) as $fila) {
                $fecha = Carbon::parse($fila->fecha_pago);
                if ($limite && $fecha->gt($limite)) {
                    continue;
                }
                $eventos[] = ['fecha' => $fecha, 'monto' => (float) $fila->monto];
            }
            usort($eventos, fn ($a, $b) => $a['fecha']->getTimestamp() <=> $b['fecha']->getTimestamp());
            $acum = 0.0;
            $fechaCubrio = null;
            foreach ($eventos as $evento) {
                $acum += $evento['monto'];
                $fechaCubrio = $evento['fecha'];
                if ($acum + 0.05 >= $objetivo) {
                    break;
                }
            }
            if ($acum + 0.05 < $objetivo && $saldoLinea > 0.009) {
                $falta = $objetivo - $acum;
                foreach ($pool as $i => $exceso) {
                    if ($falta <= 0.009) {
                        break;
                    }
                    if ($limite && $exceso['fecha']->gt($limite)) {
                        continue;
                    }
                    $tomar = min($exceso['monto'], $falta);
                    $pool[$i]['monto'] = round($exceso['monto'] - $tomar, 2);
                    $falta = round($falta - $tomar, 2);
                    $acum += $tomar;
                    $fechaCubrio = $exceso['fecha'];
                }
            }
            $resultado[$factura->id] = [
                'cubierta' => $objetivo > 0.009 && $acum + 0.05 >= $objetivo,
                'fecha' => $fechaCubrio,
            ];
        }

        return $resultado;
    }

    /**
     * @return list<array{fecha: Carbon, monto: float}>
     */
    private function excesosCliente(int $clienteId, ?int $excluirCobroId): array
    {
        if ($this->lote !== null && $excluirCobroId === null) {
            return array_map(
                fn (array $exceso) => ['fecha' => $exceso['fecha'], 'monto' => $exceso['monto']],
                $this->lote['excesos'][$clienteId] ?? []
            );
        }
        $cobros = Cobro::query()
            ->where('cliente_id', $clienteId)
            ->when($excluirCobroId, fn ($q) => $q->where('id', '!=', $excluirCobroId))
            ->with('facturaInternas')
            ->orderBy('fecha_pago')
            ->get();
        $excesos = [];
        foreach ($cobros as $cobro) {
            $aplicado = (float) $cobro->facturaInternas->sum(fn ($f) => (float) ($f->pivot->monto ?? 0));
            $exceso = round((float) $cobro->monto - $aplicado, 2);
            if ($exceso > 0.009) {
                $excesos[] = [
                    'fecha' => Carbon::parse($cobro->fecha_pago),
                    'monto' => $exceso,
                ];
            }
        }

        return $excesos;
    }

    private function pagoAnticipado(SorteoCampana $campana, int $clienteId, ?int $excluirCobroId): bool
    {
        if ($this->lote !== null && $excluirCobroId === null) {
            if (isset($this->lote['cobro_en_ventana'][$clienteId])) {
                return true;
            }
            $saldo = (float) ($this->lote['saldos'][$clienteId] ?? 0);

            return $saldo > 0.009;
        }
        $inicio = $campana->periodo_uso_desde->copy()->timezone($campana->timezone)->startOfDay();
        $limite = $campana->limite();
        if (Cobro::query()
            ->where('cliente_id', $clienteId)
            ->when($excluirCobroId, fn ($q) => $q->where('id', '!=', $excluirCobroId))
            ->where('fecha_pago', '>=', $inicio->format('Y-m-d H:i:s'))
            ->where('fecha_pago', '<=', $limite->format('Y-m-d H:i:s'))
            ->exists()) {
            return true;
        }

        return $this->saldoAFavor($clienteId) > 0.009;
    }

    private function saldoAFavor(int $clienteId): float
    {
        if (! Schema::hasTable('servicios')) {
            return 0;
        }

        return (float) Servicio::query()->where('cliente_id', $clienteId)->sum('saldo_a_favor');
    }

    private function appATiempo(SorteoCampana $campana, Cliente $cliente): bool
    {
        if (! $campana->exige_app) {
            return true;
        }
        if ($this->lote !== null) {
            return isset($this->lote['app'][$cliente->cliente_id]);
        }

        return $this->clienteTieneApp($cliente);
    }

    /** Mismo criterio que el cupón extra: usuario del cliente activo y con push. */
    private function clienteTieneApp(Cliente $cliente): bool
    {
        if (! Schema::hasTable('users')) {
            return false;
        }

        return User::query()->conAppPush()->where('cliente_id', $cliente->cliente_id)->exists();
    }

    private function esPuntual(SorteoCampana $campana, FacturaInterna $factura, Carbon $fechaPago): bool
    {
        if (! $factura->fecha_vencimiento) {
            return false;
        }
        $pago = $fechaPago->copy()->timezone($campana->timezone)->startOfDay();
        $venc = $factura->fecha_vencimiento->copy()->timezone($campana->timezone)->startOfDay();
        if ($campana->criterio_puntualidad === 'dia_fijo') {
            $dia = max(1, min(28, (int) $campana->dia_puntualidad));
            $tope = Carbon::create($venc->year, $venc->month, $dia, 0, 0, 0, $campana->timezone)->startOfDay();

            return $pago->lte($tope);
        }

        return $pago->lte($venc);
    }

    private function mesQueFalta(SorteoCampana $campana, int $clienteId, bool $anticipado): ?string
    {
        $meses = $campana->meses_puntualidad_100 ?? [];
        $mesDelAnticipo = $anticipado ? $campana->limite()->format('Y-m') : null;
        foreach ($meses as $mes) {
            $mes = (string) $mes;
            if ($mes === $mesDelAnticipo) {
                continue;
            }
            if (! preg_match('/^(\d{4})-(\d{2})$/', $mes, $m)) {
                return $mes;
            }
            $inicio = Carbon::create((int) $m[1], (int) $m[2], 1, 0, 0, 0, $campana->timezone)->startOfMonth();
            $fin = $inicio->copy()->endOfMonth();
            $facturas = $this->facturasDelMes($clienteId, $mes, $inicio, $fin);
            if ($facturas->isEmpty()) {
                return $this->nombreMes($mes);
            }
            $cobertura = $this->cobertura($clienteId, $facturas, null, null);
            foreach ($facturas as $factura) {
                $fecha = $cobertura[$factura->id]['fecha'] ?? null;
                $cubierta = (bool) ($cobertura[$factura->id]['cubierta'] ?? false);
                if (! $cubierta || ! $fecha instanceof Carbon || ! $this->esPuntual($campana, $factura, $fecha)) {
                    return $this->nombreMes($mes);
                }
            }
        }

        return null;
    }

    /**
     * @return Collection<int, FacturaInterna>
     */
    private function facturasDelMes(int $clienteId, string $mes, Carbon $inicio, Carbon $fin): Collection
    {
        if ($this->lote !== null) {
            return $this->lote['meses'][$clienteId][$mes] ?? collect();
        }

        return FacturaInterna::query()
            ->where('cliente_id', $clienteId)
            ->where('tipo_factura', FacturaInterna::TIPO_SERVICIO)
            ->whereDate('fecha_vencimiento', '>=', $inicio->toDateString())
            ->whereDate('fecha_vencimiento', '<=', $fin->toDateString())
            ->get();
    }

    private function nombreMes(string $ym): string
    {
        $nombres = [1 => 'enero', 2 => 'febrero', 3 => 'marzo', 4 => 'abril', 5 => 'mayo', 6 => 'junio', 7 => 'julio', 8 => 'agosto', 9 => 'septiembre', 10 => 'octubre', 11 => 'noviembre', 12 => 'diciembre'];
        if (! preg_match('/^(\d{4})-(\d{2})$/', $ym, $m)) {
            return $ym;
        }

        return ($nombres[(int) $m[2]] ?? $ym).' '.$m[1];
    }

    /**
     * Participa quien tiene un cobro hasta el corte de la campaña y saldo pendiente 0.
     *
     * @return array{ok: bool, motivo: ?string}
     */
    private function participaPorPago(SorteoCampana $campana, int $clienteId, ?int $excluirCobroId): array
    {
        if ($this->saldoPendiente($clienteId) > 0.009) {
            return ['ok' => false, 'motivo' => 'con_saldo'];
        }
        if ($this->tienePago($campana, $clienteId, $excluirCobroId, true)) {
            return ['ok' => true, 'motivo' => null];
        }
        if ($this->tienePago($campana, $clienteId, $excluirCobroId, false)) {
            return ['ok' => false, 'motivo' => 'pago_tarde'];
        }

        return ['ok' => false, 'motivo' => 'sin_pago'];
    }

    private function saldoPendiente(int $clienteId): float
    {
        if ($this->lote !== null) {
            return (float) ($this->lote['deuda'][$clienteId] ?? 0);
        }

        return $this->deudaPorCliente([$clienteId])[$clienteId] ?? 0.0;
    }

    private function tienePago(SorteoCampana $campana, int $clienteId, ?int $excluirCobroId, bool $hastaLimite): bool
    {
        if ($this->lote !== null && $excluirCobroId === null) {
            $clave = $hastaLimite ? 'pago_antes' : 'pago_despues';

            return isset($this->lote[$clave][$clienteId]);
        }

        $limite = $campana->limite();
        $consulta = DB::table('cobros')->where('cliente_id', $clienteId);
        if ($excluirCobroId) {
            $consulta->where('id', '!=', $excluirCobroId);
        }
        foreach ($consulta->get(['fecha_pago']) as $cobro) {
            $fecha = Carbon::parse($cobro->fecha_pago)->timezone($campana->timezone);
            $enLimite = $fecha->lte($limite);
            if ($hastaLimite === $enLimite) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<int>  $clienteIds
     * @return array<int, float>
     */
    private function deudaPorCliente(array $clienteIds): array
    {
        if ($clienteIds === []) {
            return [];
        }
        $facturas = DB::table('factura_internas')
            ->whereIn('cliente_id', $clienteIds)
            ->where('tipo_factura', FacturaInterna::TIPO_SERVICIO)
            ->whereNotIn('estado', ['anulada', 'cancelada'])
            ->get(['id', 'cliente_id', 'total']);
        if ($facturas->isEmpty()) {
            return [];
        }
        $pagos = DB::table('cobro_factura_interna')
            ->whereIn('factura_interna_id', $facturas->pluck('id'))
            ->select('factura_interna_id', DB::raw('SUM(monto) as pagado'))
            ->groupBy('factura_interna_id')
            ->pluck('pagado', 'factura_interna_id');
        $notas = [];
        if (Schema::hasTable('factura_interna_notas_credito')) {
            $notas = DB::table('factura_interna_notas_credito')
                ->whereIn('factura_interna_id', $facturas->pluck('id'))
                ->select('factura_interna_id', DB::raw('SUM(monto) as nota'))
                ->groupBy('factura_interna_id')
                ->pluck('nota', 'factura_interna_id')
                ->all();
        }
        $deuda = [];
        foreach ($facturas as $factura) {
            $saldo = round((float) $factura->total - (float) ($pagos[$factura->id] ?? 0) - (float) ($notas[$factura->id] ?? 0), 2);
            if ($saldo > 0.009) {
                $cid = (int) $factura->cliente_id;
                $deuda[$cid] = ($deuda[$cid] ?? 0) + $saldo;
            }
        }

        return $deuda;
    }

    /**
     * @param  array{nivel: string, motivo: ?string, anticipado: bool, mes_faltante: ?string}  $clase
     */
    private function nombrePublico(Cliente $cliente): string
    {
        $partes = preg_split('/\s+/', trim($cliente->nombre.' '.$cliente->apellido)) ?: [];
        $partes = array_values(array_filter($partes, fn ($p) => $p !== ''));
        $primero = array_shift($partes) ?: 'Cliente';
        $primero = mb_convert_case(mb_strtolower($primero), MB_CASE_TITLE, 'UTF-8');
        $resto = array_map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)).'.', $partes);

        return trim($primero.' '.implode(' ', $resto));
    }

    private function digitosCedula(Cliente $cliente): ?string
    {
        $digitos = preg_replace('/\D/', '', (string) $cliente->cedula) ?? '';

        return strlen($digitos) >= 4 ? substr($digitos, -4) : null;
    }

    private function celularOculto(?string $telefono): ?string
    {
        if (! TelefonoParaguayHelper::esMovilParaguay($telefono)) {
            return null;
        }
        $local = TelefonoParaguayHelper::normalize($telefono) ?? '';
        if (strlen($local) < 7) {
            return null;
        }

        return substr($local, 0, 4).' *** '.substr($local, -3);
    }

    /**
     * @return array<string, mixed>
     */
    private function campanaPublica(SorteoCampana $campana): array
    {
        $limite = $campana->limite()->timezone($campana->timezone);
        $premios = $campana->categorias()->orderBy('orden')->get()->map(function (SorteoPremioCategoria $categoria) {
            $texto = $categoria->cupo.' ganadores: '.$categoria->nombre;
            if ($categoria->tieneDescuento()) {
                $texto .= ' ('.rtrim(rtrim(number_format((float) $categoria->porcentaje, 2, '.', ''), '0'), '.').'% en la factura de enero y febrero)';
            }

            return $texto;
        })->values()->all();

        return [
            'nombre' => $campana->nombre,
            'fecha_sorteo' => $campana->fecha_sorteo?->format('d/m/Y'),
            'pago_hasta' => $limite->format('d/m/Y H:i'),
            'reglas' => [
                'Participás si pagaste hasta el '.$limite->format('d/m/Y').' a las '.$limite->format('H:i').' y no tenés saldo pendiente.',
                'Con la app activa se generan dos cupones. Sin la app, uno.',
                'Si dejás tu celular, sumamos un cupón más.',
                'El sorteo es el '.($campana->fecha_sorteo?->format('d/m/Y') ?? 'día anunciado').'.',
            ],
            'premios' => $premios,
        ];
    }

    /**
     * @param  array{nivel: string, motivo: ?string, anticipado: bool, mes_faltante: ?string}  $clase
     */
    private function etiqueta(array $clase): string
    {
        if ($clase['nivel'] === 'no') {
            $base = match ($clase['motivo']) {
                'sin_app' => 'Sin app',
                'con_saldo' => 'Con saldo pendiente',
                'pago_tarde' => 'Pagó tarde',
                default => 'Sin pago',
            };
            if (! empty($clase['mes_faltante'])) {
                $base .= ' · Falta '.$clase['mes_faltante'].' para 100%';
            }

            return $base;
        }

        $base = match ($clase['nivel']) {
            '100' => $clase['anticipado'] ? 'Apto 100% · Pagó anticipado' : 'Apto 100%',
            'anticipado' => 'Pagó anticipado',
            '50' => 'Apto 50%',
            default => 'Apto',
        };
        if (! empty($clase['mes_faltante'])) {
            $base .= ' · Falta '.$clase['mes_faltante'].' para 100%';
        }

        return $base;
    }

    private function cobroQueCubre(SorteoCampana $campana, int $clienteId, ?int $excluirCobroId): ?int
    {
        $facturas = $this->facturasDelPeriodo($campana, $clienteId);
        if ($facturas->isEmpty()) {
            return null;
        }
        $id = DB::table('cobro_factura_interna as p')
            ->join('cobros as c', 'c.id', '=', 'p.cobro_id')
            ->whereIn('p.factura_interna_id', $facturas->pluck('id'))
            ->where('c.fecha_pago', '<=', $campana->limite()->format('Y-m-d H:i:s'))
            ->when($excluirCobroId, fn ($q) => $q->where('c.id', '!=', $excluirCobroId))
            ->orderByDesc('c.fecha_pago')
            ->value('c.id');

        return $id ? (int) $id : null;
    }

    /**
     * @return Collection<int, Cliente>
     */
    private function clientesDelPeriodo(SorteoCampana $campana): Collection
    {
        $ids = FacturaInterna::query()
            ->where('tipo_factura', FacturaInterna::TIPO_SERVICIO)
            ->whereDate('periodo_desde', '>=', $campana->periodo_uso_desde->toDateString())
            ->whereDate('periodo_desde', '<=', $campana->periodo_uso_hasta->toDateString())
            ->distinct()
            ->pluck('cliente_id');

        return Cliente::query()->whereIn('cliente_id', $ids)->orderBy('cliente_id')->get();
    }

    private function contarAnticiposEnEspera(SorteoCampana $campana): int
    {
        if (! Schema::hasTable('servicios')) {
            return 0;
        }
        $conFactura = FacturaInterna::query()
            ->where('tipo_factura', FacturaInterna::TIPO_SERVICIO)
            ->whereDate('periodo_desde', '>=', $campana->periodo_uso_desde->toDateString())
            ->whereDate('periodo_desde', '<=', $campana->periodo_uso_hasta->toDateString())
            ->pluck('cliente_id');
        $conSaldo = Servicio::query()->where('saldo_a_favor', '>', 0)->pluck('cliente_id');
        $ids = $conSaldo->diff($conFactura)->unique()->values();
        if ($ids->isEmpty()) {
            return 0;
        }

        return (int) Cobro::query()
            ->whereIn('cliente_id', $ids)
            ->where('fecha_pago', '<=', $campana->limite()->format('Y-m-d H:i:s'))
            ->distinct()
            ->count('cliente_id');
    }

    private function contarBienvenidaNueva(): int
    {
        $ajuste = PromoAjuste::bienvenida();
        if (! $ajuste || ! $ajuste->activo) {
            return 0;
        }
        $ya = Cupon::query()->where('tipo', Cupon::TIPO_BIENVENIDA)->pluck('cliente_id');

        return (int) Cliente::query()->where('app_activa', true)->whereNotIn('cliente_id', $ya)->count();
    }

    /**
     * @return list<int>
     */
    private function idsListado(SorteoCampana $campana, ?string $buscar): array
    {
        $q = Cliente::query()->where('estado', 'activo');
        $buscar = trim((string) $buscar);
        if ($buscar !== '') {
            $q->where(function ($query) use ($buscar) {
                $query->where('nombre', 'like', '%'.$buscar.'%')
                    ->orWhere('apellido', 'like', '%'.$buscar.'%')
                    ->orWhere('cedula', 'like', '%'.$buscar.'%');
                if (ctype_digit($buscar)) {
                    $query->orWhere('cliente_id', (int) $buscar);
                }
            });
        }

        return $q->pluck('cliente_id')->map(fn ($id) => (int) $id)->all();
    }

    /**
     * @return Collection<int, Cliente>
     */
    private function clientesActivos(): Collection
    {
        return Cliente::query()->where('estado', 'activo')->orderBy('cliente_id')->get();
    }

    /**
     * @param  list<int>  $clienteIds
     */
    private function prepararLote(SorteoCampana $campana, array $clienteIds): void
    {
        $vacio = [
            'app' => [],
            'periodo' => [],
            'meses' => [],
            'lineas' => [],
            'pivotes' => [],
            'excesos' => [],
            'saldos' => [],
            'cobro_en_ventana' => [],
            'deuda' => [],
            'pago_antes' => [],
            'pago_despues' => [],
        ];
        if ($clienteIds === []) {
            $this->lote = $vacio;

            return;
        }

        $app = [];
        if (Schema::hasTable('users')) {
            foreach (User::query()->conAppPush()->whereIn('cliente_id', $clienteIds)->pluck('cliente_id') as $id) {
                $app[(int) $id] = true;
            }
        }

        $meses = [];
        foreach ($campana->meses_puntualidad_100 ?? [] as $mes) {
            if (preg_match('/^(\d{4})-(\d{2})$/', (string) $mes, $m)) {
                $meses[(string) $mes] = Carbon::create((int) $m[1], (int) $m[2], 1, 0, 0, 0, $campana->timezone);
            }
        }
        $facturas = FacturaInterna::query()
            ->whereIn('cliente_id', $clienteIds)
            ->where('tipo_factura', FacturaInterna::TIPO_SERVICIO)
            ->where(function ($q) use ($campana, $meses) {
                $q->where(function ($p) use ($campana) {
                    $p->whereDate('periodo_desde', '>=', $campana->periodo_uso_desde->toDateString())
                        ->whereDate('periodo_desde', '<=', $campana->periodo_uso_hasta->toDateString());
                });
                foreach ($meses as $inicio) {
                    $q->orWhere(function ($m) use ($inicio) {
                        $m->whereDate('fecha_vencimiento', '>=', $inicio->toDateString())
                            ->whereDate('fecha_vencimiento', '<=', $inicio->copy()->endOfMonth()->toDateString());
                    });
                }
            })
            ->get();

        $periodo = [];
        $porMes = [];
        $desde = $campana->periodo_uso_desde->toDateString();
        $hasta = $campana->periodo_uso_hasta->toDateString();
        foreach ($facturas as $factura) {
            $cid = (int) $factura->cliente_id;
            $periodoDesde = $factura->periodo_desde?->toDateString();
            if ($periodoDesde && $periodoDesde >= $desde && $periodoDesde <= $hasta) {
                $periodo[$cid] ??= collect();
                $periodo[$cid]->push($factura);
            }
            if ($factura->fecha_vencimiento) {
                $clave = $factura->fecha_vencimiento->timezone($campana->timezone)->format('Y-m');
                if (isset($meses[$clave])) {
                    $porMes[$cid][$clave] ??= collect();
                    $porMes[$cid][$clave]->push($factura);
                }
            }
        }

        $facturaIds = $facturas->pluck('id')->all();
        $lineas = [];
        $pivotes = [];
        if ($facturaIds !== []) {
            $sumas = FacturaInternaDetalle::query()
                ->whereIn('factura_interna_id', $facturaIds)
                ->where('descripcion', 'Saldo a favor aplicado')
                ->get(['factura_interna_id', 'total']);
            foreach ($sumas as $detalle) {
                $lineas[$detalle->factura_interna_id] = ($lineas[$detalle->factura_interna_id] ?? 0) + abs((float) $detalle->total);
            }
            $filas = DB::table('cobro_factura_interna as p')
                ->join('cobros as c', 'c.id', '=', 'p.cobro_id')
                ->whereIn('p.factura_interna_id', $facturaIds)
                ->get(['p.factura_interna_id', 'p.monto', 'c.fecha_pago', 'c.id as cobro_id']);
            foreach ($filas as $fila) {
                $pivotes[$fila->factura_interna_id][] = $fila;
            }
        }

        $cobros = DB::table('cobros')->whereIn('cliente_id', $clienteIds)->get(['id', 'cliente_id', 'monto', 'fecha_pago']);
        $aplicado = $cobros->isEmpty()
            ? collect()
            : DB::table('cobro_factura_interna')
                ->whereIn('cobro_id', $cobros->pluck('id'))
                ->select('cobro_id', DB::raw('SUM(monto) as aplicado'))
                ->groupBy('cobro_id')
                ->pluck('aplicado', 'cobro_id');
        $inicio = $campana->periodo_uso_desde->copy()->timezone($campana->timezone)->startOfDay();
        $limite = $campana->limite();
        $excesos = [];
        $enVentana = [];
        $pagoAntes = [];
        $pagoDespues = [];
        foreach ($cobros as $cobro) {
            $cid = (int) $cobro->cliente_id;
            $fecha = Carbon::parse($cobro->fecha_pago);
            $monto = round((float) $cobro->monto - (float) ($aplicado[$cobro->id] ?? 0), 2);
            if ($monto > 0.009) {
                $excesos[$cid][] = ['fecha' => $fecha, 'monto' => $monto];
            }
            $local = $fecha->copy()->timezone($campana->timezone);
            if ($local->lte($limite)) {
                $pagoAntes[$cid] = true;
            } else {
                $pagoDespues[$cid] = true;
            }
            if ($local->gte($inicio) && $local->lte($limite)) {
                $enVentana[$cid] = true;
            }
        }

        $saldos = [];
        if (Schema::hasTable('servicios')) {
            $filasSaldo = DB::table('servicios')
                ->whereIn('cliente_id', $clienteIds)
                ->select('cliente_id', DB::raw('SUM(saldo_a_favor) as saldo'))
                ->groupBy('cliente_id')
                ->get();
            foreach ($filasSaldo as $fila) {
                $saldos[(int) $fila->cliente_id] = (float) $fila->saldo;
            }
        }

        $this->lote = [
            'app' => $app,
            'periodo' => $periodo,
            'meses' => $porMes,
            'lineas' => $lineas,
            'pivotes' => $pivotes,
            'excesos' => $excesos,
            'saldos' => $saldos,
            'cobro_en_ventana' => $enVentana,
            'deuda' => $this->deudaPorCliente($clienteIds),
            'pago_antes' => $pagoAntes,
            'pago_despues' => $pagoDespues,
        ];
    }

    private function pasaFiltro(string $filtro, string $nivel, bool $participando, bool $resenaPendiente): bool
    {
        return match ($filtro) {
            '100' => $nivel === '100',
            '50' => in_array($nivel, ['100', '50', 'anticipado'], true),
            'aptos' => $nivel !== 'no',
            'no' => $nivel === 'no',
            'participando' => $participando,
            'resena' => $resenaPendiente,
            default => true,
        };
    }

    /**
     * @param  array<string, mixed>  $detalles
     */
    private function auditar(?int $usuarioId, string $tabla, string $accion, int $registroId, array $detalles): void
    {
        if (! Schema::hasTable('auditoria')) {
            return;
        }
        Auditoria::create([
            'usuario_id' => $usuarioId,
            'tabla' => $tabla,
            'accion' => $accion,
            'registro_id' => $registroId,
            'detalles' => json_encode($detalles),
            'created_at' => now(),
        ]);
    }
}
