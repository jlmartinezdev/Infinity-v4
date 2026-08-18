<template>
  <div :class="compact ? '' : 'max-w-7xl mx-auto'">
    <div
      v-if="!compact && payload"
      class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between"
    >
      <div>
        <p class="text-xs font-semibold uppercase tracking-wider text-blue-500 dark:text-blue-400">Centro de operaciones · NOC</p>
        <a
          :href="payload.urls?.servicios_index"
          class="mt-1 inline-block text-sm font-medium text-gray-500 hover:text-blue-600 dark:text-gray-400 dark:hover:text-blue-400"
        >&larr; Volver a servicios</a>
        <h1 class="mt-2 text-2xl font-bold text-gray-900 dark:text-gray-100">Herramientas de red</h1>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
          {{ servicio.cliente_nombre || ('Servicio #' + servicio.servicio_id) }}
          <template v-if="servicio.ip">
            · IP <span class="font-mono text-gray-800 dark:text-gray-200">{{ servicio.ip }}</span>
          </template>
          <template v-if="servicio.usuario_pppoe">
            · PPPoE <span class="font-mono text-gray-800 dark:text-gray-200">{{ servicio.usuario_pppoe }}</span>
          </template>
        </p>
        <p class="mt-0.5 text-xs text-gray-400 dark:text-gray-500">
          Router: {{ servicio.router_nombre || 'sin pool/router' }}
          <template v-if="servicio.nodo">
            · Nodo {{ servicio.nodo }}
          </template>
          <template v-if="servicio.tecnologia_label">
            · {{ servicio.tecnologia_label }}
          </template>
        </p>
        <p v-if="servicio.equipo_resumen" class="mt-1 text-sm text-gray-700 dark:text-gray-300">
          Equipo en casa: <span class="font-medium">{{ servicio.equipo_resumen }}</span>
        </p>
      </div>
      <div class="flex flex-wrap items-center gap-2">
        <a
          v-if="servicio.cliente_url"
          :href="servicio.cliente_url"
          class="noc-btn-ghost inline-flex items-center rounded-lg px-3 py-2 text-sm font-medium"
        >
          Detalle del cliente
        </a>
        <a
          v-if="servicio.servicio_id && servicio.edit_url"
          :href="servicio.edit_url"
          class="noc-btn-ghost inline-flex items-center rounded-lg px-3 py-2 text-sm font-medium"
        >
          Editar servicio
        </a>
      </div>
    </div>

    <div v-if="servicios.length > 1" class="mb-4">
      <label for="noc-servicio-select" class="block text-xs font-semibold uppercase tracking-wide text-gray-600 dark:text-gray-300 mb-1">Servicio</label>
      <select
        id="noc-servicio-select"
        v-model="selectedServicioId"
        class="w-full max-w-xl py-2.5 px-3 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 text-sm"
      >
        <option v-for="s in servicios" :key="s.servicio_id" :value="s.servicio_id">{{ s.label }}</option>
      </select>
    </div>

    <p v-if="loadingDatos" class="text-sm text-gray-500 dark:text-gray-400">Cargando…</p>

    <p
      v-else-if="!payload && !servicios.length"
      class="text-sm text-gray-500 dark:text-gray-400"
    >No hay servicios para consultar.</p>

    <template v-else-if="payload">
      <div class="noc-tools-grid" :class="{ 'is-4': toolsCols >= 4 }">
        <!-- Ping -->
        <section class="noc-card">
          <div class="noc-card-head">
            <span class="noc-icon noc-icon--blue">
              <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
            </span>
            <div>
              <h2 class="noc-card-title">Ping CPE</h2>
              <p class="noc-card-sub">ICMP desde el servidor</p>
            </div>
          </div>
          <div class="noc-card-body space-y-3">
            <div class="flex items-stretch gap-2">
              <div class="noc-input-display flex flex-1 items-center font-mono text-sm">{{ servicio.ip || 'Sin IP asignada' }}</div>
              <button
                type="button"
                class="noc-btn-icon noc-btn-icon--primary"
                title="Ejecutar ping"
                aria-label="Ejecutar ping"
                :disabled="!canPing || loading.ping"
                @click="onPing"
              >
                <svg v-show="!loading.ping" class="noc-btn-action-icon" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="M8 5.14v13.72a1 1 0 0 0 1.5.866l11.196-6.86a1 1 0 0 0 0-1.732L9.5 4.274A1 1 0 0 0 8 5.14z"/></svg>
                <svg v-show="loading.ping" class="noc-btn-spinner animate-spin" fill="none" viewBox="0 0 24 24" aria-hidden="true"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
              </button>
            </div>
            <div v-show="outPing" class="text-sm" v-html="outPing"></div>
            <div v-if="pingStats" class="space-y-2">
              <div class="flex flex-wrap items-center gap-2">
                <span
                  v-if="pingStats.alive"
                  class="inline-flex rounded-full bg-green-100 px-2 py-0.5 text-xs font-semibold text-green-800 dark:bg-green-900/30 dark:text-green-300"
                >Responde</span>
                <span
                  v-else
                  class="inline-flex rounded-full bg-red-100 px-2 py-0.5 text-xs font-semibold text-red-800 dark:bg-red-900/30 dark:text-red-300"
                >Sin respuesta</span>
              </div>
              <p class="text-sm text-gray-700 dark:text-gray-200">{{ pingStats.calidad }}</p>
              <div class="grid grid-cols-2 gap-2">
                <div>
                  <p class="noc-metric-label">Paquetes</p>
                  <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">
                    {{ pingStats.received }} de {{ pingStats.sent }}
                  </p>
                </div>
                <div>
                  <p class="noc-metric-label">Pérdida</p>
                  <p
                    class="text-sm font-semibold"
                    :class="pingStats.loss_pct > 0 ? 'noc-metric--warn' : 'text-gray-900 dark:text-gray-100'"
                  >{{ pingStats.loss_pct }}%</p>
                </div>
                <div v-if="pingStats.avg_ms != null">
                  <p class="noc-metric-label">Promedio</p>
                  <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ pingStats.avg_ms }} ms</p>
                </div>
                <div v-if="pingStats.min_ms != null || pingStats.max_ms != null">
                  <p class="noc-metric-label">Mín / Máx</p>
                  <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">
                    {{ pingStats.min_ms != null ? pingStats.min_ms : '—' }}
                    /
                    {{ pingStats.max_ms != null ? pingStats.max_ms : '—' }}
                    ms
                  </p>
                </div>
              </div>
              <details v-if="pingStats.output" class="text-xs text-gray-500 dark:text-gray-400">
                <summary class="cursor-pointer select-none">Ver detalle técnico</summary>
                <pre class="mt-1 max-h-40 overflow-auto rounded-lg border border-gray-200 bg-gray-50 p-2 font-mono text-[11px] text-gray-800 dark:border-gray-600 dark:bg-gray-900/50 dark:text-gray-200 whitespace-pre-wrap">{{ pingStats.output }}</pre>
              </details>
            </div>
          </div>
        </section>

        <!-- MAC -->
        <section class="noc-card">
          <div class="noc-card-head">
            <span class="noc-icon noc-icon--indigo">
              <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
            </span>
            <div>
              <h2 class="noc-card-title">MAC Address</h2>
              <p class="noc-card-sub">Consulta MikroTik</p>
            </div>
          </div>
          <div class="noc-card-body space-y-3">
            <p class="text-xs text-gray-500 dark:text-gray-400">PPP activo · ARP · DHCP lease</p>
            <div class="flex flex-wrap gap-2">
              <button
                type="button"
                class="noc-btn-icon noc-btn-icon--primary"
                title="Consultar MAC"
                aria-label="Consultar MAC"
                :disabled="!canMac || loading.mac"
                @click="onMac"
              >
                <svg v-show="!loading.mac" class="noc-btn-action-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.25" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 12a9 9 0 1 1-2.64-6.36"/><path d="M21 3v6h-6"/></svg>
                <svg v-show="loading.mac" class="noc-btn-spinner animate-spin" fill="none" viewBox="0 0 24 24" aria-hidden="true"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
              </button>
              <button
                type="button"
                class="noc-btn-icon noc-btn-icon--ghost"
                title="Ver tráfico de sesión"
                aria-label="Ver tráfico de sesión"
                :disabled="!canMac || loading.trafico"
                @click="onTrafico"
              >
                <svg v-show="!loading.trafico" class="noc-btn-action-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.25" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4"/></svg>
                <svg v-show="loading.trafico" class="noc-btn-spinner animate-spin" fill="none" viewBox="0 0 24 24" aria-hidden="true"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
              </button>
            </div>
            <div v-show="outMac" class="text-sm" v-html="outMac"></div>
            <div v-show="outTrafico" class="text-sm" v-html="outTrafico"></div>
          </div>
        </section>

        <!-- ONU Signal (solo GPON / fibra) -->
        <section v-if="esFibra" class="noc-card">
          <div class="noc-card-head">
            <span class="noc-icon noc-icon--amber">
              <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/></svg>
            </span>
            <div class="flex-1 min-w-0">
              <div class="flex items-center gap-2">
                <h2 class="noc-card-title">ONU Signal</h2>
                <span v-if="ultimaOptica" class="noc-live-badge">REGISTRO</span>
              </div>
              <p class="noc-card-sub">Señal óptica OLT</p>
            </div>
          </div>
          <div class="noc-card-body space-y-3">
            <template v-if="ultimaOptica">
              <div class="grid grid-cols-2 gap-3">
                <div v-if="ultimaOptica.tx_power_dbm != null">
                  <p class="noc-metric-label">TX Power</p>
                  <p class="noc-metric noc-metric--blue">{{ ultimaOptica.tx_power_dbm }} <span class="text-sm font-normal">dBm</span></p>
                </div>
                <div v-if="ultimaOptica.rx_power_dbm != null">
                  <p class="noc-metric-label">RX Power</p>
                  <p :class="Number(ultimaOptica.rx_power_dbm) <= -27 ? 'noc-metric noc-metric--warn' : 'noc-metric noc-metric--amber'">
                    {{ ultimaOptica.rx_power_dbm }} <span class="text-sm font-normal">dBm</span>
                  </p>
                </div>
              </div>
              <div class="grid grid-cols-2 gap-2 text-xs text-gray-500 dark:text-gray-400">
                <div v-if="ultimaOptica.pon_port != null && ultimaOptica.onu_index != null">
                  PON <span class="font-mono text-gray-700 dark:text-gray-200">{{ ultimaOptica.pon_port }}:{{ ultimaOptica.onu_index }}</span>
                </div>
                <div v-if="ultimaOptica.onu_estado">
                  Estado <span class="text-gray-700 dark:text-gray-200">{{ ultimaOptica.onu_estado }}</span>
                </div>
              </div>
              <p class="text-[10px] text-gray-400">Última lectura · {{ ultimaOptica.ocurrio_at }}</p>
            </template>
            <p v-else class="text-xs text-gray-500 dark:text-gray-400">Sin registro de señal óptica. Consultá la ONU para guardar RX/TX.</p>
            <div class="flex flex-wrap gap-2">
              <button
                type="button"
                class="noc-btn-icon noc-btn-icon--primary"
                title="Consultar señal ONU"
                aria-label="Consultar señal ONU"
                :disabled="!canOlt || loading.olt"
                @click="onOlt"
              >
                <svg v-show="!loading.olt" class="noc-btn-action-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.25" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 12a9 9 0 1 1-2.64-6.36"/><path d="M21 3v6h-6"/></svg>
                <svg v-show="loading.olt" class="noc-btn-spinner animate-spin" fill="none" viewBox="0 0 24 24" aria-hidden="true"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
              </button>
              <button
                type="button"
                class="noc-btn-icon noc-btn-icon--ghost"
                title="Aplicar descripción ONU"
                aria-label="Aplicar descripción ONU"
                :disabled="!canOltDesc || loading.oltDesc"
                @click="onOltDesc"
              >
                <svg v-show="!loading.oltDesc" class="noc-btn-action-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.25" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M11 5H6a2 2 0 0 0-2 2v11a2 2 0 0 0 2 2h11a2 2 0 0 0 2-2v-5m-1.414-9.414a2 2 0 1 1 2.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                <svg v-show="loading.oltDesc" class="noc-btn-spinner animate-spin" fill="none" viewBox="0 0 24 24" aria-hidden="true"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
              </button>
            </div>
            <div v-show="outOlt" class="text-sm" v-html="outOlt"></div>
          </div>
        </section>

        <!-- CPE / Antena (solo wireless) -->
        <section v-if="esAntena" class="noc-card">
          <div class="noc-card-head">
            <span class="noc-icon noc-icon--sky">
              <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.111 16.404a5.5 5.5 0 017.778 0M12 20h.01m-7.08-2.912a10 10 0 0114.16 0M1.394 9.393c5.857-5.857 15.355-5.857 21.213 0"/></svg>
            </span>
            <div class="flex-1 min-w-0">
              <div class="flex items-center gap-2">
                <h2 class="noc-card-title">CPE / Antena</h2>
                <span v-if="ultimaAntena" class="noc-live-badge">REGISTRO</span>
              </div>
              <p class="noc-card-sub">Wireless Ubiquiti</p>
            </div>
          </div>
          <div class="noc-card-body space-y-3">
            <template v-if="ultimaAntena">
              <div v-if="ultimaAntena.antena_signal_dbm != null">
                <div class="mb-1 flex items-center justify-between text-xs">
                  <span class="noc-metric-label mb-0">Signal Strength</span>
                  <span class="font-mono font-semibold text-blue-500">{{ ultimaAntena.antena_signal_dbm }} dBm</span>
                </div>
                <div class="noc-bar-track">
                  <div class="noc-bar-fill noc-bar-fill--signal" :style="{ width: barPct(ultimaAntena.antena_signal_dbm) + '%' }"></div>
                </div>
              </div>
              <div v-if="ultimaAntena.noise_floor_dbm">
                <div class="mb-1 flex items-center justify-between text-xs">
                  <span class="noc-metric-label mb-0">Noise Floor</span>
                  <span class="font-mono font-semibold text-orange-500">{{ ultimaAntena.noise_floor_dbm }} dBm</span>
                </div>
                <div class="noc-bar-track">
                  <div class="noc-bar-fill noc-bar-fill--noise" :style="{ width: barPct(ultimaAntena.noise_floor_dbm) + '%' }"></div>
                </div>
              </div>
              <div class="flex flex-wrap gap-3 text-xs text-gray-500 dark:text-gray-400">
                <span v-if="ultimaAntena.antena_snr_db != null">
                  SNR <strong class="text-gray-700 dark:text-gray-200">{{ ultimaAntena.antena_snr_db }} dB</strong>
                </span>
                <span v-if="ultimaAntena.ccq">
                  CCQ <strong class="text-gray-700 dark:text-gray-200">{{ ultimaAntena.ccq }}%</strong>
                </span>
              </div>
              <p class="text-[10px] text-gray-400">Última lectura · {{ ultimaAntena.ocurrio_at }}</p>
            </template>
            <p v-else class="text-xs text-gray-500 dark:text-gray-400">
              Sin registro de señal antena. Consultá vía SSH <span class="font-mono">wstalist</span>.
            </p>
            <div class="flex flex-wrap gap-2">
              <button
                type="button"
                class="noc-btn-icon noc-btn-icon--primary"
                title="Consultar señal antena"
                aria-label="Consultar señal antena"
                :disabled="!canAntena || loading.antena"
                @click="onAntena"
              >
                <svg v-show="!loading.antena" class="noc-btn-action-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.25" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 12a9 9 0 1 1-2.64-6.36"/><path d="M21 3v6h-6"/></svg>
                <svg v-show="loading.antena" class="noc-btn-spinner animate-spin" fill="none" viewBox="0 0 24 24" aria-hidden="true"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
              </button>
              <button
                type="button"
                class="noc-btn-icon noc-btn-icon--ghost"
                title="Consultar DHCP leases"
                aria-label="Consultar DHCP leases"
                :disabled="!canAntena || loading.antenaDhcp"
                @click="onAntenaDhcp"
              >
                <svg v-show="!loading.antenaDhcp" class="noc-btn-action-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.25" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01"/></svg>
                <svg v-show="loading.antenaDhcp" class="noc-btn-spinner animate-spin" fill="none" viewBox="0 0 24 24" aria-hidden="true"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
              </button>
            </div>
            <div v-show="outAntena" class="text-sm" v-html="outAntena"></div>
            <div v-show="outAntenaDhcp" class="text-sm" v-html="outAntenaDhcp"></div>
          </div>
        </section>

        <section v-if="tr069Enabled" class="noc-card">
          <div class="noc-card-head">
            <span class="noc-icon noc-icon--violet">
              <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l3 3-3 3m5 0h3M5 20h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
            </span>
            <div class="flex-1 min-w-0">
              <div class="flex items-center gap-2">
                <h2 class="noc-card-title">TR-069</h2>
                <span v-if="tr069Resumen?.online" class="noc-live-badge">INFORM</span>
              </div>
              <p class="noc-card-sub">GenieACS · clave y reboot por ACS</p>
            </div>
          </div>
          <div class="noc-card-body space-y-3">
            <p class="text-xs text-gray-500 dark:text-gray-400">
              Serial
              <span class="font-mono text-gray-800 dark:text-gray-200">{{ servicio.tr069_serial || '—' }}</span>
              <template v-if="servicio.mac_address">
                · MAC <span class="font-mono text-gray-800 dark:text-gray-200">{{ servicio.mac_address }}</span>
              </template>
            </p>
            <div class="flex flex-wrap gap-2">
              <button
                type="button"
                class="noc-btn-icon noc-btn-icon--primary"
                title="Consultar CPE en el ACS"
                aria-label="Consultar CPE en el ACS"
                :disabled="loading.tr069"
                @click="onTr069Resumen"
              >
                <svg v-show="!loading.tr069" class="noc-btn-action-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.25" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 12a9 9 0 1 1-2.64-6.36"/><path d="M21 3v6h-6"/></svg>
                <svg v-show="loading.tr069" class="noc-btn-spinner animate-spin" fill="none" viewBox="0 0 24 24" aria-hidden="true"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
              </button>
              <button
                type="button"
                class="noc-btn-icon noc-btn-icon--ghost"
                title="Hosts LAN"
                aria-label="Hosts LAN"
                :disabled="loading.tr069Hosts"
                @click="onTr069Hosts"
              >
                <svg v-show="!loading.tr069Hosts" class="noc-btn-action-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.25" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01"/></svg>
                <svg v-show="loading.tr069Hosts" class="noc-btn-spinner animate-spin" fill="none" viewBox="0 0 24 24" aria-hidden="true"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
              </button>
              <button
                type="button"
                class="noc-btn-icon noc-btn-icon--ghost"
                title="Refresh parámetros"
                aria-label="Refresh parámetros"
                :disabled="loading.tr069Refresh"
                @click="onTr069Refresh"
              >
                <svg v-show="!loading.tr069Refresh" class="noc-btn-action-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.25" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                <svg v-show="loading.tr069Refresh" class="noc-btn-spinner animate-spin" fill="none" viewBox="0 0 24 24" aria-hidden="true"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
              </button>
              <button
                type="button"
                class="noc-btn-icon noc-btn-icon--ghost"
                title="Reiniciar CPE"
                aria-label="Reiniciar CPE"
                :disabled="loading.tr069Reboot"
                @click="onTr069Reboot"
              >
                <svg v-show="!loading.tr069Reboot" class="noc-btn-action-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.25" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5.636 5.636a9 9 0 1012.728 0M12 3v9"/></svg>
                <svg v-show="loading.tr069Reboot" class="noc-btn-spinner animate-spin" fill="none" viewBox="0 0 24 24" aria-hidden="true"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
              </button>
            </div>
            <p v-if="outTr069" class="text-sm" v-html="outTr069"></p>
          </div>
        </section>
      </div>

      <section v-if="cpeSsh && esFibra" class="noc-card mt-4">
        <div class="noc-card-head">
          <span class="noc-icon noc-icon--blue">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 11V7a4 4 0 118 0v4M5 11h14v10H5V11z"/></svg>
          </span>
          <div class="flex-1 min-w-0">
            <h2 class="noc-card-title">CPE por SSH</h2>
            <p class="noc-card-sub">Huawei / ONU con acceso SSH</p>
          </div>
        </div>
        <div class="noc-card-body space-y-2">
          <p class="text-sm text-gray-700 dark:text-gray-300">
            Este servicio está marcado para comandos por <strong>SSH</strong>, no por ACS.
            <template v-if="servicio.equipo_resumen"> {{ servicio.equipo_resumen }}.</template>
          </p>
          <p class="text-xs text-gray-500 dark:text-gray-400">
            La OLT y la antena Ubnt siguen en las tarjetas de arriba. Los comandos SSH al Huawei (WiFi, reboot del ONU) se agregan sobre este perfil.
          </p>
        </div>
      </section>

      <section v-if="tr069Enabled && tr069TieneDetalle" class="noc-card mt-4">
        <div class="noc-card-head">
          <span class="noc-icon noc-icon--violet">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l3 3-3 3m5 0h3M5 20h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
          </span>
          <div class="flex-1 min-w-0">
            <h2 class="noc-card-title">Detalle TR-069</h2>
            <p class="noc-card-sub">Estado ACS, hosts LAN y cambio de clave</p>
          </div>
        </div>
        <div class="noc-card-body space-y-3">
          <template v-if="tr069Resumen && tr069Resumen.success">
            <p v-if="tr069Resumen.aviso" class="text-sm text-amber-700 dark:text-amber-300">{{ tr069Resumen.aviso }}</p>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-3 text-sm">
              <div>
                <p class="noc-metric-label">Estado</p>
                <p class="font-semibold" :class="tr069Resumen.online ? 'text-green-600 dark:text-green-400' : 'text-amber-600 dark:text-amber-400'">
                  {{ tr069Resumen.online ? 'Online' : 'Sin Inform reciente' }}
                </p>
              </div>
              <div>
                <p class="noc-metric-label">Modelo</p>
                <p class="font-medium text-gray-900 dark:text-gray-100">{{ tr069Resumen.model || tr069Resumen.product_class || '—' }}</p>
                <p v-if="tr069Resumen.manufacturer" class="text-xs text-gray-500 dark:text-gray-400">{{ tr069Resumen.manufacturer }}</p>
              </div>
              <div>
                <p class="noc-metric-label">Firmware</p>
                <p class="font-mono text-xs text-gray-800 dark:text-gray-200">{{ tr069Resumen.software_version || '—' }}</p>
              </div>
              <div>
                <p class="noc-metric-label">WAN IP</p>
                <p class="font-mono text-gray-900 dark:text-gray-100">{{ tr069Resumen.wan_ip || '—' }}</p>
                <p v-if="tr069Resumen.wan_mac" class="font-mono text-xs text-gray-500 dark:text-gray-400">{{ tr069Resumen.wan_mac }}</p>
              </div>
              <div>
                <p class="noc-metric-label">LAN CPE</p>
                <p class="font-mono text-gray-900 dark:text-gray-100">{{ tr069Resumen.lan_ip || '—' }}</p>
              </div>
              <div class="col-span-1 md:col-span-3">
                <p class="noc-metric-label">SSID</p>
                <p class="text-gray-900 dark:text-gray-100">{{ (tr069Resumen.ssids && tr069Resumen.ssids.length) ? tr069Resumen.ssids.join(' · ') : (tr069Resumen.ssid || '—') }}</p>
              </div>
              <div class="col-span-2">
                <p class="noc-metric-label">Último Inform</p>
                <p class="text-xs text-gray-500 dark:text-gray-400">{{ tr069Resumen.last_inform || '—' }}</p>
              </div>
            </div>
          </template>
          <div v-if="tr069Hosts && tr069Hosts.length" class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-600">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
              <thead class="bg-gray-50 dark:bg-gray-900/40">
                <tr>
                  <th class="px-2 py-2 text-left text-xs font-medium uppercase text-gray-500">IP</th>
                  <th class="px-2 py-2 text-left text-xs font-medium uppercase text-gray-500">MAC</th>
                  <th class="px-2 py-2 text-left text-xs font-medium uppercase text-gray-500">Nombre</th>
                  <th class="px-2 py-2 text-left text-xs font-medium uppercase text-gray-500">RSSI</th>
                  <th class="px-2 py-2 text-left text-xs font-medium uppercase text-gray-500">Origen</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                <tr v-for="(h, idx) in tr069Hosts" :key="'h-' + idx" class="hover:bg-gray-50 dark:hover:bg-gray-700/40">
                  <td class="px-2 py-2 font-mono text-gray-900 dark:text-gray-100">{{ h.ip || '—' }}</td>
                  <td class="px-2 py-2 font-mono text-gray-700 dark:text-gray-300">{{ h.mac || '—' }}</td>
                  <td class="px-2 py-2 text-gray-700 dark:text-gray-300">{{ h.hostname || '—' }}</td>
                  <td class="px-2 py-2 font-mono text-gray-700 dark:text-gray-300">{{ h.rssi != null && h.rssi !== '' ? h.rssi + ' dBm' : '—' }}</td>
                  <td class="px-2 py-2 text-gray-500 dark:text-gray-400">{{ h.source === 'wifi' ? 'WiFi' : (h.source === 'lan' ? 'LAN' : '—') }}</td>
                </tr>
              </tbody>
            </table>
          </div>
          <form
            v-if="tr069Resumen && tr069Resumen.success"
            class="rounded-lg border border-gray-200 dark:border-gray-600 p-3 space-y-2"
            @submit.prevent="onTr069Password"
          >
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-600 dark:text-gray-300">Cambiar clave por ACS</p>
            <p class="text-xs text-gray-500 dark:text-gray-400">El CPE no informa la clave actual. Se escribe por TR-069 (WPA2, 8–63 caracteres).</p>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
              <div>
                <label for="tr069-pass-tipo" class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Destino</label>
                <select
                  id="tr069-pass-tipo"
                  v-model="tr069PassTarget"
                  class="w-full py-2 px-3 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 text-sm"
                >
                  <option value="wifi-all">WiFi · todos los SSID activos</option>
                  <option
                    v-for="w in tr069WifiEnabled"
                    :key="w.id"
                    :value="'wifi:' + w.id"
                  >WiFi · {{ w.ssid }}{{ w.band ? ' (' + w.band + ')' : '' }}</option>
                  <option v-if="tr069Resumen.puede_admin_password" value="admin">Clave del router (panel)</option>
                </select>
              </div>
              <div>
                <label for="tr069-pass" class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Nueva clave</label>
                <input
                  id="tr069-pass"
                  v-model="tr069Password"
                  type="password"
                  autocomplete="new-password"
                  class="w-full py-2 px-3 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 text-sm font-mono"
                  :minlength="tr069PassTarget === 'admin' ? 4 : 8"
                  :maxlength="tr069PassTarget === 'admin' ? 64 : 63"
                  required
                >
              </div>
            </div>
            <div class="flex flex-wrap items-center gap-2">
              <input
                v-model="tr069Password2"
                type="password"
                autocomplete="new-password"
                placeholder="Repetir clave"
                class="flex-1 min-w-[10rem] py-2 px-3 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 text-sm font-mono"
                required
              >
              <button
                type="submit"
                class="noc-btn-ghost inline-flex items-center rounded-lg px-3 py-2 text-sm font-medium"
                :disabled="loading.tr069Password"
              >
                {{ loading.tr069Password ? 'Encolando…' : 'Aplicar' }}
              </button>
            </div>
          </form>
        </div>
      </section>

      <section class="noc-card mt-6">
        <div class="noc-card-head border-b border-gray-200 dark:border-gray-700 px-4 py-3">
          <div>
            <h2 class="noc-card-title text-base">Registro de actividad reciente</h2>
            <p class="noc-card-sub">{{ registroSubtitulo }} · últimos 30</p>
          </div>
        </div>

        <div class="border-b border-gray-200 px-4 py-4 dark:border-gray-700">
          <div class="mb-3 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <div>
              <p class="text-xs font-semibold uppercase tracking-wide text-gray-600 dark:text-gray-300">Últimas 12 horas · PPPoE</p>
              <p v-if="timeline" class="mt-0.5 text-[11px] text-gray-500 dark:text-gray-400">
                {{ timeline.inicio }} → {{ timeline.fin }}
                · Estado actual:
                <span v-if="timeline.estado_actual === 'up'" class="font-medium text-sky-500 dark:text-sky-400">conectado</span>
                <span v-else-if="timeline.estado_actual === 'down'" class="font-medium text-amber-500 dark:text-amber-400">desconectado</span>
                <span v-else class="font-medium text-gray-500 dark:text-gray-400">sin datos</span>
              </p>
            </div>
            <div v-if="timeline" class="flex flex-wrap items-center gap-x-4 gap-y-1 text-[10px] text-gray-500 dark:text-gray-400">
              <span class="inline-flex items-center gap-1.5">
                <span class="pppoe-timeline-legend pppoe-timeline-legend--up"></span>
                Conectado {{ timeline.conectado_humano }}
                ({{ formatPct(timeline.conectado_pct) }}%)
              </span>
              <span class="inline-flex items-center gap-1.5">
                <span class="pppoe-timeline-legend pppoe-timeline-legend--down"></span>
                Desconectado {{ timeline.desconectado_humano }}
                ({{ formatPct(timeline.desconectado_pct) }}%)
              </span>
              <span class="inline-flex items-center gap-1.5">
                <span class="pppoe-timeline-legend pppoe-timeline-legend--unknown"></span>
                Sin datos {{ timeline.sin_datos_humano }}
                ({{ formatPct(timeline.sin_datos_pct) }}%)
              </span>
            </div>
          </div>

          <div class="pppoe-timeline-track">
            <div
              v-for="(seg, idx) in (timeline?.segmentos || [])"
              :key="'seg-' + idx"
              class="pppoe-timeline-seg"
              :class="timelineSegClass(seg.estado)"
              :style="{ left: seg.left_pct + '%', width: seg.width_pct + '%' }"
              :title="seg.title"
            ></div>
          </div>

          <div v-if="timeline && timeline.marcas && timeline.marcas.length" class="relative mt-1.5 h-3">
            <span
              v-for="(marca, idx) in timeline.marcas"
              :key="'marca-' + idx"
              class="absolute text-[9px] text-gray-400/80 dark:text-gray-500"
              :style="{ left: marca.left_pct + '%', transform: 'translateX(-50%)' }"
            >{{ marca.label }}</span>
          </div>
        </div>

        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
            <thead class="bg-gray-50 dark:bg-gray-900/40">
              <tr>
                <th class="px-3 py-2 text-left text-xs font-medium uppercase text-gray-500">Fecha</th>
                <th class="px-3 py-2 text-left text-xs font-medium uppercase text-gray-500">Tipo</th>
                <th class="px-3 py-2 text-left text-xs font-medium uppercase text-gray-500">Detalle</th>
                <th class="px-3 py-2 text-left text-xs font-medium uppercase text-gray-500">Fuente</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
              <tr
                v-for="(ev, idx) in eventos"
                :key="'ev-' + idx"
                class="hover:bg-gray-50 dark:hover:bg-gray-700/40"
              >
                <td class="whitespace-nowrap px-3 py-2 text-gray-700 dark:text-gray-200">{{ ev.ocurrio_at }}</td>
                <td class="px-3 py-2 font-medium text-gray-900 dark:text-gray-100">
                  <span
                    v-if="ev.badge"
                    class="noc-log-badge"
                    :class="'noc-log-badge--' + ev.badge"
                  >{{ ev.badge_label }}</span>
                  <template v-else>{{ ev.badge_label }}</template>
                </td>
                <td class="px-3 py-2 text-gray-700 dark:text-gray-300">{{ ev.detalle }}</td>
                <td class="px-3 py-2 text-xs text-gray-500">{{ ev.fuente || '—' }}</td>
              </tr>
              <tr v-if="!eventos.length">
                <td colspan="4" class="px-3 py-6 text-center text-gray-500 dark:text-gray-400">
                  Todavía no hay eventos. Se registran al consultar MAC/tráfico o ONU en OLT.
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>

      <p v-if="!compact" class="mt-4 text-xs text-gray-400 dark:text-gray-500">
        Antena Ubiquiti: SSH a la IP del servicio con <span class="font-mono">wstalist</span> (RSSI, noise, CCQ, TX/RX, distancia, MAC remota)
        o <span class="font-mono">cat /tmp/dhcpd.leases</span> (dispositivos conectados al CPE vía DHCP).
        TR-069: GenieACS (serial del servicio) para routers/ONT sin SSH.
        OLT: estrategia principal <span class="font-mono">show address-table gpon 0/{pon}</span> (tabla por PON).
        El download MikroTik se lee de <span class="font-mono">&lt;pppoe-USUARIO&gt;</span>.
      </p>
    </template>
  </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue';

const props = defineProps({
  compact: { type: Boolean, default: false },
  initialPayload: { type: Object, default: null },
  servicios: { type: Array, default: () => [] },
});

const payload = ref(props.initialPayload || null);
const selectedServicioId = ref(
  props.initialPayload?.servicio?.servicio_id ?? props.servicios[0]?.servicio_id ?? ''
);
const loadingDatos = ref(false);
const outPing = ref('');
const pingStats = ref(null);
const outMac = ref('');
const outTrafico = ref('');
const outOlt = ref('');
const outAntena = ref('');
const outAntenaDhcp = ref('');
const outTr069 = ref('');
const tr069Resumen = ref(null);
const tr069Hosts = ref([]);
const tr069PassTarget = ref('wifi-all');
const tr069Password = ref('');
const tr069Password2 = ref('');
const loading = reactive({
  ping: false,
  mac: false,
  trafico: false,
  olt: false,
  oltDesc: false,
  antena: false,
  antenaDhcp: false,
  tr069: false,
  tr069Hosts: false,
  tr069Refresh: false,
  tr069Reboot: false,
  tr069Password: false,
});

let mikrotikCache = null;
let ignoreNextServicioWatch = false;

const servicio = computed(() => payload.value?.servicio || {});
const ultimaOptica = computed(() => payload.value?.ultima_optica || null);
const ultimaAntena = computed(() => payload.value?.ultima_antena || null);
const timeline = computed(() => payload.value?.timeline || null);
const canPing = computed(() => !!servicio.value.ip);
const canMac = computed(() => !!payload.value?.tiene_router);
const canOlt = computed(() => !!payload.value?.es_fibra);
const canOltDesc = computed(() => !!payload.value?.es_fibra && !!servicio.value.desc_onu);
const canAntena = computed(() => !!payload.value?.es_antena);
const esFibra = computed(() => !!payload.value?.es_fibra);
const esAntena = computed(() => !!payload.value?.es_antena);
const tr069Enabled = computed(() => !!payload.value?.tr069_enabled);
const cpeSsh = computed(() => !!payload.value?.cpe_ssh);
const toolsCols = computed(() => {
  let n = 2;
  if (esFibra.value || esAntena.value) n += 1;
  if (tr069Enabled.value) n += 1;
  return n;
});
const tr069TieneDetalle = computed(() => {
  if (tr069Resumen.value && tr069Resumen.value.success) return true;
  return Array.isArray(tr069Hosts.value) && tr069Hosts.value.length > 0;
});
const eventos = computed(() => {
  const all = payload.value?.eventos || [];
  if (esFibra.value) {
    return all.filter((e) => e.tipo !== 'senal_antena');
  }
  if (esAntena.value) {
    return all.filter((e) => e.tipo !== 'senal_optica');
  }
  return all;
});
const registroSubtitulo = computed(() => {
  if (esFibra.value) return 'Señal óptica ONU y eventos PPPoE';
  if (esAntena.value) return 'Señal antena y eventos PPPoE';
  return 'Eventos de conexión';
});
const tr069WifiEnabled = computed(() => {
  const list = tr069Resumen.value?.wifi;
  if (!Array.isArray(list)) return [];
  return list.filter((w) => w && w.enabled);
});

function barPct(dbm) {
  if (dbm === null || dbm === undefined || dbm === '') return 0;
  const min = -95;
  const max = -50;
  const pct = ((Number(dbm) - min) / (max - min)) * 100;
  return Math.max(4, Math.min(100, Math.round(pct)));
}

function formatPct(n) {
  return Number(n ?? 0).toFixed(1).replace('.', ',');
}

function timelineSegClass(estado) {
  if (estado === 'up') return 'pppoe-timeline-seg--up';
  if (estado === 'down') return 'pppoe-timeline-seg--down';
  return 'pppoe-timeline-seg--unknown';
}

function csrfToken() {
  return payload.value?.csrf
    || document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
    || '';
}

function clearResults() {
  outPing.value = '';
  pingStats.value = null;
  outMac.value = '';
  outTrafico.value = '';
  outOlt.value = '';
  outAntena.value = '';
  outAntenaDhcp.value = '';
  outTr069.value = '';
  tr069Resumen.value = null;
  tr069Hosts.value = [];
  tr069PassTarget.value = 'wifi-all';
  tr069Password.value = '';
  tr069Password2.value = '';
  mikrotikCache = null;
}

function servicioItemById(id) {
  return props.servicios.find((s) => String(s.servicio_id) === String(id));
}

function fetchDatos(item) {
  if (!item?.datos_url) return Promise.resolve();
  clearResults();
  const csrf = csrfToken();
  loadingDatos.value = true;
  return fetch(item.datos_url, {
    method: 'GET',
    credentials: 'same-origin',
    headers: {
      Accept: 'application/json',
      'X-Requested-With': 'XMLHttpRequest',
      'X-CSRF-TOKEN': csrf,
    },
  })
    .then((r) => r.json())
    .then((data) => {
      payload.value = data || null;
    })
    .catch(() => {
      payload.value = null;
    })
    .finally(() => {
      loadingDatos.value = false;
    });
}

function escapeHtml(s) {
  return String(s).replace(/[&<>"']/g, function (c) {
    return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]);
  });
}

function errHtml(msg) {
  return '<p class="text-red-600 dark:text-red-400">' + escapeHtml(msg || 'Error') + '</p>';
}

function postJson(url, body) {
  return fetch(url, {
    method: 'POST',
    headers: {
      Accept: 'application/json',
      'Content-Type': 'application/json',
      'X-Requested-With': 'XMLHttpRequest',
      'X-CSRF-TOKEN': csrfToken(),
    },
    body: JSON.stringify(body || {}),
    credentials: 'same-origin',
  }).then(function (r) {
    return r.json().then(function (data) {
      return { ok: r.ok, data: data || {} };
    });
  });
}

function getJson(url) {
  return fetch(url, {
    method: 'GET',
    headers: {
      Accept: 'application/json',
      'X-Requested-With': 'XMLHttpRequest',
      'X-CSRF-TOKEN': csrfToken(),
    },
    credentials: 'same-origin',
  }).then(function (r) {
    return r.json().then(function (data) {
      return { ok: r.ok, data: data || {} };
    });
  });
}

function fetchMikrotik(force) {
  if (!force && mikrotikCache) {
    return Promise.resolve({ ok: true, data: mikrotikCache });
  }
  return postJson(payload.value.urls.mikrotik).then(function (res) {
    if (res.ok && res.data.success) {
      mikrotikCache = res.data;
    }
    return res;
  });
}

function antenaRawHtml(antenaPayload, label) {
  if (!antenaPayload.raw && !antenaPayload.comando) return '';
  var summary = label || 'Salida wstalist';
  var parts = '<details class="mt-3 rounded-lg border border-dashed border-sky-300 bg-sky-50/60 p-2 dark:border-sky-700 dark:bg-sky-950/30">' +
    '<summary class="cursor-pointer text-xs font-semibold text-sky-800 dark:text-sky-300">' + escapeHtml(summary) + '</summary>';
  if (antenaPayload.comando) {
    parts += '<p class="mt-2 text-[11px] text-gray-600 dark:text-gray-400">Comando: <span class="font-mono">' +
      escapeHtml(antenaPayload.comando) + '</span> @ ' + escapeHtml(antenaPayload.host || '') + '</p>';
  }
  if (antenaPayload.raw) {
    parts += '<pre class="mt-1 max-h-56 overflow-auto rounded border border-gray-200 bg-white p-2 text-[11px] font-mono text-gray-800 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-200 whitespace-pre-wrap">' +
      escapeHtml(antenaPayload.raw) + '</pre>';
  }
  parts += '</details>';
  return parts;
}

function antenaDbmBarPercent(dbm) {
  var min = -95;
  var max = -50;
  var pct = ((Number(dbm) - min) / (max - min)) * 100;
  if (pct < 4) return 4;
  if (pct > 100) return 100;
  return Math.round(pct);
}

function antenaChainLabel(chains) {
  if (!Array.isArray(chains) || chains.length === 0) return '';
  return chains.map(function (c) {
    return String(Math.round(c.signal_dbm));
  }).join(' / ');
}

function antenaSignalGaugeHtml(d) {
  if (d.signal_dbm == null && d.noise_floor_dbm == null) return '';

  var chains = Array.isArray(d.signal_chains) ? d.signal_chains : [];
  var chainText = antenaChainLabel(chains);
  var delta = d.chain_delta != null ? Number(d.chain_delta) : null;
  var signalText = d.signal_dbm != null ? String(Math.round(Number(d.signal_dbm))) : '—';
  var noiseText = d.noise_floor_dbm != null ? String(Math.round(Number(d.noise_floor_dbm))) : '—';

  var html = '<div class="ubnt-signal-panel rounded-xl border border-gray-200 dark:border-gray-600 p-4 mb-3">' +
    '<div class="flex items-start justify-between gap-4">' +
    '<div class="min-w-0">' +
    '<div class="text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Señal</div>' +
    '<div class="mt-1 flex flex-wrap items-baseline gap-x-2 gap-y-1">' +
    '<span class="text-3xl font-light text-gray-900 dark:text-gray-100">' + escapeHtml(signalText) + '</span>';

  if (chainText) {
    html += '<span class="text-sm text-gray-600 dark:text-gray-300">(' + escapeHtml(chainText) + ')</span>';
  }
  if (delta != null && chains.length >= 2) {
    html += '<span class="text-sm font-semibold text-emerald-600 dark:text-emerald-400">Δ ' + escapeHtml(String(delta)) + '</span>';
  }
  html += '<span class="text-sm text-gray-500 dark:text-gray-400">dBm</span>' +
    '</div></div>' +
    '<div class="text-right shrink-0">' +
    '<div class="text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Ruido base</div>' +
    '<div class="mt-1 text-lg font-semibold text-gray-800 dark:text-gray-100">' + escapeHtml(noiseText) +
    ' <span class="text-sm font-normal text-gray-500">dBm</span></div>' +
    '</div></div>';

  if (chains.length > 0) {
    html += '<div class="mt-4 space-y-2.5">';
    chains.forEach(function (chain) {
      var pct = antenaDbmBarPercent(chain.signal_dbm);
      html += '<div class="flex items-center gap-2">' +
        '<span class="ubnt-chain-badge">' + escapeHtml(String(chain.chain)) + '</span>' +
        '<div class="ubnt-chain-bar flex-1">' +
        '<div class="ubnt-chain-fill" style="width:' + pct + '%"></div>' +
        '</div>' +
        '<span class="w-10 text-right text-xs font-mono text-gray-600 dark:text-gray-300">' +
        escapeHtml(String(Math.round(chain.signal_dbm))) + '</span>' +
        '</div>';
    });
    html += '</div>';
  } else if (d.signal_dbm != null) {
    var mainPct = antenaDbmBarPercent(d.signal_dbm);
    html += '<div class="mt-4"><div class="ubnt-chain-bar"><div class="ubnt-chain-fill" style="width:' + mainPct + '%"></div></div></div>';
  }

  html += '</div>';
  return html;
}

function antenaDetalleHtml(d) {
  return '<div class="grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-1 text-sm">' +
    '<p><span class="text-gray-500">SNR:</span> <span class="font-mono">' +
    (d.snr_db != null ? escapeHtml(String(d.snr_db)) + ' dB' : '—') + '</span></p>' +
    '<p><span class="text-gray-500">CCQ / score:</span> <span class="font-mono">' +
    (d.ccq != null ? escapeHtml(String(d.ccq)) + '%' : '—') + '</span></p>' +
    '<p><span class="text-gray-500">TX/RX rate:</span> <span class="font-mono">' + escapeHtml(d.tx_rx_rate || '—') + '</span></p>' +
    '<p><span class="text-gray-500">Capacity:</span> <span class="font-mono">' + escapeHtml(d.capacity || '—') + '</span></p>' +
    '<p><span class="text-gray-500">Distancia:</span> <span class="font-mono">' + escapeHtml(d.distance || '—') + '</span></p>' +
    '<p><span class="text-gray-500">MAC remota:</span> <span class="font-mono">' + escapeHtml(d.mac_remota || '—') + '</span></p>' +
    (d.ap_name ? '<p class="sm:col-span-2"><span class="text-gray-500">AP / enlace:</span> <span class="font-semibold">' + escapeHtml(d.ap_name) + '</span></p>' : '') +
    '</div>';
}

function antenaDhcpLeasesHtml(d) {
  var leases = Array.isArray(d.leases) ? d.leases : [];
  if (leases.length === 0) {
    return '<p class="text-amber-600 dark:text-amber-400">' + escapeHtml(d.message || 'Sin leases DHCP.') + '</p>';
  }

  var rows = leases.map(function (lease) {
    return '<tr class="hover:bg-gray-50 dark:hover:bg-gray-700/40">' +
      '<td class="px-2 py-2 font-mono text-gray-900 dark:text-gray-100">' + escapeHtml(lease.ip || '—') + '</td>' +
      '<td class="px-2 py-2 font-mono text-gray-700 dark:text-gray-300">' + escapeHtml(lease.mac || '—') + '</td>' +
      '<td class="px-2 py-2 text-gray-700 dark:text-gray-300">' + escapeHtml(lease.hostname || '—') + '</td>' +
      '<td class="px-2 py-2 text-xs text-gray-500 dark:text-gray-400 whitespace-nowrap">' + escapeHtml(lease.expires_human || '—') + '</td>' +
      '</tr>';
  }).join('');

  return '<div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-600">' +
    '<table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">' +
    '<thead class="bg-gray-50 dark:bg-gray-900/40">' +
    '<tr>' +
    '<th class="px-2 py-2 text-left text-xs font-medium uppercase text-gray-500">IP</th>' +
    '<th class="px-2 py-2 text-left text-xs font-medium uppercase text-gray-500">MAC</th>' +
    '<th class="px-2 py-2 text-left text-xs font-medium uppercase text-gray-500">Hostname</th>' +
    '<th class="px-2 py-2 text-left text-xs font-medium uppercase text-gray-500">Vence</th>' +
    '</tr></thead><tbody class="divide-y divide-gray-200 dark:divide-gray-700">' +
    rows +
    '</tbody></table></div>' +
    '<p class="mt-2 text-xs text-gray-400">' + escapeHtml(d.message || '') + '</p>';
}

function oltCmdResultHtml(oltPayload) {
  var parts = '<details open class="mt-3 rounded-lg border border-dashed border-amber-300 bg-amber-50/60 p-2 dark:border-amber-700 dark:bg-amber-950/30">' +
    '<summary class="cursor-pointer text-xs font-semibold text-amber-800 dark:text-amber-300">Comando y resultado OLT</summary>';
  if (oltPayload.comando) {
    parts += '<p class="mt-2 text-[11px] text-gray-600 dark:text-gray-400">Comando: <span class="font-mono">' +
      escapeHtml(oltPayload.comando) + '</span></p>';
  }
  if (oltPayload.olt || oltPayload.olts_probadas) {
    parts += '<p class="text-[11px] text-gray-600 dark:text-gray-400">OLT: <span class="font-mono">' +
      escapeHtml(oltPayload.olt || (oltPayload.olts_probadas || []).join(', ') || '—') + '</span></p>';
  }
  if (oltPayload.raw_match || oltPayload.raw) {
    parts += '<p class="mt-2 text-[11px] font-medium text-gray-500">Resultado</p>' +
      '<pre class="mt-1 max-h-56 overflow-auto rounded border border-gray-200 bg-white p-2 text-[11px] font-mono text-gray-800 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-200 whitespace-pre-wrap">' +
      escapeHtml(oltPayload.raw_match || oltPayload.raw) + '</pre>';
  }
  parts += '</details>';
  return parts;
}

function onPing() {
  loading.ping = true;
  pingStats.value = null;
  outPing.value = '<p class="text-gray-500 dark:text-gray-400">Consultando…</p>';
  postJson(payload.value.urls.ping).then(function (res) {
    var d = res.data;
    outPing.value = '';
    if (!res.ok && !d.output && d.sent == null) {
      outPing.value = errHtml(d.message);
      return;
    }
    pingStats.value = {
      alive: !!d.alive,
      sent: d.sent != null ? d.sent : (d.packets || 0),
      received: d.received != null ? d.received : 0,
      lost: d.lost != null ? d.lost : 0,
      loss_pct: d.loss_pct != null ? d.loss_pct : (d.alive ? 0 : 100),
      min_ms: d.min_ms != null ? d.min_ms : null,
      max_ms: d.max_ms != null ? d.max_ms : null,
      avg_ms: d.avg_ms != null ? d.avg_ms : null,
      calidad: d.calidad || d.message || '',
      output: d.output || '',
    };
  }).catch(function () {
    pingStats.value = null;
    outPing.value = errHtml('Error de conexión al ejecutar ping.');
  }).finally(function () {
    loading.ping = false;
  });
}

function onMac() {
  loading.mac = true;
  outMac.value = '<p class="text-gray-500 dark:text-gray-400">Consultando MikroTik…</p>';
  fetchMikrotik(true).then(function (res) {
    var d = res.data;
    if (!res.ok || d.success === false) {
      outMac.value = errHtml(d.message);
      return;
    }
    if (!d.mac) {
      outMac.value = '<p class="text-amber-600 dark:text-amber-400">' + escapeHtml(d.message || 'MAC no encontrada.') + '</p>';
      return;
    }
    var html = '<p class="font-mono text-lg font-semibold text-gray-900 dark:text-gray-100">' + escapeHtml(d.mac) + '</p>' +
      '<p class="text-xs text-gray-500 dark:text-gray-400">Fuente: ' + escapeHtml(d.mac_fuente || '—') + '</p>';
    if (d.online) {
      html += '<p class="text-xs text-green-600 dark:text-green-400">Sesión PPPoE activa' + (d.uptime ? ' · uptime ' + escapeHtml(d.uptime) : '') + '</p>';
    }
    if (d.mac_sistema && d.mac_sistema.toUpperCase() !== d.mac.toUpperCase()) {
      html += '<p class="text-xs text-gray-400">MAC en sistema: ' + escapeHtml(d.mac_sistema) + '</p>';
    }
    outMac.value = html;
  }).catch(function () {
    outMac.value = errHtml('Error de conexión con MikroTik.');
  }).finally(function () {
    loading.mac = false;
  });
}

function onTrafico() {
  loading.trafico = true;
  outTrafico.value = '<p class="text-gray-500 dark:text-gray-400">Consultando MikroTik…</p>';
  fetchMikrotik(true).then(function (res) {
    var d = res.data;
    if (!res.ok || d.success === false) {
      outTrafico.value = errHtml(d.message);
      return;
    }
    if (d.download_humano == null && d.upload_humano == null) {
      outTrafico.value = '<p class="text-amber-600 dark:text-amber-400">Sin contadores de tráfico (¿sesión caida o sin queue?).</p>';
      return;
    }
    var html = '<div class="space-y-1">' +
      '<p><span class="text-gray-500 dark:text-gray-400">Download:</span> <span class="font-semibold text-gray-900 dark:text-gray-100">' + escapeHtml(d.download_humano || '—') + '</span></p>' +
      '<p><span class="text-gray-500 dark:text-gray-400">Upload:</span> <span class="font-semibold text-gray-900 dark:text-gray-100">' + escapeHtml(d.upload_humano || '—') + '</span></p>' +
      '<p class="text-xs text-gray-500 dark:text-gray-400">Fuente: ' + escapeHtml(d.trafico_fuente || '—') + '</p>' +
      (d.online ? '<p class="text-xs text-green-600 dark:text-green-400">Sesión activa' + (d.uptime ? ' · ' + escapeHtml(d.uptime) : '') + '</p>' : '') +
      '</div>';
    outTrafico.value = html;
  }).catch(function () {
    outTrafico.value = errHtml('Error de conexión con MikroTik.');
  }).finally(function () {
    loading.trafico = false;
  });
}

function onAntena() {
  var antenaUrl = payload.value?.urls?.antena;
  if (!antenaUrl) return;
  loading.antena = true;
  outAntena.value = '<p class="text-gray-500 dark:text-gray-400">Conectando por SSH y ejecutando wstalist…</p>';
  postJson(antenaUrl).then(function (res) {
    var d = res.data || {};
    if (!res.ok || d.success === false) {
      outAntena.value = errHtml(d.message) + antenaRawHtml(d);
      return;
    }
    var html = antenaSignalGaugeHtml(d) +
      antenaDetalleHtml(d) +
      '<p class="mt-2 text-xs text-gray-400">' + escapeHtml(d.message || '') + '</p>' +
      antenaRawHtml(d);
    outAntena.value = html;
  }).catch(function () {
    outAntena.value = errHtml('Error al consultar la antena por SSH.');
  }).finally(function () {
    loading.antena = false;
  });
}

function onAntenaDhcp() {
  var antenaDhcpUrl = payload.value?.urls?.antena_dhcp;
  if (!antenaDhcpUrl) return;
  loading.antenaDhcp = true;
  outAntenaDhcp.value = '<p class="text-gray-500 dark:text-gray-400">Conectando por SSH y leyendo dhcpd.leases…</p>';
  postJson(antenaDhcpUrl).then(function (res) {
    var d = res.data || {};
    if (!res.ok || d.success === false) {
      outAntenaDhcp.value = errHtml(d.message) + antenaRawHtml(d, 'Salida dhcpd.leases');
      return;
    }
    var html = antenaDhcpLeasesHtml(d) + antenaRawHtml(d, 'Salida dhcpd.leases');
    outAntenaDhcp.value = html;
  }).catch(function () {
    outAntenaDhcp.value = errHtml('Error al consultar DHCP leases por SSH.');
  }).finally(function () {
    loading.antenaDhcp = false;
  });
}

function onTr069Resumen() {
  var url = payload.value?.urls?.tr069;
  if (!url) return;
  loading.tr069 = true;
  outTr069.value = '<p class="text-gray-500 dark:text-gray-400">Consultando GenieACS…</p>';
  tr069Resumen.value = null;
  getJson(url).then(function (res) {
    var d = res.data || {};
    if (!res.ok || d.success === false) {
      outTr069.value = errHtml(d.message);
      return;
    }
    tr069Resumen.value = d;
    outTr069.value = '<p class="text-xs text-gray-400">' + escapeHtml(d.message || '') + (d.via ? ' · vía ' + escapeHtml(d.via) : '') + '</p>';
  }).catch(function () {
    outTr069.value = errHtml('No se pudo contactar GenieACS.');
  }).finally(function () {
    loading.tr069 = false;
  });
}

function onTr069Hosts() {
  var url = payload.value?.urls?.tr069_hosts;
  if (!url) return;
  loading.tr069Hosts = true;
  outTr069.value = '<p class="text-gray-500 dark:text-gray-400">Leyendo hosts LAN del CPE…</p>';
  tr069Hosts.value = [];
  getJson(url).then(function (res) {
    var d = res.data || {};
    if (!res.ok || d.success === false) {
      outTr069.value = errHtml(d.message);
      return;
    }
    tr069Hosts.value = Array.isArray(d.hosts) ? d.hosts : [];
    outTr069.value = '<p class="text-xs text-gray-400">' + escapeHtml(d.message || '') + '</p>';
  }).catch(function () {
    outTr069.value = errHtml('No se pudo leer hosts LAN.');
  }).finally(function () {
    loading.tr069Hosts = false;
  });
}

function onTr069Refresh() {
  var url = payload.value?.urls?.tr069_refresh;
  if (!url) return;
  loading.tr069Refresh = true;
  outTr069.value = '<p class="text-gray-500 dark:text-gray-400">Encolando refresh en el ACS…</p>';
  postJson(url).then(function (res) {
    var d = res.data || {};
    if (!res.ok || d.success === false) {
      outTr069.value = errHtml(d.message);
      return;
    }
    outTr069.value = '<p class="text-green-700 dark:text-green-400 text-sm">' + escapeHtml(d.message || 'Refresh encolado.') + '</p>';
  }).catch(function () {
    outTr069.value = errHtml('No se pudo encolar el refresh.');
  }).finally(function () {
    loading.tr069Refresh = false;
  });
}

function onTr069Reboot() {
  var url = payload.value?.urls?.tr069_reboot;
  if (!url) return;
  if (!confirm('¿Reiniciar el CPE por TR-069? El equipo se desconecta unos minutos.')) return;
  loading.tr069Reboot = true;
  outTr069.value = '<p class="text-gray-500 dark:text-gray-400">Encolando reboot en el ACS…</p>';
  postJson(url).then(function (res) {
    var d = res.data || {};
    if (!res.ok || d.success === false) {
      outTr069.value = errHtml(d.message);
      return;
    }
    outTr069.value = '<p class="text-green-700 dark:text-green-400 text-sm">' + escapeHtml(d.message || 'Reboot encolado.') + '</p>';
  }).catch(function () {
    outTr069.value = errHtml('No se pudo encolar el reboot.');
  }).finally(function () {
    loading.tr069Reboot = false;
  });
}

function onTr069Password() {
  var url = payload.value?.urls?.tr069_password;
  if (!url) return;
  var pass = tr069Password.value || '';
  var pass2 = tr069Password2.value || '';
  if (pass !== pass2) {
    outTr069.value = errHtml('Las claves no coinciden.');
    return;
  }
  var target = tr069PassTarget.value || 'wifi-all';
  var tipo = target === 'admin' ? 'admin' : 'wifi';
  var wifiId = 'all';
  if (tipo === 'wifi' && target.indexOf('wifi:') === 0) {
    wifiId = target.slice(5);
  }
  var min = tipo === 'admin' ? 4 : 8;
  if (pass.length < min) {
    outTr069.value = errHtml(tipo === 'wifi'
      ? 'La clave WiFi debe tener al menos 8 caracteres.'
      : 'La clave del router debe tener al menos 4 caracteres.');
    return;
  }
  loading.tr069Password = true;
  outTr069.value = '<p class="text-gray-500 dark:text-gray-400">Encolando SetParameterValues en el ACS…</p>';
  postJson(url, { tipo: tipo, wifi_id: wifiId, password: pass }).then(function (res) {
    var d = res.data || {};
    if (!res.ok || d.success === false) {
      outTr069.value = errHtml(d.message);
      return;
    }
    tr069Password.value = '';
    tr069Password2.value = '';
    outTr069.value = '<p class="text-green-700 dark:text-green-400 text-sm">' + escapeHtml(d.message || 'Clave encolada.') + '</p>';
  }).catch(function () {
    outTr069.value = errHtml('No se pudo encolar el cambio de clave.');
  }).finally(function () {
    loading.tr069Password = false;
  });
}

function onOlt() {
  var oltUrl = payload.value?.urls?.olt;
  if (!oltUrl) return;
  loading.olt = true;
  outOlt.value = '<p class="text-gray-500 dark:text-gray-400">Consultando MikroTik + OLT (puede tardar)…</p>';
  postJson(oltUrl).then(function (res) {
    var d = res.data;
    if (!res.ok || d.success === false) {
      outOlt.value = errHtml(d.message) + oltCmdResultHtml(d);
      return;
    }
    var html = '<div class="space-y-1">' +
      '<p class="font-mono text-sm font-semibold text-gray-900 dark:text-gray-100">' + escapeHtml(d.mac || '') + '</p>' +
      '<p class="text-xs text-gray-500">MAC fuente: ' + escapeHtml(d.mac_fuente || '—') +
      (d.olt ? ' · OLT ' + escapeHtml(d.olt) : '') + '</p>' +
      '<p><span class="text-gray-500">PON/ONU:</span> <span class="font-semibold text-gray-900 dark:text-gray-100">' +
      (d.pon_port != null && d.onu_index != null ? escapeHtml(String(d.pon_port) + ':' + String(d.onu_index)) : '—') +
      '</span></p>' +
      '<p><span class="text-gray-500">Estado:</span> ' + escapeHtml(d.estado || '—') + '</p>' +
      '<p><span class="text-gray-500">Descripción:</span> ' + escapeHtml(d.descripcion || '—') + '</p>' +
      '<p><span class="text-gray-500">RX:</span> <span class="font-mono font-semibold">' +
      (d.rx_power_dbm != null ? escapeHtml(String(d.rx_power_dbm)) + ' dBm' : '—') +
      '</span></p>' +
      '<p class="text-xs text-gray-400">' + escapeHtml(d.message || '') + '</p>' +
      '</div>' + oltCmdResultHtml(d);
    outOlt.value = html;
  }).catch(function () {
    outOlt.value = errHtml('Error al consultar OLT.');
  }).finally(function () {
    loading.olt = false;
  });
}

function onOltDesc() {
  var oltDescUrl = payload.value?.urls?.olt_desc;
  if (!oltDescUrl) return;
  var label = servicio.value.desc_onu || 'usuario PPPoE';
  if (!confirm('¿Escribir en la OLT la descripción de la ONU como «' + label + '»?')) return;
  loading.oltDesc = true;
  outOlt.value = '<p class="text-gray-500 dark:text-gray-400">Localizando ONU y aplicando descripción…</p>';
  postJson(oltDescUrl).then(function (res) {
    var d = res.data || {};
    if (!res.ok || d.success === false) {
      outOlt.value = errHtml(d.message) + oltCmdResultHtml(d);
      return;
    }
    var html = '<div class="space-y-1">' +
      '<p class="text-green-700 dark:text-green-400 font-medium">' + escapeHtml(d.message || 'OK') + '</p>' +
      '<p><span class="text-gray-500">PON/ONU:</span> <span class="font-semibold">' +
      (d.pon_port != null && d.onu_index != null ? escapeHtml(String(d.pon_port) + ':' + String(d.onu_index)) : '—') +
      '</span></p>' +
      '<p><span class="text-gray-500">Desc escrita:</span> <span class="font-mono font-semibold">' + escapeHtml(d.descripcion || '') + '</span></p>' +
      '<p><span class="text-gray-500">Desc leída:</span> <span class="font-mono">' + escapeHtml(d.descripcion_leida || '—') + '</span></p>' +
      '<p class="text-xs text-gray-500">OLT ' + escapeHtml(d.olt || '—') + ' · MAC ' + escapeHtml(d.mac || '—') + '</p>' +
      '</div>' + oltCmdResultHtml(d);
    outOlt.value = html;
  }).catch(function () {
    outOlt.value = errHtml('Error al aplicar descripción en OLT.');
  }).finally(function () {
    loading.oltDesc = false;
  });
}

watch(selectedServicioId, (id, prev) => {
  if (ignoreNextServicioWatch) {
    ignoreNextServicioWatch = false;
    return;
  }
  if (prev === undefined || String(id) === String(prev)) return;
  const item = servicioItemById(id);
  if (item) fetchDatos(item);
});

watch(
  () => props.initialPayload,
  (val) => {
    if (!val) return;
    payload.value = val;
    const id = val.servicio?.servicio_id;
    if (id != null && String(id) !== String(selectedServicioId.value)) {
      ignoreNextServicioWatch = true;
      selectedServicioId.value = id;
    }
  }
);

onMounted(() => {
  if (payload.value) return;
  const item = servicioItemById(selectedServicioId.value) || props.servicios[0];
  if (!item) return;
  if (String(selectedServicioId.value) === String(item.servicio_id)) {
    fetchDatos(item);
  } else {
    selectedServicioId.value = item.servicio_id;
  }
});
</script>
