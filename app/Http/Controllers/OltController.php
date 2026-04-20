<?php

namespace App\Http\Controllers;

use App\Models\Nodo;
use App\Models\Olt;
use Illuminate\Http\Request;

class OltController extends Controller
{
    public function index(Request $request)
    {
        $query = Olt::with(['nodo', 'oltPuertos'])->orderBy('codigo')->orderBy('ip');

        if ($request->filled('nodo_id')) {
            $query->where('nodo_id', $request->nodo_id);
        }
        if ($request->filled('marca')) {
            $query->where('marca', 'like', '%'.$request->marca.'%');
        }

        $olts = $query->paginate(15)->withQueryString();
        $nodos = Nodo::orderBy('descripcion')->get();

        return view('olts.index', compact('olts', 'nodos'));
    }

    public function create()
    {
        $nodos = Nodo::orderBy('descripcion')->get();

        return view('olts.create', compact('nodos'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nodo_id' => ['required', 'exists:nodos,nodo_id'],
            'marca' => ['required', 'string', 'max:100'],
            'codigo' => ['nullable', 'string', 'max:50'],
            'modelo' => ['nullable', 'string', 'max:50'],
            'ip' => ['nullable', 'string', 'max:45'],
            'cantidad_puerto' => ['nullable', 'integer', 'min:1', 'max:128'],
            'tipo_pon' => ['required', 'in:GPON,EPON,XG-PON'],
            'estado' => ['nullable', 'string', 'max:20'],
            'notas' => ['nullable', 'string'],
        ]);

        $validated['cantidad_puerto'] = $validated['cantidad_puerto'] ?? 8;
        $validated['estado'] = $validated['estado'] ?? 'activo';
        if (empty($validated['codigo'])) {
            unset($validated['codigo']);
        }

        $olt = Olt::create($validated);
        if (empty($olt->codigo)) {
            $olt->update(['codigo' => 'OLT-'.$olt->olt_id]);
        }

        return redirect()->route('sistema.olts.show', $olt)->with('success', 'OLT creado correctamente.');
    }

    public function show(Olt $olt)
    {
        $olt->load(['nodo', 'oltPuertos', 'salidaPons']);

        return view('olts.show', compact('olt'));
    }

    public function edit(Olt $olt)
    {
        $nodos = Nodo::orderBy('descripcion')->get();

        return view('olts.edit', compact('olt', 'nodos'));
    }

    public function update(Request $request, Olt $olt)
    {
        $validated = $request->validate([
            'nodo_id' => ['required', 'exists:nodos,nodo_id'],
            'marca' => ['required', 'string', 'max:100'],
            'codigo' => ['nullable', 'string', 'max:50'],
            'modelo' => ['nullable', 'string', 'max:50'],
            'ip' => ['nullable', 'string', 'max:45'],
            'cantidad_puerto' => ['nullable', 'integer', 'min:1', 'max:128'],
            'tipo_pon' => ['required', 'in:GPON,EPON,XG-PON'],
            'estado' => ['nullable', 'string', 'max:20'],
            'notas' => ['nullable', 'string'],
        ]);

        if (empty($validated['codigo'])) {
            unset($validated['codigo']);
        }

        $olt->update($validated);

        return redirect()->route('sistema.olts.show', $olt)->with('success', 'OLT actualizado correctamente.');
    }

    public function destroy(Olt $olt)
    {
        $olt->delete();

        return redirect()->route('sistema.olts.index')->with('success', 'OLT eliminado correctamente.');
    }
}
