@csrf
@isset($router)
    @method('PUT')
@endisset

@php
    $router = $router ?? null;
@endphp

<div class="space-y-6">
    <div>
        <label for="nodo_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Nodo *</label>
        <select name="nodo_id" id="nodo_id" required
            class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none transition-colors bg-white dark:bg-gray-700">
            <option value="">-- Seleccionar nodo --</option>
            @foreach($nodos as $nodo)
                <option value="{{ $nodo->nodo_id }}" {{ old('nodo_id', $router?->nodo_id) == $nodo->nodo_id ? 'selected' : '' }}>
                    {{ $nodo->descripcion ?? "Nodo #{$nodo->nodo_id}" }}
                </option>
            @endforeach
        </select>
        @error('nodo_id')
            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div>
            <label for="nombre" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Nombre *</label>
            <input type="text" name="nombre" id="nombre" value="{{ old('nombre', $router?->nombre) }}"
                class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none transition-colors"
                maxlength="100" required placeholder="Ej: Router Principal">
            @error('nombre')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>
        <div>
            <label for="ip" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">IP *</label>
            <input type="text" name="ip" id="ip" value="{{ old('ip', $router?->ip) }}"
                class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none transition-colors"
                maxlength="64" required placeholder="192.168.88.1">
            @error('ip')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div>
            <label for="ip_loopback" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">IP Loopback</label>
            <input type="text" name="ip_loopback" id="ip_loopback" value="{{ old('ip_loopback', $router?->ip_loopback) }}"
                class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none transition-colors"
                maxlength="64" placeholder="127.0.0.1">
            @error('ip_loopback')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>
        <div>
            <label for="hotspot_servidor" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Hotspot servidor</label>
            <input type="text" name="hotspot_servidor" id="hotspot_servidor" value="{{ old('hotspot_servidor', $router?->hotspot_servidor) }}"
                class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none transition-colors"
                maxlength="64" placeholder="all (o nombre del servidor hotspot)">
            @error('hotspot_servidor')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div>
            <label for="api_port" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Puerto API</label>
            <input type="number" name="api_port" id="api_port" value="{{ old('api_port', $router?->api_port ?? 8728) }}"
                min="1" max="65535"
                class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none transition-colors"
                placeholder="8728">
            @error('api_port')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>
        <div>
            <label for="estado" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Estado</label>
            <input type="text" name="estado" id="estado" value="{{ old('estado', $router?->estado) }}"
                class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none transition-colors"
                maxlength="32" placeholder="desconocido">
            @error('estado')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div>
            <label for="usuario" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Usuario API *</label>
            <input type="text" name="usuario" id="usuario" value="{{ old('usuario', $router?->usuario) }}"
                class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none transition-colors"
                maxlength="64" required>
            @error('usuario')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>
        <div>
            <label for="password" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Contraseña API</label>
            <input type="password" name="password" id="password" value="{{ old('password') }}"
                class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none transition-colors"
                maxlength="128" placeholder="{{ $router ? 'Dejar en blanco para no cambiar' : '' }}">
            @error('password')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>
    </div>

    <div class="flex flex-wrap gap-3">
        <button type="submit"
            class="inline-flex items-center px-4 py-2 bg-purple-600 text-white rounded-lg font-medium hover:bg-purple-700 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:ring-offset-2">
            {{ $router ? 'Actualizar router' : 'Crear router' }}
        </button>
        <a href="{{ route('sistema.routers.index') }}"
            class="inline-flex items-center px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-gray-200 rounded-lg font-medium hover:bg-gray-300 dark:hover:bg-gray-600 focus:outline-none">
            Cancelar
        </a>
    </div>
</div>
