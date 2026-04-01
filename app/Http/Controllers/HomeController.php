<?php

namespace App\Http\Controllers;

use App\Models\Auditoria;
use App\Models\Cliente;
use App\Models\Cobro;
use App\Models\Servicio;
use App\Models\Ticket;
use App\Support\MenuUsuario;
use Illuminate\Support\Facades\Auth;

class HomeController extends Controller
{
    /**
     * Mapeo de tablas de auditoría a etiquetas para actividad reciente.
     */
    private const TABLA_LABELS = [
        'clientes' => 'Cliente',
        'servicios' => 'Servicio',
        'cobros' => 'Cobro',
        'factura_internas' => 'Factura interna',
        'factura_electronicas' => 'Factura electrónica',
        'tickets' => 'Ticket',
        'pedidos' => 'Pedido',
        'agenda' => 'Agenda',
        'planes' => 'Plan',
        'usuarios' => 'Usuario',
        'roles' => 'Rol',
    ];

    /**
     * Mapeo de acciones a verbos en español.
     */
    private const ACCION_LABELS = [
        'created' => 'creado',
        'updated' => 'actualizado',
        'deleted' => 'eliminado',
    ];

    /**
     * Mapeo de acciones a colores para el indicador.
     */
    private const ACCION_COLORS = [
        'created' => 'bg-blue-500',
        'updated' => 'bg-amber-500',
        'deleted' => 'bg-red-500',
    ];

    /**
     * Panel secundario: solo accesos directos (sin estadísticas). Para usuarios sin permiso dashboard.ver.
     */
    public function inicio()
    {
        $user = Auth::user();
        if (! $user) {
            return redirect()->route('login');
        }
        if ($user->tienePermiso('dashboard.ver')) {
            return redirect()->route('home');
        }

        $links = MenuUsuario::enlacesPlanos($user);

        return view('inicio', [
            'user' => $user,
            'links' => $links,
        ]);
    }

    /**
     * Show the application home page.
     *
     * @return \Illuminate\View\View
     */
    public function index()
    {
        $user = Auth::user();

        $stats = [
            'clientes' => Cliente::where('estado', 'activo')->count(),
            'servicios' => Servicio::where('estado', Servicio::ESTADO_ACTIVO)->count(),
            'facturacion' => Cobro::whereMonth('fecha_pago', now()->month)
                ->whereYear('fecha_pago', now()->year)
                ->sum('monto'),
            'tickets' => Ticket::whereIn('estado', ['pendiente', 'en_proceso'])->count(),
            'clientes_instalados_hoy' => Servicio::whereDate('fecha_instalacion', now()->toDateString())
                ->distinct()
                ->count('cliente_id'),
            'clientes_instalados_mes' => Servicio::whereMonth('fecha_instalacion', now()->month)
                ->whereYear('fecha_instalacion', now()->year)
                ->distinct()
                ->count('cliente_id'),
        ];

        $recentActivity = $this->obtenerActividadReciente();

        return view('home', compact('user', 'stats', 'recentActivity'));
    }

    /**
     * Devuelve las estadísticas del dashboard en JSON (para SPA/Vue).
     */
    public function stats()
    {
        $stats = [
            'clientes' => Cliente::where('estado', 'activo')->count(),
            'servicios' => Servicio::where('estado', Servicio::ESTADO_ACTIVO)->count(),
            'facturacion' => Cobro::whereMonth('fecha_pago', now()->month)
                ->whereYear('fecha_pago', now()->year)
                ->sum('monto'),
            'tickets' => Ticket::whereIn('estado', ['pendiente', 'en_proceso'])->count(),
            'clientes_instalados_hoy' => Servicio::whereDate('fecha_instalacion', now()->toDateString())
                ->distinct()
                ->count('cliente_id'),
            'clientes_instalados_mes' => Servicio::whereMonth('fecha_instalacion', now()->month)
                ->whereYear('fecha_instalacion', now()->year)
                ->distinct()
                ->count('cliente_id'),
        ];

        return response()->json($stats);
    }

    /**
     * Obtiene las últimas actividades desde la auditoría.
     *
     * @return array<int, array{id: int, title: string, time: string, color: string}>
     */
    private function obtenerActividadReciente(): array
    {
        $auditorias = Auditoria::with('usuario')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        return $auditorias->map(function (Auditoria $a) {
            $tabla = self::TABLA_LABELS[$a->tabla] ?? $a->tabla;
            $accion = self::ACCION_LABELS[$a->accion] ?? $a->accion;
            $title = "{$tabla} {$accion}";
            if ($a->usuario) {
                $title .= " por {$a->usuario->name}";
            }

            return [
                'id' => $a->auditoria_id,
                'title' => $title,
                'time' => $a->created_at->diffForHumans(),
                'color' => self::ACCION_COLORS[$a->accion] ?? 'bg-gray-500',
            ];
        })->all();
    }
}
