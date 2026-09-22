@php
    $perfil = (string) ($perfil ?? '');
    $vista = is_array($vista ?? null) ? $vista : [
        'etiqueta' => '',
        'porcentaje' => 0,
        'centro_num' => '0',
        'centro_unit' => 'MB',
        'tiene_cuota' => false,
    ];
    $etiqueta = (string) ($vista['etiqueta'] ?? '');
    $tieneCuota = (bool) ($vista['tiene_cuota'] ?? false);
    $centroPct = (bool) ($centroPct ?? false);
    $pct = (float) ($vista['porcentaje'] ?? 0);
    $ariaParts = [];
    if ($perfil !== '') {
        $ariaParts[] = $perfil;
    }
    if ($tieneCuota && $centroPct) {
        $ariaParts[] = ((int) round($pct)).' %';
    }
    if ($etiqueta !== '') {
        $ariaParts[] = $etiqueta;
    }
    $aria = implode(' · ', $ariaParts);
@endphp
<div class="flex items-center gap-2 min-w-0"@if($aria !== '') role="group" aria-label="{{ $aria }}"@endif>
    @if($tieneCuota)
        @include('hotspot._cuota-ring', [
            'pct' => $pct,
            'num' => $centroPct ? (string) (int) round($pct) : ($vista['centro_num'] ?? '0'),
            'unit' => $centroPct ? '%' : ($vista['centro_unit'] ?? 'MB'),
            'size' => 'table',
        ])
    @endif
    <div class="min-w-0">
        @if($perfil !== '')
            <span class="block truncate max-w-[8rem] text-gray-900 dark:text-gray-100" title="{{ $perfil }}">{{ $perfil }}</span>
        @endif
        @if($etiqueta !== '')
            <span class="block text-xs text-gray-500 dark:text-gray-400 tabular-nums">{{ $etiqueta }}</span>
        @elseif($perfil === '')
            <span class="text-gray-500 dark:text-gray-400">—</span>
        @endif
    </div>
</div>
