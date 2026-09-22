@extends('layouts.app')

@section('title', 'Mapa de puntos Hotspot')

@section('content')
<div class="max-w-full mx-auto flex flex-col h-[calc(100vh-8rem)] min-h-[400px]">
    <div class="mb-4 flex-shrink-0 flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">Puntos Hotspot (ONU)</h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                Servicios marcados como emisor. Ubicación: caja NAP si hay GPS; si no, la del cliente.
            </p>
            <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                En mapa: <strong>{{ count($puntos) }}</strong>
                @if(count($sinCoordenadas) > 0)
                    · Sin coordenadas: {{ count($sinCoordenadas) }}
                @endif
            </p>
        </div>
        <a href="{{ route('hotspot.index') }}" class="inline-flex items-center px-4 py-2 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 text-gray-800 dark:text-gray-200 rounded-lg font-medium hover:bg-gray-50 dark:hover:bg-gray-700">
            Usuarios hotspot
        </a>
    </div>

    <div class="flex-1 min-h-0 grid grid-cols-1 lg:grid-cols-[minmax(0,1fr)_20rem] gap-3">
        <div class="relative rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden bg-gray-100 dark:bg-gray-800 min-h-[320px]">
            <div id="hs-mapa" class="absolute inset-0"></div>
            @if(empty($googleMapsApiKey))
                <div class="absolute inset-0 flex items-center justify-center bg-amber-50/90 dark:bg-amber-900/20 p-4">
                    <p class="text-amber-800 dark:text-amber-200 text-center text-sm">Falta configurar GOOGLE_MAPS_API_KEY en .env</p>
                </div>
            @endif
        </div>
        <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 overflow-y-auto max-h-[calc(100vh-12rem)]">
            <p class="px-3 py-2 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 border-b border-gray-200 dark:border-gray-700">Puntos</p>
            <ul class="divide-y divide-gray-100 dark:divide-gray-700">
                @forelse($puntos as $p)
                    <li>
                        <button type="button" data-hs-focus="{{ $p['servicio_id'] }}"
                            class="w-full text-left px-3 py-2 hover:bg-purple-50 dark:hover:bg-purple-900/20">
                            <span class="block text-sm font-medium text-gray-900 dark:text-gray-100">{{ $p['nombre'] }}</span>
                            <span class="block text-xs text-gray-500 dark:text-gray-400">
                                #{{ $p['servicio_id'] }}
                                @if($p['cedula']) · {{ $p['cedula'] }} @endif
                                @if($p['nap']) · NAP {{ $p['nap'] }} @endif
                                · {{ $p['usuarios'] }}/3
                            </span>
                        </button>
                    </li>
                @empty
                    <li class="px-3 py-6 text-sm text-gray-500 dark:text-gray-400 text-center">No hay puntos con coordenadas.</li>
                @endforelse
            </ul>
            @if(count($sinCoordenadas) > 0)
                <p class="px-3 py-2 text-xs font-semibold uppercase tracking-wide text-amber-700 dark:text-amber-300 border-t border-gray-200 dark:border-gray-700">Sin GPS</p>
                <ul class="divide-y divide-gray-100 dark:divide-gray-700">
                    @foreach($sinCoordenadas as $p)
                        <li class="px-3 py-2">
                            <p class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ $p['nombre'] }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                #{{ $p['servicio_id'] }}
                                @if($p['url_cliente'])
                                    · <a href="{{ $p['url_cliente'] }}" class="text-purple-600 dark:text-purple-400 hover:underline">Ficha</a>
                                @endif
                            </p>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
window.__HS_MAPA_PUNTOS__ = @json($puntos);
</script>
@if(!empty($googleMapsApiKey))
<script src="https://maps.googleapis.com/maps/api/js?key={{ $googleMapsApiKey }}"></script>
<script>
(function() {
    var puntos = window.__HS_MAPA_PUNTOS__ || [];
    var el = document.getElementById('hs-mapa');
    if (!el || typeof google === 'undefined') return;

    var center = puntos.length
        ? { lat: puntos[0].lat, lng: puntos[0].lon }
        : { lat: -25.28646, lng: -57.647 };
    var map = new google.maps.Map(el, { zoom: puntos.length ? 13 : 11, center: center, mapTypeControl: false });
    var info = new google.maps.InfoWindow();
    var markers = {};

    function htmlEscape(s) {
        return String(s || '').replace(/[&<>"']/g, function(c) {
            return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[c];
        });
    }

    puntos.forEach(function(p) {
        var marker = new google.maps.Marker({
            map: map,
            position: { lat: p.lat, lng: p.lon },
            title: p.nombre,
            icon: {
                path: google.maps.SymbolPath.CIRCLE,
                scale: 8,
                fillColor: '#9333ea',
                fillOpacity: 1,
                strokeColor: '#fff',
                strokeWeight: 2
            }
        });
        marker.addListener('click', function() {
            var cuerpo = '<div style="min-width:180px;font-size:13px">'
                + '<strong>' + htmlEscape(p.nombre) + '</strong><br>'
                + (p.cedula ? 'CI ' + htmlEscape(p.cedula) + '<br>' : '')
                + 'Servicio #' + p.servicio_id + (p.plan ? ' · ' + htmlEscape(p.plan) : '') + '<br>'
                + (p.nap ? 'NAP ' + htmlEscape(p.nap) + '<br>' : '')
                + (p.onu ? 'ONU ' + htmlEscape(p.onu) + '<br>' : '')
                + 'Usuarios hotspot: ' + p.usuarios + ' / 3<br>'
                + (p.origen === 'nap' ? '<span style="color:#6b7280">GPS de la caja NAP</span><br>' : '')
                + (p.url_hotspot ? '<a href="' + p.url_hotspot + '">Usuarios</a> · ' : '')
                + (p.url_cliente ? '<a href="' + p.url_cliente + '">Ficha</a>' : '')
                + '</div>';
            info.setContent(cuerpo);
            info.open(map, marker);
        });
        markers[p.servicio_id] = marker;
    });

    if (puntos.length > 1) {
        var bounds = new google.maps.LatLngBounds();
        puntos.forEach(function(p) { bounds.extend({ lat: p.lat, lng: p.lon }); });
        map.fitBounds(bounds, 48);
    }

    document.querySelectorAll('[data-hs-focus]').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var id = btn.getAttribute('data-hs-focus');
            var marker = markers[id];
            if (!marker) return;
            map.panTo(marker.getPosition());
            map.setZoom(Math.max(map.getZoom(), 16));
            google.maps.event.trigger(marker, 'click');
        });
    });
})();
</script>
@endif
@endpush
