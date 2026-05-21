<template>
  <div class="max-w-7xl mx-auto">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
      <div>
        <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">Notas de crédito</h1>
        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
          Notas de crédito emitidas sobre facturas internas (reducen el saldo pendiente de la factura).
        </p>
      </div>
      <a
        :href="urlFacturasInternas"
        class="inline-flex items-center gap-2 px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-sm font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors shrink-0"
      >
        Facturas internas
      </a>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-6">
      <div class="p-4 bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700">
        <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Cantidad (filtro actual)</p>
        <p class="mt-1 text-2xl font-bold text-gray-900 dark:text-gray-100">{{ stats.cantidad }}</p>
      </div>
      <div class="p-4 bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700">
        <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Total acreditado</p>
        <p class="mt-1 text-2xl font-bold text-sky-700 dark:text-sky-400">{{ formatMonto(stats.total_monto) }} PYG</p>
      </div>
    </div>

    <div
      v-if="inlineError"
      class="mb-4 p-4 rounded-lg bg-red-100 dark:bg-red-900/20 text-red-800 dark:text-red-200 border border-red-200 dark:border-red-800 text-sm"
    >
      {{ inlineError }}
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 overflow-hidden">
      <div class="p-4 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/50 space-y-4">
        <div class="relative">
          <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
            <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
            </svg>
          </div>
          <input
            v-model="buscar"
            type="search"
            autocomplete="off"
            placeholder="Buscar por # nota, # factura, nombre o cédula del cliente…"
            class="w-full pl-10 pr-10 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20 text-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100"
          />
        </div>
        <div class="flex flex-col sm:flex-row gap-3 flex-wrap">
          <div class="sm:w-56">
            <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-0.5">Cliente</label>
            <select
              v-model="filtroClienteId"
              class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 text-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100"
            >
              <option value="">Todos</option>
              <option v-for="c in clientes" :key="c.cliente_id" :value="String(c.cliente_id)">
                {{ c.nombre }} {{ c.apellido }}
              </option>
            </select>
          </div>
          <div class="sm:w-40">
            <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-0.5">Desde</label>
            <input v-model="filtroDesde" type="date" class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 text-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100" />
          </div>
          <div class="sm:w-40">
            <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-0.5">Hasta</label>
            <input v-model="filtroHasta" type="date" class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 text-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100" />
          </div>
        </div>
      </div>

      <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
          <thead class="bg-gray-50 dark:bg-gray-700/50">
            <tr>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase"># NC</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Fecha</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Factura</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Cliente</th>
              <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Monto</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Motivo</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Usuario</th>
              <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Acción</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-200 dark:divide-gray-700 bg-white dark:bg-gray-800">
            <tr v-if="loading">
              <td colspan="8" class="px-4 py-10 text-center text-gray-500 dark:text-gray-400 text-sm">Cargando…</td>
            </tr>
            <tr v-else-if="!rows.length">
              <td colspan="8" class="px-4 py-10 text-center text-gray-500 dark:text-gray-400 text-sm">No hay notas de crédito registradas.</td>
            </tr>
            <tr v-for="row in rows" v-else :key="row.id" class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
              <td class="px-4 py-3 text-sm font-medium text-gray-900 dark:text-gray-100">#{{ row.id }}</td>
              <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-300 whitespace-nowrap">{{ formatFecha(row.created_at) }}</td>
              <td class="px-4 py-3 text-sm">
                <a :href="urlFactura(row.factura_interna_id)" class="text-purple-600 dark:text-purple-400 hover:underline font-medium">
                  #{{ row.factura_interna_id }}
                </a>
                <span v-if="row.factura_estado" class="block text-xs text-gray-500 dark:text-gray-400 capitalize">{{ row.factura_estado }}</span>
              </td>
              <td class="px-4 py-3 text-sm text-gray-900 dark:text-gray-100">
                <span class="block">{{ row.cliente_nombre || '—' }}</span>
                <span v-if="row.cliente_cedula" class="text-xs text-gray-500 dark:text-gray-400">{{ row.cliente_cedula }}</span>
              </td>
              <td class="px-4 py-3 text-sm text-right font-semibold text-sky-700 dark:text-sky-400 whitespace-nowrap">
                −{{ formatMonto(row.monto) }} {{ row.moneda }}
              </td>
              <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-300 max-w-[200px] truncate" :title="row.motivo || ''">
                {{ row.motivo || '—' }}
              </td>
              <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-300">{{ row.usuario_nombre || '—' }}</td>
              <td class="px-4 py-3 text-right">
                <a
                  :href="urlFactura(row.factura_interna_id)"
                  class="inline-flex items-center justify-center p-2 rounded-lg text-purple-600 hover:bg-purple-50 dark:text-purple-400 dark:hover:bg-purple-900/30 transition-colors"
                  title="Ver factura"
                >
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                  </svg>
                </a>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div
        v-if="meta.last_page > 1"
        class="px-4 py-3 border-t border-gray-200 dark:border-gray-700 flex flex-wrap items-center justify-between gap-3 bg-gray-50 dark:bg-gray-900/30"
      >
        <p class="text-sm text-gray-600 dark:text-gray-400">
          <span v-if="meta.total">{{ meta.from }}–{{ meta.to }} de {{ meta.total }}</span>
        </p>
        <div class="flex items-center gap-2">
          <button
            type="button"
            class="px-3 py-1.5 text-sm rounded-lg border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700 disabled:opacity-40"
            :disabled="meta.current_page <= 1 || loading"
            @click="cargar(meta.current_page - 1)"
          >
            Anterior
          </button>
          <span class="text-sm text-gray-600 dark:text-gray-400 tabular-nums">Página {{ meta.current_page }} / {{ meta.last_page }}</span>
          <button
            type="button"
            class="px-3 py-1.5 text-sm rounded-lg border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700 disabled:opacity-40"
            :disabled="meta.current_page >= meta.last_page || loading"
            @click="cargar(meta.current_page + 1)"
          >
            Siguiente
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, watch, onMounted, onUnmounted, computed } from 'vue';

const props = defineProps({
  listUrl: { type: String, required: true },
  facturaBaseUrl: { type: String, required: true },
  clientes: { type: Array, default: () => [] },
});

const buscar = ref('');
const buscarDebounced = ref('');
const filtroClienteId = ref('');
const filtroDesde = ref('');
const filtroHasta = ref('');
const rows = ref([]);
const stats = ref({ cantidad: 0, total_monto: 0 });
const meta = ref({
  current_page: 1,
  last_page: 1,
  per_page: 20,
  total: 0,
  from: null,
  to: null,
});
const loading = ref(false);
const inlineError = ref('');

let debounceTimer = null;

const urlFacturasInternas = computed(() => {
  const base = (props.facturaBaseUrl || '').replace(/\/$/, '');
  return base || '/factura-internas';
});

function formatMonto(n) {
  return new Intl.NumberFormat('es-PY', { maximumFractionDigits: 0 }).format(n);
}

function formatFecha(iso) {
  if (!iso) return '—';
  const d = new Date(iso.replace(' ', 'T'));
  if (Number.isNaN(d.getTime())) return iso;
  return d.toLocaleString('es-PY', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
}

function urlFactura(id) {
  return `${props.facturaBaseUrl}/${id}`;
}

async function cargar(page = 1) {
  loading.value = true;
  inlineError.value = '';
  try {
    const params = new URLSearchParams();
    params.set('page', String(page));
    if (filtroClienteId.value) params.set('cliente_id', filtroClienteId.value);
    if (filtroDesde.value) params.set('desde', filtroDesde.value);
    if (filtroHasta.value) params.set('hasta', filtroHasta.value);
    if (buscarDebounced.value.trim()) params.set('q', buscarDebounced.value.trim());
    const { data } = await window.axios.get(`${props.listUrl}?${params.toString()}`);
    rows.value = data.data || [];
    meta.value = { ...meta.value, ...(data.meta || {}) };
    stats.value = {
      cantidad: Number(data.stats?.cantidad ?? 0),
      total_monto: Number(data.stats?.total_monto ?? 0),
    };
  } catch (e) {
    inlineError.value = e.response?.data?.message || 'No se pudo cargar el listado.';
    rows.value = [];
    stats.value = { cantidad: 0, total_monto: 0 };
  } finally {
    loading.value = false;
  }
}

watch(buscar, () => {
  clearTimeout(debounceTimer);
  debounceTimer = setTimeout(() => {
    buscarDebounced.value = buscar.value;
    cargar(1);
  }, 350);
});

watch([filtroClienteId, filtroDesde, filtroHasta], () => cargar(1));

onMounted(() => cargar(1));
onUnmounted(() => clearTimeout(debounceTimer));
</script>
