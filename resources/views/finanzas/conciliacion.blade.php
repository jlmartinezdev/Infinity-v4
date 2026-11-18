@extends('layouts.app')
@section('title', 'Conciliación')
@section('content')
@php
    $fmt = fn ($v) => number_format((float) $v, 0, ',', '.');
    $cls = 'mt-1 w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 text-sm';
    $lbl = 'block text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400';
    $colorDiferencia = fn ($v) => abs((float) $v) < 0.01 ? 'text-emerald-700 dark:text-emerald-400' : 'text-red-600 dark:text-red-400';
@endphp
<div class="max-w-5xl mx-auto">
    @include('finanzas._nav')
    <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100 mb-2">Conciliación</h1>
    <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">Compara el saldo del libro con el saldo contado. Un ajuste queda justificado y no reescribe movimientos anteriores. Efectivo en poder de cobradores: {{ $fmt($efectivoSinRendir) }} PYG.</p>
    @include('finanzas._mensajes')

    <form method="get" class="flex flex-col sm:flex-row sm:items-end gap-2 mb-4">
        <div class="flex-1">
            <label class="{{ $lbl }}" for="cuenta">Cuenta</label>
            <select id="cuenta" name="cuenta_id" class="{{ $cls }}">@foreach($cuentas as $c)<option value="{{ $c->id }}" @selected($cuentaId === $c->id)>{{ $c->nombre }}</option>@endforeach</select>
        </div>
        <div>
            <label class="{{ $lbl }}" for="fecha-ver">Al día</label>
            <input id="fecha-ver" type="date" name="fecha" value="{{ $fecha }}" class="{{ $cls }}">
        </div>
        <button class="px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 text-sm">Ver saldo</button>
    </form>

    <form method="post" action="{{ route('finanzas.conciliacion.store') }}" class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5 space-y-4">
        @csrf
        <input type="hidden" name="cuenta_id" value="{{ $cuentaId }}">
        <input type="hidden" name="fecha" value="{{ $fecha }}">
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
            <div class="rounded-lg bg-gray-50 dark:bg-gray-700/40 px-3 py-2">
                <p class="text-xs text-gray-500 dark:text-gray-400">Saldo del sistema al {{ \Carbon\Carbon::parse($fecha)->format('d/m/Y') }}</p>
                <p class="text-lg font-mono font-semibold text-gray-900 dark:text-gray-100" id="saldo-sistema" data-valor="{{ $saldoSistema }}">{{ $fmt($saldoSistema) }}</p>
            </div>
            <div>
                <label class="{{ $lbl }}" for="saldo-real">Saldo real contado</label>
                <input id="saldo-real" name="saldo_real" required class="{{ $cls }} font-mono" value="{{ old('saldo_real') }}" placeholder="0">
            </div>
            <div class="rounded-lg bg-gray-50 dark:bg-gray-700/40 px-3 py-2">
                <p class="text-xs text-gray-500 dark:text-gray-400">Diferencia</p>
                <p class="text-lg font-mono font-semibold text-gray-900 dark:text-gray-100" id="diferencia">—</p>
            </div>
        </div>
        <label class="flex items-center gap-2 text-sm text-gray-800 dark:text-gray-200"><input type="checkbox" name="ajustar" value="1" id="ajustar" @checked(old('ajustar') === '1')> Registrar un ajuste por la diferencia</label>
        <div>
            <label class="{{ $lbl }}" for="obs-conc">Observaciones <span id="obs-requerida" class="hidden normal-case text-red-600 dark:text-red-400">(obligatorias para ajustar: explicá de dónde sale la diferencia)</span></label>
            <textarea id="obs-conc" name="observaciones" class="{{ $cls }}" rows="2">{{ old('observaciones') }}</textarea>
        </div>
        <div class="flex justify-end">
            <button class="px-4 py-2 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold">Guardar conciliación</button>
        </div>
    </form>

    @include('finanzas._tabla', [
        'titulo' => 'Últimas conciliaciones',
        'columnas' => ['Fecha', 'Cuenta', ['Sistema', 'right'], ['Real', 'right'], ['Diferencia', 'right'], 'Ajuste', 'Observaciones'],
        'filas' => $filas->map(fn ($fila) => [
            $fila->fecha?->format('d/m/Y'),
            $fila->cuenta?->nombre,
            ['valor' => $fmt($fila->saldo_sistema), 'clase' => 'text-right font-mono'],
            ['valor' => $fmt($fila->saldo_real), 'clase' => 'text-right font-mono'],
            ['valor' => $fmt($fila->diferencia), 'clase' => 'text-right font-mono font-semibold '.$colorDiferencia($fila->diferencia)],
            $fila->ajuste_movimiento_id ? 'Sí' : 'No',
            $fila->observaciones,
        ]),
        'vacio' => 'Todavía no hay conciliaciones.',
    ])
</div>
@endsection
@push('scripts')
<script>
(function () {
    const sistema = Number(document.getElementById('saldo-sistema').dataset.valor || 0);
    const real = document.getElementById('saldo-real');
    const diferencia = document.getElementById('diferencia');
    const ajustar = document.getElementById('ajustar');
    const observaciones = document.getElementById('obs-conc');
    const aviso = document.getElementById('obs-requerida');
    const numero = (texto) => {
        let t = String(texto || '').trim().replace(/\s/g, '');
        if (t.includes(',') && t.includes('.')) t = t.replace(/\./g, '').replace(',', '.');
        else if (t.includes(',')) t = t.replace(',', '.');
        else if (/^\d{1,3}(\.\d{3})+$/.test(t)) t = t.replace(/\./g, '');
        return t === '' ? null : Number(t);
    };
    function actualizar() {
        const valor = numero(real.value);
        if (valor === null || Number.isNaN(valor)) {
            diferencia.textContent = '—';
            diferencia.className = 'text-lg font-mono font-semibold text-gray-900 dark:text-gray-100';
        } else {
            const d = Math.round(valor - sistema);
            diferencia.textContent = (d > 0 ? '+' : '') + d.toLocaleString('es-PY');
            diferencia.className = 'text-lg font-mono font-semibold ' + (d === 0 ? 'text-emerald-700 dark:text-emerald-400' : 'text-red-600 dark:text-red-400');
        }
        observaciones.required = ajustar.checked;
        aviso.classList.toggle('hidden', !ajustar.checked);
    }
    real.addEventListener('input', actualizar);
    ajustar.addEventListener('change', actualizar);
    actualizar();
})();
</script>
@endpush
