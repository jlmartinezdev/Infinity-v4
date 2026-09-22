@extends('layouts.app')

@section('title', 'Sesiones hotspot')

@php
    $estados = \App\Models\Servicio::estadosDisponibles();
    $estadoBadge = [
        'A' => 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-200',
        'S' => 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-200',
        'C' => 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-200',
        'X' => 'bg-gray-100 text-gray-700 dark:bg-gray-600 dark:text-gray-200',
        'P' => 'bg-gray-100 text-gray-700 dark:bg-gray-600 dark:text-gray-200',
    ];
    $origenBadge = [
        'locker' => 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-200',
        'trial' => 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-200',
        'sin-locker' => 'bg-gray-100 text-gray-700 dark:bg-gray-600 dark:text-gray-200',
        'sin-usuario' => 'bg-gray-100 text-gray-700 dark:bg-gray-600 dark:text-gray-200',
    ];
    $origenLabel = [
        'locker' => 'En línea',
        'trial' => 'Trial',
        'sin-locker' => 'Sin locker',
        'sin-usuario' => 'Sin usuario',
    ];
    $linkUser = 'underline underline-offset-2 decoration-gray-400 dark:decoration-gray-500 text-gray-900 dark:text-gray-100 hover:text-gray-700 dark:hover:text-white';
    $btnPrimary = 'inline-flex items-center px-4 py-2.5 min-h-11 bg-purple-600 hover:bg-purple-700 text-white rounded-lg text-sm font-medium focus:outline-none focus-visible:ring-2 focus-visible:ring-purple-400';
    $btnSecondary = 'inline-flex items-center px-4 py-2.5 min-h-11 bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-gray-200 rounded-lg text-sm font-medium hover:bg-gray-300 dark:hover:bg-gray-600 focus:outline-none focus-visible:ring-2 focus-visible:ring-purple-400';
    $fieldClass = 'w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 text-sm px-3 py-2.5 min-h-11 focus:border-purple-400 focus:ring-2 focus:ring-purple-400/20 focus:outline-none';
@endphp

@push('styles')
<style>
    @include('hotspot._cuota-ring-styles')
    .hotspot-dash-tech > summary {
        cursor: pointer;
        list-style: none;
    }
    .hotspot-dash-tech > summary::-webkit-details-marker {
        display: none;
    }
</style>
@endpush

@section('content')
<div class="max-w-7xl mx-auto">
    <div class="mb-6">
        <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100">Sesiones hotspot</h1>
        <form id="hotspot-dash-router-form" method="GET" action="{{ route('hotspot.dashboard') }}" class="mt-3 flex flex-wrap items-end gap-2">
            <div class="flex-1 min-w-[16rem] space-y-2">
                <label for="hotspot-dash-router-q" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Router</label>
                <input type="search" id="hotspot-dash-router-q" autocomplete="off"
                    placeholder="Filtrar por nombre, IP o nodo"
                    aria-controls="hotspot-dash-router"
                    class="{{ $fieldClass }}">
                <label for="hotspot-dash-router" class="sr-only">Elegir router</label>
                <select name="router_id" id="hotspot-dash-router"
                    data-loaded="{{ $selectedRouter?->router_id }}"
                    class="{{ $fieldClass }}">
                    @unless($selectedRouter)
                        <option value="" selected disabled>Elegí un router</option>
                    @endunless
                    @foreach($routersPorNodo as $nodoNombre => $grupo)
                        <optgroup label="{{ $nodoNombre }}">
                            @foreach($grupo as $r)
                                <option value="{{ $r->router_id }}"
                                    data-nombre="{{ $r->nombre }}"
                                    data-ip="{{ $r->ip }}"
                                    data-nodo="{{ $nodoNombre }}"
                                    @selected($selectedRouter && (int) $selectedRouter->router_id === (int) $r->router_id)>
                                    {{ $r->nombre }} ({{ $r->ip }})
                                </option>
                            @endforeach
                        </optgroup>
                    @endforeach
                </select>
            </div>
            <button type="submit" id="hotspot-dash-ver"
                class="{{ $btnSecondary }}">
                Ver
            </button>
        </form>
        <nav class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-1 text-sm" aria-label="Otras vistas hotspot">
            <a href="{{ route('hotspot.index') }}" class="text-gray-600 dark:text-gray-300 hover:text-gray-900 dark:hover:text-gray-100 underline-offset-2 hover:underline">Usuarios hotspot</a>
            @if(auth()->user()?->tienePermiso('servicios-hotspot-perfiles.ver') || auth()->user()?->tienePermiso('servicios.ver'))
                <a href="{{ route('hotspot.perfiles.index') }}" class="text-gray-600 dark:text-gray-300 hover:text-gray-900 dark:hover:text-gray-100 underline-offset-2 hover:underline">Perfiles</a>
            @endif
        </nav>
    </div>

    @if($selectedRouter)
        <div id="hotspot-dash-session-host"
            data-copy="Tu sesión se cerró. Entrá de nuevo para seguir las sesiones."
            data-entrar="{{ route('login') }}"></div>
        <div id="hotspot-dash-watch" class="relative space-y-6">
            <div id="hotspot-dash-skel" class="rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 px-4 py-6 space-y-3" style="display: none;" aria-hidden="true">
                <div class="h-8 rounded-lg bg-gray-100 dark:bg-gray-700 animate-pulse"></div>
                <div class="h-8 rounded-lg bg-gray-100 dark:bg-gray-700 animate-pulse"></div>
                <div class="h-8 w-2/3 rounded-lg bg-gray-100 dark:bg-gray-700 animate-pulse"></div>
            </div>

            <section id="hotspot-dash-vivos" class="relative rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 overflow-hidden" aria-labelledby="hotspot-dash-vivos-title">
                @if($routerError)
                    <div id="hotspot-dash-stale" class="absolute inset-0 z-10 flex flex-col items-center justify-center gap-3 px-4" style="display:flex;background:rgba(17,24,39,0.8)" role="alert">
                        <p class="max-w-md text-center text-sm font-medium text-white">No pude leer las sesiones de {{ $selectedRouter->nombre }}.</p>
                        <details class="hotspot-dash-tech max-w-md text-center">
                            <summary class="text-sm text-gray-200 underline underline-offset-2">Detalle técnico</summary>
                            <p class="mt-2 text-sm text-gray-200 break-words">{{ $routerError }}</p>
                        </details>
                        <a href="{{ route('hotspot.dashboard', ['router_id' => $selectedRouter->router_id]) }}"
                            class="{{ $btnPrimary }}"
                            data-dash-refresh>
                            Reintentar
                        </a>
                    </div>
                @endif
                <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700 flex flex-wrap items-start justify-between gap-3">
                    <div class="min-w-0">
                        <h2 id="hotspot-dash-vivos-title" class="text-base font-semibold text-gray-900 dark:text-gray-100" aria-live="polite">
                            Sesiones vivas
                            @unless($routerError)
                                ({{ count($sesiones) }})
                            @endunless
                        </h2>
                        <p class="mt-0.5 text-sm text-gray-600 dark:text-gray-300">{{ $selectedRouter->nombre }} · {{ $selectedRouter->ip }}</p>
                        @if($consultadoEn)
                            <p id="hotspot-dash-pulse" class="mt-0.5 text-sm text-gray-500 dark:text-gray-400" aria-live="polite"
                                data-consultado="{{ $consultadoEn->toIso8601String() }}">ahora · se actualiza en 30 s</p>
                        @endif
                    </div>
                    <a href="{{ route('hotspot.dashboard', ['router_id' => $selectedRouter->router_id]) }}"
                        class="{{ $btnSecondary }}"
                        data-dash-refresh>
                        Actualizar
                    </a>
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
                                    <th scope="col" class="px-4 py-2 text-left font-medium text-gray-600 dark:text-gray-300">Usuario</th>
                                    <th scope="col" class="px-4 py-2 text-left font-medium text-gray-600 dark:text-gray-300">Cliente</th>
                                    <th scope="col" class="px-4 py-2 text-left font-medium text-gray-600 dark:text-gray-300">Perfil / cuota</th>
                                    <th scope="col" class="px-4 py-2 text-left font-medium text-gray-600 dark:text-gray-300">Tiempo en línea</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                @foreach($sesiones as $fila)
                                    <tr class="{{ $fila['origen'] === 'locker' ? '' : 'bg-amber-50/60 dark:bg-amber-950/20' }}">
                                        <td class="px-4 py-2">
                                            @if($fila['username'] !== '—' && $fila['origen'] === 'locker')
                                                <a href="{{ route('hotspot.index', ['buscar' => $fila['username']]) }}" class="font-mono {{ $linkUser }}">{{ $fila['username'] }}</a>
                                            @else
                                                <span class="font-mono text-gray-900 dark:text-gray-100">{{ $fila['username'] }}</span>
                                            @endif
                                            <span class="block font-mono text-xs text-gray-500 dark:text-gray-400">{{ $fila['ip'] }}</span>
                                            <span class="block font-mono text-xs text-gray-500 dark:text-gray-400">{{ $fila['mac'] }}</span>
                                        </td>
                                        <td class="px-4 py-2 text-gray-700 dark:text-gray-200 max-w-[12rem] truncate" title="{{ $fila['cliente'] }}">
                                            {{ $fila['cliente'] !== '' ? $fila['cliente'] : '—' }}
                                            @if($fila['origen'] === 'locker' && $fila['estado'] !== '')
                                                <span class="ml-1 inline-flex px-1.5 py-0.5 rounded text-xs font-medium {{ $estadoBadge[$fila['estado']] ?? $estadoBadge['X'] }}">{{ $estados[$fila['estado']] ?? $fila['estado'] }}</span>
                                            @elseif($fila['origen'] !== 'locker')
                                                <span class="ml-1 inline-flex px-1.5 py-0.5 rounded text-xs font-medium {{ $origenBadge[$fila['origen']] ?? $estadoBadge['X'] }}">{{ $origenLabel[$fila['origen']] ?? $fila['origen'] }}</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-2 text-gray-700 dark:text-gray-200">
                                            @include('hotspot._cuota-cell', [
                                                'perfil' => $fila['perfil'],
                                                'vista' => $fila['cuota'] ?? null,
                                                'centroPct' => true,
                                            ])
                                        </td>
                                        <td class="px-4 py-2 text-gray-600 dark:text-gray-300">{{ $fila['uptime'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>

            <section id="hotspot-dash-offline" class="rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 overflow-hidden" aria-labelledby="hotspot-dash-offline-title">
                <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700">
                    <h2 id="hotspot-dash-offline-title" class="text-base font-semibold text-gray-600 dark:text-gray-300">Con cuenta, sin sesión ({{ $offline->count() }})</h2>
                </div>
                @if($offline->isEmpty())
                    <p class="px-4 py-6 text-sm text-gray-600 dark:text-gray-300">
                        @if($mappedCount === 0)
                            Este router no tiene cuentas.
                        @else
                            Todas las cuentas de este router están en Sesiones vivas.
                        @endif
                    </p>
                @else
                    <div id="hotspot-dash-offline-scroll" class="overflow-x-auto max-h-[20rem] overflow-y-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                            <thead class="bg-gray-50 dark:bg-gray-900/40 sticky top-0">
                                <tr>
                                    <th scope="col" class="px-4 py-2 text-left font-medium text-gray-600 dark:text-gray-300">Usuario</th>
                                    <th scope="col" class="px-4 py-2 text-left font-medium text-gray-600 dark:text-gray-300">Cliente</th>
                                    <th scope="col" class="px-4 py-2 text-left font-medium text-gray-600 dark:text-gray-300">Perfil / cuota</th>
                                    <th scope="col" class="px-4 py-2 text-left font-medium text-gray-600 dark:text-gray-300">Estado</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                @foreach($offline as $sh)
                                    @php
                                        $codigo = $sh->servicio?->estado;
                                        $nombre = trim(($sh->cliente?->nombre ?? $sh->servicio?->cliente?->nombre ?? '').' '.($sh->cliente?->apellido ?? $sh->servicio?->cliente?->apellido ?? ''));
                                        $bytes = (int) ($consumo[$sh->username] ?? 0);
                                        $cuotaVista = \App\Services\RadiusHotspotSyncService::consumoVista($bytes, $sh->hotspotPerfil?->cuota_gb);
                                        $perfilNombre = $sh->hotspotPerfil?->nombreDistintoDeCuota() ?? '';
                                    @endphp
                                    <tr>
                                        <td class="px-4 py-2 font-mono">
                                            <a href="{{ route('hotspot.index', ['buscar' => $sh->username]) }}" class="{{ $linkUser }}">{{ $sh->username }}</a>
                                        </td>
                                        <td class="px-4 py-2 text-gray-700 dark:text-gray-200 max-w-[12rem] truncate" title="{{ $nombre }}">{{ $nombre !== '' ? $nombre : '—' }}</td>
                                        <td class="px-4 py-2 text-gray-700 dark:text-gray-200">
                                            @include('hotspot._cuota-cell', [
                                                'perfil' => $perfilNombre,
                                                'vista' => $cuotaVista,
                                                'centroPct' => true,
                                            ])
                                        </td>
                                        <td class="px-4 py-2">
                                            @if($codigo)
                                                <span class="inline-flex px-1.5 py-0.5 rounded text-xs font-medium {{ $estadoBadge[$codigo] ?? $estadoBadge['X'] }}">{{ $estados[$codigo] ?? $codigo }}</span>
                                            @else
                                                <span class="text-gray-500 dark:text-gray-400">Sin servicio</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>
        </div>
    @else
        <div class="rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 px-4 py-10 text-center">
            <p class="text-sm font-medium text-gray-900 dark:text-gray-100">Elegí un router.</p>
            <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">Vas a ver quién está conectado al hotspot y las cuentas de ese router que no tienen sesión.</p>
        </div>
    @endif
</div>
@endsection

@push('scripts')
<script>
(function () {
    var form = document.getElementById('hotspot-dash-router-form');
    var select = document.getElementById('hotspot-dash-router');
    var filter = document.getElementById('hotspot-dash-router-q');
    var watch = document.getElementById('hotspot-dash-watch');
    var skel = document.getElementById('hotspot-dash-skel');
    var vivos = document.getElementById('hotspot-dash-vivos');
    var pulse = document.getElementById('hotspot-dash-pulse');
    var sessionHost = document.getElementById('hotspot-dash-session-host');
    var loaded = select ? String(select.getAttribute('data-loaded') || '') : '';
    var leaving = false;
    var dead = false;
    var REFRESH_S = 30;
    var remain = REFRESH_S;
    var pingUrl = @json(route('sesion.ping', [], false));
    var btnPrimaryClass = @json($btnPrimary);

    function isDirty() {
        return !!(select && loaded !== '' && String(select.value || '') !== '' && String(select.value) !== loaded);
    }

    function isPaused() {
        if (leaving || dead || document.hidden || !loaded) return true;
        if (isDirty()) return true;
        if (select && document.activeElement === select) return true;
        if (filter && document.activeElement === filter) return true;
        return false;
    }

    function showSkel() {
        leaving = true;
        if (skel) skel.style.display = 'block';
        if (vivos) vivos.style.display = 'none';
        var offline = document.getElementById('hotspot-dash-offline');
        if (offline) offline.style.display = 'none';
        var stale = document.getElementById('hotspot-dash-stale');
        if (stale) stale.style.display = 'none';
    }

    function submitRouter() {
        if (!form || !select || !select.value) return;
        showSkel();
        if (typeof form.requestSubmit === 'function') form.requestSubmit();
        else form.submit();
    }

    function ageText() {
        if (!pulse) return 'ahora';
        var iso = pulse.getAttribute('data-consultado');
        if (!iso) return 'ahora';
        var then = Date.parse(iso);
        if (!then) return 'ahora';
        var sec = Math.max(0, Math.floor((Date.now() - then) / 1000));
        if (sec < 5) return 'ahora';
        if (sec < 60) return 'hace ' + sec + ' s';
        if (sec < 3600) return 'hace ' + Math.floor(sec / 60) + ' min';
        return 'hace ' + Math.floor(sec / 3600) + ' h';
    }

    function paintPulse() {
        if (!pulse) return;
        if (dead) {
            pulse.textContent = '';
            return;
        }
        if (isPaused()) {
            pulse.textContent = 'Actualización en pausa';
            return;
        }
        pulse.textContent = ageText() + ' · se actualiza en ' + remain + ' s';
    }

    function showSessionDead() {
        dead = true;
        leaving = false;
        if (sessionHost && !sessionHost.dataset.shown) {
            sessionHost.dataset.shown = '1';
            var box = document.createElement('div');
            box.className = 'mb-4 rounded-lg border border-red-200 dark:border-red-800 bg-red-50 dark:bg-red-900/20 px-4 py-3';
            box.setAttribute('role', 'alert');
            var p = document.createElement('p');
            p.className = 'text-sm font-medium text-red-800 dark:text-red-200';
            p.textContent = sessionHost.getAttribute('data-copy') || '';
            var a = document.createElement('a');
            a.href = sessionHost.getAttribute('data-entrar') || '';
            a.className = btnPrimaryClass + ' mt-3';
            a.textContent = 'Entrar';
            box.appendChild(p);
            box.appendChild(a);
            sessionHost.appendChild(box);
        }
        paintPulse();
    }

    function scrollWrap(id) {
        return document.getElementById(id);
    }

    function applyWatch(doc) {
        var newVivos = doc.getElementById('hotspot-dash-vivos');
        var newOff = doc.getElementById('hotspot-dash-offline');
        if (!newVivos || !newOff) return false;
        var yV = scrollWrap('hotspot-dash-vivos-scroll');
        var yO = scrollWrap('hotspot-dash-offline-scroll');
        var topV = yV ? yV.scrollTop : 0;
        var topO = yO ? yO.scrollTop : 0;
        var curV = document.getElementById('hotspot-dash-vivos');
        var curO = document.getElementById('hotspot-dash-offline');
        if (curV) curV.replaceWith(newVivos);
        if (curO) curO.replaceWith(newOff);
        vivos = document.getElementById('hotspot-dash-vivos');
        pulse = document.getElementById('hotspot-dash-pulse');
        var nv = scrollWrap('hotspot-dash-vivos-scroll');
        var no = scrollWrap('hotspot-dash-offline-scroll');
        if (nv) nv.scrollTop = topV;
        if (no) no.scrollTop = topO;
        remain = REFRESH_S;
        paintPulse();
        return true;
    }

    function tryRefresh() {
        if (leaving || dead || isPaused()) return;
        fetch(pingUrl, {
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            cache: 'no-store'
        }).then(function (r) {
            var json = (r.headers.get('content-type') || '').indexOf('json') !== -1;
            if (!r.ok || r.redirected || !json) {
                showSessionDead();
                return null;
            }
            return fetch(window.location.pathname + window.location.search, {
                credentials: 'same-origin',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                cache: 'no-store'
            });
        }).then(function (r) {
            if (!r) return null;
            if (!r.ok || r.redirected) {
                showSessionDead();
                return null;
            }
            return r.text();
        }).then(function (html) {
            if (!html) return;
            var doc = new DOMParser().parseFromString(html, 'text/html');
            if (!applyWatch(doc)) {
                showSessionDead();
            }
        }).catch(function () {
            showSessionDead();
        });
    }

    function filterRouters() {
        if (!select) return;
        var term = filter ? String(filter.value || '').trim().toLowerCase() : '';
        var groups = select.querySelectorAll('optgroup');
        groups.forEach(function (g) {
            var any = false;
            g.querySelectorAll('option').forEach(function (opt) {
                var hay = [
                    opt.getAttribute('data-nombre') || '',
                    opt.getAttribute('data-ip') || '',
                    opt.getAttribute('data-nodo') || '',
                    opt.textContent || ''
                ].join(' ').toLowerCase();
                var keep = opt.value === loaded || opt.selected;
                var show = term === '' || hay.indexOf(term) !== -1 || keep;
                opt.hidden = !show;
                if (show) any = true;
            });
            g.hidden = !any;
        });
    }

    if (filter) {
        filter.addEventListener('input', function () {
            filterRouters();
            paintPulse();
        });
        filter.addEventListener('focus', paintPulse);
        filter.addEventListener('blur', paintPulse);
        filter.addEventListener('keydown', function (e) {
            if (e.key !== 'Enter') return;
            e.preventDefault();
            if (isDirty()) submitRouter();
            else if (select) select.focus();
        });
    }
    if (select) {
        select.addEventListener('keydown', function (e) {
            if (e.key !== 'Enter') return;
            if (!isDirty()) return;
            e.preventDefault();
            submitRouter();
        });
        select.addEventListener('change', paintPulse);
        select.addEventListener('focus', paintPulse);
        select.addEventListener('blur', paintPulse);
    }
    if (form) form.addEventListener('submit', showSkel);
    if (watch) {
        watch.addEventListener('click', function (e) {
            var a = e.target.closest('[data-dash-refresh]');
            if (!a) return;
            showSkel();
        });
    }

    paintPulse();
    setInterval(paintPulse, 1000);
    setInterval(function () {
        if (isPaused()) {
            paintPulse();
            return;
        }
        remain -= 1;
        if (remain <= 0) {
            remain = REFRESH_S;
            tryRefresh();
            return;
        }
        paintPulse();
    }, 1000);
})();
</script>
@endpush
