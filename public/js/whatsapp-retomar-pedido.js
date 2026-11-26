(function () {
    var rootId = 'whatsapp-mensajes-app';
    var cfg = window.__WHATSAPP_MENSAJES_CONFIG__ || {};
    var plantilla = cfg.plantillaPedido || {};
    var nombreTpl = String(plantilla.nombre || 'pedido_pendiente_retomar');

    function nombreParaPlantilla(header) {
        var title = header && header.querySelector('.wa-title');
        if (!title) return 'cliente';
        var clone = title.cloneNode(true);
        clone.querySelectorAll('span').forEach(function (s) { s.remove(); });
        var name = clone.textContent.replace(/\s+/g, ' ').trim();
        var letters = name.match(/[A-Za-zÁÉÍÓÚÜÑáéíóúüñ]/g) || [];
        if (letters.length < 2) return 'cliente';
        return name.slice(0, 80);
    }

    function hrefRetomar(plantillaLink, header) {
        var url = new URL(plantillaLink.href, window.location.origin);
        url.searchParams.set('plantilla', nombreTpl);
        url.searchParams.set('params', nombreParaPlantilla(header));
        return url.pathname + url.search;
    }

    function sync() {
        var root = document.getElementById(rootId);
        if (!root) return;
        var pedido = root.querySelector('a[data-pedido-menu]');
        var plantillaLink = Array.prototype.find.call(root.querySelectorAll('a'), function (a) {
            return a.textContent.trim() === 'Plantilla' && a.href.indexOf('telefono=') !== -1;
        });
        var boton = root.querySelector('[data-retomar-pedido]');

        if (!pedido || !plantillaLink) {
            if (boton && boton.dataset.inyectado === '1') boton.remove();
            return;
        }

        var header = plantillaLink.closest('header') || root;
        var href = hrefRetomar(plantillaLink, header);

        if (!boton) {
            boton = document.createElement('a');
            boton.dataset.retomarPedido = '1';
            boton.dataset.inyectado = '1';
            boton.className = plantillaLink.className;
            boton.title = 'Plantilla para retomar el pedido pendiente';
            plantillaLink.before(boton);
        }
        if (boton.textContent !== 'Retomar pedido') boton.textContent = 'Retomar pedido';
        if (boton.getAttribute('href') !== href) boton.setAttribute('href', href);

        var aviso = Array.prototype.find.call(root.querySelectorAll('a'), function (a) {
            return a.textContent.indexOf('plantilla') !== -1 && a !== boton && a !== plantillaLink;
        });
        if (aviso && aviso.textContent.indexOf('APPROVED') !== -1) {
            if (aviso.getAttribute('href') !== href) aviso.setAttribute('href', href);
            if (aviso.textContent !== 'Retomar pedido con plantilla') {
                aviso.textContent = 'Retomar pedido con plantilla';
            }
        }
    }

    function arrancar() {
        var root = document.getElementById(rootId);
        if (!root) return;
        var timer = 0;
        var observer = new MutationObserver(function () {
            if (timer) cancelAnimationFrame(timer);
            timer = requestAnimationFrame(sync);
        });
        observer.observe(root, { childList: true, subtree: true });
        sync();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', arrancar);
    } else {
        arrancar();
    }
})();
