@extends('layouts.app')

@php
    $clienteNombre = trim(($servicio->cliente?->nombre ?? '').' '.($servicio->cliente?->apellido ?? ''));
    $router = $servicio->pool?->router;
@endphp

@section('title', 'Herramientas de red')

@section('content')
<div class="max-w-5xl mx-auto" id="herramientas-red-app"
     data-ping-url="{{ route('servicios.ping', $servicio->servicio_id) }}"
     data-mikrotik-url="{{ route('servicios.herramientas-red.mikrotik', $servicio->servicio_id) }}"
     data-olt-url="{{ route('servicios.herramientas-red.olt', $servicio->servicio_id) }}"
     data-olt-desc-url="{{ route('servicios.herramientas-red.olt-desc', $servicio->servicio_id) }}"
     data-csrf="{{ csrf_token() }}"
     data-desc-onu="{{ $servicio->usuario_pppoe ?: trim(($servicio->cliente?->nombre ?? '').'_'.($servicio->cliente?->apellido ?? '')) }}">
    <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <a href="{{ route('servicios.index') }}" class="text-sm font-medium text-purple-600 hover:underline dark:text-purple-400">&larr; Volver a servicios</a>
            <h1 class="mt-2 text-2xl font-bold text-gray-900 dark:text-gray-100">Herramientas de red</h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                {{ $clienteNombre !== '' ? $clienteNombre : 'Servicio #'.$servicio->servicio_id }}
                @if($servicio->ip)
                    · IP <span class="font-mono text-gray-800 dark:text-gray-200">{{ $servicio->ip }}</span>
                @endif
                @if($servicio->usuario_pppoe)
                    · PPPoE <span class="font-mono text-gray-800 dark:text-gray-200">{{ $servicio->usuario_pppoe }}</span>
                @endif
            </p>
            <p class="mt-0.5 text-xs text-gray-400 dark:text-gray-500">
                Router: {{ $router?->nombre ?? $router?->ip ?? 'sin pool/router' }}
                @if($router?->nodo)
                    · Nodo {{ $router->nodo->descripcion }}
                @endif
                @if($servicio->mac_address)
                    · MAC en sistema {{ $servicio->mac_address }}
                @endif
            </p>
        </div>
        @if($servicio->servicio_id)
            <a href="{{ route('servicios.edit', $servicio->servicio_id) }}"
               class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700">
                Editar servicio
            </a>
        @endif
    </div>

    <div class="grid grid-cols-1 gap-5 lg:grid-cols-2">
        {{-- Ping --}}
        <section class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <div class="border-b border-gray-200 bg-gray-50 px-4 py-3 dark:border-gray-700 dark:bg-gray-900/40">
                <h2 class="text-sm font-semibold text-gray-800 dark:text-gray-100">Ping CPE / ONU</h2>
                <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">ICMP desde el servidor hacia la IP del servicio</p>
            </div>
            <div class="space-y-3 p-4">
                <button type="button" id="btn-ping"
                        class="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-teal-600 px-3 py-2 text-sm font-medium text-white hover:bg-teal-700 disabled:opacity-50"
                        @disabled(! $servicio->ip)>
                    <svg id="spin-ping" class="hidden h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                    Ejecutar ping
                </button>
                @unless($servicio->ip)
                    <p class="text-xs text-amber-600 dark:text-amber-400">Sin IP asignada.</p>
                @endunless
                <div id="out-ping" class="hidden space-y-2 text-sm"></div>
            </div>
        </section>

        {{-- MAC --}}
        <section class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <div class="border-b border-gray-200 bg-gray-50 px-4 py-3 dark:border-gray-700 dark:bg-gray-900/40">
                <h2 class="text-sm font-semibold text-gray-800 dark:text-gray-100">MAC address</h2>
                <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">Desde MikroTik (PPP active / ARP / DHCP)</p>
            </div>
            <div class="space-y-3 p-4">
                <button type="button" id="btn-mac"
                        class="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50"
                        @disabled(! $router)>
                    <svg id="spin-mac" class="hidden h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                    Consultar MAC
                </button>
                @unless($router)
                    <p class="text-xs text-amber-600 dark:text-amber-400">Sin router asociado al pool.</p>
                @endunless
                <div id="out-mac" class="hidden space-y-2 text-sm"></div>
            </div>
        </section>

        {{-- Tráfico --}}
        <section class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <div class="border-b border-gray-200 bg-gray-50 px-4 py-3 dark:border-gray-700 dark:bg-gray-900/40">
                <h2 class="text-sm font-semibold text-gray-800 dark:text-gray-100">Total download</h2>
                <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">Tráfico de la sesión en MikroTik (PPP / queue)</p>
            </div>
            <div class="space-y-3 p-4">
                <button type="button" id="btn-trafico"
                        class="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-purple-600 px-3 py-2 text-sm font-medium text-white hover:bg-purple-700 disabled:opacity-50"
                        @disabled(! $router)>
                    <svg id="spin-trafico" class="hidden h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                    Consultar tráfico
                </button>
                @unless($router)
                    <p class="text-xs text-amber-600 dark:text-amber-400">Sin router asociado al pool.</p>
                @endunless
                <div id="out-trafico" class="hidden space-y-2 text-sm"></div>
            </div>
        </section>

        {{-- OLT / ONU --}}
        <section class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <div class="border-b border-gray-200 bg-gray-50 px-4 py-3 dark:border-gray-700 dark:bg-gray-900/40">
                <h2 class="text-sm font-semibold text-gray-800 dark:text-gray-100">ONU en OLT (fibra)</h2>
                <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">MAC MikroTik → tabla MAC OLT → PON/ONU → RX / desc</p>
            </div>
            <div class="space-y-3 p-4">
                <button type="button" id="btn-olt"
                        class="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-amber-600 px-3 py-2 text-sm font-medium text-white hover:bg-amber-700 disabled:opacity-50"
                        @disabled(! ($esFibra ?? false))>
                    <svg id="spin-olt" class="hidden h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                    Consultar ONU en OLT
                </button>
                <button type="button" id="btn-olt-desc"
                        class="inline-flex w-full items-center justify-center gap-2 rounded-lg border border-amber-600 bg-white px-3 py-2 text-sm font-medium text-amber-700 hover:bg-amber-50 disabled:opacity-50 dark:border-amber-500 dark:bg-gray-800 dark:text-amber-300 dark:hover:bg-amber-950/40"
                        @disabled(! ($esFibra ?? false) || (blank($servicio->usuario_pppoe) && blank($servicio->cliente?->nombre)))>
                    <svg id="spin-olt-desc" class="hidden h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                    Aplicar desc ONU = {{ $servicio->usuario_pppoe ?: 'nombre cliente' }}
                </button>
                <p class="text-xs text-gray-500 dark:text-gray-400">
                    Escribe en la OLT la descripción de la ONU con el usuario PPPoE del servicio (ej. <span class="font-mono">PEDRO_CIBILS</span>).
                </p>
                @unless($esFibra ?? false)
                    <p class="text-xs text-amber-600 dark:text-amber-400">Solo para servicios de fibra/GPON (nodo GPON, caja NAP o plan fibra).</p>
                @endunless
                <div id="out-olt" class="hidden space-y-2 text-sm"></div>
            </div>
        </section>
    </div>

    <section class="mt-6 overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
        <div class="border-b border-gray-200 bg-gray-50 px-4 py-3 dark:border-gray-700 dark:bg-gray-900/40">
            <h2 class="text-sm font-semibold text-gray-800 dark:text-gray-100">Historial de conexión y señal</h2>
            <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                Señal óptica ONU, señal antena y conexiones/desconexiones PPPoE (últimos 30).
            </p>
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
                    @forelse(($conexionEventos ?? collect()) as $ev)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/40">
                            <td class="whitespace-nowrap px-3 py-2 text-gray-700 dark:text-gray-200">
                                {{ $ev->ocurrio_at?->format('d/m/Y H:i:s') }}
                            </td>
                            <td class="px-3 py-2 font-medium text-gray-900 dark:text-gray-100">
                                {{ $ev->etiquetaTipo() }}
                            </td>
                            <td class="px-3 py-2 text-gray-700 dark:text-gray-300">
                                @if($ev->tipo === \App\Models\ServicioConexionEvento::TIPO_SENAL_OPTICA)
                                    @if($ev->pon_port !== null && $ev->onu_index !== null)
                                        PON {{ $ev->pon_port }}:{{ $ev->onu_index }}
                                    @endif
                                    @if($ev->rx_power_dbm !== null)
                                        · RX <span class="font-mono">{{ $ev->rx_power_dbm }} dBm</span>
                                    @endif
                                    @if($ev->onu_estado)
                                        · {{ $ev->onu_estado }}
                                    @endif
                                    @if($ev->onu_descripcion)
                                        · {{ $ev->onu_descripcion }}
                                    @endif
                                @elseif($ev->tipo === \App\Models\ServicioConexionEvento::TIPO_SENAL_ANTENA)
                                    @if($ev->antena_signal_dbm !== null)
                                        Señal <span class="font-mono">{{ $ev->antena_signal_dbm }} dBm</span>
                                    @endif
                                    @if($ev->antena_snr_db !== null)
                                        · SNR <span class="font-mono">{{ $ev->antena_snr_db }} dB</span>
                                    @endif
                                    @if($ev->antena_radio_iface)
                                        · {{ $ev->antena_radio_iface }}
                                    @endif
                                @else
                                    {{ $ev->pppoe_estado === 'up' ? 'Online' : 'Offline' }}
                                    @if($ev->usuario_pppoe)
                                        · <span class="font-mono">{{ $ev->usuario_pppoe }}</span>
                                    @endif
                                    @if($ev->uptime)
                                        · uptime {{ $ev->uptime }}
                                    @endif
                                    @if($ev->mac_address)
                                        · {{ $ev->mac_address }}
                                    @endif
                                @endif
                            </td>
                            <td class="px-3 py-2 text-xs text-gray-500">{{ $ev->fuente ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-3 py-6 text-center text-gray-500 dark:text-gray-400">
                                Todavía no hay eventos. Se registran al consultar MAC/tráfico o ONU en OLT.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <p class="mt-4 text-xs text-gray-400 dark:text-gray-500">
        En la OLT VSOL se usa <span class="font-mono">show mac address-table address FC1B:D1C2:8C15</span>
        (Port GPON0/XX + ONU ID). El download MikroTik se lee de <span class="font-mono">&lt;pppoe-USUARIO&gt;</span>.
    </p>
</div>
@endsection

@push('scripts')
<script>
(function () {
    var root = document.getElementById('herramientas-red-app');
    if (!root) return;

    var pingUrl = root.getAttribute('data-ping-url');
    var mikrotikUrl = root.getAttribute('data-mikrotik-url');
    var oltUrl = root.getAttribute('data-olt-url');
    var oltDescUrl = root.getAttribute('data-olt-desc-url');
    var csrf = root.getAttribute('data-csrf');
    var mikrotikCache = null;

    function setLoading(btnId, spinId, on) {
        var btn = document.getElementById(btnId);
        var spin = document.getElementById(spinId);
        if (btn) btn.disabled = !!on;
        if (spin) spin.classList.toggle('hidden', !on);
    }

    function showBox(id, html) {
        var el = document.getElementById(id);
        if (!el) return;
        el.classList.remove('hidden');
        el.innerHTML = html;
    }

    function errHtml(msg) {
        return '<p class="text-red-600 dark:text-red-400">' + escapeHtml(msg || 'Error') + '</p>';
    }

    function escapeHtml(s) {
        return String(s).replace(/[&<>"']/g, function (c) {
            return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]);
        });
    }

    function postJson(url) {
        return fetch(url, {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrf
            },
            body: '{}',
            credentials: 'same-origin'
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
        return postJson(mikrotikUrl).then(function (res) {
            if (res.ok && res.data.success) {
                mikrotikCache = res.data;
            }
            return res;
        });
    }

    document.getElementById('btn-ping')?.addEventListener('click', function () {
        setLoading('btn-ping', 'spin-ping', true);
        showBox('out-ping', '<p class="text-gray-500 dark:text-gray-400">Consultando…</p>');
        postJson(pingUrl).then(function (res) {
            var d = res.data;
            if (!res.ok && !d.output) {
                showBox('out-ping', errHtml(d.message));
                return;
            }
            var badge = d.alive
                ? '<span class="inline-flex rounded-full bg-green-100 px-2 py-0.5 text-xs font-semibold text-green-800 dark:bg-green-900/30 dark:text-green-300">Responde</span>'
                : '<span class="inline-flex rounded-full bg-red-100 px-2 py-0.5 text-xs font-semibold text-red-800 dark:bg-red-900/30 dark:text-red-300">Sin respuesta</span>';
            var html = '<div class="flex flex-wrap items-center gap-2">' + badge +
                '<span class="text-gray-600 dark:text-gray-300">' + escapeHtml(d.message || '') + '</span></div>';
            if (d.output) {
                html += '<pre class="mt-2 max-h-48 overflow-auto rounded-lg border border-gray-200 bg-gray-50 p-2 text-xs font-mono text-gray-800 dark:border-gray-600 dark:bg-gray-900/50 dark:text-gray-200 whitespace-pre-wrap">' +
                    escapeHtml(d.output) + '</pre>';
            }
            showBox('out-ping', html);
        }).catch(function () {
            showBox('out-ping', errHtml('Error de conexión al ejecutar ping.'));
        }).finally(function () {
            setLoading('btn-ping', 'spin-ping', false);
        });
    });

    document.getElementById('btn-mac')?.addEventListener('click', function () {
        setLoading('btn-mac', 'spin-mac', true);
        showBox('out-mac', '<p class="text-gray-500 dark:text-gray-400">Consultando MikroTik…</p>');
        fetchMikrotik(true).then(function (res) {
            var d = res.data;
            if (!res.ok || d.success === false) {
                showBox('out-mac', errHtml(d.message));
                return;
            }
            if (!d.mac) {
                showBox('out-mac', '<p class="text-amber-600 dark:text-amber-400">' + escapeHtml(d.message || 'MAC no encontrada.') + '</p>');
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
            showBox('out-mac', html);
        }).catch(function () {
            showBox('out-mac', errHtml('Error de conexión con MikroTik.'));
        }).finally(function () {
            setLoading('btn-mac', 'spin-mac', false);
        });
    });

    document.getElementById('btn-trafico')?.addEventListener('click', function () {
        setLoading('btn-trafico', 'spin-trafico', true);
        showBox('out-trafico', '<p class="text-gray-500 dark:text-gray-400">Consultando MikroTik…</p>');
        fetchMikrotik(true).then(function (res) {
            var d = res.data;
            if (!res.ok || d.success === false) {
                showBox('out-trafico', errHtml(d.message));
                return;
            }
            if (d.download_humano == null && d.upload_humano == null) {
                showBox('out-trafico', '<p class="text-amber-600 dark:text-amber-400">Sin contadores de tráfico (¿sesión caida o sin queue?).</p>');
                return;
            }
            var html = '<div class="space-y-1">' +
                '<p><span class="text-gray-500 dark:text-gray-400">Download:</span> <span class="font-semibold text-gray-900 dark:text-gray-100">' + escapeHtml(d.download_humano || '—') + '</span></p>' +
                '<p><span class="text-gray-500 dark:text-gray-400">Upload:</span> <span class="font-semibold text-gray-900 dark:text-gray-100">' + escapeHtml(d.upload_humano || '—') + '</span></p>' +
                '<p class="text-xs text-gray-500 dark:text-gray-400">Fuente: ' + escapeHtml(d.trafico_fuente || '—') + '</p>' +
                (d.online ? '<p class="text-xs text-green-600 dark:text-green-400">Sesión activa' + (d.uptime ? ' · ' + escapeHtml(d.uptime) : '') + '</p>' : '') +
                '</div>';
            showBox('out-trafico', html);
        }).catch(function () {
            showBox('out-trafico', errHtml('Error de conexión con MikroTik.'));
        }).finally(function () {
            setLoading('btn-trafico', 'spin-trafico', false);
        });
    });

    function oltCmdResultHtml(payload) {
        var parts = '<details open class="mt-3 rounded-lg border border-dashed border-amber-300 bg-amber-50/60 p-2 dark:border-amber-700 dark:bg-amber-950/30">' +
            '<summary class="cursor-pointer text-xs font-semibold text-amber-800 dark:text-amber-300">Comando y resultado OLT</summary>';
        if (payload.comando) {
            parts += '<p class="mt-2 text-[11px] text-gray-600 dark:text-gray-400">Comando: <span class="font-mono">' +
                escapeHtml(payload.comando) + '</span></p>';
        }
        if (payload.olt || payload.olts_probadas) {
            parts += '<p class="text-[11px] text-gray-600 dark:text-gray-400">OLT: <span class="font-mono">' +
                escapeHtml(payload.olt || (payload.olts_probadas || []).join(', ') || '—') + '</span></p>';
        }
        if (payload.raw_match || payload.raw) {
            parts += '<p class="mt-2 text-[11px] font-medium text-gray-500">Resultado</p>' +
                '<pre class="mt-1 max-h-56 overflow-auto rounded border border-gray-200 bg-white p-2 text-[11px] font-mono text-gray-800 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-200 whitespace-pre-wrap">' +
                escapeHtml(payload.raw_match || payload.raw) + '</pre>';
        }
        parts += '</details>';
        return parts;
    }

    document.getElementById('btn-olt')?.addEventListener('click', function () {
        if (!oltUrl) return;
        setLoading('btn-olt', 'spin-olt', true);
        showBox('out-olt', '<p class="text-gray-500 dark:text-gray-400">Consultando MikroTik + OLT (puede tardar)…</p>');
        postJson(oltUrl).then(function (res) {
            var d = res.data;
            if (!res.ok || d.success === false) {
                showBox('out-olt', errHtml(d.message) + oltCmdResultHtml(d));
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
            showBox('out-olt', html);
        }).catch(function () {
            showBox('out-olt', errHtml('Error al consultar OLT.'));
        }).finally(function () {
            setLoading('btn-olt', 'spin-olt', false);
        });
    });

    document.getElementById('btn-olt-desc')?.addEventListener('click', function () {
        if (!oltDescUrl) return;
        var label = root.getAttribute('data-desc-onu') || 'usuario PPPoE';
        if (!confirm('¿Escribir en la OLT la descripción de la ONU como «' + label + '»?')) return;
        setLoading('btn-olt-desc', 'spin-olt-desc', true);
        showBox('out-olt', '<p class="text-gray-500 dark:text-gray-400">Localizando ONU y aplicando descripción…</p>');
        postJson(oltDescUrl).then(function (res) {
            var d = res.data || {};
            if (!res.ok || d.success === false) {
                showBox('out-olt', errHtml(d.message) + oltCmdResultHtml(d));
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
            showBox('out-olt', html);
        }).catch(function () {
            showBox('out-olt', errHtml('Error al aplicar descripción en OLT.'));
        }).finally(function () {
            setLoading('btn-olt-desc', 'spin-olt-desc', false);
        });
    });
})();
</script>
@endpush
