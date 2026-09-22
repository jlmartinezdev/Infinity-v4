@extends('layouts.app')

@section('title', 'Facturación')

@section('content')
<div class="max-w-7xl mx-auto">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">Facturas electrónicas</h1>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('facturas.pdf-resumen', request()->query()) }}"
                class="inline-flex items-center gap-2 px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-gray-200 rounded-lg font-medium hover:bg-gray-300 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-gray-400 focus:ring-offset-2 dark:focus:ring-offset-gray-800 text-sm">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                Exportar PDF
            </a>
            @can('facturas.crear')
            @if(($lotesPendientesCount ?? 0) > 0)
            <form method="POST" action="{{ route('facturas.consultar-lotes') }}" class="inline"
                  onsubmit="return confirm('¿Consultar los {{ $lotesPendientesCount }} lote(s) pendiente(s) en SIFEN?');">
                @csrf
                <input type="hidden" name="todos" value="1">
                <button type="submit" class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white rounded-lg font-medium hover:bg-indigo-700 text-sm">
                    Consultar lotes ({{ $lotesPendientesCount }})
                </button>
            </form>
            @endif
            <a href="{{ route('facturas.create-manual') }}" class="inline-flex items-center px-4 py-2 bg-gray-600 text-white rounded-lg font-medium hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800">Datos manuales</a>
            <a href="{{ route('facturas.create') }}" class="inline-flex items-center px-4 py-2 bg-purple-600 text-white rounded-lg font-medium hover:bg-purple-700 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800">Nueva factura</a>
            @else
            <a href="{{ route('facturas.create-manual') }}" class="inline-flex items-center px-4 py-2 bg-gray-600 text-white rounded-lg font-medium hover:bg-gray-700">Datos manuales</a>
            <a href="{{ route('facturas.create') }}" class="inline-flex items-center px-4 py-2 bg-purple-600 text-white rounded-lg font-medium hover:bg-purple-700">Nueva factura</a>
            @endcan
        </div>
    </div>

    @if(session('success'))
        <div class="mb-4 p-4 rounded-lg bg-green-100 dark:bg-green-900/30 text-green-800 dark:text-green-200 border border-green-200 dark:border-green-800 text-sm">{{ session('success') }}</div>
    @endif
    @if(session('warning'))
        <div class="mb-4 p-4 rounded-lg bg-amber-100 dark:bg-amber-900/30 text-amber-900 dark:text-amber-200 border border-amber-200 dark:border-amber-800 text-sm">{{ session('warning') }}</div>
    @endif
    @if(session('error'))
        <div class="mb-4 p-4 rounded-lg bg-red-100 dark:bg-red-900/30 text-red-800 dark:text-red-200 border border-red-200 dark:border-red-800 text-sm">{{ session('error') }}</div>
    @endif

    <div class="mb-6">
        <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-3 mb-4">
            <div>
                <h2 class="text-sm font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wide">Resumen del mes</h2>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5 capitalize">Facturas emitidas · {{ $mesDashboardLabel }}</p>
            </div>
            <form method="GET" action="{{ route('facturas.index') }}" class="flex flex-wrap items-end gap-2">
                @foreach (request()->except(['mes', 'page']) as $key => $value)
                    @if(is_array($value))
                        @foreach ($value as $v)
                            <input type="hidden" name="{{ $key }}[]" value="{{ $v }}">
                        @endforeach
                    @else
                        <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                    @endif
                @endforeach
                <div>
                    <label for="mes" class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-0.5">Mes</label>
                    <input type="month" name="mes" id="mes" value="{{ $mesDashboard->format('Y-m') }}"
                           class="px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 text-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                </div>
                <button type="submit" class="px-3 py-2 bg-gray-200 dark:bg-gray-600 text-gray-800 dark:text-gray-100 rounded-lg text-sm font-medium hover:bg-gray-300 dark:hover:bg-gray-500">Ver</button>
            </form>
        </div>

        @php
            $esAdminTope = (bool) auth()->user()?->esAdministrador();
            $puedeVerMontoFacturado = $esAdminTope;
            $tope = $limiteMes ?? [
                'mes' => $mesDashboard->format('Y-m'),
                'limite' => null,
                'usado' => (float) ($statsEmitidasMes->monto_total ?? 0),
                'restante' => null,
                'porcentaje' => null,
                'excedido' => false,
                'sin_tope' => true,
            ];
            $pctTope = min(100, (float) ($tope['porcentaje'] ?? 0));
            $bordeTope = $tope['excedido']
                ? 'border-red-200 dark:border-red-800/50'
                : ($tope['sin_tope'] ? 'border-gray-200 dark:border-gray-700' : 'border-purple-200 dark:border-purple-800/50');
            $colorBarra = $tope['excedido'] ? '#dc2626' : ($pctTope >= 80 ? '#d97706' : '#7c3aed');
            $topeSwal = [
                'mes' => $tope['mes'],
                'label' => $mesDashboardLabel,
                'limite' => $tope['limite'],
                'limites' => $limitesMesMapa ?? [],
            ];
        @endphp

        <div class="grid grid-cols-1 sm:grid-cols-2 {{ $puedeVerMontoFacturado ? 'lg:grid-cols-5' : 'lg:grid-cols-3' }} gap-4">
            @if($puedeVerMontoFacturado)
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-purple-200 dark:border-purple-800/50 p-4 shadow-sm">
                <p class="text-xs font-medium text-purple-700 dark:text-purple-400 uppercase tracking-wide">Monto emitido</p>
                <p class="text-2xl font-bold text-purple-700 dark:text-purple-300 mt-1">
                    {{ number_format((float) $statsEmitidasMes->monto_total, 0, ',', '.') }}
                    <span class="text-sm font-semibold">PYG</span>
                </p>
            </div>
            @endif
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-green-200 dark:border-green-800/50 p-4 shadow-sm">
                <p class="text-xs font-medium text-green-700 dark:text-green-400 uppercase tracking-wide">Facturas emitidas</p>
                <p class="text-2xl font-bold text-green-700 dark:text-green-300 mt-1">{{ number_format((int) $statsEmitidasMes->cantidad, 0, ',', '.') }}</p>
            </div>
            @if($puedeVerMontoFacturado)
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-blue-200 dark:border-blue-800/50 p-4 shadow-sm">
                <p class="text-xs font-medium text-blue-700 dark:text-blue-400 uppercase tracking-wide">IVA emitido</p>
                <p class="text-2xl font-bold text-blue-700 dark:text-blue-300 mt-1">
                    {{ number_format((float) $statsEmitidasMes->monto_iva, 0, ',', '.') }}
                    <span class="text-sm font-semibold">PYG</span>
                </p>
            </div>
            @endif
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-amber-200 dark:border-amber-800/50 p-4 shadow-sm">
                <p class="text-xs font-medium text-amber-700 dark:text-amber-400 uppercase tracking-wide">Borradores del mes</p>
                <p class="text-2xl font-bold text-amber-700 dark:text-amber-300 mt-1">{{ number_format($borradoresMes, 0, ',', '.') }}</p>
                @if($borradoresMes > 0 && $puedeVerMontoFacturado)
                    <p class="text-xs text-amber-600 dark:text-amber-400 mt-1">{{ number_format($montoBorradoresMes, 0, ',', '.') }} PYG pendientes</p>
                @endif
            </div>
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-indigo-200 dark:border-indigo-800/50 p-4 shadow-sm">
                <p class="text-xs font-medium text-indigo-700 dark:text-indigo-400 uppercase tracking-wide">Lotes SIFEN</p>
                <p class="text-2xl font-bold text-indigo-700 dark:text-indigo-300 mt-1">{{ number_format($lotesPendientesCount ?? 0, 0, ',', '.') }}</p>
                @if(($lotesPendientesCount ?? 0) > 0)
                    <a href="{{ route('facturas.index', ['lote_pendiente' => 1]) }}" class="text-xs text-indigo-600 dark:text-indigo-400 hover:underline mt-1 inline-block">Ver pendientes</a>
                @else
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Sin pendientes</p>
                @endif
            </div>
        </div>

        <div class="mt-4 bg-white dark:bg-gray-800 rounded-xl border {{ $bordeTope }} p-4 shadow-sm">
            <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3">
                <div class="min-w-0 flex-1">
                    <p class="text-xs font-medium uppercase tracking-wide {{ $tope['excedido'] ? 'text-red-700 dark:text-red-400' : 'text-gray-600 dark:text-gray-400' }}">Tope del mes</p>
                    @if($tope['sin_tope'])
                        <p class="text-lg font-bold text-gray-900 dark:text-gray-100 mt-1">Sin tope</p>
                        @if($puedeVerMontoFacturado)
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                                Emitido: {{ number_format((float) $tope['usado'], 0, ',', '.') }} PYG · se puede facturar sin límite de monto.
                            </p>
                        @else
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Este mes no tiene límite de facturación.</p>
                        @endif
                    @elseif($puedeVerMontoFacturado)
                        <p class="text-lg font-bold text-gray-900 dark:text-gray-100 mt-1">
                            {{ number_format((float) $tope['usado'], 0, ',', '.') }}
                            <span class="text-sm font-semibold text-gray-500 dark:text-gray-400">/</span>
                            {{ number_format((float) $tope['limite'], 0, ',', '.') }}
                            <span class="text-sm font-semibold">PYG</span>
                        </p>
                        <p class="text-xs mt-0.5 {{ $tope['excedido'] ? 'text-red-600 dark:text-red-400' : 'text-gray-500 dark:text-gray-400' }}">
                            @if($tope['excedido'])
                                Superó el tope por {{ number_format((float) $tope['usado'] - (float) $tope['limite'], 0, ',', '.') }} PYG.
                            @else
                                Restan {{ number_format((float) $tope['restante'], 0, ',', '.') }} PYG para emitir en {{ $mesDashboardLabel }}.
                            @endif
                        </p>
                        <div class="h-2 rounded-full bg-gray-200 dark:bg-gray-700 overflow-hidden mt-3" aria-hidden="true">
                            <div style="width: {{ $pctTope }}%; background: {{ $colorBarra }}; height: 100%;"></div>
                        </div>
                    @else
                        <div class="flex items-end justify-between gap-3 mt-1">
                            <p class="text-lg font-bold text-gray-900 dark:text-gray-100">
                                {{ $tope['excedido'] ? 'Tope alcanzado' : number_format($pctTope, 0).'%' }}
                            </p>
                            <p class="text-xs pb-0.5 {{ $tope['excedido'] ? 'text-red-600 dark:text-red-400' : ($pctTope >= 80 ? 'text-amber-600 dark:text-amber-400' : 'text-gray-500 dark:text-gray-400') }}">
                                @if($tope['excedido'])
                                    No queda cupo en {{ $mesDashboardLabel }}.
                                @elseif($pctTope >= 80)
                                    Cerca del tope de {{ $mesDashboardLabel }}.
                                @else
                                    Cupo disponible en {{ $mesDashboardLabel }}.
                                @endif
                            </p>
                        </div>
                        <div class="h-2 rounded-full bg-gray-200 dark:bg-gray-700 overflow-hidden mt-3" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ (int) round($pctTope) }}" aria-label="Uso del tope mensual">
                            <div style="width: {{ $pctTope }}%; background: {{ $colorBarra }}; height: 100%;"></div>
                        </div>
                    @endif
                </div>
                @if($esAdminTope)
                    <button type="button" id="btn-tope-mes"
                            class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg text-sm font-medium border border-gray-300 dark:border-gray-600 text-gray-800 dark:text-gray-200 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 shrink-0"
                            data-tope='@json($topeSwal)'>
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                        </svg>
                        {{ $tope['sin_tope'] ? 'Establecer tope' : 'Cambiar tope' }}
                    </button>
                @endif
            </div>
        </div>
        @if($esAdminTope)
            <form id="form-limite-mes" method="POST" action="{{ route('facturas.limite-mes') }}" class="hidden">
                @csrf
                <input type="hidden" name="mes" id="input-mes-limite" value="{{ $tope['mes'] }}">
                <input type="hidden" name="monto_limite" id="input-monto-limite" value="">
            </form>
        @endif
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 overflow-hidden">
        @php
            $campoFiltro = 'w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 text-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100';
            $etiquetaFiltro = 'block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1';
            $nombreClienteFiltro = ($clienteFiltro ?? null)
                ? trim($clienteFiltro->nombre.' '.$clienteFiltro->apellido)
                : '';
            $filtrosMenu = collect([
                request('estado'),
                request('desde'),
                request('hasta'),
                request('aprobacion_desde'),
                request('aprobacion_hasta'),
                request()->boolean('lote_pendiente') ? '1' : null,
            ])->filter(fn ($v) => filled($v))->count();
            $filtrosActivos = $filtrosMenu > 0 || request()->filled('cliente_id');
        @endphp
        <form id="form-filtros-fe" method="GET" action="{{ route('facturas.index') }}"
              class="p-4 border-b border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-700/50"
              data-buscar-url="{{ route('clientes.buscar') }}">
            @if(request('mes'))
                <input type="hidden" name="mes" value="{{ request('mes') }}">
            @endif
            <input type="hidden" name="cliente_id" id="filtro-cliente-id" value="{{ request('cliente_id') }}">

            <div class="flex flex-col sm:flex-row sm:items-center gap-2">
                <div id="filtro-cliente-root" class="relative min-w-0 flex-1">
                    <label for="filtro-cliente-q" class="sr-only">Buscar cliente</label>
                    <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M17 11a6 6 0 11-12 0 6 6 0 0112 0z"/>
                        </svg>
                    </span>
                    <input type="search" id="filtro-cliente-q" autocomplete="off"
                           value="{{ $nombreClienteFiltro }}"
                           placeholder="Buscar cliente…"
                           class="w-full h-10 pl-9 pr-8 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm text-gray-900 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20"
                           aria-autocomplete="list" aria-controls="filtro-cliente-resultados" aria-expanded="false" aria-haspopup="listbox">
                    <button type="button" id="filtro-cliente-limpiar"
                            class="{{ $nombreClienteFiltro === '' ? 'hidden' : '' }} absolute inset-y-0 right-1 px-2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200"
                            title="Quitar cliente" aria-label="Quitar cliente">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                    <ul id="filtro-cliente-resultados"
                        class="hidden fixed z-50 max-h-72 overflow-auto rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-800 shadow-lg py-1"
                        role="listbox"></ul>
                </div>

                <div class="relative shrink-0 flex items-center gap-2">
                    <button type="button" id="filtro-menu-btn"
                            class="inline-flex items-center gap-2 h-10 px-3 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-600"
                            aria-haspopup="dialog" aria-expanded="false" aria-controls="filtro-menu-panel">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4h18M6 12h12M10 20h4"/>
                        </svg>
                        Filtros
                        <span id="filtro-menu-badge" class="{{ $filtrosMenu ? '' : 'hidden' }} inline-flex items-center justify-center w-5 h-5 rounded-full bg-purple-600 text-white text-xs leading-5">{{ $filtrosMenu }}</span>
                    </button>
                    @if($filtrosActivos)
                        <a href="{{ route('facturas.index', array_filter(['mes' => request('mes')])) }}"
                           class="h-10 inline-flex items-center px-3 rounded-lg text-sm font-medium text-gray-600 dark:text-gray-300 hover:bg-gray-200/80 dark:hover:bg-gray-600/60">
                            Limpiar
                        </a>
                    @endif

                    <div id="filtro-menu-panel"
                         class="hidden fixed z-50 w-80 max-h-96 overflow-y-auto p-4 rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-800 shadow-lg"
                         role="dialog" aria-label="Filtros del listado" aria-hidden="true">
                        <div class="space-y-4">
                            <div>
                                <label for="filtro-estado" class="{{ $etiquetaFiltro }}">Estado</label>
                                <select id="filtro-estado" name="estado" form="form-filtros-fe" class="{{ $campoFiltro }}">
                                    <option value="">Todos</option>
                                    @foreach (App\Models\Factura::estados() as $key => $label)
                                        <option value="{{ $key }}" {{ request('estado') == $key ? 'selected' : '' }}>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <fieldset>
                                <legend class="text-xs font-semibold text-gray-600 dark:text-gray-300 mb-2">Emisión</legend>
                                <div class="grid grid-cols-2 gap-3">
                                    <div>
                                        <label for="filtro-emision-desde" class="{{ $etiquetaFiltro }}">Desde</label>
                                        <input id="filtro-emision-desde" type="date" name="desde" form="form-filtros-fe" value="{{ request('desde') }}" class="{{ $campoFiltro }}">
                                    </div>
                                    <div>
                                        <label for="filtro-emision-hasta" class="{{ $etiquetaFiltro }}">Hasta</label>
                                        <input id="filtro-emision-hasta" type="date" name="hasta" form="form-filtros-fe" value="{{ request('hasta') }}" class="{{ $campoFiltro }}">
                                    </div>
                                </div>
                            </fieldset>
                            <fieldset>
                                <legend class="text-xs font-semibold text-gray-600 dark:text-gray-300 mb-2">Aprobación</legend>
                                <div class="grid grid-cols-2 gap-3">
                                    <div>
                                        <label for="filtro-aprobacion-desde" class="{{ $etiquetaFiltro }}">Desde</label>
                                        <input id="filtro-aprobacion-desde" type="date" name="aprobacion_desde" form="form-filtros-fe" value="{{ request('aprobacion_desde') }}" class="{{ $campoFiltro }}">
                                    </div>
                                    <div>
                                        <label for="filtro-aprobacion-hasta" class="{{ $etiquetaFiltro }}">Hasta</label>
                                        <input id="filtro-aprobacion-hasta" type="date" name="aprobacion_hasta" form="form-filtros-fe" value="{{ request('aprobacion_hasta') }}" class="{{ $campoFiltro }}">
                                    </div>
                                </div>
                                <p class="mt-1.5 text-xs text-gray-500 dark:text-gray-400">Una sola fecha de aprobación filtra solo ese día.</p>
                            </fieldset>
                            <label class="inline-flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                                <input type="checkbox" name="lote_pendiente" value="1" form="form-filtros-fe" {{ request()->boolean('lote_pendiente') ? 'checked' : '' }}
                                       class="rounded border-gray-300 dark:border-gray-600 text-indigo-600 focus:ring-indigo-500">
                                Solo lotes pendientes
                            </label>
                            <button type="submit" form="form-filtros-fe" class="w-full px-4 py-2 bg-purple-600 text-white rounded-lg font-medium hover:bg-purple-700 focus:outline-none focus:ring-2 focus:ring-purple-500/40 text-sm">Aplicar filtros</button>
                        </div>
                    </div>
                </div>
            </div>
        </form>

        <div id="barra-lotes" class="hidden sticky top-0 z-10 px-4 py-3 border-b border-indigo-200 dark:border-indigo-800 bg-indigo-50 dark:bg-indigo-900/30 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <p class="text-sm text-indigo-900 dark:text-indigo-100">
                <span id="contador-lotes" class="font-semibold">0</span> factura(s) seleccionada(s)
            </p>
            <button type="submit" form="form-consultar-lotes" class="px-4 py-2 text-sm font-medium rounded-lg text-white bg-indigo-600 hover:bg-indigo-700">
                Consultar lotes seleccionados
            </button>
        </div>

        <form method="POST" action="{{ route('facturas.consultar-lotes') }}" id="form-consultar-lotes"
              onsubmit="return confirmarConsultaLotes(event);">
            @csrf
        </form>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-600">
                <thead class="bg-gray-50 dark:bg-gray-700/50">
                    <tr>
                        <th class="px-4 py-3 text-left w-10">
                            <input type="checkbox" id="seleccionar-lotes"
                                   class="rounded border-gray-300 dark:border-gray-600 text-indigo-600 focus:ring-indigo-500"
                                   title="Seleccionar pendientes de esta página">
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">#</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Cliente</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Número</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Emisión</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Aprobación</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Tipo</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Estado</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Total</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Acciones</th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-600">
                    @forelse ($facturas as $f)
                        @php
                            $pendienteLote = $f->lotePendienteSifen();
                            $enCola = $f->enColaSifen();
                        @endphp
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 {{ $pendienteLote || $enCola ? 'bg-indigo-50/50 dark:bg-indigo-900/10' : '' }}">
                            <td class="px-4 py-3">
                                @if($pendienteLote && $f->set_estado_envio !== 'consultando')
                                    <input type="checkbox" name="factura_ids[]" value="{{ $f->id }}" form="form-consultar-lotes"
                                           class="chk-lote rounded border-gray-300 dark:border-gray-600 text-indigo-600 focus:ring-indigo-500">
                                @endif
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-900 dark:text-gray-100">{{ $f->id }}</td>
                            <td class="px-4 py-3 text-sm">
                                @if($f->esOcasional())
                                    <span class="text-gray-900 dark:text-gray-100">{{ $f->receptorNombreCompleto() }}</span>
                                    <span class="block text-xs text-purple-600 dark:text-purple-400">Ocasional</span>
                                @elseif($f->cliente)
                                    <a href="{{ route('clientes.edit', $f->cliente) }}" class="text-purple-600 dark:text-purple-400 hover:underline">{{ $f->cliente->nombre }} {{ $f->cliente->apellido }}</a>
                                @else
                                    —
                                @endif
                            </td>
                            <td class="px-4 py-3 text-sm font-mono text-gray-900 dark:text-gray-100">{{ $f->numero_completo ?? '—' }}</td>
                            <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-300">{{ $f->fecha_emision->format('d/m/Y') }}</td>
                            <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-300 whitespace-nowrap">{{ $f->set_fecha_autorizacion?->format('d/m/Y H:i') ?? '—' }}</td>
                            <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-300">{{ App\Models\Factura::tiposDocumento()[$f->tipo_documento] ?? $f->tipo_documento }}</td>
                            <td class="px-4 py-3">
                                @php $estados = App\Models\Factura::estados(); @endphp
                                <span class="inline-flex px-2 py-0.5 rounded text-xs font-medium
                                    @if($f->estado === 'emitida') bg-green-100 dark:bg-green-900/30 text-green-800 dark:text-green-300
                                    @elseif($f->estado === 'anulada') bg-red-100 dark:bg-red-900/30 text-red-800 dark:text-red-300
                                    @else bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-300 @endif">
                                    {{ $estados[$f->estado] ?? $f->estado }}
                                </span>
                                @if($f->set_estado_envio === 'en_cola')
                                    <span class="mt-1 inline-flex px-2 py-0.5 rounded text-xs font-medium bg-amber-100 dark:bg-amber-900/40 text-amber-800 dark:text-amber-300">
                                        Emitiendo…
                                    </span>
                                @elseif($f->set_estado_envio === 'consultando')
                                    <span class="mt-1 inline-flex px-2 py-0.5 rounded text-xs font-medium bg-amber-100 dark:bg-amber-900/40 text-amber-800 dark:text-amber-300">
                                        Consultando lote…
                                    </span>
                                @elseif($pendienteLote)
                                    <span class="mt-1 inline-flex px-2 py-0.5 rounded text-xs font-medium bg-indigo-100 dark:bg-indigo-900/40 text-indigo-800 dark:text-indigo-300">
                                        Lote pendiente
                                    </span>
                                @elseif($f->set_estado_envio === 'rechazado')
                                    @php $rechazo = $f->respuestaSifenResumen(); @endphp
                                    <span class="mt-1 inline-flex flex-col items-start gap-0.5">
                                        <span class="inline-flex px-2 py-0.5 rounded text-xs font-medium bg-red-100 dark:bg-red-900/40 text-red-800 dark:text-red-300">
                                            SIFEN rechazó{{ !empty($rechazo['codigo']) ? ' '.$rechazo['codigo'] : '' }}
                                        </span>
                                        @if(!empty($rechazo['mensaje']))
                                            <span class="max-w-[14rem] text-[10px] leading-tight text-red-700/90 dark:text-red-300/90 line-clamp-2" title="{{ $rechazo['mensaje'] }}">
                                                {{ $rechazo['mensaje'] }}
                                            </span>
                                        @endif
                                    </span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-sm text-right font-medium text-gray-900 dark:text-gray-100">{{ number_format($f->total, 0, ',', '.') }} {{ $f->moneda }}</td>
                            <td class="px-4 py-3 text-right">
                                @php
                                    $esAdminFe = (bool) auth()->user()?->esAdministrador();
                                    $menuFe = [];
                                    if ($pendienteLote && $f->set_estado_envio !== 'consultando') {
                                        $menuFe[] = [
                                            'type' => 'lote',
                                            'label' => 'Consultar lote',
                                            'url' => route('facturas.consultar-lote', $f),
                                        ];
                                    } elseif ($f->estado === 'borrador' && ! $enCola && ! $pendienteLote) {
                                        $menuFe[] = [
                                            'type' => 'link',
                                            'label' => 'Editar',
                                            'url' => route('facturas.edit', $f),
                                        ];
                                    }
                                    if ($esAdminFe && $f->puedeCancelarPorEvento()) {
                                        $menuFe[] = [
                                            'type' => 'cancelar',
                                            'label' => 'Cancelar',
                                            'url' => route('facturas.cancelar', $f),
                                            'numero' => $f->numero_completo ?? '#'.$f->id,
                                            'limite' => $f->fechaLimiteCancelacionEvento()?->timezone(config('app.timezone'))->format('d/m/Y H:i'),
                                        ];
                                    }
                                    if ($esAdminFe && $f->puedePrepararNotaCredito()) {
                                        $menuFe[] = [
                                            'type' => 'nc',
                                            'label' => 'Nota de crédito',
                                            'url' => route('facturas.nota-credito', $f),
                                            'numero' => $f->numero_completo ?? '#'.$f->id,
                                        ];
                                    }
                                @endphp
                                <div class="inline-flex items-center justify-end gap-0.5">
                                    <a href="{{ route('facturas.show', $f) }}"
                                       class="p-2 rounded-lg text-purple-600 dark:text-purple-400 hover:bg-purple-50 dark:hover:bg-purple-900/30 transition-colors"
                                       title="Ver" aria-label="Ver factura {{ $f->numero_completo ?? '#'.$f->id }}">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                        </svg>
                                    </a>
                                    @if($menuFe !== [])
                                        <button type="button"
                                                class="js-fe-acciones-menu p-2 rounded-lg text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors"
                                                title="Más acciones"
                                                aria-haspopup="menu"
                                                aria-expanded="false"
                                                data-menu-b64="{{ base64_encode(json_encode($menuFe, JSON_UNESCAPED_UNICODE)) }}">
                                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                                <path d="M12 8c1.1 0 2-.9 2-2s-.9-2-2-2-2 .9-2 2 .9 2 2 2zm0 2c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2zm0 6c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2z"/>
                                            </svg>
                                        </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">No hay facturas. <a href="{{ route('facturas.create') }}" class="text-purple-600 dark:text-purple-400 hover:underline">Crear una</a>.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($facturas->hasPages())
            <div class="px-4 py-3 border-t border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-700/50">{{ $facturas->links() }}</div>
        @endif
    </div>
</div>

@include('facturas._sifen-acciones-script')

<script>
(function () {
    const btn = document.getElementById('btn-tope-mes');
    const form = document.getElementById('form-limite-mes');
    const inputMes = document.getElementById('input-mes-limite');
    const inputMonto = document.getElementById('input-monto-limite');
    if (!btn || !form || !inputMes || !inputMonto) return;

    function theme() {
        return (window.infinitySwalTheme && window.infinitySwalTheme()) || {};
    }

    function parseMonto(raw) {
        const s = String(raw || '').trim().replace(/\s/g, '').replace(/\./g, '').replace(',', '.');
        if (s === '') return null;
        const n = Number(s);
        return Number.isFinite(n) ? n : NaN;
    }

    function leerCfg() {
        try {
            return JSON.parse(btn.getAttribute('data-tope') || '{}');
        } catch (err) {
            const ta = document.createElement('textarea');
            ta.innerHTML = btn.getAttribute('data-tope') || '{}';
            return JSON.parse(ta.value || '{}');
        }
    }

    function limiteDeMes(cfg, mes) {
        const map = cfg.limites || {};
        if (map[mes] != null && Number(map[mes]) > 0) {
            return Number(map[mes]);
        }
        if (mes === cfg.mes && cfg.limite) {
            return Number(cfg.limite);
        }
        return null;
    }

    function enviar(mes, monto) {
        inputMes.value = mes;
        inputMonto.value = monto == null ? '' : String(Math.round(monto));
        form.submit();
    }

    btn.addEventListener('click', function () {
        const cfg = leerCfg();
        const mesInicial = cfg.mes || '';

        if (typeof Swal === 'undefined') {
            const mes = window.prompt('Mes del tope (AAAA-MM):', mesInicial);
            if (mes === null) return;
            if (!/^\d{4}-\d{2}$/.test(String(mes).trim())) {
                window.alert('Mes inválido. Use AAAA-MM.');
                return;
            }
            const valor = window.prompt('Tope en PYG (vacío para quitar):', limiteDeMes(cfg, mes) || '');
            if (valor === null) return;
            const n = parseMonto(valor);
            enviar(String(mes).trim(), n == null ? null : n);
            return;
        }

        let mesElegido = mesInicial;

        Swal.fire(Object.assign({
            title: 'Establecer tope mensual',
            html:
                '<div class="swal-tope-form">' +
                    '<p class="swal-tope-hint">El tope se aplica a la fecha de emisión. Elija el mes que sigue facturando, aunque sea un período anterior.</p>' +
                    '<label for="swal-tope-mes">Mes</label>' +
                    '<input type="month" id="swal-tope-mes" class="swal2-input" value="' + mesInicial + '">' +
                    '<label for="swal-tope-monto">Monto máximo (PYG)</label>' +
                    '<input type="text" id="swal-tope-monto" class="swal2-input" inputmode="numeric" placeholder="Ej: 50000000" value="' +
                        (limiteDeMes(cfg, mesInicial) ? String(Math.round(limiteDeMes(cfg, mesInicial))) : '') + '">' +
                    '<p class="swal-tope-hint">Deje el monto vacío para quitar el tope de ese mes.</p>' +
                '</div>',
            showCancelButton: true,
            showDenyButton: !!limiteDeMes(cfg, mesInicial),
            confirmButtonText: 'Guardar',
            denyButtonText: 'Quitar tope',
            cancelButtonText: 'Volver',
            confirmButtonColor: '#7c3aed',
            denyButtonColor: '#dc2626',
            focusConfirm: false,
            didOpen: function () {
                const mesEl = document.getElementById('swal-tope-mes');
                const montoEl = document.getElementById('swal-tope-monto');
                if (!mesEl || !montoEl) return;

                function syncMes() {
                    mesElegido = mesEl.value;
                    const actual = limiteDeMes(cfg, mesElegido);
                    montoEl.value = actual ? String(Math.round(actual)) : '';
                    const deny = typeof Swal.getDenyButton === 'function' ? Swal.getDenyButton() : null;
                    if (deny) deny.style.display = actual ? 'inline-flex' : 'none';
                }

                mesEl.addEventListener('change', syncMes);
                mesEl.addEventListener('input', syncMes);
            },
            preConfirm: function () {
                const mesEl = document.getElementById('swal-tope-mes');
                const montoEl = document.getElementById('swal-tope-monto');
                const mes = mesEl ? String(mesEl.value || '').trim() : '';
                if (!/^\d{4}-\d{2}$/.test(mes)) {
                    Swal.showValidationMessage('Seleccione el mes del tope.');
                    return false;
                }
                mesElegido = mes;
                const raw = montoEl ? montoEl.value : '';
                if (raw === '' || raw == null) {
                    return { mes: mes, monto: null };
                }
                const n = parseMonto(raw);
                if (!Number.isFinite(n) || n < 1) {
                    Swal.showValidationMessage('Ingrese un monto mayor a 0, o deje vacío para quitar el tope.');
                    return false;
                }
                return { mes: mes, monto: n };
            },
        }, theme())).then(function (result) {
            if (result.isDenied) {
                enviar(mesElegido, null);
                return;
            }
            if (!result.isConfirmed || !result.value) return;
            enviar(result.value.mes, result.value.monto);
        });
    });
})();
</script>
<style>
.swal-tope-form { text-align: left; }
.swal-tope-form label {
    display: block;
    font-size: 12px;
    font-weight: 600;
    margin: 10px 0 4px;
    color: #374151;
}
.swal-tope-form .swal2-input { margin: 0; width: 100%; box-sizing: border-box; }
.swal-tope-hint { font-size: 12px; margin: 0 0 8px; color: #6b7280; line-height: 1.4; }
html.dark .swal-tope-form label { color: #e5e7eb; }
html.dark .swal-tope-hint { color: #9ca3af; }
html.dark .swal2-input[type="month"]::-webkit-calendar-picker-indicator { filter: invert(1); }
</style>

<script>
(function () {
    const form = document.getElementById('form-filtros-fe');
    if (!form) return;

    const buscarUrl = form.getAttribute('data-buscar-url');
    const inputQ = document.getElementById('filtro-cliente-q');
    const inputId = document.getElementById('filtro-cliente-id');
    const btnLimpiar = document.getElementById('filtro-cliente-limpiar');
    const lista = document.getElementById('filtro-cliente-resultados');
    const menuBtn = document.getElementById('filtro-menu-btn');
    const menuPanel = document.getElementById('filtro-menu-panel');
    let timer = null;
    let items = [];
    let activo = -1;
    let nombreSeleccionado = (inputQ && inputQ.value) ? inputQ.value.trim() : '';

    if (lista) document.body.appendChild(lista);
    if (menuPanel) document.body.appendChild(menuPanel);

    function csrf() {
        const m = document.querySelector('meta[name="csrf-token"]');
        return m ? m.getAttribute('content') : '';
    }

    function posicionarLista() {
        if (!lista || !inputQ) return;
        const r = inputQ.getBoundingClientRect();
        lista.style.left = r.left + 'px';
        lista.style.top = (r.bottom + 4) + 'px';
        lista.style.width = r.width + 'px';
    }

    function cerrarLista() {
        if (!lista || !inputQ) return;
        lista.classList.add('hidden');
        lista.innerHTML = '';
        items = [];
        activo = -1;
        inputQ.setAttribute('aria-expanded', 'false');
    }

    function marcarActivo() {
        lista.querySelectorAll('[role="option"]').forEach(function (el, i) {
            el.classList.toggle('bg-purple-50', i === activo);
            el.classList.toggle('dark:bg-gray-700', i === activo);
        });
    }

    function elegir(item) {
        if (!item) return;
        inputId.value = item.cliente_id;
        nombreSeleccionado = ((item.nombre || '') + ' ' + (item.apellido || '')).trim();
        inputQ.value = nombreSeleccionado;
        if (btnLimpiar) btnLimpiar.classList.remove('hidden');
        cerrarLista();
        form.submit();
    }

    function render(data) {
        lista.innerHTML = '';
        items = data || [];
        activo = items.length ? 0 : -1;
        if (!items.length) {
            const vacio = document.createElement('li');
            vacio.className = 'px-3 py-2 text-sm text-gray-500 dark:text-gray-400';
            vacio.textContent = 'Sin coincidencias';
            lista.appendChild(vacio);
        } else {
            items.forEach(function (item, i) {
                const li = document.createElement('li');
                li.setAttribute('role', 'option');
                li.className = 'px-3 py-2 cursor-pointer text-sm text-gray-800 dark:text-gray-100 hover:bg-purple-50 dark:hover:bg-gray-700';
                const nombre = document.createElement('div');
                nombre.className = 'font-medium truncate';
                nombre.textContent = ((item.nombre || '') + ' ' + (item.apellido || '')).trim();
                const meta = document.createElement('div');
                meta.className = 'text-xs text-gray-500 dark:text-gray-400 truncate';
                meta.textContent = item.cedula || '';
                li.appendChild(nombre);
                li.appendChild(meta);
                li.addEventListener('mousedown', function (e) {
                    e.preventDefault();
                    elegir(item);
                });
                if (i === 0) {
                    li.classList.add('bg-purple-50', 'dark:bg-gray-700');
                }
                lista.appendChild(li);
            });
        }
        posicionarLista();
        lista.classList.remove('hidden');
        inputQ.setAttribute('aria-expanded', 'true');
    }

    function buscar(q) {
        fetch(buscarUrl + '?q=' + encodeURIComponent(q), {
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf(), 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin'
        }).then(function (r) { return r.ok ? r.json() : []; })
          .then(render)
          .catch(function () { render([]); });
    }

    if (inputQ) {
        inputQ.addEventListener('input', function () {
            const q = inputQ.value.trim();
            if (btnLimpiar) btnLimpiar.classList.toggle('hidden', q === '' && !inputId.value);
            if (nombreSeleccionado && q !== nombreSeleccionado) {
                inputId.value = '';
                nombreSeleccionado = '';
            }
            clearTimeout(timer);
            if (q.length < 2) {
                cerrarLista();
                return;
            }
            timer = setTimeout(function () { buscar(q); }, 200);
        });
        inputQ.addEventListener('keydown', function (e) {
            if (lista.classList.contains('hidden')) return;
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                activo = Math.min(items.length - 1, activo + 1);
                marcarActivo();
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                activo = Math.max(0, activo - 1);
                marcarActivo();
            } else if (e.key === 'Enter' && activo >= 0 && items[activo]) {
                e.preventDefault();
                elegir(items[activo]);
            } else if (e.key === 'Escape') {
                cerrarLista();
            }
        });
        inputQ.addEventListener('blur', function () {
            setTimeout(cerrarLista, 150);
        });
    }

    if (btnLimpiar) {
        btnLimpiar.addEventListener('click', function () {
            inputId.value = '';
            inputQ.value = '';
            nombreSeleccionado = '';
            btnLimpiar.classList.add('hidden');
            cerrarLista();
            form.submit();
        });
    }

    function posicionarMenu() {
        if (!menuBtn || !menuPanel) return;
        const r = menuBtn.getBoundingClientRect();
        const ancho = Math.min(320, window.innerWidth - 16);
        let left = r.right - ancho;
        if (left < 8) left = 8;
        menuPanel.style.left = left + 'px';
        menuPanel.style.top = (r.bottom + 6) + 'px';
        menuPanel.style.width = ancho + 'px';
        menuPanel.style.maxHeight = Math.max(200, window.innerHeight - r.bottom - 16) + 'px';
    }

    function abrirMenu() {
        menuPanel.classList.remove('hidden');
        menuPanel.setAttribute('aria-hidden', 'false');
        menuBtn.setAttribute('aria-expanded', 'true');
        posicionarMenu();
    }

    function cerrarMenu() {
        menuPanel.classList.add('hidden');
        menuPanel.setAttribute('aria-hidden', 'true');
        menuBtn.setAttribute('aria-expanded', 'false');
    }

    if (menuBtn && menuPanel) {
        menuBtn.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            if (menuPanel.classList.contains('hidden')) abrirMenu();
            else cerrarMenu();
        });
        document.addEventListener('pointerdown', function (e) {
            if (menuPanel.classList.contains('hidden')) return;
            if (menuPanel.contains(e.target) || menuBtn.contains(e.target)) return;
            cerrarMenu();
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && !menuPanel.classList.contains('hidden')) {
                cerrarMenu();
                menuBtn.focus();
            }
        });
        window.addEventListener('resize', function () {
            if (!menuPanel.classList.contains('hidden')) posicionarMenu();
            if (lista && !lista.classList.contains('hidden')) posicionarLista();
        });
        window.addEventListener('scroll', function () {
            if (!menuPanel.classList.contains('hidden')) posicionarMenu();
            if (lista && !lista.classList.contains('hidden')) posicionarLista();
        }, true);
    }

    form.addEventListener('submit', function () {
        if (inputId && !inputId.value) inputId.disabled = true;
        document.querySelectorAll('#form-filtros-fe select, #form-filtros-fe input[type="date"], [form="form-filtros-fe"]').forEach(function (el) {
            if (el.matches('select, input[type="date"]') && !el.value) el.disabled = true;
        });
    });
})();
</script>
<script>
(function () {
    const checks = () => Array.from(document.querySelectorAll('.chk-lote'));
    const barra = document.getElementById('barra-lotes');
    const contador = document.getElementById('contador-lotes');
    const todos = document.getElementById('seleccionar-lotes');

    function actualizar() {
        const seleccionados = checks().filter(c => c.checked);
        const n = seleccionados.length;
        contador.textContent = String(n);
        barra.classList.toggle('hidden', n === 0);
        if (todos) {
            const elegibles = checks();
            todos.checked = elegibles.length > 0 && elegibles.every(c => c.checked);
            todos.indeterminate = n > 0 && n < elegibles.length;
        }
    }

    document.addEventListener('change', function (e) {
        if (e.target.classList.contains('chk-lote') || e.target.id === 'seleccionar-lotes') {
            if (e.target.id === 'seleccionar-lotes') {
                checks().forEach(c => { c.checked = e.target.checked; });
            }
            actualizar();
        }
    });

    window.confirmarConsultaLotes = function () {
        const n = checks().filter(c => c.checked).length;
        if (n === 0) {
            alert('Seleccione al menos una factura con lote pendiente.');
            return false;
        }
        return confirm('¿Consultar ' + n + ' lote(s) en SIFEN?');
    };

    actualizar();
})();
</script>
@endsection
