@push('scripts')
<script>
(function () {
    var panel = document.getElementById('ppe-panel');
    var fondo = document.getElementById('ppe-fondo');
    if (!panel || !fondo) return;
    var origen = null;
    var pedido = 0;

    function escapar(texto) {
        var div = document.createElement('div');
        div.textContent = texto;
        return div.innerHTML;
    }

    function aviso(titulo, mensaje, extra) {
        panel.innerHTML =
            '<header class="ppe-panel-cabecera"><div class="ppe-panel-titulos">' +
            '<h2 id="ppe-panel-titulo" class="ppe-panel-titulo">' + escapar(titulo) + '</h2></div>' +
            '<button type="button" class="ppe-btn ppe-btn-icono" data-ppe-cerrar aria-label="Cerrar detalle">✕</button></header>' +
            '<div class="ppe-panel-cuerpo"><p class="ppe-seccion-ayuda" role="status">' + escapar(mensaje) + '</p>' + (extra || '') + '</div>';
    }

    function abrir(url, boton) {
        origen = boton || null;
        var turno = ++pedido;
        aviso(boton ? boton.textContent.trim() : 'Detalle', 'Cargando eventos…');
        panel.hidden = false;
        fondo.hidden = false;
        document.body.style.overflow = 'hidden';
        panel.focus();

        fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'text/html' }, credentials: 'same-origin' })
            .then(function (respuesta) {
                if (turno !== pedido) return null;
                if (respuesta.status === 401 || respuesta.status === 419 || respuesta.redirected) {
                    aviso('Tu sesión se cerró', 'Entrá de nuevo para ver los eventos.',
                        '<p><a class="ppe-btn" href="' + panel.getAttribute('data-entrar') + '">Entrar</a></p>');
                    return null;
                }
                if (respuesta.status === 404) {
                    aviso('Sin eventos', 'No encontré eventos PPPoE para este cliente.');
                    return null;
                }
                if (!respuesta.ok) throw new Error('HTTP ' + respuesta.status);
                return respuesta.text();
            })
            .then(function (html) {
                if (html === null || turno !== pedido) return;
                panel.innerHTML = html;
                panel.scrollTop = 0;
            })
            .catch(function () {
                if (turno !== pedido) return;
                aviso('No pude cargar el detalle', 'Revisá la conexión y probá de nuevo.');
            });
    }

    function cerrar() {
        if (panel.hidden) return;
        pedido++;
        panel.hidden = true;
        fondo.hidden = true;
        panel.innerHTML = '';
        document.body.style.overflow = '';
        if (origen && document.contains(origen)) origen.focus();
        origen = null;
    }

    document.addEventListener('click', function (evento) {
        var boton = evento.target.closest('[data-ppe-detalle]');
        if (boton) {
            evento.preventDefault();
            abrir(boton.getAttribute('data-ppe-detalle'), boton);
            return;
        }
        if (evento.target.closest('[data-ppe-cerrar]') || evento.target === fondo) cerrar();
    });

    document.addEventListener('keydown', function (evento) {
        if (panel.hidden) return;
        if (evento.key === 'Escape') {
            cerrar();
            return;
        }
        if (evento.key !== 'Tab') return;
        // Mantiene el foco dentro del panel mientras está abierto.
        var enfocables = panel.querySelectorAll('a[href], button:not([disabled])');
        if (enfocables.length === 0) {
            evento.preventDefault();
            return;
        }
        var primero = enfocables[0];
        var ultimo = enfocables[enfocables.length - 1];
        if (!panel.contains(document.activeElement)) {
            evento.preventDefault();
            primero.focus();
        } else if (evento.shiftKey && (document.activeElement === primero || document.activeElement === panel)) {
            evento.preventDefault();
            ultimo.focus();
        } else if (!evento.shiftKey && document.activeElement === ultimo) {
            evento.preventDefault();
            primero.focus();
        }
    });

    // Filtro por gravedad de la lista "Requieren atención".
    var botonesNivel = document.querySelectorAll('[data-ppe-nivel]');
    Array.prototype.forEach.call(botonesNivel, function (boton) {
        boton.addEventListener('click', function () {
            var nivel = boton.getAttribute('data-ppe-nivel');
            Array.prototype.forEach.call(botonesNivel, function (otro) {
                otro.setAttribute('aria-pressed', otro === boton ? 'true' : 'false');
            });
            Array.prototype.forEach.call(document.querySelectorAll('[data-ppe-fila-nivel]'), function (fila) {
                fila.hidden = nivel !== '' && fila.getAttribute('data-ppe-fila-nivel') !== nivel;
            });
        });
    });

    // Menú "⋯" de cada fila: se posiciona fijo para que no lo corte el scroll de la lista.
    var menuAbierto = null;

    function cerrarMenu(devolverFoco) {
        if (!menuAbierto) return;
        menuAbierto.menu.hidden = true;
        menuAbierto.boton.setAttribute('aria-expanded', 'false');
        if (devolverFoco) menuAbierto.boton.focus();
        menuAbierto = null;
    }

    function abrirMenu(boton) {
        var menu = boton.parentNode.querySelector('.ppe-menu');
        if (!menu) return;
        menu.hidden = false;
        boton.setAttribute('aria-expanded', 'true');
        var caja = boton.getBoundingClientRect();
        var alto = menu.offsetHeight;
        var arriba = caja.bottom + 4;
        if (arriba + alto > window.innerHeight - 8) arriba = Math.max(8, caja.top - alto - 4);
        menu.style.top = arriba + 'px';
        menu.style.left = Math.max(8, caja.right - menu.offsetWidth) + 'px';
        menuAbierto = { menu: menu, boton: boton };
        var primero = menu.querySelector('[role="menuitem"]');
        if (primero) primero.focus();
    }

    document.addEventListener('click', function (evento) {
        var boton = evento.target.closest('[data-ppe-menu]');
        if (boton) {
            var mismo = menuAbierto && menuAbierto.boton === boton;
            cerrarMenu(false);
            if (!mismo) abrirMenu(boton);
            return;
        }
        var copiar = evento.target.closest('[data-ppe-copiar]');
        if (copiar && navigator.clipboard) {
            navigator.clipboard.writeText(copiar.getAttribute('data-ppe-copiar')).catch(function () {});
        }
        cerrarMenu(false);
    });

    document.addEventListener('keydown', function (evento) {
        if (!menuAbierto) return;
        if (evento.key === 'Escape') {
            evento.stopPropagation();
            cerrarMenu(true);
            return;
        }
        if (evento.key !== 'ArrowDown' && evento.key !== 'ArrowUp') return;
        var items = Array.prototype.slice.call(menuAbierto.menu.querySelectorAll('[role="menuitem"]'));
        var actual = items.indexOf(document.activeElement);
        var paso = evento.key === 'ArrowDown' ? 1 : -1;
        evento.preventDefault();
        items[(actual + paso + items.length) % items.length].focus();
    }, true);

    window.addEventListener('scroll', function () { cerrarMenu(false); }, true);
    window.addEventListener('resize', function () { cerrarMenu(false); });
})();

</script>
@endpush
