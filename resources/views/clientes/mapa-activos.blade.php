@extends('layouts.app')

@section('title', 'Mapa de clientes activos')

@section('content')
<div class="max-w-full mx-auto flex flex-col h-[calc(100vh-8rem)] min-h-[400px]">
    @if(session('success'))
        <div class="mb-3 px-4 py-3 rounded-lg bg-green-50 dark:bg-green-900/20 text-green-800 dark:text-green-200 text-sm border border-green-200 dark:border-green-800">{{ session('success') }}</div>
    @endif
    @if(session('warning'))
        <div class="mb-3 px-4 py-3 rounded-lg bg-amber-50 dark:bg-amber-900/20 text-amber-800 dark:text-amber-200 text-sm border border-amber-200 dark:border-amber-800">{{ session('warning') }}</div>
    @endif

    <div class="mb-4 flex-shrink-0 flex flex-col lg:flex-row lg:items-start lg:justify-between gap-3">
        <div class="min-w-0">
            <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">Clientes activos en mapa</h1>
            @if(!empty($nodoSeleccionado))
                <p class="mt-1 text-sm text-purple-700 dark:text-purple-300">
                    Filtro de nodo: <strong>{{ $nodoSeleccionado->descripcion }}</strong>
                </p>
            @endif
            @if(!empty($pingEstadoFiltro))
                <p class="mt-1 text-sm text-red-700 dark:text-red-300">
                    Filtro PPPoE: <strong>{{ ($pingEstadoFiltroLabels ?? [])[$pingEstadoFiltro] ?? $pingEstadoFiltro }}</strong>
                </p>
            @endif
            <p id="mapa-resumen" class="mt-2 text-xs text-gray-500 dark:text-gray-400">Cargando clientes…</p>
            <div id="mapa-leyenda" class="mt-2 hidden flex-wrap gap-3 text-xs">
                <span class="inline-flex items-center gap-1.5"><span class="w-3 h-3 rounded-full bg-green-600"></span><span>Online (<span data-ping="online">0</span>)</span></span>
                <span class="inline-flex items-center gap-1.5"><span class="w-3 h-3 rounded-full bg-red-600"></span><span>PPPoE caído (<span data-ping="offline">0</span>)</span></span>
                <span class="inline-flex items-center gap-1.5"><span class="w-3 h-3 rounded-full bg-orange-500"></span><span>Parcial (<span data-ping="mixed">0</span>)</span></span>
                <span class="inline-flex items-center gap-1.5"><span class="w-3 h-3 rounded-full bg-gray-400"></span><span>Sin dato (<span data-ping="unknown">0</span>)</span></span>
            </div>
            <p id="mapa-nota" class="mt-1 hidden text-[11px] text-gray-400 dark:text-gray-500">Consulta PPPoE en MikroTik cada 7 min · CLI: <code class="text-[10px]">php artisan monitoreo:ping-servicios --nodo=ID</code></p>
        </div>
        <div class="flex flex-col gap-2 shrink-0 w-full lg:w-auto">
            <form action="{{ route('clientes.mapa-activos') }}" method="GET" class="flex flex-wrap items-end gap-2">
                <div>
                    <label for="nodo_id" class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Nodo</label>
                    <select name="nodo_id" id="nodo_id"
                            class="block min-w-[180px] rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-gray-900 dark:text-gray-100 text-sm px-3 py-2">
                        <option value="">Todos los nodos</option>
                        @foreach($nodos ?? [] as $nodo)
                            <option value="{{ $nodo->nodo_id }}" @selected(($nodoIdSeleccionado ?? null) == $nodo->nodo_id)>{{ $nodo->descripcion }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="ping_estado" class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Estado PPPoE</label>
                    <select name="ping_estado" id="ping_estado"
                            class="block min-w-[160px] rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-gray-900 dark:text-gray-100 text-sm px-3 py-2">
                        <option value="">Todos</option>
                        <option value="online" @selected(($pingEstadoFiltro ?? null) === 'online')>Online</option>
                        <option value="offline" @selected(($pingEstadoFiltro ?? null) === 'offline')>PPPoE caído</option>
                        <option value="mixed" @selected(($pingEstadoFiltro ?? null) === 'mixed')>Parcial</option>
                        <option value="unknown" @selected(($pingEstadoFiltro ?? null) === 'unknown')>Sin dato</option>
                    </select>
                </div>
                <button type="submit"
                        class="inline-flex items-center justify-center px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700 font-medium text-sm">
                    Filtrar mapa
                </button>
            </form>
            <div class="flex flex-wrap items-center gap-2">
                <button type="button" id="btn-ejecutar-ping-mapa"
                        data-url="{{ $urlEjecutarPing ?? '' }}"
                        data-csrf="{{ csrf_token() }}"
                        data-nodo-id="{{ $nodoIdSeleccionado ?? '' }}"
                        class="inline-flex items-center justify-center px-4 py-2 rounded-lg font-medium text-white bg-purple-600 hover:bg-purple-700 text-sm disabled:opacity-60 disabled:cursor-not-allowed">
                    Consultar PPPoE ahora
                </button>
                <span id="mapa-ping-ejecutando" class="text-xs text-gray-500 dark:text-gray-400 hidden">Consultando MikroTik…</span>
                <span id="mapa-ping-ultima-actualizacion" class="text-xs text-gray-500 dark:text-gray-400 hidden"></span>
                <a href="{{ route('clientes.index') }}"
                   class="inline-flex items-center justify-center px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700 font-medium text-sm">
                    Volver a clientes
                </a>
            </div>
            <p id="mapa-ping-resultado" class="text-xs text-gray-600 dark:text-gray-400 hidden"></p>
        </div>
    </div>

    <div class="relative bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 overflow-hidden flex-1 min-h-[300px]">
        <div id="mapa-clientes-activos-app" class="w-full h-full min-h-[300px]"></div>
        <div id="mapa-espera" class="absolute inset-0 z-10 flex flex-col items-center justify-center gap-3 bg-gray-100/90 dark:bg-gray-900/80" aria-live="polite" aria-busy="true">
            <svg class="h-10 w-10 animate-spin text-purple-600 dark:text-purple-400" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            <p class="text-sm font-medium text-gray-700 dark:text-gray-300">Consultando clientes…</p>
        </div>
    </div>

    <div id="mapa-vacio" class="hidden mt-4 px-4 py-6 text-center text-gray-500 dark:text-gray-400 bg-gray-50 dark:bg-gray-800/50 rounded-lg border border-gray-200 dark:border-gray-700"></div>
</div>

@push('scripts')
<script>
(function () {
    const resumen = document.getElementById('mapa-resumen');
    const leyenda = document.getElementById('mapa-leyenda');
    const nota = document.getElementById('mapa-nota');
    const vacio = document.getElementById('mapa-vacio');
    const espera = document.getElementById('mapa-espera');
    const nf = new Intl.NumberFormat('es-PY');

    function fallo(mensaje) {
        if (resumen) resumen.textContent = mensaje;
        if (espera) espera.querySelector('p').textContent = mensaje;
    }

    fetch(@json($urlMapaDatos), {
        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        credentials: 'same-origin',
    }).then(function (response) {
        if (!response.ok) throw new Error('No se pudieron cargar los clientes.');
        return response.json();
    }).then(function (data) {
        const stats = data.stats || {};
        const filtro = data.ping_estado_filtro || '';
        const total = Number(stats.en_mapa_total || 0);
        const visibles = Number(stats.en_mapa || 0);
        let texto = filtro && total > visibles
            ? 'Mostrando: ' + nf.format(visibles) + ' de ' + nf.format(total) + ' en mapa'
            : 'En mapa: ' + nf.format(visibles);
        texto += ' · Con servicio vigente y ubicación: ' + nf.format(Number(stats.total_candidatos || 0));
        if (Number(stats.sin_coordenadas || 0) > 0) {
            texto += ' · Sin coordenadas parseables: ' + nf.format(Number(stats.sin_coordenadas));
        }
        if (resumen) resumen.textContent = texto;
        if (leyenda) {
            leyenda.classList.remove('hidden');
            leyenda.classList.add('flex');
            leyenda.querySelectorAll('[data-ping]').forEach(function (el) {
                el.textContent = nf.format(Number(stats['ping_' + el.dataset.ping] || 0));
            });
        }
        nota?.classList.remove('hidden');

        const puntos = Array.isArray(data.puntos) ? data.puntos : [];
        if (puntos.length === 0 && vacio) {
            vacio.textContent = 'No hay clientes con servicio activo o suspendido y coordenadas válidas. Revisá que tengan URL de ubicación o GPS en el pedido (formato coordenadas o enlace de Google Maps).';
            vacio.classList.remove('hidden');
        } else if (filtro && visibles === 0 && vacio) {
            vacio.textContent = 'No hay clientes con estado «' + (data.ping_estado_label || filtro) + '» en el mapa actual.'
                + (total > 0 ? ' Hay ' + nf.format(total) + ' cliente(s) con otro estado PPPoE.' : '');
            vacio.classList.remove('hidden');
        }

        window.__MAPA_CLIENTES_ACTIVOS_CONFIG__ = {
            apiKey: @json($googleMapsApiKey),
            puntos: puntos,
            urlDetalleClienteBase: @json(url('clientes') . '/__id__/detalle'),
            urlPingEstados: @json($urlPingEstados ?? ''),
            pingRefrescoSegundos: @json((int) ($pingRefrescoSegundos ?? 60)),
            nodoId: data.nodo_id || null,
            pingEstadoFiltro: filtro,
        };
        const script = document.createElement('script');
        script.src = @json(asset(mix('js/mapa-clientes-activos.js')));
        document.body.appendChild(script);
        espera?.remove();
    }).catch(function (err) {
        fallo(err.message || 'No se pudieron cargar los clientes.');
    });
})();
</script>
<script>
(function () {
    const btn = document.getElementById('btn-ejecutar-ping-mapa');
    const selectNodo = document.getElementById('nodo_id');
    const labelEjecutando = document.getElementById('mapa-ping-ejecutando');
    const labelResultado = document.getElementById('mapa-ping-resultado');
    if (!btn) return;

    btn.addEventListener('click', async function () {
        const url = btn.dataset.url;
        if (!url) return;

        const nodoId = selectNodo && selectNodo.value ? selectNodo.value : '';
        const scope = nodoId ? 'el nodo seleccionado' : 'todos los nodos';
        if (!confirm('¿Consultar PPPoE ahora para ' + scope + '? Puede tardar unos segundos.')) {
            return;
        }

        btn.disabled = true;
        labelEjecutando?.classList.remove('hidden');
        labelResultado?.classList.add('hidden');

        try {
            const body = new URLSearchParams();
            body.append('_token', btn.dataset.csrf || '');
            if (nodoId) body.append('nodo_id', nodoId);
            const selectPing = document.getElementById('ping_estado');
            if (selectPing && selectPing.value) body.append('ping_estado', selectPing.value);

            const response = await fetch(url, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
                body: body.toString(),
            });

            const data = await response.json().catch(function () { return {}; });
            if (!response.ok || !data.ok) {
                throw new Error(data.message || 'No se pudo consultar PPPoE.');
            }

            if (labelResultado) {
                labelResultado.textContent = data.message || 'PPPoE consultado.';
                labelResultado.classList.remove('hidden');
            }

            if (typeof window.__mapaClientesActivosRefrescarPing__ === 'function') {
                window.__mapaClientesActivosRefrescarPing__();
            } else {
                window.location.reload();
            }
        } catch (err) {
            alert(err.message || 'Error al consultar PPPoE.');
        } finally {
            btn.disabled = false;
            labelEjecutando?.classList.add('hidden');
        }
    });
})();
</script>
@endpush
@endsection
