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
        <a href="{{ route('sistema.olts.create') }}"
            class="inline-flex items-center justify-center rounded-lg bg-purple-600 px-5 py-2.5 text-sm font-medium text-white shadow-sm transition-colors hover:bg-purple-700 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:ring-offset-2 dark:focus:ring-offset-gray-900">
            Nuevo OLT
        </a>
    </div>

    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
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

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-700/50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Código</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">IP / Modelo</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Marca</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Nodo</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Tipo PON</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Puertos</th>
                        <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse ($olts as $o)
                        <tr class="transition-colors hover:bg-gray-50 dark:hover:bg-gray-700/50">
                            <td class="px-4 py-3 font-mono text-sm text-gray-900 dark:text-gray-100">{{ $o->codigo ?? '—' }}</td>
                            <td class="px-4 py-3">
                                <span class="font-medium text-gray-900 dark:text-gray-100">{{ $o->ip ?? '—' }}</span>
                                @if($o->modelo)
                                    <span class="mt-0.5 block text-xs text-gray-500 dark:text-gray-400">{{ $o->modelo }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-900 dark:text-gray-100">{{ $o->marca ?? '—' }}</td>
                            <td class="px-4 py-3 text-sm text-gray-900 dark:text-gray-100">{{ $o->nodo?->descripcion ?? '—' }}</td>
                            <td class="px-4 py-3 text-sm text-gray-900 dark:text-gray-100">{{ $o->tipo_pon }}</td>
                            <td class="px-4 py-3 text-sm text-gray-900 dark:text-gray-100">{{ $o->oltPuertos->count() }} / {{ $o->cantidad_puerto }}</td>
                            <td class="px-4 py-3 text-right text-sm">
                                <a href="{{ route('sistema.olts.show', $o) }}" class="font-medium text-purple-600 hover:underline dark:text-purple-400">Ver</a>
                                <span class="mx-2 text-gray-300 dark:text-gray-600">|</span>
                                <a href="{{ route('sistema.olts.edit', $o) }}" class="font-medium text-purple-600 hover:underline dark:text-purple-400">Editar</a>
                                <span class="mx-2 text-gray-300 dark:text-gray-600">|</span>
                                <form action="{{ route('sistema.olts.destroy', $o) }}" method="POST" class="inline" onsubmit="return confirm('¿Eliminar este OLT?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="font-medium text-red-600 hover:underline dark:text-red-400">Eliminar</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-10 text-center text-sm text-gray-500 dark:text-gray-400">
                                No hay OLTs.
                                <a href="{{ route('sistema.olts.create') }}" class="font-medium text-purple-600 hover:underline dark:text-purple-400">Crear uno</a>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($olts->hasPages())
            <div class="border-t border-gray-200 bg-gray-50 px-4 py-3 dark:border-gray-700 dark:bg-gray-900/30 sm:px-6">{{ $olts->links() }}</div>
        @endif
    </div>
</div>
@endsection
