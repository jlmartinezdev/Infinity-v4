@php
    $perfil = $perfil ?? null;
@endphp

<div class="space-y-4">
    <div>
        <label for="nombre" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Nombre *</label>
        <input type="text" name="nombre" id="nombre" value="{{ old('nombre', $perfil?->nombre) }}"
            class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20"
            maxlength="150" required placeholder="Ej: default, plan-10mb">
        @error('nombre')
            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="rate_limit" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Rate Limit</label>
        <input type="text" name="rate_limit" id="rate_limit" value="{{ old('rate_limit', $perfil?->rate_limit) }}"
            class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20"
            maxlength="50" placeholder="Ej: 10M/10M">
        @error('rate_limit')
            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="shared_users" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Shared Users</label>
        <input type="text" name="shared_users" id="shared_users" value="{{ old('shared_users', $perfil?->shared_users) }}"
            class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20"
            maxlength="20" placeholder="Ej: 1">
        @error('shared_users')
            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
            <label for="idle_timeout" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Idle Timeout</label>
            <input type="text" name="idle_timeout" id="idle_timeout" value="{{ old('idle_timeout', $perfil?->idle_timeout) }}"
                class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20"
                maxlength="20" placeholder="Ej: 5m">
            @error('idle_timeout')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>
        <div>
            <label for="session_timeout" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Session Timeout</label>
            <input type="text" name="session_timeout" id="session_timeout" value="{{ old('session_timeout', $perfil?->session_timeout) }}"
                class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20"
                maxlength="20" placeholder="Ej: 1d">
            @error('session_timeout')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>
    </div>
</div>
