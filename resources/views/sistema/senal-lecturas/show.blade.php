@extends('layouts.app')

@section('title', 'Fallos de señal')

@section('content')
<div class="max-w-5xl mx-auto space-y-6">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">
                Lectura del {{ $lectura->inicio_at?->format('d/m/Y H:i') }}
            </h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                Fibra {{ $lectura->fibra_leidas }} leídas, {{ $lectura->fibra_fallidas }} sin lectura.
                Antena {{ $lectura->antena_leidas }} leídas, {{ $lectura->antena_fallidas }} sin lectura.
                Duración {{ $lectura->duracionHumana() }}.
            </p>
        </div>
        <a href="{{ route('sistema.senal-lecturas.index') }}"
            class="text-sm text-blue-600 dark:text-blue-400 hover:underline">← Informes</a>
    </div>

    @if($lectura->nota)
        <pre class="whitespace-pre-wrap rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/40 p-4 text-xs text-gray-700 dark:text-gray-300">{{ $lectura->nota }}</pre>
    @endif

    <div class="flex flex-wrap gap-2 text-sm">
        <a href="{{ route('sistema.senal-lecturas.show', $lectura) }}"
            class="rounded-lg px-3 py-1.5 {{ $tipo === null ? 'bg-gray-900 text-white dark:bg-gray-100 dark:text-gray-900' : 'text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800' }}">Todas</a>
        <a href="{{ route('sistema.senal-lecturas.show', [$lectura, 'tipo' => 'optica']) }}"
            class="rounded-lg px-3 py-1.5 {{ $tipo === 'optica' ? 'bg-gray-900 text-white dark:bg-gray-100 dark:text-gray-900' : 'text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800' }}">Fibra</a>
        <a href="{{ route('sistema.senal-lecturas.show', [$lectura, 'tipo' => 'antena']) }}"
            class="rounded-lg px-3 py-1.5 {{ $tipo === 'antena' ? 'bg-gray-900 text-white dark:bg-gray-100 dark:text-gray-900' : 'text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800' }}">Antena</a>
    </div>

    <div class="overflow-hidden rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50 dark:bg-gray-900/40 text-left text-gray-500 dark:text-gray-400">
                <tr>
                    <th class="px-4 py-3 font-medium">Cliente</th>
                    <th class="px-4 py-3 font-medium">Medio</th>
                    <th class="px-4 py-3 font-medium">Motivo</th>
                    <th class="px-4 py-3 font-medium">Detalle</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                @forelse($fallos as $fallo)
                    @php
                        $cliente = $fallo->servicio?->cliente;
                        $nombre = trim(($cliente->nombre ?? '').' '.($cliente->apellido ?? ''));
                    @endphp
                    <tr>
                        <td class="px-4 py-3">
                            @if($cliente)
                                <a href="{{ route('clientes.detalle', $cliente) }}?tab=red" class="font-medium text-blue-600 dark:text-blue-400 hover:underline">
                                    {{ $nombre !== '' ? $nombre : 'Cliente #'.$cliente->cliente_id }}
                                </a>
                            @else
                                <span class="text-gray-500">Servicio #{{ $fallo->servicio_id }}</span>
                            @endif
                            <div class="text-xs text-gray-400">{{ $fallo->servicio?->usuario_pppoe }}</div>
                        </td>
                        <td class="px-4 py-3 text-gray-700 dark:text-gray-200">{{ $fallo->tipo === 'antena' ? 'Antena' : 'Fibra' }}</td>
                        <td class="px-4 py-3 text-gray-900 dark:text-gray-100">{{ $fallo->motivoEtiqueta() }}</td>
                        <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ $fallo->detalle }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-4 py-8 text-center text-gray-500">No hay fallos en este filtro.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $fallos->links() }}
</div>
@endsection
