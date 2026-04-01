<?php

namespace App\Http\Controllers;

use App\Models\CajaNap;
use App\Models\SplitterPrimario;
use Illuminate\Http\Request;

class SplitterPrimarioController extends Controller
{
    public function create(CajaNap $cajaNap)
    {
        return view('splitters-primarios.create', compact('cajaNap'));
    }

    public function store(Request $request, CajaNap $cajaNap)
    {
        $validated = $request->validate([
            'codigo' => ['required', 'string', 'max:50'],
            'ratio' => ['required', 'string', 'max:20', 'in:1:2,1:4,1:8,1:16,1:32'],
            'puerto_entrada' => ['nullable', 'integer', 'min:1'],
            'potencia_entrada' => ['nullable', 'numeric', 'between:-50,10'],
            'potencia_salida' => ['nullable', 'numeric', 'between:-50,10'],
            'estado' => ['nullable', 'string', 'max:20'],
            'notas' => ['nullable', 'string'],
        ]);

        $validated['caja_nap_id'] = $cajaNap->caja_nap_id;
        $validated['puerto_entrada'] = $validated['puerto_entrada'] ?? 1;
        $validated['estado'] = $validated['estado'] ?? 'activo';

        SplitterPrimario::create($validated);

        return redirect()->route('sistema.cajas-nap.show', $cajaNap)
            ->with('success', 'Splitter primario creado correctamente.');
    }

    public function edit(SplitterPrimario $splitterPrimario)
    {
        $splitterPrimario->load('cajaNap');
        return view('splitters-primarios.edit', compact('splitterPrimario'));
    }

    public function update(Request $request, SplitterPrimario $splitterPrimario)
    {
        $validated = $request->validate([
            'codigo' => ['required', 'string', 'max:50'],
            'ratio' => ['required', 'string', 'max:20', 'in:1:2,1:4,1:8,1:16,1:32'],
            'puerto_entrada' => ['nullable', 'integer', 'min:1'],
            'potencia_entrada' => ['nullable', 'numeric', 'between:-50,10'],
            'potencia_salida' => ['nullable', 'numeric', 'between:-50,10'],
            'estado' => ['nullable', 'string', 'max:20'],
            'notas' => ['nullable', 'string'],
        ]);

        $splitterPrimario->update($validated);

        return redirect()->route('sistema.cajas-nap.show', $splitterPrimario->cajaNap)
            ->with('success', 'Splitter primario actualizado correctamente.');
    }

    public function destroy(SplitterPrimario $splitterPrimario)
    {
        $cajaNap = $splitterPrimario->cajaNap;
        $splitterPrimario->delete();

        return redirect()->route('sistema.cajas-nap.show', $cajaNap)
            ->with('success', 'Splitter primario eliminado correctamente.');
    }
}
