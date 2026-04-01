@extends('layouts.app')

@section('title', 'Marcas OLT')

@section('content')
<div class="max-w-4xl mx-auto">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">Marcas OLT</h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Catálogo de marcas de equipos OLT (ZTE, Huawei, Nokia, etc.)</p>
        </div>
        <a href="{{ route('sistema.olt-marcas.create') }}"
            class="inline-flex items-center px-4 py-2 bg-purple-600 text-white rounded-lg font-medium hover:bg-purple-700">
            Nueva marca
        </a>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 overflow-hidden">
        <form method="GET" action="{{ route('sistema.olt-marcas.index') }}" class="p-4 border-b border-gray-200 dark:border-gray-700">
            <div class="flex gap-3">
                <input type="text" name="buscar" value="{{ request('buscar') }}" placeholder="Buscar por nombre..."
                    class="flex-1 px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700">
                <button type="submit" class="px-6 py-2 bg-purple-600 text-white rounded-lg font-medium">Buscar</button>
            </div>
        </form>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-700/50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Nombre</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Estado</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse ($marcas as $m)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                            <td class="px-4 py-3 font-medium">{{ $m->nombre }}</td>
                            <td class="px-4 py-3">
                                <span class="px-2 py-0.5 rounded text-xs {{ $m->estado === 'activo' ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300' : 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300' }}">
                                    {{ ucfirst($m->estado) }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <a href="{{ route('sistema.olt-marcas.edit', $m) }}" class="text-purple-600 dark:text-purple-400 hover:underline mr-3">Editar</a>
                                <form action="{{ route('sistema.olt-marcas.destroy', $m) }}" method="POST" class="inline" onsubmit="return confirm('¿Eliminar esta marca?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-red-600 dark:text-red-400 hover:underline">Eliminar</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">No hay marcas. <a href="{{ route('sistema.olt-marcas.create') }}" class="text-purple-600 hover:underline">Crear una</a></td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($marcas->hasPages())
            <div class="px-4 py-3 border-t border-gray-200 dark:border-gray-700">{{ $marcas->links() }}</div>
        @endif
    </div>
</div>
@endsection
