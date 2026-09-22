@extends('layouts.app')

@section('title', 'Registrar cobro')

@section('content')
<div class="max-w-2xl mx-auto pb-16">
    <div class="mb-6">
        <a href="{{ route('cobros.servicios') }}" class="text-green-600 dark:text-green-400 hover:text-green-700 dark:hover:text-green-300 text-sm font-medium">&larr; Volver a cobros</a>
        <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100 mt-1">Registrar cobro</h1>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 p-6">
        @if(! $cliente)
            <p class="text-sm text-gray-600 dark:text-gray-300">
                Para registrar un cobro, buscá el cliente en
                <a href="{{ route('cobros.servicios') }}" class="font-medium text-green-600 dark:text-green-400 hover:underline">Cobros</a>.
            </p>
        @else
        <form action="{{ route('cobros.store') }}" method="POST" id="form-cobro">
            @csrf
            <input type="hidden" name="cliente_id" id="cliente_id" value="{{ $cliente->cliente_id }}">

            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Cliente *</label>
                    <div class="mt-1 w-full px-3 py-2.5 rounded-lg border border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-900/40 text-gray-900 dark:text-gray-100">
                        <span class="font-medium">{{ trim($cliente->nombre.' '.$cliente->apellido) }}</span>
                        <span class="text-sm text-gray-500 dark:text-gray-400">({{ $cliente->cedula }})</span>
                    </div>
                </div>

                <div id="facturas-pendientes-wrap">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Facturas pendientes (seleccione las que desea cobrar)</label>
                    @php
                        $marcarTodas = count($facturaInternaIdsPreseleccionados ?? []) === 0;
                    @endphp
                    <div class="mt-1 rounded-lg border border-gray-300 dark:border-gray-600 overflow-hidden bg-white dark:bg-gray-700">
                        <div id="facturas-pendientes-list" class="max-h-48 overflow-y-auto divide-y divide-gray-200 dark:divide-gray-600">
                            @forelse($facturasPendientes as $f)
                                @php
                                    $checked = $marcarTodas || in_array((int) $f['id'], $facturaInternaIdsPreseleccionados, true);
                                    $periodo = implode(' - ', array_filter([$f['periodo_desde'] ?? null, $f['periodo_hasta'] ?? null]));
                                    $alias = trim((string) ($f['alias'] ?? ''));
                                    $saldo = number_format((float) $f['saldo_pendiente'], 0, ',', '.');
                                @endphp
                                <label class="flex items-center gap-3 px-3 py-2.5 hover:bg-gray-50 dark:hover:bg-gray-600/50 cursor-pointer js-factura-row">
                                    <input type="checkbox" name="factura_interna_ids[]" value="{{ $f['id'] }}"
                                        class="js-factura-cb rounded border-gray-300 dark:border-gray-600 text-green-600 focus:ring-green-500"
                                        data-saldo="{{ number_format((float) $f['saldo_pendiente'], 2, '.', '') }}"
                                        data-concepto="{{ $f['concepto'] }}"
                                        {{ $checked ? 'checked' : '' }}>
                                    <span class="flex-1 text-sm text-gray-900 dark:text-gray-100">
                                        Interna #{{ $f['id'] }}
                                        @if($alias !== '') · {{ $alias }} @endif
                                        @if($periodo !== '') · {{ $periodo }} @endif
                                        · Saldo: {{ $saldo }} PYG
                                    </span>
                                </label>
                            @empty
                                <p class="px-3 py-3 text-sm text-gray-500 dark:text-gray-400">No hay facturas pendientes.</p>
                            @endforelse
                        </div>
                        <div id="facturas-pendientes-total" class="{{ $facturasPendientes->isEmpty() ? 'hidden' : '' }} px-3 py-2 border-t border-gray-200 dark:border-gray-600 bg-amber-50 dark:bg-amber-900/20 text-right">
                            <span class="text-sm font-semibold text-gray-800 dark:text-gray-200">Total seleccionado: <span id="total-seleccionado">0</span> PYG</span>
                        </div>
                    </div>
                    @if($facturasPendientes->isEmpty())
                        <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">No hay facturas pendientes para este cliente.</p>
                    @endif
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label for="monto" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Monto (PYG) *</label>
                        <input type="number" name="monto" id="monto" step="0.01" min="0.01" value="{{ old('monto') }}" required placeholder="Se rellena con el saldo al elegir factura"
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
                    <label for="forma_pago" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Forma de pago *</label>
                    <select name="forma_pago" id="forma_pago" required class="mt-1 w-full px-3 py-2.5 rounded-lg border border-gray-300 dark:border-gray-700 shadow-sm focus:border-green-500 focus:ring-2 focus:ring-green-500/20 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                        @foreach ($formasPago as $key => $label)
                            <option value="{{ $key }}" {{ old('forma_pago', 'efectivo') == $key ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="referencia" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Referencia</label>
                    <input type="text" name="referencia" id="referencia" value="{{ old('referencia') }}" maxlength="100" placeholder="Nº cheque, ref. transferencia"
                           class="mt-1 w-full px-3 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-green-500 focus:ring-2 focus:ring-green-500/20 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-500">
                </div>

                <div>
                    <label for="concepto" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Concepto</label>
                    <input type="text" name="concepto" id="concepto" value="{{ old('concepto', 'Mensualidad') }}" maxlength="500" placeholder="Mensualidad, reconexión, descripción de factura..."
                           class="mt-1 w-full px-3 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-green-500 focus:ring-2 focus:ring-green-500/20 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-500">
                    @error('concepto')<p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="observaciones" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Observaciones</label>
                    <textarea name="observaciones" id="observaciones" rows="2" class="mt-1 w-full px-3 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-green-500 focus:ring-2 focus:ring-green-500/20 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">{{ old('observaciones') }}</textarea>
                </div>
            </div>

            <div class="mt-6 flex gap-3">
                <button type="submit" id="btn-registrar-cobro" class="px-4 py-2 bg-green-600 text-white rounded-lg font-medium hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 disabled:opacity-70 disabled:cursor-not-allowed">
                    Registrar cobro
                </button>
                <a href="{{ route('cobros.servicios') }}" class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-600">Cancelar</a>
            </div>
        </form>
        @endif
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    var formCobro = document.getElementById('form-cobro');
    var btnRegistrar = document.getElementById('btn-registrar-cobro');
    var montoInput = document.getElementById('monto');
    var conceptoInput = document.getElementById('concepto');
    var totalEl = document.getElementById('total-seleccionado');
    var conceptoYaCargado = {{ old('concepto') ? 'true' : 'false' }};
    var montoYaCargado = {{ old('monto') ? 'true' : 'false' }};

    function formatMonto(n) {
        return Number(n || 0).toLocaleString('es-PY', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
    }

    function checkboxes() {
        return document.querySelectorAll('.js-factura-cb');
    }

    function actualizarDesdeFacturas() {
        var total = 0;
        var conceptos = [];
        checkboxes().forEach(function(cb) {
            if (cb.checked) {
                var saldo = parseFloat(cb.dataset.saldo || 0);
                if (!isNaN(saldo)) total += saldo;
                var c = (cb.dataset.concepto || '').trim();
                if (c) conceptos.push(c);
            }
        });
        if (totalEl) totalEl.textContent = formatMonto(total);
        if (montoInput && !montoYaCargado) montoInput.value = total > 0 ? total.toFixed(2) : '';
        if (conceptoInput && !conceptoYaCargado) conceptoInput.value = conceptos.join(' | ').substring(0, 500);
    }

    checkboxes().forEach(function(cb) {
        cb.addEventListener('change', actualizarDesdeFacturas);
    });
    actualizarDesdeFacturas();

    if (formCobro && btnRegistrar) {
        formCobro.addEventListener('submit', function(e) {
            if (formCobro.dataset.submitting === '1') {
                e.preventDefault();
                return;
            }
            formCobro.dataset.submitting = '1';
            btnRegistrar.disabled = true;
            btnRegistrar.textContent = 'Procesando…';
        });
    }
});
</script>
@endpush
@endsection
