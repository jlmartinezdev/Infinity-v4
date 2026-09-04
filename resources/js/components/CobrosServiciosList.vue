<template>
  <div class="max-w-7xl mx-auto pb-16">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
      <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">Cobros</h1>
      <a :href="urlCobrosIndex"
        class="inline-flex items-center px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-gray-200 rounded-lg font-medium hover:bg-gray-300 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-gray-400 focus:ring-offset-2 dark:focus:ring-offset-gray-800">
        Cobros y recibos
      </a>
    </div>

    <!-- Overlay de búsqueda estilo Algolia -->
    <div class="relative">
      <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg border border-gray-200 dark:border-gray-700 overflow-hidden">
        <!-- Barra de búsqueda -->
        <div class="relative">
          <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
            <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
            </svg>
          </div>
          <input
            ref="searchInputRef"
            v-model="buscar"
            type="text"
            placeholder="CI o nombre"
            class="w-full pl-12 pr-12 py-4 text-base rounded-t-xl border-0 border-b border-gray-200 dark:border-gray-700 bg-transparent text-gray-900 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-500 focus:ring-0 focus:border-purple-500 dark:focus:border-purple-400 focus:outline-none transition-colors"
            autofocus
            @keydown.esc="onEscape"
          />
          <!--span v-if="buscar" class="absolute right-4 top-1/2 -translate-y-1/2 text-xs text-gray-400 dark:text-gray-500">esc</span-->
          <button
            v-if="buscar"
            type="button"
            @click="buscar = ''"
            class="absolute right-4 top-1/2 -translate-y-1/2 p-1 rounded text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors"
            title="Limpiar (Esc)"
          >
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
            </svg>
          </button>
        </div>

        <!-- Panel de resultados: solo visible cuando hay búsqueda -->
        <div
          v-show="buscar.trim()"
          class="border-t border-gray-200 dark:border-gray-700"
        >
          <!-- Estado vacío -->
          <div
            v-if="cargando"
            class="px-4 py-12 text-center text-gray-500 dark:text-gray-400"
          >
            <p class="text-sm">Consultando cuentas pendientes…</p>
            <p class="text-xs mt-1">Ya podés escribir; los resultados aparecen al terminar la consulta</p>
          </div>
          <div
            v-else-if="errorCarga"
            class="px-4 py-12 text-center text-red-600 dark:text-red-400"
          >
            <p class="text-sm">{{ errorCarga }}</p>
          </div>
          <div
            v-else-if="clientesFiltrados.length === 0"
            class="px-4 py-12 text-center text-gray-500 dark:text-gray-400"
          >
            <p class="text-sm">No hay resultados para "{{ buscar.trim() }}"</p>
            <p class="text-xs mt-1">Prueba con otro término</p>
          </div>

          <!-- Lista de resultados -->
          <div v-else class="max-h-[70vh] overflow-y-auto">
            <div class="py-2">
              <p class="px-4 py-2 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                Resultados ({{ clientesFiltrados.length }})
              </p>
              <div class="divide-y divide-gray-100 dark:divide-gray-700/50">
                <div
                  v-for="g in clientesFiltrados"
                  :key="g.cliente_id"
                  class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 sm:gap-4 px-4 py-3 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors group"
                >
                  <div class="w-full sm:flex-1 min-w-0">
                    <p class="font-medium text-gray-900 dark:text-gray-100 truncate">
                      {{ nombresCompletos(g) || '—' }}
                    </p>
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                      {{ g.cliente?.cedula ?? '—' }}
                      <span v-if="g.servicios.length === 1 && etiquetaServicio(g.servicios[0])" class="text-gray-400 dark:text-gray-500">
                        · {{ etiquetaServicio(g.servicios[0]) }}- {{ formatMonto(g.servicios[0].plan?.precio ?? 0) }} PYG
                      </span>
                      <span v-else-if="g.servicios.length > 1" class="text-gray-400 dark:text-gray-500">
                        · {{ g.servicios.length }} servicios
                      </span>
                    </p>
                    <ul v-if="g.servicios.length > 1" class="mt-0.5 space-y-0.5">
                      <li
                        v-for="sv in g.servicios"
                        :key="sv.servicio_id"
                        class="text-sm text-gray-500 dark:text-gray-400"
                      >
                        <span v-if="etiquetaServicio(sv)">{{ etiquetaServicio(sv) }}- {{ formatMonto(sv.plan?.precio ?? 0) }} PYG</span>
                        <span v-else>Servicio #{{ sv.servicio_id }}</span>
                      </li>
                    </ul>
                    <p v-if="g.cliente?.direccion" class="text-xs text-gray-400 dark:text-gray-500 truncate mt-0.5">
                      {{ g.cliente.direccion }}
                    </p>
                  </div>
                  <div class="w-full sm:w-auto flex flex-col sm:flex-row items-start sm:items-center gap-2 sm:gap-3 shrink-0">
                    <div class="flex items-center gap-3">
                      <span
                        v-if="(g.facturas_pendientes?.cantidad ?? 0) > 0"
                        class="text-xs px-2 py-1 rounded-md bg-amber-100 dark:bg-amber-900/40 text-amber-800 dark:text-amber-200"
                      >
                        {{ g.facturas_pendientes.cantidad }} factura(s) pendiente(s): {{ formatMonto(g.facturas_pendientes.monto ?? 0) }}
                      </span>
                    </div>
                    <div class="flex items-center gap-2">
                      <a
                        v-if="canCrearCobro"
                        :href="urlCrearCobro(g.cliente_id)"
                        class="inline-flex items-center gap-2 p-2 rounded-lg text-green-600 dark:text-green-400 hover:bg-green-100 dark:hover:bg-green-900/30"
                        title="Registrar cobro"
                      >
                        <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <span>Registrar Cobro</span>
                      </a>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Estado inicial: sin búsqueda -->
        <div
          v-if="!buscar.trim()"
          class="px-4 py-16 text-center text-gray-400 dark:text-gray-500 border-t border-gray-200 dark:border-gray-700"
        >
          <svg class="w-12 h-12 mx-auto mb-3 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
          </svg>
          <p class="text-sm font-medium">Escribe para buscar</p>
          <p class="text-xs mt-1">Puedes escribir varias palabras; deben aparecer todas en el nombre o cédula</p>
        </div>
      </div>
    </div>

    <div
      v-if="cargando"
      class="fixed bottom-0 inset-x-0 z-[60] print:hidden"
      aria-live="polite"
    >
      <div class="h-1.5 bg-gray-200 dark:bg-gray-700 overflow-hidden">
        <div class="cobro-bar-indeterminate h-full bg-green-500"></div>
      </div>
      <div class="bg-white/95 dark:bg-gray-800/95 border-t border-gray-200 dark:border-gray-700 px-4 py-2 text-sm text-gray-700 dark:text-gray-200">
        Consultando cuentas pendientes…
      </div>
    </div>
  </div>
</template>

<style scoped>
@keyframes cobro-bar-slide {
  0% { transform: translateX(-100%); }
  100% { transform: translateX(350%); }
}
.cobro-bar-indeterminate {
  width: 32%;
  animation: cobro-bar-slide 1.15s ease-in-out infinite;
}
</style>

<script setup>
import { ref, computed, onMounted } from 'vue';

const props = defineProps({
  urlDatos: { type: String, default: '' },
  urlCobrosIndex: { type: String, default: '' },
  urlEditServicioBase: { type: String, default: '' },
  urlCrearCobroBase: { type: String, default: '' },
  canCrearCobro: { type: Boolean, default: false },
});

const buscar = ref('');
const searchInputRef = ref(null);
const servicios = ref([]);
const cargando = ref(true);
const errorCarga = ref('');

/**
 * Texto donde buscar: cédula + nombre + apellido + nombre completo (por si el nombre largo está en un solo campo).
 */
function textoBusquedaCliente(s) {
  const c = s.cliente;
  if (!c) return '';
  const nombre = String(c.nombre ?? '').trim();
  const apellido = String(c.apellido ?? '').trim();
  const full = `${nombre} ${apellido}`.trim();
  return [
    String(c.cedula ?? ''),
    nombre,
    apellido,
    full,
    ...(Array.isArray(s.servicios) ? s.servicios.flatMap((sv) => [sv.alias, sv.plan?.nombre]) : []),
  ]
    .filter(Boolean)
    .join(' ');
}

/**
 * Coincide si cada palabra del término aparece en el texto (orden libre).
 * Ej.: "JOSE VILLALBA" encuentra "JOSE ALFREDO VILLALBA BENITEZ".
 */
function coincideBusquedaPorPalabras(texto, consulta) {
  const q = consulta.trim().toLowerCase();
  if (!q) return false;
  const tokens = q.split(/\s+/).filter(t => t.length > 0);
  if (tokens.length === 0) return false;
  const h = texto.toLowerCase();
  return tokens.every(t => h.includes(t));
}

const clientesAgrupados = computed(() => {
  const map = new Map();
  for (const s of servicios.value) {
    const clienteId = s.cliente?.cliente_id;
    if (!clienteId) continue;
    if (!map.has(clienteId)) {
      map.set(clienteId, {
        cliente_id: clienteId,
        cliente: s.cliente,
        servicios: [],
        facturas_pendientes: s.facturas_pendientes ?? { cantidad: 0, monto: 0 },
      });
    }
    const grupo = map.get(clienteId);
    grupo.servicios.push({
      servicio_id: s.servicio_id,
      plan: s.plan,
      alias: s.alias || null,
    });
  }
  return Array.from(map.values());
});

const clientesFiltrados = computed(() => {
  const q = buscar.value?.trim() ?? '';
  if (!q) return [];
  return clientesAgrupados.value.filter(g => {
    const texto = textoBusquedaCliente(g);
    return coincideBusquedaPorPalabras(texto, q);
  });
});

function nombresCompletos(s) {
  const n = (s.cliente?.nombre ?? '').trim();
  const a = (s.cliente?.apellido ?? '').trim();
  return `${n} ${a}`.trim() || null;
}

function etiquetaServicio(sv) {
  const alias = String(sv?.alias ?? '').trim();
  const plan = String(sv?.plan?.nombre ?? '').trim();
  if (alias && plan) return `${alias} · ${plan}`;
  return alias || plan || '';
}

function formatMonto(val) {
  const n = Number(val);
  return isNaN(n) ? '0' : n.toLocaleString('es-PY', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
}

function urlEditServicio(servicioId) {
  return (props.urlEditServicioBase || '').replace('__servicio_id__', String(servicioId));
}

function urlCrearCobro(clienteId) {
  return (props.urlCrearCobroBase || '').replace('__cliente_id__', String(clienteId ?? ''));
}

function onEscape() {
  buscar.value = '';
  searchInputRef.value?.blur();
}

async function cargarDatos() {
  if (!props.urlDatos) {
    cargando.value = false;
    errorCarga.value = 'No se configuró la consulta de cuentas.';
    return;
  }
  cargando.value = true;
  errorCarga.value = '';
  try {
    const res = await fetch(props.urlDatos, {
      headers: {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
      },
      credentials: 'same-origin',
    });
    const data = await res.json().catch(() => ({}));
    if (!res.ok || data.success === false) {
      throw new Error(data.message || 'No se pudieron cargar las cuentas pendientes.');
    }
    servicios.value = Array.isArray(data.servicios) ? data.servicios : [];
  } catch (e) {
    errorCarga.value = e?.message || 'No se pudieron cargar las cuentas pendientes. Reintentá.';
    servicios.value = [];
  } finally {
    cargando.value = false;
  }
}

onMounted(cargarDatos);
</script>
