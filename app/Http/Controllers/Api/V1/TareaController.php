<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Tarea;
use Illuminate\Http\Request;

class TareaController extends ApiController
{
    public function index(Request $request)
    {
        $query = Tarea::with(['creador:usuario_id,name', 'asignado:usuario_id,name'])
            ->orderByRaw("CASE estado WHEN 'pendiente' THEN 1 WHEN 'en_progreso' THEN 2 WHEN 'completado' THEN 3 END")
            ->orderBy('orden')
            ->orderBy('id');

        if ($request->filled('estado')) {
            $query->where('estado', $request->estado);
        }
        if ($request->filled('asignado_id')) {
            $query->where('asignado_id', (int) $request->asignado_id);
        } elseif ($request->boolean('mias')) {
            $query->where('asignado_id', $request->user()->usuario_id);
        }

        return $this->ok($query->get());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'titulo' => ['required', 'string', 'max:255'],
            'descripcion' => ['nullable', 'string', 'max:2000'],
            'estado' => ['required', 'in:pendiente,en_progreso,completado'],
            'prioridad' => ['nullable', 'in:baja,media,alta'],
            'asignado_id' => ['nullable', 'exists:users,usuario_id'],
            'fecha_vencimiento' => ['nullable', 'date'],
        ]);

        $maxOrden = Tarea::where('estado', $validated['estado'])->max('orden') ?? 0;

        $tarea = Tarea::create([
            ...$validated,
            'orden' => $maxOrden + 1,
            'usuario_id' => $request->user()->usuario_id,
            'prioridad' => $validated['prioridad'] ?? 'media',
        ]);

        $tarea->load(['creador:usuario_id,name', 'asignado:usuario_id,name']);

        return $this->ok($tarea, 'Tarea creada', 201);
    }

    public function update(Request $request, Tarea $tarea)
    {
        $validated = $request->validate([
            'titulo' => ['sometimes', 'required', 'string', 'max:255'],
            'descripcion' => ['nullable', 'string', 'max:2000'],
            'estado' => ['sometimes', 'in:pendiente,en_progreso,completado'],
            'prioridad' => ['nullable', 'in:baja,media,alta'],
            'asignado_id' => ['nullable', 'exists:users,usuario_id'],
            'fecha_vencimiento' => ['nullable', 'date'],
        ]);

        $tarea->update($validated);
        $tarea->load(['creador:usuario_id,name', 'asignado:usuario_id,name']);

        return $this->ok($tarea, 'Tarea actualizada');
    }

    public function move(Request $request, Tarea $tarea)
    {
        $validated = $request->validate([
            'estado' => ['required', 'in:pendiente,en_progreso,completado'],
            'orden' => ['required', 'integer', 'min:0'],
        ]);

        $tarea->estado = $validated['estado'];
        $tarea->orden = $validated['orden'];
        $tarea->save();

        $tareas = Tarea::where('estado', $validated['estado'])->orderBy('orden')->orderBy('id')->get();
        foreach ($tareas as $i => $t) {
            $t->update(['orden' => $i]);
        }

        $tarea->load(['creador:usuario_id,name', 'asignado:usuario_id,name']);

        return $this->ok($tarea->fresh(['creador:usuario_id,name', 'asignado:usuario_id,name']));
    }
}
