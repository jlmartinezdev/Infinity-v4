@extends('layouts.app')
@section('title', 'Liquidaciones Bancard')
@section('content')
@php
    $fmt = fn ($v) => number_format((float) $v, 0, ',', '.');
    $cls = 'mt-1 w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 text-sm';
    $lbl = 'block text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400';
    $marcados = collect(old('cobro_ids', []))->map(fn ($id) => (int) $id)->all();
@endphp
<div class="max-w-5xl mx-auto">
    @include('finanzas._nav')
    <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100 mb-2">Liquidaciones Bancard</h1>
    <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">El bruto ya entró con el recibo. Acá se descuenta la comisión y el neto se transfiere al banco. Esos importes no vuelven a contarse como ingreso.</p>
    @include('finanzas._mensajes')

    <form method="post" action="{{ route('finanzas.liquidaciones.store') }}" class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5 space-y-4">
        @csrf
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
            <div>
                <label class="{{ $lbl }}" for="fecha-liq">Fecha de liquidación</label>
                <input id="fecha-liq" type="date" name="fecha_liquidacion" required value="{{ old('fecha_liquidacion', now()->toDateString()) }}" class="{{ $cls }}">
            </div>
            <div>
                <label class="{{ $lbl }}" for="referencia">Referencia</label>
                <input id="referencia" name="referencia" required class="{{ $cls }}" value="{{ old('referencia') }}" placeholder="Número de liquidación">
            </div>
            <div>
                <label class="{{ $lbl }}" for="comision">Comisión</label>
                <input name="comision" id="comision" class="{{ $cls }} font-mono" value="{{ old('comision') }}" placeholder="0">
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                    @if($comisionSugerida)
                        Se calcula sola ({{ rtrim(rtrim(number_format($comisionSugerida, 2, ',', '.'), '0'), ',') }}% del bruto). Si la escribís, queda la tuya.
                    @else
                        Sin porcentaje configurado: escribí la comisión que descontó Bancard.
                    @endif
                </p>
            </div>
        </div>

        <div class="grid grid-cols-3 gap-3 text-sm">
            <div class="rounded-lg bg-gray-50 dark:bg-gray-700/40 px-3 py-2">
                <p class="text-xs text-gray-500 dark:text-gray-400">Bruto (<span id="cantidad">0</span> cobros)</p>
                <p class="font-mono font-semibold text-gray-900 dark:text-gray-100" id="bruto">0</p>
            </div>
            <div class="rounded-lg bg-gray-50 dark:bg-gray-700/40 px-3 py-2">
                <p class="text-xs text-gray-500 dark:text-gray-400">− Comisión</p>
                <p class="font-mono font-semibold text-gray-900 dark:text-gray-100" id="comision-ver">0</p>
            </div>
            <div class="rounded-lg bg-emerald-50 dark:bg-emerald-900/30 px-3 py-2">
                <p class="text-xs text-emerald-700 dark:text-emerald-400">= Neto al banco</p>
                <p class="font-mono font-semibold text-emerald-800 dark:text-emerald-300" id="neto">0</p>
            </div>
        </div>

        <div class="max-h-96 overflow-auto border border-gray-200 dark:border-gray-700 rounded-lg">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 dark:bg-gray-700/50 sticky top-0">
                    <tr>
                        <th class="px-3 py-2 text-left"><input type="checkbox" id="marcar-todos" aria-label="Marcar todos" @disabled($pendientes->isEmpty())></th>
                        <th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Fecha</th>
                        <th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Cobro</th>
                        <th class="px-3 py-2 text-right text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Importe</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($pendientes as $movimiento)
                    <tr class="border-t border-gray-200 dark:border-gray-700">
                        <td class="px-3 py-2"><input type="checkbox" name="cobro_ids[]" value="{{ $movimiento->referencia_id }}" data-importe="{{ $movimiento->importe }}" class="cobro-liq" @checked(in_array((int) $movimiento->referencia_id, $marcados, true))></td>
                        <td class="px-3 py-2 whitespace-nowrap">{{ $movimiento->fecha_efectiva?->format('d/m/Y') }}</td>
                        <td class="px-3 py-2">{{ $movimiento->concepto }}</td>
                        <td class="px-3 py-2 text-right font-mono">{{ $fmt($movimiento->importe) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-3 py-6 text-center text-gray-500 dark:text-gray-400">No hay cobros Bancard pendientes. Entran cuando la fecha de corte está definida y el recibo se mapea a Bancard.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if($pendientes->count() >= 200)
            <p class="text-xs text-amber-700 dark:text-amber-400">Se muestran los 200 cobros pendientes más recientes. Liquidá estos y aparecerán los siguientes.</p>
        @endif
        <div>
            <label class="{{ $lbl }}" for="obs-liq">Observaciones</label>
            <textarea id="obs-liq" name="observaciones" class="{{ $cls }}" rows="2">{{ old('observaciones') }}</textarea>
        </div>
        <div class="flex justify-end">
            <button class="px-4 py-2 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold">Liquidar</button>
        </div>
    </form>

    @include('finanzas._tabla', [
        'titulo' => 'Últimas liquidaciones',
        'columnas' => ['Fecha', 'Referencia', ['Bruto', 'right'], ['Comisión', 'right'], ['Neto', 'right']],
        'filas' => $filas->map(fn ($fila) => [
            $fila->fecha_liquidacion?->format('d/m/Y'),
            $fila->referencia,
            ['valor' => $fmt($fila->bruto), 'clase' => 'text-right font-mono'],
            ['valor' => $fmt($fila->comision), 'clase' => 'text-right font-mono text-red-600 dark:text-red-400'],
            ['valor' => $fmt($fila->neto), 'clase' => 'text-right font-mono font-semibold'],
        ]),
        'vacio' => 'Todavía no hay liquidaciones.',
    ])
</div>
@endsection
@push('scripts')
<script>
(function () {
    const casillas = Array.from(document.querySelectorAll('.cobro-liq'));
    const todos = document.getElementById('marcar-todos');
    const comision = document.getElementById('comision');
    const sugerida = @json($comisionSugerida);
    const pyg = (v) => Math.round(v).toLocaleString('es-PY');
    const numero = (texto) => Number(String(texto || '').replace(/\./g, '').replace(',', '.')) || 0;
    // La comisión se recalcula con cada selección hasta que la persona la escriba a mano.
    let manual = comision.value.trim() !== '';

    function actualizar() {
        let bruto = 0;
        let cantidad = 0;
        casillas.forEach((c) => { if (c.checked) { bruto += Number(c.dataset.importe || 0); cantidad++; } });
        if (!manual && sugerida) {
            comision.value = bruto > 0 ? pyg(bruto * sugerida / 100) : '';
        }
        const montoComision = numero(comision.value);
        document.getElementById('cantidad').textContent = cantidad;
        document.getElementById('bruto').textContent = pyg(bruto);
        document.getElementById('comision-ver').textContent = pyg(montoComision);
        document.getElementById('neto').textContent = pyg(bruto - montoComision);
        if (todos) {
            todos.checked = cantidad > 0 && cantidad === casillas.length;
            todos.indeterminate = cantidad > 0 && cantidad < casillas.length;
        }
    }

    casillas.forEach((c) => c.addEventListener('change', actualizar));
    if (todos) {
        todos.addEventListener('change', () => {
            casillas.forEach((c) => { c.checked = todos.checked; });
            actualizar();
        });
    }
    comision.addEventListener('input', () => {
        manual = comision.value.trim() !== '';
        actualizar();
    });
    actualizar();
})();
</script>
@endpush
