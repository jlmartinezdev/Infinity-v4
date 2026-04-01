<template>
  <div class="relative w-full h-full min-h-[300px]">
    <div ref="mapContainer" class="absolute inset-0 w-full h-full"></div>
    <div v-if="loading" class="absolute inset-0 flex items-center justify-center bg-gray-100/80 dark:bg-gray-800/80 rounded-lg">
      <span class="text-gray-600 dark:text-gray-400">Cargando mapa...</span>
    </div>
    <div v-if="error" class="absolute inset-0 flex items-center justify-center bg-red-50/90 dark:bg-red-900/20 rounded-lg p-4">
      <p class="text-red-700 dark:text-red-300 text-center">{{ error }}</p>
    </div>
    <div v-if="!apiKey" class="absolute inset-0 flex items-center justify-center bg-amber-50/90 dark:bg-amber-900/20 rounded-lg p-4">
      <p class="text-amber-800 dark:text-amber-200 text-center">Configurá GOOGLE_MAPS_API_KEY en .env</p>
    </div>
    <div v-if="apiKey && !loading && !error" class="absolute top-2 left-2 rounded-lg bg-white dark:bg-gray-800 shadow-sm border border-gray-200 dark:border-gray-600 px-2 py-1 text-xs text-gray-600 dark:text-gray-400">
      <span class="inline-block w-3 h-3 rounded-full bg-blue-500 mr-1"></span> Cajas
      <span class="inline-block w-3 h-3 rounded-full bg-green-500 ml-2 mr-1"></span> Nodos
      <span class="inline-block w-3 h-3 rounded-full bg-amber-500 ml-2 mr-1"></span> PON
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, onBeforeUnmount, watch } from 'vue';

const props = defineProps({
  apiKey: { type: String, default: '' },
  mapaDataUrl: { type: String, default: '' },
  nodoId: { type: [String, Number], default: '' },
});

const mapContainer = ref(null);
const loading = ref(true);
const error = ref('');
let map = null;
let markers = [];
let polylines = [];

function loadGoogleMaps() {
  return new Promise((resolve, reject) => {
    if (typeof window.google !== 'undefined' && window.google.maps) {
      resolve(window.google);
      return;
    }
    const scriptId = 'google-maps-api-mapa-nap';
    if (document.getElementById(scriptId)) {
      const check = setInterval(() => {
        if (window.google?.maps) {
          clearInterval(check);
          resolve(window.google);
        }
      }, 100);
      return;
    }
    window.__mapaNapMapsReady__ = () => resolve(window.google);
    const script = document.createElement('script');
    script.id = scriptId;
    script.src = `https://maps.googleapis.com/maps/api/js?key=${encodeURIComponent(props.apiKey)}&callback=__mapaNapMapsReady__`;
    script.async = true;
    script.defer = true;
    script.onerror = () => reject(new Error('Error al cargar Google Maps'));
    document.head.appendChild(script);
  });
}

function initMap(google) {
  if (!mapContainer.value) return;

  map = new google.maps.Map(mapContainer.value, {
    center: { lat: -25.2637, lng: -57.5759 },
    zoom: 12,
    mapTypeControl: true,
    streetViewControl: true,
    fullscreenControl: true,
    zoomControl: true,
  });

  loadData();
}

function clearMarkers() {
  markers.forEach((m) => m.setMap(null));
  markers = [];
  polylines.forEach((p) => p.setMap(null));
  polylines = [];
}

function escapeHtml(text) {
  if (!text) return '';
  const div = document.createElement('div');
  div.textContent = text;
  return div.innerHTML;
}

async function loadData() {
  if (!props.mapaDataUrl) return;
  loading.value = true;
  error.value = '';

  try {
    const url = new URL(props.mapaDataUrl);
    if (props.nodoId) url.searchParams.set('nodo_id', props.nodoId);

    const res = await fetch(url);
    const data = await res.json();

    clearMarkers();

    const bounds = new google.maps.LatLngBounds();

    // Cajas NAP - marcador azul
    (data.cajas || []).forEach((c) => {
      const pos = { lat: c.lat, lng: c.lon };
      const marker = new google.maps.Marker({
        position: pos,
        map,
        title: c.codigo,
        icon: {
          path: google.maps.SymbolPath.CIRCLE,
          scale: 10,
          fillColor: '#3B82F6',
          fillOpacity: 1,
          strokeColor: '#1E40AF',
          strokeWeight: 2,
        },
      });

      const content = `
        <div class="p-2 min-w-[180px]">
          <div class="font-semibold text-gray-900">${escapeHtml(c.codigo)}</div>
          ${c.descripcion ? `<div class="text-sm text-gray-700">${escapeHtml(c.descripcion)}</div>` : ''}
          ${c.nodo ? `<div class="text-xs text-gray-500 mt-1">Nodo: ${escapeHtml(c.nodo)}</div>` : ''}
          <div class="text-xs text-gray-500 mt-1">${escapeHtml(c.tipo_caja || '')}</div>
        </div>
      `;
      const infoWindow = new google.maps.InfoWindow({ content });
      marker.addListener('click', () => {
        infoWindow.open(map, marker);
      });

      markers.push(marker);
      bounds.extend(pos);
    });

    // Nodos - marcador verde
    (data.nodos || []).forEach((n) => {
      const pos = { lat: n.lat, lng: n.lon };
      const marker = new google.maps.Marker({
        position: pos,
        map,
        title: n.descripcion,
        icon: {
          path: google.maps.SymbolPath.CIRCLE,
          scale: 12,
          fillColor: '#22C55E',
          fillOpacity: 1,
          strokeColor: '#15803D',
          strokeWeight: 2,
        },
      });

      const content = `
        <div class="p-2 min-w-[160px]">
          <div class="font-semibold text-gray-900">Nodo</div>
          <div class="text-sm text-gray-700">${escapeHtml(n.descripcion || '')}</div>
        </div>
      `;
      const infoWindow = new google.maps.InfoWindow({ content });
      marker.addListener('click', () => infoWindow.open(map, marker));

      markers.push(marker);
      bounds.extend(pos);
    });

    // Salidas PON - marcador amarillo
    (data.salida_pons || []).forEach((p) => {
      const pos = { lat: p.lat, lng: p.lon };
      const marker = new google.maps.Marker({
        position: pos,
        map,
        title: p.codigo,
        icon: {
          path: google.maps.SymbolPath.CIRCLE,
          scale: 8,
          fillColor: '#EAB308',
          fillOpacity: 1,
          strokeColor: '#A16207',
          strokeWeight: 2,
        },
      });

      const content = `
        <div class="p-2 min-w-[140px]">
          <div class="font-semibold text-gray-900">PON: ${escapeHtml(p.codigo || '')}</div>
        </div>
      `;
      const infoWindow = new google.maps.InfoWindow({ content });
      marker.addListener('click', () => infoWindow.open(map, marker));

      markers.push(marker);
      bounds.extend(pos);
    });

    // Líneas de cable (polylines)
    (data.lineas || []).forEach((l) => {
      const path = (l.path || []).map((pt) => ({
        lat: Array.isArray(pt) ? parseFloat(pt[0]) : parseFloat(pt.lat),
        lng: Array.isArray(pt) ? parseFloat(pt[1]) : parseFloat(pt.lon || pt.lng),
      }));

      if (path.length >= 2) {
        const polyline = new google.maps.Polyline({
          path,
          geodesic: true,
          strokeColor: l.color || '#666666',
          strokeOpacity: 0.9,
          strokeWeight: 2,
        });
        polyline.setMap(map);
        polylines.push(polyline);

        path.forEach((p) => bounds.extend(p));
      }
    });

    if (markers.length > 0 || polylines.length > 0) {
      map.fitBounds(bounds);
    }
  } catch (e) {
    error.value = e.message || 'Error al cargar datos del mapa';
  } finally {
    loading.value = false;
  }
}

onMounted(async () => {
  if (!props.apiKey) {
    loading.value = false;
    return;
  }

  try {
    const google = await loadGoogleMaps();
    initMap(google);
  } catch (e) {
    error.value = e.message || 'Error al cargar Google Maps';
    loading.value = false;
  }
});

watch(
  () => props.nodoId,
  () => {
    if (map && props.apiKey) loadData();
  }
);

onBeforeUnmount(() => {
  clearMarkers();
});
</script>
