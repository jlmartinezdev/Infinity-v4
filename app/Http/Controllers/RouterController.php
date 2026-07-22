<?php

namespace App\Http\Controllers;

use App\Models\Router;
use App\Models\Nodo;
use App\Models\Servicio;
use App\Models\EstadoPedidoDetalle;
use App\Support\MikrotikModelosCatalogo;
use App\Services\MikroTikService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class RouterController extends Controller
{
    /**
     * @return array<string, mixed>
     */
    private function reglasRouter(Request $request, bool $actualizando = false): array
    {
        return $request->validate([
            'nodo_id' => ['required', 'integer', 'exists:nodos,nodo_id'],
            'nombre' => ['required', 'string', 'max:100'],
            'modelo' => ['nullable', 'string', 'max:64', 'in:'.implode(',', MikrotikModelosCatalogo::slugsValidos())],
            'ip' => ['required', 'string', 'max:64'],
            'ip_loopback' => ['nullable', 'string', 'max:64'],
            'hotspot_servidor' => ['nullable', 'string', 'max:64'],
            'api_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'usuario' => ['required', 'string', 'max:64'],
            'password' => [$actualizando ? 'nullable' : 'nullable', 'string', 'max:128'],
            'webhook_token' => ['nullable', 'string', 'max:64'],
            'generar_webhook_token' => ['nullable', 'boolean'],
            'estado' => ['nullable', 'string', 'max:32'],
        ]);
    }

    public function index(Request $request)
    {
        $query = Router::with('nodo')
            ->withCount('routerIpPools')
            ->orderBy('nombre');

        if ($request->filled('buscar')) {
            $q = $request->buscar;
            $query->where(function ($qry) use ($q) {
                $qry->where('nombre', 'like', "%{$q}%")
                    ->orWhere('ip', 'like', "%{$q}%")
                    ->orWhere('modelo', 'like', "%{$q}%")
                    ->orWhere('estado', 'like', "%{$q}%");
            });
        }

        if ($request->filled('serie') && $request->serie !== 'todas') {
            $slugs = collect(MikrotikModelosCatalogo::listado())
                ->filter(fn ($m) => ($m['serie'] ?? '') === $request->serie)
                ->keys()
                ->all();
            if ($slugs !== []) {
                $query->whereIn('modelo', $slugs);
            }
        }

        if ($request->filled('nodo_id') && $request->nodo_id !== 'todos') {
            $query->where('nodo_id', $request->nodo_id);
        }

        $routers = $query->paginate(12)->withQueryString();
        $statsClientes = $this->estadisticasClientesPorRouter(
            $routers->getCollection()->pluck('router_id')->all()
        );
        $nodos = Nodo::orderBy('descripcion')->get();
        $modelosPorSerie = MikrotikModelosCatalogo::porSerie();
        $series = array_keys($modelosPorSerie);

        return view('sistema.routers.index', compact('routers', 'nodos', 'modelosPorSerie', 'series', 'statsClientes'));
    }

    /**
     * Clientes únicos por router según servicios en sus pools (datos del sistema, no MikroTik en vivo).
     *
     * @param  list<int>  $routerIds
     * @return array<int, array{registrados: int, activos: int}>
     */
    private function estadisticasClientesPorRouter(array $routerIds): array
    {
        $routerIds = array_values(array_unique(array_filter(array_map('intval', $routerIds))));
        if ($routerIds === []) {
            return [];
        }

        $activo = Servicio::ESTADO_ACTIVO;
        $cancelado = Servicio::ESTADO_CANCELADO;

        $filas = DB::table('servicios')
            ->join('router_ip_pools', 'servicios.pool_id', '=', 'router_ip_pools.pool_id')
            ->whereIn('router_ip_pools.router_id', $routerIds)
            ->where('servicios.estado', '!=', $cancelado)
            ->groupBy('router_ip_pools.router_id')
            ->selectRaw('router_ip_pools.router_id as router_id')
            ->selectRaw('COUNT(DISTINCT servicios.cliente_id) as clientes_registrados')
            ->selectRaw('COUNT(DISTINCT CASE WHEN servicios.estado = ? THEN servicios.cliente_id END) as clientes_activos', [$activo])
            ->get();

        $stats = [];
        foreach ($filas as $fila) {
            $stats[(int) $fila->router_id] = [
                'registrados' => (int) $fila->clientes_registrados,
                'activos' => (int) $fila->clientes_activos,
            ];
        }

        return $stats;
    }

    public function create()
    {
        $nodos = Nodo::orderBy('descripcion')->get();
        $modelosPorSerie = MikrotikModelosCatalogo::porSerie();

        return view('sistema.routers.create', compact('nodos', 'modelosPorSerie'));
    }

    public function store(Request $request)
    {
        $validated = $this->reglasRouter($request);

        $validated['api_port'] = $validated['api_port'] ?? 8728;
        $validated['estado'] = $validated['estado'] ?? 'desconocido';
        unset($validated['generar_webhook_token']);

        if ($request->boolean('generar_webhook_token') || empty($validated['webhook_token'])) {
            $validated['webhook_token'] = $this->nuevoWebhookToken();
        }

        Router::create($validated);

        return redirect()->route('sistema.routers.index')->with('success', 'Router creado correctamente.');
    }

    public function edit($router)
    {
        $router = Router::where('router_id', $router)->firstOrFail();
        $nodos = Nodo::orderBy('descripcion')->get();
        $modelosPorSerie = MikrotikModelosCatalogo::porSerie();

        return view('sistema.routers.edit', compact('router', 'nodos', 'modelosPorSerie'));
    }

    public function update(Request $request, $router)
    {
        $router = Router::where('router_id', $router)->firstOrFail();

        $validated = $this->reglasRouter($request, true);

        $validated['api_port'] = $validated['api_port'] ?? 8728;
        $validated['estado'] = $validated['estado'] ?? 'desconocido';
        unset($validated['generar_webhook_token']);

        if (empty($validated['password'])) {
            unset($validated['password']);
        }

        if ($request->boolean('generar_webhook_token')) {
            $validated['webhook_token'] = $this->nuevoWebhookToken();
        } elseif (array_key_exists('webhook_token', $validated) && $validated['webhook_token'] === null) {
            unset($validated['webhook_token']);
        }

        $router->update($validated);

        return redirect()->route('sistema.routers.index')->with('success', 'Router actualizado correctamente.');
    }

    public function destroy($router)
    {
        $router = Router::with('routerIpPools')->where('router_id', $router)->firstOrFail();
        $poolIds = $router->routerIpPools->pluck('pool_id');

        if ($poolIds->isNotEmpty()) {
            $serviciosCount = Servicio::whereIn('pool_id', $poolIds)->count();
            if ($serviciosCount > 0) {
                return redirect()
                    ->route('sistema.routers.index')
                    ->with('error', "No se puede eliminar: {$serviciosCount} servicio(s) usan pools de IP de este router. Reasigná o cancelá esos servicios primero.");
            }

            $pedidosCount = EstadoPedidoDetalle::whereIn('pool_id', $poolIds)->count();
            if ($pedidosCount > 0) {
                return redirect()
                    ->route('sistema.routers.index')
                    ->with('error', "No se puede eliminar: {$pedidosCount} pedido(s) referencian pools de IP de este router.");
            }
        }

        DB::transaction(function () use ($router) {
            foreach ($router->routerIpPools as $pool) {
                $pool->poolIpAsignadas()->delete();
                $pool->delete();
            }

            $router->delete();
        });

        return redirect()->route('sistema.routers.index')->with('success', 'Router eliminado correctamente.');
    }

    /**
     * Probar conexión al router MikroTik vía API.
     */
    public function testConnection($router, MikroTikService $mikrotik)
    {
        $router = Router::where('router_id', $router)->firstOrFail();
        $result = $mikrotik->testConnection($router);

        if ($result['success']) {
            return response()->json(['success' => true, 'message' => 'Conexión exitosa al router.']);
        }

        return response()->json(['success' => false, 'message' => $result['error'] ?? 'Error al conectar.'], 422);
    }

    /**
     * Sincronizar usuarios PPPoE desde la BD al router MikroTik.
     */
    public function syncPppoe(Request $request, $router, MikroTikService $mikrotik)
    {
        $router = Router::where('router_id', $router)->firstOrFail();
        $removeOrphans = $request->boolean('remove_orphans', false);

        try {
            $result = $mikrotik->syncPppoeFromDatabase($router, $removeOrphans);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'servicios_total' => 0,
                'added' => 0,
                'updated' => 0,
                'removed' => 0,
                'errors' => [$e->getMessage()],
            ], 422);
        }

        return response()->json([
            'success' => $result['success'],
            'message' => $result['message'] ?? null,
            'servicios_total' => $result['servicios_total'] ?? 0,
            'added' => $result['added'],
            'updated' => $result['updated'],
            'removed' => $result['removed'],
            'errors' => $result['errors'],
        ], ($result['success'] ?? false) ? 200 : 422);
    }

    /**
     * Exporta script RouterOS con usuarios PPPoE activos del router.
     * ?formato=consola (default) | archivo | json
     */
    public function exportPppoeScript(Request $request, $router, MikroTikService $mikrotik)
    {
        $router = Router::where('router_id', $router)->firstOrFail();
        $formato = $request->get('formato', 'consola');
        $paraConsola = $formato !== 'archivo';
        $script = $mikrotik->generarScriptPppoeExport($router, $paraConsola);
        $usuarios = $mikrotik->serviciosPppoeActivosDelRouter($router)->count();

        if ($formato === 'json' || $request->wantsJson()) {
            return response()->json([
                'script' => $script,
                'usuarios' => $usuarios,
                'router' => [
                    'id' => $router->router_id,
                    'nombre' => $router->nombre,
                    'ip' => $router->ip,
                ],
                'download_url' => route('sistema.routers.export-pppoe-script', [
                    'router' => $router,
                    'formato' => 'archivo',
                ]),
            ]);
        }

        $slug = preg_replace('/[^a-z0-9_-]+/i', '-', strtolower($router->nombre ?? 'router'));
        $slug = trim($slug, '-') ?: 'router';
        $extension = $paraConsola ? 'txt' : 'rsc';
        $filename = 'pppoe-'.$slug.'-'.$router->router_id.'-'.now()->format('Y-m-d').'.'.$extension;

        return response()->streamDownload(function () use ($script) {
            echo $script;
        }, $filename, [
            'Content-Type' => 'text/plain; charset=UTF-8',
        ]);
    }

    private function nuevoWebhookToken(): string
    {
        do {
            $token = Str::lower(Str::random(48));
        } while (Router::where('webhook_token', $token)->exists());

        return $token;
    }
}
