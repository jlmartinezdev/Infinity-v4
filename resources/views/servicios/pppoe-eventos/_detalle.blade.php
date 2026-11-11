{{-- Panel de detalle de un cliente en Eventos PPPoE. Se carga por fetch dentro de #ppe-panel. --}}
@php
    use App\Support\PppoeDesconexionContinua as Flapping;
    use App\Support\PppoeTimeline12h as Linea;

    $miles = fn ($valor) => number_format((int) $valor, 0, ',', '.');
    $variasLineas = $lineas->count() > 1;
@endphp
<header class="ppe-panel-cabecera">
    <div class="ppe-panel-titulos">
        <h2 id="ppe-panel-titulo" class="ppe-panel-titulo">{{ $titulo }}</h2>
        <p class="ppe-panel-sub">
            {{ $variasLineas ? $lineas->count().' líneas PPPoE' : 'Línea PPPoE '.$lineas->first()->usuario }}
        </p>
    </div>
    <button type="button" class="ppe-btn ppe-btn-icono" data-ppe-cerrar aria-label="Cerrar detalle">✕</button>
</header>

<div class="ppe-panel-cuerpo">
    @if($cliente)
        <p>
            <a href="{{ route('clientes.edit', $cliente) }}" class="ppe-btn">Ficha del cliente</a>
        </p>
    @endif

    @foreach($lineas as $linea)
        @php
            $tl = $linea->timeline;
            $nivel = $linea->conteo['nivel'] ?? null;
            $estado = $tl['estado_actual'];
        @endphp
        <section class="ppe-linea" aria-label="Línea {{ $linea->usuario }}">
            <div class="ppe-linea-cabecera">
                <div class="ppe-linea-nombre">
                    <span class="ppe-mono">{{ $linea->usuario }}</span>
                    <span class="ppe-linea-meta">
                        {{ $linea->router ?? 'Sin router' }}@if($linea->ip) · {{ $linea->ip }}@endif
                    </span>
                </div>
                <a href="{{ route('servicios.herramientas-red', $linea->servicio->servicio_id) }}" class="ppe-btn">Herramientas</a>
            </div>

            <p class="ppe-linea-estado">
                <span class="ppe-punto is-{{ $estado }}" aria-hidden="true"></span>
                <strong>Ahora: {{ mb_strtolower(Linea::etiquetaEstado($estado)) }}</strong>
                @if($nivel)
                    <span class="ppe-nivel is-{{ $nivel }}">{{ Flapping::etiqueta($nivel) }}</span>
                @endif
            </p>

            <dl class="ppe-ventanas">
                <div><dt>Cortes, última hora</dt><dd>{{ $miles($linea->conteo['h1']) }}</dd></div>
                <div><dt>Últimas 6 h</dt><dd>{{ $miles($linea->conteo['h6']) }}</dd></div>
                <div><dt>Últimas 24 h</dt><dd>{{ $miles($linea->conteo['h24']) }}</dd></div>
            </dl>

            <div class="ppe-tl" role="img"
                 aria-label="Últimas 12 horas: conectado {{ $tl['conectado_pct'] }} %, sin conexión {{ $tl['desconectado_pct'] }} %, sin datos {{ $tl['sin_datos_pct'] }} %">
                @foreach($tl['segmentos'] as $seg)
                    <span class="ppe-tl-seg is-{{ $seg['estado'] }}" style="width: {{ $seg['width_pct'] }}%"
                          title="{{ Linea::etiquetaEstado($seg['estado']) }} · {{ $seg['inicio']->format('H:i') }}–{{ $seg['fin']->format('H:i') }} · {{ Linea::formatearDuracion((int) $seg['duracion_segundos']) }}"></span>
                @endforeach
            </div>
            <div class="ppe-tl-marcas" aria-hidden="true">
                @foreach($tl['marcas'] as $marca)
                    <span>{{ $marca['label'] }}</span>
                @endforeach
            </div>
            <p class="ppe-tl-leyenda">
                Últimas 12 h:
                <span class="ppe-tl-clave is-up"></span> conectado {{ Linea::formatearDuracion((int) $tl['conectado_segundos']) }}
                · <span class="ppe-tl-clave is-down"></span> sin conexión {{ Linea::formatearDuracion((int) $tl['desconectado_segundos']) }}
                @if($tl['sin_datos_segundos'] > 0)
                    · <span class="ppe-tl-clave is-unknown"></span> sin datos {{ Linea::formatearDuracion((int) $tl['sin_datos_segundos']) }}
                @endif
            </p>
        </section>
    @endforeach

    <section aria-labelledby="ppe-panel-eventos">
        <h3 id="ppe-panel-eventos" class="ppe-seccion-titulo">
            Eventos {{ $periodo['etiqueta'] }}
            <span class="ppe-seccion-ayuda">· {{ $miles($eventos->count()) }}{{ $hayMas ? ' más recientes' : '' }}</span>
        </h3>

        @if($eventos->isEmpty())
            <p class="ppe-seccion-ayuda" style="margin-top: 0.5rem">No hay eventos {{ $periodo['etiqueta'] }}. Probá con un período más largo.</p>
        @else
            <ol class="ppe-eventos">
                @foreach($eventos as $fila)
                    @php $ev = $fila->evento; @endphp
                    <li class="ppe-evento is-{{ $fila->conectado ? 'up' : 'down' }}">
                        <span class="ppe-evento-hora" title="{{ $ev->ocurrio_at->format('d/m/Y H:i:s') }}">
                            {{ $ev->ocurrio_at->isToday() ? $ev->ocurrio_at->format('H:i:s') : $ev->ocurrio_at->format('d/m H:i:s') }}
                        </span>
                        <span class="ppe-evento-texto">
                            <strong>{{ $fila->conectado ? 'Se conectó' : 'Se desconectó' }}</strong>
                            <span class="ppe-evento-dura">·
                                @if($fila->sigue)
                                    {{ $fila->conectado ? 'conectado desde hace' : 'sin conexión desde hace' }} {{ $fila->duracion }}
                                @else
                                    {{ $fila->conectado ? 'estuvo conectado' : 'estuvo sin conexión' }} {{ $fila->duracion }}
                                @endif
                            </span>
                            <span class="ppe-evento-meta">
                                @if($variasLineas){{ $ev->usuario_pppoe ?: '—' }} · @endif{{ $ev->router?->nombre ?? 'Sin router' }}@if($ev->ip) · {{ $ev->ip }}@endif @if($ev->mac_address) · {{ $ev->mac_address }}@endif · {{ $ev->etiquetaFuente() }}
                            </span>
                        </span>
                    </li>
                @endforeach
            </ol>
            @if($hayMas)
                <p class="ppe-seccion-ayuda">Se muestran los {{ $miles($eventos->count()) }} eventos más recientes. Acotá el período para ver los anteriores.</p>
            @endif
        @endif
    </section>
</div>
