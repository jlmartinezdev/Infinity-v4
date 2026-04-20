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
      <span class="inline-block w-3 h-3 rounded-full border-2 border-white ring-1 ring-gray-400 bg-[#1a73e8] ml-2 mr-1 align-middle"></span> Vos
    </div>
    <div v-if="apiKey && !loading && !error" class="absolute bottom-4 right-4 z-10 flex flex-col items-end gap-1">
      <button
        type="button"
        class="flex h-11 w-11 items-center justify-center rounded-full bg-white dark:bg-gray-800 shadow-lg border border-gray-200 dark:border-gray-600 text-[#1a73e8] hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors focus:outline-none focus:ring-2 focus:ring-purple-500 focus:ring-offset-2 dark:focus:ring-offset-gray-900"
        title="Centrar el mapa en tu ubicación (GPS)"
        aria-label="Mi ubicación"
        :disabled="ubicacionCargando"
        @click="irAMiUbicacion"
      >
        <svg v-if="!ubicacionCargando" class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
          <circle cx="12" cy="12" r="3" fill="currentColor" stroke="none" />
          <circle cx="12" cy="12" r="8" />
          <path stroke-linecap="round" d="M12 2v3M12 19v3M2 12h3M19 12h3" />
        </svg>
        <span v-else class="h-5 w-5 animate-spin rounded-full border-2 border-[#1a73e8] border-t-transparent" aria-hidden="true"></span>
      </button>
      <p
        v-if="ubicacionMensaje"
        class="max-w-[220px] rounded-lg bg-white/95 dark:bg-gray-900/95 px-2 py-1.5 text-xs text-red-700 dark:text-red-300 shadow border border-red-200 dark:border-red-800"
      >
        {{ ubicacionMensaje }}
      </p>
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
const ubicacionCargando = ref(false);
const ubicacionMensaje = ref('');
let map = null;
let markers = [];
let polylines = [];
/** Marcador del GPS del dispositivo (no forma parte de markers de infraestructura). */
let miUbicacionMarker = null;
let miUbicacionInfoWindow = null;

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

function irAMiUbicacion() {
  ubicacionMensaje.value = '';
  if (!map || typeof navigator === 'undefined' || !navigator.geolocation) {
    ubicacionMensaje.value = 'Tu navegador no permite obtener la ubicación.';
    return;
  }
  if (!window.google?.maps) return;

  ubicacionCargando.value = true;
  const google = window.google;

  navigator.geolocation.getCurrentPosition(
    (pos) => {
      ubicacionCargando.value = false;
      const lat = pos.coords.latitude;
      const lng = pos.coords.longitude;
      const posLatLng = { lat, lng };
      const precisionM = pos.coords.accuracy != null ? Math.round(pos.coords.accuracy) : null;

      if (miUbicacionMarker) {
        miUbicacionMarker.setMap(null);
        miUbicacionMarker = null;
      }
      if (miUbicacionInfoWindow) {
        miUbicacionInfoWindow.close();
        miUbicacionInfoWindow = null;
      }

      miUbicacionMarker = new google.maps.Marker({
        position: posLatLng,
        map,
        title: 'Tu ubicación',
        zIndex: 9999,
        icon: {
          path: google.maps.SymbolPath.CIRCLE,
          scale: 11,
          fillColor: '#1A73E8',
          fillOpacity: 1,
          strokeColor: '#ffffff',
          strokeWeight: 3,
        },
      });

      const mapsQ = encodeURIComponent(`${lat},${lng}`);
      const precHtml =
        precisionM != null
          ? `<div style="font-size:11px;color:#5f6368;margin-top:4px">Precisión aproximada: ±${precisionM} m</div>`
          : '';
      const html = `
        <div style="padding:10px 12px;font-family:system-ui,sans-serif;min-width:160px">
          <div style="font-size:12px;font-weight:600;color:#202124">Tu ubicación</div>
          ${precHtml}
          <a href="https://www.google.com/maps?q=${mapsQ}" target="_blank" rel="noopener noreferrer" style="display:inline-block;margin-top:8px;font-size:12px;color:#1a73e8">Abrir en Google Maps</a>
        </div>
      `;
      miUbicacionInfoWindow = new google.maps.InfoWindow({ content: html });
      miUbicacionMarker.addListener('click', () => {
        miUbicacionInfoWindow.open(map, miUbicacionMarker);
      });

      map.panTo(posLatLng);
      const z = map.getZoom();
      if (z < 15) {
        map.setZoom(16);
      }
    },
    (geoErr) => {
      ubicacionCargando.value = false;
      const code = geoErr?.code;
      if (code === 1) {
        ubicacionMensaje.value = 'Ubicación bloqueada: permití el acceso en el navegador.';
      } else if (code === 2) {
        ubicacionMensaje.value = 'No se pudo determinar la posición (GPS apagado o señal débil).';
      } else if (code === 3) {
        ubicacionMensaje.value = 'Tiempo de espera agotado. Probá de nuevo al aire libre.';
      } else {
        ubicacionMensaje.value = 'No se pudo obtener tu ubicación.';
      }
    },
    { enableHighAccuracy: true, timeout: 15000, maximumAge: 30000 }
  );
}

function clearMarkers() {
  markers.forEach((m) => m.setMap(null));
  markers = [];
  polylines.forEach((p) => p.setMap(null));
  polylines = [];
}

function escapeHtml(text) {
  if (text === null || text === undefined) return '';
  const div = document.createElement('div');
  div.textContent = String(text);
  return div.innerHTML;
}

/** HTML del popup de caja NAP: identificación clara + mini grilla FTTH (mismos colores que la ficha). */
function buildCajaNapInfoHtml(c) {
  const codigo = escapeHtml(c.codigo || '');
  const desc = c.descripcion ? `<div style="font-size:13px;color:#374151;margin-top:4px">${escapeHtml(c.descripcion)}</div>` : '';
  const dir = c.direccion
    ? `<div style="font-size:12px;color:#374151;margin-top:8px;line-height:1.4"><span style="font-weight:600;color:#6b7280;font-size:10px;text-transform:uppercase">Dirección</span><br>${escapeHtml(c.direccion)}</div>`
    : '';
  const coordsLine = c.coords_texto || (c.lat != null && c.lon != null ? `${Number(c.lat).toFixed(6)}, ${Number(c.lon).toFixed(6)}` : '');
  const mapsQ = c.lat != null && c.lon != null ? encodeURIComponent(`${c.lat},${c.lon}`) : '';
  const coords = coordsLine
    ? `<div style="font-size:11px;color:#6b7280;margin-top:6px;font-family:ui-monospace,Menlo,monospace"><span style="font-weight:600;font-family:system-ui,sans-serif;font-size:10px;color:#6b7280;text-transform:uppercase">Coordenadas</span><br>${escapeHtml(coordsLine)}${
        mapsQ
          ? ` <a href="https://www.google.com/maps?q=${mapsQ}" target="_blank" rel="noopener noreferrer" style="font-size:11px;color:#7c3aed;font-family:system-ui,sans-serif">→ Maps</a>`
          : ''
      }</div>`
    : '';
  const nodo = c.nodo ? `<div style="font-size:11px;color:#6b7280;margin-top:6px">Nodo: ${escapeHtml(c.nodo)}</div>` : '';
  const tipoCaja = c.tipo_caja ? `<div style="font-size:11px;color:#6b7280">${escapeHtml(c.tipo_caja)}</div>` : '';

  let puertosBlock = '';
  if (c.splitter_ftth && Array.isArray(c.puertos_ftth) && c.puertos_ftth.length > 0) {
    const libres = c.puertos_ftth.filter((p) => !p.ocupado).length;
    const total = c.puertos_ftth.length;
    puertosBlock = `<div style="margin-top:10px;padding-top:8px;border-top:1px solid #e5e7eb">
      <div style="font-size:11px;color:#4b5563;margin-bottom:6px">Puertos FTTH 1×${c.splitter_ftth} · <strong>${libres}</strong> libres / ${total}</div>
      <div style="display:grid;grid-template-columns:repeat(8,22px);gap:3px;max-width:200px;">
        ${c.puertos_ftth
          .map(
            (p) =>
              `<div title="Puerto ${p.n}${p.ocupado ? ' (ocupado)' : ' (libre)'}" style="width:22px;height:22px;border-radius:4px;background:${p.ocupado ? '#DC2626' : '#166534'};border:1px solid #111827;color:#fff;font-size:10px;font-weight:700;display:flex;align-items:center;justify-content:center;">${p.n}</div>`
          )
          .join('')}
      </div>
      <div style="font-size:10px;color:#6b7280;margin-top:6px">Rojo: ocupado · Verde: libre</div>
    </div>`;
  } else if (c.splitter_ftth) {
    puertosBlock = `<div style="margin-top:8px;font-size:11px;color:#6b7280">Splitter 1×${c.splitter_ftth} definido; los puertos aún no figuran en el mapa. <a href="${escapeHtml(c.url_show || '#')}" target="_blank" rel="noopener noreferrer" style="color:#7c3aed">Abrir ficha</a>.</div>`;
  } else {
    puertosBlock = `<div style="margin-top:8px;font-size:11px;color:#92400e">Sin splitter FTTH (1×8/1×16) en ficha. <a href="${escapeHtml(c.url_show || '#')}" target="_blank" rel="noopener noreferrer" style="color:#7c3aed">Editar caja</a>.</div>`;
  }

  const verFicha = c.url_show
    ? `<div style="margin-top:10px"><a href="${escapeHtml(c.url_show)}" target="_blank" rel="noopener noreferrer" style="display:inline-block;font-size:12px;font-weight:600;color:#7c3aed;text-decoration:underline">Abrir caja NAP (gestionar puertos)</a></div>`
    : '';

  return `
    <div style="padding:10px;min-width:200px;max-width:min(92vw,320px);font-family:system-ui,sans-serif">
      <div style="font-size:10px;font-weight:600;color:#1d4ed8;text-transform:uppercase;letter-spacing:0.04em">Caja NAP</div>
      <div style="font-size:15px;font-weight:700;color:#111827;margin-top:2px">${codigo}</div>
      ${desc}
      ${dir}
      ${coords}
      ${nodo}
      ${tipoCaja}
      ${puertosBlock}
      ${verFicha}
    </div>
  `;
}

async function loadData() {
  if (!props.mapaDataUrl) return;
  loading.value = true;
  error.value = '';

  try {
    const url = new URL(props.mapaDataUrl);
    if (props.nodoId) url.searchParams.set('nodo_id', props.nodoId);

    const res = await fetch(url.toString(), {
      credentials: 'same-origin',
      headers: {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
      },
    });
    const raw = await res.text();
    const trimmed = raw.trim();
    if (trimmed.startsWith('<')) {
      throw new Error(
        'El servidor devolvió HTML en lugar de JSON (sesión vencida, sin permiso o URL incorrecta). Recargá la página o verificá que accedas con el mismo dominio que APP_URL.'
      );
    }
    let data;
    try {
      data = JSON.parse(raw);
    } catch (parseErr) {
      throw new Error('La respuesta del mapa no es JSON válido.');
    }
    if (!res.ok) {
      const msg = data?.message || `Error HTTP ${res.status}`;
      throw new Error(msg);
    }

    clearMarkers();

    const bounds = new google.maps.LatLngBounds();

    // Salidas PON primero (quedan debajo) para no tapar cajas NAP en la misma coordenada
    (data.salida_pons || []).forEach((p) => {
      const pos = { lat: p.lat, lng: p.lon };
      const marker = new google.maps.Marker({
        position: pos,
        map,
        title: p.codigo,
        zIndex: 100,
        icon: {
          path: google.maps.SymbolPath.CIRCLE,
          scale: 8,
          fillColor: '#EAB308',
          fillOpacity: 1,
          strokeColor: '#A16207',
          strokeWeight: 2,
        },
      });

      const mapsQ = p.lat != null && p.lon != null ? encodeURIComponent(`${p.lat},${p.lon}`) : '';
      const coordsPon = p.coords_texto
        ? `<div style="font-size:11px;color:#6b7280;margin-top:8px;font-family:ui-monospace,Menlo,monospace"><span style="font-family:system-ui,sans-serif;font-size:10px;font-weight:600;text-transform:uppercase">Coordenadas</span><br>${escapeHtml(p.coords_texto)}${
            mapsQ
              ? ` <a href="https://www.google.com/maps?q=${mapsQ}" target="_blank" rel="noopener noreferrer" style="font-size:11px;color:#7c3aed;font-family:system-ui,sans-serif">→ Maps</a>`
              : ''
          }</div>`
        : '';
      const content = `
        <div style="padding:10px;min-width:160px;max-width:min(92vw,280px);font-family:system-ui,sans-serif">
          <div style="font-size:10px;font-weight:600;color:#b45309;text-transform:uppercase">Salida PON</div>
          <div style="font-size:15px;font-weight:700;color:#111827;margin-top:2px">${escapeHtml(p.codigo || '')}</div>
          ${coordsPon}
        </div>
      `;
      const infoWindow = new google.maps.InfoWindow({ content });
      marker.addListener('click', () => infoWindow.open(map, marker));

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
        zIndex: 200,
        icon: {
          path: google.maps.SymbolPath.CIRCLE,
          scale: 12,
          fillColor: '#22C55E',
          fillOpacity: 1,
          strokeColor: '#15803D',
          strokeWeight: 2,
        },
      });

      const mapsQ = n.lat != null && n.lon != null ? encodeURIComponent(`${n.lat},${n.lon}`) : '';
      const coordsNodo = n.coords_texto
        ? `<div style="font-size:11px;color:#6b7280;margin-top:8px;font-family:ui-monospace,Menlo,monospace"><span style="font-family:system-ui,sans-serif;font-size:10px;font-weight:600;text-transform:uppercase">Coordenadas</span><br>${escapeHtml(n.coords_texto)}${
            mapsQ
              ? ` <a href="https://www.google.com/maps?q=${mapsQ}" target="_blank" rel="noopener noreferrer" style="font-size:11px;color:#7c3aed;font-family:system-ui,sans-serif">→ Maps</a>`
              : ''
          }</div>`
        : '';
      const content = `
        <div style="padding:10px;min-width:160px;max-width:min(92vw,280px);font-family:system-ui,sans-serif">
          <div style="font-size:10px;font-weight:600;color:#15803d;text-transform:uppercase">Nodo</div>
          <div style="font-size:14px;font-weight:600;color:#111827;margin-top:2px">${escapeHtml(n.descripcion || '')}</div>
          ${coordsNodo}
        </div>
      `;
      const infoWindow = new google.maps.InfoWindow({ content });
      marker.addListener('click', () => infoWindow.open(map, marker));

      markers.push(marker);
      bounds.extend(pos);
    });

    // Cajas NAP al final (encima de PON en misma ubicación) + popup con puertos
    (data.cajas || []).forEach((c) => {
      const pos = { lat: c.lat, lng: c.lon };
      const marker = new google.maps.Marker({
        position: pos,
        map,
        title: `Caja NAP: ${c.codigo || ''}`,
        zIndex: 500,
        icon: {
          path: google.maps.SymbolPath.CIRCLE,
          scale: 10,
          fillColor: '#3B82F6',
          fillOpacity: 1,
          strokeColor: '#1E40AF',
          strokeWeight: 2,
        },
      });

      const content = buildCajaNapInfoHtml(c);
      const infoWindow = new google.maps.InfoWindow({ content });
      marker.addListener('click', () => {
        infoWindow.open(map, marker);
      });

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
  if (miUbicacionMarker) {
    miUbicacionMarker.setMap(null);
    miUbicacionMarker = null;
  }
  if (miUbicacionInfoWindow) {
    miUbicacionInfoWindow.close();
    miUbicacionInfoWindow = null;
  }
});
</script>
