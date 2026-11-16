@php
    $distBins = $distBins ?? [];
    $distModo = $distModo ?? 'optica';
    $maxDist = 0;
    foreach ($distBins as $binDist) {
        $maxDist = max($maxDist, (int) ($binDist['cantidad'] ?? 0));
    }
    $pasoDist = isset($distPaso) ? (int) $distPaso : 25;
    if (! isset($distPaso) && ($distModo ?? '') === 'cortes') {
        $pasoDist = $maxDist <= 5 ? 1 : ($maxDist <= 20 ? 5 : 25);
    }
    $topeDist = $maxDist > 0 ? (int) (ceil($maxDist / max(1, $pasoDist)) * $pasoDist) : 0;
    $marcasDist = [];
    for ($marcaDist = 0; $marcaDist <= $topeDist; $marcaDist += $pasoDist) {
        $marcasDist[] = $marcaDist;
    }
@endphp

<div class="rounded-2xl border border-gray-200/80 dark:border-white/10 bg-white dark:bg-[#12161f] shadow-sm overflow-hidden">
    <div class="px-4 sm:px-5 py-4 border-b border-gray-100 dark:border-white/5">
        <h2 class="text-sm font-semibold text-gray-900 dark:text-white">{{ $distTitulo }}</h2>
        <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">{{ $distSubtitulo }}</p>
    </div>
    @if($distBins === [])
        <p class="px-4 sm:px-5 py-8 text-center text-sm text-gray-500 dark:text-gray-400">{{ $distVacio }}</p>
    @else
        <div class="px-4 sm:px-5 py-4" data-dist data-unidad="{{ $distUnidad ?? 'dBm' }}" data-unidad-uno="{{ $distUnidadUno ?? ($distUnidad ?? 'dBm') }}" data-bins="{{ json_encode($distBins, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP) }}">
            <div class="flex gap-2">
                <div class="relative h-64 w-8 shrink-0 text-[10px] tabular-nums text-gray-400">
                    @foreach($marcasDist as $ordenDist => $marcaDist)
                        @php
                            $bottomDist = $topeDist > 0 ? round($marcaDist / $topeDist * 100, 2) : 0;
                            $esCeroDist = $ordenDist === 0;
                            $esTopeDist = $ordenDist === count($marcasDist) - 1;
                        @endphp
                        <span class="absolute right-0 {{ $esCeroDist ? 'bottom-0' : ($esTopeDist ? 'top-0' : '-translate-y-1/2') }}" @if(! $esCeroDist && ! $esTopeDist) style="bottom: {{ $bottomDist }}%" @endif>{{ number_format($marcaDist) }}</span>
                    @endforeach
                </div>
                <div class="min-w-0 flex-1">
                    <div class="relative h-64">
                        @foreach($marcasDist as $marcaDist)
                            @php $bottomDist = $topeDist > 0 ? round($marcaDist / $topeDist * 100, 2) : 0; @endphp
                            <span class="pointer-events-none absolute left-0 right-0 bg-gray-200 dark:bg-white/15" style="height:1px;{{ $bottomDist >= 100 ? 'bottom: calc(100% - 1px)' : 'bottom: '.$bottomDist.'%' }}"></span>
                        @endforeach
                        <div class="absolute inset-0 flex items-end gap-0.5">
                            @foreach($distBins as $indiceDist => $binDist)
                                @php
                                    $dbmDist = (int) $binDist['dbm'];
                                    $cantidadDist = (int) $binDist['cantidad'];
                                    $altoDist = $topeDist > 0 ? round($cantidadDist / $topeDist * 100, 2) : 0;
                                    if ($distModo === 'antena') {
                                        $colorDist = $dbmDist < -75
                                            ? 'bg-rose-500'
                                            : ($dbmDist < -65 ? 'bg-amber-400' : 'bg-emerald-500 dark:bg-[#2dd4a0]');
                                    } elseif ($distModo === 'cortes') {
                                        $nivelCorte = $binDist['nivel'] ?? \App\Support\PppoeDesconexionContinua::nivelDeVentana('h24', $dbmDist);
                                        $colorDist = match ($nivelCorte) {
                                            'critico' => 'bg-rose-500',
                                            'alto' => 'bg-amber-400',
                                            'observar' => 'bg-yellow-400',
                                            default => 'bg-emerald-500 dark:bg-[#2dd4a0]',
                                        };
                                    } else {
                                        $colorDist = $dbmDist < -27
                                            ? 'bg-rose-500'
                                            : ($dbmDist > -8 ? 'bg-amber-400' : 'bg-emerald-500 dark:bg-[#2dd4a0]');
                                    }
                                @endphp
                                <div class="relative h-full min-w-0 flex-1" data-dist-bin="{{ $indiceDist }}">
                                    <span class="absolute bottom-0 left-1/2 z-[1] w-2/3 -translate-x-1/2 rounded-t {{ $colorDist }}" style="height: {{ max($altoDist, $cantidadDist > 0 ? 0.8 : 0) }}%"></span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                    <div class="mt-1.5 flex text-[10px] tabular-nums text-gray-500 dark:text-gray-400">
                        @foreach($distBins as $binDist)
                            <div class="min-w-0 flex-1 truncate text-center">{{ (int) $binDist['dbm'] }}</div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
