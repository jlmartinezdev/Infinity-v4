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
                    <div class="mt-1 rounded-lg border border-gray-300 dark:border-gray-600 overflow-hidden bg-white dark:bg-gray-700">
                        <div id="facturas-pendientes-list" class="max-h-48 overflow-y-auto divide-y divide-gray-200 dark:divide-gray-600">
                            <p class="px-3 py-3 text-sm text-gray-500 dark:text-gray-400">Consultando cuentas pendientes…</p>
                        </div>
                        <div id="facturas-pendientes-total" class="hidden px-3 py-2 border-t border-gray-200 dark:border-gray-600 bg-amber-50 dark:bg-amber-900/20 text-right">
                            <span class="text-sm font-semibold text-gray-800 dark:text-gray-200">Total seleccionado: <span id="total-seleccionado">0</span> PYG</span>
                        </div>
                    </div>
                    <p id="facturas-pendientes-vacio" class="hidden mt-2 text-sm text-gray-500 dark:text-gray-400">No hay facturas pendientes para este cliente.</p>
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

@if($cliente && $urlPendientes)
<div id="cobro-consulta-bar" class="fixed bottom-0 inset-x-0 z-[60] print:hidden" aria-live="polite">
    <div class="h-1.5 bg-gray-200 dark:bg-gray-700 overflow-hidden">
        <div class="cobro-bar-indeterminate h-full bg-green-500"></div>
    </div>
    <div class="bg-white/95 dark:bg-gray-800/95 border-t border-gray-200 dark:border-gray-700 px-4 py-2 text-sm text-gray-700 dark:text-gray-200">
        Consultando cuentas pendientes…
    </div>
</div>
<style>
@keyframes cobro-bar-slide {
    0% { transform: translateX(-100%); }
    100% { transform: translateX(350%); }
}
.cobro-bar-indeterminate {
    width: 32%;
    animation: cobro-bar-slide 1.15s ease-in-out infinite;
}
</style>
@endif

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    var formCobro = document.getElementById('form-cobro');
    var btnRegistrar = document.getElementById('btn-registrar-cobro');
    var montoInput = document.getElementById('monto');
    var conceptoInput = document.getElementById('concepto');
    var totalEl = document.getElementById('total-seleccionado');
    var listEl = document.getElementById('facturas-pendientes-list');
    var totalWrap = document.getElementById('facturas-pendientes-total');
    var vacioEl = document.getElementById('facturas-pendientes-vacio');
    var bar = document.getElementById('cobro-consulta-bar');
    var urlPendientes = @json($urlPendientes);
    var idsPref = @json($facturaInternaIdsPreseleccionados ?? []);
    var conceptoYaCargado = {{ old('concepto') ? 'true' : 'false' }};
    var montoYaCargado = {{ old('monto') ? 'true' : 'false' }};

    function csrf() {
        var m = document.querySelector('meta[name="csrf-token"]');
        return m ? m.getAttribute('content') : '';
    }

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

    function ocultarBarra() {
        if (bar) bar.classList.add('hidden');
    }

    function bindCheckboxes() {
        checkboxes().forEach(function(cb) {
            cb.addEventListener('change', actualizarDesdeFacturas);
        });
        actualizarDesdeFacturas();
    }

    function renderFacturas(facturas) {
        if (!listEl) return;
        listEl.innerHTML = '';
        if (!facturas.length) {
            if (totalWrap) totalWrap.classList.add('hidden');
            if (vacioEl) vacioEl.classList.remove('hidden');
            listEl.innerHTML = '<p class="px-3 py-3 text-sm text-gray-500 dark:text-gray-400">No hay facturas pendientes.</p>';
            return;
        }
        if (vacioEl) vacioEl.classList.add('hidden');
        if (totalWrap) totalWrap.classList.remove('hidden');

        var marcarTodas = idsPref.length === 0;
        facturas.forEach(function(f) {
            var checked = marcarTodas || idsPref.indexOf(Number(f.id)) !== -1;
            var label = document.createElement('label');
            label.className = 'flex items-center gap-3 px-3 py-2.5 hover:bg-gray-50 dark:hover:bg-gray-600/50 cursor-pointer js-factura-row';

            var cb = document.createElement('input');
            cb.type = 'checkbox';
            cb.name = 'factura_interna_ids[]';
            cb.value = String(f.id);
            cb.className = 'js-factura-cb rounded border-gray-300 dark:border-gray-600 text-green-600 focus:ring-green-500';
            cb.dataset.saldo = Number(f.saldo_pendiente || 0).toFixed(2);
            cb.dataset.concepto = f.concepto || '';
            cb.checked = checked;

            var span = document.createElement('span');
            span.className = 'flex-1 text-sm text-gray-900 dark:text-gray-100';
            var periodo = [f.periodo_desde, f.periodo_hasta].filter(Boolean).join(' - ');
            var alias = (f.alias || '').trim();
            span.textContent = 'Interna #' + f.id
                + (alias ? ' · ' + alias : '')
                + (periodo ? ' · ' + periodo : '')
                + ' · Saldo: ' + formatMonto(f.saldo_pendiente) + ' PYG';

            label.appendChild(cb);
            label.appendChild(span);
            listEl.appendChild(label);
        });
        bindCheckboxes();
    }

    if (formCobro && btnRegistrar) {
        formCobro.addEventListener('submit', function(e) {
            if (formCobro.dataset.loading === '1') {
                e.preventDefault();
                return;
            }
            if (formCobro.dataset.submitting === '1') {
                e.preventDefault();
                return;
            }
            formCobro.dataset.submitting = '1';
            btnRegistrar.disabled = true;
            btnRegistrar.textContent = 'Procesando…';
        });
    }

    if (!urlPendientes || !listEl) {
        return;
    }

    if (formCobro) formCobro.dataset.loading = '1';
    if (btnRegistrar) {
        btnRegistrar.disabled = true;
        btnRegistrar.textContent = 'Consultando…';
    }

    fetch(urlPendientes, {
        headers: {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': csrf()
        },
        credentials: 'same-origin'
    }).then(function(res) {
        return res.json().then(function(data) {
            return { ok: res.ok, data: data || {} };
        });
    }).then(function(result) {
        ocultarBarra();
        if (formCobro) delete formCobro.dataset.loading;
        if (btnRegistrar) {
            btnRegistrar.disabled = false;
            btnRegistrar.textContent = 'Registrar cobro';
        }
        if (!result.ok || result.data.success === false) {
            listEl.innerHTML = '<p class="px-3 py-3 text-sm text-red-600 dark:text-red-400">No se pudieron cargar las cuentas pendientes. Reintentá.</p>';
            return;
        }
        renderFacturas(result.data.facturas || []);
    }).catch(function() {
        ocultarBarra();
        if (formCobro) delete formCobro.dataset.loading;
        if (btnRegistrar) {
            btnRegistrar.disabled = false;
            btnRegistrar.textContent = 'Registrar cobro';
        }
        listEl.innerHTML = '<p class="px-3 py-3 text-sm text-red-600 dark:text-red-400">No se pudieron cargar las cuentas pendientes. Reintentá.</p>';
    });
});
</script>
@endpush
@endsection
