<template>
  <div class="max-w-7xl mx-auto">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
      <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">Pendiente de pago</h1>
      <div class="flex items-center gap-2 flex-wrap">
        <a
          :href="exportHref"
          class="inline-flex items-center justify-center gap-2 px-3 py-2 border border-emerald-300 dark:border-emerald-700 rounded-lg text-sm font-medium text-emerald-900 dark:text-emerald-200 hover:bg-emerald-50 dark:hover:bg-emerald-900/30 transition-colors"
          title="Exportar listado actual a Excel (CSV)"
        >
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
          </svg>
          <span class="hidden sm:inline">Exportar Excel</span>
        </a>
        <a
          :href="urls.promesasIndex || '#'"
          class="inline-flex items-center justify-center gap-2 px-3 py-2 border border-amber-300 dark:border-amber-700 rounded-lg text-sm font-medium text-amber-900 dark:text-amber-200 hover:bg-amber-50 dark:hover:bg-amber-900/30 transition-colors"
          title="Lista de promesas de pago"
        >
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" />
          </svg>
          <span class="hidden sm:inline">Promesas</span>
        </a>
        <a
          v-if="canCrearCobro && urls.cobrosCreate"
          :href="urls.cobrosCreate"
          class="inline-flex items-center justify-center p-2 rounded-lg bg-green-600 text-white hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 dark:focus:ring-offset-gray-900 transition-colors"
          title="Registrar cobro"
          aria-label="Registrar cobro"
        >
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
          </svg>
        </a>
      </div>
    </div>

    <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
      Facturas internas con saldo pendiente. Busque por nombre o cédula. Haga clic en el encabezado de una columna para ordenar (ascendente / descendente). Use el embudo para filtrar.
      <span v-if="canMulticobro"> Marque filas y use «Multicobro» para abonar varias facturas a la vez.</span>
    </p>

    <div v-if="flashSuccess" class="mb-4 p-4 rounded-lg bg-green-100 dark:bg-green-900/20 text-green-800 dark:text-green-200 border border-green-200 dark:border-green-800 text-sm">
      {{ flashSuccess }}
    </div>
    <div v-if="flashError" class="mb-4 p-4 rounded-lg bg-red-100 dark:bg-red-900/20 text-red-800 dark:text-red-200 border border-red-200 dark:border-red-800 text-sm">
      {{ flashError }}
    </div>
    <div v-if="loadError" class="mb-4 p-4 rounded-lg bg-red-100 dark:bg-red-900/20 text-red-800 dark:text-red-200 border border-red-200 dark:border-red-800 text-sm">
      {{ loadError }}
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 overflow-hidden">
      <div class="p-4 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-700/50">
        <div class="flex flex-col sm:flex-row gap-3 flex-wrap">
          <div class="flex-1 min-w-[200px]">
            <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-0.5">Buscar por nombre o cédula</label>
            <input
              v-model="buscar"
              type="text"
              placeholder="Nombre, apellido o cédula del cliente..."
              class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-green-500 focus:ring-2 focus:ring-green-500/20 text-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 placeholder-gray-500 dark:placeholder-gray-400"
              @keyup.enter="aplicarBusqueda"
            />
          </div>
          <div class="flex items-end gap-2">
            <button type="button" class="px-4 py-2 bg-gray-700 dark:bg-gray-600 text-white rounded-lg font-medium hover:bg-gray-800 dark:hover:bg-gray-500 text-sm" @click="aplicarBusqueda">
              Buscar
            </button>
            <button v-if="hayFiltros" type="button" class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 text-sm" @click="limpiarTodo">
              Limpiar todo
            </button>
          </div>
        </div>
      </div>

      <form v-if="canMulticobro && urls.multicobro" :action="urls.multicobro" method="GET" class="p-4 border-b border-gray-200 dark:border-gray-700 bg-amber-50 dark:bg-amber-900/20">
        <div class="flex items-center gap-3 flex-wrap">
          <span class="text-sm text-gray-700 dark:text-gray-300">Marcar facturas para multicobro:</span>
          <button type="submit" :disabled="selectedIds.length === 0" class="inline-flex items-center px-4 py-2 bg-amber-600 text-white rounded-lg font-medium hover:bg-amber-700 disabled:opacity-50 disabled:cursor-not-allowed text-sm">
            Registrar multicobro ({{ selectedIds.length }})
          </button>
        </div>
        <template v-for="id in selectedIds" :key="'h-' + id">
          <input type="hidden" name="factura_interna_ids[]" :value="id" />
        </template>
      </form>

      <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
          <thead class="bg-gray-50 dark:bg-gray-700/50">
            <tr>
              <th v-if="canMulticobro" class="px-4 py-3 w-10">
                <input
                  type="checkbox"
                  class="rounded border-gray-300 dark:border-gray-600 text-amber-600 focus:ring-amber-500"
                  title="Seleccionar todos"
                  :checked="allSelected"
                  @change="toggleSelectAll(($event.target).checked)"
                />
              </th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase relative align-bottom">
                <div class="flex items-center gap-0.5">
                  <button type="button" class="inline-flex items-center gap-1 flex-1 min-w-0 rounded px-0.5 py-0.5 hover:bg-gray-200 dark:hover:bg-gray-600 text-left" title="Ordenar por ID" @click="toggleSort('id')">
                    <span>#</span>
                    <span v-if="filtrosActivo.pf_id" class="w-1.5 h-1.5 rounded-full bg-amber-500 shrink-0" title="Filtro activo" />
                    <svg v-if="sortBy === 'id' && sortDir === 'asc'" xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5 shrink-0 text-gray-600 dark:text-gray-300" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 15.75l7.5-7.5 7.5 7.5" /></svg>
                    <svg v-else-if="sortBy === 'id'" xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5 shrink-0 text-gray-600 dark:text-gray-300" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" /></svg>
                  </button>
                  <button type="button" class="pf-funnel p-0.5 rounded text-gray-500 dark:text-gray-400 hover:bg-gray-200 dark:hover:bg-gray-600 shrink-0" title="Filtrar por ID" aria-label="Filtrar por ID" @click.stop="toggleFiltroPop('pf_id')">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-3.5 h-3.5 opacity-80"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3c2.755 0 5.455.232 8.083.678.533.09.917.556.917 1.096v1.044a2.25 2.25 0 0 1-.659 1.591l-5.432 5.432a2.25 2.25 0 0 0-.659 1.591v2.927a2.25 2.25 0 0 1-1.244 2.013L9.75 21v-6.568a2.25 2.25 0 0 0-.659-1.591L3.659 7.409A2.25 2.25 0 0 1 3 5.818V4.774c0-.54.384-1.006.917-1.096A48.32 48.32 0 0 1 12 3Z" /></svg>
                  </button>
                </div>
                <div v-show="openFilterKey === 'pf_id'" class="pf-popover absolute left-0 top-full mt-1 z-[60] w-56 rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-xl p-3 font-normal normal-case">
                  <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">ID factura</label>
                  <input v-model="draftPf.pf_id" type="text" class="w-full px-2 py-1.5 text-sm rounded border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100" placeholder="Ej. 1234" autocomplete="off" />
                  <div class="flex gap-2 mt-3 justify-end">
                    <button type="button" class="px-2 py-1 text-xs border border-gray-300 dark:border-gray-600 rounded text-gray-700 dark:text-gray-200" @click="limpiarFiltroCol('pf_id')">Limpiar</button>
                    <button type="button" class="px-2 py-1 text-xs bg-green-600 text-white rounded hover:bg-green-700" @click="aplicarFiltroDraft">Aplicar</button>
                  </div>
                </div>
              </th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase relative align-bottom">
                <div class="flex items-center gap-0.5">
                  <button type="button" class="inline-flex items-center gap-1 flex-1 min-w-0 rounded px-0.5 py-0.5 hover:bg-gray-200 dark:hover:bg-gray-600 text-left" title="Ordenar por cliente" @click="toggleSort('cliente')">
                    <span>Cliente</span>
                    <span v-if="filtrosActivo.pf_cliente" class="w-1.5 h-1.5 rounded-full bg-amber-500 shrink-0" title="Filtro activo" />
                    <svg v-if="sortBy === 'cliente' && sortDir === 'asc'" xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5 shrink-0 text-gray-600 dark:text-gray-300" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 15.75l7.5-7.5 7.5 7.5" /></svg>
                    <svg v-else-if="sortBy === 'cliente'" xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5 shrink-0 text-gray-600 dark:text-gray-300" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" /></svg>
                  </button>
                  <button type="button" class="pf-funnel p-0.5 rounded text-gray-500 dark:text-gray-400 hover:bg-gray-200 dark:hover:bg-gray-600 shrink-0" title="Filtrar por cliente" aria-label="Filtrar por cliente" @click.stop="toggleFiltroPop('pf_cliente')">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-3.5 h-3.5 opacity-80"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3c2.755 0 5.455.232 8.083.678.533.09.917.556.917 1.096v1.044a2.25 2.25 0 0 1-.659 1.591l-5.432 5.432a2.25 2.25 0 0 0-.659 1.591v2.927a2.25 2.25 0 0 1-1.244 2.013L9.75 21v-6.568a2.25 2.25 0 0 0-.659-1.591L3.659 7.409A2.25 2.25 0 0 1 3 5.818V4.774c0-.54.384-1.006.917-1.096A48.32 48.32 0 0 1 12 3Z" /></svg>
                  </button>
                </div>
                <div v-show="openFilterKey === 'pf_cliente'" class="pf-popover absolute left-0 top-full mt-1 z-[60] w-64 rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-xl p-3 font-normal normal-case">
                  <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Nombre o cédula</label>
                  <input v-model="draftPf.pf_cliente" type="text" class="w-full px-2 py-1.5 text-sm rounded border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100" placeholder="Texto parcial" autocomplete="off" />
                  <div class="flex gap-2 mt-3 justify-end">
                    <button type="button" class="px-2 py-1 text-xs border border-gray-300 dark:border-gray-600 rounded text-gray-700 dark:text-gray-200" @click="limpiarFiltroCol('pf_cliente')">Limpiar</button>
                    <button type="button" class="px-2 py-1 text-xs bg-green-600 text-white rounded hover:bg-green-700" @click="aplicarFiltroDraft">Aplicar</button>
                  </div>
                </div>
              </th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase relative align-bottom">
                <div class="flex items-center gap-0.5">
                  <button type="button" class="inline-flex items-center gap-1 flex-1 min-w-0 rounded px-0.5 py-0.5 hover:bg-gray-200 dark:hover:bg-gray-600 text-left" title="Ordenar por período" @click="toggleSort('periodo')">
                    <span>Período</span>
                    <span v-if="filtrosActivo.pf_per" class="w-1.5 h-1.5 rounded-full bg-amber-500 shrink-0" title="Filtro activo" />
                    <svg v-if="sortBy === 'periodo' && sortDir === 'asc'" xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5 shrink-0 text-gray-600 dark:text-gray-300" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 15.75l7.5-7.5 7.5 7.5" /></svg>
                    <svg v-else-if="sortBy === 'periodo'" xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5 shrink-0 text-gray-600 dark:text-gray-300" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" /></svg>
                  </button>
                  <button type="button" class="pf-funnel p-0.5 rounded text-gray-500 dark:text-gray-400 hover:bg-gray-200 dark:hover:bg-gray-600 shrink-0" title="Filtrar por período" aria-label="Filtrar por período" @click.stop="toggleFiltroPop('pf_periodo')">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-3.5 h-3.5 opacity-80"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3c2.755 0 5.455.232 8.083.678.533.09.917.556.917 1.096v1.044a2.25 2.25 0 0 1-.659 1.591l-5.432 5.432a2.25 2.25 0 0 0-.659 1.591v2.927a2.25 2.25 0 0 1-1.244 2.013L9.75 21v-6.568a2.25 2.25 0 0 0-.659-1.591L3.659 7.409A2.25 2.25 0 0 1 3 5.818V4.774c0-.54.384-1.006.917-1.096A48.32 48.32 0 0 1 12 3Z" /></svg>
                  </button>
                </div>
                <div v-show="openFilterKey === 'pf_periodo'" class="pf-popover absolute left-0 top-full mt-1 z-[60] w-60 rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-xl p-3 font-normal normal-case">
                  <p class="text-xs text-gray-500 dark:text-gray-400 mb-2">Facturas cuyo período se solapa con el rango.</p>
                  <label class="block text-xs text-gray-500 dark:text-gray-400 mb-0.5">Desde</label>
                  <input v-model="draftPf.pf_per_desde" type="date" class="w-full px-2 py-1.5 text-sm rounded border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 mb-2" />
                  <label class="block text-xs text-gray-500 dark:text-gray-400 mb-0.5">Hasta</label>
                  <input v-model="draftPf.pf_per_hasta" type="date" class="w-full px-2 py-1.5 text-sm rounded border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100" />
                  <div class="flex gap-2 mt-3 justify-end">
                    <button type="button" class="px-2 py-1 text-xs border border-gray-300 dark:border-gray-600 rounded text-gray-700 dark:text-gray-200" @click="limpiarFiltroCol('pf_per_desde', 'pf_per_hasta')">Limpiar</button>
                    <button type="button" class="px-2 py-1 text-xs bg-green-600 text-white rounded hover:bg-green-700" @click="aplicarFiltroDraft">Aplicar</button>
                  </div>
                </div>
              </th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase relative align-bottom">
                <div class="flex items-center gap-0.5">
                  <button type="button" class="inline-flex items-center gap-1 flex-1 min-w-0 rounded px-0.5 py-0.5 hover:bg-gray-200 dark:hover:bg-gray-600 text-left" title="Ordenar por vencimiento" @click="toggleSort('vencimiento')">
                    <span>Vencimiento</span>
                    <span v-if="filtrosActivo.pf_ven" class="w-1.5 h-1.5 rounded-full bg-amber-500 shrink-0" title="Filtro activo" />
                    <svg v-if="sortBy === 'vencimiento' && sortDir === 'asc'" xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5 shrink-0 text-gray-600 dark:text-gray-300" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 15.75l7.5-7.5 7.5 7.5" /></svg>
                    <svg v-else-if="sortBy === 'vencimiento'" xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5 shrink-0 text-gray-600 dark:text-gray-300" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" /></svg>
                  </button>
                  <button type="button" class="pf-funnel p-0.5 rounded text-gray-500 dark:text-gray-400 hover:bg-gray-200 dark:hover:bg-gray-600 shrink-0" title="Filtrar por vencimiento" aria-label="Filtrar por vencimiento" @click.stop="toggleFiltroPop('pf_ven')">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-3.5 h-3.5 opacity-80"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3c2.755 0 5.455.232 8.083.678.533.09.917.556.917 1.096v1.044a2.25 2.25 0 0 1-.659 1.591l-5.432 5.432a2.25 2.25 0 0 0-.659 1.591v2.927a2.25 2.25 0 0 1-1.244 2.013L9.75 21v-6.568a2.25 2.25 0 0 0-.659-1.591L3.659 7.409A2.25 2.25 0 0 1 3 5.818V4.774c0-.54.384-1.006.917-1.096A48.32 48.32 0 0 1 12 3Z" /></svg>
                  </button>
                </div>
                <div v-show="openFilterKey === 'pf_ven'" class="pf-popover absolute left-0 top-full mt-1 z-[60] w-60 rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-xl p-3 font-normal normal-case">
                  <label class="block text-xs text-gray-500 dark:text-gray-400 mb-0.5">Desde</label>
                  <input v-model="draftPf.pf_ven_desde" type="date" class="w-full px-2 py-1.5 text-sm rounded border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 mb-2" />
                  <label class="block text-xs text-gray-500 dark:text-gray-400 mb-0.5">Hasta</label>
                  <input v-model="draftPf.pf_ven_hasta" type="date" class="w-full px-2 py-1.5 text-sm rounded border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100" />
                  <div class="flex gap-2 mt-3 justify-end">
                    <button type="button" class="px-2 py-1 text-xs border border-gray-300 dark:border-gray-600 rounded text-gray-700 dark:text-gray-200" @click="limpiarFiltroCol('pf_ven_desde', 'pf_ven_hasta')">Limpiar</button>
                    <button type="button" class="px-2 py-1 text-xs bg-green-600 text-white rounded hover:bg-green-700" @click="aplicarFiltroDraft">Aplicar</button>
                  </div>
                </div>
              </th>
              <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase relative align-bottom">
                <div class="flex items-center justify-end gap-0.5">
                  <button type="button" class="inline-flex items-center gap-1 rounded px-0.5 py-0.5 hover:bg-gray-200 dark:hover:bg-gray-600" title="Ordenar por total" @click="toggleSort('total')">
                    <span>Total</span>
                    <span v-if="filtrosActivo.pf_total" class="w-1.5 h-1.5 rounded-full bg-amber-500 shrink-0" title="Filtro activo" />
                    <svg v-if="sortBy === 'total' && sortDir === 'asc'" xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5 shrink-0 text-gray-600 dark:text-gray-300" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 15.75l7.5-7.5 7.5 7.5" /></svg>
                    <svg v-else-if="sortBy === 'total'" xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5 shrink-0 text-gray-600 dark:text-gray-300" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" /></svg>
                  </button>
                  <button type="button" class="pf-funnel p-0.5 rounded text-gray-500 dark:text-gray-400 hover:bg-gray-200 dark:hover:bg-gray-600 shrink-0" title="Filtrar por total" aria-label="Filtrar por total" @click.stop="toggleFiltroPop('pf_total')">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-3.5 h-3.5 opacity-80"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3c2.755 0 5.455.232 8.083.678.533.09.917.556.917 1.096v1.044a2.25 2.25 0 0 1-.659 1.591l-5.432 5.432a2.25 2.25 0 0 0-.659 1.591v2.927a2.25 2.25 0 0 1-1.244 2.013L9.75 21v-6.568a2.25 2.25 0 0 0-.659-1.591L3.659 7.409A2.25 2.25 0 0 1 3 5.818V4.774c0-.54.384-1.006.917-1.096A48.32 48.32 0 0 1 12 3Z" /></svg>
                  </button>
                </div>
                <div v-show="openFilterKey === 'pf_total'" class="pf-popover absolute right-0 top-full mt-1 z-[60] w-56 rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-xl p-3 font-normal normal-case text-left">
                  <label class="block text-xs text-gray-500 dark:text-gray-400 mb-0.5">Mínimo</label>
                  <input v-model="draftPf.pf_total_min" type="number" step="any" min="0" class="w-full px-2 py-1.5 text-sm rounded border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 mb-2" placeholder="0" />
                  <label class="block text-xs text-gray-500 dark:text-gray-400 mb-0.5">Máximo</label>
                  <input v-model="draftPf.pf_total_max" type="number" step="any" min="0" class="w-full px-2 py-1.5 text-sm rounded border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100" />
                  <div class="flex gap-2 mt-3 justify-end">
                    <button type="button" class="px-2 py-1 text-xs border border-gray-300 dark:border-gray-600 rounded text-gray-700 dark:text-gray-200" @click="limpiarFiltroCol('pf_total_min', 'pf_total_max')">Limpiar</button>
                    <button type="button" class="px-2 py-1 text-xs bg-green-600 text-white rounded hover:bg-green-700" @click="aplicarFiltroDraft">Aplicar</button>
                  </div>
                </div>
              </th>
              <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase relative align-bottom">
                <div class="flex items-center justify-end gap-0.5">
                  <button type="button" class="inline-flex items-center gap-1 rounded px-0.5 py-0.5 hover:bg-gray-200 dark:hover:bg-gray-600" title="Ordenar por cobrado" @click="toggleSort('cobrado')">
                    <span>Cobrado</span>
                    <span v-if="filtrosActivo.pf_cob" class="w-1.5 h-1.5 rounded-full bg-amber-500 shrink-0" title="Filtro activo" />
                    <svg v-if="sortBy === 'cobrado' && sortDir === 'asc'" xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5 shrink-0 text-gray-600 dark:text-gray-300" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 15.75l7.5-7.5 7.5 7.5" /></svg>
                    <svg v-else-if="sortBy === 'cobrado'" xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5 shrink-0 text-gray-600 dark:text-gray-300" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" /></svg>
                  </button>
                  <button type="button" class="pf-funnel p-0.5 rounded text-gray-500 dark:text-gray-400 hover:bg-gray-200 dark:hover:bg-gray-600 shrink-0" title="Filtrar por cobrado" aria-label="Filtrar por cobrado" @click.stop="toggleFiltroPop('pf_cob')">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-3.5 h-3.5 opacity-80"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3c2.755 0 5.455.232 8.083.678.533.09.917.556.917 1.096v1.044a2.25 2.25 0 0 1-.659 1.591l-5.432 5.432a2.25 2.25 0 0 0-.659 1.591v2.927a2.25 2.25 0 0 1-1.244 2.013L9.75 21v-6.568a2.25 2.25 0 0 0-.659-1.591L3.659 7.409A2.25 2.25 0 0 1 3 5.818V4.774c0-.54.384-1.006.917-1.096A48.32 48.32 0 0 1 12 3Z" /></svg>
                  </button>
                </div>
                <div v-show="openFilterKey === 'pf_cob'" class="pf-popover absolute right-0 top-full mt-1 z-[60] w-56 rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-xl p-3 font-normal normal-case text-left">
                  <label class="block text-xs text-gray-500 dark:text-gray-400 mb-0.5">Mínimo</label>
                  <input v-model="draftPf.pf_cob_min" type="number" step="any" min="0" class="w-full px-2 py-1.5 text-sm rounded border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 mb-2" />
                  <label class="block text-xs text-gray-500 dark:text-gray-400 mb-0.5">Máximo</label>
                  <input v-model="draftPf.pf_cob_max" type="number" step="any" min="0" class="w-full px-2 py-1.5 text-sm rounded border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100" />
                  <div class="flex gap-2 mt-3 justify-end">
                    <button type="button" class="px-2 py-1 text-xs border border-gray-300 dark:border-gray-600 rounded text-gray-700 dark:text-gray-200" @click="limpiarFiltroCol('pf_cob_min', 'pf_cob_max')">Limpiar</button>
                    <button type="button" class="px-2 py-1 text-xs bg-green-600 text-white rounded hover:bg-green-700" @click="aplicarFiltroDraft">Aplicar</button>
                  </div>
                </div>
              </th>
              <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase relative align-bottom">
                <div class="flex items-center justify-end gap-0.5">
                  <button type="button" class="inline-flex items-center gap-1 rounded px-0.5 py-0.5 hover:bg-gray-200 dark:hover:bg-gray-600" title="Ordenar por saldo" @click="toggleSort('saldo')">
                    <span>Saldo</span>
                    <span v-if="filtrosActivo.pf_saldo" class="w-1.5 h-1.5 rounded-full bg-amber-500 shrink-0" title="Filtro activo" />
                    <svg v-if="sortBy === 'saldo' && sortDir === 'asc'" xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5 shrink-0 text-gray-600 dark:text-gray-300" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 15.75l7.5-7.5 7.5 7.5" /></svg>
                    <svg v-else-if="sortBy === 'saldo'" xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5 shrink-0 text-gray-600 dark:text-gray-300" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" /></svg>
                  </button>
                  <button type="button" class="pf-funnel p-0.5 rounded text-gray-500 dark:text-gray-400 hover:bg-gray-200 dark:hover:bg-gray-600 shrink-0" title="Filtrar por saldo" aria-label="Filtrar por saldo" @click.stop="toggleFiltroPop('pf_saldo')">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-3.5 h-3.5 opacity-80"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3c2.755 0 5.455.232 8.083.678.533.09.917.556.917 1.096v1.044a2.25 2.25 0 0 1-.659 1.591l-5.432 5.432a2.25 2.25 0 0 0-.659 1.591v2.927a2.25 2.25 0 0 1-1.244 2.013L9.75 21v-6.568a2.25 2.25 0 0 0-.659-1.591L3.659 7.409A2.25 2.25 0 0 1 3 5.818V4.774c0-.54.384-1.006.917-1.096A48.32 48.32 0 0 1 12 3Z" /></svg>
                  </button>
                </div>
                <div v-show="openFilterKey === 'pf_saldo'" class="pf-popover absolute right-0 top-full mt-1 z-[60] w-56 rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-xl p-3 font-normal normal-case text-left">
                  <label class="block text-xs text-gray-500 dark:text-gray-400 mb-0.5">Mínimo</label>
                  <input v-model="draftPf.pf_saldo_min" type="number" step="any" min="0" class="w-full px-2 py-1.5 text-sm rounded border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 mb-2" />
                  <label class="block text-xs text-gray-500 dark:text-gray-400 mb-0.5">Máximo</label>
                  <input v-model="draftPf.pf_saldo_max" type="number" step="any" min="0" class="w-full px-2 py-1.5 text-sm rounded border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100" />
                  <div class="flex gap-2 mt-3 justify-end">
                    <button type="button" class="px-2 py-1 text-xs border border-gray-300 dark:border-gray-600 rounded text-gray-700 dark:text-gray-200" @click="limpiarFiltroCol('pf_saldo_min', 'pf_saldo_max')">Limpiar</button>
                    <button type="button" class="px-2 py-1 text-xs bg-green-600 text-white rounded hover:bg-green-700" @click="aplicarFiltroDraft">Aplicar</button>
                  </div>
                </div>
              </th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase relative align-bottom">
                <div class="flex items-center gap-0.5">
                  <button type="button" class="inline-flex items-center gap-1 flex-1 min-w-0 rounded px-0.5 py-0.5 hover:bg-gray-200 dark:hover:bg-gray-600 text-left" title="Ordenar por promesa" @click="toggleSort('promesa')">
                    <span>Promesa</span>
                    <span v-if="filtrosActivo.pf_promesa" class="w-1.5 h-1.5 rounded-full bg-amber-500 shrink-0" title="Filtro activo" />
                    <svg v-if="sortBy === 'promesa' && sortDir === 'asc'" xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5 shrink-0 text-gray-600 dark:text-gray-300" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 15.75l7.5-7.5 7.5 7.5" /></svg>
                    <svg v-else-if="sortBy === 'promesa'" xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5 shrink-0 text-gray-600 dark:text-gray-300" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" /></svg>
                  </button>
                  <button type="button" class="pf-funnel p-0.5 rounded text-gray-500 dark:text-gray-400 hover:bg-gray-200 dark:hover:bg-gray-600 shrink-0" title="Filtrar por promesa" aria-label="Filtrar por promesa" @click.stop="toggleFiltroPop('pf_promesa')">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-3.5 h-3.5 opacity-80"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3c2.755 0 5.455.232 8.083.678.533.09.917.556.917 1.096v1.044a2.25 2.25 0 0 1-.659 1.591l-5.432 5.432a2.25 2.25 0 0 0-.659 1.591v2.927a2.25 2.25 0 0 1-1.244 2.013L9.75 21v-6.568a2.25 2.25 0 0 0-.659-1.591L3.659 7.409A2.25 2.25 0 0 1 3 5.818V4.774c0-.54.384-1.006.917-1.096A48.32 48.32 0 0 1 12 3Z" /></svg>
                  </button>
                </div>
                <div v-show="openFilterKey === 'pf_promesa'" class="pf-popover absolute left-0 top-full mt-1 z-[60] w-56 rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-xl p-3 font-normal normal-case">
                  <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Estado</label>
                  <select v-model="draftPf.pf_promesa" class="w-full px-2 py-1.5 text-sm rounded border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                    <option value="">Todas</option>
                    <option value="con">Con promesa</option>
                    <option value="sin">Sin promesa</option>
                  </select>
                  <div class="flex gap-2 mt-3 justify-end">
                    <button type="button" class="px-2 py-1 text-xs border border-gray-300 dark:border-gray-600 rounded text-gray-700 dark:text-gray-200" @click="limpiarFiltroCol('pf_promesa')">Limpiar</button>
                    <button type="button" class="px-2 py-1 text-xs bg-green-600 text-white rounded hover:bg-green-700" @click="aplicarFiltroDraft">Aplicar</button>
                  </div>
                </div>
              </th>
              <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase w-44">Acciones</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-200 dark:divide-gray-700 bg-white dark:bg-gray-800">
            <tr v-if="loading">
              <td :colspan="colspanTabla" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">Cargando…</td>
            </tr>
            <template v-else>
            <tr v-for="row in rows" :key="row.id" class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
              <td v-if="canMulticobro" class="px-4 py-3">
                <input
                  type="checkbox"
                  class="rounded border-gray-300 dark:border-gray-600 text-amber-600 focus:ring-amber-500"
                  :checked="selectedIds.includes(row.id)"
                  @change="toggleRow(row.id, ($event.target).checked)"
                />
              </td>
              <td class="px-4 py-3 text-sm font-medium text-gray-900 dark:text-gray-100">{{ row.id }}</td>
              <td class="px-4 py-3 text-sm text-gray-900 dark:text-gray-100">{{ row.cliente_nombre }}</td>
              <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-400">{{ fmtPeriodo(row) }}</td>
              <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-400">{{ row.fecha_vencimiento ? fmtFecha(row.fecha_vencimiento) : '—' }}</td>
              <td class="px-4 py-3 text-sm text-right font-medium text-gray-900 dark:text-gray-100">{{ fmtMonto(row.total, row.moneda) }}</td>
              <td class="px-4 py-3 text-sm text-right text-gray-600 dark:text-gray-400">{{ fmtMonto(row.monto_pagado, row.moneda) }}</td>
              <td class="px-4 py-3 text-sm text-right font-semibold text-amber-700 dark:text-amber-400">{{ fmtMonto(row.saldo_pendiente, row.moneda) }}</td>
              <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-400">
                <span v-if="row.promesa_label" class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-amber-100 text-amber-900 dark:bg-amber-900/40 dark:text-amber-200" title="Promesa de pago">
                  {{ row.promesa_label }}
                </span>
                <span v-else>—</span>
              </td>
              <td class="px-4 py-3 text-right">
                <div class="inline-flex items-center gap-0.5">
                  <button
                    type="button"
                    class="inline-flex items-center justify-center p-2 rounded-lg text-sky-600 hover:bg-sky-50 dark:text-sky-400 dark:hover:bg-sky-900/30 transition-colors"
                    title="Contacto, dirección y ubicación"
                    aria-label="Ver contacto y ubicación del cliente"
                    @click="abrirModalContacto(row)"
                  >
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0Z" />
                      <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0Z" />
                    </svg>
                  </button>
                  <a :href="urlFacturaShow(row.id)" class="inline-flex items-center justify-center p-2 rounded-lg text-purple-600 hover:bg-purple-50 dark:text-purple-400 dark:hover:bg-purple-900/30 transition-colors" title="Ver factura interna" aria-label="Ver factura interna">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
                      <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0z" />
                    </svg>
                  </a>
                  <a v-if="canCrearCobro" :href="urlPromesaCreate(row.id)" class="inline-flex items-center justify-center p-2 rounded-lg text-amber-600 hover:bg-amber-50 dark:text-amber-400 dark:hover:bg-amber-900/30 transition-colors" title="Registrar promesa de pago" aria-label="Registrar promesa de pago">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" />
                    </svg>
                  </a>
                  <a v-if="canCrearCobro && urls.cobrosCreate" :href="urlCobroCreate(row.cliente_id, row.id)" class="inline-flex items-center justify-center p-2 rounded-lg text-green-600 hover:bg-green-50 dark:text-green-400 dark:hover:bg-green-900/30 transition-colors" title="Registrar cobro" aria-label="Registrar cobro">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                    </svg>
                  </a>
                </div>
              </td>
            </tr>
            <tr v-if="rows.length === 0">
              <td :colspan="colspanTabla" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">No hay facturas internas pendientes de pago.</td>
            </tr>
            </template>
          </tbody>
        </table>
      </div>

      <div v-if="meta.last_page > 1" class="px-4 py-3 border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-700/50 flex flex-wrap items-center justify-between gap-2">
        <p class="text-sm text-gray-600 dark:text-gray-400">
          Mostrando {{ meta.from ?? 0 }}–{{ meta.to ?? 0 }} de {{ meta.total }}
        </p>
        <div class="flex items-center gap-1 flex-wrap">
          <button type="button" :disabled="meta.current_page <= 1" class="px-3 py-1.5 text-sm rounded border border-gray-300 dark:border-gray-600 disabled:opacity-40" @click="irPagina(1)">«</button>
          <button type="button" :disabled="meta.current_page <= 1" class="px-3 py-1.5 text-sm rounded border border-gray-300 dark:border-gray-600 disabled:opacity-40" @click="irPagina(meta.current_page - 1)">‹</button>
          <span class="px-2 text-sm text-gray-700 dark:text-gray-200">Pág. {{ meta.current_page }} / {{ meta.last_page }}</span>
          <button type="button" :disabled="meta.current_page >= meta.last_page" class="px-3 py-1.5 text-sm rounded border border-gray-300 dark:border-gray-600 disabled:opacity-40" @click="irPagina(meta.current_page + 1)">›</button>
          <button type="button" :disabled="meta.current_page >= meta.last_page" class="px-3 py-1.5 text-sm rounded border border-gray-300 dark:border-gray-600 disabled:opacity-40" @click="irPagina(meta.last_page)">»</button>
        </div>
      </div>
    </div>

    <!-- Modal contacto cliente -->
    <div v-show="modalContactoAbierto" class="fixed inset-0 z-50 overflow-y-auto" role="dialog" aria-modal="true">
      <div class="fixed inset-0 bg-black/50 transition-opacity" aria-hidden="true" @click="cerrarModalContacto" />
      <div class="flex min-h-full items-center justify-center p-4">
        <div class="relative w-full max-w-md rounded-xl bg-white dark:bg-gray-800 shadow-xl border border-gray-200 dark:border-gray-700" @click.stop>
          <div class="flex items-center justify-between gap-3 px-4 py-3 border-b border-gray-200 dark:border-gray-700">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Cliente</h2>
            <button type="button" class="p-2 rounded-lg text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-700 dark:text-gray-400" aria-label="Cerrar" @click="cerrarModalContacto">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
            </button>
          </div>
          <div class="px-4 py-4 space-y-3 text-sm">
            <div>
              <span class="text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Nombre</span>
              <p class="mt-0.5 text-gray-900 dark:text-gray-100 font-medium">{{ modalContacto.nombre || '—' }}</p>
            </div>
            <div>
              <span class="text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Cédula</span>
              <p class="mt-0.5 text-gray-800 dark:text-gray-200">{{ modalContacto.cedula || '—' }}</p>
            </div>
            <div>
              <span class="text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Celular</span>
              <p class="mt-0.5 text-gray-800 dark:text-gray-200">{{ modalContacto.celular || '—' }}</p>
            </div>
            <div>
              <span class="text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Email</span>
              <p class="mt-0.5 text-gray-800 dark:text-gray-200 break-all">{{ modalContacto.email || '—' }}</p>
            </div>
            <div>
              <span class="text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Dirección</span>
              <p class="mt-0.5 text-gray-800 dark:text-gray-200 whitespace-pre-wrap break-words">{{ modalContacto.direccion || '—' }}</p>
            </div>
            <div>
              <span class="text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Ubicación (mapa)</span>
              <p class="mt-0.5">
                <a v-if="(modalContacto.url_ubicacion || '').trim()" :href="modalContacto.url_ubicacion.trim()" target="_blank" rel="noopener noreferrer" class="text-sky-600 dark:text-sky-400 hover:underline break-all">Abrir enlace de ubicación</a>
                <span v-else>—</span>
              </p>
            </div>
            <p v-if="modalContacto.detalle_url" class="pt-1">
              <a :href="modalContacto.detalle_url" class="text-sm font-medium text-purple-600 hover:text-purple-800 dark:text-purple-400 dark:hover:text-purple-300">Ver ficha completa del cliente</a>
            </p>
          </div>
          <div class="px-4 py-3 border-t border-gray-200 dark:border-gray-700 flex justify-end">
            <button type="button" class="px-4 py-2 rounded-lg bg-gray-200 dark:bg-gray-600 text-gray-800 dark:text-gray-200 text-sm font-medium hover:bg-gray-300 dark:hover:bg-gray-500" @click="cerrarModalContacto">Cerrar</button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, computed, watch, onMounted, onUnmounted } from 'vue';
import axios from 'axios';

const props = defineProps({
  listUrl: { type: String, default: '' },
  exportExcelUrl: { type: String, default: '' },
  pfKeys: { type: Array, default: () => [] },
  urls: { type: Object, default: () => ({}) },
  templates: { type: Object, default: () => ({}) },
  clienteDetalleTpl: { type: String, default: '' },
  canMulticobro: { type: Boolean, default: false },
  canCrearCobro: { type: Boolean, default: false },
  canVerClienteDetalle: { type: Boolean, default: false },
  flashSuccess: { type: String, default: '' },
  flashError: { type: String, default: '' },
});

const buscar = ref('');
const pf = reactive({
  pf_id: '',
  pf_cliente: '',
  pf_per_desde: '',
  pf_per_hasta: '',
  pf_ven_desde: '',
  pf_ven_hasta: '',
  pf_total_min: '',
  pf_total_max: '',
  pf_cob_min: '',
  pf_cob_max: '',
  pf_saldo_min: '',
  pf_saldo_max: '',
  pf_promesa: '',
});

const draftPf = reactive({ ...pf });

const sortBy = ref('vencimiento');
const sortDir = ref('asc');
const page = ref(1);

const rows = ref([]);
const meta = ref({
  current_page: 1,
  last_page: 1,
  per_page: 20,
  total: 0,
  from: null,
  to: null,
});
const loading = ref(true);
const loadError = ref('');

const selectedIds = ref([]);
const openFilterKey = ref(null);

const modalContactoAbierto = ref(false);
const modalContacto = reactive({
  nombre: '',
  cedula: '',
  celular: '',
  email: '',
  direccion: '',
  url_ubicacion: '',
  detalle_url: '',
});

const colspanTabla = computed(() => (props.canMulticobro ? 10 : 9));

const allSelected = computed(() => rows.value.length > 0 && selectedIds.value.length === rows.value.length);

const filtrosActivo = computed(() => ({
  pf_id: !!(pf.pf_id || '').trim(),
  pf_cliente: !!(pf.pf_cliente || '').trim(),
  pf_per: !!(pf.pf_per_desde || '').trim() || !!(pf.pf_per_hasta || '').trim(),
  pf_ven: !!(pf.pf_ven_desde || '').trim() || !!(pf.pf_ven_hasta || '').trim(),
  pf_total: !!(pf.pf_total_min || '').trim() || !!(pf.pf_total_max || '').trim(),
  pf_cob: !!(pf.pf_cob_min || '').trim() || !!(pf.pf_cob_max || '').trim(),
  pf_saldo: !!(pf.pf_saldo_min || '').trim() || !!(pf.pf_saldo_max || '').trim(),
  pf_promesa: !!(pf.pf_promesa || '').trim(),
}));

const hayFiltros = computed(() => {
  if ((buscar.value || '').trim() !== '') return true;
  return Object.keys(pf).some((k) => !!(pf[k] || '').toString().trim());
});

function syncDraftFromPf() {
  Object.keys(pf).forEach((k) => {
    draftPf[k] = pf[k];
  });
}

function onDocClick(ev) {
  const t = ev.target;
  if (t.closest?.('.pf-popover') || t.closest?.('.pf-funnel')) return;
  openFilterKey.value = null;
}

watch(openFilterKey, (v) => {
  if (v != null) {
    syncDraftFromPf();
    setTimeout(() => document.addEventListener('click', onDocClick, true), 0);
  } else {
    document.removeEventListener('click', onDocClick, true);
  }
});

function toggleFiltroPop(key) {
  openFilterKey.value = openFilterKey.value === key ? null : key;
}

function leerParamsUrl() {
  const allowedSort = ['id', 'cliente', 'periodo', 'vencimiento', 'total', 'cobrado', 'saldo', 'promesa'];
  const sp = new URLSearchParams(window.location.search || '');
  buscar.value = sp.get('buscar') || '';
  const s = sp.get('sort');
  const d = sp.get('direction');
  if (s && allowedSort.includes(s)) sortBy.value = s;
  if (d === 'asc' || d === 'desc') sortDir.value = d;
  const p = parseInt(sp.get('page') || '1', 10);
  page.value = Number.isFinite(p) && p > 0 ? p : 1;
  (props.pfKeys || []).forEach((k) => {
    const v = sp.get(k);
    if (v != null && v !== '' && k in pf) pf[k] = v;
  });
}

function construirParamsApi() {
  const p = {
    page: page.value,
    sort: sortBy.value,
    direction: sortDir.value,
  };
  const b = (buscar.value || '').trim();
  if (b) p.buscar = b;
  Object.keys(pf).forEach((k) => {
    const v = (pf[k] || '').toString().trim();
    if (v !== '') p[k] = v;
  });
  return p;
}

function syncHistory() {
  const sp = new URLSearchParams();
  const b = (buscar.value || '').trim();
  if (b) sp.set('buscar', b);
  sp.set('sort', sortBy.value);
  sp.set('direction', sortDir.value);
  Object.keys(pf).forEach((k) => {
    const v = (pf[k] || '').toString().trim();
    if (v !== '') sp.set(k, v);
  });
  if (page.value > 1) sp.set('page', String(page.value));
  const qs = sp.toString();
  window.history.replaceState({}, '', window.location.pathname + (qs ? `?${qs}` : ''));
}

async function cargar() {
  if (!props.listUrl) {
    loadError.value = 'Falta configuración del listado.';
    loading.value = false;
    return;
  }
  loading.value = true;
  loadError.value = '';
  try {
    const { data } = await axios.get(props.listUrl, { params: construirParamsApi() });
    rows.value = data.data || [];
    meta.value = { ...meta.value, ...(data.meta || {}) };
    selectedIds.value = selectedIds.value.filter((id) => rows.value.some((r) => r.id === id));
    syncHistory();
  } catch (e) {
    loadError.value = e.response?.data?.message || e.message || 'No se pudo cargar el listado.';
    rows.value = [];
  } finally {
    loading.value = false;
  }
}

function toggleSort(col) {
  if (sortBy.value === col) {
    sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc';
  } else {
    sortBy.value = col;
    sortDir.value = 'desc';
  }
  page.value = 1;
  openFilterKey.value = null;
  cargar();
}

function aplicarBusqueda() {
  page.value = 1;
  cargar();
}

function limpiarTodo() {
  buscar.value = '';
  Object.keys(pf).forEach((k) => {
    pf[k] = '';
  });
  sortBy.value = 'vencimiento';
  sortDir.value = 'asc';
  page.value = 1;
  openFilterKey.value = null;
  cargar();
}

function aplicarFiltroDraft() {
  Object.keys(pf).forEach((k) => {
    pf[k] = draftPf[k];
  });
  openFilterKey.value = null;
  page.value = 1;
  cargar();
}

function limpiarFiltroCol(...keys) {
  keys.forEach((k) => {
    if (k in pf) pf[k] = '';
    if (k in draftPf) draftPf[k] = '';
  });
  openFilterKey.value = null;
  page.value = 1;
  cargar();
}

function irPagina(n) {
  if (n < 1 || n > meta.value.last_page) return;
  page.value = n;
  cargar();
}

function toggleRow(id, checked) {
  if (checked) {
    if (!selectedIds.value.includes(id)) selectedIds.value.push(id);
  } else {
    selectedIds.value = selectedIds.value.filter((i) => i !== id);
  }
}

function toggleSelectAll(checked) {
  if (checked) {
    selectedIds.value = rows.value.map((r) => r.id);
  } else {
    selectedIds.value = [];
  }
}

const exportHref = computed(() => {
  const sp = new URLSearchParams();
  const b = (buscar.value || '').trim();
  if (b) sp.set('buscar', b);
  Object.keys(pf).forEach((k) => {
    const v = (pf[k] || '').toString().trim();
    if (v !== '') sp.set(k, v);
  });
  sp.set('sort', sortBy.value);
  sp.set('direction', sortDir.value);
  const qs = sp.toString();
  const base = props.exportExcelUrl || '';
  return qs ? `${base}?${qs}` : base;
});

function tplReplace(tpl, id) {
  if (!tpl) return '#';
  return tpl.split('{id}').join(String(id));
}

function urlFacturaShow(id) {
  return tplReplace(props.templates.facturaShow, id);
}

function urlPromesaCreate(id) {
  return tplReplace(props.templates.promesaCreate, id);
}

function urlCobroCreate(clienteId, facturaId) {
  const raw = props.urls.cobrosCreate || '';
  const u = raw.startsWith('http') ? new URL(raw) : new URL(raw || '/cobros/create', window.location.href);
  u.searchParams.set('cliente_id', String(clienteId));
  u.searchParams.set('factura_interna_id', String(facturaId));
  return u.toString();
}

function fmtMonto(n, moneda) {
  const x = Math.round(Number(n) || 0);
  return `${x.toLocaleString('es-PY')} ${moneda || ''}`.trim();
}

function fmtFecha(ymd) {
  if (!ymd) return '—';
  const [y, m, d] = ymd.split('-');
  if (!y || !m || !d) return ymd;
  return `${d}/${m}/${y}`;
}

function fmtPeriodo(row) {
  if (!row.periodo_desde || !row.periodo_hasta) return '—';
  return `${fmtFecha(row.periodo_desde)} - ${fmtFecha(row.periodo_hasta)}`;
}

function abrirModalContacto(row) {
  const p = row?.contacto_cliente || {};
  modalContacto.nombre = p.nombre || '';
  modalContacto.cedula = p.cedula || '';
  modalContacto.celular = p.celular || '';
  modalContacto.email = p.email || '';
  modalContacto.direccion = p.direccion || '';
  modalContacto.url_ubicacion = p.url_ubicacion || '';
  let du = (p.detalle_url || '').trim();
  const cid = p.cliente_id ?? row?.cliente_id;
  if (!du && props.clienteDetalleTpl && props.canVerClienteDetalle && cid != null) {
    du = tplReplace(props.clienteDetalleTpl, cid);
  }
  modalContacto.detalle_url = du;
  modalContactoAbierto.value = true;
  document.body.classList.add('overflow-hidden');
}

function cerrarModalContacto() {
  modalContactoAbierto.value = false;
  document.body.classList.remove('overflow-hidden');
}

function onEsc(ev) {
  if (ev.key === 'Escape' && modalContactoAbierto.value) cerrarModalContacto();
}

onMounted(() => {
  leerParamsUrl();
  cargar();
  document.addEventListener('keydown', onEsc);
});

onUnmounted(() => {
  document.removeEventListener('keydown', onEsc);
  document.removeEventListener('click', onDocClick, true);
});
</script>
