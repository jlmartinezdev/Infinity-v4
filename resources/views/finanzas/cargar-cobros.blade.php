@extends('layouts.app')
@section('title', 'Cobros del mes')
@section('content')
@php $fmt = fn ($v) => number_format((float) $v, 0, ',', '.'); @endphp
<div class="max-w-4xl mx-auto">
    @include('finanzas._nav')
    <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100 mb-2">Cobros del mes en el libro</h1>
    <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
        Carga los recibos ya registrados cuya fecha de pago cae en el mes.
        No repite los que ya están en el libro.
        @if($corte)
            Solo entran los pagos desde el {{ $corte->format('d/m/Y') }}.
        @else
            La fecha de corte está vacía, así que ningún recibo puede entrar hasta definirla en parámetros financieros.
        @endif
    </p>
    @if(session('success'))<div class="mb-4 p-3 rounded-lg bg-green-100 text-green-800 text-sm">{{ session('success') }}</div>@endif
    @foreach(session('errores_carga', []) as $error)
        <div class="mb-2 p-3 rounded-lg bg-red-100 text-red-800 text-sm">{{ $error }}</div>
    @endforeach

    <form method="get" action="{{ route('finanzas.cobros-mes') }}" class="mb-4 flex items-end gap-3">
        <div>
            <label class="text-sm text-gray-700 dark:text-gray-300" for="mes">Mes</label>
            <input id="mes" type="month" name="mes" value="{{ $mes }}" class="mt-1 px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-sm">
        </div>
        <button class="px-4 py-2 rounded-lg bg-gray-200 dark:bg-gray-700 text-sm">Ver</button>
    </form>

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-6">
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4">
            <p class="text-xs text-gray-500">Recibos del mes</p>
            <p class="text-lg font-semibold">{{ $fmt($previa['total']) }}</p>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4">
            <p class="text-xs text-gray-500">Ya en el libro</p>
            <p class="text-lg font-semibold">{{ $fmt($previa['ya_estaban']) }}</p>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4">
            <p class="text-xs text-gray-500">Por cargar</p>
            <p class="text-lg font-semibold">{{ $fmt($previa['pendientes']) }}</p>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4">
            <p class="text-xs text-gray-500">Monto por cargar</p>
            <p class="text-lg font-semibold">{{ $fmt($previa['monto_pendiente']) }}</p>
        </div>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-x-auto mb-6">
        <table class="min-w-full text-sm">
            <thead class="text-left text-gray-500">
                <tr><th class="px-3 py-2">Cuenta</th><th class="px-3 py-2">Recibos</th><th class="px-3 py-2">Monto</th></tr>
            </thead>
            <tbody>
                @forelse($previa['por_cuenta'] as $codigo => $fila)
                    <tr class="border-t border-gray-200 dark:border-gray-700">
                        <td class="px-3 py-2">{{ $cuentas[$codigo] ?? $codigo }}</td>
                        <td class="px-3 py-2">{{ $fmt($fila['cantidad']) }}</td>
                        <td class="px-3 py-2">{{ $fmt($fila['monto']) }}</td>
                    </tr>
                @empty
                    <tr><td class="px-3 py-3 text-gray-500" colspan="3">No hay recibos pendientes de cargar en este mes.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($previa['antes_de_corte'] > 0)
        <p class="text-sm text-gray-600 dark:text-gray-400 mb-2">{{ $fmt($previa['antes_de_corte']) }} recibos son anteriores a la fecha de corte y no entran.</p>
    @endif
    @if($previa['sin_cuenta'] > 0)
        <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">{{ $fmt($previa['sin_cuenta']) }} recibos no tienen cuenta asignada en los parámetros financieros.</p>
    @endif

    @if($puedeCargar && $previa['pendientes'] > 0)
        <form method="post" action="{{ route('finanzas.cobros-mes.store') }}">
            @csrf
            <input type="hidden" name="mes" value="{{ $mes }}">
            <button class="px-4 py-2 rounded-lg bg-emerald-600 text-white text-sm">Cargar {{ $fmt($previa['pendientes']) }} recibos en el libro</button>
        </form>
    @endif
</div>
@endsection
