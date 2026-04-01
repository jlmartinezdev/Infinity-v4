<?php

namespace App\Http\Controllers;

use App\Models\Router;
use App\Models\Nodo;
use App\Services\MikroTikService;
use Illuminate\Http\Request;

class RouterController extends Controller
{
    public function index(Request $request)
    {
        $query = Router::with('nodo')->orderBy('nombre');

        if ($request->filled('buscar')) {
            $q = $request->buscar;
            $query->where(function ($qry) use ($q) {
                $qry->where('nombre', 'like', "%{$q}%")
                    ->orWhere('ip', 'like', "%{$q}%")
                    ->orWhere('estado', 'like', "%{$q}%");
            });
        }

        if ($request->filled('nodo_id') && $request->nodo_id !== 'todos') {
            $query->where('nodo_id', $request->nodo_id);
        }

        $routers = $query->paginate(15)->withQueryString();
        $nodos = Nodo::orderBy('descripcion')->get();

        return view('sistema.routers.index', compact('routers', 'nodos'));
    }

    public function create()
    {
        $nodos = Nodo::orderBy('descripcion')->get();
        return view('sistema.routers.create', compact('nodos'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nodo_id' => ['required', 'integer', 'exists:nodos,nodo_id'],
            'nombre' => ['required', 'string', 'max:100'],
            'ip' => ['required', 'string', 'max:64'],
            'ip_loopback' => ['nullable', 'string', 'max:64'],
            'hotspot_servidor' => ['nullable', 'string', 'max:64'],
            'api_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'usuario' => ['required', 'string', 'max:64'],
            'password' => ['nullable', 'string', 'max:128'],
            'estado' => ['nullable', 'string', 'max:32'],
        ]);

        $validated['api_port'] = $validated['api_port'] ?? 8728;
        $validated['estado'] = $validated['estado'] ?? 'desconocido';

        Router::create($validated);

        return redirect()->route('sistema.routers.index')->with('success', 'Router creado correctamente.');
    }

    public function edit($router)
    {
        $router = Router::where('router_id', $router)->firstOrFail();
        $nodos = Nodo::orderBy('descripcion')->get();
        return view('sistema.routers.edit', compact('router', 'nodos'));
    }

    public function update(Request $request, $router)
    {
        $router = Router::where('router_id', $router)->firstOrFail();

        $validated = $request->validate([
            'nodo_id' => ['required', 'integer', 'exists:nodos,nodo_id'],
            'nombre' => ['required', 'string', 'max:100'],
            'ip' => ['required', 'string', 'max:64'],
            'ip_loopback' => ['nullable', 'string', 'max:64'],
            'hotspot_servidor' => ['nullable', 'string', 'max:64'],
            'api_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'usuario' => ['required', 'string', 'max:64'],
            'password' => ['nullable', 'string', 'max:128'],
            'estado' => ['nullable', 'string', 'max:32'],
        ]);

        $validated['api_port'] = $validated['api_port'] ?? 8728;
        $validated['estado'] = $validated['estado'] ?? 'desconocido';

        if (empty($validated['password'])) {
            unset($validated['password']);
        }

        $router->update($validated);

        return redirect()->route('sistema.routers.index')->with('success', 'Router actualizado correctamente.');
    }

    public function destroy($router)
    {
        $router = Router::where('router_id', $router)->firstOrFail();
        $router->delete();

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
        $result = $mikrotik->syncPppoeFromDatabase($router, $removeOrphans);

        return response()->json([
            'success' => $result['success'],
            'added' => $result['added'],
            'updated' => $result['updated'],
            'removed' => $result['removed'],
            'errors' => $result['errors'],
        ]);
    }
}
