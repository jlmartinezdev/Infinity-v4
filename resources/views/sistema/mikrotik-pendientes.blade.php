@extends('layouts.app')

@section('title', 'MikroTik — operaciones pendientes')

@section('content')
<div class="max-w-6xl mx-auto">
    <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100 mb-2">Operaciones MikroTik pendientes</h1>
    <p class="text-sm text-gray-600 dark:text-gray-400 mb-6">
        Fallos de sincronización o comandos guardados para reintentar. El comando programado <code class="text-xs bg-gray-100 dark:bg-gray-700 px-1 rounded">mikrotik:procesar-pendientes</code> también procesa esta cola.
    </p>

    @if(session('success'))
        <div class="mb-4 p-4 rounded-lg bg-green-100 dark:bg-green-900/20 text-green-800 dark:text-green-200 text-sm border border-green-200 dark:border-green-800">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="mb-4 p-4 rounded-lg bg-red-100 dark:bg-red-900/20 text-red-800 dark:text-red-200 text-sm border border-red-200 dark:border-red-800">{{ session('error') }}</div>
    @endif
    @if(session('warning'))
        <div class="mb-4 p-4 rounded-lg bg-amber-100 dark:bg-amber-900/20 text-amber-900 dark:text-amber-200 text-sm border border-amber-200 dark:border-amber-800">{{ session('warning') }}</div>
    @endif

    <div class="flex flex-wrap gap-3 mb-6">
        <form action="{{ route('sistema.mikrotik-pendientes.reintentar-todos') }}" method="POST" onsubmit="return confirm('¿Reintentar todas las operaciones pendientes?');">
            @csrf
            <button type="submit" class="inline-flex items-center px-4 py-2 rounded-lg font-medium text-white bg-purple-600 hover:bg-purple-700 text-sm">
                Reintentar todas (pendientes)
            </button>
        </form>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 overflow-hidden mb-10">
        <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-700/50">
            <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-200">Pendientes ({{ $pendientes->total() }})</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-200 dark:border-gray-700 text-left text-gray-500 dark:text-gray-400">
                        <th class="px-4 py-3 font-medium">Actualizado</th>
                        <th class="px-4 py-3 font-medium">Tipo</th>
                        <th class="px-4 py-3 font-medium">Origen</th>
                        <th class="px-4 py-3 font-medium">Payload</th>
                        <th class="px-4 py-3 font-medium">Intentos</th>
                        <th class="px-4 py-3 font-medium">Último error</th>
                        <th class="px-4 py-3 font-medium w-40">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($pendientes as $op)
                    <tr class="text-gray-900 dark:text-gray-100">
                        <td class="px-4 py-3 whitespace-nowrap">{{ $op->updated_at->format('d/m/Y H:i') }}</td>
                        <td class="px-4 py-3">{{ $labelsTipo[$op->tipo] ?? $op->tipo }}</td>
                        <td class="px-4 py-3 text-gray-600 dark:text-gray-400">{{ $op->origen ?? '—' }}</td>
                        <td class="px-4 py-3 font-mono text-xs max-w-xs break-all">{{ json_encode($op->payload, JSON_UNESCAPED_UNICODE) }}</td>
                        <td class="px-4 py-3">{{ $op->intentos }}</td>
                        <td class="px-4 py-3 text-red-700 dark:text-red-300 max-w-md break-words">{{ $op->error_ultimo ? mb_substr($op->error_ultimo, 0, 200) . (mb_strlen($op->error_ultimo) > 200 ? '…' : '') : '—' }}</td>
                        <td class="px-4 py-3">
                            <div class="flex flex-col gap-1">
                                <form action="{{ route('sistema.mikrotik-pendientes.reintentar', $op->id) }}" method="POST">
                                    @csrf
                                    <button type="submit" class="text-purple-600 dark:text-purple-400 hover:underline text-xs">Reintentar</button>
                                </form>
                                <form action="{{ route('sistema.mikrotik-pendientes.descartar', $op->id) }}" method="POST" onsubmit="return confirm('¿Descartar esta operación?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-gray-500 dark:text-gray-400 hover:underline text-xs">Descartar</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">No hay operaciones pendientes.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($pendientes->hasPages())
            <div class="px-4 py-3 border-t border-gray-200 dark:border-gray-700">{{ $pendientes->links() }}</div>
        @endif
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 overflow-hidden">
        <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-700/50">
            <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-200">Últimas completadas o descartadas</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-200 dark:border-gray-700 text-left text-gray-500 dark:text-gray-400">
                        <th class="px-4 py-3 font-medium">Estado</th>
                        <th class="px-4 py-3 font-medium">Actualizado</th>
                        <th class="px-4 py-3 font-medium">Tipo</th>
                        <th class="px-4 py-3 font-medium">Payload</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($recientes as $op)
                    <tr class="text-gray-700 dark:text-gray-300">
                        <td class="px-4 py-3">
                            @if($op->estado === \App\Models\MikrotikOperacionPendiente::ESTADO_COMPLETADO)
                                <span class="text-green-600 dark:text-green-400">Completado</span>
                            @else
                                <span class="text-gray-500">Descartado</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap">{{ $op->updated_at->format('d/m/Y H:i') }}</td>
                        <td class="px-4 py-3">{{ $labelsTipo[$op->tipo] ?? $op->tipo }}</td>
                        <td class="px-4 py-3 font-mono text-xs max-w-lg break-all">{{ json_encode($op->payload, JSON_UNESCAPED_UNICODE) }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="4" class="px-4 py-6 text-center text-gray-500 dark:text-gray-400">Sin registros recientes.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
