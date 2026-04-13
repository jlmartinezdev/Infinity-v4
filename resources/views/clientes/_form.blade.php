@csrf
@isset($cliente)
    @method('PUT')
@endisset

@php
    $cliente = $cliente ?? null;
    $urlUbicacionForm = old('url_ubicacion', $cliente?->url_ubicacion ?? '');
    $coordsFormMapa = \App\Helpers\MapsUrlHelper::extractLatLonFromMapsUrl($urlUbicacionForm !== '' ? $urlUbicacionForm : null);
@endphp

<div class="space-y-6">
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
        <div>
            <label for="cedula" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Cédula *</label>
            @if (!$cliente)
            <div class="flex gap-2">
                <input type="text" name="cedula" id="cedula" value="{{ old('cedula') }}"
                    class="flex-1 px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none transition-colors bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-500"
                    maxlength="20" required autofocus placeholder="Ej: 1234567">
                <button type="button" id="btn-buscar-padron" class="px-4 py-2.5 bg-blue-600 text-white rounded-lg font-medium hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 whitespace-nowrap disabled:opacity-50 disabled:cursor-not-allowed">
                    <span id="btn-buscar-padron-text">Buscar padrón</span>
                    <span id="btn-buscar-padron-loading" class="hidden">Consultando...</span>
                </button>
            </div>
            <p id="cedula-msg" class="mt-1 text-sm hidden"></p>
            @else
            <input type="text" name="cedula" id="cedula" value="{{ old('cedula', $cliente?->cedula) }}"
                class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none transition-colors bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100"
                maxlength="20" required autofocus>
            @endif
            @error('cedula')
                <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
            @enderror
        </div>
        <div>
            <label for="nombre" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Nombre *</label>
            <input type="text" name="nombre" id="nombre" value="{{ old('nombre', $cliente?->nombre) }}"
                class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none transition-colors bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100"
                maxlength="100" required>
            @error('nombre')
                <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
            @enderror
        </div>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
        <div>
            <label for="apellido" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Apellido</label>
            <input type="text" name="apellido" id="apellido" value="{{ old('apellido', $cliente?->apellido) }}"
                class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none transition-colors bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100"
                maxlength="100">
            @error('apellido')
                <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
            @enderror
        </div>
        <div>
            <label for="email" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Email</label>
            <input type="email" name="email" id="email" value="{{ old('email', $cliente?->email) }}"
                class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none transition-colors bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100"
                maxlength="100">
            @error('email')
                <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
            @enderror
        </div>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
        <div>
            <label for="telefono" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Teléfono</label>
            <input type="text" name="telefono" id="telefono" value="{{ old('telefono', $cliente?->telefono) }}"
                class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none transition-colors bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100"
                maxlength="20">
            @error('telefono')
                <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
            @enderror
        </div>
        <div>
            <label for="estado" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Estado *</label>
            <select name="estado" id="estado" required
                class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none transition-colors bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                <option value="activo" {{ old('estado', $cliente?->estado ?? 'activo') === 'activo' ? 'selected' : '' }}>Activo</option>
                <option value="inactivo" {{ old('estado', $cliente?->estado) === 'inactivo' ? 'selected' : '' }}>Inactivo</option>
                <option value="suspendido" {{ old('estado', $cliente?->estado) === 'suspendido' ? 'selected' : '' }}>Suspendido</option>
            </select>
            @error('estado')
                <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
            @enderror
        </div>
    </div>

    <div>
        <label for="direccion" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Dirección</label>
        <textarea name="direccion" id="direccion" rows="3"
            class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none transition-colors resize-y bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">{{ old('direccion', $cliente?->direccion) }}</textarea>
        @error('direccion')
            <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="url_ubicacion" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">URL ubicación</label>
        <input type="text" name="url_ubicacion" id="url_ubicacion" value="{{ $urlUbicacionForm }}"
            class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none transition-colors bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-500"
            maxlength="500" placeholder="Ej: https://maps.google.com/... o coordenadas (lat, lon)">
        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Enlace de Google Maps o coordenadas. Se actualiza automáticamente al marcar un pedido como instalado.</p>
        @error('url_ubicacion')
            <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
        @enderror

        <div class="mt-4">
            <p class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Ubicación en mapa</p>
            <p class="text-xs text-gray-500 dark:text-gray-400 mb-2">Haga clic en el mapa para colocar o mover el punto. Puede arrastrar el marcador para afinar. El campo «URL ubicación» se rellena con el enlace estándar de Google Maps.</p>
            <div id="cliente-form-mapa-app"></div>
        </div>
    </div>

    <div class="flex flex-wrap gap-3">
        <button type="submit"
            class="inline-flex items-center px-4 py-2 bg-purple-600 text-white rounded-lg font-medium hover:bg-purple-700 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800">
            {{ $cliente ? 'Actualizar cliente' : 'Crear cliente' }}
        </button>
        <a href="{{ route('clientes.index') }}"
            class="inline-flex items-center px-4 py-2 bg-gray-200 dark:bg-gray-600 text-gray-800 dark:text-gray-200 rounded-lg font-medium hover:bg-gray-300 dark:hover:bg-gray-500 focus:outline-none">
            Cancelar
        </a>
    </div>
</div>

@push('scripts')
@php
    $clienteFormMapaConfig = [
        'apiKey' => config('services.google.maps_key'),
        'initialLat' => $coordsFormMapa['lat'],
        'initialLon' => $coordsFormMapa['lon'],
    ];
@endphp
<script>
    window.__CLIENTE_FORM_MAPA_CONFIG__ = @json($clienteFormMapaConfig);
</script>
<script src="{{ asset(mix('js/cliente-form-mapa.js')) }}" defer></script>
@endpush
