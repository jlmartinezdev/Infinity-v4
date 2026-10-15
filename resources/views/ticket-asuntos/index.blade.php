@extends('layouts.app')

@section('title', 'Asuntos de tickets')

@section('content')
<div class="max-w-7xl mx-auto space-y-6">
    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="flex items-center gap-3">
                <span class="p-2.5 rounded-2xl bg-purple-100 dark:bg-purple-900/40 text-purple-600 dark:text-purple-300">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/></svg>
                </span>
                <div>
                    <h1 class="text-2xl font-black text-gray-900 dark:text-gray-100 tracking-tight">Asuntos de Tickets</h1>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">
                        Clasificación visual, íconos y códigos de color para el triaje ágil de soporte técnico y NOC.
                    </p>
                </div>
            </div>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('ticket-asuntos.create') }}"
                class="inline-flex items-center gap-2 px-5 py-2.5 bg-purple-600 hover:bg-purple-700 text-white rounded-xl font-semibold shadow-sm focus:outline-none focus:ring-2 focus:ring-purple-500 focus:ring-offset-2 transition-all">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                Nuevo asunto
            </a>
            <a href="{{ route('tickets.index') }}"
                class="inline-flex items-center gap-1.5 px-4 py-2.5 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-gray-700 dark:text-gray-200 rounded-xl font-medium hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                Ir a Tickets
            </a>
        </div>
    </div>

    {{-- Tarjeta Principal con Buscador y Tabla --}}
    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200/80 dark:border-gray-700 overflow-hidden">
        {{-- Buscador --}}
        <form method="GET" action="{{ route('ticket-asuntos.index') }}" class="p-4 border-b border-gray-100 dark:border-gray-700/80 bg-gray-50/60 dark:bg-gray-800/50">
            <div class="flex flex-col sm:flex-row gap-3">
                <div class="flex-1 relative">
                    <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-gray-400">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                        </svg>
                    </div>
                    <input type="text" name="buscar" value="{{ request('buscar') }}"
                        placeholder="Buscar asunto por nombre..."
                        class="w-full pl-10 pr-10 py-2 rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 text-sm shadow-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none transition-colors">
                    @if(request('buscar'))
                        <a href="{{ route('ticket-asuntos.index') }}"
                            class="absolute inset-y-0 right-0 pr-3.5 flex items-center text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 transition-colors"
                            title="Limpiar búsqueda">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </a>
                    @endif
                </div>
                <button type="submit"
                    class="inline-flex items-center justify-center px-5 py-2 bg-purple-600 hover:bg-purple-700 text-white rounded-xl font-medium text-sm transition-colors shadow-sm">
                    Buscar
                </button>
            </div>
        </form>

        {{-- Tabla --}}
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200/80 dark:divide-gray-700">
                <thead class="bg-gray-50/75 dark:bg-gray-700/40 text-[11px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                    <tr>
                        <th scope="col" class="px-5 py-3.5 text-left w-16">ID</th>
                        <th scope="col" class="px-5 py-3.5 text-left w-20">Ícono SVG</th>
                        <th scope="col" class="px-5 py-3.5 text-left">Asunto & Clasificación</th>
                        <th scope="col" class="px-5 py-3.5 text-center">Color Hex</th>
                        <th scope="col" class="px-5 py-3.5 text-center">Tickets Asignados</th>
                        <th scope="col" class="px-5 py-3.5 text-right w-36">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700/60 bg-white dark:bg-gray-800">
                    @forelse ($asuntos as $asunto)
                        <tr class="hover:bg-gray-50/80 dark:hover:bg-gray-700/40 transition-colors">
                            {{-- ID --}}
                            <td class="px-5 py-3 text-xs font-mono text-gray-400 dark:text-gray-500">
                                #{{ $asunto->id }}
                            </td>

                            {{-- Ícono SVG Grande (Tile) --}}
                            <td class="px-5 py-3">
                                <x-asunto-icono :asunto="$asunto" size="md" class="shadow-xs hover:scale-105 transition-transform" />
                            </td>

                            {{-- Nombre & Emoji --}}
                            <td class="px-5 py-3">
                                <div class="flex items-center gap-2">
                                    <span class="text-sm font-bold text-gray-900 dark:text-gray-100">
                                        {{ $asunto->nombre }}
                                    </span>
                                    <span class="text-xs text-gray-400" title="Emoji fallback">
                                        {{ $asunto->getIconoOrDefault() }}
                                    </span>
                                </div>
                                <span class="text-[11px] font-mono text-gray-400 dark:text-gray-500">
                                    svg: {{ $asunto->getIconoSvgKey() }}
                                </span>
                            </td>

                            {{-- Color Chip --}}
                            <td class="px-5 py-3 text-center">
                                <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-md font-mono text-xs text-gray-600 dark:text-gray-300 bg-gray-100 dark:bg-gray-700/60 border border-gray-200 dark:border-gray-600">
                                    <span class="w-3 h-3 rounded-full shrink-0 shadow-xs" style="background-color: {{ $asunto->getColorOrDefault() }};"></span>
                                    {{ strtoupper($asunto->getColorOrDefault()) }}
                                </span>
                            </td>

                            {{-- Tickets Count --}}
                            <td class="px-5 py-3 text-center">
                                @php
                                    $ticketsCount = $asunto->tickets_count ?? 0;
                                @endphp
                                @if($ticketsCount > 0)
                                    <a href="{{ route('tickets.index', ['ticket_asunto_id' => $asunto->id]) }}"
                                       class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-bold bg-purple-50 text-purple-700 dark:bg-purple-900/30 dark:text-purple-300 hover:bg-purple-100 dark:hover:bg-purple-900/50 transition-colors"
                                       title="Ver tickets con este asunto">
                                        {{ number_format($ticketsCount) }} tickets
                                    </a>
                                @else
                                    <span class="text-xs text-gray-400 dark:text-gray-500 font-medium">0</span>
                                @endif
                            </td>

                            {{-- Acciones --}}
                            <td class="px-5 py-3 text-right text-sm">
                                <div class="inline-flex items-center gap-2">
                                    <a href="{{ route('ticket-asuntos.edit', $asunto) }}"
                                       class="px-2.5 py-1 text-xs font-semibold rounded-lg bg-gray-100 hover:bg-purple-50 hover:text-purple-700 text-gray-700 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-purple-900/40 dark:hover:text-purple-300 transition-colors">
                                        Editar
                                    </a>
                                    <form action="{{ route('ticket-asuntos.destroy', $asunto) }}" method="POST" class="inline"
                                          onsubmit="return confirm('¿Eliminar el asunto «{{ $asunto->nombre }}»? Esta acción no se puede deshacer.');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit"
                                            class="px-2 py-1 text-xs font-semibold rounded-lg text-rose-600 hover:bg-rose-50 dark:text-rose-400 dark:hover:bg-rose-900/30 transition-colors"
                                            title="Eliminar asunto">
                                            Eliminar
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-5 py-12 text-center text-gray-500 dark:text-gray-400">
                                <div class="max-w-xs mx-auto space-y-2">
                                    <span class="text-3xl">🎫</span>
                                    <p class="font-medium text-gray-700 dark:text-gray-300">No se encontraron asuntos</p>
                                    <p class="text-xs text-gray-400">Probá con otro término de búsqueda o creá uno nuevo.</p>
                                    <a href="{{ route('ticket-asuntos.create') }}" class="inline-block mt-2 text-xs font-bold text-purple-600 dark:text-purple-400 hover:underline">
                                        + Crear nuevo asunto
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Paginación --}}
        @if ($asuntos->hasPages())
            <div class="px-5 py-3.5 border-t border-gray-100 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-800">
                {{ $asuntos->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
