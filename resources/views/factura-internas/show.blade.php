@extends('layouts.app')

@section('title', 'Factura interna #' . $factura_interna->id)

@section('content')
<div class="max-w-3xl mx-auto pb-10">
    @if(session('success'))
        <div class="mb-4 p-4 rounded-lg bg-green-100 dark:bg-green-900/20 text-green-800 dark:text-green-200 border border-green-200 dark:border-green-800 text-sm">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="mb-4 p-4 rounded-lg bg-red-100 dark:bg-red-900/20 text-red-800 dark:text-red-200 border border-red-200 dark:border-red-800 text-sm">{{ session('error') }}</div>
    @endif
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
            @if($factura_interna->saldo_pendiente > 0 && in_array($factura_interna->estado, ['pendiente', 'emitida'], true) && auth()->user()?->tienePermiso('factura-interna.crear'))
                <button type="button" id="btn-nota-credito" class="inline-flex items-center px-4 py-2 bg-sky-600 text-white rounded-lg font-medium hover:bg-sky-700">Nota de crédito</button>
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
                        @if((float) $factura_interna->monto_notas_credito > 0)
                            <p class="text-sm text-sky-700">Notas de crédito: <span class="font-medium">−{{ number_format($factura_interna->monto_notas_credito, 0, ',', '.') }} {{ $factura_interna->moneda }}</span></p>
                        @endif
                        <p class="text-sm {{ $factura_interna->esta_pagada ? 'text-green-700' : 'text-amber-700' }}">Saldo: <span class="font-medium">{{ number_format($factura_interna->saldo_pendiente, 0, ',', '.') }} {{ $factura_interna->moneda }}</span> @if($factura_interna->esta_pagada) <span class="text-green-600">(Pagada)</span> @endif</p>
                    </div>
                </div>
                @if($factura_interna->notasCredito->isNotEmpty())
                    <div class="mt-6 pt-6 border-t border-gray-100">
                        <div class="flex flex-wrap items-center justify-between gap-2 mb-2">
                            <p class="text-sm font-medium text-gray-700">Notas de crédito</p>
                            @if(auth()->user()?->tienePermiso('factura-interna.ver'))
                                <a href="{{ route('factura-internas.notas-credito.index') }}" class="text-xs text-sky-600 hover:text-sky-800 dark:text-sky-400 dark:hover:text-sky-300">Ver todas</a>
                            @endif
                        </div>
                        <ul class="space-y-1">
                            @foreach($factura_interna->notasCredito as $nc)
                                <li class="text-sm text-gray-800">
                                    {{ $nc->created_at->timezone(config('app.timezone'))->format('d/m/Y H:i') }}
                                    · −{{ number_format($nc->monto, 0, ',', '.') }} {{ $factura_interna->moneda }}
                                    @if($nc->motivo)<span class="text-gray-500"> — {{ $nc->motivo }}</span>@endif
                                    @if($nc->usuario)<span class="text-gray-400 text-xs"> ({{ $nc->usuario->name ?? $nc->usuario->email }})</span>@endif
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
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

@if($factura_interna->saldo_pendiente > 0 && in_array($factura_interna->estado, ['pendiente', 'emitida'], true) && auth()->user()?->tienePermiso('factura-interna.crear'))
<div id="modal-nota-credito" class="fixed inset-0 z-50 hidden overflow-y-auto" aria-modal="true" role="dialog">
    <div class="flex min-h-full items-center justify-center p-4">
        <div class="fixed inset-0 bg-black/40" data-dismiss-nc></div>
        <div class="relative bg-white dark:bg-gray-800 rounded-xl shadow-xl max-w-md w-full p-6 border border-gray-200 dark:border-gray-700">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-1">Emitir nota de crédito</h2>
            <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">Reduce el saldo pendiente de la factura #{{ $factura_interna->id }}. Saldo actual: <strong>{{ number_format($factura_interna->saldo_pendiente, 0, ',', '.') }} {{ $factura_interna->moneda }}</strong>.</p>
            <form method="POST" action="{{ route('factura-internas.nota-credito', $factura_interna) }}" id="form-nota-credito">
                @csrf
                <div class="space-y-4">
                    <div>
                        <label for="nc_monto" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Monto ({{ $factura_interna->moneda }})</label>
                        <input type="number" name="monto" id="nc_monto" min="1" step="1" required
                            value="{{ old('monto', (int) round($factura_interna->saldo_pendiente)) }}"
                            class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100" />
                        @error('monto')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="nc_motivo" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Motivo (opcional)</label>
                        <textarea name="motivo" id="nc_motivo" rows="2" maxlength="500" class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100" placeholder="Ej. ajuste, devolución, error de facturación">{{ old('motivo') }}</textarea>
                    </div>
                </div>
                <div class="mt-6 flex justify-end gap-2">
                    <button type="button" class="px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700" data-dismiss-nc>Cancelar</button>
                    <button type="submit" class="px-4 py-2 rounded-lg bg-sky-600 text-white font-medium hover:bg-sky-700">Emitir nota de crédito</button>
                </div>
            </form>
        </div>
    </div>
</div>
@push('scripts')
<script>
(function () {
    var modal = document.getElementById('modal-nota-credito');
    var btn = document.getElementById('btn-nota-credito');
    if (!modal || !btn) return;
    function open() { modal.classList.remove('hidden'); }
    function close() { modal.classList.add('hidden'); }
    btn.addEventListener('click', open);
    modal.querySelectorAll('[data-dismiss-nc]').forEach(function (el) {
        el.addEventListener('click', close);
    });
    @if($errors->has('monto'))
    open();
    @endif
})();
</script>
@endpush
@endif
@endsection
