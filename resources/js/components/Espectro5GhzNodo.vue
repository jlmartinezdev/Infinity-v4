<template>
  <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700 bg-gray-50/80 dark:bg-gray-900/40">
    <div class="flex flex-wrap items-baseline justify-between gap-2 mb-2">
      <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
        Espectro 5 GHz
      </h3>
      <p v-if="bloques.length" class="text-xs text-gray-600 dark:text-gray-300">
        Libre <span class="font-semibold text-emerald-700 dark:text-emerald-300">{{ resumen.mhzLibre }} MHz</span>
        ({{ resumen.pctLibre }}%)
        · ocupado {{ resumen.mhzOcupado }} MHz
      </p>
    </div>

    <p v-if="!bloques.length" class="text-xs text-gray-500 dark:text-gray-400">
      Sin frecuencia 5 GHz. Consultá SSH en los APs de este nodo para dibujar el canal.
    </p>

    <template v-else>
      <div class="relative mb-1 h-4 text-[10px] text-gray-400 dark:text-gray-500 select-none">
        <span
          v-for="reg in regionesVisibles"
          :key="reg.nombre"
          class="absolute truncate"
          :style="{ left: pct(reg.from) + '%', width: (pct(reg.to) - pct(reg.from)) + '%' }"
        >{{ reg.nombre }}</span>
      </div>

      <div
        class="relative h-9 rounded-md overflow-hidden border border-gray-200 dark:border-gray-600"
        :title="'Banda 5150–5850 MHz'"
      >
        <div class="absolute inset-0 bg-[repeating-linear-gradient(90deg,transparent,transparent_9px,rgba(148,163,184,0.18)_10px)] dark:bg-[repeating-linear-gradient(90deg,transparent,transparent_9px,rgba(71,85,105,0.35)_10px)] bg-gray-200 dark:bg-gray-700"></div>
        <div
          v-for="reg in regionesVisibles"
          :key="'bg-' + reg.nombre"
          class="absolute top-0 bottom-0 border-l border-white/40 dark:border-black/20"
          :style="{ left: pct(reg.from) + '%', width: (pct(reg.to) - pct(reg.from)) + '%' }"
        ></div>
        <div
          v-for="(b, idx) in bloques"
          :key="'occ-' + b.ap.ap_id + '-' + idx"
          class="absolute top-1 bottom-1 rounded-sm shadow-sm cursor-default"
          :style="{
            left: b.left + '%',
            width: Math.max(b.width, 0.6) + '%',
            background: b.color,
            opacity: 0.92,
          }"
          :title="tituloBloque(b)"
        >
          <span
            v-if="b.width >= 8"
            class="absolute inset-0 flex items-center justify-center px-0.5 text-[10px] font-semibold text-white truncate drop-shadow"
          >{{ etiquetaCorta(b) }}</span>
        </div>
      </div>

      <div
        v-if="bloques.length"
        class="mt-2 space-y-1.5"
      >
        <div
          v-for="b in bloques"
          :key="'lane-' + b.ap.ap_id"
          class="flex items-center gap-2"
        >
          <span class="w-28 sm:w-36 shrink-0 text-[11px] text-gray-600 dark:text-gray-300 truncate" :title="b.ap.nombre">{{ b.ap.nombre }}</span>
          <div
            class="relative flex-1 h-3 rounded-sm overflow-hidden"
            style="background: #4fc3f7"
          >
            <div class="pointer-events-none absolute top-0 bottom-0 border-l" style="left: 25%; border-color: rgba(8, 47, 73, 0.28)"></div>
            <div class="pointer-events-none absolute top-0 bottom-0 border-l" style="left: 50%; border-color: rgba(8, 47, 73, 0.28)"></div>
            <div class="pointer-events-none absolute top-0 bottom-0 border-l" style="left: 75%; border-color: rgba(8, 47, 73, 0.28)"></div>
            <div
              v-for="(raya, i) in rayas24h(b)"
              :key="'raya-' + b.ap.ap_id + '-' + i"
              class="absolute top-0 bottom-0 cursor-help"
              :style="{ left: raya.left + '%', width: raya.width + '%', minWidth: '6px', background: '#ef4444' }"
              :title="tituloRayaVisible(raya)"
              @mouseenter="mostrarDuracion($event, raya)"
              @mouseleave="ocultarDuracion"
            ></div>
          </div>
          <span class="w-40 shrink-0 text-right text-[11px] font-mono text-gray-500 dark:text-gray-400">
            {{ b.center }} / {{ b.bw }}<span v-if="rayas24h(b).length" class="text-red-400"> · {{ textoCaidas(b) }}</span>
          </span>
        </div>
        <div class="flex items-center gap-2 text-[10px] text-gray-400 dark:text-gray-500">
          <span class="w-28 sm:w-36 shrink-0"></span>
          <div class="relative flex-1 h-3">
            <span class="absolute left-0">−24 h</span>
            <span class="absolute left-1/2 -translate-x-1/2">−12 h</span>
            <span class="absolute right-0">ahora</span>
          </div>
          <span class="w-40 shrink-0"></span>
        </div>
      </div>

      <div class="relative mt-1 h-4 text-[10px] font-mono text-gray-400 dark:text-gray-500">
        <span
          v-for="mhz in marcas"
          :key="mhz"
          class="absolute -translate-x-1/2"
          :style="{ left: pct(mhz) + '%' }"
        >{{ mhz }}</span>
      </div>

      <div class="mt-2 flex flex-wrap gap-2">
        <span
          v-for="b in bloques"
          :key="'leg-' + b.ap.ap_id"
          class="inline-flex items-center gap-1.5 rounded-full border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-800 px-2 py-0.5 text-[11px] text-gray-700 dark:text-gray-200"
        >
          <span class="h-2.5 w-2.5 rounded-sm shrink-0" :style="{ background: b.color }"></span>
          {{ b.ap.nombre }}
          <span class="text-gray-400">{{ b.from }}–{{ b.to }} MHz</span>
        </span>
        <span class="inline-flex items-center gap-1.5 rounded-full border border-dashed border-gray-300 dark:border-gray-600 px-2 py-0.5 text-[11px] text-gray-500 dark:text-gray-400">
          <span class="h-2.5 w-2.5 rounded-sm bg-gray-200 dark:bg-gray-600"></span>
          Libre
        </span>
      </div>

      <p
        v-if="conflictos.length"
        class="mt-2 text-[11px] text-amber-700 dark:text-amber-300"
      >
        Solape:
        <span v-for="(c, i) in conflictos" :key="c.a.ap.ap_id + '-' + c.b.ap.ap_id">
          {{ i ? ' · ' : '' }}{{ c.a.ap.nombre }} / {{ c.b.ap.nombre }} ({{ c.from }}–{{ c.to }} MHz)
        </span>
      </p>
    </template>
  </div>
  <Teleport to="body">
    <div
      v-if="rayaHover"
      class="pointer-events-none fixed z-[9999] px-2 py-1 rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-800 text-[11px] text-gray-800 dark:text-gray-100 shadow-lg"
      :style="{ left: rayaHover.x + 'px', top: (rayaHover.y - 6) + 'px', transform: 'translate(-50%, -100%)' }"
    >
      <span class="font-medium text-red-500">{{ rayaHover.duracion }}</span><span v-if="rayaHover.abierta"> · en curso</span>
      <span class="block text-gray-500 dark:text-gray-400">{{ rayaHover.rango }}</span>
    </div>
  </Teleport>
</template>

<script setup>
import { computed, ref } from 'vue';
import {
  BANDA_5G_MIN,
  BANDA_5G_SPAN,
  MARCAS_MHZ,
  REGIONES_5G,
  bloquesDeAps,
  resumenLibre,
  solapes,
} from '@/espectro5ghz';

const props = defineProps({
  aps: { type: Array, default: () => [] },
});

const bloques = computed(() => bloquesDeAps(props.aps));
const resumen = computed(() => resumenLibre(bloques.value));
const conflictos = computed(() => solapes(bloques.value));
const marcas = MARCAS_MHZ;
const regionesVisibles = REGIONES_5G;

function pct(mhz) {
  return ((mhz - BANDA_5G_MIN) / BANDA_5G_SPAN) * 100;
}

function tituloBloque(b) {
  const ssid = b.ap.ssid ? ` · ${b.ap.ssid}` : '';
  return `${b.ap.nombre}${ssid}\n${b.center} MHz · ${b.bw} MHz (${b.from}–${b.to})`;
}

function etiquetaCorta(b) {
  return String(b.center);
}

const VENTANA_MS = 24 * 60 * 60 * 1000;

function rayas24h(b) {
  const ahora = Date.now();
  const desde = ahora - VENTANA_MS;
  const lista = Array.isArray(b.ap?.caidas_24h) ? b.ap.caidas_24h : [];
  const rayas = [];
  for (const item of lista) {
    const ini = Date.parse(item.inicio);
    const fin = Date.parse(item.fin);
    if (!Number.isFinite(ini) || !Number.isFinite(fin) || fin <= desde || ini >= ahora) continue;
    const a = Math.max(ini, desde);
    const z = Math.min(fin, ahora);
    let left = ((a - desde) / VENTANA_MS) * 100;
    let width = Math.max(((z - a) / VENTANA_MS) * 100, 0.45);
    if (left + width > 100) width = Math.max(100 - left, 0.45);
    rayas.push({
      left,
      width,
      duracion: textoDuracion(z - a),
      rango: `${horaCorta(a)} – ${horaCorta(z)}`,
      abierta: item.abierta === true,
    });
  }
  return rayas;
}

function textoCaidas(b) {
  const n = rayas24h(b).length;
  if (!n) return 'sin caídas';
  return n === 1 ? '1 caída' : `${n} caídas`;
}

const rayaHover = ref(null);

function textoDuracion(ms) {
  const totalMin = Math.max(1, Math.round(ms / 60000));
  const horas = Math.floor(totalMin / 60);
  const minutos = totalMin % 60;
  if (horas > 0 && minutos > 0) return `${horas} h ${minutos} min`;
  if (horas > 0) return `${horas} h`;
  return `${totalMin} min`;
}

function tituloRayaVisible(raya) {
  return `${raya.duracion}${raya.abierta ? ' · en curso' : ''} · ${raya.rango}`;
}

function mostrarDuracion(ev, raya) {
  const rect = ev.currentTarget.getBoundingClientRect();
  rayaHover.value = {
    duracion: raya.duracion,
    rango: raya.rango,
    abierta: raya.abierta,
    x: rect.left + rect.width / 2,
    y: rect.top,
  };
}

function ocultarDuracion() {
  rayaHover.value = null;
}

function horaCorta(ms) {
  const fecha = new Date(ms);
  const dos = (n) => String(n).padStart(2, '0');
  return `${dos(fecha.getDate())}/${dos(fecha.getMonth() + 1)} ${dos(fecha.getHours())}:${dos(fecha.getMinutes())}`;
}
</script>
