@csrf
@isset($pool)
    @method('PUT')
@endisset

@php
    $pool = $pool ?? null;
    $routerIdSelected = old('router_id', $pool?->router_id ?? ($routerId ?? null));
@endphp

<div class="space-y-6">
    <div>
        <label for="router_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Router *</label>
        <select name="router_id" id="router_id" required
            class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none transition-colors bg-white">
            <option value="">-- Seleccionar router --</option>
            @foreach($routers as $r)
                <option value="{{ $r->router_id }}" {{ $routerIdSelected == $r->router_id ? 'selected' : '' }}>
                    {{ $r->nombre }} ({{ $r->ip }}) @if($r->nodo) — {{ $r->nodo->descripcion }} @endif
                </option>
            @endforeach
        </select>
        @error('router_id')
            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="ip_range" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Rango IP *</label>
        <input type="text" name="ip_range" id="ip_range" value="{{ old('ip_range', $pool?->ip_range) }}"
            class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none transition-colors"
            maxlength="64" required placeholder="Ej: 192.168.1.0/24 o 192.168.1.1-192.168.1.100">
        @error('ip_range')
            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="descripcion" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Descripción</label>
        <input type="text" name="descripcion" id="descripcion" value="{{ old('descripcion', $pool?->descripcion) }}"
            class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none transition-colors"
            maxlength="255" placeholder="Ej: Pool clientes zona norte">
        @error('descripcion')
            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label class="flex items-center gap-2">
            <input type="checkbox" name="activo" value="1" {{ old('activo', $pool?->activo ?? true) ? 'checked' : '' }}
                class="rounded border-gray-300 text-purple-600 focus:ring-purple-500">
            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Activo</span>
        </label>
        @error('activo')
            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <div class="flex flex-wrap gap-3">
        <button type="submit"
            class="inline-flex items-center px-4 py-2 bg-purple-600 text-white rounded-lg font-medium hover:bg-purple-700 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:ring-offset-2">
            {{ $pool ? 'Actualizar pool' : 'Crear pool' }}
        </button>
        <a href="{{ route('sistema.router-ip-pools.index') }}"
            class="inline-flex items-center px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-gray-200 rounded-lg font-medium hover:bg-gray-300 dark:hover:bg-gray-600 focus:outline-none">
            Cancelar
        </a>
    </div>
</div>
