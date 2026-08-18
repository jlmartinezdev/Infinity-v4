<template>
  <div class="max-w-[1600px] mx-auto">
    <div class="flex flex-col lg:flex-row lg:items-start gap-4 mb-4">
      <div class="flex-1 min-w-0">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ titulo }}</h1>
        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">{{ subtitulo }}</p>
      </div>
      <div class="flex flex-wrap items-center gap-2">
        <a
          v-if="urlAvisos"
          :href="urlAvisos"
          class="inline-flex items-center gap-2 px-3 py-2 rounded-lg border border-rose-400/60 text-sm text-rose-700 dark:text-rose-300 hover:bg-rose-50 dark:hover:bg-rose-900/20"
          title="Elegir usuarios staff que reciben WhatsApp si cae un router"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
          </svg>
          Alertas WhatsApp
        </a>
        <button
          type="button"
          class="inline-flex items-center gap-2 px-3 py-2 rounded-lg border border-rose-500/50 text-sm text-rose-700 dark:text-rose-300 hover:bg-rose-50 dark:hover:bg-rose-900/20 disabled:opacity-50"
          :disabled="notificando"
          title="Envía push FCM de prueba al topic staff simulando caída"
          @click="notificarCaidaPrueba"
        >
          <svg class="w-4 h-4" :class="{ 'animate-pulse': notificando }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
          </svg>
          {{ notificando ? 'Enviando…' : 'Probar alerta staff' }}
        </button>
        <button
          type="button"
          class="inline-flex items-center gap-2 px-3 py-2 rounded-lg border border-cyan-500/50 text-sm text-cyan-700 dark:text-cyan-300 hover:bg-cyan-50 dark:hover:bg-cyan-900/20"
          :disabled="pingeando || cargando || notificando"
          @click="ejecutarPing"
        >
          <svg class="w-4 h-4" :class="{ 'animate-spin': pingeando }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
          </svg>
          {{ pingeando ? 'Pingeando…' : 'Ping ahora' }}
        </button>
        <button
          type="button"
          class="inline-flex items-center gap-2 px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-800"
          :disabled="cargando || pingeando"
          @click="refrescar"
        >
          <svg class="w-4 h-4" :class="{ 'animate-spin': cargando }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
          </svg>
          Actualizar
        </button>
        <a
          v-if="urlRouters"
          :href="urlRouters"
          class="inline-flex items-center px-3 py-2 rounded-lg bg-purple-600 text-white text-sm font-medium hover:bg-purple-700"
        >
          Ver routers
        </a>
      </div>
    </div>

    <div
      v-if="flashMsg"
      class="mb-4 p-3 rounded-lg text-sm border"
      :class="flashOk
        ? 'bg-emerald-50 dark:bg-emerald-900/20 text-emerald-800 dark:text-emerald-200 border-emerald-200 dark:border-emerald-800'
        : 'bg-red-50 dark:bg-red-900/20 text-red-800 dark:text-red-200 border-red-200 dark:border-red-800'"
    >
      {{ flashMsg }}
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-[280px_minmax(0,1fr)] gap-4">
      <!-- Sidebar -->
      <aside class="space-y-3">
        <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-slate-900/80 p-4 shadow-sm">
          <div class="flex items-center justify-between mb-2">
            <span class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Nodos activos</span>
            <span
              class="text-[10px] font-bold uppercase px-2 py-0.5 rounded-full"
              :class="stats.salud >= 90
                ? 'bg-emerald-500/20 text-emerald-400'
                : (stats.caidos > 0 ? 'bg-rose-500/20 text-rose-400' : 'bg-amber-500/20 text-amber-400')"
            >
              {{ stats.salud >= 90 ? 'Óptimo' : (stats.caidos > 0 ? 'Alerta' : 'Atención') }}
            </span>
          </div>
          <p class="text-3xl font-bold text-gray-900 dark:text-white tabular-nums">
            {{ stats.conectados }}
            <span class="text-lg text-gray-400 font-medium">/ {{ stats.total }}</span>
          </p>
          <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">{{ stats.salud }}% de la topología en línea</p>
        </div>

        <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-slate-900/80 p-4 shadow-sm">
          <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-2">Latencia ping</p>
          <div class="grid grid-cols-2 gap-3">
            <div>
              <p class="text-[10px] uppercase text-gray-500 dark:text-gray-400">Promedio</p>
              <p class="text-xl font-bold tabular-nums" :class="latenciaTextClass(stats.latencia_promedio_ms)">
                {{ formatLatencia(stats.latencia_promedio_ms) }}
              </p>
            </div>
            <div>
              <p class="text-[10px] uppercase text-gray-500 dark:text-gray-400">Máxima</p>
              <p class="text-xl font-bold tabular-nums" :class="latenciaTextClass(stats.latencia_max_ms)">
                {{ formatLatencia(stats.latencia_max_ms) }}
              </p>
            </div>
          </div>
          <p class="text-[11px] text-gray-500 dark:text-gray-400 mt-2">Usá «Ping ahora» para medir ICMP en vivo.</p>
        </div>

        <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-slate-900/80 p-4 shadow-sm">
          <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-2">Clientes (ping)</p>
          <p class="text-3xl font-bold text-gray-900 dark:text-white tabular-nums">
            {{ stats.clientes_online ?? 0 }}
            <span class="text-lg text-gray-400 font-medium">/ {{ stats.clientes_activos ?? 0 }}</span>
          </p>
          <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">En línea / servicios activos en la topología</p>
        </div>

        <a
          v-if="ispFailover && ispFailover.enabled"
          :href="urlIspFailover || '/sistema/isp-failover'"
          class="block rounded-xl border p-4 shadow-sm hover:opacity-95"
          :class="ispFailover.modo === 'failover'
            ? 'border-amber-400 bg-amber-50 dark:bg-amber-900/25 dark:border-amber-700'
            : 'border-emerald-200 bg-white dark:bg-slate-900/80 dark:border-gray-700'"
        >
          <p class="text-xs font-medium uppercase tracking-wide mb-1"
            :class="ispFailover.modo === 'failover' ? 'text-amber-700 dark:text-amber-300' : 'text-gray-500 dark:text-gray-400'">
            Salida ISP
          </p>
          <p class="text-lg font-bold text-gray-900 dark:text-white">
            {{ ispFailover.modo === 'failover' ? (ispFailover.isp2_nombre || 'ISP 2') : (ispFailover.isp1_nombre || 'ISP 1') }}
          </p>
          <p class="text-xs mt-1" :class="ispFailover.ping_ok === false ? 'text-rose-600 dark:text-rose-400' : 'text-gray-500 dark:text-gray-400'">
            Ping {{ ispFailover.ping_host || '1.1.1.1' }}:
            <template v-if="ispFailover.ping_ok === true">OK{{ ispFailover.latency_ms != null ? ' ' + ispFailover.latency_ms + ' ms' : '' }}</template>
            <template v-else-if="ispFailover.ping_ok === false">sin respuesta</template>
            <template v-else>sin chequeo</template>
          </p>
        </a>

        <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-slate-900/80 p-4 shadow-sm">
          <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-3">Alertas</p>
          <div v-if="alertas.length === 0" class="text-sm text-emerald-600 dark:text-emerald-400">Sin alertas activas</div>
          <ul v-else class="space-y-2">
            <li v-for="a in alertas" :key="a.id" class="flex items-start gap-2 text-sm">
              <span class="mt-1.5 w-2 h-2 rounded-full shrink-0" :class="a.dot"></span>
              <div>
                <p class="text-gray-900 dark:text-gray-100 font-medium">{{ a.titulo }}</p>
                <p class="text-xs text-gray-500 dark:text-gray-400">{{ a.detalle }}</p>
              </div>
            </li>
          </ul>
        </div>

        <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-slate-900/80 p-4 shadow-sm">
          <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-2">Buscar nodo</p>
          <input
            v-model="buscar"
            type="search"
            placeholder="Nombre o IP…"
            class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-slate-800 text-sm text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-purple-500/30 focus:border-purple-500"
          />
          <ul class="mt-3 max-h-56 overflow-y-auto space-y-1">
            <li
              v-for="n in nodosFiltrados"
              :key="n.id"
              class="flex items-center justify-between gap-2 px-2 py-1.5 rounded-lg cursor-pointer text-sm hover:bg-gray-100 dark:hover:bg-slate-800"
              :class="seleccionado === n.id ? 'bg-purple-50 dark:bg-purple-900/30 ring-1 ring-purple-400/40' : ''"
              @click="seleccionar(n.id)"
            >
              <span class="truncate text-gray-800 dark:text-gray-200">{{ n.nombre }}</span>
              <span class="flex items-center gap-1.5 shrink-0">
                <span v-if="(n.clientes_activos || 0) > 0" class="text-[10px] tabular-nums text-sky-600 dark:text-sky-400 font-medium">{{ n.clientes_online || 0 }}/{{ n.clientes_activos }}</span>
                <span v-if="n.latencia_ms != null" class="text-[10px] tabular-nums font-medium" :class="latenciaTextClass(n.latencia_ms)">{{ n.latencia_ms }}ms</span>
                <span class="w-2 h-2 rounded-full" :class="statusDot(n.status)"></span>
              </span>
            </li>
          </ul>
        </div>
      </aside>

      <!-- Canvas -->
      <div
        ref="canvasWrap"
        class="relative rounded-xl border border-gray-200 dark:border-slate-700 bg-slate-100 dark:bg-[#0b1220] overflow-hidden shadow-sm min-h-[640px]"
      >
        <div class="absolute inset-0 opacity-[0.35] dark:opacity-100 pointer-events-none"
          style="background-image: radial-gradient(rgba(148,163,184,0.18) 1px, transparent 1px); background-size: 24px 24px;"
        ></div>

        <div class="absolute top-3 left-3 z-10 flex gap-1">
          <button type="button" class="w-8 h-8 inline-flex items-center justify-center rounded-lg bg-slate-900/80 text-white border border-slate-600 hover:bg-slate-800 text-lg leading-none shadow" title="Acercar" @click="zoomIn">+</button>
          <button type="button" class="w-8 h-8 inline-flex items-center justify-center rounded-lg bg-slate-900/80 text-white border border-slate-600 hover:bg-slate-800 text-lg leading-none shadow" title="Alejar" @click="zoomOut">−</button>
          <button type="button" class="h-8 px-2 inline-flex items-center justify-center rounded-lg bg-slate-900/80 text-white border border-slate-600 hover:bg-slate-800 text-xs leading-none shadow" title="Restablecer" @click="resetView">⟲</button>
        </div>

        <div class="absolute top-3 right-3 z-10 flex gap-2 text-[10px] uppercase tracking-wide font-semibold">
          <span class="px-2 py-1 rounded-md bg-slate-900/70 text-emerald-300 border border-emerald-500/30">● Online</span>
          <span class="px-2 py-1 rounded-md bg-slate-900/70 text-rose-300 border border-rose-500/30">● Offline</span>
          <span class="px-2 py-1 rounded-md bg-slate-900/70 text-amber-300 border border-amber-500/30">● Desconocido</span>
        </div>

        <svg
          class="relative w-full h-full min-h-[640px] select-none"
          :viewBox="`${view.x} ${view.y} ${view.w} ${view.h}`"
          @wheel.prevent="onWheel"
          @mousedown="onPanStart"
          @mousemove="onPanMove"
          @mouseup="onPanEnd"
          @mouseleave="onPanEnd"
        >
          <defs>
            <filter id="glow" x="-50%" y="-50%" width="200%" height="200%">
              <feGaussianBlur stdDeviation="2.5" result="coloredBlur" />
              <feMerge>
                <feMergeNode in="coloredBlur" />
                <feMergeNode in="SourceGraphic" />
              </feMerge>
            </filter>
            <linearGradient id="linkGrad" x1="0%" y1="0%" x2="100%" y2="0%">
              <stop offset="0%" stop-color="#22d3ee" stop-opacity="0.35" />
              <stop offset="100%" stop-color="#a78bfa" stop-opacity="0.55" />
            </linearGradient>
          </defs>

          <!-- Links -->
          <g>
            <path
              v-for="link in paths"
              :key="link.id"
              :id="'path-' + link.id"
              :d="link.d"
              fill="none"
              :stroke="link.status === 'up' ? 'url(#linkGrad)' : '#64748b'"
              :stroke-width="link.status === 'up' ? 2.2 : 1.6"
              :stroke-dasharray="link.status === 'up' ? 'none' : '6 6'"
              stroke-linecap="round"
              opacity="0.9"
            />
          </g>

          <!-- Packet flow dots -->
          <g v-if="animarPaquetes">
            <template v-for="link in paths.filter(p => p.status === 'up')" :key="'pkt-' + link.id">
              <circle r="3.5" :fill="link.packetColor" filter="url(#glow)" opacity="0.95">
                <animateMotion :dur="link.dur" repeatCount="indefinite" :begin="link.begin">
                  <mpath :href="'#path-' + link.id" />
                </animateMotion>
              </circle>
              <circle r="2.5" :fill="link.packetColor2" filter="url(#glow)" opacity="0.85">
                <animateMotion :dur="link.dur" repeatCount="indefinite" :begin="link.begin2">
                  <mpath :href="'#path-' + link.id" />
                </animateMotion>
              </circle>
            </template>
          </g>

          <!-- Nodes -->
          <g
            v-for="n in nodos"
            :key="n.id"
            class="cursor-pointer"
            @click.stop="seleccionar(n.id)"
          >
            <rect
              :x="n.x - cardW / 2"
              :y="n.y - cardH / 2"
              :width="cardW"
              :height="cardH"
              rx="12"
              ry="12"
              :fill="nodeFill(n)"
              :stroke="seleccionado === n.id ? '#c084fc' : nodeStroke(n)"
              :stroke-width="seleccionado === n.id ? 2.5 : 1.5"
              :stroke-dasharray="n.status === 'down' ? '5 4' : 'none'"
              class="transition-opacity"
              :opacity="dimmed(n) ? 0.35 : 1"
            />
            <!-- Icon box -->
            <rect
              :x="n.x - cardW / 2 + 10"
              :y="n.y - 14"
              width="28"
              height="28"
              rx="7"
              :fill="iconBg(n)"
            />
            <path
              :transform="`translate(${n.x - cardW / 2 + 15}, ${n.y - 9}) scale(0.75)`"
              fill="none"
              stroke="currentColor"
              stroke-width="2"
              stroke-linecap="round"
              stroke-linejoin="round"
              class="text-white"
              d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2m-2-4h.01M17 16h.01"
              style="stroke: white"
            />
            <text
              :x="n.x - cardW / 2 + 46"
              :y="n.y - 10"
              fill="#f1f5f9"
              style="font: 600 11px ui-sans-serif, system-ui, sans-serif"
            >{{ n.nombre }}</text>
            <text
              :x="n.x - cardW / 2 + 46"
              :y="n.y + 4"
              fill="#94a3b8"
              style="font: 500 9px ui-monospace, monospace"
            >{{ n.ip || 'sin IP' }}</text>
            <text
              :x="n.x - cardW / 2 + 46"
              :y="n.y + 18"
              :fill="latenciaSvgColor(n)"
              style="font: 700 10px ui-monospace, monospace"
            >{{ latenciaLabel(n) }}</text>
            <text
              :x="n.x - cardW / 2 + 46"
              :y="n.y + 32"
              :fill="clientesSvgColor(n)"
              style="font: 600 9px ui-sans-serif, system-ui, sans-serif"
            >{{ clientesLabel(n) }}</text>
            <circle
              :cx="n.x + cardW / 2 - 14"
              :cy="n.y - cardH / 2 + 14"
              r="5"
              :fill="statusColor(n.status)"
              filter="url(#glow)"
            />
            <text
              v-if="n.rol === 'core'"
              :x="n.x"
              :y="n.y + cardH / 2 + 14"
              text-anchor="middle"
              fill="#c084fc"
              style="font: 600 9px ui-sans-serif, system-ui, sans-serif; letter-spacing: 0.08em"
            >CORE / BORDE</text>
          </g>
        </svg>

        <!-- Detail panel -->
        <div
          v-if="nodoSeleccionado"
          class="absolute bottom-3 left-3 right-3 sm:left-auto sm:right-3 sm:w-80 rounded-xl border border-gray-200 dark:border-slate-600 bg-white/95 dark:bg-slate-900/95 backdrop-blur p-4 shadow-lg z-20"
        >
          <div class="flex items-start justify-between gap-2">
            <div>
              <p class="font-semibold text-gray-900 dark:text-white">{{ nodoSeleccionado.nombre }}</p>
              <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">{{ nodoSeleccionado.nodo || 'Sin nodo' }} · {{ nodoSeleccionado.modelo || '—' }}</p>
            </div>
            <button type="button" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200" @click="seleccionado = null">✕</button>
          </div>
          <dl class="mt-3 grid grid-cols-2 gap-2 text-xs">
            <div>
              <dt class="text-gray-500 dark:text-gray-400">Estado</dt>
              <dd class="font-medium" :class="statusTextClass(nodoSeleccionado.status)">{{ estadoLabel(nodoSeleccionado) }}</dd>
            </div>
            <div>
              <dt class="text-gray-500 dark:text-gray-400">IP</dt>
              <dd class="font-mono text-gray-900 dark:text-gray-100">{{ nodoSeleccionado.ip || '—' }}</dd>
            </div>
            <div>
              <dt class="text-gray-500 dark:text-gray-400">Latencia</dt>
              <dd class="font-mono font-semibold" :class="latenciaTextClass(nodoSeleccionado.latencia_ms)">{{ formatLatencia(nodoSeleccionado.latencia_ms) }}</dd>
            </div>
            <div>
              <dt class="text-gray-500 dark:text-gray-400">Último ping</dt>
              <dd class="text-gray-900 dark:text-gray-100">{{ nodoSeleccionado.ping_at || '—' }}</dd>
            </div>
            <div>
              <dt class="text-gray-500 dark:text-gray-400">Clientes online</dt>
              <dd class="font-semibold tabular-nums" :class="clientesTextClass(nodoSeleccionado)">
                {{ nodoSeleccionado.clientes_online ?? 0 }} / {{ nodoSeleccionado.clientes_activos ?? 0 }}
              </dd>
            </div>
            <div>
              <dt class="text-gray-500 dark:text-gray-400">Sin respuesta / sin dato</dt>
              <dd class="tabular-nums text-gray-900 dark:text-gray-100">
                {{ nodoSeleccionado.clientes_offline ?? 0 }} / {{ nodoSeleccionado.clientes_sin_dato ?? 0 }}
              </dd>
            </div>
            <div>
              <dt class="text-gray-500 dark:text-gray-400">Loopback</dt>
              <dd class="font-mono text-gray-900 dark:text-gray-100">{{ nodoSeleccionado.ip_loopback || '—' }}</dd>
            </div>
            <div>
              <dt class="text-gray-500 dark:text-gray-400">Rol</dt>
              <dd class="capitalize text-gray-900 dark:text-gray-100">{{ nodoSeleccionado.rol }}</dd>
            </div>
          </dl>
          <p class="mt-2 text-[11px] text-gray-500 dark:text-gray-400">Clientes = servicios activos del pool del router · online según ping ICMP.</p>
          <p v-if="!nodoSeleccionado.en_bd" class="mt-2 text-xs text-amber-600 dark:text-amber-400">No encontrado en la base de datos (solo en topología).</p>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import axios from 'axios';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';

export default {
  name: 'RedMonitoreoTopology',
  props: {
    initialConfig: { type: Object, default: () => ({}) },
  },
  setup(props) {
    const cardW = 188;
    const cardH = 82;
    const nodos = ref([...(props.initialConfig.nodos || [])]);
    const enlaces = ref([...(props.initialConfig.enlaces || [])]);
    const stats = ref({
      ...(props.initialConfig.stats || {
        total: 0,
        conectados: 0,
        caidos: 0,
        desconocidos: 0,
        salud: 0,
        latencia_promedio_ms: null,
        latencia_max_ms: null,
        clientes_online: 0,
        clientes_activos: 0,
      }),
    });
    const titulo = ref(props.initialConfig.titulo || 'Monitoreo de red');
    const subtitulo = ref(props.initialConfig.subtitulo || '');
    const ispFailover = ref(props.initialConfig.isp_failover || null);
    const urlIspFailover = ref(props.initialConfig.urlIspFailover || '');
    const urlDatos = ref(props.initialConfig.urlDatos || '');
    const urlPing = ref(props.initialConfig.urlPing || '');
    const urlNotificarCaida = ref(props.initialConfig.urlNotificarCaida || '');
    const urlAvisos = ref(props.initialConfig.urlAvisos || '');
    const urlRouters = ref(props.initialConfig.urlRouters || '');
    const csrfToken = ref(props.initialConfig.csrfToken || '');
    const buscar = ref('');
    const seleccionado = ref(null);
    const cargando = ref(false);
    const pingeando = ref(false);
    const notificando = ref(false);
    const flashMsg = ref('');
    const flashOk = ref(true);
    const animarPaquetes = ref(true);
    const view = ref({ x: 0, y: 0, w: 1200, h: 720 });
    const panning = ref(false);
    const panOrigin = ref({ x: 0, y: 0, vx: 0, vy: 0 });

    const byId = computed(() => {
      const m = {};
      for (const n of nodos.value) m[n.id] = n;
      return m;
    });

    const paths = computed(() => {
      const colors = ['#34d399', '#22d3ee', '#a78bfa', '#fbbf24', '#60a5fa'];
      return enlaces.value.map((link, i) => {
        const a = byId.value[link.from];
        const b = byId.value[link.to];
        if (!a || !b) return null;
        const midY = (a.y + b.y) / 2;
        const d = `M ${a.x} ${a.y + cardH / 2} C ${a.x} ${midY}, ${b.x} ${midY}, ${b.x} ${b.y - cardH / 2}`;
        return {
          id: link.id,
          d,
          status: link.status,
          dur: `${2.8 + (i % 4) * 0.45}s`,
          begin: `${(i * 0.35) % 2}s`,
          begin2: `${((i * 0.35) + 1.1) % 2.5}s`,
          packetColor: colors[i % colors.length],
          packetColor2: colors[(i + 2) % colors.length],
        };
      }).filter(Boolean);
    });

    const nodosFiltrados = computed(() => {
      const q = buscar.value.trim().toLowerCase();
      if (!q) return nodos.value;
      return nodos.value.filter((n) =>
        (n.nombre || '').toLowerCase().includes(q)
        || (n.ip || '').toLowerCase().includes(q)
        || (n.nodo || '').toLowerCase().includes(q)
      );
    });

    const nodoSeleccionado = computed(() => (seleccionado.value ? byId.value[seleccionado.value] : null));

    const alertas = computed(() => {
      const list = [];
      for (const n of nodos.value) {
        if (n.status === 'down') {
          list.push({
            id: 'down-' + n.id,
            titulo: n.nombre + ' offline',
            detalle: n.ip || 'Sin IP',
            dot: 'bg-rose-500',
          });
        } else if (n.latencia_ms != null && n.latencia_ms >= 100) {
          list.push({
            id: 'lat-' + n.id,
            titulo: n.nombre + ' latencia alta',
            detalle: n.latencia_ms + ' ms',
            dot: 'bg-amber-500',
          });
        } else if (n.status === 'unknown' || !n.en_bd) {
          list.push({
            id: 'unk-' + n.id,
            titulo: n.nombre + ' sin estado',
            detalle: n.en_bd ? 'Estado desconocido' : 'No está en BD',
            dot: 'bg-amber-500',
          });
        }
      }
      for (const l of enlaces.value) {
        if (l.status === 'down') {
          list.push({
            id: 'link-' + l.id,
            titulo: 'Enlace caído',
            detalle: `${l.from} ↔ ${l.to}`,
            dot: 'bg-orange-500',
          });
        }
      }
      const isp = ispFailover.value;
      if (isp && isp.enabled && isp.modo === 'failover') {
        list.unshift({
          id: 'isp-failover',
          titulo: 'Failover ' + (isp.isp2_nombre || 'ISP 2'),
          detalle: (isp.isp1_nombre || 'ISP 1') + ' sin ping a ' + (isp.ping_host || '1.1.1.1'),
          dot: 'bg-amber-500',
        });
      }
      return list.slice(0, 8);
    });

    function statusColor(s) {
      if (s === 'ok') return '#34d399';
      if (s === 'down') return '#f43f5e';
      return '#fbbf24';
    }
    function statusDot(s) {
      if (s === 'ok') return 'bg-emerald-400';
      if (s === 'down') return 'bg-rose-500';
      return 'bg-amber-400';
    }
    function statusTextClass(s) {
      if (s === 'ok') return 'text-emerald-600 dark:text-emerald-400';
      if (s === 'down') return 'text-rose-600 dark:text-rose-400';
      return 'text-amber-600 dark:text-amber-400';
    }
    function formatLatencia(ms) {
      if (ms === null || ms === undefined || ms === '') return '—';
      return `${ms} ms`;
    }
    function latenciaTextClass(ms) {
      if (ms === null || ms === undefined) return 'text-gray-400';
      if (ms < 30) return 'text-emerald-600 dark:text-emerald-400';
      if (ms < 100) return 'text-amber-600 dark:text-amber-400';
      return 'text-rose-600 dark:text-rose-400';
    }
    function latenciaSvgColor(n) {
      if (n.status === 'down') return '#fb7185';
      if (n.latencia_ms == null) return '#64748b';
      if (n.latencia_ms < 30) return '#34d399';
      if (n.latencia_ms < 100) return '#fbbf24';
      return '#fb7185';
    }
    function latenciaLabel(n) {
      if (n.status === 'down') return 'timeout';
      if (n.latencia_ms == null) return 'sin ping';
      return `${n.latencia_ms} ms`;
    }
    function clientesLabel(n) {
      const activos = n.clientes_activos || 0;
      const online = n.clientes_online || 0;
      if (activos <= 0) return '0 clientes';
      return `${online}/${activos} clientes`;
    }
    function clientesSvgColor(n) {
      const activos = n.clientes_activos || 0;
      const online = n.clientes_online || 0;
      if (activos <= 0) return '#64748b';
      if (online === 0) return '#fb7185';
      if (online >= activos) return '#34d399';
      return '#38bdf8';
    }
    function clientesTextClass(n) {
      const activos = n.clientes_activos || 0;
      const online = n.clientes_online || 0;
      if (activos <= 0) return 'text-gray-400';
      if (online === 0) return 'text-rose-600 dark:text-rose-400';
      if (online >= activos) return 'text-emerald-600 dark:text-emerald-400';
      return 'text-sky-600 dark:text-sky-400';
    }
    function estadoLabel(n) {
      if (n.status === 'ok') return 'Conectado';
      if (n.status === 'down') return 'Desconectado';
      return 'Desconocido';
    }
    function nodeFill(n) {
      if (n.status === 'down') return 'rgba(127, 29, 29, 0.45)';
      if (n.rol === 'core') return 'rgba(30, 41, 59, 0.95)';
      return 'rgba(15, 23, 42, 0.92)';
    }
    function nodeStroke(n) {
      if (n.status === 'down') return '#fb7185';
      if (n.status === 'ok') return '#334155';
      return '#a16207';
    }
    function iconBg(n) {
      if (n.status === 'down') return '#be123c';
      if (n.rol === 'core') return '#7c3aed';
      return '#0ea5e9';
    }
    function dimmed(n) {
      const q = buscar.value.trim().toLowerCase();
      if (!q) return false;
      return !((n.nombre || '').toLowerCase().includes(q) || (n.ip || '').toLowerCase().includes(q));
    }

    function seleccionar(id) {
      seleccionado.value = seleccionado.value === id ? null : id;
    }

    function aplicarPayload(data) {
      if (!data) return;
      nodos.value = data.nodos || [];
      enlaces.value = data.enlaces || [];
      stats.value = data.stats || stats.value;
      if (data.titulo) titulo.value = data.titulo;
      if (data.subtitulo) subtitulo.value = data.subtitulo;
      if (data.isp_failover) ispFailover.value = data.isp_failover;
    }

    async function refrescar() {
      if (!urlDatos.value || cargando.value || pingeando.value) return;
      cargando.value = true;
      try {
        const { data } = await axios.get(urlDatos.value, { headers: { Accept: 'application/json' } });
        aplicarPayload(data);
      } catch (_) {
        /* ignore */
      } finally {
        cargando.value = false;
      }
    }

    async function ejecutarPing() {
      if (!urlPing.value || pingeando.value) return;
      pingeando.value = true;
      try {
        const { data } = await axios.post(
          urlPing.value,
          {},
          {
            headers: {
              Accept: 'application/json',
              'X-CSRF-TOKEN': csrfToken.value,
            },
          }
        );
        aplicarPayload(data);
      } catch (_) {
        /* ignore */
      } finally {
        pingeando.value = false;
      }
    }

    async function notificarCaidaPrueba() {
      if (notificando.value) return;

      const endpoint = urlNotificarCaida.value || '/sistema/red-monitoreo/notificar-caida';
      const n = nodoSeleccionado.value;
      const etiqueta = n ? n.nombre : 'nodo sugerido (caído o core)';

      if (!window.confirm(`¿Enviar push de prueba de caída de red a la app staff?\n\nRouter: ${etiqueta}`)) {
        return;
      }

      notificando.value = true;
      flashMsg.value = '';
      flashOk.value = true;

      try {
        const body = {};
        if (n && n.router_id) body.router_id = n.router_id;
        else if (n && n.nombre) body.router_nombre = n.nombre;

        const token = csrfToken.value
          || document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
          || '';

        const { data } = await axios.post(endpoint, body, {
          headers: {
            Accept: 'application/json',
            'X-CSRF-TOKEN': token,
            'X-Requested-With': 'XMLHttpRequest',
          },
        });

        flashOk.value = !!data.success;
        flashMsg.value = data.message || (data.success ? 'Notificación enviada.' : 'Error al enviar.');
      } catch (err) {
        flashOk.value = false;
        const msg = err.response?.data?.message
          || err.response?.data?.error
          || err.message
          || 'No se pudo enviar la notificación de prueba.';
        flashMsg.value = msg;
        console.error('[red-monitoreo] notificarCaidaPrueba', err);
      } finally {
        notificando.value = false;
      }
    }

    function zoomIn() {
      view.value = {
        ...view.value,
        w: Math.max(400, view.value.w * 0.85),
        h: Math.max(240, view.value.h * 0.85),
      };
    }
    function zoomOut() {
      view.value = {
        ...view.value,
        w: Math.min(2400, view.value.w * 1.15),
        h: Math.min(1440, view.value.h * 1.15),
      };
    }
    function resetView() {
      view.value = { x: 0, y: 0, w: 1200, h: 720 };
    }
    function onWheel(e) {
      if (e.deltaY < 0) zoomIn();
      else zoomOut();
    }
    function onPanStart(e) {
      if (e.button !== 0) return;
      panning.value = true;
      panOrigin.value = { x: e.clientX, y: e.clientY, vx: view.value.x, vy: view.value.y };
    }
    function onPanMove(e) {
      if (!panning.value) return;
      const dx = (e.clientX - panOrigin.value.x) * (view.value.w / 900);
      const dy = (e.clientY - panOrigin.value.y) * (view.value.h / 640);
      view.value = {
        ...view.value,
        x: panOrigin.value.vx - dx,
        y: panOrigin.value.vy - dy,
      };
    }
    function onPanEnd() {
      panning.value = false;
    }

    let timer = null;
    const PING_INTERVALO_MS = 60000;
    onMounted(() => {
      if (urlPing.value) {
        ejecutarPing();
        timer = setInterval(ejecutarPing, PING_INTERVALO_MS);
      } else {
        timer = setInterval(refrescar, PING_INTERVALO_MS);
      }
    });
    onUnmounted(() => {
      if (timer) clearInterval(timer);
    });

    watch(buscar, (q) => {
      if (!q) return;
      const first = nodosFiltrados.value[0];
      if (first && nodosFiltrados.value.length === 1) seleccionado.value = first.id;
    });

    return {
      cardW,
      cardH,
      nodos,
      stats,
      titulo,
      subtitulo,
      ispFailover,
      urlIspFailover,
      urlAvisos,
      urlRouters,
      buscar,
      seleccionado,
      cargando,
      pingeando,
      notificando,
      flashMsg,
      flashOk,
      animarPaquetes,
      view,
      paths,
      nodosFiltrados,
      nodoSeleccionado,
      alertas,
      statusColor,
      statusDot,
      statusTextClass,
      formatLatencia,
      latenciaTextClass,
      latenciaSvgColor,
      latenciaLabel,
      clientesLabel,
      clientesSvgColor,
      clientesTextClass,
      estadoLabel,
      nodeFill,
      nodeStroke,
      iconBg,
      dimmed,
      seleccionar,
      refrescar,
      ejecutarPing,
      notificarCaidaPrueba,
      zoomIn,
      zoomOut,
      resetView,
      onWheel,
      onPanStart,
      onPanMove,
      onPanEnd,
    };
  },
};
</script>
