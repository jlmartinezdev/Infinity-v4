@php
    $qrSorteo = app(\App\Services\Sorteo\SorteoService::class)->muestraQrRecibo($cobro)
        ? \App\Services\Sorteo\SorteoQr::deCliente((int) $cobro->cliente_id)
        : null;
@endphp
@if($qrSorteo)
<div style="text-align:center;margin-top:8px;">
    <p style="margin:0 6px 6px;font-size:9px;font-weight:700;line-height:1.35;">¡Ya estás participando en el sorteo de Navidad!</p>
    <div style="width:120px;margin:0 auto;">{!! $qrSorteo['svg'] !!}</div>
    <p style="margin:6px 6px 0;font-size:9px;line-height:1.35;"><strong>Escaneá el código y sumá más chances:</strong> dejá tu reseña en Google y ganá un cupón extra.</p>
    <p style="margin:2px 0 0;font-size:8px;word-break:break-all;">{{ preg_replace('#^https?://#', '', $qrSorteo['url']) }}</p>
</div>
@endif
