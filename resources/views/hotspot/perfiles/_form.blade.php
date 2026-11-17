@php
    $campos = $campos ?? \App\Support\HotspotPerfilFormulario::campos($perfil ?? null);
    $invalidos = $campos['invalidos'] ?? [];
    $valor = fn (string $campo) => old($campo, $campos[$campo] ?? '');
    $campo = 'w-full px-3 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 text-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none';
    $etiqueta = 'block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1';
    $ayuda = 'mt-1 text-xs text-gray-500 dark:text-gray-400';
    $error = 'mt-1 text-sm text-red-600 dark:text-red-400';
    $tarjeta = 'rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-4 space-y-4';
    $titulo = 'text-base font-semibold text-gray-900 dark:text-gray-100';
    $unidades = \App\Support\HotspotPerfilFormulario::UNIDADES;
    $avanzadoAbierto = $valor('rate_limit_avanzado') !== '' || $errors->has('rate_limit_avanzado') || isset($invalidos['velocidad']);
@endphp

<div class="space-y-4">
    <section class="{{ $tarjeta }}" aria-labelledby="perfil-datos">
        <h2 id="perfil-datos" class="{{ $titulo }}">Nombre</h2>
        <div>
            <label for="nombre" class="{{ $etiqueta }}">Nombre del perfil</label>
            <input type="text" name="nombre" id="nombre" value="{{ $valor('nombre') }}" class="{{ $campo }}"
                maxlength="150" required placeholder="Ej: 10 GB, Plaza 10M" autocomplete="off"
                @error('nombre') aria-invalid="true" aria-describedby="nombre-error" @enderror>
            @error('nombre')<p id="nombre-error" class="{{ $error }}">{{ $message }}</p>@enderror
            <p class="{{ $ayuda }}">Es el nombre que se ve al asignar el perfil a una cuenta. El MikroTik lo usa para identificar el perfil.</p>
        </div>
    </section>

    <section class="{{ $tarjeta }}" aria-labelledby="perfil-velocidad">
        <div>
            <h2 id="perfil-velocidad" class="{{ $titulo }}">Velocidad</h2>
            <p class="{{ $ayuda }}">Dejá las dos vacías para no poner tope.</p>
        </div>
        @if(isset($invalidos['velocidad']))
            <p class="text-sm text-amber-700 dark:text-amber-300">El valor guardado “{{ $invalidos['velocidad'] }}” no es válido para el MikroTik. Cargá la velocidad de nuevo.</p>
        @endif
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label for="bajada_mbps" class="{{ $etiqueta }}">Bajada (Mbps)</label>
                <input type="number" name="bajada_mbps" id="bajada_mbps" value="{{ $valor('bajada_mbps') }}" class="{{ $campo }}"
                    min="0.064" max="100000" step="any" inputmode="decimal" placeholder="Ej: 10"
                    @error('bajada_mbps') aria-invalid="true" aria-describedby="bajada-error" @enderror>
                @error('bajada_mbps')<p id="bajada-error" class="{{ $error }}">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="subida_mbps" class="{{ $etiqueta }}">Subida (Mbps)</label>
                <input type="number" name="subida_mbps" id="subida_mbps" value="{{ $valor('subida_mbps') }}" class="{{ $campo }}"
                    min="0.064" max="100000" step="any" inputmode="decimal" placeholder="Ej: 5"
                    @error('subida_mbps') aria-invalid="true" aria-describedby="subida-error" @enderror>
                @error('subida_mbps')<p id="subida-error" class="{{ $error }}">{{ $message }}</p>@enderror
            </div>
        </div>
        <details class="rounded-lg border border-gray-200 dark:border-gray-700 px-3 py-2" @if($avanzadoAbierto) open @endif>
            <summary class="cursor-pointer text-sm text-gray-700 dark:text-gray-300">Avanzado: valor del MikroTik con ráfagas</summary>
            <div class="mt-3">
                <label for="rate_limit_avanzado" class="{{ $etiqueta }}">Rate-limit del MikroTik</label>
                <input type="text" name="rate_limit_avanzado" id="rate_limit_avanzado" value="{{ $valor('rate_limit_avanzado') }}"
                    class="{{ $campo }} font-mono" maxlength="50" autocomplete="off" spellcheck="false"
                    placeholder="Ej: 5M/10M 8M/15M 4M/8M 16/16"
                    @error('rate_limit_avanzado') aria-invalid="true" aria-describedby="avanzado-error" @enderror>
                @error('rate_limit_avanzado')<p id="avanzado-error" class="{{ $error }}">{{ $message }}</p>@enderror
                <p class="{{ $ayuda }}">Solo si necesitás ráfagas. Va en formato subida/bajada. Si lo completás, dejá vacías la bajada y la subida de arriba.</p>
            </div>
        </details>
    </section>

    <section class="{{ $tarjeta }}" aria-labelledby="perfil-consumo">
        <h2 id="perfil-consumo" class="{{ $titulo }}">Cuota de datos</h2>
        <div class="max-w-sm">
            <label for="cuota_gb" class="{{ $etiqueta }}">Cuota (GB)</label>
            <input type="number" name="cuota_gb" id="cuota_gb" value="{{ $valor('cuota_gb') }}" class="{{ $campo }}"
                min="1" max="10240" step="1" inputmode="numeric" placeholder="Vacío = ilimitada"
                @error('cuota_gb') aria-invalid="true" aria-describedby="cuota-error" @enderror>
            @error('cuota_gb')<p id="cuota-error" class="{{ $error }}">{{ $message }}</p>@enderror
            <p class="{{ $ayuda }}">Tráfico total (subida + bajada) por cuenta. Al agotarse, la cuenta no puede volver a entrar hasta el reinicio de cuota.</p>
        </div>
    </section>

    <section class="{{ $tarjeta }}" aria-labelledby="perfil-conexion">
        <h2 id="perfil-conexion" class="{{ $titulo }}">Conexión</h2>
        <div class="max-w-sm">
            <label for="shared_users" class="{{ $etiqueta }}">Usuarios a la vez</label>
            <input type="number" name="shared_users" id="shared_users" value="{{ $valor('shared_users') }}" class="{{ $campo }}"
                min="1" max="100" step="1" inputmode="numeric" placeholder="Vacío = el del router"
                @error('shared_users') aria-invalid="true" aria-describedby="shared-error" @enderror>
            @error('shared_users')<p id="shared-error" class="{{ $error }}">{{ $message }}</p>@enderror
            <p class="{{ $ayuda }}">Cuántos equipos pueden entrar al mismo tiempo con la misma cuenta.</p>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            @foreach([['inactividad', 'Cortar por inactividad', 'Se desconecta si no usa internet durante este tiempo.'], ['sesion', 'Duración máxima de sesión', 'Pasado este tiempo tiene que volver a entrar.']] as [$clave, $texto, $explica])
                <div>
                    <label for="{{ $clave }}_valor" class="{{ $etiqueta }}">{{ $texto }}</label>
                    <div class="flex gap-2">
                        <input type="number" name="{{ $clave }}_valor" id="{{ $clave }}_valor" value="{{ $valor($clave.'_valor') }}"
                            class="{{ $campo }}" min="1" max="100000" step="1" inputmode="numeric" placeholder="Sin límite"
                            @error($clave.'_valor') aria-invalid="true" aria-describedby="{{ $clave }}-error" @enderror>
                        <label for="{{ $clave }}_unidad" class="sr-only">Unidad</label>
                        <select name="{{ $clave }}_unidad" id="{{ $clave }}_unidad" class="{{ $campo }}" style="width: auto">
                            @foreach($unidades as $codigo => $nombreUnidad)
                                <option value="{{ $codigo }}" @selected($valor($clave.'_unidad') === $codigo)>{{ $nombreUnidad }}</option>
                            @endforeach
                        </select>
                    </div>
                    @error($clave.'_valor')<p id="{{ $clave }}-error" class="{{ $error }}">{{ $message }}</p>@enderror
                    @if(isset($invalidos[$clave]))
                        <p class="mt-1 text-sm text-amber-700 dark:text-amber-300">El valor guardado “{{ $invalidos[$clave] }}” no se entiende y no se estaba aplicando. Cargalo de nuevo o dejalo vacío.</p>
                    @endif
                    <p class="{{ $ayuda }}">{{ $explica }} Vacío = sin límite.</p>
                </div>
            @endforeach
        </div>
    </section>
</div>
