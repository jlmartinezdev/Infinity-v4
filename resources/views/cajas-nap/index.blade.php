@extends('layouts.app')

@section('title', 'Cajas NAP')

@section('content')
<div class="max-w-7xl mx-auto">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">Cajas NAP</h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Infraestructura óptica: cajas, splitters, salidas PON</p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('sistema.cajas-nap.mapa') }}"
                class="inline-flex items-center px-4 py-2 bg-cyan-600 text-white rounded-lg font-medium hover:bg-cyan-700 focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:ring-offset-2">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/></svg>
                Ver mapa
            </a>
            <a href="{{ route('sistema.cajas-nap.create') }}"
                class="inline-flex items-center px-4 py-2 bg-purple-600 text-white rounded-lg font-medium hover:bg-purple-700 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:ring-offset-2">
                Nueva caja NAP
            </a>
        </div>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 overflow-hidden">
        <form method="GET" action="{{ route('sistema.cajas-nap.index') }}" class="p-4 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-700/50">
            <div class="flex flex-col sm:flex-row gap-3">
                <input type="text" name="buscar" value="{{ request('buscar') }}"
                    placeholder="Buscar por código, descripción..."
                    class="flex-1 px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                <select name="nodo_id" class="px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                    <option value="">Todos los nodos</option>
                    @foreach($nodos as $n)
                        <option value="{{ $n->nodo_id }}" {{ request('nodo_id') == $n->nodo_id ? 'selected' : '' }}>{{ $n->descripcion }}</option>
                    @endforeach
                </select>
                <select name="tipo" class="px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                    <option value="">Todos los tipos</option>
                    <option value="primaria" {{ request('tipo') === 'primaria' ? 'selected' : '' }}>Primaria</option>
                    <option value="secundaria" {{ request('tipo') === 'secundaria' ? 'selected' : '' }}>Secundaria</option>
                </select>
                <button type="submit" class="px-6 py-2.5 bg-purple-600 text-white rounded-lg font-medium hover:bg-purple-700">Buscar</button>
            </div>
        </form>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-700/50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Código</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Nodo</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Tipo</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">FTTH</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Coordenadas</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse ($cajas as $c)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                            <td class="px-4 py-3 font-medium">{{ $c->codigo }}</td>
                            <td class="px-4 py-3">{{ $c->nodo?->descripcion ?? '—' }}</td>
                            <td class="px-4 py-3">
                                <span class="px-2 py-0.5 rounded text-xs {{ $c->tipo === 'primaria' ? 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300' : 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300' }}">
                                    {{ ucfirst($c->tipo) }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-sm">
                                @if($c->splitter_segundo_nivel)
                                    <span class="font-medium">1×{{ $c->splitter_segundo_nivel }}</span>
                                    <span class="text-gray-500 dark:text-gray-400 text-xs block mt-0.5">
                                        {{ max(0, (int) $c->splitter_segundo_nivel - (int) $c->puertos_ocupados_count) }}/{{ (int) $c->splitter_segundo_nivel }} libres
                                    </span>
                                @else
                                    <span class="text-gray-400">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-sm">{{ $c->lat && $c->lon ? number_format($c->lat, 5) . ', ' . number_format($c->lon, 5) : '—' }}</td>
                            <td class="px-4 py-3 text-right">
                                <a href="{{ route('sistema.cajas-nap.show', $c) }}" class="text-purple-600 dark:text-purple-400 hover:underline mr-3">Ver</a>
                                <a href="{{ route('sistema.cajas-nap.edit', $c) }}" class="text-purple-600 dark:text-purple-400 hover:underline mr-3">Editar</a>
                                <form action="{{ route('sistema.cajas-nap.destroy', $c) }}" method="POST" class="inline" onsubmit="return confirm('¿Eliminar esta caja NAP?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-red-600 dark:text-red-400 hover:underline">Eliminar</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">
                                No hay cajas NAP. <a href="{{ route('sistema.cajas-nap.create') }}" class="text-purple-600 hover:underline">Crear una</a>.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($cajas->hasPages())
            <div class="px-4 py-3 border-t border-gray-200 dark:border-gray-700">{{ $cajas->links() }}</div>
        @endif
    </div>

    <div class="mt-6 flex gap-4">
        <a href="{{ route('sistema.salida-pons.index') }}" class="text-sm text-gray-600 dark:text-gray-400 hover:underline">Salidas PON →</a>
        <a href="{{ route('sistema.lineas-cable.index') }}" class="text-sm text-gray-600 dark:text-gray-400 hover:underline">Líneas de cable →</a>
    </div>
</div>
@endsection
