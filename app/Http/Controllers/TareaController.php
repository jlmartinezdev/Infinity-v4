<?php

namespace App\Http\Controllers;

use App\Models\Tarea;
use Illuminate\Http\Request;

class TareaController extends Controller
{
    public function index()
    {
        $tareas = Tarea::with(['creador', 'asignado'])
            ->orderByRaw("CASE estado WHEN 'pendiente' THEN 1 WHEN 'en_progreso' THEN 2 WHEN 'completado' THEN 3 END")
            ->orderBy('orden')
            ->orderBy('id')
            ->get();

        $usuarios = \App\Models\User::where('estado', 'activo')
            ->orderBy('name')
            ->get(['usuario_id', 'name']);

        return view('tareas.index', compact('tareas', 'usuarios'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'titulo' => 'required|string|max:255',
            'descripcion' => 'nullable|string|max:2000',
            'estado' => 'required|in:pendiente,en_progreso,completado',
            'prioridad' => 'nullable|in:baja,media,alta',
            'asignado_id' => 'nullable|exists:users,usuario_id',
            'fecha_vencimiento' => 'nullable|date',
        ]);

        $maxOrden = Tarea::where('estado', $request->estado)->max('orden') ?? 0;

        $tarea = Tarea::create([
            'titulo' => $request->titulo,
            'descripcion' => $request->descripcion,
            'estado' => $request->estado,
            'prioridad' => $request->prioridad,
            'orden' => $maxOrden + 1,
            'usuario_id' => auth()->id(),
            'asignado_id' => $request->asignado_id,
            'fecha_vencimiento' => $request->fecha_vencimiento,
        ]);

        $tarea->load(['creador', 'asignado']);

        return response()->json($tarea);
    }

    public function update(Request $request, Tarea $tarea)
    {
        $request->validate([
            'titulo' => 'sometimes|required|string|max:255',
            'descripcion' => 'nullable|string|max:2000',
            'estado' => 'sometimes|in:pendiente,en_progreso,completado',
            'prioridad' => 'nullable|in:baja,media,alta',
            'asignado_id' => 'nullable|exists:users,usuario_id',
            'fecha_vencimiento' => 'nullable|date',
        ]);

        $tarea->update($request->only([
            'titulo', 'descripcion', 'estado', 'prioridad', 'asignado_id', 'fecha_vencimiento'
        ]));

        $tarea->load(['creador', 'asignado']);

        return response()->json($tarea);
    }

    public function destroy(Tarea $tarea)
    {
        $tarea->delete();
        return response()->json(['ok' => true]);
    }

    public function move(Request $request, Tarea $tarea)
    {
        $request->validate([
            'estado' => 'required|in:pendiente,en_progreso,completado',
            'orden' => 'required|integer|min:0',
        ]);

        $tarea->estado = $request->estado;
        $tarea->orden = $request->orden;
        $tarea->save();

        $tareas = Tarea::where('estado', $request->estado)->orderBy('orden')->orderBy('id')->get();
        foreach ($tareas as $i => $t) {
            $t->update(['orden' => $i]);
        }

        $tarea->load(['creador', 'asignado']);

        return response()->json($tarea);
    }
}
