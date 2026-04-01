<?php

namespace App\Http\Controllers;

use App\Models\Router;
use App\Models\ServicioHotspot;
use App\Services\MikroTikService;
use Illuminate\Http\Request;

class HotspotController extends Controller
{
    /**
     * Dashboard: clientes activos en hotspot por router.
     */
    public function dashboard(Request $request, MikroTikService $mikrotik)
    {
        $routerId = $request->get('router_id');
        $routers = Router::with('nodo')->orderBy('nombre')->get();

        $activeHosts = [];
        $selectedRouter = null;

        if ($routerId) {
            $selectedRouter = Router::find($routerId);
            if ($selectedRouter) {
                try {
                    $activeHosts = $mikrotik->getHotspotActiveHosts($selectedRouter, $selectedRouter->hotspot_servidor);
                } catch (\Throwable $e) {
                    $activeHosts = [];
                    session()->flash('error', 'Error al conectar con el router: ' . $e->getMessage());
                }
            }
        }

        $servicioHotspots = ServicioHotspot::with(['servicio.cliente', 'servicio.plan', 'router', 'hotspotPerfil'])
            ->when($routerId, fn ($q) => $q->where('router_id', $routerId))
            ->orderByDesc('updated_at')
            ->paginate(20)
            ->withQueryString();

        return view('hotspot.dashboard', compact('routers', 'activeHosts', 'selectedRouter', 'servicioHotspots'));
    }

    /**
     * Lista de usuarios hotspot asociados a servicios.
     */
    public function index(Request $request)
    {
        $query = ServicioHotspot::with(['servicio.cliente', 'servicio.plan', 'router', 'hotspotPerfil']);

        if ($request->filled('router_id')) {
            $query->where('router_id', $request->router_id);
        }
        if ($request->filled('buscar')) {
            $q = $request->buscar;
            $query->where(function ($qry) use ($q) {
                $qry->where('username', 'like', "%{$q}%")
                    ->orWhere('comment', 'like', "%{$q}%")
                    ->orWhereHas('servicio.cliente', fn ($c) => $c->where('nombre', 'like', "%{$q}%")->orWhere('apellido', 'like', "%{$q}%"));
            });
        }

        $servicioHotspots = $query->orderByDesc('updated_at')->paginate(20)->withQueryString();
        $routers = Router::orderBy('nombre')->get();

        return view('hotspot.index', compact('servicioHotspots', 'routers'));
    }

    /**
     * Formulario para asociar hotspot a un servicio.
     */
    public function create(Request $request)
    {
        $servicioId = $request->get('servicio_id');
        $servicio = $servicioId ? \App\Models\Servicio::with('cliente')->find($servicioId) : null;
        $routers = Router::orderBy('nombre')->get();
        $perfiles = \App\Models\HotspotPerfil::orderBy('nombre')->get();

        return view('hotspot.create', compact('servicio', 'routers', 'perfiles'));
    }

    /**
     * Guardar asociación servicio-hotspot.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'servicio_id' => ['required', 'integer', 'exists:servicios,servicio_id', 'unique:servicio_hotspot,servicio_id'],
            'router_id' => ['required', 'integer', 'exists:routers,router_id'],
            'hotspot_perfil_id' => ['nullable', 'integer', 'exists:hotspot_perfiles,hotspot_perfil_id'],
            'username' => ['required', 'string', 'max:64'],
            'password' => ['required', 'string', 'max:64'],
            'comment' => ['nullable', 'string', 'max:255'],
        ]);

        ServicioHotspot::create($validated);

        return redirect()->route('hotspot.index')->with('success', 'Usuario hotspot asociado al servicio correctamente.');
    }

    /**
     * Sincronizar un usuario hotspot al router.
     */
    public function sync(Request $request, ServicioHotspot $servicioHotspot, MikroTikService $mikrotik)
    {
        $result = $mikrotik->syncHotspotServicio($servicioHotspot);

        if ($request->wantsJson()) {
            return response()->json($result);
        }

        if ($result['success']) {
            return redirect()->back()->with('success', 'Usuario hotspot sincronizado correctamente.');
        }

        return redirect()->back()->with('error', $result['error'] ?? 'Error al sincronizar.');
    }
}
