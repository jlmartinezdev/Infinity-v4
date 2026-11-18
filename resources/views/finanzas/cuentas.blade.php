@extends('layouts.app')
@section('title', 'Cuentas financieras')
@section('content')
@php
    $fmt = fn ($v) => number_format((float) $v, 0, ',', '.');
    $cls = 'mt-1 w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 text-sm';
    $lbl = 'block text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400';
    $tipos = ['efectivo' => 'Efectivo', 'banco' => 'Banco', 'pasarela' => 'Pasarela', 'otra' => 'Otra'];
    $clases = ['ingreso' => 'Ingreso', 'opex' => 'OPEX (gasto)', 'capex' => 'CAPEX (inversión)', 'comision' => 'Comisión', 'retiro' => 'Retiro', 'ajuste' => 'Ajuste'];
    $totalActivas = $cuentas->where('activa', true)->sum(fn ($c) => $saldos[$c->id] ?? 0);
@endphp
<div class="max-w-5xl mx-auto">
    @include('finanzas._nav')
    <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100 mb-4">Cuentas</h1>
    @include('finanzas._mensajes')

    @include('finanzas._tabla', [
        'titulo' => 'Cuentas financieras',
        'subtitulo' => 'Saldo actual según el libro.',
        'columnas' => ['Cuenta', 'Tipo', 'Código', 'Estado', ['Saldo', 'right']],
        'filas' => $cuentas->map(fn ($c) => [
            ['valor' => $c->nombre, 'clase' => 'font-medium'],
            $tipos[$c->tipo] ?? $c->tipo,
            ['valor' => $c->codigo, 'clase' => 'font-mono text-xs text-gray-500 dark:text-gray-400'],
            $c->activa ? 'Activa' : 'Inactiva',
            ['valor' => $fmt($saldos[$c->id] ?? 0), 'clase' => 'text-right font-mono font-semibold '.(($saldos[$c->id] ?? 0) < 0 ? 'text-red-600 dark:text-red-400' : '')],
        ]),
        'pie' => ['Total cuentas activas', '', '', '', ['valor' => $fmt($totalActivas), 'clase' => 'text-right font-mono']],
        'vacio' => 'No hay cuentas.',
    ])

    <form method="post" action="{{ route('finanzas.cuentas.store') }}" class="mt-6 bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5 grid grid-cols-1 sm:grid-cols-3 gap-4 items-end">
        @csrf
        <div>
            <label class="{{ $lbl }}" for="nombre-cuenta">Nueva cuenta</label>
            <input id="nombre-cuenta" name="nombre" required class="{{ $cls }}" value="{{ old('nombre') }}" placeholder="Ej. Banco Itaú">
        </div>
        <div>
            <label class="{{ $lbl }}" for="tipo-cuenta">Tipo</label>
            <select id="tipo-cuenta" name="tipo" class="{{ $cls }}">
                @foreach($tipos as $valor => $etiqueta)
                    <option value="{{ $valor }}" @selected(old('tipo') === $valor)>{{ $etiqueta }}</option>
                @endforeach
            </select>
        </div>
        <div class="flex justify-end">
            <button class="w-full sm:w-auto px-4 py-2 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold">Agregar cuenta</button>
        </div>
    </form>

    @include('finanzas._tabla', [
        'titulo' => 'Categorías',
        'columnas' => ['Categoría', 'Clase', 'Estado'],
        'filas' => $categorias->map(fn ($c) => [
            ['valor' => $c->nombre, 'clase' => 'font-medium'],
            $clases[$c->clase] ?? strtoupper($c->clase),
            $c->activa ? 'Activa' : 'Inactiva',
        ]),
        'vacio' => 'No hay categorías.',
    ])
</div>
@endsection
