(function () {
    var cfg = window.__WHATSAPP_MENSAJES_CONFIG__;
    if (!cfg || !cfg.urls || !cfg.urls.encargo) return;

    var mapa = {};
    var mapaListo = false;
    var actual = null;
    var tel = '';
    var modal = null;

    var barra = document.createElement('div');
    barra.id = 'wa-encargo';
    barra.hidden = true;
    barra.innerHTML = '<p id="wa-encargo-texto"></p><div id="wa-encargo-acciones"></div>';
    document.body.appendChild(barra);

    var estilo = document.createElement('style');
    estilo.textContent = ''
        + '#wa-encargo{position:fixed;z-index:40;display:flex;align-items:center;gap:8px;padding:6px 12px;background:#0b141a;border-bottom:1px solid #2a3942;color:#e9edef;font:13px/1.3 system-ui,sans-serif;box-sizing:border-box}'
        + '#wa-encargo[hidden]{display:none}'
        + '#wa-encargo p{margin:0;min-width:0;flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}'
        + '#wa-encargo-acciones{display:flex;gap:6px;flex:none}'
        + '#wa-encargo button,#wa-encargo-modal button{border:0;border-radius:8px;background:#00a884;color:#fff;font:inherit;font-weight:600;padding:4px 10px;cursor:pointer}'
        + '#wa-encargo button.ghost,#wa-encargo-modal button.ghost{background:transparent;color:#8696a0}'
        + '.wa-encargo-chip{display:none}'
        + '#wa-encargo-modal{position:fixed;inset:0;z-index:80;background:rgba(4,8,16,.72);display:flex;align-items:center;justify-content:center;padding:16px}'
        + '#wa-encargo-modal[hidden]{display:none}'
        + '#wa-encargo-caja{width:min(420px,100%);background:#111b21;color:#e9edef;border:1px solid #2a3942;border-radius:12px;padding:16px}'
        + '#wa-encargo-caja h3{margin:0 0 8px;font-size:16px}'
        + '#wa-encargo-caja p{margin:0 0 10px;color:#8696a0;font-size:13px}'
        + '#wa-encargo-buscar{width:100%;box-sizing:border-box;margin-bottom:8px;padding:8px 10px;border-radius:8px;border:1px solid #2a3942;background:#0b141a;color:#e9edef}'
        + '#wa-encargo-lista{max-height:260px;overflow:auto}'
        + '#wa-encargo-lista button{display:flex;flex-direction:column;align-items:flex-start;width:100%;margin:0 0 4px;background:transparent;color:#e9edef;text-align:left;padding:8px 10px}'
        + '#wa-encargo-lista button span{color:#8696a0;font-size:11px;font-weight:400}'
        + '#wa-encargo-lista button:hover{background:#202c33}'
        + '#wa-encargo-error{color:#fca5a5;font-size:13px;min-height:1.2em}';
    document.head.appendChild(estilo);

    function csrf() {
        var meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.content : '';
    }

    function telefonoActivo() {
        var params = new URLSearchParams(window.location.search);
        return params.get('tel') || '';
    }

    function pintarBarra() {
        var header = document.querySelector('.wa-chat-pane .wa-header');
        var asunto = document.querySelector('.wa-chat-pane .wa-asunto');
        var texto = document.getElementById('wa-encargo-texto');
        var acciones = document.getElementById('wa-encargo-acciones');
        if (!header || !tel) {
            barra.hidden = true;
            return;
        }
        var ancla = (asunto && asunto.getBoundingClientRect().height > 0) ? asunto : header;
        var rect = ancla.getBoundingClientRect();
        if (rect.width < 20) {
            barra.hidden = true;
            return;
        }
        barra.hidden = false;
        barra.style.left = rect.left + 'px';
        barra.style.top = rect.bottom + 'px';
        barra.style.width = rect.width + 'px';
        var enc = actual && actual.encargado;
        if (!enc) {
            texto.textContent = 'Nadie se encarga de responder';
        } else if (enc.mio) {
            texto.textContent = 'Te encargás de responder';
        } else {
            texto.textContent = 'Se encarga ' + enc.nombre;
        }
        acciones.innerHTML = '';
        if (!cfg.puedeEditar) return;
        if (!enc) {
            acciones.appendChild(boton('Encargarme', tomar));
        } else if (enc.mio) {
            acciones.appendChild(boton('Transferir', abrirTransferir));
            acciones.appendChild(boton('Dejar', soltar, 'ghost'));
        }
    }

    function boton(etiqueta, accion, clase) {
        var btn = document.createElement('button');
        btn.type = 'button';
        btn.textContent = etiqueta;
        if (clase) btn.className = clase;
        btn.addEventListener('click', accion);
        return btn;
    }

    function post(url, cuerpo) {
        return fetch(url, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrf(),
            },
            credentials: 'same-origin',
            body: JSON.stringify(cuerpo || {}),
        }).then(function (res) {
            return res.json().catch(function () { return {}; }).then(function (data) {
                if (!res.ok) {
                    var error = new Error(data.message || 'No se pudo completar.');
                    error.status = res.status;
                    throw error;
                }
                return data;
            });
        });
    }

    function aplicar(data) {
        actual = data;
        if (data && data.telefono) {
            if (data.encargado) {
                mapa[data.telefono] = {
                    telefono: data.telefono,
                    usuario_id: data.encargado.usuario_id,
                    nombre: data.encargado.nombre,
                    mio: data.encargado.mio,
                    en_linea: !!data.encargado.en_linea,
                };
            } else {
                delete mapa[data.telefono];
            }
        }
        pintarBarra();
        sellarFilas();
    }

    function avisar(mensaje) {
        var texto = document.getElementById('wa-encargo-texto');
        if (texto) texto.textContent = mensaje;
    }

    function tomar() {
        post(cfg.urls.encargoTomar, { telefono: tel }).then(aplicar).catch(function (error) {
            avisar(error.message);
        });
    }

    function soltar() {
        post(cfg.urls.encargoSoltar, { telefono: tel }).then(aplicar).catch(function (error) {
            avisar(error.message);
        });
    }

    function asegurarModal() {
        if (modal) return modal;
        modal = document.createElement('div');
        modal.id = 'wa-encargo-modal';
        modal.hidden = true;
        modal.innerHTML = '<div id="wa-encargo-caja" role="dialog" aria-modal="true" aria-labelledby="wa-encargo-titulo">'
            + '<h3 id="wa-encargo-titulo">Pasar el chat</h3>'
            + '<p>La persona recibe un aviso para responder este número.</p>'
            + '<input id="wa-encargo-buscar" type="search" placeholder="Buscar personal">'
            + '<div id="wa-encargo-lista"></div>'
            + '<p id="wa-encargo-error"></p>'
            + '<div style="display:flex;justify-content:flex-end"><button type="button" class="ghost" id="wa-encargo-cerrar">Cancelar</button></div>'
            + '</div>';
        document.body.appendChild(modal);
        modal.addEventListener('click', function (evento) {
            if (evento.target === modal) cerrarModal();
        });
        document.getElementById('wa-encargo-cerrar').addEventListener('click', cerrarModal);
        document.getElementById('wa-encargo-buscar').addEventListener('input', function () {
            pintarStaff(modal._staff || [], this.value);
        });
        return modal;
    }

    function cerrarModal() {
        if (modal) modal.hidden = true;
    }

    function pintarStaff(lista, filtro) {
        var caja = document.getElementById('wa-encargo-lista');
        var q = String(filtro || '').toLowerCase();
        var visibles = lista.filter(function (persona) {
            return !q || String(persona.name || '').toLowerCase().indexOf(q) !== -1;
        });
        caja.innerHTML = '';
        if (!visibles.length) {
            caja.textContent = 'No hay personal para mostrar.';
            return;
        }
        visibles.forEach(function (persona) {
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.innerHTML = '<b></b><span></span>';
            btn.querySelector('b').textContent = persona.name;
            btn.querySelector('span').textContent = persona.cargo || 'Personal';
            btn.addEventListener('click', function () { transferir(persona); });
            caja.appendChild(btn);
        });
    }

    function abrirTransferir() {
        var caja = asegurarModal();
        document.getElementById('wa-encargo-error').textContent = '';
        document.getElementById('wa-encargo-buscar').value = '';
        caja.hidden = false;
        fetch(cfg.urls.encargoStaff, { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                caja._staff = data.usuarios || [];
                pintarStaff(caja._staff, '');
            })
            .catch(function () {
                document.getElementById('wa-encargo-error').textContent = 'No se pudo cargar el personal.';
            });
    }

    function transferir(persona) {
        document.getElementById('wa-encargo-error').textContent = '';
        post(cfg.urls.encargoTransferir, { telefono: tel, usuario_id: persona.usuario_id })
            .then(function (data) {
                cerrarModal();
                aplicar(data);
            })
            .catch(function (error) {
                document.getElementById('wa-encargo-error').textContent = error.message;
            });
    }

    function refrescarActual() {
        var siguiente = telefonoActivo();
        if (siguiente !== tel) {
            tel = siguiente;
            actual = null;
        }
        if (!tel) {
            pintarBarra();
            return;
        }
        var url = cfg.urls.encargo + (cfg.urls.encargo.indexOf('?') === -1 ? '?' : '&') + 'tel=' + encodeURIComponent(tel);
        fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
            .then(function (res) { return res.ok ? res.json() : null; })
            .then(function (data) { if (data && data.telefono === tel) aplicar(data); })
            .catch(function () {});
    }

    function refrescarMapa() {
        fetch(cfg.urls.encargos, { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
            .then(function (res) { return res.ok ? res.json() : null; })
            .then(function (data) {
                if (!data) return;
                mapa = {};
                (data.encargos || []).forEach(function (item) { mapa[item.telefono] = item; });
                mapaListo = true;
                if (actual && actual.telefono && actual.encargado) {
                    mapa[actual.telefono] = {
                        telefono: actual.telefono,
                        usuario_id: actual.encargado.usuario_id,
                        nombre: actual.encargado.nombre,
                        mio: actual.encargado.mio,
                        en_linea: !!actual.encargado.en_linea,
                    };
                }
                sellarFilas();
            })
            .catch(function () {});
    }

    function chatVm() {
        var el = document.getElementById('whatsapp-mensajes-app');
        var raiz = el && el.__vue_app__ && el.__vue_app__._instance;
        var hijo = raiz && raiz.subTree && raiz.subTree.component;
        return hijo ? hijo.proxy : null;
    }

    function sellarFilas() {
        document.querySelectorAll('.wa-encargo-chip').forEach(function (chip) { chip.remove(); });
        var vm = chatVm();
        if (!vm || !vm.conversaciones) return;
        vm.conversaciones.forEach(function (conv) {
            var clave = String(conv.telefono);
            var enc = mapa[clave];
            if (!enc) {
                if ((mapaListo || (actual && String(actual.telefono) === clave)) && conv.encargado) conv.encargado = null;
                return;
            }
            var previo = conv.encargado;
            if (previo && previo.usuario_id === enc.usuario_id && !!previo.en_linea === !!enc.en_linea && !!previo.mio === !!enc.mio && previo.nombre === enc.nombre) {
                return;
            }
            conv.encargado = {
                usuario_id: enc.usuario_id,
                nombre: enc.nombre,
                mio: !!enc.mio,
                en_linea: !!enc.en_linea,
            };
        });
    }

    document.addEventListener('keydown', function (evento) {
        if (evento.key === 'Escape') cerrarModal();
    });
    window.addEventListener('resize', pintarBarra);
    var lista = document.querySelector('.wa-list');
    if (window.MutationObserver) {
        var obs = new MutationObserver(function () { sellarFilas(); pintarBarra(); });
        obs.observe(document.getElementById('whatsapp-mensajes-app') || document.body, { childList: true, subtree: true });
    }
    if (lista) lista.addEventListener('scroll', pintarBarra, true);

    refrescarActual();
    refrescarMapa();
    setInterval(refrescarActual, 4000);
    setInterval(refrescarMapa, 8000);
    setInterval(pintarBarra, 1000);
})();
