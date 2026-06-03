<?php

namespace App\Services;

use App\Models\Cobro;
use App\Models\CobroResumen;
use App\Models\FacturaInterna;
use App\Support\CobrosMesVentana;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class CobrosResumenService
{
    /**
     * Ajusta cobros_resumen al registrar o eliminar un cobro (incremental, sin escanear la tabla cobros).
     */
    public function aplicarImpactoCobro(Cobro $cobro, int $signo = 1): void
    {
        $desglose = CobrosMesVentana::desgloseCobroParaResumen($cobro);
        $multiplicador = $signo >= 0 ? 1 : -1;

        foreach ($desglose as $mesClave => $montos) {
            $this->ajustarMesPorCobro($mesClave, $montos, $multiplicador);
        }
    }

    /**
     * Recalcula facturado y pendiente del ciclo (no toca total_cobrado ni adelantado/atrasado).
     */
    public function aplicarImpactoFactura(FacturaInterna $factura): void
    {
        $this->recalcularFacturacionMeses(
            CobrosMesVentana::mesesReferenciaAfectadosPorFactura(Carbon::parse($factura->created_at))
        );
    }

    /**
     * Recalcula un mes completo (rebuild / auditoría).
     */
    public function recalcularMes(Carbon $mesReferencia): CobroResumen
    {
        $resumen = $this->recalcularFacturacionMes($mesReferencia);
        $this->recalcularCobrosMesDesdeTabla($mesReferencia);

        return $resumen->fresh();
    }

    /**
     * @param  list<string>  $meses  Fechas Y-m-d (primer día del mes)
     */
    public function recalcularMeses(array $meses): void
    {
        foreach (array_unique($meses) as $mes) {
            $this->recalcularMes(Carbon::parse($mes)->startOfMonth());
        }
    }

    public function sincronizarDesdeCero(?Carbon $desde = null, ?Carbon $hasta = null): int
    {
        $desde ??= Cobro::query()->min('fecha_pago')
            ? Carbon::parse(Cobro::query()->min('fecha_pago'))->startOfMonth()
            : now()->startOfMonth();
        $hasta ??= now()->endOfMonth();

        $cursor = $desde->copy()->startOfMonth();
        $fin = $hasta->copy()->startOfMonth();
        $count = 0;

        while ($cursor->lte($fin)) {
            $this->recalcularMes($cursor);
            $cursor->addMonthNoOverflow();
            $count++;
        }

        return $count;
    }

    /**
     * @param  array{total_cobrado: float, reduccion_pendiente: float, pago_adelantado: float, pago_atrasado: float}  $montos
     */
    private function ajustarMesPorCobro(string $mesClave, array $montos, int $multiplicador): void
    {
        $totalCobrado = round((float) $montos['total_cobrado'], 2);
        $reduccionPendiente = round((float) $montos['reduccion_pendiente'], 2);
        $pagoAdelantado = round((float) $montos['pago_adelantado'], 2);
        $pagoAtrasado = round((float) $montos['pago_atrasado'], 2);

        if ($totalCobrado <= 0 && $reduccionPendiente <= 0 && $pagoAdelantado <= 0 && $pagoAtrasado <= 0) {
            return;
        }

        $resumen = CobroResumen::query()->firstOrCreate(
            ['mes' => $mesClave],
            [
                'total_facturado' => 0,
                'total_pendiente' => 0,
                'total_cobrado' => 0,
                'pago_adelantado' => 0,
                'pago_atrasado' => 0,
            ]
        );

        if ($multiplicador > 0) {
            if ($totalCobrado > 0) {
                $resumen->increment('total_cobrado', $totalCobrado);
            }
            if ($reduccionPendiente > 0) {
                $resumen->decrement('total_pendiente', $reduccionPendiente);
            }
            if ($pagoAdelantado > 0) {
                $resumen->increment('pago_adelantado', $pagoAdelantado);
            }
            if ($pagoAtrasado > 0) {
                $resumen->increment('pago_atrasado', $pagoAtrasado);
            }
        } else {
            if ($totalCobrado > 0) {
                $resumen->decrement('total_cobrado', $totalCobrado);
            }
            if ($reduccionPendiente > 0) {
                $resumen->increment('total_pendiente', $reduccionPendiente);
            }
            if ($pagoAdelantado > 0) {
                $resumen->decrement('pago_adelantado', $pagoAdelantado);
            }
            if ($pagoAtrasado > 0) {
                $resumen->decrement('pago_atrasado', $pagoAtrasado);
            }
        }

        $resumen->refresh();
        $corregir = [];
        if ((float) $resumen->total_cobrado < 0) {
            $corregir['total_cobrado'] = 0;
        }
        if ((float) $resumen->total_pendiente < 0) {
            $corregir['total_pendiente'] = 0;
        }
        if ((float) $resumen->pago_adelantado < 0) {
            $corregir['pago_adelantado'] = 0;
        }
        if ((float) $resumen->pago_atrasado < 0) {
            $corregir['pago_atrasado'] = 0;
        }
        if ($corregir !== []) {
            $resumen->update($corregir);
        }
    }

    /**
     * @param  list<string>  $meses
     */
    private function recalcularFacturacionMeses(array $meses): void
    {
        foreach (array_unique($meses) as $mes) {
            $this->recalcularFacturacionMes(Carbon::parse($mes)->startOfMonth());
        }
    }

    private function recalcularFacturacionMes(Carbon $mesReferencia): CobroResumen
    {
        $mes = $mesReferencia->copy()->startOfMonth()->startOfDay();
        $mesClave = $mes->toDateString();

        $totalFacturado = CobrosMesVentana::calcularTotalFacturadoMesReferencia($mes);
        $totalPendiente = CobrosMesVentana::calcularTotalPendienteMesReferencia($mes);

        return CobroResumen::query()->updateOrCreate(
            ['mes' => $mesClave],
            [
                'total_facturado' => round($totalFacturado, 2),
                'total_pendiente' => round($totalPendiente, 2),
            ]
        );
    }

    private function recalcularCobrosMesDesdeTabla(Carbon $mesReferencia): void
    {
        $mes = $mesReferencia->copy()->startOfMonth()->startOfDay();
        $mesClave = $mes->toDateString();

        $totalCobrado = 0.0;
        $pagoAdelantado = 0.0;
        $pagoAtrasado = 0.0;

        $lineasPivot = DB::table('cobro_factura_interna as cfi')
            ->join('cobros', 'cobros.id', '=', 'cfi.cobro_id')
            ->join('factura_internas as fi', 'fi.id', '=', 'cfi.factura_interna_id')
            ->select([
                'cobros.fecha_pago',
                'cfi.monto as pivot_monto',
                'fi.created_at as factura_created_at',
            ])
            ->get();

        foreach ($lineasPivot as $linea) {
            $clasificado = CobrosMesVentana::clasificarLineaPivot(
                Carbon::parse($linea->fecha_pago),
                Carbon::parse($linea->factura_created_at),
                (float) $linea->pivot_monto
            );
            if ($clasificado !== null && $clasificado['mes'] === $mesClave) {
                $totalCobrado += $clasificado['total_cobrado'];
                $pagoAdelantado += $clasificado['pago_adelantado'];
                $pagoAtrasado += $clasificado['pago_atrasado'];
            }
        }

        $legacyCobros = Cobro::query()
            ->with('facturaInterna')
            ->whereNotNull('factura_interna_id')
            ->whereDoesntHave('facturaInternas')
            ->get();

        foreach ($legacyCobros as $cobro) {
            if (! $cobro->facturaInterna) {
                continue;
            }
            $clasificado = CobrosMesVentana::clasificarLineaPivot(
                Carbon::parse($cobro->fecha_pago),
                Carbon::parse($cobro->facturaInterna->created_at),
                (float) $cobro->monto
            );
            if ($clasificado !== null && $clasificado['mes'] === $mesClave) {
                $totalCobrado += $clasificado['total_cobrado'];
                $pagoAdelantado += $clasificado['pago_adelantado'];
                $pagoAtrasado += $clasificado['pago_atrasado'];
            }
        }

        $sinFactura = Cobro::query()
            ->whereNull('factura_interna_id')
            ->whereDoesntHave('facturaInternas')
            ->get();

        foreach ($sinFactura as $cobro) {
            $clasificado = CobrosMesVentana::clasificarCobroSinFactura(
                Carbon::parse($cobro->fecha_pago),
                (float) $cobro->monto
            );
            if ($clasificado !== null && $clasificado['mes'] === $mesClave) {
                $totalCobrado += $clasificado['total_cobrado'];
            }
        }

        $multicobroExceso = Cobro::query()
            ->whereHas('facturaInternas')
            ->with('facturaInternas')
            ->get();

        foreach ($multicobroExceso as $cobro) {
            $sumaPivot = (float) $cobro->facturaInternas->sum(fn ($f) => (float) ($f->pivot->monto ?? 0));
            $exceso = (float) $cobro->monto - $sumaPivot;
            if ($exceso <= 0.009) {
                continue;
            }
            $clasificado = CobrosMesVentana::clasificarCobroSinFactura(
                Carbon::parse($cobro->fecha_pago),
                $exceso
            );
            if ($clasificado !== null && $clasificado['mes'] === $mesClave) {
                $totalCobrado += $clasificado['total_cobrado'];
            }
        }

        CobroResumen::query()->updateOrCreate(
            ['mes' => $mesClave],
            [
                'total_cobrado' => round($totalCobrado, 2),
                'pago_adelantado' => round($pagoAdelantado, 2),
                'pago_atrasado' => round($pagoAtrasado, 2),
            ]
        );
    }
}
