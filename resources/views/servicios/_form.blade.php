@csrf
@isset($servicio)
    @method('PUT')
@endisset

@php
    $servicio = $servicio ?? null;
    $tecnologias = $tecnologias ?? \App\Models\TipoTecnologia::orderBy('descripcion')->get();
    $tecnologiaKinds = $tecnologiaKinds ?? [];
    if ($tecnologiaKinds === []) {
        foreach ($tecnologias as $t) {
            $id = (string) $t->tecnologia_id;
            if (\App\Services\PedidoNodoOpcionesService::descripcionEsGpon($t->descripcion)) {
                $tecnologiaKinds[$id] = 'gpon';
            } elseif (\App\Services\PedidoNodoOpcionesService::descripcionEsWireless($t->descripcion)) {
                $tecnologiaKinds[$id] = 'wireless';
            } else {
                $tecnologiaKinds[$id] = 'otro';
            }
        }
    }
    $tecnologiaIdActual = old('tecnologia_id', $servicio?->plan?->tecnologia_id);
    $cpeAcceso = old('cpe_acceso', $servicio?->cpe_acceso);
    $cpeOnu = old('cpe_onu', $servicio?->cpe_onu);
    $cpeRouter = old('cpe_router', $servicio?->cpe_router);
    $cpeAntena = old('cpe_antena', $servicio?->cpe_antena);
    $cpeOnuOtro = old('cpe_onu') === \App\Support\CpeInventario::OTRO;
    $cpeRouterOtro = old('cpe_router') === \App\Support\CpeInventario::OTRO;
    $cpeAntenaOtro = old('cpe_antena') === \App\Support\CpeInventario::OTRO;
    $svcTab = 'servicio';
    if ($errors->hasAny([
        'mac_address', 'tr069_serial', 'tr069_product_class',
        'cpe_acceso', 'cpe_onu', 'cpe_onu_otro', 'cpe_router', 'cpe_router_otro',
        'cpe_antena', 'cpe_antena_otro', 'cpe_notas',
    ])) {
        $svcTab = 'equipo';
    } elseif ($errors->hasAny(['acuerdo_tipo', 'acuerdo_meses', 'acuerdo_desde'])) {
        $svcTab = 'acuerdo';
    }
    $svcTabCls = function (string $key) use ($svcTab): string {
        return $svcTab === $key
            ? 'bg-purple-600 text-white shadow-sm'
            : 'bg-gray-100 text-gray-600 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700';
    };
@endphp

<div class="space-y-6" id="servicio-form-tabs">
    <div class="flex flex-wrap items-center gap-2" role="tablist" aria-label="Secciones del servicio">
        <button type="button" role="tab" data-svc-tab="servicio" aria-selected="{{ $svcTab === 'servicio' ? 'true' : 'false' }}"
            class="svc-tab-btn inline-flex items-center gap-2 rounded-full px-3 py-1.5 text-sm transition {{ $svcTabCls('servicio') }}">Servicio</button>
        <button type="button" role="tab" data-svc-tab="equipo" aria-selected="{{ $svcTab === 'equipo' ? 'true' : 'false' }}"
            class="svc-tab-btn inline-flex items-center gap-2 rounded-full px-3 py-1.5 text-sm transition {{ $svcTabCls('equipo') }}">Equipo en casa</button>
        <button type="button" role="tab" data-svc-tab="acuerdo" aria-selected="{{ $svcTab === 'acuerdo' ? 'true' : 'false' }}"
            class="svc-tab-btn inline-flex items-center gap-2 rounded-full px-3 py-1.5 text-sm transition {{ $svcTabCls('acuerdo') }}">Acuerdo</button>
    </div>

    <div data-svc-panel="servicio" class="{{ $svcTab === 'servicio' ? 'space-y-6' : 'hidden space-y-6' }}" role="tabpanel">
        @if(!isset($servicio))
            <div>
                <label for="cliente_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Cliente *</label>
                <select name="cliente_id" id="cliente_id" required
                    class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none transition-colors bg-white dark:bg-gray-700 dark:text-gray-100">
                    <option value="">Seleccione un cliente</option>
                    @foreach($clientes as $c)
                        <option value="{{ $c->cliente_id }}" {{ old('cliente_id', $clienteId ?? '') == $c->cliente_id ? 'selected' : '' }}>
                            {{ $c->cedula }} — {{ $c->nombre }} {{ $c->apellido }}
                        </option>
                    @endforeach
                </select>
                @error('cliente_id')
                    <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror
            </div>
        @else
            <input type="hidden" name="cliente_id" value="{{ $servicio->cliente_id }}">
            <p class="text-sm text-gray-600 dark:text-gray-300">
                <span class="font-medium text-gray-700 dark:text-gray-300">Cliente:</span>
                {{ $servicio->cliente->cedula }} — {{ $servicio->cliente->nombre }} {{ $servicio->cliente->apellido }}
            </p>
        @endif

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <span class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Tecnología *</span>
                <input type="hidden" name="tecnologia_id" id="tecnologia_id" value="{{ $tecnologiaIdActual }}" required
                    data-kinds='@json($tecnologiaKinds)'>
                <div class="flex flex-wrap gap-2" role="radiogroup" aria-label="Tecnología">
                    @foreach($tecnologias as $t)
                        @php
                            $techOn = (string) $tecnologiaIdActual === (string) $t->tecnologia_id;
                            $kind = $tecnologiaKinds[(string) $t->tecnologia_id] ?? 'otro';
                        @endphp
                        <button type="button"
                            class="svc-tech-card relative h-14 w-28 rounded-lg border p-2 cursor-pointer flex flex-col items-center justify-center bg-white dark:bg-gray-800 {{ $techOn ? 'ring-2 ring-purple-500 border-purple-500' : 'border-gray-200 dark:border-gray-600' }}"
                            data-tecnologia-id="{{ $t->tecnologia_id }}"
                            data-kind="{{ $kind }}"
                            aria-pressed="{{ $techOn ? 'true' : 'false' }}">
                            <span class="svc-tech-check {{ $techOn ? '' : 'hidden' }} absolute top-1 right-2 w-4 h-4 rounded-full bg-purple-600 text-white flex items-center justify-center pointer-events-none" aria-hidden="true">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                            </span>
                            @if($kind === 'gpon')
                                <svg class="w-5 h-5 shrink-0 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 12h4l2-6 4 12 2-6h4"/></svg>
                            @elseif($kind === 'wireless')
                                <svg class="w-5 h-5 shrink-0 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.111 16.404a5.5 5.5 0 017.778 0M12 20h.01M5.05 13.05a10 10 0 0113.9 0M1.394 9.394c6.258-6.258 16.4-6.258 22.658 0"/></svg>
                            @endif
                            <span class="mt-1 block text-xs font-semibold leading-tight text-gray-800 dark:text-gray-100">{{ $t->descripcion }}</span>
                        </button>
                    @endforeach
                </div>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Al cambiar de tecnología, elegí el plan y el pool nuevos y actualizá el equipo en casa.</p>
                @error('tecnologia_id')
                    <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="plan_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Plan *</label>
                <select name="plan_id" id="plan_id" required
                    class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none transition-colors bg-white dark:bg-gray-700 dark:text-gray-100">
                    <option value="">Seleccione un plan</option>
                    @foreach($planes as $pl)
                        <option value="{{ $pl->plan_id }}"
                            data-precio="{{ (float) ($pl->precio ?? 0) }}"
                            data-tecnologia-id="{{ $pl->tecnologia_id }}"
                            {{ old('plan_id', $servicio?->plan_id) == $pl->plan_id ? 'selected' : '' }}>
                            {{ $pl->nombre }} — {{ $pl->velocidad }}
                        </option>
                    @endforeach
                </select>
                @error('plan_id')
                    <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <label for="pool_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Pool de IP *</label>
                <select name="pool_id" id="pool_id" required
                    class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none transition-colors bg-white dark:bg-gray-700 dark:text-gray-100">
                    <option value="">Seleccione un pool</option>
                    @foreach($pools as $p)
                        @php
                            $nodoPool = $p->router?->nodo;
                            $poolGpon = (bool) $p->olt_id || ($nodoPool && $nodoPool->manejaGpon());
                            $poolWireless = $nodoPool && $nodoPool->manejaWireless();
                            if (! $poolGpon && ! $poolWireless) {
                                $poolGpon = true;
                                $poolWireless = true;
                            }
                            $poolDesc = trim((string) ($p->descripcion ?? ''));
                            $poolRango = trim((string) ($p->ip_range ?? ''));
                            $poolRouter = $p->router?->nombre ?? '—';
                            $poolLabel = '#'.$p->pool_id;
                            if ($poolDesc !== '') {
                                $poolLabel .= ' · '.$poolDesc;
                            }
                            if ($poolRango !== '') {
                                $poolLabel .= ' · '.$poolRango;
                            }
                            $poolLabel .= ' ('.$poolRouter.')';
                        @endphp
                        <option value="{{ $p->pool_id }}"
                            data-gpon="{{ $poolGpon ? '1' : '0' }}"
                            data-wireless="{{ $poolWireless ? '1' : '0' }}"
                            {{ old('pool_id', $servicio?->pool_id) == $p->pool_id ? 'selected' : '' }}>
                            {{ $poolLabel }}
                        </option>
                    @endforeach
                </select>
                @error('pool_id')
                    <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="estado" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Estado</label>
                <select name="estado" id="estado"
                    class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none transition-colors bg-white dark:bg-gray-700 dark:text-gray-100">
                    <option value="A" {{ old('estado', $servicio?->estado) === 'A' ? 'selected' : '' }}>Activo</option>
                    <option value="S" {{ old('estado', $servicio?->estado) === 'S' ? 'selected' : '' }}>Suspendido</option>
                    <option value="C" {{ old('estado', $servicio?->estado) === 'C' ? 'selected' : '' }}>Cancelado</option>
                </select>
                @error('estado')
                    <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror
            </div>
        </div>

        <div>
            <label for="ip" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">IP</label>
            <input type="text" name="ip" id="ip" value="{{ old('ip', $servicio?->ip) }}"
                class="w-full max-w-md px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none transition-colors bg-white dark:bg-gray-700 dark:text-gray-100"
                maxlength="15" placeholder="192.168.1.1">
            <div id="servicio-form-ips-app"></div>
            @error('ip')
                <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
            @enderror
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <label for="usuario_pppoe" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Usuario PPPoE</label>
                <input type="text" name="usuario_pppoe" id="usuario_pppoe" value="{{ old('usuario_pppoe', $servicio?->usuario_pppoe) }}"
                    class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none transition-colors bg-white dark:bg-gray-700 dark:text-gray-100"
                    maxlength="100" placeholder="usuario@proveedor">
                @error('usuario_pppoe')
                    <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="password_pppoe" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Contraseña PPPoE</label>
                <input type="text" name="password_pppoe" id="password_pppoe" value="{{ old('password_pppoe', $servicio?->password_pppoe) }}"
                    class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none transition-colors bg-white dark:bg-gray-700 dark:text-gray-100"
                    maxlength="20" placeholder="Contraseña del usuario PPPoE">
                @error('password_pppoe')
                    <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <label for="fecha_instalacion" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Fecha instalación</label>
                <input type="date" name="fecha_instalacion" id="fecha_instalacion" value="{{ old('fecha_instalacion', $servicio?->fecha_instalacion?->format('Y-m-d')) }}"
                    class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none transition-colors bg-white dark:bg-gray-700 dark:text-gray-100">
                @error('fecha_instalacion')
                    <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror
            </div>

            @isset($servicio)
            <div>
                <label for="fecha_cancelacion" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Fecha cancelación</label>
                <input type="date" name="fecha_cancelacion" id="fecha_cancelacion" value="{{ old('fecha_cancelacion', $servicio?->fecha_cancelacion?->format('Y-m-d')) }}"
                    class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none transition-colors bg-white dark:bg-gray-700 dark:text-gray-100">
                @error('fecha_cancelacion')
                    <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror
            </div>
            @endisset
        </div>
    </div>

    <div data-svc-panel="equipo" class="{{ $svcTab === 'equipo' ? 'space-y-6' : 'hidden space-y-6' }}" role="tabpanel">
        <p id="cpe-equipo-hint" class="text-sm text-gray-500 dark:text-gray-400">Elegí primero la tecnología. Se muestran solo los equipos que corresponden.</p>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <label for="cpe_acceso" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Acceso remoto</label>
                <select name="cpe_acceso" id="cpe_acceso"
                    class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none transition-colors bg-white dark:bg-gray-700 dark:text-gray-100">
                    <option value="" {{ $cpeAcceso === null || $cpeAcceso === '' ? 'selected' : '' }}>Sin definir</option>
                    @foreach(\App\Support\CpeInventario::accesos() as $key => $label)
                        <option value="{{ $key }}" {{ $cpeAcceso === $key ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">SSH = Huawei / antena Ubnt. ACS = Iuron, TP-Link, etc.</p>
                @error('cpe_acceso')
                    <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label for="mac_address" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">MAC address</label>
                <input type="text" name="mac_address" id="mac_address" value="{{ old('mac_address', $servicio?->mac_address) }}"
                    class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none transition-colors bg-white dark:bg-gray-700 dark:text-gray-100"
                    maxlength="20" placeholder="00:00:00:00:00:00">
                @error('mac_address')
                    <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror
            </div>
            <div data-cpe-para="gpon">
                <label for="cpe_onu" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">ONU</label>
                <select name="cpe_onu" id="cpe_onu"
                    class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none transition-colors bg-white dark:bg-gray-700 dark:text-gray-100">
                    <option value="">Ninguna / no aplica</option>
                    @foreach(\App\Support\CpeInventario::opciones('onu', $cpeOnu) as $key => $label)
                        <option value="{{ $key }}" {{ $cpeOnu === $key ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                    <option value="{{ \App\Support\CpeInventario::OTRO }}" {{ $cpeOnuOtro ? 'selected' : '' }}>Otro modelo…</option>
                </select>
                <input type="text" name="cpe_onu_otro" id="cpe_onu_otro" value="{{ old('cpe_onu_otro') }}"
                    class="{{ $cpeOnuOtro ? '' : 'hidden' }} mt-2 w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none transition-colors bg-white dark:bg-gray-700 dark:text-gray-100"
                    maxlength="64" placeholder="Nombre del modelo de ONU">
                @error('cpe_onu')
                    <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror
                @error('cpe_onu_otro')
                    <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror
            </div>
            <div data-cpe-para="wireless">
                <label for="cpe_antena" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Antena</label>
                <select name="cpe_antena" id="cpe_antena"
                    class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none transition-colors bg-white dark:bg-gray-700 dark:text-gray-100">
                    <option value="">Ninguna / no aplica</option>
                    @foreach(\App\Support\CpeInventario::opciones('antena', $cpeAntena) as $key => $label)
                        <option value="{{ $key }}" {{ $cpeAntena === $key ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                    <option value="{{ \App\Support\CpeInventario::OTRO }}" {{ $cpeAntenaOtro ? 'selected' : '' }}>Otro modelo…</option>
                </select>
                <input type="text" name="cpe_antena_otro" id="cpe_antena_otro" value="{{ old('cpe_antena_otro') }}"
                    class="{{ $cpeAntenaOtro ? '' : 'hidden' }} mt-2 w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none transition-colors bg-white dark:bg-gray-700 dark:text-gray-100"
                    maxlength="64" placeholder="Nombre del modelo de antena">
                @error('cpe_antena')
                    <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror
                @error('cpe_antena_otro')
                    <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror
            </div>
            <div data-cpe-para="ambos">
                <label for="cpe_router" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Router WiFi</label>
                <select name="cpe_router" id="cpe_router"
                    class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none transition-colors bg-white dark:bg-gray-700 dark:text-gray-100">
                    <option value="" id="cpe-router-empty">Ninguno</option>
                    @foreach(\App\Support\CpeInventario::opciones('router', $cpeRouter) as $key => $label)
                        <option value="{{ $key }}" {{ $cpeRouter === $key ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                    <option value="{{ \App\Support\CpeInventario::OTRO }}" {{ $cpeRouterOtro ? 'selected' : '' }}>Otro modelo…</option>
                </select>
                <input type="text" name="cpe_router_otro" id="cpe_router_otro" value="{{ old('cpe_router_otro') }}"
                    class="{{ $cpeRouterOtro ? '' : 'hidden' }} mt-2 w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none transition-colors bg-white dark:bg-gray-700 dark:text-gray-100"
                    maxlength="64" placeholder="Nombre del modelo de router">
                @error('cpe_router')
                    <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror
                @error('cpe_router_otro')
                    <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label for="cpe_notas" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Notas de equipo</label>
                <input type="text" name="cpe_notas" id="cpe_notas" value="{{ old('cpe_notas', $servicio?->cpe_notas) }}"
                    class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none transition-colors bg-white dark:bg-gray-700 dark:text-gray-100"
                    maxlength="120" placeholder="Ej: ONU bridge + Iuron en modo router">
                @error('cpe_notas')
                    <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <label for="tr069_serial" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Serial TR-069</label>
                <input type="text" name="tr069_serial" id="tr069_serial" value="{{ old('tr069_serial', $servicio?->tr069_serial) }}"
                    class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none transition-colors bg-white dark:bg-gray-700 dark:text-gray-100"
                    maxlength="64" placeholder="Serial del CPE en el ACS">
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Obligatorio si el acceso es ACS.</p>
                @error('tr069_serial')
                    <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label for="tr069_product_class" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">TR-069 product class</label>
                <input type="text" name="tr069_product_class" id="tr069_product_class" value="{{ old('tr069_product_class', $servicio?->tr069_product_class) }}"
                    class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none transition-colors bg-white dark:bg-gray-700 dark:text-gray-100"
                    maxlength="64" placeholder="Opcional (p. ej. DEVICE)">
                @error('tr069_product_class')
                    <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror
            </div>
        </div>
    </div>

    <div data-svc-panel="acuerdo" class="{{ $svcTab === 'acuerdo' ? 'space-y-6' : 'hidden space-y-6' }}" role="tabpanel">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div>
                <label for="acuerdo_tipo" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Acuerdo de facturación</label>
                <select name="acuerdo_tipo" id="acuerdo_tipo"
                    class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none transition-colors bg-white dark:bg-gray-700 dark:text-gray-100">
                    @php($acuerdoTipo = old('acuerdo_tipo', $servicio?->acuerdo_tipo ?? 'ninguno'))
                    <option value="ninguno" {{ $acuerdoTipo === 'ninguno' ? 'selected' : '' }}>Sin acuerdo</option>
                    <option value="libre" {{ $acuerdoTipo === 'libre' ? 'selected' : '' }}>Internet libre (sin facturar)</option>
                    <option value="meses" {{ $acuerdoTipo === 'meses' ? 'selected' : '' }}>Meses sin facturar</option>
                </select>
                @error('acuerdo_tipo')
                    <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label for="acuerdo_meses" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Cantidad de meses</label>
                <input type="number" min="1" max="24" name="acuerdo_meses" id="acuerdo_meses" value="{{ old('acuerdo_meses', $servicio?->acuerdo_meses) }}"
                    class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none transition-colors bg-white dark:bg-gray-700 dark:text-gray-100"
                    placeholder="Ej: 2">
                @error('acuerdo_meses')
                    <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label for="acuerdo_desde" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Aplicar desde</label>
                <input type="date" name="acuerdo_desde" id="acuerdo_desde" value="{{ old('acuerdo_desde', $servicio?->acuerdo_desde?->format('Y-m-d')) }}"
                    class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none transition-colors bg-white dark:bg-gray-700 dark:text-gray-100">
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Para "meses sin facturar". Si no se carga, se usa hoy.</p>
                @error('acuerdo_desde')
                    <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror
            </div>
        </div>
    </div>

    <div class="flex flex-wrap gap-3 pt-2 border-t border-gray-200 dark:border-gray-700">
        <button type="submit"
            class="inline-flex items-center px-4 py-2 bg-purple-600 text-white rounded-lg font-medium hover:bg-purple-700 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800">
            {{ $servicio ? 'Actualizar servicio' : 'Crear servicio' }}
        </button>
        <a href="{{ route('servicios.index') }}"
            class="inline-flex items-center px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-gray-200 rounded-lg font-medium hover:bg-gray-300 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-gray-500 dark:focus:ring-gray-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800">
            Cancelar
        </a>
    </div>
</div>

<script>
window.__SERVICIO_FORM_IPS_CONFIG__ = { ipsDisponiblesUrl: "{{ route('servicios.ips-disponibles') }}" };
(function () {
    var root = document.getElementById('servicio-form-tabs');
    if (!root) return;
    var activeCls = ['bg-purple-600', 'text-white', 'shadow-sm'];
    var idleCls = ['bg-gray-100', 'text-gray-600', 'hover:bg-gray-200', 'dark:bg-gray-800', 'dark:text-gray-300', 'dark:hover:bg-gray-700'];
    function show(tab) {
        root.querySelectorAll('[data-svc-panel]').forEach(function (p) {
            var on = p.getAttribute('data-svc-panel') === tab;
            p.classList.toggle('hidden', !on);
        });
        root.querySelectorAll('.svc-tab-btn').forEach(function (btn) {
            var on = btn.getAttribute('data-svc-tab') === tab;
            btn.setAttribute('aria-selected', on ? 'true' : 'false');
            activeCls.forEach(function (c) { btn.classList.toggle(c, on); });
            idleCls.forEach(function (c) { btn.classList.toggle(c, !on); });
        });
    }
    root.addEventListener('click', function (e) {
        var btn = e.target.closest('.svc-tab-btn');
        if (!btn || !root.contains(btn)) return;
        show(btn.getAttribute('data-svc-tab'));
    });
    var form = root.closest('form');
    if (form) {
        form.addEventListener('invalid', function (e) {
            var panel = e.target.closest('[data-svc-panel]');
            if (panel) show(panel.getAttribute('data-svc-panel'));
        }, true);
    }
})();
(function () {
    var techSel = document.getElementById('tecnologia_id');
    var planSel = document.getElementById('plan_id');
    var poolSel = document.getElementById('pool_id');
    if (!techSel || !planSel || !poolSel) return;
    var kinds = {};
    try { kinds = JSON.parse(techSel.getAttribute('data-kinds') || '{}'); } catch (e) {}
    var planOpts = Array.prototype.map.call(planSel.options, function (o) {
        return {
            value: o.value,
            text: o.textContent,
            tech: o.getAttribute('data-tecnologia-id') || '',
            precio: o.getAttribute('data-precio') || ''
        };
    }).filter(function (o) { return o.value !== ''; });
    var poolOpts = Array.prototype.map.call(poolSel.options, function (o) {
        return {
            value: o.value,
            text: o.textContent,
            gpon: o.getAttribute('data-gpon') === '1',
            wireless: o.getAttribute('data-wireless') === '1'
        };
    }).filter(function (o) { return o.value !== ''; });

    function rebuild(sel, items, current, placeholder) {
        var keep = String(current || '');
        sel.innerHTML = '';
        var ph = document.createElement('option');
        ph.value = '';
        ph.textContent = placeholder;
        sel.appendChild(ph);
        items.forEach(function (it) {
            var o = document.createElement('option');
            o.value = it.value;
            o.textContent = it.text;
            if (it.precio != null && it.precio !== '') o.setAttribute('data-precio', it.precio);
            if (it.tech) o.setAttribute('data-tecnologia-id', it.tech);
            if (it.gpon != null) o.setAttribute('data-gpon', it.gpon ? '1' : '0');
            if (it.wireless != null) o.setAttribute('data-wireless', it.wireless ? '1' : '0');
            if (String(it.value) === keep) o.selected = true;
            sel.appendChild(o);
        });
    }

    function kindActual() {
        var tech = String(techSel.value || '');
        if (!tech) return '';
        var card = document.querySelector('.svc-tech-card[data-tecnologia-id="' + tech + '"]');
        var fromCard = card ? (card.getAttribute('data-kind') || '') : '';
        return fromCard || kinds[tech] || 'otro';
    }

    function aplicar(cambioTech) {
        var tech = String(techSel.value || '');
        var kind = kindActual();
        var planAntes = planSel.value;
        var poolAntes = poolSel.value;
        var planes = planOpts.filter(function (o) { return tech !== '' && String(o.tech) === tech; });
        rebuild(planSel, planes, planAntes, tech ? 'Seleccione un plan' : 'Seleccione primero la tecnología');
        var pools = poolOpts.filter(function (o) {
            if (!kind || kind === 'otro') return true;
            if (kind === 'gpon') return o.gpon;
            if (kind === 'wireless') return o.wireless;
            return true;
        });
        rebuild(poolSel, pools, poolAntes, 'Seleccione un pool');
        if (poolSel.value !== poolAntes) {
            poolSel.dispatchEvent(new Event('change', { bubbles: true }));
        }
        mostrarEquipoPorTech(kind, !!cambioTech);
    }

    function mostrarEquipoPorTech(kind, limpiarOcultos) {
        document.querySelectorAll('[data-cpe-para]').forEach(function (el) {
            var para = el.getAttribute('data-cpe-para');
            var show = !kind || kind === 'otro' || para === 'ambos' || para === kind;
            el.classList.toggle('hidden', !show);
            if (limpiarOcultos && !show) {
                el.querySelectorAll('select').forEach(function (s) { s.value = ''; });
                el.querySelectorAll('input[name$="_otro"]').forEach(function (i) {
                    i.value = '';
                    i.classList.add('hidden');
                });
            }
        });
        var empty = document.getElementById('cpe-router-empty');
        if (empty) {
            empty.textContent = kind === 'gpon' ? 'Ninguno (la ONU es el router)' : 'Ninguno';
        }
        var hint = document.getElementById('cpe-equipo-hint');
        if (hint) {
            if (kind === 'gpon') hint.textContent = 'Fibra: ONU y, si la ONU va en bridge, el router WiFi. Podés agregar otro modelo si no está en la lista.';
            else if (kind === 'wireless') hint.textContent = 'Wireless: antena y, si hay, el router WiFi. Podés agregar otro modelo si no está en la lista.';
            else hint.textContent = 'Elegí GPON o Wireless arriba para ver los equipos que corresponden.';
        }
    }

    function hookOtro(selId, inputId) {
        var sel = document.getElementById(selId);
        var inp = document.getElementById(inputId);
        if (!sel || !inp) return;
        sel.addEventListener('change', function () {
            var on = sel.value === '__otro__';
            inp.classList.toggle('hidden', !on);
            if (on) inp.focus();
        });
    }
    hookOtro('cpe_onu', 'cpe_onu_otro');
    hookOtro('cpe_router', 'cpe_router_otro');
    hookOtro('cpe_antena', 'cpe_antena_otro');

    var selCls = ['ring-2', 'ring-purple-500', 'border-purple-500'];
    var idleBorder = ['border-gray-200', 'dark:border-gray-600'];
    function marcarTarjetaTech(id) {
        document.querySelectorAll('.svc-tech-card').forEach(function (card) {
            var on = String(card.getAttribute('data-tecnologia-id')) === String(id || '');
            selCls.forEach(function (c) { card.classList.toggle(c, on); });
            idleBorder.forEach(function (c) { card.classList.toggle(c, !on); });
            var check = card.querySelector('.svc-tech-check');
            if (check) check.classList.toggle('hidden', !on);
            card.setAttribute('aria-pressed', on ? 'true' : 'false');
        });
    }
    document.querySelectorAll('.svc-tech-card').forEach(function (card) {
        card.addEventListener('click', function () {
            var id = card.getAttribute('data-tecnologia-id') || '';
            if (String(techSel.value) === String(id)) return;
            techSel.value = id;
            marcarTarjetaTech(id);
            techSel.dispatchEvent(new Event('change', { bubbles: true }));
        });
    });
    techSel.addEventListener('change', function () { aplicar(true); });
    marcarTarjetaTech(techSel.value);
    aplicar(false);
})();
</script>
@push('scripts')
<script src="{{ asset(mix('js/servicio-form-ips.js')) }}" defer></script>
@endpush
