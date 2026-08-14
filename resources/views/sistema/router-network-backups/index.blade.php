@extends('layouts.app')

@section('title', 'Backup red MikroTik')

@section('content')
<div class="max-w-7xl mx-auto">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">Backup de red</h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">IPv4/IPv6 estáticas (con interfaz) y rutas estáticas</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('sistema.routers.index') }}"
                class="inline-flex items-center px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-gray-200 rounded-lg font-medium hover:bg-gray-300 dark:hover:bg-gray-600">
                Routers
            </a>
            <button type="button" id="btn-abrir-importar"
                class="inline-flex items-center px-4 py-2 bg-purple-600 text-white rounded-lg font-medium hover:bg-purple-700">
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
        <form method="GET" action="{{ route('sistema.router-network-backups.index') }}" class="p-4 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-700/50">
            <div class="flex flex-col sm:flex-row gap-3">
                <div class="flex-1">
                    <input type="text" name="buscar" value="{{ request('buscar') }}" placeholder="Buscar por nombre o notas..."
                        class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                </div>
                <div class="sm:w-64">
                    <select name="router_origen_id" class="w-full py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                        <option value="todos">Todos los routers</option>
                        @foreach($routers as $r)
                            <option value="{{ $r->router_id }}" {{ (string) request('router_origen_id') === (string) $r->router_id ? 'selected' : '' }}>
                                {{ $r->nombre }} ({{ $r->ip }})
                            </option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="px-6 py-2.5 bg-purple-600 text-white rounded-lg font-medium hover:bg-purple-700">Filtrar</button>
            </div>
        </form>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-700/50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Backup</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Origen</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">IPs</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Rutas</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Leído</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($backups as $b)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                            <td class="px-4 py-3">
                                <div class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ $b->nombre ?: ('#'.$b->router_network_backup_id) }}</div>
                                @if($b->notas)
                                    <div class="text-xs text-gray-500 mt-0.5">{{ $b->notas }}</div>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">
                                {{ $b->routerOrigen?->nombre ?? '—' }}
                                <span class="block text-xs font-mono text-gray-500">{{ $b->routerOrigen?->ip }}</span>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300 whitespace-nowrap">
                                <span class="font-mono">v4:{{ $b->cant_ipv4 }}</span>
                                <span class="text-gray-400 mx-1">/</span>
                                <span class="font-mono">v6:{{ $b->cant_ipv6 }}</span>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300 whitespace-nowrap">
                                <span class="font-mono">v4:{{ $b->cant_rutas_v4 }}</span>
                                <span class="text-gray-400 mx-1">/</span>
                                <span class="font-mono">v6:{{ $b->cant_rutas_v6 }}</span>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-400 whitespace-nowrap">
                                {{ $b->leido_en?->format('d/m/Y H:i') ?? '—' }}
                                @if($b->sincronizado_en)
                                    <span class="block text-[11px] text-gray-400">Sync {{ $b->sincronizado_en->format('d/m/Y H:i') }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right text-sm whitespace-nowrap">
                                <a href="{{ route('sistema.router-network-backups.show', $b) }}" class="text-purple-600 dark:text-purple-400 hover:underline font-medium mr-3">Ver</a>
                                <a href="{{ route('sistema.router-network-backups.export', $b) }}" class="text-amber-600 dark:text-amber-400 hover:underline font-medium mr-3">.rsc</a>
                                <form action="{{ route('sistema.router-network-backups.destroy', $b) }}" method="POST" class="inline" onsubmit="return confirm('¿Eliminar este backup?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-red-600 dark:text-red-400 hover:underline font-medium">Eliminar</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-10 text-center text-sm text-gray-500">
                                No hay backups. Usá «Leer desde router» para crear uno.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($backups->hasPages())
            <div class="p-4 border-t border-gray-200 dark:border-gray-700">{{ $backups->links() }}</div>
        @endif
    </div>
</div>

<div id="modal-importar" class="hidden fixed inset-0 z-50 overflow-y-auto" aria-modal="true" role="dialog">
    <div class="flex min-h-full items-end sm:items-center justify-center p-4">
        <div class="fixed inset-0 bg-black/50" data-close-importar></div>
        <div class="relative w-full max-w-4xl rounded-xl bg-white dark:bg-gray-800 shadow-xl border border-gray-200 dark:border-gray-700">
            <div class="flex items-start justify-between gap-3 p-4 border-b border-gray-200 dark:border-gray-700">
                <div>
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Leer red desde router</h2>
                    <p class="text-sm text-gray-500 mt-1">Solo direcciones y rutas estáticas</p>
                </div>
                <button type="button" class="p-2 rounded-lg text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-700" data-close-importar>&times;</button>
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
                    <button type="button" id="btn-preview" class="px-4 py-2.5 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700">Vista previa</button>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <input type="text" id="import-nombre" placeholder="Nombre del backup (opcional)"
                        class="px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                    <input type="text" id="import-notas" placeholder="Notas (opcional)"
                        class="px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                </div>
                <div id="preview-counts" class="text-sm text-gray-600 dark:text-gray-300"></div>
                <div id="preview-box" class="max-h-96 overflow-y-auto rounded-lg border border-gray-200 dark:border-gray-700 p-3 text-xs font-mono whitespace-pre-wrap text-gray-800 dark:text-gray-200 bg-gray-50 dark:bg-gray-900">
                    Elegí un router y generá la vista previa.
                </div>
            </div>
            <div class="flex justify-end gap-2 p-4 border-t border-gray-200 dark:border-gray-700">
                <button type="button" class="px-4 py-2 text-sm text-gray-600" data-close-importar>Cancelar</button>
                <button type="button" id="btn-guardar-backup" class="px-4 py-2 bg-purple-600 text-white rounded-lg text-sm font-medium hover:bg-purple-700" disabled>
                    Guardar backup
                </button>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    var csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    var previewUrl = @json(route('sistema.router-network-backups.preview'));
    var importUrl = @json(route('sistema.router-network-backups.import'));
    var modal = document.getElementById('modal-importar');
    var btnSave = document.getElementById('btn-guardar-backup');
    var previewBox = document.getElementById('preview-box');
    var previewCounts = document.getElementById('preview-counts');

    function open() { modal?.classList.remove('hidden'); }
    function close() { modal?.classList.add('hidden'); }

    document.getElementById('btn-abrir-importar')?.addEventListener('click', open);
    document.querySelectorAll('[data-close-importar]').forEach(function (el) {
        el.addEventListener('click', close);
    });

    function formatPreview(d) {
        var lines = [];
        lines.push('=== IPv4 (' + (d.ipv4||[]).length + ') ===');
        (d.ipv4||[]).forEach(function (a) {
            lines.push(a.address + '  @ ' + a.interface + (a.disabled === 'yes' ? ' [disabled]' : ''));
        });
        lines.push('');
        lines.push('=== IPv6 (' + (d.ipv6||[]).length + ') ===');
        (d.ipv6||[]).forEach(function (a) {
            lines.push(a.address + '  @ ' + a.interface + (a.disabled === 'yes' ? ' [disabled]' : ''));
        });
        lines.push('');
        lines.push('=== Rutas IPv4 (' + (d.rutas_v4||[]).length + ') ===');
        (d.rutas_v4||[]).forEach(function (r) {
            lines.push(r.dst_address + ' via ' + (r.gateway || '-') + ' dist=' + (r.distance || '-'));
        });
        lines.push('');
        lines.push('=== Rutas IPv6 (' + (d.rutas_v6||[]).length + ') ===');
        (d.rutas_v6||[]).forEach(function (r) {
            lines.push(r.dst_address + ' via ' + (r.gateway || '-') + ' dist=' + (r.distance || '-'));
        });
        return lines.join('\n');
    }

    document.getElementById('btn-preview')?.addEventListener('click', function () {
        var routerId = document.getElementById('import-router-id').value;
        if (!routerId) { alert('Seleccioná un router.'); return; }
        var btn = this;
        btn.disabled = true;
        previewBox.textContent = 'Consultando router...';
        previewCounts.textContent = '';
        btnSave.disabled = true;
        fetch(previewUrl + '?router_id=' + encodeURIComponent(routerId), {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        })
            .then(function (r) { return r.json().then(function (d) { return { ok: r.ok, data: d }; }); })
            .then(function (res) {
                if (!res.ok || !res.data.success) {
                    previewBox.textContent = res.data.message || 'Error al leer';
                    return;
                }
                var c = res.data.counts || {};
                previewCounts.textContent = 'IPv4: ' + (c.ipv4||0) + ' · IPv6: ' + (c.ipv6||0) + ' · Rutas v4: ' + (c.rutas_v4||0) + ' · Rutas v6: ' + (c.rutas_v6||0);
                previewBox.textContent = formatPreview(res.data);
                btnSave.disabled = false;
            })
            .catch(function () { previewBox.textContent = 'Error de red.'; })
            .finally(function () { btn.disabled = false; });
    });

    btnSave?.addEventListener('click', function () {
        var routerId = document.getElementById('import-router-id').value;
        if (!routerId) { alert('Seleccioná un router.'); return; }
        var btn = this;
        btn.disabled = true;
        fetch(importUrl, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json', 'Content-Type': 'application/json' },
            body: JSON.stringify({
                router_id: parseInt(routerId, 10),
                nombre: document.getElementById('import-nombre').value || null,
                notas: document.getElementById('import-notas').value || null
            })
        })
            .then(function (r) { return r.json().then(function (d) { return { ok: r.ok, data: d }; }); })
            .then(function (res) {
                alert(res.data.message || (res.ok ? 'OK' : 'Error'));
                if (res.ok && res.data.show_url) {
                    window.location.href = res.data.show_url;
                } else if (res.ok) {
                    window.location.reload();
                }
            })
            .catch(function () { alert('Error de red.'); })
            .finally(function () { btn.disabled = false; });
    });
})();
</script>
@endsection
