<?php

namespace App\Http\Controllers;

use App\Models\HotspotPerfil;
use App\Models\Router;
use App\Services\MikroTikService;
use Illuminate\Http\Request;
use Throwable;

class HotspotPerfilController extends Controller
{
    public function index(Request $request)
    {
        $query = HotspotPerfil::query()->orderBy('nombre');

        if ($request->filled('buscar')) {
            $q = $request->buscar;
            $query->where(function ($qry) use ($q) {
                $qry->where('nombre', 'like', "%{$q}%")
                    ->orWhere('rate_limit', 'like', "%{$q}%");
            });
        }

        $perfiles = $query->paginate(15)->withQueryString();
        $routers = Router::with('nodo')->orderBy('nombre')->get();

        return view('hotspot.perfiles.index', compact('perfiles', 'routers'));
    }

    public function create()
    {
        return view('hotspot.perfiles.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nombre' => ['required', 'string', 'max:150'],
            'rate_limit' => ['nullable', 'string', 'max:50'],
            'shared_users' => ['nullable', 'string', 'max:20'],
            'idle_timeout' => ['nullable', 'string', 'max:20'],
            'session_timeout' => ['nullable', 'string', 'max:20'],
        ]);

        HotspotPerfil::create($validated);

        return redirect()->route('hotspot.perfiles.index')->with('success', 'Perfil hotspot creado correctamente.');
    }

    public function edit(HotspotPerfil $perfil)
    {
        return view('hotspot.perfiles.edit', compact('perfil'));
    }

    public function update(Request $request, HotspotPerfil $perfil)
    {
        $validated = $request->validate([
            'nombre' => ['required', 'string', 'max:150'],
            'rate_limit' => ['nullable', 'string', 'max:50'],
            'shared_users' => ['nullable', 'string', 'max:20'],
            'idle_timeout' => ['nullable', 'string', 'max:20'],
            'session_timeout' => ['nullable', 'string', 'max:20'],
        ]);

        $perfil->update($validated);

        return redirect()->route('hotspot.perfiles.index')->with('success', 'Perfil hotspot actualizado correctamente.');
    }

    public function destroy(HotspotPerfil $perfil)
    {
        if ($perfil->servicioHotspots()->exists()) {
            return redirect()->route('hotspot.perfiles.index')->with('error', 'No se puede eliminar: hay servicios asociados.');
        }
        $perfil->delete();

        return redirect()->route('hotspot.perfiles.index')->with('success', 'Perfil hotspot eliminado correctamente.');
    }

    /**
     * Sincronizar perfiles hotspot al router MikroTik.
     */
    public function syncMikrotik(Request $request, MikroTikService $mikrotik)
    {
        $validated = $request->validate([
            'router_id' => ['required', 'integer', 'exists:routers,router_id'],
        ]);

        $router = Router::findOrFail($validated['router_id']);
        $perfiles = HotspotPerfil::orderBy('nombre')->get();
        $existing = $mikrotik->getHotspotUserProfiles($router);
        $existingByName = collect($existing)->keyBy('name');

        $added = 0;
        $updated = 0;
        $errors = [];

        foreach ($perfiles as $perfil) {
            try {
                if (isset($existingByName[$perfil->nombre])) {
                    $attrs = [];
                    if ($perfil->rate_limit) {
                        $attrs['rate_limit'] = $perfil->rate_limit;
                    }
                    if ($perfil->shared_users) {
                        $attrs['shared_users'] = $perfil->shared_users;
                    }
                    if (! empty($attrs)) {
                        $mikrotik->setHotspotUserProfile($router, $existingByName[$perfil->nombre]['.id'], $attrs);
                        $updated++;
                    }
                } else {
                    $mikrotik->addHotspotUserProfile($router, $perfil->nombre, $perfil->rate_limit, $perfil->shared_users);
                    $added++;
                }
            } catch (Throwable $e) {
                $errors[] = $perfil->nombre . ': ' . $e->getMessage();
            }
        }

        if (empty($errors)) {
            return redirect()->route('hotspot.perfiles.index')->with('success', "Perfiles sincronizados: {$added} añadidos, {$updated} actualizados.");
        }

        return redirect()->route('hotspot.perfiles.index')->with('error', 'Errores: ' . implode('; ', $errors));
    }
}
