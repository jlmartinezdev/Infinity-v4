<?php

namespace App\Http\Controllers;

use App\Models\Auditoria;
use App\Models\Cliente;
use App\Models\Cobro;
use App\Models\FacturaInterna;
use App\Models\Olt;
use App\Models\Pedido;
use App\Models\Router;
use App\Models\Servicio;
use App\Models\Tarea;
use App\Models\Ticket;
use App\Models\User;
use App\Support\CobrosMesVentana;
use App\Support\MenuUsuario;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

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
     * Panel operativo para staff sin dashboard.ver (técnicos, cajeros, etc.).
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

        $user->loadMissing('rol');
        $links = MenuUsuario::enlacesPlanos($user);
        $kpis = $this->obtenerKpisStaff($user);
        $accionesRapidas = $this->obtenerAccionesRapidasStaff($user);

        return view('inicio', [
            'user' => $user,
            'links' => $links,
            'kpis' => $kpis,
            'accionesRapidas' => $accionesRapidas,
        ]);
    }

    /**
     * KPIs relevantes según permisos del usuario (no admin).
     *
     * @return list<array{label: string, value: string, hint: string, href: string|null, tone: string}>
     */
    private function obtenerKpisStaff(User $user): array
    {
        $kpis = [];

        if ($user->tienePermiso('tickets.ver')) {
            $mios = Ticket::query()
                ->whereIn('estado', ['pendiente', 'en_proceso'])
                ->where('asignado_id', $user->usuario_id)
                ->count();
            $kpis[] = [
                'label' => 'Mis tickets',
                'value' => number_format($mios),
                'hint' => 'Abiertos asignados a vos',
                'href' => Route::has('tickets.index') ? route('tickets.index') : '/tickets',
                'tone' => 'rose',
            ];
        }

        if ($user->tienePermiso('tareas.ver')) {
            $mias = Tarea::query()
                ->whereIn('estado', ['pendiente', 'en_progreso'])
                ->where('asignado_id', $user->usuario_id)
                ->count();
            $kpis[] = [
                'label' => 'Mis tareas',
                'value' => number_format($mias),
                'hint' => 'Pendientes / en progreso',
                'href' => Route::has('tareas.index') ? route('tareas.index') : '/tareas',
                'tone' => 'violet',
            ];
        }

        if ($user->tienePermiso('pedidos.ver')) {
            $pedidos = Pedido::query()
                ->where('estado_instalado', false)
                ->whereDoesntHave('estadoPedidoDetalles', fn ($q) => $q->where('estado', 'D'))
                ->count();
            $kpis[] = [
                'label' => 'Pedidos',
                'value' => number_format($pedidos),
                'hint' => 'Pendientes de instalación',
                'href' => Route::has('pedidos.index') ? route('pedidos.index') : '/pedidos',
                'tone' => 'blue',
            ];
        }

        if ($user->tienePermiso('cobros.ver') || $user->tienePermiso('cobros-servicios.ver')) {
            $cobrosHoy = Cobro::query()
                ->where('usuario_id', $user->usuario_id)
                ->whereDate('fecha_pago', now()->toDateString());
            $cantidad = (clone $cobrosHoy)->count();
            $monto = (float) (clone $cobrosHoy)->sum('monto');
            $kpis[] = [
                'label' => 'Mis cobros hoy',
                'value' => number_format($cantidad),
                'hint' => number_format($monto, 0, ',', '.').' PYG',
                'href' => Route::has('cobros.index') ? route('cobros.index') : '/cobros',
                'tone' => 'amber',
            ];
        }

        if ($user->tienePermiso('pagos-pendientes.ver')) {
            $pendientes = FacturaInterna::query()
                ->where('estado', 'pendiente')
                ->count();
            $kpis[] = [
                'label' => 'Pendiente cobro',
                'value' => number_format($pendientes),
                'hint' => 'Facturas internas abiertas',
                'href' => Route::has('factura-internas.pendientes')
                    ? route('factura-internas.pendientes')
                    : '/factura-internas/pendientes',
                'tone' => 'emerald',
            ];
        }

        if ($user->tienePermiso('servicios-lista.ver') || $user->tienePermiso('servicios.ver')) {
            $activos = Servicio::where('estado', Servicio::ESTADO_ACTIVO)->count();
            $kpis[] = [
                'label' => 'Servicios activos',
                'value' => number_format($activos),
                'hint' => 'En la red',
                'href' => Route::has('servicios.index') ? route('servicios.index') : '/servicios',
                'tone' => 'teal',
            ];
        }

        return $kpis;
    }

    /**
     * @return list<array{label: string, href: string, icon: string, tone: string}>
     */
    private function obtenerAccionesRapidasStaff(User $user): array
    {
        $acciones = [];

        if ($user->tienePermiso('cobros.crear') || $user->tienePermiso('cobros-servicios.crear') || $user->tienePermiso('cobros-servicios.ver')) {
            $acciones[] = [
                'label' => 'Registrar cobro',
                'href' => Route::has('cobros.servicios') ? route('cobros.servicios') : '/cobros/servicios',
                'icon' => 'currency',
                'tone' => 'amber',
            ];
        }
        if ($user->tienePermiso('clientes.crear') || $user->tienePermiso('clientes-lista.crear')) {
            $acciones[] = [
                'label' => 'Nuevo cliente',
                'href' => Route::has('clientes.create') ? route('clientes.create') : '/clientes/create',
                'icon' => 'users',
                'tone' => 'blue',
            ];
        }
        if ($user->tienePermiso('tickets.crear')) {
            $acciones[] = [
                'label' => 'Nuevo ticket',
                'href' => Route::has('tickets.create') ? route('tickets.create') : '/tickets/create',
                'icon' => 'ticket',
                'tone' => 'rose',
            ];
        }
        if ($user->tienePermiso('pedidos.crear') || $user->tienePermiso('clientes-pedidos.crear')) {
            $acciones[] = [
                'label' => 'Nuevo pedido',
                'href' => Route::has('pedidos.create') ? route('pedidos.create') : '/pedidos/create',
                'icon' => 'clipboard-list',
                'tone' => 'violet',
            ];
        }
        if ($user->tienePermiso('servicios.crear') || $user->tienePermiso('servicios-lista.crear')) {
            $acciones[] = [
                'label' => 'Nuevo servicio',
                'href' => Route::has('servicios.create') ? route('servicios.create') : '/servicios/create',
                'icon' => 'wifi',
                'tone' => 'emerald',
            ];
        }

        return $acciones;
    }

    /**
     * Show the application home page.
     *
     * @return \Illuminate\View\View
     */
    public function index()
    {
        $user = Auth::user();

        $stats = $this->obtenerStats();
        $systemStatus = $this->obtenerEstadoSistema($stats);
        $ticketAvatares = $this->obtenerAvataresTicketsAbiertos();
        $recentActivity = $this->obtenerActividadReciente();
        $cobrosMesVentanaQuery = CobrosMesVentana::queryParamsDesdeRangos(CobrosMesVentana::rangosMesActual());

        return view('home', compact('user', 'stats', 'systemStatus', 'ticketAvatares', 'recentActivity', 'cobrosMesVentanaQuery'));
    }

    /**
     * Devuelve las estadísticas del dashboard en JSON (para SPA/Vue).
     */
    public function stats()
    {
        $stats = $this->obtenerStats();
        $stats['cobros_mes_ventana_query'] = CobrosMesVentana::queryParamsDesdeRangos(CobrosMesVentana::rangosMesActual());
        $stats['sistema'] = $this->obtenerEstadoSistema($stats);

        return response()->json($stats);
    }

    /**
     * @return array<string, int|float>
     */
    private function obtenerStats(): array
    {
        $serviciosActivos = Servicio::where('estado', Servicio::ESTADO_ACTIVO)->count();
        $serviciosSuspendidos = Servicio::where('estado', Servicio::ESTADO_SUSPENDIDO)->count();
        $serviciosCortados = Servicio::where('estado', Servicio::ESTADO_CORTADO)->count();
        $serviciosOperativos = $serviciosActivos + $serviciosSuspendidos + $serviciosCortados;
        $indiceSalud = $serviciosOperativos > 0
            ? round(($serviciosActivos / $serviciosOperativos) * 100, 1)
            : 100.0;

        $mesAnterior = now()->subMonth();
        $instaladosMes = Servicio::whereMonth('fecha_instalacion', now()->month)
            ->whereYear('fecha_instalacion', now()->year)
            ->distinct()
            ->count('cliente_id');
        $instaladosMesAnterior = Servicio::whereMonth('fecha_instalacion', $mesAnterior->month)
            ->whereYear('fecha_instalacion', $mesAnterior->year)
            ->distinct()
            ->count('cliente_id');
        $variacionInstalados = $instaladosMesAnterior > 0
            ? (int) round((($instaladosMes - $instaladosMesAnterior) / $instaladosMesAnterior) * 100)
            : ($instaladosMes > 0 ? 100 : 0);
        $progresoInstalados = $instaladosMesAnterior > 0
            ? min(100, (int) round(($instaladosMes / $instaladosMesAnterior) * 100))
            : ($instaladosMes > 0 ? 100 : 0);

        return [
            'clientes' => Cliente::where('estado', 'activo')->count(),
            'servicios' => $serviciosActivos,
            'servicios_suspendidos' => $serviciosSuspendidos,
            'servicios_cortados' => $serviciosCortados,
            'indice_salud' => $indiceSalud,
            'facturacion' => $this->totalCobrosMesActualDesdeCobros(),
            'tickets' => Ticket::whereIn('estado', ['pendiente', 'en_proceso'])->count(),
            'clientes_instalados_hoy' => Servicio::whereDate('fecha_instalacion', now()->toDateString())
                ->distinct()
                ->count('cliente_id'),
            'clientes_instalados_mes' => $instaladosMes,
            'instalados_variacion' => $variacionInstalados,
            'instalados_progreso' => $progresoInstalados,
            'instalados_mes_anterior' => $instaladosMesAnterior,
        ];
    }

    /**
     * Asignados con más tickets abiertos (para avatares del KPI).
     *
     * @return array{items: list<array{id: int, name: string, iniciales: string, color: string}>, extra: int}
     */
    private function obtenerAvataresTicketsAbiertos(): array
    {
        $colores = ['bg-blue-500', 'bg-violet-500', 'bg-emerald-500', 'bg-amber-500', 'bg-rose-500'];

        $ranking = Ticket::query()
            ->whereIn('estado', ['pendiente', 'en_proceso'])
            ->whereNotNull('asignado_id')
            ->selectRaw('asignado_id, COUNT(*) as total')
            ->groupBy('asignado_id')
            ->orderByDesc('total')
            ->get();

        $mostrar = 3;
        $topIds = $ranking->take($mostrar)->pluck('asignado_id');
        $usuarios = User::query()
            ->whereIn('usuario_id', $topIds)
            ->get()
            ->keyBy('usuario_id');

        $items = [];
        foreach ($topIds as $i => $id) {
            $u = $usuarios->get($id);
            if (! $u) {
                continue;
            }
            $items[] = [
                'id' => (int) $u->usuario_id,
                'name' => (string) $u->name,
                'iniciales' => $this->inicialesNombre($u->name),
                'color' => $colores[$i % count($colores)],
            ];
        }

        return [
            'items' => $items,
            'extra' => max(0, $ranking->count() - count($items)),
        ];
    }

    private function inicialesNombre(string $nombre): string
    {
        $partes = preg_split('/\s+/u', trim($nombre), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $iniciales = collect($partes)
            ->take(2)
            ->map(fn (string $p) => mb_strtoupper(mb_substr($p, 0, 1)))
            ->implode('');

        return $iniciales !== '' ? $iniciales : '?';
    }

    /**
     * @param  array<string, int|float>  $stats
     * @return array{operativo: bool, etiqueta: string, salud: float, items: list<array{label: string, detail: string, ok: bool, syncing?: bool, href?: string|null}>}
     */
    private function obtenerEstadoSistema(array $stats): array
    {
        $routersTotal = Router::count();
        $routersActivos = Router::where('estado', 'activo')->count();
        $oltsTotal = Olt::count();
        $oltsActivos = Olt::where('estado', 'activo')->count();
        $oltsConErrorLista = Olt::query()
            ->whereNotNull('onus_sync_error')
            ->where('onus_sync_error', '!=', '')
            ->orderBy('codigo')
            ->get(['olt_id', 'codigo', 'ip', 'onus_sync_error']);
        $oltsConError = $oltsConErrorLista->count();

        $salud = (float) ($stats['indice_salud'] ?? 100);
        $operativo = $salud >= 90 && $oltsConError === 0;

        $oltHref = Route::has('sistema.olts.index') ? route('sistema.olts.index') : null;
        $oltDetail = 'Sin OLTs';
        if ($oltsTotal > 0) {
            if ($oltsConError > 0) {
                $primero = $oltsConErrorLista->first();
                $nombre = $primero->codigo ?: ('OLT #'.$primero->olt_id);
                $err = Str::limit(trim((string) $primero->onus_sync_error), 90, '…');
                $oltDetail = $oltsConError === 1
                    ? "{$nombre}: {$err}"
                    : "{$oltsConError} con error · {$nombre}: {$err}";
                if ($oltsConError === 1 && Route::has('sistema.olts.show')) {
                    $oltHref = route('sistema.olts.show', $primero);
                }
            } else {
                $oltDetail = "{$oltsActivos}/{$oltsTotal} activos";
            }
        }

        $routerHref = Route::has('sistema.routers.index') ? route('sistema.routers.index') : null;

        return [
            'operativo' => $operativo,
            'etiqueta' => $operativo ? 'Operativo' : 'Atención',
            'salud' => $salud,
            'items' => [
                [
                    'label' => 'Routers MikroTik',
                    'detail' => $routersTotal > 0
                        ? ($routersActivos > 0
                            ? "{$routersActivos}/{$routersTotal} activos"
                            : "{$routersTotal} en inventario")
                        : 'Sin routers',
                    'ok' => $routersTotal > 0,
                    'syncing' => false,
                    'href' => $routerHref,
                ],
                [
                    'label' => 'OLTs GPON',
                    'detail' => $oltDetail,
                    'ok' => $oltsTotal === 0 || ($oltsActivos > 0 && $oltsConError === 0),
                    'syncing' => $oltsConError > 0,
                    'href' => $oltHref,
                ],
                [
                    'label' => 'Servicios en red',
                    'detail' => number_format((float) $stats['servicios']).' activos · '
                        .number_format((float) ($stats['servicios_suspendidos'] ?? 0)).' susp.',
                    'ok' => $salud >= 90,
                    'syncing' => $salud < 90 && $salud >= 75,
                    'href' => Route::has('servicios.index') ? route('servicios.index') : null,
                ],
            ],
        ];
    }

    /**
     * Total cobrado del ciclo mensual vigente usando cobros.monto
     * (ventana 20→fin de mes; excluye pago y factura posteriores al día 20 del mes actual).
     */
    private function totalCobrosMesActualDesdeCobros(): float
    {
        return \App\Models\CobroResumen::totalCobradoParaMes();
    }

    /**
     * Obtiene las últimas actividades desde la auditoría.
     *
     * @return array<int, array{id: int, title: string, subtitle: string, time: string, color: string}>
     */
    private function obtenerActividadReciente(): array
    {
        $auditorias = Auditoria::with('usuario')
            ->orderBy('created_at', 'desc')
            ->limit(8)
            ->get();

        return $auditorias->map(function (Auditoria $a) {
            $tabla = self::TABLA_LABELS[$a->tabla] ?? $a->tabla;
            $accion = self::ACCION_LABELS[$a->accion] ?? $a->accion;
            $title = "{$tabla} {$accion}";
            if ($a->usuario) {
                $title .= " por {$a->usuario->name}";
            }

            $registro = $a->registro_id ? "#{$a->registro_id}" : '';

            return [
                'id' => $a->auditoria_id,
                'title' => $title,
                'subtitle' => trim("{$tabla} {$registro}"),
                'time' => $a->created_at->diffForHumans(),
                'color' => self::ACCION_COLORS[$a->accion] ?? 'bg-gray-500',
            ];
        })->all();
    }
}
