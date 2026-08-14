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
    $plantillaUrl = $waVista['plantilla_url']
        ?? (($telWa !== '' && (auth()->user()?->tienePermiso('whatsapp.editar') ?? false))
            ? route('whatsapp.enviar', ['telefono' => $telWa])
            : null);
    $puedeEnviar = (bool) ($waVista['puede_enviar'] ?? (
        $telWa !== '' && (auth()->user()?->tienePermiso('whatsapp.editar') ?? false)
    ));
    $fueraVentana = (bool) ($waVista['fuera_ventana'] ?? ($mensajes === []));
    $enviarApiUrl = route('whatsapp.enviar.store');
    $enviarAdjuntoUrl = route('whatsapp.enviar-adjunto');
@endphp

<section class="cliente-wa-sidebar {{ $wrapperClass ?? '' }}"
         data-telefono="{{ e($telWa) }}"
         data-enviar-url="{{ e($enviarApiUrl) }}"
         data-enviar-adjunto-url="{{ e($enviarAdjuntoUrl) }}"
         data-fuera-ventana="{{ $fueraVentana ? '1' : '0' }}">
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
                <a href="{{ $chatUrl }}" class="cliente-wa-sidebar__icon-btn" title="Abrir chat completo">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z"/></svg>
                </a>
            @endif
        </div>
    </div>

    <div class="cliente-wa-sidebar__hilo" data-cliente-wa-hilo>
        @if ($mensajes === [])
            <div class="cliente-wa-empty" data-cliente-wa-empty>
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
                        @if (! empty($msg['tipo_label']) && empty($msg['media_es_imagen']))
                            <div class="cliente-wa-bubble__meta">{{ $msg['tipo_label'] }}</div>
                        @endif
                        @if (! empty($msg['media_es_imagen']) && ! empty($msg['media_url']))
                            <button type="button" class="cliente-wa-bubble__img-btn" data-cliente-wa-img="{{ e($msg['media_url']) }}" title="Ver imagen">
                                <img src="{{ e($msg['media_url']) }}" alt="Imagen" class="cliente-wa-bubble__img">
                            </button>
                        @elseif (! empty($msg['media_url']))
                            <a href="{{ $msg['media_url'] }}" target="_blank" rel="noopener noreferrer" class="cliente-wa-bubble__link" data-cliente-wa-doc="{{ e($msg['media_url']) }}">Ver adjunto</a>
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

    @if ($puedeEnviar)
        <div class="cliente-wa-sidebar__foot">
            @if ($fueraVentana)
                <div class="cliente-wa-sidebar__warn" data-cliente-wa-warn>
                    Fuera de ventana 24 h: el texto libre puede fallar.
                    @if ($plantillaUrl)
                        <a href="{{ $plantillaUrl }}">Enviar plantilla</a>
                    @endif
                </div>
            @endif
            <div class="cliente-wa-sidebar__pending" data-cliente-wa-pending hidden>
                <img src="" alt="" class="cliente-wa-sidebar__pending-img" data-cliente-wa-pending-img hidden>
                <div class="min-w-0 flex-1">
                    <p class="cliente-wa-sidebar__pending-name truncate" data-cliente-wa-pending-name></p>
                    <p class="cliente-wa-sidebar__hint">El texto se envía como pie</p>
                </div>
                <button type="button" class="cliente-wa-sidebar__icon-btn" data-cliente-wa-pending-clear title="Quitar">✕</button>
            </div>
            <form class="cliente-wa-sidebar__composer-form" data-cliente-wa-form>
                <input
                    type="file"
                    class="hidden"
                    data-cliente-wa-file
                    accept="image/jpeg,image/png,image/jpg,.pdf,application/pdf,video/mp4,audio/*,.doc,.docx,.xls,.xlsx,.txt"
                >
                <button
                    type="button"
                    class="cliente-wa-sidebar__icon-btn"
                    data-cliente-wa-attach
                    title="Adjuntar imagen, PDF u otro"
                    @if ($fueraVentana) disabled @endif
                >
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/></svg>
                </button>
                <textarea
                    name="texto"
                    rows="1"
                    maxlength="4000"
                    placeholder="Escribir mensaje…"
                    class="cliente-wa-sidebar__input"
                    data-cliente-wa-input
                    autocomplete="off"
                    @if ($fueraVentana) disabled @endif
                ></textarea>
                @if ($plantillaUrl)
                    <a href="{{ $plantillaUrl }}" class="cliente-wa-sidebar__icon-btn" title="Enviar plantilla">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                    </a>
                @endif
                <button
                    type="submit"
                    class="cliente-wa-sidebar__send"
                    title="{{ $fueraVentana ? 'Fuera de ventana 24h' : 'Enviar (Enter)' }}"
                    data-cliente-wa-send
                    @if ($fueraVentana) disabled @endif
                >
                    <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24"><path d="M1.101 21.757L23.8 12.017 1.101 2.276 1.1 10.01l15.4 2.007-15.4 2.007z"/></svg>
                </button>
            </form>
            @unless ($fueraVentana)
                <p class="cliente-wa-sidebar__hint">Enter envía · clip adjunto · Shift+Enter línea</p>
            @endunless
            <p class="cliente-wa-sidebar__error" data-cliente-wa-error hidden></p>
        </div>
    @elseif ($chatUrl)
        <div class="cliente-wa-sidebar__foot">
            <a href="{{ $chatUrl }}" class="cliente-wa-sidebar__composer">
                Abrir chat de WhatsApp
            </a>
        </div>
    @endif
</section>

<div class="cliente-wa-lightbox" data-cliente-wa-lightbox hidden>
    <button type="button" class="cliente-wa-lightbox__close" data-cliente-wa-lightbox-close aria-label="Cerrar">Cerrar</button>
    <img src="" alt="Imagen" class="cliente-wa-lightbox__img" data-cliente-wa-lightbox-img>
</div>

<script>
(function () {
    var root = document.querySelector('.cliente-wa-sidebar');
    if (!root) return;

    var hilo = root.querySelector('[data-cliente-wa-hilo]');
    if (hilo) hilo.scrollTop = hilo.scrollHeight;

    var lightbox = document.querySelector('[data-cliente-wa-lightbox]');
    var lightboxImg = lightbox ? lightbox.querySelector('[data-cliente-wa-lightbox-img]') : null;
    function csrf() {
        var m = document.querySelector('meta[name="csrf-token"]');
        return m ? m.getAttribute('content') : '';
    }
    function cerrarLightbox() {
        if (!lightbox) return;
        lightbox.hidden = true;
        if (lightboxImg) lightboxImg.src = '';
    }
    function abrirLightbox(url) {
        if (!lightbox || !lightboxImg || !url) return;
        lightboxImg.src = url;
        lightbox.hidden = false;
    }
    root.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-cliente-wa-img]');
        if (!btn || !root.contains(btn)) return;
        e.preventDefault();
        var url = btn.getAttribute('data-cliente-wa-img');
        abrirLightbox(url);
    });
    if (lightbox) {
        lightbox.addEventListener('click', function (e) {
            if (e.target === lightbox || e.target.closest('[data-cliente-wa-lightbox-close]')) {
                cerrarLightbox();
            }
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && lightbox && !lightbox.hidden) cerrarLightbox();
        });
    }

    var form = root.querySelector('[data-cliente-wa-form]');
    if (!form) return;

    var input = root.querySelector('[data-cliente-wa-input]');
    var btn = root.querySelector('[data-cliente-wa-send]');
    var errEl = root.querySelector('[data-cliente-wa-error]');
    var telefono = root.getAttribute('data-telefono') || '';
    var enviarUrl = root.getAttribute('data-enviar-url') || '';
    var enviando = false;

    function showError(msg) {
        if (!errEl) return;
        if (!msg) {
            errEl.hidden = true;
            errEl.textContent = '';
            return;
        }
        errEl.hidden = false;
        errEl.textContent = msg;
    }

    function escapeHtml(s) {
        return String(s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function appendBubble(msg) {
        if (!hilo || !msg) return;
        var empty = hilo.querySelector('[data-cliente-wa-empty]');
        if (empty) empty.remove();

        var entrada = msg.direccion === 'entrada' || msg.entrada === true;
        var fallido = msg.estado === 'fallido' || msg.fallido === true;
        var cuerpo = msg.cuerpo || '';
        var hora = msg.hora || '';
        var mediaHtml = '';
        if (msg.tipo === 'image' && msg.media_url) {
            mediaHtml = '<button type="button" class="cliente-wa-bubble__img-btn" data-cliente-wa-img="' + escapeHtml(msg.media_url) + '" title="Ver imagen">' +
                '<img src="' + escapeHtml(msg.media_url) + '" alt="Imagen" class="cliente-wa-bubble__img"></button>';
            if (cuerpo === 'Imagen') cuerpo = '';
        } else if (msg.media_url && msg.tipo !== 'text') {
            mediaHtml = '<a href="' + escapeHtml(msg.media_url) + '" target="_blank" rel="noopener" class="cliente-wa-bubble__link">Ver ' + escapeHtml(msg.tipo || 'adjunto') + '</a>';
        }
        var row = document.createElement('div');
        row.className = 'cliente-wa-row ' + (entrada ? 'cliente-wa-row--in' : 'cliente-wa-row--out');
        var bubbleClass = 'cliente-wa-bubble ' + (entrada ? 'cliente-wa-bubble--in' : 'cliente-wa-bubble--out');
        if (fallido) bubbleClass += ' cliente-wa-bubble--fail';
        var checks = '';
        if (!entrada && !fallido && msg.estado && ['enviado', 'entregado', 'leido'].indexOf(msg.estado) !== -1) {
            checks = '<span class="cliente-wa-bubble__checks">✓✓</span>';
        }
        row.innerHTML =
            '<div class="' + bubbleClass + '">' +
                mediaHtml +
                (cuerpo ? '<div class="cliente-wa-bubble__text">' + escapeHtml(cuerpo) + '</div>' : '') +
                '<div class="cliente-wa-bubble__foot">' +
                    '<span class="cliente-wa-bubble__hora">' + escapeHtml(hora) + '</span>' +
                    checks +
                '</div>' +
            '</div>';
        hilo.appendChild(row);
        hilo.scrollTop = hilo.scrollHeight;
    }

    function fueraVentana() {
        return root.getAttribute('data-fuera-ventana') === '1';
    }

    var fileInput = root.querySelector('[data-cliente-wa-file]');
    var attachBtn = root.querySelector('[data-cliente-wa-attach]');
    var pendingEl = root.querySelector('[data-cliente-wa-pending]');
    var pendingImg = root.querySelector('[data-cliente-wa-pending-img]');
    var pendingName = root.querySelector('[data-cliente-wa-pending-name]');
    var pendingClear = root.querySelector('[data-cliente-wa-pending-clear]');
    var enviarAdjuntoUrl = root.getAttribute('data-enviar-adjunto-url') || '';
    var adjuntoFile = null;
    var adjuntoPreviewUrl = null;

    function syncSendBtn() {
        if (!btn) return;
        var hasText = !!(input && input.value.trim());
        btn.disabled = fueraVentana() || enviando || (!hasText && !adjuntoFile);
    }

    function focusInput() {
        if (!input || fueraVentana()) return;
        input.focus({ preventScroll: true });
    }

    function resetInputHeight() {
        if (!input) return;
        input.style.height = 'auto';
        input.style.height = Math.min(input.scrollHeight, 112) + 'px';
    }

    function clearInputKeepFocus() {
        if (!input) return;
        input.value = '';
        resetInputHeight();
        syncSendBtn();
        focusInput();
    }

    function quitarAdjunto() {
        adjuntoFile = null;
        if (adjuntoPreviewUrl) {
            try { URL.revokeObjectURL(adjuntoPreviewUrl); } catch (_) {}
            adjuntoPreviewUrl = null;
        }
        if (pendingEl) pendingEl.hidden = true;
        if (pendingImg) {
            pendingImg.hidden = true;
            pendingImg.src = '';
        }
        if (pendingName) pendingName.textContent = '';
        if (input) input.placeholder = 'Escribir mensaje…';
        syncSendBtn();
    }

    function setAdjunto(file) {
        quitarAdjunto();
        if (!file) return;
        adjuntoFile = file;
        if (pendingName) pendingName.textContent = file.name;
        if (pendingEl) pendingEl.hidden = false;
        if (file.type && file.type.indexOf('image/') === 0 && pendingImg) {
            adjuntoPreviewUrl = URL.createObjectURL(file);
            pendingImg.src = adjuntoPreviewUrl;
            pendingImg.hidden = false;
        }
        if (input) input.placeholder = 'Pie de foto / mensaje (opcional)';
        syncSendBtn();
        focusInput();
    }

    if (attachBtn && fileInput) {
        attachBtn.addEventListener('click', function () {
            if (fueraVentana() || enviando) return;
            fileInput.click();
        });
        fileInput.addEventListener('change', function () {
            var f = fileInput.files && fileInput.files[0];
            fileInput.value = '';
            if (f) setAdjunto(f);
        });
    }
    if (pendingClear) {
        pendingClear.addEventListener('click', function () {
            quitarAdjunto();
            focusInput();
        });
    }

    if (input) {
        input.addEventListener('input', function () {
            syncSendBtn();
            resetInputHeight();
        });
        input.addEventListener('keydown', function (e) {
            if (e.key !== 'Enter' || e.shiftKey || e.isComposing) return;
            e.preventDefault();
            if (!enviando && !fueraVentana() && (input.value.trim() || adjuntoFile)) {
                form.requestSubmit();
            }
        });
        syncSendBtn();
        if (!fueraVentana()) focusInput();
    }

    function parseJsonResponse(res) {
        return res.json().then(function (data) {
            return { ok: res.ok, status: res.status, data: data };
        });
    }

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        if (enviando || !input || !telefono || fueraVentana()) return;

        var texto = (input.value || '').trim();
        if (adjuntoFile) {
            if (!enviarAdjuntoUrl) return;
            var file = adjuntoFile;
            var caption = texto;
            showError('');
            clearInputKeepFocus();
            quitarAdjunto();
            enviando = true;
            syncSendBtn();

            var fd = new FormData();
            fd.append('telefono', telefono);
            fd.append('archivo', file, file.name);
            if (caption) fd.append('caption', caption);

            fetch(enviarAdjuntoUrl, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrf(),
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
                body: fd,
            })
                .then(parseJsonResponse)
                .then(function (result) {
                    if (result.ok && result.data && result.data.ok) {
                        appendBubble(result.data.mensaje);
                        return;
                    }
                    showError((result.data && (result.data.error || result.data.message)) || 'Falló el envío del archivo');
                    if (result.data && result.data.mensaje) appendBubble(result.data.mensaje);
                })
                .catch(function () {
                    showError('No se pudo enviar el archivo. Revisá la conexión.');
                })
                .finally(function () {
                    enviando = false;
                    syncSendBtn();
                    focusInput();
                });
            return;
        }

        if (!texto || !enviarUrl) return;

        showError('');
        clearInputKeepFocus();
        enviando = true;
        syncSendBtn();

        fetch(enviarUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrf(),
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
            body: JSON.stringify({
                telefono: telefono,
                modo: 'texto',
                texto: texto,
            }),
        })
            .then(parseJsonResponse)
            .then(function (result) {
                if (result.ok && result.data && result.data.ok) {
                    appendBubble(result.data.mensaje);
                    return;
                }
                var msg = (result.data && (result.data.error || result.data.message)) || 'Falló el envío';
                showError(msg);
                if (result.data && result.data.mensaje) {
                    appendBubble(result.data.mensaje);
                } else if (input && !input.value.trim()) {
                    input.value = texto;
                    resetInputHeight();
                }
            })
            .catch(function () {
                showError('No se pudo enviar el mensaje. Revisá la conexión.');
                if (input && !input.value.trim()) {
                    input.value = texto;
                    resetInputHeight();
                }
            })
            .finally(function () {
                enviando = false;
                syncSendBtn();
                focusInput();
            });
    });
})();
</script>
