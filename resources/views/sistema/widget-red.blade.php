@extends('layouts.app')

@section('title', 'Widget de red')

@section('content')
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

    <div id="widget-board" class="widget-board" data-url="{{ $urlDatos }}"></div>
    <p id="widget-estado" class="text-xs text-gray-500 dark:text-gray-400"></p>
</div>

<style>
    .widget-board {
        display: grid;
        grid-template-columns: 180px minmax(0, 1fr);
        grid-template-areas: "small medium" "large large";
        gap: 14px;
        padding: 20px;
        border-radius: 24px;
        background:
            radial-gradient(800px 260px at 0% 0%, rgba(96, 165, 250, 0.10), transparent 60%),
            linear-gradient(180deg, #1c1c21 0%, #121215 100%);
        box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.05);
        font-family: "Segoe UI Variable Text", "Segoe UI", system-ui, sans-serif;
    }
    .widget-board p { margin: 0; }
    @media (max-width: 640px) {
        .widget-board { grid-template-columns: minmax(0, 1fr); grid-template-areas: "small" "medium" "large"; padding: 14px; }
    }
    .wc {
        background: rgba(44, 44, 50, 0.92);
        border: 1px solid rgba(255, 255, 255, 0.07);
        border-radius: 14px;
        color: #f4f4f5;
        padding: 14px 16px;
        box-shadow: 0 8px 24px rgba(0, 0, 0, 0.30);
        display: flex;
        flex-direction: column;
        min-width: 0;
    }
    .wc--small { grid-area: small; min-height: 180px; }
    .wc--medium { grid-area: medium; min-height: 180px; }
    .wc--large { grid-area: large; min-height: 260px; }
    .wc-top { display: flex; justify-content: space-between; align-items: center; font-size: 12px; color: #a1a1aa; }
    .wc-top b { font-weight: 600; color: #d4d4d8; }
    .wc-estado { margin-top: 6px; font-size: 20px; font-weight: 650; letter-spacing: -0.01em; line-height: 1.2; }
    .wc-estado-detalle { font-size: 12px; color: #a1a1aa; margin-top: 2px; }
    .good { color: #6ccb5f; }
    .bad { color: #ff99a4; }
    .warn { color: #fce100; }
    .muted { color: #a1a1aa; }
    .wc-rows { list-style: none; margin: auto 0 0; padding: 12px 0 0; border-top: 1px solid rgba(255, 255, 255, 0.08); display: grid; gap: 7px; }
    .wc-rows li { display: flex; align-items: baseline; gap: 10px; font-size: 13px; }
    .wc-rows li > span:first-child { flex: 1; }
    .wc-rows .num { font-weight: 650; font-variant-numeric: tabular-nums; font-size: 13px; }
    .wc-rows .num.zero { color: #71717a; }
    .wc-tiles { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 8px; margin-top: 12px; }
    .wc-tile { background: rgba(255, 255, 255, 0.05); border-radius: 10px; padding: 9px 11px; min-width: 0; }
    .wc-tile-label { font-size: 12px; color: #a1a1aa; }
    .wc-tile-num { font-size: 26px; font-weight: 650; line-height: 1.15; font-variant-numeric: tabular-nums; }
    .wc-tile-det { font-size: 12px; }
    .wc-list-title { margin: 14px 0 6px; padding-top: 10px; border-top: 1px solid rgba(255, 255, 255, 0.08); font-size: 12px; font-weight: 600; color: #a1a1aa; }
    .wc-list { list-style: none; margin: 0; padding: 0; display: grid; gap: 8px; }
    .wc-list li { display: flex; align-items: center; gap: 10px; min-width: 0; }
    .wc-list .info { flex: 1; min-width: 0; }
    .wc-list .nombre, .wc-list .det { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .wc-list .nombre { font-size: 13px; font-weight: 600; }
    .wc-list .det { font-size: 12px; color: #a1a1aa; }
    .wc-list .hace { font-size: 12px; font-weight: 650; font-variant-numeric: tabular-nums; color: #ff99a4; }
    .wc-mas { margin-top: 6px; font-size: 12px; color: #a1a1aa; }
</style>

<script>
    (function () {
        var board = document.getElementById('widget-board');
        var estado = document.getElementById('widget-estado');
        if (!board) return;
        var url = board.getAttribute('data-url');
        var etiquetas = { ap: 'AP', router: 'Router', olt: 'OLT' };
        var MAX_MEDIUM = 2;
        var MAX_LARGE = 6;

        function esc(value) {
            return String(value == null ? '' : value)
                .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
        }

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

        // Misma lógica que WidgetRedClient.Tipo del proveedor de Windows.
        function tipo(clave, counts) {
            var online = counts.online || 0;
            var offline = counts.offline || 0;
            var unknown = counts.unknown || 0;
            var sinDato = unknown > 0 ? unknown + ' sin dato' : 'sin dato';
            if (online === 0 && offline === 0) {
                return { etiqueta: etiquetas[clave], modo: 'sindato', texto: sinDato, grande: '—', grandeCls: 'muted', detalle: sinDato, detalleCls: 'muted' };
            }
            var detalle = offline === 0 ? 'en línea' : (offline === 1 ? '1 caído' : offline + ' caídos');
            if (unknown > 0) detalle += ' · ' + sinDato;
            return {
                etiqueta: etiquetas[clave], modo: 'conteo', online: online, offline: offline,
                grande: online, grandeCls: offline > 0 ? '' : 'good',
                detalle: detalle, detalleCls: offline > 0 ? 'bad' : 'muted'
            };
        }

        function cabecera(hora, est) {
            return '<div class="wc-top"><b>Red</b><span>' + esc(hora) + '</span></div>' +
                '<p class="wc-estado ' + est.cls + '">' + esc(est.texto) + '</p>';
        }

        function filas(tipos) {
            return '<ul class="wc-rows">' + tipos.map(function (t) {
                if (t.modo === 'sindato') {
                    return '<li><span>' + t.etiqueta + '</span><span class="muted">' + esc(t.texto) + '</span></li>';
                }
                return '<li><span>' + t.etiqueta + '</span>' +
                    '<span class="num good">↑ ' + t.online + '</span>' +
                    '<span class="num ' + (t.offline > 0 ? 'bad' : 'zero') + '">↓ ' + t.offline + '</span></li>';
            }).join('') + '</ul>';
        }

        function tiles(tipos) {
            return '<div class="wc-tiles">' + tipos.map(function (t) {
                return '<div class="wc-tile"><p class="wc-tile-label">' + t.etiqueta + '</p>' +
                    '<p class="wc-tile-num ' + t.grandeCls + '">' + esc(t.grande) + '</p>' +
                    '<p class="wc-tile-det ' + t.detalleCls + '">' + esc(t.detalle) + '</p></div>';
            }).join('') + '</div>';
        }

        function lista(caidas, max) {
            if (caidas.length === 0) return '';
            var html = '<p class="wc-list-title">Equipos caídos</p><ul class="wc-list">';
            caidas.slice(0, max).forEach(function (c) {
                var etiqueta = etiquetas[c.type] || c.type;
                html += '<li><div class="info"><p class="nombre">' + esc(c.name || etiqueta) + '</p>' +
                    '<p class="det">' + esc(c.nodo ? etiqueta + ' · ' + c.nodo : etiqueta) + '</p></div>' +
                    '<span class="hace" data-since="' + esc(c.since || '') + '">' + esc(hace(c.since)) + '</span></li>';
            });
            html += '</ul>';
            if (caidas.length > max) html += '<p class="wc-mas">y ' + (caidas.length - max) + ' más</p>';
            return html;
        }

        function pintar(payload) {
            var counts = payload.counts || {};
            var caidas = payload.caidas || [];
            var total = caidas.length;
            var tipos = ['ap', 'router', 'olt'].map(function (clave) { return tipo(clave, counts[clave] || {}); });
            var est = {
                texto: total === 0 ? 'Todo en línea' : (total === 1 ? '1 equipo caído' : total + ' equipos caídos'),
                cls: total === 0 ? 'good' : 'bad'
            };
            var hora = '';
            if (payload.generated_at) {
                var fecha = new Date(payload.generated_at);
                if (isFinite(fecha.getTime())) {
                    hora = fecha.toLocaleTimeString('es-PY', { hour: '2-digit', minute: '2-digit', hour12: false });
                }
            }
            board.innerHTML =
                '<article class="wc wc--small" aria-label="Widget chico">' + cabecera(hora, est) + filas(tipos) + '</article>' +
                '<article class="wc wc--medium" aria-label="Widget mediano">' + cabecera(hora, est) + tiles(tipos) + lista(caidas, MAX_MEDIUM) + '</article>' +
                '<article class="wc wc--large" aria-label="Widget grande">' + cabecera(hora, est) + tiles(tipos) + lista(caidas, MAX_LARGE) + '</article>';
        }

        function tick() {
            board.querySelectorAll('.hace[data-since]').forEach(function (el) {
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

        pintar(@json($payload));
        setInterval(refrescar, 60000);
        setInterval(tick, 30000);
    })();
</script>
@endsection
