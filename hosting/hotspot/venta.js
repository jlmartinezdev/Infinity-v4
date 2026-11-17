(function () {
  var cfg = window.INTERPLUS_VENTA || {};
  var api = String(cfg.api || "").replace(/\/$/, "");
  var clave = "hotspot_venta_token";
  var cajaPlanes = document.getElementById("planes");
  var cajaCatalogo = document.getElementById("catalogo");
  var cajaEstado = document.getElementById("estado");
  var cajaEspera = document.getElementById("espera");
  var cajaCred = document.getElementById("credenciales");
  var sondeo = null;
  var MAX_CONSULTAS = 45; // 45 x 4 s = 3 minutos
  var SVG_CHECK = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg>';

  // ---------- utilidades ----------

  function el(tag, clase, texto) {
    var nodo = document.createElement(tag);
    if (clase) nodo.className = clase;
    if (texto !== undefined && texto !== null) nodo.textContent = texto;
    return nodo;
  }

  function parametro(nombre) {
    try {
      return new URLSearchParams(window.location.search).get(nombre) || "";
    } catch (e) {
      return "";
    }
  }

  function macDeLaUrl() {
    var mac = parametro("mac").trim();
    return /^([0-9a-fA-F]{2}[:-]){5}[0-9a-fA-F]{2}$/.test(mac) ? mac : "";
  }

  function leerToken() {
    var deUrl = parametro("token");
    if (deUrl) return deUrl;
    try { return localStorage.getItem(clave) || ""; } catch (e) { return ""; }
  }

  function guardarToken(token) {
    try { localStorage.setItem(clave, token); } catch (e) {}
  }

  function olvidarToken() {
    try { localStorage.removeItem(clave); } catch (e) {}
  }

  function guaranies(monto) {
    return "Gs. " + Math.round(monto).toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
  }

  function fechaCorta(iso) {
    if (!iso) return "";
    var d = new Date(iso);
    if (isNaN(d.getTime())) return "";
    var dos = function (n) { return (n < 10 ? "0" : "") + n; };
    return dos(d.getDate()) + "/" + dos(d.getMonth() + 1) + " a las " + dos(d.getHours()) + ":" + dos(d.getMinutes());
  }

  function mostrarEstado(texto, esError) {
    cajaEstado.hidden = !texto;
    cajaEstado.textContent = texto || "";
    cajaEstado.className = esError ? "estado error" : "estado";
  }

  function mostrarCatalogo(visible) {
    cajaCatalogo.hidden = !visible;
  }

  function pararSondeo() {
    if (sondeo) clearInterval(sondeo);
    sondeo = null;
  }

  function volverAlCatalogo(mensaje, esError) {
    pararSondeo();
    olvidarToken();
    cajaEspera.hidden = true;
    cajaCred.hidden = true;
    mostrarEstado(mensaje || "", esError);
    mostrarCatalogo(true);
  }

  // ---------- catálogo ----------

  function mensajePlanes(texto) {
    cajaPlanes.textContent = "";
    cajaPlanes.appendChild(el("p", "ayuda", texto));
    cajaPlanes.setAttribute("aria-busy", "false");
  }

  function indiceMejorPrecio(planes) {
    if (planes.length < 2) return -1;
    var mejor = -1;
    var mejorValor = Infinity;
    planes.forEach(function (plan, i) {
      var gigas = Number(plan.gigas) || 0;
      var precio = Number(plan.precio) || 0;
      if (gigas <= 0 || precio <= 0) return;
      var valor = precio / gigas;
      if (valor < mejorValor) {
        mejorValor = valor;
        mejor = i;
      }
    });
    return mejor;
  }

  function pintarPlanes(planes) {
    cajaPlanes.textContent = "";
    cajaPlanes.setAttribute("aria-busy", "false");
    if (!planes || !planes.length) {
      mensajePlanes("No hay planes a la venta en este momento.");
      return;
    }
    var mejor = indiceMejorPrecio(planes);
    planes.forEach(function (plan, i) {
      var gigas = Number(plan.gigas) || 0;
      var ilimitado = !!plan.ilimitado || gigas <= 0;
      var precio = Number(plan.precio) || 0;
      var boton = el("button", "plan" + (!ilimitado && i === mejor ? " destacado" : ""));
      boton.type = "button";

      if (!ilimitado && i === mejor) boton.appendChild(el("span", "etiqueta", "Mejor precio por GB"));

      var icono = el("span", "plan-gigas");
      icono.setAttribute("aria-hidden", "true");
      icono.appendChild(el("strong", "", ilimitado ? "∞" : String(gigas)));
      icono.appendChild(el("small", "", ilimitado ? "libre" : "GB"));

      var texto = el("span", "plan-texto");
      texto.appendChild(el("span", "plan-nombre", plan.nombre || (ilimitado ? "Ilimitado" : gigas + " GB")));
      texto.appendChild(el("span", "plan-vigencia", ilimitado
        ? "Navegá sin tope por " + (plan.vigencia || "el tiempo del plan")
        : "Vigencia: " + (plan.vigencia || "hasta agotar los gigas")));

      var monto = el("span", "plan-precio");
      monto.appendChild(el("strong", "", plan.precio_texto || (precio ? guaranies(precio) : "")));
      monto.appendChild(el("small", "", "Pagar ›"));

      boton.setAttribute("aria-label",
        (plan.nombre || "Plan") + ", " + (ilimitado ? "datos ilimitados" : gigas + " GB") + ", " + (plan.precio_texto || guaranies(precio)) + ". Pagar con TPago");
      boton.appendChild(icono);
      boton.appendChild(texto);
      boton.appendChild(monto);
      boton.addEventListener("click", function () { comprar(plan, boton); });
      cajaPlanes.appendChild(boton);
    });
  }

  function cargarPlanes() {
    if (!api) {
      mensajePlanes("Falta la dirección de Infinity en config.js.");
      return;
    }
    fetch(api + "/api/hotspot/planes", { cache: "no-store", headers: { "Accept": "application/json" } })
      .then(function (r) {
        if (!r.ok) throw new Error("planes");
        return r.json();
      })
      .then(function (data) { pintarPlanes((data && data.planes) || []); })
      .catch(function () {
        cajaPlanes.textContent = "";
        cajaPlanes.setAttribute("aria-busy", "false");
        cajaPlanes.appendChild(el("p", "ayuda", "No se pudieron cargar los planes."));
        var reintentar = el("button", "boton secundario", "Reintentar");
        reintentar.type = "button";
        reintentar.addEventListener("click", function () {
          mensajePlanes("Cargando planes…");
          cargarPlanes();
        });
        cajaPlanes.appendChild(reintentar);
      });
  }

  // ---------- espera del pago ----------

  function pintarEspera(checkoutUrl, agotado) {
    cajaCred.hidden = true;
    mostrarCatalogo(false);
    cajaEspera.hidden = false;
    cajaEspera.textContent = "";
    if (!agotado) cajaEspera.appendChild(el("div", "girando"));
    cajaEspera.appendChild(el("h2", "", agotado ? "Todavía no vemos el pago" : "Confirmando tu pago…"));
    cajaEspera.appendChild(el("p", "", agotado
      ? "Si ya pagaste, esperá un momento y tocá «Revisar de nuevo»."
      : "Si ya pagaste, tu usuario y PIN aparecen acá en unos segundos. No cierres esta página."));

    if (agotado) {
      var revisar = el("button", "boton", "Revisar de nuevo");
      revisar.type = "button";
      revisar.addEventListener("click", function () { seguir(leerToken()); });
      cajaEspera.appendChild(revisar);
    }
    if (checkoutUrl) {
      var pagar = el("a", "boton" + (agotado ? " secundario" : ""), "Volver a la página de pago");
      pagar.href = checkoutUrl;
      cajaEspera.appendChild(pagar);
    }
    var otro = el("button", "boton secundario", "Elegir otro plan");
    otro.type = "button";
    otro.addEventListener("click", function () { volverAlCatalogo(); });
    cajaEspera.appendChild(otro);
  }

  // ---------- credenciales ----------

  function copiarTexto(valor, boton) {
    var listo = function () {
      boton.textContent = "Copiado";
      boton.classList.add("listo");
      setTimeout(function () {
        boton.textContent = "Copiar";
        boton.classList.remove("listo");
      }, 2000);
    };
    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(valor).then(listo).catch(function () { copiarViejo(valor) && listo(); });
    } else if (copiarViejo(valor)) {
      listo();
    }
  }

  // Los portales cautivos suelen abrir la página sin HTTPS, donde no hay navigator.clipboard.
  function copiarViejo(valor) {
    var area = document.createElement("textarea");
    area.value = valor;
    area.setAttribute("readonly", "");
    area.style.position = "fixed";
    area.style.opacity = "0";
    document.body.appendChild(area);
    area.select();
    var ok = false;
    try { ok = document.execCommand("copy"); } catch (e) { ok = false; }
    document.body.removeChild(area);
    return ok;
  }

  function dato(etiqueta, valor) {
    var fila = el("div", "dato");
    var bloque = el("div");
    bloque.appendChild(el("span", "", etiqueta));
    bloque.appendChild(el("strong", "", valor));
    var copiar = el("button", "copiar", "Copiar");
    copiar.type = "button";
    copiar.setAttribute("aria-label", "Copiar " + etiqueta.toLowerCase());
    copiar.addEventListener("click", function () { copiarTexto(valor, copiar); });
    fila.appendChild(bloque);
    fila.appendChild(copiar);
    return fila;
  }

  function mostrarCredenciales(data) {
    pararSondeo();
    mostrarEstado("");
    cajaEspera.hidden = true;
    mostrarCatalogo(false);
    cajaCred.hidden = false;
    cajaCred.textContent = "";

    var check = el("div", "check");
    check.innerHTML = SVG_CHECK;
    cajaCred.appendChild(check);
    cajaCred.appendChild(el("h2", "", "¡Pago confirmado!"));
    cajaCred.appendChild(el("p", "", "Volvé a la pantalla del Wi‑Fi Zone e ingresá estos datos en «CI o RUC» y «PIN»."));

    var datos = el("div", "datos");
    datos.appendChild(dato("Usuario", data.username || ""));
    datos.appendChild(dato("PIN", data.password || ""));
    cajaCred.appendChild(datos);

    var detalle = [];
    detalle.push(Number(data.gigas) > 0 ? data.gigas + " GB" : "Datos ilimitados");
    var vence = fechaCorta(data.vence_at);
    if (vence) detalle.push("vence el " + vence);
    if (detalle.length) cajaCred.appendChild(el("p", "vence", detalle.join(" · ")));

    cajaCred.appendChild(el("p", "nota", "Sacale una captura o anotalos: los vas a necesitar cada vez que te conectes."));

    var wifi = el("a", "boton", "Ir al Wi‑Fi Zone");
    wifi.href = "http://wifi.interplus/login";
    cajaCred.appendChild(wifi);

    var otra = el("button", "boton secundario", "Comprar más gigas");
    otra.type = "button";
    otra.addEventListener("click", function () {
      cajaCred.hidden = true;
      mostrarCatalogo(true);
      cajaCatalogo.scrollIntoView({ behavior: "smooth", block: "start" });
    });
    cajaCred.appendChild(otra);
  }

  // ---------- consulta del estado ----------

  function consultar(token, alTerminar) {
    fetch(api + "/api/hotspot/ventas/" + encodeURIComponent(token), {
      cache: "no-store",
      headers: { "Accept": "application/json" }
    })
      .then(function (r) {
        if (r.status === 404) return { estado: "no-existe" };
        return r.json();
      })
      .then(function (data) {
        if (!data) return;
        if (data.estado === "pagado" && data.username) {
          mostrarCredenciales(data);
        } else if (data.estado === "pendiente") {
          if (sondeo) pintarEspera(data.checkout_url);
          if (alTerminar) alTerminar(data);
        } else if (data.estado === "no-existe") {
          volverAlCatalogo();
        } else {
          volverAlCatalogo("El pago no se confirmó. Podés elegir un plan de nuevo.", true);
        }
      })
      .catch(function () {});
  }

  function seguir(token) {
    if (!api || !token) return;
    pararSondeo();
    mostrarEstado("");
    pintarEspera("");
    var intentos = 0;
    var ultimoCheckout = "";
    var anotar = function (data) { ultimoCheckout = data.checkout_url || ultimoCheckout; };
    sondeo = setInterval(function () {
      intentos += 1;
      if (intentos > MAX_CONSULTAS) {
        pararSondeo();
        pintarEspera(ultimoCheckout, true);
        return;
      }
      consultar(token, anotar);
    }, 4000);
    consultar(token, anotar);
  }

  // ---------- compra ----------

  function comprar(plan, boton) {
    if (!api) {
      mostrarEstado("Falta la dirección de Infinity en config.js.", true);
      return;
    }
    var botones = cajaPlanes.querySelectorAll("button.plan");
    Array.prototype.forEach.call(botones, function (b) { b.disabled = true; });
    var precio = boton.querySelector(".plan-precio small");
    if (precio) precio.textContent = "Abriendo…";
    mostrarEstado("");

    var reactivar = function (mensaje) {
      Array.prototype.forEach.call(botones, function (b) { b.disabled = false; });
      if (precio) precio.textContent = "Pagar ›";
      mostrarEstado(mensaje, true);
    };

    fetch(api + "/api/hotspot/comprar", {
      method: "POST",
      headers: { "Content-Type": "application/json", "Accept": "application/json" },
      body: JSON.stringify({ plan_id: plan.id, mac: macDeLaUrl() })
    })
      .then(function (r) {
        return r.json().then(function (data) { return { ok: r.ok, data: data }; });
      })
      .then(function (res) {
        if (!res.ok || !res.data || !res.data.checkout_url) {
          reactivar((res.data && res.data.mensaje) || "No se pudo abrir el pago.");
          return;
        }
        guardarToken(res.data.token);
        window.location.href = res.data.checkout_url;
      })
      .catch(function () {
        reactivar("No se pudo abrir el pago. Revisá la conexión e intentá de nuevo.");
      });
  }

  // ---------- inicio ----------

  var token = leerToken();
  if (token) {
    guardarToken(token);
    seguir(token);
  }
  cargarPlanes();
})();
