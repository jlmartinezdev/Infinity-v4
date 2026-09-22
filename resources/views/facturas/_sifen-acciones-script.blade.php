<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11" crossorigin="anonymous"></script>
<style>
html.dark .swal2-popup { border: 1px solid #374151; }
html.dark .swal2-title { color: #f3f4f6; }
html.dark .swal2-html-container { color: #d1d5db; }
html.dark .swal2-input,
html.dark .swal2-textarea,
html.dark .swal2-select {
    background-color: #111827 !important;
    color: #f3f4f6 !important;
    border-color: #4b5563 !important;
}
html.dark .swal2-validation-message {
    background: #111827;
    color: #fca5a5;
}
</style>
<div id="fe-acciones-dropdown"
     class="hidden fixed py-1 min-w-[200px] max-w-[260px] bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 shadow-lg z-[9999]"
     role="menu"
     aria-hidden="true"></div>
<script>
(function () {
    const csrf = @json(csrf_token());
    const motivos = @json(config('sifen.motivos_emision_nc_nd'));
    const menuEl = document.getElementById('fe-acciones-dropdown');
    let openBtn = null;

    function swalTheme() {
        if (!document.documentElement.classList.contains('dark')) {
            return {};
        }
        return {
            theme: 'dark',
            background: '#1f2937',
            color: '#f3f4f6',
        };
    }
    window.infinitySwalTheme = swalTheme;

    function postForm(url, fields) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = url;
        const token = document.createElement('input');
        token.type = 'hidden';
        token.name = '_token';
        token.value = csrf;
        form.appendChild(token);
        Object.entries(fields || {}).forEach(([name, value]) => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = name;
            input.value = value;
            form.appendChild(input);
        });
        document.body.appendChild(form);
        form.submit();
    }

    function esc(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }

    function leerMenu(btn) {
        const b64 = btn.getAttribute('data-menu-b64');
        if (!b64) {
            return JSON.parse(btn.getAttribute('data-menu') || '[]');
        }
        const bin = atob(b64);
        const json = decodeURIComponent(Array.prototype.map.call(bin, (c) => {
            return '%' + ('00' + c.charCodeAt(0).toString(16)).slice(-2);
        }).join(''));
        return JSON.parse(json);
    }

    function pedirCancelacion(item) {
        if (typeof Swal === 'undefined') {
            const motivo = window.prompt('Motivo de cancelación (mín. 5 caracteres)');
            if (motivo && motivo.trim().length >= 5) {
                postForm(item.url, { motivo: motivo.trim() });
            }
            return;
        }
        Swal.fire(Object.assign({
            icon: 'warning',
            title: 'Cancelar en SIFEN',
            html: 'Se enviará el evento de cancelación de <strong>' + esc(item.numero || 'esta factura')
                + '</strong>.'
                + (item.limite ? '<br><span class="text-sm">Vence el ' + esc(item.limite) + '.</span>' : ''),
            input: 'textarea',
            inputLabel: 'Motivo',
            inputPlaceholder: 'Ej. Error en datos del cliente',
            inputAttributes: { maxlength: 500 },
            showCancelButton: true,
            confirmButtonText: 'Cancelar factura',
            cancelButtonText: 'Volver',
            confirmButtonColor: '#dc2626',
            inputValidator: (value) => {
                if (!value || value.trim().length < 5) {
                    return 'El motivo debe tener al menos 5 caracteres.';
                }
                return undefined;
            },
        }, swalTheme())).then((result) => {
            if (result.isConfirmed) {
                postForm(item.url, { motivo: String(result.value).trim() });
            }
        });
    }

    function pedirNotaCredito(item) {
        if (typeof Swal === 'undefined') {
            postForm(item.url, { motivo_emision: 2 });
            return;
        }
        Swal.fire(Object.assign({
            icon: 'question',
            title: 'Preparar nota de crédito',
            html: 'Se crea un <strong>borrador</strong> de nota de crédito para <strong>'
                + esc(item.numero || 'esta factura') + '</strong>. Después hay que emitirla a SIFEN.',
            input: 'select',
            inputOptions: motivos,
            inputValue: '2',
            showCancelButton: true,
            confirmButtonText: 'Crear borrador',
            cancelButtonText: 'Volver',
            confirmButtonColor: '#0284c7',
        }, swalTheme())).then((result) => {
            if (result.isConfirmed) {
                postForm(item.url, { motivo_emision: result.value || 2 });
            }
        });
    }

    function cerrarMenu() {
        if (!menuEl) return;
        menuEl.classList.add('hidden');
        menuEl.setAttribute('aria-hidden', 'true');
        menuEl.innerHTML = '';
        if (openBtn) {
            openBtn.setAttribute('aria-expanded', 'false');
            openBtn = null;
        }
    }

    function posicionar(btn) {
        const rect = btn.getBoundingClientRect();
        const mw = 220;
        const mh = menuEl.offsetHeight || 160;
        const left = Math.max(8, Math.min(rect.right - mw, window.innerWidth - mw - 8));
        let top = rect.bottom + 4;
        if (rect.bottom + mh + 12 > window.innerHeight) {
            top = Math.max(8, rect.top - mh - 4);
        }
        menuEl.style.left = left + 'px';
        menuEl.style.top = top + 'px';
    }

    function itemClass(extra) {
        return 'w-full px-4 py-2.5 text-left text-sm flex items-center gap-2 ' + extra;
    }

    function construir(items) {
        return items.map((it, idx) => {
            if (it.type === 'link') {
                return '<a href="' + esc(it.url) + '" class="' + itemClass('text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700/60') + '" role="menuitem">' + esc(it.label) + '</a>';
            }
            if (it.type === 'lote') {
                return '<button type="button" class="' + itemClass('text-indigo-600 dark:text-indigo-400 hover:bg-indigo-50 dark:hover:bg-indigo-900/30') + '" data-idx="' + idx + '" role="menuitem">' + esc(it.label) + '</button>';
            }
            if (it.type === 'cancelar') {
                return '<button type="button" class="' + itemClass('text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/30') + '" data-idx="' + idx + '" role="menuitem">' + esc(it.label) + '</button>';
            }
            return '<button type="button" class="' + itemClass('text-sky-600 dark:text-sky-400 hover:bg-sky-50 dark:hover:bg-sky-900/30') + '" data-idx="' + idx + '" role="menuitem">' + esc(it.label) + '</button>';
        }).join('');
    }

    document.querySelectorAll('.js-sifen-cancelar').forEach((btn) => {
        btn.addEventListener('click', () => pedirCancelacion({
            url: btn.dataset.url,
            numero: btn.dataset.numero,
            limite: btn.dataset.limite,
        }));
    });

    document.querySelectorAll('.js-sifen-nc').forEach((btn) => {
        btn.addEventListener('click', () => pedirNotaCredito({
            url: btn.dataset.url,
            numero: btn.dataset.numero,
        }));
    });

    if (menuEl) {
        document.addEventListener('click', (e) => {
            const btn = e.target.closest('.js-fe-acciones-menu');
            if (btn) {
                e.preventDefault();
                e.stopPropagation();
                if (openBtn === btn) {
                    cerrarMenu();
                    return;
                }
                let items;
                try {
                    items = leerMenu(btn);
                } catch (err) {
                    return;
                }
                cerrarMenu();
                openBtn = btn;
                btn.setAttribute('aria-expanded', 'true');
                menuEl._items = items;
                menuEl.innerHTML = construir(items);
                if (menuEl.parentElement !== document.body) {
                    document.body.appendChild(menuEl);
                }
                menuEl.classList.remove('hidden');
                menuEl.setAttribute('aria-hidden', 'false');
                posicionar(btn);
                return;
            }
            if (!menuEl.classList.contains('hidden') && !menuEl.contains(e.target)) {
                cerrarMenu();
            }
        });

        menuEl.addEventListener('click', (e) => {
            const actionBtn = e.target.closest('button[data-idx]');
            if (!actionBtn) return;
            const item = (menuEl._items || [])[parseInt(actionBtn.getAttribute('data-idx'), 10)];
            if (!item) return;
            cerrarMenu();
            if (item.type === 'lote') {
                postForm(item.url, {});
                return;
            }
            if (item.type === 'cancelar') {
                pedirCancelacion(item);
                return;
            }
            if (item.type === 'nc') {
                pedirNotaCredito(item);
            }
        });

        window.addEventListener('scroll', cerrarMenu, true);
        window.addEventListener('resize', cerrarMenu);
    }
})();
</script>
