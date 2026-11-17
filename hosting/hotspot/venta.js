(function () {
  var cfg = window.INTERPLUS_VENTA || {};
  var api = String(cfg.api || "").replace(/\/$/, "");
  var clave = "hotspot_venta_token";
  var cajaPlanes = document.getElementById("planes");
  var cajaEstado = document.getElementById("estado");
  var cajaCred = document.getElementById("credenciales");
  var sondeo = null;

  function macDeLaUrl() {
    var mac = "";
    try {
      mac = new URLSearchParams(window.location.search).get("mac") || "";
    } catch (e) {
      mac = "";
    }
    mac = mac.trim();
    if (!/^([0-9a-fA-F]{2}[:-]){5}[0-9a-fA-F]{2}$/.test(mac)) {
      return "";
    }
    return mac;
  }

  function leerToken() {
    try {
      var deUrl = new URLSearchParams(window.location.search).get("token") || "";
      if (deUrl) return deUrl;
      return localStorage.getItem(clave) || "";
    } catch (e) {
      return "";
    }
  }

  function guardarToken(token) {
    try { localStorage.setItem(clave, token); } catch (e) {}
  }

  function olvidarToken() {
    try { localStorage.removeItem(clave); } catch (e) {}
  }

  function mostrarEstado(texto, esError) {
    cajaEstado.hidden = !texto;
    cajaEstado.textContent = texto || "";
    cajaEstado.className = esError ? "estado error" : "estado";
  }

  function pintarPlanes(planes) {
    cajaPlanes.textContent = "";
    if (!planes || !planes.length) {
      var vacio = document.createElement("p");
      vacio.className = "ayuda";
      vacio.textContent = "No hay planes a la venta en este momento.";
      cajaPlanes.appendChild(vacio);
      return;
    }
    planes.forEach(function (plan) {
      var fila = document.createElement("article");
      fila.className = "plan";
      var texto = document.createElement("div");
      var titulo = document.createElement("h2");
      titulo.textContent = plan.nombre + " · " + plan.gigas + " GB";
      var detalle = document.createElement("p");
      detalle.textContent = plan.vigencia || "Hasta agotar los gigas";
      texto.appendChild(titulo);
      texto.appendChild(detalle);
      var boton = document.createElement("button");
      boton.type = "button";
      boton.textContent = plan.precio_texto || "Pagar";
      boton.addEventListener("click", function () { comprar(plan, boton); });
      fila.appendChild(texto);
      fila.appendChild(boton);
      cajaPlanes.appendChild(fila);
    });
  }

  function dato(etiqueta, valor) {
    var fila = document.createElement("div");
    fila.className = "dato";
    var bloque = document.createElement("div");
    var nombre = document.createElement("span");
    nombre.textContent = etiqueta;
    var fuerte = document.createElement("strong");
    fuerte.textContent = valor;
    bloque.appendChild(nombre);
    bloque.appendChild(fuerte);
    var copiar = document.createElement("button");
    copiar.type = "button";
    copiar.className = "secundario";
    copiar.textContent = "Copiar";
    copiar.addEventListener("click", function () {
      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(valor).then(function () {
          copiar.textContent = "Copiado";
        }).catch(function () {
          copiar.textContent = valor;
        });
      }
    });
    fila.appendChild(bloque);
    fila.appendChild(copiar);
    return fila;
  }

  function mostrarCredenciales(data) {
    if (sondeo) clearInterval(sondeo);
    olvidarToken();
    cajaCred.hidden = false;
    cajaCred.textContent = "";
    var titulo = document.createElement("h2");
    titulo.textContent = "Pago confirmado";
    var ayuda = document.createElement("p");
    ayuda.textContent = "Volvé a la pantalla del Wi‑Fi e ingresá este usuario y este PIN.";
    cajaCred.appendChild(titulo);
    cajaCred.appendChild(ayuda);
    cajaCred.appendChild(dato("Usuario", data.username || ""));
    cajaCred.appendChild(dato("PIN", data.password || ""));
    mostrarEstado("");
  }

  function seguir(token) {
    if (!api || !token) return;
    if (sondeo) clearInterval(sondeo);
    var intentos = 0;
    mostrarEstado("Estamos confirmando tu pago. Si ya pagaste, el usuario aparece acá.");
    sondeo = setInterval(function () {
      intentos += 1;
      if (intentos > 45) {
        clearInterval(sondeo);
        mostrarEstado("El pago todavía no figura. Si ya lo hiciste, tocá de nuevo el plan o volvé a abrir esta página.");
        return;
      }
      fetch(api + "/api/hotspot/ventas/" + encodeURIComponent(token), {
        cache: "no-store",
        headers: { "Accept": "application/json" }
      })
        .then(function (r) { return r.json(); })
        .then(function (data) {
          if (data && data.estado === "pagado" && data.username) {
            mostrarCredenciales(data);
          } else if (data && data.estado === "rechazado") {
            clearInterval(sondeo);
            olvidarToken();
            mostrarEstado("El pago no se confirmó. Podés elegir un plan de nuevo.", true);
          }
        })
        .catch(function () {});
    }, 4000);
  }

  function comprar(plan, boton) {
    if (!api) {
      mostrarEstado("Falta la dirección de Infinity en config.js.", true);
      return;
    }
    boton.disabled = true;
    mostrarEstado("Abriendo el pago…");
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
          boton.disabled = false;
          mostrarEstado((res.data && res.data.mensaje) || "No se pudo abrir el pago.", true);
          return;
        }
        guardarToken(res.data.token);
        window.location.href = res.data.checkout_url;
      })
      .catch(function () {
        boton.disabled = false;
        mostrarEstado("No se pudo abrir el pago. Revisá la conexión e intentá de nuevo.", true);
      });
  }

  function cargarPlanes() {
    if (!api) {
      cajaPlanes.textContent = "";
      var falta = document.createElement("p");
      falta.className = "ayuda";
      falta.textContent = "Falta la dirección de Infinity en config.js.";
      cajaPlanes.appendChild(falta);
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
        var error = document.createElement("p");
        error.className = "ayuda";
        error.textContent = "No se pudieron cargar los planes. Actualizá la página en un momento.";
        cajaPlanes.appendChild(error);
      });
  }

  var token = leerToken();
  if (token) seguir(token);
  cargarPlanes();
})();
