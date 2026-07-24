@php
    use App\Support\TicketDiagnosticoPresenter;

    $presenter = new TicketDiagnosticoPresenter($datosDiagnostico ?? null);
    $ticketRef = $ticketOrigen ?? null;
    $secciones = $presenter->secciones();
    $toneClass = fn (?string $tone): string => match ($tone) {
        'good' => 'diag-app-metric__value--good',
        'ok' => 'diag-app-metric__value--ok',
        'warn' => 'diag-app-metric__value--warn',
        'bad' => 'diag-app-metric__value--bad',
        default => '',
    };
@endphp

@once
    @include('partials.ticket-diagnostico-app-styles')
@endonce

@if ($presenter->tieneDatos())
    <section class="diag-app-panel {{ $wrapperClass ?? '' }}">
        <div class="diag-app-panel__head">
            <div class="min-w-0">
                <h2 class="diag-app-panel__title">Diagnóstico enviado desde la app</h2>
                <p class="diag-app-panel__sub">
                    @if ($ticketRef)
                        Ticket #{{ $ticketRef->id }}
                        @if ($ticketRef->created_at)
                            · {{ $ticketRef->created_at->format('d/m/Y H:i') }}
                        @endif
                    @else
                        Telemetría de red capturada al crear el ticket
                    @endif
                </p>
            </div>
            @if ($ticketRef)
                <a href="{{ route('tickets.index') }}" class="diag-app-btn shrink-0">
                    Ver tickets
                </a>
            @endif
        </div>

        <div class="diag-app-panel__body">
            @if ($ticketRef?->descripcion)
                <p class="diag-app-report">
                    <span class="diag-app-report__label">Reporte del cliente:</span>
                    {{ $ticketRef->descripcion }}
                </p>
            @endif

            @foreach ($secciones as $seccion)
                <div>
                    <h3 class="diag-app-section__title">{{ $seccion['titulo'] }}</h3>

                    @if (($seccion['tipo'] ?? '') === 'metricas')
                        <div class="diag-app-metrics diag-app-metrics--wide">
                            @foreach ($seccion['items'] as $item)
                                <div class="diag-app-metric">
                                    <p class="diag-app-metric__label">{{ $item['label'] }}</p>
                                    <p class="diag-app-metric__value {{ $toneClass($item['tone'] ?? null) }}">{{ $item['value'] }}</p>
                                </div>
                            @endforeach
                        </div>
                    @elseif (($seccion['tipo'] ?? '') === 'ping')
                        <div class="diag-app-ping-grid">
                            @foreach ($seccion['items'] as $item)
                                <div class="diag-app-ping">
                                    <p class="diag-app-ping__label">{{ $item['label'] }}</p>
                                    <p class="diag-app-ping__value">{{ $item['value'] }}</p>
                                </div>
                            @endforeach
                        </div>
                    @elseif (($seccion['tipo'] ?? '') === 'traceroute')
                        <div class="diag-app-table-wrap">
                            <table class="diag-app-table">
                                <thead>
                                    <tr>
                                        <th>Salto</th>
                                        <th>Destino</th>
                                        <th>Latencia</th>
                                        <th>Operador / red</th>
                                        <th>Estado</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($seccion['filas'] as $fila)
                                        <tr class="{{ $fila['alcanzado'] ? 'diag-app-table__destino' : '' }}">
                                            <td class="font-mono">{{ $fila['ttl'] }}</td>
                                            <td class="break-all">{{ $fila['destino'] }}</td>
                                            <td>{{ $fila['latencia'] }}</td>
                                            <td>{{ $fila['marca'] }}</td>
                                            <td>
                                                @if ($fila['alcanzado'])
                                                    <span class="diag-app-badge diag-app-badge--destino">Destino</span>
                                                @else
                                                    <span class="diag-app-badge diag-app-badge--transito">Tránsito</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @elseif (($seccion['tipo'] ?? '') === 'ubicacion')
                        <div class="diag-app-location">
                            <p class="diag-app-location__coords">{{ $seccion['texto'] }}</p>
                            <a href="{{ $seccion['maps_url'] }}" target="_blank" rel="noopener noreferrer" class="diag-app-link">
                                Abrir en Google Maps →
                            </a>
                        </div>
                    @endif
                </div>
            @endforeach

            <details class="diag-app-json">
                <summary>Ver JSON completo</summary>
                <pre>{{ json_encode($presenter->toArray()['json'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
            </details>
        </div>
    </section>
@endif
