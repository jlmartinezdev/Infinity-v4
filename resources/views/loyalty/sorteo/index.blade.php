@extends('layouts.app')

@section('title', 'Sorteo')

@php
    $input = 'w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm';
    $inputChico = 'px-2 py-1 rounded border border-gray-300 dark:border-gray-600 text-sm bg-white dark:bg-gray-700';
    $boton = 'px-3 py-1.5 text-sm rounded-lg border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700';
    $niveles = [
        '100' => 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300',
        'anticipado' => 'bg-sky-100 text-sky-800 dark:bg-sky-900/40 dark:text-sky-300',
        '50' => 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300',
        'apto' => 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-200',
        'no' => 'bg-red-50 text-red-700 dark:bg-red-900/30 dark:text-red-300',
    ];
    $chip = 'inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium';
@endphp

@section('content')
<div class="max-w-6xl mx-auto pb-16 space-y-6">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">Sorteo</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400">La campaña activa imprime el cupón en el recibo. Al desactivarla, el comprobante vuelve a ser el de siempre.</p>
        </div>
        @if($campanas->isNotEmpty())
        <form method="GET" action="{{ route('loyalty.sorteo.index') }}">
            <select name="campana" onchange="this.form.submit()" class="px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm" aria-label="Campaña">
                @foreach($campanas as $item)
                    <option value="{{ $item->id }}" @selected($campana && $campana->id === $item->id)>{{ $item->nombre }}{{ $item->activa ? ' (activa)' : '' }}</option>
                @endforeach
            </select>
        </form>
        @endif
    </div>

    @if(session('success'))
        <p class="px-4 py-2 rounded-lg bg-green-50 text-green-800 dark:bg-green-900/30 dark:text-green-300 text-sm">{{ session('success') }}</p>
    @endif
    @if(session('error'))
        <p class="px-4 py-2 rounded-lg bg-red-50 text-red-800 dark:bg-red-900/30 dark:text-red-300 text-sm">{{ session('error') }}</p>
    @endif

    @if($campana)
    @php
        $dias = $campana->fecha_sorteo
            ? (int) now($campana->timezone)->startOfDay()->diffInDays($campana->fecha_sorteo->copy()->startOfDay(), false)
            : null;
        $corte = $campana->pago_limite?->timezone($campana->timezone);
    @endphp
    <section class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-4 space-y-4">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="space-y-2">
                <div class="flex flex-wrap items-center gap-2">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">{{ $campana->nombre }}</h2>
                    @if($campana->activa)
                        <span class="{{ $chip }} bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300"><span class="w-1.5 h-1.5 rounded-full bg-green-500"></span>Activa</span>
                    @else
                        <span class="{{ $chip }} bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300"><span class="w-1.5 h-1.5 rounded-full bg-gray-400"></span>Inactiva</span>
                    @endif
                    <span class="{{ $chip }} {{ $campana->imprimir_en_recibo ? 'bg-violet-100 text-violet-800 dark:bg-violet-900/40 dark:text-violet-300' : 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300' }}">
                        {{ $campana->imprimir_en_recibo ? 'Cupón en el recibo' : 'Recibo normal' }}
                    </span>
                </div>
                <div class="flex flex-wrap gap-x-4 gap-y-1 text-sm text-gray-600 dark:text-gray-300">
                    <span>Sorteo <strong class="text-gray-900 dark:text-gray-100">{{ $campana->fecha_sorteo?->format('d/m/Y') ?? '—' }}</strong>
                        @if($dias !== null)
                            <span class="text-gray-500 dark:text-gray-400">· {{ $dias > 1 ? 'faltan '.$dias.' días' : ($dias === 1 ? 'mañana' : ($dias === 0 ? 'hoy' : 'ya pasó')) }}</span>
                        @endif
                    </span>
                    <span>Corte de pago <strong class="text-gray-900 dark:text-gray-100">{{ $corte?->format('d/m/Y H:i') ?? '—' }}</strong>
                        @if($corte && $corte->isPast())<span class="text-gray-500 dark:text-gray-400">· cerrado</span>@endif
                    </span>
                </div>
            </div>
            <div class="flex flex-wrap gap-2">
                @if($campana->activa)
                    <form method="POST" action="{{ route('loyalty.sorteo.desactivar', $campana) }}">@csrf
                        <button class="{{ $boton }}">Desactivar</button>
                    </form>
                @else
                    <form method="POST" action="{{ route('loyalty.sorteo.activar', $campana) }}">@csrf
                        <button class="px-3 py-1.5 text-sm rounded-lg bg-green-600 hover:bg-green-700 text-white">Activar</button>
                    </form>
                @endif
                <form method="POST" action="{{ route('loyalty.sorteo.imprimir', $campana) }}">
                    @csrf
                    <input type="hidden" name="imprimir_en_recibo" value="{{ $campana->imprimir_en_recibo ? '0' : '1' }}">
                    <button class="{{ $boton }}">{{ $campana->imprimir_en_recibo ? 'Dejar el recibo normal' : 'Volver a imprimir el cupón' }}</button>
                </form>
            </div>
        </div>

        <dl class="grid grid-cols-2 md:grid-cols-4 gap-3 border-t border-gray-100 dark:border-gray-700 pt-4">
            @foreach([
                ['Clientes activos', $resumen['clientes'], null],
                ['Con app', $resumen['con_app'], 'Dos cupones · mismo criterio que avisos push'],
                ['Aptos', $resumen['aptos'], null],
                ['Al 100%', $resumen['al_100'], null],
            ] as [$etiqueta, $valor, $ayuda])
                <div>
                    <dt class="text-xs text-gray-500 dark:text-gray-400" @if($ayuda) title="{{ $ayuda }}" @endif>{{ $etiqueta }}</dt>
                    <dd class="text-xl font-semibold text-gray-900 dark:text-gray-100">{{ number_format($valor, 0, ',', '.') }}</dd>
                </div>
            @endforeach
        </dl>
    </section>
    @endif

    @php
        $pestanas = $campana
            ? ['participantes' => ['Participantes', $totales['participando'] ?? 0], 'ganadores' => ['Ganadores', $ganadores->sum(fn ($lista) => $lista->count())], 'configuracion' => ['Configuración', null]]
            : ['configuracion' => ['Configuración', null]];
    @endphp
    <nav class="inline-flex rounded-lg bg-gray-100 dark:bg-white/5 p-0.5" role="tablist" aria-label="Secciones del sorteo">
        @foreach($pestanas as $clave => [$etiqueta, $cuenta])
            @php $activo = $seccion === $clave; @endphp
            <a href="{{ route('loyalty.sorteo.index', array_filter(['campana' => $campana?->id, 'seccion' => $clave])) }}"
               role="tab"
               aria-selected="{{ $activo ? 'true' : 'false' }}"
               class="inline-flex items-center gap-1.5 rounded-md px-3 py-1 text-xs font-medium transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-violet-500
                   {{ $activo ? 'bg-white dark:bg-gray-700 text-gray-900 dark:text-white shadow-sm' : 'text-gray-500 dark:text-gray-400' }}">
                {{ $etiqueta }}
                @if($cuenta !== null)
                    <span class="rounded-full px-1.5 text-[10px] {{ $activo ? 'bg-gray-100 dark:bg-gray-600' : 'bg-gray-200 dark:bg-white/10' }}">{{ number_format($cuenta, 0, ',', '.') }}</span>
                @endif
            </a>
        @endforeach
    </nav>

    @if($campana && $seccion === 'participantes')
    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3">
        @foreach([
            'participando' => ['Ya participan', 'bg-violet-500'],
            'resena' => ['Reseña por acreditar', 'bg-amber-500'],
            '100' => ['Apto 100%', 'bg-green-500'],
            '50' => ['Apto 50%', 'bg-amber-400'],
            'aptos' => ['Todos los aptos', 'bg-sky-500'],
            'no' => ['No aptos', 'bg-red-400'],
        ] as $clave => [$etiqueta, $punto])
            <a href="{{ route('loyalty.sorteo.index', ['campana' => $campana->id, 'filtro' => $clave, 'q' => $buscar]) }}"
               class="rounded-xl border px-4 py-3 transition-colors {{ $filtro === $clave ? 'border-green-500 bg-green-50 dark:bg-green-900/20' : 'border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 hover:border-gray-300 dark:hover:border-gray-600' }}">
                <p class="flex items-center gap-1.5 text-xs text-gray-500 dark:text-gray-400"><span class="w-2 h-2 rounded-full {{ $punto }}"></span>{{ $etiqueta }}</p>
                <p class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ number_format($totales[$clave] ?? 0, 0, ',', '.') }}</p>
            </a>
        @endforeach
    </div>

    <form method="GET" class="flex flex-wrap gap-2">
        <input type="hidden" name="campana" value="{{ $campana->id }}">
        <input type="hidden" name="filtro" value="{{ $filtro }}">
        <input type="search" name="q" value="{{ $buscar }}" placeholder="Nombre, CI o ID" class="px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm flex-1 min-w-[12rem]">
        <button class="px-3 py-2 rounded-lg bg-gray-900 dark:bg-gray-100 text-white dark:text-gray-900 text-sm">Buscar</button>
        <div class="inline-flex rounded-lg border border-gray-300 dark:border-gray-600 divide-x divide-gray-300 dark:divide-gray-600 overflow-hidden text-sm">
            <a href="{{ route('loyalty.sorteo.csv', ['campana' => $campana, 'filtro' => $filtro, 'q' => $buscar]) }}" class="px-3 py-2 text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700">CSV</a>
            <a href="{{ route('loyalty.sorteo.excel', ['campana' => $campana, 'filtro' => $filtro, 'q' => $buscar]) }}" class="px-3 py-2 text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700">Excel</a>
            <a href="{{ route('loyalty.sorteo.urna', ['campana' => $campana, 'filtro' => $filtro, 'q' => $buscar]) }}" class="px-3 py-2 text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700">PDF urna</a>
        </div>
    </form>

    <p class="text-xs text-gray-500 dark:text-gray-400">
        @if($filtro === 'participando')
            Clientes que ya tienen cupón. Para sumar el de reseña, usá «Acreditar cupón».
        @elseif($filtro === 'resena')
            Enviaron el nombre de Google y el cupón de reseña todavía no está activo.
        @else
            Los aptos aparecen primero.
        @endif
        Esta página muestra {{ $filas->count() }} de {{ $filas->total() }}.
    </p>
    <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead class="text-left text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400 bg-gray-50 dark:bg-gray-900/40">
                <tr>
                    <th class="px-3 py-2 font-medium">Cliente</th>
                    <th class="px-3 py-2 font-medium">Nivel</th>
                    <th class="px-3 py-2 font-medium">Cupones</th>
                    <th class="px-3 py-2 font-medium">Reseña</th>
                </tr>
            </thead>
            <tbody>
                @forelse($filas as $fila)
                <tr class="border-t border-gray-100 dark:border-gray-700 align-top">
                    <td class="px-3 py-2">
                        <p class="font-medium text-gray-900 dark:text-gray-100">{{ $fila['nombre'] }}</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">CI {{ $fila['cedula'] ?: '—' }} · #{{ $fila['cliente_id'] }}</p>
                    </td>
                    <td class="px-3 py-2">
                        @php
                            [$insignia, $detalle] = $fila['nivel'] === 'no'
                                ? ['No apto', $fila['etiqueta']]
                                : array_pad(explode(' · ', (string) $fila['etiqueta'], 2), 2, null);
                        @endphp
                        <span class="{{ $chip }} {{ $niveles[$fila['nivel']] ?? $niveles['apto'] }}">{{ $insignia }}</span>
                        @if($detalle)
                            <p class="mt-1 text-xs {{ $fila['nivel'] === 'no' ? 'text-red-600 dark:text-red-400' : 'text-gray-500 dark:text-gray-400' }}">{{ $detalle }}</p>
                        @endif
                    </td>
                    <td class="px-3 py-2">
                        @if(($fila['cupones'] ?? 0) > 0)
                            <div class="flex flex-wrap gap-1">
                                @if($fila['ticket'] ?? null)<span class="{{ $chip }} bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-200" title="Cupón por pago">Pago {{ $fila['ticket'] }}</span>@endif
                                @if($fila['app'] ?? null)<span class="{{ $chip }} bg-sky-100 text-sky-800 dark:bg-sky-900/40 dark:text-sky-300" title="Cupón por tener la app">App {{ $fila['app'] }}</span>@endif
                                @if($fila['extra'])<span class="{{ $chip }} bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300" title="Cupón por reseña">Reseña {{ $fila['extra'] }}</span>@endif
                            </div>
                        @else
                            <span class="text-gray-400">—</span>
                        @endif
                    </td>
                    <td class="px-3 py-2">
                        @php $telOk = $fila['telefono'] === null || $fila['telefono'] === '' || \App\Helpers\TelefonoParaguayHelper::esMovilParaguay($fila['telefono']); @endphp
                        @if($fila['enviada_at'])
                            <p class="text-gray-900 dark:text-gray-100">{{ $fila['nombre_google'] }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Envió {{ $fila['enviada_at'] }} · <span class="{{ $telOk ? '' : 'text-red-600 font-medium' }}">{{ $fila['telefono'] }}</span></p>
                            @if($fila['acreditada'])
                                <p class="text-xs text-green-700 dark:text-green-400">Cupón acreditado</p>
                            @else
                            <form method="POST" action="{{ route('loyalty.sorteo.acreditar', $campana) }}" class="mt-1">
                                @csrf
                                <input type="hidden" name="cliente_id" value="{{ $fila['cliente_id'] }}">
                                <button class="text-xs px-2 py-1 rounded bg-amber-600 hover:bg-amber-700 text-white">Acreditar cupón</button>
                            </form>
                            @endif
                        @elseif($fila['acreditada'])
                            <span class="text-green-700 dark:text-green-400">Cupón acreditado</span> · <span class="{{ $telOk ? '' : 'text-red-600 font-medium' }}">{{ $fila['telefono'] }}</span>
                        @else
                        <form method="POST" action="{{ route('loyalty.sorteo.resena', $campana) }}" class="flex gap-1">
                            @csrf
                            <input type="hidden" name="cliente_id" value="{{ $fila['cliente_id'] }}">
                            <input name="telefono" value="{{ $fila['telefono'] }}" placeholder="Celular" class="px-2 py-1 rounded border text-xs w-36 bg-white dark:bg-gray-700 {{ $telOk ? 'border-gray-300 dark:border-gray-600' : 'border-red-500 text-red-600' }}">
                            <button class="text-xs px-2 py-1 rounded bg-amber-600 hover:bg-amber-700 text-white">Marcar</button>
                        </form>
                        @endif
                    </td>
                </tr>
                @empty
                <tr><td colspan="4" class="px-3 py-6 text-gray-500 dark:text-gray-400">No hay clientes en este filtro.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div>{{ $filas->links() }}</div>
    @endif

    @if($campana && $seccion === 'ganadores')
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
        <div class="lg:col-span-2 space-y-4">
            @foreach($campana->categorias as $categoria)
                @php $lista = $ganadores->get($categoria->id, collect()); @endphp
                <section class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl">
                    <header class="flex items-center justify-between px-4 py-3 border-b border-gray-100 dark:border-gray-700">
                        <h3 class="font-medium text-gray-900 dark:text-gray-100">{{ $categoria->nombre }}</h3>
                        <span class="text-xs {{ $lista->count() >= $categoria->cupo ? 'text-green-700 dark:text-green-400' : 'text-gray-500 dark:text-gray-400' }}">{{ $lista->count() }} de {{ $categoria->cupo }}</span>
                    </header>
                    @if($lista->isEmpty())
                        <p class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">Todavía no hay ganadores cargados.</p>
                    @else
                        <ul class="divide-y divide-gray-100 dark:divide-gray-700 text-sm">
                            @foreach($lista as $ganador)
                                <li class="px-4 py-2 flex justify-between gap-3">
                                    <span class="text-gray-900 dark:text-gray-100">{{ trim(($ganador->cliente->nombre ?? '').' '.($ganador->cliente->apellido ?? '')) ?: 'Cliente #'.$ganador->cliente_id }}</span>
                                    <span class="text-xs text-gray-500 dark:text-gray-400">CI {{ $ganador->cliente->cedula ?? '—' }} · #{{ $ganador->cliente_id }}</span>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </section>
            @endforeach
        </div>
        <section class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-4 h-fit">
            <h3 class="font-medium text-gray-900 dark:text-gray-100">Cargar ganadores</h3>
            <form method="POST" action="{{ route('loyalty.sorteo.ganadores', $campana) }}" class="mt-3 space-y-3">
                @csrf
                <select name="categoria_id" class="{{ $input }}">
                    @foreach($campana->categorias as $categoria)
                        <option value="{{ $categoria->id }}">{{ $categoria->nombre }} (cupo {{ $categoria->cupo }})</option>
                    @endforeach
                </select>
                <textarea name="clientes" rows="5" placeholder="Cédulas o IDs, uno por línea" class="{{ $input }}"></textarea>
                <button class="px-3 py-2 rounded-lg bg-green-600 hover:bg-green-700 text-white text-sm">Cargar</button>
            </form>
        </section>
    </div>
    @endif

    @if($seccion === 'configuracion')
    @if($campana)
    <details open class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-4">
        <summary class="cursor-pointer font-medium text-gray-900 dark:text-gray-100">Criterios de esta campaña</summary>
        <form method="POST" action="{{ route('loyalty.sorteo.actualizar', $campana) }}" class="mt-3 grid grid-cols-1 md:grid-cols-2 gap-3">
            @csrf
            @method('PUT')
            <label class="text-sm">Nombre<input name="nombre" value="{{ $campana->nombre }}" class="mt-1 {{ $input }}"></label>
            <label class="text-sm">Texto del ticket<input name="texto_ticket" value="{{ $campana->texto_ticket }}" class="mt-1 {{ $input }}"></label>
            <label class="text-sm md:col-span-2">Invitación en caja<textarea name="texto_invitacion" rows="2" class="mt-1 {{ $input }}">{{ $campana->texto_invitacion }}</textarea></label>
            <label class="text-sm">Fecha del sorteo<input type="date" name="fecha_sorteo" value="{{ $campana->fecha_sorteo?->format('Y-m-d') }}" class="mt-1 {{ $input }}"></label>
            <label class="text-sm">Zona<input name="timezone" value="{{ $campana->timezone }}" class="mt-1 {{ $input }}"></label>
            <label class="text-sm">Uso desde<input type="date" name="periodo_uso_desde" value="{{ $campana->periodo_uso_desde?->format('Y-m-d') }}" class="mt-1 {{ $input }}"></label>
            <label class="text-sm">Uso hasta<input type="date" name="periodo_uso_hasta" value="{{ $campana->periodo_uso_hasta?->format('Y-m-d') }}" class="mt-1 {{ $input }}"></label>
            <p class="md:col-span-2 text-xs text-gray-500 dark:text-gray-400">Período de uso que el cliente tiene que tener pagado. Si esa factura todavía no existe, un pago o un saldo a favor desde «Uso desde» y hasta el corte cuenta como pago anticipado.</p>
            <label class="text-sm">Corte de pago<input type="datetime-local" name="pago_limite" value="{{ $campana->pago_limite?->format('Y-m-d\TH:i') }}" class="mt-1 {{ $input }}"></label>
            <label class="text-sm">Puntualidad
                <select name="criterio_puntualidad" class="mt-1 {{ $input }}">
                    <option value="vencimiento" @selected($campana->criterio_puntualidad === 'vencimiento')>Hasta el vencimiento</option>
                    <option value="dia_fijo" @selected($campana->criterio_puntualidad === 'dia_fijo')>Hasta el día fijo</option>
                </select>
            </label>
            <label class="text-sm">Día fijo<input type="number" name="dia_puntualidad" min="1" max="28" value="{{ $campana->dia_puntualidad }}" class="mt-1 {{ $input }}"></label>
            <label class="text-sm">Meses del 100% (vencimiento)<input name="meses_puntualidad_100" value="{{ implode(',', $campana->meses_puntualidad_100 ?? []) }}" class="mt-1 {{ $input }}"></label>
            <label class="text-sm flex items-center gap-2"><input type="checkbox" name="exige_app" value="1" @checked($campana->exige_app)> Cupón extra si tiene la app</label>
            <label class="text-sm flex items-center gap-2"><input type="checkbox" name="permite_multiples_premios" value="1" @checked($campana->permite_multiples_premios)> Puede ganar más de un premio</label>
            @if($campana->categorias->isNotEmpty())
            <div class="md:col-span-2 border-t border-gray-100 dark:border-gray-700 pt-3">
                <p class="text-sm font-medium text-gray-900 dark:text-gray-100">Categorías de premio</p>
                <div class="hidden md:grid grid-cols-5 gap-2 mt-2 text-xs text-gray-500 dark:text-gray-400">
                    <span>Nombre</span><span>Cupo</span><span>%</span><span class="col-span-2">Meses de emisión</span>
                </div>
            </div>
            @endif
            @foreach($campana->categorias as $i => $categoria)
                <div class="md:col-span-2 grid grid-cols-2 md:grid-cols-5 gap-2">
                    <input type="hidden" name="categorias[{{ $i }}][codigo]" value="{{ $categoria->codigo }}">
                    <input type="hidden" name="categorias[{{ $i }}][orden]" value="{{ $categoria->orden }}">
                    <input name="categorias[{{ $i }}][nombre]" value="{{ $categoria->nombre }}" class="{{ $inputChico }}" aria-label="Nombre">
                    <input name="categorias[{{ $i }}][cupo]" value="{{ $categoria->cupo }}" class="{{ $inputChico }}" aria-label="Cupo" title="Cupo">
                    <input name="categorias[{{ $i }}][porcentaje]" value="{{ $categoria->porcentaje }}" class="{{ $inputChico }}" aria-label="Porcentaje" placeholder="%">
                    <input name="categorias[{{ $i }}][ventanas]" value="{{ implode(',', $categoria->ventanas_emision ?? []) }}" class="{{ $inputChico }} md:col-span-2" aria-label="Meses de emisión" placeholder="2027-01,2027-02">
                </div>
            @endforeach
            <div class="md:col-span-2"><button class="px-3 py-2 rounded-lg bg-gray-900 dark:bg-gray-100 text-white dark:text-gray-900 text-sm">Guardar criterios</button></div>
        </form>
    </details>
    @endif

    <details @if(!$campana) open @endif class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-4">
        <summary class="cursor-pointer font-medium text-gray-900 dark:text-gray-100">Nueva campaña con otros criterios</summary>
        <form method="POST" action="{{ route('loyalty.sorteo.crear') }}" class="mt-3 grid grid-cols-1 md:grid-cols-2 gap-3">
            @csrf
            <label class="text-sm">Nombre<input name="nombre" required class="mt-1 {{ $input }}"></label>
            <label class="text-sm">Texto del ticket<input name="texto_ticket" required class="mt-1 {{ $input }}"></label>
            <label class="text-sm md:col-span-2">Invitación<textarea name="texto_invitacion" rows="2" class="mt-1 {{ $input }}"></textarea></label>
            <label class="text-sm">Fecha del sorteo<input type="date" name="fecha_sorteo" required class="mt-1 {{ $input }}"></label>
            <label class="text-sm">Zona<input name="timezone" value="America/Asuncion" required class="mt-1 {{ $input }}"></label>
            <label class="text-sm">Uso desde<input type="date" name="periodo_uso_desde" required class="mt-1 {{ $input }}"></label>
            <label class="text-sm">Uso hasta<input type="date" name="periodo_uso_hasta" required class="mt-1 {{ $input }}"></label>
            <p class="md:col-span-2 text-xs text-gray-500 dark:text-gray-400">Período de uso que el cliente tiene que tener pagado. Si esa factura todavía no existe, un pago o un saldo a favor desde «Uso desde» y hasta el corte cuenta como pago anticipado.</p>
            <label class="text-sm">Corte de pago<input type="datetime-local" name="pago_limite" required class="mt-1 {{ $input }}"></label>
            <label class="text-sm">Puntualidad
                <select name="criterio_puntualidad" class="mt-1 {{ $input }}">
                    <option value="vencimiento">Hasta el vencimiento</option>
                    <option value="dia_fijo">Hasta el día fijo</option>
                </select>
            </label>
            <label class="text-sm">Día fijo<input type="number" name="dia_puntualidad" value="5" min="1" max="28" class="mt-1 {{ $input }}"></label>
            <label class="text-sm">Meses del 100%<input name="meses_puntualidad_100" placeholder="2026-10,2026-11,2026-12" class="mt-1 {{ $input }}"></label>
            <label class="text-sm flex items-center gap-2"><input type="checkbox" name="exige_app" value="1" checked> Cupón extra si tiene la app</label>
            <div class="md:col-span-2"><button class="px-3 py-2 rounded-lg bg-gray-900 dark:bg-gray-100 text-white dark:text-gray-900 text-sm">Crear sin activar</button></div>
        </form>
    </details>

    @if($bienvenida)
    <form method="POST" action="{{ route('loyalty.sorteo.bienvenida') }}" class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-4 flex flex-wrap items-end gap-3">
        @csrf
        <div class="flex-1 min-w-[12rem]">
            <p class="text-sm font-medium text-gray-900 dark:text-gray-100">Cupón de bienvenida a la app</p>
            <p class="text-xs text-gray-500 dark:text-gray-400">Una sola vez, en la próxima factura emitida.</p>
        </div>
        <label class="text-sm flex items-center gap-2"><input type="checkbox" name="activo" value="1" @checked($bienvenida->activo)> Activo</label>
        <label class="text-sm">%<input type="number" step="0.01" name="porcentaje" value="{{ $bienvenida->porcentaje }}" class="ml-2 {{ $inputChico }} w-24"></label>
        <button class="px-3 py-2 rounded-lg bg-gray-900 dark:bg-gray-100 text-white dark:text-gray-900 text-sm">Guardar</button>
    </form>
    @endif
    @endif
</div>
@endsection
