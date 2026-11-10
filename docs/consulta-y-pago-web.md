# Consulta de cuenta y pago con TPago

Documento para la página pública de Interplus. El cliente escribe su cédula, ve lo que debe y paga con TPago.

El token es el valor de `WEB_CONSULTA_TOKEN` en el `.env` del panel. Va en el servidor de la página, nunca en el JavaScript, nunca en el HTML y nunca en un repositorio. Quien lo tenga puede consultar cualquier cédula.

Base: `https://infinityisppro.net`

Todas las llamadas son `POST` con `Content-Type: application/json` y este header:

```
Authorization: Bearer <WEB_CONSULTA_TOKEN>
```

También se acepta el header `X-Web-Token` con el mismo valor.

## Qué hace la página

1. Un campo para la cédula y un botón Consultar.
2. Se llama a `/api/v1/web/cuenta`.
3. Se muestra el nombre, el plan, el estado del servicio, el saldo y la lista de facturas.
4. Cada factura con saldo tiene un botón Pagar.
5. Ese botón llama a `/api/v1/web/pago` y abre `checkout_url` (la página de TPago).
6. El cliente paga ahí. Al volver, un botón «Ya pagué» vuelve a consultar la cuenta. El saldo baja cuando Bancard avisa; puede tardar unos segundos.

Los montos vienen en guaraníes, sin decimales. Mostrarlos como `120.000 Gs.`

## 1. Consultar la cuenta

`POST /api/v1/web/cuenta`

```json
{ "documento": "1234567" }
```

La cédula puede ir con puntos o guiones. Mínimo 4 caracteres, máximo 32.

Respuesta `200`:

```json
{
  "success": true,
  "message": "OK",
  "data": {
    "nombre": "Nombre Apellido",
    "documento": "1234567",
    "estado": "A",
    "plan": "PLAN BASICO FTTH 100 Mbps",
    "saldo": 120000,
    "moneda": "PYG",
    "facturas": [
      {
        "id": 1001,
        "estado": "pendiente",
        "periodo_desde": "2026-10-01",
        "periodo_hasta": "2026-10-31",
        "vencimiento": "2026-11-05",
        "total": 120000,
        "saldo": 120000
      }
    ]
  }
}
```

`estado` del servicio, para mostrar al cliente:

| Valor | Texto |
| --- | --- |
| `A` | Activo |
| `S` | Suspendido |
| `C` | Cortado |

Si `estado` o `plan` vienen `null`, no mostrar esa línea.

`saldo` es la suma de las facturas de la lista. Si es `0`, la lista viene vacía: mostrar «Su cuenta está al día» y ocultar el botón de pago.

`facturas[].id` es el número que hay que mandar al pagar. `vencimiento` es la fecha límite de esa factura, en formato `AAAA-MM-DD`. `periodo_desde` y `periodo_hasta` son el mes que cubre.

## 2. Crear el pago

`POST /api/v1/web/pago`

```json
{
  "documento": "1234567",
  "factura_interna_id": 1001
}
```

`factura_interna_id` es el `id` de la factura que el cliente eligió. Si no se envía, se cobra la factura pendiente más antigua.

Respuesta `200`:

```json
{
  "success": true,
  "message": "OK",
  "data": {
    "checkout_url": "https://comercios.bancard.com.py/links?alias=XXXX",
    "factura_interna_id": 1001,
    "amount": 120000,
    "moneda": "PYG",
    "link_alias": "XXXX",
    "expires_at": "2026-11-11T13:00:00-03:00",
    "reused": false
  }
}
```

Abrir `checkout_url` en la misma pestaña o en una nueva. Esa pantalla es de Bancard / TPago. No hace falta armar otro formulario de tarjeta.

`amount` es lo que se va a cobrar. `reused: true` significa que se reutilizó un link que todavía no venció; se abre igual. `expires_at` puede venir `null`.

No hace falta avisar a Infinity cuando el pago termina. Bancard lo notifica y la factura pasa a pagada. La página solo tiene que volver a consultar.

## Errores

Cuando algo sale mal, el cuerpo es:

```json
{ "success": false, "message": "Texto para mostrar" }
```

| HTTP | Cuándo | Mensaje |
| --- | --- | --- |
| 401 | Falta el token o no coincide | No autorizado. |
| 404 | Esa cédula no tiene cuenta | No encontramos una cuenta con ese documento. |
| 422 | La cuenta no debe nada, o la factura no es de esa cédula | Esa cuenta no tiene saldo para pagar. |
| 422 | El documento viene vacío o es inválido | Respuesta de validación, con `errors` |
| 502 | TPago no pudo crear el link | No se pudo crear el link de pago. |
| 503 | TPago está apagado | El pago con TPago no está disponible. |

Mostrar `message` al cliente. En el 401, no mostrar el token: es un error del servidor de la página.

## Lo que la página no recibe

La API no entrega clave del servicio, IP, dirección ni teléfono. No hace falta pedirlos. El único dato del cliente es la cédula.
