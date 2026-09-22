<?php

namespace App\Http\Controllers;

use App\Models\Cobro;
use App\Models\CobroResumen;
use App\Models\Nodo;
use App\Support\CobrosMesVentana;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class FacturacionDashboardController extends Controller
{
    public function index(Request $request): View
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

        $mesNodoOpcion = trim((string) $request->query('mes', now()->format('Y-m')));
        try {
            $mesNodo = Carbon::createFromFormat('Y-m', $mesNodoOpcion)->startOfMonth();
        } catch (\Throwable) {
            $mesNodo = now()->startOfMonth();
            $mesNodoOpcion = $mesNodo->format('Y-m');
        }

        $opcionesMesNodo = collect(range(0, 11))
            ->map(fn (int $offset) => now()->copy()->startOfMonth()->subMonths($offset))
            ->map(fn (Carbon $mes) => [
                'valor' => $mes->format('Y-m'),
                'etiqueta' => $mes->translatedFormat('F Y'),
            ]);

        $seriesIngresoNodo = $this->serieIngresoPorNodo(
            $mesNodo->copy()->startOfMonth()->startOfDay(),
            $mesNodo->copy()->endOfMonth()->endOfDay(),
        );
        $totalIngresoNodo = round((float) $seriesIngresoNodo->sum('total'), 2);

        return view('facturacion.dashboard', [
            'series' => $series,
            'seriesCobroRealDia' => $seriesCobroRealDia,
            'seriesCobroRealMes' => $seriesCobroRealMes,
            'seriesAtrasadoFavor' => $seriesAtrasadoFavor,
            'seriesIngresoNodo' => $seriesIngresoNodo,
            'totalIngresoNodo' => $totalIngresoNodo,
            'mesNodoSeleccionado' => $mesNodoOpcion,
            'mesNodoEtiqueta' => $mesNodo->translatedFormat('F Y'),
            'opcionesMesNodo' => $opcionesMesNodo,
        ]);
    }

    /**
     * Cobros del período (por fecha de pago), atribuidos al nodo del servicio del cliente.
     * Un cobro cuenta una sola vez: se usa un servicio activo/suspendido/cortado, o el de menor id.
     *
     * @return Collection<int, array{nodo: string, total: float, cantidad: int, porcentaje: float}>
     */
    private function serieIngresoPorNodo(Carbon $desdeMes, Carbon $hastaMes): Collection
    {
        $pickServicios = DB::table('servicios')
            ->selectRaw("cliente_id, COALESCE(MIN(CASE WHEN estado IN ('A', 'S', 'C') THEN servicio_id END), MIN(servicio_id)) as servicio_id")
            ->whereIn('cliente_id', function ($q) use ($desdeMes, $hastaMes) {
                $q->select('cliente_id')
                    ->from('cobros')
                    ->whereBetween('fecha_pago', [$desdeMes, $hastaMes]);
            })
            ->groupBy('cliente_id');

        $clienteNodo = DB::table('servicios as s')
            ->joinSub($pickServicios, 'pick', 'pick.servicio_id', '=', 's.servicio_id')
            ->leftJoin('router_ip_pools as p', 'p.pool_id', '=', 's.pool_id')
            ->leftJoin('routers as r', 'r.router_id', '=', 'p.router_id')
            ->leftJoin('caja_nap_puerto_activos as cna', 'cna.servicio_id', '=', 's.servicio_id')
            ->leftJoin('caja_naps as cn', 'cn.caja_nap_id', '=', 'cna.caja_nap_id')
            ->select('s.cliente_id', DB::raw('COALESCE(r.nodo_id, cn.nodo_id) as nodo_id'));

        $totales = DB::table('cobros')
            ->leftJoinSub($clienteNodo, 'cnodo', 'cnodo.cliente_id', '=', 'cobros.cliente_id')
            ->whereBetween('cobros.fecha_pago', [$desdeMes, $hastaMes])
            ->groupBy('cnodo.nodo_id')
            ->selectRaw('cnodo.nodo_id, SUM(cobros.monto) as total, COUNT(*) as cantidad')
            ->get()
            ->keyBy(fn ($row) => $row->nodo_id === null ? 'sin' : (string) $row->nodo_id);

        $totalGeneral = (float) $totales->sum(fn ($row) => (float) $row->total);

        $series = Nodo::query()
            ->orderBy('descripcion')
            ->get(['nodo_id', 'descripcion'])
            ->map(function (Nodo $nodo) use ($totales, $totalGeneral) {
                $row = $totales->get((string) $nodo->nodo_id);
                $total = round((float) ($row->total ?? 0), 2);

                return [
                    'nodo' => $nodo->descripcion,
                    'total' => $total,
                    'cantidad' => (int) ($row->cantidad ?? 0),
                    'porcentaje' => $totalGeneral > 0 ? round(($total / $totalGeneral) * 100, 1) : 0.0,
                ];
            })
            ->filter(fn (array $row) => $row['total'] > 0)
            ->sortByDesc('total')
            ->values();

        $sinNodo = $totales->get('sin');
        if ($sinNodo && (float) $sinNodo->total > 0) {
            $totalSin = round((float) $sinNodo->total, 2);
            $series->push([
                'nodo' => 'Sin nodo',
                'total' => $totalSin,
                'cantidad' => (int) $sinNodo->cantidad,
                'porcentaje' => $totalGeneral > 0 ? round(($totalSin / $totalGeneral) * 100, 1) : 0.0,
            ]);
        }

        return $series->sortByDesc('total')->values();
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
