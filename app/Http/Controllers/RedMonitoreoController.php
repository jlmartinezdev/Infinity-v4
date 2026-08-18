<?php

namespace App\Http\Controllers;

use App\Models\Router;
use App\Services\FcmPushService;
use App\Services\Monitoreo\PingExecutor;
use App\Services\Monitoreo\RouterPingStatusService;
use App\Services\Monitoreo\ServicioPingMonitoreoService;
use App\Support\IspFailoverConfig;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class RedMonitoreoController extends Controller
{
    public function index(): View
    {
        return view('sistema.red-monitoreo', [
            'config' => $this->payload(),
        ]);
    }

    public function datos(): JsonResponse
    {
        return response()->json($this->payload());
    }

    /**
     * Ejecuta ping ICMP a los routers de la topología y actualiza latencia/estado.
     */
    public function ping(Request $request, PingExecutor $ping, RouterPingStatusService $status): JsonResponse
    {
        $layout = config('red_monitoreo.layout', []);
        $nombres = array_map(fn ($n) => strtoupper(trim((string) $n)), array_keys($layout));

        $routers = Router::query()->get()->filter(function (Router $r) use ($nombres) {
            return in_array(strtoupper(trim((string) $r->nombre)), $nombres, true);
        });

        $resultados = [];

        foreach ($routers as $router) {
            if (! $ping->ipEsPinguable($router->ip)) {
                $status->aplicarResultado($router, ['ok' => false, 'latency_ms' => null, 'error' => 'IP inválida'], true);
                $resultados[] = [
                    'router_id' => $router->router_id,
                    'nombre' => $router->nombre,
                    'ok' => false,
                    'latency_ms' => null,
                    'error' => 'IP inválida',
                ];
                continue;
            }

            $result = $ping->ping($router->ip);
            $status->aplicarResultado($router, $result, false);

            $resultados[] = [
                'router_id' => $router->router_id,
                'nombre' => $router->nombre,
                'ok' => $result['ok'],
                'latency_ms' => $result['latency_ms'],
                'error' => $result['error'],
            ];
        }

        $payload = $this->payload();
        $payload['ping_resultados'] = $resultados;

        return response()->json($payload);
    }

    /**
     * Push de prueba a la app staff simulando caída de un router de la topología.
     */
    public function notificarCaidaPrueba(Request $request, FcmPushService $fcm): JsonResponse
    {
        $validated = $request->validate([
            'router_nombre' => ['nullable', 'string', 'max:120'],
            'router_id' => ['nullable', 'integer'],
        ]);

        $layout = config('red_monitoreo.layout', []);
        $nombresLayout = array_keys($layout);

        $router = null;
        if (! empty($validated['router_id'])) {
            $router = Router::query()->find((int) $validated['router_id']);
        } elseif (! empty($validated['router_nombre'])) {
            $buscar = strtoupper(trim($validated['router_nombre']));
            $router = Router::query()->get()->first(
                fn (Router $r) => strtoupper(trim((string) $r->nombre)) === $buscar
            );
        }

        if (! $router) {
            // Preferir un nodo caído de la topología; si no, el core.
            $payloadActual = $this->payload();
            $caido = collect($payloadActual['nodos'])->firstWhere('status', 'down');
            $core = collect($payloadActual['nodos'])->firstWhere('rol', 'core');
            $elegido = $caido ?: $core ?: ($payloadActual['nodos'][0] ?? null);
            if ($elegido && ! empty($elegido['router_id'])) {
                $router = Router::query()->find((int) $elegido['router_id']);
            }
            $nombreFallback = $elegido['nombre'] ?? ($nombresLayout[0] ?? 'ROUTER');
            $ipFallback = $elegido['ip'] ?? null;
        }

        $nombre = $router?->nombre ?? ($nombreFallback ?? 'ROUTER');
        $ip = $router?->ip ?? ($ipFallback ?? null);
        $routerId = $router?->router_id ?? 0;

        $title = 'Caída de red (PRUEBA)';
        $body = $ip
            ? "Simulación: {$nombre} ({$ip}) sin respuesta — revisar topología"
            : "Simulación: {$nombre} sin respuesta — revisar topología";

        $data = [
            'tipo' => 'alerta_red',
            'id' => (string) $routerId,
            'router_id' => (string) $routerId,
            'router' => (string) $nombre,
            'ip' => (string) ($ip ?? ''),
            'prueba' => '1',
            'severidad' => 'alta',
            'title' => $title,
            'body' => $body,
        ];

        $ok = $fcm->notifyStaff($title, $body, $data);
        $topic = config('services.fcm.staff_topic') ?: env('FCM_STAFF_TOPIC', 'staff');

        if (! $ok) {
            return response()->json([
                'success' => false,
                'message' => 'No se pudo enviar el push. Revisá FCM_SERVICE_ACCOUNT_PATH y los logs.',
                'topic' => $topic,
                'data' => $data,
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => "Notificación de prueba enviada al topic «{$topic}» (router {$nombre}).",
            'topic' => $topic,
            'data' => $data,
        ]);
    }

    /**
     * @return array{titulo: string, subtitulo: string, nodos: array<int, array>, enlaces: array<int, array>, stats: array}
     */
    private function payload(): array
    {
        $layout = config('red_monitoreo.layout', []);
        $enlacesCfg = config('red_monitoreo.enlaces', []);
        $tieneCols = Schema::hasColumn('routers', 'ping_latencia_ms');

        $routers = Router::with('nodo')
            ->orderBy('nombre')
            ->get()
            ->keyBy(fn (Router $r) => strtoupper(trim((string) $r->nombre)));

        $routerIds = $routers->pluck('router_id')->filter()->map(fn ($id) => (int) $id)->unique()->values()->all();
        $conteosClientes = app(ServicioPingMonitoreoService::class)->conteosPorRouterIds($routerIds);

        $nodos = [];
        $latencias = [];
        $clientesOnlineTotal = 0;
        $clientesActivosTotal = 0;

        foreach ($layout as $nombre => $pos) {
            $key = strtoupper(trim($nombre));
            $router = $routers->get($key);
            $estado = $router ? strtolower((string) $router->estado) : 'desconocido';
            $status = match ($estado) {
                'conectado' => 'ok',
                'desconectado' => 'down',
                default => 'unknown',
            };

            $latencia = $tieneCols && $router ? $router->ping_latencia_ms : null;
            $pingAt = $tieneCols && $router && $router->ping_at
                ? $router->ping_at->timezone(config('app.timezone'))->format('d/m/Y H:i:s')
                : null;

            if ($latencia !== null) {
                $latencias[] = (int) $latencia;
            }

            $rid = $router?->router_id ? (int) $router->router_id : null;
            $conteo = $rid && isset($conteosClientes[$rid])
                ? $conteosClientes[$rid]
                : ['activos' => 0, 'online' => 0, 'offline' => 0, 'sin_dato' => 0];

            $clientesOnlineTotal += (int) $conteo['online'];
            $clientesActivosTotal += (int) $conteo['activos'];

            $nodos[] = [
                'id' => $key,
                'nombre' => $nombre,
                'router_id' => $router?->router_id,
                'ip' => $router?->ip,
                'ip_loopback' => $router?->ip_loopback,
                'estado' => $estado,
                'status' => $status,
                'latencia_ms' => $latencia,
                'ping_at' => $pingAt,
                'clientes_activos' => (int) $conteo['activos'],
                'clientes_online' => (int) $conteo['online'],
                'clientes_offline' => (int) $conteo['offline'],
                'clientes_sin_dato' => (int) $conteo['sin_dato'],
                'nodo' => $router?->nodo?->descripcion,
                'modelo' => $router?->modeloEtiqueta(),
                'x' => (float) ($pos['x'] ?? 0),
                'y' => (float) ($pos['y'] ?? 0),
                'rol' => $pos['rol'] ?? 'acceso',
                'en_bd' => (bool) $router,
            ];
        }

        $byId = collect($nodos)->keyBy('id');
        $enlaces = [];
        foreach ($enlacesCfg as $i => $link) {
            $from = strtoupper(trim((string) ($link['from'] ?? '')));
            $to = strtoupper(trim((string) ($link['to'] ?? '')));
            if (! $byId->has($from) || ! $byId->has($to)) {
                continue;
            }
            $a = $byId->get($from);
            $b = $byId->get($to);
            $linkOk = ($a['status'] ?? '') === 'ok' && ($b['status'] ?? '') === 'ok';

            $enlaces[] = [
                'id' => 'link-'.$i,
                'from' => $from,
                'to' => $to,
                'status' => $linkOk ? 'up' : 'down',
            ];
        }

        $conectados = collect($nodos)->where('status', 'ok')->count();
        $total = count($nodos);
        $caidos = collect($nodos)->where('status', 'down')->count();
        $latPromedio = count($latencias) > 0 ? (int) round(array_sum($latencias) / count($latencias)) : null;
        $latMax = count($latencias) > 0 ? max($latencias) : null;

        return [
            'titulo' => (string) config('red_monitoreo.titulo', 'Monitoreo de red'),
            'subtitulo' => (string) config('red_monitoreo.subtitulo', ''),
            'nodos' => array_values($nodos),
            'enlaces' => $enlaces,
            'stats' => [
                'total' => $total,
                'conectados' => $conectados,
                'caidos' => $caidos,
                'desconocidos' => $total - $conectados - $caidos,
                'salud' => $total > 0 ? round(($conectados / $total) * 100) : 0,
                'latencia_promedio_ms' => $latPromedio,
                'latencia_max_ms' => $latMax,
                'clientes_online' => $clientesOnlineTotal,
                'clientes_activos' => $clientesActivosTotal,
            ],
            'urlDatos' => url('/sistema/red-monitoreo/datos'),
            'urlPing' => url('/sistema/red-monitoreo/ping'),
            'urlNotificarCaida' => url('/sistema/red-monitoreo/notificar-caida'),
            'urlAvisos' => url('/sistema/router-caida-avisos'),
            'urlRouters' => url('/sistema/routers'),
            'urlIspFailover' => url('/sistema/isp-failover'),
            'isp_failover' => IspFailoverConfig::snapshot(),
            'csrfToken' => csrf_token(),
        ];
    }
}
