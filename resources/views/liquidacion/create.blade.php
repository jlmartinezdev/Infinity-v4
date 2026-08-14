@extends('layouts.app')

@section('title', 'Liquidación de sueldo')

@section('content')
@php
    $staffJson = $staff->map(fn ($u) => [
        'id' => $u->usuario_id,
        'cedula' => $u->cedula,
        'cargo' => $u->cargo,
        'salario_basico' => $u->salario_basico ?: $salarioMinimo,
        'banco' => $u->banco ?: $bancoDefault,
        'cuenta_bancaria' => $u->cuenta_bancaria,
        'ips_sugerido' => (int) round(($u->salario_basico ?: $salarioMinimo) * ($ipsPct / 100)),
    ])->values();
@endphp
<div class="max-w-4xl mx-auto">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">Liquidación de sueldo</h1>
            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                Generá el recibo PDF (1 hoja) con datos del personal
            </p>
        </div>
        <a href="{{ route('usuarios.index') }}"
           class="inline-flex items-center px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-gray-200 rounded-lg font-medium hover:bg-gray-300 dark:hover:bg-gray-600">
            Volver a usuarios
        </a>
    </div>

    @if($errors->any())
        <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-800 dark:bg-red-900/30 dark:text-red-200">
            <ul class="list-disc list-inside">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="mb-4 rounded-lg border border-gray-200 bg-white px-4 py-3 text-sm text-gray-600 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300">
        <strong class="text-gray-900 dark:text-gray-100">Empleador:</strong>
        {{ $empleadorNombre }} – C.I. {{ $empleadorCi }}
        <span class="mx-2 text-gray-400">·</span>
        {{ $ciudad }}
        <span class="mx-2 text-gray-400">·</span>
        IPS sugerido: {{ rtrim(rtrim(number_format($ipsPct, 2, ',', '.'), '0'), ',') }}%
    </div>

    <form method="POST" action="{{ route('liquidacion.pdf') }}" target="_blank"
          class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 p-6 space-y-6">
        @csrf

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div class="sm:col-span-2">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Trabajador *</label>
                <select name="usuario_id" id="usuario_id" required
                        class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                    <option value="">Seleccionar personal…</option>
                    @foreach($staff as $u)
                        <option value="{{ $u->usuario_id }}" @selected(old('usuario_id', $selectedId) == $u->usuario_id)>
                            {{ $u->name }}@if($u->cargo) — {{ $u->cargo }}@endif
                        </option>
                    @endforeach
                </select>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                    Cédula, cargo, salario y cuenta se toman del usuario (editá en Usuarios).
                </p>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Fecha *</label>
                <input type="date" name="fecha" value="{{ old('fecha', now()->toDateString()) }}" required
                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Periodo (mes/año) *</label>
                <input type="text" name="periodo" value="{{ old('periodo', now()->format('m/Y')) }}" required
                       placeholder="07/2026"
                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
            </div>
        </div>

        <div>
            <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-3">Haberes</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Salario básico (Gs.)</label>
                    <input type="number" name="salario_basico" id="salario_basico" min="0" step="1"
                           value="{{ old('salario_basico') }}"
                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Jornadas trabajadas</label>
                    <input type="number" name="jornadas" min="0" step="0.5" value="{{ old('jornadas', $jornadasDefault) }}"
                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Horas extras diurnas</label>
                    <input type="number" name="horas_extras_diurnas" min="0" step="0.5" value="{{ old('horas_extras_diurnas') }}"
                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Monto extras diurnas</label>
                    <input type="number" name="monto_extras_diurnas" min="0" step="1" value="{{ old('monto_extras_diurnas', 0) }}"
                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Horas extras nocturnas</label>
                    <input type="number" name="horas_extras_nocturnas" min="0" step="0.5" value="{{ old('horas_extras_nocturnas') }}"
                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Monto extras nocturnas</label>
                    <input type="number" name="monto_extras_nocturnas" min="0" step="1" value="{{ old('monto_extras_nocturnas', 0) }}"
                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Horas feriados</label>
                    <input type="number" name="horas_feriados" min="0" step="0.5" value="{{ old('horas_feriados') }}"
                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Monto feriados</label>
                    <input type="number" name="monto_feriados" min="0" step="1" value="{{ old('monto_feriados', 0) }}"
                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Bonificaciones / comisiones / otros</label>
                    <input type="number" name="bonificaciones" min="0" step="1" value="{{ old('bonificaciones', 0) }}"
                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                </div>
            </div>
        </div>

        <div>
            <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-3">Descuentos</h2>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">IPS (Gs.)</label>
                    <input type="number" name="ips" id="ips" min="0" step="1" value="{{ old('ips') }}"
                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Anticipo</label>
                    <input type="number" name="anticipo" min="0" step="1" value="{{ old('anticipo', 0) }}"
                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Otros descuentos</label>
                    <input type="number" name="otros_descuentos" min="0" step="1" value="{{ old('otros_descuentos', 0) }}"
                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                </div>
            </div>
        </div>

        <div>
            <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-3">Pago</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="sm:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Forma de pago *</label>
                    <select name="forma_pago" id="forma_pago" required
                            class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                        <option value="Transferencia bancaria" @selected(old('forma_pago', 'Transferencia bancaria') === 'Transferencia bancaria')>Transferencia bancaria</option>
                        <option value="Efectivo" @selected(old('forma_pago') === 'Efectivo')>Efectivo</option>
                    </select>
                </div>
                <div id="campo-banco">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Banco destino</label>
                    <input type="text" name="banco" id="banco" value="{{ old('banco') }}"
                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                </div>
                <div id="campo-cuenta">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Cuenta destino</label>
                    <input type="text" name="cuenta_bancaria" id="cuenta_bancaria" value="{{ old('cuenta_bancaria') }}"
                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Observación</label>
                    <textarea name="observacion" rows="2"
                              class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">{{ old('observacion', 'Se deja constancia de que el trabajador recibió/percibió el neto indicado.') }}</textarea>
                </div>
            </div>
        </div>

        <div class="flex flex-wrap gap-3 pt-2">
            <button type="submit"
                    class="inline-flex items-center px-5 py-2.5 bg-blue-600 text-white rounded-lg font-medium hover:bg-blue-700">
                Generar PDF
            </button>
        </div>
    </form>
</div>

<script>
(function () {
    const staff = @json($staffJson);
    const byId = Object.fromEntries(staff.map(s => [String(s.id), s]));
    const sel = document.getElementById('usuario_id');
    const salario = document.getElementById('salario_basico');
    const ips = document.getElementById('ips');
    const banco = document.getElementById('banco');
    const cuenta = document.getElementById('cuenta_bancaria');
    const formaPago = document.getElementById('forma_pago');
    const campoBanco = document.getElementById('campo-banco');
    const campoCuenta = document.getElementById('campo-cuenta');
    const ipsPct = {{ json_encode($ipsPct) }};

    function toggleBanco() {
        const esTransferencia = formaPago.value === 'Transferencia bancaria';
        campoBanco.style.display = esTransferencia ? '' : 'none';
        campoCuenta.style.display = esTransferencia ? '' : 'none';
        if (!esTransferencia) {
            banco.value = '';
            cuenta.value = '';
        }
    }

    function applyStaff() {
        const s = byId[sel.value];
        if (!s) return;
        salario.value = s.salario_basico ?? '';
        ips.value = s.ips_sugerido ?? Math.round((Number(s.salario_basico) || 0) * (ipsPct / 100));
        if (formaPago.value === 'Transferencia bancaria') {
            banco.value = s.banco || '';
            cuenta.value = s.cuenta_bancaria || '';
        }
    }

    sel.addEventListener('change', applyStaff);
    formaPago.addEventListener('change', function () {
        toggleBanco();
        if (formaPago.value === 'Transferencia bancaria' && sel.value) applyStaff();
    });
    salario.addEventListener('input', function () {
        const v = Number(salario.value) || 0;
        ips.value = Math.round(v * (ipsPct / 100));
    });

    toggleBanco();
    // Solo precargar al abrir si hay staff y el salario aún no viene de old()
    if (sel.value && !salario.value) applyStaff();
})();
</script>
@endsection
