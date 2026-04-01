@extends('layouts.app')

@section('title', 'Generar factura interna desde servicios')

@section('content')
<div class="max-w-3xl mx-auto">
    <div class="mb-6">
        <a href="{{ route('servicios.index') }}" class="text-purple-600 dark:text-purple-400 hover:text-purple-700 dark:hover:text-purple-300 text-sm font-medium">&larr; Volver a servicios</a>
        <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100 mt-1">Generar factura interna</h1>
        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Se generará una factura interna por cada cliente con los servicios seleccionados (solo servicios activos).</p>
    </div>

    @if(session('error'))
        <div class="mb-4 p-4 rounded-lg bg-red-100 dark:bg-red-900/30 text-red-800 dark:text-red-200 border border-red-200 dark:border-red-800 text-sm">{{ session('error') }}</div>
    @endif

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 overflow-hidden mb-6">
        <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-700/50">
            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Servicios seleccionados ({{ $servicios->count() }})</span>
        </div>
        <div class="overflow-x-auto max-h-48">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-600 text-sm">
                <thead class="bg-gray-50 dark:bg-gray-700/50">
                    <tr>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Cliente</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase"># Serv.</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Plan</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Monto</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Estado</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-600 bg-white dark:bg-gray-800">
                    @foreach($servicios as $s)
                        @php
                            $prorrateo = $prorrateosPorServicio[$s->servicio_id] ?? null;
                            $precioPlan = $s->plan ? (float) $s->plan->precio : 0;
                            $monto = $prorrateo ? $prorrateo['precio_prorrateado'] : $precioPlan;
                        @endphp
                        <tr>
                            <td class="px-4 py-2 text-gray-900 dark:text-gray-100">{{ $s->cliente->nombre ?? '' }} {{ $s->cliente->apellido ?? '' }}</td>
                            <td class="px-4 py-2 text-gray-900 dark:text-gray-100">{{ $s->servicio_id }}</td>
                            <td class="px-4 py-2 text-gray-900 dark:text-gray-100">{{ $s->plan->nombre ?? '—' }}</td>
                            <td class="px-4 py-2 text-gray-900 dark:text-gray-100">
                                {{ number_format($monto, 0, ',', '.') }} Gs.
                                @if($prorrateo)
                                    <span class="block text-xs text-amber-600 dark:text-amber-400" title="Instalado el {{ $prorrateo['fecha_instalacion'] }} — {{ $prorrateo['dias_restantes'] }}/{{ $prorrateo['dias_en_mes'] }} días">(prorrateado)</span>
                                @endif
                            </td>
                            <td class="px-4 py-2">
                                @if(($s->estado ?? '') === 'A')
                                    <span class="text-green-600 dark:text-green-400">Activo</span>
                                @else
                                    <span class="text-amber-600 dark:text-amber-400">No activo (no se incluirá)</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 p-6">
        <form action="{{ route('facturas.store-generar-interna-desde-servicios') }}" method="POST">
            @csrf

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="periodo_desde" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Período desde *</label>
                    <input type="date" name="periodo_desde" id="periodo_desde" value="{{ old('periodo_desde', $periodoDesde) }}" required
                           class="mt-1 w-full px-3 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                    @error('periodo_desde')<p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="periodo_hasta" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Período hasta *</label>
                    <input type="date" name="periodo_hasta" id="periodo_hasta" value="{{ old('periodo_hasta', $periodoHasta) }}" required
                           class="mt-1 w-full px-3 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                    @error('periodo_hasta')<p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
                </div>
            </div>

            <div class="mt-6 flex gap-3">
                <button type="submit" class="px-4 py-2 bg-purple-600 text-white rounded-lg font-medium hover:bg-purple-700 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800">
                    Generar factura(s) interna(s)
                </button>
                <a href="{{ route('servicios.index') }}" class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-600">Cancelar</a>
            </div>
        </form>
    </div>
</div>
@endsection
