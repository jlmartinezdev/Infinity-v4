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
      <div
        v-if="wifiBackup"
        class="mb-4 rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-800 px-4 py-3"
      >
        <div class="flex items-center justify-between">
          <p class="text-xs font-semibold uppercase tracking-wide text-gray-600 dark:text-gray-300">Backup Wi‑Fi (app)</p>
          <button
            v-if="wifiBackup.password"
            type="button"
            class="noc-copy-pill text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200"
            title="Copiar contraseña WiFi"
            @click="copyText(wifiBackup.password, 'wifi')"
          >
            <svg v-if="!copiedWifi" class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3"/></svg>
            <svg v-else class="h-3 w-3 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
            <span class="text-[11px]">{{ copiedWifi ? '¡Copiado!' : 'Copiar Clave' }}</span>
          </button>
        </div>
        <p class="mt-1 text-sm text-gray-800 dark:text-gray-100">
          <span v-if="wifiBackup.ssid" class="font-medium">{{ wifiBackup.ssid }}</span>
          <span v-else>Clave guardada</span>
          <span v-if="wifiBackup.changed_at" class="text-xs text-gray-500 dark:text-gray-400"> · {{ wifiBackup.changed_at }}</span>
        </p>
        <div class="mt-2 flex items-center gap-2">
          <code class="font-mono text-sm text-gray-900 dark:text-gray-100 break-all">{{ wifiBackupVisible ? wifiBackup.password : '••••••••' }}</code>
          <button
            type="button"
            class="text-xs font-medium text-blue-600 dark:text-blue-400 hover:underline"
            @click="wifiBackupVisible = !wifiBackupVisible"
          >{{ wifiBackupVisible ? 'Ocultar' : 'Mostrar' }}</button>
        </div>
      </div>

      <!-- Auto-Diagnóstico Rápido 1-Click -->
      <div class="mb-4 rounded-xl border border-slate-200 dark:border-slate-700/80 bg-gradient-to-r from-slate-50 via-white to-blue-50/30 dark:from-slate-800 dark:via-slate-800/90 dark:to-slate-800/60 p-3.5 shadow-sm">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
          <div class="flex items-center gap-3">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-blue-600 text-white shadow-md shadow-blue-500/20">
              <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
            </div>
            <div>
              <div class="flex items-center gap-2">
                <h3 class="text-sm font-bold text-gray-900 dark:text-gray-100">Diagnóstico Rápido NOC</h3>
                <span class="inline-flex items-center rounded-md bg-blue-50 dark:bg-blue-900/40 px-2 py-0.5 text-[10px] font-semibold text-blue-700 dark:text-blue-300">
                  1-Click Health Check
                </span>
              </div>
              <p class="text-xs text-gray-500 dark:text-gray-400">Prueba ping CPE, sesión MikroTik y estado de señal en simultáneo.</p>
            </div>
          </div>
          <div class="flex items-center gap-2">
            <button
              v-if="diagnosticoResultado"
              type="button"
              class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 px-3 py-2 text-xs font-semibold text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-600 transition-colors shadow-sm"
              title="Copiar resumen estructurado para WhatsApp o Ticket"
              @click="copyDiagnosticoResumen"
            >
              <svg v-if="!copiedDiag" class="h-3.5 w-3.5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3"/></svg>
              <svg v-else class="h-3.5 w-3.5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
              <span>{{ copiedDiag ? '¡Copiado!' : 'Copiar Resumen' }}</span>
            </button>
            <button
              type="button"
              class="inline-flex items-center gap-2 rounded-lg bg-blue-600 hover:bg-blue-700 px-3.5 py-2 text-xs font-semibold text-white shadow-sm shadow-blue-600/30 transition-all active:scale-95 disabled:opacity-50"
              :disabled="isDiagnosing || (!canPing && !canMac)"
              @click="runAutoDiagnostico"
            >
              <svg v-if="!isDiagnosing" class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 1 0 0118 0z"/></svg>
              <svg v-else class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
              <span>{{ isDiagnosing ? diagnosticoStep : (diagnosticoResultado ? 'Re-ejecutar Diagnóstico' : 'Ejecutar Diagnóstico') }}</span>
            </button>
          </div>
        </div>

        <!-- Panel de resultado del diagnóstico -->
        <div
          v-if="diagnosticoResultado"
          class="mt-3 rounded-lg border p-3"
          :class="[
            diagnosticoResultado.status === 'optimo'
              ? 'border-emerald-200 bg-emerald-50/50 dark:border-emerald-800/60 dark:bg-emerald-950/20'
              : (diagnosticoResultado.status === 'advertencia'
                ? 'border-amber-200 bg-amber-50/50 dark:border-amber-800/60 dark:bg-amber-950/20'
                : 'border-rose-200 bg-rose-50/50 dark:border-rose-800/60 dark:bg-rose-950/20')
          ]"
        >
          <div class="flex items-start justify-between gap-2">
            <div class="flex items-center gap-2">
              <span class="noc-pulse-beacon">
                <span
                  class="noc-pulse-beacon-ping"
                  :class="diagnosticoResultado.status === 'optimo' ? 'bg-emerald-400' : (diagnosticoResultado.status === 'advertencia' ? 'bg-amber-400' : 'bg-rose-400')"
                ></span>
                <span
                  class="noc-pulse-beacon-dot"
                  :class="diagnosticoResultado.status === 'optimo' ? 'bg-emerald-500' : (diagnosticoResultado.status === 'advertencia' ? 'bg-amber-500' : 'bg-rose-500')"
                ></span>
              </span>
              <h4
                class="text-xs font-bold uppercase tracking-wider"
                :class="diagnosticoResultado.status === 'optimo' ? 'text-emerald-800 dark:text-emerald-300' : (diagnosticoResultado.status === 'advertencia' ? 'text-amber-800 dark:text-amber-300' : 'text-rose-800 dark:text-rose-300')"
              >
                {{ diagnosticoResultado.titulo }}
              </h4>
              <span class="text-[10px] text-gray-500 dark:text-gray-400">· {{ diagnosticoResultado.timestamp }}</span>
            </div>
          </div>
          <p class="mt-1 text-xs text-gray-700 dark:text-gray-300">{{ diagnosticoResultado.resumen }}</p>

          <!-- 4 KPIs del diagnóstico -->
          <div class="mt-2.5 grid grid-cols-2 sm:grid-cols-4 gap-2">
            <!-- KPI 1: Ping / Latencia -->
            <div class="rounded-md border border-white/60 bg-white/70 p-2 dark:border-gray-700/50 dark:bg-gray-800/60 shadow-2xs">
              <span class="text-[10px] font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Ping CPE</span>
              <p class="mt-0.5 text-xs font-bold text-gray-900 dark:text-gray-100 flex items-center gap-1.5">
                <template v-if="diagnosticoResultado.ping">
                  <span>{{ diagnosticoResultado.ping.avg_ms != null ? diagnosticoResultado.ping.avg_ms + ' ms' : (diagnosticoResultado.ping.alive ? 'Online' : 'Sin resp.') }}</span>
                  <span
                    class="inline-block h-1.5 w-1.5 rounded-full"
                    :class="diagnosticoResultado.ping.loss_pct === 0 ? 'bg-emerald-500' : (diagnosticoResultado.ping.loss_pct < 50 ? 'bg-amber-500' : 'bg-rose-500')"
                  ></span>
                </template>
                <template v-else>—</template>
              </p>
              <p v-if="diagnosticoResultado.ping" class="text-[10px] text-gray-500 dark:text-gray-400 truncate">
                {{ diagnosticoResultado.ping.loss_pct }}% pérdida ({{ diagnosticoResultado.ping.received }}/{{ diagnosticoResultado.ping.sent }})
              </p>
            </div>

            <!-- KPI 2: Sesión MikroTik -->
            <div class="rounded-md border border-white/60 bg-white/70 p-2 dark:border-gray-700/50 dark:bg-gray-800/60 shadow-2xs">
              <span class="text-[10px] font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Sesión PPPoE</span>
              <p class="mt-0.5 text-xs font-bold text-gray-900 dark:text-gray-100 flex items-center gap-1.5">
                <template v-if="diagnosticoResultado.mikrotik">
                  <span :class="diagnosticoResultado.mikrotik.online ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400'">
                    {{ diagnosticoResultado.mikrotik.online ? 'Activa' : 'Desconectada' }}
                  </span>
                </template>
                <template v-else>—</template>
              </p>
              <p v-if="diagnosticoResultado.mikrotik?.uptime" class="text-[10px] text-gray-500 dark:text-gray-400 truncate">
                Uptime: {{ diagnosticoResultado.mikrotik.uptime }}
              </p>
              <p v-else-if="diagnosticoResultado.mikrotik" class="text-[10px] text-gray-500 dark:text-gray-400 truncate">
                {{ diagnosticoResultado.mikrotik.online ? 'Sin uptime' : 'No responde PPP' }}
              </p>
            </div>

            <!-- KPI 3: Tráfico MikroTik -->
            <div class="rounded-md border border-white/60 bg-white/70 p-2 dark:border-gray-700/50 dark:bg-gray-800/60 shadow-2xs">
              <span class="text-[10px] font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Tráfico Actual</span>
              <p class="mt-0.5 text-xs font-bold text-gray-900 dark:text-gray-100">
                <template v-if="diagnosticoResultado.mikrotik?.download_humano || diagnosticoResultado.mikrotik?.upload_humano">
                  <span class="text-blue-600 dark:text-blue-400">↓ {{ diagnosticoResultado.mikrotik.download_humano || '0' }}</span>
                </template>
                <template v-else>En espera</template>
              </p>
              <p v-if="diagnosticoResultado.mikrotik?.upload_humano" class="text-[10px] text-purple-600 dark:text-purple-400 truncate">
                ↑ {{ diagnosticoResultado.mikrotik.upload_humano }}
              </p>
            </div>

            <!-- KPI 4: Señal (Óptica / Antena) -->
            <div class="rounded-md border border-white/60 bg-white/70 p-2 dark:border-gray-700/50 dark:bg-gray-800/60 shadow-2xs">
              <span class="text-[10px] font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                {{ esFibra ? 'Potencia Óptica' : (esAntena ? 'Señal Antena' : 'Parámetro CPE') }}
              </span>
              <p class="mt-0.5 text-xs font-bold text-gray-900 dark:text-gray-100">
                <template v-if="diagnosticoResultado.signal?.tipo === 'fibra' && diagnosticoResultado.signal.rx != null">
                  <span :class="rxQuality(diagnosticoResultado.signal.rx).color">
                    {{ diagnosticoResultado.signal.rx }} dBm
                  </span>
                </template>
                <template v-else-if="diagnosticoResultado.signal?.tipo === 'antena' && diagnosticoResultado.signal.signal != null">
                  <span class="text-sky-600 dark:text-sky-400">
                    {{ diagnosticoResultado.signal.signal }} dBm
                  </span>
                </template>
                <template v-else>—</template>
              </p>
              <p v-if="diagnosticoResultado.signal?.tipo === 'fibra' && diagnosticoResultado.signal.rx != null" class="text-[10px] text-gray-500 dark:text-gray-400 truncate">
                {{ rxQuality(diagnosticoResultado.signal.rx).label }}
              </p>
              <p v-else-if="diagnosticoResultado.signal?.tipo === 'antena' && diagnosticoResultado.signal.ccq != null" class="text-[10px] text-gray-500 dark:text-gray-400 truncate">
                CCQ {{ diagnosticoResultado.signal.ccq }}%
              </p>
            </div>
          </div>
        </div>
      </div>

      <div class="noc-tools-grid" :class="{ 'is-4': toolsCols >= 4 }">
        <!-- Ping -->
        <section class="noc-card">
          <div class="noc-card-head">
            <span class="noc-icon noc-icon--blue">
              <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
            </span>
            <div class="flex-1 min-w-0">
              <div class="flex items-center justify-between">
                <h2 class="noc-card-title">Ping CPE</h2>
                <button
                  v-if="pingStats"
                  type="button"
                  class="noc-copy-pill text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200"
                  title="Copiar resultado ping"
                  @click="copyPingStats"
                >
                  <svg v-if="!copiedPing" class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3"/></svg>
                  <svg v-else class="h-3 w-3 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                  <span class="text-[11px]">{{ copiedPing ? '¡Copiado!' : 'Copiar' }}</span>
                </button>
              </div>
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
              <div class="flex flex-wrap items-center justify-between gap-2">
                <div class="flex items-center gap-1.5">
                  <span
                    class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-semibold"
                    :class="pingQuality(pingStats.avg_ms, pingStats.loss_pct).badge"
                  >
                    <span class="h-1.5 w-1.5 rounded-full" :class="pingQuality(pingStats.avg_ms, pingStats.loss_pct).dot"></span>
                    {{ pingQuality(pingStats.avg_ms, pingStats.loss_pct).label }}
                  </span>
                  <span
                    v-if="pingStats.alive"
                    class="inline-flex rounded-full bg-green-100 px-2 py-0.5 text-xs font-semibold text-green-800 dark:bg-green-900/30 dark:text-green-300"
                  >Responde</span>
                  <span
                    v-else
                    class="inline-flex rounded-full bg-red-100 px-2 py-0.5 text-xs font-semibold text-red-800 dark:bg-red-900/30 dark:text-red-300"
                  >Sin respuesta</span>
                </div>
              </div>
              <p class="text-xs text-gray-600 dark:text-gray-300">{{ pingStats.calidad }}</p>
              <div class="grid grid-cols-2 gap-2">
                <div class="rounded-md border border-gray-100 bg-gray-50/60 p-2 dark:border-gray-700/60 dark:bg-gray-800/40">
                  <p class="noc-metric-label">Paquetes</p>
                  <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">
                    {{ pingStats.received }} de {{ pingStats.sent }}
                  </p>
                </div>
                <div class="rounded-md border border-gray-100 bg-gray-50/60 p-2 dark:border-gray-700/60 dark:bg-gray-800/40">
                  <p class="noc-metric-label">Pérdida</p>
                  <p
                    class="text-sm font-semibold"
                    :class="pingStats.loss_pct > 0 ? 'noc-metric--warn' : 'text-emerald-600 dark:text-emerald-400'"
                  >{{ pingStats.loss_pct }}%</p>
                </div>
                <div v-if="pingStats.avg_ms != null" class="rounded-md border border-gray-100 bg-gray-50/60 p-2 dark:border-gray-700/60 dark:bg-gray-800/40">
                  <p class="noc-metric-label">Promedio</p>
                  <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ pingStats.avg_ms }} ms</p>
                </div>
                <div v-if="pingStats.min_ms != null || pingStats.max_ms != null" class="rounded-md border border-gray-100 bg-gray-50/60 p-2 dark:border-gray-700/60 dark:bg-gray-800/40">
                  <p class="noc-metric-label">Mín / Máx</p>
                  <p class="text-sm font-semibold text-gray-900 dark:text-gray-100 font-mono text-xs">
                    {{ pingStats.min_ms != null ? pingStats.min_ms : '—' }} / {{ pingStats.max_ms != null ? pingStats.max_ms : '—' }} ms
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
            <div class="flex-1 min-w-0">
              <div class="flex items-center justify-between">
                <h2 class="noc-card-title">MAC Address</h2>
                <button
                  v-if="servicio.mac_address || mikrotikMac"
                  type="button"
                  class="noc-copy-pill text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200"
                  title="Copiar dirección MAC"
                  @click="copyText(mikrotikMac || servicio.mac_address, 'mac')"
                >
                  <svg v-if="!copiedMac" class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3"/></svg>
                  <svg v-else class="h-3 w-3 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                  <span class="text-[11px]">{{ copiedMac ? '¡Copiado!' : 'Copiar' }}</span>
                </button>
              </div>
              <p class="noc-card-sub">Consulta MikroTik RouterOS</p>
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
              <p class="noc-card-sub">Señal óptica OLT GPON</p>
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
                  <div class="flex items-center justify-between">
                    <p class="noc-metric-label">RX Power</p>
                    <span class="text-[10px] font-semibold" :class="rxQuality(ultimaOptica.rx_power_dbm).color">
                      {{ rxQuality(ultimaOptica.rx_power_dbm).label }}
                    </span>
                  </div>
                  <p :class="Number(ultimaOptica.rx_power_dbm) <= -27 ? 'noc-metric noc-metric--warn' : 'noc-metric noc-metric--amber'">
                    {{ ultimaOptica.rx_power_dbm }} <span class="text-sm font-normal">dBm</span>
                  </p>
                </div>
              </div>

              <!-- Optical Gauge Bar -->
              <div v-if="ultimaOptica.rx_power_dbm != null" class="optical-gauge-container">
                <div class="optical-gauge-track">
                  <div
                    class="optical-gauge-needle"
                    :style="{ left: rxGaugePercent(ultimaOptica.rx_power_dbm) + '%' }"
                    :title="'Potencia RX: ' + ultimaOptica.rx_power_dbm + ' dBm'"
                  ></div>
                </div>
                <div class="optical-gauge-labels">
                  <span>-30 dBm (Crítico)</span>
                  <span>-25 dBm</span>
                  <span>-20 dBm (Óptimo)</span>
                  <span>-10 dBm</span>
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

      <section v-if="huaweiOnu" class="noc-card noc-card--float mt-4">
        <div class="noc-card-head">
          <span class="noc-icon noc-icon--blue">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 11V7a4 4 0 118 0v4M5 11h14v10H5V11z"/></svg>
          </span>
          <div class="flex-1 min-w-0">
            <div class="flex items-center gap-2">
              <h2 class="noc-card-title">ONU Huawei</h2>
              <span v-if="servicio.ipv6_configurado" class="noc-live-badge">IPv6</span>
            </div>
            <p class="noc-card-sub">IPv6 por web (DHCPv6-PD) · SSH/Telnet/web · sin TR-069</p>
          </div>
        </div>
        <div class="noc-card-body space-y-4">
          <div class="space-y-2">
            <p class="text-xs text-gray-500 dark:text-gray-400">
              SSH, Telnet o web en la ONU
              <span v-if="servicio.ip" class="font-mono text-gray-800 dark:text-gray-200">{{ servicio.ip }}</span>
              <span v-else class="text-amber-600 dark:text-amber-400">· el servicio no tiene IP</span>
              <template v-if="servicio.equipo_resumen"> · {{ servicio.equipo_resumen }}</template>
            </p>
            <div v-if="huaweiOptica" class="grid grid-cols-3 gap-3">
              <div v-if="huaweiOptica.rx_power_dbm != null">
                <p class="noc-metric-label">RX Power</p>
                <p :class="Number(huaweiOptica.rx_power_dbm) <= -27 ? 'noc-metric noc-metric--warn' : 'noc-metric noc-metric--amber'">
                  {{ huaweiOptica.rx_power_dbm }} <span class="text-sm font-normal">dBm</span>
                </p>
              </div>
              <div v-if="huaweiOptica.tx_power_dbm != null">
                <p class="noc-metric-label">TX Power</p>
                <p class="noc-metric noc-metric--blue">{{ huaweiOptica.tx_power_dbm }} <span class="text-sm font-normal">dBm</span></p>
              </div>
              <div v-if="huaweiOptica.temperatura_c != null">
                <p class="noc-metric-label">Temp</p>
                <p class="noc-metric">{{ huaweiOptica.temperatura_c }} <span class="text-sm font-normal">°C</span></p>
              </div>
            </div>
            <div class="flex flex-wrap items-center gap-2">
              <button
                type="button"
                class="noc-btn-ghost inline-flex items-center rounded-lg px-3 py-2 text-sm font-medium"
                :disabled="!canHuawei || huaweiBusy"
                @click="onHuaweiIpv6"
              >{{ loading.huaweiIpv6 ? 'Configurando IPv6…' : 'Configurar IPv6' }}</button>
              <button
                type="button"
                class="noc-btn-ghost inline-flex items-center rounded-lg px-3 py-2 text-sm font-medium"
                :disabled="!canHuawei || huaweiBusy"
                @click="onHuaweiConectados"
              >{{ loading.huaweiConectados ? 'Listando…' : 'Listar conectados' }}</button>
              <button
                type="button"
                class="noc-btn-ghost inline-flex items-center rounded-lg px-3 py-2 text-sm font-medium"
                :disabled="!canHuawei || huaweiBusy"
                @click="onHuaweiOptica"
              >{{ loading.huaweiOptica ? 'Leyendo óptica…' : 'Señal óptica' }}</button>
              <button
                type="button"
                class="noc-btn-ghost inline-flex items-center rounded-lg px-3 py-2 text-sm font-medium"
                :disabled="!canHuawei || huaweiBusy"
                @click="onHuaweiReboot"
              >{{ loading.huaweiReboot ? 'Reiniciando…' : 'Reiniciar ONU' }}</button>
              <div ref="huaweiWifiPanel" class="relative ml-auto flex items-center gap-2">
                <span
                  class="inline-flex items-center gap-1.5 min-w-0 text-sm text-gray-800 dark:text-gray-100"
                  :title="huaweiSsidsActuales.length ? huaweiSsidsActuales.join(' · ') : ''"
                >
                  <svg class="h-4 w-4 shrink-0 text-gray-500 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.111 16.404a5.5 5.5 0 017.778 0M12 20h.01m-7.08-7.071c3.904-3.905 10.236-3.905 14.14 0M1.394 9.393c5.857-5.857 15.355-5.857 21.213 0"/>
                  </svg>
                  <span class="truncate font-medium">{{ huaweiSsidVista }}</span>
                </span>
                <button
                  type="button"
                  class="noc-btn-ghost inline-flex items-center rounded-lg px-3 py-2 text-sm font-medium"
                  :disabled="!canHuawei || (huaweiBusy && !huaweiWifiOpen)"
                  :aria-expanded="huaweiWifiOpen"
                  aria-controls="huawei-wifi-float"
                  @click="toggleHuaweiWifi"
                >{{ huaweiWifiOpen ? 'Cerrar' : 'Editar SSID' }}</button>
                <form
                  v-show="huaweiWifiOpen"
                  id="huawei-wifi-float"
                  class="absolute right-0 top-full mt-2 z-30 w-[20rem] rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-800 p-3 space-y-3"
                  @submit.prevent="onHuaweiWifi"
                >
                  <div>
                    <label for="huawei-ssid" class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">SSID</label>
                    <input
                      id="huawei-ssid"
                      v-model="huaweiSsid"
                      type="text"
                      maxlength="32"
                      autocomplete="off"
                      :placeholder="loading.huaweiSsid ? 'Leyendo…' : 'SSID'"
                      :disabled="loading.huaweiSsid"
                      class="w-full py-2 px-3 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 text-sm disabled:opacity-60"
                      required
                    >
                  </div>
                  <div>
                    <div class="flex items-center justify-between gap-2 mb-1">
                      <label for="huawei-pass" class="text-xs font-medium text-gray-600 dark:text-gray-300">Nueva clave</label>
                      <span class="text-xs tabular-nums" :class="huaweiPassword.length < 8 ? 'text-gray-400 dark:text-gray-500' : 'text-gray-600 dark:text-gray-300'">{{ huaweiPassword.length }}/63</span>
                    </div>
                    <div class="relative">
                      <input
                        id="huawei-pass"
                        v-model="huaweiPassword"
                        :type="huaweiPassVisible ? 'text' : 'password'"
                        autocomplete="new-password"
                        minlength="8"
                        maxlength="63"
                        class="w-full py-2 pl-3 pr-10 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 text-sm font-mono"
                        required
                      >
                      <button
                        type="button"
                        class="absolute inset-y-0 right-0 px-2.5 text-gray-500 hover:text-gray-800 dark:text-gray-400 dark:hover:text-gray-100"
                        :aria-label="huaweiPassVisible ? 'Ocultar clave' : 'Mostrar clave'"
                        :aria-pressed="huaweiPassVisible"
                        @click="huaweiPassVisible = !huaweiPassVisible"
                      >
                        <svg v-if="!huaweiPassVisible" class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                        </svg>
                        <svg v-else class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>
                        </svg>
                      </button>
                    </div>
                  </div>
                  <div>
                    <div class="flex items-center justify-between gap-2 mb-1">
                      <label for="huawei-pass2" class="text-xs font-medium text-gray-600 dark:text-gray-300">Repetir clave</label>
                      <span class="text-xs tabular-nums" :class="huaweiPassword2.length < 8 ? 'text-gray-400 dark:text-gray-500' : 'text-gray-600 dark:text-gray-300'">{{ huaweiPassword2.length }}/63</span>
                    </div>
                    <div class="relative">
                      <input
                        id="huawei-pass2"
                        v-model="huaweiPassword2"
                        :type="huaweiPass2Visible ? 'text' : 'password'"
                        autocomplete="new-password"
                        minlength="8"
                        maxlength="63"
                        class="w-full py-2 pl-3 pr-10 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 text-sm font-mono"
                        required
                      >
                      <button
                        type="button"
                        class="absolute inset-y-0 right-0 px-2.5 text-gray-500 hover:text-gray-800 dark:text-gray-400 dark:hover:text-gray-100"
                        :aria-label="huaweiPass2Visible ? 'Ocultar clave' : 'Mostrar clave'"
                        :aria-pressed="huaweiPass2Visible"
                        @click="huaweiPass2Visible = !huaweiPass2Visible"
                      >
                        <svg v-if="!huaweiPass2Visible" class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                        </svg>
                        <svg v-else class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>
                        </svg>
                      </button>
                    </div>
                  </div>
                  <p v-if="huaweiWifiFormError" class="text-xs text-red-600 dark:text-red-400">{{ huaweiWifiFormError }}</p>
                  <div class="flex gap-2">
                    <button
                      type="submit"
                      class="noc-btn-ghost inline-flex flex-1 items-center justify-center rounded-lg px-3 py-2 text-sm font-medium"
                      :disabled="!canHuawei || huaweiBusy"
                    >
                      {{ loading.huaweiWifi ? 'Aplicando…' : 'Aplicar WiFi' }}
                    </button>
                  </div>
                </form>
              </div>
            </div>
          </div>
          <p v-if="huaweiSsidError" class="text-xs text-red-600 dark:text-red-400">
            {{ huaweiSsidError }}
            <button type="button" class="underline underline-offset-2 ml-1" @click="cargarHuaweiSsid">Reintentar</button>
          </p>
          <div v-if="outHuawei" class="text-sm" v-html="outHuawei"></div>
          <div v-if="huaweiDispositivos.length" class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-600">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
              <thead class="bg-gray-50 dark:bg-gray-900/40">
                <tr>
                  <th class="px-2 py-2 text-left text-xs font-medium uppercase text-gray-500">Host</th>
                  <th class="px-2 py-2 text-left text-xs font-medium uppercase text-gray-500">IP</th>
                  <th class="px-2 py-2 text-left text-xs font-medium uppercase text-gray-500">MAC</th>
                  <th class="px-2 py-2 text-left text-xs font-medium uppercase text-gray-500">SSID</th>
                  <th class="px-2 py-2 text-left text-xs font-medium uppercase text-gray-500">Tiempo</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                <tr v-for="(dev, idx) in huaweiDispositivos" :key="'hw-dev-' + idx" class="hover:bg-gray-50 dark:hover:bg-gray-700/40">
                  <td class="px-2 py-2 text-gray-900 dark:text-gray-100 break-all">{{ dev.host || '—' }}</td>
                  <td class="px-2 py-2 font-mono text-gray-900 dark:text-gray-100">{{ dev.ip || '—' }}</td>
                  <td class="px-2 py-2 font-mono text-gray-700 dark:text-gray-300">{{ dev.mac }}</td>
                  <td class="px-2 py-2 text-gray-700 dark:text-gray-300">{{ dev.ssid || '—' }}</td>
                  <td class="px-2 py-2 text-xs text-gray-500 whitespace-nowrap" :title="dev.tiempo ? dev.tiempo + ' s' : ''">{{ formatAsociadoTiempo(dev.tiempo) }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </section>

      <section v-else-if="cpeSsh && esFibra" class="noc-card mt-4">
        <div class="noc-card-head">
          <span class="noc-icon noc-icon--blue">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 11V7a4 4 0 118 0v4M5 11h14v10H5V11z"/></svg>
          </span>
          <div class="flex-1 min-w-0">
            <h2 class="noc-card-title">CPE por SSH</h2>
            <p class="noc-card-sub">ONU con acceso SSH (no Huawei)</p>
          </div>
        </div>
        <div class="noc-card-body space-y-2">
          <p class="text-sm text-gray-700 dark:text-gray-300">
            Este servicio está marcado para comandos por <strong>SSH</strong>, no por ACS.
            <template v-if="servicio.equipo_resumen"> {{ servicio.equipo_resumen }}.</template>
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

        <!-- Filtros de eventos -->
        <div class="px-4 py-2.5 border-b border-gray-200 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-800/40 flex flex-wrap items-center justify-between gap-2 text-xs">
          <div class="flex flex-wrap items-center gap-1.5">
            <span class="text-gray-500 dark:text-gray-400 font-medium mr-1">Filtrar:</span>
            <button
              type="button"
              class="rounded-md px-2.5 py-1 font-medium transition-colors"
              :class="eventsFilter === 'todos' ? 'bg-blue-600 text-white' : 'text-gray-600 dark:text-gray-300 hover:bg-gray-200/60 dark:hover:bg-gray-700'"
              @click="eventsFilter = 'todos'"
            >Todos ({{ eventos.length }})</button>
            <button
              type="button"
              class="rounded-md px-2.5 py-1 font-medium transition-colors"
              :class="eventsFilter === 'pppoe' ? 'bg-blue-600 text-white' : 'text-gray-600 dark:text-gray-300 hover:bg-gray-200/60 dark:hover:bg-gray-700'"
              @click="eventsFilter = 'pppoe'"
            >PPPoE</button>
            <button
              type="button"
              class="rounded-md px-2.5 py-1 font-medium transition-colors"
              :class="eventsFilter === 'optica' ? 'bg-blue-600 text-white' : 'text-gray-600 dark:text-gray-300 hover:bg-gray-200/60 dark:hover:bg-gray-700'"
              @click="eventsFilter = 'optica'"
            >Señal / Óptica</button>
            <button
              type="button"
              class="rounded-md px-2.5 py-1 font-medium transition-colors"
              :class="eventsFilter === 'noc' ? 'bg-blue-600 text-white' : 'text-gray-600 dark:text-gray-300 hover:bg-gray-200/60 dark:hover:bg-gray-700'"
              @click="eventsFilter = 'noc'"
            >NOC / Sistema</button>
          </div>
          <div class="relative w-full sm:w-48">
            <input
              v-model="eventsSearch"
              type="search"
              placeholder="Buscar evento..."
              class="w-full rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-2.5 py-1 text-xs text-gray-900 dark:text-gray-100 placeholder-gray-400 focus:border-blue-500 focus:outline-none"
            >
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
                v-for="(ev, idx) in filteredEventos"
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
              <tr v-if="!filteredEventos.length">
                <td colspan="4" class="px-3 py-6 text-center text-gray-500 dark:text-gray-400">
                  {{ eventos.length ? 'No hay eventos que coincidan con la búsqueda o filtro.' : 'Todavía no hay eventos. Se registran al consultar MAC/tráfico o ONU en OLT.' }}
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
import { computed, onMounted, onUnmounted, reactive, ref, watch } from 'vue';

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
const outHuawei = ref('');
const huaweiSsid = ref('');
const huaweiPassword = ref('');
const huaweiPassword2 = ref('');
const huaweiSsidsActuales = ref([]);
const huaweiSsidError = ref('');
const huaweiWifiOpen = ref(false);
const huaweiWifiFormError = ref('');
const huaweiWifiPanel = ref(null);
const huaweiPassVisible = ref(false);
const huaweiPass2Visible = ref(false);
const huaweiDispositivos = ref([]);
const huaweiOptica = ref(null);
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
  huaweiIpv6: false,
  huaweiConectados: false,
  huaweiWifi: false,
  huaweiSsid: false,
  huaweiOptica: false,
  huaweiReboot: false,
});
let huaweiSsidReq = 0;

let mikrotikCache = null;
let ignoreNextServicioWatch = false;

const isDiagnosing = ref(false);
const diagnosticoStep = ref('');
const diagnosticoResultado = ref(null);
const copiedDiag = ref(false);
const copiedMac = ref(false);
const copiedPing = ref(false);
const copiedWifi = ref(false);
const mikrotikMac = ref('');
const eventsFilter = ref('todos');
const eventsSearch = ref('');

const servicio = computed(() => payload.value?.servicio || {});
const wifiBackup = computed(() => payload.value?.wifi_backup || null);
const wifiBackupVisible = ref(false);
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
const huaweiOnu = computed(() => !!payload.value?.huawei_onu);
const canHuawei = computed(() => huaweiOnu.value && !!servicio.value.ip);
const huaweiBusy = computed(() =>
  loading.huaweiIpv6
  || loading.huaweiConectados
  || loading.huaweiWifi
  || loading.huaweiSsid
  || loading.huaweiOptica
  || loading.huaweiReboot
);
const huaweiSsidVista = computed(() => {
  if (loading.huaweiSsid && !huaweiSsid.value) return 'Leyendo…';
  return huaweiSsid.value || '—';
});
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
const filteredEventos = computed(() => {
  let list = eventos.value;
  if (eventsFilter.value === 'pppoe') {
    list = list.filter((e) => e.tipo === 'conexion' || e.badge === 'up' || e.badge === 'down');
  } else if (eventsFilter.value === 'optica') {
    list = list.filter((e) => e.tipo === 'senal_optica' || e.tipo === 'senal_antena' || e.badge === 'olt');
  } else if (eventsFilter.value === 'noc') {
    list = list.filter((e) => e.tipo !== 'conexion' && e.tipo !== 'senal_optica' && e.tipo !== 'senal_antena');
  }

  if (eventsSearch.value.trim()) {
    const q = eventsSearch.value.trim().toLowerCase();
    list = list.filter((e) =>
      (e.ocurrio_at && e.ocurrio_at.toLowerCase().includes(q))
      || (e.badge_label && e.badge_label.toLowerCase().includes(q))
      || (e.detalle && e.detalle.toLowerCase().includes(q))
      || (e.fuente && e.fuente.toLowerCase().includes(q))
    );
  }
  return list;
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

function formatAsociadoTiempo(raw) {
  const n = parseInt(String(raw ?? ''), 10);
  if (!Number.isFinite(n) || n < 0) {
    return 'desconectado';
  }
  const horas = Math.floor(n / 3600);
  const minutos = Math.floor((n % 3600) / 60);
  const segundos = n % 60;
  if (horas > 0) {
    return minutos > 0 ? `${horas} h ${minutos} min` : `${horas} h`;
  }
  if (minutos > 0) {
    return segundos > 0 ? `${minutos} min ${segundos} s` : `${minutos} min`;
  }
  return `${segundos} s`;
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
  outHuawei.value = '';
  huaweiSsid.value = '';
  huaweiPassword.value = '';
  huaweiPassword2.value = '';
  huaweiSsidsActuales.value = [];
  huaweiSsidError.value = '';
  huaweiWifiOpen.value = false;
  huaweiWifiFormError.value = '';
  huaweiPassVisible.value = false;
  huaweiPass2Visible.value = false;
  huaweiDispositivos.value = [];
  huaweiOptica.value = null;
  wifiBackupVisible.value = false;
  mikrotikCache = null;
  diagnosticoResultado.value = null;
  copiedDiag.value = false;
  copiedMac.value = false;
  copiedPing.value = false;
  copiedWifi.value = false;
  mikrotikMac.value = '';
  eventsSearch.value = '';
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
      if (res.data.mac) {
        mikrotikMac.value = res.data.mac;
      }
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

function rxGaugePercent(dbm) {
  if (dbm === null || dbm === undefined || dbm === '') return 50;
  const val = Number(dbm);
  if (isNaN(val)) return 50;
  // In optical GPON, values are negative:
  // -32 dBm is extreme low / critical (0%)
  // -8 dBm is overload / high (100%)
  const minDbm = -32;
  const maxDbm = -8;
  const pct = ((val - minDbm) / (maxDbm - minDbm)) * 100;
  return Math.max(4, Math.min(96, Math.round(pct)));
}

function rxQuality(dbm) {
  if (dbm === null || dbm === undefined || dbm === '') {
    return { label: 'Sin datos', color: 'text-gray-400', status: 'unknown' };
  }
  const val = Number(dbm);
  if (val <= -27) {
    return { label: 'Crítico / Atenuado', color: 'text-rose-600 dark:text-rose-400', status: 'critical', badge: 'bg-rose-100 text-rose-800 dark:bg-rose-950/40 dark:text-rose-300' };
  }
  if (val <= -24) {
    return { label: 'Límite de Atenuación', color: 'text-amber-600 dark:text-amber-400', status: 'warning', badge: 'bg-amber-100 text-amber-800 dark:bg-amber-950/40 dark:text-amber-300' };
  }
  if (val >= -10) {
    return { label: 'Saturación Alta', color: 'text-amber-600 dark:text-amber-400', status: 'warning', badge: 'bg-amber-100 text-amber-800 dark:bg-amber-950/40 dark:text-amber-300' };
  }
  return { label: 'Potencia Óptima', color: 'text-emerald-600 dark:text-emerald-400', status: 'optimal', badge: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-300' };
}

function pingQuality(avgMs, lossPct) {
  if (lossPct === 100) {
    return { label: 'Sin respuesta', color: 'text-rose-600 dark:text-rose-400', badge: 'bg-rose-100 text-rose-800 dark:bg-rose-950/40 dark:text-rose-300', dot: 'bg-rose-500' };
  }
  if (lossPct > 0) {
    return { label: `Pérdida (${lossPct}%)`, color: 'text-amber-600 dark:text-amber-400', badge: 'bg-amber-100 text-amber-800 dark:bg-amber-950/40 dark:text-amber-300', dot: 'bg-amber-500' };
  }
  if (avgMs == null) {
    return { label: 'En línea', color: 'text-emerald-600 dark:text-emerald-400', badge: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-300', dot: 'bg-emerald-500' };
  }
  const ms = Number(avgMs);
  if (ms < 25) {
    return { label: 'Excelente (<25ms)', color: 'text-emerald-600 dark:text-emerald-400', badge: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-300', dot: 'bg-emerald-500' };
  }
  if (ms < 50) {
    return { label: 'Bueno (25-50ms)', color: 'text-sky-600 dark:text-sky-400', badge: 'bg-sky-100 text-sky-800 dark:bg-sky-950/40 dark:text-sky-300', dot: 'bg-sky-500' };
  }
  if (ms < 90) {
    return { label: 'Moderado (50-90ms)', color: 'text-amber-600 dark:text-amber-400', badge: 'bg-amber-100 text-amber-800 dark:bg-amber-950/40 dark:text-amber-300', dot: 'bg-amber-500' };
  }
  return { label: 'Elevado (>90ms)', color: 'text-rose-600 dark:text-rose-400', badge: 'bg-rose-100 text-rose-800 dark:bg-rose-950/40 dark:text-rose-300', dot: 'bg-rose-500' };
}

function setCopiedState(type) {
  if (type === 'diag') {
    copiedDiag.value = true;
    setTimeout(() => { copiedDiag.value = false; }, 2500);
  } else if (type === 'mac') {
    copiedMac.value = true;
    setTimeout(() => { copiedMac.value = false; }, 2500);
  } else if (type === 'ping') {
    copiedPing.value = true;
    setTimeout(() => { copiedPing.value = false; }, 2500);
  } else if (type === 'wifi') {
    copiedWifi.value = true;
    setTimeout(() => { copiedWifi.value = false; }, 2500);
  }
}

function fallbackCopy(text, type) {
  try {
    const el = document.createElement('textarea');
    el.value = text;
    el.style.position = 'fixed';
    el.style.opacity = '0';
    document.body.appendChild(el);
    el.select();
    document.execCommand('copy');
    document.body.removeChild(el);
    setCopiedState(type);
  } catch (e) {
    window.prompt('Copiar al portapapeles:', text);
  }
}

function copyText(str, type) {
  if (!str) return;
  if (navigator?.clipboard?.writeText) {
    navigator.clipboard.writeText(str).then(() => {
      setCopiedState(type);
    }).catch(() => {
      fallbackCopy(str, type);
    });
  } else {
    fallbackCopy(str, type);
  }
}

function copyPingStats() {
  if (!pingStats.value) return;
  const p = pingStats.value;
  const serv = servicio.value;
  const text = `📡 Ping CPE [${serv.ip || '—'}]: ${p.alive ? 'Online' : 'Sin respuesta'} | Promedio: ${p.avg_ms ?? '—'} ms (Mín: ${p.min_ms ?? '—'} / Máx: ${p.max_ms ?? '—'} ms) | Pérdida: ${p.loss_pct}% (${p.received}/${p.sent})`;
  copyText(text, 'ping');
}

async function runAutoDiagnostico() {
  if (isDiagnosing.value) return;
  isDiagnosing.value = true;
  diagnosticoStep.value = 'Iniciando verificación integral...';
  diagnosticoResultado.value = null;

  let pingRes = null;
  let mkRes = null;
  let signalInfo = null;

  try {
    // 1. Ping
    diagnosticoStep.value = '1/3 · ICMP Ping al CPE...';
    try {
      if (canPing.value) {
        await onPing();
        pingRes = pingStats.value;
      }
    } catch (e) {
      console.error('AutoDiag Ping Error', e);
    }

    // 2. MikroTik
    diagnosticoStep.value = '2/3 · Sesión MikroTik y tráfico...';
    try {
      if (canMac.value) {
        const res = await fetchMikrotik(true);
        if (res.ok && res.data) {
          mkRes = res.data;
          if (mkRes.mac) {
            mikrotikMac.value = mkRes.mac;
            outMac.value = `<p class="font-mono text-lg font-semibold text-gray-900 dark:text-gray-100">${escapeHtml(mkRes.mac)}</p>
              <p class="text-xs text-gray-500 dark:text-gray-400">Fuente: ${escapeHtml(mkRes.mac_fuente || '—')}</p>` +
              (mkRes.online ? `<p class="text-xs text-green-600 dark:text-green-400">Sesión PPPoE activa${mkRes.uptime ? ' · uptime ' + escapeHtml(mkRes.uptime) : ''}</p>` : '');
          }
          if (mkRes.download_humano || mkRes.upload_humano) {
            outTrafico.value = `<div class="space-y-1">
              <p><span class="text-gray-500 dark:text-gray-400">Download:</span> <span class="font-semibold text-gray-900 dark:text-gray-100">${escapeHtml(mkRes.download_humano || '—')}</span></p>
              <p><span class="text-gray-500 dark:text-gray-400">Upload:</span> <span class="font-semibold text-gray-900 dark:text-gray-100">${escapeHtml(mkRes.upload_humano || '—')}</span></p>
              <p class="text-xs text-gray-500 dark:text-gray-400">Fuente: ${escapeHtml(mkRes.trafico_fuente || '—')}</p>
            </div>`;
          }
        }
      }
    } catch (e) {
      console.error('AutoDiag MikroTik Error', e);
    }

    // 3. Signal (OLT or Antena)
    diagnosticoStep.value = '3/3 · Comprobando señal óptica / radio...';
    try {
      if (esFibra.value) {
        if (ultimaOptica.value) {
          signalInfo = {
            tipo: 'fibra',
            rx: ultimaOptica.value.rx_power_dbm,
            tx: ultimaOptica.value.tx_power_dbm,
            pon: ultimaOptica.value.pon_port != null && ultimaOptica.value.onu_index != null ? `${ultimaOptica.value.pon_port}:${ultimaOptica.value.onu_index}` : null,
            fecha: ultimaOptica.value.ocurrio_at,
          };
        }
      } else if (esAntena.value) {
        if (ultimaAntena.value) {
          signalInfo = {
            tipo: 'antena',
            signal: ultimaAntena.value.antena_signal_dbm,
            noise: ultimaAntena.value.noise_floor_dbm,
            ccq: ultimaAntena.value.ccq,
            fecha: ultimaAntena.value.ocurrio_at,
          };
        }
      }
    } catch (e) {
      console.error('AutoDiag Signal Error', e);
    }

    // Calculate evaluation
    let status = 'optimo';
    let issues = [];

    // Evaluate Ping
    if (pingRes) {
      if (!pingRes.alive || pingRes.loss_pct === 100) {
        status = 'critico';
        issues.push('Sin respuesta ICMP (100% pérdida)');
      } else if (pingRes.loss_pct > 0) {
        if (status !== 'critico') status = 'advertencia';
        issues.push(`Pérdida de paquetes (${pingRes.loss_pct}%)`);
      }
      if (pingRes.avg_ms != null && pingRes.avg_ms > 80) {
        if (status !== 'critico') status = 'advertencia';
        issues.push(`Latencia alta (${pingRes.avg_ms} ms)`);
      }
    } else if (canPing.value) {
      if (status !== 'critico') status = 'advertencia';
      issues.push('No se pudo verificar ping');
    }

    // Evaluate MikroTik
    if (mkRes) {
      if (mkRes.online === false) {
        status = 'critico';
        issues.push('Sesión PPPoE desconectada');
      }
    }

    // Evaluate Signal
    if (signalInfo?.tipo === 'fibra' && signalInfo.rx != null) {
      const rxVal = Number(signalInfo.rx);
      if (rxVal <= -27) {
        status = 'critico';
        issues.push(`Potencia óptica crítica (${rxVal} dBm)`);
      } else if (rxVal <= -24) {
        if (status !== 'critico') status = 'advertencia';
        issues.push(`Atenuación óptica al límite (${rxVal} dBm)`);
      }
    } else if (signalInfo?.tipo === 'antena' && signalInfo.signal != null) {
      const sigVal = Number(signalInfo.signal);
      if (sigVal < -78) {
        if (status !== 'critico') status = 'advertencia';
        issues.push(`Señal inalámbrica débil (${sigVal} dBm)`);
      }
    }

    let titulo = 'Enlace Óptimo';
    let resumen = 'Todos los parámetros operativos responden con normalidad.';
    if (status === 'critico') {
      titulo = 'Atención Requerida · Falla Detectada';
      resumen = issues.join(' · ') || 'El servicio presenta cortes o fallas de conectividad.';
    } else if (status === 'advertencia') {
      titulo = 'Enlace Operativo con Advertencias';
      resumen = issues.join(' · ') || 'El servicio responde pero muestra parámetros fuera de lo ideal.';
    }

    diagnosticoResultado.value = {
      status,
      titulo,
      resumen,
      ping: pingRes,
      mikrotik: mkRes,
      signal: signalInfo,
      timestamp: new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' }),
    };

  } finally {
    isDiagnosing.value = false;
    diagnosticoStep.value = '';
  }
}

function copyDiagnosticoResumen() {
  const d = diagnosticoResultado.value;
  if (!d) return;

  const serv = servicio.value;
  let lines = [
    '📋 REPORTE DE DIAGNÓSTICO NOC · INFINITY',
    '──────────────────────────────────────────',
    `Cliente: ${serv.cliente_nombre || ('Servicio #' + serv.servicio_id)}`,
    `IP: ${serv.ip || 'Sin asignar'} | PPPoE: ${serv.usuario_pppoe || '—'}`,
    `Router / Nodo: ${serv.router_nombre || '—'}${serv.nodo ? ' · ' + serv.nodo : ''}`,
    `Estado General: ${d.titulo.toUpperCase()} (${d.status === 'optimo' ? '🟢 ÓPTIMO' : (d.status === 'advertencia' ? '🟡 ADVERTENCIA' : '🔴 CRÍTICO')})`,
    `Detalle: ${d.resumen}`,
    '──────────────────────────────────────────',
  ];

  if (d.ping) {
    lines.push(`• Ping ICMP: ${d.ping.alive ? 'Responde' : 'Sin respuesta'} | Pérdida: ${d.ping.loss_pct}% | Promedio: ${d.ping.avg_ms != null ? d.ping.avg_ms + ' ms' : '—'} (Mín: ${d.ping.min_ms ?? '—'} / Máx: ${d.ping.max_ms ?? '—'})`);
  }

  if (d.mikrotik) {
    lines.push(`• Sesión MikroTik: ${d.mikrotik.online ? 'Online' : 'Desconectado'} | Uptime: ${d.mikrotik.uptime || '—'} | MAC: ${d.mikrotik.mac || '—'}`);
    if (d.mikrotik.download_humano || d.mikrotik.upload_humano) {
      lines.push(`• Tráfico actual: ↓ ${d.mikrotik.download_humano || '0'} / ↑ ${d.mikrotik.upload_humano || '0'}`);
    }
  }

  if (d.signal?.tipo === 'fibra') {
    lines.push(`• Señal Óptica (GPON): RX ${d.signal.rx != null ? d.signal.rx + ' dBm' : '—'} | TX ${d.signal.tx != null ? d.signal.tx + ' dBm' : '—'}${d.signal.pon ? ' | PON ' + d.signal.pon : ''}`);
  } else if (d.signal?.tipo === 'antena') {
    lines.push(`• Señal Antena: ${d.signal.signal != null ? d.signal.signal + ' dBm' : '—'} | Ruido: ${d.signal.noise != null ? d.signal.noise + ' dBm' : '—'}${d.signal.ccq ? ' | CCQ ' + d.signal.ccq + '%' : ''}`);
  }

  lines.push('──────────────────────────────────────────');
  lines.push(`Hora de prueba: ${d.timestamp} · Generado desde NOC Infinity`);

  copyText(lines.join('\n'), 'diag');
}

function onPing() {
  loading.ping = true;
  pingStats.value = null;
  outPing.value = '<p class="text-gray-500 dark:text-gray-400">Consultando…</p>';
  return postJson(payload.value.urls.ping).then(function (res) {
    var d = res.data;
    outPing.value = '';
    if (!res.ok && !d.output && d.sent == null) {
      outPing.value = errHtml(d.message);
      return res;
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
    return res;
  }).catch(function (err) {
    pingStats.value = null;
    outPing.value = errHtml('Error de conexión al ejecutar ping.');
    throw err;
  }).finally(function () {
    loading.ping = false;
  });
}

function onMac() {
  loading.mac = true;
  outMac.value = '<p class="text-gray-500 dark:text-gray-400">Consultando MikroTik…</p>';
  return fetchMikrotik(true).then(function (res) {
    var d = res.data;
    if (!res.ok || d.success === false) {
      outMac.value = errHtml(d.message);
      return res;
    }
    if (!d.mac) {
      outMac.value = '<p class="text-amber-600 dark:text-amber-400">' + escapeHtml(d.message || 'MAC no encontrada.') + '</p>';
      return res;
    }
    mikrotikMac.value = d.mac;
    var html = '<p class="font-mono text-lg font-semibold text-gray-900 dark:text-gray-100">' + escapeHtml(d.mac) + '</p>' +
      '<p class="text-xs text-gray-500 dark:text-gray-400">Fuente: ' + escapeHtml(d.mac_fuente || '—') + '</p>';
    if (d.online) {
      html += '<p class="text-xs text-green-600 dark:text-green-400">Sesión PPPoE activa' + (d.uptime ? ' · uptime ' + escapeHtml(d.uptime) : '') + '</p>';
    }
    if (d.mac_sistema && d.mac_sistema.toUpperCase() !== d.mac.toUpperCase()) {
      html += '<p class="text-xs text-gray-400">MAC en sistema: ' + escapeHtml(d.mac_sistema) + '</p>';
    }
    outMac.value = html;
    return res;
  }).catch(function (err) {
    outMac.value = errHtml('Error de conexión con MikroTik.');
    throw err;
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

function huaweiMsgHtml(d, ok) {
  var cls = ok ? 'text-green-700 dark:text-green-400' : 'text-red-600 dark:text-red-400';
  var extra = '';
  if (d.via) extra += ' · vía ' + escapeHtml(d.via);
  if (d.wan) extra += ' · WAN ' + escapeHtml(d.wan);
  if (Array.isArray(d.ssids) && d.ssids.length) extra += ' · ' + escapeHtml(d.ssids.join(' · '));
  return '<p class="' + cls + ' text-sm">' + escapeHtml(d.message || (ok ? 'Listo.' : 'Error')) +
    (extra ? '<span class="block text-xs text-gray-400 mt-1">' + extra + '</span>' : '') +
    '</p>';
}

function onHuaweiIpv6() {
  var url = payload.value?.urls?.huawei_ipv6;
  if (!url) return;
  loading.huaweiIpv6 = true;
  outHuawei.value = '<p class="text-gray-500 dark:text-gray-400">Habilitando IPv6 con DHCPv6-PD…</p>';
  postJson(url).then(function (res) {
    var d = res.data || {};
    var ok = res.ok && d.success !== false;
    outHuawei.value = huaweiMsgHtml(d, ok);
    if (ok && d.ipv6 && payload.value?.servicio) {
      payload.value.servicio.ipv6_configurado = true;
    }
  }).catch(function () {
    outHuawei.value = errHtml('No se pudo conectar a la ONU Huawei.');
  }).finally(function () {
    loading.huaweiIpv6 = false;
  });
}

function onHuaweiConectados() {
  var url = payload.value?.urls?.huawei_conectados;
  if (!url) return;
  loading.huaweiConectados = true;
  huaweiDispositivos.value = [];
  outHuawei.value = '<p class="text-gray-500 dark:text-gray-400">Cruzando WiFi y DHCP en la ONU…</p>';
  postJson(url).then(function (res) {
    var d = res.data || {};
    var ok = res.ok && d.success !== false;
    outHuawei.value = huaweiMsgHtml(d, ok) + antenaRawHtml(d, 'Salida WiFi + DHCP');
    if (ok) {
      huaweiDispositivos.value = Array.isArray(d.dispositivos) ? d.dispositivos : [];
    }
  }).catch(function () {
    outHuawei.value = errHtml('No se pudo listar los conectados.');
  }).finally(function () {
    loading.huaweiConectados = false;
  });
}

function aplicarSsidLeido(d, overwrite) {
  var list = Array.isArray(d.ssids) ? d.ssids.map(function (s) { return String(s || '').trim(); }).filter(Boolean) : [];
  if (list.length) huaweiSsidsActuales.value = list;
  var ssid = String(d.ssid || '').trim();
  if (ssid && (overwrite || !huaweiSsid.value)) {
    huaweiSsid.value = ssid;
  }
  huaweiSsidError.value = '';
}

function onHuaweiOptica() {
  var url = payload.value?.urls?.huawei_optica;
  if (!url) return;
  loading.huaweiOptica = true;
  outHuawei.value = '<p class="text-gray-500 dark:text-gray-400">Leyendo display optic en la ONU…</p>';
  postJson(url).then(function (res) {
    var d = res.data || {};
    var ok = res.ok && d.success !== false;
    outHuawei.value = huaweiMsgHtml(d, ok);
    if (ok) {
      huaweiOptica.value = {
        rx_power_dbm: d.rx_power_dbm ?? null,
        tx_power_dbm: d.tx_power_dbm ?? null,
        temperatura_c: d.temperatura_c ?? null,
      };
    }
  }).catch(function () {
    outHuawei.value = errHtml('No se pudo leer la óptica de la ONU.');
  }).finally(function () {
    loading.huaweiOptica = false;
  });
}

function onHuaweiReboot() {
  var url = payload.value?.urls?.huawei_reboot;
  if (!url) return;
  if (!confirm('¿Reiniciar la ONU? El equipo se desconecta unos minutos. No es reset de fábrica.')) return;
  loading.huaweiReboot = true;
  outHuawei.value = '<p class="text-gray-500 dark:text-gray-400">Enviando reboot a la ONU…</p>';
  postJson(url).then(function (res) {
    var d = res.data || {};
    var ok = res.ok && d.success !== false;
    outHuawei.value = huaweiMsgHtml(d, ok);
  }).catch(function () {
    outHuawei.value = errHtml('No se pudo reiniciar la ONU.');
  }).finally(function () {
    loading.huaweiReboot = false;
  });
}

function cargarHuaweiSsid() {
  var url = payload.value?.urls?.huawei_ssid;
  if (!url || !canHuawei.value) return;
  if (loading.huaweiConectados || loading.huaweiIpv6 || loading.huaweiWifi || loading.huaweiOptica || loading.huaweiReboot) return;
  var req = ++huaweiSsidReq;
  loading.huaweiSsid = true;
  huaweiSsidError.value = '';
  postJson(url).then(function (res) {
    if (req !== huaweiSsidReq) return;
    var d = res.data || {};
    var ok = res.ok && d.success !== false;
    if (ok) {
      aplicarSsidLeido(d, false);
      return;
    }
    huaweiSsidError.value = d.message || 'No se pudo leer el SSID.';
  }).catch(function () {
    if (req !== huaweiSsidReq) return;
    huaweiSsidError.value = 'No se pudo leer el SSID.';
  }).finally(function () {
    if (req === huaweiSsidReq) loading.huaweiSsid = false;
  });
}

function toggleHuaweiWifi() {
  huaweiWifiOpen.value = !huaweiWifiOpen.value;
  huaweiWifiFormError.value = '';
}

function cerrarHuaweiWifi() {
  huaweiWifiOpen.value = false;
  huaweiWifiFormError.value = '';
  huaweiPassVisible.value = false;
  huaweiPass2Visible.value = false;
}

function onHuaweiWifiDocClick(e) {
  if (!huaweiWifiOpen.value) return;
  var el = huaweiWifiPanel.value;
  if (el && !el.contains(e.target)) cerrarHuaweiWifi();
}

function onHuaweiWifiDocKey(e) {
  if (e.key === 'Escape') cerrarHuaweiWifi();
}

function onHuaweiWifi() {
  var url = payload.value?.urls?.huawei_wifi;
  if (!url) return;
  var ssid = (huaweiSsid.value || '').trim();
  var pass = huaweiPassword.value || '';
  var pass2 = huaweiPassword2.value || '';
  if (pass !== pass2) {
    huaweiWifiFormError.value = 'Las claves no coinciden.';
    return;
  }
  if (ssid.length < 1 || ssid.length > 32) {
    huaweiWifiFormError.value = 'El SSID debe tener entre 1 y 32 caracteres.';
    return;
  }
  if (pass.length < 8 || pass.length > 63) {
    huaweiWifiFormError.value = 'La clave WiFi debe tener entre 8 y 63 caracteres.';
    return;
  }
  huaweiWifiFormError.value = '';
  loading.huaweiWifi = true;
  outHuawei.value = '<p class="text-gray-500 dark:text-gray-400">Aplicando SSID y clave en la ONU…</p>';
  postJson(url, { ssid: ssid, password: pass }).then(function (res) {
    var d = res.data || {};
    var ok = res.ok && d.success !== false;
    outHuawei.value = huaweiMsgHtml(d, ok);
    if (ok) {
      aplicarSsidLeido(d, true);
      huaweiPassword.value = '';
      huaweiPassword2.value = '';
      cerrarHuaweiWifi();
    } else {
      huaweiWifiFormError.value = d.message || 'No se pudo cambiar SSID y contraseña.';
    }
  }).catch(function () {
    huaweiWifiFormError.value = 'No se pudo cambiar SSID y contraseña.';
    outHuawei.value = errHtml('No se pudo cambiar SSID y contraseña.');
  }).finally(function () {
    loading.huaweiWifi = false;
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
  () => (canHuawei.value ? Number(servicio.value.servicio_id) : 0),
  (id) => {
    if (id) cargarHuaweiSsid();
  },
  { immediate: true }
);

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
  document.addEventListener('mousedown', onHuaweiWifiDocClick);
  document.addEventListener('keydown', onHuaweiWifiDocKey);
  if (payload.value) return;
  const item = servicioItemById(selectedServicioId.value) || props.servicios[0];
  if (!item) return;
  if (String(selectedServicioId.value) === String(item.servicio_id)) {
    fetchDatos(item);
  } else {
    selectedServicioId.value = item.servicio_id;
  }
});

onUnmounted(() => {
  document.removeEventListener('mousedown', onHuaweiWifiDocClick);
  document.removeEventListener('keydown', onHuaweiWifiDocKey);
});
</script>
