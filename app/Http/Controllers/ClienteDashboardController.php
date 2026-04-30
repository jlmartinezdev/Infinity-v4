<?php

namespace App\Http\Controllers;

use App\Models\Servicio;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;

class ClienteDashboardController extends Controller
{
    public function index(): View
    {
        $cantidadMeses = 12;
        $inicioPeriodo = now()->startOfMonth()->subMonths($cantidadMeses - 1)->startOfDay();
        $finPeriodo = now()->endOfMonth()->endOfDay();

        $instalacionesPorMes = Servicio::query()
            ->selectRaw("DATE_FORMAT(fecha_instalacion, '%Y-%m') as mes, COUNT(*) as cantidad")
            ->whereNotNull('fecha_instalacion')
            ->whereBetween('fecha_instalacion', [$inicioPeriodo, $finPeriodo])
            ->groupBy('mes')
            ->pluck('cantidad', 'mes');

        $meses = collect(range(0, $cantidadMeses - 1))
            ->map(fn (int $offset) => now()->copy()->startOfMonth()->subMonths($cantidadMeses - 1 - $offset));

        $series = $meses->map(function (Carbon $mesActual) use ($instalacionesPorMes) {
            $mesClave = $mesActual->format('Y-m');

            return [
                'mes' => ucfirst($mesActual->translatedFormat('M Y')),
                'cantidad_instalaciones' => (int) ($instalacionesPorMes[$mesClave] ?? 0),
            ];
        })->values();

        $seriesAnual = Servicio::query()
            ->selectRaw('YEAR(fecha_instalacion) as anio, COUNT(*) as cantidad_instalaciones')
            ->whereNotNull('fecha_instalacion')
            ->groupBy('anio')
            ->orderBy('anio')
            ->get()
            ->map(fn ($item) => [
                'anio' => (string) $item->anio,
                'cantidad_instalaciones' => (int) $item->cantidad_instalaciones,
            ])
            ->values();

        return view('clientes.dashboard', [
            'series' => $series,
            'seriesAnual' => $seriesAnual,
        ]);
    }
}
