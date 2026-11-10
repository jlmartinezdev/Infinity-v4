@php
    $vista = is_array($vista ?? null) ? $vista : [];
    $perfil = trim((string) ($perfil ?? ''));
    $esTrial = (bool) ($trial ?? false);
    $trialResta = trim((string) ($trialResta ?? ''));
    $etiqueta = (string) ($vista['etiqueta'] ?? '');
    $tieneCuota = (bool) ($vista['tiene_cuota'] ?? false);
    $usado = trim(($vista['centro_num'] ?? '0').' '.($vista['centro_unit'] ?? 'MB'));
    $total = $tieneCuota && str_contains($etiqueta, ' / ')
        ? \Illuminate\Support\Str::after($etiqueta, ' / ')
        : '';
    $pct = (int) round(max(0, min(100, (float) ($vista['porcentaje'] ?? 0))));
    $tono = \App\Services\RadiusHotspotSyncService::tonoCuota($pct);
@endphp
@if($esTrial)
    <span class="block text-sm text-gray-500 dark:text-gray-400">Sin plan · tiempo de prueba</span>
    @if($trialResta !== '')
        <span class="block text-xs text-gray-500 dark:text-gray-400 tabular-nums">{{ $trialResta }}</span>
    @endif
@elseif($etiqueta === '')
    <span class="text-gray-500 dark:text-gray-400">—</span>
@elseif($tieneCuota)
    <div class="hsd-cuota">
        <div class="flex items-baseline justify-between gap-3 text-sm tabular-nums">
            <span class="text-gray-900 dark:text-gray-100">{{ $usado }}@if($total !== '') <span class="text-gray-500 dark:text-gray-400">/ {{ $total }}</span>@endif</span>
            <span class="font-semibold hsd-cuota-pct is-{{ $tono }}">{{ $pct }} %</span>
        </div>
        <div class="hsd-bar" aria-hidden="true"><div class="hsd-bar-fill is-{{ $tono }}" style="width: {{ $pct }}%"></div></div>
        @if($perfil !== '')
            <span class="block mt-1 text-xs text-gray-500 dark:text-gray-400 truncate" title="{{ $perfil }}">{{ $perfil }}</span>
        @endif
    </div>
@else
    <span class="text-sm tabular-nums text-gray-900 dark:text-gray-100">{{ $usado }}<span class="text-gray-500 dark:text-gray-400"> · Ilimitado</span></span>
    @if($perfil !== '')
        <span class="block text-xs text-gray-500 dark:text-gray-400 truncate" title="{{ $perfil }}">{{ $perfil }}</span>
    @endif
@endif
