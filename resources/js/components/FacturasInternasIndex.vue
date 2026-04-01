<template>
  <div class="max-w-7xl mx-auto">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
      <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">Facturas internas</h1>
      <div class="flex flex-wrap items-center gap-2">
        <form
          v-if="canEjecutarCrear"
          ref="ejecutarFormRef"
          method="POST"
          :action="urlEjecutarCrear"
          class="hidden"
          aria-hidden="true"
        >
          <input type="hidden" name="_token" :value="csrfToken" />
        </form>
        <button
          v-if="canEjecutarCrear"
          type="button"
          class="inline-flex items-center px-4 py-2 bg-emerald-600 text-white rounded-lg font-medium hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800"
          @click="onEjecutarCrear"
        >
          <svg class="w-5 h-5 mr-2 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
          </svg>
          Ejecutar crear-factura-internas
        </button>
        <a
          :href="urlGenerarInterna"
          class="inline-flex items-center px-4 py-2 bg-purple-600 text-white rounded-lg font-medium hover:bg-purple-700 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800"
        >
          Generar factura interna
        </a>
      </div>
    </div>

    <div
      v-if="flashSuccess"
      class="mb-4 p-4 rounded-lg bg-green-100 dark:bg-green-900/20 text-green-800 dark:text-green-200 border border-green-200 dark:border-green-800 text-sm"
    >
      {{ flashSuccess }}
    </div>
    <div
      v-if="flashError"
      class="mb-4 p-4 rounded-lg bg-red-100 dark:bg-red-900/20 text-red-800 dark:text-red-200 border border-red-200 dark:border-red-800 text-sm"
    >
      {{ flashError }}
    </div>
    <div
      v-if="inlineMessage"
      class="mb-4 p-4 rounded-lg bg-green-100 dark:bg-green-900/20 text-green-800 dark:text-green-200 border border-green-200 dark:border-green-800 text-sm"
    >
      {{ inlineMessage }}
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
            <svg class="h-5 w-5 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
            </svg>
          </div>
          <input
            v-model="buscar"
            type="search"
            autocomplete="off"
            placeholder="Buscar por # factura, nombre, apellido o cédula del cliente…"
            class="w-full pl-10 pr-10 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 text-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 placeholder-gray-500 dark:placeholder-gray-400"
          />
          <button
            v-if="buscar.trim()"
            type="button"
            class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
            title="Limpiar búsqueda"
            @click="buscar = ''"
          >
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>

        <div class="flex flex-col sm:flex-row gap-3 flex-wrap">
          <div class="sm:w-56">
            <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-0.5">Cliente</label>
            <select
              v-model="filtroClienteId"
              class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 text-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100"
            >
              <option value="">Todos</option>
              <option v-for="c in clientes" :key="c.cliente_id" :value="String(c.cliente_id)">
                {{ c.nombre }} {{ c.apellido }}
              </option>
            </select>
          </div>
          <div class="sm:w-52">
            <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-0.5">Estado</label>
            <select
              v-model="filtroEstado"
              class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 text-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100"
            >
              <option value="">Todos</option>
              <option v-for="(label, key) in estados" :key="key" :value="key">{{ label }}</option>
            </select>
          </div>
          <div class="flex items-end gap-2">
            <button
              type="button"
              class="px-4 py-2 bg-purple-600 text-white rounded-lg font-medium hover:bg-purple-700 text-sm dark:focus:ring-2 dark:focus:ring-purple-500 dark:focus:ring-offset-2 dark:focus:ring-offset-gray-800"
              :disabled="loading"
              @click="cargar(1)"
            >
              Aplicar filtros
            </button>
          </div>
        </div>
      </div>

      <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
          <thead class="bg-gray-50 dark:bg-gray-700/50">
            <tr>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">#</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Cliente</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Período</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Fecha emisión</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Estado</th>
              <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Total</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Acciones</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-200 dark:divide-gray-700 bg-white dark:bg-gray-800">
            <tr v-if="loading">
              <td colspan="7" class="px-4 py-10 text-center text-gray-500 dark:text-gray-400 text-sm">Cargando…</td>
            </tr>
            <tr v-else-if="!facturas.length">
              <td colspan="7" class="px-4 py-10 text-center text-gray-500 dark:text-gray-400 text-sm">No hay facturas internas.</td>
            </tr>
            <tr
              v-for="f in facturas"
              v-else
              :key="f.id"
              class="hover:bg-gray-50 dark:hover:bg-gray-700/50"
            >
              <td class="px-4 py-3 text-sm font-medium text-gray-900 dark:text-gray-100">{{ f.id }}</td>
              <td class="px-4 py-3 text-sm text-gray-900 dark:text-gray-100">
                <span class="block">{{ f.cliente_nombre || '—' }}</span>
                <span v-if="f.cliente_cedula" class="text-xs text-gray-500 dark:text-gray-400">{{ f.cliente_cedula }}</span>
              </td>
              <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-300">
                {{ formatDMY(f.periodo_desde) }} – {{ formatDMY(f.periodo_hasta) }}
              </td>
              <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-300">{{ formatDMY(f.fecha_emision) }}</td>
              <td class="px-4 py-3">
                <span
                  class="inline-flex px-2.5 py-0.5 rounded-full text-xs font-medium"
                  :class="estadoBadgeClass(f.estado)"
                >
                  {{ estados[f.estado] ?? f.estado }}
                </span>
              </td>
              <td class="px-4 py-3 text-sm text-right font-medium text-gray-900 dark:text-gray-100">
                {{ formatMonto(f.total) }} {{ f.moneda }}
              </td>
              <td class="px-4 py-3">
                <div class="flex items-center gap-2">
                  <a
                    :href="urlShow(f.id)"
                    class="p-2 text-purple-600 dark:text-purple-400 hover:bg-purple-50 dark:hover:bg-purple-900/30 rounded-lg transition-colors"
                    title="Ver"
                  >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                    </svg>
                  </a>
                  <a
                    v-if="canEditar"
                    :href="urlEdit(f.id)"
                    class="p-2 text-amber-600 dark:text-amber-400 hover:bg-amber-50 dark:hover:bg-amber-900/30 rounded-lg transition-colors"
                    title="Editar factura interna"
                  >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                    </svg>
                  </a>
                  <button
                    v-if="canEliminar"
                    type="button"
                    class="p-2 text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/30 rounded-lg transition-colors disabled:opacity-50"
                    title="Eliminar"
                    :disabled="deletingId === f.id"
                    @click="confirmarEliminar(f)"
                  >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                    </svg>
                  </button>
                </div>
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
          <span class="text-sm text-gray-600 dark:text-gray-400 tabular-nums">
            Página {{ meta.current_page }} / {{ meta.last_page }}
          </span>
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
import { ref, watch, onMounted, onUnmounted } from 'vue';

const props = defineProps({
  listUrl: { type: String, required: true },
  facturaBaseUrl: { type: String, required: true },
  urlGenerarInterna: { type: String, required: true },
  urlEjecutarCrear: { type: String, default: '' },
  csrfToken: { type: String, required: true },
  clientes: { type: Array, default: () => [] },
  estados: { type: Object, required: true },
  canEjecutarCrear: { type: Boolean, default: false },
  canEditar: { type: Boolean, default: false },
  canEliminar: { type: Boolean, default: false },
  flashSuccess: { type: String, default: '' },
  flashError: { type: String, default: '' },
});

const buscar = ref('');
const buscarDebounced = ref('');
const filtroClienteId = ref('');
const filtroEstado = ref('');
const facturas = ref([]);
const meta = ref({
  current_page: 1,
  last_page: 1,
  per_page: 15,
  total: 0,
  from: null,
  to: null,
});
const loading = ref(false);
const deletingId = ref(null);
const inlineMessage = ref('');
const inlineError = ref('');
const ejecutarFormRef = ref(null);

let debounceTimer = null;

function formatDMY(iso) {
  if (!iso) return '—';
  const p = String(iso).slice(0, 10).split('-');
  if (p.length !== 3) return iso;
  return `${p[2]}/${p[1]}/${p[0]}`;
}

function formatMonto(n) {
  return new Intl.NumberFormat('es-PY', { maximumFractionDigits: 0 }).format(n);
}

function estadoBadgeClass(estado) {
  const map = {
    emitida: 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300',
    pendiente: 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300',
    pagada: 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300',
    cancelada: 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300',
    anulada: 'bg-gray-200 text-gray-800 dark:bg-gray-600 dark:text-gray-200',
  };
  return map[estado] || 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300';
}

function urlShow(id) {
  return `${props.facturaBaseUrl}/${id}`;
}

function urlEdit(id) {
  return `${props.facturaBaseUrl}/${id}/edit`;
}

async function cargar(page = 1) {
  loading.value = true;
  inlineError.value = '';
  try {
    const params = new URLSearchParams();
    params.set('page', String(page));
    if (filtroClienteId.value) params.set('cliente_id', filtroClienteId.value);
    if (filtroEstado.value) params.set('estado', filtroEstado.value);
    if (buscarDebounced.value.trim()) params.set('q', buscarDebounced.value.trim());
    const { data } = await window.axios.get(`${props.listUrl}?${params.toString()}`);
    facturas.value = data.data || [];
    meta.value = { ...meta.value, ...(data.meta || {}) };
  } catch (e) {
    inlineError.value = e.response?.data?.message || 'No se pudo cargar el listado.';
    facturas.value = [];
  } finally {
    loading.value = false;
  }
}

function onEjecutarCrear() {
  const Swal = typeof window !== 'undefined' ? window.Swal : null;
  const msg =
    '¿Ejecutar la tarea crear-factura-internas? Se crearán facturas internas automáticas del mes actual para clientes con servicios activos (omite si ya existe factura del período).';
  const run = () => ejecutarFormRef.value?.submit();
  if (Swal) {
    Swal.fire({
      title: '¿Confirmar?',
      text: msg,
      icon: 'question',
      showCancelButton: true,
      confirmButtonColor: '#059669',
      cancelButtonColor: '#6b7280',
      confirmButtonText: 'Sí, ejecutar',
      cancelButtonText: 'Cancelar',
    }).then((r) => {
      if (r.isConfirmed) run();
    });
  } else if (window.confirm(msg)) {
    run();
  }
}

async function confirmarEliminar(f) {
  const Swal = typeof window !== 'undefined' ? window.Swal : null;
  let ok = false;
  if (Swal) {
    const r = await Swal.fire({
      title: '¿Eliminar factura interna?',
      text: 'Esta acción no se puede deshacer. Los cobros asociados quedarán sin factura.',
      icon: 'warning',
      showCancelButton: true,
      confirmButtonColor: '#dc2626',
      cancelButtonColor: '#6b7280',
      confirmButtonText: 'Sí, eliminar',
      cancelButtonText: 'Cancelar',
    });
    ok = r.isConfirmed;
  } else {
    ok = window.confirm('¿Eliminar factura interna #' + f.id + '?');
  }
  if (!ok) return;

  deletingId.value = f.id;
  inlineError.value = '';
  try {
    await window.axios.delete(`${props.facturaBaseUrl}/${f.id}`);
    inlineMessage.value = 'Factura interna eliminada.';
    setTimeout(() => { inlineMessage.value = ''; }, 4000);
    await cargar(meta.value.current_page);
  } catch (e) {
    inlineError.value = e.response?.data?.message || 'No se pudo eliminar.';
  } finally {
    deletingId.value = null;
  }
}

watch(buscar, () => {
  clearTimeout(debounceTimer);
  debounceTimer = setTimeout(() => {
    buscarDebounced.value = buscar.value;
    cargar(1);
  }, 350);
});

watch([filtroClienteId, filtroEstado], () => {
  cargar(1);
});

onMounted(() => {
  cargar(1);
});

onUnmounted(() => {
  clearTimeout(debounceTimer);
});
</script>
