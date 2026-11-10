const cfg = window.__CHAT_INTERNO__;
if (cfg) {
    const yo = Number(cfg.usuarioId);
    const lista = document.getElementById('chat-lista');
    const panel = document.getElementById('chat-panel');
    const aside = document.getElementById('chat-aside');
    const hilo = document.getElementById('chat-hilo');
    const vacio = document.getElementById('chat-vacio');
    const head = document.getElementById('chat-hilo-head');
    const titulo = document.getElementById('chat-hilo-titulo');
    const sub = document.getElementById('chat-hilo-sub');
    const composer = document.getElementById('chat-composer');
    const texto = document.getElementById('chat-texto');
    const archivo = document.getElementById('chat-archivo');
    const archivoNombre = document.getElementById('chat-archivo-nombre');
    const accion = document.getElementById('chat-accion');
    const asignadosBox = document.getElementById('chat-asignados');
    const errorBox = document.getElementById('chat-error');
    const modal = document.getElementById('chat-modal');
    const modalTitulo = document.getElementById('chat-modal-titulo');
    const modalNombre = document.getElementById('chat-modal-nombre');
    const modalBuscar = document.getElementById('chat-modal-buscar');
    const modalLista = document.getElementById('chat-modal-lista');
    const modalError = document.getElementById('chat-modal-error');

    let conversaciones = [];
    let usuarios = [];
    let actual = null;
    let modoModal = 'directo';
    let canalHilo = null;
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';

    function headers(json) {
        const base = {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': csrf,
        };
        if (json) base['Content-Type'] = 'application/json';
        return base;
    }

    async function leerError(res) {
        try {
            const data = await res.json();
            if (data.errors) {
                const primero = Object.values(data.errors)[0];
                return Array.isArray(primero) ? primero[0] : 'No se pudo completar.';
            }
            return data.message || 'No se pudo completar.';
        } catch (e) {
            return 'No se pudo completar.';
        }
    }

    function hora(iso) {
        if (!iso) return '';
        const fecha = new Date(iso);
        return fecha.toLocaleString('es-PY', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' });
    }

    function escapar(valor) {
        return String(valor ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function preview(item) {
        if (!item.ultimo) return 'Sin mensajes';
        if (item.ultimo.cuerpo) return item.ultimo.cuerpo;
        if (item.ultimo.adjunto_nombre) return 'Archivo: ' + item.ultimo.adjunto_nombre;
        return 'Mensaje';
    }

    function pintarLista() {
        if (!conversaciones.length) {
            lista.innerHTML = '<p class="p-4 text-sm text-gray-500 dark:text-gray-400">Todavía no hay conversaciones.</p>';
            return;
        }
        lista.innerHTML = conversaciones.map((item) => {
            const activo = actual && Number(actual.id) === Number(item.id);
            const badge = item.no_leidos > 0
                ? '<span class="ml-2 inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-blue-600 px-1 text-xs text-white">' + item.no_leidos + '</span>'
                : '';
            return '<button type="button" data-id="' + item.id + '" class="flex w-full items-start gap-2 border-b border-gray-100 px-4 py-3 text-left hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-700/60 ' + (activo ? 'bg-blue-50 dark:bg-gray-700' : '') + '">'
                + '<span class="min-w-0 flex-1"><span class="flex items-center"><span class="truncate font-medium text-gray-900 dark:text-gray-100">' + escapar(item.titulo) + '</span>' + badge + '</span>'
                + '<span class="mt-0.5 block truncate text-xs text-gray-500 dark:text-gray-400">' + escapar(preview(item)) + '</span></span></button>';
        }).join('');
    }

    function mostrarHilo(visible) {
        vacio.classList.toggle('hidden', visible);
        vacio.classList.toggle('flex', !visible);
        hilo.classList.toggle('hidden', !visible);
        hilo.classList.toggle('flex', visible);
        hilo.classList.toggle('flex-col', visible);
        head.classList.toggle('hidden', !visible);
        head.classList.toggle('flex', visible);
        composer.classList.toggle('hidden', !visible);
        if (window.matchMedia('(max-width: 767px)').matches) {
            aside.classList.toggle('hidden', visible);
            panel.classList.toggle('hidden', !visible);
            panel.classList.toggle('flex', visible);
        }
    }

    function estadoAccion(mensaje) {
        if (!mensaje.requiere_accion) return '';
        const mia = (mensaje.asignados || []).find((item) => Number(item.usuario_id) === yo);
        if (mia && !mia.atendido_at) return '<p class="mt-1 text-xs font-medium text-amber-600 dark:text-amber-400">Te pidieron una acción</p>';
        const pendientes = (mensaje.asignados || []).filter((item) => !item.atendido_at).length;
        if (pendientes === 0) return '<p class="mt-1 text-xs text-emerald-600 dark:text-emerald-400">Acción atendida</p>';
        return '<p class="mt-1 text-xs text-amber-600 dark:text-amber-400">Acción pendiente</p>';
    }

    function htmlAdjunto(mensaje) {
        if (!mensaje.adjunto) return '';
        const adj = mensaje.adjunto;
        if (adj.es_imagen) {
            return '<a href="' + escapar(adj.url) + '" target="_blank" rel="noopener"><img src="' + escapar(adj.url) + '" alt="' + escapar(adj.nombre) + '" class="mt-2 max-h-48 rounded-lg"></a>';
        }
        return '<a href="' + escapar(adj.url) + '" class="mt-2 inline-flex items-center text-sm font-medium text-blue-600 hover:underline dark:text-blue-400" target="_blank" rel="noopener">' + escapar(adj.nombre) + '</a>';
    }

    function htmlMensaje(mensaje) {
        const propio = Number(mensaje.usuario_id) === yo;
        const lado = propio ? 'ml-auto bg-blue-600 text-white' : 'mr-auto bg-white text-gray-900 dark:bg-gray-800 dark:text-gray-100';
        const cuerpo = mensaje.cuerpo ? '<p class="whitespace-pre-wrap">' + escapar(mensaje.cuerpo) + '</p>' : '';
        return '<article data-mensaje="' + mensaje.id + '" class="max-w-lg rounded-2xl px-3 py-2 text-sm shadow-sm ' + lado + '">'
            + (!propio ? '<p class="mb-1 text-xs font-semibold opacity-80">' + escapar(mensaje.autor) + '</p>' : '')
            + cuerpo
            + htmlAdjunto(mensaje)
            + estadoAccion(mensaje)
            + '<p class="mt-1 text-[11px] opacity-70">' + escapar(hora(mensaje.creado_at)) + '</p></article>';
    }

    function agregarMensaje(mensaje) {
        if (!mensaje || !actual || Number(mensaje.conversacion_id) !== Number(actual.id)) return;
        if (hilo.querySelector('[data-mensaje="' + mensaje.id + '"]')) return;
        hilo.insertAdjacentHTML('beforeend', htmlMensaje(mensaje));
        hilo.scrollTop = hilo.scrollHeight;
    }

    function pintarAsignados() {
        const grupo = actual && actual.tipo === 'grupo' && accion.checked;
        asignadosBox.classList.toggle('hidden', !grupo);
        if (!grupo) {
            asignadosBox.innerHTML = '';
            return;
        }
        asignadosBox.innerHTML = (actual.participantes || [])
            .filter((persona) => Number(persona.usuario_id) !== yo)
            .map((persona) => '<label class="inline-flex items-center gap-1 rounded-full bg-gray-100 px-2 py-1 text-xs text-gray-700 dark:bg-gray-700 dark:text-gray-200"><input type="checkbox" value="' + persona.usuario_id + '" class="chat-asignado h-3.5 w-3.5 rounded border-gray-300 text-blue-600"> ' + escapar(persona.name) + '</label>')
            .join('');
    }

    function suscribirHilo(id) {
        if (!window.Echo) return;
        if (canalHilo) window.Echo.leave('chat.conversacion.' + canalHilo);
        canalHilo = id;
        window.Echo.private('chat.conversacion.' + id)
            .listen('.mensaje.creado', (evento) => agregarMensaje(evento.mensaje))
            .listen('.accion.atendida', () => abrir(id, true));
    }

    async function abrir(id, silencioso) {
        const res = await fetch(cfg.urls.mensajes + '/' + id + '/mensajes', { headers: headers(false), credentials: 'same-origin' });
        if (!res.ok) {
            errorBox.textContent = await leerError(res);
            errorBox.classList.remove('hidden');
            return;
        }
        const data = await res.json();
        actual = data.conversacion;
        titulo.textContent = actual.titulo;
        sub.textContent = actual.tipo === 'grupo'
            ? (actual.participantes || []).map((persona) => persona.name).join(', ')
            : 'Mensaje directo';
        hilo.innerHTML = (data.mensajes || []).map(htmlMensaje).join('');
        mostrarHilo(true);
        hilo.scrollTop = hilo.scrollHeight;
        pintarAsignados();
        const idx = conversaciones.findIndex((item) => Number(item.id) === Number(actual.id));
        if (idx >= 0) {
            conversaciones[idx] = Object.assign({}, conversaciones[idx], actual, { no_leidos: 0 });
        } else {
            conversaciones.unshift(Object.assign({}, actual, { no_leidos: 0 }));
        }
        pintarLista();
        if (!silencioso) suscribirHilo(actual.id);
        else if (canalHilo !== actual.id) suscribirHilo(actual.id);
    }

    async function cargarLista() {
        const res = await fetch(cfg.urls.conversaciones, { headers: headers(false), credentials: 'same-origin' });
        if (!res.ok) return;
        const data = await res.json();
        conversaciones = data.conversaciones || [];
        pintarLista();
    }

    async function cargarUsuarios() {
        if (usuarios.length) return usuarios;
        const res = await fetch(cfg.urls.usuarios, { headers: headers(false), credentials: 'same-origin' });
        if (!res.ok) return [];
        const data = await res.json();
        usuarios = data.usuarios || [];
        return usuarios;
    }

    function pintarModal() {
        const consulta = (modalBuscar.value || '').toLowerCase();
        const filtro = usuarios.filter((persona) => persona.name.toLowerCase().includes(consulta));
        const tipo = modoModal === 'grupo' ? 'checkbox' : 'radio';
        modalLista.innerHTML = filtro.map((persona) => '<label class="flex cursor-pointer items-center gap-2 rounded-lg px-2 py-1.5 hover:bg-gray-50 dark:hover:bg-gray-700"><input type="' + tipo + '" name="chat-persona" value="' + persona.usuario_id + '" class="text-blue-600"><span class="text-sm text-gray-800 dark:text-gray-100">' + escapar(persona.name) + (persona.cargo ? ' <span class="text-gray-500">· ' + escapar(persona.cargo) + '</span>' : '') + '</span></label>').join('')
            || '<p class="px-2 py-3 text-sm text-gray-500">No hay personal para mostrar.</p>';
    }

    async function abrirModal(modo) {
        modoModal = modo;
        modalError.classList.add('hidden');
        modalTitulo.textContent = modo === 'grupo' ? 'Nuevo grupo' : 'Mensaje directo';
        modalNombre.classList.toggle('hidden', modo !== 'grupo');
        modalNombre.value = '';
        modalBuscar.value = '';
        await cargarUsuarios();
        pintarModal();
        modal.classList.remove('hidden');
        modal.classList.add('flex');
    }

    function cerrarModal() {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }

    lista.addEventListener('click', (evento) => {
        const boton = evento.target.closest('[data-id]');
        if (boton) abrir(boton.dataset.id, false);
    });

    document.getElementById('chat-volver').addEventListener('click', () => mostrarHilo(false));
    document.getElementById('chat-btn-directo').addEventListener('click', () => abrirModal('directo'));
    document.getElementById('chat-btn-grupo').addEventListener('click', () => abrirModal('grupo'));
    document.getElementById('chat-modal-cancelar').addEventListener('click', cerrarModal);
    modalBuscar.addEventListener('input', pintarModal);
    accion.addEventListener('change', pintarAsignados);

    archivo.addEventListener('change', () => {
        const file = archivo.files && archivo.files[0];
        archivoNombre.textContent = file ? file.name : '';
        archivoNombre.classList.toggle('hidden', !file);
    });

    texto.addEventListener('keydown', (evento) => {
        if (evento.key === 'Enter' && !evento.shiftKey) {
            evento.preventDefault();
            composer.requestSubmit();
        }
    });

    document.getElementById('chat-modal-form').addEventListener('submit', async (evento) => {
        evento.preventDefault();
        modalError.classList.add('hidden');
        const elegidos = Array.from(modalLista.querySelectorAll('input:checked')).map((input) => Number(input.value));
        if (!elegidos.length) {
            modalError.textContent = 'Elige al menos una persona.';
            modalError.classList.remove('hidden');
            return;
        }
        let res;
        if (modoModal === 'directo') {
            res = await fetch(cfg.urls.directos, {
                method: 'POST',
                headers: headers(true),
                credentials: 'same-origin',
                body: JSON.stringify({ usuario_id: elegidos[0] }),
            });
        } else {
            if (!modalNombre.value.trim()) {
                modalError.textContent = 'El grupo necesita un nombre.';
                modalError.classList.remove('hidden');
                return;
            }
            res = await fetch(cfg.urls.grupos, {
                method: 'POST',
                headers: headers(true),
                credentials: 'same-origin',
                body: JSON.stringify({ titulo: modalNombre.value.trim(), usuario_ids: elegidos }),
            });
        }
        if (!res.ok) {
            modalError.textContent = await leerError(res);
            modalError.classList.remove('hidden');
            return;
        }
        const data = await res.json();
        cerrarModal();
        await cargarLista();
        abrir(data.conversacion.id, false);
    });

    composer.addEventListener('submit', async (evento) => {
        evento.preventDefault();
        if (!actual) return;
        errorBox.classList.add('hidden');
        const form = new FormData();
        form.append('cuerpo', texto.value);
        form.append('requiere_accion', accion.checked ? '1' : '0');
        if (archivo.files && archivo.files[0]) form.append('archivo', archivo.files[0]);
        if (accion.checked && actual.tipo === 'grupo') {
            asignadosBox.querySelectorAll('.chat-asignado:checked').forEach((input) => form.append('asignados[]', input.value));
        }
        const res = await fetch(cfg.urls.mensajes + '/' + actual.id + '/mensajes', {
            method: 'POST',
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrf },
            credentials: 'same-origin',
            body: form,
        });
        if (!res.ok) {
            errorBox.textContent = await leerError(res);
            errorBox.classList.remove('hidden');
            return;
        }
        const data = await res.json();
        texto.value = '';
        archivo.value = '';
        archivoNombre.classList.add('hidden');
        accion.checked = false;
        pintarAsignados();
        agregarMensaje(data.mensaje);
        const idx = conversaciones.findIndex((item) => Number(item.id) === Number(actual.id));
        if (idx >= 0) {
            conversaciones[idx].ultimo = {
                id: data.mensaje.id,
                cuerpo: data.mensaje.cuerpo,
                adjunto_nombre: data.mensaje.adjunto ? data.mensaje.adjunto.nombre : null,
                usuario_id: data.mensaje.usuario_id,
                creado_at: data.mensaje.creado_at,
            };
            conversaciones[idx].no_leidos = 0;
            const [item] = conversaciones.splice(idx, 1);
            conversaciones.unshift(item);
            pintarLista();
        }
    });

    window.addEventListener('chat:mensaje', (evento) => {
        const mensaje = evento.detail;
        if (!mensaje) return;
        agregarMensaje(mensaje);
        const idx = conversaciones.findIndex((item) => Number(item.id) === Number(mensaje.conversacion_id));
        if (idx >= 0) {
            const abierto = actual && Number(actual.id) === Number(mensaje.conversacion_id);
            conversaciones[idx].ultimo = {
                id: mensaje.id,
                cuerpo: mensaje.cuerpo,
                adjunto_nombre: mensaje.adjunto ? mensaje.adjunto.nombre : null,
                usuario_id: mensaje.usuario_id,
                creado_at: mensaje.creado_at,
            };
            if (!abierto && Number(mensaje.usuario_id) !== yo) {
                conversaciones[idx].no_leidos = (Number(conversaciones[idx].no_leidos) || 0) + 1;
            }
            const [item] = conversaciones.splice(idx, 1);
            conversaciones.unshift(item);
            pintarLista();
        } else {
            cargarLista();
        }
    });

    window.addEventListener('chat:accion', (evento) => {
        const detalle = evento.detail || {};
        if (actual && hilo.querySelector('[data-mensaje="' + detalle.mensaje_id + '"]')) {
            abrir(actual.id, true);
        }
    });

    cargarLista().then(() => {
        if (cfg.abrir) abrir(cfg.abrir, false);
    });
}
