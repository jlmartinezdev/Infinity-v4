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

@php
    $macDefaults = config('olt.vsol.mac_cli_comandos', []);
    $macAddressVal = old('mac_cmds_address', isset($olt) ? $olt->macCliComandosTexto('address') : '');
    $macTablaVal = old('mac_cmds_tabla', isset($olt) ? $olt->macCliComandosTexto('tabla') : '');
    $macPonVal = old('mac_cmds_pon', isset($olt) ? $olt->macCliComandosTexto('pon') : '');
    $macIfaceVal = old('mac_cmds_interface', isset($olt) ? $olt->macCliComandosTexto('interface') : '');
@endphp
<div class="border-t border-gray-200 bg-gray-50 px-6 py-4 dark:border-gray-700 dark:bg-gray-900/40">
    <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-600 dark:text-gray-300">Comandos búsqueda MAC (por firmware)</h2>
    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
        Estrategia principal: <strong>tabla por PON</strong> — se consulta PON 1, 2, … hasta encontrar la MAC (<span class="font-mono">GPON0/X:Y</span>).
        Un comando por línea. Vacío = defaults. Placeholders:
        <code class="rounded bg-gray-200 px-1 dark:bg-gray-700">{mac_vsol}</code>
        <code class="rounded bg-gray-200 px-1 dark:bg-gray-700">{mac}</code>
        <code class="rounded bg-gray-200 px-1 dark:bg-gray-700">{mac_dot}</code>
        <code class="rounded bg-gray-200 px-1 dark:bg-gray-700">{pon}</code>
        <code class="rounded bg-gray-200 px-1 dark:bg-gray-700">{pon2}</code>
    </p>
</div>
<div class="grid gap-5 p-6 sm:grid-cols-2">
    <div class="sm:col-span-2">
        <label for="mac_cmds_pon" class="{{ $lb }}">1) Tabla por PON <span class="font-normal text-emerald-700 dark:text-emerald-400">(principal)</span></label>
        <textarea name="mac_cmds_pon" id="mac_cmds_pon" rows="3" class="{{ $fc }} font-mono text-xs" placeholder="{{ implode("\n", $macDefaults['pon'] ?? []) }}">{{ $macPonVal }}</textarea>
        <p class="mt-1 text-xs text-gray-500">Ej.: <span class="font-mono">show mac address-table gpon 0/{pon}</span> — se barre todos los puertos del OLT.</p>
    </div>
    <div>
        <label for="mac_cmds_address" class="{{ $lb }}">2) Por dirección MAC <span class="font-normal text-gray-500">(opcional)</span></label>
        <textarea name="mac_cmds_address" id="mac_cmds_address" rows="3" class="{{ $fc }} font-mono text-xs" placeholder="{{ implode("\n", $macDefaults['address'] ?? []) }}">{{ $macAddressVal }}</textarea>
    </div>
    <div>
        <label for="mac_cmds_tabla" class="{{ $lb }}">3) Tabla MAC global <span class="font-normal text-gray-500">(opcional)</span></label>
        <textarea name="mac_cmds_tabla" id="mac_cmds_tabla" rows="3" class="{{ $fc }} font-mono text-xs" placeholder="{{ implode("\n", $macDefaults['tabla'] ?? ['(vacío)']) }}">{{ $macTablaVal }}</textarea>
    </div>
    <div class="sm:col-span-2">
        <label for="mac_cmds_interface" class="{{ $lb }}">4) Dentro de <span class="font-mono">interface gpon 0/X</span> <span class="font-normal text-gray-500">(opcional)</span></label>
        <textarea name="mac_cmds_interface" id="mac_cmds_interface" rows="2" class="{{ $fc }} font-mono text-xs" placeholder="{{ implode("\n", $macDefaults['interface'] ?? ['(vacío)']) }}">{{ $macIfaceVal }}</textarea>
    </div>
</div>
