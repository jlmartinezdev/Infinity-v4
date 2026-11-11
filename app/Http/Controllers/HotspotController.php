<?php

namespace App\Http\Controllers;

use App\Helpers\MapsUrlHelper;
use App\Models\Cliente;
use App\Models\HotspotPerfil;
use App\Models\MikrotikOperacionPendiente;
use App\Models\Router;
use App\Models\Servicio;
use App\Models\ServicioHotspot;
use App\Services\MikroTikService;
use App\Services\RadiusHotspotSyncService;
use App\Support\HotspotDuracion;
use App\Support\HotspotSesionOrigen;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class HotspotController extends Controller
{
    private const SESSION_DASHBOARD_ROUTER = 'hotspot_dashboard_router_id';

    /**
     * Dashboard: quién está vivo en el hotspot de un router.
     */
    public function dashboard(Request $request, MikroTikService $mikrotik, RadiusHotspotSyncService $radius)
    {
        $routers = Router::query()->with('nodo')->orderBy('nombre')->get();
        $routersPorNodo = $this->routersPorNodoWatch($routers);
        $vista = $this->vistaHotspot($request);
        $routerId = (int) $request->query('router_id', 0);

        if ($routerId <= 0) {
            $ultimo = (int) $request->session()->get(self::SESSION_DASHBOARD_ROUTER, 0);
            if ($ultimo > 0 && $routers->contains(fn (Router $r) => (int) $r->router_id === $ultimo)) {
                $params = $request->query();
                $params['router_id'] = $ultimo;
                $params['vista'] = $vista;

                return redirect()->route('hotspot.dashboard', $params);
            }
        }

        $selectedRouter = $routerId > 0
            ? $routers->first(fn (Router $r) => (int) $r->router_id === $routerId)
            : null;

        if ($selectedRouter) {
            $request->session()->put(self::SESSION_DASHBOARD_ROUTER, (int) $selectedRouter->router_id);
        } elseif ($routerId > 0) {
            $request->session()->forget(self::SESSION_DASHBOARD_ROUTER);
        }

        $routerError = null;
        $consultadoEn = null;
        $activeHosts = [];
        $mapped = collect();
        $sesiones = [];
        $offline = collect();
        $consumo = [];
        $ultimasConexiones = [];
        $macsBloqueadas = [];
        $clientes = null;
        $seleccionado = null;
        $esRecientes = false;
        $verTodas = false;
        $totalClientes = 0;
        $perfiles = null;

        if ($vista === 'usuarios') {
            [
                'clientes' => $clientes,
                'seleccionado' => $seleccionado,
                'esRecientes' => $esRecientes,
                'consumo' => $consumo,
                'verTodas' => $verTodas,
                'totalClientes' => $totalClientes,
            ] = $this->datosUsuarios($request, $radius, $mikrotik);
        } elseif ($vista === 'perfiles') {
            $perfiles = $this->datosPerfiles($request);
        } elseif ($selectedRouter) {
            $consultadoEn = now();
            try {
                $activeHosts = $mikrotik->getHotspotActiveHosts($selectedRouter, $selectedRouter->hotspot_servidor);
                if (! is_array($activeHosts)) {
                    $activeHosts = [];
                }
            } catch (\Throwable $e) {
                $activeHosts = [];
                $routerError = $e->getMessage();
            }
        }

        if ($vista === 'sesiones' && $selectedRouter) {
            $mapped = ServicioHotspot::query()
                ->with(['servicio.cliente', 'cliente', 'hotspotPerfil', 'router'])
                ->where(function ($q) use ($selectedRouter) {
                    $q->where('router_id', $selectedRouter->router_id);
                    if (filled($selectedRouter->hotspot_servidor)) {
                        $q->orWhereNull('router_id');
                    }
                })
                ->orderBy('username')
                ->get();
        }

        if ($vista === 'sesiones') {
        $todosPorUsername = ServicioHotspot::query()
            ->with(['servicio.cliente', 'cliente', 'hotspotPerfil', 'router'])
            ->get()
            ->keyBy(fn (ServicioHotspot $h) => mb_strtolower((string) $h->username));

        $vivosKeys = [];
        $sesiones = [];
        $vivoBytes = [];
        $hostsVivos = [];
        $macsVivas = [];

        foreach ($activeHosts as $host) {
            if (! is_array($host)) {
                continue;
            }
            $user = trim((string) ($host['user'] ?? $host['username'] ?? ''));
            $hostsVivos[] = [$host, $user];
            if (HotspotSesionOrigen::esMac($user)) {
                $macsVivas[] = $user;
            }
        }

        $cuentaPorMac = $radius->usuariosRealesPorMacs($macsVivas);

        foreach ($hostsVivos as [$host, $user]) {
            if (HotspotSesionOrigen::esMac($user)) {
                $real = $cuentaPorMac[HotspotSesionOrigen::macNormalizada($user)] ?? null;
                if (is_string($real) && $real !== '') {
                    $user = $real;
                }
            }
            $key = mb_strtolower($user);
            $match = $key !== '' ? $todosPorUsername->get($key) : null;
            if ($key !== '') {
                $vivosKeys[$key] = true;
            }
            if ($user !== '') {
                $vivoBytes[$user] = ($vivoBytes[$user] ?? 0) + $this->bytesHostHotspot($host);
            }
            $sesiones[] = $this->filaSesionViva($host, $user, $match);
        }

        $offline = $mapped
            ->reject(fn (ServicioHotspot $h) => isset($vivosKeys[mb_strtolower((string) $h->username)]))
            ->values();

        $usernames = collect($sesiones)
            ->pluck('username')
            ->merge($offline->pluck('username'))
            ->map(fn ($u) => trim((string) $u))
            ->filter(fn (string $u) => $u !== '' && $u !== '—')
            ->unique()
            ->values()
            ->all();
        $consumo = RadiusHotspotSyncService::combinarConsumoSesionViva(
            $radius->bytesRadacctPorUsuarios($usernames),
            $vivoBytes
        );

        foreach ($sesiones as $i => $fila) {
            $locker = $fila['locker'];
            $user = (string) $fila['username'];
            $bytes = (int) ($consumo[$user] ?? 0);
            $cuotaFila = $locker
                ? RadiusHotspotSyncService::consumoVista($bytes, $locker->hotspotPerfil?->cuota_gb)
                : [
                    'etiqueta' => '',
                    'porcentaje' => 0,
                    'centro_num' => '0',
                    'centro_unit' => 'MB',
                    'tiene_cuota' => false,
                ];
            $sesiones[$i]['perfil'] = $locker?->hotspotPerfil?->nombreDistintoDeCuota() ?? '';
            $sesiones[$i]['consumo'] = $cuotaFila['etiqueta'];
            $sesiones[$i]['cuota'] = $cuotaFila;
            $sesiones[$i]['trial_tiempo'] = false;
            if (($fila['origen'] ?? '') === 'trial') {
                $tiempo = HotspotDuracion::vistaRestante(
                    (string) ($fila['uptime'] ?? ''),
                    (string) ($fila['session_time_left'] ?? '')
                );
                if ($tiempo !== null) {
                    $sesiones[$i]['perfil'] = 'Trial';
                    $sesiones[$i]['cuota'] = $tiempo;
                    $sesiones[$i]['trial_tiempo'] = true;
                }
            }
        }

        $ultimasConexiones = [];
        foreach ($radius->ultimasConexionesPorUsuarios($offline->pluck('username')->all()) as $usuario => $ultima) {
            $ultimasConexiones[$usuario] = [
                'texto' => HotspotDuracion::haceCuanto($ultima['cuando']),
                'fecha' => $ultima['cuando']->format('d/m/Y H:i'),
                'mac' => $ultima['mac'],
            ];
        }
        }

        return view('hotspot.dashboard', [
            'ultimasConexiones' => $ultimasConexiones,
            'routers' => $routers,
            'routersPorNodo' => $routersPorNodo,
            'selectedRouter' => $selectedRouter,
            'sesiones' => $sesiones,
            'offline' => $offline,
            'mappedCount' => $mapped->count(),
            'consumo' => $consumo,
            'routerError' => $routerError,
            'consultadoEn' => $consultadoEn,
            'macsBloqueadas' => $vista === 'sesiones' ? $this->macsBloqueadas($selectedRouter, $routerError, $mikrotik) : [],
            'vista' => $vista,
            'clientes' => $clientes,
            'seleccionado' => $seleccionado,
            'esRecientes' => $esRecientes,
            'verTodas' => $verTodas,
            'totalClientes' => $totalClientes,
            'perfiles' => $perfiles,
        ]);
    }

    public function desconectar(Request $request, Router $router, MikroTikService $mikrotik, RadiusHotspotSyncService $radius): RedirectResponse
    {
        $mac = $this->macAccion($request);
        if ($mac instanceof RedirectResponse) {
            return $mac;
        }

        try {
            $abierta = $radius->sesionAbiertaIdPorMac($mac);
            $cerradas = $mikrotik->cerrarSesionHotspotPorMac($router, $mac);
        } catch (\Throwable $e) {
            return $this->volverAlDashboard($router, 'error', 'No pude desconectar '.$mac.'.');
        }

        if ($cerradas < 1) {
            return $this->volverAlDashboard($router, 'error', $mac.' ya no está en línea.');
        }

        if ($abierta) {
            $radius->marcarCierreComoSalidaPortal($abierta);
        }

        return $this->volverAlDashboard($router, 'success', 'Sesión de '.$mac.' cerrada.');
    }

    public function bloquear(Request $request, Router $router, MikroTikService $mikrotik): RedirectResponse
    {
        $mac = $this->macAccion($request);
        if ($mac instanceof RedirectResponse) {
            return $mac;
        }

        try {
            $resultado = $mikrotik->bloquearMacHotspot($router, $mac);
            $mikrotik->cerrarSesionHotspotPorMac($router, $mac);
        } catch (\Throwable $e) {
            return $this->volverAlDashboard($router, 'error', 'No pude bloquear '.$mac.'.');
        }

        $aviso = ! empty($resultado['ya'])
            ? $mac.' ya estaba bloqueada.'
            : $mac.' bloqueada. No entra al hotspot hasta que la desbloquees.';

        return $this->volverAlDashboard($router, 'success', $aviso);
    }

    public function desbloquear(Request $request, Router $router, MikroTikService $mikrotik): RedirectResponse
    {
        $mac = $this->macAccion($request);
        if ($mac instanceof RedirectResponse) {
            return $mac;
        }

        try {
            $quito = $mikrotik->desbloquearMacHotspot($router, $mac);
        } catch (\Throwable $e) {
            return $this->volverAlDashboard($router, 'error', 'No pude desbloquear '.$mac.'.');
        }

        return $this->volverAlDashboard(
            $router,
            $quito ? 'success' : 'error',
            $quito ? $mac.' desbloqueada.' : $mac.' no tenía un bloqueo.'
        );
    }

    /**
     * @param  array<string, mixed>  $host
     * @return array{
     *     username: string,
     *     ip: string,
     *     mac: string,
     *     uptime: string,
     *     cliente: string,
     *     cliente_id: int|null,
     *     servicio_id: int|null,
     *     estado: string,
     *     origen: string,
     *     locker: ServicioHotspot|null,
     *     perfil: string,
     *     consumo: string,
     *     cuota: array{etiqueta: string, porcentaje: float|null, centro_num: string, centro_unit: string, tiene_cuota: bool}
     * }
     */
    private function filaSesionViva(array $host, string $user, ?ServicioHotspot $match): array
    {
        $origen = $this->origenSesion($user, $match);
        $login = strtolower((string) ($host['login-by'] ?? ''));
        if ($origen !== 'locker' && str_contains($login, 'trial')) {
            $origen = 'trial';
        }
        $cliente = $origen === 'sin-sesion'
            ? 'Use una cuenta para iniciar sesión'
            : $this->nombreLocker($match);

        return [
            'username' => $user !== '' ? $user : '—',
            'ip' => (string) ($host['address'] ?? $host['ip'] ?? '—'),
            'mac' => (string) ($host['mac-address'] ?? $host['mac'] ?? '—'),
            'uptime' => (string) ($host['uptime'] ?? '—'),
            'uptime_legible' => HotspotDuracion::legible((string) ($host['uptime'] ?? '')),
            // En RouterOS bytes-in es lo que sube el equipo y bytes-out lo que baja.
            'bytes_subida' => $this->bytesCampoHotspot($host['bytes-in'] ?? $host['bytes_in'] ?? null),
            'bytes_bajada' => $this->bytesCampoHotspot($host['bytes-out'] ?? $host['bytes_out'] ?? null),
            'session_time_left' => (string) ($host['session-time-left'] ?? ''),
            'cliente' => $cliente,
            'cliente_id' => $match?->cliente_id ? (int) $match->cliente_id : null,
            'servicio_id' => $match?->servicio_id ? (int) $match->servicio_id : null,
            'estado' => (string) ($match?->servicio?->estado ?? ''),
            'origen' => $origen,
            'locker' => $match,
            'perfil' => '',
            'consumo' => '',
            'cuota' => [
                'etiqueta' => '',
                'porcentaje' => 0,
                'centro_num' => '0',
                'centro_unit' => 'MB',
                'tiene_cuota' => false,
            ],
        ];
    }

    /**
     * Agrupa por N2 / N3 extraído del nombre del router, no por cada fila de nodos.
     *
     * @param  \Illuminate\Support\Collection<int, Router>  $routers
     * @return \Illuminate\Support\Collection<string, \Illuminate\Support\Collection<int, Router>>
     */
    private function routersPorNodoWatch($routers)
    {
        return $routers
            ->groupBy(fn (Router $r) => $this->claveNodoWatch($r))
            ->sortBy(function ($grupo, $clave) {
                if ($clave === 'Sin nodo') {
                    return 999;
                }
                if (preg_match('/^N(\d+)$/', (string) $clave, $m)) {
                    return (int) $m[1];
                }

                return 500;
            })
            ->mapWithKeys(function ($grupo, $clave) {
                $ordenados = $grupo->sortBy(fn (Router $r) => mb_strtolower((string) $r->nombre))->values();

                return [$this->etiquetaNodoWatch((string) $clave, $ordenados) => $ordenados];
            });
    }

    private function claveNodoWatch(Router $r): string
    {
        if (preg_match('/N(\d+)/i', (string) $r->nombre, $m)) {
            return 'N'.$m[1];
        }
        $desc = trim((string) ($r->nodo?->descripcion ?? ''));
        if (preg_match('/N\s*(\d+)/i', $desc, $m)) {
            return 'N'.$m[1];
        }

        return $desc !== '' ? $desc : 'Sin nodo';
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Router>  $grupo
     */
    private function etiquetaNodoWatch(string $clave, $grupo): string
    {
        if (! preg_match('/^N\d+$/', $clave)) {
            return $clave;
        }
        $descs = $grupo->map(fn (Router $r) => trim((string) ($r->nodo?->descripcion ?? '')))
            ->filter()
            ->unique()
            ->values();
        if ($descs->count() === 1) {
            return $clave.' · '.$descs->first();
        }

        return $clave;
    }

    /**
     * @param  array<string, mixed>  $host
     */
    private function bytesHostHotspot(array $host): int
    {
        return (int) $this->bytesCampoHotspot($host['bytes-in'] ?? $host['bytes_in'] ?? 0)
            + (int) $this->bytesCampoHotspot($host['bytes-out'] ?? $host['bytes_out'] ?? 0);
    }

    /**
     * Contador de bytes de una sesión; null cuando el router no lo informa.
     */
    private function bytesCampoHotspot(mixed $valor): ?int
    {
        if ($valor === null || $valor === '') {
            return null;
        }
        $bytes = is_numeric($valor) ? (int) $valor : (int) preg_replace('/\D+/', '', (string) $valor);

        return max(0, $bytes);
    }

    private function origenSesion(string $user, ?ServicioHotspot $match): string
    {
        return HotspotSesionOrigen::de($user, $match !== null);
    }

    private function nombreLocker(?ServicioHotspot $hotspot): string
    {
        if (! $hotspot) {
            return '';
        }
        $cliente = $hotspot->cliente ?? $hotspot->servicio?->cliente;

        return trim((string) ($cliente?->nombre ?? '').' '.(string) ($cliente?->apellido ?? ''));
    }

    /**
     * Usuarios tal como están en la base FreeRADIUS (radcheck / radreply).
     */
    public function radius(Request $request, RadiusHotspotSyncService $radius): View
    {
        $buscar = trim((string) $request->input('buscar', ''));
        $listado = $radius->listarUsuarios($buscar !== '' ? $buscar : null);
        $locales = ServicioHotspot::query()
            ->with(['cliente', 'servicio'])
            ->get()
            ->keyBy(fn (ServicioHotspot $h) => (string) $h->username);

        $usuarios = [];
        foreach ($listado['usuarios'] as $fila) {
            $local = $locales->get($fila['username']);
            $fila['en_infinity'] = $local !== null;
            $fila['cliente'] = $local?->cliente
                ? trim($local->cliente->nombre.' '.$local->cliente->apellido)
                : null;
            $fila['cliente_id'] = $local?->cliente_id;
            $fila['servicio_id'] = $local?->servicio_id;
            $fila['servicio_estado'] = $local?->servicio?->estado;
            $usuarios[] = $fila;
        }

        $enRadius = collect($usuarios)->pluck('username');
        $soloInfinity = $locales
            ->reject(fn (ServicioHotspot $h) => $enRadius->contains((string) $h->username))
            ->values();

        return view('hotspot.radius', [
            'usuarios' => $usuarios,
            'soloInfinity' => $soloInfinity,
            'ok' => $listado['ok'],
            'error' => $listado['error'] ?? null,
            'buscar' => $buscar,
        ]);
    }

    /**
     * Mapa de servicios marcados como punto hotspot (ONU que emite).
     */
    public function mapa(): View
    {
        $servicios = Servicio::query()
            ->where('punto_hotspot', true)
            ->with([
                'cliente',
                'plan',
                'cajaNapPuertoActivo.cajaNap',
                'servicioHotspots',
            ])
            ->orderBy('servicio_id')
            ->get();

        $puntos = [];
        $sinCoordenadas = [];
        foreach ($servicios as $servicio) {
            $coords = $this->coordsPuntoHotspot($servicio);
            $cliente = $servicio->cliente;
            $nombre = trim(($cliente?->nombre ?? '').' '.($cliente?->apellido ?? ''));
            $nap = $servicio->cajaNapPuertoActivo?->cajaNap;
            $fila = [
                'servicio_id' => $servicio->servicio_id,
                'cliente_id' => $servicio->cliente_id,
                'nombre' => $nombre !== '' ? $nombre : 'Servicio #'.$servicio->servicio_id,
                'cedula' => trim((string) ($cliente?->cedula ?? '')),
                'plan' => $servicio->plan?->nombre,
                'onu' => $servicio->cpe_onu,
                'nap' => $nap?->codigo,
                'origen' => $coords['origen'],
                'usuarios' => $servicio->servicioHotspots->count(),
                'estado' => $servicio->estado,
                'url_cliente' => $cliente ? route('clientes.detalle', $cliente) : null,
                'url_hotspot' => $cliente ? route('hotspot.clientes.edit', $cliente) : null,
            ];

            if ($coords['lat'] === null || $coords['lon'] === null) {
                $sinCoordenadas[] = $fila;
                continue;
            }

            $fila['lat'] = $coords['lat'];
            $fila['lon'] = $coords['lon'];
            $puntos[] = $fila;
        }

        return view('hotspot.mapa', [
            'puntos' => $puntos,
            'sinCoordenadas' => $sinCoordenadas,
            'googleMapsApiKey' => config('services.google.maps_key'),
        ]);
    }

    /**
     * @return array{lat: float|null, lon: float|null, origen: string|null}
     */
    protected function coordsPuntoHotspot(Servicio $servicio): array
    {
        $nap = $servicio->cajaNapPuertoActivo?->cajaNap;
        if ($nap && $nap->lat !== null && $nap->lon !== null) {
            return ['lat' => (float) $nap->lat, 'lon' => (float) $nap->lon, 'origen' => 'nap'];
        }

        $url = trim((string) ($servicio->cliente?->url_ubicacion ?? ''));
        if ($url !== '') {
            $coords = MapsUrlHelper::extractLatLon($url);
            if ($coords['lat'] !== null && $coords['lon'] !== null) {
                return ['lat' => $coords['lat'], 'lon' => $coords['lon'], 'origen' => 'cliente'];
            }
        }

        return ['lat' => null, 'lon' => null, 'origen' => null];
    }

    /**
     * Cards por cliente: hasta 3 slots hotspot.
     */
    public function index(Request $request): RedirectResponse
    {
        return $this->redirigirAlMenu($request, 'usuarios');
    }

    /**
     * @return array{clientes: LengthAwarePaginator, seleccionado: ?ServicioHotspot, esRecientes: bool, consumo: array<string, int>, verTodas: bool, totalClientes: int}
     */
    private function datosUsuarios(Request $request, RadiusHotspotSyncService $radius, MikroTikService $mikrotik): array
    {
        $buscar = trim((string) $request->input('buscar', ''));
        $verTodas = $buscar === '' && $request->boolean('todas');
        $esRecientes = $buscar === '' && ! $verTodas;

        $hotspotId = (int) $request->input('hotspot', 0);
        $seleccionado = $hotspotId > 0
            ? ServicioHotspot::query()
                ->with(['servicio', 'hotspotPerfil', 'router', 'cliente'])
                ->find($hotspotId)
            : null;

        if ($seleccionado?->cliente_id) {
            ServicioHotspot::recordarCliente((int) $seleccionado->cliente_id);
        }

        $base = Cliente::query()
            ->with([
                'servicioHotspots' => fn ($q) => $q->with(['servicio', 'hotspotPerfil', 'router'])->orderBy('slot_numero'),
            ])
            ->withCount('servicioHotspots');

        if ($buscar !== '' || $verTodas) {
            $clientes = $base
                ->whereHas('servicioHotspots')
                ->when($buscar !== '', fn ($query) => $query->where(function ($inner) use ($buscar) {
                    $inner->where('nombre', 'like', "%{$buscar}%")
                        ->orWhere('apellido', 'like', "%{$buscar}%")
                        ->orWhere('cedula', 'like', "%{$buscar}%")
                        ->orWhereHas('servicioHotspots', function ($h) use ($buscar) {
                            $h->where('username', 'like', "%{$buscar}%")
                                ->orWhere('comment', 'like', "%{$buscar}%");
                        });
                }))
                ->orderBy('nombre')
                ->orderBy('apellido')
                ->paginate(20)
                ->withQueryString();

            if (! $seleccionado && $buscar !== '') {
                $ocupados = $clientes->getCollection()
                    ->flatMap(fn (Cliente $cl) => $cl->servicioHotspots);
                if ($ocupados->count() === 1) {
                    $seleccionado = $ocupados->first();
                }
            }
        } else {
            $ids = ServicioHotspot::idsRecientes();
            if ($ids === []) {
                $clientes = new LengthAwarePaginator([], 0, ServicioHotspot::RECIENTES_MAX, 1, [
                    'path' => $request->url(),
                    'query' => $request->query(),
                ]);
            } else {
                $encontrados = (clone $base)->whereIn('cliente_id', $ids)->get();
                $ordenados = collect($ids)
                    ->map(fn (int $id) => $encontrados->firstWhere('cliente_id', $id))
                    ->filter()
                    ->values();
                $clientes = new LengthAwarePaginator(
                    $ordenados,
                    $ordenados->count(),
                    ServicioHotspot::RECIENTES_MAX,
                    1,
                    ['path' => $request->url(), 'query' => $request->query()]
                );
            }
        }

        $usernames = $clientes->getCollection()
            ->flatMap(fn (Cliente $cl) => $cl->servicioHotspots->pluck('username'))
            ->filter()
            ->unique()
            ->values()
            ->all();
        if ($seleccionado?->username) {
            $usernames[] = (string) $seleccionado->username;
        }
        $radacct = $radius->bytesRadacctPorUsuarios($usernames);
        $vivo = [];
        try {
            $vivo = $mikrotik->bytesHotspotActivosPorUsuarios($usernames);
        } catch (\Throwable $e) {
            $vivo = [];
        }
        $consumo = RadiusHotspotSyncService::combinarConsumoSesionViva($radacct, $vivo);

        $totalClientes = $esRecientes
            ? Cliente::query()->whereHas('servicioHotspots')->count()
            : (int) $clientes->total();

        return compact('clientes', 'seleccionado', 'esRecientes', 'consumo', 'verTodas', 'totalClientes');
    }

    private function vistaHotspot(Request $request): string
    {
        $vista = (string) $request->query('vista', 'sesiones');
        if (! in_array($vista, ['sesiones', 'usuarios', 'perfiles'], true)) {
            $vista = 'sesiones';
        }
        $user = $request->user();
        $puedePerfiles = $user && ($user->tienePermiso('servicios-hotspot-perfiles.ver') || $user->tienePermiso('servicios.ver'));
        if ($vista === 'perfiles' && ! $puedePerfiles) {
            return 'sesiones';
        }

        return $vista;
    }

    private function datosPerfiles(Request $request): LengthAwarePaginator
    {
        $query = HotspotPerfil::query()->orderBy('nombre');
        if ($request->filled('buscar')) {
            $q = (string) $request->input('buscar');
            $query->where(function ($qry) use ($q) {
                $qry->where('nombre', 'like', "%{$q}%")
                    ->orWhere('rate_limit', 'like', "%{$q}%");
            });
        }

        return $query->paginate(15)->withQueryString();
    }

    private function redirigirAlMenu(Request $request, string $vista): RedirectResponse
    {
        $routerId = (int) $request->input('router_id', 0);
        if ($routerId <= 0) {
            $routerId = (int) $request->session()->get(self::SESSION_DASHBOARD_ROUTER, 0);
        }
        $params = array_filter([
            'vista' => $vista,
            'router_id' => $routerId > 0 ? $routerId : null,
            'buscar' => $request->input('buscar'),
            'hotspot' => $request->input('hotspot'),
            'page' => $request->input('page'),
            'todas' => $request->input('todas'),
        ], fn ($valor) => $valor !== null && $valor !== '');

        $to = redirect()->route('hotspot.dashboard', $params);
        foreach (['success', 'error', 'warning'] as $clave) {
            if ($request->session()->has($clave)) {
                $to->with($clave, $request->session()->get($clave));
            }
        }

        return $to;
    }

    /**
     * El PIN no viaja en el listado: se pide recién cuando el operador toca «Mostrar».
     */
    public function pin(ServicioHotspot $servicioHotspot): JsonResponse
    {
        return response()
            ->json(['pin' => (string) $servicioHotspot->password])
            ->header('Cache-Control', 'no-store');
    }

    public function tocar(Request $request): JsonResponse
    {
        $id = (int) $request->input('cliente_id', 0);
        if ($id <= 0) {
            return response()->json(['ok' => false], 422);
        }

        ServicioHotspot::recordarCliente($id);

        return response()->json(['ok' => true]);
    }

    /**
     * Sugerencias del find: solo lockers hotspot, nunca CRM sin slot.
     */
    public function buscar(Request $request): JsonResponse
    {
        $q = trim((string) $request->get('q', ''));
        if (mb_strlen($q) < 2) {
            return response()->json([]);
        }

        $maxSlots = ServicioHotspot::MAX_POR_CLIENTE;
        $clientes = Cliente::query()
            ->whereHas('servicioHotspots')
            ->withCount('servicioHotspots')
            ->where(function ($query) use ($q) {
                $query->where(function ($inner) use ($q) {
                    $inner->buscarTexto($q);
                })->orWhereHas('servicioHotspots', function ($h) use ($q) {
                    $h->where('username', 'like', "%{$q}%")
                        ->orWhere('comment', 'like', "%{$q}%");
                });
            })
            ->orderBy('nombre')
            ->orderBy('apellido')
            ->limit(15)
            ->get(['cliente_id', 'nombre', 'apellido', 'cedula']);

        return response()->json($clientes->map(static function (Cliente $c) use ($maxSlots) {
            $nombre = trim((string) ($c->nombre ?? ''));
            $apellido = trim((string) ($c->apellido ?? ''));
            $etiqueta = trim($nombre.' '.$apellido);
            if ($etiqueta === '' || strcasecmp($etiqueta, 'null') === 0) {
                $etiqueta = (string) ($c->cedula ?: '#'.$c->cliente_id);
            }

            return [
                'cliente_id' => (int) $c->cliente_id,
                'nombre' => $nombre,
                'apellido' => $apellido,
                'etiqueta' => $etiqueta,
                'cedula' => (string) ($c->cedula ?? ''),
                'slots' => (int) $c->servicio_hotspots_count,
                'max_slots' => $maxSlots,
            ];
        })->values());
    }

    /**
     * El alta vive en la ficha del cliente.
     */
    public function create(Request $request): RedirectResponse
    {
        $servicio = $request->filled('servicio_id')
            ? Servicio::with('cliente')->find($request->servicio_id)
            : null;

        if ($servicio?->cliente) {
            return redirect()->route('hotspot.clientes.edit', $servicio->cliente);
        }

        return redirect()->route('hotspot.index');
    }

    /**
     * Guardar usuario hotspot (alta suelta: deriva el cliente del servicio).
     */
    public function store(Request $request): RedirectResponse
    {
        $servicio = Servicio::find($request->input('servicio_id'));
        if (! $servicio) {
            return back()->withErrors(['servicio_id' => 'Seleccioná un servicio válido.'])->withInput();
        }

        $cliente = Cliente::find($servicio->cliente_id);
        if (! $cliente) {
            return back()->withErrors(['servicio_id' => 'El servicio no tiene cliente.'])->withInput();
        }

        return $this->guardarParaCliente($request, $cliente);
    }

    public function editCliente(Cliente $cliente): View
    {
        $cliente->load([
            'servicios.plan',
            'servicioHotspots.servicio',
            'servicioHotspots.hotspotPerfil',
        ]);

        $perfiles = HotspotPerfil::orderBy('nombre')->get();
        $slotsLibres = ServicioHotspot::slotsLibresDe(
            $cliente->servicioHotspots->pluck('slot_numero')->all()
        );
        $usernamesPorSlot = [];
        foreach ($slotsLibres as $slot) {
            $usernamesPorSlot[$slot] = ServicioHotspot::usernameDesdeDocumento($cliente, (int) $slot);
        }

        return view('hotspot.cliente', compact('cliente', 'perfiles', 'slotsLibres', 'usernamesPorSlot'));
    }

    public function storeCliente(Request $request, Cliente $cliente): RedirectResponse
    {
        return $this->guardarParaCliente($request, $cliente);
    }

    public function updateCliente(Request $request, Cliente $cliente, ServicioHotspot $servicioHotspot): RedirectResponse
    {
        if ((int) $servicioHotspot->cliente_id !== (int) $cliente->cliente_id) {
            abort(404);
        }

        $validated = $request->validate([
            'hotspot_perfil_id' => ['nullable', 'integer', 'exists:hotspot_perfiles,hotspot_perfil_id'],
            'password' => ServicioHotspot::reglasPin(),
        ], [
            'password.digits' => ServicioHotspot::mensajePin(),
        ]);

        $datos = [
            'hotspot_perfil_id' => $validated['hotspot_perfil_id'] ?? null,
        ];
        if (filled($validated['password'] ?? null)) {
            $datos['password'] = (string) $validated['password'];
        }

        $servicioHotspot->update($datos);

        $aviso = isset($datos['password'])
            ? 'PIN de '.$servicioHotspot->username.' actualizado.'
            : 'Perfil de '.$servicioHotspot->username.' actualizado.';

        return redirect()
            ->route('hotspot.clientes.edit', $cliente)
            ->with('success', $aviso);
    }

    public function destroy(Request $request, Cliente $cliente, ServicioHotspot $servicioHotspot): RedirectResponse
    {
        if ((int) $servicioHotspot->cliente_id !== (int) $cliente->cliente_id) {
            abort(404);
        }

        $username = $servicioHotspot->username;
        $servicioHotspot->delete();

        if ($request->boolean('from_dashboard')) {
            $routerId = (int) $request->input('router_id', 0);

            return redirect()
                ->route('hotspot.dashboard', array_filter([
                    'router_id' => $routerId > 0 ? $routerId : null,
                ]))
                ->with('success', 'Usuario hotspot '.$username.' eliminado.');
        }

        if ($request->boolean('from_index')) {
            return redirect()
                ->route('hotspot.index', array_filter([
                    'buscar' => $request->input('buscar'),
                    'page' => $request->input('page'),
                    'todas' => $request->input('todas'),
                ], fn ($valor) => $valor !== null && $valor !== ''))
                ->with('success', 'Usuario hotspot '.$username.' eliminado.');
        }

        return redirect()
            ->route('hotspot.clientes.edit', $cliente)
            ->with('success', 'Usuario hotspot '.$username.' eliminado.');
    }

    /**
     * Sincronizar un usuario hotspot al router.
     */
    public function sync(Request $request, ServicioHotspot $servicioHotspot, MikroTikService $mikrotik, RadiusHotspotSyncService $radius)
    {
        if ($radius->enabled()) {
            $radiusResult = $radius->sync($servicioHotspot);
            if (! $radiusResult['success']) {
                if ($request->wantsJson()) {
                    return response()->json($radiusResult);
                }

                return $this->redirectTrasSync($request, $servicioHotspot, 'RADIUS: '.($radiusResult['error'] ?? 'Error al sincronizar.'), 'error');
            }
            if (! config('radius.also_sync_mikrotik') || ! $servicioHotspot->router_id) {
                if ($request->wantsJson()) {
                    return response()->json(['success' => true]);
                }

                return $this->redirectTrasSync($request, $servicioHotspot, 'Usuario hotspot sincronizado en RADIUS.');
            }
        }

        if (! $servicioHotspot->router_id) {
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'error' => 'RADIUS deshabilitado y el usuario no tiene router.']);
            }

            return $this->redirectTrasSync($request, $servicioHotspot, 'RADIUS deshabilitado y el usuario no tiene router.', 'error');
        }

        $result = $mikrotik->syncHotspotServicio($servicioHotspot);

        if ($request->wantsJson()) {
            return response()->json($result);
        }

        if ($result['success']) {
            return $this->redirectTrasSync($request, $servicioHotspot, 'Usuario hotspot sincronizado correctamente.');
        }

        MikrotikOperacionPendiente::registrarSiFallo(
            MikrotikOperacionPendiente::TIPO_SYNC_HOTSPOT,
            ['servicio_hotspot_id' => $servicioHotspot->getKey()],
            $result['error'] ?? 'Error al sincronizar',
            'hotspot.sync'
        );

        return $this->redirectTrasSync($request, $servicioHotspot, $result['error'] ?? 'Error al sincronizar.', 'error');
    }

    protected function guardarParaCliente(Request $request, Cliente $cliente): RedirectResponse
    {
        if ($cliente->servicioHotspots()->count() >= ServicioHotspot::MAX_POR_CLIENTE) {
            return redirect()
                ->route('hotspot.clientes.edit', $cliente)
                ->withErrors(['slot_numero' => 'Este cliente ya tiene '.ServicioHotspot::MAX_POR_CLIENTE.' usuarios hotspot.'])
                ->withInput();
        }

        if (! $request->filled('slot_numero')) {
            $libre = ServicioHotspot::slotsLibresDe(
                $cliente->servicioHotspots()->pluck('slot_numero')->all()
            );
            if ($libre !== []) {
                $request->merge(['slot_numero' => $libre[0]]);
            }
        }

        $validated = $request->validate([
            'servicio_id' => [
                'required',
                'integer',
                Rule::exists('servicios', 'servicio_id')->where('cliente_id', $cliente->cliente_id),
            ],
            'hotspot_perfil_id' => ['nullable', 'integer', 'exists:hotspot_perfiles,hotspot_perfil_id'],
            'comment' => ['nullable', 'string', 'max:255'],
            'slot_numero' => [
                'required',
                'integer',
                Rule::in(ServicioHotspot::slotsDisponibles()),
                Rule::unique('servicio_hotspot', 'slot_numero')->where(
                    fn ($q) => $q->where('cliente_id', $cliente->cliente_id)
                ),
            ],
            'password' => ServicioHotspot::reglasPin(),
        ], [
            'password.digits' => ServicioHotspot::mensajePin(),
        ]);

        $username = ServicioHotspot::usernameDesdeDocumento($cliente, (int) $validated['slot_numero']);
        if ($username === null) {
            return redirect()
                ->route('hotspot.clientes.edit', $cliente)
                ->withErrors(['username' => 'El cliente no tiene número de documento para usar de usuario.'])
                ->withInput();
        }

        if (ServicioHotspot::where('username', $username)->exists()) {
            return redirect()
                ->route('hotspot.clientes.edit', $cliente)
                ->withErrors(['username' => 'Ya existe un usuario hotspot con el documento '.$username.'.'])
                ->withInput();
        }

        $validated['cliente_id'] = $cliente->cliente_id;
        $validated['username'] = $username;
        $validated['password'] = filled($validated['password'] ?? null)
            ? (string) $validated['password']
            : ServicioHotspot::generarPassword();
        $validated['router_id'] = null;
        if (blank($validated['comment'] ?? null)) {
            $validated['comment'] = trim($cliente->nombre.' '.$cliente->apellido);
        }

        ServicioHotspot::create($validated);

        return redirect()
            ->route('hotspot.clientes.edit', $cliente)
            ->with('success', 'Usuario '.$username.' creado.');
    }

    protected function redirectTrasSync(Request $request, ServicioHotspot $servicioHotspot, string $mensaje, string $tipo = 'success')
    {
        if ($request->boolean('from_dashboard')) {
            $routerId = (int) $request->input('router_id', 0);
            if ($routerId <= 0) {
                $routerId = (int) $request->session()->get(self::SESSION_DASHBOARD_ROUTER, 0);
            }

            return redirect()
                ->route('hotspot.dashboard', array_filter([
                    'router_id' => $routerId > 0 ? $routerId : null,
                ]))
                ->with($tipo, $mensaje);
        }

        if ($request->boolean('from_index')) {
            return redirect()
                ->route('hotspot.index', array_filter([
                    'buscar' => $request->input('buscar'),
                    'page' => $request->input('page'),
                    'todas' => $request->input('todas'),
                    'hotspot' => $servicioHotspot->getKey(),
                ], fn ($valor) => $valor !== null && $valor !== ''))
                ->with($tipo, $mensaje);
        }

        if ($request->filled('from_cliente') || $servicioHotspot->cliente_id) {
            return redirect()
                ->route('hotspot.clientes.edit', $servicioHotspot->cliente_id ?: $servicioHotspot->cliente)
                ->with($tipo, $mensaje);
        }

        return redirect()
            ->route('hotspot.index', array_filter([
                'buscar' => $request->input('buscar'),
                'page' => $request->input('page'),
                'todas' => $request->input('todas'),
            ], fn ($valor) => $valor !== null && $valor !== ''))
            ->with($tipo, $mensaje);
    }

    /**
     * @return list<string>
     */
    private function macsBloqueadas(?Router $router, ?string $routerError, MikroTikService $mikrotik): array
    {
        if (! $router || $routerError) {
            return [];
        }

        try {
            return $mikrotik->macsBloqueadasHotspot($router);
        } catch (\Throwable $e) {
            return [];
        }
    }

    private function macAccion(Request $request): string|RedirectResponse
    {
        $mac = strtoupper(str_replace('-', ':', trim((string) $request->input('mac', ''))));
        if (! preg_match('/^([0-9A-F]{2}:){5}[0-9A-F]{2}$/', $mac)) {
            return back()->with('error', 'La MAC no es válida.');
        }

        return $mac;
    }

    private function volverAlDashboard(Router $router, string $tipo, string $mensaje): RedirectResponse
    {
        return redirect()
            ->route('hotspot.dashboard', ['router_id' => $router->router_id])
            ->with($tipo, $mensaje);
    }
}
