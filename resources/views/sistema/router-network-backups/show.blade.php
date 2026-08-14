@extends('layouts.app')

@section('title', $backup->nombre ?: 'Backup red')

@section('content')
<div class="max-w-7xl mx-auto">
    <div class="mb-6 flex flex-col lg:flex-row lg:items-start lg:justify-between gap-4">
        <div>
            <a href="{{ route('sistema.router-network-backups.index') }}" class="text-sm text-purple-600 dark:text-purple-400 hover:underline">← Backups de red</a>
            <h1 class="mt-2 text-2xl font-bold text-gray-900 dark:text-gray-100">{{ $backup->nombre ?: ('Backup #'.$backup->router_network_backup_id) }}</h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                Origen: {{ $backup->routerOrigen?->nombre ?? '—' }}
                @if($backup->routerOrigen)
                    <span class="font-mono">({{ $backup->routerOrigen->ip }})</span>
                @endif
                · Leído {{ $backup->leido_en?->format('d/m/Y H:i') ?? '—' }}
            </p>
            @if($backup->notas)
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">{{ $backup->notas }}</p>
            @endif
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('sistema.router-network-backups.export', $backup) }}"
                class="inline-flex items-center px-4 py-2 bg-amber-600 text-white rounded-lg text-sm font-medium hover:bg-amber-700">Descargar .rsc</a>
            <button type="button" id="btn-sync"
                class="inline-flex items-center px-4 py-2 bg-green-600 text-white rounded-lg text-sm font-medium hover:bg-green-700">
                Sync a router
            </button>
        </div>
    </div>

    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-6">
        <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-4 text-center">
            <p class="text-2xl font-bold tabular-nums text-gray-900 dark:text-gray-100">{{ $backup->cant_ipv4 }}</p>
            <p class="text-xs text-gray-500">IPv4</p>
        </div>
        <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-4 text-center">
            <p class="text-2xl font-bold tabular-nums text-gray-900 dark:text-gray-100">{{ $backup->cant_ipv6 }}</p>
            <p class="text-xs text-gray-500">IPv6</p>
        </div>
        <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-4 text-center">
            <p class="text-2xl font-bold tabular-nums text-gray-900 dark:text-gray-100">{{ $backup->cant_rutas_v4 }}</p>
            <p class="text-xs text-gray-500">Rutas v4</p>
        </div>
        <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-4 text-center">
            <p class="text-2xl font-bold tabular-nums text-gray-900 dark:text-gray-100">{{ $backup->cant_rutas_v6 }}</p>
            <p class="text-xs text-gray-500">Rutas v6</p>
        </div>
    </div>

    @php
        $ipv4 = $backup->addresses->where('familia', 'ipv4');
        $ipv6 = $backup->addresses->where('familia', 'ipv6');
        $rutasV4 = $backup->routes->where('familia', 'ipv4');
        $rutasV6 = $backup->routes->where('familia', 'ipv6');
    @endphp

    <div class="space-y-6">
        <section class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 overflow-hidden">
            <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700 font-semibold text-gray-900 dark:text-gray-100">Direcciones IPv4</div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50 dark:bg-gray-700/50 text-xs uppercase text-gray-500">
                        <tr>
                            <th class="px-4 py-2 text-left">Address</th>
                            <th class="px-4 py-2 text-left">Interface / puerto</th>
                            <th class="px-4 py-2 text-left">Network</th>
                            <th class="px-4 py-2 text-left">Comment</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        @forelse($ipv4 as $a)
                            <tr>
                                <td class="px-4 py-2 font-mono {{ $a->disabled ? 'opacity-50' : '' }}">{{ $a->address }}</td>
                                <td class="px-4 py-2 font-mono">{{ $a->interface ?: '—' }}</td>
                                <td class="px-4 py-2 font-mono text-gray-500">{{ $a->network ?: '—' }}</td>
                                <td class="px-4 py-2 text-gray-500">{{ $a->comment ?: '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-4 py-6 text-center text-gray-500">Sin IPv4 estáticas</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 overflow-hidden">
            <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700 font-semibold text-gray-900 dark:text-gray-100">Direcciones IPv6</div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50 dark:bg-gray-700/50 text-xs uppercase text-gray-500">
                        <tr>
                            <th class="px-4 py-2 text-left">Address</th>
                            <th class="px-4 py-2 text-left">Interface / puerto</th>
                            <th class="px-4 py-2 text-left">Comment</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        @forelse($ipv6 as $a)
                            <tr>
                                <td class="px-4 py-2 font-mono {{ $a->disabled ? 'opacity-50' : '' }}">{{ $a->address }}</td>
                                <td class="px-4 py-2 font-mono">{{ $a->interface ?: '—' }}</td>
                                <td class="px-4 py-2 text-gray-500">{{ $a->comment ?: '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="px-4 py-6 text-center text-gray-500">Sin IPv6 estáticas</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 overflow-hidden">
            <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700 font-semibold text-gray-900 dark:text-gray-100">Rutas IPv4 estáticas</div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50 dark:bg-gray-700/50 text-xs uppercase text-gray-500">
                        <tr>
                            <th class="px-4 py-2 text-left">Dst</th>
                            <th class="px-4 py-2 text-left">Gateway</th>
                            <th class="px-4 py-2 text-left">Dist</th>
                            <th class="px-4 py-2 text-left">Comment</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        @forelse($rutasV4 as $r)
                            <tr>
                                <td class="px-4 py-2 font-mono {{ $r->disabled ? 'opacity-50' : '' }}">{{ $r->dst_address }}</td>
                                <td class="px-4 py-2 font-mono">{{ $r->gateway ?: '—' }}</td>
                                <td class="px-4 py-2 font-mono">{{ $r->distance ?? '—' }}</td>
                                <td class="px-4 py-2 text-gray-500">{{ $r->comment ?: '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-4 py-6 text-center text-gray-500">Sin rutas IPv4 estáticas</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 overflow-hidden">
            <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700 font-semibold text-gray-900 dark:text-gray-100">Rutas IPv6 estáticas</div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50 dark:bg-gray-700/50 text-xs uppercase text-gray-500">
                        <tr>
                            <th class="px-4 py-2 text-left">Dst</th>
                            <th class="px-4 py-2 text-left">Gateway</th>
                            <th class="px-4 py-2 text-left">Dist</th>
                            <th class="px-4 py-2 text-left">Comment</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        @forelse($rutasV6 as $r)
                            <tr>
                                <td class="px-4 py-2 font-mono {{ $r->disabled ? 'opacity-50' : '' }}">{{ $r->dst_address }}</td>
                                <td class="px-4 py-2 font-mono">{{ $r->gateway ?: '—' }}</td>
                                <td class="px-4 py-2 font-mono">{{ $r->distance ?? '—' }}</td>
                                <td class="px-4 py-2 text-gray-500">{{ $r->comment ?: '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-4 py-6 text-center text-gray-500">Sin rutas IPv6 estáticas</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</div>

<div id="modal-sync" class="hidden fixed inset-0 z-50 overflow-y-auto">
    <div class="flex min-h-full items-end sm:items-center justify-center p-4">
        <div class="fixed inset-0 bg-black/50" data-close-sync></div>
        <div class="relative w-full max-w-lg rounded-xl bg-white dark:bg-gray-800 shadow-xl border border-gray-200 dark:border-gray-700">
            <div class="p-4 border-b border-gray-200 dark:border-gray-700">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Sincronizar backup a router</h2>
                <p class="text-sm text-gray-500 mt-1">Crea las IPs y rutas estáticas en el destino (no borra las existentes)</p>
            </div>
            <div class="p-4">
                <select id="sync-router-id" class="w-full py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                    <option value="">Seleccioná destino...</option>
                    @foreach($routers as $r)
                        <option value="{{ $r->router_id }}">{{ $r->nombre }} ({{ $r->ip }})</option>
                    @endforeach
                </select>
            </div>
            <div class="flex justify-end gap-2 p-4 border-t border-gray-200 dark:border-gray-700">
                <button type="button" class="px-4 py-2 text-sm" data-close-sync>Cancelar</button>
                <button type="button" id="btn-confirmar-sync" class="px-4 py-2 bg-green-600 text-white rounded-lg text-sm font-medium hover:bg-green-700">Sincronizar</button>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    var csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    var syncUrl = @json(route('sistema.router-network-backups.sync', $backup));
    var modal = document.getElementById('modal-sync');
    document.getElementById('btn-sync')?.addEventListener('click', function () { modal.classList.remove('hidden'); });
    document.querySelectorAll('[data-close-sync]').forEach(function (el) {
        el.addEventListener('click', function () { modal.classList.add('hidden'); });
    });
    document.getElementById('btn-confirmar-sync')?.addEventListener('click', function () {
        var routerId = document.getElementById('sync-router-id').value;
        if (!routerId) { alert('Seleccioná el router destino.'); return; }
        if (!confirm('¿Crear IPs y rutas de este backup en el router destino?')) return;
        var btn = this;
        btn.disabled = true;
        fetch(syncUrl, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json', 'Content-Type': 'application/json' },
            body: JSON.stringify({ router_id: parseInt(routerId, 10) })
        })
            .then(function (r) { return r.json().then(function (d) { return { ok: r.ok, data: d }; }); })
            .then(function (res) {
                alert(res.data.message || (res.ok ? 'OK' : 'Error'));
                if (res.ok) window.location.reload();
            })
            .catch(function () { alert('Error de red.'); })
            .finally(function () { btn.disabled = false; });
    });
})();
</script>
@endsection
