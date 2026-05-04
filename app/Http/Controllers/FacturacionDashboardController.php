<?php

namespace App\Http\Controllers;

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

        $meses = collect(range(0, $cantidadMeses - 1))
            ->map(fn (int $offset) => now()->copy()->startOfMonth()->subMonths($cantidadMeses - 1 - $offset));

        $series = $meses->map(function (Carbon $mesActual) use ($totalesFacturasPorMes) {
            $mesFacturaClave = $mesActual->copy()->subMonthNoOverflow()->format('Y-m');

            $totalFacturado = (float) ($totalesFacturasPorMes[$mesFacturaClave] ?? 0);

            $rangos = CobrosMesVentana::rangosParaMesReferencia($mesActual);
            $totalCobrado = CobrosMesVentana::sumPivotMontos(
                $rangos['desdeVentana'],
                $rangos['hastaVentana'],
                $rangos['facturaDesde'],
                $rangos['facturaHasta'],
                null,
                null,
            );
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
