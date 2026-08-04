@extends('layouts.app')

@section('title', 'Novedades')

@section('content')
<div class="max-w-7xl mx-auto">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">Novedades (App)</h1>
        <a href="{{ route('loyalty.novedades.create') }}"
            class="inline-flex items-center px-4 py-2 bg-purple-600 text-white rounded-lg font-medium hover:bg-purple-700">Nueva novedad</a>
    </div>

    @if(session('success'))
        <div class="mb-4 rounded-lg bg-green-50 dark:bg-green-900/30 text-green-800 dark:text-green-200 px-4 py-3">{{ session('success') }}</div>
    @endif

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 overflow-hidden">
        <form method="GET" class="p-4 border-b border-gray-200 dark:border-gray-700 flex gap-3">
            <input type="text" name="buscar" value="{{ request('buscar') }}" placeholder="Buscar..."
                class="flex-1 px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 dark:text-gray-100">
            <button class="px-4 py-2 bg-purple-600 text-white rounded-lg">Buscar</button>
        </form>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-700/50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Img</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Título</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tipo</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Orden</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Activa</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($novedades as $n)
                        <tr>
                            <td class="px-4 py-3">
                                @if($n->imagenUrl())
                                    <img src="{{ $n->imagenUrl() }}" alt="" class="h-12 w-16 object-cover rounded">
                                @else
                                    <span class="text-xs text-gray-400">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-900 dark:text-gray-100">
                                <div class="font-medium">{{ $n->titulo }}</div>
                                <div class="text-xs text-gray-500">{{ $n->subtitulo }}</div>
                            </td>
                            <td class="px-4 py-3 text-sm">{{ $n->tipo }}</td>
                            <td class="px-4 py-3 text-sm">{{ $n->orden }}</td>
                            <td class="px-4 py-3 text-sm">{{ $n->activa ? 'Sí' : 'No' }}</td>
                            <td class="px-4 py-3 text-right text-sm space-x-2">
                                <a href="{{ route('loyalty.novedades.edit', $n) }}" class="text-purple-600 hover:underline">Editar</a>
                                <form action="{{ route('loyalty.novedades.destroy', $n) }}" method="POST" class="inline" onsubmit="return confirm('¿Eliminar?')">
                                    @csrf @method('DELETE')
                                    <button class="text-red-600 hover:underline">Eliminar</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-8 text-center text-gray-500">Sin novedades.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-4">{{ $novedades->links() }}</div>
    </div>
</div>
@endsection
