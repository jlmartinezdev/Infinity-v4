@extends('layouts.app')

@section('title', 'Sorteo')

@section('content')
<div class="max-w-6xl mx-auto pb-16 space-y-6">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">Sorteo</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400">La campaña activa imprime el cupón en el recibo. Al desactivarla, el comprobante vuelve a ser el de siempre.</p>
            @if(!empty($resumen))
            <p class="mt-2 text-sm font-medium text-gray-800 dark:text-gray-100">
                {{ number_format($resumen['clientes'], 0, ',', '.') }} clientes activos.
                {{ number_format($resumen['con_app'], 0, ',', '.') }} con app (dos cupones), el mismo criterio que avisos push.
                {{ number_format($resumen['aptos'], 0, ',', '.') }} aptos, {{ number_format($resumen['al_100'], 0, ',', '.') }} al 100%.
            </p>
            @endif
        </div>
        @if($campanas->isNotEmpty())
        <form method="GET" action="{{ route('loyalty.sorteo.index') }}">
            <select name="campana" onchange="this.form.submit()" class="px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm">
                @foreach($campanas as $item)
                    <option value="{{ $item->id }}" @selected($campana && $campana->id === $item->id)>{{ $item->nombre }}{{ $item->activa ? ' (activa)' : '' }}</option>
                @endforeach
            </select>
        </form>
        @endif
    </div>

    @if(session('success'))
        <p class="px-4 py-2 rounded-lg bg-green-50 text-green-800 text-sm">{{ session('success') }}</p>
    @endif
    @if(session('error'))
        <p class="px-4 py-2 rounded-lg bg-red-50 text-red-800 text-sm">{{ session('error') }}</p>
    @endif

    @if($campana)
    <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-4 flex flex-wrap gap-3 items-center">
        <span class="text-sm font-medium {{ $campana->activa ? 'text-green-700' : 'text-gray-500' }}">{{ $campana->activa ? 'Activa' : 'Inactiva' }}</span>
        <span class="text-sm text-gray-600 dark:text-gray-300">Sorteo {{ $campana->fecha_sorteo?->format('d/m/Y') }} · corte {{ $campana->pago_limite?->timezone($campana->timezone)->format('d/m/Y H:i') }}</span>
        <form method="POST" action="{{ route('loyalty.sorteo.activar', $campana) }}">@csrf
            <button class="px-3 py-1.5 text-sm rounded-lg bg-green-600 text-white">Activar</button>
        </form>
        <form method="POST" action="{{ route('loyalty.sorteo.desactivar', $campana) }}">@csrf
            <button class="px-3 py-1.5 text-sm rounded-lg border border-gray-300 dark:border-gray-600">Desactivar</button>
        </form>
        <form method="POST" action="{{ route('loyalty.sorteo.imprimir', $campana) }}" class="flex items-center gap-2">
            @csrf
            <input type="hidden" name="imprimir_en_recibo" value="{{ $campana->imprimir_en_recibo ? '0' : '1' }}">
            <button class="px-3 py-1.5 text-sm rounded-lg border border-gray-300 dark:border-gray-600">
                {{ $campana->imprimir_en_recibo ? 'Dejar el recibo normal' : 'Volver a imprimir el cupón' }}
            </button>
        </form>
    </div>

    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
        @foreach(['100' => 'Apto 100%', '50' => 'Apto 50%', 'aptos' => 'Todos los aptos', 'no' => 'No aptos'] as $clave => $etiqueta)
            <a href="{{ route('loyalty.sorteo.index', ['campana' => $campana->id, 'filtro' => $clave, 'q' => $buscar]) }}"
               class="rounded-xl border px-4 py-3 {{ $filtro === $clave ? 'border-green-500 bg-green-50 dark:bg-green-900/20' : 'border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800' }}">
                <p class="text-xs text-gray-500">{{ $etiqueta }}</p>
                <p class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ $totales[$clave] ?? 0 }}</p>
            </a>
        @endforeach
    </div>

    <form method="GET" class="flex flex-wrap gap-2">
        <input type="hidden" name="campana" value="{{ $campana->id }}">
        <input type="hidden" name="filtro" value="{{ $filtro }}">
        <input type="search" name="q" value="{{ $buscar }}" placeholder="Nombre, CI o ID" class="px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm flex-1 min-w-[12rem]">
        <button class="px-3 py-2 rounded-lg bg-gray-900 text-white text-sm">Buscar</button>
        <a href="{{ route('loyalty.sorteo.csv', ['campana' => $campana, 'filtro' => $filtro, 'q' => $buscar]) }}" class="px-3 py-2 rounded-lg border text-sm">CSV</a>
        <a href="{{ route('loyalty.sorteo.excel', ['campana' => $campana, 'filtro' => $filtro, 'q' => $buscar]) }}" class="px-3 py-2 rounded-lg border text-sm">Excel</a>
        <a href="{{ route('loyalty.sorteo.urna', ['campana' => $campana, 'filtro' => $filtro, 'q' => $buscar]) }}" class="px-3 py-2 rounded-lg border text-sm">PDF urna</a>
    </form>

    <p class="text-xs text-gray-500">Los aptos aparecen primero. Esta página muestra {{ $filas->count() }} de {{ $filas->total() }}.</p>
    <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead class="text-left text-gray-500">
                <tr>
                    <th class="px-3 py-2">Cliente</th>
                    <th class="px-3 py-2">CI</th>
                    <th class="px-3 py-2">Nivel</th>
                    <th class="px-3 py-2">Ticket</th>
                    <th class="px-3 py-2">Reseña</th>
                </tr>
            </thead>
            <tbody>
                @forelse($filas as $fila)
                <tr class="border-t border-gray-100 dark:border-gray-700">
                    <td class="px-3 py-2">{{ $fila['nombre'] }} <span class="text-gray-400">#{{ $fila['cliente_id'] }}</span></td>
                    <td class="px-3 py-2">{{ $fila['cedula'] }}</td>
                    <td class="px-3 py-2">{{ $fila['etiqueta'] }}</td>
                    <td class="px-3 py-2">{{ $fila['ticket'] ?? '—' }} @if($fila['app'] ?? null) / app {{ $fila['app'] }} @endif @if($fila['extra']) / extra {{ $fila['extra'] }} @endif</td>
                    <td class="px-3 py-2">
                        @php $telOk = $fila['telefono'] === null || $fila['telefono'] === '' || \App\Helpers\TelefonoParaguayHelper::esMovilParaguay($fila['telefono']); @endphp
                        @if($fila['enviada_at'])
                            <p>{{ $fila['nombre_google'] }}</p>
                            <p class="text-xs text-gray-500">Envió {{ $fila['enviada_at'] }} · <span class="{{ $telOk ? '' : 'text-red-600 font-medium' }}">{{ $fila['telefono'] }}</span></p>
                            @if($fila['acreditada'])
                                <p class="text-xs text-green-700">Cupón acreditado</p>
                            @else
                            <form method="POST" action="{{ route('loyalty.sorteo.acreditar', $campana) }}" class="mt-1">
                                @csrf
                                <input type="hidden" name="cliente_id" value="{{ $fila['cliente_id'] }}">
                                <button class="text-xs px-2 py-1 rounded bg-amber-600 text-white">Acreditar cupón</button>
                            </form>
                            @endif
                        @elseif($fila['acreditada'])
                            Cupón acreditado · <span class="{{ $telOk ? '' : 'text-red-600 font-medium' }}">{{ $fila['telefono'] }}</span>
                        @else
                        <form method="POST" action="{{ route('loyalty.sorteo.resena', $campana) }}" class="flex gap-1">
                            @csrf
                            <input type="hidden" name="cliente_id" value="{{ $fila['cliente_id'] }}">
                            <input name="telefono" value="{{ $fila['telefono'] }}" placeholder="Celular" class="px-2 py-1 rounded border text-xs w-36 bg-white dark:bg-gray-700 {{ $telOk ? 'border-gray-300 dark:border-gray-600' : 'border-red-500 text-red-600' }}">
                            <button class="text-xs px-2 py-1 rounded bg-amber-600 text-white">Marcar</button>
                        </form>
                        @endif
                    </td>
                </tr>
                @empty
                <tr><td colspan="5" class="px-3 py-6 text-gray-500">No hay clientes en este filtro.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div>{{ $filas->links() }}</div>

    <details class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-4">
        <summary class="cursor-pointer font-medium text-gray-900 dark:text-gray-100">Cargar ganadores</summary>
        <form method="POST" action="{{ route('loyalty.sorteo.ganadores', $campana) }}" class="mt-3 space-y-3">
            @csrf
            <select name="categoria_id" class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700">
                @foreach($campana->categorias as $categoria)
                    <option value="{{ $categoria->id }}">{{ $categoria->nombre }} (cupo {{ $categoria->cupo }})</option>
                @endforeach
            </select>
            <textarea name="clientes" rows="3" placeholder="Cédulas o IDs, uno por línea" class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700"></textarea>
            <button class="px-3 py-2 rounded-lg bg-green-600 text-white text-sm">Cargar</button>
        </form>
    </details>

    <details class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-4">
        <summary class="cursor-pointer font-medium text-gray-900 dark:text-gray-100">Criterios de esta campaña</summary>
        <form method="POST" action="{{ route('loyalty.sorteo.actualizar', $campana) }}" class="mt-3 grid grid-cols-1 md:grid-cols-2 gap-3">
            @csrf
            @method('PUT')
            <label class="text-sm">Nombre<input name="nombre" value="{{ $campana->nombre }}" class="mt-1 w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700"></label>
            <label class="text-sm">Texto del ticket<input name="texto_ticket" value="{{ $campana->texto_ticket }}" class="mt-1 w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700"></label>
            <label class="text-sm md:col-span-2">Invitación en caja<textarea name="texto_invitacion" rows="2" class="mt-1 w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700">{{ $campana->texto_invitacion }}</textarea></label>
            <label class="text-sm">Fecha del sorteo<input type="date" name="fecha_sorteo" value="{{ $campana->fecha_sorteo?->format('Y-m-d') }}" class="mt-1 w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700"></label>
            <label class="text-sm">Zona<input name="timezone" value="{{ $campana->timezone }}" class="mt-1 w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700"></label>
            <label class="text-sm">Uso desde<input type="date" name="periodo_uso_desde" value="{{ $campana->periodo_uso_desde?->format('Y-m-d') }}" class="mt-1 w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700"></label>
            <label class="text-sm">Uso hasta<input type="date" name="periodo_uso_hasta" value="{{ $campana->periodo_uso_hasta?->format('Y-m-d') }}" class="mt-1 w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700"></label>
            <p class="md:col-span-2 text-xs text-gray-500">Período de uso que el cliente tiene que tener pagado. Si esa factura todavía no existe, un pago o un saldo a favor desde «Uso desde» y hasta el corte cuenta como pago anticipado.</p>
            <label class="text-sm">Corte de pago<input type="datetime-local" name="pago_limite" value="{{ $campana->pago_limite?->format('Y-m-d\TH:i') }}" class="mt-1 w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700"></label>
            <label class="text-sm">Puntualidad
                <select name="criterio_puntualidad" class="mt-1 w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700">
                    <option value="vencimiento" @selected($campana->criterio_puntualidad === 'vencimiento')>Hasta el vencimiento</option>
                    <option value="dia_fijo" @selected($campana->criterio_puntualidad === 'dia_fijo')>Hasta el día fijo</option>
                </select>
            </label>
            <label class="text-sm">Día fijo<input type="number" name="dia_puntualidad" min="1" max="28" value="{{ $campana->dia_puntualidad }}" class="mt-1 w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700"></label>
            <label class="text-sm">Meses del 100% (vencimiento)<input name="meses_puntualidad_100" value="{{ implode(',', $campana->meses_puntualidad_100 ?? []) }}" class="mt-1 w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700"></label>
            <label class="text-sm flex items-center gap-2"><input type="checkbox" name="exige_app" value="1" @checked($campana->exige_app)> Cupón extra si tiene la app</label>
            <label class="text-sm flex items-center gap-2"><input type="checkbox" name="permite_multiples_premios" value="1" @checked($campana->permite_multiples_premios)> Puede ganar más de un premio</label>
            @foreach($campana->categorias as $i => $categoria)
                <div class="md:col-span-2 grid grid-cols-2 md:grid-cols-5 gap-2 border-t border-gray-100 dark:border-gray-700 pt-2">
                    <input type="hidden" name="categorias[{{ $i }}][codigo]" value="{{ $categoria->codigo }}">
                    <input type="hidden" name="categorias[{{ $i }}][orden]" value="{{ $categoria->orden }}">
                    <input name="categorias[{{ $i }}][nombre]" value="{{ $categoria->nombre }}" class="px-2 py-1 rounded border text-sm bg-white dark:bg-gray-700">
                    <input name="categorias[{{ $i }}][cupo]" value="{{ $categoria->cupo }}" class="px-2 py-1 rounded border text-sm bg-white dark:bg-gray-700" title="Cupo">
                    <input name="categorias[{{ $i }}][porcentaje]" value="{{ $categoria->porcentaje }}" class="px-2 py-1 rounded border text-sm bg-white dark:bg-gray-700" placeholder="%">
                    <input name="categorias[{{ $i }}][ventanas]" value="{{ implode(',', $categoria->ventanas_emision ?? []) }}" class="px-2 py-1 rounded border text-sm bg-white dark:bg-gray-700 md:col-span-2" placeholder="2027-01,2027-02">
                </div>
            @endforeach
            <div class="md:col-span-2"><button class="px-3 py-2 rounded-lg bg-gray-900 text-white text-sm">Guardar criterios</button></div>
        </form>
    </details>
    @endif

    <details class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-4">
        <summary class="cursor-pointer font-medium text-gray-900 dark:text-gray-100">Nueva campaña con otros criterios</summary>
        <form method="POST" action="{{ route('loyalty.sorteo.crear') }}" class="mt-3 grid grid-cols-1 md:grid-cols-2 gap-3">
            @csrf
            <label class="text-sm">Nombre<input name="nombre" required class="mt-1 w-full px-3 py-2 rounded-lg border bg-white dark:bg-gray-700"></label>
            <label class="text-sm">Texto del ticket<input name="texto_ticket" required class="mt-1 w-full px-3 py-2 rounded-lg border bg-white dark:bg-gray-700"></label>
            <label class="text-sm md:col-span-2">Invitación<textarea name="texto_invitacion" rows="2" class="mt-1 w-full px-3 py-2 rounded-lg border bg-white dark:bg-gray-700"></textarea></label>
            <label class="text-sm">Fecha del sorteo<input type="date" name="fecha_sorteo" required class="mt-1 w-full px-3 py-2 rounded-lg border bg-white dark:bg-gray-700"></label>
            <label class="text-sm">Zona<input name="timezone" value="America/Asuncion" required class="mt-1 w-full px-3 py-2 rounded-lg border bg-white dark:bg-gray-700"></label>
            <label class="text-sm">Uso desde<input type="date" name="periodo_uso_desde" required class="mt-1 w-full px-3 py-2 rounded-lg border bg-white dark:bg-gray-700"></label>
            <label class="text-sm">Uso hasta<input type="date" name="periodo_uso_hasta" required class="mt-1 w-full px-3 py-2 rounded-lg border bg-white dark:bg-gray-700"></label>
            <p class="md:col-span-2 text-xs text-gray-500">Período de uso que el cliente tiene que tener pagado. Si esa factura todavía no existe, un pago o un saldo a favor desde «Uso desde» y hasta el corte cuenta como pago anticipado.</p>
            <label class="text-sm">Corte de pago<input type="datetime-local" name="pago_limite" required class="mt-1 w-full px-3 py-2 rounded-lg border bg-white dark:bg-gray-700"></label>
            <label class="text-sm">Puntualidad
                <select name="criterio_puntualidad" class="mt-1 w-full px-3 py-2 rounded-lg border bg-white dark:bg-gray-700">
                    <option value="vencimiento">Hasta el vencimiento</option>
                    <option value="dia_fijo">Hasta el día fijo</option>
                </select>
            </label>
            <label class="text-sm">Día fijo<input type="number" name="dia_puntualidad" value="5" min="1" max="28" class="mt-1 w-full px-3 py-2 rounded-lg border bg-white dark:bg-gray-700"></label>
            <label class="text-sm">Meses del 100%<input name="meses_puntualidad_100" placeholder="2026-10,2026-11,2026-12" class="mt-1 w-full px-3 py-2 rounded-lg border bg-white dark:bg-gray-700"></label>
            <label class="text-sm flex items-center gap-2"><input type="checkbox" name="exige_app" value="1" checked> Cupón extra si tiene la app</label>
            <div class="md:col-span-2"><button class="px-3 py-2 rounded-lg bg-gray-900 text-white text-sm">Crear sin activar</button></div>
        </form>
    </details>

    @if($bienvenida)
    <form method="POST" action="{{ route('loyalty.sorteo.bienvenida') }}" class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-4 flex flex-wrap items-end gap-3">
        @csrf
        <div>
            <p class="text-sm font-medium text-gray-900 dark:text-gray-100">Cupón de bienvenida a la app</p>
            <p class="text-xs text-gray-500">Una sola vez, en la próxima factura emitida.</p>
        </div>
        <label class="text-sm flex items-center gap-2"><input type="checkbox" name="activo" value="1" @checked($bienvenida->activo)> Activo</label>
        <label class="text-sm">%<input type="number" step="0.01" name="porcentaje" value="{{ $bienvenida->porcentaje }}" class="ml-2 px-2 py-1 rounded border w-24 bg-white dark:bg-gray-700"></label>
        <button class="px-3 py-2 rounded-lg bg-gray-900 text-white text-sm">Guardar</button>
    </form>
    @endif
</div>
@endsection
