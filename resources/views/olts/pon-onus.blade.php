@extends('layouts.app')

@section('title', 'PON ' . $ponPort . ' — OLT ' . ($olt->codigo ?? $olt->ip ?? $olt->olt_id))

@section('content')
@include('olts._consulta_async')
<div class="max-w-5xl mx-auto">
    <div class="mb-6">
        <a href="{{ route('sistema.olts.show', ['olt' => $olt, 'sin_sync' => 1]) }}" class="text-sm font-medium text-purple-600 hover:text-purple-800 hover:underline dark:text-purple-400 dark:hover:text-purple-300">&larr; Volver al OLT</a>
        <h1 class="mt-2 text-2xl font-bold text-gray-900 dark:text-gray-100">
            PON 0/{{ $ponPort }}
            @if($ponPuerto)
                <span class="text-lg font-normal text-gray-500 dark:text-gray-400">({{ $ponPuerto->tipo_pon }})</span>
            @endif
        </h1>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
            OLT {{ $olt->codigo ?? $olt->ip ?? $olt->modelo ?? '#' . $olt->olt_id }}
            · {{ $onus->count() }} ONU(s)
            · Online: <span class="text-green-600 dark:text-green-400">{{ $onusOnline }}</span>
            · Offline/alarma: <span class="text-amber-600 dark:text-amber-400">{{ $onusOffline }}</span>
        </p>
    </div>

    <div class="mb-4 flex flex-wrap gap-2">
        @if($olt->tieneCredencialesGestion())
            <form action="{{ route('sistema.olts.refresh-onu-detalles-pon', [$olt, $ponPort]) }}" method="POST"
                  class="inline js-olt-consulta"
                  data-confirm="¿Consultar descripción y RX de las ONUs en PON 0/{{ $ponPort }}?"
                  data-loading="Consultando PON 0/{{ $ponPort }}…"
                  data-reload="{{ route('sistema.olts.pon-onus', [$olt, $ponPort]) }}">
                @csrf
                <button type="submit" class="inline-flex items-center rounded-lg bg-purple-600 px-4 py-2 text-sm font-medium text-white hover:bg-purple-700">
                    Consultar desde OLT
                </button>
            </form>
        @else
            <a href="{{ route('sistema.olts.edit', $olt) }}" class="inline-flex items-center rounded-lg bg-amber-600 px-4 py-2 text-sm font-medium text-white hover:bg-amber-700">
                Configurar acceso Telnet
            </a>
        @endif
    </div>

    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
        <div class="overflow-x-auto">
            @if($onus->isEmpty())
                <p class="p-6 text-sm text-gray-500 dark:text-gray-400">
                    No hay ONUs importadas en este PON. Usá «Importar ONUs» desde la ficha del OLT.
                </p>
            @else
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-900/40">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">ONU #</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Serial</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Modelo / Descripción</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Estado</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">RX (dBm)</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 bg-white dark:divide-gray-700 dark:bg-gray-800">
                        @foreach($onus as $onu)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/40">
                                <td class="px-4 py-3 text-sm text-gray-900 dark:text-gray-100">{{ $onu->onu_index }}</td>
                                <td class="px-4 py-3 text-sm font-mono text-gray-700 dark:text-gray-300">{{ $onu->serial ?? '—' }}</td>
                                <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-400">
                                    @if($onu->descripcion && $onu->modelo && strcasecmp((string) $onu->descripcion, (string) $onu->modelo) !== 0)
                                        <span class="block font-medium text-gray-900 dark:text-gray-100">{{ $onu->descripcion }}</span>
                                        <span class="text-xs text-gray-400 dark:text-gray-500">{{ $onu->modelo }}</span>
                                    @elseif($onu->descripcion)
                                        <span class="block font-medium text-gray-900 dark:text-gray-100">{{ $onu->descripcion }}</span>
                                    @else
                                        <span class="block">{{ $onu->modelo ?: '—' }}</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    @php $online = $onu->estadoEsOnline(); @endphp
                                    <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium {{ $online ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300' : 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300' }}">
                                        {{ $onu->estadoEtiqueta() }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-sm">
                                    @if($onu->rx_power_dbm !== null)
                                        @php
                                            $rx = (float) $onu->rx_power_dbm;
                                            $rxOk = $onu->rxEsOptimo();
                                            $rxClass = $rxOk === true
                                                ? 'text-green-600 dark:text-green-400'
                                                : ($rxOk === false ? 'text-amber-600 dark:text-amber-400' : 'text-gray-600 dark:text-gray-400');
                                        @endphp
                                        <span class="font-mono {{ $rxClass }}">{{ number_format($rx, 2) }}</span>
                                        <span class="text-xs text-gray-400"> dBm</span>
                                    @else
                                        <span class="text-gray-400">—</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>
</div>
@endsection
