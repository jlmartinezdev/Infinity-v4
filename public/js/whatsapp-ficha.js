(function () {
  var panel = document.getElementById('wa-ficha');
  if (!panel) return;

  var abierta = false;
  var clienteId = null;
  var cargando = false;
  var seq = 0;
  var observer = null;

  function el(tag, className, text) {
    var node = document.createElement(tag);
    if (className) node.className = className;
    if (text != null) node.textContent = text;
    return node;
  }

  function pill(texto, tono) {
    return el('span', 'wa-ficha-pill wa-ficha-' + (tono || 'muted'), texto || '');
  }

  function fila(etiqueta, valor, tono) {
    var row = el('div', 'wa-ficha-row');
    row.appendChild(el('span', 'wa-ficha-label', etiqueta));
    var strong = el('b', tono ? 'wa-ficha-' + tono : '', valor || 'Sin dato');
    if (!valor) strong.className = 'wa-ficha-muted';
    row.appendChild(strong);
    return row;
  }

  function botonCerrar() {
    var cerrar = el('button', 'wa-ficha-x');
    cerrar.type = 'button';
    cerrar.setAttribute('aria-label', 'Cerrar ficha');
    var svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
    svg.setAttribute('viewBox', '0 0 24 24');
    svg.setAttribute('width', '16');
    svg.setAttribute('height', '16');
    svg.setAttribute('fill', 'none');
    svg.setAttribute('stroke', 'currentColor');
    svg.setAttribute('stroke-width', '1.8');
    var path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
    path.setAttribute('stroke-linecap', 'round');
    path.setAttribute('d', 'M6 6l12 12M18 6L6 18');
    svg.appendChild(path);
    cerrar.appendChild(svg);
    cerrar.addEventListener('click', function () { api.toggle(clienteId); });
    return cerrar;
  }

  function icono() {
    var svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
    svg.setAttribute('viewBox', '0 0 24 24');
    svg.setAttribute('width', '16');
    svg.setAttribute('height', '16');
    svg.setAttribute('fill', 'none');
    svg.setAttribute('stroke', 'currentColor');
    svg.setAttribute('stroke-width', '1.8');
    var path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
    path.setAttribute('stroke-linecap', 'round');
    path.setAttribute('stroke-linejoin', 'round');
    path.setAttribute('d', 'M8 7h8M8 11h8M8 15h5M6 3h12a2 2 0 012 2v14l-4-2-4 2-4-2-4 2V5a2 2 0 012-2z');
    svg.appendChild(path);
    return svg;
  }

  function colocar() {
    var pane = document.querySelector('.wa-chat-pane');
    if (!pane || !abierta) return;
    var rect = pane.getBoundingClientRect();
    var overlay = rect.width < 760;
    document.documentElement.classList.toggle('wa-ficha-overlay', overlay);
    panel.style.top = Math.max(0, rect.top) + 'px';
    panel.style.height = Math.max(160, rect.height) + 'px';
    panel.style.width = (overlay ? Math.min(360, rect.width) : 340) + 'px';
    panel.style.right = Math.max(0, window.innerWidth - rect.right) + 'px';
  }

  function observar() {
    if (observer) return;
    var raiz = document.getElementById('whatsapp-mensajes-app');
    if (!raiz || typeof ResizeObserver === 'undefined') return;
    observer = new ResizeObserver(colocar);
    observer.observe(raiz);
  }

  function estadoVacio(mensaje) {
    panel.replaceChildren();
    var head = el('div', 'wa-ficha-head');
    var mark = el('div', 'wa-ficha-mark');
    mark.appendChild(icono());
    head.appendChild(mark);
    head.appendChild(el('p', 'wa-ficha-title', 'Ficha del cliente'));
    head.appendChild(botonCerrar());
    panel.appendChild(head);
    var body = el('div', 'wa-ficha-body');
    body.appendChild(el('p', 'wa-ficha-empty', mensaje));
    panel.appendChild(body);
  }

  function pintar(ficha) {
    panel.replaceChildren();
    var head = el('div', 'wa-ficha-head');
    var mark = el('div', 'wa-ficha-mark');
    mark.appendChild(icono());
    head.appendChild(mark);
    head.appendChild(el('p', 'wa-ficha-title', 'Ficha del cliente'));
    head.appendChild(pill(ficha.estado, ficha.estado_tono));
    head.appendChild(botonCerrar());
    panel.appendChild(head);

    var body = el('div', 'wa-ficha-body');

    var titular = el('section');
    titular.appendChild(el('h2', 'wa-ficha-kicker', 'Titular de la conexión'));
    var card = el('div', 'wa-ficha-card');
    card.appendChild(el('p', 'wa-ficha-name', ficha.nombre || 'Sin nombre'));
    card.appendChild(el('p', 'wa-ficha-sub', 'CI / RUC: ' + (ficha.cedula || 'Sin cédula')));
    var split = el('div', 'wa-ficha-split');
    var codigo = el('div');
    codigo.appendChild(el('span', 'wa-ficha-label', 'Código abonado'));
    codigo.appendChild(el('b', '', ficha.codigo || ''));
    var nodo = el('div');
    nodo.appendChild(el('span', 'wa-ficha-label', 'Nodo OLT / puerto'));
    nodo.appendChild(el('b', '', ficha.nodo_puerto || 'Sin nodo'));
    split.appendChild(codigo);
    split.appendChild(nodo);
    card.appendChild(split);
    titular.appendChild(card);
    body.appendChild(titular);

    var servicio = el('section');
    var kicker = el('h2', 'wa-ficha-kicker', 'Parámetros de servicio');
    servicio.appendChild(kicker);
    var params = el('div', 'wa-ficha-card');
    params.appendChild(fila('Plan contratado', ficha.plan));
    params.appendChild(fila(ficha.ip_label || 'IP asignada', ficha.ip));
    params.appendChild(fila('MAC / serial ONU', ficha.mac_serial));
    if (ficha.optica) {
      var optica = fila('Potencia óptica (Rx)', ficha.optica.valor + ' (' + ficha.optica.etiqueta + ')', ficha.optica.tono);
      params.appendChild(optica);
      if (ficha.optica.cuando) {
        params.appendChild(el('p', 'wa-ficha-note', 'Última medición ' + ficha.optica.cuando));
      }
    } else {
      params.appendChild(fila('Potencia óptica (Rx)', null));
    }
    servicio.appendChild(params);
    if (ficha.otros_servicios > 0) {
      servicio.appendChild(el('p', 'wa-ficha-note', 'Hay ' + ficha.otros_servicios + ' servicio' + (ficha.otros_servicios === 1 ? '' : 's') + ' vigente' + (ficha.otros_servicios === 1 ? '' : 's') + ' más. Esta ficha muestra el principal.'));
    }
    body.appendChild(servicio);

    var cuenta = el('section');
    cuenta.appendChild(el('h2', 'wa-ficha-kicker', 'Estado de cuenta'));
    var caja = el('div', 'wa-ficha-card');
    var estadoCuenta = el('div', 'wa-ficha-row');
    estadoCuenta.appendChild(el('span', 'wa-ficha-label', 'Situación'));
    estadoCuenta.appendChild(pill(ficha.cuenta, ficha.cuenta_tono));
    caja.appendChild(estadoCuenta);
    caja.appendChild(fila('Saldo pendiente', ficha.saldo, ficha.saldo_tono));
    caja.appendChild(fila('Último pago', ficha.ultimo_pago));
    cuenta.appendChild(caja);
    body.appendChild(cuenta);

    var bit = el('section');
    var bitHead = el('div', 'wa-ficha-bit');
    bitHead.appendChild(el('h2', 'wa-ficha-kicker', 'Bitácora de reclamo'));
    var ticketBtn = document.querySelector('button[title="Crear ticket sin salir"]');
    if (ticketBtn) {
      var nota = el('button', 'wa-ficha-nota', '+ Nota');
      nota.type = 'button';
      nota.addEventListener('click', function () {
        api.toggle(clienteId);
        ticketBtn.click();
      });
      bitHead.appendChild(nota);
    }
    bit.appendChild(bitHead);
    if (!ficha.bitacora || !ficha.bitacora.length) {
      bit.appendChild(el('p', 'wa-ficha-empty', 'Sin tickets recientes.'));
    } else {
      ficha.bitacora.forEach(function (item) {
        var itemCard = el('article', 'wa-ficha-card wa-ficha-item');
        var top = el('div', 'wa-ficha-bit');
        top.appendChild(el('b', '', item.autor || 'Sistema'));
        top.appendChild(el('span', 'wa-ficha-label', item.hora || ''));
        itemCard.appendChild(top);
        itemCard.appendChild(el('p', '', item.texto || ''));
        bit.appendChild(itemCard);
      });
    }
    body.appendChild(bit);
    panel.appendChild(body);

    if (ficha.detalle_url) {
      var foot = el('div', 'wa-ficha-foot');
      var link = el('a', 'wa-ficha-link', 'Abrir la ficha completa');
      link.href = ficha.detalle_url;
      link.target = '_blank';
      link.rel = 'noopener';
      foot.appendChild(link);
      panel.appendChild(foot);
    }
  }

  function cargar(id) {
    var url = (window.__WHATSAPP_MENSAJES_CONFIG__ && window.__WHATSAPP_MENSAJES_CONFIG__.urls && window.__WHATSAPP_MENSAJES_CONFIG__.urls.ficha) || '/whatsapp/ficha';
    var actual = ++seq;
    cargando = true;
    estadoVacio('Cargando ficha…');
    var pedido = url + (url.indexOf('?') === -1 ? '?' : '&') + 'cliente_id=' + encodeURIComponent(id);
    fetch(pedido, { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
      .then(function (res) { return res.json().then(function (data) { return { ok: res.ok, data: data }; }); })
      .then(function (pack) {
        if (actual !== seq || !abierta || String(clienteId) !== String(id)) return;
        if (!pack.data || pack.data.vinculado === false) {
          estadoVacio((pack.data && pack.data.mensaje) || 'Este chat no está vinculado a un cliente.');
          return;
        }
        if (!pack.ok || !pack.data.ficha) {
          estadoVacio('No se pudo cargar la ficha. Intentá de nuevo.');
          return;
        }
        pintar(pack.data.ficha);
      })
      .catch(function () {
        if (actual !== seq) return;
        estadoVacio('No se pudo cargar la ficha. Revisá la conexión e intentá de nuevo.');
      })
      .finally(function () {
        if (actual === seq) cargando = false;
      });
  }

  function abrir() {
    abierta = true;
    panel.classList.add('is-open');
    panel.setAttribute('aria-hidden', 'false');
    document.documentElement.classList.add('wa-ficha-abierta');
    observar();
    colocar();
    if (!clienteId) {
      estadoVacio('Este chat no está vinculado a un cliente.');
      return;
    }
    cargar(clienteId);
  }

  function cerrar() {
    abierta = false;
    seq += 1;
    panel.classList.remove('is-open');
    panel.setAttribute('aria-hidden', 'true');
    document.documentElement.classList.remove('wa-ficha-abierta', 'wa-ficha-overlay');
  }

  var api = {
    toggle: function (id) {
      clienteId = id || null;
      if (abierta) cerrar();
      else abrir();
    },
    sync: function (id) {
      var siguiente = id || null;
      var cambio = String(siguiente || '') !== String(clienteId || '');
      clienteId = siguiente;
      if (!abierta || !cambio) return;
      if (!clienteId) {
        estadoVacio('Este chat no está vinculado a un cliente.');
        return;
      }
      cargar(clienteId);
    }
  };

  window.InfinityWaFicha = api;
  window.addEventListener('resize', colocar);
  window.addEventListener('scroll', colocar, true);
})();

(function () {
  var menu = document.createElement('div');
  menu.className = 'wa-chat-menu';
  menu.setAttribute('role', 'menu');
  document.body.appendChild(menu);
  var ancla = null;

  function el(tag, className, text) {
    var node = document.createElement(tag);
    if (className) node.className = className;
    if (text != null) node.textContent = text;
    return node;
  }

  var CATALOGO = {
    Contacto: {
      desc: 'Guardar nuevo contacto.',
      paths: ['M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2', 'M12 11a4 4 0 100-8 4 4 0 000 8']
    },
    Guardar: {
      desc: 'Guardar el nombre y vincular este número a un cliente.',
      paths: ['M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2', 'M12 11a4 4 0 100-8 4 4 0 000 8']
    },
    Ticket: {
      desc: 'Crear un ticket de soporte sin salir del chat.',
      paths: ['M4 8a2 2 0 012-2h12a2 2 0 012 2v2.2a2 2 0 000 3.6V16a2 2 0 01-2 2H6a2 2 0 01-2-2v-2.2a2 2 0 000-3.6V8z', 'M9 7.5v9']
    },
    Pedido: {
      desc: 'Crear un pedido de instalación o de servicio.',
      paths: ['M8 6h8l1 3H7l1-3z', 'M6 9h12l-1 10H7L6 9z']
    },
    Pago: {
      desc: 'Registrar un cobro.',
      paths: ['M3 7h18v10H3z', 'M3 11h18', 'M12 15.2a1.7 1.7 0 100-3.4 1.7 1.7 0 000 3.4']
    },
    Cliente: {
      desc: 'Abrir la ficha completa del cliente en otra pestaña.',
      paths: ['M14 5h5v5', 'M19 5l-8 8', 'M10 5H6a1 1 0 00-1 1v12a1 1 0 001 1h12a1 1 0 001-1v-4']
    },
    Plantilla: {
      desc: 'Enviar un mensaje con una plantilla aprobada de WhatsApp.',
      paths: ['M7 4h10a2 2 0 012 2v9l-3-2H7a2 2 0 01-2-2V6a2 2 0 012-2z', 'M8 9h8', 'M8 12h5']
    },
    'Eliminar chat': {
      desc: 'Quitar este chat de Infinity.',
      paths: ['M5 7h14', 'M9 7V5h6v2', 'M8 7l.8 12h6.4L16 7', 'M10 11v5', 'M14 11v5']
    }
  };

  function iconoMenu(paths) {
    var svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
    svg.setAttribute('viewBox', '0 0 24 24');
    svg.setAttribute('aria-hidden', 'true');
    svg.setAttribute('fill', 'none');
    svg.setAttribute('stroke', 'currentColor');
    svg.setAttribute('stroke-width', '1.75');
    svg.setAttribute('stroke-linecap', 'round');
    svg.setAttribute('stroke-linejoin', 'round');
    (paths || []).forEach(function (d) {
      var path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
      path.setAttribute('d', d);
      svg.appendChild(path);
    });
    return svg;
  }

  function fichaAccion(label, extra) {
    var ficha = CATALOGO[label] || {};
    var titulo = (extra || '').replace(/\s+/g, ' ').trim();
    return {
      label: label,
      desc: ficha.desc || (titulo && titulo !== label ? titulo : ''),
      paths: ficha.paths || ['M12 6v12', 'M6 12h12']
    };
  }

  function acciones(header) {
    var items = [];
    header.querySelectorAll('.wa-chip, a.wa-accent').forEach(function (node) {
      var label = (node.textContent || '').replace(/\s+/g, ' ').trim();
      if (!label) return;
      var item = fichaAccion(label, node.getAttribute('title'));
      item.disabled = !!node.disabled;
      item.run = function () { node.click(); };
      items.push(item);
    });
    var borrar = header.querySelector('button[title^="Eliminar chat"]');
    if (borrar) {
      var itemBorrar = fichaAccion('Eliminar chat', borrar.getAttribute('title'));
      itemBorrar.danger = true;
      itemBorrar.disabled = !!borrar.disabled;
      itemBorrar.run = function () { borrar.click(); };
      items.push(itemBorrar);
    }
    return items;
  }

  function cerrar() {
    menu.classList.remove('is-open');
    menu.replaceChildren();
    if (ancla) ancla.setAttribute('aria-expanded', 'false');
    ancla = null;
  }

  function colocar() {
    if (!ancla || !menu.classList.contains('is-open')) return;
    var rect = ancla.getBoundingClientRect();
    var ancho = menu.offsetWidth || 320;
    var alto = menu.offsetHeight || 40;
    var left = Math.max(8, Math.min(rect.left, window.innerWidth - ancho - 8));
    var top = rect.bottom + 6;
    if (top + alto > window.innerHeight - 8) top = Math.max(8, rect.top - alto - 6);
    menu.style.left = left + 'px';
    menu.style.top = top + 'px';
  }

  function abrir(boton) {
    var header = boton.closest('header');
    if (!header) return;
    var items = acciones(header);
    menu.replaceChildren();
    if (!items.length) {
      menu.appendChild(el('button', '', 'Sin acciones para este chat'));
      menu.lastChild.disabled = true;
    }
    items.forEach(function (item) {
      if (item.danger) menu.appendChild(el('div', 'wa-chat-menu-sep'));
      var btn = el('button', item.danger ? 'wa-chat-menu-item wa-chat-menu-danger' : 'wa-chat-menu-item');
      btn.type = 'button';
      btn.setAttribute('role', 'menuitem');
      btn.disabled = item.disabled;
      var ico = el('span', 'wa-chat-menu-ico');
      ico.appendChild(iconoMenu(item.paths));
      var copy = el('span', 'wa-chat-menu-copy');
      copy.appendChild(el('span', 'wa-chat-menu-label', item.label));
      if (item.desc) copy.appendChild(el('span', 'wa-chat-menu-desc', item.desc));
      btn.appendChild(ico);
      btn.appendChild(copy);
      btn.addEventListener('click', function () {
        cerrar();
        item.run();
      });
      menu.appendChild(btn);
    });
    ancla = boton;
    boton.setAttribute('aria-expanded', 'true');
    menu.classList.add('is-open');
    colocar();
    requestAnimationFrame(colocar);
  }

  window.InfinityWaMenu = {
    toggle: function (boton) {
      if (!boton) return;
      if (ancla === boton) cerrar();
      else abrir(boton);
    }
  };

  document.addEventListener('click', function (ev) {
    if (!ancla) return;
    if (menu.contains(ev.target) || ancla.contains(ev.target)) return;
    cerrar();
  });
  document.addEventListener('keydown', function (ev) {
    if (ev.key === 'Escape') cerrar();
  });
  window.addEventListener('resize', colocar);
  window.addEventListener('scroll', colocar, true);
})();
