<template>
  <div class="relative w-full h-full min-h-[300px]">
    <div ref="mapContainer" class="absolute inset-0 w-full h-full rounded-lg"></div>
    <div v-if="loading" class="absolute inset-0 flex items-center justify-center bg-gray-100/80 dark:bg-gray-800/80 rounded-lg">
      <div class="w-[min(360px,92%)] px-4">
        <p class="text-center text-gray-700 dark:text-gray-300">{{ loadingMessage }}</p>
        <div v-if="totalPuntos > 0" class="mt-3">
          <div class="h-2 bg-gray-300 dark:bg-gray-700 rounded-full overflow-hidden">
            <div class="h-full bg-purple-600 transition-all duration-200" :style="{ width: `${progressPercent}%` }"></div>
          </div>
          <p class="mt-2 text-xs text-center text-gray-600 dark:text-gray-400">
            {{ puntosProcesados }} / {{ totalPuntos }} clientes ({{ progressPercent }}%)
          </p>
        </div>
      </div>
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
import { computed, ref, onMounted, onBeforeUnmount } from 'vue';

const props = defineProps({
  apiKey: { type: String, default: '' },
  puntos: { type: Array, default: () => [] },
  urlDetalleClienteBase: { type: String, default: '' },
});

const mapContainer = ref(null);
const loading = ref(true);
const loadingMessage = ref('Cargando mapa...');
const puntosProcesados = ref(0);
const totalPuntos = ref(0);
const error = ref('');
let map = null;
let markers = [];
let sharedInfoWindow = null;
const progressPercent = computed(() => {
  if (!totalPuntos.value) return 0;
  return Math.round((puntosProcesados.value / totalPuntos.value) * 100);
});

function getHomeMarkerIcon(google, color = '#9333ea') {
  // Lienzo con margen transparente: sin esto Maps suele rasterizar el SVG mal y recorta un lateral.
  const w = 48;
  const h = 48;
  const size = new google.maps.Size(w, h);
  const svg =
    '<svg xmlns="http://www.w3.org/2000/svg" width="' +
    w +
    '" height="' +
    h +
    '" viewBox="0 0 ' +
    w +
    ' ' +
    h +
    '">' +
    '<rect width="100%" height="100%" fill="none"/>' +
    '<g transform="translate(12,10)">' +
    '<path fill="' +
    color +
    '" stroke="#1f2937" stroke-width="0.45" stroke-linejoin="round" d="M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z"/>' +
    '</g></svg>';
  return {
    url: 'data:image/svg+xml;charset=UTF-8,' + encodeURIComponent(svg),
    size,
    scaledSize: size,
    origin: new google.maps.Point(0, 0),
    anchor: new google.maps.Point(24, 32),
  };
}

function markerColorByPlan(plan, tecnologia) {
  const text = `${tecnologia || ''} ${plan || ''}`.toLowerCase();
  if (text.includes('gpon')) return '#16a34a'; // verde
  if (text.includes('wireless')) return '#2563eb'; // azul
  return '#9333ea'; // por defecto
}

function loadGoogleMaps() {
  return new Promise((resolve, reject) => {
    if (typeof window.google !== 'undefined' && window.google.maps) {
      resolve(window.google);
      return;
    }
    const scriptId = 'google-maps-api-mapa-clientes-activos';
    if (document.getElementById(scriptId)) {
      const check = setInterval(() => {
        if (window.google?.maps) {
          clearInterval(check);
          resolve(window.google);
        }
      }, 100);
      return;
    }
    window.__mapaClientesActivosMapsReady__ = () => {
      resolve(window.google);
    };
    const script = document.createElement('script');
    script.id = scriptId;
    script.src = `https://maps.googleapis.com/maps/api/js?key=${encodeURIComponent(props.apiKey)}&callback=__mapaClientesActivosMapsReady__`;
    script.async = true;
    script.defer = true;
    script.onerror = () => reject(new Error('Error al cargar Google Maps'));
    document.head.appendChild(script);
  });
}

function urlDetalle(clienteId) {
  if (!props.urlDetalleClienteBase || !clienteId) return '';
  return props.urlDetalleClienteBase.replace('__id__', String(clienteId));
}

async function initMap(google) {
  if (!mapContainer.value) return;

  const center = props.puntos.length
    ? { lat: props.puntos[0].lat, lng: props.puntos[0].lon }
    : { lat: -25.2637, lng: -57.5759 };

  map = new google.maps.Map(mapContainer.value, {
    center,
    zoom: props.puntos.length ? 10 : 6,
    mapTypeControl: true,
    streetViewControl: true,
    fullscreenControl: true,
    zoomControl: true,
  });

  const bounds = new google.maps.LatLngBounds();
  const iconCache = new Map();
  sharedInfoWindow = new google.maps.InfoWindow();
  totalPuntos.value = props.puntos.length;
  puntosProcesados.value = 0;
  loadingMessage.value = totalPuntos.value
    ? 'Cargando clientes en el mapa...'
    : 'No hay clientes para mostrar';

  const batchSize = 120;
  for (let i = 0; i < props.puntos.length; i += batchSize) {
    const batch = props.puntos.slice(i, i + batchSize);

    batch.forEach((p) => {
      const position = { lat: p.lat, lng: p.lon };
      const titulo = p.nombre || `Cliente #${p.cliente_id}`;
      const marker = new google.maps.Marker({
        position,
        map,
        title: titulo,
        icon: (() => {
          const color = markerColorByPlan(p.plan, p.tecnologia);
          if (!iconCache.has(color)) {
            iconCache.set(color, getHomeMarkerIcon(google, color));
          }
          return iconCache.get(color);
        })(),
        optimized: true,
      });

      const detalleHref = urlDetalle(p.cliente_id);
      const content = `
        <div class="p-2 min-w-[200px] max-w-[320px]">
          <div class="font-semibold text-gray-900">${escapeHtml(titulo)}</div>
          ${p.plan ? `<div class="text-sm text-gray-700 mt-1">Plan: ${escapeHtml(p.plan)}</div>` : '<div class="text-sm text-gray-500 mt-1">Sin plan asociado</div>'}
          ${p.url_ubicacion ? `<a href="${escapeHtml(p.url_ubicacion)}" target="_blank" rel="noopener" class="inline-block mt-2 text-sm text-blue-600 hover:underline">Abrir ubicación</a>` : ''}
          ${detalleHref ? `<a href="${escapeHtml(detalleHref)}" class="inline-block mt-2 ml-2 text-sm text-purple-600 hover:underline">Ver cliente</a>` : ''}
        </div>
      `;

      marker.addListener('click', () => {
        sharedInfoWindow.setContent(content);
        sharedInfoWindow.open(map, marker);
      });

      markers.push(marker);
      bounds.extend(position);
    });

    puntosProcesados.value = Math.min(i + batch.length, totalPuntos.value);
    await new Promise((resolve) => requestAnimationFrame(resolve));
  }

  if (props.puntos.length > 1) {
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
    const google = await loadGoogleMaps();
    await initMap(google);
  } catch (e) {
    error.value = e.message || 'Error al cargar el mapa';
  } finally {
    loading.value = false;
  }
});

onBeforeUnmount(() => {
  sharedInfoWindow?.close();
  markers.forEach((m) => m.setMap(null));
  markers = [];
  sharedInfoWindow = null;
  map = null;
});
</script>
