@extends('layouts.app')
@section('title', 'Cierres')
@section('content')
@php $fmt = fn ($v) => number_format((float) $v, 0, ',', '.'); $cls = 'mt-1 w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-sm'; @endphp
<div class="max-w-5xl mx-auto">
    @include('finanzas._nav')
    <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100 mb-2">Cierres</h1>
    <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">Después del cierre, ese período no acepta cargas comunes. Una corrección posterior es un ajuste con auditoría.</p>
    @if(session('success'))<div class="mb-4 p-3 rounded-lg bg-green-100 text-green-800 text-sm">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="mb-4 p-3 rounded-lg bg-red-100 text-red-800 text-sm">{{ $errors->first() }}</div>@endif
    <form method="get" class="flex gap-2 mb-4">
        <select name="tipo" class="{{ $cls }}"><option value="mensual" @selected($tipo === 'mensual')>Mensual</option><option value="diario" @selected($tipo === 'diario')>Diario</option></select>
        <input type="date" name="fecha" value="{{ $fecha }}" class="{{ $cls }}">
        <button class="px-4 py-2 rounded-lg border border-gray-300 text-sm">Calcular</button>
    </form>
    <form method="post" action="{{ route('finanzas.cierres.store') }}" class="space-y-4">
        @csrf
        <input type="hidden" name="tipo" value="{{ $tipo }}">
        <input type="hidden" name="fecha" value="{{ $fecha }}">
        @foreach($preview as $fila)
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4 text-sm">
                <h2 class="font-semibold mb-2">{{ $fila['cuenta']->nombre }}</h2>
                <p>Inicial {{ $fmt($fila['inicial']) }} · Ingresos {{ $fmt($fila['desglose']['ingresos']) }} · Egresos {{ $fmt($fila['desglose']['egresos']) }} · Entradas {{ $fmt($fila['desglose']['transferencias_entrada']) }} · Salidas {{ $fmt($fila['desglose']['transferencias_salida']) }} · Retiros {{ $fmt($fila['desglose']['retiros']) }} · Ajustes {{ $fmt($fila['desglose']['ajustes']) }}</p>
                <p class="mt-1">Saldo calculado: <strong>{{ $fmt($fila['calculado']) }}</strong></p>
                <label class="block mt-2">Saldo real declarado</label>
                <input name="saldo_real[{{ $fila['cuenta']->id }}]" value="{{ old('saldo_real.'.$fila['cuenta']->id, $fila['calculado']) }}" class="{{ $cls }}">
            </div>
        @endforeach
        <button class="px-4 py-2 rounded-lg bg-emerald-600 text-white text-sm">Cerrar período</button>
    </form>
    <div class="mt-6 space-y-2 text-sm">
        @foreach($filas as $fila)
            <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg px-3 py-2">{{ $fila->tipo }} {{ $fila->periodo_desde?->format('d/m/Y') }} · {{ $fila->cuenta?->nombre }} · calculado {{ $fmt($fila->saldo_calculado) }} · real {{ $fmt($fila->saldo_real) }} · diferencia {{ $fmt($fila->diferencia) }}</div>
        @endforeach
    </div>
</div>
@endsection
