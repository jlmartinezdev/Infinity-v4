<template>
  <div class="wa-app overflow-hidden rounded-xl border shadow-xl">
    <div
      v-if="toast"
      class="wa-toast border-b px-4 py-2 text-sm"
      :class="toast.ok ? 'wa-toast-ok' : 'wa-toast-err'"
    >
      {{ toast.text }}
      <button type="button" class="ml-2 text-xs underline opacity-70" @click="toast = null">cerrar</button>
    </div>

    <div class="flex min-h-[72vh] flex-col md:h-[min(82vh,900px)] md:flex-row">
      <!-- Lista -->
      <aside
        class="wa-sidebar flex min-h-0 w-full flex-col md:w-[380px] md:shrink-0 md:border-r"
        :class="telActivo ? 'hidden md:flex' : 'flex'"
      >
        <div class="wa-header flex items-center gap-3 px-4 py-3">
          <div class="wa-me-avatar flex h-10 w-10 items-center justify-center rounded-full">
            <svg class="h-6 w-6" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
              <path d="M12 12c2.7 0 4.8-2.1 4.8-4.8S14.7 2.4 12 2.4 7.2 4.5 7.2 7.2 9.3 12 12 12zm0 2.4c-3.2 0-9.6 1.6-9.6 4.8V22h19.2v-2.8c0-3.2-6.4-4.8-9.6-4.8z" />
            </svg>
          </div>
          <div class="min-w-0 flex-1">
            <p class="wa-title truncate text-sm font-medium">Chats</p>
            <p class="wa-muted truncate text-[11px]">WhatsApp Business</p>
          </div>
        </div>

        <div class="wa-sidebar-body px-3 py-2">
          <div class="relative">
            <svg
              class="wa-muted pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M11 18a7 7 0 100-14 7 7 0 000 14z" />
            </svg>
            <input
              v-model="buscar"
              type="search"
              placeholder="Buscar o empezar un chat nuevo"
              class="wa-input w-full rounded-lg border-0 py-2 pl-10 pr-3 text-sm focus:ring-0"
              @input="onBuscarInput"
            >
          </div>
          <div class="mt-2 flex flex-wrap gap-1.5">
            <button
              type="button"
              class="wa-chip rounded-full px-2.5 py-0.5 text-[11px] font-medium transition"
              :class="filtroAsuntoId === null ? 'wa-chip-on' : ''"
              @click="filtrarAsunto(null)"
            >Todos</button>
            <button
              type="button"
              class="wa-chip rounded-full px-2.5 py-0.5 text-[11px] font-medium transition"
              :class="filtroAsuntoId === 0 ? 'wa-chip-neutral' : ''"
              @click="filtrarAsunto(0)"
            >Sin asunto</button>
            <button
              v-for="a in asuntos"
              :key="a.id"
              type="button"
              class="wa-chip rounded-full px-2.5 py-0.5 text-[11px] font-medium transition"
              :style="filtroAsuntoId === a.id ? { backgroundColor: a.color, color: '#fff' } : undefined"
              @click="filtrarAsunto(a.id)"
            >{{ a.nombre }}</button>
          </div>
          <div class="wa-muted mt-2 flex flex-wrap gap-3 text-[10px]">
            <span class="inline-flex items-center gap-1"><span class="inline-block h-2 w-2 rounded-full bg-[#3b82f6]"></span> Staff</span>
            <span class="inline-flex items-center gap-1"><span class="inline-block h-2 w-2 rounded-full bg-[#00a884]"></span> Cliente</span>
            <span class="inline-flex items-center gap-1"><span class="inline-block h-2 w-2 rounded-full bg-[#f59e0b]"></span> Pedido</span>
          </div>
        </div>

        <div class="wa-list min-h-0 flex-1 overflow-y-auto border-t">
          <div v-if="loadingLista && !conversaciones.length" class="wa-muted px-4 py-10 text-center text-sm">
            Cargando...
          </div>
          <button
            v-for="conv in conversaciones"
            :key="conv.telefono"
            type="button"
            class="wa-row block w-full border-b px-3 py-3 text-left transition"
            :class="telActivo === String(conv.telefono) ? 'wa-row-active' : ''"
            @click.prevent.stop="seleccionar(conv.telefono)"
          >
            <div class="flex items-center gap-3">
              <WaAvatar
                :nombre="conv.nombre"
                :telefono="conv.telefono"
                :ring="conv.clasificacion_color"
                :title="conv.clasificacion_label || conv.nombre || conv.telefono"
              />
              <div class="min-w-0 flex-1">
                <div class="flex items-baseline justify-between gap-2">
                  <p
                    class="wa-title truncate text-[15px]"
                    :class="(conv.sin_leer || 0) > 0 ? 'font-semibold' : 'font-normal'"
                  >
                    {{ conv.nombre || conv.telefono }}
                  </p>
                  <span
                    class="shrink-0 text-[11px]"
                    :class="(conv.sin_leer || 0) > 0 ? 'wa-accent' : 'wa-muted'"
                  >{{ conv.ultimo_at_label || '' }}</span>
                </div>
                <div class="mt-0.5 flex items-center justify-between gap-2">
                  <p class="wa-muted min-w-0 truncate text-[13px]">
                    <span v-if="conv.ultimo_direccion !== 'entrada'" class="mr-0.5 inline">
                      <span :class="conv.ultimo_estado === 'leido' ? 'wa-ticks-read' : ''">{{ ticks(conv.ultimo_estado) }}</span>
                    </span>
                    {{ truncate(conv.ultimo_cuerpo || '-', 42) }}
                  </p>
                  <div class="flex shrink-0 items-center gap-1">
                    <span
                      v-if="conv.asunto"
                      class="max-w-[4rem] truncate rounded-full px-1.5 py-0.5 text-[9px] font-semibold text-white"
                      :style="{ backgroundColor: conv.asunto.color || '#64748b' }"
                      :title="conv.asunto.nombre"
                    >{{ conv.asunto.nombre }}</span>
                    <span
                      v-if="(conv.fallidos || 0) > 0"
                      class="flex h-5 min-w-[1.25rem] items-center justify-center rounded-full bg-[#ea0038] px-1 text-[10px] font-semibold text-white"
                      title="Mensajes fallidos"
                    >{{ conv.fallidos }}</span>
                    <span
                      v-if="(conv.sin_leer || 0) > 0"
                      class="wa-unread flex h-5 min-w-[1.25rem] items-center justify-center rounded-full px-1.5 text-[11px] font-semibold"
                    >{{ conv.sin_leer }}</span>
                  </div>
                </div>
              </div>
            </div>
          </button>
          <div v-if="!loadingLista && !conversaciones.length" class="wa-muted px-4 py-10 text-center text-sm">
            Sin conversaciones.
          </div>
          <div v-if="conversaciones.length" class="border-t px-3 py-2 text-center">
            <p class="wa-muted mb-1.5 text-[11px]">
              {{ conversaciones.length }} chat{{ conversaciones.length === 1 ? '' : 's' }}
              <template v-if="tieneMas"> · hay más</template>
            </p>
            <button
              v-if="tieneMas"
              type="button"
              class="wa-chip wa-chip-on rounded-full px-3 py-1 text-[11px] font-semibold disabled:opacity-50"
              :disabled="loadingMas"
              @click="cargarMas"
            >{{ loadingMas ? 'Cargando...' : 'Cargar más chats' }}</button>
          </div>
        </div>
      </aside>

      <!-- Hilo -->
      <section
        class="wa-chat-pane relative flex min-h-0 min-w-0 flex-1 flex-col"
        :class="telActivo ? 'flex' : 'hidden md:flex'"
      >
        <template v-if="telActivo">
          <header class="wa-header relative z-10 flex items-center gap-3 border-b px-4 py-2.5">
            <button type="button" class="wa-icon-btn rounded-full p-1.5 md:hidden" title="Volver" @click="cerrarChat">
              <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
              </svg>
            </button>
            <WaAvatar
              :nombre="hiloMeta.nombre"
              :telefono="telActivo"
              :ring="hiloMeta.clasificacion_color"
              :title="hiloMeta.clasificacion_label || hiloMeta.nombre || telActivo"
              size="md"
            />
            <div class="min-w-0 flex-1">
              <p class="wa-title truncate text-[15px] font-medium">
                {{ hiloMeta.nombre || telActivo }}
                <span
                  v-if="hiloMeta.clasificacion_label"
                  class="ml-1.5 align-middle text-[10px] font-semibold uppercase tracking-wide"
                  :style="{ color: hiloMeta.clasificacion_color || undefined }"
                >{{ hiloMeta.clasificacion_label }}</span>
              </p>
              <p class="wa-muted truncate text-[12px]">
                <span class="font-mono">{{ telActivo }}</span>
                <template v-if="hiloMeta.cliente_id">
                  · #{{ hiloMeta.cliente_id }}
                  <template v-if="hiloMeta.cliente_nombre"> {{ hiloMeta.cliente_nombre }}</template>
                </template>
                · {{ hiloMeta.total || mensajes.length }} msgs
                <template v-if="(hiloMeta.fallidos || 0) > 0">
                  · <span class="text-[#ea0038]">{{ hiloMeta.fallidos }} fallido(s)</span>
                </template>
              </p>
            </div>
            <div class="flex shrink-0 items-center gap-1.5">
              <button
                v-if="puedeEditar"
                type="button"
                class="wa-chip rounded-lg px-2.5 py-1.5 text-xs font-semibold transition hover:opacity-90"
                title="Guardar nombre y vincular cliente"
                :disabled="!telActivo"
                @click="abrirModalContacto"
              >{{ hiloMeta.cliente_id ? 'Contacto' : 'Guardar' }}</button>
              <button
                v-if="puedeCrearTicket"
                type="button"
                class="wa-chip rounded-lg px-2.5 py-1.5 text-xs font-semibold transition hover:opacity-90"
                title="Crear ticket sin salir"
                :disabled="!telActivo || metaCargando"
                @click="abrirModalTicket"
              >Ticket</button>
              <button
                v-if="puedeCrearPedido"
                type="button"
                class="wa-chip rounded-lg px-2.5 py-1.5 text-xs font-semibold transition hover:opacity-90"
                title="Crear pedido sin salir"
                :disabled="!telActivo || metaCargando"
                @click="abrirModalPedido"
              >Pedido</button>
              <button
                v-if="puedeCrearCobro"
                type="button"
                class="wa-chip rounded-lg px-2.5 py-1.5 text-xs font-semibold transition hover:opacity-90"
                title="Registrar pago sin salir"
                :disabled="!telActivo || metaCargando"
                @click="abrirModalCobro"
              >Pago</button>
              <a
                v-if="hiloMeta.cliente_id"
                :href="clienteDetalleUrl(hiloMeta.cliente_id)"
                target="_blank"
                rel="noopener"
                class="wa-chip rounded-lg px-2.5 py-1.5 text-xs font-semibold transition hover:opacity-90"
                title="Abrir ficha del cliente"
              >Cliente</a>
              <a
                v-if="puedeEditar"
                :href="plantillaUrl(telActivo)"
                class="wa-accent rounded-lg px-2.5 py-1.5 text-xs font-medium hover:opacity-80"
              >Plantilla</a>
              <button
                v-if="puedeEditar"
                type="button"
                class="wa-icon-btn rounded-lg p-1.5 text-rose-600 hover:bg-rose-500/10 disabled:opacity-40 dark:text-rose-300"
                title="Eliminar chat de Infinity (no se borra en el WhatsApp del cliente)"
                :disabled="eliminandoChat || !telActivo"
                @click="eliminarChat"
              >
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M9 7V4a1 1 0 011-1h4a1 1 0 011 1v3m-9 0h12" />
                </svg>
              </button>
            </div>
          </header>

          <div v-if="puedeEditar" class="wa-asunto relative z-10 flex items-center gap-2 border-b px-4 py-2">
            <label class="wa-muted shrink-0 text-[11px]">Asunto</label>
            <select
              class="wa-input min-w-0 flex-1 rounded-lg border-0 px-2 py-1.5 text-xs focus:ring-1 focus:ring-[#00a884]"
              :value="hiloMeta.asunto?.id || ''"
              :disabled="guardandoAsunto"
              @change="onAsuntoChange"
            >
              <option value="">Sin asunto</option>
              <option v-for="a in asuntos" :key="a.id" :value="a.id">{{ a.nombre }}</option>
            </select>
          </div>
          <div v-else-if="hiloMeta.asunto" class="wa-asunto relative z-10 border-b px-4 py-1.5">
            <span
              class="inline-flex rounded-full px-2 py-0.5 text-[10px] font-semibold text-white"
              :style="{ backgroundColor: hiloMeta.asunto.color || '#64748b' }"
            >{{ hiloMeta.asunto.nombre }}</span>
          </div>

          <div
            ref="hiloEl"
            class="wa-wallpaper relative z-0 flex-1 space-y-0.5 overflow-y-auto px-3 py-3 sm:px-12"
          >
            <div v-if="loadingHilo && !mensajes.length" class="wa-muted py-16 text-center text-sm">Cargando...</div>
            <template v-for="(m, idx) in mensajes" :key="m.id">
              <div v-if="mostrarDia(m, idx)" class="my-3 flex justify-center">
                <span class="wa-day rounded-lg px-3 py-1 text-[12px] shadow">{{ m.dia_label }}</span>
              </div>
              <div class="flex" :class="m.direccion === 'salida' ? 'justify-end' : 'justify-start'">
                <div
                  class="wa-bubble relative max-w-[85%] px-2.5 py-1.5 shadow-sm sm:max-w-[65%]"
                  :class="[
                    m.direccion === 'salida' ? 'wa-bubble-out' : 'wa-bubble-in',
                    m.direccion === 'salida' && m.estado === 'fallido' ? 'wa-bubble-fail' : '',
                  ]"
                >
                  <div
                    v-if="m.direccion === 'salida' && m.estado === 'fallido'"
                    class="mb-1 flex items-center gap-1 text-[10px] font-semibold uppercase tracking-wide text-rose-600 dark:text-rose-200"
                  >
                    <span>No enviado</span>
                    <span v-if="m.fallo?.codigo" class="rounded bg-black/10 px-1 font-mono normal-case dark:bg-black/20">#{{ m.fallo.codigo }}</span>
                  </div>
                  <div v-if="m.tipo !== 'text' && m.tipo !== 'unknown'" class="mb-0.5 text-[10px] uppercase tracking-wide opacity-60">
                    {{ m.tipo }}<template v-if="m.template_name"> · {{ m.template_name }}</template>
                  </div>
                  <div v-if="m.direccion === 'salida' && m.contexto_tipo === 'app_whatsapp'" class="wa-accent mb-0.5 text-[10px] opacity-80">
                    Desde app WhatsApp
                  </div>

                  <div v-if="m.tipo === 'audio' && m.media_url" class="my-1 min-w-[220px]">
                    <div v-if="mediaHydrating[m.id] && !mediaSrc(m)" class="wa-muted text-xs py-1">Cargando audio…</div>
                    <audio
                      v-else-if="mediaSrc(m)"
                      :src="mediaSrc(m)"
                      controls
                      preload="metadata"
                      class="w-full max-w-xs"
                      style="height: 36px;"
                    />
                    <p v-else class="rounded-md bg-black/10 px-2 py-1.5 text-xs opacity-80 dark:bg-black/20">
                      No se pudo cargar el audio
                      <button type="button" class="ml-1 underline" @click="hydrateMedia(m, true)">Reintentar</button>
                    </p>
                    <p class="mt-0.5 text-[11px] opacity-70">{{ m.media_voice ? 'Nota de voz' : (captionMedia(m) || 'Audio') }}</p>
                  </div>
                  <div v-else-if="m.tipo === 'image' && m.media_url" class="my-1 max-w-full">
                    <button
                      v-if="!mediaFailed[m.id]"
                      type="button"
                      class="block max-w-full cursor-zoom-in border-0 bg-transparent p-0 text-left"
                      title="Ver imagen"
                      @click="abrirModalImagen(m)"
                    >
                      <img
                        :src="mediaSrc(m) || m.media_url"
                        alt="Imagen"
                        class="max-h-56 max-w-full rounded-md object-contain"
                        @error="onMediaImgError(m)"
                      >
                    </button>
                    <p v-else class="rounded-md bg-black/10 px-2 py-1.5 text-xs opacity-80 dark:bg-black/20">
                      No se pudo cargar la imagen
                      <button type="button" class="ml-1 underline" @click="hydrateMedia(m, true)">Reintentar</button>
                    </p>
                    <p v-if="captionMedia(m)" class="mt-1 whitespace-pre-wrap break-words text-[14.2px] leading-[19px]">{{ captionMedia(m) }}</p>
                  </div>
                  <button
                    v-else-if="['document','video','sticker'].includes(m.tipo) && m.media_url"
                    type="button"
                    class="my-1 inline-flex items-center gap-1 rounded bg-black/10 px-2 py-1 text-xs underline dark:bg-black/20"
                    @click="abrirMediaAdjunto(m)"
                  >
                    Abrir {{ m.tipo }}
                  </button>
                  <div v-else-if="m.tipo === 'location' && m.maps_url" class="my-1 min-w-[200px] max-w-xs">
                    <p class="text-[14px] font-medium leading-snug">{{ m.maps_nombre || 'Ubicación compartida' }}</p>
                    <p v-if="m.maps_direccion" class="mt-0.5 text-[11px] opacity-80">{{ m.maps_direccion }}</p>
                    <p class="mt-0.5 font-mono text-[10px] opacity-60">{{ m.maps_lat }}, {{ m.maps_lng }}</p>
                    <button
                      type="button"
                      class="mt-2 inline-flex items-center gap-1.5 rounded-lg bg-[#00a884]/20 px-2.5 py-1.5 text-xs font-semibold text-[#008069] hover:bg-[#00a884]/30 dark:text-[#d1f4cc]"
                      @click="abrirModalMapa(m)"
                    >
                      Ver mapa aquí
                    </button>
                  </div>
                  <div v-else class="wa-msg-body whitespace-pre-wrap break-words text-[14.2px] leading-[19px]">{{ m.cuerpo || '-' }}</div>

                  <div
                    v-if="m.direccion === 'salida' && m.estado === 'fallido' && m.fallo"
                    class="mt-2 rounded-md bg-rose-500/10 px-2 py-1.5 text-[11px] leading-snug text-rose-800 dark:bg-black/25 dark:text-rose-50/95"
                  >
                    <p v-if="m.fallo.titulo || m.fallo.mensaje" class="font-medium">
                      {{ m.fallo.titulo || 'Error Meta' }}
                      <template v-if="m.fallo.mensaje && m.fallo.mensaje !== m.fallo.titulo"> - {{ m.fallo.mensaje }}</template>
                    </p>
                    <p v-if="m.fallo.detalle" class="mt-1 opacity-90">{{ m.fallo.detalle }}</p>
                    <p v-if="m.fallo.tip" class="mt-1.5 rounded bg-amber-500/20 px-1.5 py-1 text-amber-900 dark:text-amber-100">{{ m.fallo.tip }}</p>
                    <details class="mt-1.5">
                      <summary class="cursor-pointer select-none text-[10px] opacity-80 hover:opacity-100">Ver más detalle</summary>
                      <dl class="mt-1 space-y-0.5 font-mono text-[10px] opacity-80">
                        <div><dt class="inline">id:</dt> <dd class="inline">#{{ m.id }}</dd></div>
                        <div v-if="m.wamid" class="break-all"><dt class="inline">wamid:</dt> <dd class="inline">{{ m.wamid }}</dd></div>
                        <div v-if="m.template_name">
                          <dt class="inline">plantilla:</dt>
                          <dd class="inline">{{ m.template_name }} ({{ m.template_language || '-' }})</dd>
                        </div>
                        <div v-if="m.fallo.href_doc" class="pt-1 font-sans">
                          <a :href="m.fallo.href_doc" target="_blank" rel="noopener" class="text-sky-600 hover:underline dark:text-sky-300">Códigos de error Meta</a>
                        </div>
                      </dl>
                    </details>
                  </div>
                  <div v-else-if="m.error_message" class="mt-1 text-[11px] text-rose-600 dark:text-rose-200/90">{{ m.error_message }}</div>

                  <div class="wa-muted mt-0.5 flex items-center justify-end gap-1.5 text-[11px]">
                    <button
                      v-if="puedeEditar"
                      type="button"
                      class="wa-msg-del rounded p-0.5 hover:bg-black/10 hover:text-rose-600 disabled:opacity-40 dark:hover:bg-white/10 dark:hover:text-rose-300"
                      title="Eliminar mensaje de Infinity (no se borra en el WhatsApp del cliente)"
                      :disabled="eliminandoId === m.id"
                      @click="eliminarMensaje(m)"
                    >
                      <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M9 7V4a1 1 0 011-1h4a1 1 0 011 1v3m-9 0h12" />
                      </svg>
                    </button>
                    <span>{{ m.hora }}</span>
                    <template v-if="m.direccion === 'salida' && m.estado === 'fallido' && puedeEditar && configured">
                      <a
                        v-if="necesitaPlantilla(m)"
                        :href="plantillaUrl(m.telefono)"
                        class="rounded bg-amber-500/30 px-2 py-0.5 font-medium text-amber-900 hover:bg-amber-500/40 dark:text-amber-100"
                      >Usar plantilla</a>
                      <button
                        v-else
                        type="button"
                        class="rounded bg-black/10 px-2 py-0.5 font-medium hover:bg-black/15 disabled:opacity-40 dark:bg-white/15 dark:text-white dark:hover:bg-white/25"
                        :disabled="reintentandoId === m.id"
                        @click="reintentar(m)"
                      >{{ reintentandoId === m.id ? '...' : 'Reintentar' }}</button>
                    </template>
                    <span
                      v-else-if="m.direccion === 'salida'"
                      :title="m.estado"
                      class="text-[14px] leading-none"
                      :class="m.estado === 'leido' ? 'wa-ticks-read' : ''"
                    >{{ ticks(m.estado) }}</span>
                  </div>
                </div>
              </div>
            </template>
            <div v-if="!loadingHilo && !mensajes.length" class="wa-muted py-16 text-center text-sm">
              Sin mensajes en este chat.
            </div>
          </div>

          <template v-if="puedeEditar && configured">
            <div v-if="hiloMeta.fuera_ventana" class="wa-warn relative z-10 border-t px-4 py-2 text-xs">
              Fuera de ventana 24 h: el texto libre va a fallar.
              <a :href="plantillaUrl(telActivo)" class="font-semibold underline">Enviar con plantilla APPROVED</a>
            </div>
            <div
              v-if="sugerenciaIa && puedeEditar"
              class="relative z-10 border-t px-3 py-2 text-xs"
              style="background: #ecfdf5; color: #065f46;"
            >
              <p class="font-semibold">Sugerencia IA — no enviada. Revisá y mandá cuando esté bien.</p>
              <p v-if="sugerenciaIa.escalate" class="mt-0.5">La IA sugiere derivar a un asesor{{ sugerenciaIa.motivo_escalado ? ' (' + sugerenciaIa.motivo_escalado + ')' : '' }}.</p>
              <div class="mt-2 flex flex-wrap gap-2">
                <button
                  type="button"
                  class="rounded-full bg-[#16a34a] px-3 py-1 text-[12px] font-semibold text-white disabled:opacity-40"
                  :disabled="!puedeEnviarSugerencia"
                  @click="enviarTexto"
                >Enviar sugerencia</button>
                <button
                  type="button"
                  class="rounded-full bg-white/80 px-3 py-1 text-[12px] font-semibold text-emerald-900"
                  @click="descartarSugerencia"
                >Descartar</button>
              </div>
            </div>
            <form class="wa-composer relative z-10 border-t px-3 py-2.5" @submit.prevent="enviarTexto">
              <div
                v-if="adjuntoPendiente"
                class="mb-2 flex items-center gap-2 rounded-lg bg-black/5 px-2 py-1.5 dark:bg-white/5"
              >
                <img
                  v-if="adjuntoPendiente.previewUrl"
                  :src="adjuntoPendiente.previewUrl"
                  alt=""
                  class="h-10 w-10 rounded object-cover"
                >
                <div class="min-w-0 flex-1">
                  <p class="truncate text-xs font-medium">{{ adjuntoPendiente.name }}</p>
                  <p class="wa-muted text-[10px]">{{ adjuntoPendiente.label }} · el texto se envía como pie</p>
                </div>
                <button type="button" class="wa-icon-btn rounded-full px-2 py-1 text-xs" @click="quitarAdjunto">Quitar</button>
              </div>
              <div class="flex items-end gap-2">
                <input
                  ref="fileInput"
                  type="file"
                  class="hidden"
                  accept="image/jpeg,image/png,image/jpg,.pdf,application/pdf,video/mp4,audio/*,.doc,.docx,.xls,.xlsx,.txt"
                  @change="onAdjuntoSeleccionado"
                >
                <button
                  type="button"
                  class="wa-icon-btn inline-flex h-[42px] w-[42px] shrink-0 items-center justify-center rounded-full disabled:opacity-40"
                  :disabled="hiloMeta.fuera_ventana || enviando"
                  title="Adjuntar imagen, PDF u otro archivo"
                  @click="abrirSelectorAdjunto"
                >
                  <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13" />
                  </svg>
                </button>
                <textarea
                  ref="composerInput"
                  v-model="texto"
                  rows="1"
                  maxlength="4000"
                  :placeholder="adjuntoPendiente ? 'Pie de foto / mensaje (opcional)' : 'Escribí un mensaje'"
                  autocomplete="off"
                  class="wa-input max-h-28 min-h-[42px] flex-1 resize-none rounded-lg border-0 px-3 py-2.5 text-[15px] focus:ring-0 disabled:opacity-50"
                  :disabled="hiloMeta.fuera_ventana"
                  @input="onComposerInput"
                  @keydown.enter.exact.prevent="enviarTexto"
                />
                <button
                  type="submit"
                  class="wa-send inline-flex h-[42px] w-[42px] shrink-0 items-center justify-center rounded-full disabled:opacity-40"
                  :disabled="hiloMeta.fuera_ventana || enviando || (!texto.trim() && !adjuntoPendiente)"
                  :title="hiloMeta.fuera_ventana ? 'Fuera de ventana 24h' : 'Enviar (Enter)'"
                >
                  <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M1.101 21.757L23.8 12.017 1.101 2.276 1.1 10.01l15.4 2.007-15.4 2.007z" />
                  </svg>
                </button>
              </div>
              <p class="wa-muted mt-1.5 px-1 text-[11px]">
                <template v-if="hiloMeta.fuera_ventana">Usá una plantilla aprobada para contactar de nuevo.</template>
                <template v-else>Enter envía · clip para imagen/PDF · Shift+Enter nueva línea</template>
              </p>
            </form>
          </template>
          <div v-else-if="puedeEditar" class="wa-composer relative z-10 border-t px-4 py-3 text-xs text-amber-700 dark:text-amber-300/90">
            WhatsApp no configurado: no se puede responder desde aquí.
          </div>
        </template>

        <div v-else class="wa-empty relative z-10 flex flex-1 flex-col items-center justify-center gap-3 px-6 text-center">
          <div class="wa-empty-icon flex h-20 w-20 items-center justify-center rounded-full">
            <svg class="h-10 w-10" fill="currentColor" viewBox="0 0 24 24">
              <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.435 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z" />
            </svg>
          </div>
          <p class="wa-title text-2xl font-light">Infinity WhatsApp</p>
          <p class="wa-muted max-w-sm text-sm">Elegí un chat de la lista para ver la conversación.</p>
        </div>
      </section>
    </div>

    <!-- Visor de imagen (dentro del chat) -->
    <div
      v-if="modalImagen"
      class="fixed inset-0 z-[85] flex items-center justify-center bg-black/80 p-3"
      @click.self="cerrarModalImagen"
      @keydown.esc.prevent="cerrarModalImagen"
    >
      <div class="relative flex max-h-[94vh] w-full max-w-5xl flex-col items-center">
        <div class="mb-2 flex w-full items-center justify-between gap-3 px-1">
          <p class="truncate text-sm font-medium text-white/90">{{ modalImagen.caption || 'Imagen' }}</p>
          <button
            type="button"
            class="rounded-full bg-white/15 px-3 py-1 text-sm text-white hover:bg-white/25"
            @click="cerrarModalImagen"
          >Cerrar</button>
        </div>
        <img
          :src="modalImagen.url"
          :alt="modalImagen.caption || 'Imagen'"
          class="max-h-[86vh] max-w-full rounded-lg object-contain shadow-2xl"
        >
      </div>
    </div>

    <!-- Modal mapa GPS -->
    <div
      v-if="modalMapa"
      class="fixed inset-0 z-[80] flex items-center justify-center bg-black/55 p-3"
      @click.self="cerrarModalMapa"
    >
      <div class="wa-app flex max-h-[92vh] w-full max-w-3xl flex-col overflow-hidden rounded-xl border shadow-2xl">
        <div class="wa-header flex items-center justify-between gap-3 border-b px-4 py-3">
          <div class="min-w-0">
            <p class="wa-title truncate text-sm font-semibold">{{ modalMapa.nombre || 'Ubicación' }}</p>
            <p class="wa-muted truncate text-xs">{{ modalMapa.direccion || `${modalMapa.lat}, ${modalMapa.lng}` }}</p>
          </div>
          <button type="button" class="wa-icon-btn rounded-full px-2 py-1 text-sm" @click="cerrarModalMapa">Cerrar</button>
        </div>
        <div class="relative w-full bg-black/10" style="height: min(58vh, 420px);">
          <iframe
            v-if="modalMapa.embed"
            :src="modalMapa.embed"
            class="absolute inset-0 h-full w-full border-0"
            loading="lazy"
            referrerpolicy="no-referrer-when-downgrade"
            title="Mapa"
          />
        </div>
        <div class="wa-composer flex flex-wrap items-center gap-2 border-t px-4 py-3">
          <a
            :href="modalMapa.url"
            target="_blank"
            rel="noopener"
            class="wa-accent rounded-lg px-3 py-1.5 text-xs font-semibold underline"
          >Abrir en Google Maps</a>
          <button
            v-if="puedeCrearPedido"
            type="button"
            class="wa-chip wa-chip-on rounded-lg px-3 py-1.5 text-xs font-semibold"
            @click="usarMapaEnPedido"
          >Usar en pedido</button>
          <button type="button" class="wa-chip rounded-lg px-3 py-1.5 text-xs" @click="cerrarModalMapa">Listo</button>
        </div>
      </div>
    </div>

    <!-- Modal guardar contacto / vincular cliente -->
    <div
      v-if="modalContacto"
      class="fixed inset-0 z-[80] flex items-center justify-center bg-black/55 p-3"
      @click.self="cerrarModalContacto"
    >
      <div class="wa-app flex max-h-[92vh] w-full max-w-lg flex-col overflow-hidden rounded-xl border shadow-2xl">
        <div class="wa-header flex items-center justify-between border-b px-4 py-3">
          <div>
            <p class="wa-title text-sm font-semibold">Guardar contacto</p>
            <p class="wa-muted font-mono text-xs">{{ telActivo }}</p>
          </div>
          <button type="button" class="wa-icon-btn rounded-full px-2 py-1 text-sm" @click="cerrarModalContacto">Cerrar</button>
        </div>
        <div class="wa-sidebar-body min-h-0 flex-1 space-y-3 overflow-y-auto px-4 py-4">
          <div>
            <label class="wa-muted mb-1 block text-[11px]">Nombre en WhatsApp</label>
            <input
              v-model="contactoForm.nombre"
              type="text"
              maxlength="200"
              class="wa-input w-full rounded-lg border-0 px-3 py-2 text-sm"
              placeholder="Ej: Juan Pérez"
              autocomplete="off"
            >
            <p class="wa-muted mt-1 text-[11px]">Solo afecta el contacto del panel; no cambia el cliente ISP.</p>
          </div>
          <div>
            <label class="wa-muted mb-1 block text-[11px]">Vincular a cliente existente</label>
            <input
              v-model="contactoForm.buscar"
              type="search"
              class="wa-input w-full rounded-lg border-0 px-3 py-2 text-sm"
              placeholder="Buscar por nombre, cédula o ID…"
              autocomplete="off"
              @input="onBuscarClienteContacto"
            >
            <div v-if="contactoForm.cliente_id" class="mt-2 flex items-center justify-between gap-2 rounded-lg bg-black/5 px-3 py-2 dark:bg-white/5">
              <p class="wa-title min-w-0 truncate text-sm">
                #{{ contactoForm.cliente_id }}
                <span v-if="contactoForm.cliente_label"> · {{ contactoForm.cliente_label }}</span>
              </p>
              <button type="button" class="wa-muted shrink-0 text-xs underline" @click="limpiarClienteContacto">Quitar</button>
            </div>
            <ul v-else-if="contactoClientes.length" class="mt-2 max-h-40 space-y-1 overflow-y-auto rounded-lg border border-black/10 dark:border-white/10">
              <li v-for="c in contactoClientes" :key="c.cliente_id">
                <button
                  type="button"
                  class="wa-title flex w-full items-center justify-between gap-2 px-3 py-2 text-left text-sm hover:bg-black/5 dark:hover:bg-white/5"
                  @click="elegirClienteContacto(c)"
                >
                  <span class="min-w-0 truncate">{{ c.nombre }} {{ c.apellido }}</span>
                  <span class="wa-muted shrink-0 font-mono text-[11px]">#{{ c.cliente_id }} · {{ c.cedula || '—' }}</span>
                </button>
              </li>
            </ul>
            <p v-else-if="contactoForm.buscar.trim().length >= 2 && !contactoBuscando" class="wa-muted mt-2 text-xs">Sin resultados.</p>
            <p v-else-if="contactoBuscando" class="wa-muted mt-2 text-xs">Buscando…</p>
          </div>
          <p v-if="modalError" class="text-sm text-rose-600 dark:text-rose-300">{{ modalError }}</p>
        </div>
        <div class="wa-composer flex justify-end gap-2 border-t px-4 py-3">
          <button type="button" class="wa-chip rounded-lg px-3 py-1.5 text-xs" :disabled="guardandoContacto" @click="cerrarModalContacto">Cancelar</button>
          <button
            type="button"
            class="wa-chip wa-chip-on rounded-lg px-3 py-1.5 text-xs font-semibold disabled:opacity-50"
            :disabled="guardandoContacto || !puedeGuardarContacto"
            @click="guardarContacto"
          >{{ guardandoContacto ? 'Guardando…' : 'Guardar' }}</button>
        </div>
      </div>
    </div>

    <!-- Modal ticket rápido -->
    <div
      v-if="modalTicket"
      class="fixed inset-0 z-[80] flex items-center justify-center bg-black/55 p-3"
      @click.self="cerrarModalTicket"
    >
      <div class="wa-app flex max-h-[92vh] w-full max-w-lg flex-col overflow-hidden rounded-xl border shadow-2xl">
        <div class="wa-header flex items-center justify-between border-b px-4 py-3">
          <div>
            <p class="wa-title text-sm font-semibold">Ticket rápido</p>
            <p class="wa-muted text-xs">{{ telActivo }} · sin salir del chat</p>
          </div>
          <button type="button" class="wa-icon-btn rounded-full px-2 py-1 text-sm" @click="cerrarModalTicket">Cerrar</button>
        </div>
        <div class="wa-sidebar-body min-h-0 flex-1 space-y-3 overflow-y-auto px-4 py-4">
          <div v-if="metaCargando" class="wa-muted text-sm">Cargando datos...</div>
          <template v-else>
            <div>
              <label class="wa-muted mb-1 block text-[11px]">Cliente</label>
              <p class="wa-title text-sm">
                <template v-if="ticketForm.cliente_id">
                  #{{ ticketForm.cliente_id }}
                  <span v-if="ticketForm.cliente_label"> · {{ ticketForm.cliente_label }}</span>
                </template>
                <template v-else>Sin cliente vinculado (se crea igual)</template>
              </p>
            </div>
            <div>
              <label class="wa-muted mb-1 block text-[11px]">Asunto *</label>
              <select v-model="ticketForm.ticket_asunto_id" class="wa-input w-full rounded-lg border-0 px-3 py-2 text-sm">
                <option value="">Elegir asunto</option>
                <option v-for="a in ticketAsuntos" :key="a.id" :value="a.id">{{ a.nombre }}</option>
              </select>
            </div>
            <div>
              <label class="wa-muted mb-1 block text-[11px]">Prioridad</label>
              <select v-model="ticketForm.prioridad" class="wa-input w-full rounded-lg border-0 px-3 py-2 text-sm">
                <option value="baja">Baja</option>
                <option value="media">Media</option>
                <option value="alta">Alta</option>
              </select>
            </div>
            <div>
              <label class="wa-muted mb-1 block text-[11px]">Asignar a</label>
              <select v-model="ticketForm.asignado_id" class="wa-input w-full rounded-lg border-0 px-3 py-2 text-sm">
                <option value="">Sin asignar</option>
                <option v-for="t in tecnicos" :key="t.id" :value="t.id">{{ t.nombre }}</option>
              </select>
            </div>
            <div>
              <label class="wa-muted mb-1 block text-[11px]">Descripción</label>
              <textarea
                v-model="ticketForm.descripcion"
                rows="4"
                class="wa-input w-full rounded-lg border-0 px-3 py-2 text-sm"
                placeholder="Detalle del problema..."
              />
            </div>
            <p v-if="modalError" class="text-sm text-rose-600 dark:text-rose-300">{{ modalError }}</p>
          </template>
        </div>
        <div class="wa-composer flex justify-end gap-2 border-t px-4 py-3">
          <button type="button" class="wa-chip rounded-lg px-3 py-1.5 text-xs" :disabled="guardandoRapido" @click="cerrarModalTicket">Cancelar</button>
          <button
            type="button"
            class="wa-chip wa-chip-on rounded-lg px-3 py-1.5 text-xs font-semibold disabled:opacity-50"
            :disabled="guardandoRapido || metaCargando || !ticketForm.ticket_asunto_id"
            @click="guardarTicketRapido"
          >{{ guardandoRapido ? 'Guardando...' : 'Crear ticket' }}</button>
        </div>
      </div>
    </div>

    <!-- Modal cobro rápido -->
    <div
      v-if="modalCobro"
      class="fixed inset-0 z-[80] flex items-center justify-center bg-black/55 p-3"
      @click.self="cerrarModalCobro"
    >
      <div class="wa-app flex max-h-[92vh] w-full max-w-lg flex-col overflow-hidden rounded-xl border shadow-2xl">
        <div class="wa-header flex items-center justify-between border-b px-4 py-3">
          <div>
            <p class="wa-title text-sm font-semibold">Registrar pago</p>
            <p class="wa-muted text-xs">{{ telActivo }} · sin salir del chat</p>
          </div>
          <button type="button" class="wa-icon-btn rounded-full px-2 py-1 text-sm" @click="cerrarModalCobro">Cerrar</button>
        </div>
        <div class="wa-sidebar-body min-h-0 flex-1 space-y-3 overflow-y-auto px-4 py-4">
          <div v-if="metaCargando" class="wa-muted text-sm">Cargando facturas...</div>
          <template v-else>
            <div class="flex items-start justify-between gap-2">
              <div>
                <label class="wa-muted mb-1 block text-[11px]">Cliente</label>
                <p class="wa-title text-sm">
                  <template v-if="cobroForm.cliente_id">
                    #{{ cobroForm.cliente_id }}
                    <span v-if="cobroForm.cliente_label"> · {{ cobroForm.cliente_label }}</span>
                  </template>
                  <template v-else>Sin cliente vinculado</template>
                </p>
                <p v-if="cobroForm.cedula" class="wa-muted mt-0.5 text-xs">CI/RUC: {{ cobroForm.cedula }}</p>
              </div>
              <a
                v-if="cobroForm.cliente_url"
                :href="cobroForm.cliente_url"
                target="_blank"
                rel="noopener"
                class="wa-accent shrink-0 text-xs font-medium"
              >Ver cuenta</a>
            </div>

            <div>
              <div class="mb-1 flex items-center justify-between gap-2">
                <label class="wa-muted text-[11px]">Facturas pendientes</label>
                <span class="wa-muted text-[11px]">Total: {{ formatGs(cobroTotalPendiente) }}</span>
              </div>
              <div v-if="!cobroFacturas.length" class="rounded-lg border border-dashed border-gray-300 px-3 py-4 text-center text-xs text-gray-500 dark:border-gray-600 dark:text-gray-400">
                No hay facturas con saldo pendiente.
              </div>
              <div v-else class="max-h-48 space-y-1.5 overflow-y-auto rounded-lg border border-gray-200 p-2 dark:border-gray-600">
                <label
                  v-for="f in cobroFacturas"
                  :key="f.id"
                  class="flex cursor-pointer items-start gap-2 rounded-md px-2 py-1.5 hover:bg-black/5 dark:hover:bg-white/5"
                >
                  <input v-model="cobroForm.factura_ids" type="checkbox" class="mt-1" :value="f.id" @change="onCobroFacturasChange" />
                  <span class="min-w-0 flex-1 text-xs">
                    <span class="wa-title font-medium">#{{ f.id }}</span>
                    <span class="wa-muted"> · vence {{ f.fecha_vencimiento || '—' }}</span>
                    <span class="mt-0.5 block font-semibold text-emerald-700 dark:text-emerald-300">{{ formatGs(f.saldo_pendiente) }}</span>
                  </span>
                </label>
              </div>
              <button
                v-if="cobroFacturas.length"
                type="button"
                class="wa-muted mt-1 text-[11px] underline"
                @click="seleccionarTodasFacturasCobro"
              >Seleccionar todas</button>
            </div>

            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="wa-muted mb-1 block text-[11px]">Monto *</label>
                <input v-model.number="cobroForm.monto" type="number" min="0.01" step="0.01" class="wa-input w-full rounded-lg border-0 px-3 py-2 text-sm" />
              </div>
              <div>
                <label class="wa-muted mb-1 block text-[11px]">Fecha *</label>
                <input v-model="cobroForm.fecha_pago" type="date" class="wa-input w-full rounded-lg border-0 px-3 py-2 text-sm" />
              </div>
            </div>
            <div>
              <label class="wa-muted mb-1 block text-[11px]">Forma de pago *</label>
              <select v-model="cobroForm.forma_pago" class="wa-input w-full rounded-lg border-0 px-3 py-2 text-sm">
                <option v-for="(label, key) in cobroFormasPago" :key="key" :value="key">{{ label }}</option>
              </select>
            </div>
            <div>
              <label class="wa-muted mb-1 block text-[11px]">Referencia</label>
              <input v-model="cobroForm.referencia" type="text" class="wa-input w-full rounded-lg border-0 px-3 py-2 text-sm" placeholder="Nº transferencia / cheque…" />
            </div>
            <div>
              <label class="wa-muted mb-1 block text-[11px]">Observaciones</label>
              <textarea v-model="cobroForm.observaciones" rows="2" class="wa-input w-full rounded-lg border-0 px-3 py-2 text-sm" placeholder="Notas del pago…" />
            </div>
            <p v-if="modalError" class="text-sm text-rose-600 dark:text-rose-300">{{ modalError }}</p>
            <p v-if="cobroResultado" class="text-sm text-emerald-700 dark:text-emerald-300">
              {{ cobroResultado.message }}
              <a v-if="cobroResultado.url" :href="cobroResultado.url" target="_blank" class="ml-1 underline">Ver recibo</a>
            </p>
          </template>
        </div>
        <div class="wa-composer flex justify-end gap-2 border-t px-4 py-3">
          <button type="button" class="wa-chip rounded-lg px-3 py-1.5 text-xs" :disabled="guardandoRapido" @click="cerrarModalCobro">Cancelar</button>
          <button
            type="button"
            class="wa-chip wa-chip-on rounded-lg px-3 py-1.5 text-xs font-semibold disabled:opacity-50"
            :disabled="guardandoRapido || metaCargando || !cobroForm.cliente_id || !cobroForm.factura_ids.length || !cobroForm.monto"
            @click="guardarCobroRapido"
          >{{ guardandoRapido ? 'Guardando...' : 'Registrar pago' }}</button>
        </div>
      </div>
    </div>

    <!-- Modal pedido (mismo PedidoForm Paso 1/2) -->
    <Teleport to="body">
      <div
        v-show="modalPedido"
        class="fixed inset-0 z-[80] overflow-hidden"
        role="dialog"
        aria-modal="true"
      >
        <div class="fixed inset-0 bg-gray-900/60 transition-opacity" aria-hidden="true" @click="cerrarModalPedido" />
        <div
          class="absolute max-h-[90vh] w-[calc(100vw-1rem)] max-w-2xl overflow-y-auto rounded-xl border border-gray-200 bg-white shadow-xl dark:border-gray-700 dark:bg-gray-800 sm:w-[calc(100vw-2rem)]"
          style="top: 50%; left: 50%; transform: translate(-50%, -50%);"
        >
          <div class="sticky top-0 z-10 flex cursor-default select-none items-center justify-between border-b border-gray-200 bg-gray-50 p-3 dark:border-gray-700 dark:bg-gray-700/50">
            <div class="flex items-center gap-2 text-sm font-semibold text-gray-700 dark:text-gray-200">
              <svg class="h-4 w-4 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8h16M4 16h16" />
              </svg>
              <span>Nuevo Pedido</span>
            </div>
            <button
              type="button"
              class="rounded-lg p-1.5 transition-colors hover:bg-gray-200 dark:hover:bg-gray-600"
              aria-label="Cerrar"
              @click="cerrarModalPedido"
            >
              <svg class="h-5 w-5 text-gray-600 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>
          <div class="min-w-0 bg-white p-3 dark:bg-gray-800 sm:p-4">
            <PedidoForm
              v-if="modalPedido && pedidoFormConfig"
              :key="pedidoFormKey"
              :pedido-id="pedidoFormConfig.pedidoId || 'Nuevo'"
              :planes="pedidoFormConfig.planes || []"
              :estado-id="pedidoFormConfig.estadoId || 1"
              :buscar-cliente-url="pedidoFormConfig.buscarClienteUrl"
              :verificar-telefono-url="pedidoFormConfig.verificarTelefonoUrl || ''"
              :cedula-temporal-url="pedidoFormConfig.cedulaTemporalUrl || ''"
              :consultar-padron-url="pedidoFormConfig.consultarPadronUrl"
              :submit-url="pedidoFormConfig.submitUrl"
              :cancel-url="pedidoFormConfig.cancelUrl"
              :csrf-token="pedidoFormConfig.csrfToken"
              :modal-mode="true"
              :initial-values="pedidoInitialValues"
            />
            <p v-else-if="modalPedido" class="p-4 text-sm text-gray-500">
              No se pudo cargar el formulario de pedido (faltan permisos o configuración).
            </p>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script>
import PedidoForm from '@/components/PedidoForm.vue';

function hashSeed(str) {
  let h = 0;
  const s = String(str || '');
  for (let i = 0; i < s.length; i += 1) h = ((h << 5) - h) + s.charCodeAt(i);
  return Math.abs(h | 0);
}

/** Solo ASCII seguro (evita URIError con nombres fancy/emoji). */
function safeInitials(nombre, telefono) {
  const raw = String(nombre || '');
  // Quitar surrogate pairs / no-BMP y dejar letras latinas si se puede
  const ascii = raw
    .normalize('NFKD')
    .replace(/[\u0300-\u036f]/g, '')
    .replace(/[^\x20-\x7E]/g, ' ')
    .replace(/[^A-Za-z0-9\s]/g, ' ')
    .trim();
  if (ascii) {
    const parts = ascii.split(/\s+/).filter(Boolean);
    if (parts.length >= 2) {
      return (parts[0].charAt(0) + parts[1].charAt(0)).toUpperCase();
    }
    return ascii.slice(0, 2).toUpperCase();
  }
  const digits = String(telefono || '').replace(/\D/g, '');
  return digits ? digits.slice(-2) : '?';
}

/** Avatar ilustrado único por teléfono. SVG solo ASCII. */
function buildAvatarDataUri(seed, label) {
  const h = hashSeed(seed);
  const palette = [
    ['#00a884', '#025c4c'], ['#53bdeb', '#1e6f9a'], ['#7f66ff', '#4a38b5'],
    ['#ff7a59', '#c24328'], ['#ffb938', '#b07a10'], ['#c453c3', '#7a2e7a'],
    ['#06cf9c', '#028060'], ['#34b7f1', '#0b6fa0'], ['#d3396d', '#8c1842'],
    ['#ac944c', '#6e5c28'], ['#128c7e', '#064e46'], ['#25d366', '#0d8a3e'],
  ];
  const [c1, c2] = palette[h % palette.length];
  const cx = 22 + (h % 52);
  const cy = 18 + ((h >> 3) % 50);
  const r = 28 + ((h >> 5) % 36);
  const initials = String(label || '?')
    .replace(/[^A-Za-z0-9?]/g, '')
    .slice(0, 2)
    .toUpperCase() || '?';
  const svg = [
    '<svg xmlns="http://www.w3.org/2000/svg" width="96" height="96" viewBox="0 0 96 96">',
    '<defs><linearGradient id="g" x1="0%" y1="0%" x2="100%" y2="100%">',
    `<stop offset="0%" stop-color="${c1}"/>`,
    `<stop offset="100%" stop-color="${c2}"/>`,
    '</linearGradient></defs>',
    '<rect width="96" height="96" fill="url(#g)"/>',
    `<circle cx="${cx}" cy="${cy}" r="${r}" fill="#fff" fill-opacity="0.16"/>`,
    `<circle cx="${96 - cx}" cy="${70 + (h % 20)}" r="${Math.round(r * 0.55)}" fill="#000" fill-opacity="0.12"/>`,
    '<circle cx="48" cy="36" r="17" fill="#fff" fill-opacity="0.95"/>',
    '<ellipse cx="48" cy="78" rx="28" ry="22" fill="#fff" fill-opacity="0.95"/>',
    '<circle cx="48" cy="48" r="22" fill="#000" fill-opacity="0.28"/>',
    `<text x="48" y="54" text-anchor="middle" font-family="Arial,sans-serif" font-size="18" font-weight="700" fill="#fff">${initials}</text>`,
    '</svg>',
  ].join('');
  try {
    return `data:image/svg+xml;charset=utf-8,${encodeURIComponent(svg)}`;
  } catch (_) {
    // Fallback absoluto si algo raro queda en el SVG
    return `data:image/svg+xml;charset=utf-8,${encodeURIComponent(
      `<svg xmlns="http://www.w3.org/2000/svg" width="96" height="96"><rect width="96" height="96" fill="${c1}"/><text x="48" y="54" text-anchor="middle" fill="#fff" font-size="18" font-family="Arial">${initials}</text></svg>`,
    )}`;
  }
}

function inicialesFrom(nombre, telefono) {
  return safeInitials(nombre, telefono);
}

const WaAvatar = {
  name: 'WaAvatar',
  props: {
    nombre: { type: String, default: null },
    telefono: { type: String, default: '' },
    ring: { type: String, default: null },
    title: { type: String, default: '' },
    size: { type: String, default: 'md' },
  },
  computed: {
    sizeClass() {
      return this.size === 'sm' ? 'h-10 w-10 text-[11px]' : 'h-11 w-11 text-xs';
    },
    seed() {
      return this.telefono || this.nombre || '?';
    },
    label() {
      return inicialesFrom(this.nombre, this.telefono);
    },
    src() {
      return buildAvatarDataUri(this.seed, this.label);
    },
    style() {
      if (!this.ring) return {};
      return { boxShadow: `0 0 0 2px ${this.ring}` };
    },
  },
  template: `
    <div
      class="relative shrink-0 overflow-hidden rounded-full bg-gray-300 shadow-sm"
      :class="sizeClass"
      :style="style"
      :title="title"
    >
      <img :src="src" alt="" class="h-full w-full object-cover" width="48" height="48" loading="lazy" draggable="false">
    </div>
  `,
};

export default {
  name: 'WhatsAppChat',
  components: { WaAvatar, PedidoForm },
  props: {
    telInicial: { type: String, default: null },
    buscarInicial: { type: String, default: '' },
    configured: { type: Boolean, default: false },
    puedeEditar: { type: Boolean, default: false },
    puedeCrearTicket: { type: Boolean, default: false },
    puedeCrearPedido: { type: Boolean, default: false },
    puedeCrearCobro: { type: Boolean, default: false },
    pedidoFormConfig: { type: Object, default: null },
    urls: { type: Object, default: () => ({}) },
    flash: { type: Object, default: () => ({}) },
  },
  data() {
    return {
      buscar: this.buscarInicial || '',
      conversaciones: [],
      asuntos: [],
      filtroAsuntoId: null,
      telActivo: this.telInicial ? String(this.telInicial) : null,
      mensajes: [],
      hiloMeta: {},
      ultimoId: 0,
      lastSyncAt: null,
      loadingLista: false,
      loadingMas: false,
      loadingHilo: false,
      enviando: false,
      adjuntoPendiente: null,
      mediaObjectUrls: {},
      mediaFailed: {},
      mediaHydrating: {},
      reintentandoId: null,
      eliminandoId: null,
      eliminandoChat: false,
      marcandoLeidos: false,
      guardandoAsunto: false,
      texto: '',
      sugerenciaAplicadaId: null,
      textoDesdeIa: false,
      toast: null,
      pollTimer: null,
      buscarTimer: null,
      stickBottom: true,
      pageLimit: 250,
      pageOffset: 0,
      tieneMas: false,
      totalChats: 0,
      hiloSeq: 0,
      hiloAbort: null,
      // Rápidos
      metaCargando: false,
      guardandoRapido: false,
      modalError: null,
      modalImagen: null,
      modalMapa: null,
      modalContacto: false,
      modalTicket: false,
      modalPedido: false,
      modalCobro: false,
      guardandoContacto: false,
      contactoBuscando: false,
      contactoBuscarTimer: null,
      contactoClientes: [],
      contactoForm: {
        nombre: '',
        buscar: '',
        cliente_id: null,
        cliente_label: '',
        quitar_cliente: false,
      },
      cobroFacturas: [],
      cobroFormasPago: {},
      cobroTotalPendiente: 0,
      cobroResultado: null,
      cobroForm: {
        cliente_id: null,
        cliente_label: '',
        cedula: '',
        cliente_url: '',
        factura_ids: [],
        monto: null,
        fecha_pago: '',
        forma_pago: 'efectivo',
        referencia: '',
        observaciones: '',
      },
      pedidoFormKey: 0,
      pedidoInitialValues: null,
      ticketAsuntos: [],
      tecnicos: [],
      ticketForm: {
        cliente_id: null,
        cliente_label: '',
        ticket_asunto_id: '',
        prioridad: 'media',
        asignado_id: '',
        descripcion: '',
      },
      _onPedidoCreated: null,
      _onClosePedidoModal: null,
    };
  },
  mounted() {
    if (this.flash?.success) this.showToast(this.flash.success, true);
    if (this.flash?.error) this.showToast(this.flash.error, false);
    if (!this.urls?.conversaciones || !this.urls?.hilo) {
      this.showToast('Config de WhatsApp incompleta (faltan URLs). Recarga la pagina.', false);
    }
    this.cargarAsuntos();
    this.cargarConversaciones(true);
    if (this.telActivo) this.cargarHilo(this.telActivo, false);
    this.pollTimer = setInterval(() => this.poll(), 8000);

    this._onPedidoCreated = (ev) => {
      this.modalPedido = false;
      const msg = ev?.detail?.message || 'Pedido creado';
      this.showToast(msg, true);
    };
    this._onClosePedidoModal = () => {
      this.modalPedido = false;
    };
    window.addEventListener('pedido-created', this._onPedidoCreated);
    window.addEventListener('close-pedido-modal', this._onClosePedidoModal);
    this._onKeydown = (ev) => {
      if (ev.key !== 'Escape') return;
      if (this.modalImagen) this.cerrarModalImagen();
      else if (this.modalContacto) this.cerrarModalContacto();
      else if (this.modalMapa) this.cerrarModalMapa();
    };
    window.addEventListener('keydown', this._onKeydown);
  },
  beforeUnmount() {
    if (this.pollTimer) clearInterval(this.pollTimer);
    if (this.buscarTimer) clearTimeout(this.buscarTimer);
    if (this.contactoBuscarTimer) clearTimeout(this.contactoBuscarTimer);
    if (this.hiloAbort) this.hiloAbort.abort();
    Object.values(this.mediaObjectUrls || {}).forEach((u) => {
      try { URL.revokeObjectURL(u); } catch (_) {}
    });
    if (this._onPedidoCreated) window.removeEventListener('pedido-created', this._onPedidoCreated);
    if (this._onClosePedidoModal) window.removeEventListener('close-pedido-modal', this._onClosePedidoModal);
    if (this._onKeydown) window.removeEventListener('keydown', this._onKeydown);
  },
  computed: {
    puedeGuardarContacto() {
      const nombre = (this.contactoForm.nombre || '').trim();
      return !!(nombre || this.contactoForm.cliente_id || this.contactoForm.quitar_cliente);
    },
    sugerenciaIa() {
      return this.hiloMeta?.sugerencia_ia || null;
    },
    puedeEnviarSugerencia() {
      return !!(this.sugerenciaIa?.reply && (this.texto || '').trim() && !this.hiloMeta.fuera_ventana && !this.enviando);
    },
  },
  methods: {
    showToast(text, ok = true) {
      this.toast = { text, ok };
      setTimeout(() => {
        if (this.toast?.text === text) this.toast = null;
      }, 6000);
    },
    truncate(s, n) {
      const t = String(s || '');
      return t.length > n ? `${t.slice(0, n - 1)}...` : t;
    },
    plantillaUrl(tel) {
      const base = this.urls.enviarPlantilla || '/whatsapp/enviar';
      return `${base}?telefono=${encodeURIComponent(tel || '')}`;
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
        this.cargarConversaciones(true);
      }, 320);
    },
    filtrarAsunto(id) {
      this.filtroAsuntoId = id;
      this.cargarConversaciones(true);
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
    async cargarConversaciones(reset = false) {
      if (!this.urls?.conversaciones) return;
      if (reset) {
        this.pageOffset = 0;
        this.loadingLista = true;
      }
      try {
        const params = {
          buscar: this.buscar || undefined,
          limit: this.pageLimit,
          offset: this.pageOffset,
        };
        if (this.filtroAsuntoId !== null && this.filtroAsuntoId !== undefined) {
          params.asunto_id = this.filtroAsuntoId;
        }
        const { data } = await window.axios.get(this.urls.conversaciones, { params });
        const lote = Array.isArray(data?.conversaciones) ? data.conversaciones : [];
        if (reset || this.pageOffset === 0) {
          this.conversaciones = lote;
        } else {
          const seen = new Set(this.conversaciones.map((c) => String(c.telefono)));
          for (const c of lote) {
            const t = String(c.telefono);
            if (!seen.has(t)) this.conversaciones.push(c);
          }
        }
        this.tieneMas = !!data.has_more;
        this.totalChats = data.total ?? this.conversaciones.length;
      } catch (e) {
        this.showToast(e.response?.data?.message || e.response?.data?.error || 'No se pudo cargar la lista', false);
      } finally {
        this.loadingLista = false;
        this.loadingMas = false;
      }
    },
    async cargarMas() {
      if (!this.tieneMas || this.loadingMas) return;
      this.loadingMas = true;
      this.pageOffset += this.pageLimit;
      await this.cargarConversaciones(false);
    },
    seleccionar(tel) {
      const t = String(tel || '').trim();
      if (!t) {
        this.showToast('Chat sin telefono', false);
        return;
      }
      // Siempre recargar al tocar (aunque sea el mismo)
      if (this.hiloAbort) {
        try { this.hiloAbort.abort(); } catch (_) {}
      }
      this.hiloSeq += 1;
      this.telActivo = t;
      this.mensajes = [];
      this.ultimoId = 0;
      this.lastSyncAt = null;
      this.hiloMeta = { nombre: this.nombreDeLista(t) };
      this.texto = '';
      this.sugerenciaAplicadaId = null;
      this.textoDesdeIa = false;
      this.quitarAdjunto();
      this.syncUrl();
      this.cargarHilo(t, false, this.hiloSeq);
    },
    nombreDeLista(tel) {
      const c = this.conversaciones.find((x) => String(x.telefono) === String(tel));
      return c?.nombre || null;
    },
    cerrarChat() {
      this.hiloSeq += 1;
      if (this.hiloAbort) {
        try { this.hiloAbort.abort(); } catch (_) {}
      }
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
        this.$nextTick(() => this.hydrateMediaEnHilo());
      }
      return { added, updated };
    },
    async cargarHilo(tel, incremental, seq = null) {
      const t = String(tel || '').trim();
      if (!t || !this.urls?.hilo) return;
      const mySeq = seq == null ? this.hiloSeq : seq;
      if (!incremental) this.loadingHilo = true;

      if (!incremental) {
        if (this.hiloAbort) {
          try { this.hiloAbort.abort(); } catch (_) {}
        }
        this.hiloAbort = typeof AbortController !== 'undefined' ? new AbortController() : null;
      }

      try {
        const params = { tel: t };
        if (incremental && this.ultimoId > 0) params.after_id = this.ultimoId;
        if (incremental && this.lastSyncAt) params.updated_after = this.lastSyncAt;
        const { data } = await window.axios.get(this.urls.hilo, {
          params,
          signal: (!incremental && this.hiloAbort) ? this.hiloAbort.signal : undefined,
        });
        if (mySeq !== this.hiloSeq || String(this.telActivo) !== t) return;

        this.hiloMeta = {
          nombre: data.nombre || this.nombreDeLista(t),
          cliente_id: data.cliente_id,
          cliente_nombre: data.cliente_nombre,
          asunto: data.asunto || null,
          clasificacion: data.clasificacion,
          clasificacion_label: data.clasificacion_label,
          clasificacion_color: data.clasificacion_color,
          fuera_ventana: data.fuera_ventana,
          sugerencia_ia: data.sugerencia_ia || null,
          total: data.total,
          fallidos: data.fallidos,
          sin_leer: data.sin_leer,
        };

        const lote = Array.isArray(data.mensajes) ? data.mensajes : [];
        if (incremental && (this.ultimoId > 0 || this.lastSyncAt)) {
          const { added } = this.mergeMensajes(lote);
          if (added) this.$nextTick(() => this.scrollHilo(false));
        } else {
          this.mensajes = lote;
          this.$nextTick(() => {
            this.scrollHilo(true);
            this.hydrateMediaEnHilo();
          });
        }
        if (incremental) {
          this.$nextTick(() => this.hydrateMediaEnHilo());
        }
        if (data.ultimo_id) this.ultimoId = Math.max(this.ultimoId, data.ultimo_id);
        if (data.server_now) this.lastSyncAt = data.server_now;

        if (!incremental && this.puedeEditar && this.configured) {
          this.aplicarSugerenciaSiCorresponde();
          this.focusComposer();
        } else if (incremental) {
          this.aplicarSugerenciaSiCorresponde();
        }
      } catch (e) {
        if (e?.code === 'ERR_CANCELED' || e?.name === 'CanceledError' || e?.name === 'AbortError') return;
        if (!incremental && mySeq === this.hiloSeq) {
          this.showToast(e.response?.data?.error || e.response?.data?.message || 'No se pudo cargar el chat', false);
        }
      } finally {
        if (mySeq === this.hiloSeq) this.loadingHilo = false;
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
          String(c.telefono) === String(this.telActivo) ? { ...c, asunto: data.asunto || null } : c
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
            String(c.telefono) === String(tel) ? { ...c, sin_leer: 0 } : c
          ));
        }
      } catch (_) {
        // Silencioso
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
      if (this.loadingHilo || this.enviando || this.loadingLista) return;
      try {
        if (this.urls?.conversaciones) {
          const params = {
            buscar: this.buscar || undefined,
            limit: Math.max(this.pageLimit, this.conversaciones.length || this.pageLimit),
            offset: 0,
          };
          if (this.filtroAsuntoId !== null && this.filtroAsuntoId !== undefined) {
            params.asunto_id = this.filtroAsuntoId;
          }
          const { data } = await window.axios.get(this.urls.conversaciones, { params });
          const lote = Array.isArray(data?.conversaciones) ? data.conversaciones : [];
          if (!this.pageOffset || this.conversaciones.length <= this.pageLimit) {
            this.conversaciones = lote;
            this.tieneMas = !!data.has_more;
          } else {
            const byTel = new Map(lote.map((c) => [String(c.telefono), c]));
            this.conversaciones = this.conversaciones.map((c) => byTel.get(String(c.telefono)) || c);
            const existing = new Set(this.conversaciones.map((c) => String(c.telefono)));
            const nuevos = lote.filter((c) => !existing.has(String(c.telefono)));
            if (nuevos.length) this.conversaciones = [...nuevos, ...this.conversaciones];
          }
          this.totalChats = data.total ?? this.totalChats;
        }
      } catch (_) {
        // Poll silencioso
      }
      if (this.telActivo && !this.loadingHilo) {
        await this.cargarHilo(this.telActivo, true, this.hiloSeq);
      }
    },
    focusComposer() {
      this.$nextTick(() => {
        const el = this.$refs.composerInput;
        if (!el || this.hiloMeta.fuera_ventana) return;
        el.focus({ preventScroll: true });
        el.style.height = 'auto';
        el.style.height = `${Math.min(el.scrollHeight, 112)}px`;
      });
    },
    autoGrowComposer() {
      const el = this.$refs.composerInput;
      if (!el) return;
      el.style.height = 'auto';
      el.style.height = `${Math.min(el.scrollHeight, 112)}px`;
    },
    onComposerInput() {
      this.textoDesdeIa = false;
      this.autoGrowComposer();
    },
    aplicarSugerenciaSiCorresponde() {
      const sug = this.sugerenciaIa;
      if (!sug?.reply || !this.puedeEditar || this.hiloMeta.fuera_ventana) return;
      if (this.sugerenciaAplicadaId === sug.mensaje_id) return;
      const actual = (this.texto || '').trim();
      if (actual && !this.textoDesdeIa) return;
      this.texto = sug.reply;
      this.textoDesdeIa = true;
      this.sugerenciaAplicadaId = sug.mensaje_id;
      this.$nextTick(() => this.autoGrowComposer());
    },
    async descartarSugerencia() {
      const tel = this.telActivo;
      if (!tel || !this.urls.descartarSugerenciaIa) {
        this.hiloMeta = { ...this.hiloMeta, sugerencia_ia: null };
        if (this.textoDesdeIa) this.texto = '';
        this.textoDesdeIa = false;
        this.sugerenciaAplicadaId = null;
        return;
      }
      try {
        await window.axios.post(this.urls.descartarSugerenciaIa, { telefono: tel }, { headers: { Accept: 'application/json' } });
      } catch (_) {
        // Si falla el POST, igual la sacamos de la UI
      }
      this.hiloMeta = { ...this.hiloMeta, sugerencia_ia: null };
      if (this.textoDesdeIa) this.texto = '';
      this.textoDesdeIa = false;
      this.sugerenciaAplicadaId = null;
    },
    abrirSelectorAdjunto() {
      if (this.hiloMeta.fuera_ventana || this.enviando) return;
      this.$refs.fileInput?.click();
    },
    onAdjuntoSeleccionado(ev) {
      const file = ev.target?.files?.[0];
      if (ev.target) ev.target.value = '';
      if (!file) return;
      this.quitarAdjunto();
      const mime = (file.type || '').toLowerCase();
      let label = 'Archivo';
      if (mime.startsWith('image/')) label = 'Imagen';
      else if (mime === 'application/pdf' || /\.pdf$/i.test(file.name)) label = 'PDF';
      else if (mime.startsWith('video/')) label = 'Video';
      else if (mime.startsWith('audio/')) label = 'Audio';
      const previewUrl = mime.startsWith('image/') ? URL.createObjectURL(file) : null;
      this.adjuntoPendiente = {
        file,
        name: file.name,
        mime,
        label,
        previewUrl,
      };
      this.focusComposer();
    },
    quitarAdjunto() {
      if (this.adjuntoPendiente?.previewUrl) {
        try { URL.revokeObjectURL(this.adjuntoPendiente.previewUrl); } catch (_) {}
      }
      this.adjuntoPendiente = null;
    },
    async enviarTexto() {
      if (!this.telActivo || this.hiloMeta.fuera_ventana || this.enviando) return;
      if (this.adjuntoPendiente) {
        await this.enviarAdjunto();
        return;
      }
      const body = (this.texto || '').trim();
      if (!body) return;
      // Vaciar al toque y dejar el cursor listo para el siguiente mensaje
      this.texto = '';
      this.focusComposer();
      this.enviando = true;
      try {
        const { data } = await window.axios.post(this.urls.enviar, {
          telefono: this.telActivo,
          modo: 'texto',
          texto: body,
        }, { headers: { Accept: 'application/json' } });
        if (data.mensaje) {
          this.mergeMensajes([data.mensaje]);
          this.ultimoId = Math.max(this.ultimoId, data.mensaje.id);
          this.$nextTick(() => this.scrollHilo(true));
        } else {
          await this.cargarHilo(this.telActivo, false);
        }
        this.hiloMeta = { ...this.hiloMeta, sugerencia_ia: null };
        this.textoDesdeIa = false;
        this.sugerenciaAplicadaId = null;
        await this.marcarLeidos(this.telActivo);
        await this.cargarConversaciones(true);
      } catch (e) {
        const msg = e.response?.data?.error || e.response?.data?.message || 'Falló el envío';
        this.showToast(msg, false);
        const fallido = e.response?.data?.mensaje;
        if (fallido) {
          this.mergeMensajes([fallido]);
          this.ultimoId = Math.max(this.ultimoId, fallido.id);
          this.$nextTick(() => this.scrollHilo(true));
        } else if (!(this.texto || '').trim()) {
          this.texto = body;
          this.autoGrowComposer();
        }
        await this.cargarConversaciones(true);
      } finally {
        this.enviando = false;
        this.focusComposer();
      }
    },
    async enviarAdjunto() {
      const adj = this.adjuntoPendiente;
      if (!adj?.file || !this.telActivo || !this.urls.enviarAdjunto || this.hiloMeta.fuera_ventana || this.enviando) return;
      const caption = (this.texto || '').trim();
      const form = new FormData();
      form.append('telefono', this.telActivo);
      form.append('archivo', adj.file, adj.name);
      if (caption) form.append('caption', caption);

      this.texto = '';
      this.quitarAdjunto();
      this.focusComposer();
      this.enviando = true;
      try {
        const { data } = await window.axios.post(this.urls.enviarAdjunto, form, {
          headers: { Accept: 'application/json' },
        });
        if (data.mensaje) {
          this.mergeMensajes([data.mensaje]);
          this.ultimoId = Math.max(this.ultimoId, data.mensaje.id);
          this.$nextTick(() => this.scrollHilo(true));
        } else {
          await this.cargarHilo(this.telActivo, false);
        }
        await this.marcarLeidos(this.telActivo);
        await this.cargarConversaciones(true);
      } catch (e) {
        const msg = e.response?.data?.error || e.response?.data?.message || 'Falló el envío del archivo';
        this.showToast(msg, false);
        const fallido = e.response?.data?.mensaje;
        if (fallido) {
          this.mergeMensajes([fallido]);
          this.ultimoId = Math.max(this.ultimoId, fallido.id);
          this.$nextTick(() => this.scrollHilo(true));
        }
        await this.cargarConversaciones(true);
      } finally {
        this.enviando = false;
        this.focusComposer();
      }
    },
    async reintentar(m) {
      if (!confirm(`¿Reintentar envío del mensaje #${m.id}?`)) return;
      this.reintentandoId = m.id;
      const url = (this.urls.reintentarTpl || '').replace('__ID__', m.id);
      try {
        const { data } = await window.axios.post(url, {}, { headers: { Accept: 'application/json' } });
        this.showToast(`Reenviado (#${m.id} -> #${data.mensaje?.id || '?'})`, true);
        await this.cargarHilo(this.telActivo, false);
        await this.cargarConversaciones(true);
      } catch (e) {
        this.showToast(e.response?.data?.error || 'No se pudo reintentar', false);
        await this.cargarHilo(this.telActivo, false);
      } finally {
        this.reintentandoId = null;
      }
    },
    revokeMedia(id) {
      if (!id || !this.mediaObjectUrls[id]) return;
      try { URL.revokeObjectURL(this.mediaObjectUrls[id]); } catch (_) {}
      const urls = { ...this.mediaObjectUrls };
      delete urls[id];
      this.mediaObjectUrls = urls;
    },
    async eliminarMensaje(m) {
      if (!this.puedeEditar || !m?.id) return;
      if (!confirm('¿Eliminar este mensaje de Infinity?\nNo se borra en el WhatsApp del cliente.')) return;
      this.eliminandoId = m.id;
      const url = (this.urls.eliminarMensajeTpl || '/whatsapp/mensajes/__ID__').replace('__ID__', m.id);
      try {
        await window.axios.delete(url, { headers: { Accept: 'application/json' } });
        this.revokeMedia(m.id);
        this.mensajes = this.mensajes.filter((x) => x.id !== m.id);
        this.showToast('Mensaje eliminado de Infinity', true);
        await this.cargarConversaciones(true);
      } catch (e) {
        this.showToast(e.response?.data?.error || e.response?.data?.message || 'No se pudo eliminar el mensaje', false);
      } finally {
        this.eliminandoId = null;
      }
    },
    async eliminarChat() {
      if (!this.puedeEditar || !this.telActivo || !this.urls.eliminarConversacion) return;
      const nombre = this.hiloMeta.nombre || this.telActivo;
      if (!confirm(`¿Eliminar todo el chat de ${nombre}?\nSe borra solo en Infinity, no en el WhatsApp del cliente.`)) return;
      this.eliminandoChat = true;
      const tel = this.telActivo;
      try {
        const { data } = await window.axios.delete(this.urls.eliminarConversacion, {
          params: { telefono: tel },
          data: { telefono: tel },
          headers: { Accept: 'application/json' },
        });
        this.mensajes.forEach((m) => this.revokeMedia(m.id));
        this.cerrarChat();
        this.showToast(`Chat eliminado (${data.borrados ?? 0} mensajes)`, true);
        await this.cargarConversaciones(true);
      } catch (e) {
        this.showToast(e.response?.data?.error || e.response?.data?.message || 'No se pudo eliminar el chat', false);
      } finally {
        this.eliminandoChat = false;
      }
    },

    // —— Modales rápidos / GPS ——
    captionMedia(m) {
      const c = String(m?.cuerpo || '').trim();
      if (!c) return '';
      const genericos = ['imagen', 'image', 'audio', 'video', 'documento', 'document', 'sticker', '—', '-'];
      return genericos.includes(c.toLowerCase()) ? '' : c;
    },
    mediaSrc(m) {
      if (!m?.id) return null;
      return this.mediaObjectUrls[m.id] || null;
    },
    onMediaLoaded(m) {
      if (!m?.id) return;
      if (this.mediaFailed[m.id]) {
        const next = { ...this.mediaFailed };
        delete next[m.id];
        this.mediaFailed = next;
      }
    },
    onMediaImgError(m) {
      if (!m?.id || this.mediaFailed[m.id]) return;
      this.mediaFailed = { ...this.mediaFailed, [m.id]: true };
    },
    async hydrateMedia(m, force = false) {
      if (!m?.id || !m.media_url) return;
      if (!force && (this.mediaObjectUrls[m.id] || this.mediaHydrating[m.id])) return;
      if (force && this.mediaObjectUrls[m.id]) {
        try { URL.revokeObjectURL(this.mediaObjectUrls[m.id]); } catch (_) {}
        const urls = { ...this.mediaObjectUrls };
        delete urls[m.id];
        this.mediaObjectUrls = urls;
      }
      this.mediaHydrating = { ...this.mediaHydrating, [m.id]: true };
      const failed = { ...this.mediaFailed };
      delete failed[m.id];
      this.mediaFailed = failed;
      try {
        const res = await window.axios.get(m.media_url, {
          responseType: 'blob',
          withCredentials: true,
          headers: {
            Accept: m.media_mime || 'application/octet-stream,*/*',
            'X-Requested-With': 'XMLHttpRequest',
          },
        });
        const data = res.data;
        if (!(data instanceof Blob) || data.size < 32) {
          throw new Error('empty');
        }
        if ((data.type || '').includes('text/html') || (data.type || '').includes('application/json')) {
          throw new Error('not-media');
        }
        const head = new Uint8Array(await data.slice(0, 16).arrayBuffer());
        const esJpeg = head[0] === 0xFF && head[1] === 0xD8 && head[2] === 0xFF;
        const esPng = head[0] === 0x89 && head[1] === 0x50 && head[2] === 0x4E;
        const esGif = head[0] === 0x47 && head[1] === 0x49 && head[2] === 0x46;
        const esWebp = head[0] === 0x52 && head[1] === 0x49 && head[2] === 0x46 && head[3] === 0x46;
        if (m.tipo === 'image' && !esJpeg && !esPng && !esGif && !esWebp) {
          throw new Error('not-image');
        }
        let blob = data;
        if (m.tipo === 'image') {
          const mime = esPng ? 'image/png' : (esGif ? 'image/gif' : (esWebp ? 'image/webp' : 'image/jpeg'));
          blob = new Blob([data], { type: mime });
        } else if ((!data.type || data.type === 'application/octet-stream') && m.tipo === 'audio') {
          blob = new Blob([data], { type: m.media_mime || 'audio/ogg' });
        }
        this.mediaObjectUrls = {
          ...this.mediaObjectUrls,
          [m.id]: URL.createObjectURL(blob),
        };
      } catch (e) {
        console.warn('[WA media] falló', m.id, e?.response?.status || e?.message);
        this.mediaFailed = { ...this.mediaFailed, [m.id]: true };
      } finally {
        const hyd = { ...this.mediaHydrating };
        delete hyd[m.id];
        this.mediaHydrating = hyd;
      }
    },
    hydrateMediaEnHilo() {
      (this.mensajes || []).forEach((m) => {
        if (m?.media_url && ['audio', 'video', 'document', 'sticker'].includes(m.tipo)) {
          this.hydrateMedia(m, false);
        }
      });
    },
    async abrirMediaAdjunto(m) {
      await this.hydrateMedia(m, false);
      const src = this.mediaSrc(m) || m.media_url;
      if (src) window.open(src, '_blank', 'noopener');
    },
    abrirModalImagen(m) {
      if (!m?.media_url) return;
      this.modalImagen = {
        url: this.mediaSrc(m) || m.media_url,
        caption: this.captionMedia(m) || 'Imagen',
      };
    },
    cerrarModalImagen() {
      this.modalImagen = null;
    },
    abrirModalContacto() {
      if (!this.telActivo || !this.puedeEditar) return;
      this.modalError = null;
      this.contactoClientes = [];
      this.contactoForm = {
        nombre: this.hiloMeta.nombre || '',
        buscar: '',
        cliente_id: this.hiloMeta.cliente_id || null,
        cliente_label: this.hiloMeta.cliente_nombre || '',
        quitar_cliente: false,
      };
      this.modalContacto = true;
    },
    cerrarModalContacto() {
      this.modalContacto = false;
      this.modalError = null;
      this.contactoClientes = [];
      this.contactoBuscando = false;
      if (this.contactoBuscarTimer) clearTimeout(this.contactoBuscarTimer);
    },
    onBuscarClienteContacto() {
      if (this.contactoBuscarTimer) clearTimeout(this.contactoBuscarTimer);
      const q = (this.contactoForm.buscar || '').trim();
      if (q.length < 2) {
        this.contactoClientes = [];
        this.contactoBuscando = false;
        return;
      }
      this.contactoBuscando = true;
      this.contactoBuscarTimer = setTimeout(async () => {
        try {
          const url = this.urls.buscarClienteContacto || this.urls.buscarCliente || '/whatsapp/conversacion/buscar-cliente';
          const { data } = await window.axios.get(url, {
            params: { q },
            headers: { Accept: 'application/json' },
          });
          this.contactoClientes = Array.isArray(data) ? data : [];
        } catch (_) {
          this.contactoClientes = [];
        } finally {
          this.contactoBuscando = false;
        }
      }, 280);
    },
    elegirClienteContacto(c) {
      if (!c?.cliente_id) return;
      this.contactoForm.cliente_id = c.cliente_id;
      this.contactoForm.cliente_label = `${c.nombre || ''} ${c.apellido || ''}`.trim();
      this.contactoForm.quitar_cliente = false;
      this.contactoForm.buscar = '';
      this.contactoClientes = [];
      if (!(this.contactoForm.nombre || '').trim() && this.contactoForm.cliente_label) {
        this.contactoForm.nombre = this.contactoForm.cliente_label;
      }
    },
    limpiarClienteContacto() {
      this.contactoForm.cliente_id = null;
      this.contactoForm.cliente_label = '';
      this.contactoForm.quitar_cliente = !!this.hiloMeta.cliente_id;
    },
    async guardarContacto() {
      if (!this.telActivo || !this.urls.guardarContacto || !this.puedeGuardarContacto || this.guardandoContacto) return;
      this.guardandoContacto = true;
      this.modalError = null;
      try {
        const payload = {
          telefono: this.telActivo,
          nombre: (this.contactoForm.nombre || '').trim() || null,
          cliente_id: this.contactoForm.cliente_id || null,
          quitar_cliente: !!this.contactoForm.quitar_cliente && !this.contactoForm.cliente_id,
        };
        const { data } = await window.axios.post(this.urls.guardarContacto, payload, {
          headers: { Accept: 'application/json' },
        });
        this.hiloMeta = {
          ...this.hiloMeta,
          nombre: data.nombre || this.hiloMeta.nombre,
          cliente_id: data.cliente_id || null,
          cliente_nombre: data.cliente_nombre || null,
        };
        this.conversaciones = this.conversaciones.map((c) => (
          String(c.telefono) === String(this.telActivo)
            ? {
              ...c,
              nombre: data.nombre || c.nombre,
              cliente_id: data.cliente_id || null,
              cliente_nombre: data.cliente_nombre || null,
            }
            : c
        ));
        this.showToast('Contacto guardado', true);
        this.cerrarModalContacto();
      } catch (e) {
        this.modalError = e.response?.data?.error || e.response?.data?.message || 'No se pudo guardar el contacto';
      } finally {
        this.guardandoContacto = false;
      }
    },
    embedMapUrl(lat, lng) {
      const la = Number(lat);
      const ln = Number(lng);
      if (Number.isNaN(la) || Number.isNaN(ln)) return null;
      const d = 0.008;
      return `https://www.openstreetmap.org/export/embed.html?bbox=${ln - d}%2C${la - d}%2C${ln + d}%2C${la + d}&layer=mapnik&marker=${la}%2C${ln}`;
    },
    ultimaUbicacionHilo() {
      for (let i = this.mensajes.length - 1; i >= 0; i -= 1) {
        const m = this.mensajes[i];
        if (m?.tipo === 'location' && m.maps_lat != null && m.maps_lng != null) return m;
      }
      return null;
    },
    abrirModalMapa(m) {
      const lat = m.maps_lat;
      const lng = m.maps_lng;
      this.modalMapa = {
        lat,
        lng,
        nombre: m.maps_nombre || 'Ubicación compartida',
        direccion: m.maps_direccion || '',
        url: m.maps_url || `https://www.google.com/maps?q=${lat},${lng}`,
        embed: this.embedMapUrl(lat, lng),
      };
    },
    cerrarModalMapa() {
      this.modalMapa = null;
    },
    usarMapaEnPedido() {
      if (!this.modalMapa || !this.puedeCrearPedido) return;
      const snap = { ...this.modalMapa };
      this.cerrarModalMapa();
      this.abrirModalPedido({
        lat: snap.lat,
        lon: snap.lng,
        maps_gps: snap.url,
        ubicacion: snap.direccion || snap.nombre || '',
      });
    },
    async cargarMetaRapido() {
      if (!this.urls.rapidoMeta) {
        this.showToast('Falta URL de meta rápido', false);
        return null;
      }
      this.metaCargando = true;
      this.modalError = null;
      try {
        const { data } = await window.axios.get(this.urls.rapidoMeta, {
          params: {
            telefono: this.telActivo || undefined,
            cliente_id: this.hiloMeta.cliente_id || undefined,
          },
          headers: { Accept: 'application/json' },
        });
        this.ticketAsuntos = data.ticket_asuntos || [];
        this.tecnicos = data.tecnicos || [];
        return data;
      } catch (e) {
        this.showToast(e.response?.data?.message || 'No se pudieron cargar datos', false);
        return null;
      } finally {
        this.metaCargando = false;
      }
    },
    async abrirModalTicket() {
      if (!this.puedeCrearTicket || !this.telActivo) return;
      this.modalTicket = true;
      this.modalError = null;
      const meta = await this.cargarMetaRapido();
      const c = meta?.cliente;
      const label = c
        ? `${c.nombre || ''}${c.apellido ? ` ${c.apellido}` : ''}`.trim()
        : (this.hiloMeta.cliente_nombre || this.hiloMeta.nombre || '');
      this.ticketForm = {
        cliente_id: c?.cliente_id || this.hiloMeta.cliente_id || null,
        cliente_label: label,
        ticket_asunto_id: '',
        prioridad: 'media',
        asignado_id: '',
        descripcion: '',
      };
    },
    cerrarModalTicket() {
      if (this.guardandoRapido) return;
      this.modalTicket = false;
      this.modalError = null;
    },
    clienteDetalleUrl(id) {
      const tpl = this.urls.clienteDetalleTpl || '/clientes/__ID__/detalle';
      return tpl.replace('__ID__', String(id));
    },
    formatGs(n) {
      const v = Number(n || 0);
      return new Intl.NumberFormat('es-PY', { style: 'currency', currency: 'PYG', maximumFractionDigits: 0 }).format(v);
    },
    async abrirModalCobro() {
      if (!this.puedeCrearCobro || !this.telActivo) return;
      this.modalCobro = true;
      this.modalError = null;
      this.cobroResultado = null;
      this.metaCargando = true;
      try {
        if (!this.urls.rapidoCobroMeta) {
          throw new Error('Falta URL de cobro rápido');
        }
        const { data } = await window.axios.get(this.urls.rapidoCobroMeta, {
          params: {
            telefono: this.telActivo || undefined,
            cliente_id: this.hiloMeta.cliente_id || undefined,
          },
          headers: { Accept: 'application/json' },
        });
        const c = data.cliente;
        this.cobroFacturas = data.facturas || [];
        this.cobroFormasPago = data.formas_pago || { efectivo: 'Efectivo' };
        this.cobroTotalPendiente = Number(data.total_pendiente || 0);
        const ids = this.cobroFacturas.map((f) => f.id);
        this.cobroForm = {
          cliente_id: c?.cliente_id || null,
          cliente_label: c
            ? `${c.nombre || ''}${c.apellido ? ` ${c.apellido}` : ''}`.trim()
            : (this.hiloMeta.cliente_nombre || ''),
          cedula: c?.cedula || '',
          cliente_url: c?.url || (c?.cliente_id ? this.clienteDetalleUrl(c.cliente_id) : ''),
          factura_ids: ids.slice(),
          monto: this.cobroTotalPendiente || null,
          fecha_pago: data.hoy || new Date().toISOString().slice(0, 10),
          forma_pago: 'efectivo',
          referencia: '',
          observaciones: '',
        };
        if (!c?.cliente_id) {
          this.modalError = data.message || 'Vinculá un cliente para registrar el pago.';
        }
      } catch (e) {
        this.modalError = e.response?.data?.message || e.message || 'No se pudieron cargar facturas';
        this.cobroFacturas = [];
      } finally {
        this.metaCargando = false;
      }
    },
    cerrarModalCobro() {
      if (this.guardandoRapido) return;
      this.modalCobro = false;
      this.modalError = null;
      this.cobroResultado = null;
    },
    onCobroFacturasChange() {
      const selected = this.cobroFacturas.filter((f) => this.cobroForm.factura_ids.includes(f.id));
      this.cobroForm.monto = Math.round(selected.reduce((s, f) => s + Number(f.saldo_pendiente || 0), 0) * 100) / 100;
    },
    seleccionarTodasFacturasCobro() {
      this.cobroForm.factura_ids = this.cobroFacturas.map((f) => f.id);
      this.onCobroFacturasChange();
    },
    async guardarCobroRapido() {
      if (!this.cobroForm.cliente_id || !this.cobroForm.factura_ids.length || this.guardandoRapido) return;
      this.guardandoRapido = true;
      this.modalError = null;
      this.cobroResultado = null;
      try {
        const { data } = await window.axios.post(this.urls.rapidoCobro, {
          cliente_id: this.cobroForm.cliente_id,
          factura_interna_ids: this.cobroForm.factura_ids,
          monto: Number(this.cobroForm.monto),
          fecha_pago: this.cobroForm.fecha_pago,
          forma_pago: this.cobroForm.forma_pago,
          referencia: this.cobroForm.referencia || null,
          observaciones: this.cobroForm.observaciones || null,
          concepto: 'Cobro desde WhatsApp',
        }, { headers: { Accept: 'application/json' } });
        this.cobroResultado = {
          message: data.message || 'Cobro registrado',
          url: data.url || null,
        };
        this.showToast(data.message || 'Cobro registrado', true);
      } catch (e) {
        this.modalError = e.response?.data?.message
          || (e.response?.data?.errors && Object.values(e.response.data.errors).flat()[0])
          || 'No se pudo registrar el cobro';
      } finally {
        this.guardandoRapido = false;
      }
    },
    async guardarTicketRapido() {
      if (!this.ticketForm.ticket_asunto_id || this.guardandoRapido) return;
      this.guardandoRapido = true;
      this.modalError = null;
      try {
        const { data } = await window.axios.post(this.urls.rapidoTicket, {
          cliente_id: this.ticketForm.cliente_id || null,
          ticket_asunto_id: Number(this.ticketForm.ticket_asunto_id),
          prioridad: this.ticketForm.prioridad || 'media',
          asignado_id: this.ticketForm.asignado_id || null,
          descripcion: this.ticketForm.descripcion || null,
          telefono: this.telActivo,
        }, { headers: { Accept: 'application/json' } });
        this.modalTicket = false;
        this.showToast(data.message || 'Ticket creado', true);
      } catch (e) {
        this.modalError = e.response?.data?.message
          || (e.response?.data?.errors && Object.values(e.response.data.errors).flat()[0])
          || 'No se pudo crear el ticket';
      } finally {
        this.guardandoRapido = false;
      }
    },
    async abrirModalPedido(extra = {}) {
      if (!this.puedeCrearPedido || !this.telActivo) return;
      if (!this.pedidoFormConfig) {
        this.showToast('Formulario de pedido no disponible', false);
        return;
      }

      let cliente = null;
      try {
        const meta = await this.cargarMetaRapido();
        cliente = meta?.cliente || null;
      } catch (_) { /* opcional */ }

      const loc = this.ultimaUbicacionHilo();
      this.pedidoInitialValues = {
        cedula: cliente?.cedula || '',
        cliente_id: cliente?.cliente_id || this.hiloMeta.cliente_id || null,
        nombre: cliente?.nombre || '',
        apellido: cliente?.apellido || '',
        telefono: cliente?.telefono || this.telActivo || '',
        ubicacion: extra.ubicacion || loc?.maps_direccion || loc?.maps_nombre || '',
        maps_gps: extra.maps_gps || loc?.maps_url || '',
        lat: extra.lat != null ? extra.lat : (loc?.maps_lat ?? null),
        lon: extra.lon != null ? extra.lon : (loc?.maps_lng ?? null),
      };
      this.pedidoFormKey += 1;
      this.modalPedido = true;
    },
    cerrarModalPedido() {
      this.modalPedido = false;
    },
  },
};
</script>
