<template>
  <div class="grid grid-cols-1 xl:grid-cols-[minmax(0,1fr)_24rem] gap-6">
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 overflow-hidden min-w-0">
      <div class="p-4 border-b border-gray-200 dark:border-gray-700 space-y-3">
        <div class="flex items-center justify-between gap-3">
          <div class="flex items-center gap-3 min-w-0">
            <h2 class="text-base font-semibold text-gray-900 dark:text-gray-100">Catálogo</h2>
            <span class="text-xs px-2 py-1 rounded bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300">
              {{ filtrados.length }} productos
            </span>
          </div>
          <div class="inline-flex rounded-lg border border-gray-300 dark:border-gray-600 overflow-hidden shrink-0">
            <button
              type="button"
              class="px-3 py-1.5 text-xs font-medium"
              :class="vista === 'cuadricula' ? 'bg-purple-600 text-white' : 'bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700'"
              @click="vista = 'cuadricula'"
            >
              Cuadrícula
            </button>
            <button
              type="button"
              class="px-3 py-1.5 text-xs font-medium border-l border-gray-300 dark:border-gray-600"
              :class="vista === 'lista' ? 'bg-purple-600 text-white' : 'bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700'"
              @click="vista = 'lista'"
            >
              Lista
            </button>
          </div>
        </div>
        <input
          ref="buscarInput"
          v-model="buscar"
          type="search"
          placeholder="Buscar por código o nombre..."
          class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 text-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none"
          @keydown.enter.prevent="agregarPrimeroFiltrado"
        >
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
          <select
            v-model="proveedorId"
            class="w-full py-2 px-3 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 text-sm"
          >
            <option value="">Todos los proveedores</option>
            <option v-for="prov in proveedores" :key="prov.id" :value="String(prov.id)">{{ prov.nombre }}</option>
          </select>
          <select
            v-model="categoriaId"
            class="w-full py-2 px-3 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 text-sm"
          >
            <option value="">Todas las categorías</option>
            <option v-for="cat in categorias" :key="cat.id" :value="String(cat.id)">{{ cat.nombre }}</option>
          </select>
        </div>
        <label class="inline-flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
          <input v-model="soloStockBajo" type="checkbox" class="rounded border-gray-300 dark:border-gray-600 text-purple-600 focus:ring-purple-500">
          Solo stock bajo
        </label>
      </div>

      <div v-if="!filtrados.length" class="px-3 py-10 text-sm text-center text-gray-500 dark:text-gray-400">
        No hay productos para este filtro.
      </div>

      <div v-else-if="vista === 'cuadricula'" class="overflow-auto max-h-[70vh] p-3">
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3">
          <button
            v-for="p in filtrados"
            :key="p.id"
            type="button"
            class="group text-left rounded-xl border bg-white dark:bg-gray-800 overflow-hidden focus:outline-none focus:ring-2 focus:ring-purple-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 disabled:opacity-50 disabled:cursor-not-allowed"
            :class="cantidadDe(p.id)
              ? 'border-purple-500 ring-1 ring-purple-500'
              : 'border-gray-200 dark:border-gray-700 hover:border-purple-400 dark:hover:border-purple-500'"
            :disabled="!p.codigo"
            :title="p.codigo ? 'Agregar al pedido' : 'Este producto no tiene código'"
            @click="agregar(p.id)"
          >
            <div class="relative aspect-square bg-gray-50 dark:bg-gray-900">
              <img
                v-if="p.imagen_url"
                :src="p.imagen_url"
                :alt="p.nombre"
                class="h-full w-full object-contain p-2"
              >
              <span
                v-else
                class="flex h-full w-full items-center justify-center text-gray-400"
                aria-hidden="true"
              >
                <svg class="h-10 w-10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 7a2 2 0 012-2h3l2-2h4l2 2h3a2 2 0 012 2v10a2 2 0 01-2 2H5a2 2 0 01-2-2V7z"/><circle cx="12" cy="13" r="3"/></svg>
              </span>
              <span
                v-if="cantidadDe(p.id)"
                class="absolute top-2 right-2 min-w-[1.5rem] h-6 px-1.5 rounded-full bg-purple-600 text-white text-xs font-semibold flex items-center justify-center"
              >
                {{ cantidadDe(p.id) }}
              </span>
              <span
                v-if="p.stock_bajo"
                class="absolute top-2 left-2 px-1.5 py-0.5 rounded bg-amber-500 text-white text-[10px] font-semibold uppercase tracking-wide"
              >
                Stock bajo
              </span>
            </div>
            <div class="p-2.5 space-y-1">
              <div class="text-xs font-mono text-purple-700 dark:text-purple-300">{{ p.codigo || 'Sin código' }}</div>
              <div class="text-sm font-medium text-gray-900 dark:text-gray-100 line-clamp-2 min-h-[2.5rem]">{{ p.nombre }}</div>
              <div class="flex items-center justify-between gap-2 text-xs text-gray-500 dark:text-gray-400">
                <span :class="p.stock_bajo ? 'text-amber-700 dark:text-amber-300 font-medium' : ''">
                  Stock {{ formatNumero(p.stock_actual) }}
                </span>
                <span class="font-medium text-gray-900 dark:text-gray-100">{{ formatPrecio(p.precio_compra, p.moneda) }}</span>
              </div>
            </div>
          </button>
        </div>
      </div>

      <div v-else class="overflow-auto max-h-[70vh]">
        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
          <thead class="bg-gray-50 dark:bg-gray-700/50 sticky top-0 z-10">
            <tr>
              <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Producto</th>
              <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400">Stock</th>
              <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400">Compra</th>
              <th class="px-3 py-2 w-16"></th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
            <tr
              v-for="p in filtrados"
              :key="p.id"
              class="hover:bg-gray-50 dark:hover:bg-gray-700/30"
              :class="p.stock_bajo ? 'bg-amber-50 dark:bg-amber-900/10' : ''"
            >
              <td class="px-3 py-2">
                <div class="flex items-center gap-3 min-w-0">
                  <img
                    v-if="p.imagen_url"
                    :src="p.imagen_url"
                    alt=""
                    class="h-10 w-10 shrink-0 rounded-lg object-cover border border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-900"
                  >
                  <span
                    v-else
                    class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg border border-dashed border-gray-300 dark:border-gray-600 text-gray-400"
                    aria-hidden="true"
                  >
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 7a2 2 0 012-2h3l2-2h4l2 2h3a2 2 0 012 2v10a2 2 0 01-2 2H5a2 2 0 01-2-2V7z"/><circle cx="12" cy="13" r="3"/></svg>
                  </span>
                  <div class="min-w-0">
                    <div class="text-sm font-medium text-gray-900 dark:text-gray-100 truncate">{{ p.nombre }}</div>
                    <div class="text-xs text-gray-500 dark:text-gray-400">
                      <span class="font-mono">{{ p.codigo || 'Sin código' }}</span>
                      <span v-if="p.proveedor"> · {{ p.proveedor }}</span>
                    </div>
                  </div>
                </div>
              </td>
              <td class="px-3 py-2 text-sm text-right" :class="p.stock_bajo ? 'text-amber-700 dark:text-amber-300 font-medium' : 'text-gray-900 dark:text-gray-100'">
                {{ formatNumero(p.stock_actual) }}
              </td>
              <td class="px-3 py-2 text-sm text-right text-gray-900 dark:text-gray-100 whitespace-nowrap">
                {{ formatPrecio(p.precio_compra, p.moneda) }}
              </td>
              <td class="px-3 py-2 text-right">
                <button
                  type="button"
                  class="text-xs px-2 py-1 rounded bg-purple-600 text-white hover:bg-purple-700 disabled:opacity-50"
                  :disabled="!p.codigo"
                  :title="p.codigo ? 'Agregar al pedido' : 'Este producto no tiene código'"
                  @click="agregar(p.id)"
                >
                  {{ cantidadDe(p.id) ? `+ (${cantidadDe(p.id)})` : 'Agregar' }}
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 overflow-hidden min-w-0 self-start">
      <div class="p-4 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between gap-3">
        <div>
          <h2 class="text-base font-semibold text-gray-900 dark:text-gray-100">Lista del pedido</h2>
          <p class="text-xs text-gray-500 dark:text-gray-400">{{ items.length }} ítems · se guarda en este navegador</p>
        </div>
        <button
          type="button"
          class="text-sm px-3 py-1.5 rounded-lg border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700 disabled:opacity-50"
          :disabled="!items.length"
          @click="vaciar"
        >
          Vaciar
        </button>
      </div>

      <div class="overflow-auto max-h-[42vh]">
        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
          <thead class="bg-gray-50 dark:bg-gray-700/50 sticky top-0 z-10">
            <tr>
              <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Código</th>
              <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Producto</th>
              <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 w-32">Cant.</th>
              <th class="px-3 py-2 w-10"></th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
            <tr v-if="!items.length">
              <td colspan="4" class="px-3 py-8 text-sm text-center text-gray-500 dark:text-gray-400">
                Tocá un producto en el catálogo para armar la lista.
              </td>
            </tr>
            <tr v-for="item in itemsResueltos" :key="item.id">
              <td class="px-3 py-2 text-sm font-mono text-gray-900 dark:text-gray-100">{{ item.codigo }}</td>
              <td class="px-3 py-2 text-sm text-gray-900 dark:text-gray-100">
                <div class="truncate max-w-[14rem]" :title="item.nombre">{{ item.nombre }}</div>
                <div class="text-xs text-gray-500 dark:text-gray-400">{{ formatPrecio(item.subtotal, item.moneda) }}</div>
              </td>
              <td class="px-3 py-2">
                <div class="flex items-center justify-end gap-1">
                  <button type="button" class="h-8 w-8 rounded-md border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200" @click="cambiarCantidad(item.id, -1)">-</button>
                  <input
                    :value="item.cantidad"
                    type="number"
                    min="1"
                    step="1"
                    class="w-16 px-2 py-1.5 rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 text-sm text-center"
                    @change="setCantidad(item.id, $event.target.value)"
                  >
                  <button type="button" class="h-8 w-8 rounded-md border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200" @click="cambiarCantidad(item.id, 1)">+</button>
                </div>
              </td>
              <td class="px-3 py-2">
                <button type="button" class="text-red-600 dark:text-red-400 hover:text-red-800 text-sm" :aria-label="'Quitar ' + item.nombre" @click="quitar(item.id)">✕</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="p-4 border-t border-gray-200 dark:border-gray-700 space-y-4">
        <div v-if="totales.length" class="space-y-1 text-sm">
          <div v-for="t in totales" :key="t.moneda" class="flex items-center justify-between text-gray-700 dark:text-gray-300">
            <span>Estimado {{ etiquetaMoneda(t.moneda) }}</span>
            <span class="font-medium">{{ formatPrecio(t.total, t.moneda) }}</span>
          </div>
        </div>

        <div>
          <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Vista previa (código + cantidad)</label>
          <textarea
            readonly
            :value="textoExport"
            rows="6"
            class="w-full px-3 py-2 rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900 font-mono text-sm text-gray-900 dark:text-gray-100"
          ></textarea>
        </div>

        <p v-if="sinCodigo" class="text-xs text-amber-700 dark:text-amber-300">
          Hay productos en la lista sin código; no se incluyen en la exportación.
        </p>

        <div class="flex flex-col sm:flex-row gap-2">
          <button
            type="button"
            class="flex-1 inline-flex justify-center items-center px-4 py-2 bg-purple-600 text-white rounded-lg font-medium hover:bg-purple-700 disabled:opacity-50"
            :disabled="!lineasExport.length"
            @click="exportarExcel"
          >
            Exportar Excel
          </button>
          <button
            type="button"
            class="flex-1 inline-flex justify-center items-center px-4 py-2 bg-white dark:bg-gray-700 text-gray-800 dark:text-gray-100 rounded-lg font-medium border border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-600 disabled:opacity-50"
            :disabled="!lineasExport.length"
            @click="copiar"
          >
            {{ copiado ? 'Copiado' : 'Copiar lista' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue';

const STORAGE_KEY = 'infinity_pedido_productos_v1';
const VISTA_KEY = 'infinity_pedido_productos_vista';

const props = defineProps({
  productos: { type: Array, default: () => [] },
  proveedores: { type: Array, default: () => [] },
  categorias: { type: Array, default: () => [] },
});

const buscar = ref('');
const proveedorId = ref('');
const categoriaId = ref('');
const soloStockBajo = ref(false);
const vista = ref('cuadricula');
const items = ref([]);
const copiado = ref(false);
const buscarInput = ref(null);

const productoPorId = computed(() => {
  const map = new Map();
  props.productos.forEach((p) => map.set(String(p.id), p));
  return map;
});

const filtrados = computed(() => {
  const term = buscar.value.toLowerCase().trim();
  return props.productos.filter((p) => {
    if (proveedorId.value && String(p.proveedor_id) !== proveedorId.value) return false;
    if (categoriaId.value && String(p.categoria_id) !== categoriaId.value) return false;
    if (soloStockBajo.value && !p.stock_bajo) return false;
    if (!term) return true;
    return `${p.codigo ?? ''} ${p.nombre}`.toLowerCase().includes(term);
  });
});

const itemsResueltos = computed(() => items.value
  .map((item) => {
    const p = productoPorId.value.get(String(item.id));
    if (!p) return null;
    const cantidad = Math.max(1, Number(item.cantidad) || 1);
    return {
      id: p.id,
      codigo: p.codigo || '',
      nombre: p.nombre,
      moneda: p.moneda,
      precio_compra: Number(p.precio_compra) || 0,
      cantidad,
      subtotal: (Number(p.precio_compra) || 0) * cantidad,
    };
  })
  .filter(Boolean));

const lineasExport = computed(() => itemsResueltos.value.filter((item) => item.codigo));

const sinCodigo = computed(() => itemsResueltos.value.some((item) => !item.codigo));

const textoExport = computed(() => lineasExport.value
  .map((item) => `${item.codigo}\t${formatCantidad(item.cantidad)}`)
  .join('\n'));

const totales = computed(() => {
  const byMoneda = {};
  itemsResueltos.value.forEach((item) => {
    const moneda = item.moneda || 'PYG';
    byMoneda[moneda] = (byMoneda[moneda] || 0) + item.subtotal;
  });
  return Object.entries(byMoneda).map(([moneda, total]) => ({ moneda, total }));
});

const formatNumero = (valor) => {
  const n = Number(valor) || 0;
  return n.toLocaleString('es-PY', { maximumFractionDigits: 2 }).replace(/,00$/, '');
};

const formatCantidad = (valor) => {
  const n = Number(valor) || 0;
  return Number.isInteger(n) ? String(n) : String(n);
};

const etiquetaMoneda = (moneda) => (moneda === 'USD' ? 'USD' : 'Gs');

const formatPrecio = (valor, moneda) => `${formatNumero(valor)} ${etiquetaMoneda(moneda)}`;

const persistir = () => {
  try {
    localStorage.setItem(STORAGE_KEY, JSON.stringify(items.value));
  } catch (_) {}
};

const cargar = () => {
  try {
    const raw = localStorage.getItem(STORAGE_KEY);
    const parsed = raw ? JSON.parse(raw) : [];
    if (!Array.isArray(parsed)) return;
    items.value = parsed
      .filter((item) => productoPorId.value.has(String(item.id)))
      .map((item) => ({
        id: Number(item.id),
        cantidad: Math.max(1, Number(item.cantidad) || 1),
      }));
  } catch (_) {
    items.value = [];
  }
};

const cantidadDe = (id) => {
  const item = items.value.find((x) => String(x.id) === String(id));
  return item ? Math.max(1, Number(item.cantidad) || 1) : 0;
};

const persistirVista = () => {
  try {
    localStorage.setItem(VISTA_KEY, vista.value);
  } catch (_) {}
};

const agregar = (productoId) => {
  const p = productoPorId.value.get(String(productoId));
  if (!p) return;
  const existing = items.value.find((item) => String(item.id) === String(productoId));
  if (existing) {
    existing.cantidad = Math.max(1, Number(existing.cantidad) || 1) + 1;
    return;
  }
  items.value.push({ id: p.id, cantidad: 1 });
};

const agregarPrimeroFiltrado = () => {
  const primero = filtrados.value.find((p) => p.codigo);
  if (primero) agregar(primero.id);
};

const quitar = (id) => {
  items.value = items.value.filter((item) => String(item.id) !== String(id));
};

const cambiarCantidad = (id, delta) => {
  const item = items.value.find((x) => String(x.id) === String(id));
  if (!item) return;
  const next = Math.max(1, (Number(item.cantidad) || 1) + delta);
  item.cantidad = next;
};

const setCantidad = (id, value) => {
  const item = items.value.find((x) => String(x.id) === String(id));
  if (!item) return;
  item.cantidad = Math.max(1, Number(value) || 1);
};

const vaciar = () => {
  if (!items.value.length) return;
  if (!window.confirm('¿Vaciar la lista del pedido?')) return;
  items.value = [];
};

const stamp = () => {
  const d = new Date();
  const pad = (n) => String(n).padStart(2, '0');
  return `${d.getFullYear()}${pad(d.getMonth() + 1)}${pad(d.getDate())}-${pad(d.getHours())}${pad(d.getMinutes())}`;
};

const exportarExcel = () => {
  if (!lineasExport.value.length) return;
  const rows = [['codigo', 'cantidad'], ...lineasExport.value.map((item) => [item.codigo, formatCantidad(item.cantidad)])];
  const csv = rows.map((row) => row.join(';')).join('\r\n');
  const blob = new Blob(['\uFEFF' + csv], { type: 'text/csv;charset=utf-8;' });
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = `pedido-${stamp()}.csv`;
  document.body.appendChild(a);
  a.click();
  a.remove();
  URL.revokeObjectURL(url);
};

const copiar = async () => {
  if (!textoExport.value) return;
  try {
    await navigator.clipboard.writeText(textoExport.value);
    copiado.value = true;
    setTimeout(() => { copiado.value = false; }, 2000);
  } catch (_) {
    window.prompt('Copiá la lista:', textoExport.value);
  }
};

watch(items, persistir, { deep: true });
watch(vista, persistirVista);

onMounted(() => {
  cargar();
  try {
    const savedVista = localStorage.getItem(VISTA_KEY);
    if (savedVista === 'lista' || savedVista === 'cuadricula') {
      vista.value = savedVista;
    }
  } catch (_) {}
  buscarInput.value?.focus();
});
</script>
