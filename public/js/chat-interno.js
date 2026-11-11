const cfg = window.__CHAT_INTERNO__;
if (cfg) {
    const yo = Number(cfg.usuarioId);
    const raiz = document.getElementById('chat-interno');
    const lista = document.getElementById('chat-lista');
    const hilo = document.getElementById('chat-hilo');
    const vacio = document.getElementById('chat-vacio');
    const head = document.getElementById('chat-hilo-head');
    const titulo = document.getElementById('chat-hilo-titulo');
    const sub = document.getElementById('chat-hilo-sub');
    const avatarHilo = document.getElementById('chat-hilo-avatar');
    const irAcciones = document.getElementById('chat-ir-acciones');
    const detalle = document.getElementById('chat-detalle');
    const composer = document.getElementById('chat-composer');
    const texto = document.getElementById('chat-texto');
    const archivo = document.getElementById('chat-archivo');
    const archivoNombre = document.getElementById('chat-archivo-nombre');
    const btnArchivo = document.getElementById('chat-btn-archivo');
    const btnImagen = document.getElementById('chat-btn-imagen');
    const btnMencion = document.getElementById('chat-btn-mencion');
    const menciones = document.getElementById('chat-menciones');
    const accion = document.getElementById('chat-accion');
    const accionBtn = document.getElementById('chat-accion-btn');
    const asignadosBox = document.getElementById('chat-asignados');
    const errorBox = document.getElementById('chat-error');
    const modal = document.getElementById('chat-modal');
    const modalTitulo = document.getElementById('chat-modal-titulo');
    const modalNombre = document.getElementById('chat-modal-nombre');
    const modalBuscar = document.getElementById('chat-modal-buscar');
    const modalLista = document.getElementById('chat-modal-lista');
    const modalError = document.getElementById('chat-modal-error');
    const buscar = document.getElementById('chat-buscar');
    const filtroAcciones = document.getElementById('chat-filtro-acciones');
    const filtroNoLeidos = document.getElementById('chat-filtro-noleidos');
    const menu = document.getElementById('chat-msg-menu');
    const reenviar = document.getElementById('chat-reenviar');
    const reenviarLista = document.getElementById('chat-reenviar-lista');
    const reenviarError = document.getElementById('chat-reenviar-error');

    let conversaciones = [];
    let usuarios = [];
    let actual = null;
    let mensajesActuales = [];
    let modoModal = 'directo';
    let canalHilo = null;
    let filtro = '';
    let mencionIndice = 0;
    let menuMensaje = null;
    let menuConfirma = false;
    const aceptaArchivo = archivo.getAttribute('accept') || '';
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const colores = ['#2563eb', '#7c3aed', '#0f766e', '#c2410c', '#be185d', '#1d4ed8'];

    document.getElementById('chat-yo-nombre').textContent = cfg.nombre || 'Staff';
    document.getElementById('chat-yo-avatar').textContent = iniciales(cfg.nombre || 'Yo');

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

    function escapar(valor) {
        return String(valor ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function iniciales(nombre) {
        const partes = String(nombre || '').trim().split(/\s+/).filter(Boolean);
        const letras = (partes[0] || '?').slice(0, 1) + (partes[1] ? partes[1].slice(0, 1) : '');
        return letras.toUpperCase();
    }

    function colorDe(nombre) {
        let n = 0;
        const texto = String(nombre || '');
        for (let i = 0; i < texto.length; i += 1) n += texto.charCodeAt(i);
        return colores[n % colores.length];
    }

    function horaCorta(iso) {
        if (!iso) return '';
        const fecha = new Date(iso);
        return fecha.toLocaleTimeString('es-PY', { hour: '2-digit', minute: '2-digit' });
    }

    function dia(iso) {
        if (!iso) return '';
        const fecha = new Date(iso);
        const hoy = new Date();
        const texto = fecha.toLocaleDateString('es-PY', { day: 'numeric', month: 'long' });
        return fecha.toDateString() === hoy.toDateString() ? 'Hoy, ' + texto : texto;
    }

    function peso(bytes) {
        const n = Number(bytes) || 0;
        if (n < 1024) return n + ' B';
        if (n < 1048576) return Math.round(n / 1024) + ' KB';
        return (n / 1048576).toFixed(1) + ' MB';
    }

    function preview(item) {
        if (!item.ultimo) return 'Sin mensajes';
        if (item.ultimo.cuerpo) return item.ultimo.cuerpo;
        if (item.ultimo.adjunto_nombre) return item.ultimo.adjunto_nombre;
        return 'Mensaje';
    }

    function otroDe(item) {
        return (item.participantes || []).find((persona) => Number(persona.usuario_id) !== yo) || null;
    }

    function visibles() {
        const q = (buscar.value || '').trim().toLowerCase();
        return conversaciones.filter((item) => {
            if (filtro === 'acciones' && !(Number(item.acciones_pendientes) > 0)) return false;
            if (filtro === 'noleidos' && !(Number(item.no_leidos) > 0)) return false;
            if (!q) return true;
            return (item.titulo || '').toLowerCase().includes(q) || preview(item).toLowerCase().includes(q);
        });
    }

    function pintarChips() {
        const acciones = conversaciones.reduce((suma, item) => suma + (Number(item.acciones_pendientes) > 0 ? 1 : 0), 0);
        const noLeidos = conversaciones.reduce((suma, item) => suma + (Number(item.no_leidos) || 0), 0);
        filtroAcciones.textContent = acciones ? 'Acciones pendientes (' + acciones + ')' : 'Acciones pendientes';
        filtroNoLeidos.textContent = 'No leídos (' + noLeidos + ')';
        filtroAcciones.setAttribute('aria-pressed', filtro === 'acciones' ? 'true' : 'false');
        filtroNoLeidos.setAttribute('aria-pressed', filtro === 'noleidos' ? 'true' : 'false');
    }

    function htmlCanal(item) {
        const activo = actual && Number(actual.id) === Number(item.id);
        const pedidos = Number(item.acciones_pendientes) || 0;
        const cuenta = Number(item.no_leidos) || pedidos;
        const badge = cuenta > 0
            ? '<span class="st-count ' + (pedidos > 0 ? 'rojo' : 'cielo') + '">' + cuenta + '</span>'
            : '';
        return '<button type="button" class="st-canal' + (activo ? ' activo' : '') + '" data-id="' + item.id + '"><span><span class="hash">#</span>' + escapar(item.titulo || 'grupo') + '</span>' + badge + '</button>';
    }

    function htmlDirecto(item) {
        const activo = actual && Number(actual.id) === Number(item.id);
        const nombre = item.titulo || 'Chat';
        const pedidos = Number(item.acciones_pendientes) || 0;
        const aviso = pedidos > 0
            ? '<span class="st-tag">' + pedidos + (pedidos === 1 ? ' pedido de acción' : ' pedidos de acción') + '</span>'
            : '';
        return '<button type="button" class="st-dm' + (activo ? ' activo' : '') + '" data-id="' + item.id + '">'
            + '<span class="st-av">' + escapar(iniciales(nombre)) + '</span>'
            + '<span class="txt"><span class="fila"><b>' + escapar(nombre) + '</b><small>' + escapar(horaCorta(item.ultimo && item.ultimo.creado_at)) + '</small></span>'
            + '<small class="prev">' + escapar(preview(item)) + '</small>' + aviso + '</span></button>';
    }

    function pintarLista() {
        pintarChips();
        const items = visibles();
        const grupos = items.filter((item) => item.tipo === 'grupo');
        const directos = items.filter((item) => item.tipo !== 'grupo');
        if (!conversaciones.length) {
            lista.innerHTML = '<p class="st-vacio">Todavía no hay conversaciones.</p>';
            return;
        }
        if (!items.length) {
            lista.innerHTML = '<p class="st-vacio">Ningún chat coincide con el filtro.</p>';
            return;
        }
        lista.innerHTML = ''
            + '<div class="st-sec"><span>Canales del equipo</span><button type="button" data-nuevo="grupo" aria-label="Nuevo grupo">+</button></div>'
            + (grupos.map(htmlCanal).join('') || '<p class="st-meta" style="padding:0 8px 12px">Sin grupos</p>')
            + '<div class="st-sec"><span>Mensajes directos</span><em>' + directos.length + '</em></div>'
            + (directos.map(htmlDirecto).join('') || '<p class="st-meta" style="padding:0 8px">Sin directos</p>');
    }

    function mostrarHilo(visible) {
        vacio.classList.toggle('hidden', visible);
        hilo.classList.toggle('hidden', !visible);
        head.classList.toggle('hidden', !visible);
        composer.classList.toggle('hidden', !visible);
        raiz.classList.toggle('st-en-hilo', visible);
    }

    function pendientesMios(mensaje) {
        return (mensaje.asignados || []).filter((item) => Number(item.usuario_id) === yo && !item.atendido_at);
    }

    function htmlAdjunto(mensaje) {
        if (!mensaje.adjunto) return '';
        const adj = mensaje.adjunto;
        if (adj.es_imagen) {
            return '<a href="' + escapar(adj.url) + '" target="_blank" rel="noopener"><img class="st-img" src="' + escapar(adj.url) + '" alt="' + escapar(adj.nombre) + '"></a>';
        }
        return '<a class="st-adjunto" href="' + escapar(adj.url) + '" target="_blank" rel="noopener">' + escapar(adj.nombre) + '</a>';
    }

    function horaLarga(iso) {
        if (!iso) return '';
        const fecha = new Date(iso);
        const diaMes = fecha.toLocaleDateString('es-PY', { day: '2-digit', month: '2-digit' });
        const hora = fecha.toLocaleTimeString('es-PY', { hour: '2-digit', minute: '2-digit' });
        return diaMes + ', ' + hora;
    }

    function htmlPedido(mensaje) {
        if (!mensaje.requiere_accion) return '';
        const mios = pendientesMios(mensaje);
        const pendientes = (mensaje.asignados || []).filter((item) => !item.atendido_at);
        const detalle = mensaje.cuerpo || (mensaje.adjunto ? mensaje.adjunto.nombre : 'Mensaje sin texto');
        if (mios.length) {
            return '<div class="st-banner"><div><strong>Te pidieron una acción</strong><span>' + escapar(detalle) + '</span></div><button type="button" class="st-resolver" data-atender="' + mensaje.id + '">Resolver</button></div>';
        }
        if (!pendientes.length) {
            return '<div class="st-banner"><div><strong>Acción atendida</strong><span>' + escapar(detalle) + '</span></div></div>';
        }
        return '<div class="st-banner"><div><strong>Acción pendiente</strong><span>Esperando a ' + pendientes.length + (pendientes.length === 1 ? ' persona' : ' personas') + '</span></div></div>';
    }

    function htmlMensaje(mensaje, anterior) {
        const propio = Number(mensaje.usuario_id) === yo;
        const separador = !anterior || dia(anterior.creado_at) !== dia(mensaje.creado_at)
            ? '<div class="st-dia"><span>' + escapar(dia(mensaje.creado_at)) + '</span></div>'
            : '';
        const cuerpo = htmlLinea(mensaje, propio);
        const pedido = htmlPedido(mensaje);
        if (propio) {
            return separador
                + '<article class="msg out" data-mensaje="' + mensaje.id + '"><div class="msg-col">'
                + '<div class="msg-meta"><span class="st-hora">' + escapar(horaLarga(mensaje.creado_at)) + '</span><span class="tu">Tú</span></div>'
                + '<div class="bubble out' + (pedido ? ' accion' : '') + '">' + cuerpo + htmlAdjunto(mensaje) + pedido + '</div>'
                + '</div></article>';
        }
        return separador
            + '<article class="msg" data-mensaje="' + mensaje.id + '">'
            + '<span class="st-av">' + escapar(iniciales(mensaje.autor)) + '</span>'
            + '<div><div class="msg-meta"><span class="name">' + escapar(mensaje.autor) + '</span><span class="st-hora">' + escapar(horaLarga(mensaje.creado_at)) + '</span></div>'
            + '<div class="bubble in' + (pedido ? ' accion' : '') + '">' + cuerpo + htmlAdjunto(mensaje) + pedido + '</div></div></article>';
    }

    function leidoPorOtros(mensaje) {
        const otros = (actual && actual.participantes ? actual.participantes : []).filter((persona) => Number(persona.usuario_id) !== yo);
        if (!otros.length) return false;
        return otros.every((persona) => Number(persona.ultimo_leido_mensaje_id) >= Number(mensaje.id));
    }

    function marcarMenciones(valor) {
        const nombres = (actual && actual.participantes ? actual.participantes : [])
            .map((persona) => persona.name)
            .filter(Boolean)
            .sort((a, b) => b.length - a.length);
        let html = escapar(valor);
        nombres.forEach((nombre) => {
            const marca = '@' + escapar(nombre);
            html = html.split(marca).join('<span class="st-mencion">' + marca + '</span>');
        });
        return html;
    }

    function personasMencionables() {
        return (actual && actual.participantes ? actual.participantes : [])
            .filter((persona) => Number(persona.usuario_id) !== yo && persona.name);
    }

    function consultaMencion() {
        const hasta = texto.value.slice(0, texto.selectionStart);
        const match = hasta.match(/(^|\s)@([^@\n]*)$/);
        return match ? match[2] : null;
    }

    function cerrarMenciones() {
        menciones.classList.add('hidden');
        menciones.innerHTML = '';
        mencionIndice = 0;
    }

    function pintarMenciones(consulta) {
        const q = String(consulta || '').toLowerCase();
        const personas = personasMencionables().filter((persona) => persona.name.toLowerCase().includes(q));
        if (!personas.length) {
            cerrarMenciones();
            return;
        }
        if (mencionIndice >= personas.length) mencionIndice = 0;
        menciones.innerHTML = personas.map((persona, indice) => {
            const cargo = persona.cargo ? '<span>' + escapar(persona.cargo) + '</span>' : '';
            return '<button type="button" role="option" class="' + (indice === mencionIndice ? 'activa' : '') + '" data-mencion="' + encodeURIComponent(persona.name) + '"><b>' + escapar(persona.name) + '</b>' + cargo + '</button>';
        }).join('');
        menciones.classList.remove('hidden');
    }

    function insertarMencion(nombre) {
        const pos = texto.selectionStart;
        const hasta = texto.value.slice(0, pos);
        const resto = texto.value.slice(pos);
        const match = hasta.match(/(^|\s)@([^@\n]*)$/);
        const base = match ? hasta.slice(0, match.index + match[1].length) : hasta + (hasta && !/\s$/.test(hasta) ? ' ' : '');
        const pieza = '@' + nombre + ' ';
        texto.value = base + pieza + resto;
        const cursor = (base + pieza).length;
        texto.focus();
        texto.setSelectionRange(cursor, cursor);
        cerrarMenciones();
    }

    function htmlChecks(mensaje) {
        const leido = leidoPorOtros(mensaje);
        const marca = '<svg viewBox="0 0 16 16" aria-hidden="true"><path d="M6.2 11.2 2.9 7.9l-.9.9 4.2 4.2 8-8-.9-.9z"/></svg>';
        return '<span class="st-checks' + (leido ? ' leido' : '') + '" title="' + (leido ? 'Leído' : 'Enviado') + '">' + marca + (leido ? marca : '') + '</span>';
    }

    function htmlLinea(mensaje, propio) {
        const texto = mensaje.cuerpo ? '<p>' + marcarMenciones(mensaje.cuerpo) + '</p>' : '';
        const checks = propio ? htmlChecks(mensaje) : '';
        if (!texto && !checks) return '';
        return '<div class="st-linea">' + (texto || '<p></p>') + checks + '</div>';
    }

    function pintarMensajes(conservar) {
        cerrarMenu();
        const top = hilo.scrollTop;
        let anterior = null;
        hilo.innerHTML = mensajesActuales.map((mensaje) => {
            const html = htmlMensaje(mensaje, anterior);
            anterior = mensaje;
            return html;
        }).join('');
        hilo.scrollTop = conservar ? top : hilo.scrollHeight;
        const mios = mensajesActuales.filter((mensaje) => pendientesMios(mensaje).length).length;
        irAcciones.classList.toggle('hidden', mios < 1);
        irAcciones.textContent = mios + (mios === 1 ? ' Acción pendiente' : ' Acciones pendientes');
    }

    function pintarDetalle() {
        if (!actual) {
            detalle.innerHTML = '<p class="st-vacio">Los datos del chat aparecen al abrir una conversación.</p>';
            return;
        }
        const otro = otroDe(actual);
        const nombre = actual.titulo || 'Chat';
        const cargo = actual.tipo === 'grupo'
            ? ((actual.participantes || []).length + ' personas')
            : (otro && otro.cargo ? otro.cargo : 'Mensaje directo');
        const pedidos = mensajesActuales.filter((mensaje) => (mensaje.asignados || []).some((item) => !item.atendido_at));
        const archivos = mensajesActuales.filter((mensaje) => mensaje.adjunto);
        const tarjetas = pedidos.map((mensaje) => {
            const mio = pendientesMios(mensaje).length > 0;
            return '<article class="st-card"><div class="top"><span>Acción</span><span>' + escapar(horaLarga(mensaje.creado_at)) + '</span></div><p>' + escapar(mensaje.cuerpo || 'Archivo') + '</p><div class="pie"><span>Solicitado por ' + escapar(mensaje.autor) + '</span>'
                + (mio ? '<button type="button" data-atender="' + mensaje.id + '">Resolver</button>' : '<span></span>')
                + '</div></article>';
        }).join('') || '<p class="st-meta">No hay acciones abiertas.</p>';
        const listaArchivos = archivos.map((mensaje) => {
            const adj = mensaje.adjunto;
            return '<a class="st-file" href="' + escapar(adj.url) + '" target="_blank" rel="noopener"><span>' + escapar(adj.nombre) + '</span><small>' + escapar(peso(adj.tamano)) + '</small></a>';
        }).join('') || '<p class="st-meta">Sin archivos.</p>';
        detalle.innerHTML = '<div class="st-det-h">Detalles y asignaciones</div>'
            + '<div class="st-ficha"><span class="st-av xl">' + escapar(iniciales(nombre)) + '</span><strong>' + escapar(nombre) + '</strong><em>' + escapar(cargo) + '</em></div>'
            + '<div class="st-bloque"><h3>Acciones en este chat <b>' + pedidos.length + '</b></h3>' + tarjetas + '</div>'
            + '<div class="st-bloque"><h3>Archivos compartidos <b>' + archivos.length + '</b></h3>' + listaArchivos + '</div>';
    }

    function agregarMensaje(mensaje) {
        if (!mensaje || !actual || Number(mensaje.conversacion_id) !== Number(actual.id)) return;
        if (mensajesActuales.some((item) => Number(item.id) === Number(mensaje.id))) return;
        mensajesActuales.push(mensaje);
        pintarMensajes();
        pintarDetalle();
    }

    function pintarAsignados() {
        const grupo = actual && actual.tipo === 'grupo' && accion.checked;
        asignadosBox.classList.toggle('hidden', !grupo);
        accionBtn.classList.toggle('activa', accion.checked);
        accionBtn.setAttribute('aria-pressed', accion.checked ? 'true' : 'false');
        if (!grupo) {
            asignadosBox.innerHTML = '';
            return;
        }
        asignadosBox.innerHTML = (actual.participantes || [])
            .filter((persona) => Number(persona.usuario_id) !== yo)
            .map((persona) => '<label><input type="checkbox" value="' + persona.usuario_id + '" class="chat-asignado"> ' + escapar(persona.name) + '</label>')
            .join('');
    }

    function suscribirHilo(id) {
        if (!window.Echo) return;
        if (canalHilo) window.Echo.leave('chat.conversacion.' + canalHilo);
        canalHilo = id;
        window.Echo.private('chat.conversacion.' + id)
            .listen('.mensaje.creado', (evento) => agregarMensaje(evento.mensaje))
            .listen('.accion.atendida', () => abrir(id, true))
            .listen('.lectura.actualizada', (evento) => aplicarLectura(evento))
            .listen('.mensaje.eliminado', (evento) => aplicarEliminado(evento));
    }

    function aplicarLectura(evento) {
        if (!actual || !evento || Number(evento.conversacion_id) !== Number(actual.id)) return;
        const persona = (actual.participantes || []).find((item) => Number(item.usuario_id) === Number(evento.usuario_id));
        if (!persona) return;
        const cursor = Number(evento.ultimo_leido_mensaje_id) || 0;
        if (Number(persona.ultimo_leido_mensaje_id) >= cursor) return;
        persona.ultimo_leido_mensaje_id = cursor;
        pintarMensajes(true);
    }

    async function abrir(id, silencioso) {
        cerrarMenciones();
        const res = await fetch(cfg.urls.mensajes + '/' + id + '/mensajes', { headers: headers(false), credentials: 'same-origin' });
        if (!res.ok) {
            errorBox.textContent = await leerError(res);
            errorBox.classList.remove('hidden');
            return;
        }
        const data = await res.json();
        actual = data.conversacion;
        mensajesActuales = data.mensajes || [];
        titulo.textContent = actual.titulo;
        const otro = otroDe(actual);
        sub.textContent = actual.tipo === 'grupo'
            ? (actual.participantes || []).map((persona) => persona.name).join(', ')
            : 'Mensaje directo';
        avatarHilo.textContent = iniciales(actual.titulo);
        pintarMensajes();
        pintarDetalle();
        mostrarHilo(true);
        pintarAsignados();
        const idx = conversaciones.findIndex((item) => Number(item.id) === Number(actual.id));
        if (idx >= 0) {
            conversaciones[idx] = Object.assign({}, conversaciones[idx], actual, { no_leidos: 0 });
        } else {
            conversaciones.unshift(Object.assign({}, actual, { no_leidos: 0 }));
        }
        pintarLista();
        if (!silencioso || canalHilo !== actual.id) suscribirHilo(actual.id);
        if (otro && !otro.cargo && actual.tipo !== 'grupo') sub.textContent = 'Mensaje directo';
    }

    async function atender(mensajeId) {
        const res = await fetch(cfg.urls.atender + '/' + mensajeId + '/atender', {
            method: 'POST',
            headers: headers(true),
            credentials: 'same-origin',
            body: '{}',
        });
        if (!res.ok) {
            errorBox.textContent = await leerError(res);
            errorBox.classList.remove('hidden');
            return;
        }
        const idx = conversaciones.findIndex((item) => actual && Number(item.id) === Number(actual.id));
        if (idx >= 0) {
            conversaciones[idx].acciones_pendientes = Math.max(0, (Number(conversaciones[idx].acciones_pendientes) || 0) - 1);
        }
        await abrir(actual.id, true);
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
        const filtroPersonas = usuarios.filter((persona) => persona.name.toLowerCase().includes(consulta));
        const tipo = modoModal === 'grupo' ? 'checkbox' : 'radio';
        modalLista.innerHTML = filtroPersonas.map((persona) => {
            const cargo = persona.cargo ? '<span>' + escapar(persona.cargo) + '</span>' : '';
            return '<label><input type="' + tipo + '" name="chat-persona" value="' + persona.usuario_id + '"><span class="st-persona"><b>' + escapar(persona.name) + '</b>' + cargo + '</span></label>';
        }).join('') || '<p class="st-meta">No hay personal para mostrar.</p>';
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
    }

    function cerrarModal() {
        modal.classList.add('hidden');
    }

    lista.addEventListener('click', (evento) => {
        const nuevo = evento.target.closest('[data-nuevo]');
        if (nuevo) {
            abrirModal(nuevo.dataset.nuevo);
            return;
        }
        const boton = evento.target.closest('[data-id]');
        if (boton) abrir(boton.dataset.id, false);
    });

    document.getElementById('chat-btn-directo').addEventListener('click', () => abrirModal('directo'));
    document.getElementById('chat-btn-grupo').addEventListener('click', () => abrirModal('grupo'));

    document.getElementById('chat-volver').addEventListener('click', () => mostrarHilo(false));
    document.getElementById('chat-modal-cancelar').addEventListener('click', cerrarModal);
    modalBuscar.addEventListener('input', pintarModal);
    buscar.addEventListener('input', () => {
        const global = document.getElementById('chat-buscar-global');
        if (global && global.value !== buscar.value) global.value = buscar.value;
        pintarLista();
    });
    const buscarGlobal = document.getElementById('chat-buscar-global');
    if (buscarGlobal) {
        buscarGlobal.addEventListener('input', () => {
            buscar.value = buscarGlobal.value;
            pintarLista();
        });
    }
    filtroAcciones.addEventListener('click', () => {
        filtro = filtro === 'acciones' ? '' : 'acciones';
        pintarLista();
    });
    filtroNoLeidos.addEventListener('click', () => {
        filtro = filtro === 'noleidos' ? '' : 'noleidos';
        pintarLista();
    });
    accionBtn.addEventListener('click', () => {
        accion.checked = !accion.checked;
        pintarAsignados();
    });
    irAcciones.addEventListener('click', () => {
        const pedido = hilo.querySelector('.st-banner');
        if (pedido) pedido.scrollIntoView({ block: 'center' });
    });

    function textoCopiable(mensaje) {
        const cuerpo = (mensaje.cuerpo || '').trim();
        if (cuerpo) return cuerpo;
        return mensaje.adjunto ? mensaje.adjunto.nombre : '';
    }

    function cerrarMenu() {
        menuMensaje = null;
        menuConfirma = false;
        menu.classList.add('hidden');
        menu.innerHTML = '';
    }

    function pintarMenu() {
        if (!menuMensaje) return;
        if (menuConfirma) {
            menu.innerHTML = '<p>¿Eliminar este mensaje?</p>'
                + '<button type="button" class="peligro" data-accion="confirmar-eliminar">Eliminar</button>'
                + '<button type="button" data-accion="cancelar">Cancelar</button>';
            return;
        }
        const propio = Number(menuMensaje.usuario_id) === yo;
        const copiar = textoCopiable(menuMensaje)
            ? '<button type="button" role="menuitem" data-accion="copiar">Copiar</button>'
            : '';
        const eliminar = propio
            ? '<button type="button" role="menuitem" class="peligro" data-accion="eliminar">Eliminar mensaje</button>'
            : '';
        menu.innerHTML = copiar
            + '<button type="button" role="menuitem" data-accion="reenviar">Reenviar</button>'
            + eliminar;
    }

    function abrirMenu(mensaje, ancla) {
        menuMensaje = mensaje;
        menuConfirma = false;
        pintarMenu();
        menu.classList.remove('hidden');
        const caja = menu.getBoundingClientRect();
        const rect = ancla.getBoundingClientRect();
        const limite = Math.min(window.innerHeight, composer.getBoundingClientRect().top) - 8;
        const cabeAbajo = rect.bottom + 6 + caja.height <= limite;
        const top = cabeAbajo ? rect.bottom + 6 : Math.max(8, rect.top - caja.height - 6);
        const preferido = Number(mensaje.usuario_id) === yo ? rect.right - caja.width : rect.left;
        menu.style.top = top + 'px';
        menu.style.left = Math.max(8, Math.min(preferido, window.innerWidth - caja.width - 8)) + 'px';
        const primero = menu.querySelector('button');
        if (primero) primero.focus();
    }

    async function copiarMensaje(mensaje) {
        const valor = textoCopiable(mensaje);
        const boton = menu.querySelector('[data-accion="copiar"]');
        let ok = false;
        try {
            if (navigator.clipboard && window.isSecureContext) {
                await navigator.clipboard.writeText(valor);
                ok = true;
            }
        } catch (e) {
            ok = false;
        }
        if (!ok) {
            const area = document.createElement('textarea');
            area.value = valor;
            area.setAttribute('readonly', '');
            area.style.position = 'fixed';
            area.style.left = '-9999px';
            document.body.appendChild(area);
            area.select();
            try { ok = document.execCommand('copy'); } catch (e) { ok = false; }
            area.remove();
        }
        if (boton) boton.textContent = ok ? 'Copiado' : 'No se pudo copiar';
        window.setTimeout(cerrarMenu, 700);
    }

    function cerrarReenviar() {
        reenviar.classList.add('hidden');
        reenviarError.classList.add('hidden');
        reenviarLista.innerHTML = '';
    }

    function abrirReenviar(mensaje) {
        cerrarMenu();
        menuMensaje = mensaje;
        reenviarError.classList.add('hidden');
        const destinos = conversaciones.filter((item) => !actual || Number(item.id) !== Number(actual.id));
        reenviarLista.innerHTML = destinos.length
            ? destinos.map((item) => '<button type="button" data-destino="' + item.id + '">' + escapar(item.titulo) + '<span>' + escapar(item.tipo === 'grupo' ? 'Grupo' : 'Directo') + '</span></button>').join('')
            : '<p class="st-meta">No hay otro chat para reenviar.</p>';
        reenviar.classList.remove('hidden');
    }

    async function enviarReenvio(destinoId) {
        if (!menuMensaje) return;
        reenviarError.classList.add('hidden');
        const res = await fetch(cfg.urls.atender + '/' + menuMensaje.id + '/reenviar', {
            method: 'POST',
            headers: headers(true),
            credentials: 'same-origin',
            body: JSON.stringify({ conversacion_id: Number(destinoId) }),
        });
        if (!res.ok) {
            reenviarError.textContent = await leerError(res);
            reenviarError.classList.remove('hidden');
            return;
        }
        const data = await res.json();
        const mensaje = data.mensaje;
        cerrarReenviar();
        menuMensaje = null;
        if (!mensaje) return;
        agregarMensaje(mensaje);
        const idx = conversaciones.findIndex((item) => Number(item.id) === Number(mensaje.conversacion_id));
        if (idx >= 0) {
            conversaciones[idx].ultimo = {
                id: mensaje.id,
                cuerpo: mensaje.cuerpo,
                adjunto_nombre: mensaje.adjunto ? mensaje.adjunto.nombre : null,
                usuario_id: mensaje.usuario_id,
                creado_at: mensaje.creado_at,
            };
            const [item] = conversaciones.splice(idx, 1);
            conversaciones.unshift(item);
            pintarLista();
        } else {
            cargarLista();
        }
    }

    async function eliminarMensaje(mensaje) {
        const res = await fetch(cfg.urls.atender + '/' + mensaje.id, {
            method: 'DELETE',
            headers: headers(true),
            credentials: 'same-origin',
        });
        if (!res.ok) {
            cerrarMenu();
            errorBox.textContent = await leerError(res);
            errorBox.classList.remove('hidden');
            return;
        }
        const data = await res.json();
        aplicarEliminado(data);
    }

    function aplicarEliminado(evento) {
        if (!evento) return;
        const id = Number(evento.mensaje_id);
        const convId = Number(evento.conversacion_id);
        cerrarMenu();
        if (actual && Number(actual.id) === convId) {
            const antes = mensajesActuales.length;
            mensajesActuales = mensajesActuales.filter((mensaje) => Number(mensaje.id) !== id);
            if (mensajesActuales.length !== antes) pintarMensajes(true);
        }
        const idx = conversaciones.findIndex((item) => Number(item.id) === convId && item.ultimo && Number(item.ultimo.id) === id);
        if (idx >= 0) cargarLista();
    }

    function clickAtender(evento) {
        const boton = evento.target.closest('[data-atender]');
        if (!boton) return;
        evento.preventDefault();
        atender(boton.dataset.atender);
    }
    hilo.addEventListener('click', (evento) => {
        clickAtender(evento);
        if (evento.target.closest('a, button')) return;
        const burbuja = evento.target.closest('.bubble');
        if (!burbuja) return;
        const articulo = burbuja.closest('[data-mensaje]');
        const mensaje = mensajesActuales.find((item) => Number(item.id) === Number(articulo && articulo.dataset.mensaje));
        if (!mensaje) return;
        evento.stopPropagation();
        abrirMenu(mensaje, burbuja);
    });
    menu.addEventListener('click', (evento) => {
        evento.stopPropagation();
        const boton = evento.target.closest('[data-accion]');
        if (!boton || !menuMensaje) return;
        const mensaje = menuMensaje;
        if (boton.dataset.accion === 'copiar') copiarMensaje(mensaje);
        else if (boton.dataset.accion === 'reenviar') abrirReenviar(mensaje);
        else if (boton.dataset.accion === 'eliminar') {
            menuConfirma = true;
            pintarMenu();
        } else if (boton.dataset.accion === 'confirmar-eliminar') eliminarMensaje(mensaje);
        else if (boton.dataset.accion === 'cancelar') cerrarMenu();
    });
    document.addEventListener('click', (evento) => {
        if (menu.classList.contains('hidden')) return;
        if (menu.contains(evento.target)) return;
        cerrarMenu();
    });
    document.addEventListener('keydown', (evento) => {
        if (evento.key !== 'Escape') return;
        if (!menu.classList.contains('hidden')) cerrarMenu();
        if (!reenviar.classList.contains('hidden')) cerrarReenviar();
    });
    hilo.addEventListener('scroll', () => {
        if (!menu.classList.contains('hidden')) cerrarMenu();
    });
    document.getElementById('chat-reenviar-cancelar').addEventListener('click', () => {
        menuMensaje = null;
        cerrarReenviar();
    });
    reenviarLista.addEventListener('click', (evento) => {
        const boton = evento.target.closest('[data-destino]');
        if (boton) enviarReenvio(boton.dataset.destino);
    });
    detalle.addEventListener('click', clickAtender);

    archivo.addEventListener('change', () => {
        const file = archivo.files && archivo.files[0];
        archivoNombre.textContent = file ? file.name : '';
        archivoNombre.classList.toggle('hidden', !file);
        archivo.accept = aceptaArchivo;
    });

    btnArchivo.addEventListener('click', () => {
        archivo.accept = aceptaArchivo;
        archivo.click();
    });
    btnImagen.addEventListener('click', () => {
        archivo.accept = 'image/jpeg,image/png,image/gif,image/webp';
        archivo.click();
    });
    btnMencion.addEventListener('click', () => {
        const pos = texto.selectionStart;
        const hasta = texto.value.slice(0, pos);
        if (!/(^|\s)@[^@\n]*$/.test(hasta)) {
            const prefijo = hasta && !/\s$/.test(hasta) ? ' @' : '@';
            texto.value = hasta + prefijo + texto.value.slice(pos);
            const cursor = pos + prefijo.length;
            texto.focus();
            texto.setSelectionRange(cursor, cursor);
        } else {
            texto.focus();
        }
        mencionIndice = 0;
        pintarMenciones(consultaMencion() || '');
    });
    menciones.addEventListener('mousedown', (evento) => evento.preventDefault());
    menciones.addEventListener('click', (evento) => {
        const boton = evento.target.closest('[data-mencion]');
        if (!boton) return;
        insertarMencion(decodeURIComponent(boton.dataset.mencion));
    });
    texto.addEventListener('input', () => {
        const consulta = consultaMencion();
        if (consulta === null) cerrarMenciones();
        else {
            mencionIndice = 0;
            pintarMenciones(consulta);
        }
    });
    document.addEventListener('click', (evento) => {
        if (menciones.classList.contains('hidden')) return;
        if (evento.target === texto || evento.target.closest('#chat-btn-mencion') || menciones.contains(evento.target)) return;
        cerrarMenciones();
    });

    texto.addEventListener('keydown', (evento) => {
        const abierta = !menciones.classList.contains('hidden');
        if (abierta && (evento.key === 'ArrowDown' || evento.key === 'ArrowUp')) {
            evento.preventDefault();
            const total = menciones.querySelectorAll('button').length;
            if (!total) return;
            mencionIndice = evento.key === 'ArrowDown'
                ? (mencionIndice + 1) % total
                : (mencionIndice - 1 + total) % total;
            pintarMenciones(consultaMencion() || '');
            return;
        }
        if (abierta && (evento.key === 'Enter' || evento.key === 'Tab')) {
            evento.preventDefault();
            const activa = menciones.querySelector('button.activa') || menciones.querySelector('button');
            if (activa) insertarMencion(decodeURIComponent(activa.dataset.mencion));
            return;
        }
        if (abierta && evento.key === 'Escape') {
            evento.preventDefault();
            cerrarMenciones();
            return;
        }
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
            if ((mensaje.asignados || []).some((item) => Number(item.usuario_id) === yo && !item.atendido_at)) {
                conversaciones[idx].acciones_pendientes = (Number(conversaciones[idx].acciones_pendientes) || 0) + 1;
            }
            const [item] = conversaciones.splice(idx, 1);
            conversaciones.unshift(item);
            pintarLista();
        } else {
            cargarLista();
        }
    });

    window.addEventListener('chat:eliminado', (evento) => aplicarEliminado(evento.detail || {}));

    window.addEventListener('chat:accion', (evento) => {
        const detalleEvento = evento.detail || {};
        if (actual && mensajesActuales.some((mensaje) => Number(mensaje.id) === Number(detalleEvento.mensaje_id))) {
            abrir(actual.id, true);
        }
    });

    cargarLista().then(() => {
        if (cfg.abrir) abrir(cfg.abrir, false);
    });
}
