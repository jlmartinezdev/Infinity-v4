{{-- Dropdown fijo teletransportado (mismo patrón que tickets/servicios) --}}
<div id="sol-acciones-dropdown"
     class="hidden fixed py-1 min-w-[220px] max-w-[280px] bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 shadow-lg z-[9999]"
     aria-hidden="true"></div>

@once
@push('scripts')
<script>
(function () {
    if (window.__solAccionesMenuInit) return;
    window.__solAccionesMenuInit = true;

    var menuEl = document.getElementById('sol-acciones-dropdown');
    if (!menuEl) return;
    var openBtn = null;

    var icons = {
        eye: '<svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>',
        check: '<svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>',
        x: '<svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>',
        key: '<svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/></svg>',
        pause: '<svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 9v6m4-6v6m7-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>',
        play: '<svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>',
        'user-x': '<svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7a4 4 0 11-8 0 4 4 0 018 0zM9 14a7 7 0 00-7 7h8m5-8l5 5m0-5l-5 5"/></svg>',
        trash: '<svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>'
    };

    var colorClass = {
        slate: 'text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700/50',
        emerald: 'text-emerald-600 dark:text-emerald-400 hover:bg-emerald-50 dark:hover:bg-emerald-900/30',
        rose: 'text-rose-600 dark:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-900/30',
        blue: 'text-blue-600 dark:text-blue-400 hover:bg-blue-50 dark:hover:bg-blue-900/30',
        amber: 'text-amber-600 dark:text-amber-400 hover:bg-amber-50 dark:hover:bg-amber-900/30',
        orange: 'text-orange-600 dark:text-orange-400 hover:bg-orange-50 dark:hover:bg-orange-900/30',
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
            var ic = icons[it.icon] || icons.eye;
            if (it.type === 'link') {
                html += '<a href="' + esc(it.href) + '" class="block ' + base + ' ' + col + '">' + ic + ' ' + esc(it.label) + '</a>';
                return;
            }
            html += '<form method="POST" action="' + esc(it.action) + '" class="block sol-menu-form" data-confirm-idx="' + idx + '">';
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
        var mh = menuEl.offsetHeight || 260;
        var left = Math.max(8, Math.min(rect.right - mw, window.innerWidth - mw - 8));
        var top = rect.bottom + 4;
        if (rect.bottom + mh + 12 > window.innerHeight) {
            top = Math.max(8, rect.top - mh - 4);
        }
        menuEl.style.left = left + 'px';
        menuEl.style.top = top + 'px';
    }

    menuEl.addEventListener('submit', function (e) {
        var form = e.target.closest('.sol-menu-form');
        if (!form) return;
        var idx = parseInt(form.getAttribute('data-confirm-idx'), 10);
        var msg = (menuEl._confirms && menuEl._confirms[idx]) || null;
        if (msg && !window.confirm(msg)) {
            e.preventDefault();
        }
    });

    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.sol-acciones-kebab');
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
