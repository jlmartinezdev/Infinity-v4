@extends('layouts.app')

@section('title', 'Catálogo MikroTik')

@section('content')
<div class="max-w-7xl mx-auto">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">Catálogo MikroTik</h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Modelos RB, CCR, hAP y CHR con imagen para seleccionar en routers</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('sistema.routers.index') }}"
                class="inline-flex items-center px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-gray-200 rounded-lg font-medium hover:bg-gray-300 dark:hover:bg-gray-600 focus:outline-none">
                Ver routers
            </a>
            <a href="{{ route('sistema.router-modelos.create') }}"
                class="inline-flex items-center px-4 py-2 bg-purple-600 text-white rounded-lg font-medium hover:bg-purple-700 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:ring-offset-2">
                Nuevo modelo
            </a>
        </div>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 overflow-hidden mb-6">
        <form method="GET" action="{{ route('sistema.router-modelos.index') }}" class="p-4 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-700/50">
            <div class="flex flex-col lg:flex-row gap-3">
                <div class="flex-1">
                    <input type="text" name="buscar" value="{{ request('buscar') }}"
                        placeholder="Buscar por nombre, slug, serie..."
                        class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none transition-colors bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                </div>
                <div class="sm:w-44">
                    <select name="serie" class="w-full py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none transition-colors bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                        <option value="todas">Todas las series</option>
                        @foreach($series as $serie)
                            <option value="{{ $serie }}" {{ request('serie') == $serie ? 'selected' : '' }}>{{ $serie }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="sm:w-40">
                    <select name="activo" class="w-full py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none transition-colors bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                        <option value="todos">Todos</option>
                        <option value="1" {{ request('activo') === '1' ? 'selected' : '' }}>Activos</option>
                        <option value="0" {{ request('activo') === '0' ? 'selected' : '' }}>Inactivos</option>
                    </select>
                </div>
                <button type="submit" class="inline-flex items-center justify-center px-6 py-2.5 bg-purple-600 text-white rounded-lg font-medium hover:bg-purple-700 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:ring-offset-2">
                    Filtrar
                </button>
            </div>
        </form>
    </div>

    @if($modelos->isEmpty())
        <div class="rounded-xl border border-dashed border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 p-12 text-center">
            <img src="{{ asset('images/routers/mikrotik-generic.svg') }}" alt="" class="mx-auto h-24 w-48 object-contain opacity-60 mb-4">
            <p class="text-gray-500 dark:text-gray-400">No hay modelos en el catálogo.</p>
            <a href="{{ route('sistema.router-modelos.create') }}" class="mt-3 inline-block text-purple-600 dark:text-purple-400 hover:underline font-medium">Agregar el primero</a>
        </div>
    @else
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-5">
            @foreach($modelos as $m)
                <article class="group flex flex-col overflow-hidden rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-sm hover:shadow-md hover:border-purple-300 dark:hover:border-purple-700 transition-all {{ !$m->activo ? 'opacity-75' : '' }}">
                    <div class="relative bg-gradient-to-b from-gray-50 to-gray-100 dark:from-gray-900/60 dark:to-gray-800 px-4 pt-5 pb-3">
                        <span class="absolute top-3 left-3 inline-flex rounded-full bg-white/90 dark:bg-gray-900/80 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-purple-700 dark:text-purple-300 border border-purple-200 dark:border-purple-800">
                            {{ $m->serie }}
                        </span>
                        @if(!$m->activo)
                            <span class="absolute top-3 right-3 inline-flex rounded-full bg-gray-600/90 px-2 py-0.5 text-[10px] font-semibold text-white">Inactivo</span>
                        @endif
                        <img src="{{ $m->imagenUrl() }}" alt="{{ $m->nombre }}"
                            class="mx-auto h-28 w-full max-w-[220px] object-contain drop-shadow-sm group-hover:scale-[1.02] transition-transform duration-200">
                    </div>

                    <div class="flex flex-1 flex-col p-4">
                        <h2 class="text-base font-semibold text-gray-900 dark:text-gray-100 leading-tight">{{ $m->nombre }}</h2>
                        <p class="text-xs font-mono text-gray-400 mt-0.5">{{ $m->slug }}</p>
                        @if($m->descripcion)
                            <p class="mt-2 text-xs text-gray-500 dark:text-gray-400 line-clamp-2">{{ $m->descripcion }}</p>
                        @endif
                        <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                            {{ $m->routers_count }} {{ $m->routers_count === 1 ? 'router' : 'routers' }}
                        </p>

                        <div class="mt-auto pt-4 flex flex-wrap gap-2">
                            <a href="{{ route('sistema.router-modelos.edit', $m) }}"
                                class="rounded-md px-2 py-1 text-xs font-medium text-purple-700 bg-purple-50 hover:bg-purple-100 dark:bg-purple-900/30 dark:text-purple-300">
                                Editar
                            </a>
                            <form action="{{ route('sistema.router-modelos.destroy', $m) }}" method="POST" class="inline"
                                onsubmit="return confirm('¿Eliminar «{{ $m->nombre }}» del catálogo?');">
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

        <div class="mt-6">
            {{ $modelos->links() }}
        </div>
    @endif
</div>
@endsection
