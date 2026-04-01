@extends('layouts.app')

@section('title', 'Caja NAP: ' . $cajaNap->codigo)

@section('content')
<div class="max-w-4xl mx-auto">
    <div class="mb-6">
        <a href="{{ route('sistema.cajas-nap.index') }}" class="text-purple-600 hover:text-purple-700 text-sm font-medium">&larr; Volver a cajas NAP</a>
        <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100 mt-1">Caja NAP: {{ $cajaNap->codigo }}</h1>
        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">{{ $cajaNap->nodo?->descripcion }} — {{ ucfirst($cajaNap->tipo) }}</p>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 p-6">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">Datos generales</h2>
            <dl class="space-y-2 text-sm">
                <div><dt class="text-gray-500 dark:text-gray-400">Código</dt><dd class="font-medium">{{ $cajaNap->codigo }}</dd></div>
                <div><dt class="text-gray-500 dark:text-gray-400">Nodo</dt><dd>{{ $cajaNap->nodo?->descripcion ?? '—' }}</dd></div>
                <div><dt class="text-gray-500 dark:text-gray-400">Tipo</dt><dd>{{ ucfirst($cajaNap->tipo) }}</dd></div>
                <div><dt class="text-gray-500 dark:text-gray-400">Descripción</dt><dd>{{ $cajaNap->descripcion ?? '—' }}</dd></div>
                <div><dt class="text-gray-500 dark:text-gray-400">Dirección</dt><dd>{{ $cajaNap->direccion ?? '—' }}</dd></div>
                <div><dt class="text-gray-500 dark:text-gray-400">Coordenadas</dt><dd>{{ $cajaNap->lat && $cajaNap->lon ? number_format($cajaNap->lat, 6) . ', ' . number_format($cajaNap->lon, 6) : '—' }}</dd></div>
            </dl>
            <div class="mt-4">
                <a href="{{ route('sistema.cajas-nap.edit', $cajaNap) }}" class="text-purple-600 dark:text-purple-400 hover:underline font-medium">Editar caja</a>
            </div>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 p-6">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Splitters primarios</h2>
                <a href="{{ route('sistema.splitters-primarios.create', $cajaNap) }}" class="text-sm px-3 py-1.5 bg-purple-600 text-white rounded-lg hover:bg-purple-700">+ Primario</a>
            </div>
            @if($cajaNap->splitterPrimarios->isEmpty())
                <p class="text-sm text-gray-500 dark:text-gray-400">No hay splitters primarios. <a href="{{ route('sistema.splitters-primarios.create', $cajaNap) }}" class="text-purple-600 hover:underline">Crear uno</a></p>
            @else
                <ul class="space-y-3">
                    @foreach($cajaNap->splitterPrimarios as $sp)
                        <li class="py-3 border-b border-gray-100 dark:border-gray-700 last:border-0">
                            <div class="flex justify-between items-start gap-2">
                                <div>
                                    <span class="font-medium">{{ $sp->codigo }}</span>
                                    <span class="text-gray-500 dark:text-gray-400">({{ $sp->ratio }})</span>
                                    @if($sp->potencia_entrada !== null || $sp->potencia_salida !== null)
                                        <span class="ml-2 text-xs text-cyan-600 dark:text-cyan-400">
                                            @if($sp->potencia_entrada !== null)Ent: {{ number_format($sp->potencia_entrada, 1) }} dBm@endif
                                            @if($sp->potencia_entrada !== null && $sp->potencia_salida !== null) · @endif
                                            @if($sp->potencia_salida !== null)Sal: {{ number_format($sp->potencia_salida, 1) }} dBm@endif
                                        </span>
                                    @endif
                                </div>
                                <div class="flex gap-2 shrink-0">
                                    <a href="{{ route('sistema.splitters-primarios.edit', $sp) }}" class="text-purple-600 dark:text-purple-400 hover:underline text-sm">Editar</a>
                                    <form action="{{ route('sistema.splitters-primarios.destroy', $sp) }}" method="POST" class="inline" onsubmit="return confirm('¿Eliminar este splitter primario?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-red-600 dark:text-red-400 hover:underline text-sm">Eliminar</button>
                                    </form>
                                </div>
                            </div>
                            @if($sp->splitterSecundarios->isNotEmpty())
                                <ul class="mt-2 ml-4 space-y-1 text-sm text-gray-600 dark:text-gray-400">
                                    @foreach($sp->splitterSecundarios as $ss)
                                        <li class="flex justify-between items-center">
                                            <span>{{ $ss->codigo }} ({{ $ss->ratio }})</span>
                                            @if($ss->potencia_entrada !== null || $ss->potencia_salida !== null)
                                                <span class="text-xs text-cyan-600 dark:text-cyan-400">
                                                    @if($ss->potencia_entrada !== null)Ent: {{ number_format($ss->potencia_entrada, 1) }} dBm@endif
                                                    @if($ss->potencia_entrada !== null && $ss->potencia_salida !== null) · @endif
                                                    @if($ss->potencia_salida !== null)Sal: {{ number_format($ss->potencia_salida, 1) }} dBm@endif
                                                </span>
                                            @endif
                                            <span>
                                                <a href="{{ route('sistema.splitters-secundarios.edit', $ss) }}" class="text-purple-600 hover:underline">Editar</a>
                                                <form action="{{ route('sistema.splitters-secundarios.destroy', $ss) }}" method="POST" class="inline ml-1" onsubmit="return confirm('¿Eliminar este splitter secundario?');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="text-red-600 hover:underline">Eliminar</button>
                                                </form>
                                            </span>
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                            <a href="{{ route('sistema.splitters-secundarios.create', ['cajaNap' => $cajaNap, 'splitter_primario_id' => $sp->splitter_primario_id]) }}" class="mt-1 ml-4 text-xs text-purple-600 hover:underline">+ Secundario</a>
                        </li>
                    @endforeach
                </ul>
                <a href="{{ route('sistema.splitters-secundarios.create', $cajaNap) }}" class="mt-3 inline-block text-sm text-purple-600 dark:text-purple-400 hover:underline">+ Splitter secundario</a>
            @endif
        </div>
    </div>

    <div class="mt-6 bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 p-6">
        <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">Salidas PON</h2>
        @if($cajaNap->salidaPons->isEmpty())
            <p class="text-sm text-gray-500 dark:text-gray-400">No hay salidas PON en esta caja.</p>
        @else
            <ul class="space-y-2">
                @foreach($cajaNap->salidaPons as $sp)
                    <li>{{ $sp->codigo }} — Puerto {{ $sp->puerto }}</li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
@endsection
