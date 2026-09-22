@php
    $pct = max(0, min(100, (float) ($pct ?? 0)));
    $num = (string) ($num ?? '0');
    $unit = (string) ($unit ?? 'MB');
    $size = (string) ($size ?? 'slot');
    $tone = $pct >= 90 ? 'is-danger' : ($pct >= 70 ? 'is-warn' : 'is-ok');
    $resto = 100 - $pct;
@endphp
<span class="hotspot-cuota hotspot-cuota--{{ $size }} {{ $tone }}" @if(! empty($id)) id="{{ $id }}" @endif aria-hidden="true">
    <svg viewBox="0 0 36 36" class="hotspot-cuota-svg" focusable="false">
        <circle class="hotspot-cuota-track" cx="18" cy="18" r="15.9155" fill="none" pathLength="100"></circle>
        <circle class="hotspot-cuota-fill" cx="18" cy="18" r="15.9155" fill="none" pathLength="100"
            stroke-dasharray="{{ $pct }} {{ $resto }}" transform="rotate(-90 18 18)"></circle>
    </svg>
    <span class="hotspot-cuota-center">
        <span class="hotspot-cuota-num">{{ $num }}</span>
        <span class="hotspot-cuota-unit">{{ $unit }}</span>
    </span>
</span>
