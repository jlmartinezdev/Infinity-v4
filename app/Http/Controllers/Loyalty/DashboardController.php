<?php

namespace App\Http\Controllers\Loyalty;

use App\Http\Controllers\Controller;
use App\Models\Canje;
use App\Models\ClientePuntos;
use App\Models\LoyaltyRegla;
use App\Models\Novedad;
use App\Models\PlanUpsell;
use App\Models\Premio;
use App\Models\PuntosMovimiento;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    private const PERMISOS = [
        'loyalty-novedades.ver',
        'loyalty-premios.ver',
        'loyalty-canjes.ver',
        'loyalty-puntos.ver',
        'loyalty-upsell.ver',
        'loyalty-app-config.ver',
    ];

    public function index(Request $request)
    {
        $user = $request->user();
        $puede = false;
        foreach (self::PERMISOS as $codigo) {
            if ($user && $user->tienePermiso($codigo)) {
                $puede = true;
                break;
            }
        }
        if (! $puede) {
            abort(403, 'No tienes permiso para ver Loyalty.');
        }

        $hoy = now()->toDateString();
        $inicioMes = now()->startOfMonth();

        $reglas = LoyaltyRegla::query()->orderBy('nombre')->get();
        $reglasActivas = $reglas->where('activa', true)->values();

        $stats = [
            'novedades_activas' => Novedad::query()->publicadas()->count(),
            'novedades_total' => Novedad::query()->count(),
            'premios_activos' => Premio::query()->where('activo', true)->count(),
            'premios_stock' => (int) Premio::query()->where('activo', true)->sum('stock'),
            'canjes_abiertos' => Canje::query()
                ->whereNotIn('estado', [
                    Canje::ESTADO_ENTREGADO,
                    Canje::ESTADO_APLICADO,
                    Canje::ESTADO_CANCELADO,
                ])
                ->count(),
            'canjes_hoy' => Canje::query()->whereDate('created_at', $hoy)->count(),
            'canjes_mes' => Canje::query()->where('created_at', '>=', $inicioMes)->count(),
            'reglas_activas' => $reglasActivas->count(),
            'reglas_total' => $reglas->count(),
            'clientes_con_saldo' => ClientePuntos::query()->where('saldo', '>', 0)->count(),
            'puntos_en_circulacion' => (int) ClientePuntos::query()->sum('saldo'),
            'puntos_acreditados_mes' => (int) PuntosMovimiento::query()
                ->where('created_at', '>=', $inicioMes)
                ->where('puntos', '>', 0)
                ->sum('puntos'),
            'puntos_debitados_mes' => (int) abs((int) PuntosMovimiento::query()
                ->where('created_at', '>=', $inicioMes)
                ->where('puntos', '<', 0)
                ->sum('puntos')),
            'upsell_activos' => PlanUpsell::query()->where('activo', true)->count(),
        ];

        $movimientosRecientes = PuntosMovimiento::with(['cliente'])
            ->orderByDesc('id')
            ->limit(10)
            ->get();

        $canjesRecientes = Canje::with(['cliente', 'premio'])
            ->orderByDesc('id')
            ->limit(8)
            ->get();

        $topSaldos = ClientePuntos::with('cliente')
            ->where('saldo', '>', 0)
            ->orderByDesc('saldo')
            ->limit(8)
            ->get();

        $acredPorEvento = PuntosMovimiento::query()
            ->select('tipo', DB::raw('COUNT(*) as cantidad'), DB::raw('SUM(puntos) as total'))
            ->where('created_at', '>=', $inicioMes)
            ->where('puntos', '>', 0)
            ->groupBy('tipo')
            ->orderByDesc('total')
            ->get();

        return view('loyalty.dashboard', [
            'stats' => $stats,
            'reglasActivas' => $reglasActivas,
            'movimientosRecientes' => $movimientosRecientes,
            'canjesRecientes' => $canjesRecientes,
            'topSaldos' => $topSaldos,
            'acredPorEvento' => $acredPorEvento,
            'eventos' => LoyaltyRegla::eventos(),
            'estadosCanje' => Canje::estados(),
        ]);
    }
}
