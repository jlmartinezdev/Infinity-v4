@extends('layouts.app')

@section('title', 'Inicio')

@php
    $salud = (float) ($stats['indice_salud'] ?? 100);
    $saludCirc = max(0, min(100, $salud));
    $saludDash = 2 * M_PI * 42;
    $saludOffset = $saludDash * (1 - ($saludCirc / 100));
    $variacion = (int) ($stats['clientes_variacion'] ?? 0);
@endphp

@section('content')
<div class="max-w-[1400px] mx-auto min-w-0 space-y-6">
    {{-- KPIs --}}
    <div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-6 gap-3 sm:gap-4">
        <a href="{{ route('clientes.index') }}" class="group relative overflow-hidden rounded-2xl border border-gray-200/80 dark:border-white/10 bg-white dark:bg-[#12161f] p-4 sm:p-5 shadow-sm hover:border-blue-400/60 dark:hover:border-blue-500/40 transition-all">
            <p class="text-[11px] font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Clientes</p>
            <p class="mt-2 text-2xl sm:text-3xl font-bold tabular-nums text-gray-900 dark:text-white">{{ number_format($stats['clientes']) }}</p>
            <div class="mt-3 flex items-center gap-1.5">
                @if($variacion >= 0)
                    <span class="inline-flex items-center gap-0.5 rounded-md bg-emerald-500/10 px-1.5 py-0.5 text-[11px] font-semibold text-emerald-600 dark:text-emerald-400">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 10l7-7m0 0l7 7m-7-7v18"/></svg>
                        +{{ $variacion }}%
                    </span>
                @else
                    <span class="inline-flex items-center gap-0.5 rounded-md bg-red-500/10 px-1.5 py-0.5 text-[11px] font-semibold text-red-600 dark:text-red-400">
                        {{ $variacion }}%
                    </span>
                @endif
                <span class="text-[10px] text-gray-400 dark:text-gray-500">vs mes ant.</span>
            </div>
        </a>

        <a href="{{ route('servicios.index') }}" class="group relative overflow-hidden rounded-2xl border border-gray-200/80 dark:border-white/10 bg-white dark:bg-[#12161f] p-4 sm:p-5 shadow-sm hover:border-emerald-400/60 dark:hover:border-emerald-500/40 transition-all">
            <p class="text-[11px] font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Servicios activos</p>
            <p class="mt-2 text-2xl sm:text-3xl font-bold tabular-nums text-gray-900 dark:text-white">{{ number_format($stats['servicios']) }}</p>
            <div class="mt-3">
                <div class="flex items-center justify-between text-[10px] mb-1">
                    <span class="text-gray-400 dark:text-gray-500">Índice de salud</span>
                    <span class="font-semibold text-emerald-600 dark:text-emerald-400">{{ number_format($salud, 1) }}%</span>
                </div>
                <div class="h-1.5 rounded-full bg-gray-100 dark:bg-white/5 overflow-hidden">
                    <div class="h-full rounded-full bg-gradient-to-r from-emerald-500 to-teal-400 transition-all" style="width: {{ $saludCirc }}%"></div>
                </div>
            </div>
        </a>

        <a href="{{ route('cobros.index', $cobrosMesVentanaQuery ?? []) }}" class="group relative overflow-hidden rounded-2xl border border-gray-200/80 dark:border-white/10 bg-white dark:bg-[#12161f] p-4 sm:p-5 shadow-sm hover:border-amber-400/60 dark:hover:border-amber-500/40 transition-all col-span-2 md:col-span-1">
            <p class="text-[11px] font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Cobros del mes</p>
            <p class="mt-2 text-xl sm:text-2xl font-bold tabular-nums text-gray-900 dark:text-white leading-tight">
                {{ number_format($stats['facturacion'], 0, ',', '.') }}
                <span class="text-xs font-semibold text-gray-400">PYG</span>
            </p>
            <div class="mt-3 flex items-center gap-1.5">
                <span class="relative flex h-2 w-2">
                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-60"></span>
                    <span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-500"></span>
                </span>
                <span class="text-[11px] text-gray-500 dark:text-gray-400">Ciclo de cobro vigente</span>
            </div>
        </a>

        <a href="{{ route('tickets.index') }}" class="group relative overflow-hidden rounded-2xl border border-gray-200/80 dark:border-white/10 bg-white dark:bg-[#12161f] p-4 sm:p-5 shadow-sm hover:border-rose-400/60 dark:hover:border-rose-500/40 transition-all">
            <p class="text-[11px] font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Tickets abiertos</p>
            <p class="mt-2 text-2xl sm:text-3xl font-bold tabular-nums text-gray-900 dark:text-white">{{ number_format($stats['tickets']) }}</p>
            <div class="mt-3 flex items-center justify-between gap-2 min-w-0">
                <span class="text-[11px] text-gray-500 dark:text-gray-400 truncate">Pendientes</span>
                @php
                    $avatares = $ticketAvatares['items'] ?? [];
                    $avataresExtra = (int) ($ticketAvatares['extra'] ?? 0);
                @endphp
                @if(count($avatares) > 0)
                    <div class="flex items-center -space-x-2 shrink-0" title="Asignados con más tickets abiertos">
                        @foreach($avatares as $avatar)
                            <span
                                class="inline-flex h-6 w-6 items-center justify-center rounded-full text-[9px] font-bold text-white ring-2 ring-white dark:ring-[#12161f] {{ $avatar['color'] }}"
                                title="{{ $avatar['name'] }}"
                            >{{ $avatar['iniciales'] }}</span>
                        @endforeach
                        @if($avataresExtra > 0)
                            <span class="inline-flex h-6 w-6 items-center justify-center rounded-full text-[9px] font-bold text-gray-600 dark:text-gray-300 bg-gray-200 dark:bg-white/10 ring-2 ring-white dark:ring-[#12161f]" title="+{{ $avataresExtra }} asignados más">
                                +{{ $avataresExtra }}
                            </span>
                        @endif
                    </div>
                @else
                    <span class="text-[10px] text-gray-400 dark:text-gray-500 shrink-0">Sin asignar</span>
                @endif
            </div>
        </a>

        <a href="{{ route('servicios.index', ['fecha_desde' => now()->toDateString(), 'fecha_hasta' => now()->toDateString()]) }}" class="group relative overflow-hidden rounded-2xl border border-gray-200/80 dark:border-white/10 bg-white dark:bg-[#12161f] p-4 sm:p-5 shadow-sm hover:border-cyan-400/60 dark:hover:border-cyan-500/40 transition-all">
            <p class="text-[11px] font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Instalados hoy</p>
            <p class="mt-2 text-2xl sm:text-3xl font-bold tabular-nums text-gray-900 dark:text-white">{{ number_format($stats['clientes_instalados_hoy']) }}</p>
            <p class="mt-3 text-[11px] {{ $stats['clientes_instalados_hoy'] > 0 ? 'text-cyan-600 dark:text-cyan-400' : 'text-gray-400 dark:text-gray-500' }}">
                {{ $stats['clientes_instalados_hoy'] > 0 ? 'En curso' : 'En espera…' }}
            </p>
        </a>

        <a href="{{ route('servicios.index', ['fecha_desde' => now()->startOfMonth()->toDateString(), 'fecha_hasta' => now()->endOfMonth()->toDateString()]) }}" class="group relative overflow-hidden rounded-2xl border border-gray-200/80 dark:border-white/10 bg-white dark:bg-[#12161f] p-4 sm:p-5 shadow-sm hover:border-teal-400/60 dark:hover:border-teal-500/40 transition-all">
            <p class="text-[11px] font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Instalados este mes</p>
            <div class="mt-2 flex items-end gap-2">
                <p class="text-2xl sm:text-3xl font-bold tabular-nums text-gray-900 dark:text-white">{{ number_format($stats['clientes_instalados_mes']) }}</p>
                @if($stats['clientes_instalados_mes'] > 0)
                    <span class="mb-1 inline-flex h-5 w-5 items-center justify-center rounded-full bg-emerald-500/15 text-emerald-500">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                    </span>
                @endif
            </div>
            @php
                $metaMes = max(1, (int) ceil(now()->day * 2.5));
                $progresoMes = min(100, (int) round(($stats['clientes_instalados_mes'] / $metaMes) * 100));
            @endphp
            <div class="mt-3 h-1.5 rounded-full bg-gray-100 dark:bg-white/5 overflow-hidden flex gap-0.5">
                @for($i = 0; $i < 5; $i++)
                    <div class="flex-1 rounded-sm {{ $progresoMes > ($i * 20) ? 'bg-teal-500' : 'bg-transparent' }}"></div>
                @endfor
            </div>
        </a>
    </div>

    {{-- Búsqueda + acciones rápidas --}}
    @if(auth()->user()?->tienePermiso('clientes.ver'))
    <div class="rounded-2xl border border-gray-200/80 dark:border-white/10 bg-white dark:bg-[#12161f] p-5 sm:p-6 shadow-sm">
        <label for="dashboard-cliente-buscar" class="block text-sm font-semibold text-gray-900 dark:text-white">Buscar cliente — ir a acciones</label>
        <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5 mb-4">Escriba nombre, apellido, cédula, teléfono o número de cliente.</p>
        <div class="relative" id="dashboard-buscar-cliente-root">
            <div class="relative">
                <span class="absolute inset-y-0 left-0 flex items-center pl-4 pointer-events-none text-gray-400" aria-hidden="true">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                </span>
                <input type="search" id="dashboard-cliente-buscar" name="dashboard_cliente_buscar" autocomplete="off"
                    placeholder="Buscar por nombre, cédula, teléfono…"
                    aria-autocomplete="list" aria-controls="dashboard-cliente-resultados" aria-expanded="false"
                    class="w-full pl-12 pr-24 py-3.5 rounded-xl border border-gray-200 dark:border-white/10 bg-gray-50 dark:bg-[#0b0e14] text-gray-900 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-500 text-sm shadow-inner focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20" />
                <div class="absolute inset-y-0 right-0 flex items-center gap-1 pr-2">
                    <button type="button" id="dashboard-cliente-limpiar" class="p-1.5 rounded-lg text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 hidden" title="Limpiar" aria-label="Limpiar búsqueda">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                    <kbd class="hidden sm:inline-flex items-center rounded-md border border-gray-200 dark:border-white/10 bg-white dark:bg-white/5 px-2 py-1 text-[10px] font-medium text-gray-400">Ctrl K</kbd>
                </div>
            </div>
            <div id="dashboard-cliente-resultados" role="listbox" class="hidden absolute z-30 w-full mt-1.5 max-h-60 overflow-y-auto rounded-xl border border-gray-200 dark:border-white/10 bg-white dark:bg-[#161b26] shadow-xl py-1"></div>
            <p id="dashboard-cliente-ayuda" class="mt-2 text-xs text-gray-500 dark:text-gray-400 hidden"></p>
        </div>

        <div class="mt-5 grid grid-cols-2 lg:grid-cols-4 gap-3">
            @if(auth()->user()?->tienePermiso('clientes.crear'))
            <a href="{{ route('clientes.create') }}" class="flex flex-col items-center justify-center gap-2 rounded-xl border border-dashed border-gray-300 dark:border-white/15 bg-transparent hover:bg-blue-50 dark:hover:bg-blue-500/10 hover:border-blue-400 dark:hover:border-blue-500/50 px-4 py-5 transition-all group">
                <span class="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-blue-500/10 text-blue-600 dark:text-blue-400 group-hover:scale-105 transition-transform">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/></svg>
                </span>
                <span class="text-sm font-medium text-gray-700 dark:text-gray-200">Nuevo Cliente</span>
            </a>
            @endif
            @if(auth()->user()?->tienePermiso('servicios.crear'))
            <a href="{{ route('servicios.create') }}" class="flex flex-col items-center justify-center gap-2 rounded-xl border border-dashed border-gray-300 dark:border-white/15 bg-transparent hover:bg-emerald-50 dark:hover:bg-emerald-500/10 hover:border-emerald-400 dark:hover:border-emerald-500/50 px-4 py-5 transition-all group">
                <span class="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 group-hover:scale-105 transition-transform">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M12 9v3m0 0v3m0-3h3m-3 0H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </span>
                <span class="text-sm font-medium text-gray-700 dark:text-gray-200">Nuevo Servicio</span>
            </a>
            @endif
            @if(auth()->user()?->tienePermiso('tickets.crear'))
            <a href="{{ route('tickets.create') }}" class="flex flex-col items-center justify-center gap-2 rounded-xl border border-dashed border-gray-300 dark:border-white/15 bg-transparent hover:bg-amber-50 dark:hover:bg-amber-500/10 hover:border-amber-400 dark:hover:border-amber-500/50 px-4 py-5 transition-all group">
                <span class="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-amber-500/10 text-amber-600 dark:text-amber-400 group-hover:scale-105 transition-transform">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"/></svg>
                </span>
                <span class="text-sm font-medium text-gray-700 dark:text-gray-200">Nuevo Ticket</span>
            </a>
            @endif
            @if(auth()->user()?->tienePermiso('facturas.crear'))
            <a href="{{ route('facturas.generar-interna') }}" class="flex flex-col items-center justify-center gap-2 rounded-xl border border-dashed border-gray-300 dark:border-white/15 bg-transparent hover:bg-violet-50 dark:hover:bg-violet-500/10 hover:border-violet-400 dark:hover:border-violet-500/50 px-4 py-5 transition-all group">
                <span class="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-violet-500/10 text-violet-600 dark:text-violet-400 group-hover:scale-105 transition-transform">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                </span>
                <span class="text-sm font-medium text-gray-700 dark:text-gray-200">Nueva Factura</span>
            </a>
            @endif
        </div>
    </div>
    @push('scripts')
    <script>
    (function () {
        var root = document.getElementById('dashboard-buscar-cliente-root');
        if (!root) return;
        var input = document.getElementById('dashboard-cliente-buscar');
        var panel = document.getElementById('dashboard-cliente-resultados');
        var btnLimpiar = document.getElementById('dashboard-cliente-limpiar');
        var ayuda = document.getElementById('dashboard-cliente-ayuda');
        var urlBuscar = @json(route('clientes.buscar'));
        var baseAcciones = @json(rtrim(url('/clientes'), '/'));
        var debounceTimer = null;
        var abortCtrl = null;
        var items = [];
        var activeIndex = -1;

        function escapeHtml(s) {
            var d = document.createElement('div');
            d.textContent = s;
            return d.innerHTML;
        }

        function cerrarPanel() {
            panel.classList.add('hidden');
            panel.innerHTML = '';
            items = [];
            activeIndex = -1;
            input.setAttribute('aria-expanded', 'false');
        }

        function irAcciones(clienteId) {
            window.location.href = baseAcciones + '/' + clienteId + '/acciones';
        }

        function renderResultados(data) {
            panel.innerHTML = '';
            items = [];
            activeIndex = -1;
            if (!data || data.length === 0) {
                panel.innerHTML = '<div class="px-3 py-2 text-sm text-gray-500 dark:text-gray-400">Sin resultados</div>';
                panel.classList.remove('hidden');
                input.setAttribute('aria-expanded', 'true');
                return;
            }
            data.forEach(function (c, idx) {
                var btn = document.createElement('button');
                btn.type = 'button';
                btn.role = 'option';
                btn.className = 'w-full text-left px-3 py-2.5 text-sm text-gray-900 dark:text-gray-100 hover:bg-blue-50 dark:hover:bg-blue-900/30 focus:bg-blue-50 dark:focus:bg-blue-900/30 focus:outline-none border-0 border-b border-gray-100 dark:border-white/5 last:border-0';
                btn.dataset.index = String(idx);
                btn.dataset.id = String(c.cliente_id);
                btn.innerHTML = '<span class="font-medium">' + escapeHtml(((c.nombre || '') + ' ' + (c.apellido || '')).trim()) + '</span>' +
                    (c.cedula
                        ? '<span class="block text-xs text-gray-500 dark:text-gray-400">#' + escapeHtml(String(c.cliente_id)) + ' · CI ' + escapeHtml(String(c.cedula)) + '</span>'
                        : '<span class="block text-xs text-gray-500 dark:text-gray-400">#' + escapeHtml(String(c.cliente_id)) + '</span>');
                btn.addEventListener('mousedown', function (e) { e.preventDefault(); });
                btn.addEventListener('click', function () { irAcciones(c.cliente_id); });
                panel.appendChild(btn);
                items.push(btn);
            });
            panel.classList.remove('hidden');
            input.setAttribute('aria-expanded', 'true');
        }

        function buscar(q) {
            if (abortCtrl) abortCtrl.abort();
            if (q.length < 2) {
                cerrarPanel();
                ayuda.classList.add('hidden');
                return;
            }
            abortCtrl = new AbortController();
            ayuda.textContent = 'Buscando…';
            ayuda.classList.remove('hidden');
            fetch(urlBuscar + '?q=' + encodeURIComponent(q), {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                signal: abortCtrl.signal
            }).then(function (r) { return r.json(); }).then(function (data) {
                ayuda.classList.add('hidden');
                renderResultados(Array.isArray(data) ? data : []);
            }).catch(function (e) {
                if (e.name === 'AbortError') return;
                ayuda.textContent = 'No se pudo buscar. Intente de nuevo.';
                ayuda.classList.remove('hidden');
            });
        }

        function debouncedBuscar() {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(function () {
                buscar(input.value.trim());
            }, 280);
        }

        function setActive(i) {
            items.forEach(function (el, j) {
                if (j === i) {
                    el.classList.add('ring-2', 'ring-inset', 'ring-blue-500');
                } else {
                    el.classList.remove('ring-2', 'ring-inset', 'ring-blue-500');
                }
            });
            activeIndex = i;
        }

        function focusSearch(e) {
            if ((e.ctrlKey || e.metaKey) && (e.key === 'k' || e.key === 'K')) {
                e.preventDefault();
                input.focus();
                input.select();
            }
        }

        input.addEventListener('input', function () {
            btnLimpiar.classList.toggle('hidden', !input.value.length);
            debouncedBuscar();
        });

        input.addEventListener('keydown', function (e) {
            if (!panel.classList.contains('hidden') && items.length) {
                if (e.key === 'ArrowDown') {
                    e.preventDefault();
                    setActive(Math.min(activeIndex + 1, items.length - 1));
                } else if (e.key === 'ArrowUp') {
                    e.preventDefault();
                    setActive(Math.max(activeIndex - 1, 0));
                } else if (e.key === 'Enter' && activeIndex >= 0 && items[activeIndex]) {
                    e.preventDefault();
                    irAcciones(items[activeIndex].dataset.id);
                } else if (e.key === 'Enter' && activeIndex < 0 && items.length === 1) {
                    e.preventDefault();
                    irAcciones(items[0].dataset.id);
                } else if (e.key === 'Escape') {
                    cerrarPanel();
                }
            }
        });

        btnLimpiar.addEventListener('click', function () {
            input.value = '';
            btnLimpiar.classList.add('hidden');
            cerrarPanel();
            ayuda.classList.add('hidden');
            input.focus();
        });

        document.addEventListener('click', function (e) {
            if (!root.contains(e.target)) cerrarPanel();
        });
        document.addEventListener('keydown', focusSearch);
    })();
    </script>
    @endpush
    @endif

    {{-- Estado del sistema + Actividad --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 sm:gap-6">
        <div class="rounded-2xl border border-gray-200/80 dark:border-white/10 bg-white dark:bg-[#12161f] p-5 sm:p-6 shadow-sm">
            <div class="flex items-center justify-between mb-5">
                <h3 class="text-base font-semibold text-gray-900 dark:text-white">Estado del sistema</h3>
                <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[11px] font-semibold
                    {{ ($systemStatus['operativo'] ?? false)
                        ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400'
                        : 'bg-amber-500/10 text-amber-600 dark:text-amber-400' }}">
                    <span class="h-1.5 w-1.5 rounded-full {{ ($systemStatus['operativo'] ?? false) ? 'bg-emerald-500' : 'bg-amber-500' }}"></span>
                    {{ $systemStatus['etiqueta'] ?? '—' }}
                </span>
            </div>

            <div class="flex flex-col sm:flex-row gap-6 items-center sm:items-start">
                <div class="relative shrink-0">
                    <svg class="w-32 h-32 -rotate-90" viewBox="0 0 100 100" aria-hidden="true">
                        <circle cx="50" cy="50" r="42" fill="none" stroke="currentColor" stroke-width="8" class="text-gray-100 dark:text-white/5"/>
                        <circle cx="50" cy="50" r="42" fill="none" stroke="url(#dashSaludGrad)" stroke-width="8" stroke-linecap="round"
                            stroke-dasharray="{{ $saludDash }}" stroke-dashoffset="{{ $saludOffset }}"
                            class="transition-all duration-700"/>
                        <defs>
                            <linearGradient id="dashSaludGrad" x1="0%" y1="0%" x2="100%" y2="0%">
                                <stop offset="0%" stop-color="#10b981"/>
                                <stop offset="100%" stop-color="#2dd4bf"/>
                            </linearGradient>
                        </defs>
                    </svg>
                    <div class="absolute inset-0 flex flex-col items-center justify-center">
                        <span class="text-2xl font-bold tabular-nums text-gray-900 dark:text-white">{{ number_format($salud, 1) }}%</span>
                        <span class="text-[10px] uppercase tracking-wider text-gray-400">salud</span>
                    </div>
                </div>

                <div class="flex-1 w-full space-y-3">
                    @foreach(($systemStatus['items'] ?? []) as $item)
                        <div class="flex items-center justify-between gap-3 rounded-xl bg-gray-50 dark:bg-white/[0.03] px-3 py-2.5 border border-transparent dark:border-white/5">
                            <div class="flex items-center gap-2.5 min-w-0">
                                @if(!empty($item['syncing']))
                                    <span class="h-2 w-2 rounded-full bg-amber-400 shrink-0 animate-pulse"></span>
                                @elseif(!empty($item['ok']))
                                    <span class="h-2 w-2 rounded-full bg-emerald-500 shrink-0"></span>
                                @else
                                    <span class="h-2 w-2 rounded-full bg-rose-500 shrink-0"></span>
                                @endif
                                <div class="min-w-0">
                                    <p class="text-sm font-medium text-gray-800 dark:text-gray-100 truncate">{{ $item['label'] }}</p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 truncate">{{ $item['detail'] }}</p>
                                </div>
                            </div>
                            <span class="text-[11px] font-medium shrink-0
                                {{ !empty($item['syncing']) ? 'text-amber-500' : (!empty($item['ok']) ? 'text-emerald-500' : 'text-rose-500') }}">
                                {{ !empty($item['syncing']) ? 'Sync' : (!empty($item['ok']) ? 'OK' : 'Alert') }}
                            </span>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="mt-5 rounded-xl border border-gray-100 dark:border-white/5 bg-gradient-to-r from-blue-50 to-transparent dark:from-blue-500/10 dark:to-transparent px-4 py-3">
                <p class="text-xs font-semibold text-gray-700 dark:text-gray-200">Resumen operativo</p>
                <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                    {{ number_format($stats['servicios']) }} servicios activos ·
                    {{ number_format($stats['servicios_suspendidos'] ?? 0) }} suspendidos ·
                    {{ number_format($stats['servicios_cortados'] ?? 0) }} cortados ·
                    {{ number_format($stats['tickets']) }} tickets abiertos
                </p>
            </div>
        </div>

        <div class="rounded-2xl border border-gray-200/80 dark:border-white/10 bg-white dark:bg-[#12161f] p-5 sm:p-6 shadow-sm flex flex-col">
            <div class="flex items-center justify-between mb-5">
                <h3 class="text-base font-semibold text-gray-900 dark:text-white">Actividad reciente</h3>
                @if(auth()->user()?->esAdministrador() && Route::has('auditoria.index'))
                    <a href="{{ route('auditoria.index') }}" class="text-xs font-medium text-blue-600 dark:text-blue-400 hover:underline">Ver auditoría</a>
                @endif
            </div>

            <div class="flex-1 space-y-1">
                @forelse ($recentActivity as $activity)
                <div class="flex items-start gap-3 rounded-xl px-2 py-2.5 hover:bg-gray-50 dark:hover:bg-white/[0.03] transition-colors">
                    <div class="mt-1.5 shrink-0">
                        <div class="w-2 h-2 rounded-full {{ $activity['color'] }}"></div>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium text-gray-900 dark:text-gray-100 truncate">{{ $activity['title'] }}</p>
                        @if(!empty($activity['subtitle']))
                            <p class="text-xs text-gray-500 dark:text-gray-400 truncate">{{ $activity['subtitle'] }}</p>
                        @endif
                    </div>
                    <span class="text-[11px] text-gray-400 dark:text-gray-500 shrink-0 whitespace-nowrap">{{ $activity['time'] }}</span>
                </div>
                @empty
                <p class="text-sm text-gray-500 dark:text-gray-400 py-6 text-center">No hay actividad reciente.</p>
                @endforelse
            </div>

            @if(auth()->user()?->esAdministrador() && Route::has('auditoria.index') && count($recentActivity) > 0)
                <a href="{{ route('auditoria.index') }}" class="mt-4 flex items-center justify-center gap-1.5 rounded-xl border border-dashed border-gray-200 dark:border-white/10 py-2.5 text-xs font-medium text-gray-500 dark:text-gray-400 hover:text-blue-600 dark:hover:text-blue-400 hover:border-blue-400/50 transition-colors">
                    Cargar actividad anterior
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </a>
            @endif
        </div>
    </div>
</div>
@endsection
