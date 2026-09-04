<template>
  <div class="relative w-full h-full min-h-[300px] flex flex-col">
    <div class="relative z-20 flex-shrink-0 p-2 sm:p-3 border-b border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800">
      <div class="relative max-w-xl">
        <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M11 18a7 7 0 100-14 7 7 0 000 14z" />
        </svg>
        <input
          v-model="busqueda"
          type="search"
          autocomplete="off"
          placeholder="Buscar cliente por nombre, cédula, teléfono o plan…"
          class="w-full pl-9 pr-9 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-sm text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-purple-500/40"
          @focus="mostrarResultados = true"
          @input="mostrarResultados = true"
          @keydown.enter.prevent="abrirPrimerResultado"
          @keydown.escape="mostrarResultados = false"
          @blur="onBlurBusqueda"
        />
        <button
          v-if="busqueda"
          type="button"
          class="absolute right-2 top-1/2 -translate-y-1/2 p-1 rounded text-gray-400 hover:text-gray-600 dark:hover:text-gray-200"
          title="Limpiar"
          @mousedown.prevent="limpiarBusqueda"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
        <ul
          v-if="mostrarResultados && busqueda.trim() && resultadosBusqueda.length"
          class="absolute z-30 left-0 right-0 mt-1 max-h-56 overflow-y-auto rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-800 shadow-lg divide-y divide-gray-100 dark:divide-gray-700"
        >
          <li v-for="punto in resultadosBusqueda" :key="punto.cliente_id">
            <button
              type="button"
              class="w-full text-left px-3 py-2 hover:bg-purple-50 dark:hover:bg-purple-900/30 transition-colors"
              @mousedown.prevent="enfocarCliente(punto.cliente_id)"
              @click.stop.prevent="enfocarCliente(punto.cliente_id)"
            >
              <div class="text-sm font-medium text-gray-900 dark:text-gray-100 truncate">
                {{ punto.nombre || ('Cliente #' + punto.cliente_id) }}
              </div>
              <div class="text-xs text-gray-500 dark:text-gray-400 truncate">
                <span v-if="punto.cedula">CI {{ punto.cedula }}</span>
                <span v-if="punto.telefono">{{ punto.cedula ? ' · ' : '' }}{{ punto.telefono }}</span>
                <span v-if="punto.plan">{{ (punto.cedula || punto.telefono) ? ' · ' : '' }}{{ punto.plan }}</span>
              </div>
            </button>
          </li>
        </ul>
        <p
          v-else-if="mostrarResultados && busqueda.trim() && !resultadosBusqueda.length"
          class="absolute left-0 right-0 mt-1 text-xs text-amber-800 dark:text-amber-200 bg-amber-50 dark:bg-amber-900/40 border border-amber-200 dark:border-amber-800 rounded-lg px-3 py-2"
        >
          Sin coincidencias para “{{ busqueda.trim() }}”
        </p>
      </div>
    </div>
    <div class="relative flex-1 min-h-0">
    <div ref="mapContainer" class="absolute inset-0 w-full h-full"></div>
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
  </div>
</template>

<script setup>
import { computed, ref, onMounted, onBeforeUnmount } from 'vue';

const props = defineProps({
  apiKey: { type: String, default: '' },
  puntos: { type: Array, default: () => [] },
  urlDetalleClienteBase: { type: String, default: '' },
  urlPingEstados: { type: String, default: '' },
  pingRefrescoSegundos: { type: Number, default: 60 },
  nodoId: { type: [Number, String], default: null },
  pingEstadoFiltro: { type: String, default: '' },
});

const mapContainer = ref(null);
const loading = ref(true);
const loadingMessage = ref('Cargando mapa...');
const puntosProcesados = ref(0);
const totalPuntos = ref(0);
const error = ref('');
const busqueda = ref('');
const mostrarResultados = ref(false);
let map = null;
let googleRef = null;
let markers = [];
let markerMeta = [];
let puntosByClienteId = {};
let sharedInfoWindow = null;
let pingPollTimer = null;
let pendingFocusId = null;
let focusedClienteId = null;
let bounceTimer = null;
const iconCache = new Map();
const progressPercent = computed(() => {
  if (!totalPuntos.value) return 0;
  return Math.round((puntosProcesados.value / totalPuntos.value) * 100);
});

function normalizarTexto(valor) {
  return (valor || '')
    .toString()
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .replace(/[_-]+/g, ' ')
    .replace(/[^\w\s]/g, ' ')
    .replace(/\s+/g, ' ')
    .trim()
    .toLowerCase();
}

function clienteCoincide(punto, termino) {
  if (!termino) return true;
  const tokens = normalizarTexto(termino).split(' ').filter(Boolean);
  if (!tokens.length) return true;
  const haystack = normalizarTexto([
    punto.cliente_id,
    punto.nombre,
    punto.cedula,
    punto.telefono,
    punto.plan,
    punto.tecnologia,
  ].filter(Boolean).join(' '));
  return tokens.every((token) => haystack.includes(token));
}

const resultadosBusqueda = computed(() => {
  const q = busqueda.value.trim();
  if (!q) return [];
  const fuente = Object.keys(puntosByClienteId).length
    ? Object.values(puntosByClienteId)
    : props.puntos;
  return fuente.filter((p) => clienteCoincide(p, q) && pasaFiltroPingEstado(p)).slice(0, 12);
});

function onBlurBusqueda() {
  window.setTimeout(() => {
    mostrarResultados.value = false;
  }, 180);
}

function limpiarBusqueda() {
  busqueda.value = '';
  mostrarResultados.value = false;
  pendingFocusId = null;
  focusedClienteId = null;
}

function abrirPrimerResultado() {
  const primero = resultadosBusqueda.value[0];
  if (primero) {
    enfocarCliente(primero.cliente_id);
  }
}

function enfocarCliente(clienteId) {
  const id = Number(clienteId);
  const punto = puntosByClienteId[id]
    || puntosByClienteId[clienteId]
    || props.puntos.find((p) => Number(p.cliente_id) === id);
  if (!punto) return;

  if (punto.nombre) {
    busqueda.value = punto.nombre;
  }
  mostrarResultados.value = false;
  focusedClienteId = id;

  const meta = markerMeta.find((m) => Number(m.clienteId) === id);
  if (!map || !googleRef) {
    pendingFocusId = id;
    return;
  }

  pendingFocusId = meta ? null : id;
  const pos = (meta && meta.marker && meta.marker.getPosition())
    || (punto.lat != null && punto.lon != null ? { lat: Number(punto.lat), lng: Number(punto.lon) } : null);
  if (!pos) return;

  map.panTo(pos);
  const zoom = map.getZoom();
  if (!zoom || zoom < 16) {
    map.setZoom(16);
  }

  sharedInfoWindow.setContent(buildInfoWindowContent(punto));
  if (meta && meta.marker) {
    sharedInfoWindow.open(map, meta.marker);
    if (bounceTimer) {
      clearTimeout(bounceTimer);
      bounceTimer = null;
    }
    if (googleRef.maps.Animation) {
      meta.marker.setAnimation(googleRef.maps.Animation.BOUNCE);
      bounceTimer = window.setTimeout(() => {
        meta.marker.setAnimation(null);
        bounceTimer = null;
      }, 1400);
    }
  } else {
    sharedInfoWindow.setPosition(pos);
    sharedInfoWindow.open(map);
  }
}

function getHomeMarkerIcon(google, color = '#9333ea') {
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

function markerColorByPingEstado(estado) {
  switch (estado) {
    case 'online':
      return '#16a34a';
    case 'offline':
      return '#dc2626';
    case 'mixed':
      return '#f97316';
    default:
      return '#9ca3af';
  }
}

function pingEstadoLabel(estado) {
  switch (estado) {
    case 'online':
      return 'Online';
    case 'offline':
      return 'PPPoE caído';
    case 'mixed':
      return 'Parcial';
    default:
      return 'Sin dato';
  }
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

function formatVerificadoAt(value) {
  if (!value) return '—';
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return '—';
  return date.toLocaleString('es-PY', { dateStyle: 'short', timeStyle: 'short' });
}

function buildInfoWindowContent(p) {
  const titulo = p.nombre || `Cliente #${p.cliente_id}`;
  const detalleHref = urlDetalle(p.cliente_id);
  const pingLabel = pingEstadoLabel(p.ping_estado);
  const pingColor = markerColorByPingEstado(p.ping_estado);
  const serviciosPing =
    p.ping_total > 0 ? `${p.ping_en_linea ?? 0} / ${p.ping_total} servicios` : 'Sin PPPoE para consultar';

  return `
    <div class="p-2 min-w-[200px] max-w-[320px]">
      <div class="font-semibold text-gray-900">${escapeHtml(titulo)}</div>
      ${p.plan ? `<div class="text-sm text-gray-700 mt-1">Plan: ${escapeHtml(p.plan)}</div>` : '<div class="text-sm text-gray-500 mt-1">Sin plan asociado</div>'}
      <div class="mt-2 text-sm flex items-center gap-1.5">
        <span class="inline-block w-2.5 h-2.5 rounded-full" style="background:${pingColor}"></span>
        <span class="text-gray-800">${escapeHtml(pingLabel)}</span>
      </div>
      <div class="text-xs text-gray-600 mt-1">${escapeHtml(serviciosPing)}</div>
      <div class="text-xs text-gray-500 mt-0.5">Última consulta: ${escapeHtml(formatVerificadoAt(p.ping_verificado_at))}</div>
      ${p.url_ubicacion ? `<a href="${escapeHtml(p.url_ubicacion)}" target="_blank" rel="noopener" class="inline-block mt-2 text-sm text-blue-600 hover:underline">Abrir ubicación</a>` : ''}
      ${detalleHref ? `<a href="${escapeHtml(detalleHref)}" class="inline-block mt-2 ml-2 text-sm text-purple-600 hover:underline">Ver cliente</a>` : ''}
    </div>
  `;
}

function markerIconForPunto(google, punto) {
  const color = markerColorByPingEstado(punto.ping_estado);
  if (!iconCache.has(color)) {
    iconCache.set(color, getHomeMarkerIcon(google, color));
  }
  return iconCache.get(color);
}

function pasaFiltroPingEstado(punto) {
  if (!props.pingEstadoFiltro) return true;
  return (punto.ping_estado || 'unknown') === props.pingEstadoFiltro;
}

function syncMarkerVisibility() {
  if (!map || !googleRef) return;

  const bounds = new google.maps.LatLngBounds();
  let visibleCount = 0;

  markerMeta.forEach(({ marker, clienteId }) => {
    const punto = puntosByClienteId[clienteId];
    const visible = Boolean(punto && pasaFiltroPingEstado(punto));
    marker.setMap(visible ? map : null);
    if (visible && marker.getPosition()) {
      bounds.extend(marker.getPosition());
      visibleCount++;
    }
  });

  if (visibleCount > 1) {
    map.fitBounds(bounds);
  } else if (visibleCount === 1) {
    map.setCenter(bounds.getCenter());
    map.setZoom(14);
  }
}

async function initMap(google) {
  if (!mapContainer.value) return;

  googleRef = google;
  puntosByClienteId = {};
  props.puntos.forEach((p) => {
    puntosByClienteId[p.cliente_id] = { ...p };
  });

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
        map: pasaFiltroPingEstado(p) ? map : null,
        title: titulo,
        icon: markerIconForPunto(google, p),
        optimized: true,
      });

      marker.addListener('click', () => {
        const punto = puntosByClienteId[p.cliente_id] || p;
        sharedInfoWindow.setContent(buildInfoWindowContent(punto));
        sharedInfoWindow.open(map, marker);
      });

      markers.push(marker);
      markerMeta.push({ marker, clienteId: p.cliente_id });
      if (pasaFiltroPingEstado(p)) {
        bounds.extend(position);
      }
      if (pendingFocusId === p.cliente_id) {
        enfocarCliente(p.cliente_id);
      }
    });

    puntosProcesados.value = Math.min(i + batch.length, totalPuntos.value);
    await new Promise((resolve) => requestAnimationFrame(resolve));
  }

  const focusId = pendingFocusId || focusedClienteId;
  if (focusId) {
    enfocarCliente(focusId);
    return;
  }

  if (props.puntos.filter(pasaFiltroPingEstado).length > 1) {
    map.fitBounds(bounds);
  } else if (props.puntos.filter(pasaFiltroPingEstado).length === 1) {
    const visible = props.puntos.find(pasaFiltroPingEstado);
    if (visible) {
      map.setCenter({ lat: visible.lat, lng: visible.lon });
      map.setZoom(14);
    }
  }
}

function escapeHtml(text) {
  if (!text) return '';
  const div = document.createElement('div');
  div.textContent = text;
  return div.innerHTML;
}

function updateUltimaActualizacionLabel(isoString) {
  const el = document.getElementById('mapa-ping-ultima-actualizacion');
  if (!el) return;
  el.textContent = `PPPoE actualizado: ${formatVerificadoAt(isoString)}`;
  el.classList.remove('hidden');
}

function applyPingEstados(estados, actualizadoAt) {
  if (!googleRef || !estados) return;

  markerMeta.forEach(({ marker, clienteId }) => {
    const ping = estados[clienteId];
    if (!ping) return;

    const punto = puntosByClienteId[clienteId];
    if (!punto) return;

    punto.ping_estado = ping.estado ?? 'unknown';
    punto.ping_en_linea = ping.en_linea ?? 0;
    punto.ping_total = ping.total ?? 0;
    punto.ping_latencia_ms = ping.latencia_ms ?? null;
    punto.ping_verificado_at = ping.verificado_at ?? null;

    marker.setIcon(markerIconForPunto(googleRef, punto));
    const titulo = punto.nombre || `Cliente #${clienteId}`;
    marker.setTitle(`${titulo} · ${pingEstadoLabel(punto.ping_estado)}`);
  });

  syncMarkerVisibility();

  if (actualizadoAt) {
    updateUltimaActualizacionLabel(actualizadoAt);
  }
}

async function refrescarPingEstados() {
  if (!props.urlPingEstados || !props.puntos.length) return;

  const ids = props.puntos.map((p) => p.cliente_id).filter(Boolean);
  if (!ids.length) return;

  const params = new URLSearchParams();
  ids.forEach((id) => params.append('cliente_ids[]', String(id)));
  if (props.nodoId) {
    params.append('nodo_id', String(props.nodoId));
  }

  try {
    const response = await fetch(`${props.urlPingEstados}?${params.toString()}`, {
      headers: { Accept: 'application/json' },
      credentials: 'same-origin',
    });
    if (!response.ok) return;
    const data = await response.json();
    applyPingEstados(data.estados, data.actualizado_at);
  } catch (_) {
    // Silencioso: el mapa sigue mostrando el último estado conocido.
  }
}

function startPingPolling() {
  if (!props.urlPingEstados || props.pingRefrescoSegundos < 15) return;
  pingPollTimer = window.setInterval(refrescarPingEstados, props.pingRefrescoSegundos * 1000);
}

onMounted(async () => {
  if (!props.apiKey) {
    loading.value = false;
    return;
  }
  try {
    const google = await loadGoogleMaps();
    await initMap(google);
    startPingPolling();
    window.__mapaClientesActivosRefrescarPing__ = refrescarPingEstados;
  } catch (e) {
    error.value = e.message || 'Error al cargar el mapa';
  } finally {
    loading.value = false;
  }
});

onBeforeUnmount(() => {
  if (window.__mapaClientesActivosRefrescarPing__ === refrescarPingEstados) {
    delete window.__mapaClientesActivosRefrescarPing__;
  }
  if (bounceTimer) {
    clearTimeout(bounceTimer);
    bounceTimer = null;
  }
  if (pingPollTimer) {
    clearInterval(pingPollTimer);
    pingPollTimer = null;
  }
  sharedInfoWindow?.close();
  markers.forEach((m) => m.setMap(null));
  markers = [];
  markerMeta = [];
  sharedInfoWindow = null;
  map = null;
  googleRef = null;
});
</script>
