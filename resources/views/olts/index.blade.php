@extends('layouts.app')

@section('title', 'OLTs')

@section('content')
<div class="max-w-7xl mx-auto">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">OLTs</h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Equipos Optical Line Terminal (FTTH)</p>
        </div>
        <a href="{{ route('sistema.olts.create') }}"
            class="inline-flex items-center px-4 py-2 bg-purple-600 text-white rounded-lg font-medium hover:bg-purple-700">
            Nuevo OLT
        </a>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 overflow-hidden">
        <form method="GET" action="{{ route('sistema.olts.index') }}" class="p-4 border-b border-gray-200 dark:border-gray-700">
            <div class="flex flex-wrap gap-3">
                <select name="nodo_id" class="px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                    <option value="">Todos los nodos</option>
                    @foreach($nodos as $n)
                        <option value="{{ $n->nodo_id }}" {{ request('nodo_id') == $n->nodo_id ? 'selected' : '' }}>{{ $n->descripcion }}</option>
                    @endforeach
                </select>
                <select name="olt_marca_id" class="px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                    <option value="">Todas las marcas</option>
                    @foreach($marcas as $m)
                        <option value="{{ $m->olt_marca_id }}" {{ request('olt_marca_id') == $m->olt_marca_id ? 'selected' : '' }}>{{ $m->nombre }}</option>
                    @endforeach
                </select>
                <button type="submit" class="px-6 py-2 bg-purple-600 text-white rounded-lg font-medium">Filtrar</button>
            </div>
        </form>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-700/50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">IP / Modelo</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Marca</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Nodo</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Tipo PON</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Puertos</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse ($olts as $o)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                            <td class="px-4 py-3">
                                <span class="font-medium">{{ $o->ip ?? '—' }}</span>
                                @if($o->modelo)
                                    <span class="block text-xs text-gray-500 dark:text-gray-400">{{ $o->modelo }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-3">{{ $o->oltMarca?->nombre ?? '—' }}</td>
                            <td class="px-4 py-3">{{ $o->nodo?->descripcion ?? '—' }}</td>
                            <td class="px-4 py-3">{{ $o->tipo_pon }}</td>
                            <td class="px-4 py-3">{{ $o->oltPuertos->count() }} / {{ $o->cantidad_puertos }}</td>
                            <td class="px-4 py-3 text-right">
                                <a href="{{ route('sistema.olts.show', $o) }}" class="text-purple-600 dark:text-purple-400 hover:underline mr-3">Ver</a>
                                <a href="{{ route('sistema.olts.edit', $o) }}" class="text-purple-600 dark:text-purple-400 hover:underline mr-3">Editar</a>
                                <form action="{{ route('sistema.olts.destroy', $o) }}" method="POST" class="inline" onsubmit="return confirm('¿Eliminar este OLT?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-red-600 dark:text-red-400 hover:underline">Eliminar</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">No hay OLTs. <a href="{{ route('sistema.olts.create') }}" class="text-purple-600 hover:underline">Crear uno</a></td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($olts->hasPages())
            <div class="px-4 py-3 border-t border-gray-200 dark:border-gray-700">{{ $olts->links() }}</div>
        @endif
    </div>
</div>
@endsection
