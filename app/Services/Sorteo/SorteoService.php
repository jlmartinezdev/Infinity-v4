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
        $this->sincronizarTickets($campana, $cliente, $usuarioId, null, (int) $cobro->id);
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
        if ($nueva) {
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
        $app = $this->appATiempo($campana, $cliente);
        $anticipado = false;
        if ($facturasPeriodo->isEmpty()) {
            $anticipado = $this->pagoAnticipado($campana, $cliente->cliente_id, $excluirCobroId);
            $cubiertas = $anticipado;
            $cubiertasAlgunaVez = $anticipado;
            $puntualParticipacion = $anticipado;
            $cobertura = [];
        } else {
            $cobertura = $this->cobertura($cliente->cliente_id, $facturasPeriodo, $campana->limite(), $excluirCobroId);
            $cubiertas = $facturasPeriodo->every(
                fn (FacturaInterna $f) => (bool) ($cobertura[$f->id]['cubierta'] ?? false)
            );
            $coberturaSinTope = $this->cobertura($cliente->cliente_id, $facturasPeriodo, null, $excluirCobroId);
            $cubiertasAlgunaVez = $facturasPeriodo->every(
                fn (FacturaInterna $f) => (bool) ($coberturaSinTope[$f->id]['cubierta'] ?? false)
            );
            $puntualParticipacion = false;
        }

        $motivo = null;
        $apto = $app && $cubiertas;
        if (! $apto) {
            if ($campana->exige_app && ! $app) {
                $motivo = 'sin_app';
            } elseif (! $cubiertasAlgunaVez) {
                $motivo = 'sin_pago';
            } else {
                $motivo = 'pago_tarde';
            }
        }

        if (! $anticipado) {
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
        $totales = ['100' => 0, '50' => 0, 'aptos' => 0, 'no' => 0];
        foreach ($clientes as $cliente) {
            $clase = $this->clasificar($campana, $cliente);
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
            if (! $this->pasaFiltro($filtro, $clase['nivel'])) {
                continue;
            }
            $delCliente = $tickets->get($cliente->cliente_id, collect());
            $pago = $delCliente->first(fn (SorteoTicket $t) => $t->origen === SorteoTicket::ORIGEN_PAGO && $t->estado === SorteoTicket::ACTIVO);
            $extra = $delCliente->first(fn (SorteoTicket $t) => $t->origen === SorteoTicket::ORIGEN_RESENA && $t->estado === SorteoTicket::ACTIVO);
            $resena = $resenas->get($cliente->cliente_id);
            $filas[] = [
                'cliente_id' => $cliente->cliente_id,
                'nombre' => trim($cliente->nombre.' '.$cliente->apellido),
                'cedula' => $cliente->cedula,
                'telefono' => $resena->telefono ?? $cliente->telefono,
                'nivel' => $clase['nivel'],
                'motivo' => $clase['motivo'],
                'etiqueta' => $clase['etiqueta'],
                'mes_faltante' => $clase['mes_faltante'],
                'ticket' => $pago?->numeroFormateado(),
                'extra' => $extra?->numeroFormateado(),
                'resena' => $resena !== null,
            ];
        }
        $this->lote = null;

        return ['filas' => $filas, 'totales' => $totales];
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
            'extra' => $extra?->numeroFormateado(),
            'nombre' => Str::ascii(trim(($cliente->nombre ?? '').' '.($cliente->apellido ?? ''))),
            'cedula' => Str::ascii((string) ($cliente->cedula ?? '')),
            'fecha' => $cobro->fecha_pago?->timezone($campana->timezone)->format('d/m/Y H:i'),
            'telefono' => $resena?->telefono,
        ];
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
        $tieneResena = SorteoResena::query()
            ->where('campana_id', $campana->id)
            ->where('cliente_id', $cliente->cliente_id)
            ->exists();
        $this->asegurarTicket($campana, $cliente->cliente_id, SorteoTicket::ORIGEN_RESENA, $apto && $tieneResena, $usuarioId, null);
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
     * @param  array{nivel: string, motivo: ?string, anticipado: bool, mes_faltante: ?string}  $clase
     */
    private function etiqueta(array $clase): string
    {
        if ($clase['nivel'] === 'no') {
            $base = match ($clase['motivo']) {
                'sin_app' => 'Sin app',
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
        foreach ($cobros as $cobro) {
            $cid = (int) $cobro->cliente_id;
            $fecha = Carbon::parse($cobro->fecha_pago);
            $monto = round((float) $cobro->monto - (float) ($aplicado[$cobro->id] ?? 0), 2);
            if ($monto > 0.009) {
                $excesos[$cid][] = ['fecha' => $fecha, 'monto' => $monto];
            }
            $local = $fecha->copy()->timezone($campana->timezone);
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
        ];
    }

    private function pasaFiltro(string $filtro, string $nivel): bool
    {
        return match ($filtro) {
            '100' => $nivel === '100',
            '50' => in_array($nivel, ['100', '50', 'anticipado'], true),
            'aptos' => $nivel !== 'no',
            'no' => $nivel === 'no',
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
