@extends('layouts.app')
@section('title', 'Premios')
@section('content')
<div class="max-w-7xl mx-auto">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">Premios (Loyalty)</h1>
        <a href="{{ route('loyalty.premios.create') }}" class="inline-flex px-4 py-2 bg-purple-600 text-white rounded-lg">Nuevo premio</a>
    </div>
    @if(session('success'))<div class="mb-4 rounded-lg bg-green-50 text-green-800 px-4 py-3">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="mb-4 rounded-lg bg-red-50 text-red-800 px-4 py-3">{{ session('error') }}</div>@endif
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-700/50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs uppercase text-gray-500">Img</th>
                        <th class="px-4 py-3 text-left text-xs uppercase text-gray-500">Nombre</th>
                        <th class="px-4 py-3 text-left text-xs uppercase text-gray-500">Tipo</th>
                        <th class="px-4 py-3 text-left text-xs uppercase text-gray-500">Puntos</th>
                        <th class="px-4 py-3 text-left text-xs uppercase text-gray-500">Stock</th>
                        <th class="px-4 py-3 text-left text-xs uppercase text-gray-500">Destacado</th>
                        <th class="px-4 py-3 text-left text-xs uppercase text-gray-500">Activo</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($premios as $p)
                        <tr>
                            <td class="px-4 py-3">@if($p->imagenUrl())<img src="{{ $p->imagenUrl() }}" class="h-12 w-12 object-cover rounded">@endif</td>
                            <td class="px-4 py-3 text-sm font-medium text-gray-900 dark:text-gray-100">{{ $p->nombre }}</td>
                            <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-300">{{ ($tipos[$p->tipo] ?? null) ?: ($p->tipo ?: 'físico') }}</td>
                            <td class="px-4 py-3 text-sm">{{ $p->puntos_requeridos }}</td>
                            <td class="px-4 py-3 text-sm">{{ $p->stock }}</td>
                            <td class="px-4 py-3 text-sm">
                                @if($p->destacado)
                                    <span class="inline-flex px-2 py-0.5 rounded text-xs font-medium bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-200">Sí</span>
                                @else
                                    No
                                @endif
                            </td>
                            <td class="px-4 py-3 text-sm">{{ $p->activo ? 'Sí' : 'No' }}</td>
                            <td class="px-4 py-3 text-right text-sm space-x-2">
                                <a href="{{ route('loyalty.premios.edit', $p) }}" class="text-purple-600">Editar</a>
                                <form action="{{ route('loyalty.premios.destroy', $p) }}" method="POST" class="inline" onsubmit="return confirm('¿Eliminar?')">
                                    @csrf @method('DELETE')
                                    <button class="text-red-600">Eliminar</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="px-4 py-8 text-center text-gray-500">Sin premios.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-4">{{ $premios->links() }}</div>
    </div>
</div>
@endsection
