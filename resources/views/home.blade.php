@extends('layouts.app')

@section('title', 'Inicio')

@section('content')
<div class="max-w-7xl mx-auto min-w-0">
    <div class="mb-8">
        <h2 class="text-3xl font-bold text-gray-900 dark:text-gray-100 mb-2">Bienvenido, {{ $user->name ?? 'Usuario' }}</h2>
        <p class="text-gray-600 dark:text-gray-400">Panel de control de Infinity ISP</p>
    </div>

    @if(auth()->user()?->tienePermiso('clientes.ver'))
    <div class="mb-8 w-full">
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 p-6">
            <label for="dashboard-cliente-buscar" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Buscar cliente — ir a acciones</label>
            <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5 mb-3">Escriba nombre, apellido, cédula, teléfono o número de cliente. Elija un resultado para abrir el panel de acciones (factura, ticket, servicios, etc.).</p>
            <div class="relative" id="dashboard-buscar-cliente-root">
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none text-gray-400 dark:text-gray-500" aria-hidden="true">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    </span>
                    <input type="search" id="dashboard-cliente-buscar" name="dashboard_cliente_buscar" autocomplete="off" placeholder="Buscar por nombre, cédula, teléfono…" aria-autocomplete="list" aria-controls="dashboard-cliente-resultados" aria-expanded="false"
                        class="mt-0 w-full pl-10 pr-10 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-green-500 focus:ring-2 focus:ring-green-500/20 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-500 text-sm" />
                    <button type="button" id="dashboard-cliente-limpiar" class="absolute inset-y-0 right-0 flex items-center pr-2 p-1.5 rounded-lg text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 hidden" title="Limpiar" aria-label="Limpiar búsqueda">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
                <div id="dashboard-cliente-resultados" role="listbox" class="hidden absolute z-30 w-full mt-1 max-h-60 overflow-y-auto rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-800 shadow-lg py-1"></div>
                <p id="dashboard-cliente-ayuda" class="mt-2 text-xs text-gray-500 dark:text-gray-400 hidden"></p>
            </div>
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
                var label = (c.nombre || '') + ' ' + (c.apellido || '') + (c.cedula ? ' · ' + c.cedula : '');
                var btn = document.createElement('button');
                btn.type = 'button';
                btn.role = 'option';
                btn.className = 'w-full text-left px-3 py-2 text-sm text-gray-900 dark:text-gray-100 hover:bg-green-50 dark:hover:bg-green-900/30 focus:bg-green-50 dark:focus:bg-green-900/30 focus:outline-none border-0 border-b border-gray-100 dark:border-gray-700 last:border-0';
                btn.dataset.index = String(idx);
                btn.dataset.id = String(c.cliente_id);
                btn.innerHTML = '<span class="font-medium">' + escapeHtml((c.nombre || '') + ' ' + (c.apellido || '')).trim() + '</span>' +
                    (c.cedula ? '<span class="block text-xs text-gray-500 dark:text-gray-400">#' + escapeHtml(String(c.cliente_id)) + ' · CI ' + escapeHtml(String(c.cedula)) + '</span>' : '<span class="block text-xs text-gray-500 dark:text-gray-400">#' + escapeHtml(String(c.cliente_id)) + '</span>');
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
                    el.classList.add('ring-2', 'ring-inset', 'ring-green-500', 'dark:ring-green-400');
                } else {
                    el.classList.remove('ring-2', 'ring-inset', 'ring-green-500', 'dark:ring-green-400');
                }
            });
            activeIndex = i;
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
    })();
    </script>
    @endpush
    @endif

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 sm:gap-6 mb-8">
        <a href="{{ route('clientes.index') }}" class="bg-white dark:bg-gray-800 rounded-lg shadow p-5 sm:p-6 border border-gray-200 dark:border-gray-700 hover:border-blue-400 dark:hover:border-blue-600 transition-colors block min-w-0">
            <div class="flex items-start justify-between gap-3 min-w-0">
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Clientes</p>
                    <p class="text-3xl font-bold text-gray-900 dark:text-gray-100 mt-2 tabular-nums">{{ number_format($stats['clientes']) }}</p>
                </div>
                <div class="bg-blue-100 dark:bg-blue-900/30 rounded-full p-3 flex-shrink-0">
                    <svg class="w-8 h-8 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                    </svg>
                </div>
            </div>
        </a>
        <a href="{{ route('servicios.index') }}" class="bg-white dark:bg-gray-800 rounded-lg shadow p-5 sm:p-6 border border-gray-200 dark:border-gray-700 hover:border-green-400 dark:hover:border-green-600 transition-colors block min-w-0">
            <div class="flex items-start justify-between gap-3 min-w-0">
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Servicios Activos</p>
                    <p class="text-3xl font-bold text-gray-900 dark:text-gray-100 mt-2 tabular-nums">{{ number_format($stats['servicios']) }}</p>
                </div>
                <div class="bg-green-100 dark:bg-green-900/30 rounded-full p-3 flex-shrink-0">
                    <svg class="w-8 h-8 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                    </svg>
                </div>
            </div>
        </a>
        <a href="{{ route('cobros.index', ['desde' => now()->startOfMonth()->toDateString(), 'hasta' => now()->endOfMonth()->toDateString()]) }}" class="bg-white dark:bg-gray-800 rounded-lg shadow p-5 sm:p-6 border border-gray-200 dark:border-gray-700 hover:border-amber-400 dark:hover:border-amber-600 transition-colors block min-w-0 sm:col-span-2 lg:col-span-1">
            <div class="flex items-start justify-between gap-3 min-w-0">
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Facturación del mes</p>
                    <p class="text-lg sm:text-xl font-bold text-gray-900 dark:text-gray-100 mt-2 break-words leading-tight tabular-nums">{{ number_format($stats['facturacion'], 0, ',', '.') }} <span class="text-sm font-semibold text-gray-500 dark:text-gray-400">PYG</span></p>
                </div>
                <div class="bg-yellow-100 dark:bg-yellow-900/30 rounded-full p-3 flex-shrink-0">
                    <svg class="w-8 h-8 text-yellow-600 dark:text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
            </div>
        </a>
        <a href="{{ route('tickets.index') }}" class="bg-white dark:bg-gray-800 rounded-lg shadow p-5 sm:p-6 border border-gray-200 dark:border-gray-700 hover:border-red-400 dark:hover:border-red-600 transition-colors block min-w-0">
            <div class="flex items-start justify-between gap-3 min-w-0">
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Tickets Abiertos</p>
                    <p class="text-3xl font-bold text-gray-900 dark:text-gray-100 mt-2 tabular-nums">{{ number_format($stats['tickets']) }}</p>
                </div>
                <div class="bg-red-100 dark:bg-red-900/30 rounded-full p-3 flex-shrink-0">
                    <svg class="w-8 h-8 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                </div>
            </div>
        </a>
        <a href="{{ route('servicios.index', ['fecha_desde' => now()->toDateString(), 'fecha_hasta' => now()->toDateString()]) }}" class="bg-white dark:bg-gray-800 rounded-lg shadow p-5 sm:p-6 border border-gray-200 dark:border-gray-700 hover:border-cyan-400 dark:hover:border-cyan-600 transition-colors block min-w-0">
            <div class="flex items-start justify-between gap-3 min-w-0">
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Instalados hoy</p>
                    <p class="text-3xl font-bold text-gray-900 dark:text-gray-100 mt-2 tabular-nums">{{ number_format($stats['clientes_instalados_hoy']) }}</p>
                </div>
                <div class="bg-cyan-100 dark:bg-cyan-900/30 rounded-full p-3 flex-shrink-0">
                    <svg class="w-8 h-8 text-cyan-600 dark:text-cyan-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
            </div>
        </a>
        <a href="{{ route('servicios.index', ['fecha_desde' => now()->startOfMonth()->toDateString(), 'fecha_hasta' => now()->endOfMonth()->toDateString()]) }}" class="bg-white dark:bg-gray-800 rounded-lg shadow p-5 sm:p-6 border border-gray-200 dark:border-gray-700 hover:border-teal-400 dark:hover:border-teal-600 transition-colors block min-w-0">
            <div class="flex items-start justify-between gap-3 min-w-0">
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Instalados este mes</p>
                    <p class="text-3xl font-bold text-gray-900 dark:text-gray-100 mt-2 tabular-nums">{{ number_format($stats['clientes_instalados_mes']) }}</p>
                </div>
                <div class="bg-teal-100 dark:bg-teal-900/30 rounded-full p-3 flex-shrink-0">
                    <svg class="w-8 h-8 text-teal-600 dark:text-teal-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                    </svg>
                </div>
            </div>
        </a>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow border border-gray-200 dark:border-gray-700 p-6">
            <h3 class="text-xl font-semibold text-gray-900 dark:text-gray-100 mb-4">Acciones Rápidas</h3>
            <div class="grid grid-cols-2 gap-4">
                @if(auth()->user()?->tienePermiso('clientes.crear'))
                <a href="{{ route('clientes.create') }}" class="bg-blue-600 text-white px-4 py-3 rounded-lg font-medium hover:bg-blue-700 transition-colors text-left inline-flex items-center">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
                    Nuevo Cliente
                </a>
                @endif
                @if(auth()->user()?->tienePermiso('servicios.crear'))
                <a href="{{ route('servicios.create') }}" class="bg-green-600 text-white px-4 py-3 rounded-lg font-medium hover:bg-green-700 transition-colors text-left inline-flex items-center">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    Nuevo Servicio
                </a>
                @endif
                @if(auth()->user()?->tienePermiso('tickets.crear'))
                <a href="{{ route('tickets.create') }}" class="bg-yellow-600 text-white px-4 py-3 rounded-lg font-medium hover:bg-yellow-700 transition-colors text-left inline-flex items-center">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                    Nuevo Ticket
                </a>
                @endif
                @if(auth()->user()?->tienePermiso('facturas.crear'))
                <a href="{{ route('facturas.generar-interna') }}" class="bg-purple-600 text-white px-4 py-3 rounded-lg font-medium hover:bg-purple-700 transition-colors text-left inline-flex items-center">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    Nueva Factura
                </a>
                @endif
            </div>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow border border-gray-200 dark:border-gray-700 p-6">
            <h3 class="text-xl font-semibold text-gray-900 dark:text-gray-100 mb-4">Actividad Reciente</h3>
            <div class="space-y-3">
                @forelse ($recentActivity as $activity)
                <div class="flex items-center space-x-3 p-3 bg-gray-50 dark:bg-gray-700/50 rounded-lg">
                    <div class="flex-shrink-0">
                        <div class="w-2 h-2 rounded-full {{ $activity['color'] }}"></div>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium text-gray-900 dark:text-gray-100 truncate">{{ $activity['title'] }}</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ $activity['time'] }}</p>
                    </div>
                </div>
                @empty
                <p class="text-sm text-gray-500 dark:text-gray-400">No hay actividad reciente.</p>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection
