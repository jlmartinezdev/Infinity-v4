<?php

namespace App\Http\Controllers;

use App\Models\Nodo;
use Illuminate\Http\Request;

class NodoController extends Controller
{
    /**
     * Listar nodos.
     */
    public function index(Request $request)
    {
        $query = Nodo::query()->orderBy('descripcion');

        if ($request->filled('buscar')) {
            $q = $request->buscar;
            $query->where(function ($query) use ($q) {
                $query->where('descripcion', 'like', "%{$q}%")
                    ->orWhere('ciudad', 'like', "%{$q}%")
                    ->orWhere('coordenas_gps', 'like', "%{$q}%");
            });
        }

        $nodos = $query->paginate(15)->withQueryString();

        return view('nodos.index', compact('nodos'));
    }

    /**
     * Formulario crear nodo.
     */
    public function create()
    {
        return view('nodos.create');
    }

    /**
     * Guardar nuevo nodo.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'descripcion' => ['nullable', 'string', 'max:120'],
            'coordenas_gps' => ['nullable', 'string', 'max:50'],
            'ciudad' => ['nullable', 'string', 'max:50'],
        ]);

        Nodo::create($validated);

        return redirect()->route('nodos.index')->with('success', 'Nodo creado correctamente.');
    }

    /**
     * Formulario editar nodo.
     */
    public function edit($nodo)
    {
        $nodo = Nodo::where('nodo_id', $nodo)->firstOrFail();
        return view('nodos.edit', compact('nodo'));
    }

    /**
     * Actualizar nodo.
     */
    public function update(Request $request, $nodo)
    {
        $nodo = Nodo::where('nodo_id', $nodo)->firstOrFail();

        $validated = $request->validate([
            'descripcion' => ['nullable', 'string', 'max:120'],
            'coordenas_gps' => ['nullable', 'string', 'max:50'],
            'ciudad' => ['nullable', 'string', 'max:50'],
        ]);

        $nodo->update($validated);

        return redirect()->route('nodos.index')->with('success', 'Nodo actualizado correctamente.');
    }

    /**
     * Eliminar nodo.
     */
    public function destroy($nodo)
    {
        $nodo = Nodo::where('nodo_id', $nodo)->firstOrFail();
        $nodo->delete();

        return redirect()->route('nodos.index')->with('success', 'Nodo eliminado correctamente.');
    }
}
