@extends('layouts.app')

@section('title', 'Salidas PON')

@section('content')
<div class="max-w-7xl mx-auto">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">Salidas PON</h1>
        <a href="{{ route('sistema.salida-pons.create') }}"
            class="inline-flex items-center px-4 py-2 bg-purple-600 text-white rounded-lg font-medium hover:bg-purple-700">
            Nueva salida PON
        </a>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 overflow-hidden">
        <form method="GET" action="{{ route('sistema.salida-pons.index') }}" class="p-4 border-b border-gray-200 dark:border-gray-700">
            <div class="flex gap-3">
                <select name="nodo_id" class="px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700">
                    <option value="">Todos los nodos</option>
                    @foreach($nodos as $n)
                        <option value="{{ $n->nodo_id }}" {{ request('nodo_id') == $n->nodo_id ? 'selected' : '' }}>{{ $n->descripcion }}</option>
                    @endforeach
                </select>
                <button type="submit" class="px-6 py-2 bg-purple-600 text-white rounded-lg font-medium">Filtrar</button>
            </div>
        </form>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-700/50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Código</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Nodo</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">OLT</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Puerto OLT</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Módulo / potencia</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Cajas NAP</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse ($salidas as $s)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                            <td class="px-4 py-3 font-medium">{{ $s->codigo }}</td>
                            <td class="px-4 py-3">{{ $s->nodo?->descripcion ?? '—' }}</td>
                            <td class="px-4 py-3 text-sm">
                                @if($s->olt)
                                    {{ $s->olt->codigo ?? $s->olt->ip ?? 'OLT #' . $s->olt_id }}
                                @else
                                    —
                                @endif
                            </td>
                            <td class="px-4 py-3">{{ $s->puerto_olt }}</td>
                            <td class="px-4 py-3 text-sm">
                                {{ $s->tipo_modulo ?? '—' }}
                                @if($s->potencia_salida !== null)
                                    <span class="text-gray-500"> / {{ number_format((float) $s->potencia_salida, 2) }} dBm</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-sm">{{ $s->cajaNaps->pluck('codigo')->filter()->implode(', ') ?: '—' }}</td>
                            <td class="px-4 py-3 text-right">
                                <a href="{{ route('sistema.salida-pons.edit', $s) }}" class="text-purple-600 hover:underline mr-3">Editar</a>
                                <form action="{{ route('sistema.salida-pons.destroy', $s) }}" method="POST" class="inline" onsubmit="return confirm('¿Eliminar?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-red-600 hover:underline">Eliminar</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">No hay salidas PON.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($salidas->hasPages())
            <div class="px-4 py-3 border-t border-gray-200 dark:border-gray-700">{{ $salidas->links() }}</div>
        @endif
    </div>
</div>
@endsection
