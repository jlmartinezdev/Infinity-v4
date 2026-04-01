@extends('layouts.app')

@section('title', 'Factura interna #' . $factura_interna->id)

@section('content')
<div class="max-w-4xl mx-auto">
    <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">Factura interna #{{ $factura_interna->id }}</h1>
        <div class="flex gap-2">
            @if(!$factura_interna->esta_pagada && auth()->user()?->tienePermiso('cobros.crear'))
                <a href="{{ route('cobros.create', ['cliente_id' => $factura_interna->cliente_id, 'factura_interna_id' => $factura_interna->id]) }}" class="inline-flex items-center px-4 py-2 bg-green-600 text-white rounded-lg font-medium hover:bg-green-700">Registrar cobro</a>
            @endif
            @if(auth()->user()?->tienePermiso('factura-interna.crear'))
                <a href="{{ route('factura-internas.edit', $factura_interna) }}" class="inline-flex items-center px-4 py-2 bg-amber-500 text-white rounded-lg font-medium hover:bg-amber-600 dark:focus:ring-2 dark:focus:ring-amber-400 dark:focus:ring-offset-2 dark:focus:ring-offset-gray-900">Editar</a>
            @endif
            <a href="{{ route('factura-internas.index') }}" class="inline-flex items-center px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-gray-200 rounded-lg font-medium hover:bg-gray-300 dark:hover:bg-gray-600">Volver</a>
        </div>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 overflow-hidden">
        <div class="p-6 border-b border-gray-200 dark:border-gray-700">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Cliente</p>
                    <p class="font-medium text-gray-900 dark:text-gray-100">{{ $factura_interna->cliente->nombre }} {{ $factura_interna->cliente->apellido }}</p>
                    <p class="text-sm text-gray-600 dark:text-gray-400">{{ $factura_interna->cliente->cedula }}</p>
                </div>
                <div class="text-right">
                    <p class="text-sm text-gray-500 dark:text-gray-400">Período</p>
                    <p class="font-medium text-gray-900 dark:text-gray-100">{{ $factura_interna->periodo_desde->format('d/m/Y') }} - {{ $factura_interna->periodo_hasta->format('d/m/Y') }}</p>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-2">Fecha emisión</p>
                    <p class="text-gray-900 dark:text-gray-100">{{ $factura_interna->fecha_emision->format('d/m/Y') }}</p>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-2">Vencimiento</p>
                    <p class="text-gray-900 dark:text-gray-100">{{ $factura_interna->fecha_vencimiento?->format('d/m/Y') }}</p>
                    @if($factura_interna->fecha_pago)
                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-2">Fecha de pago (referencia)</p>
                        <p class="text-gray-900 dark:text-gray-100">{{ $factura_interna->fecha_pago->format('d/m/Y') }}</p>
                    @endif
                </div>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-700/50">
                    <tr>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Descripción</th>
                        <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Cant.</th>
                        <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">P. unit.</th>
                        <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Total</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700 bg-white dark:bg-gray-800">
                    @foreach ($factura_interna->detalles as $d)
                        <tr>
                            <td class="px-4 py-2 text-sm text-gray-900 dark:text-gray-100">{{ $d->descripcion }}</td>
                            <td class="px-4 py-2 text-sm text-right text-gray-900 dark:text-gray-100">{{ number_format($d->cantidad, 2, ',', '.') }}</td>
                            <td class="px-4 py-2 text-sm text-right text-gray-900 dark:text-gray-100">{{ number_format($d->precio_unitario, 0, ',', '.') }}</td>
                            <td class="px-4 py-2 text-sm text-right font-medium text-gray-900 dark:text-gray-100">{{ number_format($d->total, 0, ',', '.') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="p-6 border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-700/50">
            <div class="flex justify-end">
                <div class="text-right space-y-1">
                    <p class="text-sm text-gray-600 dark:text-gray-400">Subtotal: <span class="font-medium text-gray-900 dark:text-gray-100">{{ number_format($factura_interna->subtotal, 0, ',', '.') }} {{ $factura_interna->moneda }}</span></p>
                    <p class="text-sm text-gray-600 dark:text-gray-400">Impuestos: <span class="font-medium text-gray-900 dark:text-gray-100">{{ number_format($factura_interna->total_impuestos, 0, ',', '.') }} {{ $factura_interna->moneda }}</span></p>
                    @if((float) ($factura_interna->descuento ?? 0) > 0)
                        <p class="text-sm text-gray-600 dark:text-gray-400">Descuento: <span class="font-medium text-amber-700 dark:text-amber-400">−{{ number_format($factura_interna->descuento, 0, ',', '.') }} {{ $factura_interna->moneda }}</span></p>
                    @endif
                    <p class="text-lg font-bold text-gray-900 dark:text-gray-100">Total: {{ number_format($factura_interna->total, 0, ',', '.') }} {{ $factura_interna->moneda }}</p>
                    <p class="text-sm text-green-600 dark:text-green-400 mt-2">Cobrado: <span class="font-medium">{{ number_format($factura_interna->monto_pagado, 0, ',', '.') }} {{ $factura_interna->moneda }}</span></p>
                    <p class="text-sm {{ $factura_interna->esta_pagada ? 'text-green-700 dark:text-green-400' : 'text-amber-700 dark:text-amber-400' }}">Saldo: <span class="font-medium">{{ number_format($factura_interna->saldo_pendiente, 0, ',', '.') }} {{ $factura_interna->moneda }}</span> @if($factura_interna->esta_pagada) <span class="text-green-600 dark:text-green-400">(Pagada)</span> @endif</p>
                </div>
            </div>
            @if($factura_interna->cobros->isNotEmpty())
                <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-600">
                    <p class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Cobros aplicados</p>
                    <ul class="space-y-1">
                        @foreach($factura_interna->cobros as $cobro)
                            <li class="text-sm text-gray-900 dark:text-gray-100">
                                <a href="{{ route('cobros.show', $cobro) }}" class="text-green-600 dark:text-green-400 hover:underline">{{ $cobro->numero_recibo }}</a>
                                {{ $cobro->fecha_pago->format('d/m/Y H:i') }} · {{ number_format($cobro->pivot->monto ?? $cobro->monto, 0, ',', '.') }} {{ $factura_interna->moneda }}
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
