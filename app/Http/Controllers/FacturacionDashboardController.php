<?php

namespace App\Http\Controllers;

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

        $totalesCobrosPorMes = DB::table('cobro_factura_interna as cfi')
            ->join('cobros', 'cobros.id', '=', 'cfi.cobro_id')
            ->join('factura_internas as fi', 'fi.id', '=', 'cfi.factura_interna_id')
            ->selectRaw("DATE_FORMAT(cobros.fecha_pago, '%Y-%m') as mes, SUM(cfi.monto) as total_cobrado")
            ->whereBetween('cobros.fecha_pago', [$inicioPeriodo, $finPeriodo])
            ->whereBetween('fi.created_at', [$inicioPeriodo->copy()->subMonthNoOverflow(), $finPeriodo->copy()->subMonthNoOverflow()])
            ->groupBy('mes')
            ->pluck('total_cobrado', 'mes');

        $meses = collect(range(0, $cantidadMeses - 1))
            ->map(fn (int $offset) => now()->copy()->startOfMonth()->subMonths($cantidadMeses - 1 - $offset));

        $series = $meses->map(function (Carbon $mesActual) use ($totalesFacturasPorMes, $totalesCobrosPorMes) {
            $mesClave = $mesActual->format('Y-m');
            $mesFacturaClave = $mesActual->copy()->subMonthNoOverflow()->format('Y-m');

            $totalFacturado = (float) ($totalesFacturasPorMes[$mesFacturaClave] ?? 0);
            $totalCobrado = (float) ($totalesCobrosPorMes[$mesClave] ?? 0);
            $totalPendiente = max($totalFacturado - $totalCobrado, 0);

            return [
                'mes' => $mesActual->translatedFormat('M Y'),
                'total_facturado' => round($totalFacturado, 2),
                'total_cobrado' => round($totalCobrado, 2),
                'total_pendiente' => round($totalPendiente, 2),
            ];
        })->values();

        return view('facturacion.dashboard', [
            'series' => $series,
        ]);
    }
}
