@php
    $optica = $senal['optica'] ?? ['total' => 0, 'optima' => 0, 'baja' => 0, 'alta' => 0, 'sin' => 0, 'online' => 0];
    $antena = $senal['antena'] ?? ['total' => 0, 'buena' => 0, 'regular' => 0, 'debil' => 0];
    $distribucionOptica = $senal['distribucion_optica'] ?? [];
    $distribucionAntena = $senal['distribucion_antena'] ?? [];
    $lectura = $senal['lectura'] ?? null;
    $ultimaSync = $senal['ultima_sync'] ?? null;
    $totalOptica = max(0, (int) $optica['total']);
    $pct = function (int $n) use ($totalOptica): float {
        return $totalOptica > 0 ? round($n / $totalOptica * 100, 1) : 0;
    };
@endphp

<div class="space-y-6">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-lg font-semibold text-gray-900 dark:text-white">Señales y potencias</h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                Óptica en rango de −27 a −8 dBm. Antena débil por debajo de −75 dBm.
                @if($ultimaSync)
                    Última potencia de ONU {{ $ultimaSync }}.
                @endif
            </p>
        </div>
        @if(auth()->user()?->tienePermiso('sistema-red-monitoreo.ver') && Route::has('sistema.senal-lecturas.index'))
            <a href="{{ route('sistema.senal-lecturas.index') }}" class="text-sm font-medium text-violet-600 dark:text-violet-400 hover:underline">Informe de señal</a>
        @endif
    </div>

    <div class="grid grid-cols-2 xl:grid-cols-4 gap-3 sm:gap-4">
        <div class="rounded-2xl border border-gray-200/80 dark:border-white/10 bg-white dark:bg-[#12161f] p-4 sm:p-5 shadow-sm">
            <p class="text-[11px] font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">En rango</p>
            <p class="mt-2 text-2xl sm:text-3xl font-bold tabular-nums text-emerald-600 dark:text-emerald-400">{{ number_format($optica['optima']) }}</p>
            <p class="mt-2 text-[11px] text-gray-500 dark:text-gray-400">de {{ number_format($totalOptica) }} ONU · −27 a −8 dBm</p>
        </div>
        <div class="rounded-2xl border border-gray-200/80 dark:border-white/10 bg-white dark:bg-[#12161f] p-4 sm:p-5 shadow-sm">
            <p class="text-[11px] font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Potencia baja</p>
            <p class="mt-2 text-2xl sm:text-3xl font-bold tabular-nums text-rose-600 dark:text-rose-400">{{ number_format($optica['baja']) }}</p>
            <p class="mt-2 text-[11px] text-gray-500 dark:text-gray-400">Peor que −27 dBm</p>
        </div>
        <div class="rounded-2xl border border-gray-200/80 dark:border-white/10 bg-white dark:bg-[#12161f] p-4 sm:p-5 shadow-sm">
            <p class="text-[11px] font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Sin potencia</p>
            <p class="mt-2 text-2xl sm:text-3xl font-bold tabular-nums text-gray-900 dark:text-white">{{ number_format($optica['sin']) }}</p>
            <p class="mt-2 text-[11px] text-gray-500 dark:text-gray-400">{{ number_format($optica['online']) }} ONU en línea</p>
        </div>
        <div class="rounded-2xl border border-gray-200/80 dark:border-white/10 bg-white dark:bg-[#12161f] p-4 sm:p-5 shadow-sm">
            <p class="text-[11px] font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Antenas débiles</p>
            <p class="mt-2 text-2xl sm:text-3xl font-bold tabular-nums text-amber-600 dark:text-amber-400">{{ number_format($antena['debil']) }}</p>
            <p class="mt-2 text-[11px] text-gray-500 dark:text-gray-400">de {{ number_format($antena['total']) }} lecturas · bajo −75 dBm</p>
        </div>
    </div>

    @if($totalOptica > 0)
        <div class="rounded-2xl border border-gray-200/80 dark:border-white/10 bg-white dark:bg-[#12161f] p-4 sm:p-5 shadow-sm">
            <div class="flex h-2 overflow-hidden rounded-full bg-gray-100 dark:bg-white/5">
                <div class="bg-emerald-500" style="width: {{ $pct((int) $optica['optima']) }}%"></div>
                <div class="bg-rose-500" style="width: {{ $pct((int) $optica['baja']) }}%"></div>
                <div class="bg-amber-400" style="width: {{ $pct((int) $optica['alta']) }}%"></div>
                <div class="bg-gray-400 dark:bg-gray-600" style="width: {{ $pct((int) $optica['sin']) }}%"></div>
            </div>
            <div class="mt-3 flex flex-wrap gap-x-4 gap-y-1 text-[11px] text-gray-500 dark:text-gray-400">
                <span>En rango {{ number_format($optica['optima']) }}</span>
                <span>Baja {{ number_format($optica['baja']) }}</span>
                <span>Alta {{ number_format($optica['alta']) }}</span>
                <span>Sin lectura {{ number_format($optica['sin']) }}</span>
                <span>Antena buena {{ number_format($antena['buena']) }} · regular {{ number_format($antena['regular']) }}</span>
            </div>
        </div>
    @endif

    @php
        $gruposCortes = [];
        foreach ($atencion ?? [] as $filaCorte) {
            $gruposCortes[(int) $filaCorte->h24][] = [
                'nombre' => (string) $filaCorte->nombre,
                'nivel' => (string) $filaCorte->nivel,
            ];
        }
        krsort($gruposCortes);
        $pesoCorte = ['critico' => 0, 'alto' => 1, 'observar' => 2];
        $binsCortes = [];
        foreach ($gruposCortes as $cortes => $filasCorte) {
            usort($filasCorte, fn (array $a, array $b) => strnatcasecmp($a['nombre'], $b['nombre']));
            $nivelCorte = 'observar';
            foreach ($filasCorte as $filaCorte) {
                if (($pesoCorte[$filaCorte['nivel']] ?? 9) < ($pesoCorte[$nivelCorte] ?? 9)) {
                    $nivelCorte = $filaCorte['nivel'];
                }
            }
            $binsCortes[] = [
                'dbm' => (int) $cortes,
                'cantidad' => count($filasCorte),
                'clientes' => array_column($filasCorte, 'nombre'),
                'nivel' => $nivelCorte,
            ];
        }
    @endphp
    @include('home._distribucion', [
        'distTitulo' => 'Desconexión continua',
        'distSubtitulo' => 'Abajo, cortes en 24 h. En altura, clientes con esa cantidad.',
        'distVacio' => 'Ninguna línea tuvo desconexión continua en las últimas 24 h.',
        'distBins' => $binsCortes,
        'distModo' => 'cortes',
        'distUnidad' => 'cortes',
        'distUnidadUno' => 'corte',
    ])

    <div class="space-y-3">
        <div class="flex flex-wrap gap-2" role="tablist" aria-label="Gráficos de señal">
            <button type="button" role="tab" data-senal-tab="optica" aria-selected="true" class="inline-flex items-center rounded-full bg-violet-600 px-4 py-1.5 text-sm font-medium text-white shadow-sm focus:outline-none focus-visible:ring-2 focus-visible:ring-violet-500">Potencia óptica</button>
            <button type="button" role="tab" data-senal-tab="antena" aria-selected="false" class="inline-flex items-center rounded-full border border-gray-200/80 bg-white px-4 py-1.5 text-sm font-medium text-gray-600 focus:outline-none focus-visible:ring-2 focus-visible:ring-violet-500 dark:border-white/10 dark:bg-[#12161f] dark:text-gray-300">Señal de antena</button>
        </div>
        <div data-senal-panel="optica">
            @include('home._distribucion', [
                'distTitulo' => 'Potencia óptica',
                'distSubtitulo' => 'Abajo, dBm. En altura, clientes en esa potencia.',
                'distVacio' => 'No hay ONU con potencia registrada.',
                'distBins' => $distribucionOptica,
                'distModo' => 'optica',
            ])
        </div>
        <div data-senal-panel="antena" class="hidden">
            @include('home._distribucion', [
                'distTitulo' => 'Señal de antena',
                'distSubtitulo' => 'Abajo, dBm. En altura, clientes en esa señal. Buena desde −65.',
                'distVacio' => 'Todavía no hay lecturas de antena.',
                'distBins' => $distribucionAntena,
                'distModo' => 'antena',
            ])
        </div>
    </div>
    <div id="dist-tip" class="pointer-events-none fixed z-50 hidden max-h-52 max-w-[15rem] overflow-hidden rounded-md border border-gray-200 bg-white px-2.5 py-2 text-[10px] leading-snug text-gray-700 shadow-lg dark:border-white/10 dark:bg-[#12161f] dark:text-gray-200" role="tooltip"></div>
    <script>
        (function () {
            var tip = document.getElementById('dist-tip');
            if (!tip) return;
            var tope = 24;
            var activo = null;

            function esc(texto) {
                return String(texto).replace(/[&<>"']/g, function (c) {
                    return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c];
                });
            }

            function binsDe(root) {
                if (root._bins) return root._bins;
                try { root._bins = JSON.parse(root.getAttribute('data-bins') || '[]'); }
                catch (e) { root._bins = []; }
                return root._bins;
            }

            function pintar(root, indice) {
                var bin = binsDe(root)[indice];
                if (!bin) return;
                var nombres = bin.clientes || [];
                var vistos = nombres.slice(0, tope);
                var resto = nombres.length - vistos.length;
                var unidad = Number(bin.dbm) === 1
                    ? (root.getAttribute('data-unidad-uno') || 'dBm')
                    : (root.getAttribute('data-unidad') || 'dBm');
                var html = '<p class="font-semibold">' + esc(bin.dbm) + ' ' + esc(unidad) + ' · ' + esc(bin.cantidad) + (bin.cantidad === 1 ? ' cliente' : ' clientes') + '</p>';
                if (vistos.length) {
                    html += '<ul class="mt-1 space-y-0.5">';
                    vistos.forEach(function (nombre) {
                        html += '<li class="truncate">' + esc(nombre) + '</li>';
                    });
                    if (resto > 0) html += '<li class="text-gray-400">y ' + resto + ' más</li>';
                    html += '</ul>';
                }
                tip.innerHTML = html;
            }

            function mover(ev) {
                var margen = 14;
                tip.classList.remove('hidden');
                var ancho = tip.offsetWidth;
                var alto = tip.offsetHeight;
                var x = ev.clientX + margen;
                var y = ev.clientY + margen;
                if (x + ancho > window.innerWidth - 8) x = ev.clientX - ancho - margen;
                if (y + alto > window.innerHeight - 8) y = Math.max(8, ev.clientY - alto - margen);
                tip.style.left = x + 'px';
                tip.style.top = y + 'px';
            }

            var baseTab = 'inline-flex items-center rounded-full px-4 py-1.5 text-sm font-medium focus:outline-none focus-visible:ring-2 focus-visible:ring-violet-500 ';
            var tabOn = baseTab + 'bg-violet-600 text-white shadow-sm';
            var tabOff = baseTab + 'border border-gray-200/80 dark:border-white/10 bg-white dark:bg-[#12161f] text-gray-600 dark:text-gray-300';

            function mostrarSenal(clave) {
                document.querySelectorAll('[data-senal-tab]').forEach(function (boton) {
                    var encendido = boton.getAttribute('data-senal-tab') === clave;
                    boton.setAttribute('aria-selected', encendido ? 'true' : 'false');
                    boton.className = encendido ? tabOn : tabOff;
                });
                document.querySelectorAll('[data-senal-panel]').forEach(function (panel) {
                    panel.classList.toggle('hidden', panel.getAttribute('data-senal-panel') !== clave);
                });
                tip.classList.add('hidden');
                activo = null;
            }

            document.querySelectorAll('[data-senal-tab]').forEach(function (boton) {
                boton.addEventListener('click', function () {
                    mostrarSenal(boton.getAttribute('data-senal-tab'));
                });
            });

            document.querySelectorAll('[data-dist]').forEach(function (root) {
                root.addEventListener('mouseover', function (ev) {
                    var fila = ev.target.closest('[data-dist-bin]');
                    if (!fila || !root.contains(fila)) return;
                    activo = root;
                    pintar(root, fila.getAttribute('data-dist-bin'));
                    mover(ev);
                });
                root.addEventListener('mousemove', function (ev) {
                    if (activo !== root || tip.classList.contains('hidden')) return;
                    if (!ev.target.closest('[data-dist-bin]')) return;
                    mover(ev);
                });
                root.addEventListener('mouseleave', function () {
                    if (activo === root) activo = null;
                    tip.classList.add('hidden');
                });
            });
        })();
    </script>

    @if($lectura)
        <div class="rounded-2xl border border-gray-200/80 dark:border-white/10 bg-white dark:bg-[#12161f] p-4 sm:p-5 shadow-sm flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div>
                <p class="text-sm font-semibold text-gray-900 dark:text-white">Lectura diaria</p>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                    {{ $lectura->inicio_at?->format('d/m/Y H:i') ?? 'Sin fecha' }}
                    · fibra {{ number_format((int) $lectura->fibra_leidas) }} leídas
                    @if((int) $lectura->fibra_fallidas > 0)
                        · {{ number_format((int) $lectura->fibra_fallidas) }} sin lectura
                    @endif
                    · antena {{ number_format((int) $lectura->antena_leidas) }} leídas
                    @if((int) $lectura->antena_fallidas > 0)
                        · {{ number_format((int) $lectura->antena_fallidas) }} sin lectura
                    @endif
                </p>
            </div>
            @if(auth()->user()?->tienePermiso('sistema-red-monitoreo.ver') && Route::has('sistema.senal-lecturas.asociacion'))
                <a href="{{ route('sistema.senal-lecturas.asociacion', $lectura) }}" class="text-sm font-medium text-violet-600 dark:text-violet-400 hover:underline shrink-0">Ver asociación</a>
            @endif
        </div>
    @endif
</div>
