<div class="border-b border-gray-200 bg-gray-50 px-6 py-4 dark:border-gray-700 dark:bg-gray-900/40">
    <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-600 dark:text-gray-300">Acceso de gestión (VSOL Telnet)</h2>
    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Credenciales para importar ONUs desde la CLI del OLT (como AdminOLT). Habilitá Telnet en el equipo.</p>
</div>
<div class="grid gap-5 p-6 sm:grid-cols-2">
    <div>
        <label for="gestion_usuario" class="{{ $lb }}">Usuario</label>
        <input type="text" name="gestion_usuario" id="gestion_usuario" value="{{ old('gestion_usuario', $olt->gestion_usuario ?? config('olt.vsol.default_user', 'admin')) }}" maxlength="64" class="{{ $fc }}" autocomplete="off">
        @error('gestion_usuario')<p class="mt-1.5 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
    </div>
    <div>
        <label for="gestion_password" class="{{ $lb }}">Contraseña</label>
        <input type="password" name="gestion_password" id="gestion_password" value="" maxlength="255" class="{{ $fc }}" autocomplete="new-password" placeholder="{{ isset($olt) && $olt->gestion_password ? '•••••••• (dejar vacío para no cambiar)' : 'Contraseña CLI' }}">
        @error('gestion_password')<p class="mt-1.5 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
    </div>
    <div>
        <label for="gestion_protocolo" class="{{ $lb }}">Protocolo</label>
        <select name="gestion_protocolo" id="gestion_protocolo" class="{{ $fc }}">
            <option value="telnet" {{ old('gestion_protocolo', $olt->gestion_protocolo ?? 'telnet') === 'telnet' ? 'selected' : '' }}>Telnet</option>
            <option value="ssh" {{ old('gestion_protocolo', $olt->gestion_protocolo ?? '') === 'ssh' ? 'selected' : '' }} disabled>SSH (próximamente)</option>
        </select>
    </div>
    <div>
        <label for="gestion_puerto" class="{{ $lb }}">Puerto</label>
        <input type="number" name="gestion_puerto" id="gestion_puerto" value="{{ old('gestion_puerto', $olt->gestion_puerto ?? '') }}" min="1" max="65535" class="{{ $fc }}" placeholder="23 (Telnet por defecto)">
        @error('gestion_puerto')<p class="mt-1.5 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
    </div>
    <div class="sm:col-span-2">
        <label for="gestion_enable_password" class="{{ $lb }}">Contraseña enable <span class="font-normal text-gray-500">(opcional, si difiere de la contraseña CLI)</span></label>
        <input type="password" name="gestion_enable_password" id="gestion_enable_password" value="" maxlength="255" class="{{ $fc }}" autocomplete="new-password" placeholder="{{ isset($olt) && $olt->gestion_enable_password ? '•••••••• (dejar vacío para no cambiar)' : 'Igual que contraseña si se deja vacío' }}">
    </div>
</div>
