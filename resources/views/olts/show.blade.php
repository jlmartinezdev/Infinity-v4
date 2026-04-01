@extends('layouts.app')

@section('title', 'OLT: ' . ($olt->ip ?? $olt->modelo ?? $olt->olt_id))

@section('content')
<div class="max-w-4xl mx-auto">
    <div class="mb-6">
        <a href="{{ route('sistema.olts.index') }}" class="text-purple-600 dark:text-purple-400 hover:underline text-sm">&larr; Volver a OLTs</a>
        <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100 mt-1">
            OLT {{ $olt->ip ?? $olt->modelo ?? '#' . $olt->olt_id }}
        </h1>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
            {{ $olt->oltMarca?->nombre ?? '—' }} {{ $olt->modelo ? "— {$olt->modelo}" : '' }} · {{ $olt->nodo?->descripcion ?? '—' }}
        </p>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 p-6">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">Datos del OLT</h2>
            <dl class="space-y-2 text-sm">
                <div><dt class="text-gray-500 dark:text-gray-400">Nodo</dt><dd>{{ $olt->nodo?->descripcion ?? '—' }}</dd></div>
                <div><dt class="text-gray-500 dark:text-gray-400">Marca</dt><dd>{{ $olt->oltMarca?->nombre ?? '—' }}</dd></div>
                <div><dt class="text-gray-500 dark:text-gray-400">Modelo</dt><dd>{{ $olt->modelo ?? '—' }}</dd></div>
                <div><dt class="text-gray-500 dark:text-gray-400">IP</dt><dd>{{ $olt->ip ?? '—' }}</dd></div>
                <div><dt class="text-gray-500 dark:text-gray-400">Tipo PON</dt><dd>{{ $olt->tipo_pon }}</dd></div>
                <div><dt class="text-gray-500 dark:text-gray-400">Puertos</dt><dd>{{ $olt->oltPuertos->count() }} de {{ $olt->cantidad_puertos }}</dd></div>
                <div><dt class="text-gray-500 dark:text-gray-400">Estado</dt><dd>{{ ucfirst($olt->estado) }}</dd></div>
            </dl>
            <div class="mt-4 flex gap-2">
                <a href="{{ route('sistema.olts.edit', $olt) }}" class="text-purple-600 dark:text-purple-400 hover:underline font-medium">Editar</a>
                <form action="{{ route('sistema.olts.destroy', $olt) }}" method="POST" class="inline" onsubmit="return confirm('¿Eliminar este OLT?');">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="text-red-600 dark:text-red-400 hover:underline">Eliminar</button>
                </form>
            </div>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 p-6">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Puertos PON</h2>
                <a href="{{ route('sistema.olt-puertos.create', $olt) }}" class="text-sm px-3 py-1.5 bg-purple-600 text-white rounded-lg hover:bg-purple-700">+ Puerto</a>
            </div>
            @if($olt->oltPuertos->isEmpty())
                <p class="text-sm text-gray-500 dark:text-gray-400">No hay puertos definidos. <a href="{{ route('sistema.olt-puertos.create', $olt) }}" class="text-purple-600 hover:underline">Agregar puertos</a></p>
            @else
                <ul class="space-y-2">
                    @foreach($olt->oltPuertos->sortBy('numero') as $p)
                        <li class="flex justify-between items-center py-2 border-b border-gray-100 dark:border-gray-700 last:border-0">
                            <span>Puerto {{ $p->numero }} ({{ $p->tipo_pon }})</span>
                            <span class="text-xs text-gray-500">{{ $p->salidaPons->count() }} salidas</span>
                            <span>
                                <a href="{{ route('sistema.olt-puertos.edit', $p) }}" class="text-purple-600 hover:underline text-sm">Editar</a>
                                <form action="{{ route('sistema.olt-puertos.destroy', $p) }}" method="POST" class="inline ml-1" onsubmit="return confirm('¿Eliminar puerto?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-red-600 hover:underline text-sm">Eliminar</button>
                                </form>
                            </span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>
</div>
@endsection
