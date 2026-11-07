@extends('layouts.app')
@section('title', 'Cuentas financieras')
@section('content')
@php $cls = 'mt-1 w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-sm'; @endphp
<div class="max-w-3xl mx-auto">
    @include('finanzas._nav')
    <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100 mb-4">Cuentas</h1>
    @if(session('success'))<div class="mb-4 p-3 rounded-lg bg-green-100 text-green-800 text-sm">{{ session('success') }}</div>@endif
    <div class="space-y-2 mb-6 text-sm">
        @foreach($cuentas as $cuenta)
            <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg px-3 py-2">{{ $cuenta->nombre }} · {{ $cuenta->tipo }} · {{ $cuenta->codigo }}</div>
        @endforeach
    </div>
    <form method="post" action="{{ route('finanzas.cuentas.store') }}" class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6 space-y-4">
        @csrf
        <div><label class="text-sm">Nueva cuenta</label><input name="nombre" required class="{{ $cls }}"></div>
        <div><label class="text-sm">Tipo</label><select name="tipo" class="{{ $cls }}"><option value="efectivo">Efectivo</option><option value="banco">Banco</option><option value="pasarela">Pasarela</option><option value="otra">Otra</option></select></div>
        <button class="px-4 py-2 rounded-lg bg-emerald-600 text-white text-sm">Agregar cuenta</button>
    </form>
    <h2 class="text-lg font-semibold mt-8 mb-2">Categorías</h2>
    <div class="space-y-2 text-sm">
        @foreach($categorias as $categoria)
            <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg px-3 py-2">{{ strtoupper($categoria->clase) }} — {{ $categoria->nombre }}</div>
        @endforeach
    </div>
</div>
@endsection
