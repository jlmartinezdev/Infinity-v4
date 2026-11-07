@extends('layouts.app')

@section('title', 'Configuración - Finanzas')

@section('content')
<div class="max-w-2xl mx-auto">
    <div class="mb-4">
        <a href="{{ route('configuracion.index') }}" class="text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200 text-sm font-medium">&larr; Configuración</a>
    </div>
    <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100 mb-2">Parámetros financieros</h1>
    <p class="text-sm text-gray-600 dark:text-gray-400 mb-2">Estos valores definen el libro. No modifican recibos, facturas ni el dashboard de cobranzas.</p>
    <p class="mb-6"><a href="{{ route('finanzas.dashboard') }}" class="text-sm font-medium text-emerald-700 dark:text-emerald-400">Abrir el libro financiero</a></p>

    @if(session('success'))
        <div class="mb-4 p-4 rounded-lg bg-green-100 dark:bg-green-900/20 text-green-800 dark:text-green-200 text-sm border border-green-200 dark:border-green-800">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="mb-4 p-4 rounded-lg bg-red-100 dark:bg-red-900/20 text-red-800 dark:text-red-200 text-sm border border-red-200 dark:border-red-800">
            <ul class="list-disc list-inside">
                @foreach($errors->all() as $e)
                    <li>{{ $e }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form action="{{ route('configuracion.finanzas.store') }}" method="POST" class="space-y-6">
        @csrf

        <div class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-700/50">
                <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-200">Fecha de corte</h2>
            </div>
            <div class="p-6">
                <label for="fin_fecha_corte" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Desde cuándo entran los recibos al libro</label>
                <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">Vacío: el libro no toma recibos. Los anteriores a esta fecha quedan solo en cobranzas.</p>
                <input type="date" name="fin_fecha_corte" id="fin_fecha_corte"
                    value="{{ old('fin_fecha_corte', $fechaCorte) }}"
                    class="mt-1 w-full px-3 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-green-500 focus:ring-2 focus:ring-green-500/20 bg-white dark:bg-gray-700 dark:text-gray-100">
            </div>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-700/50">
                <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-200">Medio de pago y cuenta</h2>
            </div>
            <div class="p-6 space-y-4">
                <p class="text-xs text-gray-500 dark:text-gray-400">Un recibo con referencia TPAGO entra a Bancard / TPago aunque la forma de pago diga tarjeta. El recibo no se reescribe.</p>
                @foreach($formas as $forma => $etiqueta)
                    <div>
                        <label for="fin_cuenta_{{ $forma }}" class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ $etiqueta }}</label>
                        <select name="fin_cuenta_{{ $forma }}" id="fin_cuenta_{{ $forma }}"
                            class="mt-1 w-full px-3 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-green-500 focus:ring-2 focus:ring-green-500/20 bg-white dark:bg-gray-700 dark:text-gray-100">
                            @foreach($cuentasOpciones as $codigo => $nombre)
                                <option value="{{ $codigo }}" @selected(old('fin_cuenta_'.$forma, $cuentas[$forma]) === $codigo)>{{ $nombre }}</option>
                            @endforeach
                        </select>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-700/50">
                <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-200">Comisión Bancard</h2>
            </div>
            <div class="p-6">
                <label for="fin_comision_bancard" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Porcentaje sugerido</label>
                <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">Sirve de referencia al liquidar. Cada liquidación confirma su comisión. Vacío si todavía no hay un porcentaje.</p>
                <input type="number" name="fin_comision_bancard" id="fin_comision_bancard" min="0" max="100" step="0.01"
                    value="{{ old('fin_comision_bancard', $comision) }}"
                    placeholder="3"
                    class="mt-1 w-full px-3 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-green-500 focus:ring-2 focus:ring-green-500/20 bg-white dark:bg-gray-700 dark:text-gray-100">
            </div>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-700/50">
                <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-200">Saldos iniciales</h2>
            </div>
            <div class="p-6 space-y-4">
                <p class="text-xs text-gray-500 dark:text-gray-400">Vacío significa que la cuenta arranca sin saldo declarado. El importe se escribe a mano, con su fecha.</p>
                <div>
                    <label for="fin_saldo_inicial_fecha" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Fecha del saldo</label>
                    <input type="date" name="fin_saldo_inicial_fecha" id="fin_saldo_inicial_fecha"
                        value="{{ old('fin_saldo_inicial_fecha', $fechaSaldo) }}"
                        class="mt-1 w-full px-3 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-green-500 focus:ring-2 focus:ring-green-500/20 bg-white dark:bg-gray-700 dark:text-gray-100">
                </div>
                @foreach(['efectivo' => 'Efectivo', 'banco' => 'Transferencia bancaria', 'bancard' => 'Bancard / TPago'] as $codigo => $nombre)
                    <div>
                        <label for="fin_saldo_inicial_{{ $codigo }}" class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ $nombre }}</label>
                        <input type="text" inputmode="decimal" name="fin_saldo_inicial_{{ $codigo }}" id="fin_saldo_inicial_{{ $codigo }}"
                            value="{{ old('fin_saldo_inicial_'.$codigo, $saldos[$codigo]) }}"
                            placeholder="0"
                            class="mt-1 w-full px-3 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-green-500 focus:ring-2 focus:ring-green-500/20 bg-white dark:bg-gray-700 dark:text-gray-100">
                    </div>
                @endforeach
            </div>
        </div>

        <div class="flex gap-3">
            <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded-lg font-medium hover:bg-green-700">Guardar parámetros</button>
            <a href="{{ route('configuracion.index') }}" class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700">Cancelar</a>
        </div>
    </form>
</div>
@endsection
