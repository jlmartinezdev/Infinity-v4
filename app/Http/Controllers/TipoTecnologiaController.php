<?php

namespace App\Http\Controllers;

use App\Models\TipoTecnologia;
use Illuminate\Http\Request;

class TipoTecnologiaController extends Controller
{
    /**
     * Listar tipos de tecnologías.
     */
    public function index(Request $request)
    {
        $query = TipoTecnologia::query()->orderBy('descripcion');

        if ($request->filled('buscar')) {
            $q = $request->buscar;
            $query->where('descripcion', 'like', "%{$q}%");
        }

        $tiposTecnologias = $query->paginate(15)->withQueryString();

        return view('tipos-tecnologias.index', compact('tiposTecnologias'));
    }

    /**
     * Formulario crear tipo de tecnología.
     */
    public function create()
    {
        return view('tipos-tecnologias.create');
    }

    /**
     * Guardar nuevo tipo de tecnología.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'descripcion' => ['required', 'string', 'max:50'],
        ]);

        TipoTecnologia::create($validated);

        return redirect()->route('tipos-tecnologias.index')->with('success', 'Tipo de tecnología creado correctamente.');
    }

    /**
     * Formulario editar tipo de tecnología.
     * El parámetro debe llamarse $tipos_tecnologia para coincidir con la ruta {tipos_tecnologia}.
     */
    public function edit(TipoTecnologia $tipos_tecnologia)
    {
        return view('tipos-tecnologias.edit', ['tipoTecnologia' => $tipos_tecnologia]);
    }

    /**
     * Actualizar tipo de tecnología.
     */
    public function update(Request $request, TipoTecnologia $tipos_tecnologia)
    {
        $validated = $request->validate([
            'descripcion' => ['required', 'string', 'max:50'],
        ]);

        $tipos_tecnologia->update($validated);

        return redirect()->route('tipos-tecnologias.index')->with('success', 'Tipo de tecnología actualizado correctamente.');
    }

    /**
     * Eliminar tipo de tecnología.
     */
    public function destroy(TipoTecnologia $tipos_tecnologia)
    {
        $tipos_tecnologia->delete();

        return redirect()->route('tipos-tecnologias.index')->with('success', 'Tipo de tecnología eliminado correctamente.');
    }
}
