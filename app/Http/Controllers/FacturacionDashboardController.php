<?php

namespace App\Http\Controllers;

use App\Models\Cobro;
use App\Models\CobroResumen;
use App\Support\CobrosMesVentana;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
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

        $resumenesPorMes = CobroResumen::mapaPorRangoMeses($inicioPeriodo, $finPeriodo);

        $inicioMesActual = now()->copy()->startOfMonth()->startOfDay();
        $finMesActual = now()->copy()->endOfMonth()->endOfDay();
        $totalesCobroRealPorDia = DB::table('cobros')
            ->selectRaw("DATE(fecha_pago) as dia, SUM(monto) as total_cobrado_real")
            ->whereBetween('fecha_pago', [$inicioMesActual, $finMesActual])
            ->groupBy('dia')
            ->pluck('total_cobrado_real', 'dia');

        $meses = collect(range(0, $cantidadMeses - 1))
            ->map(fn (int $offset) => now()->copy()->startOfMonth()->subMonths($cantidadMeses - 1 - $offset));

        $series = $meses->map(function (Carbon $mesActual) use ($resumenesPorMes) {
            $resumen = $resumenesPorMes->get($mesActual->format('Y-m'));

            return [
                'mes' => $mesActual->translatedFormat('M Y'),
                'total_facturado' => round((float) ($resumen?->total_facturado ?? 0), 2),
                'total_cobrado' => round((float) ($resumen?->total_cobrado ?? 0), 2),
                'total_pendiente' => round((float) ($resumen?->total_pendiente ?? 0), 2),
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

        $seriesCobroRealMes = $meses->map(function (Carbon $mesActual) use ($resumenesPorMes) {
            $resumen = $resumenesPorMes->get($mesActual->format('Y-m'));

            return [
                'mes' => $mesActual->translatedFormat('M Y'),
                'total_cobrado_real' => round((float) ($resumen?->total_cobrado ?? 0), 2),
            ];
        })->values();

        $seriesAtrasadoFavor = $meses->map(function (Carbon $mesActual) use ($resumenesPorMes) {
            $resumen = $resumenesPorMes->get($mesActual->format('Y-m'));
            $desdeMes = $mesActual->copy()->startOfMonth()->startOfDay();
            $hastaMes = $mesActual->copy()->endOfMonth()->endOfDay();

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
                'cobro_atrasado' => round((float) ($resumen?->pago_atrasado ?? 0), 2),
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

    public function cobrosSaldoFavor(Request $request): View
    {
        $user = auth()->user();
        $esAdmin = $user && $user->rol && strtolower($user->rol->descripcion) === 'administrador';
        abort_unless($esAdmin, 403, 'Solo administradores pueden acceder al dashboard de facturacion.');

        $mesOpcion = trim((string) $request->query('mes', now()->format('Y-m')));
        try {
            $mesReferencia = Carbon::createFromFormat('Y-m', $mesOpcion)->startOfMonth();
        } catch (\Throwable) {
            $mesReferencia = now()->startOfMonth();
            $mesOpcion = $mesReferencia->format('Y-m');
        }

        $desdeMes = $mesReferencia->copy()->startOfMonth()->startOfDay();
        $hastaMes = $mesReferencia->copy()->endOfMonth()->endOfDay();

        $sqlSaldoFavor = CobrosMesVentana::sqlMontoSaldoFavorRegistrado();

        $baseQuery = Cobro::query()
            ->with(['cliente', 'facturaInternas', 'usuario'])
            ->whereBetween('fecha_pago', [$desdeMes, $hastaMes]);
        CobrosMesVentana::scopeConSaldoFavorRegistrado($baseQuery);

        $totalSaldoFavor = (float) (clone $baseQuery)
            ->selectRaw('SUM('.$sqlSaldoFavor.') as total')
            ->value('total');

        $cobros = (clone $baseQuery)
            ->select('cobros.*')
            ->selectRaw($sqlSaldoFavor.' as monto_saldo_favor')
            ->orderByDesc('fecha_pago')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        $opcionesMes = collect(range(0, 11))
            ->map(fn (int $offset) => now()->copy()->startOfMonth()->subMonths($offset))
            ->map(fn (Carbon $mes) => [
                'valor' => $mes->format('Y-m'),
                'etiqueta' => $mes->translatedFormat('F Y'),
            ]);

        return view('facturacion.cobros-saldo-favor', [
            'cobros' => $cobros,
            'mesSeleccionado' => $mesOpcion,
            'mesEtiqueta' => $mesReferencia->translatedFormat('F Y'),
            'opcionesMes' => $opcionesMes,
            'totalSaldoFavor' => $totalSaldoFavor,
            'totalCobros' => $cobros->total(),
        ]);
    }
}
