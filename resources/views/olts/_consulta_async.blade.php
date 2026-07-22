{{-- Overlay + AJAX para consultas OLT en segundo plano --}}
<div id="olt-consulta-overlay" class="fixed inset-0 z-[9999] hidden items-center justify-center bg-black/50 p-4" aria-live="polite" aria-busy="true">
    <div class="w-full max-w-sm rounded-xl bg-white p-6 text-center shadow-xl dark:bg-gray-800">
        <svg class="mx-auto h-10 w-10 animate-spin text-purple-600 dark:text-purple-400" fill="none" viewBox="0 0 24 24" aria-hidden="true">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
        </svg>
        <p id="olt-consulta-overlay-msg" class="mt-4 text-sm font-medium text-gray-900 dark:text-gray-100">Consultando OLT…</p>
        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">La consulta corre en segundo plano; no cierres esta pestaña.</p>
    </div>
</div>

@once
<script>
(function () {
    if (window.OltConsulta) return;

    var overlay = document.getElementById('olt-consulta-overlay');
    var msgEl = document.getElementById('olt-consulta-overlay-msg');
    var busy = false;

    function csrf() {
        var m = document.querySelector('meta[name="csrf-token"]');
        return m ? m.getAttribute('content') : '';
    }

    function showOverlay(text) {
        overlay = document.getElementById('olt-consulta-overlay');
        msgEl = document.getElementById('olt-consulta-overlay-msg');
        if (!overlay) return;
        if (msgEl) msgEl.textContent = text || 'Consultando OLT…';
        overlay.classList.remove('hidden');
        overlay.classList.add('flex');
    }

    function hideOverlay() {
        overlay = document.getElementById('olt-consulta-overlay');
        if (!overlay) return;
        overlay.classList.add('hidden');
        overlay.classList.remove('flex');
    }

    function flashReplace(type, message) {
        var main = document.querySelector('main');
        if (!main || !message) return;
        var cls = {
            success: 'mb-6 rounded-lg bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 px-4 py-3 text-green-800 dark:text-green-200 print:hidden break-words text-sm',
            error: 'mb-6 rounded-lg bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 px-4 py-3 text-red-800 dark:text-red-200 print:hidden',
            warning: 'mb-6 rounded-lg bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 px-4 py-3 text-amber-900 dark:text-amber-200 print:hidden break-words text-sm'
        };
        main.querySelectorAll('[data-olt-flash]').forEach(function (n) { n.remove(); });
        var div = document.createElement('div');
        div.setAttribute('data-olt-flash', '1');
        div.className = cls[type] || cls.warning;
        div.textContent = message;
        main.insertBefore(div, main.firstChild);
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    function withSinSync(url) {
        try {
            var u = new URL(url, window.location.origin);
            u.searchParams.set('sin_sync', '1');
            return u.pathname + u.search;
        } catch (e) {
            return url;
        }
    }

    window.OltConsulta = {
        post: function (url, options) {
            options = options || {};
            if (busy) return Promise.reject(new Error('Ya hay una consulta en curso'));
            busy = true;
            showOverlay(options.message || 'Consultando OLT…');

            return fetch(url, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrf(),
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(options.body || {}),
                credentials: 'same-origin'
            }).then(function (res) {
                return res.json().then(function (data) {
                    return { ok: res.ok, status: res.status, data: data || {} };
                }).catch(function () {
                    return { ok: res.ok, status: res.status, data: { message: 'Respuesta inválida del servidor' } };
                });
            }).then(function (result) {
                busy = false;
                hideOverlay();
                var data = result.data || {};
                var msg = data.message || (result.ok ? 'Consulta finalizada' : 'Error al consultar el OLT');

                if (!result.ok || data.success === false) {
                    flashReplace('error', msg);
                    if (typeof options.onError === 'function') options.onError(data);
                    return data;
                }

                if (data.skipped) {
                    if (typeof options.onSkip === 'function') options.onSkip(data);
                    return data;
                }

                if (typeof options.onSuccess === 'function') {
                    options.onSuccess(data);
                    return data;
                }

                if (data.redirect) {
                    window.location.href = withSinSync(data.redirect);
                    return data;
                }

                window.location.href = options.reloadUrl || withSinSync(window.location.href);
                return data;
            }).catch(function (err) {
                busy = false;
                hideOverlay();
                flashReplace('error', err && err.message ? err.message : 'No se pudo completar la consulta');
                if (typeof options.onError === 'function') options.onError(err);
            });
        }
    };

    document.addEventListener('submit', function (e) {
        var form = e.target;
        if (!form || !form.classList || !form.classList.contains('js-olt-consulta')) return;
        e.preventDefault();
        if (form.dataset.confirm && !window.confirm(form.dataset.confirm)) return;

        var action = form.getAttribute('action');
        if (!action) return;

        window.OltConsulta.post(action, {
            message: form.dataset.loading || 'Consultando OLT…',
            reloadUrl: form.dataset.reload || null
        });
    });

    document.addEventListener('DOMContentLoaded', function () {
        var host = document.querySelector('[data-olt-auto-sync]');
        var auto = host ? host.getAttribute('data-olt-auto-sync') : null;
        if (!auto) return;
        window.OltConsulta.post(auto, {
            message: 'Consultando OLT…',
            reloadUrl: withSinSync(window.location.href),
            onSkip: function () {}
        });
    });
})();
</script>
@endonce
