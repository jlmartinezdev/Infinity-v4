<template>
  <div class="flex flex-col w-full h-full min-h-[420px]">
    <!-- Toolbar fuera del mapa -->
    <div class="flex-shrink-0 border-b border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 px-3 sm:px-4 py-2 space-y-2">
      <div class="flex flex-wrap items-center justify-between gap-2">
        <div class="min-w-0">
          <h1 class="text-base sm:text-lg font-semibold text-gray-900 dark:text-gray-100 leading-tight">Mapas de pedidos</h1>
          <p class="text-xs text-gray-500 dark:text-gray-400">
            Buscá pedidos · capas de cobertura · mapa / satélite
          </p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
          <div class="inline-flex rounded-lg border border-gray-300 dark:border-gray-600 overflow-hidden text-xs font-medium">
            <button
              type="button"
              class="px-3 py-1.5 transition-colors"
              :class="!satelite ? 'bg-indigo-600 text-white' : 'bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700'"
              @click="setMapaTipo(false)"
            >Mapa</button>
            <button
              type="button"
              class="px-3 py-1.5 transition-colors border-l border-gray-300 dark:border-gray-600"
              :class="satelite ? 'bg-indigo-600 text-white' : 'bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700'"
              @click="setMapaTipo(true)"
            >Satélite</button>
          </div>
        </div>
      </div>

      <div class="flex flex-wrap items-center gap-2">
        <div class="relative flex-1 min-w-[200px] max-w-xl">
          <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M11 18a7 7 0 100-14 7 7 0 000 14z" />
          </svg>
          <input
            v-model="busqueda"
            type="search"
            autocomplete="off"
            placeholder="Buscar cliente, documento, plan, zona…"
            class="w-full pl-9 pr-9 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-sm text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-indigo-500/40"
            @keydown.enter.prevent="abrirPrimerResultado"
          />
          <button
            v-if="busqueda"
            type="button"
            class="absolute right-2 top-1/2 -translate-y-1/2 p-1 rounded text-gray-400 hover:text-gray-600 dark:hover:text-gray-200"
            title="Limpiar"
            @click="limpiarBusqueda"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
          </button>
        </div>

        <span class="shrink-0 inline-flex items-center px-2.5 py-1.5 rounded-lg bg-gray-100 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-xs font-medium text-gray-600 dark:text-gray-300">
          {{ visiblesCount }}/{{ pedidos.length }} pedidos
        </span>

        <label class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-2.5 py-1.5 text-xs text-gray-700 dark:text-gray-200 cursor-pointer">
          <input v-model="capaPedidos" type="checkbox" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
          <span class="w-2.5 h-2.5 rounded-full bg-indigo-500"></span>
          Pedidos
        </label>
        <label class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-2.5 py-1.5 text-xs text-gray-700 dark:text-gray-200 cursor-pointer">
          <input v-model="capaClientes" type="checkbox" class="rounded border-gray-300 text-violet-600 focus:ring-violet-500" :disabled="cargandoClientes" />
          <span class="w-2.5 h-2.5 rounded-full bg-emerald-600"></span>
          Clientes{{ clientes.length ? ` (${clientes.length})` : '' }}
          <span v-if="cargandoClientes" class="text-gray-400">…</span>
        </label>
      </div>

      <ul
        v-if="busqueda.trim() && resultadosLista.length"
        class="max-w-xl max-h-44 overflow-y-auto rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-800 shadow-sm divide-y divide-gray-100 dark:divide-gray-700"
      >
        <li v-for="item in resultadosLista" :key="item.pedido.pedido_id">
          <button
            type="button"
            class="w-full text-left px-3 py-2 hover:bg-indigo-50 dark:hover:bg-indigo-900/30 transition-colors"
            @click="enfocarPedido(item.pedido.pedido_id)"
          >
            <div class="text-sm font-medium text-gray-900 dark:text-gray-100 truncate">
              #{{ item.pedido.pedido_id }} · {{ item.pedido.cliente || 'Sin cliente' }}
            </div>
            <div class="text-xs text-gray-500 dark:text-gray-400 truncate">
              <span v-if="item.pedido.documento">CI {{ item.pedido.documento }}</span>
              <span v-if="item.pedido.zona"> · {{ item.pedido.zona }}</span>
              <span v-if="item.pedido.plan_confirmado || item.pedido.plan_solicitado || item.pedido.plan">
                · {{ item.pedido.plan_confirmado || item.pedido.plan_solicitado || item.pedido.plan }}
              </span>
            </div>
          </button>
        </li>
      </ul>
      <p
        v-else-if="busqueda.trim() && !loading && visiblesCount === 0"
        class="max-w-xl text-xs text-amber-800 dark:text-amber-200 bg-amber-50 dark:bg-amber-900/40 border border-amber-200 dark:border-amber-800 rounded-lg px-3 py-2"
      >
        Sin coincidencias para “{{ busqueda.trim() }}”
      </p>
      <p v-if="errorCapa" class="text-xs text-red-600 dark:text-red-300">{{ errorCapa }}</p>
    </div>

    <div class="relative flex-1 min-h-0 bg-gray-100 dark:bg-gray-800">
      <div ref="mapContainer" class="absolute inset-0 w-full h-full"></div>
      <div v-if="loading" class="absolute inset-0 flex items-center justify-center bg-gray-100/80 dark:bg-gray-800/80 z-10">
        <span class="text-gray-600 dark:text-gray-400">Cargando mapa...</span>
      </div>
      <div v-if="error" class="absolute inset-0 flex items-center justify-center bg-red-50/90 dark:bg-red-900/20 p-4 z-10">
        <p class="text-red-700 dark:text-red-300 text-center">{{ error }}</p>
      </div>
      <div v-if="!apiKey" class="absolute inset-0 flex items-center justify-center bg-amber-50/90 dark:bg-amber-900/20 p-4 z-10">
        <p class="text-amber-800 dark:text-amber-200 text-center">Falta configurar GOOGLE_MAPS_API_KEY en .env</p>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted, onBeforeUnmount } from 'vue';
import { aprobarEstadoPedido } from '@/pedidos/aprobarEstadoPedido';

const props = defineProps({
  apiKey: { type: String, default: '' },
  pedidos: { type: Array, default: () => [] },
  nodos: { type: Array, default: () => [] },
  planes: { type: Array, default: () => [] },
  tiposTecnologia: { type: Array, default: () => [] },
  aprobarEstadoUrl: { type: String, default: '' },
  urlOpcionesNodoAprobacion: { type: String, default: '' },
  urlClientes: { type: String, default: '' },
});

const mapContainer = ref(null);
const loading = ref(true);
const error = ref('');
const errorCapa = ref('');
const busqueda = ref('');
const visiblesCount = ref(0);
const capaPedidos = ref(true);
const capaClientes = ref(false);
const satelite = ref(false);
const clientes = ref([]);
const cargandoClientes = ref(false);
let map = null;
/** @type {{ pedido: any, marker: any, infoWindow: any }[]} */
let markerEntries = [];
let markersClientes = [];
let infoWindows = [];
let infoWindowCliente = null;
const pedidosById = new Map();
let aprobandoKey = null;
let googleRef = null;
let clientesCargados = false;

const resultadosLista = computed(() => {
  const q = busqueda.value.trim();
  if (!q) return [];
  return markerEntries
    .filter((e) => pedidoCoincide(e.pedido, q))
    .slice(0, 12);
});

const aprobarConfig = () => ({
  nodos: props.nodos,
  planes: props.planes,
  tiposTecnologia: props.tiposTecnologia,
  aprobarEstadoUrl: props.aprobarEstadoUrl,
  urlOpcionesNodoAprobacion: props.urlOpcionesNodoAprobacion,
});

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

/**
 * Iconos originales de pedido (rack GPON / antena wireless).
 * size + scaledSize + width/height del SVG alineados para que Google no los aplaste.
 */
function getMarkerIcon(google, tecnologiaDesc = '') {
  const color = '#6366f1';
  const w = 36;
  const h = 36;
  const size = new google.maps.Size(w, h);
  const anchor = new google.maps.Point(w / 2, h);

  if (isGpon(tecnologiaDesc)) {
    const svg =
      `<svg xmlns="http://www.w3.org/2000/svg" width="${w}" height="${h}" viewBox="0 0 24 24" fill="none">` +
      `<rect x="3" y="17" width="7" height="5" rx="0.6" fill="${color}" stroke="#000000" stroke-width="1.5"/>` +
      `<rect x="8.5" y="2" width="7" height="5" rx="0.6" fill="${color}" stroke="#000000" stroke-width="1.5"/>` +
      `<rect x="14" y="17" width="7" height="5" rx="0.6" fill="${color}" stroke="#000000" stroke-width="1.5"/>` +
      `<path d="M6.5 17V13.5C6.5 12.3954 7.39543 11.5 8.5 11.5H15.5C16.6046 11.5 17.5 12.3954 17.5 13.5V17" stroke="#000000" stroke-width="1.5" fill="none"/>` +
      `<path d="M12 11.5V7" stroke="#000000" stroke-width="1.5" fill="none"/>` +
      `</svg>`;
    return {
      url: 'data:image/svg+xml;charset=UTF-8,' + encodeURIComponent(svg),
      size,
      scaledSize: size,
      anchor,
      origin: new google.maps.Point(0, 0),
    };
  }

  if (isWireless(tecnologiaDesc)) {
    // viewBox ampliado: el path original supera y=24 y se cortaba/deformaba
    const svg =
      `<svg xmlns="http://www.w3.org/2000/svg" width="${w}" height="${h}" viewBox="0 0 24 26" fill="${color}">` +
      `<path d="M13.68 24.8h-2.28v-11.56c0-0.48-0.36-0.84-0.84-0.84s-0.84 0.36-0.84 0.84v11.56h-2.28c-0.48 0-0.84 0.36-0.84 0.84s0.36 0.84 0.84 0.84h6.24c0.44 0 0.84-0.4 0.84-0.84 0-0.48-0.36-0.84-0.84-0.84zM12.88 16.4c-0.2 0-0.44-0.08-0.6-0.24-0.32-0.32-0.32-0.84 0-1.2 0.48-0.48 0.72-1.08 0.72-1.72s-0.24-1.28-0.72-1.72c-0.32-0.32-0.32-0.84 0-1.2 0.32-0.32 0.84-0.32 1.2 0 0.76 0.76 1.2 1.8 1.2 2.92s-0.44 2.12-1.2 2.92c-0.16 0.16-0.4 0.24-0.6 0.24zM15.2 18.72c-0.2 0-0.44-0.08-0.6-0.24-0.32-0.32-0.32-0.84 0-1.2 1.08-1.08 1.68-2.52 1.68-4.04s-0.6-2.96-1.68-4.08c-0.32-0.32-0.32-0.84 0-1.2 0.32-0.32 0.84-0.32 1.2 0 1.4 1.4 2.16 3.28 2.16 5.24 0 2-0.76 3.84-2.16 5.24-0.16 0.2-0.36 0.28-0.6 0.28zM17.44 20.96c-0.2 0-0.44-0.08-0.6-0.24-0.32-0.32-0.32-0.84 0-1.2 1.68-1.68 2.6-3.92 2.6-6.28s-0.92-4.6-2.6-6.28c-0.32-0.32-0.32-0.84 0-1.2 0.32-0.32 0.84-0.32 1.2 0 2 2 3.08 4.64 3.08 7.48 0 2.8-1.08 5.48-3.08 7.48-0.2 0.16-0.4 0.24-0.6 0.24zM7.64 16.16c-0.76-0.8-1.2-1.8-1.2-2.92s0.44-2.16 1.2-2.92c0.36-0.32 0.88-0.32 1.2 0 0.32 0.36 0.32 0.88 0 1.2-0.48 0.44-0.72 1.08-0.72 1.72s0.24 1.24 0.72 1.72c0.32 0.36 0.32 0.88 0 1.2-0.16 0.16-0.4 0.24-0.6 0.24s-0.44-0.08-0.6-0.24zM5.32 18.44c-1.4-1.4-2.16-3.24-2.16-5.24 0-1.96 0.76-3.84 2.16-5.24 0.36-0.32 0.88-0.32 1.2 0 0.32 0.36 0.32 0.88 0 1.2-1.08 1.12-1.68 2.56-1.68 4.08s0.6 2.96 1.68 4.04c0.32 0.36 0.32 0.88 0 1.2-0.16 0.16-0.4 0.24-0.6 0.24-0.24 0-0.44-0.08-0.6-0.28zM3.08 20.72c-2-2-3.08-4.68-3.08-7.48 0-2.84 1.08-5.48 3.08-7.48 0.36-0.32 0.88-0.32 1.2 0 0.32 0.36 0.32 0.88 0 1.2-1.68 1.68-2.6 3.92-2.6 6.28s0.92 4.6 2.6 6.28c0.32 0.36 0.32 0.88 0 1.2-0.16 0.16-0.4 0.24-0.6 0.24s-0.4-0.08-0.6-0.24z"/>` +
      `</svg>`;
    return {
      url: 'data:image/svg+xml;charset=UTF-8,' + encodeURIComponent(svg),
      size,
      scaledSize: size,
      anchor,
      origin: new google.maps.Point(0, 0),
    };
  }

  return null;
}

const MAP_VIEW_KEY = 'infinity.mapas-pedidos.view';

function saveMapView() {
  if (!map) return;
  const c = map.getCenter();
  const z = map.getZoom();
  if (!c || z == null) return;
  try {
    sessionStorage.setItem(MAP_VIEW_KEY, JSON.stringify({
      lat: c.lat(),
      lng: c.lng(),
      zoom: z,
      mapTypeId: map.getMapTypeId(),
    }));
  } catch (_) { /* ignore */ }
}

function restoreMapView() {
  if (!map) return false;
  try {
    const raw = sessionStorage.getItem(MAP_VIEW_KEY);
    if (!raw) return false;
    const v = JSON.parse(raw);
    if (!Number.isFinite(v.lat) || !Number.isFinite(v.lng) || !Number.isFinite(v.zoom)) return false;
    map.setCenter({ lat: v.lat, lng: v.lng });
    map.setZoom(v.zoom);
    if (v.mapTypeId) {
      map.setMapTypeId(v.mapTypeId);
      satelite.value = v.mapTypeId === 'hybrid' || v.mapTypeId === 'satellite';
    }
    return true;
  } catch (_) {
    return false;
  }
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

function escapeHtml(text) {
  if (!text) return '';
  const div = document.createElement('div');
  div.textContent = text;
  return div.innerHTML;
}

function estadoBadgeStyle(estado) {
  if (estado === 'A') return 'background:#dcfce7;color:#166534;';
  if (estado === 'D') return 'background:#fee2e2;color:#991b1b;';
  if (estado === 'P') return 'background:#fef9c3;color:#854d0e;';
  return 'background:#f3f4f6;color:#374151;';
}

function buildAnalisisBlock(titulo, analisis, campos) {
  const rows = [];
  if (analisis) {
    rows.push(`<span style="display:inline-block;padding:2px 8px;border-radius:9999px;font-size:11px;font-weight:600;${estadoBadgeStyle(analisis.estado)}">${escapeHtml(analisis.resolucion)}</span>`);
    if (analisis.fecha) rows.push(`<div><strong>Fecha:</strong> ${escapeHtml(analisis.fecha)}</div>`);
    if (analisis.usuario) rows.push(`<div><strong>Por:</strong> ${escapeHtml(analisis.usuario)}</div>`);
    campos.forEach(({ label, value }) => {
      if (value) rows.push(`<div><strong>${escapeHtml(label)}:</strong> ${escapeHtml(value)}</div>`);
    });
    if (analisis.notas) rows.push(`<div style="margin-top:4px;font-style:italic;color:#6b7280;">${escapeHtml(analisis.notas)}</div>`);
  } else {
    rows.push('<div style="color:#9ca3af;font-style:italic;">Sin registro</div>');
  }

  return `
    <div style="margin-top:10px;padding-top:10px;border-top:1px solid #e5e7eb;">
      <div style="font-size:12px;font-weight:700;color:#111827;margin-bottom:6px;">${escapeHtml(titulo)}</div>
      <div style="font-size:12px;color:#374151;line-height:1.45;display:flex;flex-direction:column;gap:3px;">
        ${rows.join('')}
      </div>
    </div>
  `;
}

function mapsUrlForPedido(pedido) {
  if (pedido.maps_url) return pedido.maps_url;
  const lat = pedido.lat != null && pedido.lat !== '' ? Number(pedido.lat) : NaN;
  const lon = pedido.lon != null && pedido.lon !== '' ? Number(pedido.lon) : NaN;
  if (Number.isFinite(lat) && Number.isFinite(lon)) {
    return `https://www.google.com/maps?q=${lat},${lon}`;
  }
  const gps = (pedido.maps_gps || '').toString().trim();
  if (!gps) return null;
  if (/^https?:\/\//i.test(gps)) return gps;
  const parts = gps.split(/[,;\s]+/).map((p) => p.trim()).filter(Boolean);
  if (parts.length >= 2) {
    const la = Number(parts[0]);
    const lo = Number(parts[1]);
    if (Number.isFinite(la) && Number.isFinite(lo)) {
      return `https://www.google.com/maps?q=${la},${lo}`;
    }
  }
  return `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(gps)}`;
}

function buildAprobarBlock(pedido) {
  if (!props.aprobarEstadoUrl || !pedido.estados_pendientes?.length) return '';
  const buttons = pedido.estados_pendientes.map((est) => {
    const label = escapeHtml(est.descripcion || `Estado #${est.estado_id}`);
    const parametro = escapeHtml(est.parametro || '');
    return `
      <button type="button"
        class="mapas-pedidos-iw-aprobar"
        data-pedido-id="${pedido.pedido_id}"
        data-estado-id="${est.estado_id}"
        data-parametro="${parametro}"
        style="display:inline-flex;align-items:center;gap:4px;margin-top:6px;margin-right:6px;padding:6px 10px;font-size:12px;font-weight:600;color:#fff;background:#16a34a;border:none;border-radius:6px;cursor:pointer;">
        Aprobar: ${label}
      </button>`;
  }).join('');
  return `
    <div style="margin-top:12px;padding-top:10px;border-top:1px solid #e5e7eb;">
      <div style="font-size:12px;font-weight:700;color:#111827;margin-bottom:4px;">Acciones</div>
      <div style="display:flex;flex-wrap:wrap;gap:4px;">${buttons}</div>
    </div>`;
}

function buildInfoWindowContent(pedido) {
  const factibilidad = buildAnalisisBlock('Análisis de factibilidad', pedido.analisis_factibilidad, [
    { label: 'Nodo', value: pedido.analisis_factibilidad?.nodo },
    { label: 'Tecnología', value: pedido.analisis_factibilidad?.tecnologia },
  ]);
  // Solo el plan del detalle estado 2 (no fallback a pedido.plan = plan al crear).
  const confirmacion = buildAnalisisBlock('Confirmación de plan', pedido.confirmacion_plan, [
    { label: 'Plan', value: pedido.confirmacion_plan?.plan || '—' },
  ]);
  const mapsUrl = mapsUrlForPedido(pedido);

  return `
    <div class="mapas-pedidos-iw" style="padding:12px;min-width:220px;max-width:340px;font-family:system-ui,sans-serif;">
      <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:10px;margin-bottom:4px;">
        <div style="font-weight:600;font-size:14px;color:#111827;line-height:1.3;">Pedido #${pedido.pedido_id}</div>
        <button type="button" class="mapas-pedidos-iw-close" aria-label="Cerrar" title="Cerrar"
          style="flex-shrink:0;width:24px;height:24px;margin:0;border:1px solid #d1d5db;border-radius:4px;background:#f9fafb;color:#374151;font-size:18px;line-height:1;cursor:pointer;display:flex;align-items:center;justify-content:center;">
          &times;
        </button>
      </div>
      ${pedido.cliente ? `<div style="font-size:13px;color:#374151;">${escapeHtml(pedido.cliente)}</div>` : ''}
      ${pedido.tecnologia_descripcion ? `<div style="font-size:11px;color:#4f46e5;margin-top:2px;">${escapeHtml(pedido.tecnologia_descripcion)}</div>` : ''}
      ${pedido.ubicacion ? `<div style="font-size:12px;color:#4b5563;margin-top:6px;">${escapeHtml(pedido.ubicacion)}</div>` : ''}
      ${pedido.fecha_pedido ? `<div style="font-size:11px;color:#6b7280;margin-top:4px;">Pedido: ${escapeHtml(pedido.fecha_pedido)}</div>` : ''}
      ${factibilidad}
      ${confirmacion}
      ${buildAprobarBlock(pedido)}
      ${mapsUrl ? `<a href="${escapeHtml(mapsUrl)}" target="_blank" rel="noopener" style="display:inline-block;margin-top:10px;font-size:12px;color:#2563eb;text-decoration:underline;">Abrir en Google Maps</a>` : ''}
    </div>
  `;
}

async function handleAprobarClick(btn) {
  const pedidoId = Number(btn.dataset.pedidoId);
  const estadoId = Number(btn.dataset.estadoId);
  const parametro = btn.dataset.parametro || '';
  const pedido = pedidosById.get(pedidoId);
  if (!pedido || !estadoId) return;

  const key = `${pedidoId}-${estadoId}`;
  if (aprobandoKey === key) return;
  aprobandoKey = key;
  btn.disabled = true;
  btn.style.opacity = '0.6';

  try {
    await aprobarEstadoPedido(aprobarConfig(), pedido, estadoId, parametro, { reloadOnSuccess: true });
  } catch (_e) {
    aprobandoKey = null;
    btn.disabled = false;
    btn.style.opacity = '1';
  }
}

function attachInfoWindowHandlers(infoWindow) {
  infoWindow.addListener('domready', () => {
    document.querySelectorAll('.mapas-pedidos-iw-close').forEach((btn) => {
      btn.onclick = (e) => {
        e.preventDefault();
        e.stopPropagation();
        infoWindow.close();
      };
    });
    document.querySelectorAll('.mapas-pedidos-iw-aprobar').forEach((btn) => {
      btn.onclick = (e) => {
        e.preventDefault();
        e.stopPropagation();
        handleAprobarClick(btn);
      };
    });
  });
}

function normalizeText(value) {
  return String(value ?? '')
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .toLowerCase()
    .trim();
}

function pedidoCoincide(pedido, query) {
  const q = normalizeText(query);
  if (!q) return true;
  const parts = [
    pedido.pedido_id,
    pedido.cliente,
    pedido.documento,
    pedido.zona,
    pedido.nodo,
    pedido.ubicacion,
    pedido.plan,
    pedido.plan_confirmado,
    pedido.plan_solicitado,
    pedido.tecnologia_descripcion,
    pedido.analisis_factibilidad?.nodo,
    pedido.analisis_factibilidad?.usuario,
    pedido.analisis_factibilidad?.tecnologia,
    pedido.confirmacion_plan?.plan,
    pedido.confirmacion_plan?.usuario,
  ];
  const haystack = normalizeText(parts.filter(Boolean).join(' '));
  // Todas las palabras del query deben coincidir (AND)
  return q.split(/\s+/).filter(Boolean).every((token) => haystack.includes(token));
}

function aplicarFiltro(ajustarVista = true) {
  if (!map || !googleRef) return;
  const q = busqueda.value.trim();
  const bounds = new googleRef.maps.LatLngBounds();
  let visibles = 0;

  infoWindows.forEach((iw) => iw.close());
  infoWindowCliente?.close();

  markerEntries.forEach(({ pedido, marker }) => {
    const ok = capaPedidos.value && pedidoCoincide(pedido, q);
    marker.setVisible(ok);
    if (ok) {
      visibles += 1;
      bounds.extend(marker.getPosition());
    }
  });

  visiblesCount.value = visibles;

  if (!ajustarVista || visibles === 0) return;
  if (visibles === 1) {
    const only = markerEntries.find((e) => e.marker.getVisible());
    if (only) {
      map.panTo(only.marker.getPosition());
      map.setZoom(Math.max(map.getZoom() || 12, 14));
    }
    return;
  }
  map.fitBounds(bounds, 48);
}

function enfocarPedido(pedidoId) {
  const entry = markerEntries.find((e) => e.pedido.pedido_id === pedidoId);
  if (!entry || !map) return;
  if (!capaPedidos.value) capaPedidos.value = true;
  aplicarFiltro(false);
  if (!entry.marker.getVisible()) {
    entry.marker.setVisible(true);
  }
  infoWindows.forEach((iw) => iw.close());
  infoWindowCliente?.close();
  map.panTo(entry.marker.getPosition());
  map.setZoom(Math.max(map.getZoom() || 14, 15));
  entry.infoWindow.open(map, entry.marker);
}

function abrirPrimerResultado() {
  const first = resultadosLista.value[0];
  if (first) enfocarPedido(first.pedido.pedido_id);
}

function limpiarBusqueda() {
  busqueda.value = '';
}

function setMapaTipo(usarSatelite) {
  satelite.value = !!usarSatelite;
  if (!map) return;
  map.setMapTypeId(satelite.value ? 'hybrid' : 'roadmap');
}

function pinClienteIcon(google) {
  // Verde esmeralda: distinto del índigo (P) / ámbar (W) de pedidos
  const color = '#059669';
  const svg =
    `<svg xmlns="http://www.w3.org/2000/svg" width="36" height="44" viewBox="0 0 36 44">` +
    `<path fill="${color}" stroke="#064e3b" stroke-width="1.2" d="M18 0C9.7 0 3 6.7 3 15c0 11 15 29 15 29s15-18 15-29C33 6.7 26.3 0 18 0z"/>` +
    `<text x="18" y="20" text-anchor="middle" fill="#ffffff" font-size="12" font-family="Arial,sans-serif" font-weight="700">C</text>` +
    `</svg>`;
  return {
    url: 'data:image/svg+xml;charset=UTF-8,' + encodeURIComponent(svg),
    size: new google.maps.Size(36, 44),
    scaledSize: new google.maps.Size(36, 44),
    anchor: new google.maps.Point(18, 44),
  };
}

function popupCliente(item) {
  const nodo = item.nodo || item.zona || '—';
  const plan = item.plan || '—';
  const doc = item.documento ? `<div style="color:#374151;margin-top:2px">CI: <strong style="color:#111827">${escapeHtml(item.documento)}</strong></div>` : '';
  const dir = item.direccion
    ? `<div style="color:#374151;margin-top:4px">${escapeHtml(item.direccion)}</div>`
    : '';
  const link = item.url
    ? `<a href="${escapeHtml(item.url)}" target="_blank" rel="noopener" style="display:inline-block;margin-top:10px;padding:6px 10px;background:#059669;color:#ffffff;text-decoration:none;border-radius:6px;font-size:12px;font-weight:600">Ver cliente</a>`
    : '';
  return (
    `<div style="min-width:200px;max-width:280px;padding:10px 12px;background:#ffffff;color:#111827;font:13px/1.45 system-ui,sans-serif">` +
    `<div style="font-size:11px;font-weight:700;letter-spacing:.03em;color:#059669;text-transform:uppercase;margin-bottom:4px">Cliente activo</div>` +
    `<div style="font-weight:700;font-size:14px;color:#111827">${escapeHtml(item.nombre || 'Cliente')}</div>` +
    doc +
    dir +
    `<div style="margin-top:8px;padding-top:8px;border-top:1px solid #e5e7eb">` +
    `<div style="color:#374151"><strong style="color:#111827">Nodo:</strong> ${escapeHtml(nodo)}</div>` +
    `<div style="color:#374151;margin-top:2px"><strong style="color:#111827">Plan:</strong> ${escapeHtml(plan)}</div>` +
    `</div>` +
    link +
    `</div>`
  );
}

function clearClienteMarkers() {
  markersClientes.forEach((m) => m.setMap(null));
  markersClientes = [];
}

function syncClientesMarkers() {
  clearClienteMarkers();
  if (!capaClientes.value || !map || !googleRef) return;
  if (!infoWindowCliente) {
    infoWindowCliente = new googleRef.maps.InfoWindow();
  }
  clientes.value.forEach((item) => {
    const lat = Number(item.lat);
    const lng = Number(item.lng);
    if (!Number.isFinite(lat) || !Number.isFinite(lng)) return;
    const marker = new googleRef.maps.Marker({
      map,
      position: { lat, lng },
      title: [item.nombre || 'Cliente', item.nodo].filter(Boolean).join(' · '),
      icon: pinClienteIcon(googleRef),
      zIndex: 5,
      opacity: 0.9,
    });
    marker.addListener('click', () => {
      infoWindows.forEach((iw) => iw.close());
      infoWindowCliente.setContent(popupCliente(item));
      infoWindowCliente.open({ map, anchor: marker });
    });
    markersClientes.push(marker);
  });
}

async function ensureClientes() {
  if (clientesCargados || !props.urlClientes) return;
  cargandoClientes.value = true;
  errorCapa.value = '';
  try {
    const res = await fetch(props.urlClientes, {
      headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      credentials: 'same-origin',
    });
    const data = await res.json().catch(() => ({}));
    if (!res.ok || data.success === false) {
      throw new Error(data.message || 'No se pudieron cargar clientes.');
    }
    clientes.value = Array.isArray(data.data) ? data.data : [];
    clientesCargados = true;
  } finally {
    cargandoClientes.value = false;
  }
}

// Solo reencuadra al buscar (con texto). Al limpiar o toglear capas, conserva la vista.
watch(busqueda, (q) => aplicarFiltro(String(q || '').trim().length > 0));
watch(capaPedidos, () => aplicarFiltro(false));
watch(capaClientes, async (on) => {
  if (on) {
    try {
      await ensureClientes();
    } catch (e) {
      errorCapa.value = e.message || 'No se pudieron cargar clientes.';
      capaClientes.value = false;
      return;
    }
  }
  syncClientesMarkers();
});

function initMap(google) {
  if (!mapContainer.value) return;
  googleRef = google;

  const center = props.pedidos.length
    ? { lat: props.pedidos[0].lat, lng: props.pedidos[0].lon }
    : { lat: -25.2637, lng: -57.5759 };

  map = new google.maps.Map(mapContainer.value, {
    center,
    zoom: props.pedidos.length ? 10 : 6,
    mapTypeId: satelite.value ? 'hybrid' : 'roadmap',
    mapTypeControl: true,
    mapTypeControlOptions: {
      style: google.maps.MapTypeControlStyle.HORIZONTAL_BAR,
      position: google.maps.ControlPosition.TOP_LEFT,
      mapTypeIds: ['roadmap', 'hybrid', 'satellite', 'terrain'],
    },
    streetViewControl: true,
    fullscreenControl: true,
    zoomControl: true,
  });

  map.addListener('click', () => {
    infoWindows.forEach((iw) => iw.close());
    infoWindowCliente?.close();
  });

  map.addListener('maptypeid_changed', () => {
    const id = map.getMapTypeId();
    satelite.value = id === 'hybrid' || id === 'satellite';
  });

  const bounds = new google.maps.LatLngBounds();

  props.pedidos.forEach((pedido) => {
    pedidosById.set(pedido.pedido_id, pedido);
    const position = { lat: pedido.lat, lng: pedido.lon };
    const marker = new google.maps.Marker({
      position,
      map,
      title: pedido.cliente || `Pedido #${pedido.pedido_id}`,
      zIndex: 30,
      icon: getMarkerIcon(google, pedido.tecnologia_descripcion) || undefined,
    });

    const infoWindow = new google.maps.InfoWindow({ content: buildInfoWindowContent(pedido) });
    attachInfoWindowHandlers(infoWindow);

    marker.addListener('click', () => {
      infoWindows.forEach((iw) => iw.close());
      infoWindowCliente?.close();
      infoWindow.open(map, marker);
    });

    markerEntries.push({ pedido, marker, infoWindow });
    infoWindows.push(infoWindow);
    bounds.extend(position);
  });

  visiblesCount.value = markerEntries.length;

  // Restaurar última vista; si no hay, encuadrar pedidos una sola vez.
  if (!restoreMapView() && props.pedidos.length > 1) {
    map.fitBounds(bounds);
  }

  map.addListener('idle', saveMapView);
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
    error.value = e.message || 'Error al cargar el mapa';
  } finally {
    loading.value = false;
  }
});

onBeforeUnmount(() => {
  infoWindows.forEach((iw) => iw.close());
  infoWindowCliente?.close();
  clearClienteMarkers();
  markerEntries.forEach(({ marker }) => marker.setMap(null));
  markerEntries = [];
  infoWindows = [];
  map = null;
  googleRef = null;
});
</script>

<style>
/* Un solo botón cerrar: ocultamos el de Google Maps y usamos el del contenido */
.gm-style .gm-style-iw-chr {
  display: none !important;
}
.gm-style .gm-style-iw-c {
  padding: 0 !important;
  background: #ffffff !important;
  color: #111827 !important;
}
.gm-style .gm-style-iw-d {
  overflow: auto !important;
  max-height: min(70vh, 520px) !important;
  background: #ffffff !important;
  color: #111827 !important;
}
</style>
