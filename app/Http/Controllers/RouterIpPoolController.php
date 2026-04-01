<?php

namespace App\Http\Controllers;

use App\Models\RouterIpPool;
use App\Models\Router;
use Illuminate\Http\Request;

class RouterIpPoolController extends Controller
{
    public function index(Request $request)
    {
        $query = RouterIpPool::with('router.nodo')->orderBy('pool_id');

        if ($request->filled('buscar')) {
            $q = $request->buscar;
            $query->where(function ($qry) use ($q) {
                $qry->where('ip_range', 'like', "%{$q}%")
                    ->orWhere('descripcion', 'like', "%{$q}%")
                    ->orWhereHas('router', function ($r) use ($q) {
                        $r->where('nombre', 'like', "%{$q}%");
                    });
            });
        }

        if ($request->filled('router_id') && $request->router_id !== 'todos') {
            $query->where('router_id', $request->router_id);
        }

        $pools = $query->paginate(15)->withQueryString();
        $routers = Router::with('nodo')->orderBy('nombre')->get();

        return view('sistema.router-ip-pools.index', compact('pools', 'routers'));
    }

    public function create(Request $request)
    {
        $routers = Router::with('nodo')->orderBy('nombre')->get();
        $routerId = $request->get('router_id');
        return view('sistema.router-ip-pools.create', compact('routers', 'routerId'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'router_id' => ['required', 'integer', 'exists:routers,router_id'],
            'ip_range' => ['required', 'string', 'max:64'],
            'descripcion' => ['nullable', 'string', 'max:255'],
            'activo' => ['nullable', 'boolean'],
        ]);

        $validated['activo'] = $request->boolean('activo', true);

        RouterIpPool::create($validated);

        return redirect()->route('sistema.router-ip-pools.index')->with('success', 'Pool de IP creado correctamente.');
    }

    public function edit($pool)
    {
        $pool = RouterIpPool::where('pool_id', $pool)->firstOrFail();
        $routers = Router::with('nodo')->orderBy('nombre')->get();
        return view('sistema.router-ip-pools.edit', compact('pool', 'routers'));
    }

    public function update(Request $request, $pool)
    {
        $pool = RouterIpPool::where('pool_id', $pool)->firstOrFail();

        $validated = $request->validate([
            'router_id' => ['required', 'integer', 'exists:routers,router_id'],
            'ip_range' => ['required', 'string', 'max:64'],
            'descripcion' => ['nullable', 'string', 'max:255'],
            'activo' => ['nullable', 'boolean'],
        ]);

        $validated['activo'] = $request->boolean('activo', true);

        $pool->update($validated);

        return redirect()->route('sistema.router-ip-pools.index')->with('success', 'Pool de IP actualizado correctamente.');
    }

    public function destroy($pool)
    {
        $pool = RouterIpPool::where('pool_id', $pool)->firstOrFail();
        $pool->poolIpAsignadas()->delete();
        $pool->delete();

        return redirect()->route('sistema.router-ip-pools.index')->with('success', 'Pool de IP eliminado correctamente.');
    }
}
