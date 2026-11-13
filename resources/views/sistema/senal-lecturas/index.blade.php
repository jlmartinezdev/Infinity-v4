@extends('layouts.app')

@section('title', 'Informe de señal')

@section('content')
<div class="max-w-5xl mx-auto space-y-6">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">Informe de señal</h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                Cada día a las 04:00 se lee la señal de los servicios activos. Si el equipo estaba apagado, la lectura corre al encender.
                Acá quedan las que no se pudieron leer.
            </p>
        </div>
        <a href="{{ route('sistema.red-monitoreo.index') }}"
            class="text-sm text-blue-600 dark:text-blue-400 hover:underline">← Monitoreo de red</a>
    </div>

    <div class="overflow-hidden rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50 dark:bg-gray-900/40 text-left text-gray-500 dark:text-gray-400">
                <tr>
                    <th class="px-4 py-3 font-medium">Fecha</th>
                    <th class="px-4 py-3 font-medium">Duración</th>
                    <th class="px-4 py-3 font-medium">Fibra</th>
                    <th class="px-4 py-3 font-medium">Antena</th>
                    <th class="px-4 py-3 font-medium">Estado</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                @forelse($lecturas as $lectura)
                    <tr>
                        <td class="px-4 py-3 text-gray-900 dark:text-gray-100">
                            {{ $lectura->inicio_at?->format('d/m/Y H:i') }}
                            @if($lectura->parcial)
                                <span class="ml-1 text-xs text-gray-400">parcial</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ $lectura->duracionHumana() }}</td>
                        <td class="px-4 py-3 text-gray-700 dark:text-gray-200">
                            {{ $lectura->fibra_leidas }} leídas
                            @if($lectura->fibra_fallidas)
                                · <span class="text-amber-700 dark:text-amber-300">{{ $lectura->fibra_fallidas }} sin lectura</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-gray-700 dark:text-gray-200">
                            {{ $lectura->antena_leidas }} leídas
                            @if($lectura->antena_fallidas)
                                · <span class="text-amber-700 dark:text-amber-300">{{ $lectura->antena_fallidas }} sin lectura</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-gray-600 dark:text-gray-300">
                            {{ $lectura->estado === 'lista' ? 'Lista' : ($lectura->estado === 'error' ? 'Interrumpida' : 'En curso') }}
                        </td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('sistema.senal-lecturas.show', $lectura) }}"
                                class="text-blue-600 dark:text-blue-400 hover:underline">Ver fallos</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-8 text-center text-gray-500">Todavía no hay lecturas. La primera corre a las 04:00.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $lecturas->links() }}
</div>
@endsection
