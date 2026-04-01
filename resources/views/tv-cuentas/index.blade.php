@extends('layouts.app')

@section('title', 'Cuentas TV (app)')

@section('content')
<div class="max-w-6xl mx-auto">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">Cuentas TV (streaming)</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Cada cuenta permite hasta 3 clientes (1 dispositivo por cliente).</p>
        </div>
        @if(auth()->user()?->tienePermiso('tv.editar'))
            <a href="{{ route('tv-cuentas.create') }}" class="inline-flex items-center px-4 py-2 bg-purple-600 text-white rounded-lg font-medium hover:bg-purple-700">
                Nueva cuenta
            </a>
        @endif
    </div>

    @if(session('success'))
        <div class="mb-4 p-4 rounded-lg bg-green-100 dark:bg-green-900/30 text-green-800 dark:text-green-200 border border-green-200 dark:border-green-800 text-sm">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="mb-4 p-4 rounded-lg bg-red-100 dark:bg-red-900/30 text-red-800 dark:text-red-200 border border-red-200 dark:border-red-800 text-sm">{{ session('error') }}</div>
    @endif

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-700/50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Nombre / usuario app</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Vencimiento pago</th>
                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Usos</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($cuentas as $c)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                            <td class="px-4 py-3 text-sm">
                                <span class="font-medium text-gray-900 dark:text-gray-100">{{ $c->nombre ?: '—' }}</span>
                                <div class="text-gray-500 dark:text-gray-400 text-xs mt-0.5">{{ $c->usuario_app }}</div>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">
                                {{ $c->vencimiento_pago->format('d/m/Y') }}
                                @if($c->vencimiento_pago->isPast())
                                    <span class="ml-2 text-xs text-red-600 dark:text-red-400">(vencido)</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-sm text-center">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $c->asignaciones_count >= 3 ? 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-200' : 'bg-gray-100 text-gray-700 dark:bg-gray-600 dark:text-gray-200' }}">
                                    {{ $c->asignaciones_count }} / 3
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                @if(auth()->user()?->tienePermiso('tv.editar'))
                                    <a href="{{ route('tv-cuentas.edit', $c) }}" class="text-purple-600 dark:text-purple-400 hover:underline text-sm font-medium">Editar</a>
                                @else
                                    <span class="text-gray-400 text-sm">—</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">No hay cuentas TV registradas.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($cuentas->hasPages())
            <div class="px-4 py-3 border-t border-gray-200 dark:border-gray-700">{{ $cuentas->links() }}</div>
        @endif
    </div>
</div>
@endsection
