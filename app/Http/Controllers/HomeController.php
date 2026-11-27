<?php

namespace App\Http\Controllers;

use App\Models\Auditoria;
use App\Models\Cliente;
use App\Models\Cobro;
use App\Models\FacturaInterna;
use App\Models\NodoApWireless;
use App\Models\Olt;
use App\Models\OltOnu;
use App\Models\Pedido;
use App\Models\Router;
use App\Models\SenalLectura;
use App\Models\Servicio;
use App\Models\ServicioConexionEvento;
use App\Models\Tarea;
use App\Models\Ticket;
use App\Models\TvCuenta;
use App\Models\User;
use App\Support\CobrosMesVentana;
use App\Support\HerramientasRedPayload;
use App\Support\MenuUsuario;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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
     * La dirección vieja /inicio abre el mismo dashboard del rol.
     */
    public function inicio(Request $request)
    {
        if (! Auth::user() instanceof User) {
            return redirect()->route('login');
        }

        return redirect()->route('home');
    }

    /**
     * Datos del panel operativo (/inicio y pestaña Cajero).
     *
     * @return array{user: User, links: mixed, kpis: list<array<string, mixed>>, accionesRapidas: list<array<string, mixed>>}
     */
    private function datosPanelInicio(User $user, bool $conServicios = true): array
    {
        $user->loadMissing('rol');

        return [
            'user' => $user,
            'links' => MenuUsuario::enlacesPlanos($user),
            'kpis' => $this->obtenerKpisStaff($user, $conServicios),
            'accionesRapidas' => $this->obtenerAccionesRapidasStaff($user),
        ];
    }

    /**
     * KPIs relevantes según permisos del usuario (no admin).
     *
     * @return list<array{label: string, value: string, hint: string, href: string|null, tone: string}>
     */
    private function obtenerKpisStaff(User $user, bool $conServicios = true): array
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

        if ($conServicios && ($user->tienePermiso('servicios-lista.ver') || $user->tienePermiso('servicios.ver'))) {
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
    public function index(Request $request)
    {
        $user = Auth::user();
        if (! $user instanceof User) {
            return redirect()->route('login');
        }

        return $this->respuestaPanel($request, $user);
    }

    /**
     * @return \Illuminate\View\View
     */
    private function respuestaPanel(Request $request, User $user)
    {
        $user->loadMissing('rol');
        $tab = $this->tabDashboard($request, $user);
        $datos = [
            'user' => $user,
            'tab' => $tab,
            'puedeCambiarPanel' => $user->esAdministrador(),
        ];

        if ($tab === 'tecnico') {
            $datos['senal'] = $this->obtenerDashboardSenal();
            $datos['infra'] = $this->obtenerEstadoInfra($user);
            $datos['atencion'] = app(ServicioPppoeEventoController::class)->lineasEnAtencion(Request::create('/', 'GET'));
            $datos['tvRenovar'] = TvCuenta::paraRenovarEnDashboard();
            $datos['tvRenovarDias'] = TvCuenta::diasAnticipoRenovacionDashboard();
            $datos['observacionesEquipo'] = app(\App\Services\Red\EquipoObservacionInforme::class)->filas();
            $datos['puedeObservarEquipo'] = $user->tienePermiso('servicios.editar') || $user->tienePermiso('servicios.crear');
        } elseif ($tab === 'cajero' || $tab === 'operativo') {
            $datos = array_merge($datos, $this->datosPanelInicio($user, $tab !== 'cajero'));
        } else {
            $stats = $this->obtenerStats();
            $datos['stats'] = $stats;
            $datos['systemStatus'] = $this->obtenerEstadoSistema($stats);
            $datos['ticketAvatares'] = $this->obtenerAvataresTicketsAbiertos();
            $datos['recentActivity'] = $this->obtenerActividadReciente();
            $datos['cobrosMesVentanaQuery'] = CobrosMesVentana::queryParamsDesdeRangos(CobrosMesVentana::rangosMesActual());
        }

        return view('home', $datos);
    }

    private function tabDashboard(Request $request, User $user): string
    {
        if (! $user->esAdministrador()) {
            return $this->panelPropio($user);
        }

        $pedido = (string) $request->query('tab', '');
        if (in_array($pedido, ['administrador', 'tecnico', 'cajero'], true)) {
            return $pedido;
        }

        return 'administrador';
    }

    private function panelPropio(User $user): string
    {
        if ($this->esTecnico($user)) {
            return 'tecnico';
        }
        if ($this->esCajero($user)) {
            return 'cajero';
        }
        if ($user->tienePermiso('dashboard.ver')) {
            return 'administrador';
        }

        return 'operativo';
    }

    private function esTecnico(User $user): bool
    {
        return str_contains($this->rolNormalizado($user), 'tecnico');
    }

    private function esCajero(User $user): bool
    {
        return str_contains($this->rolNormalizado($user), 'cajero');
    }

    private function rolNormalizado(User $user): string
    {
        $rol = mb_strtolower(trim((string) ($user->rol?->descripcion ?? '')), 'UTF-8');

        return strtr($rol, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u']);
    }

    /**
     * Routers, OLTs y APs según el último ping o la última sincronización.
     *
     * @return list<array{titulo: string, en_linea: int, total: int, caidos: list<string>, href: ?string}>
     */
    private function obtenerEstadoInfra(User $user): array
    {
        $routers = Router::query()->orderBy('nombre')->get(['router_id', 'nombre', 'ip', 'estado']);
        $routersCaidos = $routers
            ->filter(fn (Router $router) => ! $router->estaConectado())
            ->map(fn (Router $router) => $router->nombre ?: ($router->ip ?: 'Router #'.$router->router_id))
            ->values()
            ->all();

        $olts = Olt::query()->orderBy('codigo')->get(['olt_id', 'codigo', 'ip', 'estado', 'onus_sync_error']);
        $oltsCaidos = $olts
            ->filter(function (Olt $olt) {
                $error = trim((string) $olt->onus_sync_error);

                return strtolower((string) $olt->estado) !== 'activo' || $error !== '';
            })
            ->map(fn (Olt $olt) => $olt->codigo ?: ($olt->ip ?: 'OLT #'.$olt->olt_id))
            ->values()
            ->all();

        $aps = NodoApWireless::query()->where('activo', true)->orderBy('nombre')->get(['ap_id', 'nombre', 'ip', 'ping_ok']);
        $apsCaidos = $aps
            ->filter(fn (NodoApWireless $ap) => $ap->ping_ok !== true)
            ->map(fn (NodoApWireless $ap) => $ap->nombre ?: ($ap->ip ?: 'AP #'.$ap->ap_id))
            ->values()
            ->all();

        return [
            [
                'titulo' => 'Routers',
                'en_linea' => $routers->count() - count($routersCaidos),
                'total' => $routers->count(),
                'caidos' => $routersCaidos,
                'href' => $this->enlaceInfra($user, 'sistema.ver', 'sistema.routers.index'),
            ],
            [
                'titulo' => 'OLTs',
                'en_linea' => $olts->count() - count($oltsCaidos),
                'total' => $olts->count(),
                'caidos' => $oltsCaidos,
                'href' => $this->enlaceInfra($user, 'sistema.ver', 'sistema.olts.index'),
            ],
            [
                'titulo' => 'APs',
                'en_linea' => $aps->count() - count($apsCaidos),
                'total' => $aps->count(),
                'caidos' => $apsCaidos,
                'href' => $this->enlaceInfra($user, 'sistema-aps-wireless.ver', 'sistema.aps-wireless.index'),
            ],
        ];
    }

    private function enlaceInfra(User $user, string $permiso, string $ruta): ?string
    {
        if (! $user->tienePermiso('sistema.ver') || ! $user->tienePermiso($permiso) || ! Route::has($ruta)) {
            return null;
        }

        return route($ruta);
    }

    /**
     * Potencias ópticas actuales y última señal de antena por servicio.
     *
     * @return array<string, mixed>
     */
    private function obtenerDashboardSenal(): array
    {
        $base = OltOnu::query()->registradas();
        $total = (clone $base)->count();
        $conRx = (clone $base)->whereNotNull('rx_power_dbm')->count();
        $optima = (clone $base)->where('rx_power_dbm', '>=', -27)->where('rx_power_dbm', '<=', -8)->count();
        $baja = (clone $base)->where('rx_power_dbm', '<', -27)->count();
        $alta = (clone $base)->where('rx_power_dbm', '>', -8)->count();
        $online = (clone $base)
            ->whereRaw('LOWER(COALESCE(estado, "")) in (?, ?, ?, ?)', ['working', 'online', 'up', 'active'])
            ->count();
        $ultimaSyncRaw = (clone $base)->max('synced_at');
        $ultimaSync = $ultimaSyncRaw ? Carbon::parse($ultimaSyncRaw)->diffForHumans() : null;

        $onusConRx = OltOnu::query()
            ->registradas()
            ->whereNotNull('rx_power_dbm')
            ->get(['descripcion', 'rx_power_dbm']);

        $claves = $onusConRx
            ->map(fn (OltOnu $onu) => trim((string) $onu->descripcion))
            ->filter(fn (string $clave) => $clave !== '' && mb_strtolower($clave) !== 'unknown')
            ->unique()
            ->values();
        $serviciosPorPppoe = $claves->isEmpty()
            ? collect()
            : Servicio::query()
                ->with('cliente:cliente_id,nombre,apellido')
                ->whereIn('usuario_pppoe', $claves->all())
                ->get()
                ->keyBy(fn (Servicio $servicio) => mb_strtolower(trim((string) $servicio->usuario_pppoe)));

        $grupos = [];
        foreach ($onusConRx as $onu) {
            $dbm = (int) round((float) $onu->rx_power_dbm);
            $descripcion = trim((string) $onu->descripcion);
            $servicio = $descripcion !== ''
                ? $serviciosPorPppoe->get(mb_strtolower($descripcion))
                : null;
            $cliente = $servicio?->cliente;
            $nombre = trim(((string) ($cliente->nombre ?? '')).' '.((string) ($cliente->apellido ?? '')));
            if ($nombre === '') {
                $nombre = ($descripcion !== '' && mb_strtolower($descripcion) !== 'unknown')
                    ? $descripcion
                    : 'Sin identificar';
            }
            $grupos[$dbm][] = $nombre;
        }

        krsort($grupos);
        $distribucionOptica = [];
        foreach ($grupos as $dbm => $nombres) {
            sort($nombres, SORT_NATURAL | SORT_FLAG_CASE);
            $distribucionOptica[] = [
                'dbm' => (int) $dbm,
                'cantidad' => count($nombres),
                'clientes' => array_values($nombres),
            ];
        }

        $ultimosAntena = DB::table('servicio_conexion_eventos')
            ->selectRaw('servicio_id, MAX(servicio_conexion_evento_id) as id')
            ->where('tipo', ServicioConexionEvento::TIPO_SENAL_ANTENA)
            ->whereNotNull('antena_signal_dbm')
            ->groupBy('servicio_id');

        $filasAntena = DB::table('servicio_conexion_eventos as e')
            ->joinSub($ultimosAntena, 'u', 'u.id', '=', 'e.servicio_conexion_evento_id')
            ->leftJoin('servicios as s', 's.servicio_id', '=', 'e.servicio_id')
            ->leftJoin('clientes as c', 'c.cliente_id', '=', 's.cliente_id')
            ->where(function ($q) {
                $q->whereNull('s.estado')->orWhere('s.estado', '!=', Servicio::ESTADO_CANCELADO);
            })
            ->get(['e.servicio_id', 'e.antena_signal_dbm', 'c.nombre', 'c.apellido']);

        $serviciosAntena = Servicio::query()
            ->with(HerramientasRedPayload::relaciones())
            ->whereIn('servicio_id', $filasAntena->pluck('servicio_id')->filter()->unique()->all())
            ->get()
            ->keyBy('servicio_id');
        $filasAntena = $filasAntena->filter(function ($fila) use ($serviciosAntena) {
            $servicio = $serviciosAntena->get($fila->servicio_id);

            return $servicio && HerramientasRedPayload::esAntena($servicio);
        })->values();

        $buenaAntena = 0;
        $regularAntena = 0;
        $debilAntena = 0;
        $gruposAntena = [];
        foreach ($filasAntena as $fila) {
            if ($fila->antena_signal_dbm === null) {
                continue;
            }
            $valor = (float) $fila->antena_signal_dbm;
            if ($valor >= -65) {
                $buenaAntena++;
            } elseif ($valor >= -75) {
                $regularAntena++;
            } else {
                $debilAntena++;
            }
            $dbm = (int) round((float) $fila->antena_signal_dbm);
            $nombre = trim(((string) ($fila->nombre ?? '')).' '.((string) ($fila->apellido ?? '')));
            if ($nombre === '') {
                $nombre = 'Servicio #'.(int) $fila->servicio_id;
            }
            $gruposAntena[$dbm][] = $nombre;
        }

        krsort($gruposAntena);
        $distribucionAntena = [];
        foreach ($gruposAntena as $dbm => $nombres) {
            sort($nombres, SORT_NATURAL | SORT_FLAG_CASE);
            $distribucionAntena[] = [
                'dbm' => (int) $dbm,
                'cantidad' => count($nombres),
                'clientes' => array_values($nombres),
            ];
        }

        return [
            'optica' => [
                'total' => $total,
                'optima' => $optima,
                'baja' => $baja,
                'alta' => $alta,
                'sin' => max(0, $total - $conRx),
                'online' => $online,
            ],
            'antena' => [
                'total' => $buenaAntena + $regularAntena + $debilAntena,
                'buena' => $buenaAntena,
                'regular' => $regularAntena,
                'debil' => $debilAntena,
            ],
            'distribucion_optica' => $distribucionOptica,
            'distribucion_antena' => $distribucionAntena,
            'lectura' => SenalLectura::query()->orderByDesc('id')->first(),
            'ultima_sync' => $ultimaSync,
        ];
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
        $auditorias = Auditoria::query()
            ->deStaff()
            ->with('usuario')
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
