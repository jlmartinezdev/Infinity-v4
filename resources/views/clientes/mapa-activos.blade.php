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
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                Clientes con al menos un servicio activo y coordenadas válidas (URL de ubicación, enlace corto de Maps, coordenadas del pedido o lat/lon del pedido).
            </p>
            @if(!empty($nodoSeleccionado))
                <p class="mt-1 text-sm text-purple-700 dark:text-purple-300">
                    Filtro de nodo: <strong>{{ $nodoSeleccionado->descripcion }}</strong>
                </p>
            @endif
            @if(!empty($pingEstadoFiltro))
                <p class="mt-1 text-sm text-red-700 dark:text-red-300">
                    Filtro de ping: <strong>{{ ($pingEstadoFiltroLabels ?? [])[$pingEstadoFiltro] ?? $pingEstadoFiltro }}</strong>
                </p>
            @endif
            @if(!empty($statsMapa))
                <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                    @if(!empty($pingEstadoFiltro) && ($statsMapa['en_mapa_total'] ?? 0) > ($statsMapa['en_mapa'] ?? 0))
                        Mostrando: <strong>{{ number_format($statsMapa['en_mapa'] ?? 0) }}</strong> de {{ number_format($statsMapa['en_mapa_total'] ?? 0) }} en mapa
                    @else
                        En mapa: <strong>{{ number_format($statsMapa['en_mapa'] ?? 0) }}</strong>
                    @endif
                    · Con servicio activo y ubicación: {{ number_format($statsMapa['total_candidatos'] ?? 0) }}
                    @if(($statsMapa['sin_coordenadas'] ?? 0) > 0)
                        · Sin coordenadas parseables: {{ number_format($statsMapa['sin_coordenadas']) }}
                    @endif
                </p>
                <div class="mt-2 flex flex-wrap gap-3 text-xs">
                    <span class="inline-flex items-center gap-1.5"><span class="w-3 h-3 rounded-full bg-green-600"></span> Online ({{ number_format($statsMapa['ping_online'] ?? 0) }})</span>
                    <span class="inline-flex items-center gap-1.5"><span class="w-3 h-3 rounded-full bg-red-600"></span> Sin respuesta ({{ number_format($statsMapa['ping_offline'] ?? 0) }})</span>
                    <span class="inline-flex items-center gap-1.5"><span class="w-3 h-3 rounded-full bg-orange-500"></span> Parcial ({{ number_format($statsMapa['ping_mixed'] ?? 0) }})</span>
                    <span class="inline-flex items-center gap-1.5"><span class="w-3 h-3 rounded-full bg-gray-400"></span> Sin ping ({{ number_format($statsMapa['ping_unknown'] ?? 0) }})</span>
                </div>
                <p class="mt-1 text-[11px] text-gray-400 dark:text-gray-500">Ping automático cada 7 min · CLI: <code class="text-[10px]">php artisan monitoreo:ping-servicios --nodo=ID</code></p>
            @endif
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
                    <label for="ping_estado" class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Estado ping</label>
                    <select name="ping_estado" id="ping_estado"
                            class="block min-w-[160px] rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-gray-900 dark:text-gray-100 text-sm px-3 py-2">
                        <option value="">Todos</option>
                        <option value="online" @selected(($pingEstadoFiltro ?? null) === 'online')>Online</option>
                        <option value="offline" @selected(($pingEstadoFiltro ?? null) === 'offline')>Sin respuesta</option>
                        <option value="mixed" @selected(($pingEstadoFiltro ?? null) === 'mixed')>Parcial</option>
                        <option value="unknown" @selected(($pingEstadoFiltro ?? null) === 'unknown')>Sin ping</option>
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
                    Ejecutar ping ahora
                </button>
                <span id="mapa-ping-ejecutando" class="text-xs text-gray-500 dark:text-gray-400 hidden">Ejecutando ping…</span>
                <span id="mapa-ping-ultima-actualizacion" class="text-xs text-gray-500 dark:text-gray-400 hidden"></span>
                <a href="{{ route('clientes.index') }}"
                   class="inline-flex items-center justify-center px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700 font-medium text-sm">
                    Volver a clientes
                </a>
            </div>
            <p id="mapa-ping-resultado" class="text-xs text-gray-600 dark:text-gray-400 hidden"></p>
        </div>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 overflow-hidden flex-1 min-h-[300px]">
        <div id="mapa-clientes-activos-app" class="w-full h-full min-h-[300px]"></div>
    </div>

    @if (empty($puntos))
        <div class="mt-4 px-4 py-6 text-center text-gray-500 dark:text-gray-400 bg-gray-50 dark:bg-gray-800/50 rounded-lg border border-gray-200 dark:border-gray-700">
            No hay clientes con servicio activo y coordenadas válidas. Revisá que tengan URL de ubicación o GPS en el pedido (formato coordenadas o enlace de Google Maps).
        </div>
    @elseif(!empty($pingEstadoFiltro) && ($statsMapa['en_mapa'] ?? 0) === 0)
        <div class="mt-4 px-4 py-6 text-center text-gray-500 dark:text-gray-400 bg-gray-50 dark:bg-gray-800/50 rounded-lg border border-gray-200 dark:border-gray-700">
            No hay clientes con estado «{{ ($pingEstadoFiltroLabels ?? [])[$pingEstadoFiltro] ?? $pingEstadoFiltro }}» en el mapa actual.
            @if(($statsMapa['en_mapa_total'] ?? 0) > 0)
                Hay {{ number_format($statsMapa['en_mapa_total']) }} cliente(s) con otro estado de ping.
            @endif
        </div>
    @endif
</div>

@push('scripts')
@php
    $mapaConfig = [
        'apiKey' => $googleMapsApiKey,
        'puntos' => $puntos,
        'urlDetalleClienteBase' => url('clientes') . '/__id__/detalle',
        'urlPingEstados' => $urlPingEstados ?? '',
        'pingRefrescoSegundos' => (int) ($pingRefrescoSegundos ?? 60),
        'nodoId' => $nodoIdSeleccionado ?? null,
        'pingEstadoFiltro' => $pingEstadoFiltro ?? null,
    ];
@endphp
<script>
    window.__MAPA_CLIENTES_ACTIVOS_CONFIG__ = @json($mapaConfig);
</script>
<script src="{{ asset(mix('js/mapa-clientes-activos.js')) }}"></script>
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
        if (!confirm('¿Ejecutar ping ahora para ' + scope + '? Puede tardar unos segundos.')) {
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
                throw new Error(data.message || 'No se pudo ejecutar el ping.');
            }

            if (labelResultado) {
                labelResultado.textContent = data.message || 'Ping ejecutado.';
                labelResultado.classList.remove('hidden');
            }

            if (typeof window.__mapaClientesActivosRefrescarPing__ === 'function') {
                window.__mapaClientesActivosRefrescarPing__();
            } else {
                window.location.reload();
            }
        } catch (err) {
            alert(err.message || 'Error al ejecutar ping.');
        } finally {
            btn.disabled = false;
            labelEjecutando?.classList.add('hidden');
        }
    });
})();
</script>
@endpush
@endsection
