@extends('layouts.app')
@section('title', 'Conciliación')
@section('content')
@php $fmt = fn ($v) => number_format((float) $v, 0, ',', '.'); $cls = 'mt-1 w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-sm'; @endphp
<div class="max-w-3xl mx-auto">
    @include('finanzas._nav')
    <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100 mb-2">Conciliación</h1>
    <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">Compara el saldo del libro con el saldo contado. Un ajuste queda justificado y no reescribe movimientos anteriores. Efectivo en poder de cobradores: {{ $fmt($efectivoSinRendir) }} PYG.</p>
    @if(session('success'))<div class="mb-4 p-3 rounded-lg bg-green-100 text-green-800 text-sm">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="mb-4 p-3 rounded-lg bg-red-100 text-red-800 text-sm">{{ $errors->first() }}</div>@endif
    <form method="get" class="flex gap-2 mb-4">
        <select name="cuenta_id" class="{{ $cls }}">@foreach($cuentas as $c)<option value="{{ $c->id }}" @selected($cuentaId === $c->id)>{{ $c->nombre }}</option>@endforeach</select>
        <input type="date" name="fecha" value="{{ $fecha }}" class="{{ $cls }}">
        <button class="px-4 py-2 rounded-lg border border-gray-300 text-sm">Ver</button>
    </form>
    <form method="post" action="{{ route('finanzas.conciliacion.store') }}" class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6 space-y-4">
        @csrf
        <input type="hidden" name="cuenta_id" value="{{ $cuentaId }}">
        <input type="hidden" name="fecha" value="{{ $fecha }}">
        <p class="text-sm">Saldo del sistema al {{ \Carbon\Carbon::parse($fecha)->format('d/m/Y') }}: <strong>{{ $fmt($saldoSistema) }}</strong></p>
        <div><label class="text-sm">Saldo real contado</label><input name="saldo_real" required class="{{ $cls }}" value="{{ old('saldo_real') }}"></div>
        <div><label class="text-sm">Observaciones</label><textarea name="observaciones" class="{{ $cls }}" rows="2">{{ old('observaciones') }}</textarea></div>
        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="ajustar" value="1"> Registrar un ajuste por la diferencia</label>
        <button class="px-4 py-2 rounded-lg bg-emerald-600 text-white text-sm">Guardar conciliación</button>
    </form>
    <div class="mt-6 space-y-2 text-sm">
        @foreach($filas as $fila)
            <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg px-3 py-2">{{ $fila->fecha?->format('d/m/Y') }} · {{ $fila->cuenta?->nombre }} · sistema {{ $fmt($fila->saldo_sistema) }} · real {{ $fmt($fila->saldo_real) }} · diferencia {{ $fmt($fila->diferencia) }}</div>
        @endforeach
    </div>
</div>
@endsection
