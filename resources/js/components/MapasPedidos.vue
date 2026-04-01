<template>
  <div class="relative w-full h-full min-h-[300px]">
    <div ref="mapContainer" class="absolute inset-0 w-full h-full rounded-lg"></div>
    <div v-if="loading" class="absolute inset-0 flex items-center justify-center bg-gray-100/80 dark:bg-gray-800/80 rounded-lg">
      <span class="text-gray-600 dark:text-gray-400">Cargando mapa...</span>
    </div>
    <div v-if="error" class="absolute inset-0 flex items-center justify-center bg-red-50/90 dark:bg-red-900/20 rounded-lg p-4">
      <p class="text-red-700 dark:text-red-300 text-center">{{ error }}</p>
    </div>
    <div v-if="!apiKey" class="absolute inset-0 flex items-center justify-center bg-amber-50/90 dark:bg-amber-900/20 rounded-lg p-4">
      <p class="text-amber-800 dark:text-amber-200 text-center">Falta configurar GOOGLE_MAPS_API_KEY en .env</p>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, onBeforeUnmount } from 'vue';

const props = defineProps({
  apiKey: { type: String, default: '' },
  pedidos: { type: Array, default: () => [] },
});

const mapContainer = ref(null);
const loading = ref(true);
const error = ref('');
let map = null;
let markers = [];
let infoWindows = [];

function isGpon(desc) {
  if (!desc || typeof desc !== 'string') return false;
  const d = desc.toLowerCase();
  return /gpon|epon|ftth|fibra|fiber|pon|xg-pon/i.test(d);
}

function isWireless(desc) {
  if (!desc || typeof desc !== 'string') return false;
  const d = desc.toLowerCase();
  return /wireless|inalambr|anten|radio|wifi/i.test(d);
}

function getMarkerIcon(google, tecnologiaDesc = '') {
  const color = '#6366f1';
  const size = new google.maps.Size(32, 32);
  const anchor = new google.maps.Point(16, 32);
  if (isGpon(tecnologiaDesc)) {
    return {
      url: 'data:image/svg+xml;charset=UTF-8,' + encodeURIComponent(
        '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="' + color + '"><rect width="7" height="5" rx="0.6" transform="matrix(1 0 0 -1 3 22)" stroke="#000000" stroke-width="1.5"/><rect width="7" height="5" rx="0.6" transform="matrix(1 0 0 -1 8.5 7)" stroke="#000000" stroke-width="1.5"/><rect width="7" height="5" rx="0.6" transform="matrix(1 0 0 -1 14 22)" stroke="#000000" stroke-width="1.5"/><path d="M6.5 17V13.5C6.5 12.3954 7.39543 11.5 8.5 11.5H15.5C16.6046 11.5 17.5 12.3954 17.5 13.5V17" stroke="#000000" stroke-width="1.5"/><path d="M12 11.5V7" stroke="#000000" stroke-width="1.5"/></svg>'
      ),
      scaledSize: size,
      anchor,
    };
  }
  if (isWireless(tecnologiaDesc)) {
    return {
      url: 'data:image/svg+xml;charset=UTF-8,' + encodeURIComponent(
        '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"  fill="'+color+'"><path d="M13.68 24.8h-2.28v-11.56c0-0.48-0.36-0.84-0.84-0.84s-0.84 0.36-0.84 0.84v11.56h-2.28c-0.48 0-0.84 0.36-0.84 0.84s0.36 0.84 0.84 0.84h6.24c0.44 0 0.84-0.4 0.84-0.84 0-0.48-0.36-0.84-0.84-0.84zM12.88 16.4c-0.2 0-0.44-0.080-0.6-0.24-0.32-0.32-0.32-0.84 0-1.2 0.48-0.48 0.72-1.080 0.72-1.72s-0.24-1.28-0.72-1.72c-0.32-0.32-0.32-0.84 0-1.2 0.32-0.32 0.84-0.32 1.2 0 0.76 0.76 1.2 1.8 1.2 2.92s-0.44 2.12-1.2 2.92c-0.16 0.16-0.4 0.24-0.6 0.24zM15.2 18.72c-0.2 0-0.44-0.080-0.6-0.24-0.32-0.32-0.32-0.84 0-1.2 1.080-1.080 1.68-2.52 1.68-4.040s-0.6-2.96-1.68-4.080c-0.32-0.32-0.32-0.84 0-1.2 0.32-0.32 0.84-0.32 1.2 0 1.4 1.4 2.16 3.28 2.16 5.24 0 2-0.76 3.84-2.16 5.24-0.16 0.2-0.36 0.28-0.6 0.28zM17.44 20.96c-0.2 0-0.44-0.080-0.6-0.24-0.32-0.32-0.32-0.84 0-1.2 1.68-1.68 2.6-3.92 2.6-6.28s-0.92-4.6-2.6-6.28c-0.32-0.32-0.32-0.84 0-1.2 0.32-0.32 0.84-0.32 1.2 0 2 2 3.080 4.64 3.080 7.48 0 2.8-1.080 5.48-3.080 7.48-0.2 0.16-0.4 0.24-0.6 0.24zM7.64 16.16c-0.76-0.8-1.2-1.8-1.2-2.92s0.44-2.16 1.2-2.92c0.36-0.32 0.88-0.32 1.2 0 0.32 0.36 0.32 0.88 0 1.2-0.48 0.44-0.72 1.080-0.72 1.72s0.24 1.24 0.72 1.72c0.32 0.36 0.32 0.88 0 1.2-0.16 0.16-0.4 0.24-0.6 0.24s-0.44-0.080-0.6-0.24zM5.32 18.44c-1.4-1.4-2.16-3.24-2.16-5.24 0-1.96 0.76-3.84 2.16-5.24 0.36-0.32 0.88-0.32 1.2 0 0.32 0.36 0.32 0.88 0 1.2-1.080 1.12-1.68 2.56-1.68 4.080s0.6 2.96 1.68 4.040c0.32 0.36 0.32 0.88 0 1.2-0.16 0.16-0.4 0.24-0.6 0.24-0.24 0-0.44-0.080-0.6-0.28zM3.080 20.72c-2-2-3.080-4.68-3.080-7.48 0-2.84 1.080-5.48 3.080-7.48 0.36-0.32 0.88-0.32 1.2 0 0.32 0.36 0.32 0.88 0 1.2-1.68 1.68-2.6 3.92-2.6 6.28s0.92 4.6 2.6 6.28c0.32 0.36 0.32 0.88 0 1.2-0.16 0.16-0.4 0.24-0.6 0.24s-0.4-0.080-0.6-0.24z"/></svg>'
      ),
      scaledSize: size,
      anchor,
    };
  }
  return null;
}

function loadGoogleMaps() {
  return new Promise((resolve, reject) => {
    if (typeof window.google !== 'undefined' && window.google.maps) {
      resolve(window.google);
      return;
    }
    const scriptId = 'google-maps-api-mapas-pedidos';
    if (document.getElementById(scriptId)) {
      const check = setInterval(() => {
        if (window.google?.maps) {
          clearInterval(check);
          resolve(window.google);
        }
      }, 100);
      return;
    }
    window.__mapasPedidosMapsReady__ = () => {
      resolve(window.google);
    };
    const script = document.createElement('script');
    script.id = scriptId;
    script.src = `https://maps.googleapis.com/maps/api/js?key=${encodeURIComponent(props.apiKey)}&callback=__mapasPedidosMapsReady__`;
    script.async = true;
    script.defer = true;
    script.onerror = () => reject(new Error('Error al cargar Google Maps'));
    document.head.appendChild(script);
  });
}

function initMap(google) {
  if (!mapContainer.value) return;

  const center = props.pedidos.length
    ? { lat: props.pedidos[0].lat, lng: props.pedidos[0].lon }
    : { lat: -25.2637, lng: -57.5759 }; // Paraguay por defecto

  map = new google.maps.Map(mapContainer.value, {
    center,
    zoom: props.pedidos.length ? 10 : 6,
    mapTypeControl: true,
    streetViewControl: true,
    fullscreenControl: true,
    zoomControl: true,
  });

  const bounds = new google.maps.LatLngBounds();

  props.pedidos.forEach((pedido) => {
    const position = { lat: pedido.lat, lng: pedido.lon };
    const iconConfig = getMarkerIcon(google, pedido.tecnologia_descripcion);
    const marker = new google.maps.Marker({
      position,
      map,
      title: pedido.cliente || `Pedido #${pedido.pedido_id}`,
      ...(iconConfig && { icon: iconConfig }),
    });

    const content = `
      <div class="p-2 min-w-[200px] max-w-[320px]">
        <div class="font-semibold text-gray-900">Pedido #${pedido.pedido_id}</div>
        ${pedido.cliente ? `<div class="text-sm text-gray-700">${escapeHtml(pedido.cliente)}</div>` : ''}
        ${pedido.tecnologia_descripcion ? `<div class="text-xs text-indigo-600 mt-0.5">${escapeHtml(pedido.tecnologia_descripcion)}</div>` : ''}
        ${pedido.ubicacion ? `<div class="text-sm text-gray-600 mt-1">${escapeHtml(pedido.ubicacion)}</div>` : ''}
        ${pedido.plan ? `<div class="text-xs text-gray-500 mt-1">Plan: ${escapeHtml(pedido.plan)}</div>` : ''}
        ${pedido.fecha_pedido ? `<div class="text-xs text-gray-500">${pedido.fecha_pedido}</div>` : ''}
        ${pedido.maps_gps ? `<a href="${escapeHtml(pedido.maps_gps)}" target="_blank" rel="noopener" class="inline-block mt-2 text-sm text-blue-600 hover:underline">Abrir en Google Maps</a>` : ''}
      </div>
    `;

    const infoWindow = new google.maps.InfoWindow({ content });

    marker.addListener('click', () => {
      infoWindows.forEach((iw) => iw.close());
      infoWindow.open(map, marker);
    });

    markers.push(marker);
    infoWindows.push(infoWindow);
    bounds.extend(position);
  });

  if (props.pedidos.length > 1) {
    map.fitBounds(bounds);
  }
}

function escapeHtml(text) {
  if (!text) return '';
  const div = document.createElement('div');
  div.textContent = text;
  return div.innerHTML;
}

onMounted(async () => {
  if (!props.apiKey) {
    loading.value = false;
    return;
  }
  try {
    // Con 0 pedidos se muestra mapa centrado en Paraguay
    const google = await loadGoogleMaps();
    initMap(google);
  } catch (e) {
    error.value = e.message || 'Error al cargar el mapa';
  } finally {
    loading.value = false;
  }
});

onBeforeUnmount(() => {
  infoWindows.forEach((iw) => iw.close());
  markers.forEach((m) => m.setMap(null));
  markers = [];
  infoWindows = [];
  map = null;
});
</script>
