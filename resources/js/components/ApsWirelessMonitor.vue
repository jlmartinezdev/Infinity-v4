<template>
  <div class="max-w-[1600px] mx-auto">
    <div class="flex flex-col lg:flex-row lg:items-start gap-4 mb-4">
      <div class="flex-1 min-w-0">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">APs wireless</h1>
        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
          Ping ICMP cada minuto y consulta SSH (airOS) de access points Ubiquiti por nodo.
        </p>
      </div>
      <div class="flex flex-wrap items-center gap-2">
        <a
          v-if="urlAvisos"
          :href="urlAvisos"
          class="inline-flex items-center gap-2 px-3 py-2 rounded-lg border border-rose-400/60 text-sm text-rose-700 dark:text-rose-300 hover:bg-rose-50 dark:hover:bg-rose-900/20"
        >
          Alertas WhatsApp
        </a>
        <button
          type="button"
          class="inline-flex items-center gap-2 px-3 py-2 rounded-lg border border-cyan-500/50 text-sm text-cyan-700 dark:text-cyan-300 hover:bg-cyan-50 dark:hover:bg-cyan-900/20 disabled:opacity-50"
          :disabled="ocupado || !apsActivos.length"
          @click="pingTodos"
        >
          <svg class="w-4 h-4" :class="{ 'animate-spin': pingeandoTodos }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
          </svg>
          {{ pingeandoTodos ? progresoPing : 'Ping todos' }}
        </button>
        <button
          type="button"
          class="inline-flex items-center gap-2 px-3 py-2 rounded-lg border border-violet-500/50 text-sm text-violet-700 dark:text-violet-300 hover:bg-violet-50 dark:hover:bg-violet-900/20 disabled:opacity-50"
          :disabled="ocupado || !apsActivos.length"
          @click="sshTodos"
        >
          <svg class="w-4 h-4" :class="{ 'animate-spin': sshTodosActivo }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l3 3-3 3m5 0h3M5 20h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
          </svg>
          {{ sshTodosActivo ? progresoSsh : 'Consultar SSH' }}
        </button>
        <button
          v-if="canCrear"
          type="button"
          class="inline-flex items-center gap-2 px-3 py-2 rounded-lg bg-blue-600 text-white text-sm hover:bg-blue-700 disabled:opacity-50"
          :disabled="ocupado"
          @click="abrirFormulario(null)"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
          </svg>
          Nuevo AP
        </button>
      </div>
    </div>

    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-4" role="group" aria-label="Filtrar por estado de ping">
      <button
        type="button"
        class="rounded-xl border px-4 py-3 text-left transition cursor-pointer border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 hover:border-gray-400 dark:hover:border-gray-500"
        :class="filtroPing === '' ? 'ring-2 ring-offset-1 ring-gray-400 dark:ring-gray-500 dark:ring-offset-gray-900' : ''"
        :aria-pressed="filtroPing === ''"
        title="Mostrar todos los APs"
        @click="filtroPing = ''"
      >
        <p class="text-xs text-gray-500 dark:text-gray-400">Registrados</p>
        <p class="text-xl font-semibold text-gray-900 dark:text-gray-100">{{ aps.length }}</p>
      </button>
      <button
        type="button"
        class="rounded-xl border px-4 py-3 text-left transition cursor-pointer border-emerald-200 dark:border-emerald-800 bg-emerald-50 dark:bg-emerald-900/20 hover:border-emerald-400 dark:hover:border-emerald-600"
        :class="filtroPing === 'online' ? 'ring-2 ring-offset-1 ring-emerald-500 dark:ring-offset-gray-900' : ''"
        :aria-pressed="filtroPing === 'online'"
        title="Filtrar APs en línea"
        @click="seleccionarFiltroPing('online')"
      >
        <p class="text-xs text-emerald-700 dark:text-emerald-300">En línea</p>
        <p class="text-xl font-semibold text-emerald-800 dark:text-emerald-200">{{ resumen.online }}</p>
      </button>
      <button
        type="button"
        class="rounded-xl border px-4 py-3 text-left transition cursor-pointer border-rose-200 dark:border-rose-800 bg-rose-50 dark:bg-rose-900/20 hover:border-rose-400 dark:hover:border-rose-600"
        :class="filtroPing === 'offline' ? 'ring-2 ring-offset-1 ring-rose-500 dark:ring-offset-gray-900' : ''"
        :aria-pressed="filtroPing === 'offline'"
        title="Filtrar APs sin ping"
        @click="seleccionarFiltroPing('offline')"
      >
        <p class="text-xs text-rose-700 dark:text-rose-300">Sin ping</p>
        <p class="text-xl font-semibold text-rose-800 dark:text-rose-200">{{ resumen.offline }}</p>
      </button>
      <button
        type="button"
        class="rounded-xl border px-4 py-3 text-left transition cursor-pointer border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 hover:border-gray-400 dark:hover:border-gray-500"
        :class="filtroPing === 'desconocido' ? 'ring-2 ring-offset-1 ring-gray-400 dark:ring-gray-500 dark:ring-offset-gray-900' : ''"
        :aria-pressed="filtroPing === 'desconocido'"
        title="Filtrar APs sin consultar"
        @click="seleccionarFiltroPing('desconocido')"
      >
        <p class="text-xs text-gray-500 dark:text-gray-400">Sin consultar</p>
        <p class="text-xl font-semibold text-gray-900 dark:text-gray-100">{{ resumen.desconocido }}</p>
      </button>
    </div>

    <div class="flex flex-col sm:flex-row gap-2 mb-4">
      <input
        v-model="busqueda"
        type="search"
        placeholder="Buscar nombre, IP o SSID…"
        class="flex-1 py-2 px-3 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm text-gray-900 dark:text-gray-100"
      >
      <select
        v-model="filtroNodo"
        class="sm:w-56 py-2 px-3 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm text-gray-900 dark:text-gray-100"
      >
        <option value="">Todos los nodos</option>
        <option v-for="n in nodos" :key="n.nodo_id" :value="String(n.nodo_id)">{{ n.descripcion }}</option>
      </select>
    </div>

    <p v-if="aviso" class="mb-3 text-sm text-gray-600 dark:text-gray-300">{{ aviso }}</p>
    <p v-if="errorGlobal" class="mb-3 text-sm text-rose-600 dark:text-rose-400">{{ errorGlobal }}</p>

    <div v-if="!gruposFiltrados.length" class="rounded-xl border border-dashed border-gray-300 dark:border-gray-600 p-8 text-center text-sm text-gray-500 dark:text-gray-400">
      {{ aps.length ? 'Ningún AP coincide con el filtro.' : 'Todavía no hay APs. Registrá la IP de cada access point por nodo.' }}
      <button
        v-if="aps.length && (filtroPing || filtroNodo || busqueda.trim())"
        type="button"
        class="mt-2 block mx-auto text-sm text-blue-600 dark:text-blue-400 hover:underline"
        @click="limpiarFiltros"
      >Quitar filtros</button>
    </div>

    <section
      v-for="grupo in gruposFiltrados"
      :key="grupo.nodo_id"
      class="mb-4 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 overflow-hidden"
    >
      <header class="flex items-center justify-between gap-3 px-4 py-2.5 bg-gray-50 dark:bg-gray-900/60 border-b border-gray-200 dark:border-gray-700">
        <h2 class="text-sm font-semibold text-gray-800 dark:text-gray-100">{{ grupo.nodo }}</h2>
        <p class="text-xs text-gray-500 dark:text-gray-400">
          {{ grupo.aps.length }} AP ·
          <span class="text-emerald-600 dark:text-emerald-400">{{ contarEstado(grupo.aps, true) }} en línea</span>
          <span v-if="contarEstado(grupo.aps, false)" class="text-rose-600 dark:text-rose-400"> · {{ contarEstado(grupo.aps, false) }} caídos</span>
        </p>
      </header>

      <Espectro5GhzNodo :aps="grupo.aps" />

      <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
          <thead class="text-left text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">
            <tr>
              <th class="px-3 py-2 font-medium">AP</th>
              <th class="px-3 py-2 font-medium">SSID</th>
              <th class="px-3 py-2 font-medium text-right w-px">Acciones</th>
            </tr>
          </thead>
          <tbody>
            <template v-for="ap in grupo.aps" :key="ap.ap_id">
              <tr class="border-t border-gray-100 dark:border-gray-700/80">
                <td class="px-3 py-2 cursor-pointer" @click="toggleDetalle(ap.ap_id)">
                  <div class="flex items-start gap-2.5 min-w-0">
                    <span class="mt-1.5 h-2 w-2 rounded-full shrink-0" :class="clasePunto(ap)"></span>
                    <div class="min-w-0">
                      <div class="flex flex-wrap items-baseline gap-x-3 gap-y-0.5">
                        <p class="flex items-center gap-1.5 min-w-0 font-medium text-gray-900 dark:text-gray-100">
                          <span
                            class="h-2.5 w-2.5 rounded-sm shrink-0"
                            :style="{ background: colorAp(ap.ap_id) }"
                            title="Color en espectro 5 GHz"
                          ></span>
                          {{ ap.nombre }}
                        </p>
                        <span class="text-xs font-medium tabular-nums" :class="claseTextoPing(ap)">{{ etiquetaPing(ap) }}</span>
                        <span v-if="ap.ping_ok === false && ap.ping_caido_desde" class="text-[11px] text-rose-500 tabular-nums" :title="ap.caido_desde || ''">{{ caidoHace(ap) }}</span>
                      </div>
                      <p class="mt-0.5 text-[11px] text-gray-400">
                        <a
                          v-if="ap.ip"
                          :href="'http://' + ap.ip"
                          target="_blank"
                          rel="noopener noreferrer"
                          class="font-mono text-blue-600 dark:text-blue-400 hover:underline"
                          :title="'Abrir ' + ap.ip + ' en una pestaña'"
                          @click.stop
                        >{{ ap.ip }}</a>
                        <template v-if="ap.hostname && ap.hostname !== ap.nombre"><span v-if="ap.ip"> · </span>{{ ap.hostname }}</template>
                        <template v-if="ap.modo"><span v-if="ap.ip || (ap.hostname && ap.hostname !== ap.nombre)"> · </span>{{ ap.modo }}</template>
                        <span v-if="!ap.activo" class="text-amber-600"> · Inactivo</span>
                      </p>
                      <p v-if="ap.ping_error && ap.ping_ok === false" class="text-[11px] text-rose-500 truncate" :title="ap.ping_error">{{ ap.ping_error }}</p>
                      <p v-if="ap.ssh_error" class="text-[11px] text-rose-500 truncate" :title="ap.ssh_error">{{ ap.ssh_error }}</p>
                    </div>
                  </div>
                </td>
                <td class="px-3 py-2 whitespace-nowrap align-middle text-gray-700 dark:text-gray-200">{{ ap.ssid || '—' }}</td>
                <td class="px-3 py-2 text-right whitespace-nowrap align-middle">
                  <div class="inline-flex items-center justify-end gap-1">
                  <button
                    type="button"
                    class="inline-flex items-center justify-center w-7 h-7 rounded-lg text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-700"
                    title="Datos"
                    :aria-expanded="detalleId === ap.ap_id"
                    @click.stop="toggleDetalle(ap.ap_id)"
                  >
                    <svg class="w-4 h-4 transition-transform" :class="detalleId === ap.ap_id ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                  </button>
                  <button
                    type="button"
                    class="inline-flex items-center justify-center w-7 h-7 rounded-lg text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-700"
                    data-menu-ap
                    title="Acciones"
                    aria-label="Acciones"
                    :aria-expanded="menuAccionesId === ap.ap_id"
                    @click.stop="toggleMenuAcciones(ap.ap_id, $event)"
                  >
                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><circle cx="12" cy="5" r="1.6"/><circle cx="12" cy="12" r="1.6"/><circle cx="12" cy="19" r="1.6"/></svg>
                  </button>
                  </div>
                </td>
              </tr>
              <tr v-if="detalleId === ap.ap_id" class="bg-gray-50 dark:bg-gray-900/40">
                <td colspan="3" class="px-4 py-3 text-xs text-gray-600 dark:text-gray-300">
                  <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                    <div><span class="text-gray-400">Radio</span><br>{{ textoRadio(ap) }}</div>
                    <div><span class="text-gray-400">Estaciones</span><br>{{ ap.estaciones ?? '—' }}</div>
                    <div><span class="text-gray-400">Uptime</span><br>{{ ap.uptime || '—' }}</div>
                    <div><span class="text-gray-400">Modelo</span><br>{{ ap.modelo || '—' }}</div>
                    <div><span class="text-gray-400">MAC</span><br><span class="font-mono">{{ ap.mac || '—' }}</span></div>
                    <div><span class="text-gray-400">Noise</span><br>{{ extraVal(ap, 'noise') }}</div>
                    <div><span class="text-gray-400">CCQ</span><br>{{ extraVal(ap, 'ccq') }}</div>
                    <div><span class="text-gray-400">CPU</span><br>{{ extraVal(ap, 'cpu') }}</div>
                    <div><span class="text-gray-400">Último ping</span><br>{{ formatearFecha(ap.ping_at) }}</div>
                    <div v-if="ap.ping_ok === false && ap.ping_caido_desde">
                      <span class="text-gray-400">Caído desde</span><br>
                      {{ ap.caido_desde || formatearFecha(ap.ping_caido_desde) }}
                      <span class="text-rose-500"> · {{ caidoHace(ap) }}</span>
                    </div>
                    <div><span class="text-gray-400">Último SSH</span><br>{{ formatearFecha(ap.ssh_at) }}</div>
                    <div v-if="ap.notas" class="sm:col-span-2"><span class="text-gray-400">Notas</span><br>{{ ap.notas }}</div>
                  </div>
                </td>
              </tr>
            </template>
          </tbody>
        </table>
      </div>
    </section>

    <div
      v-if="formAbierto"
      class="fixed inset-0 z-40 flex items-end sm:items-center justify-center bg-black/40 p-0 sm:p-4"
      @mousedown.self="cerrarFormulario"
    >
      <form
        class="w-full sm:max-w-md rounded-t-2xl sm:rounded-xl bg-white dark:bg-gray-800 shadow-xl p-5 space-y-3"
        @submit.prevent="guardar"
      >
        <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">{{ form.ap_id ? 'Editar AP' : 'Nuevo AP' }}</h3>
        <label class="block text-sm">
          <span class="text-gray-600 dark:text-gray-300">Nodo</span>
          <select v-model="form.nodo_id" required class="mt-1 w-full py-2 px-3 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
            <option disabled value="">Seleccionar…</option>
            <option v-for="n in nodos" :key="n.nodo_id" :value="n.nodo_id">{{ n.descripcion }}</option>
          </select>
        </label>
        <label class="block text-sm">
          <span class="text-gray-600 dark:text-gray-300">Nombre</span>
          <input v-model="form.nombre" type="text" required maxlength="120" class="mt-1 w-full py-2 px-3 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
        </label>
        <label class="block text-sm">
          <span class="text-gray-600 dark:text-gray-300">IP de gestión</span>
          <input v-model="form.ip" type="text" required placeholder="10.x.x.x" class="mt-1 w-full py-2 px-3 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 font-mono text-gray-900 dark:text-gray-100">
        </label>
        <label class="block text-sm">
          <span class="text-gray-600 dark:text-gray-300">Notas</span>
          <textarea v-model="form.notas" rows="2" class="mt-1 w-full py-2 px-3 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100"></textarea>
        </label>
        <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-200">
          <input v-model="form.activo" type="checkbox" class="rounded">
          Activo (incluir en ping automático)
        </label>
        <p v-if="errorForm" class="text-sm text-rose-600">{{ errorForm }}</p>
        <div class="flex justify-end gap-2 pt-1">
          <button type="button" class="px-3 py-2 rounded-lg text-sm text-gray-600 dark:text-gray-300" @click="cerrarFormulario">Cancelar</button>
          <button type="submit" class="px-3 py-2 rounded-lg bg-blue-600 text-white text-sm disabled:opacity-50" :disabled="guardando">
            {{ guardando ? 'Guardando…' : 'Guardar' }}
          </button>
        </div>
      </form>
    </div>
    <Teleport to="body">
      <div
        v-if="apMenu && menuPos"
        ref="menuAccionesEl"
        class="fixed z-[9999] min-w-[10.5rem] py-1 rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-lg"
        :style="{ top: menuPos.top + 'px', left: menuPos.left + 'px' }"
      >
        <button
          type="button"
          class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-cyan-700 dark:text-cyan-300 hover:bg-gray-50 dark:hover:bg-gray-700 disabled:opacity-50"
          :disabled="ocupadoId === apMenu.ap_id"
            @click="elegirMenu((ap) => pingUno(ap))"
        ><svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>Ping</button>
        <button
          type="button"
          class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-violet-700 dark:text-violet-300 hover:bg-gray-50 dark:hover:bg-gray-700 disabled:opacity-50"
          :disabled="ocupadoId === apMenu.ap_id"
          @click="elegirMenu((ap) => sshUno(ap))"
        ><svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l3 3-3 3M13 15h3"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16v12H4z"/></svg>SSH</button>
        <button
          type="button"
          class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700"
          @click="elegirMenu((ap) => toggleDetalle(ap.ap_id))"
        ><svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>{{ detalleId === apMenu.ap_id ? 'Ocultar' : 'Datos' }}</button>
        <button
          v-if="canEditar"
          type="button"
          class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700"
          @click="elegirMenu((ap) => abrirFormulario(ap))"
        ><svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>Editar</button>
        <button
          v-if="canEliminar"
          type="button"
          class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-rose-600 dark:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-900/30"
          @click="elegirMenu((ap) => eliminar(ap))"
        ><svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>Borrar</button>
      </div>
    </Teleport>
  </div>
</template>

<script setup>
import { computed, nextTick, onMounted, onUnmounted, reactive, ref } from 'vue';
import Espectro5GhzNodo from '@/components/Espectro5GhzNodo.vue';
import { colorAp } from '@/espectro5ghz';
import { formatearCaidoHace } from '@/monitoreo-duracion';

const props = defineProps({
  initialConfig: { type: Object, default: () => ({}) },
});

const aps = ref(Array.isArray(props.initialConfig.aps) ? props.initialConfig.aps : []);
const nodos = ref(Array.isArray(props.initialConfig.nodos) ? props.initialConfig.nodos : []);
const urlBase = (props.initialConfig.urlBase || '/sistema/aps-wireless').replace(/\/$/, '');
const canCrear = !!props.initialConfig.canCrear;
const canEditar = !!props.initialConfig.canEditar;
const canEliminar = !!props.initialConfig.canEliminar;
const urlAvisos = props.initialConfig.urlAvisos || '';

const busqueda = ref('');
const filtroNodo = ref('');
const filtroPing = ref('');
const aviso = ref('');
const errorGlobal = ref('');
const ocupadoId = ref(null);
const pingeandoTodos = ref(false);
const sshTodosActivo = ref(false);
const progresoPing = ref('Pingeando…');
const progresoSsh = ref('SSH…');
const detalleId = ref(null);
const menuAccionesId = ref(null);
const menuPos = ref(null);
const menuAccionesEl = ref(null);
const ahora = ref(Date.now());
let tickCaido = null;
const formAbierto = ref(false);
const guardando = ref(false);
const errorForm = ref('');
const form = reactive({
  ap_id: null,
  nodo_id: '',
  nombre: '',
  ip: '',
  notas: '',
  activo: true,
});

const ocupado = computed(() => pingeandoTodos.value || sshTodosActivo.value || ocupadoId.value != null || guardando.value);
const apMenu = computed(() => aps.value.find((a) => a.ap_id === menuAccionesId.value) || null);
const apsActivos = computed(() => aps.value.filter((a) => a.activo !== false));

const resumen = computed(() => {
  let online = 0;
  let offline = 0;
  let desconocido = 0;
  for (const ap of aps.value) {
    if (ap.ping_ok === true) online += 1;
    else if (ap.ping_ok === false) offline += 1;
    else desconocido += 1;
  }
  return { online, offline, desconocido };
});

function coincidenciaPing(ap) {
  if (!filtroPing.value) return true;
  if (filtroPing.value === 'online') return ap.ping_ok === true;
  if (filtroPing.value === 'offline') return ap.ping_ok === false;
  return ap.ping_ok !== true && ap.ping_ok !== false;
}

function seleccionarFiltroPing(valor) {
  filtroPing.value = filtroPing.value === valor ? '' : valor;
}

function limpiarFiltros() {
  filtroPing.value = '';
  filtroNodo.value = '';
  busqueda.value = '';
}

const gruposFiltrados = computed(() => {
  const q = busqueda.value.trim().toLowerCase();
  const nodo = filtroNodo.value;
  const filtrados = aps.value.filter((ap) => {
    if (nodo && String(ap.nodo_id) !== String(nodo)) return false;
    if (!coincidenciaPing(ap)) return false;
    if (!q) return true;
    const blob = [ap.nombre, ap.ip, ap.ssid, ap.hostname, ap.nodo].filter(Boolean).join(' ').toLowerCase();
    return blob.includes(q);
  });
  const map = new Map();
  for (const ap of filtrados) {
    const key = ap.nodo_id ?? 0;
    if (!map.has(key)) {
      map.set(key, { nodo_id: key, nodo: ap.nodo || 'Sin nodo', aps: [] });
    }
    map.get(key).aps.push(ap);
  }
  return Array.from(map.values());
});

function contarEstado(lista, ok) {
  return lista.filter((a) => a.ping_ok === ok).length;
}

function clasePunto(ap) {
  if (ap.ping_ok === true) return 'bg-emerald-500';
  if (ap.ping_ok === false) return 'bg-rose-500';
  return 'bg-gray-400';
}

function claseTextoPing(ap) {
  if (ap.ping_ok === true) return 'text-emerald-700 dark:text-emerald-300';
  if (ap.ping_ok === false) return 'text-rose-600 dark:text-rose-400';
  return 'text-gray-500';
}

function etiquetaPing(ap) {
  if (ap.ping_ok === true) {
    return ap.ping_latencia_ms != null ? `${ap.ping_latencia_ms} ms` : 'OK';
  }
  if (ap.ping_ok === false) return 'Caído';
  return '—';
}

function caidoHace(ap) {
  return formatearCaidoHace(ap?.ping_caido_desde, ahora.value) || ap?.caido_hace || null;
}

function textoRadio(ap) {
  const partes = [];
  if (ap.frecuencia) {
    partes.push(String(ap.frecuencia) + (/mhz/i.test(String(ap.frecuencia)) ? '' : ' MHz'));
  }
  if (ap.canal) partes.push('ch ' + ap.canal);
  if (ap.chanbw) {
    partes.push(String(ap.chanbw) + (/mhz/i.test(String(ap.chanbw)) ? '' : ' MHz'));
  }
  return partes.length ? partes.join(' · ') : '—';
}

function extraVal(ap, key) {
  const extra = ap.extra || {};
  return extra[key] || '—';
}

function formatearFecha(iso) {
  if (!iso) return '—';
  const d = new Date(iso);
  if (Number.isNaN(d.getTime())) return '—';
  return d.toLocaleString();
}

function toggleDetalle(id) {
  detalleId.value = detalleId.value === id ? null : id;
}

let menuAncla = null;

function cerrarMenuAcciones() {
  menuAccionesId.value = null;
  menuPos.value = null;
  menuAncla = null;
}

function ubicarMenuAcciones() {
  const btn = menuAncla;
  if (!btn || typeof btn.getBoundingClientRect !== 'function') return;
  const rect = btn.getBoundingClientRect();
  const menu = menuAccionesEl.value;
  const ancho = menu?.offsetWidth || 168;
  const alto = menu?.offsetHeight || 0;
  const margen = 4;
  let top = rect.bottom + margen;
  if (alto > 0 && top + alto > window.innerHeight - 8) {
    top = rect.top - alto - margen;
  }
  if (top < 8) top = 8;
  let left = rect.right - ancho;
  if (left < 8) left = 8;
  if (left + ancho > window.innerWidth - 8) left = Math.max(8, window.innerWidth - ancho - 8);
  menuPos.value = { top, left };
}

function toggleMenuAcciones(id, ev) {
  if (menuAccionesId.value === id) {
    cerrarMenuAcciones();
    return;
  }
  const origen = ev?.target?.closest?.('[data-menu-ap]')
    || ev?.currentTarget?.closest?.('[data-menu-ap]')
    || ev?.currentTarget;
  menuAncla = origen && typeof origen.getBoundingClientRect === 'function' ? origen : null;
  menuAccionesId.value = id;
  ubicarMenuAcciones();
  nextTick(() => ubicarMenuAcciones());
}

function elegirMenu(accion) {
  const ap = apMenu.value;
  cerrarMenuAcciones();
  if (ap) accion(ap);
}

function onClickFueraMenu(ev) {
  if (menuAccionesId.value == null) return;
  if (ev.target?.closest?.('[data-menu-ap]')) return;
  if (menuAccionesEl.value?.contains(ev.target)) return;
  cerrarMenuAcciones();
}

function onScrollMenu() {
  if (menuAccionesId.value != null) cerrarMenuAcciones();
}

function reemplazarAp(actualizado) {
  if (!actualizado || !actualizado.ap_id) return;
  const i = aps.value.findIndex((a) => a.ap_id === actualizado.ap_id);
  if (i >= 0) aps.value.splice(i, 1, actualizado);
  else aps.value.push(actualizado);
}

function mensajeAxios(err, fallback) {
  const data = err?.response?.data;
  if (data?.message) return data.message;
  if (data?.errors) {
    return Object.values(data.errors).flat().join(' ');
  }
  return fallback;
}

async function pingUno(ap) {
  ocupadoId.value = ap.ap_id;
  errorGlobal.value = '';
  try {
    const { data } = await window.axios.post(`${urlBase}/${ap.ap_id}/ping`);
    if (data.ap) reemplazarAp(data.ap);
  } catch (err) {
    errorGlobal.value = mensajeAxios(err, 'No se pudo hacer ping.');
  } finally {
    ocupadoId.value = null;
  }
}

async function sshUno(ap) {
  ocupadoId.value = ap.ap_id;
  errorGlobal.value = '';
  aviso.value = `Consultando SSH ${ap.nombre}…`;
  try {
    const { data } = await window.axios.post(`${urlBase}/${ap.ap_id}/ssh`);
    if (data.ap) reemplazarAp(data.ap);
    aviso.value = data.success ? `SSH OK: ${ap.nombre}` : (data.message || 'SSH con error');
    if (!data.success) errorGlobal.value = data.message || '';
  } catch (err) {
    errorGlobal.value = mensajeAxios(err, 'No se pudo consultar por SSH.');
    aviso.value = '';
  } finally {
    ocupadoId.value = null;
  }
}

async function pingTodos() {
  pingeandoTodos.value = true;
  errorGlobal.value = '';
  const lista = apsActivos.value;
  for (let i = 0; i < lista.length; i += 1) {
    progresoPing.value = `Ping ${i + 1}/${lista.length}`;
    await pingUno(lista[i]);
  }
  pingeandoTodos.value = false;
  progresoPing.value = 'Pingeando…';
  aviso.value = 'Ping de todos los APs activos terminado.';
}

async function sshTodos() {
  sshTodosActivo.value = true;
  errorGlobal.value = '';
  const lista = apsActivos.value;
  for (let i = 0; i < lista.length; i += 1) {
    progresoSsh.value = `SSH ${i + 1}/${lista.length}`;
    await sshUno(lista[i]);
  }
  sshTodosActivo.value = false;
  progresoSsh.value = 'SSH…';
  aviso.value = 'Consulta SSH de todos los APs activos terminada.';
}

function abrirFormulario(ap) {
  errorForm.value = '';
  if (ap) {
    form.ap_id = ap.ap_id;
    form.nodo_id = ap.nodo_id;
    form.nombre = ap.nombre;
    form.ip = ap.ip;
    form.notas = ap.notas || '';
    form.activo = ap.activo !== false;
  } else {
    form.ap_id = null;
    form.nodo_id = nodos.value[0]?.nodo_id || '';
    form.nombre = '';
    form.ip = '';
    form.notas = '';
    form.activo = true;
  }
  formAbierto.value = true;
}

function cerrarFormulario() {
  formAbierto.value = false;
}

async function guardar() {
  guardando.value = true;
  errorForm.value = '';
  const payload = {
    nodo_id: form.nodo_id,
    nombre: form.nombre,
    ip: form.ip,
    notas: form.notas || null,
    activo: !!form.activo,
  };
  try {
    const req = form.ap_id
      ? window.axios.put(`${urlBase}/${form.ap_id}`, payload)
      : window.axios.post(urlBase, payload);
    const { data } = await req;
    if (data.ap) reemplazarAp(data.ap);
    aviso.value = data.message || 'Guardado.';
    cerrarFormulario();
  } catch (err) {
    errorForm.value = mensajeAxios(err, 'No se pudo guardar.');
  } finally {
    guardando.value = false;
  }
}

async function eliminar(ap) {
  if (!window.confirm(`¿Eliminar el AP «${ap.nombre}» (${ap.ip})?`)) return;
  errorGlobal.value = '';
  try {
    await window.axios.delete(`${urlBase}/${ap.ap_id}`);
    aps.value = aps.value.filter((a) => a.ap_id !== ap.ap_id);
    aviso.value = 'AP eliminado.';
  } catch (err) {
    errorGlobal.value = mensajeAxios(err, 'No se pudo eliminar.');
  }
}

onMounted(() => {
  tickCaido = setInterval(() => {
    ahora.value = Date.now();
  }, 30000);
  document.addEventListener('click', onClickFueraMenu);
  window.addEventListener('scroll', onScrollMenu, true);
  window.addEventListener('resize', onScrollMenu);
});

onUnmounted(() => {
  if (tickCaido) clearInterval(tickCaido);
  document.removeEventListener('click', onClickFueraMenu);
  window.removeEventListener('scroll', onScrollMenu, true);
  window.removeEventListener('resize', onScrollMenu);
});
</script>
