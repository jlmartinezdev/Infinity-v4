<?php

namespace App\Services\Loyalty;

use App\Models\Canje;
use App\Models\Cliente;
use App\Models\ClientePuntos;
use App\Models\Cobro;
use App\Models\FacturaInterna;
use App\Models\LoyaltyRegla;
use App\Models\Premio;
use App\Models\PuntosLote;
use App\Models\PuntosMovimiento;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class PuntosService
{
    public function limiteCanjesMes(): int
    {
        return max(1, (int) config('loyalty.limite_canjes_mes', self::LIMITE_CANJES_MES));
    }

    /** @deprecated usar limiteCanjesMes() */
    public const LIMITE_CANJES_MES = 1;

    public function saldo(int $clienteId): int
    {
        return (int) (ClientePuntos::query()->where('cliente_id', $clienteId)->value('saldo') ?? 0);
    }

    public function canjesDelMes(int $clienteId): int
    {
        return Canje::delMesActual($clienteId)->count();
    }

    public function puedeCanjear(int $clienteId): bool
    {
        return $this->canjesDelMes($clienteId) < $this->limiteCanjesMes();
    }

    public function resumenPortal(int $clienteId): array
    {
        $canjesMes = $this->canjesDelMes($clienteId);
        $saldo = $this->saldo($clienteId);
        $limite = $this->limiteCanjesMes();

        $data = [
            'saldo' => $saldo,
            'puede_canjear' => $canjesMes < $limite,
            'canjes_mes' => $canjesMes,
            'limite_mensual' => $limite,
        ];

        $venc = $this->infoVencimientoPortal($clienteId);
        if ($venc !== null) {
            $data = array_merge($data, $venc);
        }

        $bono = $this->infoBonoBienvenidaPortal($clienteId);
        if ($bono !== null) {
            $data = array_merge($data, $bono);
        }

        $siguiente = $this->siguientePremioPortal($saldo);
        if ($siguiente !== null) {
            $data = array_merge($data, $siguiente);
        }

        return $data;
    }

    /**
     * @return array{puntos_por_vencer: int, dias_al_vencimiento: int, proximo_vencimiento: string}|null
     */
    public function infoVencimientoPortal(int $clienteId): ?array
    {
        $lote = PuntosLote::query()
            ->where('cliente_id', $clienteId)
            ->vigentes()
            ->whereNotNull('vence_at')
            ->ordenFifo()
            ->first();

        if (! $lote || ! $lote->vence_at) {
            return null;
        }

        $vence = $lote->vence_at->copy()->startOfDay();
        $hoy = now()->startOfDay();
        if ($vence->lt($hoy)) {
            return null;
        }
        $dias = (int) $hoy->diffInDays($vence);

        $ventana = (int) config('loyalty.ventana_alerta_vencimiento_dias', 30);
        if ($ventana > 0 && $dias > $ventana) {
            return null;
        }

        $puntos = (int) PuntosLote::query()
            ->where('cliente_id', $clienteId)
            ->conSaldo()
            ->whereDate('vence_at', $vence->toDateString())
            ->sum('puntos_restantes');

        if ($puntos <= 0) {
            return null;
        }

        return [
            'puntos_por_vencer' => $puntos,
            'dias_al_vencimiento' => $dias,
            'proximo_vencimiento' => $vence->toDateString(),
        ];
    }

    /**
     * @return array{bono_bienvenida_activo: bool, bono_bienvenida_vence_en_dias: int|null}|null
     */
    public function infoBonoBienvenidaPortal(int $clienteId): ?array
    {
        $lote = PuntosLote::query()
            ->where('cliente_id', $clienteId)
            ->where('origen', PuntosLote::ORIGEN_BIENVENIDA)
            ->vigentes()
            ->ordenFifo()
            ->first();

        if (! $lote) {
            return null;
        }

        $dias = null;
        if ($lote->vence_at) {
            $vence = $lote->vence_at->copy()->startOfDay();
            $hoy = now()->startOfDay();
            if ($vence->lt($hoy)) {
                return null;
            }
            $dias = (int) $hoy->diffInDays($vence);
        }

        return [
            'bono_bienvenida_activo' => true,
            'bono_bienvenida_vence_en_dias' => $dias,
        ];
    }

    /**
     * @return array{siguiente_premio_puntos: int, siguiente_premio_nombre: string}|null
     */
    public function siguientePremioPortal(int $saldo): ?array
    {
        $premio = Premio::disponiblesPortal()
            ->where('puntos_requeridos', '>', $saldo)
            ->orderBy('puntos_requeridos')
            ->orderBy('orden')
            ->first();

        if (! $premio) {
            return null;
        }

        return [
            'siguiente_premio_puntos' => (int) $premio->puntos_requeridos,
            'siguiente_premio_nombre' => (string) $premio->nombre,
        ];
    }

    /**
     * @param  array{referencia_tipo?: string, referencia_id?: int, meta?: array, created_by?: int|null, vence_at?: Carbon|string|null, dias_vencimiento?: int|null, origen_lote?: string}  $opts
     */
    public function acreditar(int $clienteId, int $puntos, string $concepto, string $tipo = 'credito', array $opts = []): PuntosMovimiento
    {
        if ($puntos <= 0) {
            throw new \InvalidArgumentException('Los puntos a acreditar deben ser mayores a 0.');
        }

        return $this->mover($clienteId, $puntos, $concepto, $tipo, $opts);
    }

    /**
     * @param  array{referencia_tipo?: string, referencia_id?: int, meta?: array, created_by?: int|null}  $opts
     */
    public function debitar(int $clienteId, int $puntos, string $concepto, string $tipo = 'debito', array $opts = []): PuntosMovimiento
    {
        if ($puntos <= 0) {
            throw new \InvalidArgumentException('Los puntos a debitar deben ser mayores a 0.');
        }

        return $this->mover($clienteId, -$puntos, $concepto, $tipo, $opts);
    }

    /**
     * Aplica una regla activa por código (si existe).
     */
    public function aplicarRegla(int $clienteId, string $codigo, array $opts = []): ?PuntosMovimiento
    {
        $regla = LoyaltyRegla::activas()->where('codigo', $codigo)->first();
        if (! $regla || $regla->puntos == 0) {
            return null;
        }

        $opts['referencia_tipo'] = $opts['referencia_tipo'] ?? 'loyalty_regla';
        $opts['referencia_id'] = $opts['referencia_id'] ?? $regla->id;
        $opts['meta'] = array_merge($opts['meta'] ?? [], ['regla_codigo' => $regla->codigo]);

        if ($regla->evento === LoyaltyRegla::EVENTO_BIENVENIDA) {
            $opts['origen_lote'] = PuntosLote::ORIGEN_BIENVENIDA;
            $opts['dias_vencimiento'] = $opts['dias_vencimiento']
                ?? (int) config('loyalty.dias_vencimiento_bienvenida', 30);
        } else {
            $opts['origen_lote'] = $opts['origen_lote'] ?? PuntosLote::ORIGEN_REGLA;
        }

        if ($regla->puntos > 0) {
            return $this->acreditar($clienteId, $regla->puntos, $regla->nombre, 'regla', $opts);
        }

        return $this->debitar($clienteId, abs($regla->puntos), $regla->nombre, 'regla', $opts);
    }

    /**
     * Aplica todas las reglas activas de un evento (p. ej. pago_recibido).
     *
     * @return list<PuntosMovimiento>
     */
    public function aplicarEvento(int $clienteId, string $evento, array $opts = []): array
    {
        $out = [];
        $reglas = LoyaltyRegla::activas()->where('evento', $evento)->get();
        foreach ($reglas as $regla) {
            $mov = $this->aplicarRegla($clienteId, $regla->codigo, $opts);
            if ($mov) {
                $out[] = $mov;
            }
        }

        return $out;
    }

    public function yaRecibioBienvenida(int $clienteId): bool
    {
        $reglaIds = LoyaltyRegla::query()
            ->where('evento', LoyaltyRegla::EVENTO_BIENVENIDA)
            ->pluck('id');

        $q = PuntosMovimiento::query()->where('cliente_id', $clienteId);

        return $q->where(function ($inner) use ($reglaIds) {
            if ($reglaIds->isNotEmpty()) {
                $inner->where(function ($r) use ($reglaIds) {
                    $r->where('referencia_tipo', 'loyalty_regla')
                        ->whereIn('referencia_id', $reglaIds);
                });
            }
            $inner->orWhere('meta->evento', LoyaltyRegla::EVENTO_BIENVENIDA)
                ->orWhere('meta->regla_codigo', 'bienvenida_app');
        })->exists();
    }

    /**
     * Acredita reglas de bienvenida una sola vez por cliente.
     *
     * @return list<PuntosMovimiento>
     */
    public function aplicarBienvenidaUnaVez(int $clienteId, array $opts = []): array
    {
        if ($this->yaRecibioBienvenida($clienteId)) {
            return [];
        }

        $opts['meta'] = array_merge($opts['meta'] ?? [], [
            'evento' => LoyaltyRegla::EVENTO_BIENVENIDA,
        ]);
        $opts['origen_lote'] = PuntosLote::ORIGEN_BIENVENIDA;
        $opts['dias_vencimiento'] = $opts['dias_vencimiento']
            ?? (int) config('loyalty.dias_vencimiento_bienvenida', 30);

        return $this->aplicarEvento($clienteId, LoyaltyRegla::EVENTO_BIENVENIDA, $opts);
    }

    /**
     * Puntos por pago solo si el acceso app fue otorgado (solicitud/alta),
     * la app está activa (primer login) y el usuario portal está activo.
     * Evita acreditar a clientes que solo tenían login legacy con CI.
     */
    public function clienteElegiblePuntosPorApp(int $clienteId): bool
    {
        $cliente = Cliente::query()
            ->with(['usuarioPortal:usuario_id,cliente_id,estado'])
            ->find($clienteId);

        if (! $cliente || ! $cliente->app_activa || ! $cliente->fecha_otorgamiento) {
            return false;
        }

        $user = $cliente->usuarioPortal;

        return $user !== null && $user->estado === 'activo';
    }

    /**
     * Puntos por pago: solo facturas tipo «servicio», según día del mes de fecha_pago.
     * Exclusivo para clientes con app activa.
     *
     * @return list<PuntosMovimiento>
     */
    public function aplicarPuntosPorCobro(Cobro $cobro, array $opts = []): array
    {
        $cobro->loadMissing(['facturaInternas', 'facturaInterna']);

        if (! $this->cobroIncluyeFacturaServicio($cobro)) {
            return [];
        }

        if (! $this->clienteElegiblePuntosPorApp((int) $cobro->cliente_id)) {
            return [];
        }

        if ($this->yaAcreditoCobro((int) $cobro->id)) {
            return [];
        }

        $fecha = $cobro->fecha_pago
            ? Carbon::parse($cobro->fecha_pago)
            : now();
        $dia = (int) $fecha->day;

        $out = [];
        $reglas = LoyaltyRegla::activas()
            ->where('evento', LoyaltyRegla::EVENTO_PAGO)
            ->get();

        foreach ($reglas as $regla) {
            $puntos = $regla->puntosParaDiaMes($dia);
            if ($puntos <= 0) {
                continue;
            }

            $concepto = $regla->usaPuntosPorDia()
                ? sprintf('%s (día %d)', $regla->nombre, $dia)
                : $regla->nombre;

            $mov = $this->acreditar(
                (int) $cobro->cliente_id,
                $puntos,
                $concepto,
                'regla',
                array_merge($opts, [
                    'referencia_tipo' => 'cobro',
                    'referencia_id' => $cobro->id,
                    'origen_lote' => PuntosLote::ORIGEN_REGLA,
                    'meta' => array_merge($opts['meta'] ?? [], [
                        'regla_codigo' => $regla->codigo,
                        'regla_id' => $regla->id,
                        'evento' => LoyaltyRegla::EVENTO_PAGO,
                        'dia_mes' => $dia,
                        'solo_factura_servicio' => true,
                        'monto' => (float) $cobro->monto,
                    ]),
                ])
            );
            $out[] = $mov;
        }

        return $out;
    }

    public function cobroIncluyeFacturaServicio(Cobro $cobro): bool
    {
        $facturas = $cobro->facturaInternas;
        if ($facturas && $facturas->isNotEmpty()) {
            return $facturas->contains(
                fn ($f) => ($f->tipo_factura ?? FacturaInterna::TIPO_SERVICIO) === FacturaInterna::TIPO_SERVICIO
            );
        }

        $legacy = $cobro->facturaInterna;
        if ($legacy) {
            return ($legacy->tipo_factura ?? FacturaInterna::TIPO_SERVICIO) === FacturaInterna::TIPO_SERVICIO;
        }

        return false;
    }

    public function yaAcreditoCobro(int $cobroId): bool
    {
        return PuntosMovimiento::query()
            ->where('referencia_tipo', 'cobro')
            ->where('referencia_id', $cobroId)
            ->exists();
    }

    /**
     * Vence lotes vencidos (FIFO): descuenta saldo y deja puntos_restantes = 0.
     *
     * @return array{lotes: int, puntos: int, clientes: int}
     */
    public function expirarLotesVencidos(?int $clienteId = null): array
    {
        $lotes = 0;
        $puntos = 0;
        $clientes = [];

        $query = PuntosLote::query()
            ->conSaldo()
            ->whereNotNull('vence_at')
            ->where('vence_at', '<=', now())
            ->orderBy('id');

        if ($clienteId !== null) {
            $query->where('cliente_id', $clienteId);
        }

        $query->chunkById(100, function ($chunk) use (&$lotes, &$puntos, &$clientes) {
            foreach ($chunk as $lote) {
                $pts = (int) $lote->puntos_restantes;
                if ($pts <= 0) {
                    continue;
                }

                DB::transaction(function () use ($lote, $pts, &$lotes, &$puntos, &$clientes) {
                    $locked = PuntosLote::query()->lockForUpdate()->find($lote->id);
                    if (! $locked || (int) $locked->puntos_restantes <= 0) {
                        return;
                    }
                    if (! $locked->vence_at || $locked->vence_at->isFuture()) {
                        return;
                    }

                    $restantes = (int) $locked->puntos_restantes;
                    $cuenta = ClientePuntos::query()->firstOrCreate(
                        ['cliente_id' => $locked->cliente_id],
                        ['saldo' => 0]
                    );
                    $cuenta = ClientePuntos::query()->where('id', $cuenta->id)->lockForUpdate()->firstOrFail();

                    $nuevo = max(0, (int) $cuenta->saldo - $restantes);
                    $cuenta->saldo = $nuevo;
                    $cuenta->save();

                    $mov = PuntosMovimiento::create([
                        'cliente_id' => $locked->cliente_id,
                        'puntos' => -$restantes,
                        'saldo_despues' => $nuevo,
                        'tipo' => 'vencimiento',
                        'concepto' => 'Vencimiento de puntos',
                        'referencia_tipo' => 'puntos_lote',
                        'referencia_id' => $locked->id,
                        'meta' => [
                            'lote_id' => $locked->id,
                            'vence_at' => optional($locked->vence_at)->toIso8601String(),
                            'origen_lote' => $locked->origen,
                        ],
                    ]);

                    $locked->puntos_restantes = 0;
                    $meta = $locked->meta ?? [];
                    $meta['expirado_por_movimiento_id'] = $mov->id;
                    $locked->meta = $meta;
                    $locked->save();

                    $lotes++;
                    $puntos += $restantes;
                    $clientes[$locked->cliente_id] = true;
                });
            }
        });

        return [
            'lotes' => $lotes,
            'puntos' => $puntos,
            'clientes' => count($clientes),
        ];
    }

    /**
     * Elimina un movimiento y revierte su efecto en el saldo actual.
     */
    public function eliminarMovimiento(PuntosMovimiento $movimiento): void
    {
        DB::transaction(function () use ($movimiento) {
            $cuenta = ClientePuntos::query()->firstOrCreate(
                ['cliente_id' => $movimiento->cliente_id],
                ['saldo' => 0]
            );
            $cuenta = ClientePuntos::query()->where('id', $cuenta->id)->lockForUpdate()->firstOrFail();

            $nuevo = (int) $cuenta->saldo - (int) $movimiento->puntos;
            if ($nuevo < 0) {
                throw new \InvalidArgumentException(
                    'No se puede eliminar: el saldo quedaría negativo. Ajustá el saldo antes.'
                );
            }

            $delta = (int) $movimiento->puntos;
            if ($delta > 0) {
                $lote = PuntosLote::query()
                    ->where('puntos_movimiento_id', $movimiento->id)
                    ->lockForUpdate()
                    ->first();
                if ($lote) {
                    if ((int) $lote->puntos_restantes < $delta) {
                        throw new \InvalidArgumentException(
                            'No se puede eliminar: parte de esos puntos ya se usaron o vencieron.'
                        );
                    }
                    $lote->delete();
                }
            } elseif ($delta < 0) {
                // Débito eliminado: devolver puntos al lote más reciente del cliente (mejor esfuerzo).
                $devolver = abs($delta);
                $lote = PuntosLote::query()
                    ->where('cliente_id', $movimiento->cliente_id)
                    ->orderByDesc('id')
                    ->lockForUpdate()
                    ->first();
                if ($lote) {
                    $lote->puntos_restantes = (int) $lote->puntos_restantes + $devolver;
                    $lote->save();
                } else {
                    PuntosLote::create([
                        'cliente_id' => $movimiento->cliente_id,
                        'puntos_movimiento_id' => null,
                        'puntos_iniciales' => $devolver,
                        'puntos_restantes' => $devolver,
                        'vence_at' => null,
                        'origen' => PuntosLote::ORIGEN_AJUSTE,
                        'meta' => ['restaurado_por_eliminar_movimiento' => $movimiento->id],
                    ]);
                }
            }

            $cuenta->saldo = $nuevo;
            $cuenta->save();
            $movimiento->delete();
        });
    }

    /**
     * @param  array{referencia_tipo?: string, referencia_id?: int, meta?: array, created_by?: int|null, vence_at?: Carbon|string|null, dias_vencimiento?: int|null, origen_lote?: string}  $opts
     */
    private function mover(int $clienteId, int $delta, string $concepto, string $tipo, array $opts = []): PuntosMovimiento
    {
        return DB::transaction(function () use ($clienteId, $delta, $concepto, $tipo, $opts) {
            $cuenta = ClientePuntos::query()->firstOrCreate(
                ['cliente_id' => $clienteId],
                ['saldo' => 0]
            );

            $cuenta = ClientePuntos::query()->where('id', $cuenta->id)->lockForUpdate()->firstOrFail();
            $nuevo = (int) $cuenta->saldo + $delta;
            if ($nuevo < 0) {
                throw new \InvalidArgumentException('Saldo de puntos insuficiente.');
            }

            $cuenta->saldo = $nuevo;
            $cuenta->save();

            $mov = PuntosMovimiento::create([
                'cliente_id' => $clienteId,
                'puntos' => $delta,
                'saldo_despues' => $nuevo,
                'tipo' => $tipo,
                'concepto' => $concepto,
                'referencia_tipo' => $opts['referencia_tipo'] ?? null,
                'referencia_id' => $opts['referencia_id'] ?? null,
                'meta' => $opts['meta'] ?? null,
                'created_by' => $opts['created_by'] ?? null,
            ]);

            if ($delta > 0) {
                $this->crearLote($clienteId, $mov, $delta, $tipo, $opts);
            } elseif ($delta < 0 && $tipo !== 'vencimiento') {
                $this->consumirLotesFifo($clienteId, abs($delta));
            }

            return $mov;
        });
    }

    /**
     * @param  array{vence_at?: Carbon|string|null, dias_vencimiento?: int|null, origen_lote?: string, meta?: array}  $opts
     */
    private function crearLote(int $clienteId, PuntosMovimiento $mov, int $puntos, string $tipo, array $opts): void
    {
        $origen = $opts['origen_lote']
            ?? match ($tipo) {
                'regla' => (($opts['meta']['evento'] ?? null) === LoyaltyRegla::EVENTO_BIENVENIDA)
                    ? PuntosLote::ORIGEN_BIENVENIDA
                    : PuntosLote::ORIGEN_REGLA,
                'ajuste' => PuntosLote::ORIGEN_AJUSTE,
                'reversa' => PuntosLote::ORIGEN_REVERSA,
                default => PuntosLote::ORIGEN_CREDITO,
            };

        $venceAt = $opts['vence_at'] ?? null;
        if ($venceAt !== null && ! $venceAt instanceof Carbon) {
            $venceAt = Carbon::parse($venceAt);
        }

        if ($venceAt === null) {
            $dias = array_key_exists('dias_vencimiento', $opts)
                ? $opts['dias_vencimiento']
                : ($origen === PuntosLote::ORIGEN_BIENVENIDA
                    ? (int) config('loyalty.dias_vencimiento_bienvenida', 30)
                    : (int) config('loyalty.dias_vencimiento_default', 90));

            if ($dias !== null && (int) $dias > 0) {
                $venceAt = now()->addDays((int) $dias)->endOfDay();
            }
        }

        // Reversa de canje: sin vencimiento nuevo (conserva el saldo recuperado).
        if ($origen === PuntosLote::ORIGEN_REVERSA) {
            $venceAt = null;
        }

        PuntosLote::create([
            'cliente_id' => $clienteId,
            'puntos_movimiento_id' => $mov->id,
            'puntos_iniciales' => $puntos,
            'puntos_restantes' => $puntos,
            'vence_at' => $venceAt,
            'origen' => $origen,
            'meta' => $opts['meta'] ?? null,
        ]);
    }

    private function consumirLotesFifo(int $clienteId, int $puntos): void
    {
        $restante = $puntos;
        $lotes = PuntosLote::query()
            ->where('cliente_id', $clienteId)
            ->conSaldo()
            ->ordenFifo()
            ->lockForUpdate()
            ->get();

        foreach ($lotes as $lote) {
            if ($restante <= 0) {
                break;
            }
            $disponible = (int) $lote->puntos_restantes;
            $usar = min($disponible, $restante);
            $lote->puntos_restantes = $disponible - $usar;
            $lote->save();
            $restante -= $usar;
        }

        if ($restante > 0) {
            // Saldo de cuenta y lotes desfasados: no bloquear el débito ya aplicado al saldo.
            // Se tolera el desfase (p. ej. datos legacy) para no romper canjes.
        }
    }
}
