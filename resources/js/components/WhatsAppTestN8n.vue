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
      <aside class="wa-sidebar flex min-h-0 w-full flex-col md:w-[320px] md:shrink-0 md:border-r">
        <div class="wa-header px-4 py-3">
          <p class="wa-title text-sm font-medium">Número inventado</p>
          <p class="wa-muted mt-0.5 text-[11px]">Empieza con 595000 · nunca se manda a Meta</p>
        </div>
        <div class="wa-sidebar-body space-y-2 px-3 py-3">
          <input
            v-model="telInput"
            type="text"
            inputmode="numeric"
            placeholder="595000000001"
            class="wa-input w-full rounded-lg border-0 px-3 py-2 font-mono text-sm focus:ring-0"
            @keydown.enter.prevent="usarNumero"
          >
          <div class="flex flex-wrap gap-1.5">
            <button type="button" class="wa-chip wa-chip-on rounded-full px-3 py-1 text-[11px] font-semibold" @click="usarNumero">
              Usar número
            </button>
            <button type="button" class="wa-chip rounded-full px-3 py-1 text-[11px] font-semibold" @click="nuevoNumero">
              Otro número
            </button>
            <button
              type="button"
              class="rounded-full bg-amber-500 px-3 py-1 text-[11px] font-semibold text-white disabled:opacity-40"
              :disabled="enviando"
              @click="ubicacionAprobada"
            >
              Ubicación aprobada
            </button>
            <button type="button" class="wa-chip rounded-full px-3 py-1 text-[11px] font-semibold text-rose-700" :disabled="borrando" @click="borrarHilo">
              {{ borrando ? '...' : 'Borrar hilo' }}
            </button>
          </div>
          <p class="wa-muted text-[11px]">Activo: <span class="font-mono">{{ telActivo }}</span></p>
        </div>
        <div class="wa-list min-h-0 flex-1 overflow-y-auto border-t">
          <button
            v-for="r in recientes"
            :key="r.telefono"
            type="button"
            class="wa-row block w-full border-b px-3 py-2.5 text-left"
            :class="telActivo === r.telefono ? 'wa-row-active' : ''"
            @click="abrirReciente(r.telefono)"
          >
            <p class="wa-title font-mono text-[13px]">{{ r.telefono }}</p>
            <p class="wa-muted text-[11px]">{{ r.ultimo_at || '' }}</p>
          </button>
          <p v-if="!recientes.length" class="wa-muted px-3 py-6 text-center text-xs">Todavía no hay pruebas.</p>
        </div>
      </aside>

      <section class="flex min-h-0 min-w-0 flex-1 flex-col">
        <div class="wa-header flex items-center gap-3 px-4 py-3">
          <div class="min-w-0 flex-1">
            <p class="wa-title truncate text-sm font-medium">Cliente de prueba</p>
            <p class="wa-muted truncate font-mono text-[11px]">{{ telActivo }}</p>
          </div>
          <span v-if="ultimo.modo" class="rounded-full bg-emerald-600 px-2.5 py-0.5 text-[11px] font-semibold text-white">
            {{ ultimo.modo }}
          </span>
        </div>

        <div ref="scrollEl" class="wa-wallpaper min-h-0 flex-1 overflow-y-auto px-3 py-3 sm:px-8">
          <div v-if="loadingHilo && !mensajes.length" class="wa-muted py-16 text-center text-sm">Cargando hilo...</div>
          <div
            v-for="m in mensajes"
            :key="m.id"
            class="mb-1.5 flex"
            :class="m.direccion === 'salida' ? 'justify-end' : 'justify-start'"
          >
            <div
              class="wa-bubble relative max-w-[85%] px-2.5 py-1.5 shadow-sm sm:max-w-[70%]"
              :class="m.direccion === 'salida' ? 'wa-bubble-out' : 'wa-bubble-in'"
            >
              <p class="wa-msg-body whitespace-pre-wrap text-[14.2px] leading-5">{{ m.cuerpo }}</p>
              <p class="wa-muted mt-0.5 text-right text-[10px]">
                {{ m.hora }}
                <span v-if="m.direccion === 'salida'"> · agente</span>
              </p>
            </div>
          </div>
          <div v-if="enviando" class="mb-1.5 flex justify-end">
            <div class="wa-bubble wa-bubble-out relative max-w-[70%] px-2.5 py-1.5 text-[13px] opacity-70">
              Pensando...
            </div>
          </div>
          <p v-if="!loadingHilo && !mensajes.length" class="wa-muted py-16 text-center text-sm">
            Escribí como si fueras el cliente. El agente responde acá, sin WhatsApp real.
          </p>
        </div>

        <div v-if="ultimo.error" class="wa-warn border-t px-4 py-2 text-xs">
          n8n: {{ ultimo.error }}
        </div>
        <div v-else-if="ultimo.n8n_latency_ms" class="wa-muted border-t px-4 py-1.5 text-[11px]">
          {{ ultimo.n8n_latency_ms }} ms
          <template v-if="ultimo.escalate"> · escala {{ ultimo.motivo_escalado || '' }}</template>
          <template v-if="flagsLabel"> · {{ flagsLabel }}</template>
        </div>

        <div class="wa-sidebar-body flex flex-wrap gap-1.5 border-t px-3 py-2">
          <button
            v-for="atajo in atajos"
            :key="atajo.label"
            type="button"
            class="wa-chip rounded-full px-2.5 py-0.5 text-[11px] font-medium"
            :disabled="enviando"
            @click="enviar(atajo.text)"
          >{{ atajo.label }}</button>
        </div>

        <form class="wa-composer border-t px-3 py-2.5" @submit.prevent="enviar()">
          <div class="flex items-end gap-2">
            <textarea
              v-model="draft"
              rows="1"
              class="wa-input max-h-32 min-h-[40px] flex-1 resize-none rounded-lg border-0 px-3 py-2 text-sm focus:ring-0"
              placeholder="Mensaje del cliente..."
              :disabled="enviando"
              @keydown.enter.exact.prevent="enviar()"
            />
            <button
              type="submit"
              class="wa-send flex h-10 w-10 shrink-0 items-center justify-center rounded-full disabled:opacity-40"
              :disabled="enviando || !draft.trim()"
            >
              <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z" />
              </svg>
            </button>
          </div>
        </form>
      </section>
    </div>
  </div>
</template>

<script>
const STORAGE_KEY = 'infinity.wa.testN8n.tel';
const ATAJOS = [
  { label: 'Hola', text: 'Hola' },
  { label: 'Quiero wifi', text: 'Hola, quiero wifi' },
  { label: 'Ubicación', text: 'Tres de Mayo https://share.google/lbSE4qK4z27koOCwX' },
  { label: 'Cédula', text: '75616117' },
  { label: 'Plan 20', text: 'Cuánto sale el de 20 megas?' },
  { label: 'Beneficios', text: 'Qué beneficios tiene el estándar y el premium?' },
  { label: 'Transferencia', text: 'A qué cuenta transfiero?' },
  { label: 'Condiciones', text: 'Cuáles son las condiciones del servicio?' },
  { label: 'Ya pagué', text: 'Ya pagué, les mando el comprobante' },
];

export default {
  name: 'WhatsAppTestN8n',
  props: {
    telInicial: { type: String, default: '595000000001' },
    urls: { type: Object, default: () => ({}) },
  },
  data() {
    const saved = typeof localStorage !== 'undefined' ? localStorage.getItem(STORAGE_KEY) : '';
    const tel = saved || this.telInicial || '595000000001';
    return {
      telInput: tel,
      telActivo: tel,
      mensajes: [],
      recientes: [],
      draft: '',
      loadingHilo: false,
      enviando: false,
      borrando: false,
      toast: null,
      ultimo: {},
      flags: {},
      atajos: ATAJOS,
    };
  },
  computed: {
    flagsLabel() {
      const on = Object.entries(this.flags || {})
        .filter(([, v]) => v === true)
        .map(([k]) => k.replace('parece_', ''));
      return on.join(', ');
    },
  },
  mounted() {
    this.cargarHilo();
  },
  methods: {
    csrf() {
      const el = document.head.querySelector('meta[name="csrf-token"]');
      return el ? el.content : '';
    },
    mostrarToast(text, ok = true) {
      this.toast = { text, ok };
      window.setTimeout(() => {
        if (this.toast && this.toast.text === text) this.toast = null;
      }, 4000);
    },
    aplicarPayload(data) {
      if (data.telefono) {
        this.telActivo = data.telefono;
        this.telInput = data.telefono;
        try { localStorage.setItem(STORAGE_KEY, data.telefono); } catch (_) { /* ignore */ }
        if (window.history && window.history.replaceState) {
          const url = new URL(window.location.href);
          url.searchParams.set('tel', data.telefono);
          window.history.replaceState({}, '', url.toString());
        }
      }
      if (Array.isArray(data.mensajes)) this.mensajes = data.mensajes;
      if (Array.isArray(data.recientes)) this.recientes = data.recientes;
      if (data.agent) this.ultimo = data.agent;
      if (data.flags) this.flags = data.flags;
      this.$nextTick(() => this.scrollBottom());
    },
    scrollBottom() {
      const el = this.$refs.scrollEl;
      if (el) el.scrollTop = el.scrollHeight;
    },
    async cargarHilo() {
      this.loadingHilo = true;
      try {
        const { data } = await window.axios.get(this.urls.hilo, {
          params: { tel: this.telActivo },
        });
        this.aplicarPayload(data);
        this.ultimo = {};
        this.flags = {};
      } catch (e) {
        this.mostrarToast('No pude cargar el hilo', false);
      } finally {
        this.loadingHilo = false;
      }
    },
    usarNumero() {
      this.telActivo = (this.telInput || '').replace(/\D+/g, '') || '595000000001';
      this.cargarHilo();
    },
    nuevoNumero() {
      const n = '595000' + String(Math.floor(100000 + Math.random() * 900000));
      this.telInput = n;
      this.telActivo = n;
      this.mensajes = [];
      this.ultimo = {};
      this.flags = {};
      this.cargarHilo();
    },
    abrirReciente(tel) {
      this.telInput = tel;
      this.telActivo = tel;
      this.cargarHilo();
    },
    async ubicacionAprobada() {
      if (this.enviando) return;
      this.enviando = true;
      try {
        const { data } = await window.axios.post(
          this.urls.ubicacionAprobada,
          { telefono: this.telActivo },
          { timeout: 40000, headers: { 'X-CSRF-TOKEN': this.csrf() } },
        );
        this.aplicarPayload(data);
        if (data.error) this.mostrarToast(data.error, false);
        else this.mostrarToast('Cobertura aprobada: el agente sigue con los planes');
      } catch (e) {
        const msg = e.response?.data?.error || e.response?.data?.message || e.message || 'Error al llamar n8n';
        this.mostrarToast(msg, false);
      } finally {
        this.enviando = false;
        this.$nextTick(() => this.scrollBottom());
      }
    },
    async borrarHilo() {
      if (!window.confirm('¿Borrar este hilo de prueba?')) return;
      this.borrando = true;
      try {
        const { data } = await window.axios.delete(this.urls.borrar, {
          data: { telefono: this.telActivo },
          headers: { 'X-CSRF-TOKEN': this.csrf() },
        });
        this.mensajes = [];
        this.ultimo = {};
        this.flags = {};
        if (Array.isArray(data.recientes)) this.recientes = data.recientes;
        this.mostrarToast('Hilo borrado');
      } catch (e) {
        this.mostrarToast('No pude borrar el hilo', false);
      } finally {
        this.borrando = false;
      }
    },
    async enviar(texto) {
      const mensaje = String(texto != null ? texto : this.draft).trim();
      if (!mensaje || this.enviando) return;
      this.enviando = true;
      if (texto == null) this.draft = '';
      try {
        const { data } = await window.axios.post(
          this.urls.mensaje,
          { telefono: this.telActivo, mensaje },
          { timeout: 40000, headers: { 'X-CSRF-TOKEN': this.csrf() } },
        );
        this.aplicarPayload(data);
        if (data.error) this.mostrarToast(data.error, false);
      } catch (e) {
        const msg = e.response?.data?.error || e.response?.data?.message || e.message || 'Error al llamar n8n';
        this.mostrarToast(msg, false);
      } finally {
        this.enviando = false;
        this.$nextTick(() => this.scrollBottom());
      }
    },
  },
};
</script>
