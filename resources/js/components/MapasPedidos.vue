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
import { aprobarEstadoPedido } from '@/pedidos/aprobarEstadoPedido';

const props = defineProps({
  apiKey: { type: String, default: '' },
  pedidos: { type: Array, default: () => [] },
  nodos: { type: Array, default: () => [] },
  planes: { type: Array, default: () => [] },
  tiposTecnologia: { type: Array, default: () => [] },
  aprobarEstadoUrl: { type: String, default: '' },
  urlOpcionesNodoAprobacion: { type: String, default: '' },
});

const mapContainer = ref(null);
const loading = ref(true);
const error = ref('');
let map = null;
let markers = [];
let infoWindows = [];
const pedidosById = new Map();
let aprobandoKey = null;

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
  const confirmacion = buildAnalisisBlock('Confirmación de plan', pedido.confirmacion_plan, [
    { label: 'Plan', value: pedido.confirmacion_plan?.plan || pedido.plan },
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
    pedidosById.set(pedido.pedido_id, pedido);
    const position = { lat: pedido.lat, lng: pedido.lon };
    const iconConfig = getMarkerIcon(google, pedido.tecnologia_descripcion);
    const marker = new google.maps.Marker({
      position,
      map,
      title: pedido.cliente || `Pedido #${pedido.pedido_id}`,
      ...(iconConfig && { icon: iconConfig }),
    });

    const content = buildInfoWindowContent(pedido);

    const infoWindow = new google.maps.InfoWindow({ content });
    attachInfoWindowHandlers(infoWindow);

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

<style>
/* Un solo botón cerrar: ocultamos el de Google Maps y usamos el del contenido */
.gm-style .gm-style-iw-chr {
  display: none !important;
}
.gm-style .gm-style-iw-c {
  padding: 0 !important;
}
.gm-style .gm-style-iw-d {
  overflow: auto !important;
  max-height: 360px !important;
}
</style>
