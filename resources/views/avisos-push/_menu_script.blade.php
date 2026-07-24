{{-- Dropdown fijo teletransportado (mismo patrón que solicitudes/servicios) --}}
<div id="push-acciones-dropdown"
     class="hidden fixed py-1 min-w-[220px] max-w-[280px] bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 shadow-lg z-[9999]"
     aria-hidden="true"></div>

@once
@push('scripts')
<script>
(function () {
    if (window.__pushAccionesMenuInit) return;
    window.__pushAccionesMenuInit = true;

    var menuEl = document.getElementById('push-acciones-dropdown');
    if (!menuEl) return;
    var openBtn = null;

    var icons = {
        refresh: '<svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>',
        trash: '<svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>'
    };

    var colorClass = {
        slate: 'text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700/50',
        blue: 'text-blue-600 dark:text-blue-400 hover:bg-blue-50 dark:hover:bg-blue-900/30',
        red: 'text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/30'
    };

    function esc(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }

    function b64ToUtf8(b64) {
        var bin = atob(b64);
        try {
            return decodeURIComponent(Array.prototype.map.call(bin, function (c) {
                return '%' + ('00' + c.charCodeAt(0).toString(16)).slice(-2);
            }).join(''));
        } catch (e) {
            return bin;
        }
    }

    function cerrar() {
        menuEl.classList.add('hidden');
        menuEl.setAttribute('aria-hidden', 'true');
        menuEl.innerHTML = '';
        if (openBtn) {
            openBtn.setAttribute('aria-expanded', 'false');
            openBtn = null;
        }
    }

    function construir(cfg) {
        var base = 'w-full px-4 py-2.5 text-left text-sm flex items-center gap-2';
        var html = '';
        (cfg.items || []).forEach(function (it, idx) {
            var col = colorClass[it.color] || colorClass.slate;
            var ic = icons[it.icon] || icons.refresh;
            html += '<form method="POST" action="' + esc(it.action) + '" class="block push-menu-form" data-confirm-idx="' + idx + '">';
            html += '<input type="hidden" name="_token" value="' + esc(cfg.csrf) + '">';
            if (it.method_spoof) {
                html += '<input type="hidden" name="_method" value="' + esc(it.method_spoof) + '">';
            }
            html += '<button type="submit" class="' + base + ' ' + col + '">' + ic + ' ' + esc(it.label) + '</button>';
            html += '</form>';
        });
        menuEl._confirms = (cfg.items || []).map(function (it) { return it.confirm || null; });
        return html;
    }

    function posicionar(btn) {
        var rect = btn.getBoundingClientRect();
        var mw = 240;
        var mh = menuEl.offsetHeight || 160;
        var left = Math.max(8, Math.min(rect.right - mw, window.innerWidth - mw - 8));
        var top = rect.bottom + 4;
        if (rect.bottom + mh + 12 > window.innerHeight) {
            top = Math.max(8, rect.top - mh - 4);
        }
        menuEl.style.left = left + 'px';
        menuEl.style.top = top + 'px';
    }

    menuEl.addEventListener('submit', function (e) {
        var form = e.target.closest('.push-menu-form');
        if (!form) return;
        var idx = parseInt(form.getAttribute('data-confirm-idx'), 10);
        var msg = (menuEl._confirms && menuEl._confirms[idx]) || null;
        if (msg && !window.confirm(msg)) {
            e.preventDefault();
        }
    });

    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.push-acciones-kebab');
        if (btn) {
            e.preventDefault();
            e.stopPropagation();
            var raw = btn.getAttribute('data-menu-b64');
            if (!raw) return;
            if (openBtn === btn) {
                cerrar();
                return;
            }
            var cfg;
            try {
                cfg = JSON.parse(b64ToUtf8(raw));
            } catch (err) {
                return;
            }
            cerrar();
            openBtn = btn;
            btn.setAttribute('aria-expanded', 'true');
            menuEl.innerHTML = construir(cfg);
            menuEl.classList.remove('hidden');
            menuEl.setAttribute('aria-hidden', 'false');
            posicionar(btn);
            return;
        }
        if (!menuEl.classList.contains('hidden') && !menuEl.contains(e.target)) {
            cerrar();
        }
    });

    window.addEventListener('scroll', cerrar, true);
    window.addEventListener('resize', cerrar);
})();
</script>
@endpush
@endonce
