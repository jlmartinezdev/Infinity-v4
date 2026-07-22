@extends('layouts.app')

@section('title', 'Routers')

@section('content')
<div class="max-w-7xl mx-auto">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">Routers</h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Equipos MikroTik por nodo — RB, CCR, hAP y CHR</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('sistema.router-modelos.index') }}"
                class="inline-flex items-center px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-gray-200 rounded-lg font-medium hover:bg-gray-300 dark:hover:bg-gray-600 focus:outline-none">
                Catálogo MikroTik
            </a>
            <a href="{{ route('sistema.routers.create') }}"
                class="inline-flex items-center px-4 py-2 bg-purple-600 text-white rounded-lg font-medium hover:bg-purple-700 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:ring-offset-2">
                Nuevo router
            </a>
        </div>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 overflow-hidden mb-6">
        <form method="GET" action="{{ route('sistema.routers.index') }}" class="p-4 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-700/50">
            <div class="flex flex-col lg:flex-row gap-3">
                <div class="flex-1">
                    <input type="text" name="buscar" value="{{ request('buscar') }}"
                        placeholder="Buscar por nombre, IP, modelo..."
                        class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none transition-colors bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                </div>
                <div class="sm:w-44">
                    <select name="serie" class="w-full py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none transition-colors bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                        <option value="todas">Todas las series</option>
                        @foreach($series as $serie)
                            <option value="{{ $serie }}" {{ request('serie') == $serie ? 'selected' : '' }}>{{ $serie }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="sm:w-48">
                    <select name="nodo_id" class="w-full py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none transition-colors bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                        <option value="todos">Todos los nodos</option>
                        @foreach($nodos as $nodo)
                            <option value="{{ $nodo->nodo_id }}" {{ request('nodo_id') == $nodo->nodo_id ? 'selected' : '' }}>
                                {{ $nodo->descripcion ?? "Nodo #{$nodo->nodo_id}" }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="inline-flex items-center justify-center px-6 py-2.5 bg-purple-600 text-white rounded-lg font-medium hover:bg-purple-700 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:ring-offset-2">
                    Filtrar
                </button>
            </div>
        </form>
    </div>

    @if($routers->isEmpty())
        <div class="rounded-xl border border-dashed border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 p-12 text-center">
            <img src="{{ asset('images/routers/mikrotik-generic.svg') }}" alt="" class="mx-auto h-24 w-48 object-contain opacity-60 mb-4">
            <p class="text-gray-500 dark:text-gray-400">No hay routers registrados.</p>
            <a href="{{ route('sistema.routers.create') }}" class="mt-3 inline-block text-purple-600 dark:text-purple-400 hover:underline font-medium">Crear el primero</a>
        </div>
    @else
        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4 gap-5">
            @foreach($routers as $r)
                @php
                    $cat = $r->modeloCatalogo();
                    $serie = $cat['serie'] ?? 'MikroTik';
                    $stats = $statsClientes[$r->router_id] ?? ['registrados' => 0, 'activos' => 0];
                    $pctActivos = $stats['registrados'] > 0
                        ? min(100, round(($stats['activos'] / $stats['registrados']) * 100))
                        : 0;
                @endphp
                <article class="group flex flex-col overflow-hidden rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-sm hover:shadow-md hover:border-purple-300 dark:hover:border-purple-700 transition-all">
                    <div class="relative bg-gradient-to-b from-gray-50 to-gray-100 dark:from-gray-900/60 dark:to-gray-800 px-4 pt-5 pb-3">
                        <span class="absolute top-3 left-3 inline-flex rounded-full bg-white/90 dark:bg-gray-900/80 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-purple-700 dark:text-purple-300 border border-purple-200 dark:border-purple-800">
                            {{ $serie }}
                        </span>
                        <span class="absolute top-3 right-3 text-xs text-gray-400 font-mono">#{{ $r->router_id }}</span>
                        <img src="{{ $r->imagenUrl() }}" alt="{{ $r->modeloEtiqueta() }}"
                            class="mx-auto h-28 w-full max-w-[220px] object-contain drop-shadow-sm group-hover:scale-[1.02] transition-transform duration-200">
                    </div>

                    <div class="flex flex-1 flex-col p-4">
                        <h2 class="text-base font-semibold text-gray-900 dark:text-gray-100 truncate" title="{{ $r->nombre }}">{{ $r->nombre }}</h2>
                        <p class="text-sm font-medium text-purple-700 dark:text-purple-300">{{ $r->modeloEtiqueta() }}</p>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400 truncate">{{ $r->nodo?->descripcion ?? 'Sin nodo' }}</p>

                        <div class="mt-3 rounded-lg border border-gray-100 dark:border-gray-700 bg-gray-50/80 dark:bg-gray-900/40 p-3">
                            <p class="text-[10px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-2">Clientes en sistema</p>
                            <div class="grid grid-cols-2 gap-2">
                                <div class="rounded-lg bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-600 px-2 py-2 text-center">
                                    <p class="text-xl font-bold tabular-nums text-gray-900 dark:text-gray-100">{{ number_format($stats['registrados']) }}</p>
                                    <p class="text-[10px] leading-tight text-gray-500 dark:text-gray-400 mt-0.5">Registrados</p>
                                </div>
                                <div class="rounded-lg bg-white dark:bg-gray-800 border border-emerald-200 dark:border-emerald-800/60 px-2 py-2 text-center">
                                    <p class="text-xl font-bold tabular-nums text-emerald-700 dark:text-emerald-300">{{ number_format($stats['activos']) }}</p>
                                    <p class="text-[10px] leading-tight text-gray-500 dark:text-gray-400 mt-0.5">Activos</p>
                                </div>
                            </div>
                            @if($stats['registrados'] > 0)
                                <div class="mt-2">
                                    <div class="flex justify-between text-[10px] text-gray-500 dark:text-gray-400 mb-1">
                                        <span>Activos / registrados</span>
                                        <span>{{ $pctActivos }}%</span>
                                    </div>
                                    <div class="h-1.5 rounded-full bg-gray-200 dark:bg-gray-700 overflow-hidden">
                                        <div class="h-full rounded-full bg-emerald-500 transition-all" style="width: {{ $pctActivos }}%"></div>
                                    </div>
                                </div>
                            @endif
                        </div>

                        <dl class="mt-3 space-y-1 text-xs text-gray-600 dark:text-gray-400">
                            <div class="flex justify-between gap-2">
                                <dt>IP gestión</dt>
                                <dd class="font-mono text-gray-900 dark:text-gray-100">{{ $r->ip }}</dd>
                            </div>
                            @if($r->ip_loopback)
                                <div class="flex justify-between gap-2">
                                    <dt>Loopback</dt>
                                    <dd class="font-mono">{{ $r->ip_loopback }}</dd>
                                </div>
                            @endif
                            <div class="flex justify-between gap-2">
                                <dt>API</dt>
                                <dd class="font-mono">{{ $r->api_port ?? 8728 }}</dd>
                            </div>
                            <div class="flex justify-between gap-2">
                                <dt>Pools</dt>
                                <dd>{{ $r->router_ip_pools_count ?? 0 }}</dd>
                            </div>
                            <div class="flex justify-between gap-2">
                                <dt>Estado</dt>
                                <dd>{{ $r->estado ?? '—' }}</dd>
                            </div>
                        </dl>

                        <div class="mt-4 flex flex-wrap gap-1.5 border-t border-gray-100 dark:border-gray-700 pt-3">
                            <button type="button" class="router-test-btn rounded-md px-2 py-1 text-xs font-medium text-blue-700 bg-blue-50 hover:bg-blue-100 dark:bg-blue-900/30 dark:text-blue-300"
                                data-url="{{ route('sistema.routers.test-connection', $r) }}" data-csrf="{{ csrf_token() }}">Probar</button>
                            <button type="button" class="router-sync-btn rounded-md px-2 py-1 text-xs font-medium text-green-700 bg-green-50 hover:bg-green-100 dark:bg-green-900/30 dark:text-green-300"
                                data-url="{{ route('sistema.routers.sync-pppoe', $r) }}" data-csrf="{{ csrf_token() }}">Sync PPPoE</button>
                            <button type="button" class="router-export-script-btn rounded-md px-2 py-1 text-xs font-medium text-amber-700 bg-amber-50 hover:bg-amber-100 dark:bg-amber-900/30 dark:text-amber-300"
                                data-url="{{ route('sistema.routers.export-pppoe-script', ['router' => $r, 'formato' => 'json']) }}"
                                data-nombre="{{ $r->nombre }}">Script</button>
                            <a href="{{ route('sistema.routers.edit', $r) }}" class="rounded-md px-2 py-1 text-xs font-medium text-purple-700 bg-purple-50 hover:bg-purple-100 dark:bg-purple-900/30 dark:text-purple-300">Editar</a>
                            <form action="{{ route('sistema.routers.destroy', $r) }}" method="POST" class="inline" onsubmit="return confirm('¿Eliminar este router?{{ $r->router_ip_pools_count > 0 ? ' También se eliminarán sus pools de IP ('.$r->router_ip_pools_count.').' : '' }}');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="rounded-md px-2 py-1 text-xs font-medium text-red-700 bg-red-50 hover:bg-red-100 dark:bg-red-900/30 dark:text-red-300">Eliminar</button>
                            </form>
                        </div>
                    </div>
                </article>
            @endforeach
        </div>

        @if($routers->hasPages())
            <div class="mt-6">
                {{ $routers->links() }}
            </div>
        @endif
    @endif
</div>

<div id="router-script-modal" class="hidden fixed inset-0 z-50 overflow-y-auto" aria-modal="true" role="dialog">
    <div class="flex min-h-full items-end sm:items-center justify-center p-4">
        <div class="fixed inset-0 bg-black/50" id="router-script-modal-backdrop"></div>
        <div class="relative w-full max-w-3xl rounded-xl bg-white dark:bg-gray-800 shadow-xl border border-gray-200 dark:border-gray-700">
            <div class="flex items-start justify-between gap-3 p-4 border-b border-gray-200 dark:border-gray-700">
                <div>
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Script PPPoE para consola</h2>
                    <p id="router-script-modal-subtitle" class="text-sm text-gray-500 dark:text-gray-400 mt-1"></p>
                </div>
                <button type="button" id="router-script-modal-close" class="p-2 rounded-lg text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-700" aria-label="Cerrar">&times;</button>
            </div>
            <div class="p-4">
                <p class="text-sm text-gray-600 dark:text-gray-400 mb-3">
                    Copiá el contenido y pegalo en la terminal de Winbox, SSH o WebFig del MikroTik.
                </p>
                <textarea id="router-script-textarea" readonly rows="16"
                    class="w-full font-mono text-xs sm:text-sm rounded-lg border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-900 text-gray-900 dark:text-gray-100 p-3 focus:outline-none focus:ring-2 focus:ring-purple-500/20"></textarea>
            </div>
            <div class="flex flex-wrap items-center justify-end gap-2 p-4 border-t border-gray-200 dark:border-gray-700">
                <a id="router-script-download" href="#" class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 hover:underline">Descargar .rsc</a>
                <button type="button" id="router-script-copy" class="px-4 py-2 bg-purple-600 text-white rounded-lg text-sm font-medium hover:bg-purple-700">Copiar al portapapeles</button>
            </div>
        </div>
    </div>
</div>

<script>
(function() {
    var csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    document.querySelectorAll('.router-test-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var url = this.getAttribute('data-url');
            var token = this.getAttribute('data-csrf') || csrf;
            this.disabled = true;
            fetch(url, { method: 'POST', headers: { 'X-CSRF-TOKEN': token, 'Accept': 'application/json', 'Content-Type': 'application/json' }, body: '{}' })
                .then(function(r) { return r.json(); })
                .then(function(d) { alert(d.success ? 'Conexión exitosa.' : (d.message || 'Error al conectar.')); })
                .catch(function() { alert('Error de red.'); })
                .finally(function() { btn.disabled = false; });
        });
    });
    document.querySelectorAll('.router-sync-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            if (!confirm('¿Sincronizar usuarios PPPoE de la BD a este router?')) return;
            var url = this.getAttribute('data-url');
            var token = this.getAttribute('data-csrf') || csrf;
            this.disabled = true;
            fetch(url, { method: 'POST', headers: { 'X-CSRF-TOKEN': token, 'Accept': 'application/json', 'Content-Type': 'application/json' }, body: JSON.stringify({ remove_orphans: false }) })
                .then(function(r) { return r.json().then(function(d) { return { ok: r.ok, data: d }; }); })
                .then(function(res) {
                    var d = res.data;
                    var msg = (d.message ? d.message + '\n\n' : '');
                    msg += 'Servicios en BD: ' + (d.servicios_total ?? 0);
                    msg += '\nAñadidos: ' + (d.added || 0) + ', Actualizados: ' + (d.updated || 0) + ', Eliminados: ' + (d.removed || 0);
                    if (d.errors && d.errors.length) msg += '\nErrores:\n' + d.errors.join('\n');
                    alert(msg);
                })
                .catch(function() { alert('Error de red.'); })
                .finally(function() { btn.disabled = false; });
        });
    });

    var scriptModal = document.getElementById('router-script-modal');
    var scriptTextarea = document.getElementById('router-script-textarea');
    var scriptSubtitle = document.getElementById('router-script-modal-subtitle');
    var scriptDownload = document.getElementById('router-script-download');
    var scriptCopyBtn = document.getElementById('router-script-copy');

    function closeScriptModal() {
        if (scriptModal) scriptModal.classList.add('hidden');
    }

    function openScriptModal() {
        if (scriptModal) scriptModal.classList.remove('hidden');
    }

    document.getElementById('router-script-modal-close')?.addEventListener('click', closeScriptModal);
    document.getElementById('router-script-modal-backdrop')?.addEventListener('click', closeScriptModal);

    document.querySelectorAll('.router-export-script-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var url = this.getAttribute('data-url');
            var nombre = this.getAttribute('data-nombre') || 'Router';
            btn.disabled = true;
            fetch(url, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
                .then(function(r) { return r.json(); })
                .then(function(d) {
                    scriptTextarea.value = d.script || '';
                    scriptSubtitle.textContent = (d.router?.nombre || nombre) + ' · ' + (d.usuarios || 0) + ' usuario(s)';
                    if (d.download_url) scriptDownload.href = d.download_url;
                    openScriptModal();
                    scriptTextarea.focus();
                    scriptTextarea.select();
                })
                .catch(function() { alert('No se pudo cargar el script.'); })
                .finally(function() { btn.disabled = false; });
        });
    });

    scriptCopyBtn?.addEventListener('click', function() {
        var text = scriptTextarea.value;
        if (!text) return;
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(function() {
                alert('Script copiado al portapapeles.');
            }).catch(function() {
                scriptTextarea.select();
                document.execCommand('copy');
                alert('Script copiado al portapapeles.');
            });
        } else {
            scriptTextarea.select();
            document.execCommand('copy');
            alert('Script copiado al portapapeles.');
        }
    });
})();
</script>
@endsection
