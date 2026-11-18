@extends('layouts.app')
@section('title', 'Cierres')
@section('content')
@php
    $fmt = fn ($v) => number_format((float) $v, 0, ',', '.');
    $cls = 'mt-1 w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 text-sm';
    $lbl = 'block text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400';
    $colorDiferencia = fn ($v) => abs((float) $v) < 0.01 ? 'text-emerald-700 dark:text-emerald-400' : 'text-red-600 dark:text-red-400';
    $periodoTexto = $tipo === 'mensual' ? $desde->translatedFormat('F Y') : $desde->format('d/m/Y');
@endphp
<div class="max-w-5xl mx-auto">
    @include('finanzas._nav')
    <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100 mb-2">Cierres</h1>
    <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">Después del cierre, ese período no acepta cargas comunes. Una corrección posterior es un ajuste con auditoría.</p>
    @include('finanzas._mensajes')

    <form method="get" class="flex flex-col sm:flex-row sm:items-end gap-2 mb-4">
        <div>
            <label class="{{ $lbl }}" for="tipo">Tipo</label>
            <select id="tipo" name="tipo" class="{{ $cls }}"><option value="mensual" @selected($tipo === 'mensual')>Mensual</option><option value="diario" @selected($tipo === 'diario')>Diario</option></select>
        </div>
        <div>
            <label class="{{ $lbl }}" for="fecha-cierre">Fecha del período</label>
            <input id="fecha-cierre" type="date" name="fecha" value="{{ $fecha }}" class="{{ $cls }}">
        </div>
        <button class="px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 text-sm">Calcular</button>
    </form>

    @unless($periodoTerminado)
        <div class="mb-4 p-3 rounded-lg bg-amber-50 dark:bg-amber-900/30 border border-amber-200 dark:border-amber-800 text-amber-800 dark:text-amber-300 text-sm">
            <span class="capitalize">{{ $periodoTexto }}</span> todavía no terminó: se puede cerrar desde el {{ $hasta->copy()->addDay()->format('d/m/Y') }}. Mientras tanto, esto es solo una vista previa.
        </div>
    @endunless

    <form method="post" action="{{ route('finanzas.cierres.store') }}" class="space-y-4"
        onsubmit="return confirm('Se cierra {{ $tipo === 'mensual' ? 'el mes' : 'el día' }} {{ $periodoTexto }} en todas las cuentas. Después no se pueden cargar movimientos comunes en ese período. ¿Continuar?');">
        @csrf
        <input type="hidden" name="tipo" value="{{ $tipo }}">
        <input type="hidden" name="fecha" value="{{ $fecha }}">
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 overflow-x-auto">
            <table class="min-w-full text-sm divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-700/50">
                    <tr>
                        @foreach(['Cuenta', 'Inicial', 'Ingresos', 'Egresos', 'Entradas', 'Salidas', 'Retiros', 'Ajustes', 'Calculado'] as $i => $col)
                            <th class="px-3 py-2.5 text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 whitespace-nowrap {{ $i === 0 ? 'text-left' : 'text-right' }}">{{ $col }}</th>
                        @endforeach
                        <th class="px-3 py-2.5 text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 text-left">Saldo real contado</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @foreach($preview as $fila)
                        @php $d = $fila['desglose']; @endphp
                        <tr>
                            <td class="px-3 py-2 font-medium text-gray-900 dark:text-gray-100 whitespace-nowrap">{{ $fila['cuenta']->nombre }}</td>
                            @foreach([$fila['inicial'], $d['ingresos'], -$d['egresos'], $d['transferencias_entrada'], -$d['transferencias_salida'], -$d['retiros'], $d['ajustes']] as $valor)
                                <td class="px-3 py-2 text-right font-mono text-gray-700 dark:text-gray-300">{{ $fmt($valor) }}</td>
                            @endforeach
                            <td class="px-3 py-2 text-right font-mono font-semibold text-gray-900 dark:text-gray-100">{{ $fmt($fila['calculado']) }}</td>
                            <td class="px-3 py-2">
                                <input name="saldo_real[{{ $fila['cuenta']->id }}]" value="{{ old('saldo_real.'.$fila['cuenta']->id, $fmt($fila['calculado'])) }}" class="w-36 px-2 py-1.5 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 text-sm font-mono" @disabled(! $periodoTerminado)>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <p class="text-xs text-gray-500 dark:text-gray-400">El saldo real viene precargado con el calculado. Corregilo con lo que contaste o con el extracto: la diferencia queda registrada en el cierre.</p>
        <div class="flex justify-end">
            <button class="px-4 py-2 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold disabled:opacity-50" @disabled(! $periodoTerminado)>Cerrar <span class="capitalize">{{ $periodoTexto }}</span></button>
        </div>
    </form>

    @include('finanzas._tabla', [
        'titulo' => 'Últimos cierres',
        'columnas' => ['Período', 'Tipo', 'Cuenta', ['Calculado', 'right'], ['Real', 'right'], ['Diferencia', 'right']],
        'filas' => $filas->map(fn ($fila) => [
            $fila->tipo === 'mensual' ? ucfirst($fila->periodo_desde?->translatedFormat('F Y') ?? '') : $fila->periodo_desde?->format('d/m/Y'),
            ucfirst($fila->tipo),
            $fila->cuenta?->nombre,
            ['valor' => $fmt($fila->saldo_calculado), 'clase' => 'text-right font-mono'],
            ['valor' => $fmt($fila->saldo_real), 'clase' => 'text-right font-mono'],
            ['valor' => $fmt($fila->diferencia), 'clase' => 'text-right font-mono font-semibold '.$colorDiferencia($fila->diferencia)],
        ]),
        'vacio' => 'Todavía no hay cierres.',
    ])
</div>
@endsection
