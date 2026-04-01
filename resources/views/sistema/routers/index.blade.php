@extends('layouts.app')

@section('title', 'Routers')

@section('content')
<div class="max-w-7xl mx-auto">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">Routers</h1>
        <a href="{{ route('sistema.routers.create') }}"
            class="inline-flex items-center px-4 py-2 bg-purple-600 text-white rounded-lg font-medium hover:bg-purple-700 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:ring-offset-2">
            Nuevo router
        </a>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 overflow-hidden">
        <form method="GET" action="{{ route('sistema.routers.index') }}" class="p-4 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-700/50">
            <div class="flex flex-col sm:flex-row gap-3">
                <div class="flex-1">
                    <input type="text" name="buscar" value="{{ request('buscar') }}"
                        placeholder="Buscar por nombre, IP o estado..."
                        class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none transition-colors bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
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
                    Buscar
                </button>
            </div>
        </form>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-700/50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">ID</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Nombre</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Nodo</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">IP</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">IP Loopback</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Puerto API</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Estado</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Acciones</th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($routers as $r)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                            <td class="px-4 py-3 text-sm text-gray-900 dark:text-gray-100">{{ $r->router_id }}</td>
                            <td class="px-4 py-3 text-sm font-medium text-gray-900 dark:text-gray-100">{{ $r->nombre }}</td>
                            <td class="px-4 py-3 text-sm text-gray-900 dark:text-gray-100">{{ $r->nodo?->descripcion ?? '—' }}</td>
                            <td class="px-4 py-3 text-sm text-gray-900 dark:text-gray-100">{{ $r->ip }}</td>
                            <td class="px-4 py-3 text-sm text-gray-900 dark:text-gray-100">{{ $r->ip_loopback ?? '—' }}</td>
                            <td class="px-4 py-3 text-sm text-gray-900 dark:text-gray-100">{{ $r->api_port ?? '—' }}</td>
                            <td class="px-4 py-3 text-sm text-gray-900 dark:text-gray-100">{{ $r->estado ?? '—' }}</td>
                            <td class="px-4 py-3 text-right text-sm">
                                <button type="button" class="router-test-btn text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300 font-medium mr-2"
                                    data-url="{{ route('sistema.routers.test-connection', $r) }}" data-csrf="{{ csrf_token() }}"
                                    title="Probar conexión MikroTik API">Probar</button>
                                <button type="button" class="router-sync-btn text-green-600 dark:text-green-400 hover:text-green-800 dark:hover:text-green-300 font-medium mr-2"
                                    data-url="{{ route('sistema.routers.sync-pppoe', $r) }}" data-csrf="{{ csrf_token() }}"
                                    title="Sincronizar usuarios PPPoE al router">Sync PPPoE</button>
                                <a href="{{ route('sistema.routers.edit', $r) }}" class="text-purple-600 dark:text-purple-400 hover:text-purple-800 dark:hover:text-purple-300 font-medium mr-4">Editar</a>
                                <form action="{{ route('sistema.routers.destroy', $r) }}" method="POST" class="inline" onsubmit="return confirm('¿Eliminar este router?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-red-600 dark:text-red-400 hover:text-red-800 dark:hover:text-red-300 font-medium">Eliminar</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">No hay routers. <a href="{{ route('sistema.routers.create') }}" class="text-purple-600 dark:text-purple-400 hover:underline">Crear uno</a>.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($routers->hasPages())
            <div class="px-4 py-3 border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-700/50">
                {{ $routers->links() }}
            </div>
        @endif
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
                .then(function(r) { return r.json(); })
                .then(function(d) {
                    var msg = 'Añadidos: ' + (d.added || 0) + ', Actualizados: ' + (d.updated || 0) + ', Eliminados: ' + (d.removed || 0);
                    if (d.errors && d.errors.length) msg += '\nErrores: ' + d.errors.join(', ');
                    alert(msg);
                })
                .catch(function() { alert('Error de red.'); })
                .finally(function() { btn.disabled = false; });
        });
    });
})();
</script>
@endsection
