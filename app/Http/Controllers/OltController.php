<?php

namespace App\Http\Controllers;

use App\Models\Nodo;
use App\Models\Olt;
use App\Models\OltMarca;
use Illuminate\Http\Request;

class OltController extends Controller
{
    public function index(Request $request)
    {
        $query = Olt::with(['nodo', 'oltMarca', 'oltPuertos'])->orderBy('ip');

        if ($request->filled('nodo_id')) {
            $query->where('nodo_id', $request->nodo_id);
        }
        if ($request->filled('olt_marca_id')) {
            $query->where('olt_marca_id', $request->olt_marca_id);
        }

        $olts = $query->paginate(15)->withQueryString();
        $nodos = Nodo::orderBy('descripcion')->get();
        $marcas = OltMarca::where('estado', 'activo')->orderBy('nombre')->get();

        return view('olts.index', compact('olts', 'nodos', 'marcas'));
    }

    public function create()
    {
        $nodos = Nodo::orderBy('descripcion')->get();
        $marcas = OltMarca::where('estado', 'activo')->orderBy('nombre')->get();

        return view('olts.create', compact('nodos', 'marcas'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nodo_id' => ['required', 'exists:nodos,nodo_id'],
            'olt_marca_id' => ['required', 'exists:olt_marcas,olt_marca_id'],
            'modelo' => ['nullable', 'string', 'max:100'],
            'ip' => ['nullable', 'string', 'max:45'],
            'cantidad_puertos' => ['nullable', 'integer', 'min:1', 'max:128'],
            'tipo_pon' => ['required', 'in:GPON,EPON,XG-PON'],
            'estado' => ['nullable', 'string', 'max:20'],
            'notas' => ['nullable', 'string'],
        ]);

        $validated['cantidad_puertos'] = $validated['cantidad_puertos'] ?? 8;
        $validated['estado'] = $validated['estado'] ?? 'activo';

        $olt = Olt::create($validated);

        return redirect()->route('sistema.olts.show', $olt)->with('success', 'OLT creado correctamente.');
    }

    public function show(Olt $olt)
    {
        $olt->load(['nodo', 'oltMarca', 'oltPuertos.salidaPons']);

        return view('olts.show', compact('olt'));
    }

    public function edit(Olt $olt)
    {
        $nodos = Nodo::orderBy('descripcion')->get();
        $marcas = OltMarca::where('estado', 'activo')->orderBy('nombre')->get();

        return view('olts.edit', compact('olt', 'nodos', 'marcas'));
    }

    public function update(Request $request, Olt $olt)
    {
        $validated = $request->validate([
            'nodo_id' => ['required', 'exists:nodos,nodo_id'],
            'olt_marca_id' => ['required', 'exists:olt_marcas,olt_marca_id'],
            'modelo' => ['nullable', 'string', 'max:100'],
            'ip' => ['nullable', 'string', 'max:45'],
            'cantidad_puertos' => ['nullable', 'integer', 'min:1', 'max:128'],
            'tipo_pon' => ['required', 'in:GPON,EPON,XG-PON'],
            'estado' => ['nullable', 'string', 'max:20'],
            'notas' => ['nullable', 'string'],
        ]);

        $olt->update($validated);

        return redirect()->route('sistema.olts.show', $olt)->with('success', 'OLT actualizado correctamente.');
    }

    public function destroy(Olt $olt)
    {
        $olt->delete();

        return redirect()->route('sistema.olts.index')->with('success', 'OLT eliminado correctamente.');
    }
}
