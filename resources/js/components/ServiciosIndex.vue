<template>
  <div class="max-w-7xl mx-auto">
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-visible mb-6">
      <form class="p-4 flex flex-wrap items-center gap-3 overflow-visible" @submit.prevent>
        <!-- Título -->
        

        <!-- Grupo búsqueda: input + botón con icono -->
        <div class="flex flex-1 min-w-[400px] max-w-xl">
          <input
            v-model="filtros.buscar"
            type="text"
            placeholder="Buscar aquí..."
            class="flex-1 px-4 py-2.5 rounded-l-lg border border-gray-300 dark:border-gray-600 border-r-0 focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 focus:z-10 focus:outline-none bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-500"
          />
          <button
            type="button"
            class="inline-flex items-center justify-center px-4 py-2.5 bg-blue-600 text-white rounded-r-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-colors shrink-0"
          >
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
            </svg>
          </button>
        </div>

        <!-- Filtros: Dropdown Estado -->
        <div class="flex-1 min-w-[1rem]"></div>
        <div class="relative shrink-0" ref="dropdownEstadoRef">
          <button
            type="button"
            @click="dropdownEstadoOpen = !dropdownEstadoOpen; dropdownEstadoPagoOpen = false"
            class="inline-flex items-center gap-1.5 px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-200 focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-colors text-sm font-medium min-w-[140px] justify-between"
          >
            <span>{{ filtros.estado === 'todos' ? 'Estado' : estadoLabel(filtros.estado) }}</span>
            <svg class="w-4 h-4 text-gray-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
            </svg>
          </button>
          <div
            v-show="dropdownEstadoOpen"
            class="absolute left-0 mt-1.5 min-w-[160px] py-1 bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 shadow-lg z-20"
          >
            <button
              v-for="opt in opcionesEstado"
              :key="opt.value"
              type="button"
              @click="filtros.estado = opt.value; dropdownEstadoOpen = false"
              class="w-full px-4 py-2.5 text-left text-sm hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors"
              :class="filtros.estado === opt.value ? 'text-blue-600 dark:text-blue-400 font-medium bg-blue-50 dark:bg-blue-900/30' : 'text-gray-700 dark:text-gray-300'"
            >
              {{ opt.label }}
            </button>
          </div>
        </div>
        <!-- Filtro fecha activo -->
        <div v-if="filtros.fecha_desde || filtros.fecha_hasta" class="flex items-center gap-1.5 shrink-0">
          <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-cyan-100 dark:bg-cyan-900/30 text-cyan-800 dark:text-cyan-200 text-sm font-medium">
            {{ filtros.fecha_desde === filtros.fecha_hasta ? 'Hoy' : 'Este mes' }}
          </span>
          <button
            type="button"
            @click="filtros.fecha_desde = ''; filtros.fecha_hasta = ''"
            class="p-1 rounded text-cyan-600 dark:text-cyan-400 hover:bg-cyan-100 dark:hover:bg-cyan-900/30"
            title="Quitar filtro fecha"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
          </button>
        </div>
        <!-- Dropdown Estado de Pago -->
        <div class="relative shrink-0" ref="dropdownEstadoPagoRef">
          <button
            type="button"
            @click="dropdownEstadoPagoOpen = !dropdownEstadoPagoOpen; dropdownEstadoOpen = false"
            class="inline-flex items-center gap-1.5 px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-200 focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-colors text-sm font-medium min-w-[160px] justify-between"
          >
            <span>{{ filtros.estado_pago === 'todos' ? 'Estado de Pago' : estadoPagoLabel(filtros.estado_pago) }}</span>
            <svg class="w-4 h-4 text-gray-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
            </svg>
          </button>
          <div
            v-show="dropdownEstadoPagoOpen"
            class="absolute left-0 mt-1.5 min-w-[180px] py-1 bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 shadow-lg z-20"
          >
            <button
              v-for="opt in opcionesEstadoPago"
              :key="opt.value"
              type="button"
              @click="filtros.estado_pago = opt.value; dropdownEstadoPagoOpen = false"
              class="w-full px-4 py-2.5 text-left text-sm hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors"
              :class="filtros.estado_pago === opt.value ? 'text-blue-600 dark:text-blue-400 font-medium bg-blue-50 dark:bg-blue-900/30' : 'text-gray-700 dark:text-gray-300'"
            >
              {{ opt.label }}
            </button>
          </div>
        </div>


        <!-- Espaciador -->
        <div class="flex-1 min-w-[1rem]"></div>

        <!-- Botones de acción (solo iconos) -->
        <template v-if="canCreateFactura">
          <form id="form-generar-interna" method="POST" :action="formAction" class="inline shrink-0">
            <input type="hidden" name="_token" :value="csrfToken" />
            <input
              v-for="id in selectedIds"
              :key="id"
              type="hidden"
              name="servicio_ids[]"
              :value="id"
            />
            <button
              type="submit"
              :disabled="selectedCount === 0"
              :title="botonGenerarTexto"
              class="inline-flex items-center justify-center p-2 rounded-lg transition-colors focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed"
              :class="selectedCount > 0
                ? 'bg-blue-600 text-white hover:bg-blue-700'
                : 'bg-gray-300 text-gray-500'"
            >
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
              </svg>
            </button>
          </form>
        </template>
        <a
          :href="urlCreate"
          title="Nuevo servicio"
          class="inline-flex items-center justify-center p-2 rounded-lg text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-colors shrink-0"
        >
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
          </svg>
        </a>
      </form>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700">

      <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
          <thead class="bg-gray-50 dark:bg-gray-700/50">
            <tr>
              <th v-if="canCreateFactura" class="px-4 py-3 w-10">
                <input
                  type="checkbox"
                  class="rounded border-gray-300 text-purple-600 focus:ring-purple-500"
                  :checked="allSelected"
                  :indeterminate.prop="someSelected && !allSelected"
                  title="Seleccionar todos de esta página"
                  @change="toggleSelectAll"
                />
              </th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Cliente</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Plan</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Router/ IP</th>

              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Estado Servicio</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Estado Pago</th>
              <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Acciones</th>
            </tr>
          </thead>
          <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
            <template v-if="serviciosFiltrados.length === 0">
              <tr>
                <td :colspan="canCreateFactura ? 9 : 8" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">
                  No hay servicios. <a :href="urlCreate" class="text-purple-600 dark:text-purple-400 hover:underline">Crear servicio</a>.
                </td>
              </tr>
            </template>
            <tr
              v-else
              v-for="s in serviciosPaginados"
              :key="s.servicio_id"
              class="hover:bg-gray-50 dark:hover:bg-gray-700/50"
              :class="{ 'cursor-pointer': canCreateFactura }"
              @click="toggleRow(s, $event)"
            >
              <td v-if="canCreateFactura" class="px-4 py-3">
                <input
                  type="checkbox"
                  :value="s.servicio_id"
                  class="rounded border-gray-300 text-purple-600 focus:ring-purple-500"
                  :checked="selectedIds.includes(s.servicio_id)"
                  @change="toggleServicio(s.servicio_id)"
                />
              </td>
              <td class="px-4 py-3 text-sm">
                <span class="text-gray-600 dark:text-gray-300 font-medium">{{ s.cliente?.nombre ?? '' }} {{ s.cliente?.apellido ?? '' }}</span><br>
                <span class="text-gray-600 dark:text-gray-400 text-xs">{{ formatCedula(s.cliente?.cedula) }}</span>
              </td>
              <td class="px-4 py-3 text-sm">
                <span class="text-gray-600 dark:text-gray-300 font-medium">{{ s.plan?.nombre ?? '—' }}</span><br>
                <span class="text-gray-600 dark:text-gray-400 text-xs">{{ s.fecha_instalacion_formatted ?? '—' }}</span>
              </td>
              <td class="px-4 py-3 text-sm">
                <a v-if="s.ip" :href="'http://' + s.ip" target="_blank">
                  <span class="text-gray-600 dark:text-gray-300 font-medium">{{ s.pool?.router?.nombre ?? '—' }}</span><br>
                  <span class="text-gray-600 dark:text-gray-400 text-xs">{{ s.ip ?? '—' }}</span>
                </a>
                <template v-else>
                  <span class="text-gray-600 dark:text-gray-300 font-medium">—</span><br>
                  <span class="text-gray-600 dark:text-gray-400 text-xs">—</span>
                </template>
              </td>
              
              <td class="px-4 py-3">
                <span
                  class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-medium"
                  :class="estadoClase(s.estado)"
                >
                  <span
                    class="w-2 h-2 rounded-full flex-shrink-0"
                    :class="estadoLedDotClase(s.estado)"
                    :title="estadoLabel(s.estado)"
                  ></span>
                  {{ estadoLabel(s.estado) }}
                </span>
              </td>
              <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-300">{{ estadoPagoLabel(s.estado_pago) }}</td>
              <td class="px-4 py-3">
                <div class="flex items-center justify-end gap-0.5 acciones-row" :ref="el => setAccionesRef(s.servicio_id, el)">
                  <!-- Activar / Suspender siempre visibles -->
                  <form
                    v-if="s.estado === 'S'"
                    :action="urlActivar.replace('__id__', s.servicio_id)"
                    method="POST"
                    class="inline"
                  >
                    <input type="hidden" name="_token" :value="csrfToken" />
                    <button type="submit" class="p-2 text-green-600 dark:text-green-400 hover:bg-green-50 dark:hover:bg-green-900/30 rounded-lg transition-colors" title="Activar servicio (sistema + router)">
                      <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    </button>
                  </form>
                  <form
                    v-if="s.estado === 'A'"
                    :action="urlSuspender.replace('__id__', s.servicio_id)"
                    method="POST"
                    class="inline"
                  >
                    <input type="hidden" name="_token" :value="csrfToken" />
                    <button type="submit" class="p-2 text-amber-600 dark:text-amber-400 hover:bg-amber-50 dark:hover:bg-amber-900/30 rounded-lg transition-colors" title="Suspender servicio (sistema + router)">
                      <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 9v6m4-6v6m7-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    </button>
                  </form>
                  <!-- Menú 3 puntos: Sync, Editar, Migrar, Eliminar -->
                  <div class="relative inline-block">
                    <button
                      type="button"
                      @click.prevent="toggleAcciones(s.servicio_id, $event)"
                      class="p-2 text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition-colors"
                      title="Más acciones"
                    >
                      <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M12 8c1.1 0 2-.9 2-2s-.9-2-2-2-2 .9-2 2 .9 2 2 2zm0 2c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2zm0 6c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2z"/></svg>
                    </button>
                  </div>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div v-if="serviciosFiltrados.length > perPage" class="px-4 py-3 border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-700/50 flex flex-wrap items-center justify-between gap-3">
        <p class="text-sm text-gray-600 dark:text-gray-400">
          Mostrando {{ paginationInfo.start }}-{{ paginationInfo.end }} de {{ paginationInfo.total }}
        </p>
        <div class="flex items-center gap-1">
          <button
            type="button"
            :disabled="currentPage <= 1"
            @click="goToPage(currentPage - 1)"
            class="px-3 py-1.5 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-200 disabled:opacity-50 disabled:cursor-not-allowed hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors text-sm"
          >
            Anterior
          </button>
          <template v-for="(p, idx) in visiblePages" :key="idx">
            <button
              v-if="p !== '...'"
              type="button"
              @click="goToPage(p)"
              class="min-w-[2rem] px-2 py-1.5 rounded-lg text-sm font-medium transition-colors"
              :class="p === currentPage
                ? 'bg-blue-600 text-white'
                : 'border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-600'"
            >
              {{ p }}
            </button>
            <span v-else class="px-1 text-gray-500">…</span>
          </template>
          <button
            type="button"
            :disabled="currentPage >= totalPages"
            @click="goToPage(currentPage + 1)"
            class="px-3 py-1.5 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-200 disabled:opacity-50 disabled:cursor-not-allowed hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors text-sm"
          >
            Siguiente
          </button>
        </div>
      </div>
    </div>

    <!-- Dropdown teleportado: se muestra encima del contenido, sin scroll -->
    <Teleport to="body">
      <div
        v-show="openAccionesId && dropdownPos"
        ref="dropdownMenuRef"
        class="fixed py-1 min-w-[180px] bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 shadow-lg z-[9999]"
        :style="dropdownPos ? { top: dropdownPos.top + 'px', left: dropdownPos.left + 'px' } : {}"
      >
        <template v-if="servicioAcciones">
          <button
            v-if="servicioAcciones.usuario_pppoe"
            type="button"
            class="w-full px-4 py-2.5 text-left text-sm text-slate-600 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-700/50 flex items-center gap-2"
            @click="abrirModalPppoe(servicioAcciones)"
          >
            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"></path></svg>
            Ver usuario y contraseña PPPoE
          </button>
          <a
            v-if="canCreateFactura && urlCrearFacturaInterna"
            :href="urlCrearFacturaInterna.replace('__id__', servicioAcciones.servicio_id)"
            class="block px-4 py-2.5 text-sm text-purple-600 dark:text-purple-400 hover:bg-purple-50 dark:hover:bg-purple-900/30 flex items-center gap-2"
          >
            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
            Crear factura
          </a>
          <form
            v-if="servicioAcciones.usuario_pppoe && servicioAcciones.pool?.router"
            :action="urlSyncPppoe.replace('__id__', servicioAcciones.servicio_id)"
            method="POST"
            class="block"
          >
            <input type="hidden" name="_token" :value="csrfToken" />
            <button type="submit" class="w-full px-4 py-2.5 text-left text-sm text-cyan-600 dark:text-cyan-400 hover:bg-cyan-50 dark:hover:bg-cyan-900/30 flex items-center gap-2">
              <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
              Sincronizar PPPoE
            </button>
          </form>
          <a
            :href="urlEdit.replace('__id__', servicioAcciones.servicio_id)"
            class="block px-4 py-2.5 text-sm text-purple-600 dark:text-purple-400 hover:bg-purple-50 dark:hover:bg-purple-900/30 flex items-center gap-2"
          >
            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
            Editar
          </a>
          <a
            v-if="urlMigrar && servicioAcciones.pool?.router?.nodo"
            :href="urlMigrar.replace('__id__', servicioAcciones.servicio_id)"
            class="block px-4 py-2.5 text-sm text-indigo-600 dark:text-indigo-400 hover:bg-indigo-50 dark:hover:bg-indigo-900/30 flex items-center gap-2"
          >
            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"></path></svg>
            Migrar a otro nodo
          </a>
          <form
            :action="urlDestroy.replace('__id__', servicioAcciones.servicio_id)"
            method="POST"
            class="block"
            @submit.prevent="confirmDestroy($event)"
          >
            <input type="hidden" name="_token" :value="csrfToken" />
            <input type="hidden" name="_method" value="DELETE" />
            <button type="submit" class="w-full px-4 py-2.5 text-left text-sm text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/30 flex items-center gap-2">
              <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
              Eliminar
            </button>
          </form>
        </template>
      </div>
    </Teleport>

    <!-- Modal Usuario y Contraseña PPPoE -->
    <Teleport to="body">
      <div
        v-if="modalPppoeVisible"
        class="fixed inset-0 z-[9999] flex items-center justify-center p-4 bg-black/50"
        @click.self="cerrarModalPppoe"
      >
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-xl max-w-md w-full overflow-hidden">
          <div class="p-4 border-b border-gray-200 dark:border-gray-700 flex justify-between items-center">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Usuario y contraseña PPPoE</h3>
            <button type="button" class="p-2 text-gray-500 hover:text-gray-700 dark:hover:text-gray-300 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700" @click="cerrarModalPppoe" aria-label="Cerrar">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
          </div>
          <div class="p-4 space-y-4">
            <div v-if="modalPppoeServicio" class="space-y-3">
              <div>
                <label class="block text-sm font-medium text-gray-600 dark:text-gray-400 mb-1">Usuario</label>
                <div class="flex gap-2">
                  <input
                    type="text"
                    :value="modalPppoeServicio.usuario_pppoe ?? ''"
                    readonly
                    class="flex-1 px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-700 text-gray-900 dark:text-gray-100 text-sm font-mono"
                  />
                  <button
                    type="button"
                    @click="copiarAlPortapapeles(modalPppoeServicio.usuario_pppoe ?? '', 'usuario')"
                    class="px-3 py-2 rounded-lg bg-blue-600 text-white hover:bg-blue-700 text-sm font-medium shrink-0 flex items-center gap-1.5"
                  >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path></svg>
                    {{ copiadoUsuario ? 'Copiado' : 'Copiar' }}
                  </button>
                </div>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-600 dark:text-gray-400 mb-1">Contraseña</label>
                <div class="flex gap-2">
                  <input
                    :type="mostrarPassword ? 'text' : 'password'"
                    :value="modalPppoeServicio.password_pppoe ?? ''"
                    readonly
                    class="flex-1 px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-700 text-gray-900 dark:text-gray-100 text-sm font-mono"
                  />
                  <button
                    type="button"
                    @click="mostrarPassword = !mostrarPassword"
                    class="px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600 text-sm shrink-0"
                    :title="mostrarPassword ? 'Ocultar' : 'Mostrar'"
                  >
                    <svg v-if="mostrarPassword" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"></path></svg>
                    <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                  </button>
                  <button
                    type="button"
                    @click="copiarAlPortapapeles(modalPppoeServicio.password_pppoe ?? '', 'password')"
                    class="px-3 py-2 rounded-lg bg-blue-600 text-white hover:bg-blue-700 text-sm font-medium shrink-0 flex items-center gap-1.5"
                  >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path></svg>
                    {{ copiadoPassword ? 'Copiado' : 'Copiar' }}
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';

const opcionesEstado = [
  { value: 'todos', label: 'Todos' },
  { value: 'P', label: 'Pendiente' },
  { value: 'A', label: 'Activo' },
  { value: 'S', label: 'Suspendido' },
  { value: 'C', label: 'Cancelado' },
];
const opcionesEstadoPago = [
  { value: 'todos', label: 'Todos' },
  { value: 'pagado', label: 'Pagado' },
  { value: 'parcial', label: 'Parcial' },
  { value: 'exento', label: 'Exento' },
  { value: 'pendiente', label: 'Pendiente' },
];

const props = defineProps({
  servicios: { type: Array, default: () => [] },
  clientes: { type: Array, default: () => [] },
  canCreateFactura: { type: Boolean, default: false },
  formAction: { type: String, default: '' },
  csrfToken: { type: String, default: '' },
  urlIndex: { type: String, default: '' },
  urlCreate: { type: String, default: '' },
  urlEdit: { type: String, default: '' },
  urlMigrar: { type: String, default: '' },
  urlDestroy: { type: String, default: '' },
  urlActivar: { type: String, default: '' },
  urlSuspender: { type: String, default: '' },
  urlSyncPppoe: { type: String, default: '' },
  urlCrearFacturaInterna: { type: String, default: '' },
  filtros: { type: Object, default: () => ({ buscar: '', cliente_id: '', estado: 'todos', estado_pago: 'todos', fecha_desde: '', fecha_hasta: '' }) },
});

const STORAGE_KEY_BUSCAR = 'servicios_index_buscar';

function getBuscarInicial() {
  try {
    const stored = localStorage.getItem(STORAGE_KEY_BUSCAR);
    if (stored !== null && typeof stored === 'string') return stored;
  } catch (e) {}
  return props.filtros?.buscar ?? '';
}

const serviciosList = ref([...(props.servicios || [])]);
const currentPage = ref(1);
const perPage = ref(15);
const filtros = ref({
  buscar: getBuscarInicial(),
  cliente_id: props.filtros?.cliente_id ?? '',
  estado: props.filtros?.estado ?? 'todos',
  estado_pago: props.filtros?.estado_pago ?? 'todos',
  fecha_desde: props.filtros?.fecha_desde ?? '',
  fecha_hasta: props.filtros?.fecha_hasta ?? '',
});
const dropdownEstadoOpen = ref(false);
const dropdownEstadoPagoOpen = ref(false);
const dropdownEstadoRef = ref(null);
const dropdownEstadoPagoRef = ref(null);
const openAccionesId = ref(null);
const accionesRefs = ref({});
const dropdownPos = ref(null);
const dropdownMenuRef = ref(null);
const modalPppoeVisible = ref(false);
const modalPppoeServicio = ref(null);
const mostrarPassword = ref(false);
const copiadoUsuario = ref(false);
const copiadoPassword = ref(false);

function setAccionesRef(id, el) {
  if (el) {
    accionesRefs.value[id] = el;
  } else {
    delete accionesRefs.value[id];
  }
}

function abrirModalPppoe(servicio) {
  openAccionesId.value = null;
  dropdownPos.value = null;
  modalPppoeServicio.value = servicio;
  modalPppoeVisible.value = true;
  mostrarPassword.value = false;
  copiadoUsuario.value = false;
  copiadoPassword.value = false;
}

function cerrarModalPppoe() {
  modalPppoeVisible.value = false;
  modalPppoeServicio.value = null;
}

function copiarAlPortapapeles(texto, tipo) {
  const str = String(texto ?? '');
  if (!str) return;

  const marcarCopiado = () => {
    if (tipo === 'usuario') {
      copiadoUsuario.value = true;
      setTimeout(() => { copiadoUsuario.value = false; }, 1500);
    } else if (tipo === 'password') {
      copiadoPassword.value = true;
      setTimeout(() => { copiadoPassword.value = false; }, 1500);
    }
  };

  const fallbackCopy = () => {
    const textarea = document.createElement('textarea');
    textarea.value = str;
    textarea.setAttribute('readonly', '');
    textarea.style.position = 'fixed';
    textarea.style.left = '-9999px';
    textarea.style.top = '0';
    textarea.style.opacity = '0';
    document.body.appendChild(textarea);
    textarea.focus();
    textarea.select();
    textarea.setSelectionRange(0, str.length);
    let result = false;
    try {
      result = document.execCommand('copy');
    } catch (_) {}
    document.body.removeChild(textarea);
    return result;
  };

  const intentarCopiar = () => {
    if (navigator.clipboard?.writeText) {
      navigator.clipboard.writeText(str)
        .then(() => marcarCopiado())
        .catch(() => { if (fallbackCopy()) marcarCopiado(); });
    } else {
      if (fallbackCopy()) marcarCopiado();
    }
  };
  intentarCopiar();
}

function toggleAcciones(id, ev) {
  if (openAccionesId.value === id) {
    openAccionesId.value = null;
    dropdownPos.value = null;
  } else {
    openAccionesId.value = id;
    const btn = ev?.currentTarget;
    if (btn) {
      const rect = btn.getBoundingClientRect();
      const dropdownHeight = 220;
      const dropdownWidth = 180;
      const spaceBelow = window.innerHeight - rect.bottom;
      const showAbove = spaceBelow < dropdownHeight;
      dropdownPos.value = {
        top: showAbove ? rect.top - dropdownHeight - 4 : rect.bottom + 4,
        left: Math.max(8, Math.min(rect.right - dropdownWidth, window.innerWidth - dropdownWidth - 8)),
      };
    } else {
      dropdownPos.value = { top: 0, left: 0 };
    }
  }
}

function closeDropdowns(ev) {
  if (
    dropdownEstadoRef.value && !dropdownEstadoRef.value.contains(ev.target) &&
    dropdownEstadoPagoRef.value && !dropdownEstadoPagoRef.value.contains(ev.target)
  ) {
    dropdownEstadoOpen.value = false;
    dropdownEstadoPagoOpen.value = false;
  }
  if (openAccionesId.value !== null) {
    const el = accionesRefs.value[openAccionesId.value];
    const menuEl = dropdownMenuRef.value;
    const clickedInside = el?.contains(ev.target) || menuEl?.contains(ev.target);
    if (!clickedInside) {
      openAccionesId.value = null;
      dropdownPos.value = null;
    }
  }
}

const selectedIds = ref([]);

const selectedCount = computed(() => selectedIds.value.length);

const serviciosFiltrados = computed(() => {
  let list = serviciosList.value;
  const { buscar, cliente_id, estado, estado_pago, fecha_desde, fecha_hasta } = filtros.value;

  if (fecha_desde || fecha_hasta) {
    list = list.filter(s => {
      const f = s.fecha_instalacion;
      if (!f) return false;
      if (fecha_desde && f < fecha_desde) return false;
      if (fecha_hasta && f > fecha_hasta) return false;
      return true;
    });
  }
  if (buscar && buscar.trim()) {
    const q = buscar.trim().toLowerCase();
    list = list.filter(s => {
      const ip = (s.ip || '').toLowerCase();
      const pppoe = (s.usuario_pppoe || '').toLowerCase();
      const cedula = String(s.cliente?.cedula ?? '').toLowerCase();
      const nombre = (s.cliente?.nombre ?? '').toLowerCase();
      const apellido = (s.cliente?.apellido ?? '').toLowerCase();
      const planNombre = (s.plan?.nombre ?? '').toLowerCase();
      return ip.includes(q) || pppoe.includes(q) || cedula.includes(q) ||
        nombre.includes(q) || apellido.includes(q) || planNombre.includes(q);
    });
  }
  if (cliente_id) {
    list = list.filter(s => String(s.cliente?.cliente_id) === String(cliente_id));
  }
  if (estado && estado !== 'todos') {
    list = list.filter(s => (s.estado || 'P') === estado);
  }
  if (estado_pago && estado_pago !== 'todos') {
    list = list.filter(s => (s.estado_pago || '') === estado_pago);
  }
  return list;
});

const totalPages = computed(() => Math.ceil(serviciosFiltrados.value.length / perPage.value) || 1);

const serviciosPaginados = computed(() => {
  const list = serviciosFiltrados.value;
  const start = (currentPage.value - 1) * perPage.value;
  return list.slice(start, start + perPage.value);
});

const paginationInfo = computed(() => {
  const total = serviciosFiltrados.value.length;
  if (total === 0) return { start: 0, end: 0, total: 0 };
  const start = (currentPage.value - 1) * perPage.value + 1;
  const end = Math.min(currentPage.value * perPage.value, total);
  return { start, end, total };
});

const visiblePages = computed(() => {
  const total = totalPages.value;
  const curr = currentPage.value;
  if (total <= 7) return Array.from({ length: total }, (_, i) => i + 1);
  const pages = [];
  if (curr <= 4) {
    pages.push(1, 2, 3, 4, 5, '...', total);
  } else if (curr >= total - 3) {
    pages.push(1, '...', total - 4, total - 3, total - 2, total - 1, total);
  } else {
    pages.push(1, '...', curr - 1, curr, curr + 1, '...', total);
  }
  return pages;
});

watch(filtros, () => { currentPage.value = 1; }, { deep: true });

watch(() => filtros.value.buscar, (val) => {
  try {
    if (val != null && val !== '') {
      localStorage.setItem(STORAGE_KEY_BUSCAR, String(val));
    } else {
      localStorage.removeItem(STORAGE_KEY_BUSCAR);
    }
  } catch (e) {}
}, { immediate: true });

function goToPage(page) {
  const p = Math.max(1, Math.min(page, totalPages.value));
  currentPage.value = p;
}

const servicioAcciones = computed(() => {
  if (!openAccionesId.value) return null;
  return serviciosFiltrados.value.find(s => s.servicio_id === openAccionesId.value) ?? null;
});

const allSelected = computed(() => {
  if (serviciosPaginados.value.length === 0) return false;
  return serviciosPaginados.value.every(s => selectedIds.value.includes(s.servicio_id));
});

const someSelected = computed(() => selectedIds.value.length > 0);

const botonGenerarTexto = computed(() => {
  if (selectedCount.value === 0) {
    return 'Crear Factura';
  }
  return `Crear Factura (${selectedCount.value})`;
});

function toggleServicio(id) {
  const i = selectedIds.value.indexOf(id);
  if (i >= 0) {
    selectedIds.value = selectedIds.value.filter(x => x !== id);
  } else {
    selectedIds.value = [...selectedIds.value, id];
  }
}

function toggleRow(s, ev) {
  if (!props.canCreateFactura) return;
  if (ev.target.closest('a, button, input, form')) return;
  toggleServicio(s.servicio_id);
}

function toggleSelectAll() {
  if (allSelected.value) {
    selectedIds.value = selectedIds.value.filter(id => !serviciosPaginados.value.some(s => s.servicio_id === id));
  } else {
    const toAdd = serviciosPaginados.value.map(s => s.servicio_id).filter(id => !selectedIds.value.includes(id));
    selectedIds.value = [...selectedIds.value, ...toAdd];
  }
}

function formatCedula(cedula) {
  if (!cedula) return '—';
  return typeof cedula === 'number' || (typeof cedula === 'string' && /^\d+$/.test(cedula))
    ? Number(cedula).toLocaleString('es-AR')
    : cedula;
}

function estadoLabel(estado) {
  const map = { A: 'Activo', S: 'Suspendido', C: 'Cancelado', P: 'Pendiente' };
  return map[estado ?? 'P'] ?? 'Pendiente';
}

function estadoClase(estado) {
  const map = {
    A: 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300',
    S: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-300',
    C: 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300',
    P: 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300',
  };
  return map[estado ?? 'P'] ?? 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300';
}

function estadoLedDotClase(estado) {
  const map = {
    A: 'bg-green-500 shadow-[0_0_8px_3px_rgba(34,197,94,0.7)]',
    S: 'bg-amber-500 shadow-[0_0_8px_3px_rgba(245,158,11,0.7)]',
    C: 'bg-gray-500 shadow-[0_0_8px_3px_rgba(107,114,128,0.6)]',
    P: 'bg-blue-500 shadow-[0_0_8px_3px_rgba(59,130,246,0.7)]',
  };
  return map[estado ?? 'P'] ?? 'bg-blue-500 shadow-[0_0_8px_3px_rgba(59,130,246,0.7)]';
}

function estadoPagoLabel(estadoPago) {
  const map = { pagado: 'Pagado', parcial: 'Parcial', exento: 'Exento', pendiente: 'Pendiente' };
  return map[estadoPago] ?? (estadoPago || '—');
}

function confirmDestroy(ev) {
  if (window.confirm('¿Eliminar este servicio? Si tiene IP asignada, quedará disponible en el pool.')) {
    ev.target.closest('form').submit();
  }
}

onMounted(() => {
  document.addEventListener('click', closeDropdowns);
});
onUnmounted(() => {
  document.removeEventListener('click', closeDropdowns);
});
</script>
