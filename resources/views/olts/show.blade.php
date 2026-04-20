@extends('layouts.app')

@section('title', 'OLT: ' . ($olt->codigo ?? $olt->ip ?? $olt->modelo ?? $olt->olt_id))

@section('content')
<div class="max-w-4xl mx-auto">
    <div class="mb-6">
        <a href="{{ route('sistema.olts.index') }}" class="text-sm font-medium text-purple-600 hover:text-purple-800 hover:underline dark:text-purple-400 dark:hover:text-purple-300">&larr; Volver a OLTs</a>
        <h1 class="mt-2 text-2xl font-bold text-gray-900 dark:text-gray-100">
            OLT {{ $olt->codigo ?? $olt->ip ?? $olt->modelo ?? '#' . $olt->olt_id }}
        </h1>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
            {{ $olt->marca ?? '—' }}{{ $olt->modelo ? ' — ' . $olt->modelo : '' }} · {{ $olt->nodo?->descripcion ?? '—' }}
        </p>
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <div class="border-b border-gray-200 bg-gray-50 px-6 py-4 dark:border-gray-700 dark:bg-gray-900/40">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-600 dark:text-gray-300">Datos del OLT</h2>
            </div>
            <div class="p-6">
                <dl class="space-y-3 text-sm">
                    <div class="flex flex-wrap gap-x-2 gap-y-0.5">
                        <dt class="shrink-0 text-gray-500 dark:text-gray-400">Código</dt>
                        <dd class="min-w-0 font-mono font-medium text-gray-900 dark:text-gray-100">{{ $olt->codigo ?? '—' }}</dd>
                    </div>
                    <div class="flex flex-wrap gap-x-2 gap-y-0.5">
                        <dt class="shrink-0 text-gray-500 dark:text-gray-400">Nodo</dt>
                        <dd class="text-gray-900 dark:text-gray-100">{{ $olt->nodo?->descripcion ?? '—' }}</dd>
                    </div>
                    <div class="flex flex-wrap gap-x-2 gap-y-0.5">
                        <dt class="shrink-0 text-gray-500 dark:text-gray-400">Marca</dt>
                        <dd class="text-gray-900 dark:text-gray-100">{{ $olt->marca ?? '—' }}</dd>
                    </div>
                    <div class="flex flex-wrap gap-x-2 gap-y-0.5">
                        <dt class="shrink-0 text-gray-500 dark:text-gray-400">Modelo</dt>
                        <dd class="text-gray-900 dark:text-gray-100">{{ $olt->modelo ?? '—' }}</dd>
                    </div>
                    <div class="flex flex-wrap gap-x-2 gap-y-0.5">
                        <dt class="shrink-0 text-gray-500 dark:text-gray-400">IP</dt>
                        <dd class="font-mono text-gray-900 dark:text-gray-100">{{ $olt->ip ?? '—' }}</dd>
                    </div>
                    <div class="flex flex-wrap gap-x-2 gap-y-0.5">
                        <dt class="shrink-0 text-gray-500 dark:text-gray-400">Tipo PON</dt>
                        <dd class="text-gray-900 dark:text-gray-100">{{ $olt->tipo_pon }}</dd>
                    </div>
                    <div class="flex flex-wrap gap-x-2 gap-y-0.5">
                        <dt class="shrink-0 text-gray-500 dark:text-gray-400">Puertos físicos (tabla)</dt>
                        <dd class="text-gray-900 dark:text-gray-100">{{ $olt->oltPuertos->count() }} de {{ $olt->cantidad_puerto }}</dd>
                    </div>
                    <div class="flex flex-wrap gap-x-2 gap-y-0.5">
                        <dt class="shrink-0 text-gray-500 dark:text-gray-400">Salidas PON</dt>
                        <dd class="text-gray-900 dark:text-gray-100">{{ $olt->salidaPons->count() }}</dd>
                    </div>
                    <div class="flex flex-wrap gap-x-2 gap-y-0.5">
                        <dt class="shrink-0 text-gray-500 dark:text-gray-400">Estado</dt>
                        <dd class="text-gray-900 dark:text-gray-100">{{ ucfirst($olt->estado) }}</dd>
                    </div>
                </dl>
                <div class="mt-6 flex flex-wrap gap-2">
                    <a href="{{ route('sistema.olts.edit', $olt) }}" class="inline-flex items-center rounded-lg border border-purple-200 bg-purple-50 px-4 py-2 text-sm font-medium text-purple-800 transition-colors hover:bg-purple-100 dark:border-purple-800 dark:bg-purple-900/30 dark:text-purple-200 dark:hover:bg-purple-900/50">
                        Editar
                    </a>
                    <form action="{{ route('sistema.olts.destroy', $olt) }}" method="POST" class="inline" onsubmit="return confirm('¿Eliminar este OLT?');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="inline-flex items-center rounded-lg border border-red-200 bg-red-50 px-4 py-2 text-sm font-medium text-red-800 transition-colors hover:bg-red-100 dark:border-red-900 dark:bg-red-950/40 dark:text-red-300 dark:hover:bg-red-950/60">
                            Eliminar
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <div class="flex flex-col gap-3 border-b border-gray-200 bg-gray-50 px-6 py-4 sm:flex-row sm:items-center sm:justify-between dark:border-gray-700 dark:bg-gray-900/40">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-600 dark:text-gray-300">Puertos PON (detalle)</h2>
                <a href="{{ route('sistema.olt-puertos.create', $olt) }}" class="inline-flex shrink-0 items-center justify-center rounded-lg bg-purple-600 px-3 py-2 text-sm font-medium text-white shadow-sm transition-colors hover:bg-purple-700 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:ring-offset-2 dark:focus:ring-offset-gray-900">
                    + Puerto
                </a>
            </div>
            <div class="p-6">
                @if($olt->oltPuertos->isEmpty())
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        No hay puertos definidos.
                        <a href="{{ route('sistema.olt-puertos.create', $olt) }}" class="font-medium text-purple-600 hover:underline dark:text-purple-400">Agregar puertos</a>
                    </p>
                @else
                    <ul class="divide-y divide-gray-100 dark:divide-gray-700">
                        @foreach($olt->oltPuertos->sortBy('numero') as $p)
                            <li class="flex flex-wrap items-center justify-between gap-2 py-3 first:pt-0 last:pb-0">
                                <span class="text-sm text-gray-900 dark:text-gray-100">Puerto {{ $p->numero }} <span class="text-gray-500 dark:text-gray-400">({{ $p->tipo_pon }})</span></span>
                                <span class="flex shrink-0 items-center gap-2 text-sm">
                                    <a href="{{ route('sistema.olt-puertos.edit', $p) }}" class="font-medium text-purple-600 hover:underline dark:text-purple-400">Editar</a>
                                    <form action="{{ route('sistema.olt-puertos.destroy', $p) }}" method="POST" class="inline" onsubmit="return confirm('¿Eliminar puerto?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="font-medium text-red-600 hover:underline dark:text-red-400">Eliminar</button>
                                    </form>
                                </span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    </div>

    @if($olt->salidaPons->isNotEmpty())
        <div class="mt-6 overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <div class="border-b border-gray-200 bg-gray-50 px-6 py-4 dark:border-gray-700 dark:bg-gray-900/40">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-600 dark:text-gray-300">Salidas PON registradas</h2>
            </div>
            <div class="p-6">
                <ul class="space-y-2 text-sm">
                    @foreach($olt->salidaPons as $sp)
                        <li class="text-gray-700 dark:text-gray-300">
                            <a href="{{ route('sistema.salida-pons.edit', $sp) }}" class="font-medium text-purple-600 hover:underline dark:text-purple-400">{{ $sp->codigo }}</a>
                            <span class="text-gray-500 dark:text-gray-400">— puerto OLT {{ $sp->puerto_olt }}</span>
                            @if($sp->tipo_modulo)<span class="text-gray-500 dark:text-gray-400"> · {{ $sp->tipo_modulo }}</span>@endif
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
    @endif
</div>
@endsection
