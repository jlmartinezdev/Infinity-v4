@extends('layouts.app')

@section('title', 'Factura #' . $factura->id)

@section('content')
<div class="max-w-4xl mx-auto">
    <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">Factura {{ $factura->numero_completo ?? '#' . $factura->id }}</h1>
        <div class="flex gap-2">
            @if($factura->estado === 'borrador')
                <a href="{{ route('facturas.edit', $factura) }}" class="inline-flex items-center px-4 py-2 bg-purple-600 text-white rounded-lg font-medium hover:bg-purple-700">Editar</a>
            @endif
            <a href="{{ route('facturas.index') }}" class="inline-flex items-center px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-gray-200 rounded-lg font-medium hover:bg-gray-300 dark:hover:bg-gray-600">Volver</a>
        </div>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 overflow-hidden">
        <div class="p-6 border-b border-gray-200 dark:border-gray-600">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Cliente</p>
                    <p class="font-medium text-gray-900 dark:text-gray-100">{{ $factura->cliente->nombre }} {{ $factura->cliente->apellido }}</p>
                    <p class="text-sm text-gray-600 dark:text-gray-300">{{ $factura->cliente->cedula }}</p>
                    @if($factura->cliente->direccion)
                        <p class="text-sm text-gray-600 dark:text-gray-300 mt-1">{{ $factura->cliente->direccion }}</p>
                    @endif
                </div>
                <div class="text-right">
                    <p class="text-sm text-gray-500 dark:text-gray-400">Fecha emisión</p>
                    <p class="font-medium text-gray-900 dark:text-gray-100">{{ $factura->fecha_emision->format('d/m/Y') }}</p>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-2">Tipo</p>
                    <p class="text-gray-900 dark:text-gray-100">{{ App\Models\Factura::tiposDocumento()[$factura->tipo_documento] ?? $factura->tipo_documento }}</p>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-2">Estado</p>
                    <span class="inline-flex px-2 py-0.5 rounded text-xs font-medium
                        @if($factura->estado === 'emitida') bg-green-100 dark:bg-green-900/30 text-green-800 dark:text-green-300
                        @elseif($factura->estado === 'anulada') bg-red-100 dark:bg-red-900/30 text-red-800 dark:text-red-300
                        @else bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-300 @endif">
                        {{ App\Models\Factura::estados()[$factura->estado] ?? $factura->estado }}
                    </span>
                </div>
            </div>
            @if($factura->numero_timbrado)
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-4">Timbrado: {{ $factura->numero_timbrado }} · Vigencia: {{ $factura->timbrado_vigencia_desde?->format('d/m/Y') }} - {{ $factura->timbrado_vigencia_hasta?->format('d/m/Y') }}</p>
            @endif
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-600">
                <thead class="bg-gray-50 dark:bg-gray-700/50">
                    <tr>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Descripción</th>
                        <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Cant.</th>
                        <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">P. unit.</th>
                        <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Subtotal</th>
                        <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Impuesto</th>
                        <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Total</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-600 bg-white dark:bg-gray-800">
                    @foreach ($factura->detalles as $d)
                        <tr>
                            <td class="px-4 py-2 text-sm text-gray-900 dark:text-gray-100">{{ $d->descripcion }}</td>
                            <td class="px-4 py-2 text-sm text-right text-gray-900 dark:text-gray-100">{{ number_format($d->cantidad, 2, ',', '.') }}</td>
                            <td class="px-4 py-2 text-sm text-right text-gray-900 dark:text-gray-100">{{ number_format($d->precio_unitario, 0, ',', '.') }}</td>
                            <td class="px-4 py-2 text-sm text-right text-gray-900 dark:text-gray-100">{{ number_format($d->subtotal, 0, ',', '.') }}</td>
                            <td class="px-4 py-2 text-sm text-right text-gray-900 dark:text-gray-100">{{ number_format($d->monto_impuesto, 0, ',', '.') }} @if($d->porcentaje_impuesto)({{ $d->porcentaje_impuesto }}%)@endif</td>
                            <td class="px-4 py-2 text-sm text-right font-medium text-gray-900 dark:text-gray-100">{{ number_format($d->total, 0, ',', '.') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="p-6 border-t border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-700/50">
            <div class="flex justify-end">
                <div class="text-right space-y-1">
                    <p class="text-sm text-gray-600 dark:text-gray-300">Subtotal: <span class="font-medium text-gray-900 dark:text-gray-100">{{ number_format($factura->subtotal, 0, ',', '.') }} {{ $factura->moneda }}</span></p>
                    <p class="text-sm text-gray-600 dark:text-gray-300">Impuestos: <span class="font-medium text-gray-900 dark:text-gray-100">{{ number_format($factura->total_impuestos, 0, ',', '.') }} {{ $factura->moneda }}</span></p>
                    <p class="text-lg font-bold text-gray-900 dark:text-gray-100">Total: {{ number_format($factura->total, 0, ',', '.') }} {{ $factura->moneda }}</p>
                    @if($factura->estado === 'emitida' && $factura->total > 0)
                        <p class="text-sm text-green-600 dark:text-green-400 mt-2">Cobrado: <span class="font-medium">{{ number_format($factura->monto_pagado, 0, ',', '.') }} {{ $factura->moneda }}</span></p>
                        <p class="text-sm {{ $factura->esta_pagada ? 'text-green-700 dark:text-green-400' : 'text-amber-700 dark:text-amber-400' }}">Saldo: <span class="font-medium">{{ number_format($factura->saldo_pendiente, 0, ',', '.') }} {{ $factura->moneda }}</span> @if($factura->esta_pagada) <span class="text-green-600 dark:text-green-400">(Pagada)</span> @endif</p>
                    @endif
                </div>
            </div>
            @if($factura->cobros->isNotEmpty())
                <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-600">
                    <p class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Cobros aplicados</p>
                    <ul class="space-y-1">
                        @foreach($factura->cobros as $cobro)
                            <li class="text-sm text-gray-900 dark:text-gray-100">
                                <a href="{{ route('cobros.show', $cobro) }}" class="text-green-600 dark:text-green-400 hover:underline">{{ $cobro->numero_recibo }}</a>
                                {{ $cobro->fecha_pago->format('d/m/Y H:i') }} · {{ number_format($cobro->monto, 0, ',', '.') }} {{ $factura->moneda }}
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
            @if($factura->observaciones)
                <p class="mt-4 text-sm text-gray-600 dark:text-gray-300"><span class="font-medium">Observaciones:</span> {{ $factura->observaciones }}</p>
            @endif
            @if($factura->set_cdc)
                <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">CDC (factura electrónica): {{ $factura->set_cdc }}</p>
            @endif
        </div>
    </div>
</div>
@endsection
