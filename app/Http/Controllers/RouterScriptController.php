<?php

namespace App\Http\Controllers;

use App\Models\Router;
use App\Models\RouterScript;
use App\Services\MikroTikService;
use Illuminate\Http\Request;
use Throwable;

class RouterScriptController extends Controller
{
    public function index(Request $request)
    {
        $query = RouterScript::with('routerOrigen')->orderBy('nombre');

        if ($request->filled('buscar')) {
            $q = $request->buscar;
            $query->where(function ($qry) use ($q) {
                $qry->where('nombre', 'like', "%{$q}%")
                    ->orWhere('notas', 'like', "%{$q}%")
                    ->orWhere('source', 'like', "%{$q}%");
            });
        }

        if ($request->filled('router_origen_id') && $request->router_origen_id !== 'todos') {
            $query->where('router_origen_id', $request->router_origen_id);
        }

        $scripts = $query->paginate(20)->withQueryString();
        $routers = Router::orderBy('nombre')->get(['router_id', 'nombre', 'ip']);

        return view('sistema.router-scripts.index', compact('scripts', 'routers'));
    }

    public function edit(RouterScript $router_script)
    {
        $script = $router_script->load('routerOrigen');
        $routers = Router::orderBy('nombre')->get(['router_id', 'nombre', 'ip']);

        return view('sistema.router-scripts.edit', compact('script', 'routers'));
    }

    public function update(Request $request, RouterScript $router_script)
    {
        $validated = $request->validate([
            'nombre' => ['required', 'string', 'max:128', 'unique:router_scripts,nombre,'.$router_script->router_script_id.',router_script_id'],
            'source' => ['required', 'string'],
            'owner' => ['nullable', 'string', 'max:64'],
            'policy' => ['nullable', 'string', 'max:255'],
            'dont_require_permissions' => ['nullable', 'boolean'],
            'notas' => ['nullable', 'string', 'max:255'],
        ]);

        $validated['dont_require_permissions'] = $request->boolean('dont_require_permissions');
        $router_script->update($validated);

        return redirect()
            ->route('sistema.router-scripts.index')
            ->with('success', 'Script actualizado en la base de datos.');
    }

    public function destroy(RouterScript $router_script)
    {
        $nombre = $router_script->nombre;
        $router_script->delete();

        return redirect()
            ->route('sistema.router-scripts.index')
            ->with('success', "Script «{$nombre}» eliminado de la base de datos (no se borró del router).");
    }

    /**
     * Lista scripts remotos de un router (AJAX).
     */
    public function listRemote(Request $request, MikroTikService $mikrotik)
    {
        $validated = $request->validate([
            'router_id' => ['required', 'integer', 'exists:routers,router_id'],
        ]);

        $router = Router::where('router_id', $validated['router_id'])->firstOrFail();

        try {
            $remotos = $mikrotik->getSystemScripts($router);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'scripts' => [],
            ], 422);
        }

        $scripts = collect($remotos)
            ->map(function (array $s) {
                $source = (string) ($s['source'] ?? '');

                return [
                    'name' => (string) ($s['name'] ?? ''),
                    'owner' => (string) ($s['owner'] ?? ''),
                    'policy' => (string) ($s['policy'] ?? ''),
                    'dont_require_permissions' => (string) ($s['dont-require-permissions'] ?? 'no'),
                    'run_count' => (string) ($s['run-count'] ?? '0'),
                    'source_preview' => mb_substr(preg_replace('/\s+/', ' ', $source) ?? '', 0, 120),
                    'source_length' => mb_strlen($source),
                ];
            })
            ->filter(fn ($s) => $s['name'] !== '')
            ->values()
            ->all();

        return response()->json([
            'success' => true,
            'router' => [
                'id' => $router->router_id,
                'nombre' => $router->nombre,
                'ip' => $router->ip,
            ],
            'scripts' => $scripts,
        ]);
    }

    /**
     * Lee scripts del router y los guarda en BD.
     */
    public function importFromRouter(Request $request, MikroTikService $mikrotik)
    {
        $validated = $request->validate([
            'router_id' => ['required', 'integer', 'exists:routers,router_id'],
            'nombres' => ['nullable', 'array'],
            'nombres.*' => ['string', 'max:128'],
        ]);

        $router = Router::where('router_id', $validated['router_id'])->firstOrFail();
        $nombres = $validated['nombres'] ?? null;
        if (is_array($nombres) && $nombres === []) {
            $nombres = null;
        }

        $result = $mikrotik->importSystemScriptsToDatabase($router, $nombres);

        return response()->json([
            'success' => $result['success'],
            'message' => sprintf(
                'Importados: %d nuevos, %d actualizados.',
                $result['imported'],
                $result['updated']
            ),
            'imported' => $result['imported'],
            'updated' => $result['updated'],
            'errors' => $result['errors'],
        ], ($result['success'] || ($result['imported'] + $result['updated']) > 0) ? 200 : 422);
    }

    /**
     * Sincroniza un script de la BD hacia un router destino.
     */
    public function syncToRouter(Request $request, RouterScript $router_script, MikroTikService $mikrotik)
    {
        $validated = $request->validate([
            'router_id' => ['required', 'integer', 'exists:routers,router_id'],
        ]);

        $destino = Router::where('router_id', $validated['router_id'])->firstOrFail();

        try {
            $result = $mikrotik->syncScriptToRouter($router_script, $destino);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }

        if (! ($result['success'] ?? false)) {
            return response()->json([
                'success' => false,
                'message' => $result['error'] ?? 'No se pudo sincronizar el script.',
            ], 422);
        }

        $accion = ($result['action'] ?? '') === 'added' ? 'creado' : 'actualizado';

        return response()->json([
            'success' => true,
            'message' => "Script «{$router_script->nombre}» {$accion} en {$destino->nombre} ({$destino->ip}).",
            'action' => $result['action'] ?? null,
        ]);
    }
}
