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
        <select
          v-if="personasMapa.length"
          v-model="filtroTecnico"
          class="rounded-lg bg-white/95 dark:bg-gray-900/95 px-2 py-1.5 text-xs shadow border border-gray-200 dark:border-gray-700 text-gray-700 dark:text-gray-200 max-w-[11rem]"
          title="Mostrar un técnico"
        >
          <option value="">Todos</option>
          <option v-for="p in personasMapa" :key="p.tecnico_id" :value="String(p.tecnico_id)">{{ p.nombre }}</option>
        </select>
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

    <div v-if="capaRecorrido && editando" class="absolute bottom-3 left-3 z-10 w-72 pointer-events-auto rounded-lg bg-white/95 dark:bg-gray-900/95 shadow border border-gray-200 dark:border-gray-700 px-3 py-2 text-xs text-gray-700 dark:text-gray-200">
      <div class="font-semibold text-gray-900 dark:text-gray-100">Editar ruta · {{ editando.nombre }}</div>
      <p class="mt-0.5 text-[11px] text-gray-500 dark:text-gray-400">Arrastrá un punto para corregir el desvío. Un clic en el mapa agrega un punto en el tramo más cercano.</p>
      <p v-if="errorEdicion" class="mt-1 text-red-600 dark:text-red-300">{{ errorEdicion }}</p>
      <div class="mt-2 flex flex-wrap gap-1">
        <button type="button" class="rounded-md border border-gray-300 dark:border-gray-600 px-2 py-1" @click="alternarCallesEdicion">{{ editando.porCalles ? 'Por calles' : 'Línea recta' }}</button>
        <button type="button" class="rounded-md border border-gray-300 dark:border-gray-600 px-2 py-1 disabled:opacity-40" :disabled="verticeActivo == null || editando.vertices.length <= 2" @click="quitarVertice">Quitar punto</button>
        <button type="button" class="rounded-md bg-emerald-600 text-white px-2 py-1 disabled:opacity-50" :disabled="guardandoRuta" @click="guardarEdicion">{{ guardandoRuta ? 'Guardando…' : 'Guardar' }}</button>
        <button type="button" class="rounded-md border border-gray-300 dark:border-gray-600 px-2 py-1" @click="restaurarRuta">Automático</button>
        <button type="button" class="rounded-md border border-gray-300 dark:border-gray-600 px-2 py-1" @click="cancelarEdicion">Cancelar</button>
      </div>
    </div>

    <div v-else-if="capaRecorrido" class="absolute bottom-3 left-3 z-10 max-w-xs pointer-events-auto rounded-lg bg-white/95 dark:bg-gray-900/95 shadow border border-gray-200 dark:border-gray-700 px-3 py-2 text-xs text-gray-700 dark:text-gray-200">
      <div class="flex items-center justify-between gap-2">
        <div class="font-semibold text-gray-900 dark:text-gray-100">{{ tituloRecorrido }}</div>
        <button
          type="button"
          class="shrink-0 rounded-md border border-gray-300 dark:border-gray-600 px-2 py-0.5 text-[11px] text-gray-700 dark:text-gray-200 disabled:opacity-40"
          :disabled="!recorridosVisibles.length"
          title="Dibujar desde el inicio hasta el fin"
          @click="reproducirRutas"
        >
          Dibujar
        </button>
      </div>
      <p class="mt-0.5 text-[11px] text-gray-500 dark:text-gray-400">{{ notaRecorrido }}</p>
      <p v-if="avisoRuta" class="mt-1 text-[11px] text-amber-700 dark:text-amber-300">{{ avisoRuta }}</p>
      <p v-if="errorRecorrido" class="mt-1 text-red-600 dark:text-red-300">{{ errorRecorrido }}</p>
      <ul v-else-if="recorridos.length" class="mt-1 max-h-40 overflow-y-auto space-y-1">
        <li v-if="filtroTecnico">
          <button type="button" class="text-left text-sky-700 dark:text-sky-300 hover:underline" @click="filtroTecnico = ''">Ver todos</button>
        </li>
        <li v-for="t in recorridos" :key="t.tecnico_id" class="flex items-center gap-1">
          <button type="button" class="flex items-center gap-1.5 text-left min-w-0 flex-1 hover:underline" :class="recorridoVisible(t.tecnico_id) ? '' : 'opacity-45'" @click="enfocarRecorrido(t)">
            <span class="w-2.5 h-2.5 rounded-full shrink-0" :style="{ background: colorTecnico(t.tecnico_id) }"></span>
            <span class="truncate">{{ t.nombre }} · {{ formatoDistancia(t.distancia_m) }}<template v-if="t.visitas"> · {{ t.visitas }} visitas</template></span>
          </button>
          <button
            type="button"
            class="shrink-0 rounded p-0.5 text-gray-500 hover:text-gray-900 hover:bg-gray-100 dark:text-gray-300 dark:hover:text-white dark:hover:bg-gray-800"
            title="Editar ruta"
            @click="empezarEdicion(t)"
          >
            <svg viewBox="0 0 24 24" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
              <path d="M12 20h9" />
              <path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L8 18l-4 1 1-4 11.5-11.5z" />
            </svg>
          </button>
          <button
            type="button"
            class="shrink-0 rounded p-0.5 text-gray-500 hover:text-gray-900 hover:bg-gray-100 dark:text-gray-300 dark:hover:text-white dark:hover:bg-gray-800"
            :title="recorridoVisible(t.tecnico_id) ? 'Ocultar recorrido' : 'Mostrar recorrido'"
            @click="alternarRecorrido(t.tecnico_id)"
          >
            <svg v-if="recorridoVisible(t.tecnico_id)" viewBox="0 0 24 24" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
              <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z" />
              <circle cx="12" cy="12" r="3" />
            </svg>
            <svg v-else viewBox="0 0 24 24" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
              <path d="M3 3l18 18" />
              <path d="M10.6 6.1A11 11 0 0 1 12 6c6.5 0 10 6 10 6a18 18 0 0 1-4.1 4.7" />
              <path d="M6.2 6.2C3.6 8.1 2 12 2 12s3.5 7 10 7a10 10 0 0 0 4-.8" />
              <path d="M9.9 9.9a3 3 0 0 0 4.2 4.2" />
            </svg>
          </button>
          <button
            type="button"
            class="shrink-0 rounded p-0.5 text-gray-500 hover:text-gray-900 hover:bg-gray-100 dark:text-gray-300 dark:hover:text-white dark:hover:bg-gray-800"
            title="Dibujar recorrido"
            @click="reproducirRuta(t)"
          >
            <svg viewBox="0 0 24 24" class="w-3.5 h-3.5" fill="currentColor" aria-hidden="true">
              <path d="M8 5.5v13l11-6.5-11-6.5z" />
            </svg>
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
  urlAjusteRecorrido: { type: String, default: '' },
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
const filtroTecnico = ref('');
const recorridosOcultos = ref({});
const editando = ref(null);
const verticeActivo = ref(null);
const guardandoRuta = ref(false);
const errorEdicion = ref('');
const satelite = ref(false);

const COLORES_RECORRIDO = [
  '#e11d48', '#2563eb', '#d97706', '#059669', '#7c3aed',
  '#db2777', '#0891b2', '#65a30d', '#ea580c', '#4f46e5',
  '#0f766e', '#b45309', '#be123c', '#1d4ed8', '#6d28d9',
  '#15803d', '#c026d3', '#0369a1', '#a16207', '#b91c1c',
];

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
let ultimosDibujos = [];
let animacionRuta = 0;
let animFrame = null;
let camsActivas = [];
let recorridoPoll = null;
let recorridoSeq = 0;
let rutaSeq = 0;
let rutasPorCalle = true;
let directionsService = null;
let editLine = null;
let editMarkers = [];
let editClick = null;
let editTimer = null;
let dibujoEdicion = [];
const cacheRutas = new Map();
const OFFLINE_MS = 5 * 60 * 1000;

function colorTecnico(id) {
  return colorPorId.value[id] || COLORES_RECORRIDO[Math.abs(Number(id) || 0) % COLORES_RECORRIDO.length];
}

function pasaFiltro(id) {
  return !filtroTecnico.value || String(id) === String(filtroTecnico.value);
}

const personasMapa = computed(() => {
  const nombres = new Map();
  tecnicos.value.forEach((t) => {
    nombres.set(t.tecnico_id, t.nombre || `Técnico ${t.tecnico_id}`);
  });
  recorridos.value.forEach((t) => {
    if (!nombres.has(t.tecnico_id)) nombres.set(t.tecnico_id, t.nombre || `Técnico ${t.tecnico_id}`);
  });
  return [...nombres.entries()]
    .map(([tecnico_id, nombre]) => ({ tecnico_id, nombre }))
    .sort((a, b) => a.nombre.localeCompare(b.nombre, 'es') || Number(a.tecnico_id) - Number(b.tecnico_id));
});

const colorPorId = computed(() => {
  const out = {};
  personasMapa.value.forEach((p, i) => {
    out[p.tecnico_id] = COLORES_RECORRIDO[i % COLORES_RECORRIDO.length];
  });
  return out;
});

function recorridoVisible(id) {
  return !recorridosOcultos.value[String(id)];
}

function alternarRecorrido(id) {
  const key = String(id);
  recorridosOcultos.value = { ...recorridosOcultos.value, [key]: !recorridosOcultos.value[key] };
  if (capaRecorrido.value) syncRecorridoFiguras();
}

const recorridosVisibles = computed(() => recorridos.value.filter((t) => pasaFiltro(t.tecnico_id) && recorridoVisible(t.tecnico_id)));

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

function pinIcon(google, color, glyph = '', online = null) {
  const w = 36;
  const h = 44;
  const label = glyph
    ? `<text x="18" y="20" text-anchor="middle" fill="#fff" font-size="12" font-family="sans-serif" font-weight="700">${glyph}</text>`
    : '<circle cx="18" cy="15" r="5" fill="#fff"/>';
  const badge = online === null
    ? ''
    : `<circle cx="28" cy="7" r="4.5" fill="${online ? '#16a34a' : '#6b7280'}" stroke="#fff" stroke-width="1.5"/>`;
  const svg =
    `<svg xmlns="http://www.w3.org/2000/svg" width="${w}" height="${h}" viewBox="0 0 36 44">` +
    `<path fill="${color}" stroke="#1f2937" stroke-width="1" d="M18 0C9.7 0 3 6.7 3 15c0 11 15 29 15 29s15-18 15-29C33 6.7 26.3 0 18 0z"/>` +
    label +
    badge +
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
        icon: pinIcon(googleRef, colorTecnico(item.tecnico_id), '', isOnline(item)),
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
      entry.marker.setIcon(pinIcon(googleRef, colorTecnico(item.tecnico_id), '', isOnline(item)));
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
    upsertTecnicos(tecnicos.value.filter((t) => pasaFiltro(t.tecnico_id)));
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

function cancelarAnimacionRuta() {
  animacionRuta += 1;
  if (animFrame) cancelAnimationFrame(animFrame);
  animFrame = null;
  camsActivas = [];
}

function completarAnimacionEnCurso(exceptoId) {
  camsActivas.forEach((c) => {
    if (exceptoId != null && String(c.id) === String(exceptoId)) return;
    if (c.poly) c.poly.setPath(c.camino.puntos);
    if (c.cabeza) c.cabeza.setMap(null);
  });
  camsActivas = [];
}

function clearRecorridoFiguras() {
  cancelarAnimacionRuta();
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

function largoTrazo(path) {
  let metros = 0;
  for (let i = 1; i < path.length; i += 1) metros += distanciaMetros(path[i - 1], path[i]);
  return metros;
}

function tramoRazonable(road, desde, hasta) {
  const recta = distanciaMetros(desde, hasta);
  const ruta = largoTrazo(road);
  if (!(ruta < 80000) || !road.length) return false;
  if (distanciaMetros(road[0], desde) > 400) return false;
  if (distanciaMetros(road[road.length - 1], hasta) > 400) return false;
  if (recta < 80) return ruta < 450;
  if (ruta > recta * 2.2 && ruta - recta > 800) return false;
  const tope = Math.max(500, recta * 0.7);
  const paso = Math.max(1, Math.floor(road.length / 12));
  for (let i = 0; i < road.length; i += paso) {
    if (distanciaASegmento(road[i], desde, hasta) > tope) return false;
  }
  return true;
}

function esPuntaYRetorno(origen, punta, regreso) {
  if (!regreso) return false;
  const ida = distanciaMetros(origen, punta);
  const vuelta = distanciaMetros(punta, regreso);
  const cierre = distanciaMetros(origen, regreso);
  if (ida < 70 || vuelta < 70) return false;
  return cierre <= 90 || (cierre < ida * 0.45 && cierre < vuelta * 0.45);
}

function anexarCamino(destino, camino) {
  if (!camino?.length) return;
  const inicio = destino.length && distanciaMetros(destino[destino.length - 1], camino[0]) < 8 ? 1 : 0;
  for (let i = inicio; i < camino.length; i += 1) destino.push(camino[i]);
}

function caminoDeLegs(legs) {
  const road = [];
  (legs || []).forEach((leg) => {
    (leg.steps || []).forEach((step) => {
      (step.path || []).forEach((ll) => road.push({ lat: ll.lat(), lng: ll.lng() }));
    });
  });
  return road;
}

function recortarTramo(seg, desde, hasta) {
  if (!seg || seg.length < 2) return [desde, hasta];
  let i0 = 0;
  let mejor0 = Infinity;
  for (let i = 0; i < seg.length; i += 1) {
    const d = distanciaMetros(seg[i], desde);
    if (d < mejor0) {
      mejor0 = d;
      i0 = i;
    }
    if (d <= 30 && i > 0) break;
  }
  let i1 = i0;
  let mejor1 = Infinity;
  for (let i = i0; i < seg.length; i += 1) {
    const d = distanciaMetros(seg[i], hasta);
    if (d < mejor1) {
      mejor1 = d;
      i1 = i;
    }
    if (d <= 40 && i > i0) break;
  }
  const cortado = seg.slice(i0, Math.max(i0, i1) + 1).map((p) => ({ lat: p.lat, lng: p.lng }));
  if (cortado.length < 2) return [{ lat: desde.lat, lng: desde.lng }, { lat: hasta.lat, lng: hasta.lng }];
  cortado[0] = { lat: desde.lat, lng: desde.lng };
  cortado[cortado.length - 1] = { lat: hasta.lat, lng: hasta.lng };
  return cortado;
}

function pedirPar(desde, hasta) {
  if (!rutasPorCalle) return Promise.resolve(null);
  if (!directionsService) directionsService = new googleRef.maps.DirectionsService();
  return new Promise((resolve) => {
    directionsService.route({
      origin: desde,
      destination: hasta,
      travelMode: googleRef.maps.TravelMode.DRIVING,
      region: 'py',
    }, (result, status) => {
      if (status === 'REQUEST_DENIED' || status === 'OVER_QUERY_LIMIT') rutasPorCalle = false;
      const seg = status === 'OK' ? caminoDeLegs(result?.routes?.[0]?.legs) : [];
      if (seg.length > 1 && tramoRazonable(seg, desde, hasta)) resolve(recortarTramo(seg, desde, hasta));
      else resolve([{ lat: desde.lat, lng: desde.lng }, { lat: hasta.lat, lng: hasta.lng }]);
    });
  });
}

function pedirTramo(trozo) {
  if (!rutasPorCalle || trozo.length < 2) return Promise.resolve(null);
  if (trozo.length === 2) return pedirPar(trozo[0], trozo[1]);
  if (!directionsService) directionsService = new googleRef.maps.DirectionsService();
  const request = {
    origin: trozo[0],
    destination: trozo[trozo.length - 1],
    waypoints: trozo.slice(1, -1).map((p) => ({ location: p, stopover: true })),
    travelMode: googleRef.maps.TravelMode.DRIVING,
    optimizeWaypoints: false,
    region: 'py',
  };
  return new Promise((resolve) => {
    directionsService.route(request, (result, status) => {
      const partir = () => {
        const medio = Math.ceil(trozo.length / 2);
        Promise.all([
          pedirTramo(trozo.slice(0, medio)),
          pedirTramo(trozo.slice(medio - 1)),
        ]).then(([a, b]) => {
          if (!a || !b) {
            resolve(null);
            return;
          }
          const unido = a.slice();
          anexarCamino(unido, b);
          resolve(unido);
        });
      };
      if ((status === 'MAX_WAYPOINTS_EXCEEDED' || status === 'ZERO_RESULTS' || status === 'NOT_FOUND') && trozo.length > 3) {
        partir();
        return;
      }
      if (status === 'REQUEST_DENIED' || status === 'OVER_QUERY_LIMIT') rutasPorCalle = false;
      if (status !== 'OK' || !result?.routes?.[0]) {
        resolve(null);
        return;
      }
      const puntos = trozo;
      const legs = result.routes[0].legs || [];
      if (legs.length !== puntos.length - 1) {
        if (trozo.length > 3) {
          partir();
          return;
        }
        const road = caminoDeLegs(legs);
        resolve(road.length > 1 ? recortarTramo(road, puntos[0], puntos[puntos.length - 1]) : null);
        return;
      }
      const road = [];
      let previo = null;
      legs.forEach((leg, i) => {
        const desde = puntos[i];
        const hasta = puntos[i + 1];
        const seg = caminoDeLegs([leg]);
        let camino;
        if (i > 0 && previo?.length > 1 && esPuntaYRetorno(puntos[i - 1], desde, hasta)) {
          camino = previo.slice().reverse();
          camino[0] = { lat: desde.lat, lng: desde.lng };
          camino[camino.length - 1] = { lat: hasta.lat, lng: hasta.lng };
        } else if (seg.length > 1 && tramoRazonable(seg, desde, hasta)) {
          camino = recortarTramo(seg, desde, hasta);
        } else {
          camino = [{ lat: desde.lat, lng: desde.lng }, { lat: hasta.lat, lng: hasta.lng }];
        }
        anexarCamino(road, camino);
        previo = camino;
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
    let i = 0;
    while (i < muestra.length - 1) {
      if (!rutasPorCalle) return { path, porCalles: false };
      if (i + 2 < muestra.length && esPuntaYRetorno(muestra[i], muestra[i + 1], muestra[i + 2])) {
        const ida = await pedirPar(muestra[i], muestra[i + 1]);
        if (!ida) return { path, porCalles: false };
        anexarCamino(unido, ida);
        const vuelta = ida.map((p) => ({ lat: p.lat, lng: p.lng })).reverse();
        vuelta[0] = { lat: muestra[i + 1].lat, lng: muestra[i + 1].lng };
        vuelta[vuelta.length - 1] = { lat: muestra[i + 2].lat, lng: muestra[i + 2].lng };
        anexarCamino(unido, vuelta);
        i += 2;
        continue;
      }
      let j = i + 1;
      while (j < muestra.length - 1 && !(j + 2 < muestra.length && esPuntaYRetorno(muestra[j], muestra[j + 1], muestra[j + 2]))) {
        j += 1;
        if (j - i >= 24) break;
      }
      const road = await pedirTramo(muestra.slice(i, j + 1));
      if (!road) return { path, porCalles: false };
      anexarCamino(unido, road);
      i = j;
    }
    if (unido.length < 2) return { path, porCalles: false };
    cacheRutas.set(firma, unido);
    return { path: unido, porCalles: true };
  } catch {
    return { path, porCalles: false };
  }
}

function prefiereMenosMovimiento() {
  return window.matchMedia?.('(prefers-reduced-motion: reduce)').matches === true;
}

function avanceSuave(t) {
  const x = Math.min(1, Math.max(0, t));
  return 1 - (1 - x) ** 3;
}

function duracionDibujo(metros) {
  return Math.round(Math.min(7500, Math.max(2200, (metros / 1000) * 550)));
}

function caminoAnimable(path) {
  const puntos = [];
  const acum = [];
  let total = 0;
  path.forEach((p, idx) => {
    if (idx > 0) total += distanciaMetros(path[idx - 1], p);
    puntos.push({ lat: p.lat, lng: p.lng });
    acum.push(total);
  });
  const tope = 520;
  if (puntos.length <= tope || total < 1) return { puntos, acum, total };
  const paso = total / (tope - 1);
  const out = [puntos[0]];
  let meta = paso;
  for (let i = 1; i < puntos.length - 1 && out.length < tope - 1; i += 1) {
    if (acum[i] >= meta) {
      out.push(puntos[i]);
      meta += paso;
    }
  }
  out.push(puntos[puntos.length - 1]);
  const fino = [0];
  for (let i = 1; i < out.length; i += 1) fino.push(fino[i - 1] + distanciaMetros(out[i - 1], out[i]));
  return { puntos: out, acum: fino, total: fino[fino.length - 1] };
}

function sliceHasta(camino, metros) {
  const { puntos, acum, total } = camino;
  if (!puntos.length) return [];
  const m = Math.max(0, Math.min(total, metros));
  if (m <= 0 || puntos.length < 2) return [puntos[0]];
  let lo = 1;
  let hi = acum.length - 1;
  while (lo < hi) {
    const mid = (lo + hi) >> 1;
    if (acum[mid] < m) lo = mid + 1;
    else hi = mid;
  }
  const span = acum[lo] - acum[lo - 1];
  const t = span < 0.01 ? 1 : (m - acum[lo - 1]) / span;
  const head = {
    lat: puntos[lo - 1].lat + (puntos[lo].lat - puntos[lo - 1].lat) * t,
    lng: puntos[lo - 1].lng + (puntos[lo].lng - puntos[lo - 1].lng) * t,
  };
  const parcial = puntos.slice(0, lo);
  parcial.push(head);
  return parcial;
}

function iconoCabeza(color) {
  const s = 14;
  const svg = `<svg xmlns="http://www.w3.org/2000/svg" width="${s}" height="${s}"><circle cx="${s / 2}" cy="${s / 2}" r="${(s / 2) - 2}" fill="${color}" stroke="#fff" stroke-width="2"/></svg>`;
  return {
    url: 'data:image/svg+xml;charset=UTF-8,' + encodeURIComponent(svg),
    scaledSize: new googleRef.maps.Size(s, s),
    anchor: new googleRef.maps.Point(s / 2, s / 2),
  };
}

function animarTrazos(dibujos, { conservarOtros = false } = {}) {
  if (conservarOtros) completarAnimacionEnCurso(dibujos.length === 1 ? dibujos[0].item.tecnico_id : null);
  cancelarAnimacionRuta();
  const token = animacionRuta;
  if (!conservarOtros) {
    figurasRecorrido.forEach((f) => f.setMap(null));
    figurasRecorrido = [];
  } else {
    const ids = new Set(dibujos.map((d) => String(d.item.tecnico_id)));
    figurasRecorrido = figurasRecorrido.filter((f) => {
      if (!ids.has(String(f.tecnicoId))) return true;
      f.setMap(null);
      return false;
    });
  }
  if (prefiereMenosMovimiento()) {
    dibujos.forEach((d) => dibujarTrazo(d.item, d.path, d.color));
    return;
  }
  const cams = [];
  dibujos.forEach((d) => {
    if (!d.path || d.path.length < 2) {
      if (d.path?.length) dibujarTrazo(d.item, d.path, d.color);
      return;
    }
    const camino = caminoAnimable(d.path);
    if (camino.total < 1) {
      dibujarTrazo(d.item, d.path, d.color);
      return;
    }
    const poly = new googleRef.maps.Polyline({
      map,
      path: [camino.puntos[0]],
      strokeColor: d.color,
      strokeOpacity: 0.95,
      strokeWeight: 5,
      geodesic: false,
      zIndex: 35,
    });
    poly.addListener('click', (e) => {
      infoWindow.setContent(popupRecorrido(d.item));
      infoWindow.setPosition(e.latLng);
      infoWindow.open(map);
    });
    const cabeza = new googleRef.maps.Marker({
      map,
      position: camino.puntos[0],
      clickable: false,
      zIndex: 46,
      icon: iconoCabeza(d.color),
    });
    poly.tecnicoId = d.item.tecnico_id;
    cabeza.tecnicoId = d.item.tecnico_id;
    figurasRecorrido.push(poly, cabeza);
    cams.push({ id: d.item.tecnico_id, camino, poly, cabeza, ms: duracionDibujo(camino.total) });
  });
  camsActivas = cams;
  if (!cams.length) return;
  const t0 = performance.now();
  const tick = (now) => {
    if (token !== animacionRuta || !map) return;
    let sigue = false;
    const avance = now - t0;
    cams.forEach((c) => {
      const t = Math.min(1, avance / c.ms);
      const parcial = sliceHasta(c.camino, c.camino.total * avanceSuave(t));
      c.poly.setPath(parcial);
      const punta = parcial[parcial.length - 1];
      if (punta) c.cabeza.setPosition(punta);
      if (t < 1) sigue = true;
      else c.cabeza.setMap(null);
    });
    if (sigue) animFrame = requestAnimationFrame(tick);
    else {
      animFrame = null;
      camsActivas = [];
    }
  };
  animFrame = requestAnimationFrame(tick);
}

function reproducirRutas() {
  if (!ultimosDibujos.length || !map || !googleRef) return;
  animarTrazos(ultimosDibujos);
}

async function reproducirRuta(t) {
  if (!map || !googleRef || !t) return;
  if (editando.value && String(editando.value.tecnico_id) === String(t.tecnico_id)) return;
  const id = String(t.tecnico_id);
  if (!recorridoVisible(t.tecnico_id)) {
    recorridosOcultos.value = { ...recorridosOcultos.value, [id]: false };
    await syncRecorridoFiguras();
  }
  const dibujo = ultimosDibujos.find((d) => String(d.item.tecnico_id) === id);
  if (!dibujo || (dibujo.path || []).length < 2) return;
  animarTrazos([dibujo], { conservarOtros: true });
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
    marker.tecnicoId = item.tecnico_id;
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
  poly.tecnicoId = item.tecnico_id;
  figurasRecorrido.push(poly);
}

function iconoVertice(color, activo) {
  const s = activo ? 18 : 14;
  const svg = `<svg xmlns="http://www.w3.org/2000/svg" width="${s}" height="${s}"><circle cx="${s / 2}" cy="${s / 2}" r="${(s / 2) - 2}" fill="${color}" stroke="#fff" stroke-width="2"/></svg>`;
  return {
    url: 'data:image/svg+xml;charset=UTF-8,' + encodeURIComponent(svg),
    scaledSize: new googleRef.maps.Size(s, s),
    anchor: new googleRef.maps.Point(s / 2, s / 2),
  };
}

function distanciaASegmento(p, a, b) {
  const lat0 = (a.lat * Math.PI) / 180;
  const x = (q) => ((q.lng - a.lng) * Math.PI) / 180 * Math.cos(lat0) * 6371000;
  const y = (q) => ((q.lat - a.lat) * Math.PI) / 180 * 6371000;
  const px = x(p);
  const py = y(p);
  const bx = x(b);
  const by = y(b);
  const len2 = bx * bx + by * by;
  if (len2 < 1) return Math.hypot(px, py);
  const t = Math.max(0, Math.min(1, (px * bx + py * by) / len2));
  return Math.hypot(px - t * bx, py - t * by);
}

function limpiarEdicion() {
  if (editTimer) clearTimeout(editTimer);
  editTimer = null;
  if (editClick) googleRef?.maps?.event?.removeListener(editClick);
  editClick = null;
  editMarkers.forEach((m) => m.setMap(null));
  editMarkers = [];
  if (editLine) editLine.setMap(null);
  editLine = null;
  dibujoEdicion = [];
}

function tokenCsrf() {
  return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
}

async function refrescarLineaEdicion() {
  if (!editando.value || !map || !googleRef) return;
  const vertices = editando.value.vertices;
  let path = vertices;
  if (editando.value.porCalles && vertices.length > 1) {
    const ruta = await rutaPorCalles(vertices);
    path = ruta.path;
  }
  dibujoEdicion = path;
  const color = colorTecnico(editando.value.tecnico_id);
  if (!editLine) {
    editLine = new googleRef.maps.Polyline({
      map,
      path,
      strokeColor: color,
      strokeOpacity: 0.95,
      strokeWeight: 5,
      zIndex: 60,
    });
  } else {
    editLine.setPath(path);
    editLine.setOptions({ strokeColor: color });
  }
}

function syncMarcadoresEdicion() {
  editMarkers.forEach((m) => m.setMap(null));
  editMarkers = [];
  if (!editando.value || !map || !googleRef) return;
  const color = colorTecnico(editando.value.tecnico_id);
  editando.value.vertices.forEach((p, i) => {
    const marker = new googleRef.maps.Marker({
      map,
      position: p,
      draggable: true,
      icon: iconoVertice(color, verticeActivo.value === i),
      zIndex: 70,
      title: 'Arrastrá para corregir',
    });
    marker.addListener('click', () => {
      verticeActivo.value = i;
      syncMarcadoresEdicion();
      refrescarLineaEdicion();
    });
    marker.addListener('dragend', (e) => {
      editando.value.vertices[i] = { lat: e.latLng.lat(), lng: e.latLng.lng() };
      refrescarLineaEdicion();
    });
    editMarkers.push(marker);
  });
}

function programarLineaEdicion() {
  if (editTimer) clearTimeout(editTimer);
  editTimer = setTimeout(() => refrescarLineaEdicion(), 250);
}

function empezarEdicion(t) {
  const crudos = (t.traza || [])
    .map((p) => ({ lat: Number(p.lat), lng: Number(p.lng) }))
    .filter((p) => Number.isFinite(p.lat) && Number.isFinite(p.lng));
  if (crudos.length < 2) return;
  limpiarEdicion();
  errorEdicion.value = '';
  verticeActivo.value = null;
  editando.value = {
    tecnico_id: t.tecnico_id,
    nombre: t.nombre,
    porCalles: !t.manual,
    vertices: (t.manual ? crudos : muestrearRuta(crudos, 180, 40)).map((p) => ({ lat: p.lat, lng: p.lng })),
  };
  syncRecorridoFiguras();
  syncMarcadoresEdicion();
  refrescarLineaEdicion();
  editClick = map.addListener('click', (e) => insertarVertice(e.latLng.lat(), e.latLng.lng()));
  enfocarRecorrido(t);
}

function insertarVertice(lat, lng) {
  if (!editando.value) return;
  const punto = { lat, lng };
  const vertices = editando.value.vertices;
  let mejor = 0;
  let mejorDist = Infinity;
  for (let i = 0; i < vertices.length - 1; i += 1) {
    const d = distanciaASegmento(punto, vertices[i], vertices[i + 1]);
    if (d < mejorDist) {
      mejorDist = d;
      mejor = i;
    }
  }
  vertices.splice(mejor + 1, 0, punto);
  verticeActivo.value = mejor + 1;
  syncMarcadoresEdicion();
  programarLineaEdicion();
}

function quitarVertice() {
  if (!editando.value || verticeActivo.value == null || editando.value.vertices.length <= 2) return;
  editando.value.vertices.splice(verticeActivo.value, 1);
  verticeActivo.value = null;
  syncMarcadoresEdicion();
  programarLineaEdicion();
}

function alternarCallesEdicion() {
  if (!editando.value) return;
  editando.value.porCalles = !editando.value.porCalles;
  refrescarLineaEdicion();
}

function cancelarEdicion() {
  limpiarEdicion();
  editando.value = null;
  verticeActivo.value = null;
  errorEdicion.value = '';
  syncRecorridoFiguras();
}

async function enviarAjuste(method, body) {
  const res = await fetch(props.urlAjusteRecorrido, {
    method,
    headers: {
      Accept: 'application/json',
      'Content-Type': 'application/json',
      'X-CSRF-TOKEN': tokenCsrf(),
      'X-Requested-With': 'XMLHttpRequest',
    },
    credentials: 'same-origin',
    body: JSON.stringify(body),
  });
  const data = await res.json().catch(() => ({}));
  if (!res.ok || data.success === false) {
    throw new Error(data.message || 'No se pudo guardar la ruta.');
  }
}

async function guardarEdicion() {
  if (!editando.value || !props.urlAjusteRecorrido) {
    errorEdicion.value = 'No está configurado el guardado de la ruta.';
    return;
  }
  guardandoRuta.value = true;
  errorEdicion.value = '';
  try {
    await refrescarLineaEdicion();
    const traza = (dibujoEdicion.length > 1 ? dibujoEdicion : editando.value.vertices)
      .map((p) => ({ lat: Number(p.lat), lng: Number(p.lng) }));
    await enviarAjuste('POST', {
      tecnico_id: editando.value.tecnico_id,
      fecha: fechaRecorrido.value,
      traza,
    });
    const id = String(editando.value.tecnico_id);
    const item = recorridos.value.find((t) => String(t.tecnico_id) === id);
    if (item) {
      item.traza = traza;
      item.manual = true;
    }
    cancelarEdicion();
  } catch (e) {
    errorEdicion.value = e.message || 'No se pudo guardar la ruta.';
  } finally {
    guardandoRuta.value = false;
  }
}

async function restaurarRuta() {
  if (!editando.value || !props.urlAjusteRecorrido) return;
  guardandoRuta.value = true;
  errorEdicion.value = '';
  try {
    await enviarAjuste('DELETE', {
      tecnico_id: editando.value.tecnico_id,
      fecha: fechaRecorrido.value,
    });
    const id = String(editando.value.tecnico_id);
    cancelarEdicion();
    await fetchRecorrido();
    const item = recorridos.value.find((t) => String(t.tecnico_id) === id);
    if (item) item.manual = false;
    syncRecorridoFiguras();
  } catch (e) {
    errorEdicion.value = e.message || 'No se pudo restaurar la ruta.';
  } finally {
    guardandoRuta.value = false;
  }
}

async function syncRecorridoFiguras({ animar = false } = {}) {
  const seq = ++rutaSeq;
  clearRecorridoFiguras();
  if (!capaRecorrido.value || !map || !googleRef) {
    ultimosDibujos = [];
    return;
  }
  let algunaRecta = false;
  const dibujos = [];
  await Promise.all(recorridosVisibles.value.map(async (t) => {
    const path = (t.traza || [])
      .map((p) => ({ lat: Number(p.lat), lng: Number(p.lng) }))
      .filter((p) => Number.isFinite(p.lat) && Number.isFinite(p.lng));
    if (!path.length || seq !== rutaSeq) return;
    const color = colorTecnico(t.tecnico_id);
    if (editando.value && String(editando.value.tecnico_id) === String(t.tecnico_id)) return;
    const ruta = t.manual
      ? { path, porCalles: true }
      : (path.length > 1 ? await rutaPorCalles(path) : { path, porCalles: true });
    if (seq !== rutaSeq || !capaRecorrido.value || !map) return;
    if (path.length > 1 && !ruta.porCalles) algunaRecta = true;
    dibujos.push({ item: t, path: ruta.path, color });
  }));
  if (seq !== rutaSeq || !capaRecorrido.value) return;
  avisoRuta.value = algunaRecta
    ? 'No se pudo calzar alguna ruta sobre las calles. Esa línea une los puntos en recta.'
    : '';
  ultimosDibujos = dibujos;
  if (animar) animarTrazos(dibujos);
  else dibujos.forEach((d) => dibujarTrazo(d.item, d.path, d.color));
}

function elegirTecnico(t) {
  filtroTecnico.value = String(t.tecnico_id);
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

async function fetchRecorrido({ animar = false } = {}) {
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
    syncRecorridoFiguras({ animar });
  } catch (e) {
    if (seq !== recorridoSeq) return;
    errorRecorrido.value = e.message || 'No se pudo cargar el recorrido.';
  }
}

watch(filtroTecnico, () => {
  upsertTecnicos(tecnicos.value.filter((t) => pasaFiltro(t.tecnico_id)));
  if (capaRecorrido.value) syncRecorridoFiguras({ animar: true });
  if (!filtroTecnico.value || !map) return;
  const rec = recorridos.value.find((t) => String(t.tecnico_id) === filtroTecnico.value);
  if (rec && (rec.traza || []).length) {
    enfocarRecorrido(rec);
    return;
  }
  const entry = [...markersTecnicos.entries()].find(([id]) => String(id) === filtroTecnico.value);
  if (entry) map.panTo(entry[1].marker.getPosition());
});

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
    limpiarEdicion();
    editando.value = null;
    syncRecorridoPoll();
    return;
  }
  await fetchRecorrido({ animar: true });
  syncRecorridoPoll();
});
watch(fechaRecorrido, async () => {
  if (editando.value) cancelarEdicion();
  if (!capaRecorrido.value) return;
  await fetchRecorrido({ animar: true });
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
      await fetchRecorrido({ animar: true });
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
  limpiarEdicion();
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
