@csrf
@isset($plan)
    @method('PUT')
@endisset

@php
    $plan = $plan ?? null;
    $perfilesPppoe = $perfilesPppoe ?? collect();
@endphp

<div class="space-y-6">
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div>
            <label for="nombre" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Nombre *</label>
            <input type="text" name="nombre" id="nombre" value="{{ old('nombre', $plan?->nombre) }}"
                class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none transition-colors"
                maxlength="100" required autofocus placeholder="Ej: Plan Básico 10MB">
            @error('nombre')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="velocidad" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Velocidad *</label>
            <input type="text" name="velocidad" id="velocidad" value="{{ old('velocidad', $plan?->velocidad) }}"
                class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none transition-colors"
                maxlength="50" required placeholder="Ej: 10/10 Mbps">
            @error('velocidad')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div>
            <label for="tecnologia_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Tecnología *</label>
            <select name="tecnologia_id" id="tecnologia_id"
                class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none transition-colors"
                required>
                <option value="">Seleccione una tecnología</option>
                @foreach($tecnologias as $tecnologia)
                    <option value="{{ $tecnologia->tecnologia_id }}" 
                        {{ old('tecnologia_id', $plan?->tecnologia_id) == $tecnologia->tecnologia_id ? 'selected' : '' }}>
                        {{ $tecnologia->descripcion ?? "Tecnología #{$tecnologia->tecnologia_id}" }}
                    </option>
                @endforeach
            </select>
            @error('tecnologia_id')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="perfil_pppoe_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Perfil PPPoE</label>
            <select name="perfil_pppoe_id" id="perfil_pppoe_id"
                class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none transition-colors">
                <option value="">Sin perfil</option>
                @foreach($perfilesPppoe as $perfil)
                    <option value="{{ $perfil->perfil_pppoe_id }}" 
                        {{ old('perfil_pppoe_id', $plan?->perfil_pppoe_id) == $perfil->perfil_pppoe_id ? 'selected' : '' }}>
                        {{ $perfil->nombre }}
                    </option>
                @endforeach
            </select>
            @error('perfil_pppoe_id')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div>
            <label for="precio" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Precio *</label>
            <div class="relative">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                    <span class="text-gray-500 dark:text-gray-400 text-sm">$</span>
                </div>
           

                <input type="number" name="precio" id="precio" value="{{ old('precio', $plan?->precio) }}"
                    step="0.01" min="0"
                    class="w-full pl-10 pr-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none transition-colors"
                    required placeholder="0.00">
            </div>
            @error('precio')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="estado" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Estado *</label>
            <select name="estado" id="estado"
                class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none transition-colors"
                required>
                <option value="activo" {{ old('estado', $plan?->estado ?? 'activo') === 'activo' ? 'selected' : '' }}>Activo</option>
                <option value="inactivo" {{ old('estado', $plan?->estado) === 'inactivo' ? 'selected' : '' }}>Inactivo</option>
            </select>
            @error('estado')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="prioridad" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Prioridad</label>
            <select name="prioridad" id="prioridad"
                class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none transition-colors">
                <option value="1" {{ old('prioridad', $plan?->prioridad ?? 2) == 1 ? 'selected' : '' }}>Alta</option>
                <option value="2" {{ old('prioridad', $plan?->prioridad ?? 2) == 2 ? 'selected' : '' }}>Media</option>
                <option value="3" {{ old('prioridad', $plan?->prioridad ?? 2) == 3 ? 'selected' : '' }}>Baja</option>
            </select>
            <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">Se usará como prioridad de instalación al asignar este plan a un pedido.</p>
            @error('prioridad')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>
    </div>

    <div>
        <label for="descripcion" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Descripción</label>
        <textarea name="descripcion" id="descripcion" rows="4"
            class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none transition-colors"
            placeholder="Descripción adicional del plan...">{{ old('descripcion', $plan?->descripcion) }}</textarea>
        @error('descripcion')
            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <div class="flex flex-wrap gap-3">
        <button type="submit"
            class="inline-flex items-center px-4 py-2 bg-purple-600 text-white rounded-lg font-medium hover:bg-purple-700 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:ring-offset-2">
            {{ $plan ? 'Actualizar plan' : 'Crear plan' }}
        </button>
        <a href="{{ route('planes.index') }}"
            class="inline-flex items-center px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-gray-200 rounded-lg font-medium hover:bg-gray-300 dark:hover:bg-gray-600 focus:outline-none">
            Cancelar
        </a>
    </div>
</div>
