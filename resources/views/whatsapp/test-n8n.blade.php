@extends('layouts.app')

@section('title', 'WhatsApp - Test n8n')

@section('content')
@include('partials.whatsapp-chat-theme')
<div class="mx-auto max-w-[1400px]">
    <div class="mb-4 flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">WhatsApp</h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Playground del agente n8n: no sale a WhatsApp real</p>
        </div>
    </div>

    @include('whatsapp._tabs', ['waTab' => 'test-n8n'])

    <div id="whatsapp-test-n8n-app" class="mt-3">
        <div class="rounded-xl border border-gray-200 bg-white p-10 text-center text-sm text-gray-500 shadow-xl dark:border-[#2a3942] dark:bg-[#111b21] dark:text-[#8696a0]">
            Cargando test n8n...
        </div>
    </div>
    <pre id="wa-resumen-admin" class="mx-auto mt-3 hidden max-h-48 max-w-3xl overflow-auto whitespace-pre-wrap rounded-xl border border-gray-200 bg-white p-3 text-xs text-gray-600 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300"></pre>
</div>

<script>
window.__WHATSAPP_TEST_N8N_CONFIG__ = @json([
    'telInicial' => $telInicial,
    'urls' => $urls,
]);
</script>

@push('scripts')
<script src="{{ asset(mix('js/whatsapp-test-n8n.js')) }}" defer></script>
<script>
(function () {
    var urls = (window.__WHATSAPP_TEST_N8N_CONFIG__ || {}).urls || {};
    var enviandoImg = false;

    function csrf() {
        var el = document.head.querySelector('meta[name="csrf-token"]');
        return el ? el.content : '';
    }

    function telefonoActivo() {
        var el = document.querySelector('.wa-header .font-mono');
        return el ? (el.textContent || '').trim() : '';
    }

    function captionActual() {
        var ta = document.querySelector('.wa-composer textarea');
        return ta ? (ta.value || '').trim() : '';
    }

    function archivoElegido() {
        var file = document.getElementById('wa-test-n8n-imagen');
        return file && file.files && file.files[0] ? file.files[0] : null;
    }

    function enviarImagenPrueba(archivo) {
        if (enviandoImg) return;
        if (!urls.mensaje || !window.axios) {
            alert('No hay endpoint de mensaje');
            return;
        }
        enviandoImg = true;
        var fd = new FormData();
        fd.append('telefono', telefonoActivo());
        fd.append('mensaje', captionActual());
        fd.append('imagen', archivo);
        var hint = document.getElementById('wa-test-n8n-img-hint');
        var sendImg = document.getElementById('wa-test-n8n-send-img');
        if (hint) {
            hint.textContent = 'Enviando imagen al agente...';
            hint.classList.remove('hidden');
        }
        if (sendImg) sendImg.disabled = true;
        window.axios.post(urls.mensaje, fd, {
            timeout: 90000,
            headers: { 'X-CSRF-TOKEN': csrf() },
        }).then(function (res) {
            var data = res.data || {};
            var box = document.getElementById('wa-resumen-admin');
            if (box) {
                box.textContent = (data.agent && data.agent.resumen_admin) || '';
                box.classList.toggle('hidden', !box.textContent);
            }
            var tel = data.telefono || telefonoActivo();
            window.location = window.location.pathname + '?tel=' + encodeURIComponent(tel);
        }).catch(function (e) {
            enviandoImg = false;
            var msg = (e.response && e.response.data && (e.response.data.error || e.response.data.message)) || e.message || 'Error';
            alert(msg);
            if (hint) hint.textContent = msg;
            if (sendImg) sendImg.disabled = false;
        });
    }

    function montarUiImagen() {
        var row = document.querySelector('.wa-composer .flex.items-end');
        if (!row || row.dataset.waImgReady === '1') return !!row;
        row.dataset.waImgReady = '1';

        var file = document.createElement('input');
        file.type = 'file';
        file.accept = 'image/jpeg,image/png,image/webp,image/gif';
        file.className = 'hidden';
        file.id = 'wa-test-n8n-imagen';

        var btn = document.createElement('button');
        btn.type = 'button';
        btn.title = 'Adjuntar imagen';
        btn.className = 'wa-chip flex h-10 w-10 shrink-0 items-center justify-center rounded-full text-lg';
        btn.textContent = '📷';
        btn.addEventListener('click', function () { file.click(); });

        var hint = document.createElement('div');
        hint.id = 'wa-test-n8n-img-hint';
        hint.className = 'mb-1.5 hidden flex flex-wrap items-center gap-2 px-1 text-[11px] text-emerald-700';

        var sendImg = document.createElement('button');
        sendImg.type = 'button';
        sendImg.id = 'wa-test-n8n-send-img';
        sendImg.className = 'hidden rounded-full bg-emerald-600 px-3 py-1 text-[11px] font-semibold text-white disabled:opacity-40';
        sendImg.textContent = 'Enviar imagen';
        sendImg.addEventListener('click', function () {
            var f = archivoElegido();
            if (f) enviarImagenPrueba(f);
        });

        var form = row.closest('form');
        if (form) {
            form.insertBefore(hint, row);
            hint.appendChild(document.createElement('span'));
            hint.appendChild(sendImg);
        }
        row.insertBefore(btn, row.firstChild);
        row.appendChild(file);

        file.addEventListener('change', function () {
            var f = file.files && file.files[0];
            if (!f) {
                hint.classList.add('hidden');
                sendImg.classList.add('hidden');
                return;
            }
            hint.firstChild.textContent = 'Imagen lista: ' + f.name + ' —';
            hint.classList.remove('hidden');
            sendImg.classList.remove('hidden');
            // Vue deshabilita el submit si no hay texto; lo reactivamos.
            var sendBtn = row.querySelector('button[type="submit"]');
            if (sendBtn) sendBtn.disabled = false;
        });

        // Captura submit/Enter antes que Vue (el bundle viejo no manda FormData).
        if (form) {
            form.addEventListener('submit', function (ev) {
                var f = archivoElegido();
                if (!f) return;
                ev.preventDefault();
                ev.stopImmediatePropagation();
                enviarImagenPrueba(f);
            }, true);
        }
        document.addEventListener('keydown', function (ev) {
            if (ev.key !== 'Enter' || ev.shiftKey) return;
            if (!(ev.target && ev.target.closest && ev.target.closest('.wa-composer'))) return;
            var f = archivoElegido();
            if (!f) return;
            ev.preventDefault();
            ev.stopImmediatePropagation();
            enviarImagenPrueba(f);
        }, true);

        // Vue puede volver a disabled=true al re-render; mantener habilitado con imagen.
        setInterval(function () {
            if (!archivoElegido()) return;
            var sendBtn = row.querySelector('button[type="submit"]');
            if (sendBtn && sendBtn.disabled && !enviandoImg) sendBtn.disabled = false;
        }, 400);

        return true;
    }

    function boot() {
        if (window.axios) {
            window.axios.interceptors.response.use(function (response) {
                var resumen = response && response.data && response.data.agent && response.data.agent.resumen_admin;
                var box = document.getElementById('wa-resumen-admin');
                if (!box) return response;
                box.textContent = resumen || '';
                box.classList.toggle('hidden', !resumen);
                return response;
            });
        }
        var intentos = 0;
        var timer = setInterval(function () {
            intentos += 1;
            if (montarUiImagen() || intentos > 60) clearInterval(timer);
        }, 250);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
</script>
@endpush
@endsection
