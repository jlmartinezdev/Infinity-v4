{{--
    Tabla simple de historial.
    columnas: lista de 'Etiqueta' o ['Etiqueta', 'right'].
    filas: lista de celdas; cada celda es texto o ['valor' => ..., 'clase' => ...].
    pie: (opcional) una fila igual que las otras, para totales.
--}}
<div class="mt-6 bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 overflow-hidden">
    @isset($titulo)
        <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-700/30">
            <h2 class="text-sm font-bold text-gray-900 dark:text-gray-100">{{ $titulo }}</h2>
            @isset($subtitulo)<p class="text-xs text-gray-500 dark:text-gray-400">{{ $subtitulo }}</p>@endisset
        </div>
    @endisset
    <div class="overflow-x-auto">
        <table class="min-w-full text-sm divide-y divide-gray-200 dark:divide-gray-700">
            <thead class="bg-gray-50 dark:bg-gray-700/50">
                <tr>
                    @foreach($columnas as $columna)
                        @php [$etiqueta, $alineado] = is_array($columna) ? $columna : [$columna, 'left']; @endphp
                        <th scope="col" class="px-4 py-2.5 text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 whitespace-nowrap {{ $alineado === 'right' ? 'text-right' : 'text-left' }}">{{ $etiqueta }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                @forelse($filas as $fila)
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/40">
                        @foreach($fila as $celda)
                            @php $esArreglo = is_array($celda); @endphp
                            <td class="px-4 py-2.5 text-gray-800 dark:text-gray-200 {{ $esArreglo ? ($celda['clase'] ?? '') : '' }}">{{ $esArreglo ? ($celda['valor'] ?? '') : (filled($celda) ? $celda : '—') }}</td>
                        @endforeach
                    </tr>
                @empty
                    <tr><td colspan="{{ count($columnas) }}" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">{{ $vacio ?? 'Sin registros.' }}</td></tr>
                @endforelse
            </tbody>
            @if(! empty($pie) && count($filas) > 0)
                <tfoot class="bg-gray-50 dark:bg-gray-700/50 font-semibold">
                    <tr>
                        @foreach($pie as $celda)
                            <td class="px-4 py-2.5 text-gray-900 dark:text-gray-100 {{ is_array($celda) ? ($celda['clase'] ?? '') : '' }}">{{ is_array($celda) ? ($celda['valor'] ?? '') : $celda }}</td>
                        @endforeach
                    </tr>
                </tfoot>
            @endif
        </table>
    </div>
</div>
