<?php

namespace App\Http\Controllers;

use App\Models\Cobro;
use App\Support\CobrosMesVentana;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class FacturacionDashboardController extends Controller
{
    public function index(): View
    {
        $user = auth()->user();
        $esAdmin = $user && $user->rol && strtolower($user->rol->descripcion) === 'administrador';
        abort_unless($esAdmin, 403, 'Solo administradores pueden acceder al dashboard de facturacion.');

        $cantidadMeses = 6;
        $inicioPeriodo = now()->startOfMonth()->subMonths($cantidadMeses - 1)->startOfDay();
        $finPeriodo = now()->endOfMonth()->endOfDay();

        $totalesFacturasPorMes = DB::table('factura_internas')
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as mes, SUM(total) as total_facturado")
            ->whereBetween('created_at', [$inicioPeriodo->copy()->subMonthNoOverflow(), $finPeriodo->copy()->subMonthNoOverflow()])
            ->groupBy('mes')
            ->pluck('total_facturado', 'mes');
        $inicioMesActual = now()->copy()->startOfMonth()->startOfDay();
        $finMesActual = now()->copy()->endOfMonth()->endOfDay();
        $totalesCobroRealPorMes = DB::table('cobros')
            ->selectRaw("DATE_FORMAT(fecha_pago, '%Y-%m') as mes, SUM(monto) as total_cobrado_real")
            ->whereBetween('fecha_pago', [$inicioPeriodo, $finPeriodo])
            ->groupBy('mes')
            ->pluck('total_cobrado_real', 'mes');
        $totalesCobroRealPorDia = DB::table('cobros')
            ->selectRaw("DATE(fecha_pago) as dia, SUM(monto) as total_cobrado_real")
            ->whereBetween('fecha_pago', [$inicioMesActual, $finMesActual])
            ->groupBy('dia')
            ->pluck('total_cobrado_real', 'dia');

        $meses = collect(range(0, $cantidadMeses - 1))
            ->map(fn (int $offset) => now()->copy()->startOfMonth()->subMonths($cantidadMeses - 1 - $offset));

        $series = $meses->map(function (Carbon $mesActual) use ($totalesFacturasPorMes) {
            $mesFacturaClave = $mesActual->copy()->subMonthNoOverflow()->format('Y-m');

            $totalFacturado = (float) ($totalesFacturasPorMes[$mesFacturaClave] ?? 0);

            $rangos = CobrosMesVentana::rangosParaMesReferencia($mesActual);
            // Cobrado real del ciclo: ingreso registrado en cobros.monto dentro de la ventana,
            // asociado a facturas del mes facturado del ciclo.
            $totalCobrado = (float) Cobro::query()
                ->whereBetween('fecha_pago', [$rangos['desdeVentana'], $rangos['hastaVentana']])
                ->whereHas('facturaInternas', function ($q) use ($rangos) {
                    $q->whereBetween('factura_internas.created_at', [$rangos['facturaDesde'], $rangos['facturaHasta']]);
                })
                ->sum('monto');

            // Pendiente real del mismo cohort de facturas del ciclo.
            $totalPendiente = (float) (DB::table('factura_internas')
                ->selectRaw('SUM(total - COALESCE((SELECT SUM(monto) FROM cobro_factura_interna WHERE factura_interna_id = factura_internas.id), 0)) as total_pendiente')
                ->whereBetween('created_at', [$rangos['facturaDesde'], $rangos['facturaHasta']])
                ->whereRaw('total > COALESCE((SELECT SUM(monto) FROM cobro_factura_interna WHERE factura_interna_id = factura_internas.id), 0)')
                ->value('total_pendiente') ?? 0);

            return [
                'mes' => $mesActual->translatedFormat('M Y'),
                'total_facturado' => round($totalFacturado, 2),
                'total_cobrado' => round($totalCobrado, 2),
                'total_pendiente' => round($totalPendiente, 2),
            ];
        })->values();

        $diasMesActual = collect(range(1, $inicioMesActual->daysInMonth))
            ->map(fn (int $day) => $inicioMesActual->copy()->day($day));
        $seriesCobroRealDia = $diasMesActual->map(function (Carbon $dia) use ($totalesCobroRealPorDia) {
            $diaClave = $dia->toDateString();
            $totalCobradoReal = (float) ($totalesCobroRealPorDia[$diaClave] ?? 0);

            return [
                'dia' => $dia->format('d/m'),
                'total_cobrado_real' => round($totalCobradoReal, 2),
            ];
        })->values();
        $seriesCobroRealMes = $meses->map(function (Carbon $mesActual) use ($totalesCobroRealPorMes) {
            $mesClave = $mesActual->format('Y-m');
            $totalCobradoReal = (float) ($totalesCobroRealPorMes[$mesClave] ?? 0);

            return [
                'mes' => $mesActual->translatedFormat('M Y'),
                'total_cobrado_real' => round($totalCobradoReal, 2),
            ];
        })->values();
        $seriesAtrasadoFavor = $meses->map(function (Carbon $mesActual) {
            $desdeMes = $mesActual->copy()->startOfMonth()->startOfDay();
            $hastaMes = $mesActual->copy()->endOfMonth()->endOfDay();
            $inicioMesPrevio = $mesActual->copy()->subMonthNoOverflow()->startOfMonth()->startOfDay();

            // Cobro atrasado: cobros del mes aplicados a facturas creadas antes del mes previo del ciclo.
            $cobroAtrasado = (float) (DB::table('cobro_factura_interna as cfi')
                ->join('cobros', 'cobros.id', '=', 'cfi.cobro_id')
                ->join('factura_internas as fi', 'fi.id', '=', 'cfi.factura_interna_id')
                ->whereBetween('cobros.fecha_pago', [$desdeMes, $hastaMes])
                ->where('fi.created_at', '<', $inicioMesPrevio)
                ->sum('cfi.monto') ?? 0);

            // Saldo a favor: parte del cobro del mes no aplicada a facturas.
            $subPivotPorCobro = DB::table('cobro_factura_interna')
                ->selectRaw('cobro_id, SUM(monto) as monto_aplicado')
                ->groupBy('cobro_id');
            $saldoFavor = (float) (DB::table('cobros')
                ->leftJoinSub($subPivotPorCobro, 'piv', function ($join) {
                    $join->on('piv.cobro_id', '=', 'cobros.id');
                })
                ->whereBetween('cobros.fecha_pago', [$desdeMes, $hastaMes])
                ->selectRaw('SUM(GREATEST(cobros.monto - COALESCE(piv.monto_aplicado, 0), 0)) as saldo_favor')
                ->value('saldo_favor') ?? 0);

            return [
                'mes' => $mesActual->translatedFormat('M Y'),
                'cobro_atrasado' => round($cobroAtrasado, 2),
                'saldo_favor' => round($saldoFavor, 2),
            ];
        })->values();

        return view('facturacion.dashboard', [
            'series' => $series,
            'seriesCobroRealDia' => $seriesCobroRealDia,
            'seriesCobroRealMes' => $seriesCobroRealMes,
            'seriesAtrasadoFavor' => $seriesAtrasadoFavor,
        ]);
    }
}
