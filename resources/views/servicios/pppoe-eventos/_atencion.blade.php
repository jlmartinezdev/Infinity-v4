@php
    $atencion = $atencion ?? collect();
    $umbrales = $umbrales ?? \App\Support\PppoeDesconexionContinua::umbrales();
    $miles = $miles ?? fn ($valor) => number_format((int) $valor, 0, ',', '.');
    $urlDetalle = $urlDetalle ?? fn (array $destino) => route('servicios.pppoe-eventos.detalle', array_filter(
        $destino,
        fn ($valor) => $valor !== null && $valor !== ''
    ));
    if (! isset($nAtencion)) {
        $nAtencion = $atencion->count();
        $porNivel = $atencion->countBy('nivel');
        $nCriticos = (int) ($porNivel['critico'] ?? 0);
        $nAltos = (int) ($porNivel['alto'] ?? 0);
        $nObservar = (int) ($porNivel['observar'] ?? 0);
    }
@endphp
    <section id="ppe-atencion" class="ppe-card" aria-labelledby="ppe-atencion-titulo">
        <div class="ppe-atencion-cabecera">
            <div>
                <h2 id="ppe-atencion-titulo" class="ppe-seccion-titulo">
                    Requieren atención por desconexión continua
                    <span class="ppe-contador">{{ $miles($nAtencion) }}</span>
                </h2>
                <p class="ppe-seccion-ayuda">
                    Líneas que se cortaron varias veces en las últimas 24 h, de la más grave a la menos grave. No depende del período elegido.
                </p>
            </div>
            @if($nAtencion > 0)
                <div class="ppe-niveles" role="group" aria-label="Filtrar por gravedad">
                    <button type="button" class="ppe-nivel-btn" data-ppe-nivel="" aria-pressed="true">Todos</button>
                    @foreach(['critico' => ['Crítico', $nCriticos], 'alto' => ['Alto', $nAltos], 'observar' => ['A observar', $nObservar]] as $claveNivel => [$textoNivel, $cuenta])
                        @if($cuenta > 0)
                            <button type="button" class="ppe-nivel-btn" data-ppe-nivel="{{ $claveNivel }}" aria-pressed="false">{{ $textoNivel }} · {{ $cuenta }}</button>
                        @endif
                    @endforeach
                </div>
            @endif
        </div>

        @if($nAtencion === 0)
            <p class="ppe-atencion-vacio">Ninguna línea tuvo desconexión continua en las últimas 24 h.</p>
        @else
            <div class="ppe-atencion-scroll">
                <table class="ppe-tabla">
                    <thead>
                        <tr>
                            <th scope="col">Gravedad</th>
                            <th scope="col">Cliente</th>
                            <th scope="col" class="is-num">Última hora</th>
                            <th scope="col" class="is-num">6 h</th>
                            <th scope="col" class="is-num">24 h</th>
                            <th scope="col">Ahora</th>
                            <th scope="col">Último corte</th>
                            <th scope="col" class="is-num">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($atencion as $fila)
                            @php
                                $detalleFila = $urlDetalle(['servicio_id' => $fila->servicio_id]);
                            @endphp
                            <tr data-ppe-fila-nivel="{{ $fila->nivel }}">
                                <td><span class="ppe-nivel is-{{ $fila->nivel }}">{{ $fila->etiqueta }}</span></td>
                                <td>
                                    <button type="button" class="ppe-cliente-btn" aria-haspopup="dialog"
                                            data-ppe-detalle="{{ $detalleFila }}"
                                            title="Ver los eventos de {{ $fila->nombre }}">{{ $fila->nombre }}</button>
                                    <span class="ppe-conteo">{{ $fila->usuario }} · {{ $fila->router ?? 'Sin router' }}</span>
                                </td>
                                @foreach(['h1', 'h6', 'h24'] as $ventana)
                                    <td class="is-num">
                                        <span class="ppe-cortes {{ $fila->ventanas[$ventana] ? 'is-'.$fila->ventanas[$ventana] : '' }}">{{ $miles($fila->$ventana) }}</span>
                                    </td>
                                @endforeach
                                <td>
                                    @if($fila->conectado === null)
                                        <span class="ppe-seccion-ayuda">Sin datos</span>
                                    @elseif($fila->conectado)
                                        <span class="pppoe-badge pppoe-badge--sm pppoe-badge--up">Conectado</span>
                                    @else
                                        <span class="pppoe-badge pppoe-badge--sm pppoe-badge--down">Desconectado</span>
                                    @endif
                                </td>
                                <td>
                                    @if($fila->ultimo_corte)
                                        <span title="{{ $fila->ultimo_corte->format('d/m/Y H:i:s') }}">hace {{ $fila->ultimo_corte_hace }}</span>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="is-num">
                                    <div class="ppe-acciones">
                                        <a href="{{ route('servicios.herramientas-red', $fila->servicio_id) }}" class="ppe-btn">Herramientas</a>
                                        <div class="ppe-menu-wrap">
                                            <button type="button" class="ppe-btn ppe-btn-icono" data-ppe-menu aria-haspopup="menu" aria-expanded="false" aria-label="Más acciones de {{ $fila->nombre }}">⋯</button>
                                            <div class="ppe-menu" role="menu" hidden>
                                                <button type="button" role="menuitem" class="ppe-menu-item" data-ppe-detalle="{{ $detalleFila }}">Ver eventos</button>
                                                @if($fila->cliente)
                                                    <a role="menuitem" class="ppe-menu-item" href="{{ route('clientes.edit', $fila->cliente) }}">Ficha del cliente</a>
                                                @endif
                                                <button type="button" role="menuitem" class="ppe-menu-item" data-ppe-copiar="{{ $fila->usuario }}">Copiar usuario PPPoE</button>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if($nAtencion >= \App\Http\Controllers\ServicioPppoeEventoController::ATENCION_MAX_FILAS)
                <p class="ppe-atencion-vacio">Se muestran las {{ $nAtencion }} líneas más graves.</p>
            @endif
        @endif

        <details class="ppe-umbrales">
            <summary>Cómo se clasifica</summary>
            <ul>
                @foreach($umbrales as $claveNivel => $minimos)
                    @php
                        $partes = [];
                        foreach (['h1' => 'la última hora', 'h6' => '6 h', 'h24' => '24 h'] as $ventana => $textoVentana) {
                            if ($minimos[$ventana] !== null) {
                                $partes[] = $minimos[$ventana].' o más en '.$textoVentana;
                            }
                        }
                    @endphp
                    @if($partes !== [])
                        <li><strong>{{ \App\Support\PppoeDesconexionContinua::etiqueta($claveNivel) }}:</strong> {{ implode(', o ', $partes) }}.</li>
                    @endif
                @endforeach
            </ul>
            <p>Solo cuentan las desconexiones. Los valores se cambian en <span class="ppe-mono">config/pppoe_eventos.php</span>.</p>
        </details>
    </section>
