@csrf
@isset($servicio)
    @method('PUT')
@endisset

@php
    $servicio = $servicio ?? null;
@endphp

<div class="space-y-6">
    @if(!isset($servicio))
        <div>
            <label for="cliente_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Cliente *</label>
            <select name="cliente_id" id="cliente_id" required
                class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none transition-colors bg-white dark:bg-gray-700 dark:text-gray-100">
                <option value="">Seleccione un cliente</option>
                @foreach($clientes as $c)
                    <option value="{{ $c->cliente_id }}" {{ old('cliente_id', $clienteId ?? '') == $c->cliente_id ? 'selected' : '' }}>
                        {{ $c->cedula }} — {{ $c->nombre }} {{ $c->apellido }}
                    </option>
                @endforeach
            </select>
            @error('cliente_id')
                <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
            @enderror
        </div>
    @else
        <input type="hidden" name="cliente_id" value="{{ $servicio->cliente_id }}">
        <p class="text-sm text-gray-600 dark:text-gray-300">
            <span class="font-medium text-gray-700 dark:text-gray-300">Cliente:</span>
            {{ $servicio->cliente->cedula }} — {{ $servicio->cliente->nombre }} {{ $servicio->cliente->apellido }}
        </p>
    @endif

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div>
            <label for="pool_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Pool de IP *</label>
            <select name="pool_id" id="pool_id" required
                class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none transition-colors bg-white dark:bg-gray-700 dark:text-gray-100">
                <option value="">Seleccione un pool</option>
                @foreach($pools as $p)
                    <option value="{{ $p->pool_id }}" {{ old('pool_id', $servicio?->pool_id) == $p->pool_id ? 'selected' : '' }}>
                        #{{ $p->pool_id }} {{ $p->ip_range }} ({{ $p->router?->nombre ?? '—' }})
                    </option>
                @endforeach
            </select>
            @error('pool_id')
                <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="plan_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Plan *</label>
            <select name="plan_id" id="plan_id" required
                class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none transition-colors bg-white dark:bg-gray-700 dark:text-gray-100">
                <option value="">Seleccione un plan</option>
                @foreach($planes as $pl)
                    <option value="{{ $pl->plan_id }}" {{ old('plan_id', $servicio?->plan_id) == $pl->plan_id ? 'selected' : '' }}>
                        {{ $pl->nombre }} — {{ $pl->velocidad }}
                    </option>
                @endforeach
            </select>
            @error('plan_id')
                <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
            @enderror
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div>
            <label for="ip" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">IP</label>
            <input type="text" name="ip" id="ip" value="{{ old('ip', $servicio?->ip) }}"
                class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none transition-colors bg-white dark:bg-gray-700 dark:text-gray-100"
                maxlength="15" placeholder="192.168.1.1">
            <div id="servicio-form-ips-app"></div>
            @error('ip')
                <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="estado" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Estado</label>
            <select name="estado" id="estado"
                class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none transition-colors bg-white dark:bg-gray-700 dark:text-gray-100">
                <option value="A" {{ old('estado', $servicio?->estado) === 'A' ? 'selected' : '' }}>Activo</option>
                <option value="S" {{ old('estado', $servicio?->estado) === 'S' ? 'selected' : '' }}>Suspendido</option>
                <option value="C" {{ old('estado', $servicio?->estado) === 'C' ? 'selected' : '' }}>Cancelado</option>
            </select>
            @error('estado')
                <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
            @enderror
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div>
            <label for="usuario_pppoe" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Usuario PPPoE</label>
            <input type="text" name="usuario_pppoe" id="usuario_pppoe" value="{{ old('usuario_pppoe', $servicio?->usuario_pppoe) }}"
                class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none transition-colors bg-white dark:bg-gray-700 dark:text-gray-100"
                maxlength="100" placeholder="usuario@proveedor">
            @error('usuario_pppoe')
                <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="password_pppoe" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Contraseña PPPoE</label>
            <input type="text" name="password_pppoe" id="password_pppoe" value="{{ old('password_pppoe', $servicio?->password_pppoe) }}"
                class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none transition-colors bg-white dark:bg-gray-700 dark:text-gray-100"
                maxlength="20" placeholder="Contraseña del usuario PPPoE">
            @error('password_pppoe')
                <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
            @enderror
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div>
            <label for="fecha_instalacion" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Fecha instalación</label>
            <input type="date" name="fecha_instalacion" id="fecha_instalacion" value="{{ old('fecha_instalacion', $servicio?->fecha_instalacion?->format('Y-m-d')) }}"
                class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none transition-colors bg-white dark:bg-gray-700 dark:text-gray-100">
            @error('fecha_instalacion')
                <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
            @enderror
        </div>

        @isset($servicio)
        <div>
            <label for="fecha_cancelacion" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Fecha cancelación</label>
            <input type="date" name="fecha_cancelacion" id="fecha_cancelacion" value="{{ old('fecha_cancelacion', $servicio?->fecha_cancelacion?->format('Y-m-d')) }}"
                class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none transition-colors bg-white dark:bg-gray-700 dark:text-gray-100">
            @error('fecha_cancelacion')
                <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
            @enderror
        </div>
        @endisset
    </div>

    <div>
        <label for="mac_address" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">MAC address</label>
        <input type="text" name="mac_address" id="mac_address" value="{{ old('mac_address', $servicio?->mac_address) }}"
            class="w-full max-w-xs px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none transition-colors bg-white dark:bg-gray-700 dark:text-gray-100"
            maxlength="20" placeholder="00:00:00:00:00:00">
        @error('mac_address')
            <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
        @enderror
    </div>

    <div class="flex flex-wrap gap-3">
        <button type="submit"
            class="inline-flex items-center px-4 py-2 bg-purple-600 text-white rounded-lg font-medium hover:bg-purple-700 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800">
            {{ $servicio ? 'Actualizar servicio' : 'Crear servicio' }}
        </button>
        <a href="{{ route('servicios.index') }}"
            class="inline-flex items-center px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-gray-200 rounded-lg font-medium hover:bg-gray-300 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-gray-500 dark:focus:ring-gray-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800">
            Cancelar
        </a>
    </div>
</div>

<script>
window.__SERVICIO_FORM_IPS_CONFIG__ = { ipsDisponiblesUrl: "{{ route('servicios.ips-disponibles') }}" };
</script>
@push('scripts')
<script src="{{ asset(mix('js/servicio-form-ips.js')) }}" defer></script>
@endpush
