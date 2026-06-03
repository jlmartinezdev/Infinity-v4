<template>
    <div class="max-w-7xl mx-auto">
        <!-- Header -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
            <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">Lista de pedidos</h1>
            <div class="flex items-center gap-2 flex-wrap">
                <a v-if="urlExportarExcel"
                    :href="urlExportarExcel"
                    class="inline-flex items-center gap-2 px-4 py-2 border border-green-600 text-green-700 dark:text-green-400 dark:border-green-500 bg-white dark:bg-gray-800 rounded-lg font-medium hover:bg-green-50 dark:hover:bg-green-900/20 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 dark:focus:ring-offset-gray-900">
                    <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    Exportar Excel
                </a>
                <button
                    v-if="urlResolucionesHoy"
                    type="button"
                    class="inline-flex items-center gap-2 px-4 py-2 border border-sky-600 text-sky-700 dark:text-sky-400 dark:border-sky-500 bg-white dark:bg-gray-800 rounded-lg font-medium hover:bg-sky-50 dark:hover:bg-sky-900/20 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:ring-offset-2 dark:focus:ring-offset-gray-900"
                    @click="abrirModalResolucionesHoy"
                >
                    <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    Aprobados / desaprobados hoy
                </button>
                <button type="button" @click="modalFactibilidadOpen = true"
                    class="inline-flex items-center px-4 py-2 bg-purple-600 text-white rounded-lg font-medium hover:bg-purple-700 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:ring-offset-2">
                    Análisis de Factibilidad Rapido
                </button>
                <button type="button" @click="openModalPedido"
                    class="inline-flex items-center px-4 py-2 bg-purple-600 text-white rounded-lg font-medium hover:bg-purple-700 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:ring-offset-2">
                    Nuevo pedido
                </button>
            </div>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 overflow-hidden" style="min-height: 160px;">
            <!-- Filtros (client-side con Vue) -->
            <div class="p-4 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-700/50">
                <div class="flex flex-col sm:flex-row gap-3 flex-wrap">
                    <div class="sm:w-48">
                        <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-0.5">Estado</label>
                        <select name="estado_id" v-model="formEstadoId" class="w-full py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none transition-colors bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                            <option value="todos">Todos los estados</option>
                            <option v-for="e in estados" :key="e.estado_id" :value="String(e.estado_id)">
                                {{ e.descripcion }}
                            </option>
                        </select>
                    </div>
                    <div class="sm:w-40">
                        <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-0.5">Tecnología</label>
                        <select name="tecnologia" v-model="formTecnologia" class="w-full py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none transition-colors bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                            <option value="todos">Todas las tecnologías</option>
                            <option value="gpon">GPON / Fibra</option>
                            <option value="wireless">Wireless</option>
                        </select>
                    </div>
                    <div class="sm:w-40">
                        <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-0.5">Fecha desde</label>
                        <input
                            v-model="filtroFechaDesde"
                            type="date"
                            class="w-full py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 text-sm"
                        />
                    </div>
                    <div class="sm:w-40">
                        <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-0.5">Fecha hasta</label>
                        <input
                            v-model="filtroFechaHasta"
                            type="date"
                            class="w-full py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 text-sm"
                        />
                    </div>
                    <div class="sm:w-56">
                        <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-0.5">Instalación</label>
                        <select v-model="filtroInstalacion" class="w-full py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none transition-colors bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                            <option value="todos">Todos</option>
                            <option value="pendientes">Pendientes (sin instalar)</option>
                            <option value="instalados">Solo instalados</option>
                        </select>
                    </div>
                    <div class="sm:w-56">
                        <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-0.5">Ampliación de red</label>
                        <select v-model="filtroEsperaAmpliacion" class="w-full py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none transition-colors bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                            <option value="todos">Todos</option>
                            <option value="solo">Solo en espera</option>
                            <option value="no">Sin espera</option>
                        </select>
                    </div>
                    <div v-if="filtroFechaDesde || filtroFechaHasta" class="flex items-end">
                        <button
                            type="button"
                            class="text-sm text-purple-600 dark:text-purple-400 hover:underline px-1 py-2"
                            @click="filtroFechaDesde = ''; filtroFechaHasta = ''"
                        >
                            Limpiar fechas
                        </button>
                    </div>
                    <div class="relative pt-2">
                        
                            <label class="flex items-center gap-2 px-2 py-2 cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-700 rounded-lg transition-colors">
                                <input type="checkbox" :checked="mostrarDescartados === '1'" @change="setMostrarDescartados($event.target.checked ? '1' : '0')"
                                    class="rounded border-gray-300 dark:border-gray-600 text-purple-600 focus:ring-purple-500">
                                <span class="text-sm text-gray-700 dark:text-gray-200">Mostrar pedidos descartados</span>
                            </label>
                        
                    </div>
                    <!--button type="button"
                        class="inline-flex items-center justify-center px-6 py-2.5 bg-purple-600 text-white rounded-lg font-medium hover:bg-purple-700 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:ring-offset-2 transition-colors shadow-sm"
                        @click="dropdownInstaladosOpen = false">
                        Filtrar
                    </button-->
                </div>
            </div>

            <div class="overflow-x-hidden">
                <!-- Barra de búsqueda instantánea -->
        <div class="p-4 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-700/50">
            <div class="flex flex-col sm:flex-row gap-3">
                <div class="flex-1 relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                        </svg>
                    </div>
                    <input
                        v-model="buscar"
                        type="text"
                        placeholder="Buscar por cliente, cédula o descripción..."
                        class="w-full pl-10 pr-10 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none transition-colors bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100"
                    />
                    <button
                        v-if="buscar"
                        type="button"
                        @click="buscar = ''"
                        class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 transition-colors"
                        title="Limpiar búsqueda"
                    >
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
            </div>
        </div>

        <div v-if="pedidosOrdenados.length === 0" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">
            <template v-if="buscar">No hay resultados para la búsqueda.</template>
            <template v-else>No hay pedidos. <button type="button" class="text-purple-600 dark:text-purple-400 hover:underline btn-open-pedido-modal">Crear uno</button></template>
        </div>

        <div v-for="pedido in pedidosOrdenados" :key="pedido.pedido_id" class="border-b border-gray-200 dark:border-gray-700 overflow-hidden">
            <!-- Header del Accordion -->
            <button type="button" @click="togglePedido(pedido.pedido_id)"
                class="w-full px-3 sm:px-4 py-3 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors flex flex-col sm:flex-row items-stretch sm:items-center justify-between text-left gap-3 sm:gap-0 touch-manipulation">
                <!-- Id del pedido -->
                <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5 p-3">{{ pedido.pedido_id }}</div>
                <div class="flex-1 grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-2 sm:gap-4 items-center min-w-0">
                    <!-- Cliente -->
                    <div class="col-span-2">
                        <div class="text-xs font-semibold text-gray-900 dark:text-gray-100 uppercase">{{ pedido.cliente.nombre }} {{ pedido.cliente.apellido }}</div>
                        <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">{{ pedido.cliente.cedula }}</div>
                    </div>
                    
                    <!-- Celular -->
                    <div class="flex items-center gap-1 order-3 sm:order-none">
                        <a v-if="pedido.cliente.telefono"
                           :href="`https://wa.me/${pedido.cliente.telefono.replace(/[^0-9]/g, '')}`"
                           target="_blank"
                           @click.stop
                           class="text-green-600 dark:text-green-400 hover:text-green-700 dark:hover:text-green-300 hover:bg-green-50 dark:hover:bg-green-900/30 rounded pr-1 transition-colors"
                           title="Abrir WhatsApp">
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 32 32">
                                <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.381 1.262.619 1.694.791.712.306 1.36.263 1.871.16.571-.116 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/>
                            </svg>
                        </a>
                        <span class="text-xs text-gray-900 dark:text-gray-100">{{ pedido.cliente.telefono ?? '—' }}</span>
                        
                    </div>
                    
                    <!-- Plan / Estado -->
                    <div class="text-xs text-gray-900 dark:text-gray-100 flex flex-wrap items-center gap-1.5 order-2 sm:order-none">
                        <span v-if="pedido.estado_instalado"
                              class="inline-flex px-2.5 py-1 text-xs font-medium rounded-full bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-300">
                            INSTALADO
                        </span>
                        <span v-else-if="pedido.espera_ampliacion_red"
                              class="inline-flex px-2.5 py-1 text-xs font-medium rounded-full bg-orange-100 text-orange-900 dark:bg-orange-900/40 dark:text-orange-200"
                              title="En espera de ampliación de red">
                            ESPERA RED
                        </span>
                        <span v-else :class="getBadgeClassEstado(pedido)" 
                              class="inline-flex px-2.5 py-1 text-xs font-medium rounded-full">
                            {{ getEstadoActual(pedido)?.estado_pedido?.descripcion ?? 'Sin estado' }}
                        </span>
                    </div>
                   
                    <!-- Barra de progreso por estado (3 puntos: números 1-3 si aprobado) -->
                    <div class="flex items-center gap-0.5 order-4 sm:order-none">
                        <template v-for="(step, idx) in getProgressSteps(pedido)" :key="step.estado_id">
                            <div v-if="idx > 0" class="flex-shrink-0 w-4 h-0.5 rounded-full"
                                 :class="step.status === 'approved' ? 'bg-purple-500' : (step.status === 'pending' ? 'bg-purple-400' : 'bg-gray-200 dark:bg-gray-600')"></div>
                            <div class="flex flex-col items-center flex-shrink-0"
                                 :class="step.status === 'approved' ? 'text-purple-600 dark:text-purple-400' : (step.status === 'pending' ? 'text-purple-600 dark:text-purple-400' : 'text-gray-300 dark:text-gray-500')">
                                <div v-if="step.status === 'approved'"
                                     class="w-6 h-6 rounded-full bg-purple-600 flex items-center justify-center text-white text-xs font-semibold"
                                     :title="step.descripcion + ' (aprobado)'">
                                    {{ idx + 1 }}
                                </div>
                                <div v-else-if="step.status === 'pending'"
                                     class="w-6 h-6 rounded-full bg-purple-100 dark:bg-purple-900/30 flex items-center justify-center ring-2 ring-purple-400 dark:ring-purple-600"
                                     :title="step.descripcion + ' (pendiente)'">
                                    <span class="w-2 h-2 rounded-full bg-purple-500"></span>
                                </div>
                                <div v-else
                                     class="w-6 h-6 rounded-full border-2 border-gray-200 dark:border-gray-600 flex items-center justify-center bg-white dark:bg-gray-800"
                                     :title="(step.descripcion || 'Paso ' + (idx + 1)) + ' (pendiente)'">
                                    <span class="w-1.5 h-1.5 rounded-full bg-gray-200 dark:bg-gray-600"></span>
                                </div>
                            </div>
                        </template>
                        <template v-if="!getProgressSteps(pedido).length">
                            <span class="text-xs text-gray-400 dark:text-gray-500">—</span>
                        </template>
                    </div>
                    
                    <!-- Fecha -->
                    <div class="text-xs text-gray-900 dark:text-gray-100 order-5 sm:order-none">
                        <div v-if="pedido.fecha_pedido">
                            {{ formatFecha(pedido.fecha_pedido) }}
                            <span v-if="diasDesdePedidoLabel(pedido.fecha_pedido)" class="text-gray-500 dark:text-gray-400"> ({{ diasDesdePedidoLabel(pedido.fecha_pedido) }})</span>
                        </div>
                        <div v-else>—</div>
                    </div>
                </div>
                
                <!-- Botón de acciones y expandir -->
                <div class="flex items-center justify-end sm:justify-start gap-1.5 sm:gap-2 sm:ml-4 shrink-0 order-last sm:order-none">
                    <div class="flex items-center gap-1">
                        <button v-if="puedeFinalizarPedido(pedido) && !pedido.tiene_agenda" type="button" @click.stop="crearAgenda(pedido)" class="p-2 sm:p-1.5 rounded-full bg-green-100 dark:bg-green-900/30 text-green-600 dark:text-green-400 hover:bg-green-200 dark:hover:bg-green-900/50 transition-colors disabled:opacity-50 flex items-center justify-center touch-manipulation" title="Crear agenda">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                            </svg>
                        </button>
                        <button v-else type="button" class="mx-3">&nbsp;&nbsp;</button>
                        <button v-if="puedeFinalizarPedido(pedido)"
                                type="button"
                                @click.stop="finalizarPedido(pedido)"
                                :disabled="loadingFinalizar === pedido.pedido_id"
                                class="p-2 sm:p-1.5 rounded-full bg-green-100 dark:bg-green-900/30 text-green-600 dark:text-green-400 hover:bg-green-200 dark:hover:bg-green-900/50 transition-colors disabled:opacity-50 flex items-center justify-center touch-manipulation"
                                title="Finalizar pedido (marcar instalación completada)">
                            <svg v-if="loadingFinalizar === pedido.pedido_id" class="w-4 h-4 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                        </button>
                        <button v-else type="button" class="mx-2">&nbsp;&nbsp;</button>
                        <button
                            v-if="esperaAmpliacionRedUrl && !pedido.estado_instalado"
                            type="button"
                            @click.stop="toggleEsperaAmpliacionRed(pedido)"
                            :disabled="loadingEsperaRed === pedido.pedido_id"
                            :class="[
                                'p-2 sm:p-1.5 rounded-full hover:bg-orange-200 dark:hover:bg-orange-900/50 transition-colors disabled:opacity-50 flex items-center justify-center touch-manipulation',
                                pedido.espera_ampliacion_red
                                    ? 'bg-orange-200 dark:bg-orange-900/50 text-orange-800 dark:text-orange-200 ring-2 ring-orange-400'
                                    : 'bg-orange-100 dark:bg-orange-900/30 text-orange-700 dark:text-orange-300',
                            ]"
                            :title="pedido.espera_ampliacion_red ? 'Quitar espera de ampliación de red' : 'Dejar en espera de ampliación de red'"
                        >
                            <svg v-if="loadingEsperaRed === pedido.pedido_id" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                            </svg>
                            <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.288 15.038a5.25 5.25 0 017.424 0M5.106 11.856c3.807-3.808 9.98-3.808 13.788 0M2.34 8.66a9.75 9.75 0 0115.32 0M12 18.75h.008v.008H12v-.008z" />
                            </svg>
                        </button>
                        <a :href="`/pedidos/${pedido.pedido_id}/edit`"
                           @click.stop
                           class="p-2 sm:p-1.5 rounded-full bg-blue-100 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400 hover:bg-blue-200 dark:hover:bg-blue-900/50 transition-colors flex items-center justify-center touch-manipulation"
                           title="Editar">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                            </svg>
                        </a>
                        <form :action="`/pedidos/${pedido.pedido_id}`"
                              method="POST"
                              @submit.prevent="eliminarPedido(pedido.pedido_id)"
                              class="inline">
                            <input type="hidden" name="_token" :value="csrfToken">
                            <input type="hidden" name="_method" value="DELETE">
                            <button type="submit"
                                    @click.stop
                                    class="p-2 sm:p-1.5 rounded-full bg-red-100 dark:bg-red-900/30 text-red-600 dark:text-red-400 hover:bg-red-200 dark:hover:bg-red-900/50 transition-colors flex items-center justify-center touch-manipulation"
                                    title="Eliminar">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                </svg>
                            </button>
                        </form>
                    </div>
                    <svg class="w-5 h-5 text-gray-400 dark:text-gray-500 transition-transform"
                         :class="{ 'rotate-180': expandedPedidos.includes(pedido.pedido_id) }"
                         fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                    </svg>
                </div>
            </button>
            
            <!-- Contenido del Accordion -->
            <div v-show="expandedPedidos.includes(pedido.pedido_id)" class="border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-700/30">
                <div class="p-3 sm:p-4 space-y-4">
                    <div
                        v-if="pedido.espera_ampliacion_red"
                        class="p-3 rounded-lg border border-orange-200 dark:border-orange-800/60 bg-orange-50 dark:bg-orange-900/20 text-sm text-orange-900 dark:text-orange-100"
                    >
                        <p class="font-semibold">En espera de ampliación de red</p>
                        <p v-if="pedido.espera_ampliacion_red_at" class="text-xs mt-1 text-orange-800/80 dark:text-orange-200/80">
                            Desde {{ formatFechaHora(pedido.espera_ampliacion_red_at) }}
                            <span v-if="pedido.espera_ampliacion_red_usuario"> · {{ pedido.espera_ampliacion_red_usuario }}</span>
                        </p>
                        <p v-if="pedido.espera_ampliacion_red_notas" class="text-xs mt-1 italic">{{ pedido.espera_ampliacion_red_notas }}</p>
                    </div>

                    <!-- Información adicional del pedido -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
                        <div class="break-words">
                            <span class="font-medium text-gray-700 dark:text-gray-300">Ubicación:</span>
                            <span class="text-gray-900 dark:text-gray-100 ml-2">{{ pedido.ubicacion ?? '—' }}</span>
                            <a v-if="getMapsUrl(pedido)"
                               :href="getMapsUrl(pedido)"
                               target="_blank"
                               class="inline-flex items-center gap-1 text-xs text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300 ml-2">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                </svg>
                                Ver mapa
                            </a>
                        </div>
                        <div class="break-words">
                            <span class="font-medium text-gray-700 dark:text-gray-300">Selección:</span>
                            <span class="text-gray-900 dark:text-gray-100 ml-2">
                                Nodo: {{ getNodoDescripcion(getSeleccionPedido(pedido)?.nodo_id) || '—' }} ·
                                Tecnología: {{ getTecnologiaDescripcion(getSeleccionPedido(pedido)?.tecnologia_id) || '—' }} ·
                                Plan: {{ getPlanNombre(getSeleccionPedido(pedido)?.plan_id) || '—' }}
                            </span>
                        </div>
                    </div>
                    
                    <!-- Historial de Estados -->
                    <div>
                        <h4 class="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-3">Historial de Estados</h4>
                        <div class="space-y-2">
                            <div v-for="detalle in getDetallesOrdenados(pedido.estado_pedido_detalles)"
                                 :key="`${detalle.pedido_id}-${detalle.estado_id}`"
                                 class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 p-3 bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700">
                                <div class="flex flex-wrap items-center gap-2 sm:gap-3 min-w-0">
                                    <!-- Mostrar numero de estado -->
                                    <span class="text-lg text-gray-600 dark:text-gray-400">
                                        {{ detalle.estado_id }}
                                    </span>

                                    <span :class="getBadgeClass(detalle.estado_pedido?.descripcion)"
                                          class="inline-flex px-2.5 py-1 text-xs font-medium rounded-full">
                                        {{ detalle.estado_pedido?.descripcion ?? 'Sin estado' }}
                                    </span>
                                    <div class="text-xs text-gray-600 dark:text-gray-400">
                                        <div v-if="detalle.usuario">
                                            Por: {{ detalle.usuario.name }}
                                        </div>
                                        <div v-if="detalle.fecha">
                                            {{ formatFechaHora(detalle.fecha) }}
                                        </div>
                                        <div v-if="detalle.notas" class="mt-1 text-gray-500 dark:text-gray-400 italic">
                                            {{ detalle.notas }}
                                        </div>
                                        <div v-if="(detalle.nodo_id != null || detalle.tecnologia_id != null || detalle.plan_id != null)" class="mt-1 text-gray-600 dark:text-gray-400">
                                            <span v-if="detalle.nodo_id != null">Nodo: {{ getNodoDescripcion(detalle.nodo_id) || '—' }}</span>
                                            <span v-if="detalle.tecnologia_id != null" class="ml-2">· Tecnología: {{ getTecnologiaDescripcion(detalle.tecnologia_id) || '—' }}</span>
                                            <span v-if="detalle.plan_id != null" class="ml-2">· Plan: {{ getPlanNombre(detalle.plan_id) || '—' }}</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="flex flex-wrap items-center gap-2 shrink-0">
                                    <button v-if="detalle.estado_id === 3 && detalle.estado === 'A' && !pedido.usuario_pppoe_creado"
                                            type="button"
                                            @click.stop="crearUsuarioPppoe(pedido)"
                                            :disabled="loadingCrearPppoe === pedido.pedido_id"
                                            class="inline-flex items-center px-2.5 py-1 text-xs font-medium text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                                            title="Crear usuario PPPoE">
                                        <svg v-if="loadingCrearPppoe !== pedido.pedido_id" class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                        </svg>
                                        <svg v-else class="w-3 h-3 mr-1 animate-spin" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                        </svg>
                                        <span v-if="loadingCrearPppoe !== pedido.pedido_id">Crear usuario PPPoE</span>
                                        <span v-else>Creando…</span>
                                    </button>
                                    <span v-else-if="detalle.estado_id === 3 && detalle.estado === 'A' && pedido.usuario_pppoe_creado"
                                          class="inline-flex px-2 py-0.5 text-xs font-medium rounded-full bg-indigo-100 text-indigo-800 dark:bg-indigo-900/30 dark:text-indigo-300"
                                          title="Usuario PPPoE ya creado">
                                        Usuario creado
                                    </span>
                                    <span v-if="detalle.estado === 'A'"
                                          class="inline-flex px-2 py-0.5 text-xs font-medium rounded-full bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300">
                                        Aprobado
                                    </span>
                                    <button v-if="(detalle.estado === 'A' || detalle.estado === 'D') && reabrirEstadoUrl"
                                            type="button"
                                            @click.stop="reabrirEstado(pedido.pedido_id, detalle.estado_id)"
                                            :disabled="loadingReabrir === `${pedido.pedido_id}-${detalle.estado_id}`"
                                            class="inline-flex items-center px-2.5 py-1 text-xs font-medium text-amber-700 dark:text-amber-300 bg-amber-100 dark:bg-amber-900/30 rounded-lg hover:bg-amber-200 dark:hover:bg-amber-900/50 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                                            title="Reabrir estado (volver a pendiente)">
                                        <svg v-if="loadingReabrir !== `${pedido.pedido_id}-${detalle.estado_id}`" class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                                        </svg>
                                        <svg v-else class="w-3 h-3 mr-1 animate-spin" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                        </svg>
                                        <span v-if="loadingReabrir !== `${pedido.pedido_id}-${detalle.estado_id}`">Reabrir</span>
                                        <span v-else>Reabriendo...</span>
                                    </button>
                                    <span v-else-if="detalle.estado === 'P'"
                                          class="inline-flex px-2 py-0.5 text-xs font-medium rounded-full bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-300">
                                        Pendiente
                                    </span>
                                    <span v-else-if="detalle.estado === 'D'"
                                          class="inline-flex px-2 py-0.5 text-xs font-medium rounded-full bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300">
                                        Descartado
                                    </span>
                                    <span v-else
                                          class="inline-flex px-2 py-0.5 text-xs font-medium rounded-full bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300">
                                        {{ detalle.estado }}
                                    </span>
                                    <div v-if="detalle.estado === 'P'" class="flex items-center gap-1">
                                        <button
                                            @click.stop="aprobarEstado(pedido, detalle.estado_id, detalle.estado_pedido?.parametro)"
                                            :disabled="loadingAprobar === `${pedido.pedido_id}-${detalle.estado_id}` || loadingDescartar === `${pedido.pedido_id}-${detalle.estado_id}`"
                                            class="inline-flex items-center px-2.5 py-1 text-xs font-medium text-white bg-green-600 rounded-lg hover:bg-green-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                                            title="Aprobar estado">
                                            <svg v-if="loadingAprobar !== `${pedido.pedido_id}-${detalle.estado_id}`" 
                                                 class="w-3 h-3 mr-1" 
                                                 fill="none" 
                                                 stroke="currentColor" 
                                                 viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                            </svg>
                                            <svg v-else 
                                                 class="w-3 h-3 mr-1 animate-spin" 
                                                 fill="none" 
                                                 viewBox="0 0 24 24">
                                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                            </svg>
                                            <span v-if="loadingAprobar !== `${pedido.pedido_id}-${detalle.estado_id}`">Aprobar</span>
                                            <span v-else>Aprobando...</span>
                                        </button>
                                        <button
                                            @click.stop="descartarEstado(pedido.pedido_id, detalle.estado_id)"
                                            :disabled="loadingAprobar === `${pedido.pedido_id}-${detalle.estado_id}` || loadingDescartar === `${pedido.pedido_id}-${detalle.estado_id}`"
                                            class="inline-flex items-center px-2.5 py-1 text-xs font-medium text-white bg-red-600 rounded-lg hover:bg-red-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                                            title="Descartar estado">
                                            <svg v-if="loadingDescartar !== `${pedido.pedido_id}-${detalle.estado_id}`" 
                                                 class="w-3 h-3 mr-1" 
                                                 fill="none" 
                                                 stroke="currentColor" 
                                                 viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                            </svg>
                                            <svg v-else 
                                                 class="w-3 h-3 mr-1 animate-spin" 
                                                 fill="none" 
                                                 viewBox="0 0 24 24">
                                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                            </svg>
                                            <span v-if="loadingDescartar !== `${pedido.pedido_id}-${detalle.estado_id}`">Descartar</span>
                                            <span v-else>Descartando...</span>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div v-if="!pedido.estado_pedido_detalles || pedido.estado_pedido_detalles.length === 0"
                                 class="p-3 text-center text-sm text-gray-500 dark:text-gray-400 bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700">
                                No hay estados registrados
                            </div>
                        </div>
                    </div>
                </div>
                </div>
            </div>
        </div>
        </div>
    </div>

    <!-- Modal crear pedido -->
    <Teleport to="body">
        <div v-show="modalPedidoOpen" class="fixed inset-0 z-50 overflow-hidden" role="dialog" aria-modal="true">
            <div class="fixed inset-0 bg-gray-900/60 transition-opacity" @click="closeModalPedido" aria-hidden="true"></div>
            <div ref="modalPedidoContentRef" class="absolute w-[calc(100vw-1rem)] sm:w-[calc(100vw-2rem)] max-w-2xl max-h-[90vh] overflow-y-auto border border-gray-200 dark:border-gray-700 rounded-xl bg-white dark:bg-gray-800 shadow-xl"
                style="top: 50%; left: 50%; transform: translate(-50%, -50%);">
                <div ref="modalPedidoHeaderRef" class="sticky top-0 z-10 flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-700/50 border-b border-gray-200 dark:border-gray-700 cursor-move select-none">
                    <div class="flex items-center gap-2 text-sm font-semibold text-gray-700 dark:text-gray-200">
                        <svg class="w-4 h-4 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8h16M4 16h16"></path>
                        </svg>
                        <span>Nuevo Pedido</span>
                    </div>
                    <button type="button" @click="closeModalPedido" class="p-1.5 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors" aria-label="Cerrar">
                        <svg class="w-5 h-5 text-gray-600 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
                <div class="p-3 sm:p-4 bg-white dark:bg-gray-800 min-w-0">
                    <PedidoForm
                        v-if="modalPedidoOpen"
                        :pedido-id="pedidoFormConfig.pedidoId"
                        :planes="pedidoFormConfig.planes"
                        :estado-id="pedidoFormConfig.estadoId"
                        :buscar-cliente-url="pedidoFormConfig.buscarClienteUrl"
                        :verificar-telefono-url="pedidoFormConfig.verificarTelefonoUrl || ''"
                        :cedula-temporal-url="pedidoFormConfig.cedulaTemporalUrl || ''"
                        :consultar-padron-url="pedidoFormConfig.consultarPadronUrl"
                        :submit-url="pedidoFormConfig.submitUrl"
                        :cancel-url="pedidoFormConfig.cancelUrl"
                        :csrf-token="pedidoFormConfig.csrfToken"
                        :modal-mode="true"
                    />
                </div>
            </div>
        </div>
    </Teleport>

    <!-- Modal Análisis de Factibilidad -->
    <Teleport to="body">
        <div v-show="modalFactibilidadOpen" class="fixed inset-0 z-50 overflow-hidden" role="dialog" aria-modal="true">
            <div class="fixed inset-0 bg-gray-900/60 transition-opacity" @click="modalFactibilidadOpen = false" aria-hidden="true"></div>
            <div class="absolute w-full max-w-md border border-gray-200 dark:border-gray-700 rounded-xl bg-white dark:bg-gray-800 shadow-xl p-4" style="top: 50%; left: 50%; transform: translate(-50%, -50%);">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Análisis de Factibilidad</h2>
                    <button type="button" @click="modalFactibilidadOpen = false" class="p-1.5 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors" aria-label="Cerrar">
                        <svg class="w-5 h-5 text-gray-600 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
                <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">CLIENTE TEST</p>
                <form :action="urlPedidosStore" method="POST">
                    <input type="hidden" name="_token" :value="csrfToken">
                    <input type="hidden" name="cedula" value="1">
                    <input type="hidden" name="nombre" value="cliente test">
                    <input type="hidden" name="apellido" value="">
                    <input type="hidden" name="fecha_pedido" :value="fechaHoy">
                    <div class="space-y-4 mb-4">
                        <div>
                            <label for="factibilidad-maps-gps" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Ubicación (URL Google Maps) <span class="text-red-500">*</span></label>
                            <input type="text" id="factibilidad-maps-gps" name="maps_gps" required
                                class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none transition-colors bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100"
                                placeholder="Pega el link de Google Maps"/>
                        </div>
                        <div>
                            <label for="factibilidad-telefono" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Número de celular</label>
                            <input type="text" id="factibilidad-telefono" name="telefono" maxlength="20"
                                class="w-full rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-amber-500 focus:ring-2 focus:ring-amber-500/20 focus:outline-none px-3 py-2 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100"
                                placeholder="Ej. 098 123 4567">
                        </div>
                        <div>
                            <label for="factibilidad-descripcion" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Descripción</label>
                            <textarea id="factibilidad-descripcion" name="descripcion" rows="3"
                                class="w-full rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-amber-500 focus:ring-2 focus:ring-amber-500/20 focus:outline-none px-3 py-2 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100"
                                placeholder="Descripción del análisis de factibilidad"></textarea>
                        </div>
                    </div>
                    <div class="flex justify-end gap-2 mt-5">
                        <button type="button" @click="modalFactibilidadOpen = false" class="px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700">
                            Cancelar
                        </button>
                        <button type="submit" class="px-4 py-2 bg-purple-600 text-white rounded-lg font-medium hover:bg-purple-700">
                            Guardar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </Teleport>

    <!-- Modal resoluciones de hoy (aprobados / desaprobados) -->
    <Teleport to="body">
        <div v-show="modalResolucionesHoyOpen" class="fixed inset-0 z-[60] overflow-y-auto" role="dialog" aria-modal="true" aria-labelledby="modal-resoluciones-hoy-titulo">
            <div class="fixed inset-0 bg-gray-900/60" aria-hidden="true" @click="modalResolucionesHoyOpen = false" />
            <div class="relative min-h-full flex items-center justify-center p-4">
                <div class="relative w-full max-w-5xl max-h-[90vh] flex flex-col rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-xl">
                    <div class="flex items-center justify-between gap-3 p-4 border-b border-gray-200 dark:border-gray-700 shrink-0">
                        <div>
                            <h2 id="modal-resoluciones-hoy-titulo" class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                                Clientes aprobados y desaprobados
                            </h2>
                            <p v-if="resolucionesFechaLabel" class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">
                                {{ resolucionesFechaLabel }}
                                <span v-if="resolucionesStats">
                                    · {{ resolucionesStats.clientes_aprobados }} cliente(s) aprobado(s) · {{ resolucionesStats.clientes_desaprobados }} desaprobado(s)
                                </span>
                            </p>
                        </div>
                        <button type="button" class="p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 text-gray-500" aria-label="Cerrar" @click="modalResolucionesHoyOpen = false">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                        </button>
                    </div>
                    <div v-if="resolucionesCargando" class="p-10 text-center text-gray-500 dark:text-gray-400 text-sm">Cargando…</div>
                    <p v-else-if="resolucionesError" class="p-4 text-sm text-red-600 dark:text-red-400">{{ resolucionesError }}</p>
                    <div v-else class="overflow-y-auto p-4 grid grid-cols-1 lg:grid-cols-2 gap-4">
                        <section class="rounded-lg border border-green-200 dark:border-green-800/60 bg-green-50/50 dark:bg-green-900/10 overflow-hidden">
                            <h3 class="px-4 py-2.5 text-sm font-semibold text-green-800 dark:text-green-300 bg-green-100/80 dark:bg-green-900/30 border-b border-green-200 dark:border-green-800/60">
                                Aprobados ({{ resolucionesAprobados.length }})
                            </h3>
                            <ul v-if="resolucionesAprobados.length" class="divide-y divide-green-100 dark:divide-green-900/40 max-h-[50vh] overflow-y-auto">
                                <li v-for="(row, idx) in resolucionesAprobados" :key="'a-' + row.pedido_id + '-' + row.estado_id + '-' + idx" class="px-4 py-3 text-sm">
                                    <p class="font-medium text-gray-900 dark:text-gray-100">{{ row.cliente_nombre || '—' }}</p>
                                    <p class="text-xs text-gray-600 dark:text-gray-400">{{ row.cliente_cedula }}<span v-if="row.cliente_telefono"> · {{ row.cliente_telefono }}</span></p>
                                    <p class="text-xs text-gray-500 dark:text-gray-500 mt-1">
                                        Pedido #{{ row.pedido_id }} · {{ row.estado_pedido }}
                                        <span v-if="row.plan_nombre"> · {{ row.plan_nombre }}</span>
                                        <span v-if="row.nodo"> · {{ row.nodo }}</span>
                                    </p>
                                    <p class="text-xs text-gray-500 mt-0.5">{{ formatResolucionHora(row.fecha) }}<span v-if="row.usuario"> · {{ row.usuario }}</span></p>
                                    <p v-if="row.notas" class="text-xs text-gray-600 dark:text-gray-400 mt-1 italic">{{ row.notas }}</p>
                                </li>
                            </ul>
                            <p v-else class="px-4 py-6 text-sm text-gray-500 dark:text-gray-400 text-center">Sin aprobaciones hoy.</p>
                        </section>
                        <section class="rounded-lg border border-red-200 dark:border-red-800/60 bg-red-50/50 dark:bg-red-900/10 overflow-hidden">
                            <h3 class="px-4 py-2.5 text-sm font-semibold text-red-800 dark:text-red-300 bg-red-100/80 dark:bg-red-900/30 border-b border-red-200 dark:border-red-800/60">
                                Desaprobados ({{ resolucionesDesaprobados.length }})
                            </h3>
                            <ul v-if="resolucionesDesaprobados.length" class="divide-y divide-red-100 dark:divide-red-900/40 max-h-[50vh] overflow-y-auto">
                                <li v-for="(row, idx) in resolucionesDesaprobados" :key="'d-' + row.pedido_id + '-' + row.estado_id + '-' + idx" class="px-4 py-3 text-sm">
                                    <p class="font-medium text-gray-900 dark:text-gray-100">{{ row.cliente_nombre || '—' }}</p>
                                    <p class="text-xs text-gray-600 dark:text-gray-400">{{ row.cliente_cedula }}<span v-if="row.cliente_telefono"> · {{ row.cliente_telefono }}</span></p>
                                    <p class="text-xs text-gray-500 dark:text-gray-500 mt-1">
                                        Pedido #{{ row.pedido_id }} · {{ row.estado_pedido }}
                                    </p>
                                    <p class="text-xs text-gray-500 mt-0.5">{{ formatResolucionHora(row.fecha) }}<span v-if="row.usuario"> · {{ row.usuario }}</span></p>
                                    <p v-if="row.notas" class="text-xs text-gray-600 dark:text-gray-400 mt-1 italic">{{ row.notas }}</p>
                                </li>
                            </ul>
                            <p v-else class="px-4 py-6 text-sm text-gray-500 dark:text-gray-400 text-center">Sin desaprobaciones hoy.</p>
                        </section>
                    </div>
                    <div class="p-4 border-t border-gray-200 dark:border-gray-700 flex justify-end shrink-0">
                        <button type="button" class="px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700" @click="modalResolucionesHoyOpen = false">
                            Cerrar
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </Teleport>
</template>

<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';
import PedidoForm from '@/components/PedidoForm.vue';
import axios from 'axios';
import Swal from 'sweetalert2';

const props = defineProps({
    pedidos: {
        type: Array,
        required: true
    },
    estados: {
        type: Array,
        required: true
    },
    csrfToken: {
        type: String,
        required: true
    },
    aprobarEstadoUrl: {
        type: String,
        required: true
    },
    urlOpcionesNodoAprobacion: {
        type: String,
        default: ''
    },
    descartarEstadoUrl: {
        type: String,
        required: true
    },
    reabrirEstadoUrl: {
        type: String,
        default: ''
    },
    esperaAmpliacionRedUrl: {
        type: String,
        default: ''
    },
    crearUsuarioPppoeUrl: {
        type: String,
        default: ''
    },
    crearAgendaUrl: {
        type: String,
        default: ''
    },
    finalizarPedidoUrl: {
        type: String,
        default: ''
    },
    urlExportarExcel: {
        type: String,
        default: ''
    },
    urlResolucionesHoy: {
        type: String,
        default: ''
    },
    nodos: {
        type: Array,
        default: () => []
    },
    planes: {
        type: Array,
        default: () => []
    },
    tiposTecnologia: {
        type: Array,
        default: () => []
    },
    clientes: { type: Array, default: () => [] },
    urlPedidosIndex: { type: String, default: '' },
    urlPedidosStore: { type: String, default: '' },
    pedidoFormConfig: { type: Object, default: () => ({}) },
    filtroEstadoId: { type: String, default: '' },
    filtroClienteId: { type: String, default: '' },
    mostrarInstaladosInitial: { type: String, default: '1' },
});

const buscar = ref('');
const modalPedidoOpen = ref(false);
const modalFactibilidadOpen = ref(false);
const modalResolucionesHoyOpen = ref(false);
const resolucionesCargando = ref(false);
const resolucionesError = ref('');
const resolucionesAprobados = ref([]);
const resolucionesDesaprobados = ref([]);
const resolucionesFechaLabel = ref('');
const resolucionesStats = ref(null);

function formatResolucionHora(iso) {
    if (!iso) return '—';
    const d = new Date(String(iso).replace(' ', 'T'));
    if (Number.isNaN(d.getTime())) return iso;
    return d.toLocaleString('es-PY', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' });
}

async function abrirModalResolucionesHoy() {
    if (!props.urlResolucionesHoy) return;
    modalResolucionesHoyOpen.value = true;
    resolucionesCargando.value = true;
    resolucionesError.value = '';
    resolucionesAprobados.value = [];
    resolucionesDesaprobados.value = [];
    resolucionesFechaLabel.value = '';
    resolucionesStats.value = null;
    try {
        const { data } = await axios.get(props.urlResolucionesHoy);
        resolucionesAprobados.value = data.aprobados || [];
        resolucionesDesaprobados.value = data.desaprobados || [];
        resolucionesFechaLabel.value = data.fecha_label ? `Hoy · ${data.fecha_label}` : 'Hoy';
        resolucionesStats.value = data.stats || null;
    } catch (e) {
        resolucionesError.value = e.response?.data?.message || 'No se pudo cargar el listado.';
    } finally {
        resolucionesCargando.value = false;
    }
}
const dropdownInstaladosOpen = ref(false);
const dropdownInstaladosRef = ref(null);
const modalPedidoContentRef = ref(null);
const modalPedidoHeaderRef = ref(null);

/** todos | pendientes | instalados — filtro por estado_instalado del pedido */
const filtroInstalacion = ref('todos');
const filtroEsperaAmpliacion = ref('todos');
const filtroFechaDesde = ref('');
const filtroFechaHasta = ref('');
const mostrarDescartados = ref('1');
const formEstadoId = ref(props.filtroEstadoId || 'todos');
const formClienteId = ref(props.filtroClienteId || 'todos');
const formTecnologia = ref('todos');
const fechaHoy = new Date().toISOString().split('T')[0];

function getSwalThemeOptions() {
    const isDark = document.documentElement.classList.contains('dark');
    if (!isDark) return {};

    return {
        background: '#1f2937',
        color: '#f3f4f6',
        customClass: {
            popup: 'border border-gray-700',
            title: 'text-gray-100',
            htmlContainer: 'text-gray-200',
            confirmButton: 'focus:!ring-2 focus:!ring-offset-2 focus:!ring-offset-gray-800',
            cancelButton: 'focus:!ring-2 focus:!ring-offset-2 focus:!ring-offset-gray-800',
        },
    };
}

function aplicarCompatFiltroInstalacionDesdeProps() {
    if (props.mostrarInstaladosInitial === '0') {
        filtroInstalacion.value = 'pendientes';
    }
}

function setMostrarDescartados(val) {
    mostrarDescartados.value = val;
    try { localStorage.setItem('pedidos_mostrar_descartados', val); } catch (e) {}
    dropdownInstaladosOpen.value = false;
}

function openModalPedido() {
    modalPedidoOpen.value = true;
}

function closeModalPedido() {
    modalPedidoOpen.value = false;
}

function handleClickOutside(e) {
    if (dropdownInstaladosRef.value && !dropdownInstaladosRef.value.contains(e.target)) {
        dropdownInstaladosOpen.value = false;
    }
}

watch(filtroInstalacion, (v) => {
    try { localStorage.setItem('pedidos_filtro_instalacion', v); } catch (e) {}
});

watch([filtroFechaDesde, filtroFechaHasta], ([desde, hasta]) => {
    try {
        if (desde) localStorage.setItem('pedidos_fecha_desde', desde); else localStorage.removeItem('pedidos_fecha_desde');
        if (hasta) localStorage.setItem('pedidos_fecha_hasta', hasta); else localStorage.removeItem('pedidos_fecha_hasta');
    } catch (e) {}
});

onMounted(() => {
    // Restaurar preferencias desde localStorage
    try {
        const fi = localStorage.getItem('pedidos_filtro_instalacion');
        if (fi === 'todos' || fi === 'pendientes' || fi === 'instalados') {
            filtroInstalacion.value = fi;
        } else {
            const storedOld = localStorage.getItem('pedidos_mostrar_instalados');
            if (storedOld === '0') {
                filtroInstalacion.value = 'pendientes';
            } else {
                aplicarCompatFiltroInstalacionDesdeProps();
            }
        }
        filtroFechaDesde.value = localStorage.getItem('pedidos_fecha_desde') || '';
        filtroFechaHasta.value = localStorage.getItem('pedidos_fecha_hasta') || '';
        const storedDesc = localStorage.getItem('pedidos_mostrar_descartados');
        if (storedDesc === '0' || storedDesc === '1') mostrarDescartados.value = storedDesc;
    } catch (e) {}
    document.addEventListener('click', handleClickOutside);
    const onClose = () => closeModalPedido();
    const onCreated = () => { closeModalPedido(); window.location.reload(); };
    window.addEventListener('close-pedido-modal', onClose);
    window.addEventListener('pedido-created', onCreated);
    window._pedidosListCleanup = () => {
        window.removeEventListener('close-pedido-modal', onClose);
        window.removeEventListener('pedido-created', onCreated);
    };
    // Delegar clics en .btn-open-pedido-modal para abrir modal
    document.addEventListener('click', (e) => {
        if (e.target.closest('.btn-open-pedido-modal')) openModalPedido();
    });
});

onUnmounted(() => {
    document.removeEventListener('click', handleClickOutside);
    if (window._pedidosListCleanup) window._pedidosListCleanup();
});

// Filtrar por búsqueda, estado, cliente, fechas, instalación y descartados (todo client-side)
const pedidosOverrides = ref({});

const pedidosFiltrados = computed(() => {
    let list = (props.pedidos || []).map((p) => {
        const ov = pedidosOverrides.value[p.pedido_id];
        return ov ? { ...p, ...ov } : p;
    });

    // Filtro por estado_id (estado actual del pedido)
    if (formEstadoId.value && formEstadoId.value !== 'todos') {
        const estadoId = String(formEstadoId.value);
        list = list.filter(p => {
            const actual = getEstadoActual(p);
            return actual && String(actual.estado_id) === estadoId;
        });
    }

    // Filtro por cliente_id
    if (formClienteId.value && formClienteId.value !== 'todos') {
        const clienteId = String(formClienteId.value);
        list = list.filter(p => String(p.cliente_id ?? p.cliente?.cliente_id ?? '') === clienteId);
    }

    // Filtro por rango de fecha del pedido (campo fecha_pedido)
    if (filtroFechaDesde.value) {
        const desde = filtroFechaDesde.value;
        list = list.filter(p => {
            const fp = p.fecha_pedido;
            if (!fp) return false;
            return String(fp) >= desde;
        });
    }
    if (filtroFechaHasta.value) {
        const hasta = filtroFechaHasta.value;
        list = list.filter(p => {
            const fp = p.fecha_pedido;
            if (!fp) return false;
            return String(fp) <= hasta;
        });
    }

    // Filtro instalación: pendientes = sin instalar; instalados = completados
    if (filtroInstalacion.value === 'pendientes') {
        list = list.filter(p => !p.estado_instalado);
    } else if (filtroInstalacion.value === 'instalados') {
        list = list.filter(p => !!p.estado_instalado);
    }

    // Filtro mostrar/ocultar descartados (pedidos con al menos un estado D)
    if (mostrarDescartados.value === '0') {
        list = list.filter(p => !(p.estado_pedido_detalles || []).some(d => d.estado === 'D'));
    }

    if (filtroEsperaAmpliacion.value === 'solo') {
        list = list.filter(p => !!p.espera_ampliacion_red);
    } else if (filtroEsperaAmpliacion.value === 'no') {
        list = list.filter(p => !p.espera_ampliacion_red);
    }

    // Filtro por tecnología (GPON / Wireless)
    if (formTecnologia.value && formTecnologia.value !== 'todos') {
        const tipo = formTecnologia.value;
        list = list.filter(p => {
            const sel = getSeleccionPedido(p);
            const techId = sel?.tecnologia_id ?? p.tecnologia_id_seleccionado;
            if (techId == null) return tipo === 'todos';
            const desc = getTecnologiaDescripcion(techId).toLowerCase();
            if (tipo === 'gpon') return /gpon|epon|ftth|fibra|fiber|pon|xg-pon/i.test(desc);
            if (tipo === 'wireless') return /wireless|inalambr|anten|radio|wifi/i.test(desc);
            return true;
        });
    }

    // Búsqueda instantánea (cliente, cédula, descripción)
    const q = buscar.value?.trim()?.toLowerCase();
    if (q) {
        list = list.filter(p => {
            const nombre = (p.cliente?.nombre ?? '').toLowerCase();
            const apellido = (p.cliente?.apellido ?? '').toLowerCase();
            const cedula = String(p.cliente?.cedula ?? '').toLowerCase();
            const descripcion = (p.descripcion ?? '').toLowerCase();
            const fullName = `${nombre} ${apellido}`.trim().toLowerCase();
            return cedula.includes(q) || nombre.includes(q) || apellido.includes(q) || fullName.includes(q) || descripcion.includes(q);
        });
    }

    return list;
});

// Lista filtrada y ordenada por pedido_id descendente (más recientes primero)
const pedidosOrdenados = computed(() => {
    return [...pedidosFiltrados.value].sort((a, b) => (b.pedido_id ?? 0) - (a.pedido_id ?? 0));
});

const expandedPedidos = ref([]);
const loadingAprobar = ref(null);
const loadingDescartar = ref(null);
const loadingReabrir = ref(null);
const loadingFinalizar = ref(null);
const loadingCrearPppoe = ref(null);
const loadingEsperaRed = ref(null);

function actualizarPedidoEnLista(pedidoId, datos) {
    pedidosOverrides.value = {
        ...pedidosOverrides.value,
        [pedidoId]: { ...(pedidosOverrides.value[pedidoId] || {}), ...datos },
    };
}

const toggleEsperaAmpliacionRed = async (pedido) => {
    if (!props.esperaAmpliacionRedUrl) return;
    const activar = !pedido.espera_ampliacion_red;
    let notas = null;

    if (activar) {
        const result = await Swal.fire({
            title: 'Espera de ampliación de red',
            html: `
                <p class="text-sm text-gray-600 mb-3 text-left">El pedido quedará marcado hasta que la red esté ampliada.</p>
                <textarea id="swal-notas-espera-red" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" rows="3" maxlength="1000" placeholder="Motivo o detalle (opcional)"></textarea>
            `,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#ea580c',
            cancelButtonColor: '#6b7280',
            confirmButtonText: 'Marcar en espera',
            cancelButtonText: 'Cancelar',
            preConfirm: () => {
                const el = document.getElementById('swal-notas-espera-red');
                return el?.value?.trim() || null;
            },
        });
        if (!result.isConfirmed) return;
        notas = result.value;
    } else {
        const result = await Swal.fire({
            title: '¿Quitar espera de ampliación?',
            text: 'El pedido volverá al flujo normal en la lista.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ea580c',
            cancelButtonColor: '#6b7280',
            confirmButtonText: 'Sí, quitar',
            cancelButtonText: 'Cancelar',
        });
        if (!result.isConfirmed) return;
    }

    const pedidoId = pedido.pedido_id;
    loadingEsperaRed.value = pedidoId;
    try {
        const url = props.esperaAmpliacionRedUrl.replace(':pedido', String(pedidoId));
        const { data } = await axios.post(url, { activo: activar, notas });
        if (data.pedido) {
            actualizarPedidoEnLista(pedidoId, data.pedido);
        }
        await Swal.fire({
            icon: 'success',
            title: activar ? 'Marcado en espera' : 'Espera quitada',
            text: data.message || '',
            timer: 2200,
            showConfirmButton: false,
        });
    } catch (error) {
        const message = error.response?.data?.message || 'No se pudo actualizar el estado de espera.';
        await Swal.fire({ icon: 'error', title: 'Error', text: message });
    } finally {
        loadingEsperaRed.value = null;
    }
};
const togglePedido = (pedidoId) => {
    const index = expandedPedidos.value.indexOf(pedidoId);
    if (index > -1) {
        expandedPedidos.value.splice(index, 1);
    } else {
        expandedPedidos.value.push(pedidoId);
    }
};

const getBadgeClass = (descripcion) => {
    if (!descripcion) return 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300';
    const estadoLower = descripcion.toLowerCase();
    const badgeColors = {
        'pendiente': 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-300',
        'en proceso': 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300',
        'completado': 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300',
        'cancelado': 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300',
    };
    return badgeColors[estadoLower] || 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300';
};

const formatFecha = (fecha) => {
    if (!fecha) return '—';
    const meses = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
    const date = new Date(fecha);
    const mes = meses[date.getMonth()];
    return `${date.getDate()} ${mes}. ${date.getFullYear()}`;
};

/** Etiqueta de días transcurridos desde la fecha: "hoy", "hace 1 día" o "hace X días". */
const diasDesdePedidoLabel = (fecha) => {
    if (!fecha) return '';
    const date = new Date(fecha);
    const hoy = new Date();
    date.setHours(0, 0, 0, 0);
    hoy.setHours(0, 0, 0, 0);
    const dias = Math.floor((hoy - date) / (24 * 60 * 60 * 1000));
    if (dias < 0) return '';
    if (dias === 0) return 'hoy';
    if (dias === 1) return 'hace 1 día';
    return 'hace ' + dias + ' días';
};

const formatFechaHora = (fecha) => {
    if (!fecha) return '—';
    const date = new Date(fecha);
    const horaStr = date.getHours().toString().padStart(2, '0');
    const minutos = date.getMinutes();
    const minutosStr = minutos.toString().padStart(2, '0');
    const meses = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
    const mes = meses[date.getMonth()];
    return `${date.getDate()} ${mes}. ${date.getFullYear()} ${horaStr}:${minutosStr}`;
};

/** URL de Google Maps con lat,lon para que muestre la ubicación en el mapa */
const getMapsUrl = (pedido) => {
    if (!pedido) return null;
    const lat = pedido.lat != null && pedido.lat !== '' ? Number(pedido.lat) : NaN;
    const lon = pedido.lon != null && pedido.lon !== '' ? Number(pedido.lon) : NaN;
    if (Number.isFinite(lat) && Number.isFinite(lon)) {
        return `https://www.google.com/maps?q=${lat},${lon}`;
    }
    const gps = (pedido.maps_gps || '').toString().trim();
    if (!gps) return null;
    if (gps.startsWith('http')) return gps;
    const parts = gps.split(/[,;\s]+/).map((p) => p.trim()).filter(Boolean);
    if (parts.length >= 2) {
        const la = Number(parts[0]);
        const lo = Number(parts[1]);
        if (Number.isFinite(la) && Number.isFinite(lo)) {
            return `https://www.google.com/maps?q=${la},${lo}`;
        }
    }
    return `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(gps)}`;
};

const tieneEstadoAprobado = (pedido) => {
    if (!pedido.estado_pedido_detalles || pedido.estado_pedido_detalles.length === 0) return false;
    return pedido.estado_pedido_detalles.some(d => d.estado === 'A');
};

const getEstadoActual = (pedido) => {
    if (!pedido.estado_pedido_detalles || pedido.estado_pedido_detalles.length === 0) return null;
    // Primero buscar estados pendientes, ordenar por estado_id (mayor = más avanzado)
    const aprobados = pedido.estado_pedido_detalles.filter(d => d.estado === 'P');
    if (aprobados.length > 0) {
        return aprobados.sort((a, b) => (b.estado_id ?? 0) - (a.estado_id ?? 0))[0];
    }
    // Si no hay pedientes, retornar el de mayor estado_id (pendiente más avanzado)
    const todos = [...pedido.estado_pedido_detalles].sort((a, b) => (b.estado_id ?? 0) - (a.estado_id ?? 0));
    return todos[0] || null;
};

const getBadgeClassEstado = (pedido) => {
    if (!pedido.estado_pedido_detalles || pedido.estado_pedido_detalles.length === 0) {
        return 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300';
    }
    
    const detalles = pedido.estado_pedido_detalles;
    const tienePendientes = detalles.some(d => d.estado === 'P');
    const tieneDescartados = detalles.some(d => d.estado === 'D');
    const todosAprobados = detalles.every(d => d.estado === 'A');
    
    // Si hay algún estado descartado -> rojo
    if (tieneDescartados) {
        return 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300';
    }
    
    // Si hay estados pendientes -> amarillo
    if (tienePendientes) {
        return 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-300';
    }
    
    // Si todos están aprobados -> verde
    if (todosAprobados) {
        return 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300';
    }
    
    // Por defecto
    return 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300';
};

const getDetallesOrdenados = (detalles) => {
    if (!detalles || detalles.length === 0) return [];
    return [...detalles].sort((a, b) => (b.estado_id ?? 0) - (a.estado_id ?? 0));
};

/** Obtiene nodo_id, tecnologia_id, plan_id del detalle más reciente que tenga al menos uno asignado */
const getSeleccionPedido = (pedido) => {
    if (!pedido?.estado_pedido_detalles?.length) return null;
    const ordenados = [...pedido.estado_pedido_detalles].sort((a, b) => {
        const fa = a.fecha || a.created_at || '';
        const fb = b.fecha || b.created_at || '';
        return fb.localeCompare(fa);
    });
    const conDatos = ordenados.find(d => d.nodo_id != null || d.tecnologia_id != null || d.plan_id != null);
    if (!conDatos) return null;
    return {
        nodo_id: conDatos.nodo_id ?? null,
        tecnologia_id: conDatos.tecnologia_id ?? null,
        plan_id: conDatos.plan_id ?? null,
    };
};

const getNodoDescripcion = (nodoId) => {
    if (nodoId == null || nodoId === '') return '';
    const n = (props.nodos || []).find(x => String(x.nodo_id) === String(nodoId));
    return n?.descripcion ?? '';
};

const getTecnologiaDescripcion = (tecnologiaId) => {
    if (tecnologiaId == null || tecnologiaId === '') return '';
    const t = (props.tiposTecnologia || []).find(x => String(x.tecnologia_id) === String(tecnologiaId));
    return t?.descripcion ?? '';
};

const getPlanNombre = (planId) => {
    if (planId == null || planId === '') return '';
    const p = (props.planes || []).find(x => String(x.plan_id) === String(planId));
    return p?.nombre ?? '';
};

/** Primeros 3 estados como pasos de la barra de progreso: aprobado = check, pendiente = punto, no alcanzado = vacío */
const getProgressSteps = (pedido) => {
    const estadosList = (props.estados || []).slice(0, 3);
    const detalles = pedido.estado_pedido_detalles || [];
    const detalleByEstadoId = {};
    detalles.forEach(d => {
        const id = d.estado_id ?? d.estado_pedido?.estado_id;
        if (id != null && (detalleByEstadoId[id] == null || d.estado === 'P')) detalleByEstadoId[id] = d;
    });
    return estadosList.map(est => {
        const det = detalleByEstadoId[est.estado_id];
        let status = 'not_reached';
        if (det) {
            if (det.estado === 'A') status = 'approved';
            else if (det.estado === 'P') status = 'pending';
        }
        return {
            estado_id: est.estado_id,
            descripcion: est.descripcion || est.estado_pedido?.descripcion || '',
            status
        };
    });
};

/** Puede finalizar pedido: todos los estados (primeros 3) aprobados, usuario PPPoE creado y no finalizado ya */
function puedeFinalizarPedido(pedido) {
    if (!pedido || pedido.estado_instalado) return false;
    if (!pedido.usuario_pppoe_creado) return false;
    const steps = getProgressSteps(pedido);
    if (!steps.length) return false;
    return steps.every(s => s.status === 'approved');
}

// Acciones definidas en estados_pedidos.parametro (separadas por comas)
const ACCION_SELECCIONAR_NODO = 'SELECCIONAR_NODO';
const ACCION_SELECCIONAR_TIPO_TECNOLOGIA = 'SELECCIONAR_TIPO_TECNOLOGIA';
const ACCION_SELECCIONAR_PLAN = 'SELECCIONAR_PLAN';
const ACCION_CREAR_USUARIO = 'CREAR_USUARIO';
const ACCION_FINALIZAR = 'FINALIZAR';

function getAccionesFromParametro(parametro) {
    if (!parametro || typeof parametro !== 'string') return [];
    return parametro.split(',').map(s => {
        const a = s.trim().toUpperCase();
        if (a === 'SELECCIONAR_NODO') return ACCION_SELECCIONAR_NODO;
        if (a === 'SELECIONAR_PLAN') return ACCION_SELECCIONAR_PLAN;
        return a;
    }).filter(Boolean);
}

/** Extrae datos JSON de "Tipo tecnología" desde el texto de notas. Retorna { id, value } o null. */
function extraerTipoTecnologiaDeNotas(notas) {
    if (!notas || typeof notas !== 'string') return null;
    const prefix = 'Tipo tecnología:';
    const lines = notas.split(/\r?\n/);
    for (const line of lines) {
        const trimmed = line.trim();
        if (trimmed.startsWith(prefix)) {
            const jsonStr = trimmed.slice(prefix.length).trim();
            try {
                const data = JSON.parse(jsonStr);
                if (data && (data.id != null || data.value != null)) {
                    return { id: data.id, value: data.value };
                }
            } catch (_e) {
                // JSON inválido, continuar buscando
            }
        }
    }
    return null;
}

/** Filtra planes por tecnologia_id. Si tecnologiaId es null, retorna todos. */
function getPlanesFiltradosPorTecnologia(planes, tecnologiaId) {
  
    if (!planes || !planes.length) return [];
    if (tecnologiaId == null || tecnologiaId === '') return planes;
    const id = String(tecnologiaId);
    return planes.filter(p => String(p.tecnologia_id || '') === id);
}

/** Lee notas, extrae Tipo tecnología y actualiza el select de Plan si aplica. */
function aplicarFiltroPlanDesdeNotas(planes, acciones) {
    if (!acciones?.includes(ACCION_SELECCIONAR_PLAN) || !planes?.length) return;
    const notasInput = document.getElementById('swal-notas');
    if (!notasInput) return;
    const extraido = extraerTipoTecnologiaDeNotas(notasInput.value);
    console.log("Extraido: ", extraido);
    actualizarSelectPlan(planes, extraido?.id ?? null);
}

function actualizarSelectPlan(planes, tecnologiaId) {
    console.log("Funcion actualizarSelectPlan - Tecnologia ID: ", tecnologiaId);
    const selectPlan = document.getElementById('swal-select-plan');
    if (!selectPlan || !planes) return;
    const id = tecnologiaId != null && tecnologiaId !== '' ? String(tecnologiaId) : null;
    const filtrados = getPlanesFiltradosPorTecnologia(planes, id);
    const selected = selectPlan.value;
    if (id) {
        selectPlan.disabled = false;
        selectPlan.removeAttribute('disabled');
        selectPlan.innerHTML = '<option value="">-- Seleccionar plan --</option>' +
            filtrados.map(p => `<option value="${p.plan_id}">${(p.nombre || '').replace(/"/g, '&quot;')}</option>`).join('');
        const sigueValido = filtrados.some(p => String(p.plan_id) === selected);
        if (!sigueValido) selectPlan.value = '';
    } else {
        selectPlan.disabled = true;
        selectPlan.setAttribute('disabled', 'disabled');
        selectPlan.innerHTML = '<option value="">Seleccione primero el tipo de tecnología</option>';
        selectPlan.value = '';
    }
}

function descripcionTecnologiaEsGpon(desc) {
    return /gpon|epon|ftth|fibra|fiber|pon|xg-pon/i.test(desc || '');
}

function descripcionTecnologiaEsWireless(desc) {
    return /wireless|inalambr|anten|radio|wifi/i.test(desc || '');
}

function nodosFiltradosPorTecnologia(nodos, tecnologiaId, tiposTecnologia) {
    if (!nodos?.length) return [];
    if (tecnologiaId == null || tecnologiaId === '') return nodos;
    const t = (tiposTecnologia || []).find((x) => String(x.tecnologia_id) === String(tecnologiaId));
    const desc = t?.descripcion || '';
    const esGpon = descripcionTecnologiaEsGpon(desc);
    const esWireless = descripcionTecnologiaEsWireless(desc);
    if (!esGpon && !esWireless) return nodos;
    return nodos.filter((n) => {
        if (esGpon && esWireless) return n.tecnologia_gpon || n.tecnologia_wireless;
        if (esGpon) return !!n.tecnologia_gpon;
        if (esWireless) return !!n.tecnologia_wireless;
        return true;
    });
}

function etiquetaNodoSelect(n) {
    const base = (n.descripcion || `Nodo #${n.nodo_id}`).replace(/"/g, '&quot;');
    const tech = n.tecnologias_etiqueta || '';
    return tech ? `${base} (${tech})` : base;
}

function obtenerTecnologiaIdDesdeSwal(acciones) {
    const auto = document.getElementById('swal-tecnologia-auto')?.value;
    if (auto) return auto;
    const desdeNodo = document.getElementById('swal-select-tecnologia-nodo')?.value;
    if (desdeNodo) return desdeNodo;
    if (acciones.includes(ACCION_SELECCIONAR_TIPO_TECNOLOGIA)) {
        return document.getElementById('swal-select-tecnologia')?.value || '';
    }
    return '';
}

function obtenerPoolIdDesdeSwal() {
    const auto = document.getElementById('swal-pool-auto')?.value;
    if (auto) return auto;
    return document.getElementById('swal-select-pool')?.value || '';
}

async function aplicarOpcionesNodoEnSwal(nodoId, planes, acciones) {
    const wrapTech = document.getElementById('swal-tecnologia-nodo-wrap');
    const wrapPool = document.getElementById('swal-pool-wrap');
    const selectTechNodo = document.getElementById('swal-select-tecnologia-nodo');
    const selectPool = document.getElementById('swal-select-pool');
    const autoTech = document.getElementById('swal-tecnologia-auto');
    const autoPool = document.getElementById('swal-pool-auto');
    const hintTech = document.getElementById('swal-tecnologia-auto-hint');

    const reset = () => {
        if (autoTech) autoTech.value = '';
        if (autoPool) autoPool.value = '';
        if (wrapTech) wrapTech.style.display = 'none';
        if (wrapPool) wrapPool.style.display = 'none';
        if (hintTech) {
            hintTech.style.display = 'none';
            hintTech.textContent = '';
        }
        if (selectTechNodo) selectTechNodo.innerHTML = '<option value="">-- Seleccionar tipo --</option>';
        if (selectPool) selectPool.innerHTML = '<option value="">-- Seleccionar pool --</option>';
    };

    if (!nodoId) {
        reset();
        if (acciones.includes(ACCION_SELECCIONAR_PLAN)) {
            actualizarSelectPlan(planes, null);
        }
        return;
    }

    if (!props.urlOpcionesNodoAprobacion) return;

    try {
        const url = props.urlOpcionesNodoAprobacion.replace('__id__', String(nodoId));
        const { data } = await axios.get(url);

        if (data.sin_pools_activos) {
            reset();
            Swal.showValidationMessage('El nodo no tiene pools de IP activos.');
            return;
        }
        if (data.sin_tecnologia_configurada) {
            reset();
            Swal.showValidationMessage('El nodo no tiene tipos de tecnología compatibles en el catálogo.');
            return;
        }

        Swal.resetValidationMessage();

        if (data.tecnologia_id_auto) {
            if (autoTech) autoTech.value = String(data.tecnologia_id_auto);
            if (wrapTech) wrapTech.style.display = 'none';
            if (hintTech) {
                const t = (data.tecnologias || []).find((x) => String(x.tecnologia_id) === String(data.tecnologia_id_auto));
                hintTech.textContent = `Tecnología asignada desde el nodo: ${t?.descripcion || 'automática'}`;
                hintTech.style.display = 'block';
            }
            if (acciones.includes(ACCION_SELECCIONAR_PLAN)) {
                actualizarSelectPlan(planes, data.tecnologia_id_auto);
            }
        } else if (data.requiere_seleccion_tecnologia && selectTechNodo && wrapTech) {
            if (autoTech) autoTech.value = '';
            selectTechNodo.innerHTML = '<option value="">-- Seleccionar tipo --</option>' +
                (data.tecnologias || []).map((t) =>
                    `<option value="${t.tecnologia_id}">${(t.descripcion || '').replace(/"/g, '&quot;')}</option>`,
                ).join('');
            wrapTech.style.display = 'block';
            if (hintTech) hintTech.style.display = 'none';
        }

        if (data.pool_id_auto) {
            if (autoPool) autoPool.value = String(data.pool_id_auto);
            if (wrapPool) wrapPool.style.display = 'none';
        } else if (data.requiere_seleccion_pool && selectPool && wrapPool) {
            if (autoPool) autoPool.value = '';
            selectPool.innerHTML = '<option value="">-- Seleccionar pool --</option>' +
                (data.pools || []).map((p) =>
                    `<option value="${p.pool_id}">${(p.label || '').replace(/"/g, '&quot;')}</option>`,
                ).join('');
            wrapPool.style.display = 'block';
        }
    } catch (_e) {
        Swal.showValidationMessage('No se pudieron cargar las opciones del nodo.');
    }
}

function buildHtmlAcciones(acciones, nodos, planes, tiposTecnologia, tecnologiaIdSeleccionado = null) {
    let html = '';
    const tieneSeleccionNodo = acciones.includes(ACCION_SELECCIONAR_NODO);
    const nodosSelect = nodosFiltradosPorTecnologia(nodos, tecnologiaIdSeleccionado, tiposTecnologia);
    if (tieneSeleccionNodo && nodosSelect.length) {
        html += `
            <div class="mb-3">
                <label class="block text-sm font-medium text-gray-700 mb-1">Nodo</label>
                <select id="swal-select-nodo" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-purple-500 focus:border-purple-500">
                    <option value="">-- Seleccionar nodo --</option>
                    ${nodosSelect.map((n) => `<option value="${n.nodo_id}">${etiquetaNodoSelect(n)}</option>`).join('')}
                </select>
            </div>
            <input type="hidden" id="swal-tecnologia-auto" value="">
            <input type="hidden" id="swal-pool-auto" value="">
            <p id="swal-tecnologia-auto-hint" class="mb-2 text-xs text-green-700" style="display:none"></p>
            <div id="swal-tecnologia-nodo-wrap" class="mb-3" style="display:none">
                <label class="block text-sm font-medium text-gray-700 mb-1">Tipo de tecnología</label>
                <select id="swal-select-tecnologia-nodo" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-purple-500 focus:border-purple-500">
                    <option value="">-- Seleccionar tipo --</option>
                </select>
                <p class="mt-0.5 text-xs text-gray-500">El nodo maneja GPON y Wireless; elegí cuál aplica a este pedido.</p>
            </div>
            <div id="swal-pool-wrap" class="mb-3" style="display:none">
                <label class="block text-sm font-medium text-gray-700 mb-1">Pool de IP</label>
                <select id="swal-select-pool" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-purple-500 focus:border-purple-500">
                    <option value="">-- Seleccionar pool --</option>
                </select>
                <p class="mt-0.5 text-xs text-gray-500">Este nodo tiene más de un pool activo.</p>
            </div>`;
    } else if (tieneSeleccionNodo && nodos?.length && nodosSelect.length === 0) {
        html += `<p class="mb-3 text-sm text-amber-700">No hay nodos configurados para la tecnología de este pedido. Revisá las tecnologías del nodo en Sistema → Nodos.</p>`;
    }
    if (acciones.includes(ACCION_SELECCIONAR_TIPO_TECNOLOGIA) && !tieneSeleccionNodo && tiposTecnologia && tiposTecnologia.length) {
        html += `
            <div class="mb-3">
                <label class="block text-sm font-medium text-gray-700 mb-1">Tipo de tecnología</label>
                <select id="swal-select-tecnologia" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-purple-500 focus:border-purple-500">
                    <option value="">-- Seleccionar tipo --</option>
                    ${tiposTecnologia.map(t => `<option value="${t.tecnologia_id}">${(t.descripcion || '').replace(/"/g, '&quot;')}</option>`).join('')}
                </select>
            </div>`;
    }
    if (acciones.includes(ACCION_SELECCIONAR_PLAN) && planes && planes.length) {
        const requiereTecnologiaPrimero = acciones.includes(ACCION_SELECCIONAR_TIPO_TECNOLOGIA) || tieneSeleccionNodo;
        const idParaFiltrar = (tecnologiaIdSeleccionado != null && tecnologiaIdSeleccionado !== '') ? tecnologiaIdSeleccionado : null;
        const tieneTecnologiaPreseleccionada = idParaFiltrar !== null;
        const planesToShow = requiereTecnologiaPrimero
            ? (tieneTecnologiaPreseleccionada ? getPlanesFiltradosPorTecnologia(planes, idParaFiltrar) : [])
            : getPlanesFiltradosPorTecnologia(planes, idParaFiltrar);
        const planDisabled = requiereTecnologiaPrimero && !tieneTecnologiaPreseleccionada;
        html += `
            <div class="mb-3">
                <label class="block text-sm font-medium text-gray-700 mb-1">Plan</label>
                <select id="swal-select-plan" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-purple-500 focus:border-purple-500" ${planDisabled ? 'disabled' : ''}>
                    <option value="">${planDisabled ? 'Seleccione primero el tipo de tecnología' : '-- Seleccionar plan --'}</option>
                    ${planesToShow.map(p => `<option value="${p.plan_id}">${(p.nombre || '').replace(/"/g, '&quot;')}</option>`).join('')}
                </select>
                ${requiereTecnologiaPrimero ? '<p class="mt-0.5 text-xs text-gray-500">El plan se filtrará según el tipo de tecnología elegido.</p>' : ''}
            </div>`;
    }
    if (acciones.includes(ACCION_FINALIZAR)) {
        
    }
    return html;
}

function accionCrearUsuario() {
    // Reservado para implementar después
}

function accionFinalizar() {
    // Reservado para implementar después
}

const aprobarEstado = async (pedido, estadoId, parametro) => {
    const pedidoId = pedido?.pedido_id ?? pedido;
    const tecnologiaIdSeleccionado = pedido?.tecnologia_id_seleccionado ?? null;
    let notasValue = '';
    let nodoIdValue = null;
    let tecnologiaIdValue = null;
    let planIdValue = null;
    let poolIdValue = null;
    const acciones = getAccionesFromParametro(parametro);
    const htmlAcciones = buildHtmlAcciones(acciones, props.nodos, props.planes, props.tiposTecnologia, tecnologiaIdSeleccionado);

    const result = await Swal.fire({
        ...getSwalThemeOptions(),
        title: '¿Aprobar este estado?',
        html: `
            <div class="text-left">
                <p class="mb-4">Una vez aprobado, este estado no se podrá modificar.</p>
                ${htmlAcciones}
                <label class="block text-sm font-medium text-gray-700 mb-1">Notas (opcional)</label>
                <textarea id="swal-notas" 
                          rows="3" 
                          maxlength="1000"
                          placeholder="Agregar notas sobre la aprobación..."
                          class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-purple-500 focus:border-purple-500 resize-none"></textarea>
                <p id="swal-char-count" class="mt-1 text-xs text-gray-500">0/1000 caracteres</p>
            </div>
        `,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#16a34a',
        cancelButtonColor: '#6b7280',
        confirmButtonText: 'Sí, aprobar',
        cancelButtonText: 'Cancelar',
        didOpen: () => {
            const notasInput = document.getElementById('swal-notas');
            const charCount = document.getElementById('swal-char-count');
            if (notasInput && charCount) {
                notasInput.addEventListener('input', () => {
                    charCount.textContent = `${notasInput.value.length}/1000 caracteres`;
                });
            }
            const selectTecnologia = document.getElementById('swal-select-tecnologia');
            if (selectTecnologia) {
                const techIdInicial = tecnologiaIdSeleccionado != null && tecnologiaIdSeleccionado !== '' ? String(tecnologiaIdSeleccionado) : null;
                if (techIdInicial) {
                    selectTecnologia.value = techIdInicial;
                    // No llamar actualizarSelectPlan: el HTML ya tiene los planes filtrados desde buildHtmlAcciones
                }
                const onTecnologiaChange = () => {
                    const valor = selectTecnologia.value;
                    // Evitar llamada con null cuando hubo preselección y el 'change' se disparó con valor vacío
                    if (!valor && techIdInicial) return;
                    actualizarSelectPlan(props.planes, valor || null);
                };
                queueMicrotask(() => selectTecnologia.addEventListener('change', onTecnologiaChange));
            } else if (!acciones.includes(ACCION_SELECCIONAR_NODO)) {
                // No ejecutar cuando ya hay tecnología preseleccionada desde BD (evita sobrescribir el select de Plan con null)
                if (!tecnologiaIdSeleccionado) {
                    aplicarFiltroPlanDesdeNotas(props.planes, acciones);
                }
            }
            const selectNodo = document.getElementById('swal-select-nodo');
            if (selectNodo && acciones.includes(ACCION_SELECCIONAR_NODO)) {
                const onNodoChange = () => {
                    aplicarOpcionesNodoEnSwal(selectNodo.value || '', props.planes, acciones);
                };
                const onTechNodoChange = () => {
                    const tid = document.getElementById('swal-select-tecnologia-nodo')?.value;
                    if (tid && acciones.includes(ACCION_SELECCIONAR_PLAN)) {
                        actualizarSelectPlan(props.planes, tid);
                    }
                };
                queueMicrotask(() => {
                    selectNodo.addEventListener('change', onNodoChange);
                    document.getElementById('swal-select-tecnologia-nodo')?.addEventListener('change', onTechNodoChange);
                });
            }
            if (acciones.includes(ACCION_CREAR_USUARIO)) accionCrearUsuario();
            if (acciones.includes(ACCION_FINALIZAR)) accionFinalizar();
        },
        preConfirm: async () => {
            const notasInput = document.getElementById('swal-notas');
            notasValue = notasInput ? notasInput.value.trim() : '';
            if (acciones.includes(ACCION_SELECCIONAR_NODO)) {
                const selectNodo = document.getElementById('swal-select-nodo');
                if (!selectNodo?.value) {
                    Swal.showValidationMessage('Seleccioná un nodo.');
                    return false;
                }
                nodoIdValue = selectNodo.value;
                const tech = obtenerTecnologiaIdDesdeSwal(acciones);
                const pool = obtenerPoolIdDesdeSwal();
                if (props.urlOpcionesNodoAprobacion) {
                    try {
                        const url = props.urlOpcionesNodoAprobacion.replace('__id__', String(nodoIdValue));
                        const { data } = await axios.get(url);
                        if (data.sin_pools_activos) {
                            Swal.showValidationMessage('El nodo no tiene pools de IP activos.');
                            return false;
                        }
                        if (data.sin_tecnologia_configurada) {
                            Swal.showValidationMessage('El nodo no tiene tecnologías compatibles configuradas.');
                            return false;
                        }
                        if (data.requiere_seleccion_tecnologia && !tech) {
                            Swal.showValidationMessage('Seleccioná el tipo de tecnología (el nodo maneja GPON y Wireless).');
                            return false;
                        }
                        if (data.requiere_seleccion_pool && !pool) {
                            Swal.showValidationMessage('Seleccioná el pool de IP.');
                            return false;
                        }
                        if (tech) tecnologiaIdValue = tech;
                        else if (data.tecnologia_id_auto) tecnologiaIdValue = String(data.tecnologia_id_auto);
                        if (pool) poolIdValue = pool;
                        else if (data.pool_id_auto) poolIdValue = String(data.pool_id_auto);
                    } catch (_e) {
                        Swal.showValidationMessage('No se pudieron validar las opciones del nodo.');
                        return false;
                    }
                }
            } else {
                if (acciones.includes(ACCION_SELECCIONAR_TIPO_TECNOLOGIA)) {
                    const selectTecnologia = document.getElementById('swal-select-tecnologia');
                    if (selectTecnologia?.value) tecnologiaIdValue = selectTecnologia.value;
                }
            }
            if (acciones.includes(ACCION_SELECCIONAR_PLAN)) {
                const selectPlan = document.getElementById('swal-select-plan');
                if (selectPlan?.value) planIdValue = selectPlan.value;
            }
            return true;
        }
    });

    if (!result.isConfirmed) return;

    const notas = notasValue || null;
    const payload = {
        estado_id: estadoId,
        notas
    };
    if (acciones.includes(ACCION_SELECCIONAR_NODO) && nodoIdValue != null) payload.nodo_id = parseInt(nodoIdValue, 10);
    if (tecnologiaIdValue != null && tecnologiaIdValue !== '') payload.tecnologia_id = parseInt(tecnologiaIdValue, 10);
    else if (acciones.includes(ACCION_SELECCIONAR_TIPO_TECNOLOGIA) && tecnologiaIdValue != null) payload.tecnologia_id = parseInt(tecnologiaIdValue, 10);
    if (poolIdValue != null && poolIdValue !== '') payload.pool_id = parseInt(poolIdValue, 10);
    if (acciones.includes(ACCION_SELECCIONAR_PLAN) && planIdValue != null) payload.plan_id = parseInt(planIdValue, 10);

    loadingAprobar.value = `${pedidoId}-${estadoId}`;
    try {
        if (!props.aprobarEstadoUrl) {
            throw new Error('URL de aprobar estado no configurada');
        }
        const url = props.aprobarEstadoUrl.replace(':pedido', pedidoId);
        const response = await axios.post(url, payload, {
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json'
            }
        });

        await Swal.fire({
            ...getSwalThemeOptions(),
            icon: 'success',
            title: '¡Éxito!',
            text: response.data?.message || 'Estado aprobado correctamente.',
            confirmButtonColor: '#16a34a',
            timer: 1500,
            timerProgressBar: true
        });

        if (response.data && response.data.redirect) {
            window.location.href = response.data.redirect;
        } else {
            window.location.reload();
        }
    } catch (error) {
        console.error('Error:', error);
        loadingAprobar.value = null;

        if (error.response) {
            const status = error.response.status;
            const message = error.response.data?.message || 'Error al aprobar el estado';

            if (status === 400) {
                Swal.fire({
                    ...getSwalThemeOptions(),
                    icon: 'warning',
                    title: 'Advertencia',
                    text: message,
                    confirmButtonColor: '#7c3aed'
                });
            } else {
                Swal.fire({
                    ...getSwalThemeOptions(),
                    icon: 'error',
                    title: 'Error',
                    text: message,
                    confirmButtonColor: '#7c3aed'
                });
            }
        } else {
            Swal.fire({
                ...getSwalThemeOptions(),
                icon: 'error',
                title: 'Error',
                text: 'Error al aprobar el estado. Por favor, intenta nuevamente.',
                confirmButtonColor: '#7c3aed'
            });
        }
    }
};

const crearUsuarioPppoe = async (pedido) => {
    const pedidoId = pedido?.pedido_id ?? pedido;
    const url = props.crearUsuarioPppoeUrl
        ? props.crearUsuarioPppoeUrl.replace(':pedido', String(pedidoId))
        : `/pedidos/${pedidoId}/crear-usuario-pppoe`;

    loadingCrearPppoe.value = pedidoId;
    try {
        const response = await axios.get(url, {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        });
        const msg = response.data?.message || 'Usuario PPPoE creado.';
        const syncOk = response.data?.sync_ok;
        await Swal.fire({
            icon: syncOk === false ? 'warning' : 'success',
            title: syncOk === false ? 'Creado con advertencia' : 'Usuario PPPoE',
            text: msg,
            confirmButtonColor: '#4f46e5',
        });
        if (response.data?.redirect) {
            window.location.href = response.data.redirect;
        } else {
            window.location.reload();
        }
    } catch (error) {
        const msg = error.response?.data?.message || 'No se pudo crear el usuario PPPoE.';
        await Swal.fire({
            icon: error.response?.status === 422 ? 'warning' : 'error',
            title: error.response?.status === 422 ? 'No se puede crear' : 'Error',
            text: msg,
            confirmButtonColor: '#7c3aed',
        });
    } finally {
        loadingCrearPppoe.value = null;
    }
};
const crearAgenda = (pedido) => {
    const pedidoId = pedido?.pedido_id ?? pedido;
    if (props.crearAgendaUrl) {
        const url = props.crearAgendaUrl.replace(':pedido', String(pedidoId));
        window.location.href = url;
    } else {
        window.location.href = `/pedidos/${pedidoId}/crear-agenda`;
    }
};
const finalizarPedido = async (pedido) => {
    const pedidoId = pedido?.pedido_id ?? pedido;
    if (!props.finalizarPedidoUrl) return;

    const confirmResult = await Swal.fire({
        title: '¿Finalizar pedido?',
        html: 'Se marcará el pedido como instalado y los servicios asociados quedarán con estado activo. ¿Continuar?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#16a34a',
        cancelButtonColor: '#6b7280',
        confirmButtonText: 'Sí, finalizar',
        cancelButtonText: 'Cancelar'
    });
    if (!confirmResult.isConfirmed) return;

    loadingFinalizar.value = pedidoId;
    try {
        const url = props.finalizarPedidoUrl.replace(':pedido', String(pedidoId));
        const response = await axios.post(url, {}, {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        });
        await Swal.fire({
            icon: 'success',
            title: 'Pedido finalizado',
            text: response.data?.message || 'Instalación marcada como completada.',
            confirmButtonColor: '#16a34a'
        });
        if (response.data?.redirect) {
            window.location.href = response.data.redirect;
        } else {
            window.location.reload();
        }
    } catch (error) {
        const msg = error.response?.data?.message || 'No se pudo finalizar el pedido.';
        await Swal.fire({
            icon: error.response?.status === 400 ? 'warning' : 'error',
            title: error.response?.status === 400 ? 'Advertencia' : 'Error',
            text: msg,
            confirmButtonColor: '#7c3aed'
        });
    } finally {
        loadingFinalizar.value = null;
    }
};

const reabrirEstado = async (pedidoId, estadoId) => {
    const result = await Swal.fire({
        title: '¿Reabrir este estado?',
        text: 'El estado volverá a pendiente y podrá aprobar o descartar nuevamente.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#d97706',
        cancelButtonColor: '#6b7280',
        confirmButtonText: 'Sí, reabrir',
        cancelButtonText: 'Cancelar',
    });

    if (!result.isConfirmed) return;

    loadingReabrir.value = `${pedidoId}-${estadoId}`;
    try {
        if (!props.reabrirEstadoUrl) {
            throw new Error('URL de reabrir estado no configurada');
        }
        const url = props.reabrirEstadoUrl.replace(':pedido', pedidoId);
        const response = await axios.post(url, { estado_id: estadoId }, {
            headers: { 'Accept': 'application/json', 'Content-Type': 'application/json' }
        });

        await Swal.fire({
            icon: 'success',
            title: '¡Éxito!',
            text: response.data?.message || 'Estado reabierto correctamente.',
            confirmButtonColor: '#7c3aed',
            timer: 1500,
            timerProgressBar: true
        });

        if (response.data?.redirect) {
            window.location.href = response.data.redirect;
        } else {
            window.location.reload();
        }
    } catch (error) {
        console.error('Error:', error);
        loadingReabrir.value = null;
        const message = error.response?.data?.message || 'Error al reabrir el estado. Intente nuevamente.';
        Swal.fire({
            icon: error.response?.status === 400 ? 'warning' : 'error',
            title: error.response?.status === 400 ? 'Advertencia' : 'Error',
            text: message,
            confirmButtonColor: '#7c3aed'
        });
    }
};

const descartarEstado = async (pedidoId, estadoId) => {
    let notasValue = '';
    
    const result = await Swal.fire({
        title: '¿Descartar este estado?',
        html: `
            <div class="text-left">
                <p class="mb-4">Esta acción no se puede deshacer.</p>
                <label class="block text-sm font-medium text-gray-700 mb-1">Notas (opcional)</label>
                <textarea id="swal-notas-descartar" 
                          rows="3" 
                          maxlength="1000"
                          placeholder="Agregar notas sobre el descarte..."
                          class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-purple-500 focus:border-purple-500 resize-none"></textarea>
                <p id="swal-char-count-descartar" class="mt-1 text-xs text-gray-500">0/1000 caracteres</p>
            </div>
        `,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc2626',
        cancelButtonColor: '#6b7280',
        confirmButtonText: 'Sí, descartar',
        cancelButtonText: 'Cancelar',
        didOpen: () => {
            const notasInput = document.getElementById('swal-notas-descartar');
            const charCount = document.getElementById('swal-char-count-descartar');
            if (notasInput && charCount) {
                notasInput.addEventListener('input', () => {
                    charCount.textContent = `${notasInput.value.length}/1000 caracteres`;
                });
            }
        },
        preConfirm: () => {
            const notasInput = document.getElementById('swal-notas-descartar');
            notasValue = notasInput ? notasInput.value.trim() : '';
            return true;
        }
    });
    
    if (!result.isConfirmed) return;
    
    const notas = notasValue || null;
    
    loadingDescartar.value = `${pedidoId}-${estadoId}`;
    try {
        if (!props.descartarEstadoUrl) {
            throw new Error('URL de descartar estado no configurada');
        }
        const url = props.descartarEstadoUrl.replace(':pedido', pedidoId);
        const response = await axios.post(url, {
            estado_id: estadoId,
            notas: notas
        }, {
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json'
            }
        });
        
        // Mostrar mensaje de éxito
        await Swal.fire({
            icon: 'success',
            title: '¡Éxito!',
            text: response.data?.message || 'Estado descartado correctamente.',
            confirmButtonColor: '#7c3aed',
            timer: 1500,
            timerProgressBar: true
        });
        
        if (response.data && response.data.redirect) {
            window.location.href = response.data.redirect;
        } else {
            window.location.reload();
        }
    } catch (error) {
        console.error('Error:', error);
        loadingDescartar.value = null;
        
        if (error.response) {
            const status = error.response.status;
            const message = error.response.data?.message || 'Error al descartar el estado';
            
            if (status === 400) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Advertencia',
                    text: message,
                    confirmButtonColor: '#7c3aed'
                });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: message,
                    confirmButtonColor: '#7c3aed'
                });
            }
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Error al descartar el estado. Por favor, intenta nuevamente.',
                confirmButtonColor: '#7c3aed'
            });
        }
    }
};

const eliminarPedido = async (pedidoId) => {
    const result = await Swal.fire({
        title: '¿Eliminar este pedido?',
        text: 'Esta acción no se puede deshacer.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc2626',
        cancelButtonColor: '#6b7280',
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar'
    });
    
    if (!result.isConfirmed) return;
    
    try {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = `/pedidos/${pedidoId}`;
        
        const tokenInput = document.createElement('input');
        tokenInput.type = 'hidden';
        tokenInput.name = '_token';
        tokenInput.value = props.csrfToken;
        form.appendChild(tokenInput);
        
        const methodInput = document.createElement('input');
        methodInput.type = 'hidden';
        methodInput.name = '_method';
        methodInput.value = 'DELETE';
        form.appendChild(methodInput);
        
        document.body.appendChild(form);
        form.submit();
    } catch (error) {
        console.error('Error:', error);
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'Error al eliminar el pedido',
            confirmButtonColor: '#7c3aed'
        });
    }
};
</script>
