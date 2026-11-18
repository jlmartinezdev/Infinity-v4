@extends('layouts.app')
@section('title', 'Retiros')
@section('content')
@php
    $fmt = fn ($v) => number_format((float) $v, 0, ',', '.');
    $cls = 'mt-1 w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 text-sm';
    $lbl = 'block text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400';
@endphp
<div class="max-w-5xl mx-auto">
    @include('finanzas._nav')
    <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-2 mb-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100 mb-1">Retiros</h1>
            <p class="text-sm text-gray-600 dark:text-gray-400">Bajan la cuenta de origen y quedan aparte de los gastos operativos.</p>
        </div>
        <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 px-4 py-2 text-right">
            <p class="text-xs text-gray-500 dark:text-gray-400">Retirado en <span class="capitalize">{{ now()->translatedFormat('F') }}</span></p>
            <p class="text-lg font-semibold font-mono text-gray-900 dark:text-gray-100">{{ $fmt($totalMes) }}</p>
        </div>
    </div>
    @include('finanzas._mensajes')

    <form method="post" action="{{ route('finanzas.retiros.store') }}" class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5 grid grid-cols-1 sm:grid-cols-2 gap-4">
        @csrf
        <div>
            <label class="{{ $lbl }}" for="persona">Persona</label>
            <input id="persona" name="persona" required class="{{ $cls }}" value="{{ old('persona') }}">
        </div>
        <div>
            <label class="{{ $lbl }}" for="importe">Importe</label>
            <input id="importe" name="importe" required class="{{ $cls }} font-mono" value="{{ old('importe') }}" placeholder="100.000">
        </div>
        <div>
            <label class="{{ $lbl }}" for="cuenta">Cuenta</label>
            <select id="cuenta" name="cuenta_id" class="{{ $cls }}" required>
                @foreach($cuentas as $c)
                    <option value="{{ $c->id }}" @selected(old('cuenta_id') == $c->id)>{{ $c->nombre }} — saldo {{ $fmt($saldos[$c->id] ?? 0) }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="{{ $lbl }}" for="fecha">Fecha</label>
            <input id="fecha" type="date" name="fecha" required value="{{ old('fecha', now()->toDateString()) }}" class="{{ $cls }}">
        </div>
        <div class="sm:col-span-2">
            <label class="{{ $lbl }}" for="motivo">Motivo</label>
            <input id="motivo" name="motivo" required class="{{ $cls }}" value="{{ old('motivo') }}">
        </div>
        <div>
            <label class="{{ $lbl }}" for="autorizacion">Autorización</label>
            <input id="autorizacion" name="autorizacion" class="{{ $cls }}" value="{{ old('autorizacion') }}" placeholder="Quién lo autorizó">
        </div>
        <div>
            <label class="{{ $lbl }}" for="comprobante">Comprobante</label>
            <input id="comprobante" name="comprobante" class="{{ $cls }}" value="{{ old('comprobante') }}">
        </div>
        <div class="sm:col-span-2 flex justify-end">
            <button class="px-4 py-2 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold">Registrar retiro</button>
        </div>
    </form>

    @include('finanzas._tabla', [
        'titulo' => 'Últimos retiros',
        'columnas' => ['Fecha', 'Persona', 'Motivo', 'Cuenta', 'Autorización', ['Importe', 'right']],
        'filas' => $filas->map(fn ($fila) => [
            $fila->fecha?->format('d/m/Y'),
            ['valor' => $fila->persona, 'clase' => 'font-medium'],
            $fila->motivo,
            $fila->cuenta?->nombre,
            $fila->autorizacion,
            ['valor' => $fmt($fila->importe), 'clase' => 'text-right font-mono font-semibold'],
        ]),
        'vacio' => 'Todavía no hay retiros.',
    ])
</div>
@endsection
