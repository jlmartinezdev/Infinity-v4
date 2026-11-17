{{-- Tarjetas y tablas de Sesiones. Se arma en la página completa y sola en el refresco (?parcial=1). --}}
@php
    $estados = $estados ?? \App\Models\Servicio::estadosDisponibles();
    $linkUser = $linkUser ?? 'underline underline-offset-2 decoration-gray-400 dark:decoration-gray-500 text-gray-900 dark:text-gray-100 hover:text-gray-700 dark:hover:text-white';
    $btnPrimary = $btnPrimary ?? 'inline-flex items-center justify-center gap-2 px-4 py-2.5 min-h-11 bg-purple-600 hover:bg-purple-700 text-white rounded-lg text-sm font-medium focus:outline-none focus-visible:ring-2 focus-visible:ring-purple-400';
    $card = $card ?? 'rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800';
    $puedeCrear = $puedeCrear ?? (bool) auth()->user()?->tienePermiso('servicios.editar');
    $menuHotspot = $menuHotspot ?? fn (string $destino, array $extra = []) => route('hotspot.dashboard', array_filter(array_merge([
        'vista' => $destino,
        'router_id' => $selectedRouter?->router_id,
    ], $extra), fn ($valor) => $valor !== null && $valor !== ''));
    $sesiones = $sesiones ?? [];
    $offline = $offline ?? collect();
    $consumo = $consumo ?? [];
    $ultimasConexiones = $ultimasConexiones ?? [];
    $mappedCount = $mappedCount ?? 0;
    $estadoRouterActual = $estadoRouterActual ?? ['clase' => '', 'texto' => 'Sin datos'];
    $nVivas = count($sesiones);
    $nLocker = 0;
    $nPortal = 0;
    $nSinLocker = 0;
    foreach ($sesiones as $filaConteo) {
        $origenConteo = (string) ($filaConteo['origen'] ?? '');
        if ($origenConteo === 'locker') {
            $nLocker++;
        } elseif (in_array($origenConteo, ['sin-sesion', 'sin-usuario', 'trial'], true)) {
            $nPortal++;
        } else {
            $nSinLocker++;
        }
    }
@endphp
<span id="hotspot-dash-router-estado-nuevo" data-clase="{{ $estadoRouterActual['clase'] }}" data-texto="{{ $estadoRouterActual['texto'] }}" hidden></span>
<div id="hotspot-dash-kpis" class="grid grid-cols-2 lg:grid-cols-4 gap-3">
    @php
        $nConCuenta = $nLocker + $nSinLocker;
        $offlineVistas = [];
        $nCuota80 = 0;
        foreach ($offline as $shVista) {
            $vistaOff = \App\Services\RadiusHotspotSyncService::consumoVista(
                (int) ($consumo[$shVista->username] ?? 0),
                $shVista->hotspotPerfil?->cuota_gb
            );
            $offlineVistas[$shVista->getKey()] = $vistaOff;
            if (! empty($vistaOff['tiene_cuota']) && (float) ($vistaOff['porcentaje'] ?? 0) >= 80) {
                $nCuota80++;
            }
        }
        foreach ($sesiones as $filaCuota) {
            if (($filaCuota['origen'] ?? '') === 'locker'
                && ! empty($filaCuota['cuota']['tiene_cuota'])
                && (float) ($filaCuota['cuota']['porcentaje'] ?? 0) >= 80) {
                $nCuota80++;
            }
        }
        $nClientesOffline = $offline->pluck('cliente_id')->filter()->unique()->count();
        $kpis = [
            [
                'filtro' => 'en-linea',
                'titulo' => 'En línea ahora',
                'valor' => $routerError ? '—' : (string) $nVivas,
                'detalle' => $routerError
                    ? 'No pude leer el router'
                    : $nConCuenta.' con cuenta · '.$nPortal.' en portal (trial)',
                'alerta' => false,
            ],
            [
                'filtro' => 'trial',
                'titulo' => 'Sin cuenta (trial)',
                'valor' => $routerError ? '—' : (string) $nPortal,
                'detalle' => 'Conectado al portal, sin login',
                'alerta' => ! $routerError && $nPortal > 0,
            ],
            [
                'filtro' => 'sin-sesion',
                'titulo' => 'Cuentas sin sesión',
                'valor' => (string) $offline->count(),
                'detalle' => $nClientesOffline === 1 ? 'De 1 cliente' : 'De '.$nClientesOffline.' clientes',
                'alerta' => false,
            ],
            [
                'filtro' => 'cuota80',
                'titulo' => 'Cuota sobre 80 %',
                'valor' => (string) $nCuota80,
                'detalle' => 'Cuentas por agotar su plan',
                'alerta' => $nCuota80 > 0,
            ],
        ];
    @endphp
    @foreach($kpis as $kpi)
        <button type="button" class="hsd-kpi {{ $card }} px-4 py-3" data-tarjeta="{{ $kpi['filtro'] }}" aria-pressed="false">
            <span class="hsd-kpi-titulo block text-sm text-gray-600 dark:text-gray-300">{{ $kpi['titulo'] }}</span>
            <span class="block mt-1 text-2xl font-bold tabular-nums {{ $kpi['alerta'] ? 'hsd-kpi-alerta' : 'text-gray-900 dark:text-gray-100' }}">{{ $kpi['valor'] }}</span>
            <span class="block mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $kpi['detalle'] }}</span>
        </button>
    @endforeach
</div>

<div id="hotspot-dash-watch" class="relative space-y-4">
    <div id="hotspot-dash-skel" class="{{ $card }} px-4 py-6 space-y-3" style="display: none;" aria-hidden="true">
        <div class="h-8 rounded-lg bg-gray-100 dark:bg-gray-700 animate-pulse"></div>
        <div class="h-8 rounded-lg bg-gray-100 dark:bg-gray-700 animate-pulse"></div>
        <div class="h-8 w-2/3 rounded-lg bg-gray-100 dark:bg-gray-700 animate-pulse"></div>
    </div>

    <section id="hotspot-dash-vivos" class="relative {{ $card }} overflow-hidden" aria-labelledby="hotspot-dash-vivos-title">
        @if($routerError)
            <div id="hotspot-dash-stale" class="absolute inset-0 z-10 flex flex-col items-center justify-center gap-3 px-4" style="display:flex;background:rgba(17,24,39,0.8)" role="alert">
                <p class="max-w-md text-center text-sm font-medium text-white">No pude leer las sesiones de {{ $selectedRouter->nombre }}.</p>
                <details class="hotspot-dash-tech max-w-md text-center">
                    <summary class="text-sm text-gray-200 underline underline-offset-2">Detalle técnico</summary>
                    <p class="mt-2 text-sm text-gray-200 break-words">{{ $routerError }}</p>
                </details>
                <a href="{{ route('hotspot.dashboard', ['router_id' => $selectedRouter->router_id]) }}" class="{{ $btnPrimary }}" data-dash-refresh>Reintentar</a>
            </div>
        @endif
        <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700 flex flex-wrap items-start justify-between gap-3">
            <div class="min-w-0">
                <h2 id="hotspot-dash-vivos-title" class="text-base font-semibold text-gray-900 dark:text-gray-100 flex items-center gap-2" aria-live="polite">
                    @unless($routerError)
                        <span class="inline-block h-2 w-2 rounded-full bg-green-500" aria-hidden="true"></span>
                    @endunless
                    Sesiones en línea
                    @unless($routerError)
                        <span class="px-2 py-0.5 rounded-full bg-gray-100 dark:bg-gray-700 text-xs font-medium text-gray-600 dark:text-gray-300 tabular-nums">{{ count($sesiones) }}</span>
                    @endunless
                </h2>
            </div>
            @if($consultadoEn)
                <p id="hotspot-dash-pulse" class="text-xs text-gray-500 dark:text-gray-400 tabular-nums"
                    data-consultado="{{ $consultadoEn->toIso8601String() }}">Actualizado ahora</p>
            @endif
        </div>

        @if($routerError)
            <div class="px-4 py-16" aria-hidden="true"></div>
        @elseif(count($sesiones) === 0)
            <div class="px-4 py-8 text-center" role="status">
                <p class="text-sm text-gray-700 dark:text-gray-200">Nadie conectado en {{ $selectedRouter->nombre }}.</p>
            </div>
        @else
            <div id="hotspot-dash-vivos-scroll" class="overflow-x-auto max-h-[28rem] overflow-y-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                    <thead class="bg-gray-50 dark:bg-gray-900/40 sticky top-0">
                        <tr>
                            <th scope="col" class="px-4 py-2 text-left font-medium text-gray-600 dark:text-gray-300">Usuario / equipo</th>
                            <th scope="col" class="px-4 py-2 text-left font-medium text-gray-600 dark:text-gray-300">Cliente</th>
                            <th scope="col" class="px-4 py-2 text-left font-medium text-gray-600 dark:text-gray-300">Consumo del plan</th>
                            <th scope="col" class="px-4 py-2 text-left font-medium text-gray-600 dark:text-gray-300">Tráfico ahora</th>
                            <th scope="col" class="px-4 py-2 text-left font-medium text-gray-600 dark:text-gray-300">En línea</th>
                            <th scope="col" class="px-4 py-2 text-right font-medium text-gray-600 dark:text-gray-300">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        @foreach($sesiones as $fila)
                            @php
                                $esPortal = in_array($fila['origen'], ['sin-sesion', 'sin-usuario', 'trial'], true);
                                $esLocker = $fila['origen'] === 'locker';
                                $pctFila = $esLocker && ! empty($fila['cuota']['tiene_cuota'])
                                    ? (float) ($fila['cuota']['porcentaje'] ?? 0)
                                    : null;
                                $macOk = preg_match('/^([0-9a-fA-F]{2}[:-]){5}[0-9a-fA-F]{2}$/', (string) $fila['mac']) === 1;
                                $etiquetaPortal = $fila['origen'] === 'trial' ? 'TRIAL' : 'PORTAL';
                                $quien = $esPortal ? 'Invitado '.$fila['mac'] : $fila['username'];
                                $estadoCodigo = (string) $fila['estado'];
                                $estadoTexto = $estadoCodigo !== ''
                                    ? 'Cuenta '.preg_replace('/o$/', 'a', mb_strtolower((string) ($estados[$estadoCodigo] ?? $estadoCodigo)))
                                    : '';
                                $estadoTono = ['A' => 'ok', 'S' => 'warn'][$estadoCodigo] ?? 'off';
                                if ($esLocker && $fila['locker']?->fueraDelPlan()) {
                                    $estadoTexto = 'Fuera del plan · suspendida';
                                    $estadoTono = 'off';
                                }
                                $puedeCortar = $puedeCrear && $macOk;
                                $hayMenu = $macOk || $esLocker;
                            @endphp
                            <tr class="hotspot-dash-fila {{ $esLocker ? '' : 'bg-amber-50/60 dark:bg-amber-950/20' }}"
                                data-grupo="{{ $esPortal ? 'trial' : 'cuenta' }}"
                                data-pct="{{ $pctFila ?? '' }}"
                                data-buscar="{{ mb_strtolower($fila['username'].' '.$fila['ip'].' '.$fila['mac'].' '.$fila['cliente'].' '.$fila['perfil'].($esPortal ? ' invitado '.$etiquetaPortal : '')) }}">
                                <td class="px-4 py-3">
                                    @if($esPortal)
                                        <span class="flex items-center gap-2">
                                            <span class="font-semibold text-gray-900 dark:text-gray-100">Invitado</span>
                                            <span class="hsd-tag">{{ $etiquetaPortal }}</span>
                                        </span>
                                    @elseif($esLocker && $fila['username'] !== '—')
                                        <a href="{{ $menuHotspot('usuarios', ['buscar' => $fila['username']]) }}" class="font-semibold {{ $linkUser }}">{{ $fila['username'] }}</a>
                                    @else
                                        <span class="font-semibold text-gray-900 dark:text-gray-100">{{ $fila['username'] }}</span>
                                    @endif
                                    <span class="block mt-0.5 font-mono text-xs text-gray-500 dark:text-gray-400 whitespace-nowrap">{{ $fila['ip'] }} · {{ $fila['mac'] }}</span>
                                </td>
                                <td class="px-4 py-3">
                                    @if($esLocker)
                                        <span class="block max-w-[14rem] truncate text-gray-900 dark:text-gray-100" title="{{ $fila['cliente'] }}">{{ $fila['cliente'] !== '' ? $fila['cliente'] : '—' }}</span>
                                        @if($estadoTexto !== '')
                                            <span class="block text-xs hsd-estado is-{{ $estadoTono }}">{{ $estadoTexto }}</span>
                                        @endif
                                    @elseif($esPortal)
                                        <span class="text-gray-500 dark:text-gray-400">Sin cuenta asignada</span>
                                    @else
                                        <span class="text-gray-500 dark:text-gray-400">Usuario que no está en Infinity</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-gray-700 dark:text-gray-200">
                                    @include('hotspot._cuota-barra', [
                                        'perfil' => $fila['origen'] === 'trial' ? '' : $fila['perfil'],
                                        'vista' => $fila['cuota'] ?? null,
                                        'trial' => $fila['origen'] === 'trial',
                                        'trialResta' => ! empty($fila['trial_tiempo']) ? ($fila['cuota']['etiqueta'] ?? '') : '',
                                    ])
                                </td>
                                <td class="px-4 py-3 font-mono text-xs text-gray-600 dark:text-gray-300 whitespace-nowrap"
                                    data-trafico="{{ $fila['mac'] }}"
                                    data-subida="{{ $fila['bytes_subida'] ?? '' }}"
                                    data-bajada="{{ $fila['bytes_bajada'] ?? '' }}"
                                    title="Promedio desde la actualización anterior">—</td>
                                <td class="px-4 py-3 whitespace-nowrap tabular-nums text-gray-900 dark:text-gray-100">{{ $fila['uptime_legible'] ?? $fila['uptime'] }}</td>
                                <td class="px-4 py-3 text-right whitespace-nowrap">
                                    <div class="inline-flex items-center gap-1">
                                        @if($esPortal)
                                            <a href="{{ $menuHotspot('usuarios') }}" class="hsd-btn hsd-btn-primary">Asignar cuenta</a>
                                        @elseif($puedeCortar)
                                            <form method="POST" action="{{ route('hotspot.dashboard.desconectar', $selectedRouter) }}" data-accion="desconectar" class="inline" data-confirmar="{{ '¿Desconectar a '.$quien.'? Si entró con la cuenta del celular, no vuelve a entrar solo.' }}">
                                                @csrf
                                                <input type="hidden" name="mac" value="{{ $fila['mac'] }}">
                                                <button type="submit" class="hsd-btn hsd-btn-danger">Desconectar</button>
                                            </form>
                                        @endif
                                        @if($hayMenu)
                                            <div class="hsd-menu-wrap">
                                                <button type="button" class="hsd-btn hsd-btn-icon" data-menu-btn aria-haspopup="menu" aria-expanded="false" aria-label="Más acciones de {{ $quien }}">⋯</button>
                                                <div class="hsd-menu" role="menu" hidden>
                                                    @if($macOk)
                                                        <button type="button" role="menuitem" class="hsd-menu-item" data-copiar="{{ $fila['mac'] }}" data-copiar-texto>Copiar MAC</button>
                                                    @endif
                                                    @if($esLocker && $fila['username'] !== '—')
                                                        <a role="menuitem" class="hsd-menu-item" href="{{ $menuHotspot('usuarios', ['buscar' => $fila['username']]) }}">Ver usuario</a>
                                                    @endif
                                                    @if($esLocker && $fila['cliente_id'] && $puedeCrear)
                                                        <a role="menuitem" class="hsd-menu-item" href="{{ route('hotspot.clientes.edit', $fila['cliente_id']) }}">Editar cuenta</a>
                                                    @endif
                                                    @if($puedeCortar)
                                                        @if($esPortal)
                                                            <form method="POST" action="{{ route('hotspot.dashboard.desconectar', $selectedRouter) }}" data-accion="desconectar" data-confirmar="{{ '¿Desconectar a '.$quien.'?' }}">
                                                                @csrf
                                                                <input type="hidden" name="mac" value="{{ $fila['mac'] }}">
                                                                <button type="submit" role="menuitem" class="hsd-menu-item is-danger">Desconectar</button>
                                                            </form>
                                                        @endif
                                                        <form method="POST" action="{{ route('hotspot.dashboard.bloquear', $selectedRouter) }}" data-accion="bloquear" data-confirmar="{{ '¿Bloquear '.$fila['mac'].'? No va a poder entrar al hotspot hasta que la desbloquees.' }}">
                                                            @csrf
                                                            <input type="hidden" name="mac" value="{{ $fila['mac'] }}">
                                                            <button type="submit" role="menuitem" class="hsd-menu-item is-danger">Bloquear MAC</button>
                                                        </form>
                                                    @endif
                                                </div>
                                            </div>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <p id="hotspot-dash-vivos-vacio" class="px-4 py-6 text-sm text-gray-600 dark:text-gray-300" style="display:none" role="status">Ninguna sesión coincide con el filtro.</p>
        @endif
    </section>

    <section id="hotspot-dash-offline" class="{{ $card }} overflow-hidden" aria-labelledby="hotspot-dash-offline-title">
        <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 id="hotspot-dash-offline-title" class="text-base font-semibold text-gray-900 dark:text-gray-100 flex items-center gap-2">
                    Cuentas sin sesión
                    <span class="px-2 py-0.5 rounded-full bg-gray-100 dark:bg-gray-700 text-xs font-medium text-gray-600 dark:text-gray-300 tabular-nums">{{ $offline->count() }}</span>
                </h2>
                <p class="mt-0.5 text-sm text-gray-600 dark:text-gray-300">Cuentas de este router que ahora no están en línea.</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <div class="inline-flex rounded-lg border border-gray-200 dark:border-gray-600 p-0.5 text-sm" role="group" aria-label="Filtrar por cuota">
                    <button type="button" data-cuota-filtro="todos" class="px-2.5 py-1 rounded-md font-medium text-gray-900 dark:text-gray-100 bg-gray-100 dark:bg-gray-700" aria-pressed="true">Todos</button>
                    <button type="button" data-cuota-filtro="cuota" class="px-2.5 py-1 rounded-md text-gray-600 dark:text-gray-300" aria-pressed="false">Con cuota</button>
                    <button type="button" data-cuota-filtro="libre" class="px-2.5 py-1 rounded-md text-gray-600 dark:text-gray-300" aria-pressed="false">Ilimitadas</button>
                </div>
                @if($puedeCrear)
                    <a href="{{ route('hotspot.create') }}" class="{{ $btnPrimary }}">+ Nuevo usuario</a>
                @endif
            </div>
        </div>
        @if($offline->isEmpty())
            <p class="px-4 py-6 text-sm text-gray-600 dark:text-gray-300">
                @if($mappedCount === 0)
                    Este router no tiene cuentas.
                @else
                    Todas las cuentas de este router están en Sesiones en línea.
                @endif
            </p>
        @else
            @php
                $nombreClienteDe = function ($sh): string {
                    $cliente = $sh->cliente ?? $sh->servicio?->cliente;

                    return trim(($cliente?->nombre ?? '').' '.($cliente?->apellido ?? ''));
                };
                $gruposOffline = $offline
                    ->groupBy(fn ($sh) => $sh->cliente_id ? 'c'.$sh->cliente_id : 'u'.$sh->getKey())
                    ->sortBy(fn ($cuentas) => mb_strtolower($nombreClienteDe($cuentas->first())));
                $ultimasConexiones = $ultimasConexiones ?? [];
            @endphp
            <div id="hotspot-dash-offline-scroll" class="overflow-x-auto max-h-[28rem] overflow-y-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50 dark:bg-gray-900/40 sticky top-0">
                        <tr>
                            <th scope="col" class="px-4 py-2 text-left font-medium text-gray-600 dark:text-gray-300">Usuario</th>
                            <th scope="col" class="px-4 py-2 text-left font-medium text-gray-600 dark:text-gray-300">Consumo del plan</th>
                            <th scope="col" class="px-4 py-2 text-left font-medium text-gray-600 dark:text-gray-300">Última conexión</th>
                            <th scope="col" class="px-4 py-2 text-left font-medium text-gray-600 dark:text-gray-300">Cuenta</th>
                            <th scope="col" class="px-4 py-2 text-right font-medium text-gray-600 dark:text-gray-300">Acciones</th>
                        </tr>
                    </thead>
                    @foreach($gruposOffline as $grupoId => $cuentas)
                        @php
                            $nombre = $nombreClienteDe($cuentas->first());
                            $clienteId = $cuentas->first()->cliente_id;
                            $nCuentas = $cuentas->count();
                        @endphp
                        <tbody class="hsd-grupo" data-grupo-offline="{{ $grupoId }}">
                            <tr class="hsd-grupo-titulo">
                                <th scope="rowgroup" colspan="5" class="px-4 py-2 text-left text-sm font-normal">
                                    @if($clienteId)
                                        <a href="{{ route('hotspot.clientes.edit', $clienteId) }}" class="font-semibold {{ $linkUser }}">{{ $nombre !== '' ? $nombre : 'Cliente sin nombre' }}</a>
                                    @else
                                        <span class="font-semibold text-gray-900 dark:text-gray-100">{{ $nombre !== '' ? $nombre : 'Sin cliente' }}</span>
                                    @endif
                                    <span class="text-gray-500 dark:text-gray-400"> · {{ $nCuentas === 1 ? '1 cuenta' : $nCuentas.' cuentas' }}</span>
                                </th>
                            </tr>
                            @foreach($cuentas as $sh)
                                @php
                                    $codigo = (string) ($sh->servicio?->estado ?? '');
                                    $cuotaVista = $offlineVistas[$sh->getKey()];
                                    $perfilNombre = $sh->hotspotPerfil?->nombreDistintoDeCuota() ?? '';
                                    $ultima = $ultimasConexiones[$sh->username] ?? null;
                                    $estadoTexto = $codigo !== ''
                                        ? ucfirst((string) preg_replace('/o$/', 'a', mb_strtolower((string) ($estados[$codigo] ?? $codigo))))
                                        : '';
                                    $estadoTono = ['A' => 'ok', 'S' => 'warn'][$codigo] ?? 'off';
                                    if ($sh->fueraDelPlan()) {
                                        $estadoTexto = 'Fuera del plan · suspendida';
                                        $estadoTono = 'off';
                                    }
                                @endphp
                                <tr class="hotspot-dash-fila border-t border-gray-200 dark:border-gray-700"
                                    data-cuota="{{ ! empty($cuotaVista['tiene_cuota']) ? 'cuota' : 'libre' }}"
                                    data-pct="{{ ! empty($cuotaVista['tiene_cuota']) ? (float) ($cuotaVista['porcentaje'] ?? 0) : '' }}"
                                    data-buscar="{{ mb_strtolower($sh->username.' '.$nombre.' '.$perfilNombre.' '.($ultima['mac'] ?? '')) }}">
                                    <td class="px-4 py-3">
                                        <a href="{{ $menuHotspot('usuarios', ['buscar' => $sh->username]) }}" class="font-semibold {{ $linkUser }}">{{ $sh->username }}</a>
                                    </td>
                                    <td class="px-4 py-3 text-gray-700 dark:text-gray-200">
                                        @include('hotspot._cuota-barra', [
                                            'perfil' => $perfilNombre,
                                            'vista' => $cuotaVista,
                                        ])
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        @if($ultima)
                                            <span class="text-gray-700 dark:text-gray-200" title="{{ $ultima['fecha'] }}">{{ $ultima['texto'] }}</span>@if($ultima['mac'] !== '')<span class="font-mono text-xs text-gray-500 dark:text-gray-400"> · {{ $ultima['mac'] }}</span>@endif
                                        @else
                                            <span class="text-gray-500 dark:text-gray-400">Nunca se conectó</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        @if($estadoTexto !== '')
                                            <span class="hsd-estado is-{{ $estadoTono }}">{{ $estadoTexto }}</span>
                                        @else
                                            <span class="text-gray-500 dark:text-gray-400">Sin servicio</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-right whitespace-nowrap">
                                        <div class="inline-flex items-center gap-1">
                                            <form method="POST" action="{{ route('hotspot.sync', $sh) }}" data-accion="sincronizar" class="inline">
                                                @csrf
                                                <input type="hidden" name="from_dashboard" value="1">
                                                <input type="hidden" name="router_id" value="{{ $selectedRouter->router_id }}">
                                                <button type="submit" class="hsd-btn">Sincronizar</button>
                                            </form>
                                            <div class="hsd-menu-wrap">
                                                <button type="button" class="hsd-btn hsd-btn-icon" data-menu-btn aria-haspopup="menu" aria-expanded="false" aria-label="Más acciones de {{ $sh->username }}">⋯</button>
                                                <div class="hsd-menu" role="menu" hidden>
                                                    <a role="menuitem" class="hsd-menu-item" href="{{ $menuHotspot('usuarios', ['buscar' => $sh->username]) }}">Ver usuario</a>
                                                    @if($sh->cliente_id && $puedeCrear)
                                                        <a role="menuitem" class="hsd-menu-item" href="{{ route('hotspot.clientes.edit', $sh->cliente_id) }}">Editar cuenta</a>
                                                        <form method="POST" action="{{ route('hotspot.clientes.destroy', [$sh->cliente_id, $sh]) }}" data-accion="quitar" data-confirmar="{{ '¿Quitar la cuenta '.$sh->username.'? Deja de poder entrar al hotspot.' }}">
                                                            @csrf
                                                            @method('DELETE')
                                                            <input type="hidden" name="from_dashboard" value="1">
                                                            <input type="hidden" name="router_id" value="{{ $selectedRouter->router_id }}">
                                                            <button type="submit" role="menuitem" class="hsd-menu-item is-danger">Quitar cuenta</button>
                                                        </form>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    @endforeach
                </table>
            </div>
            <p id="hotspot-dash-offline-vacio" class="px-4 py-6 text-sm text-gray-600 dark:text-gray-300" style="display:none" role="status">Ninguna cuenta coincide con el filtro.</p>
        @endif
    </section>
</div>
