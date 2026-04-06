@extends('layouts.app')

@section('title', 'Factura interna #' . $factura_interna->id)

@section('content')
<div class="max-w-3xl mx-auto pb-10">
    <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">Factura interna #{{ $factura_interna->id }}</h1>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('factura-internas.pdf', $factura_interna) }}" class="inline-flex items-center gap-2 px-4 py-2 bg-red-600 text-white rounded-lg font-medium hover:bg-red-700 shadow-sm" title="Descargar PDF">
                <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                Descargar PDF
            </a>
            @if(!$factura_interna->esta_pagada && auth()->user()?->tienePermiso('cobros.crear'))
                <a href="{{ route('cobros.create', ['cliente_id' => $factura_interna->cliente_id, 'factura_interna_id' => $factura_interna->id]) }}" class="inline-flex items-center px-4 py-2 bg-green-600 text-white rounded-lg font-medium hover:bg-green-700">Registrar cobro</a>
            @endif
            @if(auth()->user()?->tienePermiso('factura-interna.crear'))
                <a href="{{ route('factura-internas.edit', $factura_interna) }}" class="inline-flex items-center px-4 py-2 bg-amber-500 text-white rounded-lg font-medium hover:bg-amber-600 dark:focus:ring-2 dark:focus:ring-amber-400 dark:focus:ring-offset-2 dark:focus:ring-offset-gray-900">Editar</a>
            @endif
            <a href="{{ route('factura-internas.index') }}" class="inline-flex items-center px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-gray-200 rounded-lg font-medium hover:bg-gray-300 dark:hover:bg-gray-600">Volver</a>
        </div>
    </div>

    {{-- Hoja tipo documento (papel en blanco) --}}
    <div class="rounded-xl shadow-xl border border-gray-200 dark:border-gray-600 bg-gray-100 dark:bg-gray-900/50 p-4 sm:p-6 md:p-8">
        <article class="bg-white text-gray-900 rounded-lg shadow-md border border-gray-100 max-w-none mx-auto px-6 py-8 sm:px-10 sm:py-10 min-h-[60vh]">
            <header class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-6 pb-6 mb-6 border-b border-gray-200">
                <div class="flex flex-col sm:flex-row gap-4 sm:gap-6 items-start">
                    @if($ajustes && $ajustes->urlLogo())
                        <img src="{{ $ajustes->urlLogo() }}" alt="Logo" class="h-16 sm:h-20 w-auto object-contain max-w-[200px]">
                    @endif
                    <div>
                        <p class="text-lg font-bold text-gray-900">{{ $ajustes?->nombre_empresa ?? config('app.name', 'Empresa') }}</p>
                        @if($ajustes)
                            <div class="text-sm text-gray-600 mt-1 space-y-0.5">
                                @if($ajustes->direccion)<p>{{ $ajustes->direccion }}</p>@endif
                                <p>
                                    @if($ajustes->telefono)<span>Tel. {{ $ajustes->telefono }}</span>@endif
                                    @if($ajustes->email)<span class="ml-1">{{ $ajustes->email }}</span>@endif
                                </p>
                                @if($ajustes->sitio_web)<p class="text-gray-500">{{ $ajustes->sitio_web }}</p>@endif
                            </div>
                        @endif
                    </div>
                </div>
                <div class="text-left sm:text-right shrink-0">
                    <p class="text-xs font-semibold tracking-widest text-gray-500 uppercase">Factura interna</p>
                    <p class="text-2xl font-bold text-gray-900 mt-1">#{{ $factura_interna->id }}</p>
                    <dl class="mt-3 text-sm text-gray-600 space-y-1">
                        <div><dt class="inline text-gray-500">Emisión:</dt> <dd class="inline">{{ $factura_interna->fecha_emision->format('d/m/Y') }}</dd></div>
                        @if($factura_interna->fecha_vencimiento)
                            <div><dt class="inline text-gray-500">Vencimiento:</dt> <dd class="inline">{{ $factura_interna->fecha_vencimiento->format('d/m/Y') }}</dd></div>
                        @endif
                        <div><dt class="inline text-gray-500">Período:</dt> <dd class="inline">{{ $factura_interna->periodo_desde->format('d/m/Y') }} – {{ $factura_interna->periodo_hasta->format('d/m/Y') }}</dd></div>
                    </dl>
                </div>
            </header>

            <section class="mb-8">
                <h2 class="text-xs font-semibold text-gray-500 uppercase mb-2">Cliente</h2>
                <p class="font-medium text-gray-900">{{ $factura_interna->cliente->nombre }} {{ $factura_interna->cliente->apellido }}</p>
                <p class="text-sm text-gray-600">{{ $factura_interna->cliente->cedula }}</p>
                @if($factura_interna->fecha_pago)
                    <p class="text-sm text-gray-500 mt-2">Fecha de pago (referencia): {{ $factura_interna->fecha_pago->format('d/m/Y') }}</p>
                @endif
            </section>

            <div class="overflow-x-auto -mx-2">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Descripción</th>
                            <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Cant.</th>
                            <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">P. unit.</th>
                            <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Total</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 bg-white">
                        @foreach ($factura_interna->detalles as $d)
                            <tr>
                                <td class="px-3 py-2 text-gray-900">{{ $d->descripcion }}</td>
                                <td class="px-3 py-2 text-right text-gray-900">{{ number_format($d->cantidad, 2, ',', '.') }}</td>
                                <td class="px-3 py-2 text-right text-gray-900">{{ number_format($d->precio_unitario, 0, ',', '.') }}</td>
                                <td class="px-3 py-2 text-right font-medium text-gray-900">{{ number_format($d->total, 0, ',', '.') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="mt-8 pt-6 border-t border-gray-200">
                <div class="flex justify-end">
                    <div class="text-right space-y-1 min-w-[240px]">
                        <p class="text-sm text-gray-600">Subtotal: <span class="font-medium text-gray-900">{{ number_format($factura_interna->subtotal, 0, ',', '.') }} {{ $factura_interna->moneda }}</span></p>
                        <p class="text-sm text-gray-600">Impuestos: <span class="font-medium text-gray-900">{{ number_format($factura_interna->total_impuestos, 0, ',', '.') }} {{ $factura_interna->moneda }}</span></p>
                        @if((float) ($factura_interna->descuento ?? 0) > 0)
                            <p class="text-sm text-gray-600">Descuento: <span class="font-medium text-amber-700">−{{ number_format($factura_interna->descuento, 0, ',', '.') }} {{ $factura_interna->moneda }}</span></p>
                        @endif
                        <p class="text-lg font-bold text-gray-900 pt-2 border-t border-gray-100">Total: {{ number_format($factura_interna->total, 0, ',', '.') }} {{ $factura_interna->moneda }}</p>
                        <p class="text-sm text-green-600 mt-2">Cobrado: <span class="font-medium">{{ number_format($factura_interna->monto_pagado, 0, ',', '.') }} {{ $factura_interna->moneda }}</span></p>
                        <p class="text-sm {{ $factura_interna->esta_pagada ? 'text-green-700' : 'text-amber-700' }}">Saldo: <span class="font-medium">{{ number_format($factura_interna->saldo_pendiente, 0, ',', '.') }} {{ $factura_interna->moneda }}</span> @if($factura_interna->esta_pagada) <span class="text-green-600">(Pagada)</span> @endif</p>
                    </div>
                </div>
                @if($factura_interna->cobros->isNotEmpty())
                    <div class="mt-6 pt-6 border-t border-gray-100">
                        <p class="text-sm font-medium text-gray-700 mb-2">Cobros aplicados</p>
                        <ul class="space-y-1">
                            @foreach($factura_interna->cobros as $cobro)
                                <li class="text-sm text-gray-800">
                                    <a href="{{ route('cobros.show', $cobro) }}" class="text-green-600 hover:underline">{{ $cobro->numero_recibo }}</a>
                                    {{ $cobro->fecha_pago->format('d/m/Y H:i') }} · {{ number_format($cobro->pivot->monto ?? $cobro->monto, 0, ',', '.') }} {{ $factura_interna->moneda }}
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
                @if($factura_interna->observaciones)
                    <div class="mt-6 pt-6 border-t border-gray-100">
                        <p class="text-xs font-semibold text-gray-500 uppercase mb-1">Observaciones</p>
                        <p class="text-sm text-gray-700 whitespace-pre-wrap">{{ $factura_interna->observaciones }}</p>
                    </div>
                @endif
            </div>
        </article>
    </div>
</div>
@endsection
