@extends('layouts.app')

@section('title', 'Registrar multicobro')

@section('content')
<div class="max-w-3xl mx-auto">
    <div class="mb-6">
        <a href="{{ route('factura-internas.pendientes') }}" class="text-green-600 dark:text-green-400 hover:text-green-700 dark:hover:text-green-300 text-sm font-medium">&larr; Volver a pendiente de pago</a>
        <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100 mt-1">Registrar multicobro</h1>
        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">El monto total se repartirá entre las facturas de forma proporcional al saldo. Si hay clientes diferentes, se generará un cobro por cada cliente.</p>
    </div>

    @if(session('error'))
        <div class="mb-4 p-4 rounded-lg bg-red-100 dark:bg-red-900/30 text-red-800 dark:text-red-200 border border-red-200 dark:border-red-800 text-sm">{{ session('error') }}</div>
    @endif

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 overflow-hidden mb-6">
        <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-700/50 font-medium text-gray-700 dark:text-gray-300">Facturas seleccionadas ({{ $facturas->count() }})</div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-600 text-sm">
                <thead class="bg-gray-50 dark:bg-gray-700/50">
                    <tr>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">#</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Cliente</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Período</th>
                        <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Saldo</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-600 bg-white dark:bg-gray-800">
                    @foreach($facturas as $f)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                            <td class="px-4 py-2 font-medium text-gray-900 dark:text-gray-100">{{ $f->id }}</td>
                            <td class="px-4 py-2 text-gray-900 dark:text-gray-100">{{ $f->cliente->nombre }} {{ $f->cliente->apellido }}</td>
                            <td class="px-4 py-2 text-gray-600 dark:text-gray-300">{{ $f->periodo_desde->format('d/m/Y') }} - {{ $f->periodo_hasta->format('d/m/Y') }}</td>
                            <td class="px-4 py-2 text-right font-medium text-amber-700 dark:text-amber-400">{{ number_format($f->saldo_pendiente, 0, ',', '.') }} {{ $f->moneda }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="px-4 py-3 border-t border-gray-200 dark:border-gray-600 bg-amber-50 dark:bg-amber-900/20 text-right">
            <span class="text-sm font-semibold text-gray-800 dark:text-gray-200">Saldo total a distribuir: {{ number_format($totalSaldo, 0, ',', '.') }} PYG</span>
        </div>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 p-6">
        <form action="{{ route('cobros.store-multicobro') }}" method="POST" id="form-multicobro">
            @csrf
            @foreach($facturas as $f)
                <input type="hidden" name="factura_interna_ids[]" value="{{ $f->id }}">
            @endforeach

            <div class="space-y-4">
                <div>
                    <label for="monto_total" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Monto total a abonar (PYG) *</label>
                    <input type="number" name="monto_total" id="monto_total" step="0.01" min="0.01" value="{{ old('monto_total', $totalSaldo) }}" required
                           class="mt-1 w-full px-3 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-green-500 focus:ring-2 focus:ring-green-500/20 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-500">
                    @error('monto_total')<p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label for="fecha_pago" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Fecha y hora de pago *</label>
                        <input type="datetime-local" name="fecha_pago" id="fecha_pago" value="{{ old('fecha_pago', now()->format('Y-m-d\TH:i')) }}" required step="60"
                               class="mt-1 w-full px-3 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-green-500 focus:ring-2 focus:ring-green-500/20 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                        @error('fecha_pago')<p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="forma_pago" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Forma de pago *</label>
                        <select name="forma_pago" id="forma_pago" required
                                class="mt-1 w-full px-3 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-green-500 focus:ring-2 focus:ring-green-500/20 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                            @foreach($formasPago as $key => $label)
                                <option value="{{ $key }}" {{ old('forma_pago', 'efectivo') == $key ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('forma_pago')<p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
                    </div>
                </div>

                <div>
                    <label for="referencia" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Referencia (opcional)</label>
                    <input type="text" name="referencia" id="referencia" value="{{ old('referencia') }}" maxlength="100" placeholder="Nº cheque, ref. transferencia"
                           class="mt-1 w-full px-3 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-green-500 focus:ring-2 focus:ring-green-500/20 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-500">
                </div>

                <div>
                    <label for="observaciones" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Observaciones (opcional)</label>
                    <textarea name="observaciones" id="observaciones" rows="2"
                              class="mt-1 w-full px-3 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-green-500 focus:ring-2 focus:ring-green-500/20 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-500">{{ old('observaciones') }}</textarea>
                </div>
            </div>

            <p class="mt-4 text-xs text-gray-500 dark:text-gray-400">
                @if($cantidadCobros > 1)
                    Se generarán {{ $cantidadCobros }} cobros (uno por cliente). Cada recibo recibirá su número al confirmar el registro.
                @else
                    Al confirmar se generará un recibo; el número se asigna en ese momento de forma única.
                @endif
            </p>

            <div class="mt-6 flex gap-3">
                <button type="submit" id="btn-registrar-multicobro" class="px-4 py-2 bg-green-600 text-white rounded-lg font-medium hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 disabled:opacity-70 disabled:cursor-not-allowed">
                    Registrar multicobro
                </button>
                <a href="{{ route('factura-internas.pendientes') }}" class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-600">
                    Cancelar
                </a>
            </div>
        </form>
    </div>
</div>
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    var form = document.getElementById('form-multicobro');
    var btn = document.getElementById('btn-registrar-multicobro');
    if (form && btn) {
        form.addEventListener('submit', function(e) {
            if (form.dataset.submitting === '1') {
                e.preventDefault();
                return;
            }
            form.dataset.submitting = '1';
            btn.disabled = true;
            btn.textContent = 'Procesando…';
        });
    }
});
</script>
@endpush
@endsection
