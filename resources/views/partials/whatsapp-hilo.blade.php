@php
    use App\Support\ClienteWhatsappPresenter;

    if (isset($whatsappVista)) {
        $waVista = $whatsappVista;
    } elseif (isset($cliente) && $cliente) {
        $waVista = (new ClienteWhatsappPresenter($cliente))->toArray(auth()->user());
    } else {
        $waVista = ['tiene' => false];
    }

    $mensajes = $waVista['mensajes'] ?? [];
    $diaAnterior = null;
@endphp

@once
    @include('partials.whatsapp-hilo-styles')
@endonce

@if (! empty($waVista['tiene']) && $mensajes !== [])
    <section class="ticket-wa-panel {{ $wrapperClass ?? '' }}">
        <div class="ticket-wa-panel__head">
            <div class="min-w-0">
                <h2 class="ticket-wa-panel__title">Historial WhatsApp</h2>
                <p class="ticket-wa-panel__sub">
                    {{ $waVista['total'] ?? count($mensajes) }} mensaje(s)
                    @if (! empty($waVista['telefono']))
                        · {{ $waVista['telefono'] }}
                    @endif
                </p>
            </div>
            @if (! empty($waVista['chat_url']))
                <a href="{{ $waVista['chat_url'] }}" class="ticket-wa-panel__btn shrink-0">
                    Abrir chat completo
                </a>
            @endif
        </div>

        <div class="ticket-wa-hilo">
            @foreach ($mensajes as $msg)
                @if (($msg['dia'] ?? null) && $msg['dia'] !== $diaAnterior)
                    @php $diaAnterior = $msg['dia']; @endphp
                    <div class="ticket-wa-dia">
                        <span>{{ $msg['dia_label'] ?? $msg['dia'] }}</span>
                    </div>
                @endif

                <div class="ticket-wa-row {{ ($msg['entrada'] ?? false) ? 'ticket-wa-row--in' : 'ticket-wa-row--out' }}">
                    <div class="ticket-wa-bubble {{ ($msg['entrada'] ?? false) ? 'ticket-wa-bubble--in' : 'ticket-wa-bubble--out' }} {{ ! empty($msg['fallido']) ? 'ticket-wa-bubble--fail' : '' }}">
                        @if (! empty($msg['tipo_label']))
                            <div class="ticket-wa-bubble__meta">{{ $msg['tipo_label'] }}</div>
                        @endif

                        @if (! empty($msg['media_es_imagen']) && ! empty($msg['media_url']))
                            <a href="{{ $msg['media_url'] }}" target="_blank" rel="noopener noreferrer">
                                <img src="{{ $msg['media_url'] }}" alt="Imagen WhatsApp" class="ticket-wa-bubble__img" loading="lazy">
                            </a>
                        @elseif (! empty($msg['media_url']))
                            <a href="{{ $msg['media_url'] }}" target="_blank" rel="noopener noreferrer" class="ticket-wa-bubble__link">
                                Ver adjunto ({{ $msg['tipo_label'] ?? $msg['tipo'] }})
                            </a>
                        @endif

                        @if (! empty($msg['maps_url']))
                            <a href="{{ $msg['maps_url'] }}" target="_blank" rel="noopener noreferrer" class="ticket-wa-bubble__link">
                                {{ $msg['maps_label'] ?? 'Ver ubicación en mapa' }}
                            </a>
                        @endif

                        @if (! empty($msg['cuerpo']))
                            <div class="ticket-wa-bubble__text">{{ $msg['cuerpo'] }}</div>
                        @endif

                        <div class="ticket-wa-bubble__foot">
                            @if (! empty($msg['estado_label']) && empty($msg['entrada']))
                                <span class="ticket-wa-bubble__estado {{ ! empty($msg['fallido']) ? 'ticket-wa-bubble__estado--fail' : '' }}">
                                    {{ $msg['estado_label'] }}
                                </span>
                            @endif
                            <span class="ticket-wa-bubble__hora">{{ $msg['hora'] ?? '' }}</span>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </section>
@endif
