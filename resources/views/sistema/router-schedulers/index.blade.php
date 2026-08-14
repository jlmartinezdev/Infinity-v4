@extends('layouts.app')

@section('title', 'Schedulers MikroTik')

@section('content')
<div class="max-w-7xl mx-auto">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">Schedulers MikroTik</h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Leé schedulers de un router, guardalos en BD y sincronizalos a otro</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('sistema.router-scripts.index') }}"
                class="inline-flex items-center px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-gray-200 rounded-lg font-medium hover:bg-gray-300 dark:hover:bg-gray-600">
                Scripts
            </a>
            <a href="{{ route('sistema.routers.index') }}"
                class="inline-flex items-center px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-gray-200 rounded-lg font-medium hover:bg-gray-300 dark:hover:bg-gray-600">
                Routers
            </a>
            <button type="button" id="btn-abrir-importar"
                class="inline-flex items-center px-4 py-2 bg-purple-600 text-white rounded-lg font-medium hover:bg-purple-700 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:ring-offset-2">
                Leer desde router
            </button>
        </div>
    </div>

    @if(session('success'))
        <div class="mb-4 rounded-lg border border-green-200 bg-green-50 dark:bg-green-900/20 dark:border-green-800 px-4 py-3 text-sm text-green-800 dark:text-green-200">
            {{ session('success') }}
        </div>
    @endif

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 overflow-hidden">
        <form method="GET" action="{{ route('sistema.router-schedulers.index') }}" class="p-4 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-700/50">
            <div class="flex flex-col sm:flex-row gap-3">
                <div class="flex-1">
                    <input type="text" name="buscar" value="{{ request('buscar') }}"
                        placeholder="Buscar por nombre, on-event, notas..."
                        class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                </div>
                <div class="sm:w-64">
                    <select name="router_origen_id" class="w-full py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                        <option value="todos">Todos los orígenes</option>
                        @foreach($routers as $r)
                            <option value="{{ $r->router_id }}" {{ (string) request('router_origen_id') === (string) $r->router_id ? 'selected' : '' }}>
                                {{ $r->nombre }} ({{ $r->ip }})
                            </option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="inline-flex items-center justify-center px-6 py-2.5 bg-purple-600 text-white rounded-lg font-medium hover:bg-purple-700">
                    Buscar
                </button>
            </div>
        </form>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-700/50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Nombre</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Interval / Start</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Origen</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Estado</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($schedulers as $s)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                            <td class="px-4 py-3">
                                <div class="text-sm font-medium text-gray-900 dark:text-gray-100 font-mono">{{ $s->nombre }}</div>
                                @if($s->on_event)
                                    <div class="text-[11px] text-gray-400 mt-0.5 truncate max-w-xs" title="{{ $s->on_event }}">{{ \Illuminate\Support\Str::limit($s->on_event, 80) }}</div>
                                @endif
                                @if($s->notas)
                                    <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">{{ $s->notas }}</div>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300 whitespace-nowrap">
                                <span class="font-mono">{{ $s->interval ?: '—' }}</span>
                                <span class="block text-xs text-gray-500">
                                    {{ $s->start_date ?: '' }} {{ $s->start_time ?: '' }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">
                                @if($s->routerOrigen)
                                    {{ $s->routerOrigen->nombre }}
                                    <span class="block text-xs text-gray-500 font-mono">{{ $s->routerOrigen->ip }}</span>
                                @else
                                    <span class="text-gray-400">—</span>
                                @endif
                                <span class="block text-[11px] text-gray-400 mt-1">
                                    Leído: {{ $s->leido_en?->format('d/m/Y H:i') ?? '—' }}
                                    · Sync: {{ $s->sincronizado_en?->format('d/m/Y H:i') ?? '—' }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-sm">
                                @if($s->disabled)
                                    <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300">Disabled</span>
                                @else
                                    <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 dark:bg-green-900/30 text-green-800 dark:text-green-300">Enabled</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right text-sm whitespace-nowrap">
                                <button type="button"
                                    class="btn-sync-scheduler text-green-600 dark:text-green-400 hover:underline font-medium mr-3"
                                    data-nombre="{{ $s->nombre }}"
                                    data-url="{{ route('sistema.router-schedulers.sync', $s) }}">
                                    Sync a router
                                </button>
                                <a href="{{ route('sistema.router-schedulers.edit', $s) }}" class="text-purple-600 dark:text-purple-400 hover:underline font-medium mr-3">Editar</a>
                                <form action="{{ route('sistema.router-schedulers.destroy', $s) }}" method="POST" class="inline" onsubmit="return confirm('¿Eliminar este scheduler de la BD?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-red-600 dark:text-red-400 hover:underline font-medium">Eliminar</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-10 text-center text-sm text-gray-500 dark:text-gray-400">
                                No hay schedulers guardados. Usá «Leer desde router» para importar.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($schedulers->hasPages())
            <div class="p-4 border-t border-gray-200 dark:border-gray-700">
                {{ $schedulers->links() }}
            </div>
        @endif
    </div>
</div>

<div id="modal-importar" class="hidden fixed inset-0 z-50 overflow-y-auto" aria-modal="true" role="dialog">
    <div class="flex min-h-full items-end sm:items-center justify-center p-4">
        <div class="fixed inset-0 bg-black/50" data-close-importar></div>
        <div class="relative w-full max-w-3xl rounded-xl bg-white dark:bg-gray-800 shadow-xl border border-gray-200 dark:border-gray-700">
            <div class="flex items-start justify-between gap-3 p-4 border-b border-gray-200 dark:border-gray-700">
                <div>
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Leer schedulers desde router</h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Seleccioná el origen, listá y guardá en la BD</p>
                </div>
                <button type="button" class="p-2 rounded-lg text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-700" data-close-importar aria-label="Cerrar">&times;</button>
            </div>
            <div class="p-4 space-y-4">
                <div class="flex flex-col sm:flex-row gap-3">
                    <select id="import-router-id" class="flex-1 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                        <option value="">Seleccioná un router...</option>
                        @foreach($routers as $r)
                            <option value="{{ $r->router_id }}" {{ (string) request('router_origen_id') === (string) $r->router_id ? 'selected' : '' }}>
                                {{ $r->nombre }} ({{ $r->ip }})
                            </option>
                        @endforeach
                    </select>
                    <button type="button" id="btn-listar-remotos" class="px-4 py-2.5 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700">
                        Listar schedulers
                    </button>
                </div>
                <div id="import-lista" class="max-h-80 overflow-y-auto rounded-lg border border-gray-200 dark:border-gray-700 divide-y divide-gray-100 dark:divide-gray-700">
                    <p class="p-4 text-sm text-gray-500">Elegí un router y listá sus schedulers.</p>
                </div>
            </div>
            <div class="flex flex-wrap items-center justify-between gap-2 p-4 border-t border-gray-200 dark:border-gray-700">
                <label class="inline-flex items-center gap-2 text-sm text-gray-600 dark:text-gray-300">
                    <input type="checkbox" id="import-select-all" class="rounded border-gray-300">
                    Seleccionar todos
                </label>
                <div class="flex gap-2">
                    <button type="button" class="px-4 py-2 text-sm text-gray-600 dark:text-gray-300" data-close-importar>Cancelar</button>
                    <button type="button" id="btn-guardar-import" class="px-4 py-2 bg-purple-600 text-white rounded-lg text-sm font-medium hover:bg-purple-700" disabled>
                        Guardar en BD
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<div id="modal-sync" class="hidden fixed inset-0 z-50 overflow-y-auto" aria-modal="true" role="dialog">
    <div class="flex min-h-full items-end sm:items-center justify-center p-4">
        <div class="fixed inset-0 bg-black/50" data-close-sync></div>
        <div class="relative w-full max-w-lg rounded-xl bg-white dark:bg-gray-800 shadow-xl border border-gray-200 dark:border-gray-700">
            <div class="flex items-start justify-between gap-3 p-4 border-b border-gray-200 dark:border-gray-700">
                <div>
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Sincronizar scheduler</h2>
                    <p id="sync-subtitle" class="text-sm text-gray-500 dark:text-gray-400 mt-1"></p>
                </div>
                <button type="button" class="p-2 rounded-lg text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-700" data-close-sync aria-label="Cerrar">&times;</button>
            </div>
            <div class="p-4 space-y-3">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Router destino</label>
                <select id="sync-router-id" class="w-full py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                    <option value="">Seleccioná un router...</option>
                    @foreach($routers as $r)
                        <option value="{{ $r->router_id }}">{{ $r->nombre }} ({{ $r->ip }})</option>
                    @endforeach
                </select>
                <p class="text-xs text-gray-500 dark:text-gray-400">Si ya existe en el destino (mismo nombre), se actualiza.</p>
            </div>
            <div class="flex justify-end gap-2 p-4 border-t border-gray-200 dark:border-gray-700">
                <button type="button" class="px-4 py-2 text-sm text-gray-600 dark:text-gray-300" data-close-sync>Cancelar</button>
                <button type="button" id="btn-confirmar-sync" class="px-4 py-2 bg-green-600 text-white rounded-lg text-sm font-medium hover:bg-green-700">
                    Sincronizar
                </button>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    var csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    var listUrl = @json(route('sistema.router-schedulers.list-remote'));
    var importUrl = @json(route('sistema.router-schedulers.import'));

    var modalImport = document.getElementById('modal-importar');
    var modalSync = document.getElementById('modal-sync');
    var lista = document.getElementById('import-lista');
    var btnGuardar = document.getElementById('btn-guardar-import');
    var syncUrl = null;

    function open(el) { el?.classList.remove('hidden'); }
    function close(el) { el?.classList.add('hidden'); }

    document.getElementById('btn-abrir-importar')?.addEventListener('click', function () { open(modalImport); });
    document.querySelectorAll('[data-close-importar]').forEach(function (el) {
        el.addEventListener('click', function () { close(modalImport); });
    });
    document.querySelectorAll('[data-close-sync]').forEach(function (el) {
        el.addEventListener('click', function () { close(modalSync); });
    });

    document.getElementById('btn-listar-remotos')?.addEventListener('click', function () {
        var routerId = document.getElementById('import-router-id').value;
        if (!routerId) { alert('Seleccioná un router.'); return; }
        var btn = this;
        btn.disabled = true;
        lista.innerHTML = '<p class="p-4 text-sm text-gray-500">Consultando router...</p>';
        btnGuardar.disabled = true;

        fetch(listUrl + '?router_id=' + encodeURIComponent(routerId), {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        })
            .then(function (r) { return r.json().then(function (d) { return { ok: r.ok, data: d }; }); })
            .then(function (res) {
                if (!res.ok || !res.data.success) {
                    lista.innerHTML = '<p class="p-4 text-sm text-red-600">' + (res.data.message || 'Error al listar') + '</p>';
                    return;
                }
                var items = res.data.schedulers || [];
                if (!items.length) {
                    lista.innerHTML = '<p class="p-4 text-sm text-gray-500">Este router no tiene schedulers en /system/scheduler.</p>';
                    return;
                }
                lista.innerHTML = items.map(function (s) {
                    return '<label class="flex items-start gap-3 p-3 hover:bg-gray-50 dark:hover:bg-gray-700/40 cursor-pointer">'
                        + '<input type="checkbox" class="import-check mt-1 rounded border-gray-300" value="' + s.name.replace(/"/g, '&quot;') + '">'
                        + '<span class="min-w-0">'
                        + '<span class="block text-sm font-mono font-medium text-gray-900 dark:text-gray-100">' + s.name + '</span>'
                        + '<span class="block text-xs text-gray-500 mt-0.5">'
                        + (s.interval || 'sin interval')
                        + (s.start_time ? ' · start ' + s.start_time : '')
                        + (s.disabled === 'yes' ? ' · disabled' : '')
                        + '</span>'
                        + (s.on_event_preview ? '<span class="block text-[11px] text-gray-400 mt-1 truncate">' + s.on_event_preview + '</span>' : '')
                        + '</span></label>';
                }).join('');
                btnGuardar.disabled = false;
                document.getElementById('import-select-all').checked = false;
            })
            .catch(function () {
                lista.innerHTML = '<p class="p-4 text-sm text-red-600">Error de red.</p>';
            })
            .finally(function () { btn.disabled = false; });
    });

    document.getElementById('import-select-all')?.addEventListener('change', function () {
        document.querySelectorAll('.import-check').forEach(function (cb) { cb.checked = this.checked; }.bind(this));
    });

    btnGuardar?.addEventListener('click', function () {
        var routerId = document.getElementById('import-router-id').value;
        var nombres = Array.from(document.querySelectorAll('.import-check:checked')).map(function (cb) { return cb.value; });
        if (!routerId) { alert('Seleccioná un router.'); return; }
        if (!nombres.length) { alert('Seleccioná al menos un scheduler.'); return; }
        var btn = this;
        btn.disabled = true;
        fetch(importUrl, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json', 'Content-Type': 'application/json' },
            body: JSON.stringify({ router_id: parseInt(routerId, 10), nombres: nombres })
        })
            .then(function (r) { return r.json().then(function (d) { return { ok: r.ok, data: d }; }); })
            .then(function (res) {
                var d = res.data;
                var msg = d.message || '';
                if (d.errors && d.errors.length) msg += '\n' + d.errors.join('\n');
                alert(msg || (res.ok ? 'OK' : 'Error'));
                if (res.ok) window.location.reload();
            })
            .catch(function () { alert('Error de red.'); })
            .finally(function () { btn.disabled = false; });
    });

    document.querySelectorAll('.btn-sync-scheduler').forEach(function (btn) {
        btn.addEventListener('click', function () {
            syncUrl = this.getAttribute('data-url');
            document.getElementById('sync-subtitle').textContent = this.getAttribute('data-nombre') || '';
            document.getElementById('sync-router-id').value = '';
            open(modalSync);
        });
    });

    document.getElementById('btn-confirmar-sync')?.addEventListener('click', function () {
        var routerId = document.getElementById('sync-router-id').value;
        if (!syncUrl || !routerId) { alert('Seleccioná el router destino.'); return; }
        if (!confirm('¿Escribir este scheduler en el router destino?')) return;
        var btn = this;
        btn.disabled = true;
        fetch(syncUrl, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json', 'Content-Type': 'application/json' },
            body: JSON.stringify({ router_id: parseInt(routerId, 10) })
        })
            .then(function (r) { return r.json().then(function (d) { return { ok: r.ok, data: d }; }); })
            .then(function (res) {
                alert(res.data.message || (res.ok ? 'Sincronizado.' : 'Error'));
                if (res.ok) window.location.reload();
            })
            .catch(function () { alert('Error de red.'); })
            .finally(function () { btn.disabled = false; });
    });
})();
</script>
@endsection
