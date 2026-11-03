@extends('layouts.app')

@section('title', 'Disco')

@section('content')
<div class="max-w-3xl">
    <h1 class="text-xl font-semibold mb-1">Disco</h1>
    <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">Si el temporal llega al 100%, el sitio responde 500. La alerta aparece arriba en todas las pantallas.</p>

    <div class="overflow-hidden rounded-lg border border-gray-200 dark:border-gray-700">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50 dark:bg-gray-800 text-left">
                <tr>
                    <th class="px-3 py-2">Volumen</th>
                    <th class="px-3 py-2">Uso</th>
                    <th class="px-3 py-2">Libre</th>
                    <th class="px-3 py-2">Ruta</th>
                </tr>
            </thead>
            <tbody>
                @forelse($estado['montajes'] as $m)
                    <tr class="border-t border-gray-200 dark:border-gray-700 {{ $m['alerta'] ? 'bg-red-50 dark:bg-red-950' : '' }}">
                        <td class="px-3 py-2">{{ $m['nombre'] }}</td>
                        <td class="px-3 py-2">{{ $m['uso'] }}%</td>
                        <td class="px-3 py-2">{{ \App\Services\Sistema\DiscoService::humano($m['libre']) }}</td>
                        <td class="px-3 py-2 text-gray-500">{{ $m['ruta'] }}</td>
                    </tr>
                @empty
                    <tr><td class="px-3 py-3" colspan="4">No se pudo leer el disco.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
