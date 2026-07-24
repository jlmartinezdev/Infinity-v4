@php
    if (isset($whatsappVista)) {
        $waVista = $whatsappVista;
    } elseif (isset($cliente) && $cliente) {
        $waVista = (new \App\Support\ClienteWhatsappPresenter($cliente))->toArray(auth()->user());
    } else {
        $waVista = ['tiene' => false];
    }

    $mensajes = $waVista['mensajes'] ?? [];
    $diaAnterior = null;
    $nombreCliente = isset($cliente) ? trim(($cliente->nombre ?? '').' '.($cliente->apellido ?? '')) : 'Cliente';
    $iniciales = collect(preg_split('/\s+/u', $nombreCliente, -1, PREG_SPLIT_NO_EMPTY))
        ->take(2)
        ->map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)))
        ->implode('');
    $iniciales = $iniciales !== '' ? $iniciales : '?';
    $telWa = $waVista['telefono'] ?? (isset($cliente) ? trim((string) ($cliente->telefono ?? '')) : '');
    $chatUrl = $waVista['chat_url'] ?? null;
    $enviarUrl = ($telWa !== '' && (auth()->user()?->tienePermiso('whatsapp.editar') ?? false))
        ? route('whatsapp.enviar', ['telefono' => $telWa])
        : $chatUrl;
@endphp

<section class="cliente-wa-sidebar {{ $wrapperClass ?? '' }}">
    <div class="cliente-wa-sidebar__head">
        <div class="cliente-wa-sidebar__avatar" aria-hidden="true">{{ $iniciales }}</div>
        <div class="min-w-0 flex-1">
            <p class="cliente-wa-sidebar__name truncate">{{ $nombreCliente }}</p>
        </div>
        <div class="cliente-wa-sidebar__actions">
            @if ($telWa !== '')
                <a href="https://wa.me/{{ preg_replace('/\D+/', '', $telWa) }}" target="_blank" rel="noopener noreferrer"
                   class="cliente-wa-sidebar__icon-btn" title="WhatsApp">
                    <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z"/><path d="M12 0C5.373 0 0 5.373 0 12c0 2.625.846 5.059 2.284 7.034L.789 23.492a.75.75 0 0 0 .917.917l4.458-1.495A11.953 11.953 0 0 0 12 24c6.627 0 12-5.373 12-12S18.627 0 12 0zm0 22c-2.387 0-4.584-.832-6.314-2.222l-.447-.372-2.627.882.882-2.627-.372-.447A9.96 9.96 0 0 1 2 12C2 6.477 6.477 2 12 2s10 4.477 10 10-4.477 10-10 10z"/></svg>
                </a>
            @endif
            @if ($chatUrl)
                <a href="{{ $chatUrl }}" class="cliente-wa-sidebar__icon-btn" title="Abrir chat">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z"/></svg>
                </a>
            @endif
        </div>
    </div>

    <div class="cliente-wa-sidebar__hilo">
        @if ($mensajes === [])
            <div class="cliente-wa-empty">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H8.25m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H12m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 01-2.555-.337A5.972 5.972 0 015.41 20.97a5.969 5.969 0 01-.474-.065 4.48 4.48 0 00.978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25z"/></svg>
                <p>Sin mensajes de WhatsApp registrados para este cliente.</p>
            </div>
        @else
            @foreach ($mensajes as $msg)
                @if (($msg['dia'] ?? null) && $msg['dia'] !== $diaAnterior)
                    @php $diaAnterior = $msg['dia']; @endphp
                    <div class="cliente-wa-dia">
                        <span>{{ ($msg['dia'] ?? '') === now()->format('Y-m-d') ? 'HOY' : ($msg['dia_label'] ?? $msg['dia']) }}</span>
                    </div>
                @endif

                <div class="cliente-wa-row {{ ($msg['entrada'] ?? false) ? 'cliente-wa-row--in' : 'cliente-wa-row--out' }}">
                    <div class="cliente-wa-bubble {{ ($msg['entrada'] ?? false) ? 'cliente-wa-bubble--in' : 'cliente-wa-bubble--out' }} {{ ! empty($msg['fallido']) ? 'cliente-wa-bubble--fail' : '' }}">
                        @if (! empty($msg['tipo_label']))
                            <div class="cliente-wa-bubble__meta">{{ $msg['tipo_label'] }}</div>
                        @endif
                        @if (! empty($msg['media_es_imagen']) && ! empty($msg['media_url']))
                            <a href="{{ $msg['media_url'] }}" target="_blank" rel="noopener noreferrer">
                                <img src="{{ $msg['media_url'] }}" alt="Imagen" class="cliente-wa-bubble__img" loading="lazy">
                            </a>
                        @elseif (! empty($msg['media_url']))
                            <a href="{{ $msg['media_url'] }}" target="_blank" rel="noopener noreferrer" class="cliente-wa-bubble__link">Ver adjunto</a>
                        @endif
                        @if (! empty($msg['maps_url']))
                            <a href="{{ $msg['maps_url'] }}" target="_blank" rel="noopener noreferrer" class="cliente-wa-bubble__link">{{ $msg['maps_label'] ?? 'Ver mapa' }}</a>
                        @endif
                        @if (! empty($msg['cuerpo']))
                            <div class="cliente-wa-bubble__text">{{ $msg['cuerpo'] }}</div>
                        @endif
                        <div class="cliente-wa-bubble__foot">
                            <span class="cliente-wa-bubble__hora">{{ $msg['hora'] ?? '' }}</span>
                            @if (empty($msg['entrada']) && ! empty($msg['estado']) && in_array($msg['estado'], ['entregado', 'leido', 'enviado'], true) && empty($msg['fallido']))
                                <span class="cliente-wa-bubble__checks" title="{{ $msg['estado_label'] ?? '' }}">✓✓</span>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        @endif
    </div>

    @if ($enviarUrl)
        <div class="cliente-wa-sidebar__foot">
            <a href="{{ $enviarUrl }}" class="cliente-wa-sidebar__composer">
                <svg class="h-5 w-5 shrink-0 opacity-70" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                Escribir mensaje…
            </a>
        </div>
    @endif
</section>

@if (! empty($mensajes))
<script>
(function () {
    var hilo = document.querySelector('.cliente-wa-sidebar__hilo');
    if (hilo) hilo.scrollTop = hilo.scrollHeight;
})();
</script>
@endif
