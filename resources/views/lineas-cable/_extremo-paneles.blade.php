<div data-extremo-panel="{{ $lado }}" data-tipo="linea_cable" hidden>
    <select name="{{ $lado }}_id" class="w-full h-10 px-3 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm">
        @forelse($cables as $cable)
            <option value="{{ $cable->linea_cable_id }}" @selected($tipo === 'linea_cable' && (int) $id === (int) $cable->linea_cable_id)>
                Cable: {{ $cable->nombre ?: '#'.$cable->linea_cable_id }} · {{ $cable->cantidad_hilos }} FO
            </option>
        @empty
            <option value="" disabled>Todavía no hay otro cable</option>
        @endforelse
    </select>
</div>
<div data-extremo-panel="{{ $lado }}" data-tipo="caja_fusion" hidden>
    <select name="{{ $lado }}_id" class="w-full h-10 px-3 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm">
        @forelse($cajasFusion as $caja)
            <option value="{{ $caja->caja_fusion_id }}" @selected($tipo === 'caja_fusion' && (int) $id === (int) $caja->caja_fusion_id)>
                Fusión: {{ $caja->codigo }}@if($caja->ubicacion) ({{ $caja->ubicacion }})@endif
            </option>
        @empty
            <option value="" disabled>Todavía no hay caja de fusión</option>
        @endforelse
    </select>
</div>
