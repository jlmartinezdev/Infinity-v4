@csrf
@isset($router)
    @method('PUT')
@endisset

@php
    $router = $router ?? null;
    $modelosPorSerie = $modelosPorSerie ?? [];
    $modeloSeleccionado = old('modelo', $router?->modelo ?? 'otro');
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

    <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Modelo MikroTik</label>
        <p class="text-xs text-gray-500 dark:text-gray-400 mb-3">
            Seleccioná el equipo del <a href="{{ route('sistema.router-modelos.index') }}" class="text-purple-600 dark:text-purple-400 hover:underline">catálogo MikroTik</a>.
        </p>
        <input type="hidden" name="modelo" id="modelo" value="{{ $modeloSeleccionado }}">
        @if($modelosPorSerie !== [])
            <div class="space-y-4 max-h-[420px] overflow-y-auto rounded-xl border border-gray-200 dark:border-gray-600 p-3 bg-gray-50/50 dark:bg-gray-900/30">
                @foreach($modelosPorSerie as $serie => $modelos)
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-2">{{ $serie }}</p>
                        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-2">
                            @foreach($modelos as $slug => $m)
                                <button type="button"
                                    class="router-modelo-btn rounded-lg border-2 p-2 text-left transition-all hover:border-purple-400 focus:outline-none focus:ring-2 focus:ring-purple-500/30 {{ $modeloSeleccionado === $slug ? 'border-purple-600 bg-purple-50 dark:bg-purple-900/20 ring-2 ring-purple-500/20' : 'border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-800' }}"
                                    data-slug="{{ $slug }}">
                                    <img src="{{ $m['imagen_url'] ?? asset($m['imagen'] ?? 'images/routers/mikrotik-generic.svg') }}" alt="{{ $m['nombre'] }}" class="mx-auto h-16 w-full object-contain mb-2" loading="lazy">
                                    <span class="block text-xs font-semibold text-gray-900 dark:text-gray-100 leading-tight">{{ $m['nombre'] }}</span>
                                    <span class="block text-[10px] text-gray-500 dark:text-gray-400 mt-0.5 line-clamp-2">{{ $m['descripcion'] }}</span>
                                </button>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
        @error('modelo')
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

    <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-600 dark:bg-gray-900/30">
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Token webhook PPPoE</label>
        <p class="mb-2 text-xs text-gray-500 dark:text-gray-400">
            Lo usa el MikroTik (scripts on-up/on-down) para avisar conexiones al sistema.
            Endpoint: <span class="font-mono">POST /api/v1/webhooks/mikrotik/pppoe</span>
        </p>
        @if($router?->webhook_token)
            <input type="text" readonly value="{{ $router->webhook_token }}"
                class="mb-2 w-full rounded-lg border border-gray-300 bg-white px-3 py-2 font-mono text-xs text-gray-800 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100"
                onclick="this.select()">
        @else
            <p class="mb-2 text-xs text-amber-600 dark:text-amber-400">Todavía no hay token. Marcá generar al guardar.</p>
        @endif
        <label class="inline-flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
            <input type="checkbox" name="generar_webhook_token" value="1" class="rounded border-gray-300 text-purple-600 focus:ring-purple-500"
                {{ old('generar_webhook_token', ! $router) ? 'checked' : '' }}>
            Generar nuevo token al guardar
        </label>
        @error('webhook_token')
            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
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

<script>
(function() {
    var input = document.getElementById('modelo');
    if (input) {
        document.querySelectorAll('.router-modelo-btn').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var slug = this.getAttribute('data-slug');
                input.value = slug;
                document.querySelectorAll('.router-modelo-btn').forEach(function(b) {
                    b.classList.remove('border-purple-600', 'bg-purple-50', 'dark:bg-purple-900/20', 'ring-2', 'ring-purple-500/20');
                    b.classList.add('border-gray-200', 'dark:border-gray-600', 'bg-white', 'dark:bg-gray-800');
                });
                this.classList.remove('border-gray-200', 'dark:border-gray-600', 'bg-white', 'dark:bg-gray-800');
                this.classList.add('border-purple-600', 'bg-purple-50', 'dark:bg-purple-900/20', 'ring-2', 'ring-purple-500/20');
            });
        });
    }

})();
</script>
