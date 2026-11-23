@php
    $sorteo = $sorteo ?? null;
    $sorteoModo = $sorteoModo ?? 'ambos';
@endphp
@if($sorteo)
@once
<style>
.sorteo-corte { display: flex; align-items: center; gap: 6px; margin: 10px 0 6px; }
.sorteo-corte .puntos { flex: 1; border-top: 1px dotted #111; height: 0; }
.recibo-modo-wrapper[data-recibo-modo="con_grafico"] .sorteo-ascii { display: none !important; }
.recibo-modo-wrapper:not([data-recibo-modo="con_grafico"]) .sorteo-svg { display: none !important; }
.sorteo-cupon { text-align: center; font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; font-size: 12px; color: #111; line-height: 1.35; }
.sorteo-cupon .titulo { font-weight: 700; letter-spacing: 0.02em; }
.sorteo-cupon .numero { font-weight: 700; font-size: 14px; margin-top: 4px; }
</style>
@endonce
<div class="sorteo-cupon">
    @if($sorteoModo !== 'ascii')
    <div class="sorteo-corte sorteo-svg">
        <svg width="16" height="14" viewBox="0 0 16 14" aria-hidden="true">
            <circle cx="3.2" cy="3" r="2" fill="none" stroke="#111" stroke-width="1.2"/>
            <circle cx="3.2" cy="11" r="2" fill="none" stroke="#111" stroke-width="1.2"/>
            <path d="M4.8 4.2 L14 12.2 M4.8 9.8 L14 1.8" stroke="#111" stroke-width="1.2" fill="none"/>
        </svg>
        <span class="puntos"></span>
    </div>
    @endif
    @if($sorteoModo !== 'svg')
    <p class="sorteo-ascii" style="margin:10px 0 6px; letter-spacing:1px;">8&lt; . . . . . . . . . . . . . .</p>
    @endif
    <p class="titulo">{{ $sorteo['texto'] }}</p>
    <p>{{ $sorteo['fecha_sorteo'] }}</p>
    <p class="numero">TICKET NRO {{ $sorteo['numero'] }}</p>
    @if(!empty($sorteo['app']))
        <p class="numero">TICKET NRO {{ $sorteo['app'] }}</p>
    @endif
    @if(!empty($sorteo['extra']))
        <p class="numero">TICKET EXTRA {{ $sorteo['extra'] }}</p>
    @endif
    <p>NOMBRE: {{ $sorteo['nombre'] }}</p>
    <p>CI: {{ $sorteo['cedula'] !== '' ? $sorteo['cedula'] : '—' }}</p>
    <p>FECHA: {{ $sorteo['fecha'] }}</p>
    @if(!empty($sorteo['extra']))
        @if($sorteoModo !== 'ascii')
        <div class="sorteo-corte sorteo-svg">
            <svg width="16" height="14" viewBox="0 0 16 14" aria-hidden="true">
                <circle cx="3.2" cy="3" r="2" fill="none" stroke="#111" stroke-width="1.2"/>
                <circle cx="3.2" cy="11" r="2" fill="none" stroke="#111" stroke-width="1.2"/>
                <path d="M4.8 4.2 L14 12.2 M4.8 9.8 L14 1.8" stroke="#111" stroke-width="1.2" fill="none"/>
            </svg>
            <span class="puntos"></span>
        </div>
        @endif
        @if($sorteoModo !== 'svg')
        <p class="sorteo-ascii" style="margin:10px 0 6px; letter-spacing:1px;">8&lt; . . . . . . . . . . . . . .</p>
        @endif
        <p class="titulo">CORTE Y RETENER EN CAJA</p>
        <p>LLAMAR POR EL PREMIO</p>
        <p>CI: {{ $sorteo['cedula'] !== '' ? $sorteo['cedula'] : '—' }}</p>
        <p>CEL: {{ $sorteo['telefono'] }}</p>
        <p>NOMBRE: {{ $sorteo['nombre'] }}</p>
        <p>TICKET: {{ $sorteo['numero'] }}@if(!empty($sorteo['app'])) / {{ $sorteo['app'] }}@endif</p>
        <p>EXTRA: {{ $sorteo['extra'] }}</p>
    @endif
</div>
@endif
