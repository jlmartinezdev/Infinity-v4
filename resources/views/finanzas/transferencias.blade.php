@extends('layouts.app')
@section('title', 'Transferencias')
@section('content')
@php
    $fmt = fn ($v) => number_format((float) $v, 0, ',', '.');
    $cls = 'mt-1 w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 text-sm';
    $lbl = 'block text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400';
    $origenDefecto = old('cuenta_origen_id', $cuentas->first()?->id);
    $destinoDefecto = old('cuenta_destino_id', $cuentas->firstWhere('id', '!=', $origenDefecto)?->id);
@endphp
<div class="max-w-5xl mx-auto">
    @include('finanzas._nav')
    <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100 mb-2">Transferencias</h1>
    <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">Sale de una cuenta y entra en la otra, en la misma operación. El total de Interplus no cambia.</p>
    @include('finanzas._mensajes')

    <form method="post" action="{{ route('finanzas.transferencias.store') }}" class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5 grid grid-cols-1 sm:grid-cols-2 gap-4">
        @csrf
        <div>
            <label class="{{ $lbl }}" for="origen">Origen</label>
            <select id="origen" name="cuenta_origen_id" class="{{ $cls }}" required>
                @foreach($cuentas as $c)
                    <option value="{{ $c->id }}" @selected($origenDefecto == $c->id)>{{ $c->nombre }} — saldo {{ $fmt($saldos[$c->id] ?? 0) }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="{{ $lbl }}" for="destino">Destino</label>
            <select id="destino" name="cuenta_destino_id" class="{{ $cls }}" required>
                @foreach($cuentas as $c)
                    <option value="{{ $c->id }}" @selected($destinoDefecto == $c->id)>{{ $c->nombre }} — saldo {{ $fmt($saldos[$c->id] ?? 0) }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="{{ $lbl }}" for="importe">Importe</label>
            <input id="importe" name="importe" required class="{{ $cls }} font-mono" placeholder="100.000" value="{{ old('importe') }}">
        </div>
        <div>
            <label class="{{ $lbl }}" for="fecha">Fecha</label>
            <input id="fecha" type="date" name="fecha" required value="{{ old('fecha', now()->toDateString()) }}" class="{{ $cls }}">
        </div>
        <div>
            <label class="{{ $lbl }}" for="referencia">Referencia</label>
            <input id="referencia" name="numero_referencia" class="{{ $cls }}" value="{{ old('numero_referencia') }}" placeholder="Número de operación">
        </div>
        <div>
            <label class="{{ $lbl }}" for="observaciones">Observaciones</label>
            <input id="observaciones" name="observaciones" class="{{ $cls }}" value="{{ old('observaciones') }}">
        </div>
        <div class="sm:col-span-2 flex justify-end">
            <button class="px-4 py-2 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold">Transferir</button>
        </div>
    </form>

    @include('finanzas._tabla', [
        'titulo' => 'Últimas transferencias',
        'columnas' => ['Fecha', 'Origen', 'Destino', 'Referencia', ['Importe', 'right']],
        'filas' => $filas->map(fn ($fila) => [
            $fila->fecha?->format('d/m/Y'),
            $fila->origen?->nombre,
            $fila->destino?->nombre,
            $fila->numero_referencia ?: ($fila->liquidacion_id ? 'Liquidación Bancard' : '—'),
            ['valor' => $fmt($fila->importe), 'clase' => 'text-right font-mono font-semibold'],
        ]),
        'vacio' => 'Todavía no hay transferencias.',
    ])
</div>
@endsection
