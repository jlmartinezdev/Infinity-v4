<template>
  <div class="overflow-hidden rounded-2xl border border-gray-200/80 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
    <div v-if="toast" class="border-b px-4 py-2 text-sm" :class="toast.ok ? 'border-emerald-200 bg-emerald-50 text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-200' : 'border-rose-200 bg-rose-50 text-rose-800 dark:border-rose-900 dark:bg-rose-950/40 dark:text-rose-200'">
      {{ toast.text }}
      <button type="button" class="ml-2 text-xs underline opacity-70" @click="toast = null">cerrar</button>
    </div>

    <div class="flex min-h-[70vh] flex-col md:flex-row">
      <!-- Lista -->
      <aside
        class="w-full flex-col border-b border-gray-100 dark:border-gray-700/80 md:flex md:w-80 md:shrink-0 md:border-b-0 md:border-r md:max-h-[70vh]"
        :class="telActivo ? 'hidden md:flex' : 'flex'"
      >
        <div class="border-b border-gray-100 p-3 dark:border-gray-700/80">
          <input
            v-model="buscar"
            type="search"
            placeholder="Buscar chat…"
            class="w-full rounded-xl border-0 bg-gray-50 px-3 py-2 text-sm text-gray-900 ring-1 ring-gray-200 placeholder:text-gray-400 focus:ring-2 focus:ring-emerald-500 dark:bg-gray-900/40 dark:text-gray-100 dark:ring-gray-600"
            @input="onBuscarInput"
          >
          <div class="mt-2 flex flex-wrap gap-1.5">
            <button
              type="button"
              class="rounded-full px-2 py-0.5 text-[10px] font-medium"
              :class="filtroAsuntoId === null ? 'bg-emerald-600 text-white' : 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300'"
              @click="filtrarAsunto(null)"
            >Todos</button>
            <button
              type="button"
              class="rounded-full px-2 py-0.5 text-[10px] font-medium"
              :class="filtroAsuntoId === 0 ? 'bg-slate-600 text-white' : 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300'"
              @click="filtrarAsunto(0)"
            >Sin asunto</button>
            <button
              v-for="a in asuntos"
              :key="a.id"
              type="button"
              class="rounded-full px-2 py-0.5 text-[10px] font-medium text-white"
              :style="{ backgroundColor: filtroAsuntoId === a.id ? a.color : undefined, opacity: filtroAsuntoId === a.id ? 1 : 0.85 }"
              :class="filtroAsuntoId === a.id ? '' : 'bg-gray-100 !text-gray-700 dark:bg-gray-700 dark:!text-gray-200'"
              @click="filtrarAsunto(a.id)"
            >{{ a.nombre }}</button>
          </div>
          <div class="mt-2 flex flex-wrap gap-2 text-[10px] text-gray-500 dark:text-gray-400">
            <span class="inline-flex items-center gap-1"><span class="inline-block h-2.5 w-2.5 rounded-full ring-2 ring-[#3b82f6]"></span> Staff</span>
            <span class="inline-flex items-center gap-1"><span class="inline-block h-2.5 w-2.5 rounded-full ring-2 ring-[#10b981]"></span> Cliente</span>
            <span class="inline-flex items-center gap-1"><span class="inline-block h-2.5 w-2.5 rounded-full ring-2 ring-[#f59e0b]"></span> Pedido</span>
          </div>
        </div>
        <div class="flex-1 overflow-y-auto md:max-h-[calc(70vh-3.5rem)]">
          <div v-if="loadingLista && !conversaciones.length" class="px-4 py-10 text-center text-sm text-gray-500">
            Cargando…
          </div>
          <button
            v-for="conv in conversaciones"
            :key="conv.telefono"
            type="button"
            class="block w-full border-b border-gray-50 px-3 py-3 text-left transition dark:border-gray-700/50"
            :class="telActivo === conv.telefono ? 'bg-emerald-50 dark:bg-emerald-950/30' : 'hover:bg-gray-50 dark:hover:bg-gray-700/30'"
            @click="seleccionar(conv.telefono)"
          >
            <div class="flex items-start gap-3">
              <div
                class="mt-0.5 flex h-10 w-10 shrink-0 items-center justify-center rounded-full text-sm font-semibold text-white shadow-sm"
                :style="avatarStyle(conv.telefono, conv.clasificacion_color)"
                :title="conv.clasificacion_label || conv.nombre || conv.telefono"
              >
                {{ iniciales(conv.nombre || conv.telefono) }}
              </div>
              <div class="min-w-0 flex-1">
                <div class="flex items-baseline justify-between gap-2">
                  <p class="truncate text-sm font-semibold text-gray-900 dark:text-gray-100">
                    {{ conv.nombre || conv.telefono }}
                  </p>
                  <div class="flex shrink-0 items-center gap-1.5">
                    <span
                      v-if="conv.asunto"
                      class="max-w-[4.5rem] truncate rounded-full px-1.5 py-0.5 text-[9px] font-semibold text-white"
                      :style="{ backgroundColor: conv.asunto.color || '#64748b' }"
                      :title="conv.asunto.nombre"
                    >{{ conv.asunto.nombre }}</span>
                    <span
                      v-if="(conv.fallidos || 0) > 0"
                      class="rounded-full bg-rose-100 px-1.5 py-0.5 text-[10px] font-semibold text-rose-700 dark:bg-rose-950/60 dark:text-rose-300"
                      title="Mensajes fallidos"
                    >{{ conv.fallidos }} ✕</span>
                    <span
                      v-if="(conv.sin_leer || 0) > 0 && telActivo !== conv.telefono"
                      class="min-w-[1.25rem] rounded-full bg-emerald-600 px-1.5 py-0.5 text-center text-[10px] font-semibold text-white"
                      title="Sin leer"
                    >{{ conv.sin_leer }}</span>
                    <span class="text-[10px] text-gray-400">{{ conv.ultimo_at_label || '' }}</span>
                  </div>
                </div>
                <p v-if="conv.nombre" class="truncate font-mono text-[11px] text-gray-400">{{ conv.telefono }}</p>
                <p class="mt-0.5 truncate text-xs text-gray-500 dark:text-gray-400">
                  <span v-if="conv.ultimo_direccion !== 'entrada'" class="text-gray-400">Vos: </span>
                  {{ truncate(conv.ultimo_cuerpo || '—', 48) }}
                </p>
              </div>
            </div>
          </button>
          <div v-if="!loadingLista && !conversaciones.length" class="px-4 py-10 text-center text-sm text-gray-500">
            Sin conversaciones.
          </div>
        </div>
      </aside>

      <!-- Hilo -->
      <section
        class="min-w-0 flex-1 flex-col bg-[#0b141a] md:max-h-[70vh]"
        :class="telActivo ? 'flex' : 'hidden md:flex'"
      >
        <template v-if="telActivo">
          <header class="flex items-center gap-3 border-b border-white/5 bg-[#202c33] px-4 py-3">
            <button type="button" class="rounded-lg p-1.5 text-gray-300 hover:bg-white/10 md:hidden" title="Volver a chats" @click="cerrarChat">
              <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </button>
            <div
              class="flex h-10 w-10 items-center justify-center rounded-full text-sm font-semibold text-white shadow-sm"
              :style="avatarStyle(telActivo, hiloMeta.clasificacion_color)"
              :title="hiloMeta.clasificacion_label || hiloMeta.nombre || telActivo"
            >
              {{ iniciales(hiloMeta.nombre || telActivo) }}
            </div>
            <div class="min-w-0 flex-1">
              <p class="truncate text-sm font-semibold text-gray-100">
                {{ hiloMeta.nombre || telActivo }}
                <span
                  v-if="hiloMeta.clasificacion_label"
                  class="ml-1.5 align-middle text-[10px] font-semibold uppercase tracking-wide"
                  :style="{ color: hiloMeta.clasificacion_color || '#94a3b8' }"
                >{{ hiloMeta.clasificacion_label }}</span>
              </p>
              <p class="truncate text-xs text-gray-400">
                <span class="font-mono">{{ telActivo }}</span>
                <template v-if="hiloMeta.cliente_id">
                  · #{{ hiloMeta.cliente_id }}
                  <template v-if="hiloMeta.cliente_nombre"> {{ hiloMeta.cliente_nombre }}</template>
                </template>
                · {{ hiloMeta.total || mensajes.length }} msgs
                <template v-if="(hiloMeta.fallidos || 0) > 0">
                  · <span class="text-rose-400">{{ hiloMeta.fallidos }} fallido(s)</span>
                </template>
              </p>
            </div>
            <a
              v-if="puedeEditar"
              :href="plantillaUrl(telActivo)"
              class="rounded-lg px-2 py-1 text-xs text-emerald-400 hover:bg-white/5"
            >Plantilla</a>
          </header>
          <div v-if="puedeEditar" class="flex items-center gap-2 border-b border-white/5 bg-[#1a242b] px-4 py-2">
            <label class="shrink-0 text-[11px] text-gray-400">Asunto</label>
            <select
              class="min-w-0 flex-1 rounded-lg border-0 bg-[#2a3942] px-2 py-1.5 text-xs text-gray-100 focus:ring-2 focus:ring-emerald-500"
              :value="hiloMeta.asunto?.id || ''"
              :disabled="guardandoAsunto"
              @change="onAsuntoChange"
            >
              <option value="">Sin asunto</option>
              <option v-for="a in asuntos" :key="a.id" :value="a.id">{{ a.nombre }}</option>
            </select>
          </div>
          <div v-else-if="hiloMeta.asunto" class="border-b border-white/5 bg-[#1a242b] px-4 py-1.5">
            <span
              class="inline-flex rounded-full px-2 py-0.5 text-[10px] font-semibold text-white"
              :style="{ backgroundColor: hiloMeta.asunto.color || '#64748b' }"
            >{{ hiloMeta.asunto.nombre }}</span>
          </div>

          <div
            ref="hiloEl"
            class="flex-1 space-y-1 overflow-y-auto px-3 py-4 sm:px-6"
            style="background-image: radial-gradient(rgba(255,255,255,0.03) 1px, transparent 1px); background-size: 18px 18px;"
          >
            <div v-if="loadingHilo && !mensajes.length" class="py-16 text-center text-sm text-gray-400">Cargando…</div>
            <template v-for="(m, idx) in mensajes" :key="m.id">
              <div v-if="mostrarDia(m, idx)" class="my-3 flex justify-center">
                <span class="rounded-lg bg-[#182229] px-3 py-1 text-[11px] text-gray-300 shadow">{{ m.dia_label }}</span>
              </div>
              <div class="flex" :class="m.direccion === 'salida' ? 'justify-end' : 'justify-start'">
                <div
                  class="max-w-[85%] rounded-lg px-2.5 py-1.5 shadow sm:max-w-[70%]"
                  :class="burbujaClass(m)"
                >
                  <div
                    v-if="m.direccion === 'salida' && m.estado === 'fallido'"
                    class="mb-1 flex items-center gap-1 text-[10px] font-semibold uppercase tracking-wide text-rose-200"
                  >
                    <span>No enviado</span>
                    <span v-if="m.fallo?.codigo" class="rounded bg-black/20 px-1 font-mono normal-case">#{{ m.fallo.codigo }}</span>
                  </div>
                  <div v-if="m.tipo !== 'text' && m.tipo !== 'unknown'" class="mb-0.5 text-[10px] uppercase tracking-wide opacity-60">
                    {{ m.tipo }}<template v-if="m.template_name"> · {{ m.template_name }}</template>
                  </div>
                  <div v-if="m.direccion === 'salida' && m.contexto_tipo === 'app_whatsapp'" class="mb-0.5 text-[10px] text-emerald-200/70">
                    Desde app WhatsApp
                  </div>

                  <div v-if="m.tipo === 'audio' && m.media_url" class="my-1 min-w-[220px]">
                    <audio
                      :src="m.media_url"
                      controls
                      preload="metadata"
                      class="w-full max-w-xs"
                      style="height: 36px;"
                    />
                    <p class="mt-0.5 text-[11px] opacity-70">{{ m.media_voice ? 'Nota de voz' : (m.cuerpo || 'Audio') }}</p>
                  </div>
                  <a
                    v-else-if="m.tipo === 'image' && m.media_url"
                    :href="m.media_url"
                    target="_blank"
                    rel="noopener"
                    class="my-1 block"
                  >
                    <img :src="m.media_url" alt="Imagen" class="max-h-56 max-w-full rounded-md object-contain" loading="lazy">
                  </a>
                  <a
                    v-else-if="['document','video','sticker'].includes(m.tipo) && m.media_url"
                    :href="m.media_url"
                    target="_blank"
                    rel="noopener"
                    class="my-1 inline-flex items-center gap-1 rounded bg-black/20 px-2 py-1 text-xs underline"
                  >
                    Abrir {{ m.tipo }}
                  </a>
                  <div v-else-if="m.tipo === 'location' && m.maps_url" class="my-1 min-w-[200px] max-w-xs">
                    <p class="text-[13.5px] font-medium leading-snug">{{ m.maps_nombre || 'Ubicación compartida' }}</p>
                    <p v-if="m.maps_direccion" class="mt-0.5 text-[11px] opacity-80">{{ m.maps_direccion }}</p>
                    <p class="mt-0.5 font-mono text-[10px] opacity-60">{{ m.maps_lat }}, {{ m.maps_lng }}</p>
                    <a
                      :href="m.maps_url"
                      target="_blank"
                      rel="noopener"
                      class="mt-2 inline-flex items-center gap-1.5 rounded-lg bg-sky-500/25 px-2.5 py-1.5 text-xs font-semibold text-sky-100 hover:bg-sky-500/40"
                    >
                      <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                      Abrir en Google Maps
                    </a>
                  </div>
                  <div v-else class="whitespace-pre-wrap break-words text-[13.5px] leading-snug">{{ m.cuerpo || '—' }}</div>

                  <div
                    v-if="m.direccion === 'salida' && m.estado === 'fallido' && m.fallo"
                    class="mt-2 rounded-md bg-black/25 px-2 py-1.5 text-[11px] leading-snug text-rose-50/95"
                  >
                    <p v-if="m.fallo.titulo || m.fallo.mensaje" class="font-medium">
                      {{ m.fallo.titulo || 'Error Meta' }}
                      <template v-if="m.fallo.mensaje && m.fallo.mensaje !== m.fallo.titulo"> — {{ m.fallo.mensaje }}</template>
                    </p>
                    <p v-if="m.fallo.detalle" class="mt-1 opacity-90">{{ m.fallo.detalle }}</p>
                    <p v-if="m.fallo.tip" class="mt-1.5 rounded bg-amber-500/20 px-1.5 py-1 text-amber-100">{{ m.fallo.tip }}</p>
                    <details class="mt-1.5">
                      <summary class="cursor-pointer select-none text-[10px] text-rose-200/80 hover:text-white">Ver más detalle</summary>
                      <dl class="mt-1 space-y-0.5 font-mono text-[10px] text-rose-100/80">
                        <div><dt class="inline text-rose-300/70">id:</dt> <dd class="inline">#{{ m.id }}</dd></div>
                        <div v-if="m.wamid" class="break-all"><dt class="inline text-rose-300/70">wamid:</dt> <dd class="inline">{{ m.wamid }}</dd></div>
                        <div v-if="m.template_name">
                          <dt class="inline text-rose-300/70">plantilla:</dt>
                          <dd class="inline">{{ m.template_name }} ({{ m.template_language || '—' }})</dd>
                        </div>
                        <div v-if="m.fallo.href_doc" class="pt-1 font-sans">
                          <a :href="m.fallo.href_doc" target="_blank" rel="noopener" class="text-sky-300 hover:underline">Códigos de error Meta ↗</a>
                        </div>
                      </dl>
                    </details>
                  </div>
                  <div v-else-if="m.error_message" class="mt-1 text-[11px] text-rose-200/90">{{ m.error_message }}</div>

                  <div class="mt-1 flex items-center justify-end gap-2 text-[10px] opacity-80">
                    <span class="opacity-60">{{ m.hora }}</span>
                    <template v-if="m.direccion === 'salida' && m.estado === 'fallido' && puedeEditar && configured">
                      <a
                        v-if="necesitaPlantilla(m)"
                        :href="plantillaUrl(m.telefono)"
                        class="rounded bg-amber-500/30 px-2 py-0.5 font-medium text-amber-100 hover:bg-amber-500/40"
                      >Usar plantilla</a>
                      <button
                        v-else
                        type="button"
                        class="rounded bg-white/15 px-2 py-0.5 font-medium text-white hover:bg-white/25 disabled:opacity-40"
                        :disabled="reintentandoId === m.id"
                        @click="reintentar(m)"
                      >{{ reintentandoId === m.id ? '…' : 'Reintentar' }}</button>
                    </template>
                    <span v-else-if="m.direccion === 'salida'" :title="m.estado" :class="m.estado === 'leido' ? 'text-sky-300' : ''">{{ ticks(m.estado) }}</span>
                  </div>
                </div>
              </div>
            </template>
            <div v-if="!loadingHilo && !mensajes.length" class="py-16 text-center text-sm text-gray-400">
              Sin mensajes en este chat.
            </div>
          </div>

          <template v-if="puedeEditar && configured">
            <div v-if="hiloMeta.fuera_ventana" class="border-t border-white/5 bg-amber-950/40 px-4 py-2 text-xs text-amber-100">
              Fuera de ventana 24 h: el texto libre va a fallar.
              <a :href="plantillaUrl(telActivo)" class="font-semibold underline">Enviar con plantilla APPROVED</a>
            </div>
            <form class="border-t border-white/5 bg-[#202c33] p-3" @submit.prevent="enviarTexto">
              <div class="flex items-end gap-2">
                <textarea
                  v-model="texto"
                  rows="1"
                  maxlength="4000"
                  placeholder="Escribí un mensaje…"
                  class="max-h-28 min-h-[42px] flex-1 resize-y rounded-xl border-0 bg-[#2a3942] px-3 py-2.5 text-sm text-gray-100 placeholder:text-gray-400 focus:ring-2 focus:ring-emerald-500 disabled:opacity-50"
                  :disabled="hiloMeta.fuera_ventana || enviando"
                  @keydown.enter.exact.prevent="enviarTexto"
                />
                <button
                  type="submit"
                  class="inline-flex h-[42px] w-[42px] shrink-0 items-center justify-center rounded-full bg-emerald-600 text-white hover:bg-emerald-500 disabled:opacity-40"
                  :disabled="hiloMeta.fuera_ventana || enviando || !texto.trim()"
                  :title="hiloMeta.fuera_ventana ? 'Fuera de ventana 24h' : 'Enviar'"
                >
                  <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24"><path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/></svg>
                </button>
              </div>
              <p class="mt-1.5 text-[10px] text-gray-500">
                <template v-if="hiloMeta.fuera_ventana">Usá una plantilla aprobada para contactar de nuevo.</template>
                <template v-else>Texto libre disponible (el cliente escribió hace menos de 24 h).</template>
              </p>
            </form>
          </template>
          <div v-else-if="puedeEditar" class="border-t border-white/5 bg-[#202c33] px-4 py-3 text-xs text-amber-300/90">
            WhatsApp no configurado: no se puede responder desde aquí.
          </div>
        </template>

        <div v-else class="flex flex-1 flex-col items-center justify-center gap-2 px-6 text-center text-gray-400">
          <svg class="h-16 w-16 opacity-30" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
          <p class="text-sm">Elegí un chat de la lista</p>
        </div>
      </section>
    </div>
  </div>
</template>

<script>
export default {
  name: 'WhatsAppChat',
  props: {
    telInicial: { type: String, default: null },
    buscarInicial: { type: String, default: '' },
    configured: { type: Boolean, default: false },
    puedeEditar: { type: Boolean, default: false },
    urls: { type: Object, default: () => ({}) },
    flash: { type: Object, default: () => ({}) },
  },
  data() {
    return {
      buscar: this.buscarInicial || '',
      conversaciones: [],
      asuntos: [],
      filtroAsuntoId: null,
      telActivo: this.telInicial || null,
      mensajes: [],
      hiloMeta: {},
      ultimoId: 0,
      lastSyncAt: null,
      loadingLista: false,
      loadingHilo: false,
      enviando: false,
      reintentandoId: null,
      marcandoLeidos: false,
      guardandoAsunto: false,
      texto: '',
      toast: null,
      pollTimer: null,
      buscarTimer: null,
      stickBottom: true,
    };
  },
  mounted() {
    if (this.flash?.success) this.showToast(this.flash.success, true);
    if (this.flash?.error) this.showToast(this.flash.error, false);
    this.cargarAsuntos();
    this.cargarConversaciones();
    if (this.telActivo) this.cargarHilo(this.telActivo, false);
    this.pollTimer = setInterval(() => this.poll(), 4000);
  },
  beforeUnmount() {
    if (this.pollTimer) clearInterval(this.pollTimer);
    if (this.buscarTimer) clearTimeout(this.buscarTimer);
  },
  methods: {
    showToast(text, ok = true) {
      this.toast = { text, ok };
      setTimeout(() => {
        if (this.toast?.text === text) this.toast = null;
      }, 6000);
    },
    inicial(s) {
      return this.iniciales(s).charAt(0) || '?';
    },
    iniciales(s) {
      const t = String(s || '?').trim();
      if (!t) return '?';
      // Solo dígitos → últimos 2
      if (/^\d+$/.test(t)) return t.slice(-2);
      const parts = t.split(/\s+/).filter(Boolean);
      if (parts.length >= 2) {
        return (parts[0].charAt(0) + parts[1].charAt(0)).toUpperCase();
      }
      return t.slice(0, 2).toUpperCase();
    },
    avatarColor(seed) {
      const palette = [
        '#00a884', '#53bdeb', '#02a698', '#027eb5', '#06cf9c',
        '#7f66ff', '#ff7a59', '#ffb938', '#c453c3', '#02a698',
        '#008069', '#54656f', '#25d366', '#128c7e', '#34b7f1',
      ];
      const s = String(seed || '');
      let h = 0;
      for (let i = 0; i < s.length; i += 1) h = ((h << 5) - h) + s.charCodeAt(i);
      return palette[Math.abs(h) % palette.length];
    },
    avatarStyle(seed, borderColor) {
      const style = {
        backgroundColor: this.avatarColor(seed),
        boxShadow: '0 0 0 1px rgba(0,0,0,0.08)',
      };
      if (borderColor) {
        style.boxShadow = `0 0 0 2.5px ${borderColor}, 0 0 0 4px rgba(0,0,0,0.12)`;
      }
      return style;
    },
    truncate(s, n) {
      const t = String(s || '');
      return t.length > n ? t.slice(0, n - 1) + '…' : t;
    },
    plantillaUrl(tel) {
      const base = this.urls.enviarPlantilla || '/whatsapp/enviar';
      return `${base}?telefono=${encodeURIComponent(tel || '')}`;
    },
    burbujaClass(m) {
      if (m.direccion !== 'salida') return 'bg-[#202c33] text-gray-100';
      if (m.estado === 'fallido') return 'bg-rose-900/80 text-rose-50 ring-1 ring-rose-400/40';
      return 'bg-[#005c4b] text-gray-50';
    },
    ticks(estado) {
      if (estado === 'leido') return '✓✓';
      if (estado === 'entregado' || estado === 'enviado') return '✓✓';
      if (estado === 'fallido') return '✕';
      return '✓';
    },
    necesitaPlantilla(m) {
      const cod = String(m.fallo?.codigo || m.error_code || '');
      if (cod === '131047' || cod === '132001' || cod === '132000' || cod === '132015' || cod === '132016') return true;
      return /Re-engagement/i.test(String(m.error_message || ''));
    },
    mostrarDia(m, idx) {
      if (!m.dia) return false;
      if (idx === 0) return true;
      return this.mensajes[idx - 1]?.dia !== m.dia;
    },
    syncUrl() {
      const u = new URL(window.location.href);
      if (this.telActivo) u.searchParams.set('tel', this.telActivo);
      else u.searchParams.delete('tel');
      if (this.buscar) u.searchParams.set('buscar', this.buscar);
      else u.searchParams.delete('buscar');
      window.history.replaceState({}, '', u);
    },
    onBuscarInput() {
      if (this.buscarTimer) clearTimeout(this.buscarTimer);
      this.buscarTimer = setTimeout(() => {
        this.syncUrl();
        this.cargarConversaciones();
      }, 320);
    },
    filtrarAsunto(id) {
      this.filtroAsuntoId = id;
      this.cargarConversaciones();
    },
    async cargarAsuntos() {
      if (!this.urls.asuntos) return;
      try {
        const { data } = await window.axios.get(this.urls.asuntos);
        this.asuntos = data.asuntos || [];
      } catch (_) {
        this.asuntos = [];
      }
    },
    async cargarConversaciones() {
      this.loadingLista = true;
      try {
        const params = { buscar: this.buscar || undefined };
        if (this.filtroAsuntoId !== null && this.filtroAsuntoId !== undefined) {
          params.asunto_id = this.filtroAsuntoId;
        }
        const { data } = await window.axios.get(this.urls.conversaciones, { params });
        this.conversaciones = data.conversaciones || [];
      } catch (e) {
        this.showToast(e.response?.data?.message || 'No se pudo cargar la lista', false);
      } finally {
        this.loadingLista = false;
      }
    },
    seleccionar(tel) {
      if (this.telActivo === tel) return;
      this.telActivo = tel;
      this.mensajes = [];
      this.ultimoId = 0;
      this.lastSyncAt = null;
      this.hiloMeta = {};
      this.texto = '';
      this.syncUrl();
      this.cargarHilo(tel, false);
    },
    cerrarChat() {
      this.telActivo = null;
      this.mensajes = [];
      this.ultimoId = 0;
      this.lastSyncAt = null;
      this.hiloMeta = {};
      this.syncUrl();
    },
    mergeMensajes(incoming) {
      if (!incoming?.length) return { added: 0, updated: 0 };
      const byId = new Map(this.mensajes.map((m) => [m.id, m]));
      let added = 0;
      let updated = 0;
      for (const m of incoming) {
        const prev = byId.get(m.id);
        if (!prev) {
          byId.set(m.id, m);
          added += 1;
        } else if (
          prev.estado !== m.estado
          || prev.error_message !== m.error_message
          || prev.error_code !== m.error_code
          || JSON.stringify(prev.fallo) !== JSON.stringify(m.fallo)
        ) {
          byId.set(m.id, { ...prev, ...m });
          updated += 1;
        }
      }
      if (added || updated) {
        this.mensajes = Array.from(byId.values()).sort((a, b) => a.id - b.id);
      }
      return { added, updated };
    },
    async cargarHilo(tel, incremental) {
      if (!tel) return;
      if (!incremental) this.loadingHilo = true;
      try {
        const params = { tel };
        if (incremental && this.ultimoId > 0) params.after_id = this.ultimoId;
        if (incremental && this.lastSyncAt) params.updated_after = this.lastSyncAt;
        const { data } = await window.axios.get(this.urls.hilo, { params });
        if (this.telActivo !== tel) return;

        this.hiloMeta = {
          nombre: data.nombre,
          cliente_id: data.cliente_id,
          cliente_nombre: data.cliente_nombre,
          asunto: data.asunto || null,
          clasificacion: data.clasificacion,
          clasificacion_label: data.clasificacion_label,
          clasificacion_color: data.clasificacion_color,
          fuera_ventana: data.fuera_ventana,
          total: data.total,
          fallidos: data.fallidos,
          sin_leer: data.sin_leer,
        };

        const lote = data.mensajes || [];
        if (incremental && (this.ultimoId > 0 || this.lastSyncAt)) {
          const { added } = this.mergeMensajes(lote);
          if (added) this.$nextTick(() => this.scrollHilo(false));
        } else {
          this.mensajes = lote;
          this.$nextTick(() => this.scrollHilo(true));
        }
        if (data.ultimo_id) this.ultimoId = Math.max(this.ultimoId, data.ultimo_id);
        if (data.server_now) this.lastSyncAt = data.server_now;

        // Al abrir o si hay entradas nuevas sin leer: recibo a Meta + ticks locales.
        if (this.puedeEditar && this.configured && (data.sin_leer || 0) > 0) {
          await this.marcarLeidos(tel);
        }
      } catch (e) {
        if (!incremental) this.showToast(e.response?.data?.error || 'No se pudo cargar el chat', false);
      } finally {
        this.loadingHilo = false;
      }
    },
    async onAsuntoChange(ev) {
      if (!this.telActivo || !this.urls.asignarAsunto) return;
      const raw = ev.target.value;
      const asuntoId = raw === '' ? null : Number(raw);
      this.guardandoAsunto = true;
      try {
        const { data } = await window.axios.post(
          this.urls.asignarAsunto,
          { telefono: this.telActivo, whatsapp_asunto_id: asuntoId },
          { headers: { Accept: 'application/json' } },
        );
        this.hiloMeta = { ...this.hiloMeta, asunto: data.asunto || null };
        this.conversaciones = this.conversaciones.map((c) => (
          c.telefono === this.telActivo ? { ...c, asunto: data.asunto || null } : c
        ));
      } catch (e) {
        this.showToast(e.response?.data?.error || 'No se pudo guardar el asunto', false);
        await this.cargarHilo(this.telActivo, false);
      } finally {
        this.guardandoAsunto = false;
      }
    },
    async marcarLeidos(tel) {
      if (!this.urls.marcarLeidos || this.marcandoLeidos || !tel) return;
      this.marcandoLeidos = true;
      try {
        const { data } = await window.axios.post(
          this.urls.marcarLeidos,
          { telefono: tel },
          { headers: { Accept: 'application/json' } },
        );
        if (data?.ok && (data.marcados || 0) > 0) {
          this.mensajes = this.mensajes.map((m) => (
            m.direccion === 'entrada' && m.estado !== 'leido'
              ? { ...m, estado: 'leido' }
              : m
          ));
          this.hiloMeta = { ...this.hiloMeta, sin_leer: 0 };
          this.conversaciones = this.conversaciones.map((c) => (
            c.telefono === tel ? { ...c, sin_leer: 0 } : c
          ));
        }
      } catch (_) {
        // Silencioso: no bloquear el chat si Meta falla el recibo.
      } finally {
        this.marcandoLeidos = false;
      }
    },
    scrollHilo(force) {
      const el = this.$refs.hiloEl;
      if (!el) return;
      if (force || this.stickBottom) el.scrollTop = el.scrollHeight;
    },
    async poll() {
      await this.cargarConversaciones();
      if (this.telActivo) await this.cargarHilo(this.telActivo, true);
    },
    async enviarTexto() {
      const body = (this.texto || '').trim();
      if (!body || !this.telActivo || this.hiloMeta.fuera_ventana || this.enviando) return;
      this.enviando = true;
      try {
        const { data } = await window.axios.post(this.urls.enviar, {
          telefono: this.telActivo,
          modo: 'texto',
          texto: body,
        }, { headers: { Accept: 'application/json' } });
        this.texto = '';
        if (data.mensaje) {
          this.mergeMensajes([data.mensaje]);
          this.ultimoId = Math.max(this.ultimoId, data.mensaje.id);
          this.$nextTick(() => this.scrollHilo(true));
        } else {
          await this.cargarHilo(this.telActivo, false);
        }
        await this.cargarConversaciones();
      } catch (e) {
        const msg = e.response?.data?.error || e.response?.data?.message || 'Falló el envío';
        this.showToast(msg, false);
        const fallido = e.response?.data?.mensaje;
        if (fallido) {
          this.mergeMensajes([fallido]);
          this.ultimoId = Math.max(this.ultimoId, fallido.id);
          this.$nextTick(() => this.scrollHilo(true));
        }
        await this.cargarConversaciones();
      } finally {
        this.enviando = false;
      }
    },
    async reintentar(m) {
      if (!confirm(`¿Reintentar envío del mensaje #${m.id}?`)) return;
      this.reintentandoId = m.id;
      const url = (this.urls.reintentarTpl || '').replace('__ID__', m.id);
      try {
        const { data } = await window.axios.post(url, {}, { headers: { Accept: 'application/json' } });
        this.showToast(`Reenviado (#${m.id} → #${data.mensaje?.id || '?'})`, true);
        await this.cargarHilo(this.telActivo, false);
        await this.cargarConversaciones();
      } catch (e) {
        this.showToast(e.response?.data?.error || 'No se pudo reintentar', false);
        await this.cargarHilo(this.telActivo, false);
      } finally {
        this.reintentandoId = null;
      }
    },
  },
};
</script>
