<template>
  <template v-if="clientes.length === 0">
    <tr>
      <td colspan="7" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">
        No hay clientes. <a :href="urlCreate" class="text-purple-600 dark:text-purple-400 hover:underline">Crear uno</a>.
      </td>
    </tr>
  </template>
  <template v-else>
    <template v-for="c in clientes" :key="c.cliente_id">
      <tr
        class="hover:bg-gray-50 dark:hover:bg-gray-700/50"
        :class="{ 'cursor-pointer': c.servicios && c.servicios.length > 0 }"
        :id="c.servicios?.length ? 'accordion-trigger-' + c.cliente_id : undefined"
        :aria-expanded="c.servicios?.length && openedIds.includes(c.cliente_id)"
        :aria-controls="c.servicios?.length ? 'servicios-' + c.cliente_id : undefined"
        :role="c.servicios?.length ? 'button' : undefined"
        :tabindex="c.servicios?.length ? 0 : undefined"
        :title="c.servicios?.length ? 'Clic para ver servicios' : undefined"
        @click="c.servicios?.length && toggle(c.cliente_id)"
        @keydown.enter.prevent="c.servicios?.length && toggle(c.cliente_id)"
        @keydown.space.prevent="c.servicios?.length && toggle(c.cliente_id)"
      >
      <td class="px-4 py-3 text-sm text-gray-900 dark:text-gray-100">
        {{ firstItem + clientes.indexOf(c) }}
      </td>
        <td class="px-4 py-3 text-sm text-gray-900 dark:text-gray-100">
          <span class="text-gray-600 dark:text-gray-300 font-medium">{{ c.nombre }} {{ c.apellido }}</span><br>
          <span class="text-gray-400 dark:text-gray-200">{{ formatDocument(c.cedula) }}</span>
        </td>
        <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-300 max-w-[min(18rem,28vw)] w-[min(18rem,28vw)]">
          <div class="truncate" :title="c.direccion || ''">{{ c.direccion || '—' }}</div>
          <a v-if="getMapsUrl(c)"
             :href="getMapsUrl(c)"
             target="_blank"
             rel="noopener noreferrer"
             @click.stop
             class="inline-flex items-center gap-1 text-xs text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300 mt-0.5 whitespace-nowrap">
            <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
            </svg>
            Ver mapa
          </a>
        </td>
        <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-300">{{ c.telefono || '—' }}</td>
        <td class="px-4 py-3">
          <span
            class="inline-flex px-2 py-1 text-xs font-medium rounded-full"
            :class="estadoClase(c.estado)"
          >
            {{ c.estado ? c.estado.charAt(0).toUpperCase() + c.estado.slice(1) : '—' }}
          </span>
        </td>
        <td class="px-4 py-3">
          <span
            v-if="c.calificacion_pago"
            class="inline-flex items-center gap-0.5"
            :class="calificacionPagoClase(c.calificacion_pago)"
            :title="calificacionPagoLabel(c.calificacion_pago)"
          >
            <template v-for="i in 3" :key="i">
              <svg
                v-if="i <= calificacionPagoEstrellas(c.calificacion_pago)"
                class="w-4 h-4"
                fill="currentColor"
                viewBox="0 0 20 20"
              >
                <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
              </svg>
              <svg
                v-else
                class="w-4 h-4 opacity-30"
                fill="currentColor"
                viewBox="0 0 20 20"
              >
                <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
              </svg>
            </template>
          </span>
          <span v-else class="text-gray-400 dark:text-gray-500 text-xs">—</span>
        </td>
        <td class="px-4 py-3">
          <div class="flex items-center justify-end gap-1">
            <button
              v-if="puedeEditar"
              type="button"
              class="p-2 rounded-lg text-blue-600 dark:text-blue-400 hover:bg-blue-100 dark:hover:bg-blue-900/30 transition-colors"
              title="Buscar en temp"
              aria-label="Buscar en temp"
              @click.stop="buscarTemp(c)"
            >
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
              </svg>
            </button>
            <a
              v-if="urlCreateServicioBase && (!c.servicios || c.servicios.length === 0)"
              :href="urlCreateServicio(c.cliente_id)"
              class="p-2 rounded-lg text-green-600 dark:text-green-400 hover:text-green-800 dark:hover:text-green-300 font-medium hover:bg-green-100 dark:hover:bg-green-900/30 transition-colors"
              @click.stop
              title="Crear servicio"
            >
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
              </svg>
            </a>
            <a :href="urlEditCliente(c.cliente_id)" class="p-2 rounded-lg text-purple-600 dark:text-purple-400 hover:text-purple-800 dark:hover:text-purple-300 font-medium hover:bg-purple-100 dark:hover:bg-purple-900/30 transition-colors" @click.stop title="Editar">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
              </svg>
            </a>
            <form :action="urlDestroyCliente(c.cliente_id)" method="POST" class="inline" @submit.prevent="confirmDestroy($event)" @click.stop>
              <input type="hidden" name="_token" :value="csrfToken" />
              <input type="hidden" name="_method" value="DELETE" />
              <button type="submit" class="text-red-600 dark:text-red-400 hover:text-red-800 dark:hover:text-red-300 font-medium">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                </svg>
              </button>
            </form>
            <span
              v-if="c.servicios && c.servicios.length > 0"
              class="inline-flex items-center justify-center w-8 h-8 ml-2 rounded text-gray-500 dark:text-gray-400 transition-transform"
              :class="{ 'rotate-180': openedIds.includes(c.cliente_id) }"
            >
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
              </svg>
            </span>
            <span v-else class="inline-flex items-center justify-center w-8 h-8 ml-2 rounded text-gray-500 dark:text-gray-400">—</span>
          </div>
        </td>
      </tr>
      <tr
        v-if="c.servicios && c.servicios.length > 0 && openedIds.includes(c.cliente_id)"
        :id="'servicios-' + c.cliente_id"
        class="servicios-accordion-panel bg-gray-50/80 dark:bg-gray-700/50"
        role="region"
        :aria-labelledby="'accordion-trigger-' + c.cliente_id"
      >
        <td colspan="7" class="px-4 py-3 border-l-4 border-purple-200 dark:border-purple-800">
          <div class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-2">
            Servicios ({{ c.servicios.length }})
          </div>
          <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-800">
            <table class="min-w-full text-sm divide-y divide-gray-200 dark:divide-gray-700">
              <thead class="bg-gray-100 dark:bg-gray-700/50">
                <tr>
                  <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400">#</th>
                  <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Plan</th>
                  <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Fecha Instalación</th>
                  <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400">IP</th>
                  <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Estado</th>
                  <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400">Acción</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="s in c.servicios" :key="s.cliente_id + '-' + s.servicio_id" class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                  <td class="px-3 py-2 text-gray-900 dark:text-gray-100">{{ s.servicio_id }}</td>
                  <td class="px-3 py-2 text-gray-700 dark:text-gray-300">{{ s.plan && s.plan.nombre ? s.plan.nombre : '—' }}</td>
                
                  <td class="px-3 py-2 text-gray-600 dark:text-gray-400">{{ s.fecha_instalacion ? new Date(s.fecha_instalacion).toLocaleDateString() : '—' }}</td>
                  <td class="px-3 py-2 text-gray-700 dark:text-gray-300">{{ s.ip ? s.ip : '—' }}</td>
                  <td class="px-3 py-2">{{ estadoServicioLabel(s.estado) }}</td>
                  <td class="px-3 py-2 text-right">
                    <a :href="urlEditServicio(s.servicio_id)" class="text-purple-600 dark:text-purple-400 hover:text-purple-800 dark:hover:text-purple-300 font-medium">Editar</a>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
          <a :href="urlCreateServicio(c.cliente_id)" class="inline-block mt-2 text-xs text-purple-600 dark:text-purple-400 hover:text-purple-800 dark:hover:text-purple-300 font-medium">+ Agregar servicio</a>
        </td>
      </tr>
    </template>
  </template>

  <!-- Modal buscar temp -->
  <Teleport to="body">
    <div
      v-if="modalTempVisible"
      class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50"
      @click.self="cerrarModalTemp"
    >
      <div class="bg-white dark:bg-gray-800 rounded-xl shadow-xl max-w-lg w-full max-h-[90vh] overflow-hidden flex flex-col">
        <div class="p-4 border-b border-gray-200 dark:border-gray-700 flex justify-between items-center">
          <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Datos encontrados en temp</h3>
          <button type="button" class="p-2 text-gray-500 hover:text-gray-700 dark:hover:text-gray-300" @click="cerrarModalTemp" aria-label="Cerrar">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
          </button>
        </div>
        <div class="p-4 overflow-y-auto flex-1">
          <p v-if="modalTempLoading" class="text-sm text-gray-600 dark:text-gray-400">Buscando...</p>
          <div v-else-if="modalTempResultados.length === 0" class="space-y-3">
            <p class="text-sm text-gray-600 dark:text-gray-400">No se encontraron resultados.</p>
            <div class="flex gap-2">
              <input
                v-model="modalTempBuscar"
                type="text"
                class="flex-1 px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 text-sm focus:ring-2 focus:ring-purple-500 focus:border-purple-500"
                placeholder="Modificá el nombre y buscá de nuevo"
              />
              <button
                type="button"
                class="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 text-sm font-medium disabled:opacity-50"
                :disabled="!modalTempBuscar || modalTempBuscar.trim().length < 2"
                @click="buscarTempDeNuevo"
              >
                Buscar
              </button>
            </div>
          </div>
          <div v-else class="space-y-3">
            <div
              v-for="(r, idx) in modalTempResultados"
              :key="idx"
              class="p-3 rounded-lg border border-gray-200 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700/50 cursor-pointer"
              :class="{ 'ring-2 ring-purple-500': modalTempSeleccionado === idx }"
              @click="modalTempSeleccionado = idx"
            >
              <div class="text-sm text-gray-900 dark:text-gray-100 font-medium">{{ r.nombre }}</div>
              <div class="text-xs text-gray-600 dark:text-gray-400 mt-1">
                Cédula: {{ r.cedula || '—' }} | Cel: {{ r.celular || '—' }}<br>
                Dirección: {{ r.direccion || '—' }}<br>
                <span v-if="r.latitud && r.longitud">Coords: {{ r.latitud }}, {{ r.longitud }}</span>
              </div>
            </div>
          </div>
        </div>
        <div class="p-4 border-t border-gray-200 dark:border-gray-700 flex justify-end gap-2">
          <button type="button" class="px-4 py-2 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600 rounded-lg" @click="cerrarModalTemp">Cancelar</button>
          <button
            v-if="modalTempResultados.length > 0"
            type="button"
            class="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 disabled:opacity-50 disabled:cursor-not-allowed"
            :disabled="modalTempSeleccionado === null || modalTempActualizando"
            @click="aplicarTemp"
          >
            {{ modalTempActualizando ? 'Actualizando...' : 'Actualizar cliente' }}
          </button>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script setup>
import { ref, computed } from 'vue';

const props = defineProps({
  clientes: { type: Array, default: () => [] },
  firstItem: { type: Number, default: 1 },
  csrfToken: { type: String, default: '' },
  urlEditClienteBase: { type: String, default: '' },
  urlDestroyClienteBase: { type: String, default: '' },
  urlCreateCliente: { type: String, default: '' },
  urlEditServicioBase: { type: String, default: '' },
  urlCreateServicioBase: { type: String, default: '' },
  urlBuscarTemp: { type: String, default: '' },
  urlActualizarDesdeTempBase: { type: String, default: '' },
  puedeEditar: { type: Boolean, default: false },
});

const openedIds = ref([]);
const modalTempVisible = ref(false);
const modalTempLoading = ref(false);
const modalTempResultados = ref([]);
const modalTempSeleccionado = ref(null);
const modalTempActualizando = ref(false);
const modalTempCliente = ref(null);
const modalTempBuscar = ref('');

function toggle(clienteId) {
  const i = openedIds.value.indexOf(clienteId);
  if (i >= 0) openedIds.value.splice(i, 1);
  else openedIds.value.push(clienteId);
}

function estadoClase(estado) {
  const map = {
    activo: 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300',
    inactivo: 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300',
    suspendido: 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300',
  };
  return map[estado] || 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300';
}

function estadoServicioLabel(estado) {
  const map = { A: 'Activo', S: 'Suspendido', C: 'Cancelado', P: 'Pendiente' };
  return map[estado] || 'Pendiente';
}

function calificacionPagoLabel(calif) {
  const map = { malo: 'Malo', bueno: 'Bueno', excelente: 'Excelente' };
  return map[calif] || calif;
}

function calificacionPagoEstrellas(calif) {
  const map = { malo: 1, bueno: 2, excelente: 3 };
  return map[calif] ?? 0;
}

function calificacionPagoClase(calif) {
  const map = {
    malo: 'text-red-500 dark:text-red-400',
    bueno: 'text-blue-500 dark:text-blue-400',
    excelente: 'text-amber-400 dark:text-amber-300',
  };
  return map[calif] || 'text-gray-500 dark:text-gray-400';
}

function urlEditCliente(id) {
  return props.urlEditClienteBase.replace('__id__', id);
}

function urlDestroyCliente(id) {
  return props.urlDestroyClienteBase.replace('__id__', id);
}

function urlEditServicio(servicioId) {
  return props.urlEditServicioBase.replace('__servicio_id__', servicioId);
}

function urlCreateServicio(clienteId) {
  return props.urlCreateServicioBase.replace('__cliente_id__', clienteId);
}

const urlCreate = computed(() => props.urlCreateCliente);

function confirmDestroy(ev) {
  if (window.confirm('¿Eliminar este cliente?')) {
    ev.target.closest('form').submit();
  }
}

function formatDocument(document) {
  return document.replace(/(\d{3})(\d{3})(\d{4})/, '$1-$2-$3');
}

/** URL del mapa solo desde url_ubicacion (no usa dirección, evita URLs largas que ensanchan la tabla). */
function getMapsUrl(cliente) {
  if (!cliente) return null;
  const raw = (cliente.url_ubicacion || '').toString().trim();
  if (!raw) return null;
  if (/^https?:\/\//i.test(raw)) return raw;
  if (/^\/\//.test(raw)) return 'https:' + raw;
  const coordMatch = raw.match(/^(-?\d+\.?\d*)\s*,\s*(-?\d+\.?\d*)$/);
  if (coordMatch) {
    return 'https://www.google.com/maps?q=' + coordMatch[1] + ',' + coordMatch[2];
  }
  return 'https://www.google.com/maps/search/?api=1&query=' + encodeURIComponent(raw);
}

async function ejecutarBuscarTemp(nombre) {
  if (!props.urlBuscarTemp || !nombre || nombre.trim().length < 2) return;
  modalTempLoading.value = true;
  modalTempResultados.value = [];
  modalTempSeleccionado.value = null;
  try {
    const r = await fetch(props.urlBuscarTemp + '?nombre=' + encodeURIComponent(nombre.trim()), {
      headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
    });
    const data = await r.json();
    modalTempResultados.value = data.encontrados || [];
    if (modalTempResultados.value.length > 0) {
      modalTempSeleccionado.value = 0;
    }
  } catch (e) {
    modalTempResultados.value = [];
  } finally {
    modalTempLoading.value = false;
  }
}

async function buscarTemp(cliente) {
  if (!props.urlBuscarTemp) return;
  const nombre = [cliente.nombre, cliente.apellido].filter(Boolean).join(' ').trim();
  if (!nombre) {
    alert('El cliente no tiene nombre para buscar.');
    return;
  }
  modalTempCliente.value = cliente;
  modalTempBuscar.value = nombre;
  modalTempVisible.value = true;
  await ejecutarBuscarTemp(nombre);
}

function buscarTempDeNuevo() {
  if (modalTempCliente.value) {
    ejecutarBuscarTemp(modalTempBuscar.value);
  }
}

function cerrarModalTemp() {
  modalTempVisible.value = false;
  modalTempCliente.value = null;
}

async function aplicarTemp() {
  const cliente = modalTempCliente.value;
  const idx = modalTempSeleccionado.value;
  if (!cliente || idx === null || !props.urlActualizarDesdeTempBase) return;
  const r = modalTempResultados.value[idx];
  if (!r) return;
  modalTempActualizando.value = true;
  try {
    const url = props.urlActualizarDesdeTempBase.replace('__id__', cliente.cliente_id);
    const res = await fetch(url, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        'X-CSRF-TOKEN': props.csrfToken,
        'X-Requested-With': 'XMLHttpRequest',
      },
      body: JSON.stringify({
        cedula: r.cedula || '',
        celular: r.celular || '',
        direccion: r.direccion || '',
        latitud: r.latitud ?? null,
        longitud: r.longitud ?? null,
      }),
    });
    const data = await res.json();
    if (data.success) {
      if (data.cliente) {
        Object.assign(cliente, data.cliente);
      }
      cerrarModalTemp();
      //alert('Cliente actualizado correctamente.');
    } else {
      alert('Error al actualizar: ' + (data.message || 'Error desconocido'));
    }
  } catch (e) {
    alert('Error de conexión: ' + (e.message || 'Error desconocido'));
  } finally {
    modalTempActualizando.value = false;
  }
}
</script>
