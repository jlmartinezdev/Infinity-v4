import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

const cfg = window.InfinityChat;
if (cfg && cfg.usuarioId) {
    const claveCerradas = 'chat_interno_cerradas';

    function cerradas() {
        try {
            const ids = JSON.parse(sessionStorage.getItem(claveCerradas) || '[]');
            return Array.isArray(ids) ? ids.map(Number) : [];
        } catch (e) {
            return [];
        }
    }

    function cerrar(id) {
        const ids = cerradas();
        const numero = Number(id);
        if (!ids.includes(numero)) {
            ids.push(numero);
        }
        sessionStorage.setItem(claveCerradas, JSON.stringify(ids.slice(-100)));
    }

    function pintarBadge(total) {
        const badge = document.getElementById('chat-interno-badge');
        if (!badge) return;
        const n = Number(total) || 0;
        badge.textContent = n > 99 ? '99+' : String(n);
        badge.classList.toggle('hidden', n < 1);
    }

    let popupActual = null;
    const popup = document.getElementById('chat-interno-popup');

    function mostrarPopup(accion) {
        if (!accion || !popup || cerradas().includes(Number(accion.mensaje_id))) return;
        popupActual = accion;
        popup.querySelector('[data-autor]').textContent = accion.autor || 'Staff';
        popup.querySelector('[data-titulo]').textContent = accion.titulo || 'Chat interno';
        const texto = accion.cuerpo || (accion.adjunto_nombre ? ('Archivo: ' + accion.adjunto_nombre) : 'Mensaje sin texto');
        popup.querySelector('[data-cuerpo]').textContent = texto;
        popup.classList.remove('hidden');
        popup.classList.add('flex');
    }

    function ocultarPopup() {
        if (!popup) return;
        popup.classList.add('hidden');
        popup.classList.remove('flex');
    }

    async function cargarPendientes() {
        try {
            const res = await fetch(cfg.pendientesUrl, {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            });
            if (!res.ok) return;
            const data = await res.json();
            pintarBadge(data.no_leidos);
            const pendiente = (data.acciones || []).find((accion) => !cerradas().includes(Number(accion.mensaje_id)));
            if (pendiente) mostrarPopup(pendiente);
            window.dispatchEvent(new CustomEvent('chat:pendientes', { detail: data }));
        } catch (e) {
            /* El chat sigue usable si el aviso no carga. */
        }
    }

    document.getElementById('chat-popup-cerrar')?.addEventListener('click', () => {
        if (popupActual) cerrar(popupActual.mensaje_id);
        ocultarPopup();
    });

    document.getElementById('chat-popup-abrir')?.addEventListener('click', () => {
        if (!popupActual) return;
        cerrar(popupActual.mensaje_id);
        window.location.href = cfg.chatUrl + '?c=' + popupActual.conversacion_id;
    });

    document.getElementById('chat-popup-atender')?.addEventListener('click', async () => {
        if (!popupActual) return;
        const id = popupActual.mensaje_id;
        await fetch(cfg.atenderUrl + '/' + id + '/atender', {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': cfg.csrf,
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
            body: '{}',
        });
        cerrar(id);
        ocultarPopup();
        window.dispatchEvent(new CustomEvent('chat:accion', { detail: { mensaje_id: id, usuario_id: cfg.usuarioId } }));
        cargarPendientes();
    });

    cargarPendientes();

    if (cfg.key) {
        window.Pusher = Pusher;
        window.Echo = new Echo({
            broadcaster: 'reverb',
            key: cfg.key,
            wsHost: cfg.host,
            wsPort: cfg.port,
            wssPort: cfg.port,
            forceTLS: cfg.scheme === 'https',
            enabledTransports: ['ws', 'wss'],
            authEndpoint: '/broadcasting/auth',
        });

        const yo = Number(cfg.usuarioId);
        window.Echo.private('chat.usuario.' + cfg.usuarioId)
            .listen('.mensaje.creado', (evento) => {
                const mensaje = evento.mensaje || {};
                window.dispatchEvent(new CustomEvent('chat:mensaje', { detail: mensaje }));
                const asignado = (mensaje.asignados || []).some((accion) => Number(accion.usuario_id) === yo && !accion.atendido_at);
                if (Number(mensaje.usuario_id) !== yo && mensaje.requiere_accion && asignado) {
                    mostrarPopup({
                        mensaje_id: mensaje.id,
                        conversacion_id: mensaje.conversacion_id,
                        autor: mensaje.autor,
                        titulo: mensaje.titulo || 'Chat interno',
                        cuerpo: mensaje.cuerpo,
                        adjunto_nombre: mensaje.adjunto ? mensaje.adjunto.nombre : null,
                    });
                }
                cargarPendientes();
            })
            .listen('.accion.atendida', (evento) => {
                window.dispatchEvent(new CustomEvent('chat:accion', { detail: evento }));
                if (Number(evento.usuario_id) === yo && popupActual && Number(popupActual.mensaje_id) === Number(evento.mensaje_id)) {
                    ocultarPopup();
                }
                cargarPendientes();
            });

        window.Echo.connector.pusher.connection.bind('connected', () => cargarPendientes());
    }
}
