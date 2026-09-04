<template>
  <div>
    <div class="p-4 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-700/50">
      <div class="flex flex-col sm:flex-row gap-3">
        <div v-if="!hideSearchBar" class="flex-1 relative">
          <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
            <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
            </svg>
          </div>
          <input
            v-model="buscar"
            type="text"
            placeholder="Buscar por cédula, nombre, apellido, email o teléfono..."
            class="w-full pl-10 pr-10 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none transition-colors bg-white dark:bg-gray-700 dark:text-gray-100"
          >
          <button
            v-if="buscar"
            type="button"
            class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 transition-colors"
            title="Limpiar búsqueda"
            @click="buscar = ''"
          >
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
            </svg>
          </button>
        </div>
        <div class="sm:w-48">
          <select v-model="estado" class="w-full py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none transition-colors bg-white dark:bg-gray-700 dark:text-gray-100">
            <option value="todos">Todos los estados</option>
            <option value="activo">Activo</option>
            <option value="inactivo">Inactivo</option>
            <option value="suspendido">Suspendido</option>
          </select>
        </div>
        <div class="sm:w-56">
          <select v-model="sinServicio" class="w-full py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none transition-colors bg-white dark:bg-gray-700 dark:text-gray-100">
            <option value="">Todos (con o sin servicio)</option>
            <option value="1">Sin servicio asociado</option>
          </select>
        </div>
      </div>
    </div>

    <div class="overflow-x-auto">
      <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
        <thead class="bg-gray-50 dark:bg-gray-700/50">
          <tr>
            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">#</th>
            <th scope="col" class="px-4 py-3 w-[min(16rem,52vw)] max-w-[min(16rem,52vw)] sm:w-[min(22rem,34vw)] sm:max-w-[min(22rem,34vw)] text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Nombre / Documento</th>
            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Direccion</th>
            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Teléfono</th>
            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Estado</th>
            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Calificación</th>
            <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Acciones</th>
          </tr>
        </thead>
        <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
          <template v-if="clientesFiltrados.length === 0">
            <tr>
              <td colspan="7" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">
                No hay clientes para los filtros aplicados.
              </td>
            </tr>
          </template>
          <template v-else>
            <template v-for="(c, idx) in clientesFiltrados" :key="c.cliente_id">
      <tr
        class="hover:bg-gray-50 dark:hover:bg-gray-700/50"
        :class="{ 'cursor-pointer': c.servicios && c.servicios.length > 0 }"
        :id="c.servicios?.length ? 'accordion-trigger-' + c.cliente_id : undefined"
        :aria-expanded="c.servicios?.length && openedIds.includes(c.cliente_id)"
        :aria-controls="c.servicios?.length ? 'servicios-' + c.cliente_id : undefined"
        :role="c.servicios?.length ? 'button' : undefined"
        :tabindex="c.servicios?.length ? 0 : undefined"
        :title="c.servicios?.length ? 'Clic para ver servicios' : undefined"
        @click="c.servicios?.length && toggle(c.cliente_id)"
        @keydown.enter.prevent="c.servicios?.length && toggle(c.cliente_id)"
        @keydown.space.prevent="c.servicios?.length && toggle(c.cliente_id)"
      >
      <td class="px-4 py-3 text-sm text-gray-900 dark:text-gray-100">
        {{ firstItem + idx }}
      </td>
        <td class="px-4 py-3 text-sm text-gray-900 dark:text-gray-100 w-[min(16rem,52vw)] max-w-[min(16rem,52vw)] sm:w-[min(22rem,34vw)] sm:max-w-[min(22rem,34vw)]">
          <span class="text-gray-600 dark:text-gray-300 font-medium">{{ c.nombre }} {{ c.apellido }}</span><br>
          <span class="text-gray-400 dark:text-gray-200">{{ formatDocument(c.cedula) }}</span>
        </td>
        <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-300 max-w-[min(18rem,28vw)] w-[min(18rem,28vw)]">
          <div class="truncate" :title="c.direccion || ''">{{ c.direccion || '—' }}</div>
          <a v-if="getMapsUrl(c)"
             :href="getMapsUrl(c)"
             target="_blank"
             rel="noopener noreferrer"
             @click.stop
             class="inline-flex items-center gap-1 text-xs text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300 mt-0.5 whitespace-nowrap">
            <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
            </svg>
            Ver mapa
          </a>
        </td>
        <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-300">{{ c.telefono || '—' }}</td>
        <td class="px-4 py-3">
          <span
            class="inline-flex px-2 py-1 text-xs font-medium rounded-full"
            :class="estadoClase(c.estado)"
          >
            {{ c.estado ? c.estado.charAt(0).toUpperCase() + c.estado.slice(1) : '—' }}
          </span>
        </td>
        <td class="px-4 py-3">
          <span
            v-if="c.calificacion_pago"
            class="inline-flex items-center gap-0.5"
            :class="calificacionPagoClase(c.calificacion_pago)"
            :title="calificacionPagoLabel(c.calificacion_pago)"
          >
            <template v-for="i in 3" :key="i">
              <svg
                v-if="i <= calificacionPagoEstrellas(c.calificacion_pago)"
                class="w-4 h-4"
                fill="currentColor"
                viewBox="0 0 20 20"
              >
                <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
              </svg>
              <svg
                v-else
                class="w-4 h-4 opacity-30"
                fill="currentColor"
                viewBox="0 0 20 20"
              >
                <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
              </svg>
            </template>
          </span>
          <span v-else class="text-gray-400 dark:text-gray-500 text-xs">—</span>
        </td>
        <td class="px-4 py-3">
          <div class="flex items-center justify-end gap-1">
            <div
              class="relative"
              :data-menu-acciones="c.cliente_id"
              @click.stop
            >
              <button
                type="button"
                class="inline-flex items-center justify-center w-9 h-9 rounded-lg text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors"
                :class="{ 'bg-gray-100 dark:bg-gray-700': menuAccionesAbiertoId === c.cliente_id }"
                title="Acciones"
                aria-label="Acciones del cliente"
                aria-haspopup="true"
                :aria-expanded="menuAccionesAbiertoId === c.cliente_id"
                @click.stop="toggleMenuAcciones(c.cliente_id, $event)"
              >
                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                  <circle cx="12" cy="5" r="1.75" />
                  <circle cx="12" cy="12" r="1.75" />
                  <circle cx="12" cy="19" r="1.75" />
                </svg>
              </button>
            </div>
            <button
              v-if="c.servicios && c.servicios.length > 0"
              type="button"
              class="inline-flex items-center justify-center w-8 h-8 rounded text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700 transition-transform"
              :class="{ 'rotate-180': openedIds.includes(c.cliente_id) }"
              title="Ver servicios"
              aria-label="Ver servicios"
              @click.stop="toggle(c.cliente_id)"
            >
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
              </svg>
            </button>
            <span v-else class="inline-flex items-center justify-center w-8 h-8 rounded text-gray-500 dark:text-gray-400">—</span>
          </div>
        </td>
      </tr>
      <tr
        v-if="c.servicios && c.servicios.length > 0 && openedIds.includes(c.cliente_id)"
        :id="'servicios-' + c.cliente_id"
        class="servicios-accordion-panel bg-gray-50/80 dark:bg-gray-700/50"
        role="region"
        :aria-labelledby="'accordion-trigger-' + c.cliente_id"
      >
        <td colspan="7" class="px-4 py-3 border-l-4 border-purple-200 dark:border-purple-800">
          <div class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-2">
            Servicios ({{ c.servicios.length }})
          </div>
          <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-800">
            <table class="min-w-full text-sm divide-y divide-gray-200 dark:divide-gray-700">
              <thead class="bg-gray-100 dark:bg-gray-700/50">
                <tr>
                  <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400">#</th>
                  <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Alias</th>
                  <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Plan</th>
                  <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Fecha Instalación</th>
                  <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400">IP</th>
                  <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Estado</th>
                  <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400">Acción</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="s in c.servicios" :key="s.cliente_id + '-' + s.servicio_id" class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                  <td class="px-3 py-2 text-gray-900 dark:text-gray-100">{{ s.servicio_id }}</td>
                  <td class="px-3 py-2 text-gray-700 dark:text-gray-300">{{ s.alias ? s.alias : '—' }}</td>
                  <td class="px-3 py-2 text-gray-700 dark:text-gray-300">{{ s.plan && s.plan.nombre ? s.plan.nombre : '—' }}</td>
                
                  <td class="px-3 py-2 text-gray-600 dark:text-gray-400">{{ s.fecha_instalacion ? new Date(s.fecha_instalacion).toLocaleDateString() : '—' }}</td>
                  <td class="px-3 py-2 text-gray-700 dark:text-gray-300">{{ s.ip ? s.ip : '—' }}</td>
                  <td class="px-3 py-2">{{ estadoServicioLabel(s.estado) }}</td>
                  <td class="px-3 py-2 text-right">
                    <a :href="urlEditServicio(s.servicio_id)" class="text-purple-600 dark:text-purple-400 hover:text-purple-800 dark:hover:text-purple-300 font-medium">Editar</a>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
          <a :href="urlCreateServicio(c.cliente_id)" class="inline-block mt-2 text-xs text-purple-600 dark:text-purple-400 hover:text-purple-800 dark:hover:text-purple-300 font-medium">+ Agregar servicio</a>
        </td>
      </tr>
            </template>
          </template>
        </tbody>
      </table>
    </div>

    <button
      v-show="mostrarSubir"
      type="button"
      class="fixed bottom-6 right-6 z-30 inline-flex h-12 w-12 items-center justify-center rounded-full bg-purple-600 text-white shadow-lg ring-1 ring-black/5 transition hover:bg-purple-700 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:ring-offset-2 dark:focus:ring-offset-gray-900"
      title="Subir arriba"
      aria-label="Subir arriba"
      @click="subirArriba"
    >
      <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7" />
      </svg>
    </button>
  </div>

  <!-- Modal buscar temp -->
  <Teleport to="body">
    <div
      v-if="modalTempVisible"
      class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50"
      @click.self="cerrarModalTemp"
    >
      <div class="bg-white dark:bg-gray-800 rounded-xl shadow-xl max-w-lg w-full max-h-[90vh] overflow-hidden flex flex-col">
        <div class="p-4 border-b border-gray-200 dark:border-gray-700 flex justify-between items-center">
          <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Datos encontrados en temp</h3>
          <button type="button" class="p-2 text-gray-500 hover:text-gray-700 dark:hover:text-gray-300" @click="cerrarModalTemp" aria-label="Cerrar">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
          </button>
        </div>
        <div class="p-4 overflow-y-auto flex-1">
          <p v-if="modalTempLoading" class="text-sm text-gray-600 dark:text-gray-400">Buscando...</p>
          <div v-else-if="modalTempResultados.length === 0" class="space-y-3">
            <p class="text-sm text-gray-600 dark:text-gray-400">No se encontraron resultados.</p>
            <div class="flex gap-2">
              <input
                v-model="modalTempBuscar"
                type="text"
                class="flex-1 px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 text-sm focus:ring-2 focus:ring-purple-500 focus:border-purple-500"
                placeholder="Modificá el nombre y buscá de nuevo"
              />
              <button
                type="button"
                class="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 text-sm font-medium disabled:opacity-50"
                :disabled="!modalTempBuscar || modalTempBuscar.trim().length < 2"
                @click="buscarTempDeNuevo"
              >
                Buscar
              </button>
            </div>
          </div>
          <div v-else class="space-y-3">
            <div
              v-for="(r, idx) in modalTempResultados"
              :key="idx"
              class="p-3 rounded-lg border border-gray-200 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700/50 cursor-pointer"
              :class="{ 'ring-2 ring-purple-500': modalTempSeleccionado === idx }"
              @click="modalTempSeleccionado = idx"
            >
              <div class="text-sm text-gray-900 dark:text-gray-100 font-medium">{{ r.nombre }}</div>
              <div class="text-xs text-gray-600 dark:text-gray-400 mt-1">
                Cédula: {{ r.cedula || '—' }} | Cel: {{ r.celular || '—' }}<br>
                Dirección: {{ r.direccion || '—' }}<br>
                <span v-if="r.latitud && r.longitud">Coords: {{ r.latitud }}, {{ r.longitud }}</span>
              </div>
            </div>
          </div>
        </div>
        <div class="p-4 border-t border-gray-200 dark:border-gray-700 flex justify-end gap-2">
          <button type="button" class="px-4 py-2 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600 rounded-lg" @click="cerrarModalTemp">Cancelar</button>
          <button
            v-if="modalTempResultados.length > 0"
            type="button"
            class="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 disabled:opacity-50 disabled:cursor-not-allowed"
            :disabled="modalTempSeleccionado === null || modalTempActualizando"
            @click="aplicarTemp"
          >
            {{ modalTempActualizando ? 'Actualizando...' : 'Actualizar cliente' }}
          </button>
        </div>
      </div>
    </div>
  </Teleport>

  <!-- Modal consulta RUC -->
  <Teleport to="body">
    <div
      v-if="modalRucVisible"
      class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50"
      @click.self="cerrarModalRuc"
    >
      <div class="bg-white dark:bg-gray-800 rounded-xl shadow-xl max-w-lg w-full max-h-[90vh] overflow-hidden flex flex-col">
        <div class="p-4 border-b border-gray-200 dark:border-gray-700 flex justify-between items-center">
          <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Consulta RUC</h3>
          <button type="button" class="p-2 text-gray-500 hover:text-gray-700 dark:hover:text-gray-300" @click="cerrarModalRuc" aria-label="Cerrar">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
          </button>
        </div>
        <div class="p-4 overflow-y-auto flex-1 space-y-3">
          <p v-if="modalRucCliente" class="text-sm text-gray-600 dark:text-gray-400">
            Cliente: <span class="font-medium text-gray-900 dark:text-gray-100">{{ modalRucCliente.nombre }} {{ modalRucCliente.apellido }}</span><br>
            Documento actual: <span class="font-medium text-gray-900 dark:text-gray-100">{{ modalRucCliente.cedula || '—' }}</span><br>
            Consulta: <span class="font-medium text-gray-900 dark:text-gray-100">{{ modalRucTermino || '—' }}</span>
          </p>
          <p v-if="modalRucLoading" class="text-sm text-gray-600 dark:text-gray-400">Consultando...</p>
          <template v-else>
            <p v-if="modalRucError" class="text-sm text-red-600 dark:text-red-400">{{ modalRucError }}</p>
            <p v-else-if="modalRucMensaje" class="text-sm text-gray-700 dark:text-gray-300">{{ modalRucMensaje }}</p>
            <p v-else-if="modalRucResultados.length === 0" class="text-sm text-gray-600 dark:text-gray-400">
              No se encontró RUC registrado para este documento.
            </p>
            <div v-if="!modalRucError && modalRucResultados.length > 0" class="space-y-3">
              <label
                v-for="(r, idx) in modalRucResultados"
                :key="idx"
                class="block p-3 rounded-lg border transition-colors"
                :class="r.aplicable
                  ? (modalRucSeleccionado === idx
                    ? 'border-purple-500 bg-purple-50 dark:bg-purple-900/20 cursor-pointer'
                    : 'border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-700/40 cursor-pointer hover:border-purple-300')
                  : 'border-gray-200 dark:border-gray-700 bg-gray-100/80 dark:bg-gray-900/40 opacity-80'"
              >
                <div class="flex items-start gap-2">
                  <input
                    v-if="r.aplicable"
                    type="radio"
                    class="mt-1 text-purple-600"
                    :value="idx"
                    v-model="modalRucSeleccionado"
                    name="ruc-resultado"
                  >
                  <div class="min-w-0 flex-1">
                    <div class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ r.ruc || '—' }}</div>
                    <div class="text-sm text-gray-700 dark:text-gray-300 mt-1">{{ r.razon_social || '—' }}</div>
                    <div class="mt-2 flex flex-wrap gap-1.5">
                      <span
                        v-if="r.estado"
                        class="inline-flex px-2 py-0.5 text-xs font-medium rounded-full"
                        :class="estadoRucPillClass(r)"
                      >
                        {{ r.estado }}
                      </span>
                      <span
                        v-if="!r.coincide_documento"
                        class="inline-flex px-2 py-0.5 text-xs font-medium rounded-full bg-rose-100 text-rose-800 dark:bg-rose-900/30 dark:text-rose-300"
                      >
                        No coincide
                      </span>
                      <span
                        v-if="r.cancelado"
                        class="inline-flex px-2 py-0.5 text-xs font-medium rounded-full bg-rose-100 text-rose-800 dark:bg-rose-900/30 dark:text-rose-300"
                      >
                        Cancelado
                      </span>
                    </div>
                    <p v-if="r.motivo_bloqueo" class="mt-2 text-xs text-amber-700 dark:text-amber-300">{{ r.motivo_bloqueo }}</p>
                  </div>
                </div>
              </label>

              <div v-if="resultadoRucSeleccionado" class="rounded-lg border border-purple-200 dark:border-purple-800 bg-purple-50/60 dark:bg-purple-950/20 p-3 space-y-2">
                <p class="text-xs font-semibold uppercase tracking-wide text-purple-700 dark:text-purple-300">Confirmar cambios</p>
                <label class="flex items-start gap-2 text-sm text-gray-800 dark:text-gray-200">
                  <input type="checkbox" v-model="modalRucActualizarDoc" class="mt-0.5 text-purple-600">
                  <span>
                    Actualizar documento:
                    <span class="font-mono">{{ modalRucCliente?.cedula || '—' }}</span>
                    →
                    <span class="font-mono font-semibold">{{ resultadoRucSeleccionado.ruc }}</span>
                  </span>
                </label>
                <label class="flex items-start gap-2 text-sm text-gray-800 dark:text-gray-200">
                  <input type="checkbox" v-model="modalRucActualizarNombre" class="mt-0.5 text-purple-600">
                  <span>
                    Actualizar nombre:
                    <span>{{ modalRucCliente?.nombre || '—' }} {{ modalRucCliente?.apellido || '' }}</span>
                    →
                    <span class="font-semibold">{{ resultadoRucSeleccionado.nombre_preview || '—' }} {{ resultadoRucSeleccionado.apellido_preview || '' }}</span>
                  </span>
                </label>
              </div>
            </div>
          </template>
        </div>
        <div class="p-4 border-t border-gray-200 dark:border-gray-700 flex flex-wrap justify-end gap-2">
          <button type="button" class="px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700" @click="cerrarModalRuc">
            Cerrar
          </button>
          <button
            v-if="!modalRucLoading && !modalRucError && resultadoRucSeleccionado"
            type="button"
            class="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 disabled:opacity-50"
            :disabled="modalRucAplicando || (!modalRucActualizarDoc && !modalRucActualizarNombre)"
            @click="aplicarConsultaRucSeleccionada"
          >
            {{ modalRucAplicando ? 'Aplicando...' : 'Confirmar y aplicar' }}
          </button>
        </div>
      </div>
    </div>
  </Teleport>

  <Teleport to="body">
    <div
      v-if="clienteMenuAcciones"
      data-menu-acciones-panel
      class="fixed z-[80] min-w-[12.5rem] py-1 rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-800 shadow-lg ring-1 ring-black/5 dark:ring-white/10"
      :style="menuAccionesStyle"
      role="menu"
      @click.stop
    >
      <a
        v-if="urlAccionesCliente"
        :href="urlAccionesCliente(clienteMenuAcciones.cliente_id)"
        class="flex items-center gap-2.5 px-4 py-2.5 text-sm font-medium text-gray-700 dark:text-gray-100 hover:bg-gray-50 dark:hover:bg-gray-700/80"
        role="menuitem"
        @click="cerrarMenuAcciones"
      >
        <svg class="w-4 h-4 shrink-0 text-indigo-600 dark:text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" />
        </svg>
        Acciones del cliente
      </a>
      <a
        v-if="urlDetalleCliente"
        :href="urlDetalleCliente(clienteMenuAcciones.cliente_id)"
        class="flex items-center gap-2.5 px-4 py-2.5 text-sm font-medium text-gray-700 dark:text-gray-100 hover:bg-gray-50 dark:hover:bg-gray-700/80"
        role="menuitem"
        @click="cerrarMenuAcciones"
      >
        <svg class="w-4 h-4 shrink-0 text-slate-600 dark:text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
        </svg>
        Ver detalle
      </a>
      <button
        v-if="puedeEditar"
        type="button"
        class="flex w-full items-center gap-2.5 px-4 py-2.5 text-sm font-medium text-gray-700 dark:text-gray-100 hover:bg-gray-50 dark:hover:bg-gray-700/80"
        role="menuitem"
        @click="accionBuscarTemp(clienteMenuAcciones)"
      >
        <svg class="w-4 h-4 shrink-0 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
        </svg>
        Buscar en temp
      </button>
      <button
        v-if="puedeConsultarRuc(clienteMenuAcciones)"
        type="button"
        class="flex w-full items-center gap-2.5 px-4 py-2.5 text-sm font-medium text-gray-700 dark:text-gray-100 hover:bg-gray-50 dark:hover:bg-gray-700/80 disabled:opacity-50"
        role="menuitem"
        :disabled="consultaRucLoadingId === clienteMenuAcciones.cliente_id"
        @click="accionConsultarRuc(clienteMenuAcciones)"
      >
        <svg
          v-if="consultaRucLoadingId !== clienteMenuAcciones.cliente_id"
          class="w-4 h-4 shrink-0 text-amber-600 dark:text-amber-400"
          fill="none"
          stroke="currentColor"
          viewBox="0 0 24 24"
          aria-hidden="true"
        >
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
        </svg>
        <svg v-else class="w-4 h-4 shrink-0 animate-spin text-amber-600 dark:text-amber-400" fill="none" viewBox="0 0 24 24" aria-hidden="true">
          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
          <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
        </svg>
        {{ consultaRucLoadingId === clienteMenuAcciones.cliente_id ? 'Consultando RUC…' : 'Consultar RUC' }}
      </button>
      <a
        v-if="urlCreateServicioBase && (!clienteMenuAcciones.servicios || clienteMenuAcciones.servicios.length === 0)"
        :href="urlCreateServicio(clienteMenuAcciones.cliente_id)"
        class="flex items-center gap-2.5 px-4 py-2.5 text-sm font-medium text-gray-700 dark:text-gray-100 hover:bg-gray-50 dark:hover:bg-gray-700/80"
        role="menuitem"
        @click="cerrarMenuAcciones"
      >
        <svg class="w-4 h-4 shrink-0 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
        </svg>
        Crear servicio
      </a>
      <a
        :href="urlEditCliente(clienteMenuAcciones.cliente_id)"
        class="flex items-center gap-2.5 px-4 py-2.5 text-sm font-medium text-gray-700 dark:text-gray-100 hover:bg-gray-50 dark:hover:bg-gray-700/80"
        role="menuitem"
        @click="cerrarMenuAcciones"
      >
        <svg class="w-4 h-4 shrink-0 text-purple-600 dark:text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
        </svg>
        Editar
      </a>
      <button
        type="button"
        class="flex w-full items-center gap-2.5 px-4 py-2.5 text-sm font-medium text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 border-t border-gray-200 dark:border-gray-600 mt-1 pt-2.5"
        role="menuitem"
        @click="eliminarCliente(clienteMenuAcciones)"
      >
        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
        </svg>
        Eliminar
      </button>
    </div>
  </Teleport>
</template>

<script setup>
import { ref, computed, nextTick, onMounted, onUnmounted } from 'vue';

const props = defineProps({
  clientes: { type: Array, default: () => [] },
  firstItem: { type: Number, default: 1 },
  csrfToken: { type: String, default: '' },
  urlEditClienteBase: { type: String, default: '' },
  urlDestroyClienteBase: { type: String, default: '' },
  urlCreateCliente: { type: String, default: '' },
  urlEditServicioBase: { type: String, default: '' },
  urlCreateServicioBase: { type: String, default: '' },
  urlBuscarTemp: { type: String, default: '' },
  urlConsultarRucBase: { type: String, default: '' },
  urlAplicarConsultaRucBase: { type: String, default: '' },
  urlActualizarDesdeTempBase: { type: String, default: '' },
  urlDetalleClienteBase: { type: String, default: '' },
  urlAccionesClienteBase: { type: String, default: '' },
  puedeEditar: { type: Boolean, default: false },
  initialBuscar: { type: String, default: '' },
  initialEstado: { type: String, default: 'todos' },
  initialSinServicio: { type: String, default: '' },
  sharedBuscar: { type: Object, default: null },
  hideSearchBar: { type: Boolean, default: false },
});

const listaClientes = ref((props.clientes || []).map((c) => ({ ...c })));
const openedIds = ref([]);
const localBuscar = ref(props.initialBuscar || '');
const buscar = computed({
  get() {
    return props.sharedBuscar ? (props.sharedBuscar.text ?? '') : localBuscar.value;
  },
  set(value) {
    if (props.sharedBuscar) {
      props.sharedBuscar.text = value;
      return;
    }
    localBuscar.value = value;
  },
});
const estado = ref(props.initialEstado || 'todos');
const sinServicio = ref(props.initialSinServicio || '');
const modalTempVisible = ref(false);
const modalTempLoading = ref(false);
const modalTempResultados = ref([]);
const modalTempSeleccionado = ref(null);
const modalTempActualizando = ref(false);
const modalTempCliente = ref(null);
const modalTempBuscar = ref('');
const modalRucVisible = ref(false);
const modalRucLoading = ref(false);
const modalRucAplicando = ref(false);
const modalRucResultados = ref([]);
const modalRucError = ref('');
const modalRucCliente = ref(null);
const modalRucTermino = ref('');
const modalRucMensaje = ref('');
const modalRucSeleccionado = ref(null);
const modalRucActualizarDoc = ref(true);
const modalRucActualizarNombre = ref(true);

const resultadoRucSeleccionado = computed(() => {
  if (modalRucSeleccionado.value === null || modalRucSeleccionado.value === undefined) return null;
  const row = modalRucResultados.value[modalRucSeleccionado.value];
  return row && row.aplicable ? row : null;
});

const mostrarSubir = ref(false);
const UMBRAL_SCROLL_SUBIR = 320;

function actualizarBotonSubir() {
  mostrarSubir.value = window.scrollY > UMBRAL_SCROLL_SUBIR;
}

function subirArriba() {
  window.scrollTo({ top: 0, behavior: 'smooth' });
}
const consultaRucLoadingId = ref(null);
const menuAccionesAbiertoId = ref(null);
const menuAccionesPos = ref({ top: 0, left: 0, openUp: false });

const clienteMenuAcciones = computed(() => {
  if (menuAccionesAbiertoId.value == null) return null;
  return listaClientes.value.find((c) => c.cliente_id === menuAccionesAbiertoId.value) || null;
});

const menuAccionesStyle = computed(() => ({
  top: `${menuAccionesPos.value.top}px`,
  left: `${menuAccionesPos.value.left}px`,
  transform: menuAccionesPos.value.openUp ? 'translateY(-100%)' : 'none',
}));

function posicionarMenuAcciones(anchorEl) {
  if (!anchorEl) return;
  const rect = anchorEl.getBoundingClientRect();
  const menuWidth = 200;
  const estimatedHeight = 260;
  const gap = 4;
  const spaceBelow = window.innerHeight - rect.bottom;
  const openUp = spaceBelow < estimatedHeight && rect.top > spaceBelow;
  let left = rect.right - menuWidth;
  left = Math.max(8, Math.min(left, window.innerWidth - menuWidth - 8));
  menuAccionesPos.value = {
    top: openUp ? rect.top - gap : rect.bottom + gap,
    left,
    openUp,
  };
}

function toggleMenuAcciones(clienteId, event) {
  if (menuAccionesAbiertoId.value === clienteId) {
    cerrarMenuAcciones();
    return;
  }
  menuAccionesAbiertoId.value = clienteId;
  const btn = event?.currentTarget || null;
  nextTick(() => posicionarMenuAcciones(btn));
}

function cerrarMenuAcciones() {
  menuAccionesAbiertoId.value = null;
}

function handleClickOutsideMenuAcciones(e) {
  if (menuAccionesAbiertoId.value == null) return;
  const trigger = document.querySelector(`[data-menu-acciones="${menuAccionesAbiertoId.value}"]`);
  const panel = document.querySelector('[data-menu-acciones-panel]');
  if (trigger?.contains(e.target) || panel?.contains(e.target)) return;
  cerrarMenuAcciones();
}

function handleScrollOrResizeMenuAcciones() {
  actualizarBotonSubir();
  if (menuAccionesAbiertoId.value != null) {
    cerrarMenuAcciones();
  }
}

function accionBuscarTemp(cliente) {
  cerrarMenuAcciones();
  buscarTemp(cliente);
}

function accionConsultarRuc(cliente) {
  cerrarMenuAcciones();
  consultarRucCliente(cliente);
}

function eliminarCliente(cliente) {
  cerrarMenuAcciones();
  if (!window.confirm('¿Eliminar este cliente?')) return;

  const form = document.createElement('form');
  form.method = 'POST';
  form.action = urlDestroyCliente(cliente.cliente_id);

  const token = document.createElement('input');
  token.type = 'hidden';
  token.name = '_token';
  token.value = props.csrfToken;
  form.appendChild(token);

  const method = document.createElement('input');
  method.type = 'hidden';
  method.name = '_method';
  method.value = 'DELETE';
  form.appendChild(method);

  document.body.appendChild(form);
  form.submit();
}

onMounted(() => {
  document.addEventListener('click', handleClickOutsideMenuAcciones);
  window.addEventListener('scroll', handleScrollOrResizeMenuAcciones, { passive: true, capture: true });
  window.addEventListener('resize', handleScrollOrResizeMenuAcciones, { passive: true });
  actualizarBotonSubir();
});

onUnmounted(() => {
  document.removeEventListener('click', handleClickOutsideMenuAcciones);
  window.removeEventListener('scroll', handleScrollOrResizeMenuAcciones, true);
  window.removeEventListener('resize', handleScrollOrResizeMenuAcciones);
});

function toggle(clienteId) {
  const i = openedIds.value.indexOf(clienteId);
  if (i >= 0) openedIds.value.splice(i, 1);
  else openedIds.value.push(clienteId);
}

function estadoClase(estado) {
  const map = {
    activo: 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300',
    inactivo: 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300',
    suspendido: 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300',
  };
  return map[estado] || 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300';
}

function estadoServicioLabel(estado) {
  const map = { A: 'Activo', S: 'Suspendido', C: 'Cancelado', P: 'Pendiente' };
  return map[estado] || 'Pendiente';
}

function calificacionPagoLabel(calif) {
  const map = { malo: 'Malo', bueno: 'Bueno', excelente: 'Excelente' };
  return map[calif] || calif;
}

function calificacionPagoEstrellas(calif) {
  const map = { malo: 1, bueno: 2, excelente: 3 };
  return map[calif] ?? 0;
}

function calificacionPagoClase(calif) {
  const map = {
    malo: 'text-red-500 dark:text-red-400',
    bueno: 'text-blue-500 dark:text-blue-400',
    excelente: 'text-amber-400 dark:text-amber-300',
  };
  return map[calif] || 'text-gray-500 dark:text-gray-400';
}

function urlEditCliente(id) {
  return props.urlEditClienteBase.replace('__id__', id);
}

function urlDestroyCliente(id) {
  return props.urlDestroyClienteBase.replace('__id__', id);
}

function urlEditServicio(servicioId) {
  return props.urlEditServicioBase.replace('__servicio_id__', servicioId);
}

function urlCreateServicio(clienteId) {
  return props.urlCreateServicioBase.replace('__cliente_id__', clienteId);
}

const urlCreate = computed(() => props.urlCreateCliente);

const urlDetalleCliente = computed(() => {
  const b = props.urlDetalleClienteBase;
  return b && String(b).includes('__id__') ? (id) => b.replace('__id__', id) : null;
});

const urlAccionesCliente = computed(() => {
  const b = props.urlAccionesClienteBase;
  return b && String(b).includes('__id__') ? (id) => b.replace('__id__', id) : null;
});

function normalizarTexto(valor) {
  return (valor || '')
    .toString()
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .replace(/[_-]+/g, ' ')
    .replace(/[^\w\s]/g, ' ')
    .replace(/\s+/g, ' ')
    .trim()
    .toLowerCase();
}

function coincideBusqueda(cliente, termino) {
  if (!termino) return true;
  const tokens = normalizarTexto(termino).split(' ').filter(Boolean);
  if (tokens.length === 0) return true;

  const textoCliente = normalizarTexto([
    cliente.nombre,
    cliente.apellido,
    cliente.cedula,
    cliente.email,
    cliente.telefono,
    cliente.celular,
    cliente.direccion,
    ...(Array.isArray(cliente.servicios) ? cliente.servicios.flatMap((s) => [s.alias, s.plan?.nombre, s.ip]) : []),
  ].filter(Boolean).join(' '));

  return tokens.every((token) => textoCliente.includes(token));
}

const clientesFiltrados = computed(() => {
  return listaClientes.value.filter((cliente) => {
    const cumpleBusqueda = coincideBusqueda(cliente, buscar.value);
    const cumpleEstado = estado.value === 'todos' || !estado.value ? true : cliente.estado === estado.value;
    const cantidadServicios = Array.isArray(cliente.servicios) ? cliente.servicios.length : 0;
    const cumpleSinServicio = sinServicio.value === '1' ? cantidadServicios === 0 : true;
    return cumpleBusqueda && cumpleEstado && cumpleSinServicio;
  });
});

function urlConsultarRuc(clienteId) {
  return props.urlConsultarRucBase.replace('__id__', clienteId);
}

function urlAplicarConsultaRuc(clienteId) {
  return (props.urlAplicarConsultaRucBase || '').replace('__id__', clienteId);
}

function puedeConsultarRuc(cliente) {
  if (!props.puedeEditar || !props.urlConsultarRucBase) return false;
  if (cliente.ruc_consultado) return false;
  const termino = normalizarTerminoRuc(cliente.cedula);
  return termino.length >= 5;
}

function estadoRucPillClass(r) {
  const estado = (r?.estado || '').toString().toUpperCase();
  if (r?.cancelado || estado.includes('CANCEL') || estado.includes('BAJA')) {
    return 'bg-rose-100 text-rose-800 dark:bg-rose-900/30 dark:text-rose-300';
  }
  if (estado === 'ACTIVO') {
    return 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300';
  }
  return 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300';
}


function formatDocument(document) {
  if (!document) return '—';
  return document.toString().replace(/(\d{3})(\d{3})(\d{4})/, '$1-$2-$3');
}

/** URL del mapa solo desde url_ubicacion (no usa dirección, evita URLs largas que ensanchan la tabla). */
function getMapsUrl(cliente) {
  if (!cliente) return null;
  const raw = (cliente.url_ubicacion || '').toString().trim();
  if (!raw) return null;
  if (/^https?:\/\//i.test(raw)) return raw;
  if (/^\/\//.test(raw)) return 'https:' + raw;
  const coordMatch = raw.match(/^(-?\d+\.?\d*)\s*,\s*(-?\d+\.?\d*)$/);
  if (coordMatch) {
    return 'https://www.google.com/maps?q=' + coordMatch[1] + ',' + coordMatch[2];
  }
  return 'https://www.google.com/maps/search/?api=1&query=' + encodeURIComponent(raw);
}

function normalizarTerminoRuc(documento) {
  return (documento || '').toString().replace(/\D+/g, '');
}

async function consultarRucCliente(cliente) {
  if (!puedeConsultarRuc(cliente)) return;
  const termino = normalizarTerminoRuc(cliente.cedula);

  modalRucCliente.value = cliente;
  modalRucTermino.value = termino;
  modalRucVisible.value = true;
  modalRucLoading.value = true;
  modalRucAplicando.value = false;
  modalRucResultados.value = [];
  modalRucError.value = '';
  modalRucMensaje.value = '';
  modalRucSeleccionado.value = null;
  modalRucActualizarDoc.value = true;
  modalRucActualizarNombre.value = true;
  consultaRucLoadingId.value = cliente.cliente_id;

  try {
    const r = await fetch(urlConsultarRuc(cliente.cliente_id), {
      method: 'POST',
      headers: {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-TOKEN': props.csrfToken,
      },
      body: JSON.stringify({}),
    });
    const data = await r.json();
    if (!r.ok) {
      modalRucError.value = data.message || 'No se pudo consultar el RUC.';
      return;
    }
    modalRucResultados.value = data.resultados || [];
    modalRucMensaje.value = data.message || '';

    const primeroAplicable = modalRucResultados.value.findIndex((row) => row.aplicable);
    modalRucSeleccionado.value = primeroAplicable >= 0 ? primeroAplicable : null;
  } catch (e) {
    modalRucError.value = 'Error de conexión al consultar RUC.';
  } finally {
    modalRucLoading.value = false;
    consultaRucLoadingId.value = null;
  }
}

async function aplicarConsultaRucSeleccionada() {
  const cliente = modalRucCliente.value;
  const seleccionado = resultadoRucSeleccionado.value;
  if (!cliente || !seleccionado || !props.urlAplicarConsultaRucBase) return;
  if (!modalRucActualizarDoc.value && !modalRucActualizarNombre.value) return;

  const cambios = [];
  if (modalRucActualizarDoc.value) {
    cambios.push(`documento ${cliente.cedula || '—'} → ${seleccionado.ruc}`);
  }
  if (modalRucActualizarNombre.value) {
    const nombreNuevo = `${seleccionado.nombre_preview || ''} ${seleccionado.apellido_preview || ''}`.trim();
    cambios.push(`nombre → ${nombreNuevo || '—'}`);
  }
  if (!window.confirm(`¿Confirmás actualizar?\n\n• ${cambios.join('\n• ')}`)) {
    return;
  }

  modalRucAplicando.value = true;
  modalRucError.value = '';
  try {
    const r = await fetch(urlAplicarConsultaRuc(cliente.cliente_id), {
      method: 'POST',
      headers: {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-TOKEN': props.csrfToken,
      },
      body: JSON.stringify({
        ruc: seleccionado.ruc,
        razon_social: seleccionado.razon_social || '',
        estado: seleccionado.estado || '',
        actualizar_ruc: !!modalRucActualizarDoc.value,
        actualizar_nombre: !!modalRucActualizarNombre.value,
        marcar_consultado: true,
      }),
    });
    const data = await r.json();
    if (!r.ok) {
      modalRucError.value = data.message || 'No se pudo aplicar la consulta RUC.';
      return;
    }

    modalRucMensaje.value = data.message || 'Cambios aplicados.';
    const actualizado = data.cliente || {};
    const idx = listaClientes.value.findIndex((c) => c.cliente_id === cliente.cliente_id);
    if (idx >= 0) {
      listaClientes.value[idx] = {
        ...listaClientes.value[idx],
        cedula: actualizado.cedula ?? listaClientes.value[idx].cedula,
        nombre: actualizado.nombre ?? listaClientes.value[idx].nombre,
        apellido: actualizado.apellido ?? listaClientes.value[idx].apellido,
        ruc_consultado: actualizado.ruc_consultado ?? true,
      };
      modalRucCliente.value = listaClientes.value[idx];
    }
    modalRucResultados.value = [];
    modalRucSeleccionado.value = null;
  } catch (e) {
    modalRucError.value = 'Error de conexión al aplicar la consulta RUC.';
  } finally {
    modalRucAplicando.value = false;
  }
}

function cerrarModalRuc() {
  modalRucVisible.value = false;
  modalRucCliente.value = null;
  modalRucTermino.value = '';
  modalRucResultados.value = [];
  modalRucError.value = '';
  modalRucMensaje.value = '';
  modalRucSeleccionado.value = null;
  modalRucActualizarDoc.value = true;
  modalRucActualizarNombre.value = true;
  modalRucAplicando.value = false;
}

async function ejecutarBuscarTemp(nombre) {
  if (!props.urlBuscarTemp || !nombre || nombre.trim().length < 2) return;
  modalTempLoading.value = true;
  modalTempResultados.value = [];
  modalTempSeleccionado.value = null;
  try {
    const r = await fetch(props.urlBuscarTemp + '?nombre=' + encodeURIComponent(nombre.trim()), {
      headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
    });
    const data = await r.json();
    modalTempResultados.value = data.encontrados || [];
    if (modalTempResultados.value.length > 0) {
      modalTempSeleccionado.value = 0;
    }
  } catch (e) {
    modalTempResultados.value = [];
  } finally {
    modalTempLoading.value = false;
  }
}

async function buscarTemp(cliente) {
  if (!props.urlBuscarTemp) return;
  const nombre = [cliente.nombre, cliente.apellido].filter(Boolean).join(' ').trim();
  if (!nombre) {
    alert('El cliente no tiene nombre para buscar.');
    return;
  }
  modalTempCliente.value = cliente;
  modalTempBuscar.value = nombre;
  modalTempVisible.value = true;
  await ejecutarBuscarTemp(nombre);
}

function buscarTempDeNuevo() {
  if (modalTempCliente.value) {
    ejecutarBuscarTemp(modalTempBuscar.value);
  }
}

function cerrarModalTemp() {
  modalTempVisible.value = false;
  modalTempCliente.value = null;
}

async function aplicarTemp() {
  const cliente = modalTempCliente.value;
  const idx = modalTempSeleccionado.value;
  if (!cliente || idx === null || !props.urlActualizarDesdeTempBase) return;
  const r = modalTempResultados.value[idx];
  if (!r) return;
  modalTempActualizando.value = true;
  try {
    const url = props.urlActualizarDesdeTempBase.replace('__id__', cliente.cliente_id);
    const res = await fetch(url, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        'X-CSRF-TOKEN': props.csrfToken,
        'X-Requested-With': 'XMLHttpRequest',
      },
      body: JSON.stringify({
        cedula: r.cedula || '',
        celular: r.celular || '',
        direccion: r.direccion || '',
        latitud: r.latitud ?? null,
        longitud: r.longitud ?? null,
      }),
    });
    const data = await res.json();
    if (data.success) {
      if (data.cliente) {
        Object.assign(cliente, data.cliente);
      }
      cerrarModalTemp();
      //alert('Cliente actualizado correctamente.');
    } else {
      alert('Error al actualizar: ' + (data.message || 'Error desconocido'));
    }
  } catch (e) {
    alert('Error de conexión: ' + (e.message || 'Error desconocido'));
  } finally {
    modalTempActualizando.value = false;
  }
}
</script>
