<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\Router;
use App\Models\Servicio;
use App\Models\ServicioConexionEvento;
use App\Support\PppoeDesconexionContinua;
use App\Support\PppoeEventosResumen;
use App\Support\PppoeTimeline12h;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ServicioPppoeEventoController extends Controller
{
    /** Períodos rápidos del dashboard (clave => etiqueta). */
    public const PERIODOS = [
        'hoy' => 'Hoy',
        '24h' => '24 h',
        '7d' => '7 días',
        '30d' => '30 días',
        'todo' => 'Todo',
    ];

    public const PERIODO_DEFECTO = '24h';

    /** Máximo de eventos que muestra el detalle de un cliente. */
    public const DETALLE_MAX_EVENTOS = 200;

    /** Máximo de líneas en la lista de "requieren atención". */
    public const ATENCION_MAX_FILAS = 100;

    public function index(Request $request)
    {
        $periodo = $this->periodo($request);
        $filtrada = $this->queryFiltrada($request, $periodo);
        $orden = $request->input('orden') === 'cortes' ? 'cortes' : 'reciente';

        $gruposSub = (clone $filtrada)
            ->join('servicios', 'servicios.servicio_id', '=', 'servicio_conexion_eventos.servicio_id')
            ->select('servicios.cliente_id')
            ->selectRaw('COUNT(*) as total_eventos')
            ->selectRaw(
                'SUM(CASE WHEN servicio_conexion_eventos.tipo = ? THEN 1 ELSE 0 END) as desconexiones',
                [ServicioConexionEvento::TIPO_PPPOE_DOWN]
            )
            ->selectRaw('MAX(servicio_conexion_eventos.ocurrio_at) as ultimo_ocurrio_at')
            ->groupBy('servicios.cliente_id');

        $grupos = DB::query()
            ->fromSub($gruposSub, 'grupos_pppoe')
            ->when($orden === 'cortes', fn ($q) => $q->orderByDesc('desconexiones'))
            ->orderByDesc('ultimo_ocurrio_at')
            ->paginate(30)
            ->withQueryString();

        $clienteIds = collect($grupos->items())
            ->pluck('cliente_id')
            ->filter(fn ($id) => $id !== null)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $clientes = $clienteIds->isNotEmpty()
            ? Cliente::query()->whereIn('cliente_id', $clienteIds)->get()->keyBy('cliente_id')
            : collect();

        $ultimosPorCliente = collect();
        if ($grupos->count() > 0) {
            $eventosRecientes = (clone $filtrada)
                ->with(['servicio.cliente', 'router'])
                ->whereHas('servicio', function (Builder $q) use ($grupos) {
                    $ids = collect($grupos->items())->pluck('cliente_id')->unique()->values();
                    $q->where(function (Builder $inner) use ($ids) {
                        $conCliente = $ids->filter(fn ($id) => $id !== null)->values();
                        if ($conCliente->isNotEmpty()) {
                            $inner->whereIn('cliente_id', $conCliente);
                        }
                        if ($ids->contains(null)) {
                            $inner->orWhereNull('cliente_id');
                        }
                    });
                })
                ->orderByDesc('ocurrio_at')
                ->orderByDesc('servicio_conexion_evento_id')
                ->get();

            $ultimosPorCliente = $eventosRecientes->groupBy(fn (ServicioConexionEvento $ev) => $ev->servicio?->cliente_id ?? 'sin-cliente')
                ->map->first();
        }

        $filas = collect($grupos->items())->map(function ($grupo) use ($clientes, $ultimosPorCliente) {
            $clave = $grupo->cliente_id ?? 'sin-cliente';
            $ultimo = $ultimosPorCliente->get($clave);
            $cliente = $grupo->cliente_id ? $clientes->get((int) $grupo->cliente_id) : null;
            $total = (int) $grupo->total_eventos;
            $desconexiones = (int) $grupo->desconexiones;

            return (object) [
                'cliente_id' => $grupo->cliente_id,
                'cliente' => $cliente,
                'cliente_nombre' => $cliente
                    ? trim($cliente->nombre.' '.($cliente->apellido ?? ''))
                    : ($ultimo?->usuario_pppoe ?: 'Sin cliente'),
                'total_eventos' => $total,
                'desconexiones' => $desconexiones,
                'conexiones' => $total - $desconexiones,
                'ultimo_ocurrio_at' => $grupo->ultimo_ocurrio_at,
                'ultimo_evento' => $ultimo,
            ];
        });

        $grupos->setCollection($filas);

        $routers = Router::query()
            ->orderBy('nombre')
            ->get(['router_id', 'nombre']);

        // Las tarjetas cuentan todo el período aunque la tabla esté filtrada por tipo,
        // y el resumen por router ignora el router elegido para poder cambiar de uno a otro.
        $resumen = PppoeEventosResumen::totales(
            $this->queryFiltrada($request, $periodo, conTipo: false)
        );
        $porRouter = PppoeEventosResumen::porRouter(
            $this->queryFiltrada($request, $periodo, conTipo: false, conRouter: false),
            $routers->pluck('nombre', 'router_id')->all()
        );

        return view('servicios.pppoe-eventos.index', [
            'grupos' => $grupos,
            'routers' => $routers,
            'resumen' => $resumen,
            'porRouter' => $porRouter,
            'atencion' => $this->atencion($request),
            'umbrales' => PppoeDesconexionContinua::umbrales(),
            'periodo' => $periodo,
            'periodos' => self::PERIODOS,
            'orden' => $orden,
        ]);
    }

    /**
     * Detalle de un cliente (o de un servicio sin cliente): estado de cada línea,
     * barra de las últimas 12 h y lista de eventos del período. Devuelve el HTML del panel.
     */
    public function detalle(Request $request)
    {
        $servicios = Servicio::query()
            ->with('cliente')
            ->when(
                $request->filled('cliente_id'),
                fn ($q) => $q->where('cliente_id', (int) $request->input('cliente_id')),
                fn ($q) => $q->where('servicio_id', (int) $request->input('servicio_id'))
            )
            ->whereIn('servicio_id', ServicioConexionEvento::query()->pppoe()->select('servicio_id'))
            ->orderBy('servicio_id')
            ->get();

        abort_if($servicios->isEmpty(), 404, 'No hay eventos PPPoE para ese cliente.');

        $servicioIds = $servicios->pluck('servicio_id')->map(fn ($id) => (int) $id)->all();
        $periodo = $this->periodo($request);

        $eventos = $this->queryFiltrada($request, $periodo, conTipo: false, conRouter: false, conBuscar: false)
            ->whereIn('servicio_conexion_eventos.servicio_id', $servicioIds)
            ->with('router')
            ->orderByDesc('ocurrio_at')
            ->orderByDesc('servicio_conexion_evento_id')
            ->limit(self::DETALLE_MAX_EVENTOS + 1)
            ->get();

        $hayMas = $eventos->count() > self::DETALLE_MAX_EVENTOS;
        $eventos = $eventos->take(self::DETALLE_MAX_EVENTOS)->values();

        // Cuánto duró cada estado: hasta el evento siguiente de la misma línea, o hasta ahora si es el último.
        $siguiente = [];
        $filasEventos = $eventos->map(function (ServicioConexionEvento $ev) use (&$siguiente) {
            $posterior = $siguiente[$ev->servicio_id] ?? null;
            $siguiente[$ev->servicio_id] = $ev;
            $hasta = $posterior?->ocurrio_at ?? now();
            $segundos = max(0, $hasta->getTimestamp() - $ev->ocurrio_at->getTimestamp());

            return (object) [
                'evento' => $ev,
                'conectado' => $ev->esPppoeConexion(),
                'duracion' => PppoeTimeline12h::formatearDuracion($segundos),
                'sigue' => $posterior === null,
            ];
        });

        $conteos = PppoeDesconexionContinua::conteos(
            ServicioConexionEvento::query()->whereIn('servicio_conexion_eventos.servicio_id', $servicioIds)
        );

        $lineas = $servicios->map(function (Servicio $servicio) use ($conteos, $eventos) {
            $conteo = $conteos->get((int) $servicio->servicio_id)
                ?? ['h1' => 0, 'h6' => 0, 'h24' => 0, 'nivel' => null];
            $ultimo = $eventos->firstWhere('servicio_id', $servicio->servicio_id);

            return (object) [
                'servicio' => $servicio,
                'usuario' => $servicio->usuario_pppoe ?: ($ultimo?->usuario_pppoe ?: 'Servicio #'.$servicio->servicio_id),
                'router' => $ultimo?->router?->nombre,
                'ip' => $ultimo?->ip ?: $servicio->ip,
                'timeline' => PppoeTimeline12h::construir((int) $servicio->servicio_id),
                'conteo' => $conteo,
            ];
        });

        $cliente = $servicios->first()->cliente;

        return view('servicios.pppoe-eventos._detalle', [
            'cliente' => $cliente,
            'titulo' => $cliente
                ? trim($cliente->nombre.' '.($cliente->apellido ?? ''))
                : $lineas->first()->usuario,
            'lineas' => $lineas,
            'eventos' => $filasEventos,
            'hayMas' => $hayMas,
            'periodo' => $periodo,
        ]);
    }

    /**
     * Líneas con desconexión continua en las últimas 24 h, de la más grave a la menos grave.
     * Respeta la fuente y el router elegidos, pero no el período ni la búsqueda.
     *
     * @return Collection<int, object>
     */
    private function atencion(Request $request): Collection
    {
        $sinPeriodo = ['desde' => null, 'fecha_desde' => null, 'fecha_hasta' => null];
        $eventos = $this->queryFiltrada($request, $sinPeriodo, conTipo: false, conBuscar: false);

        $filas = PppoeDesconexionContinua::enAtencion($eventos)->take(self::ATENCION_MAX_FILAS);
        if ($filas->isEmpty()) {
            return collect();
        }

        $servicioIds = $filas->pluck('servicio_id')->all();
        $servicios = Servicio::query()->with('cliente')->whereIn('servicio_id', $servicioIds)->get()->keyBy('servicio_id');

        // Último evento de cada línea: dice si ahora está conectada y en qué router.
        $ultimos = (clone $eventos)
            ->whereIn('servicio_conexion_eventos.servicio_id', $servicioIds)
            ->where('servicio_conexion_eventos.ocurrio_at', '>=', now()->subHours(24))
            ->with('router')
            ->orderByDesc('ocurrio_at')
            ->orderByDesc('servicio_conexion_evento_id')
            ->get()
            ->unique('servicio_id')
            ->keyBy('servicio_id');

        return $filas->map(function (array $fila) use ($servicios, $ultimos) {
            $servicio = $servicios->get($fila['servicio_id']);
            $ultimo = $ultimos->get($fila['servicio_id']);
            $cliente = $servicio?->cliente;
            $usuario = $servicio?->usuario_pppoe ?: ($ultimo?->usuario_pppoe ?: 'Servicio #'.$fila['servicio_id']);
            $ultimoCorte = $fila['ultimo_corte_at'] ? Carbon::parse($fila['ultimo_corte_at']) : null;

            return (object) [
                'servicio_id' => $fila['servicio_id'],
                'cliente' => $cliente,
                'nombre' => $cliente ? trim($cliente->nombre.' '.($cliente->apellido ?? '')) : $usuario,
                'usuario' => $usuario,
                'router' => $ultimo?->router?->nombre,
                'conectado' => $ultimo?->esPppoeConexion(),
                'nivel' => $fila['nivel'],
                'etiqueta' => PppoeDesconexionContinua::etiqueta($fila['nivel']),
                'h1' => $fila['h1'],
                'h6' => $fila['h6'],
                'h24' => $fila['h24'],
                'ventanas' => [
                    'h1' => PppoeDesconexionContinua::nivelDeVentana('h1', $fila['h1']),
                    'h6' => PppoeDesconexionContinua::nivelDeVentana('h6', $fila['h6']),
                    'h24' => PppoeDesconexionContinua::nivelDeVentana('h24', $fila['h24']),
                ],
                'ultimo_corte' => $ultimoCorte,
                'ultimo_corte_hace' => $ultimoCorte
                    ? PppoeTimeline12h::formatearDuracion(max(0, now()->getTimestamp() - $ultimoCorte->getTimestamp()))
                    : null,
            ];
        })->values();
    }

    /**
     * Período elegido: las fechas a mano mandan; si no vienen, uno de los rápidos.
     *
     * @return array{clave: string, etiqueta: string, desde: ?CarbonInterface, fecha_desde: ?string, fecha_hasta: ?string}
     */
    private function periodo(Request $request): array
    {
        $fechaDesde = $request->filled('fecha_desde') ? (string) $request->input('fecha_desde') : null;
        $fechaHasta = $request->filled('fecha_hasta') ? (string) $request->input('fecha_hasta') : null;

        if ($fechaDesde !== null || $fechaHasta !== null) {
            $formato = fn (?string $f) => $f !== null && strtotime($f) !== false ? date('d/m/Y', strtotime($f)) : null;
            $desdeTxt = $formato($fechaDesde);
            $hastaTxt = $formato($fechaHasta);
            $etiqueta = match (true) {
                $desdeTxt !== null && $hastaTxt !== null => "del {$desdeTxt} al {$hastaTxt}",
                $desdeTxt !== null => "desde el {$desdeTxt}",
                $hastaTxt !== null => "hasta el {$hastaTxt}",
                default => 'en las fechas elegidas',
            };

            return [
                'clave' => 'fechas',
                'etiqueta' => $etiqueta,
                'desde' => null,
                'fecha_desde' => $fechaDesde,
                'fecha_hasta' => $fechaHasta,
            ];
        }

        $clave = (string) $request->input('periodo', self::PERIODO_DEFECTO);
        if (! array_key_exists($clave, self::PERIODOS)) {
            $clave = self::PERIODO_DEFECTO;
        }

        [$desde, $etiqueta] = match ($clave) {
            'hoy' => [today(), 'hoy'],
            '7d' => [now()->subDays(7), 'en los últimos 7 días'],
            '30d' => [now()->subDays(30), 'en los últimos 30 días'],
            'todo' => [null, 'en todo el historial'],
            default => [now()->subDay(), 'en las últimas 24 h'],
        };

        return [
            'clave' => $clave,
            'etiqueta' => $etiqueta,
            'desde' => $desde,
            'fecha_desde' => null,
            'fecha_hasta' => null,
        ];
    }

    /**
     * @param  array{desde: ?CarbonInterface, fecha_desde: ?string, fecha_hasta: ?string}  $periodo
     * @return Builder<ServicioConexionEvento>
     */
    private function queryFiltrada(
        Request $request,
        array $periodo,
        bool $conTipo = true,
        bool $conRouter = true,
        bool $conBuscar = true
    ): Builder
    {
        // Columnas con el nombre de la tabla: estas consultas se unen con `servicios`,
        // que también tiene usuario_pppoe, ip y mac_address.
        $t = 'servicio_conexion_eventos.';

        $query = ServicioConexionEvento::query()->whereIn($t.'tipo', [
            ServicioConexionEvento::TIPO_PPPOE_UP,
            ServicioConexionEvento::TIPO_PPPOE_DOWN,
        ]);

        $fuente = $request->input('fuente', 'mikrotik');
        if ($fuente === 'mikrotik') {
            $query->whereIn($t.'fuente', [
                ServicioConexionEvento::FUENTE_WEBHOOK,
                ServicioConexionEvento::FUENTE_MIKROTIK_CONSULTA,
            ]);
        } elseif ($fuente !== '' && $fuente !== 'todas') {
            $query->where($t.'fuente', $fuente);
        }

        if ($conTipo && $request->filled('tipo')) {
            $query->where($t.'tipo', $request->input('tipo'));
        }

        if ($conRouter && $request->filled('router_id')) {
            $query->where($t.'router_id', (int) $request->input('router_id'));
        }

        if ($conBuscar && $request->filled('buscar')) {
            $term = trim((string) $request->input('buscar'));
            $query->where(function ($q) use ($term, $t) {
                $q->where($t.'usuario_pppoe', 'like', '%'.$term.'%')
                    ->orWhere($t.'ip', 'like', '%'.$term.'%')
                    ->orWhere($t.'mac_address', 'like', '%'.$term.'%')
                    ->orWhereHas('servicio.cliente', function ($cq) use ($term) {
                        $cq->where('nombre', 'like', '%'.$term.'%')
                            ->orWhere('apellido', 'like', '%'.$term.'%')
                            ->orWhere('cedula', 'like', '%'.$term.'%');
                    });
            });
        }

        if ($periodo['desde'] !== null) {
            $query->where($t.'ocurrio_at', '>=', $periodo['desde']);
        }

        if ($periodo['fecha_desde'] !== null) {
            $query->whereDate($t.'ocurrio_at', '>=', $periodo['fecha_desde']);
        }

        if ($periodo['fecha_hasta'] !== null) {
            $query->whereDate($t.'ocurrio_at', '<=', $periodo['fecha_hasta']);
        }

        return $query;
    }
}
