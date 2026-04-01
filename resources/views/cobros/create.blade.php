@extends('layouts.app')

@section('title', 'Registrar cobro')

@section('content')
<div class="max-w-2xl mx-auto">
    <div class="mb-6">
        <a href="{{ route('cobros.servicios') }}" class="text-green-600 dark:text-green-400 hover:text-green-700 dark:hover:text-green-300 text-sm font-medium">&larr; Volver a cobros</a>
        <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100 mt-1">Registrar cobro</h1>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 p-6">
        <form action="{{ route('cobros.store') }}" method="POST" id="form-cobro">
            @csrf

            <div class="space-y-4">
                <div>
                    <label for="cliente_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Cliente *</label>
                    <select name="cliente_id" id="cliente_id" required
                            class="mt-1 w-full px-3 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-green-500 focus:ring-2 focus:ring-green-500/20 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100"
                            onchange="window.location.href='{{ route('cobros.create') }}?cliente_id='+this.value">
                        <option value="">Seleccione cliente</option>
                        @foreach ($clientes as $c)
                            <option value="{{ $c->cliente_id }}" {{ (old('cliente_id', $cliente?->cliente_id) == $c->cliente_id) ? 'selected' : '' }}>{{ $c->nombre }} {{ $c->apellido }} ({{ $c->cedula }})</option>
                        @endforeach
                    </select>
                    @error('cliente_id')<p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
                </div>

                @if($facturasInternasPendientes->isNotEmpty())
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Facturas pendientes (seleccione las que desea cobrar)</label>
                    <div class="mt-1 rounded-lg border border-gray-300 dark:border-gray-600 overflow-hidden bg-white dark:bg-gray-700">
                        <div class="max-h-48 overflow-y-auto divide-y divide-gray-200 dark:divide-gray-600">
                            @foreach ($facturasInternasPendientes as $f)
                                @php
                                    $fId = data_get($f, 'id');
                                    $fSaldo = (float) (data_get($f, 'saldo_pendiente') ?? 0);
                                    $fConcepto = (string) (data_get($f, 'concepto') ?? '');
                                    $fPeriodoDesde = data_get($f, 'periodo_desde');
                                    $fPeriodoHasta = data_get($f, 'periodo_hasta');
                                @endphp
                                <label class="flex items-center gap-3 px-3 py-2.5 hover:bg-gray-50 dark:hover:bg-gray-600/50 cursor-pointer js-factura-row">
                                    <input type="checkbox" name="factura_interna_ids[]" value="{{ $fId }}"
                                           class="js-factura-cb rounded border-gray-300 dark:border-gray-600 text-green-600 focus:ring-green-500"
                                           data-saldo="{{ number_format($fSaldo, 2, '.', '') }}"
                                           data-concepto="{{ e($fConcepto) }}"
                                           {{ in_array($fId, old('factura_interna_ids', $facturaInternaIdsPreseleccionados ?? [])) ? 'checked' : '' }}>
                                    <span class="flex-1 text-sm text-gray-900 dark:text-gray-100">
                                        Interna #{{ $fId }} · {{ $fPeriodoDesde?->format('d/m/Y') }} - {{ $fPeriodoHasta?->format('d/m/Y') }} · Saldo: {{ number_format($fSaldo, 0, ',', '.') }} PYG
                                    </span>
                                </label>
                            @endforeach
                        </div>
                        <div class="px-3 py-2 border-t border-gray-200 dark:border-gray-600 bg-amber-50 dark:bg-amber-900/20 text-right">
                            <span class="text-sm font-semibold text-gray-800 dark:text-gray-200">Total seleccionado: <span id="total-seleccionado">0</span> PYG</span>
                        </div>
                    </div>
                </div>
                @endif

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label for="monto" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Monto (PYG) *</label>
                        <input type="number" name="monto" id="monto" step="0.01" min="0.01" value="{{ old('monto', $montoSugerido ?? '') }}" required placeholder="Se rellena con el saldo al elegir factura"
                               class="mt-1 w-full px-3 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-green-500 focus:ring-2 focus:ring-green-500/20 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-500">
                        @error('monto')<p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="fecha_pago" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Fecha y hora de pago *</label>
                        <input type="datetime-local" name="fecha_pago" id="fecha_pago" value="{{ old('fecha_pago', now()->format('Y-m-d\TH:i')) }}" required step="60"
                               class="mt-1 w-full px-3 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-green-500 focus:ring-2 focus:ring-green-500/20 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                        @error('fecha_pago')<p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
                    </div>
                </div>

                <div>

                    <div>
                        <label for="forma_pago" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Forma de pago *</label>
                        <select name="forma_pago" id="forma_pago" required class="mt-1 w-full px-3 py-2.5 rounded-lg border border-gray-300 dark:border-gray-700 shadow-sm focus:border-green-500 focus:ring-2 focus:ring-green-500/20 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                            @foreach ($formasPago as $key => $label)
                                <option value="{{ $key }}" {{ old('forma_pago', 'efectivo') == $key ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div>
                    <label for="referencia" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Referencia</label>
                    <input type="text" name="referencia" id="referencia" value="{{ old('referencia') }}" maxlength="100" placeholder="Nº cheque, ref. transferencia"
                           class="mt-1 w-full px-3 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-green-500 focus:ring-2 focus:ring-green-500/20 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-500">
                </div>

                <div>
                    <label for="concepto" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Concepto</label>
                    <input type="text" name="concepto" id="concepto" value="{{ old('concepto', $conceptoSugerido ?? 'Mensualidad') }}" maxlength="500" placeholder="Mensualidad, reconexión, descripción de factura..."
                           class="mt-1 w-full px-3 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-green-500 focus:ring-2 focus:ring-green-500/20 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-500">
                    @error('concepto')<p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="observaciones" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Observaciones</label>
                    <textarea name="observaciones" id="observaciones" rows="2" class="mt-1 w-full px-3 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-green-500 focus:ring-2 focus:ring-green-500/20 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">{{ old('observaciones') }}</textarea>
                </div>
            </div>

            <div class="mt-6 flex gap-3">
                <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded-lg font-medium hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800">
                    Registrar cobro
                </button>
                <a href="{{ route('cobros.servicios') }}" class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-600">Cancelar</a>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    var montoInput = document.getElementById('monto');
    var conceptoInput = document.getElementById('concepto');
    var totalEl = document.getElementById('total-seleccionado');
    var checkboxes = document.querySelectorAll('.js-factura-cb');

    function actualizarDesdeFacturas() {
        var total = 0;
        var conceptos = [];
        checkboxes.forEach(function(cb) {
            if (cb.checked) {
                var saldo = parseFloat(cb.dataset.saldo || 0);
                if (!isNaN(saldo)) total += saldo;
                var c = (cb.dataset.concepto || '').trim();
                if (c) conceptos.push(c);
            }
        });
        if (totalEl) totalEl.textContent = total.toLocaleString('es-PY', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
        if (montoInput) montoInput.value = total > 0 ? total.toFixed(2) : '';
        if (conceptoInput) conceptoInput.value = conceptos.join(' | ').substring(0, 500);
    }

    checkboxes.forEach(function(cb) {
        cb.addEventListener('change', actualizarDesdeFacturas);
    });
    actualizarDesdeFacturas();
});
</script>
@endpush
@endsection
