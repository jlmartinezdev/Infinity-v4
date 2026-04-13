@extends('layouts.app')

@section('title', 'Dashboard de Tareas')

@section('content')
<div id="tareas-kanban-app"></div>

@php
    $config = [
        'tareas' => $tareas->map(fn($t) => [
            'id' => $t->id,
            'titulo' => $t->titulo,
            'descripcion' => $t->descripcion,
            'estado' => $t->estado,
            'prioridad' => $t->prioridad,
            'orden' => $t->orden,
            'fecha_vencimiento' => $t->fecha_vencimiento?->format('Y-m-d'),
            'creador' => $t->creador ? ['usuario_id' => $t->creador->usuario_id, 'name' => $t->creador->name] : null,
            'asignado' => $t->asignado ? ['usuario_id' => $t->asignado->usuario_id, 'name' => $t->asignado->name] : null,
            'created_at' => $t->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $t->updated_at?->format('Y-m-d H:i:s'),
        ])->values()->all(),
        'usuarios' => $usuarios->map(fn($u) => ['usuario_id' => $u->usuario_id, 'name' => $u->name])->values()->all(),
        'canCreate' => auth()->user()?->tienePermiso('tareas.crear') ?? false,
        'csrfToken' => csrf_token(),
        'urlStore' => route('tareas.store'),
        'urlUpdate' => route('tareas.update', ['tarea' => '__id__']),
        'urlMove' => url('tareas') . '/__id__/move',
        'urlDestroy' => url('tareas') . '/__id__',
    ];
@endphp
<script>
window.__TAREAS_KANBAN_CONFIG__ = @json($config);
</script>

@push('scripts')
<script src="{{ asset(mix('js/tareas-dashboard.js')) }}" defer></script>
@endpush
@endsection
