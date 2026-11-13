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
        <label class="inline-flex items-center gap-1.5 rounded-lg bg-white/95 dark:bg-gray-900/95 px-3 py-1.5 text-xs shadow border border-gray-200 dark:border-gray-700 text-gray-700 dark:text-gray-200 cursor-pointer">
          <input v-model="capaRecorrido" type="checkbox" class="rounded border-gray-300" />
          <span class="w-2.5 h-2.5 rounded-full bg-rose-600"></span>
          Recorrido
        </label>
        <input
          v-if="capaRecorrido"
          v-model="fechaRecorrido"
          type="date"
          :max="hoyLocal()"
          class="rounded-lg bg-white/95 dark:bg-gray-900/95 px-2 py-1.5 text-xs shadow border border-gray-200 dark:border-gray-700 text-gray-700 dark:text-gray-200"
        />
      </div>
      <div class="flex flex-wrap gap-2 pointer-events-auto">
        <button type="button" class="rounded-lg bg-white/95 dark:bg-gray-900/95 px-3 py-1.5 text-xs shadow border border-gray-200 dark:border-gray-700 text-gray-700 dark:text-gray-200" @click="toggleSatelite">
          {{ satelite ? 'Mapa' : 'Satélite' }}
        </button>
      </div>
    </div>

    <div v-if="capaRecorrido" class="absolute bottom-3 left-3 z-10 max-w-xs pointer-events-auto rounded-lg bg-white/95 dark:bg-gray-900/95 shadow border border-gray-200 dark:border-gray-700 px-3 py-2 text-xs text-gray-700 dark:text-gray-200">
      <div class="font-semibold text-gray-900 dark:text-gray-100">{{ tituloRecorrido }}</div>
      <p class="mt-0.5 text-[11px] text-gray-500 dark:text-gray-400">{{ notaRecorrido }}</p>
      <p v-if="avisoRuta" class="mt-1 text-[11px] text-amber-700 dark:text-amber-300">{{ avisoRuta }}</p>
      <p v-if="errorRecorrido" class="mt-1 text-red-600 dark:text-red-300">{{ errorRecorrido }}</p>
      <ul v-else-if="recorridos.length" class="mt-1 max-h-40 overflow-y-auto space-y-1">
        <li v-for="t in recorridos" :key="t.tecnico_id">
          <button type="button" class="flex items-center gap-1.5 text-left w-full hover:underline" @click="enfocarRecorrido(t)">
            <span class="w-2.5 h-2.5 rounded-full shrink-0" :style="{ background: colorTecnico(t.tecnico_id) }"></span>
            <span>{{ t.nombre }} · {{ formatoDistancia(t.distancia_m) }}<template v-if="t.visitas"> · {{ t.visitas }} visitas</template></span>
          </button>
        </li>
      </ul>
      <p v-else class="mt-1 text-gray-500 dark:text-gray-400">Sin recorrido en esa fecha.</p>
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
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';

function hoyLocal() {
  const d = new Date();
  const m = String(d.getMonth() + 1).padStart(2, '0');
  const day = String(d.getDate()).padStart(2, '0');
  return `${d.getFullYear()}-${m}-${day}`;
}

const props = defineProps({
  apiKey: { type: String, default: '' },
  urlUbicaciones: { type: String, required: true },
  urlRecorridos: { type: String, default: '' },
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
const capaRecorrido = ref(false);
const fechaRecorrido = ref(hoyLocal());
const recorridos = ref([]);
const modoRecorrido = ref('puntos');
const errorRecorrido = ref('');
const avisoRuta = ref('');
const satelite = ref(false);

const COLORES_RECORRIDO = ['#e11d48', '#2563eb', '#d97706', '#059669', '#7c3aed', '#db2777', '#0891b2', '#65a30d', '#ea580c', '#4f46e5'];

const tituloRecorrido = computed(() => {
  const [y, m, d] = String(fechaRecorrido.value || '').split('-');
  const etiqueta = d && m && y ? `${d}/${m}/${y}` : fechaRecorrido.value;
  return modoRecorrido.value === 'resumen' ? `Resumen ${etiqueta}` : `Recorrido ${etiqueta}`;
});

const notaRecorrido = computed(() => (
  modoRecorrido.value === 'resumen'
    ? 'Resumen del día, dibujado sobre las calles. Los puntos detallados ya se eliminaron.'
    : 'La línea sigue las calles entre los puntos. A la noche queda solo el resumen.'
));

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
let recorridoPorId = {};
let figurasRecorrido = [];
let recorridoPoll = null;
let recorridoSeq = 0;
let rutaSeq = 0;
let rutasPorCalle = true;
let directionsService = null;
const cacheRutas = new Map();
const OFFLINE_MS = 5 * 60 * 1000;

function colorTecnico(id) {
  const n = Math.abs(Number(id) || 0);
  return COLORES_RECORRIDO[n % COLORES_RECORRIDO.length];
}

function formatoDistancia(metros) {
  const n = Number(metros) || 0;
  if (n < 1000) return `${Math.round(n)} m`;
  return `${(n / 1000).toLocaleString('es-PY', { maximumFractionDigits: 1 })} km`;
}

function formatoHora(iso) {
  if (!iso) return '—';
  const d = new Date(iso);
  if (Number.isNaN(d.getTime())) return '—';
  return d.toLocaleTimeString('es-PY', { hour: '2-digit', minute: '2-digit' });
}

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
  const rec = recorridoPorId[item.tecnico_id];
  const lineaRec = rec
    ? `<div style="color:#374151">Recorrido: ${escapeHtml(formatoDistancia(rec.distancia_m))} · ${escapeHtml(formatoHora(rec.inicio))}–${escapeHtml(formatoHora(rec.fin))}</div>`
    : '';
  // Colores inline: el body en dark mode hereda text-gray-100 y deja el popup ilegible.
  return (
    `<div style="min-width:180px;font:13px/1.4 system-ui,sans-serif;color:#111827;background:#fff">` +
    `<div style="font-weight:700;margin-bottom:4px;color:#111827">${escapeHtml(item.nombre || 'Técnico')}</div>` +
    `<div style="color:#374151">Estado: <strong style="color:${online ? '#16a34a' : '#4b5563'}">${online ? 'Online' : 'Offline'}</strong></div>` +
    `<div style="color:#374151">Última act.: ${escapeHtml(formatUpdated(item.updated_at))}</div>` +
    `<div style="color:#374151">Visita: ${escapeHtml(String(visita))}</div>` +
    lineaRec +
    `<div style="margin-top:6px"><a href="${mapsUrl}" target="_blank" rel="noopener" style="color:#2563eb;font-weight:600;text-decoration:underline">Abrir en Maps</a></div></div>`
  );
}

function popupRecorrido(item) {
  const visitas = item.visitas ? `<div style="color:#374151">Visitas: ${escapeHtml(String(item.visitas))}</div>` : '';
  const nota = modoRecorrido.value === 'resumen'
    ? '<div style="color:#6b7280;margin-top:4px">Resumen guardado. Los puntos detallados de ese día ya se eliminaron.</div>'
    : '<div style="color:#6b7280;margin-top:4px">Trayecto del día. A la noche queda solo el resumen.</div>';
  return (
    `<div style="min-width:180px;font:13px/1.4 system-ui,sans-serif;color:#111827;background:#fff">` +
    `<div style="font-weight:700;color:#111827">${escapeHtml(item.nombre || 'Técnico')}</div>` +
    `<div style="color:#374151">Distancia: ${escapeHtml(formatoDistancia(item.distancia_m))}</div>` +
    `<div style="color:#374151">${escapeHtml(formatoHora(item.inicio))} – ${escapeHtml(formatoHora(item.fin))}</div>` +
    visitas +
    nota +
    '</div>'
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

function clearRecorridoFiguras() {
  figurasRecorrido.forEach((f) => f.setMap(null));
  figurasRecorrido = [];
}

function distanciaMetros(a, b) {
  const radio = 6371000;
  const p1 = (a.lat * Math.PI) / 180;
  const p2 = (b.lat * Math.PI) / 180;
  const dp = ((b.lat - a.lat) * Math.PI) / 180;
  const dl = ((b.lng - a.lng) * Math.PI) / 180;
  const h = Math.sin(dp / 2) ** 2 + Math.cos(p1) * Math.cos(p2) * Math.sin(dl / 2) ** 2;
  return 2 * radio * Math.asin(Math.min(1, Math.sqrt(h)));
}

function muestrearRuta(path, minMetros = 120, maxPuntos = 49) {
  if (path.length <= 2) return path;
  const kept = [path[0]];
  for (let i = 1; i < path.length - 1; i += 1) {
    if (distanciaMetros(kept[kept.length - 1], path[i]) >= minMetros) kept.push(path[i]);
  }
  const ultimo = path[path.length - 1];
  if (distanciaMetros(kept[kept.length - 1], ultimo) > 15) kept.push(ultimo);
  else kept[kept.length - 1] = ultimo;
  if (kept.length <= maxPuntos) return kept;
  const paso = Math.ceil((kept.length - 2) / (maxPuntos - 2));
  const out = [kept[0]];
  for (let i = paso; i < kept.length - 1; i += paso) out.push(kept[i]);
  out.push(kept[kept.length - 1]);
  return out;
}

function partirRuta(path, tamano = 25) {
  if (path.length <= tamano) return [path];
  const trozos = [];
  let i = 0;
  while (i < path.length - 1) {
    const fin = Math.min(i + tamano - 1, path.length - 1);
    trozos.push(path.slice(i, fin + 1));
    i = fin;
  }
  return trozos;
}

function pedirTramo(trozo) {
  if (!directionsService) directionsService = new googleRef.maps.DirectionsService();
  const request = {
    origin: trozo[0],
    destination: trozo[trozo.length - 1],
    waypoints: trozo.slice(1, -1).map((p) => ({ location: p, stopover: false })),
    travelMode: googleRef.maps.TravelMode.DRIVING,
    optimizeWaypoints: false,
    region: 'py',
  };
  return new Promise((resolve) => {
    directionsService.route(request, (result, status) => {
      if (status === 'MAX_WAYPOINTS_EXCEEDED' && trozo.length > 4) {
        const medio = Math.ceil(trozo.length / 2);
        Promise.all([
          pedirTramo(trozo.slice(0, medio)),
          pedirTramo(trozo.slice(medio - 1)),
        ]).then(([a, b]) => resolve(a && b ? a.concat(b) : null));
        return;
      }
      if (status === 'REQUEST_DENIED' || status === 'OVER_QUERY_LIMIT') rutasPorCalle = false;
      if (status !== 'OK' || !result?.routes?.[0]) {
        resolve(null);
        return;
      }
      const road = [];
      result.routes[0].legs.forEach((leg) => {
        leg.steps.forEach((step) => {
          (step.path || []).forEach((ll) => road.push({ lat: ll.lat(), lng: ll.lng() }));
        });
      });
      resolve(road.length > 1 ? road : null);
    });
  });
}

async function rutaPorCalles(path) {
  const muestra = muestrearRuta(path);
  if (muestra.length < 2 || !rutasPorCalle || !googleRef?.maps?.DirectionsService) return { path, porCalles: false };
  const firma = muestra.map((p) => `${p.lat.toFixed(4)},${p.lng.toFixed(4)}`).join('|');
  if (cacheRutas.has(firma)) return { path: cacheRutas.get(firma), porCalles: true };
  try {
    const unido = [];
    for (const trozo of partirRuta(muestra)) {
      const road = await pedirTramo(trozo);
      if (!road) return { path, porCalles: false };
      unido.push(...road);
    }
    cacheRutas.set(firma, unido);
    return { path: unido, porCalles: true };
  } catch {
    return { path, porCalles: false };
  }
}

function dibujarTrazo(item, path, color) {
  if (path.length === 1) {
    const marker = new googleRef.maps.Marker({
      map,
      position: path[0],
      title: item.nombre,
      icon: pinIcon(googleRef, color),
      zIndex: 35,
    });
    marker.addListener('click', () => {
      infoWindow.setContent(popupRecorrido(item));
      infoWindow.open({ map, anchor: marker });
    });
    figurasRecorrido.push(marker);
    return;
  }
  const poly = new googleRef.maps.Polyline({
    map,
    path,
    strokeColor: color,
    strokeOpacity: 0.95,
    strokeWeight: 5,
    geodesic: false,
    zIndex: 35,
  });
  poly.addListener('click', (e) => {
    infoWindow.setContent(popupRecorrido(item));
    infoWindow.setPosition(e.latLng);
    infoWindow.open(map);
  });
  figurasRecorrido.push(poly);
}

async function syncRecorridoFiguras() {
  const seq = ++rutaSeq;
  clearRecorridoFiguras();
  if (!capaRecorrido.value || !map || !googleRef) return;
  let algunaRecta = false;
  await Promise.all(recorridos.value.map(async (t) => {
    const path = (t.traza || [])
      .map((p) => ({ lat: Number(p.lat), lng: Number(p.lng) }))
      .filter((p) => Number.isFinite(p.lat) && Number.isFinite(p.lng));
    if (!path.length || seq !== rutaSeq) return;
    const color = colorTecnico(t.tecnico_id);
    const ruta = path.length > 1 ? await rutaPorCalles(path) : { path, porCalles: true };
    if (seq !== rutaSeq || !capaRecorrido.value || !map) return;
    if (path.length > 1 && !ruta.porCalles) algunaRecta = true;
    dibujarTrazo(t, ruta.path, color);
  }));
  if (seq !== rutaSeq || !capaRecorrido.value) return;
  avisoRuta.value = algunaRecta
    ? 'No se pudo calzar alguna ruta sobre las calles. Esa línea une los puntos en recta.'
    : '';
}

function enfocarRecorrido(t) {
  if (!map || !googleRef) return;
  const path = (t.traza || []).filter((p) => Number.isFinite(Number(p.lat)) && Number.isFinite(Number(p.lng)));
  if (!path.length) return;
  const bounds = new googleRef.maps.LatLngBounds();
  path.forEach((p) => bounds.extend({ lat: Number(p.lat), lng: Number(p.lng) }));
  map.fitBounds(bounds, 64);
}

function syncRecorridoPoll() {
  if (recorridoPoll) {
    clearInterval(recorridoPoll);
    recorridoPoll = null;
  }
  if (!capaRecorrido.value || fechaRecorrido.value !== hoyLocal()) return;
  recorridoPoll = setInterval(() => {
    fetchRecorrido();
  }, 60000);
}

async function fetchRecorrido() {
  if (!props.urlRecorridos) {
    errorRecorrido.value = 'No está configurada la ruta de recorridos.';
    return;
  }
  const seq = ++recorridoSeq;
  const url = `${props.urlRecorridos}?fecha=${encodeURIComponent(fechaRecorrido.value)}`;
  try {
    const res = await fetch(url, {
      headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      credentials: 'same-origin',
    });
    const data = await res.json().catch(() => ({}));
    if (seq !== recorridoSeq) return;
    if (!res.ok || data.success === false) {
      throw new Error(data.message || 'No se pudo cargar el recorrido.');
    }
    const payload = data.data || {};
    modoRecorrido.value = payload.modo === 'resumen' ? 'resumen' : 'puntos';
    const list = Array.isArray(payload.tecnicos) ? payload.tecnicos : [];
    recorridos.value = list;
    const index = {};
    list.forEach((t) => { index[t.tecnico_id] = t; });
    recorridoPorId = index;
    errorRecorrido.value = '';
    syncRecorridoFiguras();
  } catch (e) {
    if (seq !== recorridoSeq) return;
    errorRecorrido.value = e.message || 'No se pudo cargar el recorrido.';
  }
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
watch(capaRecorrido, async (on) => {
  if (!on) {
    recorridoSeq += 1;
    rutaSeq += 1;
    clearRecorridoFiguras();
    recorridos.value = [];
    recorridoPorId = {};
    errorRecorrido.value = '';
    avisoRuta.value = '';
    syncRecorridoPoll();
    return;
  }
  await fetchRecorrido();
  syncRecorridoPoll();
});
watch(fechaRecorrido, async () => {
  if (!capaRecorrido.value) return;
  await fetchRecorrido();
  syncRecorridoPoll();
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
    if (capaRecorrido.value) {
      await fetchRecorrido();
      syncRecorridoPoll();
    }
    const ms = Math.max(5, Number(props.pollSegundos) || 15) * 1000;
    pollTimer = setInterval(fetchUbicaciones, ms);
  } catch (e) {
    error.value = e.message || 'Error inicializando mapa.';
    loading.value = false;
  }
});

onBeforeUnmount(() => {
  if (pollTimer) clearInterval(pollTimer);
  if (recorridoPoll) clearInterval(recorridoPoll);
  for (const entry of markersTecnicos.values()) entry.marker.setMap(null);
  markersTecnicos.clear();
  clearRecorridoFiguras();
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
