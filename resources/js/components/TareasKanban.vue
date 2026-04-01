<template>
  <div class="max-w-7xl mx-auto">
    <div class="mb-6 flex items-center justify-between">
      <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">Dashboard de Tareas</h1>
      <p class="text-sm text-gray-500 dark:text-gray-400">Pendiente · En progreso · Completado</p>
    </div>

    <div class="flex gap-4 overflow-x-auto pb-4">
      <div
        v-for="col in columnas"
        :key="col.id"
        class="column flex-shrink-0 w-80 bg-gray-100 dark:bg-gray-800 rounded-xl p-4 min-h-[400px]"
        :data-estado="col.id"
        @dragover.prevent="onDragOver"
        @drop="onDrop($event, col.id)"
      >
        <div class="flex items-center justify-between mb-4">
          <h3 class="font-semibold text-gray-700 dark:text-gray-300" :class="col.clase">
            {{ col.label }}
          </h3>
          <span class="text-sm text-gray-500 dark:text-gray-400">{{ tareasPorColumna(col.id).length }}</span>
        </div>

        <div class="space-y-3 min-h-[200px]">
          <div
            v-for="t in tareasPorColumna(col.id)"
            :key="t.id"
            draggable="true"
            class="card bg-white dark:bg-gray-700 rounded-lg p-4 shadow-sm border border-gray-200 dark:border-gray-600 cursor-grab active:cursor-grabbing hover:shadow-md transition-shadow"
            @dragstart="onDragStart($event, t)"
            @dragend="onDragEnd"
          >
            <div class="flex justify-between items-start gap-2">
              <div class="flex-1 min-w-0">
                <p class="font-medium text-gray-900 dark:text-gray-100 truncate">{{ t.titulo }}</p>
                <p v-if="t.descripcion" class="text-sm text-gray-500 dark:text-gray-400 mt-1 line-clamp-2">{{ t.descripcion }}</p>
              </div>
              <div class="flex items-center gap-1 shrink-0">
                <span
                  v-if="t.prioridad"
                  class="px-1.5 py-0.5 rounded text-xs font-medium"
                  :class="prioridadClase(t.prioridad)"
                >
                  {{ prioridadLabel(t.prioridad) }}
                </span>
                <button
                  v-if="canCreate"
                  type="button"
                  @click="abrirEditar(t)"
                  class="p-1 text-gray-400 dark:text-gray-500 hover:text-gray-600 dark:hover:text-gray-300 rounded"
                  title="Editar"
                >
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                </button>
                <button
                  v-if="canCreate"
                  type="button"
                  @click="confirmarEliminar(t)"
                  class="p-1 text-gray-400 dark:text-gray-500 hover:text-red-600 dark:hover:text-red-400 rounded"
                  title="Eliminar"
                >
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                </button>
              </div>
            </div>
            <div v-if="t.asignado || t.fecha_vencimiento" class="mt-2 flex items-center gap-2 text-xs text-gray-500 dark:text-gray-400">
              <span v-if="t.asignado">{{ t.asignado.name }}</span>
              <span v-if="t.fecha_vencimiento">{{ formatFecha(t.fecha_vencimiento) }}</span>
            </div>
          </div>
        </div>

        <button
          v-if="canCreate"
          type="button"
          @click="abrirNueva(col.id)"
          class="mt-4 w-full py-2 rounded-lg border-2 border-dashed border-gray-300 dark:border-gray-600 text-gray-500 dark:text-gray-400 hover:border-blue-400 dark:hover:border-blue-500 hover:text-blue-600 dark:hover:text-blue-400 transition-colors text-sm font-medium"
        >
          + Nueva tarea
        </button>
      </div>
    </div>

    <!-- Modal -->
    <div v-show="modalOpen" class="fixed inset-0 z-50 flex items-center justify-center px-4">
      <div class="absolute inset-0 bg-black/50" @click="cerrarModal"></div>
      <div class="relative bg-white dark:bg-gray-800 rounded-xl shadow-xl max-w-lg w-full p-6 border border-gray-200 dark:border-gray-700">
        <h2 class="text-lg font-semibold mb-4 text-gray-900 dark:text-gray-100">{{ editando ? 'Editar tarea' : 'Nueva tarea' }}</h2>
        <form @submit.prevent="guardar">
          <div class="space-y-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Título</label>
              <input v-model="form.titulo" type="text" required class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-white dark:bg-gray-700 dark:text-gray-100" placeholder="Título de la tarea" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Descripción</label>
              <textarea v-model="form.descripcion" rows="3" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-white dark:bg-gray-700 dark:text-gray-100" placeholder="Descripción (opcional)"></textarea>
            </div>
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Estado</label>
                <select v-model="form.estado" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-white dark:bg-gray-700 dark:text-gray-100">
                  <option value="pendiente">Pendiente</option>
                  <option value="en_progreso">En progreso</option>
                  <option value="completado">Completado</option>
                </select>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Prioridad</label>
                <select v-model="form.prioridad" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-white dark:bg-gray-700 dark:text-gray-100">
                  <option value="">Sin prioridad</option>
                  <option value="baja">Baja</option>
                  <option value="media">Media</option>
                  <option value="alta">Alta</option>
                </select>
              </div>
            </div>
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Asignado</label>
                <select v-model="form.asignado_id" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-white dark:bg-gray-700 dark:text-gray-100">
                  <option :value="null">Sin asignar</option>
                  <option v-for="u in usuarios" :key="u.usuario_id" :value="u.usuario_id">{{ u.name }}</option>
                </select>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Vencimiento</label>
                <input v-model="form.fecha_vencimiento" type="date" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-white dark:bg-gray-700 dark:text-gray-100" />
              </div>
            </div>
          </div>
          <div class="mt-6 flex justify-end gap-3">
            <button type="button" @click="cerrarModal" class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700">Cancelar</button>
            <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">Guardar</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import axios from 'axios';

const columnas = [
  { id: 'pendiente', label: 'Pendiente', clase: 'text-amber-600 dark:text-amber-400' },
  { id: 'en_progreso', label: 'En progreso', clase: 'text-blue-600 dark:text-blue-400' },
  { id: 'completado', label: 'Completado', clase: 'text-green-600 dark:text-green-400' },
];

const props = defineProps({
  tareas: { type: Array, default: () => [] },
  usuarios: { type: Array, default: () => [] },
  canCreate: { type: Boolean, default: false },
  csrfToken: { type: String, default: '' },
  urlStore: { type: String, default: '' },
  urlUpdate: { type: String, default: '' },
  urlMove: { type: String, default: '' },
  urlDestroy: { type: String, default: '' },
});

const tareasList = ref([...(props.tareas || [])]);
const modalOpen = ref(false);
const editando = ref(null);
const form = ref({
  titulo: '',
  descripcion: '',
  estado: 'pendiente',
  prioridad: '',
  asignado_id: null,
  fecha_vencimiento: '',
});

const dragTask = ref(null);

const tareasPorColumna = (estado) => {
  return tareasList.value
    .filter(t => t.estado === estado)
    .sort((a, b) => a.orden - b.orden);
};

function prioridadLabel(p) {
  const m = { baja: 'Baja', media: 'Media', alta: 'Alta' };
  return m[p] || p;
}

function prioridadClase(p) {
  const m = {
    baja: 'bg-gray-100 text-gray-700 dark:bg-gray-600 dark:text-gray-300',
    media: 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300',
    alta: 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300',
  };
  return m[p] || 'bg-gray-100 text-gray-700 dark:bg-gray-600 dark:text-gray-300';
}

function formatFecha(f) {
  if (!f) return '';
  const d = new Date(f);
  return d.toLocaleDateString('es-AR', { day: '2-digit', month: 'short', year: 'numeric' });
}

function abrirNueva(estado) {
  editando.value = null;
  form.value = { titulo: '', descripcion: '', estado, prioridad: '', asignado_id: null, fecha_vencimiento: '' };
  modalOpen.value = true;
}

function abrirEditar(t) {
  editando.value = t;
  form.value = {
    titulo: t.titulo,
    descripcion: t.descripcion || '',
    estado: t.estado,
    prioridad: t.prioridad || '',
    asignado_id: t.asignado?.usuario_id ?? null,
    fecha_vencimiento: t.fecha_vencimiento || '',
  };
  modalOpen.value = true;
}

function cerrarModal() {
  modalOpen.value = false;
  editando.value = null;
}

async function guardar() {
  if (editando.value) {
    const { data } = await axios.put(
      props.urlUpdate.replace('__id__', editando.value.id),
      { ...form.value, _token: props.csrfToken },
      { headers: { 'X-CSRF-TOKEN': props.csrfToken, 'Content-Type': 'application/json', Accept: 'application/json' } }
    );
    const i = tareasList.value.findIndex(t => t.id === data.id);
    if (i >= 0) tareasList.value[i] = data;
    else tareasList.value.push(data);
  } else {
    const { data } = await axios.post(props.urlStore, { ...form.value, _token: props.csrfToken }, {
      headers: { 'X-CSRF-TOKEN': props.csrfToken, 'Content-Type': 'application/json', Accept: 'application/json' }
    });
    tareasList.value.push(data);
  }
  cerrarModal();
}

function onDragStart(ev, t) {
  dragTask.value = t;
  ev.dataTransfer.effectAllowed = 'move';
  ev.dataTransfer.setData('text/plain', t.id);
  ev.target.classList.add('opacity-50');
}

function onDragEnd(ev) {
  ev.target.classList.remove('opacity-50');
  dragTask.value = null;
}

function onDragOver(ev) {
  ev.dataTransfer.dropEffect = 'move';
}

async function onDrop(ev, estadoDestino) {
  ev.preventDefault();
  const t = dragTask.value;
  if (!t || t.estado === estadoDestino) return;

  const col = tareasPorColumna(estadoDestino);
  const orden = col.length;

  try {
    const { data } = await axios.post(
      props.urlMove.replace('__id__', t.id),
      { estado: estadoDestino, orden, _token: props.csrfToken },
      { headers: { 'X-CSRF-TOKEN': props.csrfToken, 'Content-Type': 'application/json', Accept: 'application/json' } }
    );
    const i = tareasList.value.findIndex(x => x.id === data.id);
    if (i >= 0) tareasList.value[i] = data;
  } catch (err) {
    console.error('Error al mover tarea:', err);
  }
}

async function confirmarEliminar(t) {
  if (!window.confirm('¿Eliminar esta tarea?')) return;
  try {
    await axios.delete(props.urlDestroy.replace('__id__', t.id), {
      headers: { 'X-CSRF-TOKEN': props.csrfToken, Accept: 'application/json' }
    });
    tareasList.value = tareasList.value.filter(x => x.id !== t.id);
  } catch (err) {
    console.error('Error al eliminar:', err);
  }
}
</script>
