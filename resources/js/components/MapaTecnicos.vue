<template>
  <div class="relative w-full h-full min-h-[420px]">
    <div ref="mapContainer" class="absolute inset-0 w-full h-full rounded-lg"></div>

    <div class="absolute top-3 left-3 right-3 z-10 flex flex-wrap gap-2 items-start justify-between pointer-events-none">
      <div class="flex flex-wrap gap-2 pointer-events-auto">
        <label class="inline-flex items-center gap-1.5 rounded-lg bg-white/95 dark:bg-gray-900/95 px-3 py-1.5 text-xs shadow border border-gray-200 dark:border-gray-700 text-gray-700 dark:text-gray-200 cursor-pointer">
          <input v-model="capaTecnicos" type="checkbox" class="rounded border-gray-300" />
          <span class="w-2.5 h-2.5 rounded-full bg-green-600"></span>
          Técnicos ({{ tecnicos.length }})
        </label>
        <label class="inline-flex items-center gap-1.5 rounded-lg bg-white/95 dark:bg-gray-900/95 px-3 py-1.5 text-xs shadow border border-gray-200 dark:border-gray-700 text-gray-700 dark:text-gray-200 cursor-pointer">
          <input v-model="capaClientes" type="checkbox" class="rounded border-gray-300" />
          <span class="w-2.5 h-2.5 rounded-full bg-violet-600"></span>
          Clientes ({{ clientes.length }})
        </label>
        <label class="inline-flex items-center gap-1.5 rounded-lg bg-white/95 dark:bg-gray-900/95 px-3 py-1.5 text-xs shadow border border-gray-200 dark:border-gray-700 text-gray-700 dark:text-gray-200 cursor-pointer">
          <input v-model="capaPedidos" type="checkbox" class="rounded border-gray-300" />
          <span class="w-2.5 h-2.5 rounded-full bg-amber-500"></span>
          Pedidos ({{ pedidos.length }})
        </label>
        <label class="inline-flex items-center gap-1.5 rounded-lg bg-white/95 dark:bg-gray-900/95 px-3 py-1.5 text-xs shadow border border-gray-200 dark:border-gray-700 text-gray-700 dark:text-gray-200 cursor-pointer">
          <input v-model="capaTickets" type="checkbox" class="rounded border-gray-300" />
          <span class="w-2.5 h-2.5 rounded-full bg-sky-600"></span>
          Tickets ({{ tickets.length }})
        </label>
      </div>
      <div class="flex flex-wrap gap-2 pointer-events-auto">
        <button type="button" class="rounded-lg bg-white/95 dark:bg-gray-900/95 px-3 py-1.5 text-xs shadow border border-gray-200 dark:border-gray-700 text-gray-700 dark:text-gray-200" @click="toggleSatelite">
          {{ satelite ? 'Mapa' : 'Satélite' }}
        </button>
      </div>
    </div>

    <div v-if="loading" class="absolute inset-0 flex items-center justify-center bg-gray-100/70 dark:bg-gray-800/70 rounded-lg z-20">
      <p class="text-gray-700 dark:text-gray-300 text-sm">Cargando flota...</p>
    </div>
    <div v-if="error" class="absolute bottom-3 left-3 right-3 z-20 rounded-lg bg-red-50 dark:bg-red-900/30 border border-red-200 dark:border-red-800 px-3 py-2 text-sm text-red-700 dark:text-red-200">
      {{ error }}
    </div>
    <div v-if="!apiKey" class="absolute inset-0 flex items-center justify-center bg-amber-50/90 dark:bg-amber-900/20 rounded-lg z-20 p-4">
      <p class="text-amber-800 dark:text-amber-200 text-center text-sm">Falta configurar GOOGLE_MAPS_API_KEY en .env</p>
    </div>
  </div>
</template>

<script setup>
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';

const props = defineProps({
  apiKey: { type: String, default: '' },
  urlUbicaciones: { type: String, required: true },
  urlClientes: { type: String, default: '' },
  urlPedidos: { type: String, default: '' },
  urlTickets: { type: String, default: '' },
  pollSegundos: { type: Number, default: 15 },
  centerLat: { type: Number, default: -25.2867 },
  centerLng: { type: Number, default: -57.647 },
});

const mapContainer = ref(null);
const loading = ref(true);
const error = ref('');
const tecnicos = ref([]);
const clientes = ref([]);
const pedidos = ref([]);
const tickets = ref([]);
const capaTecnicos = ref(true);
const capaClientes = ref(false);
const capaPedidos = ref(false);
const capaTickets = ref(false);
const satelite = ref(false);

let map = null;
let googleRef = null;
let markersTecnicos = new Map();
let markersClientes = [];
let markersPedidos = [];
let markersTickets = [];
let infoWindow = null;
let pollTimer = null;
let fittedOnce = false;
let clientesCargados = false;
let pedidosCargados = false;
let ticketsCargados = false;
const OFFLINE_MS = 5 * 60 * 1000;

function isOnline(item) {
  if (!item?.updated_at) return false;
  const t = Date.parse(item.updated_at);
  if (Number.isNaN(t)) return false;
  return Date.now() - t <= OFFLINE_MS;
}

function markerColor(item) {
  return isOnline(item) ? '#16a34a' : '#9ca3af';
}

function loadGoogleMaps(apiKey) {
  if (window.google?.maps) return Promise.resolve(window.google);
  return new Promise((resolve, reject) => {
    const existing = document.querySelector('script[data-google-maps-staff]');
    if (existing) {
      existing.addEventListener('load', () => resolve(window.google));
      existing.addEventListener('error', () => reject(new Error('No se pudo cargar Google Maps')));
      return;
    }
    const script = document.createElement('script');
    script.src = `https://maps.googleapis.com/maps/api/js?key=${encodeURIComponent(apiKey)}`;
    script.async = true;
    script.defer = true;
    script.dataset.googleMapsStaff = '1';
    script.onload = () => resolve(window.google);
    script.onerror = () => reject(new Error('No se pudo cargar Google Maps'));
    document.head.appendChild(script);
  });
}

function pinIcon(google, color, glyph = '') {
  const w = 36;
  const h = 44;
  const label = glyph
    ? `<text x="18" y="20" text-anchor="middle" fill="#fff" font-size="12" font-family="sans-serif" font-weight="700">${glyph}</text>`
    : '<circle cx="18" cy="15" r="5" fill="#fff"/>';
  const svg =
    `<svg xmlns="http://www.w3.org/2000/svg" width="${w}" height="${h}" viewBox="0 0 36 44">` +
    `<path fill="${color}" stroke="#1f2937" stroke-width="1" d="M18 0C9.7 0 3 6.7 3 15c0 11 15 29 15 29s15-18 15-29C33 6.7 26.3 0 18 0z"/>` +
    label +
    '</svg>';
  return {
    url: 'data:image/svg+xml;charset=UTF-8,' + encodeURIComponent(svg),
    scaledSize: new google.maps.Size(w, h),
    anchor: new google.maps.Point(18, 44),
  };
}

function escapeHtml(text) {
  return String(text)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;');
}

function formatUpdated(iso) {
  if (!iso) return '—';
  try {
    return new Date(iso).toLocaleString();
  } catch {
    return iso;
  }
}

function popupTecnico(item) {
  const online = isOnline(item);
  const mapsUrl = `https://www.google.com/maps?q=${encodeURIComponent(item.lat + ',' + item.lng)}`;
  const visita = item.visita_id ? `#${item.visita_id}` : 'Ninguna';
  // Colores inline: el body en dark mode hereda text-gray-100 y deja el popup ilegible.
  return (
    `<div style="min-width:180px;font:13px/1.4 system-ui,sans-serif;color:#111827;background:#fff">` +
    `<div style="font-weight:700;margin-bottom:4px;color:#111827">${escapeHtml(item.nombre || 'Técnico')}</div>` +
    `<div style="color:#374151">Estado: <strong style="color:${online ? '#16a34a' : '#4b5563'}">${online ? 'Online' : 'Offline'}</strong></div>` +
    `<div style="color:#374151">Última act.: ${escapeHtml(formatUpdated(item.updated_at))}</div>` +
    `<div style="color:#374151">Visita: ${escapeHtml(String(visita))}</div>` +
    `<div style="margin-top:6px"><a href="${mapsUrl}" target="_blank" rel="noopener" style="color:#2563eb;font-weight:600;text-decoration:underline">Abrir en Maps</a></div></div>`
  );
}

function popupPunto(item, tipo) {
  const extra = [];
  if (item.asunto) {
    extra.push(`<div style="color:#374151">Asunto: ${escapeHtml(item.asunto)}</div>`);
  }
  if (item.estado) {
    extra.push(`<div style="color:#374151">Estado: ${escapeHtml(item.estado)}</div>`);
  }
  if (tipo === 'ticket') {
    extra.push(
      item.asignado
        ? `<div style="color:#374151">Técnico: ${escapeHtml(item.asignado)}</div>`
        : `<div style="color:#6b7280">Sin técnico asignado</div>`
    );
  }
  const link = item.url
    ? `<div style="margin-top:6px"><a href="${escapeHtml(item.url)}" target="_blank" rel="noopener" style="color:#2563eb;font-weight:600;text-decoration:underline">Ver ${tipo}</a></div>`
    : '';
  return (
    `<div style="min-width:160px;font:13px/1.4 system-ui,sans-serif;color:#111827;background:#fff">` +
    `<div style="font-weight:700;color:#111827">${escapeHtml(item.nombre || tipo)}</div>` +
    extra.join('') +
    (item.direccion ? `<div style="color:#374151">${escapeHtml(item.direccion)}</div>` : '') +
    link +
    '</div>'
  );
}

function applyMapType() {
  if (!map) return;
  // El mapa siempre usa estilo claro de Google; el dark mode solo afecta la UI alrededor.
  map.setMapTypeId(satelite.value ? 'hybrid' : 'roadmap');
  map.setOptions({ styles: [] });
}

function toggleSatelite() {
  satelite.value = !satelite.value;
  applyMapType();
}

function clearMarkers(list) {
  list.forEach((m) => m.setMap(null));
  list.length = 0;
}

function syncTecnicosVisibility() {
  for (const entry of markersTecnicos.values()) {
    entry.marker.setMap(capaTecnicos.value ? map : null);
  }
}

function syncClientesMarkers() {
  clearMarkers(markersClientes);
  if (!capaClientes.value || !map || !googleRef) return;
  clientes.value.forEach((item) => {
    const marker = new googleRef.maps.Marker({
      map,
      position: { lat: Number(item.lat), lng: Number(item.lng) },
      title: item.nombre,
      icon: pinIcon(googleRef, '#7c3aed', 'C'),
      zIndex: 10,
    });
    marker.addListener('click', () => {
      infoWindow.setContent(popupPunto(item, 'cliente'));
      infoWindow.open({ map, anchor: marker });
    });
    markersClientes.push(marker);
  });
}

function syncPedidosMarkers() {
  clearMarkers(markersPedidos);
  if (!capaPedidos.value || !map || !googleRef) return;
  pedidos.value.forEach((item) => {
    const marker = new googleRef.maps.Marker({
      map,
      position: { lat: Number(item.lat), lng: Number(item.lng) },
      title: item.nombre,
      icon: pinIcon(googleRef, '#f59e0b', 'P'),
      zIndex: 20,
    });
    marker.addListener('click', () => {
      infoWindow.setContent(popupPunto(item, 'pedido'));
      infoWindow.open({ map, anchor: marker });
    });
    markersPedidos.push(marker);
  });
}

function syncTicketsMarkers() {
  clearMarkers(markersTickets);
  if (!capaTickets.value || !map || !googleRef) return;
  tickets.value.forEach((item) => {
    const marker = new googleRef.maps.Marker({
      map,
      position: { lat: Number(item.lat), lng: Number(item.lng) },
      title: item.nombre,
      icon: pinIcon(googleRef, '#0284c7', '#'),
      zIndex: 25,
    });
    marker.addListener('click', () => {
      infoWindow.setContent(popupPunto(item, 'ticket'));
      infoWindow.open({ map, anchor: marker });
    });
    markersTickets.push(marker);
  });
}

function upsertTecnicos(items) {
  if (!map || !googleRef) return;
  const seen = new Set();
  items.forEach((item) => {
    const id = item.tecnico_id;
    seen.add(id);
    const pos = { lat: Number(item.lat), lng: Number(item.lng) };
    if (!Number.isFinite(pos.lat) || !Number.isFinite(pos.lng)) return;
    let entry = markersTecnicos.get(id);
    if (!entry) {
      const marker = new googleRef.maps.Marker({
        map: capaTecnicos.value ? map : null,
        position: pos,
        title: item.nombre || `Técnico ${id}`,
        icon: pinIcon(googleRef, markerColor(item)),
        zIndex: 50,
      });
      marker.addListener('click', () => {
        infoWindow.setContent(popupTecnico(item));
        infoWindow.open({ map, anchor: marker });
      });
      entry = { marker, item };
      markersTecnicos.set(id, entry);
    } else {
      entry.marker.setPosition(pos);
      entry.marker.setIcon(pinIcon(googleRef, markerColor(item)));
      entry.marker.setTitle(item.nombre || `Técnico ${id}`);
      entry.marker.setMap(capaTecnicos.value ? map : null);
      entry.item = item;
      googleRef.maps.event.clearListeners(entry.marker, 'click');
      entry.marker.addListener('click', () => {
        infoWindow.setContent(popupTecnico(item));
        infoWindow.open({ map, anchor: entry.marker });
      });
    }
  });
  for (const [id, entry] of markersTecnicos.entries()) {
    if (!seen.has(id)) {
      entry.marker.setMap(null);
      markersTecnicos.delete(id);
    }
  }
  if (!fittedOnce && items.length > 0) {
    const bounds = new googleRef.maps.LatLngBounds();
    items.forEach((item) => {
      if (Number.isFinite(Number(item.lat)) && Number.isFinite(Number(item.lng))) {
        bounds.extend({ lat: Number(item.lat), lng: Number(item.lng) });
      }
    });
    map.fitBounds(bounds, 64);
    fittedOnce = true;
  }
}

async function fetchJson(url) {
  let res;
  try {
    res = await fetch(url, {
      headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      credentials: 'same-origin',
    });
  } catch {
    throw new Error('Sin conexión al servidor. Revisá la red e intentá de nuevo.');
  }

  const contentType = res.headers.get('content-type') || '';
  const data = contentType.includes('application/json')
    ? await res.json().catch(() => ({}))
    : {};

  if (res.status === 401 || res.status === 419) {
    throw new Error('Sesión expirada. Recargá la página e iniciá sesión de nuevo.');
  }
  if (res.status === 403) {
    throw new Error(data.message || 'No tenés permiso para ver estos datos del mapa.');
  }
  if (!res.ok || data.success === false) {
    throw new Error(data.message || `No se pudieron cargar los datos del mapa (HTTP ${res.status}).`);
  }
  return Array.isArray(data.data) ? data.data : [];
}

async function fetchUbicaciones() {
  const yaHayDatos = tecnicos.value.length > 0;
  try {
    const list = await fetchJson(props.urlUbicaciones);
    tecnicos.value = list;
    upsertTecnicos(list);
    error.value = '';
  } catch (e) {
    // Si el mapa ya tiene pines, un fallo de refresco no debe tapar la vista.
    if (yaHayDatos) {
      console.warn('[mapa-tecnicos] refresco falló:', e.message);
    } else {
      error.value = e.message || 'Error al cargar flota.';
    }
  } finally {
    loading.value = false;
  }
}

async function ensureClientes() {
  if (clientesCargados || !props.urlClientes) return;
  clientes.value = await fetchJson(props.urlClientes);
  clientesCargados = true;
  syncClientesMarkers();
}

async function ensurePedidos() {
  if (pedidosCargados || !props.urlPedidos) return;
  pedidos.value = await fetchJson(props.urlPedidos);
  pedidosCargados = true;
  syncPedidosMarkers();
}

async function ensureTickets() {
  if (ticketsCargados || !props.urlTickets) return;
  tickets.value = await fetchJson(props.urlTickets);
  ticketsCargados = true;
  syncTicketsMarkers();
}

watch(capaTecnicos, syncTecnicosVisibility);
watch(capaClientes, async (on) => {
  if (on) {
    try {
      await ensureClientes();
    } catch (e) {
      error.value = e.message || 'No se pudieron cargar clientes.';
      capaClientes.value = false;
      return;
    }
  }
  syncClientesMarkers();
});
watch(capaPedidos, async (on) => {
  if (on) {
    try {
      await ensurePedidos();
    } catch (e) {
      error.value = e.message || 'No se pudieron cargar pedidos.';
      capaPedidos.value = false;
      return;
    }
  }
  syncPedidosMarkers();
});
watch(capaTickets, async (on) => {
  if (on) {
    try {
      await ensureTickets();
    } catch (e) {
      error.value = e.message || 'No se pudieron cargar tickets.';
      capaTickets.value = false;
      return;
    }
  }
  syncTicketsMarkers();
});

onMounted(async () => {
  if (!props.apiKey) {
    loading.value = false;
    return;
  }
  try {
    googleRef = await loadGoogleMaps(props.apiKey);
    map = new googleRef.maps.Map(mapContainer.value, {
      center: { lat: props.centerLat, lng: props.centerLng },
      zoom: 12,
      mapTypeControl: false,
      streetViewControl: false,
      fullscreenControl: true,
    });
    infoWindow = new googleRef.maps.InfoWindow();
    applyMapType();
    await fetchUbicaciones();
    const ms = Math.max(5, Number(props.pollSegundos) || 15) * 1000;
    pollTimer = setInterval(fetchUbicaciones, ms);
  } catch (e) {
    error.value = e.message || 'Error inicializando mapa.';
    loading.value = false;
  }
});

onBeforeUnmount(() => {
  if (pollTimer) clearInterval(pollTimer);
  for (const entry of markersTecnicos.values()) entry.marker.setMap(null);
  markersTecnicos.clear();
  clearMarkers(markersClientes);
  clearMarkers(markersPedidos);
  clearMarkers(markersTickets);
});
</script>

<style>
/* InfoWindow de Google Maps hereda dark:text-gray-100 del body; forzar contraste legible */
.gm-style .gm-style-iw-c,
.gm-style .gm-style-iw-d {
  background: #ffffff !important;
  color: #111827 !important;
}
.gm-style .gm-style-iw-c a,
.gm-style .gm-style-iw-d a {
  color: #2563eb !important;
}
</style>
