@extends('layouts.app')

@section('title', 'Factura fraccionada por deuda')

@section('content')
<div class="max-w-3xl mx-auto">
    <div class="mb-6">
        <a href="{{ route('servicios.index') }}" class="text-purple-600 dark:text-purple-400 hover:text-purple-700 dark:hover:text-purple-300 text-sm font-medium">&larr; Volver a servicios</a>
        <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100 mt-1">Factura interna fraccionada por deuda</h1>
        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
            Cliente: {{ $servicio->cliente->nombre ?? '' }} {{ $servicio->cliente->apellido ?? '' }}
            — Plan: {{ $servicio->plan->nombre ?? '—' }}
            — Servicio #{{ $servicio->servicio_id }}
        </p>
    </div>

    @if(session('error'))
        <div class="mb-4 p-4 rounded-lg bg-red-100 dark:bg-red-900/30 text-red-800 dark:text-red-200 border border-red-200 dark:border-red-800 text-sm">{{ session('error') }}</div>
    @endif

    <div class="mb-6 p-4 rounded-xl bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 text-sm text-amber-900 dark:text-amber-200">
        <p class="font-semibold">Saldo pendiente en facturas internas (este cliente)</p>
        <p class="mt-1 text-lg font-bold tabular-nums">{{ number_format($saldoPendiente, 0, ',', '.') }} Gs.</p>
        <p class="mt-2 text-amber-800 dark:text-amber-300">Indique un monto igual o menor a ese saldo. Se generará una factura con un solo ítem exento por ese monto (fracción de la deuda).</p>
    </div>

    <form action="{{ route('facturas.store-interna-servicio-fraccion-deuda', $servicio) }}" method="POST" class="space-y-6">
        @csrf

        <div class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-700/50">
                <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-100">Monto y descripción</h2>
            </div>
            <div class="p-6 space-y-5">
                <div class="min-w-0">
                    <label for="monto_fraccion" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Monto a facturar (Gs.) *</label>
                    <input type="number" name="monto_fraccion" id="monto_fraccion" value="{{ old('monto_fraccion') }}" required min="1" max="{{ $saldoPendiente }}" step="1"
                           class="mt-1 block w-full max-w-xs px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Máximo: {{ number_format($saldoPendiente, 0, ',', '.') }} Gs.</p>
                    @error('monto_fraccion')<p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
                </div>
                <div class="min-w-0">
                    <label for="descripcion_linea" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Texto en la línea de la factura (opcional)</label>
                    <input type="text" name="descripcion_linea" id="descripcion_linea" value="{{ old('descripcion_linea', $descripcionLineaDefault) }}" maxlength="500"
                           class="mt-1 block w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                    @error('descripcion_linea')<p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
                </div>
            </div>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-700/50">
                <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-100">Datos de la factura</h2>
            </div>
            <div class="p-6">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-5">
                    <div class="min-w-0">
                        <label for="fecha_emision" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Fecha de emisión *</label>
                        <input type="date" name="fecha_emision" id="fecha_emision" value="{{ old('fecha_emision', $fechaEmision) }}" required
                               class="mt-1 block w-full min-w-0 px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                        @error('fecha_emision')<p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
                    </div>
                    <div class="min-w-0">
                        <label for="fecha_vencimiento" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Fecha de vencimiento *</label>
                        <input type="date" name="fecha_vencimiento" id="fecha_vencimiento" value="{{ old('fecha_vencimiento', $fechaVencimiento) }}" required
                               class="mt-1 block w-full min-w-0 px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                        @error('fecha_vencimiento')<p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
                    </div>
                    <div class="min-w-0 flex flex-col sm:col-span-2">
                        <label for="fecha_pago" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Fecha de pago (opcional)</label>
                        <span class="block text-xs text-gray-500 dark:text-gray-400 mt-0.5">Referencia para cobros.</span>
                        <input type="date" name="fecha_pago" id="fecha_pago" value="{{ old('fecha_pago', $fechaPago) }}"
                               class="mt-1 block w-full max-w-xs px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                        @error('fecha_pago')<p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
                    </div>
                    <div class="min-w-0">
                        <label for="periodo_desde" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Período desde *</label>
                        <input type="date" name="periodo_desde" id="periodo_desde" value="{{ old('periodo_desde', $periodoDesde) }}" required
                               class="mt-1 block w-full min-w-0 px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                        @error('periodo_desde')<p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
                    </div>
                    <div class="min-w-0">
                        <label for="periodo_hasta" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Período hasta *</label>
                        <input type="date" name="periodo_hasta" id="periodo_hasta" value="{{ old('periodo_hasta', $periodoHasta) }}" required
                               class="mt-1 block w-full min-w-0 px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                        @error('periodo_hasta')<p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
                    </div>
                </div>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-3">
            <button type="submit" class="inline-flex items-center px-5 py-2.5 rounded-lg bg-purple-600 text-white font-medium hover:bg-purple-700 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:ring-offset-2 dark:focus:ring-offset-gray-900">
                Crear factura fraccionada
            </button>
            <a href="{{ route('servicios.index') }}" class="text-sm text-gray-600 dark:text-gray-400 hover:underline">Cancelar</a>
        </div>
    </form>
</div>
@endsection
