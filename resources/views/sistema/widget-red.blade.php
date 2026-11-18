@extends('layouts.app')

@section('title', 'Widget de red')

@section('content')
@php
    $etiquetas = ['ap' => 'AP', 'router' => 'Router', 'olt' => 'OLT'];

    $lineaTipo = function (array $counts): array {
        $online = (int) ($counts['online'] ?? 0);
        $offline = (int) ($counts['offline'] ?? 0);
        $unknown = (int) ($counts['unknown'] ?? 0);
        if ($online === 0 && $offline === 0) {
            return [
                'modo' => 'sindato',
                'texto' => $unknown > 0 ? $unknown.' sin dato' : 'sin dato',
            ];
        }

        return [
            'modo' => 'conteo',
            'online' => $online,
            'offline' => $offline,
            'extra' => $unknown > 0 ? $unknown.' sin dato' : '',
        ];
    };

    $hace = function (?string $since): string {
        if ($since === null || $since === '') {
            return '—';
        }

        return \App\Support\MonitoreoDuracion::formatear(\Illuminate\Support\Carbon::parse($since)) ?? '—';
    };

    $generado = \Illuminate\Support\Carbon::parse($payload['generated_at'] ?? now())->timezone(config('app.timezone'))->format('H:i');
    $totalCaidas = count($payload['caidas'] ?? []);
@endphp
<div class="max-w-5xl mx-auto space-y-4">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">Widget de red</h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                Vista previa del tablero de Widgets. Los mismos datos salen por la API del proveedor de Windows.
                Se actualiza cada minuto.
            </p>
        </div>
        <a href="{{ route('sistema.red-monitoreo.index') }}" class="text-sm text-blue-600 dark:text-blue-400 hover:underline">Monitoreo de red</a>
    </div>

    <div id="widget-board" class="widget-board" data-url="{{ $urlDatos }}">
        <article class="widget-card widget-card--small" aria-label="Red, resumen">
            <p class="widget-kicker">Red</p>
            <p class="widget-sub">en vivo</p>
            <ul class="widget-rows">
                @foreach($etiquetas as $clave => $etiqueta)
                    @php $linea = $lineaTipo($payload['counts'][$clave] ?? []); @endphp
                    <li>
                        <span>{{ $etiqueta }}</span>
                        @if($linea['modo'] === 'sindato')
                            <span class="widget-sindato">{{ $linea['texto'] }}</span>
                        @else
                            <span class="widget-nums">
                                <span class="up">{{ $linea['online'] }}</span>
                                <span class="down">{{ $linea['offline'] }}</span>
                            </span>
                        @endif
                    </li>
                @endforeach
            </ul>
            <p class="widget-foot {{ $totalCaidas === 0 ? 'up' : 'down' }}">
                {{ $totalCaidas === 0 ? 'Sin caídas' : ($totalCaidas === 1 ? '1 caída' : $totalCaidas.' caídas') }}
            </p>
        </article>

        <article class="widget-card widget-card--large" aria-label="Caídas de red">
            <header class="widget-head">
                <div>
                    <p class="widget-kicker">Caídas de red</p>
                    <p class="widget-sub">Actualizado {{ $generado }}</p>
                </div>
            </header>
            <div class="widget-pills">
                @foreach($etiquetas as $clave => $etiqueta)
                    @php $linea = $lineaTipo($payload['counts'][$clave] ?? []); @endphp
                    <div class="widget-pill">
                        <p class="widget-pill-label">{{ $etiqueta }}</p>
                        @if($linea['modo'] === 'sindato')
                            <p class="widget-sindato">{{ $linea['texto'] }}</p>
                        @else
                            <p>
                                <span class="up">{{ $linea['online'] }} en línea</span>
                                <span class="down">{{ $linea['offline'] }} {{ $linea['offline'] === 1 ? 'caída' : 'caídas' }}</span>
                            </p>
                            @if($linea['extra'] !== '')
                                <p class="widget-sindato">{{ $linea['extra'] }}</p>
                            @endif
                        @endif
                    </div>
                @endforeach
            </div>
            <h2 class="widget-list-title">Caídas</h2>
            @if($totalCaidas === 0)
                <p class="up widget-empty">Sin caídas</p>
            @else
                <ul class="widget-list">
                    @foreach(array_slice($payload['caidas'], 0, 8) as $caida)
                        <li>
                            <span class="down widget-tipo">{{ $etiquetas[$caida['type']] ?? $caida['type'] }}</span>
                            <span class="widget-nombre">{{ $caida['name'] }}</span>
                            <span class="widget-nodo">{{ $caida['nodo'] ?: '—' }}</span>
                            <span class="down widget-hace">{{ $hace($caida['since'] ?? null) }}</span>
                        </li>
                    @endforeach
                </ul>
                @if($totalCaidas > 8)
                    <p class="widget-sub">y {{ $totalCaidas - 8 }} más</p>
                @endif
            @endif
        </article>
    </div>
    <p id="widget-estado" class="text-xs text-gray-500 dark:text-gray-400"></p>
</div>

<style>
    .widget-board {
        display: flex;
        flex-wrap: wrap;
        gap: 16px;
        align-items: stretch;
        padding: 22px;
        border-radius: 28px;
        background:
            radial-gradient(900px 280px at 0% 0%, rgba(255,255,255,0.06), transparent 55%),
            linear-gradient(180deg, #1a1a1e 0%, #101012 100%);
        box-shadow: inset 0 0 0 1px rgba(255,255,255,0.04);
    }
    .widget-card {
        background: rgba(43, 43, 48, 0.94);
        border: 1px solid rgba(255,255,255,0.08);
        border-radius: 16px;
        color: #f4f4f5;
        box-shadow: 0 10px 28px rgba(0,0,0,0.35);
    }
    .widget-card--small {
        width: 200px;
        min-height: 210px;
        padding: 14px 14px 12px;
        display: flex;
        flex-direction: column;
    }
    .widget-card--large {
        flex: 1 1 340px;
        min-width: 280px;
        padding: 16px 18px 14px;
    }
    .widget-kicker {
        font-size: 18px;
        font-weight: 650;
        letter-spacing: -0.02em;
        line-height: 1.1;
    }
    .widget-sub { color: #a1a1aa; font-size: 12px; margin-top: 2px; }
    .widget-rows { list-style: none; margin: 14px 0 0; padding: 0; display: grid; gap: 8px; }
    .widget-rows li, .widget-list li {
        display: flex;
        align-items: baseline;
        justify-content: space-between;
        gap: 8px;
        font-size: 13px;
    }
    .widget-nums { display: flex; gap: 10px; font-variant-numeric: tabular-nums; font-weight: 650; }
    .up { color: #4ade80; }
    .down { color: #f87171; }
    .widget-sindato { color: #a1a1aa; font-size: 12px; }
    .widget-foot { margin-top: auto; padding-top: 12px; font-size: 13px; font-weight: 650; }
    .widget-head { display: flex; justify-content: space-between; gap: 12px; }
    .widget-pills { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 14px; }
    .widget-pill {
        flex: 1 1 120px;
        background: rgba(255,255,255,0.04);
        border-radius: 12px;
        padding: 10px 12px;
        font-size: 13px;
    }
    .widget-pill p + p { margin-top: 2px; }
    .widget-pill-label { color: #e4e4e7; font-weight: 650; }
    .widget-list-title { margin: 16px 0 8px; font-size: 13px; font-weight: 650; color: #e4e4e7; }
    .widget-list { list-style: none; margin: 0; padding: 0; display: grid; gap: 8px; }
    .widget-tipo { width: 4.5rem; flex: none; font-size: 12px; font-weight: 650; }
    .widget-nombre { flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .widget-nodo, .widget-hace { color: #a1a1aa; font-size: 12px; font-variant-numeric: tabular-nums; }
    .widget-hace.down { color: #f87171; }
    .widget-empty { font-size: 13px; font-weight: 650; }
</style>

<script>
    (function () {
        var board = document.getElementById('widget-board');
        var estado = document.getElementById('widget-estado');
        if (!board) return;
        var url = board.getAttribute('data-url');
        var etiquetas = { ap: 'AP', router: 'Router', olt: 'OLT' };

        function hace(since) {
            if (!since) return '—';
            var desde = new Date(since).getTime();
            if (!isFinite(desde)) return '—';
            var segundos = Math.max(0, Math.floor((Date.now() - desde) / 1000));
            if (segundos < 60) return '< 1 min';
            var dias = Math.floor(segundos / 86400);
            var horas = Math.floor((segundos % 86400) / 3600);
            var minutos = Math.floor((segundos % 3600) / 60);
            var partes = [];
            if (dias > 0) partes.push(dias + 'd');
            if (horas > 0 || dias > 0) partes.push(horas + 'h');
            partes.push(minutos + 'm');
            return partes.join(' ');
        }

        function linea(counts) {
            var online = counts.online || 0;
            var offline = counts.offline || 0;
            var unknown = counts.unknown || 0;
            if (online === 0 && offline === 0) {
                return { modo: 'sindato', texto: unknown > 0 ? (unknown + ' sin dato') : 'sin dato' };
            }
            return { modo: 'conteo', online: online, offline: offline, extra: unknown > 0 ? (unknown + ' sin dato') : '' };
        }

        function esc(value) {
            return String(value == null ? '' : value)
                .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
        }

        function pintar(payload) {
            var counts = payload.counts || {};
            var caidas = payload.caidas || [];
            var total = caidas.length;
            var filas = '';
            var pills = '';
            ['ap', 'router', 'olt'].forEach(function (clave) {
                var item = linea(counts[clave] || {});
                var etiqueta = etiquetas[clave];
                if (item.modo === 'sindato') {
                    filas += '<li><span>' + etiqueta + '</span><span class="widget-sindato">' + esc(item.texto) + '</span></li>';
                    pills += '<div class="widget-pill"><p class="widget-pill-label">' + etiqueta + '</p><p class="widget-sindato">' + esc(item.texto) + '</p></div>';
                } else {
                    filas += '<li><span>' + etiqueta + '</span><span class="widget-nums"><span class="up">' + item.online + '</span><span class="down">' + item.offline + '</span></span></li>';
                    pills += '<div class="widget-pill"><p class="widget-pill-label">' + etiqueta + '</p><p><span class="up">' + item.online + ' en línea</span> <span class="down">' + item.offline + (item.offline === 1 ? ' caída' : ' caídas') + '</span></p>' + (item.extra ? '<p class="widget-sindato">' + esc(item.extra) + '</p>' : '') + '</div>';
                }
            });
            var resumen = total === 0 ? 'Sin caídas' : (total === 1 ? '1 caída' : total + ' caídas');
            var lista = '';
            if (total === 0) {
                lista = '<p class="up widget-empty">Sin caídas</p>';
            } else {
                lista = '<ul class="widget-list">';
                caidas.slice(0, 8).forEach(function (caida) {
                    lista += '<li><span class="down widget-tipo">' + esc(etiquetas[caida.type] || caida.type) + '</span><span class="widget-nombre">' + esc(caida.name) + '</span><span class="widget-nodo">' + esc(caida.nodo || '—') + '</span><span class="down widget-hace">' + esc(hace(caida.since)) + '</span></li>';
                });
                lista += '</ul>';
                if (total > 8) lista += '<p class="widget-sub">y ' + (total - 8) + ' más</p>';
            }
            var hora = '';
            if (payload.generated_at) {
                var fecha = new Date(payload.generated_at);
                if (isFinite(fecha.getTime())) {
                    hora = fecha.toLocaleTimeString('es-AR', { hour: '2-digit', minute: '2-digit' });
                }
            }
            board.innerHTML =
                '<article class="widget-card widget-card--small" aria-label="Red, resumen"><p class="widget-kicker">Red</p><p class="widget-sub">en vivo</p><ul class="widget-rows">' + filas + '</ul><p class="widget-foot ' + (total === 0 ? 'up' : 'down') + '">' + resumen + '</p></article>' +
                '<article class="widget-card widget-card--large" aria-label="Caídas de red"><header class="widget-head"><div><p class="widget-kicker">Caídas de red</p><p class="widget-sub">Actualizado ' + esc(hora) + '</p></div></header><div class="widget-pills">' + pills + '</div><h2 class="widget-list-title">Caídas</h2>' + lista + '</article>';
        }

        function tick() {
            board.querySelectorAll('.widget-hace').forEach(function (el) {
                var since = el.getAttribute('data-since');
                if (since) el.textContent = hace(since);
            });
        }

        async function refrescar() {
            try {
                var res = await fetch(url, {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin'
                });
                if (!res.ok) {
                    if (estado) estado.textContent = 'No se pudo actualizar.';
                    return;
                }
                pintar(await res.json());
                if (estado) estado.textContent = '';
            } catch (e) {
                if (estado) estado.textContent = 'No se pudo actualizar.';
            }
        }

        setInterval(refrescar, 60000);
        setInterval(tick, 30000);
    })();
</script>
@endsection
