<?php

namespace App\Http\Controllers;

use App\Models\PerfilPppoe;
use App\Models\Router;
use App\Services\MikroTikService;
use Illuminate\Http\Request;

class PerfilPppoeController extends Controller
{
    /**
     * Listar perfiles PPPoE.
     */
    public function index(Request $request)
    {
        $query = PerfilPppoe::query()->orderBy('nombre');

        if ($request->filled('buscar')) {
            $q = $request->buscar;
            $query->where(function ($query) use ($q) {
                $query->where('nombre', 'like', "%{$q}%")
                    ->orWhere('local_address', 'like', "%{$q}%")
                    ->orWhere('remote_address', 'like', "%{$q}%")
                    ->orWhere('rate_limit_tx_rx', 'like', "%{$q}%");
            });
        }

        $perfilesPppoe = $query->paginate(15)->withQueryString();
        $routers = Router::with('nodo')->orderBy('nombre')->get();

        return view('perfiles-pppoe.index', compact('perfilesPppoe', 'routers'));
    }

    /**
     * Sincronizar perfiles PPPoE al router MikroTik seleccionado.
     */
    public function syncMikrotik(Request $request, MikroTikService $mikrotik)
    {
        $validated = $request->validate([
            'router_id' => ['required', 'integer', 'exists:routers,router_id'],
        ]);

        $router = Router::findOrFail($validated['router_id']);
        $result = $mikrotik->syncProfilesToRouter($router);

        if ($result['success']) {
            $msg = 'Perfiles sincronizados: ' . $result['added'] . ' añadidos, ' . $result['updated'] . ' actualizados.';
            return redirect()->route('perfiles-pppoe.index')->with('success', $msg);
        }

        $msg = 'Sincronización con errores: ' . implode('; ', $result['errors']);
        return redirect()->route('perfiles-pppoe.index')->with('error', $msg);
    }

    /**
     * Formulario crear perfil PPPoE.
     */
    public function create()
    {
        return view('perfiles-pppoe.create');
    }

    /**
     * Guardar nuevo perfil PPPoE.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'nombre' => ['nullable', 'string', 'max:150'],
            'local_address' => ['nullable', 'string', 'max:20'],
            'remote_address' => ['nullable', 'string', 'max:20'],
            'rate_limit_tx_rx' => ['nullable', 'string', 'max:100'],
        ]);

        PerfilPppoe::create($validated);

        return redirect()->route('perfiles-pppoe.index')->with('success', 'Perfil PPPoE creado correctamente.');
    }

    /**
     * Formulario editar perfil PPPoE.
     */
    public function edit(PerfilPppoe $perfilPppoe)
    {
        return view('perfiles-pppoe.edit', compact('perfilPppoe'));
    }

    /**
     * Actualizar perfil PPPoE.
     */
    public function update(Request $request, PerfilPppoe $perfilPppoe)
    {
        $validated = $request->validate([
            'nombre' => ['nullable', 'string', 'max:150'],
            'local_address' => ['nullable', 'string', 'max:20'],
            'remote_address' => ['nullable', 'string', 'max:20'],
            'rate_limit_tx_rx' => ['nullable', 'string', 'max:100'],
        ]);

        $perfilPppoe->update($validated);

        return redirect()->route('perfiles-pppoe.index')->with('success', 'Perfil PPPoE actualizado correctamente.');
    }

    /**
     * Eliminar perfil PPPoE.
     */
    public function destroy(PerfilPppoe $perfilPppoe)
    {
        $perfilPppoe->delete();

        return redirect()->route('perfiles-pppoe.index')->with('success', 'Perfil PPPoE eliminado correctamente.');
    }
}
