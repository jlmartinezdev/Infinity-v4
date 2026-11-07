@php
    $fmt = $fmt ?? fn ($valor) => number_format((float) $valor, 0, ',', '.');
    $pct = $pct ?? fn ($valor) => number_format((float) $valor, 1, ',', '.').'%';
@endphp
<div class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 p-4 sm:p-6 mt-6">
    <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-1">{{ $titulo }}</h2>
    <p class="text-xs text-gray-500 dark:text-gray-400 mb-4">{{ $ayuda }}</p>
    <div class="overflow-x-auto">
        <table class="min-w-full text-sm divide-y divide-gray-200 dark:divide-gray-700">
            <thead class="bg-gray-50 dark:bg-gray-700/50">
                <tr>
                    <th class="px-3 py-2 text-left font-medium text-gray-600 dark:text-gray-300">Ciclo</th>
                    @if($modo === 'especial')
                        <th class="px-3 py-2 text-right font-medium text-gray-600 dark:text-gray-300">Ancladas</th>
                        <th class="px-3 py-2 text-right font-medium text-gray-600 dark:text-gray-300">Cobradas</th>
                        <th class="px-3 py-2 text-right font-medium text-gray-600 dark:text-gray-300">Sin cobro</th>
                    @else
                        <th class="px-3 py-2 text-right font-medium text-gray-600 dark:text-gray-300">Facturas</th>
                    @endif
                    <th class="px-3 py-2 text-right font-medium text-gray-600 dark:text-gray-300">Facturado</th>
                    <th class="px-3 py-2 text-right font-medium text-gray-600 dark:text-gray-300">Notas</th>
                    <th class="px-3 py-2 text-right font-medium text-gray-600 dark:text-gray-300">Cobrado</th>
                    <th class="px-3 py-2 text-right font-medium text-gray-600 dark:text-gray-300">Pendiente</th>
                    @if($modo === 'consolidado')
                        <th class="px-3 py-2 text-right font-medium text-gray-600 dark:text-gray-300">Saldo a favor</th>
                    @endif
                    <th class="px-3 py-2 text-right font-medium text-gray-600 dark:text-gray-300">Cobranza</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                @forelse($filas as $fila)
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/40">
                        <td class="px-3 py-2 text-gray-900 dark:text-gray-100 whitespace-nowrap">{{ $fila['etiqueta'] }}</td>
                        @if($modo === 'especial')
                            <td class="px-3 py-2 text-right">{{ $fmt($fila['cantidad']) }}</td>
                            <td class="px-3 py-2 text-right">{{ $fmt($fila['cobradas']) }}</td>
                            <td class="px-3 py-2 text-right">{{ $fmt($fila['sin_cobro']) }}</td>
                        @else
                            <td class="px-3 py-2 text-right">{{ $fmt($fila['cantidad']) }}</td>
                        @endif
                        <td class="px-3 py-2 text-right text-blue-700 dark:text-blue-400">{{ $fmt($fila['facturado']) }}</td>
                        <td class="px-3 py-2 text-right">{{ $fmt($fila['notas']) }}</td>
                        <td class="px-3 py-2 text-right text-green-700 dark:text-green-400">{{ $fmt($fila['aplicado']) }}</td>
                        <td class="px-3 py-2 text-right text-amber-700 dark:text-amber-400">{{ $fmt($fila['pendiente']) }}</td>
                        @if($modo === 'consolidado')
                            <td class="px-3 py-2 text-right text-purple-700 dark:text-purple-400">{{ $fmt($fila['saldo_favor']) }}</td>
                        @endif
                        <td class="px-3 py-2 text-right">{{ $pct($fila['porcentaje']) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="px-3 py-6 text-center text-gray-500">Sin datos en el rango.</td>
                    </tr>
                @endforelse
                @if($modo === 'consolidado' && !empty($totales))
                    <tr class="bg-gray-50 dark:bg-gray-700/40 font-semibold">
                        <td class="px-3 py-2 text-gray-900 dark:text-gray-100">Total</td>
                        <td class="px-3 py-2 text-right">{{ $fmt($totales['cantidad']) }}</td>
                        <td class="px-3 py-2 text-right text-blue-700 dark:text-blue-400">{{ $fmt($totales['facturado']) }}</td>
                        <td class="px-3 py-2 text-right">{{ $fmt($totales['notas']) }}</td>
                        <td class="px-3 py-2 text-right text-green-700 dark:text-green-400">{{ $fmt($totales['aplicado']) }}</td>
                        <td class="px-3 py-2 text-right text-amber-700 dark:text-amber-400">{{ $fmt($totales['pendiente']) }}</td>
                        <td class="px-3 py-2 text-right text-purple-700 dark:text-purple-400">{{ $fmt($totales['saldo_favor']) }}</td>
                        <td class="px-3 py-2 text-right">{{ $pct($totales['porcentaje']) }}</td>
                    </tr>
                @endif
            </tbody>
        </table>
    </div>
</div>
