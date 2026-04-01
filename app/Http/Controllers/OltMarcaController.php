<?php

namespace App\Http\Controllers;

use App\Models\OltMarca;
use Illuminate\Http\Request;

class OltMarcaController extends Controller
{
    public function index(Request $request)
    {
        $query = OltMarca::query()->orderBy('nombre');

        if ($request->filled('buscar')) {
            $q = $request->buscar;
            $query->where('nombre', 'like', "%{$q}%");
        }

        $marcas = $query->paginate(15)->withQueryString();

        return view('olt-marcas.index', compact('marcas'));
    }

    public function create()
    {
        return view('olt-marcas.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nombre' => ['required', 'string', 'max:100'],
            'estado' => ['nullable', 'string', 'max:20'],
            'notas' => ['nullable', 'string'],
        ]);

        $validated['estado'] = $validated['estado'] ?? 'activo';

        OltMarca::create($validated);

        return redirect()->route('sistema.olt-marcas.index')->with('success', 'Marca OLT creada correctamente.');
    }

    public function edit(OltMarca $oltMarca)
    {
        return view('olt-marcas.edit', compact('oltMarca'));
    }

    public function update(Request $request, OltMarca $oltMarca)
    {
        $validated = $request->validate([
            'nombre' => ['required', 'string', 'max:100'],
            'estado' => ['nullable', 'string', 'max:20'],
            'notas' => ['nullable', 'string'],
        ]);

        $oltMarca->update($validated);

        return redirect()->route('sistema.olt-marcas.index')->with('success', 'Marca OLT actualizada correctamente.');
    }

    public function destroy(OltMarca $oltMarca)
    {
        if ($oltMarca->olts()->exists()) {
            return redirect()->route('sistema.olt-marcas.index')
                ->with('error', 'No se puede eliminar: hay OLTs asociados a esta marca.');
        }

        $oltMarca->delete();

        return redirect()->route('sistema.olt-marcas.index')->with('success', 'Marca OLT eliminada correctamente.');
    }
}
