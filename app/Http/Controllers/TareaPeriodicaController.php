<?php

namespace App\Http\Controllers;

use App\Models\TareaPeriodica;
use App\Models\Nodo;
use Illuminate\Http\Request;

class TareaPeriodicaController extends Controller
{
    public function index()
    {
        $tareas = TareaPeriodica::with('nodo')->orderBy('nombre')->get();
        return view('tareas-periodicas.index', compact('tareas'));
    }

    public function create()
    {
        $nodos = Nodo::orderBy('descripcion')->get();
        return view('tareas-periodicas.create', compact('nodos'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:255',
            'accion' => 'required|string|max:100',
            'estado' => 'required|in:activo,pausado,error',
            'nodo_id' => 'nullable|exists:nodos,nodo_id',
        ]);

        TareaPeriodica::create([
            'nombre' => $validated['nombre'],
            'accion' => $validated['accion'],
            'estado' => $validated['estado'],
            'nodo_id' => $validated['nodo_id'] ?? null,
        ]);

        return redirect()->route('tareas-periodicas.index')->with('success', 'Tarea periódica creada correctamente.');
    }

    public function edit(TareaPeriodica $tareaPeriodica)
    {
        $nodos = Nodo::orderBy('descripcion')->get();
        return view('tareas-periodicas.edit', compact('tareaPeriodica', 'nodos'));
    }

    public function update(Request $request, TareaPeriodica $tareaPeriodica)
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:255',
            'accion' => 'required|string|max:100',
            'estado' => 'required|in:activo,pausado,error',
            'nodo_id' => 'nullable|exists:nodos,nodo_id',
        ]);

        $tareaPeriodica->update([
            'nombre' => $validated['nombre'],
            'accion' => $validated['accion'],
            'estado' => $validated['estado'],
            'nodo_id' => $validated['nodo_id'] ?? null,
        ]);

        return redirect()->route('tareas-periodicas.index')->with('success', 'Tarea periódica actualizada correctamente.');
    }

    public function destroy(TareaPeriodica $tareaPeriodica)
    {
        $tareaPeriodica->delete();
        return redirect()->route('tareas-periodicas.index')->with('success', 'Tarea periódica eliminada correctamente.');
    }
}
