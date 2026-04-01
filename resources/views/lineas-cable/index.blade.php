@extends('layouts.app')

@section('title', 'Líneas de cable')

@section('content')
<div class="max-w-7xl mx-auto">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">Líneas de cable</h1>
        <a href="{{ route('sistema.lineas-cable.create') }}"
            class="inline-flex items-center px-4 py-2 bg-purple-600 text-white rounded-lg font-medium hover:bg-purple-700">
            Nueva línea
        </a>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-700/50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">ID</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Color fibra</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Origen</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Destino</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Longitud</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse ($lineas as $l)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                            <td class="px-4 py-3">{{ $l->linea_cable_id }}</td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center gap-1">
                                    <span class="w-3 h-3 rounded-full" style="background-color: {{ $l->fibraColor?->codigo_hex ?? '#666' }}"></span>
                                    {{ $l->fibraColor?->nombre ?? '—' }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-sm">{{ $l->origen_tipo }} #{{ $l->origen_id }}</td>
                            <td class="px-4 py-3 text-sm">{{ $l->destino_tipo }} #{{ $l->destino_id }}</td>
                            <td class="px-4 py-3">{{ $l->longitud_metros ? number_format($l->longitud_metros, 0) . ' m' : '—' }}</td>
                            <td class="px-4 py-3 text-right">
                                <a href="{{ route('sistema.lineas-cable.edit', $l) }}" class="text-purple-600 hover:underline mr-3">Editar</a>
                                <form action="{{ route('sistema.lineas-cable.destroy', $l) }}" method="POST" class="inline" onsubmit="return confirm('¿Eliminar?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-red-600 hover:underline">Eliminar</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-8 text-center text-gray-500">No hay líneas de cable.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($lineas->hasPages())
            <div class="px-4 py-3 border-t border-gray-200 dark:border-gray-700">{{ $lineas->links() }}</div>
        @endif
    </div>
</div>
@endsection
