@extends('layouts.app')

@section('title', 'Migrar servicio')

@section('content')
<div class="max-w-2xl mx-auto">
    <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100 mb-6">Migrar servicio #{{ $servicio->servicio_id }}</h1>

    <div class="bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-xl p-4 mb-6">
        <p class="text-sm font-medium text-amber-800 dark:text-amber-200">Origen (nodo actual)</p>
        <p class="text-sm text-amber-700 dark:text-amber-300 mt-1">
            {{ $servicio->cliente->nombre ?? '' }} {{ $servicio->cliente->apellido ?? '' }} —
            Nodo: {{ $servicio->pool?->router?->nodo?->descripcion ?? '—' }} —
            Pool: {{ $servicio->pool?->ip_range ?? '—' }} ({{ $servicio->pool?->router?->nombre ?? '—' }}) —
            IP: {{ $servicio->ip ?? '—' }} —
            Plan: {{ $servicio->plan?->nombre ?? '—' }}
        </p>
        @if($servicio->usuario_pppoe)
            <p class="text-xs text-amber-700/90 dark:text-amber-300/90 mt-2">
                Al confirmar, se eliminará el usuario PPPoE <strong class="font-mono">{{ $servicio->usuario_pppoe }}</strong> del MikroTik del nodo actual y se creará o actualizará en el router del nodo destino.
            </p>
        @endif
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 p-6">
        <form action="{{ route('servicios.migrar-store', $servicio->servicio_id) }}" method="POST" id="form-migrar">
            @csrf
            <div class="space-y-6">
                <div>
                    <label for="pool_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Pool destino (otro nodo) *</label>
                    <select name="pool_id" id="pool_id" required
                        class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 focus:outline-none bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                        <option value="">Seleccione pool destino</option>
                        @foreach($poolsDestino as $p)
                            <option value="{{ $p->pool_id }}" data-nodo="{{ $p->router?->nodo?->descripcion ?? '' }}">
                                {{ $p->router?->nodo?->descripcion ?? 'Nodo' }} — {{ $p->ip_range }} ({{ $p->router?->nombre ?? '—' }})
                            </option>
                        @endforeach
                    </select>
                    @error('pool_id')
                        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="ip" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">IP asignada *</label>
                    <select name="ip" id="ip" required
                        class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 focus:outline-none bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                        <option value="">Primero seleccione un pool</option>
                    </select>
                    @error('ip')
                        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="plan_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Plan (opcional: cambiar plan)</label>
                    <select name="plan_id" id="plan_id"
                        class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 focus:outline-none bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                        @foreach($planes as $plan)
                            <option value="{{ $plan->plan_id }}" {{ old('plan_id', $servicio->plan_id) == $plan->plan_id ? 'selected' : '' }}>
                                {{ $plan->nombre }}
                            </option>
                        @endforeach
                    </select>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Si cambia el plan, se sincronizará con MikroTik al migrar.</p>
                    @error('plan_id')
                        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div class="mt-6 flex flex-wrap gap-3">
                <button type="submit"
                    class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg font-medium hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800">
                    Migrar servicio
                </button>
                <a href="{{ route('servicios.index') }}"
                    class="inline-flex items-center px-4 py-2 bg-gray-200 dark:bg-gray-600 text-gray-800 dark:text-gray-200 rounded-lg font-medium hover:bg-gray-300 dark:hover:bg-gray-500 focus:outline-none">
                    Cancelar
                </a>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
(function() {
    var poolSelect = document.getElementById('pool_id');
    var ipSelect = document.getElementById('ip');
    var ipsUrl = "{{ $ipsDisponiblesUrl }}";
    var oldIp = "{{ old('ip') }}";

    function cargarIps() {
        var poolId = poolSelect.value;
        ipSelect.innerHTML = '<option value="">Cargando...</option>';
        ipSelect.disabled = true;

        if (!poolId) {
            ipSelect.innerHTML = '<option value="">Primero seleccione un pool</option>';
            return;
        }

        fetch(ipsUrl + '?pool_id=' + encodeURIComponent(poolId))
            .then(function(r) { return r.json(); })
            .then(function(data) {
                var ips = data.ips || [];
                ipSelect.innerHTML = '<option value="">Seleccione IP</option>';
                ips.forEach(function(ip) {
                    var opt = document.createElement('option');
                    opt.value = ip;
                    opt.textContent = ip;
                    if (ip === oldIp) opt.selected = true;
                    ipSelect.appendChild(opt);
                });
                ipSelect.disabled = false;
            })
            .catch(function() {
                ipSelect.innerHTML = '<option value="">Error al cargar IPs</option>';
                ipSelect.disabled = false;
            });
    }

    poolSelect.addEventListener('change', cargarIps);

    if (poolSelect.value) {
        cargarIps();
    }
})();
</script>
@endpush
@endsection
