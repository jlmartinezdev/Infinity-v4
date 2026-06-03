<template>
    <div class="max-w-6xl mx-auto">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
            <div>
                <a :href="urlIndex" class="text-sm text-purple-600 dark:text-purple-400 hover:underline mb-1 inline-block">← Volver a nodos</a>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">Migrar / copiar PPPoE</h1>
                <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                    Nodo: <span class="font-medium">{{ nodo.descripcion || ('#' + nodo.nodo_id) }}</span>
                </p>
            </div>
        </div>

        <div v-if="!canEditar" class="mb-4 p-4 rounded-lg bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 text-amber-800 dark:text-amber-200 text-sm">
            Solo tenés permiso de lectura. Necesitás «referenciales.editar» para ejecutar la migración.
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 p-6 space-y-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Modo</label>
                    <select v-model="modo" class="w-full py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                        <option value="mover">Mover (quita del RB origen, actualiza servicio y sincroniza destino)</option>
                        <option value="copiar">Copiar (crea en RB destino sin quitar origen ni cambiar BD)</option>
                    </select>
                </div>
                <div v-if="modo === 'mover'">
                    <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300 mt-8 cursor-pointer">
                        <input v-model="asignarIpAutomatica" type="checkbox" class="rounded border-gray-300 text-purple-600 focus:ring-purple-500" />
                        Asignar IP automática del pool destino
                    </label>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Router origen (este nodo)</label>
                    <select
                        v-model="routerOrigenId"
                        class="w-full py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100"
                        @change="onRouterOrigenChange"
                    >
                        <option value="">— Seleccionar —</option>
                        <option v-for="r in routersNodo" :key="r.router_id" :value="String(r.router_id)">{{ r.label }}</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Router destino</label>
                    <select
                        v-model="routerDestinoId"
                        class="w-full py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100"
                        @change="onRouterDestinoChange"
                    >
                        <option value="">— Seleccionar —</option>
                        <option v-for="r in routersTodos" :key="'d-' + r.router_id" :value="String(r.router_id)">{{ r.label }}</option>
                    </select>
                </div>
            </div>

            <div v-if="modo === 'mover'" class="max-w-xl">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Pool destino</label>
                <select
                    v-model="poolDestinoId"
                    :disabled="!routerDestinoId || cargandoPools"
                    class="w-full py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 disabled:opacity-50"
                >
                    <option value="">{{ cargandoPools ? 'Cargando pools…' : '— Seleccionar pool —' }}</option>
                    <option v-for="p in poolsDestino" :key="p.pool_id" :value="String(p.pool_id)">{{ p.label }}</option>
                </select>
            </div>

            <div v-if="mensaje" :class="mensajeClase" class="p-4 rounded-lg text-sm whitespace-pre-wrap">{{ mensaje }}</div>

            <div>
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 mb-3">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Servicios en router origen</h2>
                    <div v-if="servicios.length" class="flex gap-2 text-sm">
                        <button type="button" class="text-purple-600 dark:text-purple-400 hover:underline" @click="seleccionarTodos">Seleccionar todos</button>
                        <span class="text-gray-400">|</span>
                        <button type="button" class="text-gray-600 dark:text-gray-400 hover:underline" @click="limpiarSeleccion">Limpiar</button>
                    </div>
                </div>

                <p v-if="cargandoServicios" class="text-sm text-gray-500">Cargando servicios…</p>
                <p v-else-if="routerOrigenId && !servicios.length" class="text-sm text-gray-500">No hay servicios con usuario PPPoE en este router.</p>
                <p v-else-if="!routerOrigenId" class="text-sm text-gray-500">Elegí un router origen para listar servicios.</p>

                <div v-else class="overflow-x-auto border border-gray-200 dark:border-gray-700 rounded-lg max-h-96 overflow-y-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                        <thead class="bg-gray-50 dark:bg-gray-700/50 sticky top-0">
                            <tr>
                                <th class="px-3 py-2 w-10"></th>
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Usuario PPPoE</th>
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Cliente</th>
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Plan</th>
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">IP</th>
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Estado</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            <tr v-for="s in servicios" :key="s.servicio_id" class="hover:bg-gray-50 dark:hover:bg-gray-700/30">
                                <td class="px-3 py-2">
                                    <input
                                        v-model="servicioIdsSeleccionados"
                                        type="checkbox"
                                        :value="s.servicio_id"
                                        class="rounded border-gray-300 text-purple-600 focus:ring-purple-500"
                                    />
                                </td>
                                <td class="px-3 py-2 font-mono text-xs">{{ s.usuario_pppoe }}</td>
                                <td class="px-3 py-2">{{ s.cliente }}</td>
                                <td class="px-3 py-2">{{ s.plan }}</td>
                                <td class="px-3 py-2 font-mono text-xs">{{ s.ip || '—' }}</td>
                                <td class="px-3 py-2">{{ s.estado_label }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="flex justify-end pt-2 border-t border-gray-200 dark:border-gray-700">
                <button
                    type="button"
                    :disabled="!puedeEjecutar || ejecutando"
                    class="inline-flex items-center px-6 py-2.5 bg-purple-600 text-white rounded-lg font-medium hover:bg-purple-700 disabled:opacity-50 disabled:cursor-not-allowed"
                    @click="ejecutar"
                >
                    {{ ejecutando ? 'Procesando…' : (modo === 'mover' ? 'Mover seleccionados' : 'Copiar seleccionados') }}
                </button>
            </div>
        </div>

        <div v-if="resultadoDetalles.length" class="mt-6 bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 p-4">
            <h3 class="font-semibold text-gray-900 dark:text-gray-100 mb-2">Detalle</h3>
            <ul class="text-sm text-gray-700 dark:text-gray-300 space-y-1 list-disc list-inside">
                <li v-for="(d, i) in resultadoDetalles" :key="'ok-' + i">{{ d }}</li>
            </ul>
            <ul v-if="resultadoErrores.length" class="mt-3 text-sm text-red-600 dark:text-red-400 space-y-1 list-disc list-inside">
                <li v-for="e in resultadoErrores" :key="'err-' + e.servicio_id">
                    {{ e.usuario }}: {{ e.error }}
                </li>
            </ul>
        </div>
    </div>
</template>

<script>
import axios from 'axios';

export default {
    name: 'NodoMigrarPppoe',
    props: {
        nodo: { type: Object, required: true },
        canEditar: { type: Boolean, default: false },
        urlIndex: { type: String, default: '' },
        urlDatos: { type: String, default: '' },
        urlServicios: { type: String, default: '' },
        urlPools: { type: String, default: '' },
        urlEjecutar: { type: String, default: '' },
        csrfToken: { type: String, default: '' },
    },
    data() {
        return {
            routersNodo: [],
            routersTodos: [],
            servicios: [],
            poolsDestino: [],
            modo: 'mover',
            routerOrigenId: '',
            routerDestinoId: '',
            poolDestinoId: '',
            asignarIpAutomatica: true,
            servicioIdsSeleccionados: [],
            cargandoServicios: false,
            cargandoPools: false,
            ejecutando: false,
            mensaje: '',
            mensajeEsError: false,
            resultadoDetalles: [],
            resultadoErrores: [],
        };
    },
    computed: {
        puedeEjecutar() {
            if (!this.canEditar || this.ejecutando) return false;
            if (!this.routerOrigenId || !this.routerDestinoId) return false;
            if (this.servicioIdsSeleccionados.length === 0) return false;
            if (this.modo === 'mover' && !this.poolDestinoId) return false;
            if (this.modo === 'mover' && this.routerOrigenId === this.routerDestinoId) return false;
            return true;
        },
        mensajeClase() {
            return this.mensajeEsError
                ? 'bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 text-red-800 dark:text-red-200'
                : 'bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 text-green-800 dark:text-green-200';
        },
    },
    mounted() {
        this.cargarDatos();
    },
    methods: {
        headers() {
            return {
                'X-CSRF-TOKEN': this.csrfToken,
                Accept: 'application/json',
            };
        },
        async cargarDatos() {
            try {
                const { data } = await axios.get(this.urlDatos, { headers: this.headers() });
                this.routersNodo = data.routers_nodo || [];
                this.routersTodos = data.routers_todos || [];
            } catch {
                this.mostrarMensaje('No se pudieron cargar los routers.', true);
            }
        },
        async onRouterOrigenChange() {
            this.servicios = [];
            this.servicioIdsSeleccionados = [];
            if (!this.routerOrigenId) return;
            this.cargandoServicios = true;
            try {
                const { data } = await axios.get(this.urlServicios, {
                    params: { router_origen_id: this.routerOrigenId },
                    headers: this.headers(),
                });
                this.servicios = data.servicios || [];
            } catch {
                this.mostrarMensaje('Error al cargar servicios.', true);
            } finally {
                this.cargandoServicios = false;
            }
        },
        async onRouterDestinoChange() {
            this.poolDestinoId = '';
            this.poolsDestino = [];
            if (!this.routerDestinoId || this.modo !== 'mover') return;
            this.cargandoPools = true;
            try {
                const { data } = await axios.get(this.urlPools, {
                    params: { router_destino_id: this.routerDestinoId },
                    headers: this.headers(),
                });
                this.poolsDestino = data.pools || [];
            } catch {
                this.mostrarMensaje('Error al cargar pools del router destino.', true);
            } finally {
                this.cargandoPools = false;
            }
        },
        seleccionarTodos() {
            this.servicioIdsSeleccionados = this.servicios.map((s) => s.servicio_id);
        },
        limpiarSeleccion() {
            this.servicioIdsSeleccionados = [];
        },
        mostrarMensaje(texto, esError = false) {
            this.mensaje = texto;
            this.mensajeEsError = esError;
        },
        async ejecutar() {
            if (!this.puedeEjecutar) return;
            const accion = this.modo === 'mover' ? 'mover' : 'copiar';
            const n = this.servicioIdsSeleccionados.length;
            if (!confirm(`¿${accion === 'mover' ? 'Mover' : 'Copiar'} ${n} usuario(s) PPPoE?`)) return;

            this.ejecutando = true;
            this.mensaje = '';
            this.resultadoDetalles = [];
            this.resultadoErrores = [];

            try {
                const { data } = await axios.post(
                    this.urlEjecutar,
                    {
                        modo: this.modo,
                        router_origen_id: Number(this.routerOrigenId),
                        router_destino_id: Number(this.routerDestinoId),
                        pool_destino_id: this.poolDestinoId ? Number(this.poolDestinoId) : null,
                        asignar_ip_automatica: this.asignarIpAutomatica,
                        servicio_ids: this.servicioIdsSeleccionados,
                    },
                    { headers: this.headers() }
                );

                const res = data.resultado || {};
                this.resultadoDetalles = res.detalles || [];
                this.resultadoErrores = res.errores || [];
                this.mostrarMensaje(data.message || 'Listo.', !data.success);

                if (data.success) {
                    await this.onRouterOrigenChange();
                    this.servicioIdsSeleccionados = [];
                }
            } catch (err) {
                const msg = err.response?.data?.message || err.message || 'Error al ejecutar.';
                this.mostrarMensaje(msg, true);
            } finally {
                this.ejecutando = false;
            }
        },
    },
    watch: {
        modo(val) {
            if (val === 'copiar') {
                this.poolDestinoId = '';
            } else if (this.routerDestinoId) {
                this.onRouterDestinoChange();
            }
        },
    },
};
</script>
