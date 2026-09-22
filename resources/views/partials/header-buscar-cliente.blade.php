<div id="header-buscar-cliente" class="flex-1 min-w-0 max-w-xl mx-2 sm:mx-4 relative" data-url="{{ route('clientes.buscar') }}">
    <style>
        @media (max-width: 639px) {
            #header-cliente-abrir { display: inline-flex; }
            #header-buscar-cliente:not(.is-abierto) .header-cliente-campo { display: none; }
            #header-buscar-cliente.is-abierto #header-cliente-abrir { display: none; }
            #header-buscar-cliente.is-abierto {
                position: absolute;
                inset-inline: 0;
                z-index: 20;
                max-width: none;
                margin-left: 0;
                margin-right: 0;
            }
            #header-bar.is-header-buscando > :not(#header-buscar-cliente) {
                visibility: hidden;
                pointer-events: none;
            }
        }
        @media (min-width: 640px) {
            #header-cliente-abrir,
            #header-cliente-cerrar { display: none !important; }
            #header-buscar-cliente .header-cliente-campo { display: block; }
        }
    </style>
    <label for="header-cliente-q" class="sr-only">Buscar cliente</label>
    <button type="button" id="header-cliente-abrir"
            class="hidden h-9 w-9 items-center justify-center rounded-lg text-gray-500 hover:text-gray-700 hover:bg-gray-100 dark:text-gray-400 dark:hover:text-gray-200 dark:hover:bg-gray-800"
            aria-label="Buscar cliente" title="Buscar cliente">
        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M17 11a6 6 0 11-12 0 6 6 0 0112 0z"/>
        </svg>
    </button>
    <div class="header-cliente-campo relative">
        <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M17 11a6 6 0 11-12 0 6 6 0 0112 0z"/>
            </svg>
        </span>
        <input
            type="search"
            id="header-cliente-q"
            autocomplete="off"
            placeholder="Buscar cliente…"
            class="w-full h-9 pl-9 pr-9 rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-sm text-gray-900 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-blue-500/30 focus:border-blue-500"
            aria-autocomplete="list"
            aria-controls="header-cliente-resultados"
            aria-expanded="false"
            aria-haspopup="listbox"
        >
        <button type="button" id="header-cliente-cerrar"
                class="absolute inset-y-0 right-1 px-2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200"
                aria-label="Cerrar búsqueda" title="Cerrar">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
        </button>
        <kbd class="hidden sm:flex absolute inset-y-0 right-2 items-center text-[10px] font-medium text-gray-400 dark:text-gray-500 pointer-events-none">Ctrl K</kbd>
    </div>
    <ul
        id="header-cliente-resultados"
        class="hidden absolute z-50 mt-1 w-full max-h-80 overflow-auto rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-lg py-1"
        role="listbox"
    ></ul>
</div>
<script>
(function () {
    var root = document.getElementById('header-buscar-cliente');
    if (!root) return;
    var url = root.getAttribute('data-url') || '';
    var input = document.getElementById('header-cliente-q');
    var list = document.getElementById('header-cliente-resultados');
    var bar = document.getElementById('header-bar');
    var btnAbrir = document.getElementById('header-cliente-abrir');
    var btnCerrar = document.getElementById('header-cliente-cerrar');
    if (!input || !list || !url) return;

    var timer = null;
    var items = [];
    var active = -1;
    var lastQ = '';

    function esCompacto() {
        return window.matchMedia('(max-width: 639px)').matches;
    }

    function expandir() {
        root.classList.add('is-abierto');
        if (bar) bar.classList.add('is-header-buscando');
        if (btnAbrir) btnAbrir.setAttribute('aria-expanded', 'true');
        setTimeout(function () { input.focus(); }, 0);
    }

    function colapsar() {
        root.classList.remove('is-abierto');
        if (bar) bar.classList.remove('is-header-buscando');
        if (btnAbrir) btnAbrir.setAttribute('aria-expanded', 'false');
        cerrar();
    }

    function cerrar() {
        list.classList.add('hidden');
        list.innerHTML = '';
        items = [];
        active = -1;
        input.setAttribute('aria-expanded', 'false');
    }

    function abrir() {
        list.classList.remove('hidden');
        input.setAttribute('aria-expanded', 'true');
    }

    function etiquetaEstado(estado) {
        var map = { activo: 'Activo', inactivo: 'Inactivo', suspendido: 'Suspendido', solo_pedido: 'Pedido' };
        return map[estado] || estado || '';
    }

    function pintar() {
        list.innerHTML = '';
        if (items.length === 0) {
            var vacio = document.createElement('li');
            vacio.className = 'px-3 py-2 text-sm text-gray-500 dark:text-gray-400';
            vacio.textContent = lastQ.length < 2 ? 'Escribí al menos 2 caracteres' : 'Sin resultados';
            list.appendChild(vacio);
            abrir();
            return;
        }
        items.forEach(function (c, i) {
            var a = document.createElement('a');
            a.href = c.detalle_url;
            a.id = 'header-cliente-opt-' + i;
            a.setAttribute('role', 'option');
            a.setAttribute('aria-selected', i === active ? 'true' : 'false');
            a.className = 'block px-3 py-2 text-sm no-underline ' + (i === active
                ? 'bg-blue-50 dark:bg-blue-900/40 text-gray-900 dark:text-gray-100'
                : 'text-gray-800 dark:text-gray-100 hover:bg-gray-50 dark:hover:bg-gray-700/60');
            var nombre = document.createElement('span');
            nombre.className = 'block font-medium truncate';
            nombre.textContent = ((c.nombre || '') + ' ' + (c.apellido || '')).trim() || ('#' + c.cliente_id);
            var meta = document.createElement('span');
            meta.className = 'block text-xs text-gray-500 dark:text-gray-400 truncate';
            var partes = ['#' + c.cliente_id];
            if (c.cedula) partes.push(c.cedula);
            var est = etiquetaEstado(c.estado);
            if (est) partes.push(est);
            meta.textContent = partes.join(' · ');
            a.appendChild(nombre);
            a.appendChild(meta);
            var li = document.createElement('li');
            li.appendChild(a);
            list.appendChild(li);
        });
        abrir();
        var sel = list.querySelector('[aria-selected="true"]');
        if (sel && sel.scrollIntoView) {
            sel.scrollIntoView({ block: 'nearest' });
        }
    }

    function buscar(q) {
        lastQ = q;
        if (q.length < 2) {
            items = [];
            if (q.length === 0) {
                cerrar();
                return;
            }
            pintar();
            return;
        }
        fetch(url + '?q=' + encodeURIComponent(q), {
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        }).then(function (r) {
            return r.json().then(function (data) {
                if (!r.ok) throw new Error('fail');
                return data;
            });
        }).then(function (data) {
            if (input.value.trim() !== q) return;
            items = Array.isArray(data) ? data : [];
            active = items.length ? 0 : -1;
            pintar();
        }).catch(function () {
            if (input.value.trim() !== q) return;
            items = [];
            lastQ = q;
            list.innerHTML = '';
            var err = document.createElement('li');
            err.className = 'px-3 py-2 text-sm text-red-600 dark:text-red-400';
            err.textContent = 'No se pudo buscar.';
            list.appendChild(err);
            abrir();
        });
    }

    function irActivo() {
        if (active < 0 || !items[active] || !items[active].detalle_url) return;
        window.location.href = items[active].detalle_url;
    }

    if (btnAbrir) {
        btnAbrir.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            expandir();
        });
    }
    if (btnCerrar) {
        btnCerrar.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            input.value = '';
            colapsar();
        });
    }

    input.addEventListener('input', function () {
        clearTimeout(timer);
        var q = input.value.trim();
        timer = setTimeout(function () { buscar(q); }, 220);
    });

    input.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            cerrar();
            if (esCompacto()) {
                input.value = '';
                colapsar();
            } else {
                input.blur();
            }
            return;
        }
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            if (!items.length) return;
            active = (active + 1) % items.length;
            pintar();
            return;
        }
        if (e.key === 'ArrowUp') {
            e.preventDefault();
            if (!items.length) return;
            active = (active - 1 + items.length) % items.length;
            pintar();
            return;
        }
        if (e.key === 'Enter') {
            if (items.length && active >= 0) {
                e.preventDefault();
                irActivo();
            }
        }
    });

    document.addEventListener('click', function (e) {
        if (root.contains(e.target)) return;
        cerrar();
        if (esCompacto() && root.classList.contains('is-abierto')) colapsar();
    });

    document.addEventListener('keydown', function (e) {
        if (!(e.ctrlKey || e.metaKey) || e.key.toLowerCase() !== 'k') return;
        if (e.target && (e.target.tagName === 'TEXTAREA' || e.target.isContentEditable)) return;
        e.preventDefault();
        if (esCompacto()) expandir();
        input.focus();
        input.select();
    });

    window.addEventListener('resize', function () {
        if (!esCompacto()) colapsar();
    });
})();
</script>
