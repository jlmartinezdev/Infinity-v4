@extends('layouts.app')

@section('title', 'Asociación de señal')

@section('content')
@php
    $sinAsociar = $grupos['sin_asociar'];
    $sinRx = $grupos['sin_rx'];
    $ambiguas = $grupos['asociacion_ambigua'];
    $porZona = function (array $filas): array {
        $nodos = [];
        foreach ($filas as $fila) {
            $nodos[$fila['nodo']][$fila['olt']][] = $fila;
        }

        return $nodos;
    };
    $zonasSinAsociar = $porZona($sinAsociar);
    $zonasSinRx = $porZona($sinRx);
    $zonasAmbiguas = $porZona($ambiguas);
@endphp
<div class="max-w-5xl mx-auto space-y-8">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">
                Asociación del {{ $lectura->inicio_at?->format('d/m/Y H:i') }}
            </h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                {{ count($sinAsociar) }} no coincidieron,
                {{ count($sinRx) }} no devolvieron potencia
                y {{ count($ambiguas) }} coinciden con más de una ONU.
            </p>
        </div>
        <a href="{{ route('sistema.senal-lecturas.show', $lectura) }}"
            class="text-sm text-gray-500 dark:text-gray-400 hover:underline">← Fallos de esta lectura</a>
    </div>

    <section class="space-y-3">
        <div>
            <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">No coincidieron · {{ count($sinAsociar) }}</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400">Ni el usuario PPPoE ni el nombre del cliente coinciden con la descripción de una ONU, con o sin guion. Tampoco hay un puerto guardado de una lectura anterior.</p>
        </div>
        @forelse($zonasSinAsociar as $nodo => $olts)
            <div class="space-y-3">
                <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $nodo }}</h3>
                @foreach($olts as $olt => $filas)
                    <div class="overflow-hidden rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800">
                        <div class="border-b border-gray-100 dark:border-gray-700 px-4 py-2 text-xs font-medium text-gray-500 dark:text-gray-400">
                            {{ $olt }} · {{ count($filas) }}
                        </div>
                        <table class="min-w-full text-sm">
                            <thead class="bg-gray-50 dark:bg-gray-900/40 text-left text-gray-500 dark:text-gray-400">
                                <tr>
                                    <th class="px-4 py-3 font-medium">Cliente</th>
                                    <th class="px-4 py-3 font-medium">PPPoE</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                @foreach($filas as $fila)
                                    <tr>
                                        <td class="px-4 py-3">
                                            @include('sistema.senal-lecturas._cliente', ['fila' => $fila])
                                        </td>
                                        <td class="px-4 py-3 text-gray-700 dark:text-gray-200">{{ $fila['pppoe'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endforeach
            </div>
        @empty
            <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 px-4 py-8 text-center text-sm text-gray-500">Ningún servicio quedó sin coincidencia.</div>
        @endforelse
    </section>

    <section class="space-y-3">
        <div>
            <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">No devolvieron potencia · {{ count($sinRx) }}</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400">La ONU está identificada, pero esta lectura no trajo su potencia. La cifra es la última que había guardada.</p>
        </div>
        @forelse($zonasSinRx as $nodo => $olts)
            <div class="space-y-3">
                <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $nodo }}</h3>
                @foreach($olts as $olt => $filas)
                    <div class="overflow-hidden rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800">
                        <div class="border-b border-gray-100 dark:border-gray-700 px-4 py-2 text-xs font-medium text-gray-500 dark:text-gray-400">
                            {{ $olt }} · {{ count($filas) }}
                        </div>
                        <table class="min-w-full text-sm">
                            <thead class="bg-gray-50 dark:bg-gray-900/40 text-left text-gray-500 dark:text-gray-400">
                                <tr>
                                    <th class="px-4 py-3 font-medium">Cliente</th>
                                    <th class="px-4 py-3 font-medium">PPPoE</th>
                                    <th class="px-4 py-3 font-medium">ONU</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                @foreach($filas as $fila)
                                    <tr>
                                        <td class="px-4 py-3">
                                            @include('sistema.senal-lecturas._cliente', ['fila' => $fila])
                                        </td>
                                        <td class="px-4 py-3 text-gray-700 dark:text-gray-200">{{ $fila['pppoe'] }}</td>
                                        <td class="px-4 py-3 text-gray-700 dark:text-gray-200">
                                            @if($fila['onus'] === [])
                                                <span class="text-gray-400">Sin ONU en el inventario</span>
                                            @else
                                                @foreach($fila['onus'] as $onu)
                                                    <div>PON {{ $onu['pon'] }}:{{ $onu['indice'] }}</div>
                                                    <div class="text-xs text-gray-400">
                                                        {{ $onu['descripcion'] }} · {{ $onu['estado'] }}
                                                        @if($onu['rx'])
                                                            · {{ $onu['rx'] }} dBm
                                                        @endif
                                                    </div>
                                                @endforeach
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endforeach
            </div>
        @empty
            <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 px-4 py-8 text-center text-sm text-gray-500">Todas las ONU identificadas devolvieron potencia.</div>
        @endforelse
    </section>

    <section class="space-y-3">
        <div>
            <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Coinciden con más de una ONU · {{ count($ambiguas) }}</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400">El mismo usuario PPPoE está en la descripción de varias ONU que devolvieron potencia.</p>
        </div>
        @forelse($zonasAmbiguas as $nodo => $olts)
            <div class="space-y-3">
                <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $nodo }}</h3>
                @foreach($olts as $olt => $filas)
                    <div class="overflow-hidden rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800">
                        <div class="border-b border-gray-100 dark:border-gray-700 px-4 py-2 text-xs font-medium text-gray-500 dark:text-gray-400">
                            {{ $olt }} · {{ count($filas) }}
                        </div>
                        <table class="min-w-full text-sm">
                            <thead class="bg-gray-50 dark:bg-gray-900/40 text-left text-gray-500 dark:text-gray-400">
                                <tr>
                                    <th class="px-4 py-3 font-medium">Cliente</th>
                                    <th class="px-4 py-3 font-medium">PPPoE</th>
                                    <th class="px-4 py-3 font-medium">ONUs</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                @foreach($filas as $fila)
                                    <tr>
                                        <td class="px-4 py-3 align-top">
                                            @include('sistema.senal-lecturas._cliente', ['fila' => $fila])
                                        </td>
                                        <td class="px-4 py-3 align-top text-gray-700 dark:text-gray-200">{{ $fila['pppoe'] }}</td>
                                        <td class="px-4 py-3 text-gray-700 dark:text-gray-200">
                                            @foreach($fila['onus'] as $onu)
                                                <div class="{{ $loop->first ? '' : 'mt-2' }}">
                                                    {{ $onu['olt'] }} · PON {{ $onu['pon'] }}:{{ $onu['indice'] }}
                                                    @if($onu['rx'])
                                                        · {{ $onu['rx'] }} dBm
                                                    @endif
                                                    <div class="text-xs text-gray-400">{{ $onu['descripcion'] }} · {{ $onu['estado'] }}</div>
                                                </div>
                                            @endforeach
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endforeach
            </div>
        @empty
            <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 px-4 py-8 text-center text-sm text-gray-500">Ningún usuario coincide con más de una ONU.</div>
        @endforelse
    </section>
</div>
@endsection
