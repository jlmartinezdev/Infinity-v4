@extends('layouts.app')

@section('title', 'OLTs')

@php
    $fc = 'rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 shadow-sm focus:border-purple-500 focus:outline-none focus:ring-2 focus:ring-purple-500/20 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100';
@endphp

@section('content')
<div class="max-w-7xl mx-auto">
    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">OLTs</h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Equipos Optical Line Terminal (FTTH)</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('sistema.olt-modelos.index') }}"
                class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 shadow-sm transition-colors hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700">
                Catálogo de modelos
            </a>
            <a href="{{ route('sistema.olts.create') }}"
                class="inline-flex items-center justify-center rounded-lg bg-purple-600 px-5 py-2.5 text-sm font-medium text-white shadow-sm transition-colors hover:bg-purple-700 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:ring-offset-2 dark:focus:ring-offset-gray-900">
                Nuevo OLT
            </a>
        </div>
    </div>

    <div class="mb-6 overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
        <form method="GET" action="{{ route('sistema.olts.index') }}" class="border-b border-gray-200 bg-gray-50 px-4 py-4 dark:border-gray-700 dark:bg-gray-900/40 sm:px-6">
            <div class="flex flex-wrap items-end gap-3">
                <div class="min-w-[12rem] flex-1 sm:max-w-xs">
                    <label for="filtro_nodo" class="mb-1 block text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Nodo</label>
                    <select name="nodo_id" id="filtro_nodo" class="w-full {{ $fc }}">
                        <option value="">Todos los nodos</option>
                        @foreach($nodos as $n)
                            <option value="{{ $n->nodo_id }}" {{ (string) request('nodo_id') === (string) $n->nodo_id ? 'selected' : '' }}>{{ $n->descripcion }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="min-w-[10rem] flex-1 sm:max-w-xs">
                    <label for="filtro_marca" class="mb-1 block text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Marca</label>
                    <input type="text" name="marca" id="filtro_marca" value="{{ request('marca') }}" placeholder="Filtrar por marca…" class="w-full {{ $fc }}">
                </div>
                <button type="submit" class="inline-flex items-center justify-center rounded-lg bg-purple-600 px-5 py-2.5 text-sm font-medium text-white shadow-sm transition-colors hover:bg-purple-700 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:ring-offset-2 dark:focus:ring-offset-gray-900">
                    Filtrar
                </button>
            </div>
        </form>
    </div>

    @if($olts->isEmpty())
        <div class="rounded-xl border border-dashed border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 p-12 text-center">
            <img src="{{ asset('images/olts/olt-generic.svg') }}" alt="" class="mx-auto h-24 w-48 object-contain opacity-60 mb-4">
            <p class="text-gray-500 dark:text-gray-400">No hay OLTs.</p>
            <a href="{{ route('sistema.olts.create') }}" class="mt-3 inline-block font-medium text-purple-600 hover:underline dark:text-purple-400">Crear uno</a>
        </div>
    @else
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-5">
            @foreach($olts as $o)
                @php
                    $imgUrl = \App\Support\OltModelosCatalogo::imagenUrl($o->modelo);
                    $modeloNombre = \App\Support\OltModelosCatalogo::nombre($o->modelo) ?: ($o->modelo ?: 'Sin modelo');
                @endphp
                <article class="group flex flex-col overflow-hidden rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-sm hover:shadow-md hover:border-purple-300 dark:hover:border-purple-700 transition-all">
                    <a href="{{ route('sistema.olts.show', $o) }}" class="relative block bg-gradient-to-b from-gray-50 to-gray-100 dark:from-gray-900/60 dark:to-gray-800 px-4 pt-5 pb-3">
                        <span class="absolute top-3 left-3 inline-flex rounded-full bg-white/90 dark:bg-gray-900/80 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-purple-700 dark:text-purple-300 border border-purple-200 dark:border-purple-800">
                            {{ $o->tipo_pon }}
                        </span>
                        @if($o->estado && $o->estado !== 'activo')
                            <span class="absolute top-3 right-3 inline-flex rounded-full bg-amber-600/90 px-2 py-0.5 text-[10px] font-semibold text-white">{{ ucfirst($o->estado) }}</span>
                        @elseif($o->onus_sync_error)
                            <span class="absolute top-3 right-3 inline-flex rounded-full bg-amber-500 px-2 py-0.5 text-[10px] font-semibold text-white">Error sync</span>
                        @endif
                        <img src="{{ $imgUrl }}" alt="{{ $modeloNombre }}"
                            class="mx-auto h-28 w-full max-w-[220px] object-contain drop-shadow-sm group-hover:scale-[1.02] transition-transform duration-200"
                            loading="lazy">
                    </a>

                    <div class="flex flex-1 flex-col p-4">
                        <a href="{{ route('sistema.olts.show', $o) }}" class="block">
                            <h2 class="text-base font-semibold text-gray-900 dark:text-gray-100 leading-tight">{{ $o->codigo ?? 'OLT #'.$o->olt_id }}</h2>
                            <p class="mt-0.5 text-sm font-medium text-gray-700 dark:text-gray-300">{{ $o->ip ?? 'Sin IP' }}</p>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                {{ $o->marca ?? '—' }} · {{ $modeloNombre }}
                            </p>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                {{ $o->nodo?->descripcion ?? 'Sin nodo' }}
                            </p>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                Puertos {{ $o->oltPuertos->count() }} / {{ $o->cantidad_puerto }}
                            </p>
                            @if($o->onus_sync_error)
                                <p class="mt-2 text-xs text-amber-700 dark:text-amber-400 leading-snug line-clamp-3" title="{{ $o->onus_sync_error }}">
                                    {{ $o->onus_sync_error }}
                                </p>
                            @endif
                        </a>

                        <div class="mt-auto pt-4 flex flex-wrap gap-2">
                            <a href="{{ route('sistema.olts.show', $o) }}"
                                class="rounded-md px-2 py-1 text-xs font-medium text-purple-700 bg-purple-50 hover:bg-purple-100 dark:bg-purple-900/30 dark:text-purple-300">
                                Ver
                            </a>
                            <a href="{{ route('sistema.olts.edit', $o) }}"
                                class="rounded-md px-2 py-1 text-xs font-medium text-slate-700 bg-slate-50 hover:bg-slate-100 dark:bg-slate-900/40 dark:text-slate-300">
                                Editar
                            </a>
                            <form action="{{ route('sistema.olts.destroy', $o) }}" method="POST" class="inline"
                                onsubmit="return confirm('¿Eliminar este OLT?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="rounded-md px-2 py-1 text-xs font-medium text-red-700 bg-red-50 hover:bg-red-100 dark:bg-red-900/30 dark:text-red-300">
                                    Eliminar
                                </button>
                            </form>
                        </div>
                    </div>
                </article>
            @endforeach
        </div>

        @if ($olts->hasPages())
            <div class="mt-6">{{ $olts->links() }}</div>
        @endif
    @endif
</div>
@endsection
