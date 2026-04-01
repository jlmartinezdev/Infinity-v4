@extends('layouts.app')

@section('title', 'Agregar IP al pool')

@section('content')
<div class="max-w-2xl mx-auto" id="pool-ip-create" data-ip-range="{{ e($pool->ip_range ?? '') }}">
    <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100 mb-6">Agregar IP al pool</h1>
    <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">Pool: {{ $pool->ip_range }} ({{ $pool->router?->nombre ?? '—' }})</p>

    {{-- Agregar IPs por rango (inputs completados desde ip_range del pool) --}}
    <div class="mb-6 bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 p-6">
        <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-3">Agregar IPs por rango</h2>
        <p class="text-xs text-gray-500 dark:text-gray-400 mb-3">El rango del pool usa formato CIDR (ej. 0.0.0.0/0). Los campos IP inicio e IP fin se completan automáticamente. Puedes editarlos antes de enviar.</p>
        <form action="{{ route('sistema.pool-ip-asignadas.store-rango') }}" method="POST" class="flex flex-wrap items-end gap-4">
            @csrf
            <input type="hidden" name="pool_id" value="{{ $pool->pool_id }}">
            <div>
                <label for="ip_inicio" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">IP inicio</label>
                <input type="text" name="ip_inicio" id="ip_inicio" value="{{ old('ip_inicio') }}"
                    placeholder="192.168.1.1"
                    class="w-44 px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                @error('ip_inicio')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label for="ip_fin" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">IP fin</label>
                <input type="text" name="ip_fin" id="ip_fin" value="{{ old('ip_fin') }}"
                    placeholder="192.168.1.100"
                    class="w-44 px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                @error('ip_fin')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>
            <button type="submit"
                class="px-4 py-2.5 bg-blue-600 text-white rounded-lg font-medium hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                Agregar rango
            </button>
        </form>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 p-6">
        <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-3">Agregar una sola IP</h2>
        <form action="{{ route('sistema.pool-ip-asignadas.store') }}" method="POST">
            @csrf
            <input type="hidden" name="pool_id" value="{{ $pool->pool_id }}">

            <div class="space-y-6">
                <div>
                    <label for="ip" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">IP *</label>
                    <input type="text" name="ip" id="ip" value="{{ old('ip') }}"
                        class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none transition-colors bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100"
                        maxlength="15" required placeholder="192.168.1.1">
                    @error('ip')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="estado" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Estado *</label>
                    <select name="estado" id="estado" required
                        class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none transition-colors bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                        <option value="disponible" {{ old('estado', 'disponible') === 'disponible' ? 'selected' : '' }}>Disponible</option>
                        <option value="asignada" {{ old('estado') === 'asignada' ? 'selected' : '' }}>Asignada</option>
                        <option value="reservada" {{ old('estado') === 'reservada' ? 'selected' : '' }}>Reservada</option>
                    </select>
                    @error('estado')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div class="flex flex-wrap gap-3">
                    <button type="submit"
                        class="inline-flex items-center px-4 py-2 bg-purple-600 text-white rounded-lg font-medium hover:bg-purple-700 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:ring-offset-2">
                        Agregar IP
                    </button>
                    <a href="{{ route('sistema.pool-ip-asignadas.index', ['pool_id' => $pool->pool_id]) }}"
                        class="inline-flex items-center px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-gray-200 rounded-lg font-medium hover:bg-gray-300 dark:hover:bg-gray-600 focus:outline-none">
                        Cancelar
                    </a>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
(function() {
    var el = document.getElementById('pool-ip-create');
    if (!el) return;
    var ipRange = (el.getAttribute('data-ip-range') || '').trim();
    if (!ipRange) return;

    var ipInicio = document.getElementById('ip_inicio');
    var ipFin = document.getElementById('ip_fin');
    if (!ipInicio || !ipFin) return;

    if (ipInicio.value || ipFin.value) return;

    // Formato CIDR: 0.0.0.0/0 o 192.168.1.0/24
    if (ipRange.indexOf('/') !== -1) {
        var cidrParts = ipRange.split('/');
        var baseIp = (cidrParts[0] || '').trim();
        var prefix = parseInt(cidrParts[1], 10);
        if (isNaN(prefix) || prefix < 0 || prefix > 32) return;

        var octets = baseIp.split('.');
        if (octets.length !== 4) return;
        var ipNum = 0;
        for (var i = 0; i < 4; i++) {
            var o = parseInt(octets[i], 10);
            if (isNaN(o) || o < 0 || o > 255) return;
            ipNum = (ipNum << 8) | o;
        }
        ipNum = ipNum >>> 0;

        var hostBits = 32 - prefix;
        var networkMask = (0xFFFFFFFF << hostBits) >>> 0;
        var startNum = (ipNum & networkMask) >>> 0;
        var endNum = startNum | ((hostBits === 32) ? 0xFFFFFFFF : ((1 << hostBits) - 1));

        // No usar .0 (red) ni .255 (broadcast)
        if ((startNum & 255) === 0) startNum = (startNum + 1) >>> 0;
        if ((endNum & 255) === 255) endNum = (endNum - 1) >>> 0;
        if (startNum > endNum) endNum = startNum;

        function numToIp(n) {
            n = n >>> 0;
            return ((n >>> 24) & 255) + '.' + ((n >>> 16) & 255) + '.' + ((n >>> 8) & 255) + '.' + (n & 255);
        }

        ipInicio.value = numToIp(startNum);
        ipFin.value = numToIp(endNum);
        return;
    }

    // Fallback: formato "inicio - fin" o "inicio-fin"
    var parts = null;
    if (ipRange.indexOf(' - ') !== -1) {
        parts = ipRange.split(' - ').map(function(s) { return s.trim(); });
    } else if (ipRange.indexOf(' a ') !== -1) {
        parts = ipRange.split(' a ').map(function(s) { return s.trim(); });
    } else if (ipRange.indexOf('-') !== -1) {
        parts = ipRange.split('-').map(function(s) { return s.trim(); });
    } else if (ipRange.indexOf('–') !== -1) {
        parts = ipRange.split('–').map(function(s) { return s.trim(); });
    }
    var ipRegex = /^\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}$/;
    function looksLikeIp(s) {
        if (!s || typeof s !== 'string') return false;
        return ipRegex.test(s.trim());
    }
    if (parts && parts.length >= 2 && looksLikeIp(parts[0]) && looksLikeIp(parts[1])) {
        ipInicio.value = parts[0].trim();
        ipFin.value = parts[1].trim();
    }
})();
</script>
@endsection
