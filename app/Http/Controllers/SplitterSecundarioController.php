<?php

namespace App\Http\Controllers;

use App\Models\CajaNap;
use App\Models\SplitterPrimario;
use App\Models\SplitterSecundario;
use Illuminate\Http\Request;

class SplitterSecundarioController extends Controller
{
    public function create(CajaNap $cajaNap)
    {
        $cajaNap->load('splitterPrimarios');
        $splitterPrimario = $cajaNap->splitterPrimarios->firstWhere('splitter_primario_id', request('splitter_primario_id'));
        return view('splitters-secundarios.create', compact('cajaNap', 'splitterPrimario'));
    }

    public function store(Request $request, CajaNap $cajaNap)
    {
        $validated = $request->validate([
            'splitter_primario_id' => ['required', 'exists:splitter_primarios,splitter_primario_id'],
            'codigo' => ['required', 'string', 'max:50'],
            'ratio' => ['required', 'string', 'max:20', 'in:1:2,1:4,1:8'],
            'puerto_entrada' => ['nullable', 'integer', 'min:1'],
            'potencia_entrada' => ['nullable', 'numeric', 'between:-50,10'],
            'potencia_salida' => ['nullable', 'numeric', 'between:-50,10'],
            'estado' => ['nullable', 'string', 'max:20'],
            'notas' => ['nullable', 'string'],
        ]);

        $validated['caja_nap_id'] = $cajaNap->caja_nap_id;
        $validated['puerto_entrada'] = $validated['puerto_entrada'] ?? 1;
        $validated['estado'] = $validated['estado'] ?? 'activo';

        SplitterSecundario::create($validated);

        return redirect()->route('sistema.cajas-nap.show', $cajaNap)
            ->with('success', 'Splitter secundario creado correctamente.');
    }

    public function edit(SplitterSecundario $splitterSecundario)
    {
        $splitterSecundario->load(['cajaNap.splitterPrimarios', 'splitterPrimario']);
        return view('splitters-secundarios.edit', compact('splitterSecundario'));
    }

    public function update(Request $request, SplitterSecundario $splitterSecundario)
    {
        $validated = $request->validate([
            'splitter_primario_id' => ['required', 'exists:splitter_primarios,splitter_primario_id'],
            'codigo' => ['required', 'string', 'max:50'],
            'ratio' => ['required', 'string', 'max:20', 'in:1:2,1:4,1:8'],
            'puerto_entrada' => ['nullable', 'integer', 'min:1'],
            'potencia_entrada' => ['nullable', 'numeric', 'between:-50,10'],
            'potencia_salida' => ['nullable', 'numeric', 'between:-50,10'],
            'estado' => ['nullable', 'string', 'max:20'],
            'notas' => ['nullable', 'string'],
        ]);

        $splitterSecundario->update($validated);

        return redirect()->route('sistema.cajas-nap.show', $splitterSecundario->cajaNap)
            ->with('success', 'Splitter secundario actualizado correctamente.');
    }

    public function destroy(SplitterSecundario $splitterSecundario)
    {
        $cajaNap = $splitterSecundario->cajaNap;
        $splitterSecundario->delete();

        return redirect()->route('sistema.cajas-nap.show', $cajaNap)
            ->with('success', 'Splitter secundario eliminado correctamente.');
    }
}
