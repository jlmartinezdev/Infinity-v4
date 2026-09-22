@php
    $tipoActual = old($name ?? 'tipo', $value ?? \App\Support\BackupScheduleConfig::TIPO_ESENCIAL);
    $campo = $name ?? 'tipo';
@endphp
<fieldset class="space-y-2">
    <legend class="text-sm font-medium text-gray-700 dark:text-gray-300">Tipo de copia</legend>
    <label class="flex items-start gap-2 rounded-lg border border-gray-200 dark:border-gray-600 px-3 py-2 cursor-pointer">
        <input type="radio" name="{{ $campo }}" value="esencial" class="mt-1"
               {{ $tipoActual === 'esencial' ? 'checked' : '' }}>
        <span>
            <span class="block text-sm font-medium text-gray-900 dark:text-gray-100">Esencial</span>
            <span class="block text-xs text-gray-500 dark:text-gray-400">Clientes, servicios, cobros y el resto operativo. Sin notificaciones, auditoría, eventos de red ni mensajes de WhatsApp.</span>
        </span>
    </label>
    <label class="flex items-start gap-2 rounded-lg border border-gray-200 dark:border-gray-600 px-3 py-2 cursor-pointer">
        <input type="radio" name="{{ $campo }}" value="completo" class="mt-1"
               {{ $tipoActual === 'completo' ? 'checked' : '' }}>
        <span>
            <span class="block text-sm font-medium text-gray-900 dark:text-gray-100">Completo</span>
            <span class="block text-xs text-gray-500 dark:text-gray-400">Toda la base, incluyendo logs y chats.</span>
        </span>
    </label>
</fieldset>
