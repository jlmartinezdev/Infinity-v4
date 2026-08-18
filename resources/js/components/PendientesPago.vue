<template>
  <div class="max-w-7xl mx-auto">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
      <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">Pendiente de pago</h1>
      <div class="flex items-center gap-2 flex-wrap">
        <a
          :href="exportHref"
          class="inline-flex items-center justify-center gap-2 px-3 py-2 border border-emerald-300 dark:border-emerald-700 rounded-lg text-sm font-medium text-emerald-900 dark:text-emerald-200 hover:bg-emerald-50 dark:hover:bg-emerald-900/30 transition-colors"
          title="Exportar listado actual a Excel (.xlsx)"
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
        <button
          v-if="mapPuntosUrl"
          type="button"
          class="inline-flex items-center justify-center gap-2 px-3 py-2 border border-sky-300 dark:border-sky-700 rounded-lg text-sm font-medium text-sky-900 dark:text-sky-200 hover:bg-sky-50 dark:hover:bg-sky-900/30 transition-colors"
          title="Clientes con saldo pendiente en el mapa (usa la URL o coordenadas de ubicación del cliente)"
          @click="abrirModalMapa"
        >
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0Z" />
          </svg>
          <span class="hidden sm:inline">Ver mapa</span>
        </button>
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
      Facturas internas con saldo pendiente, agrupadas por cliente (totales y saldos sumados). La búsqueda por nombre o cédula se aplica mientras escribe. Ordene por columna o use el embudo para filtrar.
      Un clic en los recuadros filtra la lista; otro clic vuelve a todas. Marque clientes para multicobro o envío masivo por WhatsApp.
    </p>

    <div v-if="esAdmin" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4" :class="deuda === 'todas' ? 'mb-6' : 'mb-3'">
      <button
        type="button"
        class="p-4 bg-white dark:bg-gray-800 rounded-xl shadow border text-left transition-shadow"
        :class="deuda === 'vencida' ? 'border-amber-400 ring-2 ring-amber-400/70' : 'border-gray-200 dark:border-gray-700 hover:border-amber-300 dark:hover:border-amber-600'"
        title="Filtrar clientes con facturas vencidas. Clic de nuevo para ver todas."
        @click="filtrarDeuda('vencida')"
      >
        <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Clientes con deuda</p>
        <p class="mt-1 text-2xl font-bold text-gray-900 dark:text-gray-100">{{ stats.clientes_vencidos }}</p>
        <p class="text-xs text-gray-500 dark:text-gray-500">{{ stats.facturas_vencidas }} factura(s) vencida(s)</p>
      </button>
      <button
        type="button"
        class="p-4 bg-white dark:bg-gray-800 rounded-xl shadow border text-left transition-shadow"
        :class="deuda === 'todas' ? 'border-green-400 ring-2 ring-green-400/60' : 'border-gray-200 dark:border-gray-700 hover:border-gray-400 dark:hover:border-gray-500'"
        title="Mostrar todas las facturas abiertas de esta vista"
        @click="filtrarDeuda('todas')"
      >
        <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Total facturado</p>
        <p class="mt-1 text-2xl font-bold text-gray-900 dark:text-gray-100">{{ fmtMonto(stats.monto_total, 'PYG') }}</p>
        <p class="text-xs text-gray-500 dark:text-gray-500">Facturas abiertas de esta vista</p>
      </button>
      <button
        type="button"
        class="p-4 bg-white dark:bg-gray-800 rounded-xl shadow border text-left transition-shadow"
        :class="deuda === 'vigente' ? 'border-cyan-400 ring-2 ring-cyan-400/70' : 'border-gray-200 dark:border-gray-700 hover:border-cyan-300 dark:hover:border-cyan-600'"
        title="Filtrar facturas aún en plazo. Clic de nuevo para ver todas."
        @click="filtrarDeuda('vigente')"
      >
        <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Total vigente</p>
        <p class="mt-1 text-2xl font-bold text-cyan-700 dark:text-cyan-400">{{ fmtMonto(stats.saldo_vigente, 'PYG') }}</p>
        <p class="text-xs text-gray-500 dark:text-gray-500">{{ stats.facturas_vigentes }} factura(s) aún en plazo</p>
      </button>
      <button
        type="button"
        class="p-4 bg-white dark:bg-gray-800 rounded-xl shadow border text-left transition-shadow"
        :class="deuda === 'vencida' ? 'border-amber-400 ring-2 ring-amber-400/70' : 'border-gray-200 dark:border-gray-700 hover:border-amber-300 dark:hover:border-amber-600'"
        title="Filtrar facturas vencidas. Clic de nuevo para ver todas."
        @click="filtrarDeuda('vencida')"
      >
        <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Total vencido</p>
        <p class="mt-1 text-2xl font-bold text-amber-700 dark:text-amber-400">{{ fmtMonto(stats.saldo_vencido, 'PYG') }}</p>
        <p class="text-xs text-gray-500 dark:text-gray-500">{{ notaVistaBadges }}</p>
      </button>
    </div>
    <p v-if="esAdmin && deuda !== 'todas'" class="mb-4 text-xs text-gray-500 dark:text-gray-400">
      Lista filtrada:
      <span class="font-medium text-gray-700 dark:text-gray-200">{{ deuda === 'vigente' ? 'solo facturas vigentes' : 'solo facturas vencidas' }}</span>.
      Clic de nuevo en el recuadro o en «Total facturado» para quitar el filtro.
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
              type="search"
              autocomplete="off"
              placeholder="Escribe para filtrar al instante…"
              class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-green-500 focus:ring-2 focus:ring-green-500/20 text-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 placeholder-gray-500 dark:placeholder-gray-400"
              @keyup.enter="aplicarBusqueda"
            />
          </div>
          <div class="shrink-0 min-w-[180px] sm:min-w-[210px]">
            <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-0.5">Vista</label>
            <select
              v-model="vista"
              class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-green-500 focus:ring-2 focus:ring-green-500/20 text-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100"
              @change="cambiarVista"
            >
              <option value="activos">Ocultar dados de baja</option>
              <option value="todos">Mostrar todos</option>
              <option value="bajas">Solo dados de baja</option>
            </select>
          </div>
          <div v-if="nodos.length" class="relative shrink-0 min-w-[150px] sm:min-w-[180px]" ref="dropdownNodoRef">
            <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-0.5">Nodo</label>
            <button
              type="button"
              class="w-full inline-flex items-center gap-1.5 px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-600/50 text-sm font-medium justify-between"
              @click="dropdownNodoOpen = !dropdownNodoOpen"
            >
              <span class="truncate">{{ nodoFiltroLabel }}</span>
              <svg class="w-4 h-4 text-gray-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
              </svg>
            </button>
            <div
              v-show="dropdownNodoOpen"
              class="absolute left-0 right-0 sm:right-auto sm:min-w-[200px] mt-1 max-h-64 overflow-y-auto py-1 bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 shadow-lg z-30"
            >
              <button
                type="button"
                class="w-full px-4 py-2.5 text-left text-sm hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors"
                :class="!nodoId ? 'text-green-700 dark:text-green-400 font-medium bg-green-50 dark:bg-green-900/30' : 'text-gray-700 dark:text-gray-300'"
                @click="seleccionarNodo('')"
              >
                Todos los nodos
              </button>
              <button
                v-for="n in nodos"
                :key="n.nodo_id"
                type="button"
                class="w-full px-4 py-2.5 text-left text-sm hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors truncate"
                :class="String(nodoId) === String(n.nodo_id) ? 'text-green-700 dark:text-green-400 font-medium bg-green-50 dark:bg-green-900/30' : 'text-gray-700 dark:text-gray-300'"
                @click="seleccionarNodo(String(n.nodo_id))"
              >
                {{ n.descripcion || ('Nodo #' + n.nodo_id) }}{{ n.tecnologias_etiqueta ? ' · ' + n.tecnologias_etiqueta : '' }}
              </button>
            </div>
          </div>
          <div class="flex items-end gap-2">
            <button type="button" class="px-4 py-2 bg-gray-700 dark:bg-gray-600 text-white rounded-lg font-medium hover:bg-gray-800 dark:hover:bg-gray-500 text-sm" title="Aplicar ya sin esperar" @click="aplicarBusqueda">
              Buscar
            </button>
            <button v-if="hayFiltros" type="button" class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 text-sm" @click="limpiarTodo">
              Limpiar todo
            </button>
          </div>
        </div>
      </div>

      <div class="p-4 border-b border-gray-200 dark:border-gray-700 bg-amber-50 dark:bg-amber-900/20">
        <div class="flex items-center gap-3 flex-wrap">
          <span class="text-sm text-gray-700 dark:text-gray-300">
            {{ selectedClienteCount }} cliente(s) · {{ selectedFacturaIds.length }} factura(s)
            <span v-if="haySeleccionFueraDePagina" class="text-xs text-gray-500 dark:text-gray-400"> (se mantienen al buscar)</span>
          </span>
          <button
            v-if="selectedClienteCount > 0"
            type="button"
            class="text-xs font-medium text-gray-600 dark:text-gray-300 underline underline-offset-2 hover:text-gray-900 dark:hover:text-gray-100"
            @click="limpiarSeleccion"
          >
            Quitar selección
          </button>
          <form v-if="canMulticobro && urls.multicobro" :action="urls.multicobro" method="GET" class="inline">
            <button type="submit" :disabled="selectedFacturaIds.length === 0" class="inline-flex items-center px-4 py-2 bg-amber-600 text-white rounded-lg font-medium hover:bg-amber-700 disabled:opacity-50 disabled:cursor-not-allowed text-sm">
              Registrar multicobro ({{ selectedFacturaIds.length }})
            </button>
            <template v-for="id in selectedFacturaIds" :key="'h-' + id">
              <input type="hidden" name="factura_interna_ids[]" :value="id" />
            </template>
          </form>
          <div class="flex items-center gap-2 flex-wrap sm:ml-auto">
            <label class="text-xs font-medium text-gray-600 dark:text-gray-300" for="wa-masivo-tipo">Tipo de aviso</label>
            <select
              id="wa-masivo-tipo"
              v-model="waMasivoTipo"
              class="px-2.5 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm text-gray-900 dark:text-gray-100"
            >
              <option value="aviso">Aviso de factura</option>
              <option value="reclamo">Reclamo de mora</option>
            </select>
            <button
              type="button"
              class="inline-flex items-center gap-1.5 px-4 py-2 bg-[#128C7E] text-white rounded-lg font-medium hover:bg-[#0e7368] disabled:opacity-50 disabled:cursor-not-allowed text-sm"
              :disabled="selectedClienteCount === 0 || waMasivoEnviando || !urls.whatsappMasivo"
              title="Enviar WhatsApp a los clientes marcados (número registrado)"
              @click="enviarWhatsappMasivo"
            >
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-4 h-4" aria-hidden="true">
                <path d="M12.04 2C6.58 2 2.15 6.43 2.15 11.89c0 1.96.52 3.86 1.5 5.54L2 22l4.71-1.55a9.86 9.86 0 0 0 5.33 1.54h.01c5.46 0 9.89-4.43 9.89-9.89C21.94 6.43 17.51 2 12.04 2Z" />
              </svg>
              WhatsApp ({{ selectedClienteCount }})
            </button>
          </div>
        </div>
        <label v-if="waMasivoTipo === 'reclamo'" class="mt-2 inline-flex items-center gap-2 text-xs text-gray-600 dark:text-gray-300">
          <input v-model="waMasivoForzar" type="checkbox" class="rounded border-gray-300 dark:border-gray-600 text-amber-600 focus:ring-amber-500" />
          Reenviar aunque ya se haya enviado un reclamo hoy
        </label>
      </div>

      <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
          <thead class="bg-gray-50 dark:bg-gray-700/50">
            <tr>
              <th class="px-4 py-3 w-10">
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
                  <button type="button" class="inline-flex items-center gap-1 flex-1 min-w-0 rounded px-0.5 py-0.5 hover:bg-gray-200 dark:hover:bg-gray-600 text-left" title="Ordenar por factura más antigua (mín. ID)" @click="toggleSort('id')">
                    <span>Facturas</span>
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
              <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase w-52">Acciones</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-200 dark:divide-gray-700 bg-white dark:bg-gray-800">
            <tr v-if="loading">
              <td :colspan="colspanTabla" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">Cargando…</td>
            </tr>
            <template v-else>
            <tr
              v-for="row in rows"
              :key="row.cliente_id"
              :class="row.cliente_dado_baja
                ? 'bg-gray-100 dark:bg-gray-700/50 text-gray-500 dark:text-gray-400'
                : 'hover:bg-gray-50 dark:hover:bg-gray-700/50'"
              :title="row.cliente_dado_baja ? 'Cliente inactivo o servicio dado de baja' : undefined"
            >
              <td class="px-4 py-3">
                <input
                  type="checkbox"
                  class="rounded border-gray-300 dark:border-gray-600 text-amber-600 focus:ring-amber-500"
                  :checked="isRowFullySelected(row)"
                  @change="toggleRowCliente(row, ($event.target).checked)"
                />
              </td>
              <td class="px-4 py-3 text-sm font-medium" :class="row.cliente_dado_baja ? '' : 'text-gray-900 dark:text-gray-100'">
                <span class="tabular-nums">{{ row.facturas_count ?? 1 }}</span>
                <span class="block text-xs font-normal text-gray-500 dark:text-gray-400">
                  <template v-if="(row.facturas_count ?? 1) > 1">#{{ row.min_factura_id }} +{{ (row.facturas_count ?? 1) - 1 }}</template>
                  <template v-else>#{{ row.min_factura_id }}</template>
                </span>
              </td>
              <td class="px-4 py-3 text-sm" :class="row.cliente_dado_baja ? '' : 'text-gray-900 dark:text-gray-100'">
                {{ row.cliente_nombre }}
                <span v-if="row.cliente_dado_baja" class="ml-1.5 inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium uppercase tracking-wide bg-gray-200 text-gray-600 dark:bg-gray-600 dark:text-gray-300">Baja</span>
              </td>
              <td class="px-4 py-3 text-sm" :class="row.cliente_dado_baja ? '' : 'text-gray-600 dark:text-gray-400'">{{ fmtPeriodo(row) }}</td>
              <td class="px-4 py-3 text-sm" :class="row.cliente_dado_baja ? '' : 'text-gray-600 dark:text-gray-400'">
                <template v-if="row.fecha_vencimiento">{{ fmtFecha(row.fecha_vencimiento) }}</template>
                <template v-else>—</template>
                <span
                  v-if="(row.facturas_count ?? 1) > 1 && row.fecha_vencimiento_max && row.fecha_vencimiento_max !== row.fecha_vencimiento"
                  class="block text-xs text-gray-500 dark:text-gray-500"
                >al {{ fmtFecha(row.fecha_vencimiento_max) }}</span>
              </td>
              <td class="px-4 py-3 text-sm text-right font-medium" :class="row.cliente_dado_baja ? '' : 'text-gray-900 dark:text-gray-100'">{{ fmtMonto(row.total, row.moneda) }}</td>
              <td class="px-4 py-3 text-sm text-right" :class="row.cliente_dado_baja ? '' : 'text-gray-600 dark:text-gray-400'">{{ fmtMonto(row.monto_pagado, row.moneda) }}</td>
              <td class="px-4 py-3 text-sm text-right font-semibold" :class="claseSaldoFila(row)">
                <template v-if="row.cliente_dado_baja">{{ fmtMonto(row.saldo_pendiente, row.moneda) }}</template>
                <template v-else-if="saldoFilaMixto(row)">
                  <span v-if="saldosFila(row).vigente > 0" class="block text-cyan-700 dark:text-cyan-400">{{ fmtMonto(saldosFila(row).vigente, row.moneda) }}</span>
                  <span v-if="saldosFila(row).vencido > 0" class="block text-amber-700 dark:text-amber-400">{{ fmtMonto(saldosFila(row).vencido, row.moneda) }}</span>
                </template>
                <template v-else>{{ fmtMonto(row.saldo_pendiente, row.moneda) }}</template>
              </td>
              <td class="px-4 py-3 text-sm" :class="row.cliente_dado_baja ? '' : 'text-gray-600 dark:text-gray-400'">
                <span v-if="row.promesa_label" class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-amber-100 text-amber-900 dark:bg-amber-900/40 dark:text-amber-200" title="Promesa de pago">
                  {{ row.promesa_label }}
                </span>
                <span v-else>—</span>
              </td>
              <td class="px-4 py-3 text-right">
                <div class="inline-flex items-center gap-0.5">
                  <button
                    type="button"
                    class="relative inline-flex items-center justify-center p-2 rounded-lg transition-colors"
                    :class="whatsappBtnClass(row)"
                    :title="(row.whatsapp && row.whatsapp.titulo) || 'WhatsApp'"
                    aria-label="WhatsApp de esta deuda"
                    @click="abrirModalWhatsapp(row)"
                  >
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-5 h-5" aria-hidden="true">
                      <path d="M12.04 2C6.58 2 2.15 6.43 2.15 11.89c0 1.96.52 3.86 1.5 5.54L2 22l4.71-1.55a9.86 9.86 0 0 0 5.33 1.54h.01c5.46 0 9.89-4.43 9.89-9.89C21.94 6.43 17.51 2 12.04 2Zm5.75 14.05c-.24.68-1.4 1.25-1.93 1.3-.49.04-1.1.06-1.78-.11-.41-.1-.94-.3-1.62-.59-2.85-1.23-4.7-4.1-4.84-4.29-.14-.19-1.15-1.53-1.15-2.92 0-1.39.73-2.07.99-2.36.26-.29.57-.36.76-.36h.54c.17 0 .41-.07.64.49.24.58.8 2 .87 2.14.07.15.12.32.02.51-.1.19-.14.32-.29.49-.14.17-.3.38-.43.51-.14.15-.29.31-.12.6.17.3.75 1.23 1.61 2 .1.86 2.03 1.79 2.36 1.92.32.14.51.12.7-.07.19-.19.8-.93 1.02-1.25.21-.32.43-.26.73-.16.3.1 1.89.89 2.21 1.05.32.17.54.25.62.39.07.14.07.82-.17 1.5Z" />
                    </svg>
                    <span
                      v-if="whatsappIcono(row) === 'ok'"
                      class="absolute -bottom-0.5 -right-0.5 flex h-3.5 w-3.5 items-center justify-center rounded-full bg-white dark:bg-gray-800 text-emerald-600"
                      aria-hidden="true"
                    >
                      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-3 h-3"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 0 1 .143 1.052l-8 10.5a.75.75 0 0 1-1.127.075l-4.5-4.5a.75.75 0 0 1 1.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 0 1 1.05-.143Z" clip-rule="evenodd" /></svg>
                    </span>
                    <span
                      v-else-if="whatsappIcono(row) === 'pendiente'"
                      class="absolute -bottom-0.5 -right-0.5 flex h-3.5 w-3.5 items-center justify-center rounded-full bg-white dark:bg-gray-800 text-amber-500"
                      aria-hidden="true"
                    >
                      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-3 h-3"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
                    </span>
                    <span
                      v-else
                      class="absolute -bottom-0.5 -right-0.5 flex h-3.5 w-3.5 items-center justify-center rounded-full bg-white dark:bg-gray-800 text-red-600"
                      aria-hidden="true"
                    >
                      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-3 h-3"><path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.166-.168 2.63-1.516 2.63H3.72c-1.347 0-2.189-1.464-1.515-2.63L8.485 2.495ZM10 6a.75.75 0 0 1 .75.75v3.5a.75.75 0 0 1-1.5 0v-3.5A.75.75 0 0 1 10 6Zm0 9a1 1 0 1 0 0-2 1 1 0 0 0 0 2Z" clip-rule="evenodd" /></svg>
                    </span>
                  </button>
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
                  <button
                    type="button"
                    class="inline-flex items-center justify-center p-2 rounded-lg text-purple-600 hover:bg-purple-50 dark:text-purple-400 dark:hover:bg-purple-900/30 transition-colors"
                    title="Ver facturas pendientes de este cliente"
                    aria-label="Ver facturas pendientes de este cliente"
                    @click="abrirModalFacturasCliente(row)"
                  >
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
                      <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0z" />
                    </svg>
                  </button>
                  <a v-if="canCrearCobro" :href="urlPromesaCreate(row.min_factura_id)" class="inline-flex items-center justify-center p-2 rounded-lg text-amber-600 hover:bg-amber-50 dark:text-amber-400 dark:hover:bg-amber-900/30 transition-colors" title="Registrar promesa (factura más antigua)" aria-label="Registrar promesa de pago">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" />
                    </svg>
                  </a>
                  <a v-if="canCrearCobro && urls.cobrosCreate" :href="urlCobroCreate(row.cliente_id, row.min_factura_id)" class="inline-flex items-center justify-center p-2 rounded-lg text-green-600 hover:bg-green-50 dark:text-green-400 dark:hover:bg-green-900/30 transition-colors" title="Registrar cobro (factura más antigua)" aria-label="Registrar cobro">
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
    <div v-show="waMasivoAbierto" class="fixed inset-0 z-50 overflow-y-auto" role="dialog" aria-modal="true">
      <div class="fixed inset-0 bg-black/50 transition-opacity" aria-hidden="true" @click="cerrarWaMasivo" />
      <div class="flex min-h-full items-center justify-center p-4">
        <div class="relative w-full max-w-lg rounded-xl bg-white dark:bg-gray-800 shadow-xl border border-gray-200 dark:border-gray-700" @click.stop>
          <div class="flex items-center justify-between gap-3 px-4 py-3 border-b border-gray-200 dark:border-gray-700">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Envío masivo WhatsApp</h2>
            <button type="button" class="p-2 rounded-lg text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-700 dark:text-gray-400" aria-label="Cerrar" :disabled="waMasivoEnviando" @click="cerrarWaMasivo">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
            </button>
          </div>
          <div class="px-4 py-4 space-y-3 text-sm">
            <p v-if="waMasivoEnviando" class="text-gray-700 dark:text-gray-200">Enviando… puede tardar unos segundos.</p>
            <p v-if="waMasivoResultado?.message" :class="waMasivoResultado.ok ? 'text-emerald-700 dark:text-emerald-300' : 'text-amber-800 dark:text-amber-200'">
              {{ waMasivoResultado.message }}
            </p>
            <ul v-if="(waMasivoResultado?.resultados || []).length" class="max-h-72 overflow-y-auto divide-y divide-gray-100 dark:divide-gray-700 rounded-lg border border-gray-200 dark:border-gray-700">
              <li
                v-for="r in waMasivoResultado.resultados"
                :key="r.cliente_id"
                class="px-3 py-2"
              >
                <p class="font-medium" :class="r.ok ? 'text-gray-900 dark:text-gray-100' : 'text-gray-800 dark:text-gray-200'">{{ r.nombre }}</p>
                <p class="text-xs mt-0.5" :class="r.ok ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400'">{{ r.message }}</p>
              </li>
            </ul>
          </div>
          <div class="px-4 py-3 border-t border-gray-200 dark:border-gray-700 flex justify-end">
            <button
              type="button"
              class="px-4 py-2 rounded-lg bg-gray-200 dark:bg-gray-600 text-gray-800 dark:text-gray-200 text-sm font-medium hover:bg-gray-300 dark:hover:bg-gray-500 disabled:opacity-50"
              :disabled="waMasivoEnviando"
              @click="cerrarWaMasivo"
            >
              Cerrar
            </button>
          </div>
        </div>
      </div>
    </div>

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

    <!-- Modal facturas pendientes del cliente -->
    <div v-show="modalFacturasAbierto" class="fixed inset-0 z-50 overflow-y-auto" role="dialog" aria-modal="true">
      <div class="fixed inset-0 bg-black/50 transition-opacity" aria-hidden="true" @click="cerrarModalFacturasCliente" />
      <div class="flex min-h-full items-center justify-center p-4">
        <div class="relative w-full max-w-3xl rounded-xl bg-white dark:bg-gray-800 shadow-xl border border-gray-200 dark:border-gray-700" @click.stop>
          <div class="flex flex-wrap items-center justify-between gap-3 px-4 py-3 border-b border-gray-200 dark:border-gray-700">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Facturas pendientes</h2>
            <div class="flex items-center gap-2 flex-wrap">
              <a
                v-if="modalFacturasClienteId != null && templates.pdfPendientesCliente"
                :href="urlPdfCliente(modalFacturasClienteId)"
                target="_blank"
                rel="noopener noreferrer"
                class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg text-sm font-medium bg-red-600 text-white hover:bg-red-700 transition-colors"
              >
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" /></svg>
                PDF
              </a>
              <button type="button" class="p-2 rounded-lg text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-700 dark:text-gray-400" aria-label="Cerrar" @click="cerrarModalFacturasCliente">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
              </button>
            </div>
          </div>
          <div class="px-4 py-3 text-sm text-gray-600 dark:text-gray-300 border-b border-gray-200 dark:border-gray-700">
            <span class="font-medium text-gray-900 dark:text-gray-100">{{ modalFacturasClienteNombre || '—' }}</span>
            <span v-if="modalFacturasLineas.length" class="text-gray-500 dark:text-gray-400"> · {{ modalFacturasLineas.length }} factura(s)</span>
          </div>
          <div class="px-4 py-3 max-h-[min(70vh,520px)] overflow-y-auto">
            <table class="min-w-full text-sm divide-y divide-gray-200 dark:divide-gray-700">
              <thead class="bg-gray-50 dark:bg-gray-700/50 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">
                <tr>
                  <th class="px-3 py-2">#</th>
                  <th class="px-3 py-2">Período</th>
                  <th class="px-3 py-2">Venc.</th>
                  <th class="px-3 py-2 text-right">Total</th>
                  <th class="px-3 py-2 text-right">Cobrado</th>
                  <th class="px-3 py-2 text-right">Saldo</th>
                  <th class="px-3 py-2">Promesa</th>
                  <th class="px-3 py-2 text-right w-36">Acciones</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                <tr v-for="f in modalFacturasLineas" :key="f.id">
                  <td class="px-3 py-2 font-medium text-gray-900 dark:text-gray-100">{{ f.id }}</td>
                  <td class="px-3 py-2 text-gray-600 dark:text-gray-400">{{ fmtPeriodo(f) }}</td>
                  <td class="px-3 py-2 text-gray-600 dark:text-gray-400">{{ f.fecha_vencimiento ? fmtFecha(f.fecha_vencimiento) : '—' }}</td>
                  <td class="px-3 py-2 text-right text-gray-900 dark:text-gray-100">{{ fmtMonto(f.total, f.moneda) }}</td>
                  <td class="px-3 py-2 text-right text-gray-600 dark:text-gray-400">{{ fmtMonto(f.monto_pagado, f.moneda) }}</td>
                  <td class="px-3 py-2 text-right font-semibold" :class="claseSaldoFactura(f)">{{ fmtMonto(f.saldo_pendiente, f.moneda) }}</td>
                  <td class="px-3 py-2 text-gray-600 dark:text-gray-400 text-xs">{{ f.promesa_label || '—' }}</td>
                  <td class="px-3 py-2 text-right">
                    <div class="inline-flex items-center gap-0.5 justify-end">
                      <a :href="urlFacturaShow(f.id)" class="inline-flex p-1.5 rounded text-purple-600 hover:bg-purple-50 dark:text-purple-400 dark:hover:bg-purple-900/30" title="Ver factura" aria-label="Ver factura">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0z" /></svg>
                      </a>
                      <a v-if="canCrearCobro" :href="urlPromesaCreate(f.id)" class="inline-flex p-1.5 rounded text-amber-600 hover:bg-amber-50 dark:text-amber-400 dark:hover:bg-amber-900/30" title="Promesa" aria-label="Promesa">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" /></svg>
                      </a>
                      <a v-if="canCrearCobro && urls.cobrosCreate && modalFacturasClienteId != null" :href="urlCobroCreate(modalFacturasClienteId, f.id)" class="inline-flex p-1.5 rounded text-green-600 hover:bg-green-50 dark:text-green-400 dark:hover:bg-green-900/30" title="Cobro" aria-label="Cobro">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                      </a>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
          <div class="px-4 py-3 border-t border-gray-200 dark:border-gray-700 flex justify-end">
            <button type="button" class="px-4 py-2 rounded-lg bg-gray-200 dark:bg-gray-600 text-gray-800 dark:text-gray-200 text-sm font-medium hover:bg-gray-300 dark:hover:bg-gray-500" @click="cerrarModalFacturasCliente">Cerrar</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Modal WhatsApp: aviso de factura / reclamo de mora -->
    <div v-show="modalWaAbierto" class="fixed inset-0 z-[60] overflow-y-auto" role="dialog" aria-modal="true" aria-labelledby="pendientes-wa-titulo">
      <div class="fixed inset-0 bg-black/50 transition-opacity" aria-hidden="true" @click="cerrarModalWhatsapp" />
      <div class="flex min-h-full items-center justify-center p-4">
        <div class="relative w-full max-w-3xl max-h-[92vh] flex flex-col rounded-xl bg-white dark:bg-gray-800 shadow-xl border border-gray-200 dark:border-gray-700 overflow-hidden" @click.stop>
          <div class="flex items-center justify-between gap-3 px-4 py-2.5 border-b border-gray-200 dark:border-gray-700 shrink-0">
            <div class="min-w-0">
              <h2 id="pendientes-wa-titulo" class="text-lg font-semibold text-gray-900 dark:text-gray-100 leading-tight">WhatsApp</h2>
              <p class="text-xs text-gray-500 dark:text-gray-400 truncate">{{ waDetalle?.cliente?.nombre || '—' }}</p>
            </div>
            <button type="button" class="p-2 rounded-lg text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-700 dark:text-gray-400" aria-label="Cerrar" @click="cerrarModalWhatsapp">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
            </button>
          </div>

          <div v-if="waCargando" class="px-4 py-8 text-center text-sm text-gray-500 dark:text-gray-400">Cargando historial…</div>
          <div v-else-if="waError" class="m-4 p-3 rounded-lg bg-red-100 dark:bg-red-900/20 text-red-800 dark:text-red-200 border border-red-200 dark:border-red-800 text-sm flex items-start justify-between gap-2">
            <span>{{ waError }}</span>
            <button type="button" class="shrink-0 p-0.5 rounded hover:bg-black/10" aria-label="Ocultar" @click="waError = ''">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
            </button>
          </div>
          <template v-else-if="waDetalle">
            <div class="px-4 py-2.5 shrink-0 space-y-2 border-b border-gray-200 dark:border-gray-700">
              <div v-if="waResultado" class="flex items-start gap-2 p-2 rounded-lg text-xs border" :class="waResultadoOk ? 'bg-green-100 dark:bg-green-900/20 text-green-800 dark:text-green-200 border-green-200 dark:border-green-800' : 'bg-red-100 dark:bg-red-900/20 text-red-800 dark:text-red-200 border-red-200 dark:border-red-800'">
                <p class="flex-1 min-w-0">{{ waResultado }}</p>
                <button type="button" class="shrink-0 p-0.5 rounded hover:bg-black/10" aria-label="Ocultar aviso" @click="waResultado = ''">
                  <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
                </button>
              </div>

              <div class="flex flex-wrap gap-2">
                <button
                  type="button"
                  class="inline-flex items-center gap-2 rounded-full border px-3 py-1.5 text-left transition-colors"
                  :class="chipFiltroWaClass('aviso', waDetalle.aviso)"
                  :title="'Filtrar avisos de factura'"
                  @click="toggleFiltroWa('aviso')"
                >
                  <span class="text-[11px] font-semibold uppercase tracking-wide opacity-70">Aviso</span>
                  <span class="text-sm font-medium">{{ etiquetaEstadoWa(waDetalle.aviso) }}</span>
                  <span v-if="waDetalle.aviso?.created_at" class="text-[11px] opacity-60">{{ horaCortaWa(waDetalle.aviso.created_at) }}</span>
                </button>
                <button
                  type="button"
                  class="inline-flex items-center gap-2 rounded-full border px-3 py-1.5 text-left transition-colors"
                  :class="chipFiltroWaClass('reclamo', waDetalle.reclamo)"
                  :title="waDetalle.tiene_vencida ? 'Filtrar reclamos de mora' : 'Sin facturas vencidas'"
                  @click="toggleFiltroWa('reclamo')"
                >
                  <span class="text-[11px] font-semibold uppercase tracking-wide opacity-70">Reclamo</span>
                  <span class="text-sm font-medium">{{ etiquetaEstadoWa(waDetalle.reclamo) }}</span>
                  <span v-if="waDetalle.reclamo?.created_at" class="text-[11px] opacity-60">{{ horaCortaWa(waDetalle.reclamo.created_at) }}</span>
                </button>
              </div>

              <div class="space-y-2">
                <p class="text-[11px] font-medium uppercase text-gray-500 dark:text-gray-400">Enviar a</p>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                  <label
                    class="flex cursor-pointer items-start gap-2.5 rounded-lg border p-2.5"
                    :class="[
                      waDestino === 'registrado' ? 'border-[#00a884] ring-1 ring-[#00a884]' : 'border-gray-200 dark:border-gray-600',
                      !waTelRegistrado ? 'opacity-50 cursor-not-allowed' : '',
                    ]"
                  >
                    <input
                      v-model="waDestino"
                      type="radio"
                      value="registrado"
                      class="mt-0.5 text-emerald-600 focus:ring-emerald-500"
                      :disabled="!waTelRegistrado"
                    >
                    <span class="min-w-0">
                      <span class="block text-sm font-medium text-gray-900 dark:text-gray-100">Número registrado</span>
                      <span class="block font-mono text-xs text-gray-500 dark:text-gray-400 truncate">{{ waTelRegistrado || 'Sin teléfono en el cliente' }}</span>
                    </span>
                  </label>
                  <label
                    class="flex cursor-pointer items-start gap-2.5 rounded-lg border p-2.5"
                    :class="waDestino === 'otro' ? 'border-[#00a884] ring-1 ring-[#00a884]' : 'border-gray-200 dark:border-gray-600'"
                  >
                    <input v-model="waDestino" type="radio" value="otro" class="mt-0.5 text-emerald-600 focus:ring-emerald-500">
                    <span class="min-w-0 flex-1">
                      <span class="block text-sm font-medium text-gray-900 dark:text-gray-100">Otro número</span>
                      <span class="block text-[11px] text-gray-500 dark:text-gray-400">Para probar o si el del cliente no sirve</span>
                    </span>
                  </label>
                </div>
                <div v-if="waDestino === 'otro'" class="sm:pl-1 space-y-1.5">
                  <input
                    v-model="waTelefonoOtro"
                    type="text"
                    maxlength="40"
                    class="w-full px-3 py-1.5 text-sm rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100"
                    placeholder="Ej: 0981 123 456"
                  >
                  <label class="inline-flex items-center gap-2 text-xs text-gray-600 dark:text-gray-300">
                    <input v-model="waGuardarTel" type="checkbox" class="rounded border-gray-300" />
                    Guardar este número en la ficha del cliente
                  </label>
                </div>
              </div>

              <div v-if="(waDetalle.facturas || []).length">
                <label class="text-[11px] font-medium uppercase text-gray-500 dark:text-gray-400">Factura</label>
                <select v-model="waFacturaId" class="mt-0.5 w-full px-3 py-1.5 text-sm rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                  <option v-for="f in waDetalle.facturas" :key="f.id" :value="f.id">
                    #{{ f.id }} · {{ f.fecha_vencimiento ? fmtFecha(f.fecha_vencimiento) : '—' }} · {{ fmtMonto(f.saldo_pendiente, f.moneda) }}{{ f.vencida ? ' · vencida' : '' }}
                  </option>
                </select>
              </div>
              <div v-if="waDetalle.tiene_vencida && !waDetalle.plantilla_reclamo" class="rounded-lg border border-amber-300 dark:border-amber-700 bg-amber-50 dark:bg-amber-900/20 px-3 py-2 text-xs text-amber-900 dark:text-amber-200">
                Sin plantilla Meta de reclamo: el mensaje sale como texto y <strong>no llega</strong> si el número no escribió en las últimas 24 h. Hay que crear <code class="text-[11px]">factura_reclamo_mora</code> y poner <code class="text-[11px]">WHATSAPP_TEMPLATE_FACTURA_RECLAMO</code> en el .env.
              </div>
              <div v-if="waDetalle.tiene_vencida" class="flex flex-wrap items-center gap-x-4 gap-y-1">
                <label class="inline-flex items-center gap-2 text-xs text-gray-600 dark:text-gray-300">
                  <input v-model="waAdjuntarPdf" type="checkbox" class="rounded border-gray-300" />
                  Link PDF en el reclamo
                </label>
                <button
                  type="button"
                  class="text-xs font-medium text-amber-700 dark:text-amber-300 hover:underline"
                  @click="waMostrarPreview = !waMostrarPreview"
                >
                  {{ waMostrarPreview ? 'Ocultar preview' : 'Ver preview del reclamo' }}
                </button>
              </div>

              <div v-if="waDetalle.tiene_vencida && waMostrarPreview" class="rounded-lg bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 p-2.5 space-y-1">
                <pre class="whitespace-pre-wrap text-xs text-gray-800 dark:text-gray-200 font-sans">{{ waDetalle.preview_reclamo }}</pre>
                <p class="text-xs text-gray-600 dark:text-gray-400">
                  Vencido {{ fmtMonto(waDetalle.saldo_vencido, 'PYG') }}
                  <template v-if="waDetalle.saldo_vigente > 0"> · vigente {{ fmtMonto(waDetalle.saldo_vigente, 'PYG') }}</template>
                  <a v-if="waDetalle.pdf_url" :href="waDetalle.pdf_url" target="_blank" rel="noopener noreferrer" class="ml-2 text-sky-600 dark:text-sky-400 hover:underline">Probar PDF</a>
                </p>
              </div>
            </div>

            <div class="wa-app flex-1 min-h-[320px] flex flex-col">
              <div class="wa-header px-4 py-1 text-[11px] flex items-center justify-between gap-2 shrink-0">
                <span class="wa-muted">{{ etiquetaFiltroWa }}</span>
                <span v-if="waEntradasCount" class="inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-semibold bg-[#25d366] text-white">{{ waEntradasCount }} respuesta{{ waEntradasCount === 1 ? '' : 's' }}</span>
              </div>
              <div ref="waHiloEl" class="wa-wallpaper flex-1 space-y-0.5 overflow-y-auto px-3 py-3 sm:px-8 min-h-[280px] max-h-[min(52vh,520px)]">
                <template v-for="(m, idx) in waHistorialFiltrado" :key="m.id">
                  <div v-if="mostrarDiaWa(m, idx)" class="my-3 flex justify-center">
                    <span class="wa-day rounded-lg px-3 py-1 text-[12px] shadow">{{ m.dia_label || m.created_at }}</span>
                  </div>
                  <div class="flex" :class="m.direccion === 'salida' ? 'justify-end' : 'justify-start'">
                    <div
                      class="wa-bubble relative max-w-[88%] px-2.5 py-1.5 shadow-sm sm:max-w-[75%]"
                      :class="m.direccion === 'salida' ? 'wa-bubble-out' : 'wa-bubble-in'"
                    >
                      <div v-if="m.direccion === 'entrada'" class="wa-accent mb-0.5 text-[10px] font-semibold opacity-90">Cliente</div>
                      <div v-else-if="etiquetaContextoWa(m)" class="mb-0.5 text-[10px] uppercase tracking-wide opacity-70">{{ etiquetaContextoWa(m) }}</div>
                      <div class="wa-msg-body whitespace-pre-wrap break-words text-[14.2px] leading-[19px]">{{ m.cuerpo || '—' }}</div>
                      <div
                        v-if="mostrarFalloWa(m)"
                        class="mt-1.5 rounded-md bg-rose-500/15 px-2 py-1 text-[11px] leading-snug text-rose-800 dark:text-rose-100"
                      >
                        <div class="flex items-start gap-2">
                          <p class="flex-1 min-w-0">
                            <span class="font-medium">No enviado</span>
                            <template v-if="m.fallo?.codigo"> · {{ m.fallo.codigo }}</template>
                            <template v-if="falloEsRecienteWa(m) && (m.fallo?.mensaje || m.fallo?.tip)">
                              <span class="block mt-0.5 opacity-90">{{ m.fallo.mensaje || m.fallo.titulo }}</span>
                              <span v-if="m.fallo.tip" class="block mt-0.5 opacity-80">{{ m.fallo.tip }}</span>
                            </template>
                          </p>
                          <button
                            type="button"
                            class="shrink-0 p-0.5 rounded hover:bg-black/10 dark:hover:bg-white/10"
                            title="Ocultar este error"
                            aria-label="Ocultar error"
                            @click.stop="ocultarFalloWa(m.id)"
                          >
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-3.5 h-3.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
                          </button>
                        </div>
                      </div>
                      <div class="wa-muted mt-0.5 flex items-center justify-end gap-1.5 text-[11px]">
                        <span>{{ m.hora || m.created_at }}</span>
                        <span
                          v-if="m.direccion === 'salida'"
                          :title="m.estado"
                          class="text-[14px] leading-none"
                          :class="m.estado === 'leido' ? 'wa-ticks-read' : (m.estado === 'fallido' ? 'text-rose-500' : '')"
                        >{{ ticksWa(m.estado) }}</span>
                      </div>
                    </div>
                  </div>
                </template>
                <div v-if="!waHistorialFiltrado.length" class="wa-muted py-12 text-center text-sm">
                  {{ waFiltro === 'todos' ? 'Sin mensajes con este número.' : 'No hay mensajes en este filtro.' }}
                </div>
              </div>
            </div>
          </template>

          <div class="px-4 py-3 border-t border-gray-200 dark:border-gray-700 flex flex-wrap items-center justify-end gap-2 shrink-0">
            <button type="button" class="px-4 py-2 rounded-lg bg-gray-200 dark:bg-gray-600 text-gray-800 dark:text-gray-200 text-sm font-medium hover:bg-gray-300 dark:hover:bg-gray-500" @click="cerrarModalWhatsapp">Cerrar</button>
            <button
              type="button"
              class="px-4 py-2 rounded-lg bg-sky-600 text-white text-sm font-medium hover:bg-sky-700 disabled:opacity-50"
              :disabled="waEnviando || waCargando || !waFacturaId || !waPuedeEnviar"
              @click="enviarAvisoWa"
            >
              {{ waEnviando ? 'Enviando…' : 'Reenviar aviso' }}
            </button>
            <button
              v-if="waDetalle?.tiene_vencida"
              type="button"
              class="px-4 py-2 rounded-lg bg-emerald-600 text-white text-sm font-medium hover:bg-emerald-700 disabled:opacity-50"
              :disabled="waEnviando || waCargando || !waPuedeEnviar"
              @click="enviarReclamoWa(false)"
            >
              {{ waEnviando ? 'Enviando…' : 'Enviar reclamo' }}
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Modal mapa Google: clientes con saldo pendiente (mismos filtros que la tabla) -->
    <div v-show="modalMapaAbierto" class="fixed inset-0 z-[55] overflow-y-auto" role="dialog" aria-modal="true" aria-labelledby="pendientes-mapa-titulo">
      <div class="fixed inset-0 bg-black/50 transition-opacity" aria-hidden="true" @click="cerrarModalMapa" />
      <div class="flex min-h-full items-center justify-center p-4">
        <div class="relative w-full max-w-5xl rounded-xl bg-white dark:bg-gray-800 shadow-xl border border-gray-200 dark:border-gray-700 overflow-hidden" @click.stop>
          <div class="flex flex-wrap items-center justify-between gap-3 px-4 py-3 border-b border-gray-200 dark:border-gray-700">
            <div>
              <h2 id="pendientes-mapa-titulo" class="text-lg font-semibold text-gray-900 dark:text-gray-100">Mapa · pendientes de pago</h2>
              <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                Respetan los filtros y la búsqueda actuales.
                <template v-if="mapaStats.total_clientes > 0">
                  {{ mapaStats.con_coordenadas }} con pin /
                  {{ mapaStats.total_clientes }} cliente(s) con deuda
                  <span v-if="mapaStats.sin_coordenadas > 0"> · {{ mapaStats.sin_coordenadas }} sin coordenadas en «URL ubicación»</span>
                </template>
              </p>
            </div>
            <button type="button" class="p-2 rounded-lg text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-700 dark:text-gray-400" aria-label="Cerrar mapa" @click="cerrarModalMapa">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
            </button>
          </div>
          <div v-if="mapaErrorMapa" class="mx-4 mt-3 p-3 rounded-lg bg-red-100 dark:bg-red-900/20 text-red-800 dark:text-red-200 text-sm border border-red-200 dark:border-red-800">
            {{ mapaErrorMapa }}
          </div>
          <div v-if="!googleMapsApiKey" class="m-4 p-6 rounded-lg bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 text-sm text-amber-900 dark:text-amber-200 text-center">
            Configurá <code class="text-xs bg-amber-100 dark:bg-amber-900/40 px-1 rounded">GOOGLE_MAPS_API_KEY</code> en el archivo <code class="text-xs">.env</code> para cargar el mapa.
          </div>
          <div v-else class="relative w-full h-[min(72vh,620px)] min-h-[320px] bg-gray-100 dark:bg-gray-900">
            <div ref="mapaPendientesContainer" class="absolute inset-0 w-full h-full" />
            <div
              v-if="mapaCargandoPuntos"
              class="absolute inset-0 flex items-center justify-center bg-white/80 dark:bg-gray-900/80 z-10"
            >
              <p class="text-sm font-medium text-gray-700 dark:text-gray-300">Cargando clientes y mapa…</p>
            </div>
          </div>
          <div class="px-4 py-3 border-t border-gray-200 dark:border-gray-700 flex justify-end">
            <button type="button" class="px-4 py-2 rounded-lg bg-gray-200 dark:bg-gray-600 text-gray-800 dark:text-gray-200 text-sm font-medium hover:bg-gray-300 dark:hover:bg-gray-500" @click="cerrarModalMapa">Cerrar</button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, computed, watch, onMounted, onUnmounted, nextTick } from 'vue';
import axios from 'axios';

const props = defineProps({
  listUrl: { type: String, default: '' },
  mapPuntosUrl: { type: String, default: '' },
  googleMapsApiKey: { type: String, default: '' },
  exportExcelUrl: { type: String, default: '' },
  pfKeys: { type: Array, default: () => [] },
  urls: { type: Object, default: () => ({}) },
  templates: { type: Object, default: () => ({}) },
  clienteDetalleTpl: { type: String, default: '' },
  canMulticobro: { type: Boolean, default: false },
  canCrearCobro: { type: Boolean, default: false },
  canVerClienteDetalle: { type: Boolean, default: false },
  esAdmin: { type: Boolean, default: false },
  flashSuccess: { type: String, default: '' },
  flashError: { type: String, default: '' },
  nodos: { type: Array, default: () => [] },
});

const BUSCAR_DEBOUNCE_MS = 400;

const buscar = ref('');
const vista = ref('activos');
const deuda = ref('todas');
const nodoId = ref('');
const dropdownNodoOpen = ref(false);
const dropdownNodoRef = ref(null);
/** Evita disparar búsqueda incremental al hidratar desde la URL o al limpiar con cargar() explícito. */
const skipBuscarWatch = ref(true);
let buscarDebounceTimer = null;

const nodoFiltroLabel = computed(() => {
  if (!nodoId.value) return 'Todos los nodos';
  const n = props.nodos.find((x) => String(x.nodo_id) === String(nodoId.value));
  return n ? (n.descripcion || `Nodo #${n.nodo_id}`) : 'Nodo';
});

const notaVistaBadges = computed(() => {
  if (vista.value === 'bajas') return 'Vista: solo dados de baja';
  if (vista.value === 'todos') return 'Vista: incluye dados de baja';
  return 'Ya pasó la fecha de vencimiento';
});

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
const stats = ref({
  cantidad_facturas: 0,
  cantidad_clientes: 0,
  monto_total: 0,
  monto_cobrado: 0,
  monto_saldo: 0,
  saldo_vigente: 0,
  saldo_vencido: 0,
  facturas_vencidas: 0,
  clientes_vencidos: 0,
  facturas_vigentes: 0,
});
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

/** Selección persistente por cliente (sobrevive búsqueda, paginación y filtros). */
const selectedByCliente = ref({});
const openFilterKey = ref(null);

const waMasivoTipo = ref('aviso');
const waMasivoForzar = ref(false);
const waMasivoEnviando = ref(false);
const waMasivoAbierto = ref(false);
const waMasivoResultado = ref(null);

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

const modalFacturasAbierto = ref(false);
const modalFacturasClienteId = ref(null);
const modalFacturasClienteNombre = ref('');
const modalFacturasLineas = ref([]);

const modalWaAbierto = ref(false);
const waCargando = ref(false);
const waEnviando = ref(false);
const waError = ref('');
const waResultado = ref('');
const waResultadoOk = ref(false);
const waDetalle = ref(null);
const waTelefono = ref('');
const waTelefonoOtro = ref('');
const waDestino = ref('registrado');
const waGuardarTel = ref(false);
const waAdjuntarPdf = ref(true);
const waFacturaId = ref(null);
const waClienteId = ref(null);
const waFiltro = ref('todos');
const waHiloEl = ref(null);
const waMostrarPreview = ref(false);
const waErroresOcultos = ref(new Set());
const WA_ERRORES_LS = 'pendientes_wa_errores_ocultos';

const waTelRegistrado = computed(() => (waDetalle.value?.cliente?.telefono || '').trim());
const waPuedeEnviar = computed(() => {
  if (waDestino.value === 'otro') return (waTelefonoOtro.value || '').trim() !== '';
  return waTelRegistrado.value !== '';
});

const modalMapaAbierto = ref(false);
const mapaPendientesContainer = ref(null);
const mapaCargandoPuntos = ref(false);
const mapaErrorMapa = ref('');
const mapaStats = ref({
  total_clientes: 0,
  con_coordenadas: 0,
  sin_coordenadas: 0,
});
const mapaPuntosData = ref([]);

let pendMapInstance = null;
let pendMapMarkers = [];
let pendMapInfoWindow = null;

const colspanTabla = computed(() => 10);

function snapshotCliente(row) {
  return {
    cliente_id: row.cliente_id,
    factura_id: row.min_factura_id,
    nombre: row.cliente_nombre,
    tiene_vencida: !!row.whatsapp?.tiene_vencida,
    factura_ids: [...(row.factura_ids || [])],
  };
}

function isRowFullySelected(row) {
  return row?.cliente_id != null && Object.prototype.hasOwnProperty.call(selectedByCliente.value, row.cliente_id);
}

const allSelected = computed(() => rows.value.length > 0 && rows.value.every((r) => isRowFullySelected(r)));

const selectedClienteItems = computed(() => Object.values(selectedByCliente.value));

const selectedClienteCount = computed(() => selectedClienteItems.value.length);

const selectedFacturaIds = computed(() => {
  const ids = [];
  selectedClienteItems.value.forEach((c) => {
    (c.factura_ids || []).forEach((id) => {
      if (!ids.includes(id)) ids.push(id);
    });
  });
  return ids;
});

const haySeleccionFueraDePagina = computed(() => {
  if (!selectedClienteCount.value) return false;
  const enPagina = rows.value.filter((r) => isRowFullySelected(r)).length;
  return selectedClienteCount.value > enPagina;
});

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
  if ((nodoId.value || '').trim() !== '') return true;
  if (vista.value !== 'activos') return true;
  if (deuda.value && deuda.value !== 'todas') return true;
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

function onDocClickNodo(ev) {
  const t = ev.target;
  if (dropdownNodoRef.value?.contains(t)) return;
  dropdownNodoOpen.value = false;
}

watch(openFilterKey, (v) => {
  if (v != null) {
    syncDraftFromPf();
    setTimeout(() => document.addEventListener('click', onDocClick, true), 0);
  } else {
    document.removeEventListener('click', onDocClick, true);
  }
});

function clearBuscarDebounce() {
  if (buscarDebounceTimer) {
    clearTimeout(buscarDebounceTimer);
    buscarDebounceTimer = null;
  }
}

function scheduleBusquedaIncremental() {
  clearBuscarDebounce();
  buscarDebounceTimer = setTimeout(() => {
    buscarDebounceTimer = null;
    page.value = 1;
    cargar();
  }, BUSCAR_DEBOUNCE_MS);
}

watch(buscar, () => {
  if (skipBuscarWatch.value) return;
  scheduleBusquedaIncremental();
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
  const nid = sp.get('nodo_id');
  if (nid != null && nid !== '') nodoId.value = nid;
  const v = (sp.get('vista') || 'activos').toLowerCase();
  vista.value = ['activos', 'todos', 'bajas'].includes(v) ? v : 'activos';
  const deu = (sp.get('deuda') || 'todas').toLowerCase();
  deuda.value = ['todas', 'vencida', 'vigente'].includes(deu) ? deu : 'todas';
  if (deuda.value === 'vigente') waMasivoTipo.value = 'aviso';
  if (deuda.value === 'vencida') waMasivoTipo.value = 'reclamo';
}

function seleccionarNodo(id) {
  nodoId.value = id || '';
  dropdownNodoOpen.value = false;
  page.value = 1;
  cargar();
}

function cambiarVista() {
  page.value = 1;
  cargar();
}

function appendVista(p) {
  if (vista.value && vista.value !== 'activos') p.vista = vista.value;
  return p;
}

function appendDeuda(p) {
  if (deuda.value && deuda.value !== 'todas') p.deuda = deuda.value;
  return p;
}

function construirParamsApi() {
  const p = {
    page: page.value,
    sort: sortBy.value,
    direction: sortDir.value,
  };
  const b = (buscar.value || '').trim();
  if (b) p.buscar = b;
  const nid = (nodoId.value || '').trim();
  if (nid) p.nodo_id = nid;
  appendVista(p);
  appendDeuda(p);
  Object.keys(pf).forEach((k) => {
    const v = (pf[k] || '').toString().trim();
    if (v !== '') p[k] = v;
  });
  return p;
}

/** Parámetros para el endpoint del mapa (mismos filtros que el listado, sin paginación). */
function construirParamsMapa() {
  const p = {
    sort: sortBy.value,
    direction: sortDir.value,
  };
  const b = (buscar.value || '').trim();
  if (b) p.buscar = b;
  const nid = (nodoId.value || '').trim();
  if (nid) p.nodo_id = nid;
  appendVista(p);
  appendDeuda(p);
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
  const nid = (nodoId.value || '').trim();
  if (nid) sp.set('nodo_id', nid);
  if (vista.value && vista.value !== 'activos') sp.set('vista', vista.value);
  if (deuda.value && deuda.value !== 'todas') sp.set('deuda', deuda.value);
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
    stats.value = {
      cantidad_facturas: Number(data.stats?.cantidad_facturas || 0),
      cantidad_clientes: Number(data.stats?.cantidad_clientes || 0),
      monto_total: Number(data.stats?.monto_total || 0),
      monto_cobrado: Number(data.stats?.monto_cobrado || 0),
      monto_saldo: Number(data.stats?.monto_saldo || 0),
      saldo_vigente: Number(data.stats?.saldo_vigente || 0),
      saldo_vencido: Number(data.stats?.saldo_vencido || 0),
      facturas_vencidas: Number(data.stats?.facturas_vencidas || 0),
      clientes_vencidos: Number(data.stats?.clientes_vencidos || 0),
      facturas_vigentes: Number(data.stats?.facturas_vigentes || 0),
    };
    syncSeleccionConFilas();
    syncHistory();
  } catch (e) {
    loadError.value = e.response?.data?.message || e.message || 'No se pudo cargar el listado.';
    rows.value = [];
    stats.value = {
      cantidad_facturas: 0,
      cantidad_clientes: 0,
      monto_total: 0,
      monto_cobrado: 0,
      monto_saldo: 0,
      saldo_vigente: 0,
      saldo_vencido: 0,
      facturas_vencidas: 0,
      clientes_vencidos: 0,
      facturas_vigentes: 0,
    };
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
  clearBuscarDebounce();
  page.value = 1;
  cargar();
}

function limpiarTodo() {
  clearBuscarDebounce();
  skipBuscarWatch.value = true;
  buscar.value = '';
  vista.value = 'activos';
  deuda.value = 'todas';
  nodoId.value = '';
  dropdownNodoOpen.value = false;
  Object.keys(pf).forEach((k) => {
    pf[k] = '';
  });
  sortBy.value = 'vencimiento';
  sortDir.value = 'asc';
  page.value = 1;
  openFilterKey.value = null;
  limpiarSeleccion();
  cargar();
  nextTick(() => {
    skipBuscarWatch.value = false;
  });
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

function agregarClienteSeleccion(row) {
  if (row?.cliente_id == null) return;
  selectedByCliente.value = {
    ...selectedByCliente.value,
    [row.cliente_id]: snapshotCliente(row),
  };
}

function quitarClienteSeleccion(row) {
  if (row?.cliente_id == null) return;
  const next = { ...selectedByCliente.value };
  delete next[row.cliente_id];
  selectedByCliente.value = next;
}

function limpiarSeleccion() {
  selectedByCliente.value = {};
}

function syncSeleccionConFilas() {
  const next = { ...selectedByCliente.value };
  let changed = false;
  rows.value.forEach((row) => {
    if (row?.cliente_id == null || !Object.prototype.hasOwnProperty.call(next, row.cliente_id)) return;
    next[row.cliente_id] = snapshotCliente(row);
    changed = true;
  });
  if (changed) selectedByCliente.value = next;
}

function toggleRowCliente(row, checked) {
  if (checked) agregarClienteSeleccion(row);
  else quitarClienteSeleccion(row);
}

function toggleSelectAll(checked) {
  if (checked) {
    rows.value.forEach((r) => agregarClienteSeleccion(r));
    return;
  }
  rows.value.forEach((r) => quitarClienteSeleccion(r));
}

function filtrarDeuda(clave) {
  if (clave === 'todas') {
    if (deuda.value === 'todas') return;
    deuda.value = 'todas';
  } else if (deuda.value === clave) {
    deuda.value = 'todas';
  } else {
    deuda.value = clave;
    if (clave === 'vigente') waMasivoTipo.value = 'aviso';
    if (clave === 'vencida') waMasivoTipo.value = 'reclamo';
  }
  page.value = 1;
  cargar();
}

function cerrarWaMasivo() {
  if (waMasivoEnviando.value) return;
  waMasivoAbierto.value = false;
  waMasivoResultado.value = null;
  document.body.classList.remove('overflow-hidden');
}

async function enviarWhatsappMasivo() {
  const items = selectedClienteItems.value;
  if (!items.length || waMasivoEnviando.value || !props.urls.whatsappMasivo) return;
  if (items.length > 50) {
    window.alert('Máximo 50 clientes por envío. Desmarcá algunos o filtrá la lista.');
    return;
  }
  const tipo = waMasivoTipo.value === 'reclamo' ? 'reclamo' : 'aviso';
  const tipoLabel = tipo === 'reclamo' ? 'reclamo de mora' : 'aviso de factura';
  if (tipo === 'reclamo') {
    const sinVencida = items.filter((i) => !i.tiene_vencida).length;
    if (sinVencida === items.length) {
      window.alert('Ningún cliente marcado tiene facturas vencidas para reclamar. Filtrá por «Total vencido» o cambiá a aviso de factura.');
      return;
    }
  }
  if (!window.confirm(`¿Enviar ${tipoLabel} por WhatsApp a ${items.length} cliente(s)? Se usa el número registrado en cada ficha.`)) {
    return;
  }

  waMasivoEnviando.value = true;
  waMasivoAbierto.value = true;
  waMasivoResultado.value = { ok: true, message: 'Enviando…', resultados: [] };
  document.body.classList.add('overflow-hidden');
  try {
    const { data } = await axios.post(
      props.urls.whatsappMasivo,
      {
        tipo,
        items: items.map((i) => ({ cliente_id: i.cliente_id, factura_id: i.factura_id })),
        forzar: tipo === 'reclamo' ? !!waMasivoForzar.value : false,
        adjuntar_resumen: true,
      },
      { timeout: 180000 },
    );
    waMasivoResultado.value = {
      ok: !!data.ok,
      message: data.message || 'Envío terminado.',
      resultados: Array.isArray(data.resultados) ? data.resultados : [],
    };
    await cargar();
  } catch (e) {
    const data = e.response?.data || {};
    waMasivoResultado.value = {
      ok: false,
      message: data.message || e.message || 'No se pudo completar el envío masivo.',
      resultados: Array.isArray(data.resultados) ? data.resultados : [],
    };
  } finally {
    waMasivoEnviando.value = false;
  }
}

const exportHref = computed(() => {
  const sp = new URLSearchParams();
  const b = (buscar.value || '').trim();
  if (b) sp.set('buscar', b);
  const nid = (nodoId.value || '').trim();
  if (nid) sp.set('nodo_id', nid);
  if (vista.value && vista.value !== 'activos') sp.set('vista', vista.value);
  if (deuda.value && deuda.value !== 'todas') sp.set('deuda', deuda.value);
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

function urlPdfCliente(clienteId) {
  return tplReplace(props.templates.pdfPendientesCliente || '#', clienteId);
}

function whatsappIcono(row) {
  return row?.whatsapp?.icono || 'alerta';
}

function whatsappBtnClass(row) {
  const icono = whatsappIcono(row);
  if (icono === 'ok') return 'text-emerald-600 hover:bg-emerald-50 dark:text-emerald-400 dark:hover:bg-emerald-900/30';
  if (icono === 'pendiente') return 'text-amber-600 hover:bg-amber-50 dark:text-amber-400 dark:hover:bg-amber-900/30';
  return 'text-red-600 hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-900/30';
}

function etiquetaEstadoWa(msg) {
  if (!msg || !msg.estado) return 'No enviado';
  const map = {
    leido: 'Leído',
    entregado: 'Entregado',
    enviado: 'Enviado',
    pendiente: 'Pendiente',
    fallido: 'Fallido',
    sin_envio: 'No enviado',
  };
  return map[msg.estado] || msg.estado;
}

function horaCortaWa(createdAt) {
  if (!createdAt) return '';
  const [fecha, hora] = String(createdAt).split(' ');
  if (!fecha) return createdAt;
  const parts = fecha.split('/');
  if (parts.length === 3) return `${parts[0]}/${parts[1]} ${hora || ''}`.trim();
  return createdAt;
}

function chipFiltroWaClass(filtro, msg) {
  const activo = waFiltro.value === filtro;
  const fallido = msg?.estado === 'fallido';
  const ok = msg?.estado === 'leido' || msg?.estado === 'entregado';
  const base = activo
    ? 'ring-2 ring-[#00a884] border-[#00a884] bg-emerald-50 dark:bg-emerald-900/25'
    : 'border-gray-200 dark:border-gray-700 hover:border-[#00a884]/60';
  if (fallido && !activo) return `${base} text-rose-700 dark:text-rose-300`;
  if (ok && !activo) return `${base} text-emerald-700 dark:text-emerald-300`;
  return `${base} text-gray-800 dark:text-gray-100`;
}

function leerErroresOcultosWa() {
  try {
    const raw = localStorage.getItem(WA_ERRORES_LS);
    const ids = raw ? JSON.parse(raw) : [];
    waErroresOcultos.value = new Set(Array.isArray(ids) ? ids.map(Number) : []);
  } catch {
    waErroresOcultos.value = new Set();
  }
}

function ocultarFalloWa(id) {
  const next = new Set(waErroresOcultos.value);
  next.add(Number(id));
  waErroresOcultos.value = next;
  try {
    localStorage.setItem(WA_ERRORES_LS, JSON.stringify([...next].slice(-200)));
  } catch {
    /* ignore */
  }
}

function mostrarFalloWa(m) {
  if (m.direccion === 'entrada' || m.estado !== 'fallido' || !m.fallo) return false;
  return !waErroresOcultos.value.has(Number(m.id));
}

function falloEsRecienteWa(m) {
  const t = Date.parse(m.created_at_iso || '');
  if (!t) return false;
  return (Date.now() - t) < 24 * 3600 * 1000;
}

const waHistorialFiltrado = computed(() => {
  const all = Array.isArray(waDetalle.value?.historial) ? waDetalle.value.historial : [];
  const f = waFiltro.value;
  if (f === 'todos') return all;
  const ctx = f === 'aviso' ? 'factura' : 'factura_reclamo';
  const outs = all.filter((h) => h.direccion !== 'entrada' && h.contexto_tipo === ctx);
  const ventanaMs = 7 * 24 * 3600 * 1000;
  const margenAntesMs = 12 * 3600 * 1000;
  return all.filter((m) => {
    if (m.direccion !== 'entrada' && m.contexto_tipo === ctx) return true;
    if (m.direccion !== 'entrada') return false;
    const t = Date.parse(m.created_at_iso || '');
    if (!t || !outs.length) return false;
    return outs.some((h) => {
      const ht = Date.parse(h.created_at_iso || '');
      return ht && t >= ht - margenAntesMs && t <= ht + ventanaMs;
    });
  });
});

const waEntradasCount = computed(() => waHistorialFiltrado.value.filter((m) => m.direccion === 'entrada').length);

const etiquetaFiltroWa = computed(() => {
  if (waFiltro.value === 'aviso') return 'Filtro: aviso de factura + respuestas cercanas';
  if (waFiltro.value === 'reclamo') return 'Filtro: reclamo de mora + respuestas cercanas';
  return 'Conversación completa';
});

function toggleFiltroWa(filtro) {
  waFiltro.value = waFiltro.value === filtro ? 'todos' : filtro;
  nextTick(scrollHiloWaAlFinal);
}

function etiquetaContextoWa(m) {
  if (m.contexto_tipo === 'factura_reclamo') return 'Reclamo de mora';
  if (m.contexto_tipo === 'factura') return 'Aviso de factura';
  if (m.contexto_tipo === 'recibo') return 'Recibo';
  return '';
}

function ticksWa(estado) {
  if (estado === 'leido') return '✓✓';
  if (estado === 'entregado' || estado === 'enviado') return '✓✓';
  if (estado === 'fallido') return '✕';
  return '✓';
}

function mostrarDiaWa(m, idx) {
  if (!m.dia) return idx === 0;
  if (idx === 0) return true;
  return waHistorialFiltrado.value[idx - 1]?.dia !== m.dia;
}

function scrollHiloWaAlFinal() {
  const el = waHiloEl.value;
  if (!el) return;
  el.scrollTop = el.scrollHeight;
}

function aplicarDetalleWa(detalle, resetDestino = false) {
  waDetalle.value = detalle;
  const tel = (detalle?.cliente?.telefono || '').trim();
  waTelefono.value = tel;
  if (resetDestino) {
    waDestino.value = tel ? 'registrado' : 'otro';
    waTelefonoOtro.value = '';
    waGuardarTel.value = false;
  }
  const facturas = Array.isArray(detalle?.facturas) ? detalle.facturas : [];
  const sinOk = facturas.find((f) => !f.aviso || !['leido', 'entregado'].includes(f.aviso.estado));
  waFacturaId.value = (sinOk || facturas[0] || {}).id || null;
  nextTick(scrollHiloWaAlFinal);
}

async function abrirModalWhatsapp(row) {
  const clienteId = row?.cliente_id;
  if (!clienteId) return;
  waClienteId.value = clienteId;
  modalWaAbierto.value = true;
  waCargando.value = true;
  waError.value = '';
  waResultado.value = '';
  waDetalle.value = null;
  waGuardarTel.value = false;
  waAdjuntarPdf.value = true;
  waFiltro.value = 'todos';
  waMostrarPreview.value = false;
  waTelefonoOtro.value = '';
  waDestino.value = 'registrado';
  leerErroresOcultosWa();
  document.body.classList.add('overflow-hidden');
  try {
    const url = tplReplace(props.urls.whatsappDetalle || '', clienteId);
    const { data } = await axios.get(url);
    aplicarDetalleWa(data, true);
  } catch (e) {
    waError.value = e.response?.data?.message || e.message || 'No se pudo cargar el historial de WhatsApp.';
  } finally {
    waCargando.value = false;
  }
}

function cerrarModalWhatsapp() {
  modalWaAbierto.value = false;
  waClienteId.value = null;
  waDetalle.value = null;
  waError.value = '';
  waResultado.value = '';
  waEnviando.value = false;
  waFiltro.value = 'todos';
  document.body.classList.remove('overflow-hidden');
}

function payloadTelWa() {
  if (waDestino.value === 'otro') {
    return {
      destino: 'otro',
      telefono: (waTelefonoOtro.value || '').trim() || null,
      guardar_telefono: !!waGuardarTel.value,
    };
  }
  return {
    destino: 'registrado',
    telefono: null,
    guardar_telefono: false,
  };
}

async function enviarAvisoWa() {
  if (!waClienteId.value || !waFacturaId.value || waEnviando.value) return;
  waEnviando.value = true;
  waResultado.value = '';
  try {
    const url = tplReplace(props.urls.whatsappReenviar || '', waClienteId.value);
    const { data } = await axios.post(url, {
      ...payloadTelWa(),
      factura_id: waFacturaId.value,
    });
    waResultadoOk.value = !!data.ok;
    waResultado.value = data.message || 'Aviso enviado.';
    if (data.detalle) aplicarDetalleWa(data.detalle);
    await cargar();
  } catch (e) {
    const data = e.response?.data || {};
    waResultadoOk.value = false;
    waResultado.value = data.message || e.message || 'No se pudo reenviar el aviso.';
    if (data.detalle) aplicarDetalleWa(data.detalle);
  } finally {
    waEnviando.value = false;
  }
}

async function enviarReclamoWa(forzar) {
  if (!waClienteId.value || waEnviando.value) return;
  waEnviando.value = true;
  waResultado.value = '';
  try {
    const url = tplReplace(props.urls.whatsappReclamo || '', waClienteId.value);
    const { data } = await axios.post(url, {
      ...payloadTelWa(),
      adjuntar_resumen: !!waAdjuntarPdf.value,
      forzar: !!forzar,
    });
    waResultadoOk.value = !!data.ok;
    waResultado.value = data.message || 'Reclamo enviado.';
    if (data.detalle) aplicarDetalleWa(data.detalle);
    await cargar();
  } catch (e) {
    const data = e.response?.data || {};
    if (e.response?.status === 409 && data.ya_enviado && !forzar) {
      if (window.confirm(data.message || 'Ya se envió un reclamo hoy. ¿Reenviar?')) {
        waEnviando.value = false;
        await enviarReclamoWa(true);
        return;
      }
    }
    waResultadoOk.value = false;
    waResultado.value = data.message || e.message || 'No se pudo enviar el reclamo.';
    if (data.detalle) aplicarDetalleWa(data.detalle);
  } finally {
    waEnviando.value = false;
  }
}

function abrirModalFacturasCliente(row) {
  modalFacturasClienteId.value = row?.cliente_id ?? null;
  modalFacturasClienteNombre.value = row?.cliente_nombre || '';
  modalFacturasLineas.value = Array.isArray(row?.facturas) ? [...row.facturas] : [];
  modalFacturasAbierto.value = true;
  document.body.classList.add('overflow-hidden');
}

function cerrarModalFacturasCliente() {
  modalFacturasAbierto.value = false;
  modalFacturasClienteId.value = null;
  modalFacturasClienteNombre.value = '';
  modalFacturasLineas.value = [];
  document.body.classList.remove('overflow-hidden');
}

function destruirMapaPendientes() {
  pendMapInfoWindow?.close();
  pendMapMarkers.forEach((m) => m.setMap(null));
  pendMapMarkers = [];
  pendMapInfoWindow = null;
  pendMapInstance = null;
}

function escapeHtmlMapa(text) {
  if (!text) return '';
  const div = document.createElement('div');
  div.textContent = text;
  return div.innerHTML;
}

function getDeudaMarkerIcon(google) {
  const w = 48;
  const h = 48;
  const size = new google.maps.Size(w, h);
  const color = '#d97706';
  const svg =
    '<svg xmlns="http://www.w3.org/2000/svg" width="' +
    w +
    '" height="' +
    h +
    '" viewBox="0 0 ' +
    w +
    ' ' +
    h +
    '">' +
    '<rect width="100%" height="100%" fill="none"/>' +
    '<g transform="translate(12,10)">' +
    '<path fill="' +
    color +
    '" stroke="#1f2937" stroke-width="0.45" stroke-linejoin="round" d="M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z"/>' +
    '</g></svg>';
  return {
    url: 'data:image/svg+xml;charset=UTF-8,' + encodeURIComponent(svg),
    size,
    scaledSize: size,
    origin: new google.maps.Point(0, 0),
    anchor: new google.maps.Point(24, 32),
  };
}

function loadGoogleMapsPendientes() {
  return new Promise((resolve, reject) => {
    const key = (props.googleMapsApiKey || '').trim();
    if (!key) {
      reject(new Error('Sin clave de Google Maps'));
      return;
    }
    if (typeof window.google !== 'undefined' && window.google.maps) {
      resolve(window.google);
      return;
    }
    const scriptId = 'google-maps-api-pendientes-pago';
    if (document.getElementById(scriptId)) {
      const check = setInterval(() => {
        if (window.google?.maps) {
          clearInterval(check);
          resolve(window.google);
        }
      }, 100);
      return;
    }
    window.__pendientesPagoMapsReady__ = () => resolve(window.google);
    const script = document.createElement('script');
    script.id = scriptId;
    script.src = `https://maps.googleapis.com/maps/api/js?key=${encodeURIComponent(key)}&callback=__pendientesPagoMapsReady__`;
    script.async = true;
    script.defer = true;
    script.onerror = () => reject(new Error('No se pudo cargar Google Maps'));
    document.head.appendChild(script);
  });
}

async function renderMapaPendientes(google, puntos) {
  const el = mapaPendientesContainer.value;
  if (!el) return;
  destruirMapaPendientes();
  const list = Array.isArray(puntos) ? puntos : [];
  const center = list.length ? { lat: list[0].lat, lng: list[0].lon } : { lat: -25.2637, lng: -57.5759 };
  pendMapInstance = new google.maps.Map(el, {
    center,
    zoom: list.length ? 11 : 6,
    mapTypeControl: true,
    streetViewControl: true,
    fullscreenControl: true,
    zoomControl: true,
  });
  pendMapInfoWindow = new google.maps.InfoWindow();
  const bounds = new google.maps.LatLngBounds();
  const icon = getDeudaMarkerIcon(google);
  const batchSize = 120;
  for (let i = 0; i < list.length; i += batchSize) {
    const batch = list.slice(i, i + batchSize);
    batch.forEach((p) => {
      const position = { lat: p.lat, lng: p.lon };
      const titulo = p.nombre || `Cliente #${p.cliente_id}`;
      const marker = new google.maps.Marker({
        position,
        map: pendMapInstance,
        title: titulo,
        icon,
        optimized: true,
      });
      const saldoTxt = fmtMonto(p.saldo_pendiente, p.moneda);
      const dir = (p.direccion || '').trim();
      const detalleHref =
        props.canVerClienteDetalle && props.clienteDetalleTpl
          ? tplReplace(props.clienteDetalleTpl, p.cliente_id)
          : '';
      const urlUb = (p.url_ubicacion || '').trim();
      const content = `
        <div class="p-2 min-w-[200px] max-w-[320px]">
          <div class="font-semibold text-gray-900">${escapeHtmlMapa(titulo)}</div>
          <div class="text-sm text-amber-800 mt-1 font-medium">Saldo: ${escapeHtmlMapa(saldoTxt)}</div>
          ${dir ? `<div class="text-xs text-gray-600 mt-1">${escapeHtmlMapa(dir)}</div>` : ''}
          ${urlUb ? `<a href="${escapeHtmlMapa(urlUb)}" target="_blank" rel="noopener" class="inline-block mt-2 text-sm text-sky-600 hover:underline">Abrir ubicación</a>` : ''}
          ${detalleHref ? `<a href="${escapeHtmlMapa(detalleHref)}" class="inline-block mt-2 ml-2 text-sm text-purple-600 hover:underline">Ver cliente</a>` : ''}
        </div>
      `;
      marker.addListener('click', () => {
        pendMapInfoWindow.setContent(content);
        pendMapInfoWindow.open(pendMapInstance, marker);
      });
      pendMapMarkers.push(marker);
      bounds.extend(position);
    });
    await new Promise((r) => requestAnimationFrame(r));
  }
  if (list.length > 1) {
    pendMapInstance.fitBounds(bounds);
  }
}

async function abrirModalMapa() {
  if (!props.mapPuntosUrl) return;
  mapaErrorMapa.value = '';
  modalMapaAbierto.value = true;
  mapaCargandoPuntos.value = true;
  mapaPuntosData.value = [];
  mapaStats.value = { total_clientes: 0, con_coordenadas: 0, sin_coordenadas: 0 };
  document.body.classList.add('overflow-hidden');
  try {
    const { data } = await axios.get(props.mapPuntosUrl, { params: construirParamsMapa() });
    mapaPuntosData.value = Array.isArray(data.puntos) ? data.puntos : [];
    mapaStats.value = {
      total_clientes: Number(data.stats_mapa?.total_clientes || 0),
      con_coordenadas: Number(data.stats_mapa?.con_coordenadas || 0),
      sin_coordenadas: Number(data.stats_mapa?.sin_coordenadas || 0),
    };
  } catch (e) {
    mapaErrorMapa.value = e.response?.data?.message || e.message || 'No se pudo cargar los puntos del mapa.';
  } finally {
    mapaCargandoPuntos.value = false;
  }
  await nextTick();
  const key = (props.googleMapsApiKey || '').trim();
  if (!key) return;
  try {
    const google = await loadGoogleMapsPendientes();
    await renderMapaPendientes(google, mapaPuntosData.value);
  } catch (e) {
    mapaErrorMapa.value = e.message || 'Error al inicializar Google Maps.';
  }
}

function cerrarModalMapa() {
  destruirMapaPendientes();
  modalMapaAbierto.value = false;
  mapaErrorMapa.value = '';
  document.body.classList.remove('overflow-hidden');
}

function hoyYmd() {
  const d = new Date();
  const p = (n) => String(n).padStart(2, '0');
  return `${d.getFullYear()}-${p(d.getMonth() + 1)}-${p(d.getDate())}`;
}

function facturaEstaVencida(fechaYmd) {
  if (!fechaYmd) return false;
  return String(fechaYmd).slice(0, 10) < hoyYmd();
}

function saldosFila(row) {
  const facturas = Array.isArray(row?.facturas) && row.facturas.length
    ? row.facturas
    : [{ fecha_vencimiento: row?.fecha_vencimiento, saldo_pendiente: row?.saldo_pendiente }];
  let vigente = 0;
  let vencido = 0;
  facturas.forEach((f) => {
    const s = Number(f.saldo_pendiente || 0);
    if (facturaEstaVencida(f.fecha_vencimiento)) vencido += s;
    else vigente += s;
  });
  return { vigente, vencido };
}

function saldoFilaMixto(row) {
  const s = saldosFila(row);
  return s.vigente > 0 && s.vencido > 0;
}

function claseSaldoFila(row) {
  if (row?.cliente_dado_baja) return 'text-gray-500 dark:text-gray-400';
  if (saldoFilaMixto(row)) return '';
  const s = saldosFila(row);
  if (s.vencido > 0) return 'text-amber-700 dark:text-amber-400';
  return 'text-cyan-700 dark:text-cyan-400';
}

function claseSaldoFactura(f) {
  return facturaEstaVencida(f?.fecha_vencimiento)
    ? 'text-amber-700 dark:text-amber-400'
    : 'text-cyan-700 dark:text-cyan-400';
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
  if (ev.key !== 'Escape') return;
  if (waMasivoAbierto.value) cerrarWaMasivo();
  else if (modalWaAbierto.value) cerrarModalWhatsapp();
  else if (modalMapaAbierto.value) cerrarModalMapa();
  else if (modalFacturasAbierto.value) cerrarModalFacturasCliente();
  else if (modalContactoAbierto.value) cerrarModalContacto();
}

onMounted(() => {
  skipBuscarWatch.value = true;
  leerParamsUrl();
  cargar().finally(() => {
    nextTick(() => {
      skipBuscarWatch.value = false;
    });
  });
  document.addEventListener('keydown', onEsc);
  document.addEventListener('click', onDocClickNodo, true);
});

onUnmounted(() => {
  clearBuscarDebounce();
  document.removeEventListener('keydown', onEsc);
  document.removeEventListener('click', onDocClick, true);
  document.removeEventListener('click', onDocClickNodo, true);
  destruirMapaPendientes();
});
</script>
