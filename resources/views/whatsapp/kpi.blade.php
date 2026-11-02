@extends('layouts.app')

@section('title', 'WhatsApp - KPI agente')

@section('content')
@php
    $r = $resumen;
    $periodo = $rango['periodo'];
    $fmtSeg = function (?int $seg): string {
        if ($seg === null) return '—';
        if ($seg < 60) return $seg.' s';
        return intdiv($seg, 60).'m '.($seg % 60).'s';
    };
    $qs = array_filter([
        'periodo' => $periodo === 'custom' ? 'custom' : $periodo,
        'desde' => request('desde', $fechaDia),
        'hasta' => request('hasta'),
        'sandbox' => $incluirSandbox ? 1 : null,
    ]);
@endphp
<div class="mx-auto max-w-7xl">
    <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">WhatsApp</h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                Productividad · primera respuesta &lt; {{ $sla['meta_min'] }} min (humano o agente) · {{ $rango['etiqueta'] }}
            </p>
        </div>
        <a href="{{ route('whatsapp.kpi.export', $qs) }}"
           class="inline-flex w-fit items-center rounded-lg border border-gray-300 px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-800">
            Exportar CSV
        </a>
    </div>

    @include('whatsapp._tabs', ['waTab' => 'kpi'])

    <form method="get" action="{{ route('whatsapp.kpi') }}" class="mb-6 flex flex-wrap items-end gap-2">
        @foreach(['dia' => 'Día', 'semana' => 'Semana', 'mes' => 'Mes', 'anio' => 'Año'] as $key => $label)
            <a href="{{ route('whatsapp.kpi', array_filter(['periodo' => $key, 'sandbox' => $incluirSandbox ? 1 : null])) }}"
               class="rounded-full px-3 py-1.5 text-sm {{ $periodo === $key ? 'bg-emerald-600 text-white' : 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300' }}">
                {{ $label }}
            </a>
        @endforeach
        <div class="flex items-end gap-2">
            <div>
                <label class="block text-xs text-gray-500">Día / desde</label>
                <input type="date" name="desde" value="{{ request('desde', $fechaDia) }}"
                       class="rounded-lg border border-gray-300 px-2 py-1.5 text-sm dark:border-gray-600 dark:bg-gray-900">
            </div>
            <div>
                <label class="block text-xs text-gray-500">Hasta (rango)</label>
                <input type="date" name="hasta" value="{{ request('hasta') }}"
                       class="rounded-lg border border-gray-300 px-2 py-1.5 text-sm dark:border-gray-600 dark:bg-gray-900">
            </div>
            <input type="hidden" name="periodo" id="kpiPeriodoInput" value="{{ request('hasta') ? 'custom' : 'dia' }}">
            <button type="submit" class="rounded-lg bg-emerald-600 px-3 py-1.5 text-sm text-white"
                    onclick="document.getElementById('kpiPeriodoInput').value = this.form.hasta.value ? 'custom' : 'dia'">Ver</button>
        </div>
        <label class="ml-auto flex items-center gap-2 text-sm text-gray-600 dark:text-gray-300">
            <input type="checkbox" name="sandbox" value="1" @checked($incluirSandbox) onchange="this.form.submit()">
            Incluir sandbox
        </label>
    </form>

    <div class="mb-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        @php
            $slaCards = [
                ['% bajo '.$sla['meta_min'].' min', ($sla['pct_ok'] !== null ? $sla['pct_ok'].'%' : '—'), $sla['ok'].' ok / '.$sla['fail'].' tarde'],
                ['1ª respuesta p50 / p95', $fmtSeg($sla['p50_seg']).' / '.$fmtSeg($sla['p95_seg']), 'promedio '.$fmtSeg($sla['promedio_seg'])],
                ['Contestó humano', number_format($sla['humano']), 'app / panel'],
                ['Contestó agente', number_format($sla['agente']), $sla['sin_respuesta'].' sin respuesta'],
            ];
        @endphp
        @foreach($slaCards as [$title, $value, $sub])
            <div class="rounded-xl border border-emerald-200 bg-emerald-50/60 p-4 dark:border-emerald-900 dark:bg-emerald-950/30">
                <p class="text-xs font-medium uppercase tracking-wide text-emerald-800 dark:text-emerald-300">{{ $title }}</p>
                <p class="mt-1 text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ $value }}</p>
                <p class="mt-0.5 text-xs text-gray-600 dark:text-gray-400">{{ $sub }}</p>
            </div>
        @endforeach
    </div>

    <div class="mb-6 rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
        <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Volumen en el tiempo</h2>
                <p class="text-xs text-gray-500">Eje X = {{ $serie['granularidad'] }} · Y = cantidad. Entradas del cliente vs respuestas humanas y del agente.</p>
            </div>
        </div>
        <div class="mt-3 w-full" style="height: 320px;">
            <canvas id="kpiLineChart"></canvas>
        </div>
    </div>

    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        @php
            $cards = [
                ['Ventas agente solo', number_format($r['ventas_agente'] ?? 0), number_format($r['ventas_n'] ?? 0).' planes en total'],
                ['Ventas asistidas', number_format($r['ventas_asistidas'] ?? 0), number_format($r['ventas_monto'] ?? 0, 0, ',', '.').' Gs'],
                ['Plan más convencido', $r['top_plan'] ?? '—', count($r['planes_convencidos'] ?? []).' planes distintos'],
                ['Conversaciones agente', number_format($r['conversaciones']), null],
                ['Respuestas agente', number_format($r['respuestas']), null],
                ['% resuelto auto', $r['pct_resuelto_auto'].'%', $r['resuelto_auto'].' casos'],
                ['Tickets agente', number_format($r['tickets']), null],
                ['Comprobantes', number_format($r['comprobantes']), null],
                ['Zona avisos / OK', $r['zona_aviso'].' / '.$r['zona_ok'], null],
                ['CSAT', $r['csat_promedio'] !== null ? number_format($r['csat_promedio'], 2) : '—', $r['csat_n'] ? $r['csat_n'].' respuestas' : 'sin datos'],
                ['Latencia modelo p50/p95', ($r['latency_p50'] ?? '—').' / '.($r['latency_p95'] ?? '—').' ms', 'solo agente'],
                ['Errores', number_format($r['errores']), null],
            ];
        @endphp
        @foreach($cards as [$title, $value, $sub])
            <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
                <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ $title }}</p>
                <p class="mt-1 text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ $value }}</p>
                @if($sub)
                    <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">{{ $sub }}</p>
                @endif
            </div>
        @endforeach
    </div>

    <div class="mt-6 rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
        <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Planes convencidos</h2>
        <p class="mt-1 text-xs text-gray-500">Pedidos atribuibles al agente (solo o con ayuda humana) en el período.</p>
        <div class="mt-3 overflow-x-auto">
            <table class="min-w-full text-sm divide-y divide-gray-200 dark:divide-gray-700">
                <thead>
                    <tr class="text-left text-xs text-gray-500">
                        <th class="py-1.5 pr-2">Plan</th>
                        <th class="py-1.5 pr-2 text-right">Agente</th>
                        <th class="py-1.5 pr-2 text-right">Asistida</th>
                        <th class="py-1.5 pr-2 text-right">Total</th>
                        <th class="py-1.5 text-right">Monto Gs</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                    @forelse(($r['planes_convencidos'] ?? []) as $planRow)
                        <tr>
                            <td class="py-1.5 pr-2 text-gray-900 dark:text-gray-100">{{ $planRow['nombre'] }}</td>
                            <td class="py-1.5 pr-2 text-right">{{ $planRow['agente'] }}</td>
                            <td class="py-1.5 pr-2 text-right">{{ $planRow['asistida'] }}</td>
                            <td class="py-1.5 pr-2 text-right font-medium">{{ $planRow['total'] }}</td>
                            <td class="py-1.5 text-right">{{ number_format($planRow['monto'], 0, ',', '.') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="py-4 text-center text-gray-500">Sin ventas de plan en el período.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-2">
        <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
            <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Respuestas por modo</h2>
            <div class="mt-3 w-full" style="height: 260px;">
                <canvas id="kpiModoChart"></canvas>
            </div>
            <table class="mt-3 min-w-full text-sm">
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                    @forelse($r['por_modo'] as $row)
                        <tr>
                            <td class="py-1.5 text-gray-700 dark:text-gray-300">{{ $row['modo'] }}</td>
                            <td class="py-1.5 text-right font-medium text-gray-900 dark:text-gray-100">{{ $row['total'] }}</td>
                        </tr>
                    @empty
                        <tr><td class="py-3 text-gray-500">Sin respuestas en el período.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
            <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Heatmap respuestas (día × hora)</h2>
            <p class="mt-1 text-xs text-gray-500">Intensidad = cantidad de respuestas del agente.</p>
            <div class="mt-3 overflow-x-auto">
                <table class="text-[10px] leading-none">
                    <thead>
                        <tr>
                            <th class="p-0.5"></th>
                            @for($h = 0; $h < 24; $h++)
                                <th class="p-0.5 font-normal text-gray-400">{{ $h }}</th>
                            @endfor
                        </tr>
                    </thead>
                    <tbody>
                        @php
                            $maxH = 1;
                            foreach ($heatmap['respuestas'] as $rowH) {
                                foreach ($rowH as $c) { $maxH = max($maxH, $c); }
                            }
                        @endphp
                        @foreach($diasSemana as $di => $diaLabel)
                            <tr>
                                <td class="pr-1 text-gray-500">{{ $diaLabel }}</td>
                                @for($h = 0; $h < 24; $h++)
                                    @php
                                        $c = $heatmap['respuestas'][$di][$h] ?? 0;
                                        $a = $c > 0 ? max(0.12, $c / $maxH) : 0;
                                        $v = $heatmap['ventas'][$di][$h] ?? 0;
                                    @endphp
                                    <td class="p-0.5" title="{{ $diaLabel }} {{ $h }}h: {{ $c }} resp.{{ $v ? ', '.$v.' ventas' : '' }}">
                                        <span class="block h-3 w-3 rounded-sm"
                                              style="background: {{ $c ? 'rgba(5,150,105,'.round($a, 2).')' : 'rgba(156,163,175,0.15)' }};
                                                     outline: {{ $v ? '1px solid #f59e0b' : 'none' }};"></span>
                                    </td>
                                @endfor
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <p class="mt-2 text-xs text-gray-500">Borde ámbar = hubo venta atribuida en esa celda.</p>
        </div>
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-2">
        <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
            <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Ventas de plan</h2>
            <div class="mt-3 overflow-x-auto">
                <table class="min-w-full text-sm divide-y divide-gray-200 dark:divide-gray-700">
                    <thead>
                        <tr class="text-left text-xs text-gray-500">
                            <th class="py-1.5 pr-2">Cuándo</th>
                            <th class="py-1.5 pr-2">Plan</th>
                            <th class="py-1.5 pr-2">Origen</th>
                            <th class="py-1.5 text-right">Monto</th>
                            <th class="py-1.5"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        @forelse($ventas as $v)
                            <tr>
                                <td class="py-1.5 pr-2 text-gray-600 dark:text-gray-300 whitespace-nowrap">{{ $v->ocurrido_at?->format('d/m H:i') }}</td>
                                <td class="py-1.5 pr-2">
                                    <div class="text-gray-900 dark:text-gray-100">{{ data_get($v->meta, 'plan_nombre') ?: ('Plan #'.(data_get($v->meta, 'plan_id') ?: '—')) }}</div>
                                    @if($v->telefono)
                                        <a class="text-xs text-emerald-700 hover:underline dark:text-emerald-400" href="{{ route('whatsapp.mensajes', ['tel' => $v->telefono]) }}">{{ $v->telefono }}</a>
                                    @endif
                                </td>
                                <td class="py-1.5 pr-2 text-gray-600">{{ data_get($v->meta, 'origen') ?: ($v->modo === 'venta_agente' ? 'agente' : 'asistida') }}</td>
                                <td class="py-1.5 text-right font-medium">{{ number_format((float) data_get($v->meta, 'monto', 0), 0, ',', '.') }}</td>
                                <td class="py-1.5 pl-2 text-right text-xs">
                                    @if($v->pedido_id)
                                        <a class="text-emerald-700 hover:underline" href="{{ route('pedidos.edit', $v->pedido_id) }}">Pedido</a>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="py-4 text-center text-gray-500">Sin ventas de plan en el período.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
            <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Eventos recientes</h2>
            <div class="mt-3 max-h-96 overflow-y-auto">
                <table class="min-w-full text-sm divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="sticky top-0 bg-white dark:bg-gray-800">
                        <tr class="text-left text-xs text-gray-500">
                            <th class="py-1.5 pr-2">Cuándo</th>
                            <th class="py-1.5 pr-2">Tipo</th>
                            <th class="py-1.5 pr-2">Modo</th>
                            <th class="py-1.5">Tel / ticket</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        @forelse($eventos as $ev)
                            <tr>
                                <td class="py-1.5 pr-2 whitespace-nowrap text-gray-600">{{ $ev->ocurrido_at?->format('d/m H:i') }}</td>
                                <td class="py-1.5 pr-2 font-medium">{{ $ev->tipo }}</td>
                                <td class="py-1.5 pr-2 text-gray-600">{{ $ev->modo }}</td>
                                <td class="py-1.5 text-xs">
                                    @if($ev->telefono)
                                        <a class="text-emerald-700 hover:underline dark:text-emerald-400" href="{{ route('whatsapp.mensajes', ['tel' => $ev->telefono]) }}">{{ $ev->telefono }}</a>
                                    @endif
                                    @if($ev->ticket_id)
                                        <span class="text-gray-500"> · ticket #{{ $ev->ticket_id }}</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="py-4 text-center text-gray-500">Sin eventos. Corré <code class="text-xs">php artisan whatsapp:kpi-backfill</code>.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
(function () {
    const serie = @json($serie);
    const lineEl = document.getElementById('kpiLineChart');
    if (lineEl && serie.labels && serie.labels.length) {
        new Chart(lineEl, {
            type: 'line',
            data: {
                labels: serie.labels,
                datasets: [
                    {
                        label: 'Entradas cliente',
                        data: serie.entradas,
                        borderColor: 'rgb(107, 114, 128)',
                        backgroundColor: 'rgba(107, 114, 128, 0.12)',
                        tension: 0.25,
                        fill: false,
                        pointRadius: 2,
                    },
                    {
                        label: 'Respuesta humana',
                        data: serie.humano,
                        borderColor: 'rgb(37, 99, 235)',
                        backgroundColor: 'rgba(37, 99, 235, 0.12)',
                        tension: 0.25,
                        fill: false,
                        pointRadius: 2,
                    },
                    {
                        label: 'Respuesta agente',
                        data: serie.agente,
                        borderColor: 'rgb(5, 150, 105)',
                        backgroundColor: 'rgba(5, 150, 105, 0.12)',
                        tension: 0.25,
                        fill: false,
                        pointRadius: 2,
                    },
                    {
                        label: 'SLA ok (< meta)',
                        data: serie.sla_ok,
                        borderColor: 'rgb(16, 185, 129)',
                        borderDash: [4, 3],
                        tension: 0.2,
                        fill: false,
                        pointRadius: 0,
                        yAxisID: 'y',
                    },
                    {
                        label: 'SLA tarde / sin resp.',
                        data: serie.sla_fail,
                        borderColor: 'rgb(244, 63, 94)',
                        borderDash: [4, 3],
                        tension: 0.2,
                        fill: false,
                        pointRadius: 0,
                        yAxisID: 'y',
                    },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 11 } } },
                },
                scales: {
                    x: {
                        title: { display: true, text: 'Tiempo (' + serie.granularidad + ')' },
                        ticks: { maxRotation: 0, autoSkip: true, maxTicksLimit: 24, font: { size: 10 } },
                    },
                    y: {
                        beginAtZero: true,
                        title: { display: true, text: 'Cantidad' },
                        ticks: { precision: 0 },
                    },
                },
            },
        });
    }

    const modos = @json($r['por_modo']);
    const el = document.getElementById('kpiModoChart');
    if (!el || !modos.length) return;
    new Chart(el, {
        type: 'bar',
        data: {
            labels: modos.map(m => m.modo),
            datasets: [{
                label: 'Respuestas',
                data: modos.map(m => m.total),
                backgroundColor: 'rgba(5, 150, 105, 0.75)',
                borderRadius: 6,
            }],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                x: { ticks: { maxRotation: 45, minRotation: 0, font: { size: 10 } } },
                y: { beginAtZero: true, ticks: { precision: 0 } },
            },
        },
    });
})();
</script>
@endsection
