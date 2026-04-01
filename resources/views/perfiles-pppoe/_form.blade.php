@csrf
@isset($perfilPppoe)
    @method('PUT')
@endisset

@php
    $perfilPppoe = $perfilPppoe ?? null;
@endphp

<div class="space-y-6">
    <div>
        <label for="nombre" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Nombre</label>
        <input type="text" name="nombre" id="nombre" value="{{ old('nombre', $perfilPppoe?->nombre) }}"
            class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none transition-colors"
            maxlength="150" autofocus placeholder="Ej: Perfil Básico 10MB">
        @error('nombre')
            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div>
            <label for="local_address" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Dirección Local</label>
            <input type="text" name="local_address" id="local_address" value="{{ old('local_address', $perfilPppoe?->local_address) }}"
                class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none transition-colors"
                maxlength="20" placeholder="Ej: 192.168.1.1">
            @error('local_address')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="remote_address" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Dirección Remota</label>
            <input type="text" name="remote_address" id="remote_address" value="{{ old('remote_address', $perfilPppoe?->remote_address) }}"
                class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none transition-colors"
                maxlength="20" placeholder="Ej: 10.0.0.1">
            @error('remote_address')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>
    </div>

    <div>
        <label for="rate_limit_tx_rx" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Rate Limit TX/RX</label>
        <input type="text" name="rate_limit_tx_rx" id="rate_limit_tx_rx" value="{{ old('rate_limit_tx_rx', $perfilPppoe?->rate_limit_tx_rx) }}"
            class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none transition-colors"
            maxlength="100" placeholder="Ej: 10M/10M">
        @error('rate_limit_tx_rx')
            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <div class="flex flex-wrap gap-3">
        <button type="submit"
            class="inline-flex items-center px-4 py-2 bg-purple-600 text-white rounded-lg font-medium hover:bg-purple-700 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:ring-offset-2">
            {{ $perfilPppoe ? 'Actualizar perfil' : 'Crear perfil' }}
        </button>
        <a href="{{ route('perfiles-pppoe.index') }}"
            class="inline-flex items-center px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-gray-200 rounded-lg font-medium hover:bg-gray-300 dark:hover:bg-gray-600 focus:outline-none">
            Cancelar
        </a>
    </div>
</div>
