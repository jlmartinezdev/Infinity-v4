<template>
  <div class="relative w-full h-64 sm:h-80 rounded-lg border border-gray-200 dark:border-gray-600 overflow-hidden bg-gray-100 dark:bg-gray-900 shadow-inner">
    <div ref="mapContainer" class="absolute inset-0 w-full h-full"></div>
    <div v-if="loading" class="absolute inset-0 flex items-center justify-center bg-gray-100/90 dark:bg-gray-900/90 z-10">
      <span class="text-sm text-gray-600 dark:text-gray-400">Cargando mapa…</span>
    </div>
    <div v-if="error" class="absolute inset-0 flex items-center justify-center bg-red-50/95 dark:bg-red-950/40 z-10 p-3">
      <p class="text-sm text-red-700 dark:text-red-300 text-center">{{ error }}</p>
    </div>
    <div v-if="!apiKey" class="absolute inset-0 flex items-center justify-center bg-amber-50/95 dark:bg-amber-950/30 z-10 p-3">
      <p class="text-sm text-amber-900 dark:text-amber-200 text-center">Configure GOOGLE_MAPS_API_KEY en .env para usar el mapa.</p>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, onBeforeUnmount } from 'vue';

const props = defineProps({
  apiKey: { type: String, default: '' },
  initialLat: { type: Number, default: null },
  initialLon: { type: Number, default: null },
});

const mapContainer = ref(null);
const loading = ref(true);
const error = ref('');

const DEFAULT_CENTER = { lat: -25.2637, lng: -57.5759 };

let map = null;
let marker = null;

function latLonInputs() {
  return {
    lat: document.getElementById('lat'),
    lon: document.getElementById('lon'),
  };
}

function setInputsFromLatLng(lat, lng) {
  const { lat: latEl, lon: lonEl } = latLonInputs();
  const latN = Number(lat);
  const lngN = Number(lng);
  if (!Number.isFinite(latN) || !Number.isFinite(lngN)) return;
  const latStr = latN.toFixed(7);
  const lonStr = lngN.toFixed(7);
  if (latEl) {
    latEl.value = latStr;
    latEl.dispatchEvent(new Event('input', { bubbles: true }));
  }
  if (lonEl) {
    lonEl.value = lonStr;
    lonEl.dispatchEvent(new Event('input', { bubbles: true }));
  }
}

function loadGoogleMaps() {
  return new Promise((resolve, reject) => {
    if (typeof window.google !== 'undefined' && window.google.maps) {
      resolve(window.google);
      return;
    }
    const scriptId = 'google-maps-api-caja-nap-form';
    if (document.getElementById(scriptId)) {
      const check = setInterval(() => {
        if (window.google?.maps) {
          clearInterval(check);
          resolve(window.google);
        }
      }, 100);
      return;
    }
    window.__cajaNapFormMapaReady__ = () => resolve(window.google);
    const script = document.createElement('script');
    script.id = scriptId;
    script.src = `https://maps.googleapis.com/maps/api/js?key=${encodeURIComponent(props.apiKey)}&callback=__cajaNapFormMapaReady__`;
    script.async = true;
    script.defer = true;
    script.onerror = () => reject(new Error('No se pudo cargar Google Maps'));
    document.head.appendChild(script);
  });
}

function initMap(google) {
  if (!mapContainer.value) return;

  const hasInitial =
    props.initialLat != null &&
    props.initialLon != null &&
    !Number.isNaN(props.initialLat) &&
    !Number.isNaN(props.initialLon);

  const center = hasInitial
    ? { lat: props.initialLat, lng: props.initialLon }
    : DEFAULT_CENTER;

  map = new google.maps.Map(mapContainer.value, {
    center,
    zoom: hasInitial ? 17 : 6,
    mapTypeControl: true,
    streetViewControl: false,
    fullscreenControl: true,
    zoomControl: true,
  });

  if (hasInitial) {
    marker = new google.maps.Marker({
      position: center,
      map,
      draggable: true,
      title: 'Ubicación de la caja NAP',
    });
    marker.addListener('dragend', () => {
      const p = marker.getPosition();
      if (p) setInputsFromLatLng(p.lat(), p.lng());
    });
  }

  map.addListener('click', (e) => {
    const latLng = e.latLng;
    if (!latLng) return;
    if (!marker) {
      marker = new google.maps.Marker({
        position: latLng,
        map,
        draggable: true,
        title: 'Ubicación de la caja NAP',
      });
      marker.addListener('dragend', () => {
        const p = marker.getPosition();
        if (p) setInputsFromLatLng(p.lat(), p.lng());
      });
    } else {
      marker.setPosition(latLng);
    }
    setInputsFromLatLng(latLng.lat(), latLng.lng());
  });
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
    error.value = e.message || 'Error al inicializar el mapa';
  } finally {
    loading.value = false;
  }
});

onBeforeUnmount(() => {
  if (marker) {
    marker.setMap(null);
    marker = null;
  }
  map = null;
});
</script>
