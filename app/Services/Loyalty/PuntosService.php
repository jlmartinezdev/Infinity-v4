<?php

namespace App\Services\Loyalty;

use App\Models\Canje;
use App\Models\Cliente;
use App\Models\ClientePuntos;
use App\Models\Cobro;
use App\Models\FacturaInterna;
use App\Models\LoyaltyRegla;
use App\Models\PuntosMovimiento;
use Illuminate\Support\Facades\DB;

class PuntosService
{
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
        return $this->canjesDelMes($clienteId) < self::LIMITE_CANJES_MES;
    }

    public function resumenPortal(int $clienteId): array
    {
        $canjesMes = $this->canjesDelMes($clienteId);

        return [
            'saldo' => $this->saldo($clienteId),
            'puede_canjear' => $canjesMes < self::LIMITE_CANJES_MES,
            'canjes_mes' => $canjesMes,
            'limite_mensual' => self::LIMITE_CANJES_MES,
        ];
    }

    /**
     * @param  array{referencia_tipo?: string, referencia_id?: int, meta?: array, created_by?: int|null}  $opts
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
            ? \Carbon\Carbon::parse($cobro->fecha_pago)
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

            $cuenta->saldo = $nuevo;
            $cuenta->save();
            $movimiento->delete();
        });
    }

    /**
     * @param  array{referencia_tipo?: string, referencia_id?: int, meta?: array, created_by?: int|null}  $opts
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

            return PuntosMovimiento::create([
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
        });
    }
}
