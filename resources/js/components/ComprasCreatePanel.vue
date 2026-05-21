<template>
  <div class="space-y-4">
    <div>
      <div class="flex items-center justify-between mb-2">
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Detalle de compra *</label>
        <span class="text-xs px-2 py-1 rounded bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300">
          Items: {{ detalles.length }}
        </span>
        <button type="button" class="text-sm px-3 py-1.5 bg-purple-600 text-white rounded-lg hover:bg-purple-700" @click="addEmptyRow">
          + Linea
        </button>
      </div>

      <div class="overflow-x-auto border border-gray-200 dark:border-gray-700 rounded-lg">
        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700" id="tabla-detalles">
          <thead class="bg-gray-50 dark:bg-gray-700/50">
            <tr>
              <th class="px-2 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Articulo</th>
              <th class="px-2 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 w-32">Cant.</th>
              <th class="px-2 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 w-24">Precio</th>
              <th class="px-2 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 w-24">Subt.</th>
              <th class="px-2 py-2 w-10"></th>
            </tr>
          </thead>
          <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
            <tr v-for="(d, index) in detalles" :key="d.uid">
              <td class="px-3 py-2">
                <select
                  v-model="d.producto_id"
                  class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 text-sm"
                  required
                  @change="onProductChange(d)"
                >
                  <option value="">Seleccionar...</option>
                  <option v-for="p in productos" :key="p.id" :value="String(p.id)">
                    {{ labelProducto(p) }}
                  </option>
                </select>
                <input type="hidden" :name="`detalles[${index}][producto_id]`" :value="d.producto_id">
              </td>
              <td class="px-3 py-2">
                <div class="flex items-center justify-end gap-1">
                  <button type="button" class="h-9 w-9 rounded-md border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200" @click="changeCantidad(d, -1)">-</button>
                  <input
                    v-model.number="d.cantidad"
                    type="number"
                    step="0.01"
                    min="0.01"
                    required
                    class="w-16 px-2 py-2 rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 text-sm text-center"
                  >
                  <button type="button" class="h-9 w-9 rounded-md border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200" @click="changeCantidad(d, 1)">+</button>
                </div>
                <input type="hidden" :name="`detalles[${index}][cantidad]`" :value="safeNumber(d.cantidad)">
              </td>
              <td class="px-3 py-2">
                <input
                  v-model.number="d.precio_unitario"
                  type="number"
                  step="0.01"
                  min="0"
                  required
                  class="w-full px-2 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 text-sm text-right"
                >
                <input type="hidden" :name="`detalles[${index}][precio_unitario]`" :value="safeNumber(d.precio_unitario)">
              </td>
              <td class="px-3 py-2 text-right text-sm text-gray-900 dark:text-gray-100">{{ formatMoney(lineSubtotal(d)) }}</td>
              <td class="px-3 py-2">
                <button type="button" class="text-red-600 dark:text-red-400 hover:text-red-800 text-sm" @click="removeRow(index)">✕</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <div class="bg-gray-50 dark:bg-gray-900/40 border border-gray-200 dark:border-gray-700 rounded-lg p-4">
      <div class="space-y-1 text-sm">
        <div class="flex items-center justify-between text-gray-600 dark:text-gray-400"><span>Subtotal</span><span>{{ formatMoney(subtotal) }}</span></div>
        <div class="flex items-center justify-between text-gray-600 dark:text-gray-400"><span>Descuento</span><span>{{ formatMoney(descuento) }}</span></div>
        <div class="flex items-center justify-between text-gray-600 dark:text-gray-400"><span>Impuesto</span><span>{{ formatMoney(impuesto) }}</span></div>
        <div class="pt-2 mt-2 border-t border-gray-200 dark:border-gray-700 flex items-center justify-between font-semibold text-lg text-gray-900 dark:text-gray-100"><span>TOTAL</span><span>{{ formatMoney(total) }}</span></div>
      </div>
      <div class="mt-4 flex gap-2">
        <button type="button" id="btn-submit-compra" class="flex-1 inline-flex justify-center items-center px-4 py-2 bg-purple-600 text-white rounded-lg font-medium hover:bg-purple-700" @click="submitForm">
          {{ sending ? 'Guardando...' : 'Registrar compra' }}
        </button>
        <a :href="cancelUrl" class="inline-flex items-center px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-gray-200 rounded-lg font-medium hover:bg-gray-300 dark:hover:bg-gray-600 focus:outline-none">Cancelar</a>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';

const props = defineProps({
  productos: { type: Array, default: () => [] },
  oldDetalles: { type: Array, default: () => [] },
  formId: { type: String, default: 'form-compra' },
  cancelUrl: { type: String, default: '#' },
});

const detalles = ref([]);
const descuento = ref(0);
const impuesto = ref(0);
const sending = ref(false);

const formatMoney = (value) => new Intl.NumberFormat('es-AR', {
  minimumFractionDigits: 2,
  maximumFractionDigits: 2,
}).format(parseFloat(value) || 0);

const safeNumber = (value) => parseFloat(value) || 0;

const labelProducto = (p) => `${p.codigo ? `${p.codigo} - ` : ''}${p.nombre}`;

const lineSubtotal = (d) => safeNumber(d.cantidad) * safeNumber(d.precio_unitario);

const subtotal = computed(() => detalles.value.reduce((sum, d) => sum + lineSubtotal(d), 0));
const total = computed(() => subtotal.value - safeNumber(descuento.value) + safeNumber(impuesto.value));

const setFromInputs = () => {
  const d = document.getElementById('descuento');
  const i = document.getElementById('impuesto');
  descuento.value = d ? safeNumber(d.value) : 0;
  impuesto.value = i ? safeNumber(i.value) : 0;
};

const addEmptyRow = () => {
  detalles.value.push({
    uid: crypto.randomUUID(),
    producto_id: '',
    cantidad: 1,
    precio_unitario: 0,
  });
};

const removeRow = (index) => {
  detalles.value.splice(index, 1);
};

const onProductChange = (detalle) => {
  const p = props.productos.find((x) => String(x.id) === String(detalle.producto_id));
  if (p && (!detalle.precio_unitario || safeNumber(detalle.precio_unitario) === 0)) {
    detalle.precio_unitario = safeNumber(p.precio_compra);
  }
};

const changeCantidad = (detalle, delta) => {
  const next = safeNumber(detalle.cantidad) + delta;
  detalle.cantidad = next <= 0 ? 0.01 : next;
};

const addOrIncrementProducto = (productoId) => {
  const existing = detalles.value.find((d) => String(d.producto_id) === String(productoId));
  if (existing) {
    existing.cantidad = safeNumber(existing.cantidad) + 1;
    return;
  }
  const p = props.productos.find((x) => String(x.id) === String(productoId));
  if (!p) return;
  detalles.value.push({
    uid: crypto.randomUUID(),
    producto_id: String(p.id),
    cantidad: 1,
    precio_unitario: safeNumber(p.precio_compra),
  });
};

const submitForm = () => {
  const form = document.getElementById(props.formId);
  if (!form) return;
  if (!detalles.value.length) {
    alert('Debe agregar al menos una linea de detalle.');
    return;
  }
  sending.value = true;
  form.submit();
};

defineExpose({ addOrIncrementProducto });

onMounted(() => {
  setFromInputs();
  const d = document.getElementById('descuento');
  const i = document.getElementById('impuesto');
  d?.addEventListener('input', setFromInputs);
  i?.addEventListener('input', setFromInputs);

  if (props.oldDetalles.length) {
    detalles.value = props.oldDetalles.map((x) => ({
      uid: crypto.randomUUID(),
      producto_id: String(x.producto_id ?? ''),
      cantidad: safeNumber(x.cantidad || 1),
      precio_unitario: safeNumber(x.precio_unitario || 0),
    }));
  } else {
    addEmptyRow();
  }
});
</script>
