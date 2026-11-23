@php
    $optica = $senal['optica'] ?? ['total' => 0, 'optima' => 0, 'baja' => 0, 'alta' => 0, 'sin' => 0, 'online' => 0];
    $antena = $senal['antena'] ?? ['total' => 0, 'buena' => 0, 'regular' => 0, 'debil' => 0];
    $distribucionOptica = $senal['distribucion_optica'] ?? [];
    $distribucionAntena = $senal['distribucion_antena'] ?? [];
    $lectura = $senal['lectura'] ?? null;
    $ultimaSync = $senal['ultima_sync'] ?? null;
    $totalOptica = max(0, (int) $optica['total']);
    $totalAntena = max(0, (int) $antena['total']);
    $pct = function (int $n) use ($totalOptica): float {
        return $totalOptica > 0 ? round($n / $totalOptica * 100, 1) : 0;
    };
    $pctAntena = function (int $n) use ($totalAntena): float {
        return $totalAntena > 0 ? round($n / $totalAntena * 100, 1) : 0;
    };
    $infra = $infra ?? [];
    $tvRenovar = $tvRenovar ?? collect();
    $tvRenovarDias = (int) ($tvRenovarDias ?? 1);
    $tvVencidas = $tvRenovar->filter(fn ($cuenta) => $cuenta->diasParaVencimiento() < 0)->count();
    $puedeVerInformeSenal = auth()->user()?->tienePermiso('sistema-red-monitoreo.ver') ?? false;

    $tarjeta = 'rounded-2xl border border-gray-200/80 dark:border-white/10 bg-white dark:bg-[#12161f] shadow-sm';
    $iconos = [
        'olt' => 'M5.25 14.25h13.5m-13.5 0a3 3 0 01-3-3m3 3a3 3 0 100 6h13.5a3 3 0 100-6m-16.5-3a3 3 0 013-3h13.5a3 3 0 013 3m-19.5 0a4.5 4.5 0 01.9-2.7L5.737 5.1a3.375 3.375 0 012.7-1.35h7.126c1.062 0 2.062.5 2.7 1.35l2.587 3.45a4.5 4.5 0 01.9 2.7m0 0a3 3 0 01-3 3m0 3h.008v.008h-.008v-.008zm0-6h.008v.008h-.008v-.008zm-3 6h.008v.008h-.008v-.008zm0-6h.008v.008h-.008v-.008z',
        'red' => 'M8.288 15.038a5.25 5.25 0 017.424 0M5.106 11.856c3.807-3.808 9.98-3.808 13.788 0M1.924 8.674c5.565-5.565 14.587-5.565 20.152 0M12.53 18.22l-.53.53-.53-.53a.75.75 0 011.06 0z',
        'nodo' => 'M7.5 21L3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5',
        'reloj' => 'M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z',
        'tv' => 'M6 20.25h12m-7.5-3v3m3-3v3m-10.125-3h17.25c.621 0 1.125-.504 1.125-1.125V4.875c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125z',
        'senal' => 'M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z',
        'check' => 'M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
        'abajo' => 'M2.25 6L9 12.75l4.306-4.307a11.95 11.95 0 015.814 5.519l2.74 1.22m0 0l-5.94 2.28m5.94-2.28l-2.28-5.941',
        'nada' => 'M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636',
        'alerta' => 'M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z',
    ];
    $icono = function (string $clave, string $clase = 'h-5 w-5') use ($iconos): string {
        return '<svg class="'.$clase.'" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="'.($iconos[$clave] ?? '').'"/></svg>';
    };
    $iconoInfra = function (string $titulo): string {
        $t = mb_strtolower($titulo);

        return str_contains($t, 'olt') ? 'olt' : (str_contains($t, 'nodo') ? 'nodo' : 'red');
    };
@endphp

<div class="space-y-6">
    {{-- Estado de la red --}}
    @if($infra !== [] || $lectura)
        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-3 sm:gap-4">
            @foreach($infra as $grupo)
                @php
                    $caidosInfra = $grupo['caidos'] ?? [];
                    $hayCaidos = $caidosInfra !== [];
                    $nombresCaidos = array_slice($caidosInfra, 0, 3);
                    $restoCaidos = count($caidosInfra) - count($nombresCaidos);
                    $etiquetaCaidos = implode(', ', $nombresCaidos).($restoCaidos > 0 ? ' y '.$restoCaidos.' más' : '');
                    $tagInfra = ! empty($grupo['href']) ? 'a' : 'div';
                    $totalInfra = max(0, (int) $grupo['total']);
                    $pctInfra = $totalInfra > 0 ? round((int) $grupo['en_linea'] / $totalInfra * 100, 1) : 0;
                    $anilloInfra = 2 * M_PI * 42;
                    $offsetInfra = $anilloInfra * (1 - ($pctInfra / 100));
                    $gradInfra = 'infraGrad'.$loop->index;
                @endphp
                <{{ $tagInfra }}
                    @if(! empty($grupo['href'])) href="{{ $grupo['href'] }}" @endif
                    class="group relative overflow-hidden {{ $tarjeta }} p-4 sm:p-5 {{ ! empty($grupo['href']) ? 'hover:border-violet-400/60 dark:hover:border-violet-500/40 transition-colors' : '' }}"
                >
                    <span class="absolute inset-y-0 left-0 w-1 {{ $hayCaidos ? 'bg-rose-500' : 'bg-emerald-500' }}" aria-hidden="true"></span>
                    <div class="flex items-center justify-between gap-3">
                        <div class="min-w-0">
                            <div class="flex items-center gap-2">
                                <span class="inline-flex h-8 w-8 items-center justify-center rounded-lg {{ $hayCaidos ? 'bg-rose-500/10 text-rose-600 dark:text-rose-400' : 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400' }}">{!! $icono($iconoInfra((string) $grupo['titulo']), 'h-4 w-4') !!}</span>
                                <p class="text-[11px] font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">{{ $grupo['titulo'] }}</p>
                            </div>
                            <p class="mt-3 flex items-baseline gap-1.5">
                                <span class="text-2xl sm:text-3xl font-bold tabular-nums {{ $hayCaidos ? 'text-rose-600 dark:text-rose-400' : 'text-gray-900 dark:text-white' }}">{{ number_format((int) $grupo['en_linea']) }}</span>
                                <span class="text-sm font-medium tabular-nums text-gray-400 dark:text-gray-500">/ {{ number_format($totalInfra) }}</span>
                            </p>
                            @if($hayCaidos)
                                <p class="mt-1 text-[11px] leading-snug text-rose-600 dark:text-rose-400">{{ count($caidosInfra) === 1 ? 'Caído' : 'Caídos' }}: {{ $etiquetaCaidos }}</p>
                            @else
                                <p class="mt-1 inline-flex items-center gap-1.5 text-[11px] text-emerald-600 dark:text-emerald-400">
                                    <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                                    Todos en línea
                                </p>
                            @endif
                        </div>
                        <div class="relative h-16 w-16 sm:h-20 sm:w-20 shrink-0">
                            <svg class="h-full w-full -rotate-90" viewBox="0 0 100 100" aria-hidden="true">
                                <circle cx="50" cy="50" r="42" fill="none" stroke="currentColor" stroke-width="8" class="text-gray-100 dark:text-white/5"/>
                                <circle cx="50" cy="50" r="42" fill="none" stroke="url(#{{ $gradInfra }})" stroke-width="8" stroke-linecap="round"
                                    stroke-dasharray="{{ $anilloInfra }}" stroke-dashoffset="{{ $offsetInfra }}"/>
                                <defs>
                                    <linearGradient id="{{ $gradInfra }}" x1="0%" y1="0%" x2="100%" y2="0%">
                                        @if($hayCaidos)
                                            <stop offset="0%" stop-color="#f43f5e"/>
                                            <stop offset="100%" stop-color="#fb7185"/>
                                        @else
                                            <stop offset="0%" stop-color="#10b981"/>
                                            <stop offset="100%" stop-color="#2dd4bf"/>
                                        @endif
                                    </linearGradient>
                                </defs>
                            </svg>
                            <div class="absolute inset-0 flex items-center justify-center">
                                <span class="text-xs sm:text-sm font-bold tabular-nums text-gray-900 dark:text-white">{{ number_format($pctInfra, $pctInfra == 100 ? 0 : 1) }}%</span>
                            </div>
                        </div>
                    </div>
                </{{ $tagInfra }}>
            @endforeach

            @if($lectura)
                @php
                    $fallidasLectura = (int) $lectura->fibra_fallidas + (int) $lectura->antena_fallidas;
                @endphp
                <div class="relative overflow-hidden {{ $tarjeta }} p-4 sm:p-5 flex flex-col">
                    <span class="absolute inset-y-0 left-0 w-1 {{ $fallidasLectura > 0 ? 'bg-amber-400' : 'bg-violet-500' }}" aria-hidden="true"></span>
                    <div class="flex items-center justify-between gap-2">
                        <div class="flex items-center gap-2">
                            <span class="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-violet-500/10 text-violet-600 dark:text-violet-400">{!! $icono('reloj', 'h-4 w-4') !!}</span>
                            <p class="text-[11px] font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Lectura diaria</p>
                        </div>
                        @if($puedeVerInformeSenal && Route::has('sistema.senal-lecturas.asociacion'))
                            <a href="{{ route('sistema.senal-lecturas.asociacion', $lectura) }}" class="text-xs font-medium text-violet-600 dark:text-violet-400 hover:underline shrink-0">Asociación</a>
                        @endif
                    </div>
                    <p class="mt-3 text-base font-semibold tabular-nums text-gray-900 dark:text-white">{{ $lectura->inicio_at?->format('d/m/Y H:i') ?? 'Sin fecha' }}</p>
                    <dl class="mt-2 grid grid-cols-2 gap-2 text-[11px]">
                        <div class="rounded-lg bg-gray-50 dark:bg-white/5 px-2.5 py-1.5">
                            <dt class="text-gray-500 dark:text-gray-400">Fibra</dt>
                            <dd class="font-semibold tabular-nums text-gray-900 dark:text-white">
                                {{ number_format((int) $lectura->fibra_leidas) }}
                                @if((int) $lectura->fibra_fallidas > 0)
                                    <span class="font-medium text-amber-600 dark:text-amber-400" title="Sin lectura">· {{ number_format((int) $lectura->fibra_fallidas) }} sin leer</span>
                                @endif
                            </dd>
                        </div>
                        <div class="rounded-lg bg-gray-50 dark:bg-white/5 px-2.5 py-1.5">
                            <dt class="text-gray-500 dark:text-gray-400">Antena</dt>
                            <dd class="font-semibold tabular-nums text-gray-900 dark:text-white">
                                {{ number_format((int) $lectura->antena_leidas) }}
                                @if((int) $lectura->antena_fallidas > 0)
                                    <span class="font-medium text-amber-600 dark:text-amber-400" title="Sin lectura">· {{ number_format((int) $lectura->antena_fallidas) }} sin leer</span>
                                @endif
                            </dd>
                        </div>
                    </dl>
                </div>
            @endif
        </div>
    @endif

    {{-- Resumen de señal --}}
    <section class="{{ $tarjeta }} overflow-hidden">
        <div class="flex flex-wrap items-start justify-between gap-3 px-4 sm:px-5 py-4 border-b border-gray-100 dark:border-white/5">
            <div class="flex items-start gap-3 min-w-0">
                <span class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-violet-500/10 text-violet-600 dark:text-violet-400">{!! $icono('senal') !!}</span>
                <div class="min-w-0">
                    <h1 class="text-base font-semibold text-gray-900 dark:text-white">Señales y potencias</h1>
                    <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                        Óptica en rango de −27 a −8 dBm. Antena débil por debajo de −75 dBm.
                        @if($ultimaSync)
                            <span class="whitespace-nowrap">Última potencia de ONU {{ $ultimaSync }}.</span>
                        @endif
                    </p>
                </div>
            </div>
            @if($puedeVerInformeSenal && Route::has('sistema.senal-lecturas.index'))
                <a href="{{ route('sistema.senal-lecturas.index') }}" class="inline-flex items-center gap-1 rounded-lg border border-gray-200 dark:border-white/10 px-3 py-1.5 text-xs font-medium text-gray-700 dark:text-gray-200 hover:border-violet-400/60 hover:text-violet-600 dark:hover:text-violet-400 transition-colors">
                    Informe de señal
                    <span aria-hidden="true">→</span>
                </a>
            @endif
        </div>

        @php
            $kpisSenal = [
                ['etiqueta' => 'En rango', 'valor' => (int) $optica['optima'], 'pct' => $pct((int) $optica['optima']), 'nota' => 'de '.number_format($totalOptica).' ONU', 'icono' => 'check', 'color' => 'emerald'],
                ['etiqueta' => 'Potencia baja', 'valor' => (int) $optica['baja'], 'pct' => $pct((int) $optica['baja']), 'nota' => 'Peor que −27 dBm', 'icono' => 'abajo', 'color' => 'rose'],
                ['etiqueta' => 'Sin potencia', 'valor' => (int) $optica['sin'], 'pct' => $pct((int) $optica['sin']), 'nota' => number_format((int) $optica['online']).' ONU en línea', 'icono' => 'nada', 'color' => 'gray'],
                ['etiqueta' => 'Antenas débiles', 'valor' => (int) $antena['debil'], 'pct' => $pctAntena((int) $antena['debil']), 'nota' => 'de '.number_format($totalAntena).' lecturas', 'icono' => 'alerta', 'color' => 'amber'],
            ];
            $colorKpi = [
                'emerald' => ['chip' => 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400', 'valor' => 'text-emerald-600 dark:text-emerald-400', 'barra' => 'bg-emerald-500'],
                'rose' => ['chip' => 'bg-rose-500/10 text-rose-600 dark:text-rose-400', 'valor' => 'text-rose-600 dark:text-rose-400', 'barra' => 'bg-rose-500'],
                'gray' => ['chip' => 'bg-gray-500/10 text-gray-600 dark:text-gray-300', 'valor' => 'text-gray-900 dark:text-white', 'barra' => 'bg-gray-400 dark:bg-gray-500'],
                'amber' => ['chip' => 'bg-amber-500/10 text-amber-600 dark:text-amber-400', 'valor' => 'text-amber-600 dark:text-amber-400', 'barra' => 'bg-amber-400'],
            ];
        @endphp
        <div class="grid grid-cols-2 lg:grid-cols-4 divide-gray-100 dark:divide-white/5 lg:divide-x">
            @foreach($kpisSenal as $kpi)
                @php $c = $colorKpi[$kpi['color']]; @endphp
                <div class="p-4 sm:p-5 {{ $loop->index < 2 ? 'border-b lg:border-b-0 border-gray-100 dark:border-white/5' : '' }} {{ $loop->odd ? 'border-r lg:border-r-0 border-gray-100 dark:border-white/5' : '' }}">
                    <div class="flex items-center justify-between gap-2">
                        <p class="text-[11px] font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">{{ $kpi['etiqueta'] }}</p>
                        <span class="inline-flex h-7 w-7 items-center justify-center rounded-lg {{ $c['chip'] }}">{!! $icono($kpi['icono'], 'h-4 w-4') !!}</span>
                    </div>
                    <p class="mt-2 flex items-baseline gap-2">
                        <span class="text-2xl sm:text-3xl font-bold tabular-nums {{ $c['valor'] }}">{{ number_format($kpi['valor']) }}</span>
                        <span class="text-xs font-medium tabular-nums text-gray-400 dark:text-gray-500">{{ number_format($kpi['pct'], 1) }}%</span>
                    </p>
                    <div class="mt-2 h-1 overflow-hidden rounded-full bg-gray-100 dark:bg-white/5">
                        <div class="h-full rounded-full {{ $c['barra'] }}" style="width: {{ min(100, $kpi['pct']) }}%"></div>
                    </div>
                    <p class="mt-2 text-[11px] text-gray-500 dark:text-gray-400">{{ $kpi['nota'] }}</p>
                </div>
            @endforeach
        </div>

        @if($totalOptica > 0)
            @php
                $tramosOptica = [
                    ['etiqueta' => 'En rango', 'n' => (int) $optica['optima'], 'color' => 'bg-emerald-500'],
                    ['etiqueta' => 'Baja', 'n' => (int) $optica['baja'], 'color' => 'bg-rose-500'],
                    ['etiqueta' => 'Alta', 'n' => (int) $optica['alta'], 'color' => 'bg-amber-400'],
                    ['etiqueta' => 'Sin lectura', 'n' => (int) $optica['sin'], 'color' => 'bg-gray-400 dark:bg-gray-600'],
                ];
            @endphp
            <div class="px-4 sm:px-5 py-4 border-t border-gray-100 dark:border-white/5 bg-gray-50/60 dark:bg-[#12161f]">
                <div class="flex items-center justify-between gap-3 text-[11px] font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                    <span>Distribución óptica</span>
                    <span class="normal-case tracking-normal font-medium tabular-nums">{{ number_format($totalOptica) }} ONU</span>
                </div>
                <div class="mt-2 flex h-2.5 gap-0.5 overflow-hidden rounded-full bg-gray-100 dark:bg-white/5">
                    @foreach($tramosOptica as $tramo)
                        @if($tramo['n'] > 0)
                            <div class="{{ $tramo['color'] }}" style="width: {{ $pct($tramo['n']) }}%" title="{{ $tramo['etiqueta'] }}: {{ number_format($tramo['n']) }}"></div>
                        @endif
                    @endforeach
                </div>
                <div class="mt-3 flex flex-wrap gap-x-5 gap-y-1.5 text-[11px] text-gray-600 dark:text-gray-300">
                    @foreach($tramosOptica as $tramo)
                        <span class="inline-flex items-center gap-1.5">
                            <span class="h-2 w-2 rounded-full {{ $tramo['color'] }}"></span>
                            {{ $tramo['etiqueta'] }}
                            <span class="font-semibold tabular-nums text-gray-900 dark:text-white">{{ number_format($tramo['n']) }}</span>
                        </span>
                    @endforeach
                    <span class="inline-flex items-center gap-1.5 sm:ml-auto text-gray-500 dark:text-gray-400">
                        Antena: buena <span class="font-semibold tabular-nums text-gray-900 dark:text-white">{{ number_format((int) $antena['buena']) }}</span>
                        · regular <span class="font-semibold tabular-nums text-gray-900 dark:text-white">{{ number_format((int) $antena['regular']) }}</span>
                    </span>
                </div>
            </div>
        @endif
    </section>

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
        $leyendaCortes = '<div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-[11px] text-gray-500 dark:text-gray-400">'
            .'<span class="inline-flex items-center gap-1"><span class="h-2 w-2 rounded-full bg-rose-500"></span>Crítico</span>'
            .'<span class="inline-flex items-center gap-1"><span class="h-2 w-2 rounded-full bg-amber-400"></span>Alto</span>'
            .'<span class="inline-flex items-center gap-1"><span class="h-2 w-2 rounded-full bg-yellow-400"></span>Observar</span>'
            .'</div>';
    @endphp

    <div class="grid grid-cols-1 xl:grid-cols-5 gap-6 items-start">
        <div class="xl:col-span-3 min-w-0">
            @include('home._distribucion', [
                'distTitulo' => 'Desconexión continua',
                'distSubtitulo' => 'Abajo, cortes en 24 h. En altura, clientes con esa cantidad.',
                'distVacio' => 'Ninguna línea tuvo desconexión continua en las últimas 24 h.',
                'distBins' => $binsCortes,
                'distModo' => 'cortes',
                'distUnidad' => 'cortes',
                'distUnidadUno' => 'corte',
                'distAccion' => $binsCortes !== [] ? $leyendaCortes : '',
            ])
        </div>

        {{-- Cuentas de TV --}}
        <section class="xl:col-span-2 min-w-0 {{ $tarjeta }} overflow-hidden">
            <div class="flex flex-wrap items-start justify-between gap-3 px-4 sm:px-5 py-4 border-b border-gray-100 dark:border-white/5">
                <div class="flex items-start gap-3 min-w-0">
                    <span class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-xl {{ $tvVencidas > 0 ? 'bg-rose-500/10 text-rose-600 dark:text-rose-400' : 'bg-sky-500/10 text-sky-600 dark:text-sky-400' }}">{!! $icono('tv') !!}</span>
                    <div class="min-w-0">
                        <h2 class="flex items-center gap-2 text-sm font-semibold text-gray-900 dark:text-white">
                            Cuentas de TV para renovar
                            @if($tvRenovar->isNotEmpty())
                                <span class="inline-flex items-center rounded-full bg-gray-100 dark:bg-white/10 px-2 py-0.5 text-[11px] font-semibold tabular-nums text-gray-700 dark:text-gray-200">{{ $tvRenovar->count() }}</span>
                            @endif
                        </h2>
                        <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                            @if($tvRenovarDias === 2)
                                Hoy es sábado: vencidas y las que vencen hoy, mañana o en 2 días.
                            @else
                                Vencidas y las que vencen hoy o mañana.
                            @endif
                        </p>
                    </div>
                </div>
                @if(auth()->user()?->tienePermiso('tv.ver') && Route::has('tv-cuentas.index'))
                    <a href="{{ route('tv-cuentas.index') }}" class="text-xs font-medium text-violet-600 dark:text-violet-400 hover:underline">Ver cuentas</a>
                @endif
            </div>
            @if($tvRenovar->isEmpty())
                <div class="flex flex-col items-center justify-center gap-2 px-4 py-10 text-center">
                    <span class="inline-flex h-10 w-10 items-center justify-center rounded-full bg-emerald-500/10 text-emerald-600 dark:text-emerald-400">{!! $icono('check') !!}</span>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Ninguna cuenta requiere renovación en esta ventana.</p>
                </div>
            @else
                <ul class="max-h-[19rem] overflow-y-auto divide-y divide-gray-100 dark:divide-white/5">
                    @foreach($tvRenovar as $cuentaTv)
                        @php
                            $diasTv = $cuentaTv->diasParaVencimiento();
                            $cuandoTv = $diasTv < 0
                                ? 'Vencido hace '.abs($diasTv).' día'.(abs($diasTv) === 1 ? '' : 's')
                                : ($diasTv === 0 ? 'Vence hoy' : ($diasTv === 1 ? 'Vence mañana' : 'Vence en '.$diasTv.' días'));
                            $tituloTv = trim((string) ($cuentaTv->nombre ?: $cuentaTv->usuario_app ?: 'Cuenta TV'));
                            $hrefTv = auth()->user()?->tienePermiso('tv.editar') && Route::has('tv-cuentas.edit')
                                ? route('tv-cuentas.edit', $cuentaTv)
                                : null;
                            $inicialTv = mb_strtoupper(mb_substr($tituloTv, 0, 1));
                            $estiloTv = $diasTv < 0
                                ? ['avatar' => 'bg-rose-500/10 text-rose-600 dark:text-rose-400', 'badge' => 'bg-rose-500/10 text-rose-700 dark:text-rose-300 ring-1 ring-inset ring-rose-500/20', 'punto' => 'bg-rose-500']
                                : ($diasTv === 0
                                    ? ['avatar' => 'bg-amber-500/10 text-amber-600 dark:text-amber-400', 'badge' => 'bg-amber-500/10 text-amber-700 dark:text-amber-300 ring-1 ring-inset ring-amber-500/20', 'punto' => 'bg-amber-500']
                                    : ['avatar' => 'bg-sky-500/10 text-sky-600 dark:text-sky-400', 'badge' => 'bg-sky-500/10 text-sky-700 dark:text-sky-300 ring-1 ring-inset ring-sky-500/20', 'punto' => 'bg-sky-500']);
                        @endphp
                        <li class="flex items-center gap-3 px-4 sm:px-5 py-3 {{ $hrefTv ? 'hover:bg-gray-50 dark:hover:bg-white/[0.03] transition-colors' : '' }}">
                            <span class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-sm font-semibold {{ $estiloTv['avatar'] }}">{{ $inicialTv }}</span>
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-medium text-gray-900 dark:text-white truncate">
                                    @if($hrefTv)
                                        <a href="{{ $hrefTv }}" class="hover:underline">{{ $tituloTv }}</a>
                                    @else
                                        {{ $tituloTv }}
                                    @endif
                                </p>
                                <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400 truncate">
                                    {{ \App\Models\TvCuenta::aplicaciones()[$cuentaTv->aplicacion] ?? $cuentaTv->aplicacion }}
                                    @if(filled($cuentaTv->usuario_app))
                                        · {{ $cuentaTv->usuario_app }}
                                    @endif
                                    · {{ $cuentaTv->fechaVencimientoReferencia()->format('d/m/Y') }}
                                </p>
                            </div>
                            <span class="inline-flex shrink-0 items-center gap-1.5 rounded-full px-2.5 py-0.5 text-[11px] font-semibold whitespace-nowrap {{ $estiloTv['badge'] }}">
                                <span class="h-1.5 w-1.5 rounded-full {{ $estiloTv['punto'] }}"></span>
                                {{ $cuandoTv }}
                            </span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    </div>

    {{-- Histogramas de señal --}}
    @php
        $tabsSenal = function (string $activa): string {
            $base = 'inline-flex items-center rounded-md px-3 py-1 text-xs font-medium transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-violet-500 ';
            $on = $base.'bg-white dark:bg-gray-700 text-gray-900 dark:text-white shadow-sm';
            $off = $base.'text-gray-500 dark:text-gray-400';
            $html = '<div class="inline-flex rounded-lg bg-gray-100 dark:bg-white/5 p-0.5" role="tablist" aria-label="Gráficos de señal">';
            foreach (['optica' => 'Potencia óptica', 'antena' => 'Señal de antena'] as $clave => $etiqueta) {
                $html .= '<button type="button" role="tab" data-senal-tab="'.$clave.'" aria-selected="'.($clave === $activa ? 'true' : 'false').'" class="'.($clave === $activa ? $on : $off).'">'.e($etiqueta).'</button>';
            }

            return $html.'</div>';
        };
    @endphp
    <div>
        <div data-senal-panel="optica">
            @include('home._distribucion', [
                'distTitulo' => 'Potencia óptica',
                'distSubtitulo' => 'Abajo, dBm. En altura, clientes en esa potencia.',
                'distVacio' => 'No hay ONU con potencia registrada.',
                'distBins' => $distribucionOptica,
                'distModo' => 'optica',
                'distAccion' => $tabsSenal('optica'),
            ])
        </div>
        <div data-senal-panel="antena" class="hidden">
            @include('home._distribucion', [
                'distTitulo' => 'Señal de antena',
                'distSubtitulo' => 'Abajo, dBm. En altura, clientes en esa señal. Buena desde −65.',
                'distVacio' => 'Todavía no hay lecturas de antena.',
                'distBins' => $distribucionAntena,
                'distModo' => 'antena',
                'distAccion' => $tabsSenal('antena'),
            ])
        </div>
    </div>
    <div id="dist-tip" class="pointer-events-none fixed z-50 hidden max-h-52 max-w-[15rem] overflow-hidden rounded-lg border border-gray-200 bg-white px-2.5 py-2 text-[10px] leading-snug text-gray-700 shadow-lg dark:border-white/10 dark:bg-[#12161f] dark:text-gray-200" role="tooltip"></div>
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

            var baseTab = 'inline-flex items-center rounded-md px-3 py-1 text-xs font-medium transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-violet-500 ';
            var tabOn = baseTab + 'bg-white dark:bg-gray-700 text-gray-900 dark:text-white shadow-sm';
            var tabOff = baseTab + 'text-gray-500 dark:text-gray-400';

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
</div>
