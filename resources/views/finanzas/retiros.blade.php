@extends('layouts.app')
@section('title', 'Retiros')
@section('content')
@php $fmt = fn ($v) => number_format((float) $v, 0, ',', '.'); $cls = 'mt-1 w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-sm'; @endphp
<div class="max-w-3xl mx-auto">
    @include('finanzas._nav')
    <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100 mb-2">Retiros</h1>
    <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">Bajan la cuenta de origen y quedan aparte de los gastos operativos.</p>
    @if(session('success'))<div class="mb-4 p-3 rounded-lg bg-green-100 text-green-800 text-sm">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="mb-4 p-3 rounded-lg bg-red-100 text-red-800 text-sm">{{ $errors->first() }}</div>@endif
    <form method="post" action="{{ route('finanzas.retiros.store') }}" class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6 space-y-4">
        @csrf
        <div><label class="text-sm">Persona</label><input name="persona" required class="{{ $cls }}"></div>
        <div><label class="text-sm">Fecha</label><input type="date" name="fecha" required value="{{ now()->toDateString() }}" class="{{ $cls }}"></div>
        <div><label class="text-sm">Importe</label><input name="importe" required class="{{ $cls }}"></div>
        <div><label class="text-sm">Cuenta</label><select name="cuenta_id" class="{{ $cls }}" required>@foreach($cuentas as $c)<option value="{{ $c->id }}">{{ $c->nombre }}</option>@endforeach</select></div>
        <div><label class="text-sm">Motivo</label><input name="motivo" required class="{{ $cls }}"></div>
        <div><label class="text-sm">Autorización</label><input name="autorizacion" class="{{ $cls }}"></div>
        <div><label class="text-sm">Comprobante</label><input name="comprobante" class="{{ $cls }}"></div>
        <button class="px-4 py-2 rounded-lg bg-emerald-600 text-white text-sm">Registrar retiro</button>
    </form>
    <div class="mt-6 space-y-2 text-sm">
        @foreach($filas as $fila)
            <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg px-3 py-2">{{ $fila->fecha?->format('d/m/Y') }} · {{ $fila->persona }} · {{ $fila->cuenta?->nombre }} · {{ $fmt($fila->importe) }}</div>
        @endforeach
    </div>
</div>
@endsection
