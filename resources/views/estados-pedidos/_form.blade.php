@csrf
@isset($estadoPedido)
    @method('PUT')
@endisset

@php
    $estadoPedido = $estadoPedido ?? null;
    $roles = $roles ?? \App\Models\Rol::orderBy('descripcion')->get();
@endphp

<div class="space-y-6">
    <div>
        <label for="descripcion" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Descripción *</label>
        <input type="text" name="descripcion" id="descripcion" value="{{ old('descripcion', $estadoPedido?->descripcion) }}"
            class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none transition-colors bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100"
            maxlength="120" required autofocus placeholder="Ej: Pendiente, En proceso, Completado, Cancelado">
        @error('descripcion')
            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="rol_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Rol asociado</label>
        <select name="rol_id" id="rol_id"
            class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none transition-colors bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
            <option value="">Seleccione un rol (opcional)</option>
            @foreach($roles as $rol)
                <option value="{{ $rol->rol_id }}" {{ old('rol_id', $estadoPedido?->rol_id) == $rol->rol_id ? 'selected' : '' }}>
                    {{ $rol->descripcion }}
                </option>
            @endforeach
        </select>
        @error('rol_id')
            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="parametro" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Parámetro</label>
        <textarea name="parametro" id="parametro" rows="4"
            class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none transition-colors resize-none bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100"
            placeholder="Texto o parámetros adicionales (opcional)">{{ old('parametro', $estadoPedido?->parametro) }}</textarea>
        @error('parametro')
            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <div class="flex flex-wrap gap-3">
        <button type="submit"
            class="inline-flex items-center px-4 py-2 bg-purple-600 text-white rounded-lg font-medium hover:bg-purple-700 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:ring-offset-2">
            {{ $estadoPedido ? 'Actualizar estado' : 'Crear estado' }}
        </button>
        <a href="{{ route('estados-pedidos.index') }}"
            class="inline-flex items-center px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-gray-200 rounded-lg font-medium hover:bg-gray-300 dark:hover:bg-gray-600 focus:outline-none">
            Cancelar
        </a>
    </div>
</div>
