# Contrato API — Loyalty / CMS (App Interplus)

Base: `https://infinityisppro.net/api/v1`  
Auth cliente: `Authorization: Bearer {token}` (mismo login portal)  
Respuesta estándar: `{ "success": true|false, "message": "...", "data": ... }`  
Listas: array directo **o** paginado Laravel `{ "data": [ ... ] }`.

La app **ya consume** estos endpoints (404 = vacío / 0 pts, no rompe).

---

## 1) Novedades (CMS)

### Panel Infinity
- CRUD novedades + upload imagen a storage Infinity
- Campos: título, subtítulo, imagen, URL acción, tipo, orden, activa, vigencia (opcional)
- Tipos: `promo` | `aviso` | `upsell` | `referi`

### Cliente
`GET /portal/novedades`

```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "titulo": "Promo del mes",
      "subtitulo": "50% off instalación",
      "imagen_url": "https://infinityisppro.net/storage/novedades/x.jpg",
      "accion_url": "https://...",
      "tipo": "promo",
      "orden": 1,
      "activa": true
    }
  ]
}
```

---

## 2) Puntos

### Reglas (backend)
- Sistema de puntos sobre clientes actuales
- Condiciones configurables en Infinity:
  - Bienvenida (al aprobar acceso app, una vez)
  - Pago recibido: puntos por **día del mes** (1–5), solo **factura de servicio**
- Exponer saldo al portal

`GET /portal/puntos`

```json
{
  "success": true,
  "data": {
    "saldo": 1200,
    "puede_canjear": true,
    "canjes_mes": 0,
    "limite_mensual": 1
  }
}
```

---

## 3) Premios (galería)

### Panel Infinity
- CRUD premios + upload imagen
- `puntos_requeridos`, `stock`, `activo`, `orden`
- **`destacado` (bool):** marca el premio del bloque grande “Premio destacado” en la app
  - Ideal: **solo uno** activo a la vez (toggle en CMS)
  - Si vienen varios en `true`, la app usa el de menor `orden`
- **Tipo** del premio (define el canje; el cliente no elige modalidad):
  - `fisico` | `producto` | `retiro` → retiro en oficina
  - `descuento_factura` → descuento % y/o monto Gs.

`GET /portal/premios`

```json
{
  "success": true,
  "data": [
    {
      "id": 10,
      "nombre": "Camiseta Interplus",
      "descripcion": "Edición especial selección",
      "imagen_url": "https://.../premios/camiseta.jpg",
      "puntos_requeridos": 50,
      "descuento_porcentaje": null,
      "descuento_monto": null,
      "stock": 8,
      "activo": true,
      "orden": 1,
      "destacado": true,
      "tipo": "fisico"
    },
    {
      "id": 11,
      "nombre": "Gorra Interplus",
      "imagen_url": "https://.../premios/gorra.jpg",
      "puntos_requeridos": 25,
      "descuento_porcentaje": null,
      "descuento_monto": null,
      "stock": 20,
      "activo": true,
      "orden": 2,
      "destacado": false,
      "tipo": "fisico"
    }
  ]
}
```

> Sin `destacado`, la app elige un fallback (alcanzable / con stock). Con imágenes buenas en `imagen_url` el layout se ve como el mockup.

---

## 4) Canjes / Premios tipados

### Reglas
- Máx. **1 canje por mes** por cliente
- El **tipo del premio** define qué pasa al canjear (el cliente **no** elige modalidad):
  - `fisico` / `producto` / `retiro` → retiro en oficina (cola staff)
  - `descuento_factura` → aplica % o monto a ítem/factura y descuenta puntos
- Al canjear: descontar puntos + reservar stock / encolar descuento

### Premio (campos CMS)
```json
{
  "id": 10,
  "nombre": "5% off en factura",
  "tipo": "descuento_factura",
  "puntos_requeridos": 50,
  "descuento_porcentaje": 5,
  "stock": 999,
  "activo": true
}
```

Otros: `descuento_monto` (Gs.), o premio físico sin %:
`"tipo": "fisico"`.

`POST /portal/canjes`

```json
{ "premio_id": 10 }
```

(`modalidad` opcional/legacy; si viene, el backend la ignora cuando el premio tiene `tipo` y usa el tipo del premio.)

`GET /portal/canjes` — historial del cliente

Estados sugeridos:
`PENDIENTE` → `EN_PREPARACION` → `LISTO_PARA_RETIRAR` → `ENTREGADO`  
o `APLICADO` (descuento factura) / `CANCELADO`

### Staff (panel Infinity — no va en la app cliente)
- Dashboard cola canjes del día (físicos)
- Descuentos: cola de aplicación a factura
- Acciones: preparar, listo, entregado, aplicar descuento, cancelar (+ devolver pts si corresponde)

---

## 5) Mejorá tu plan (upsell)

### Panel
- Catálogo planes publicables (`activo`, velocidad, precio, beneficios, `es_superior`)

`GET /portal/planes-upsell`

`POST /portal/solicitud-cambio-plan`

```json
{ "plan_id": 5, "servicio_id": 123 }
```

### Reglas de negocio
1. **Plan más elevado** + **saldo pendiente = 0** → cambio **automático**
2. **Plan más bajo** → ticket automático + seguimiento WhatsApp + aviso a **staff multiseleccionado**
3. Si hay saldo pendiente y pide upgrade → rechazar con mensaje claro

Respuesta ejemplo:

```json
{
  "success": true,
  "data": {
    "aplicado": true,
    "tipo_cambio": "upgrade",
    "mensaje": "Plan actualizado correctamente"
  }
}
```

o

```json
{
  "success": true,
  "data": {
    "aplicado": false,
    "tipo_cambio": "downgrade",
    "ticket_id": 987,
    "mensaje": "Solicitud creada. Te contactamos por WhatsApp."
  }
}
```

---

## 6) Media
- Upload de imágenes de novedades/premios **a Infinity** (storage)
- Devolver URL pública absoluta en `imagen_url`

---

## Checklist backend
- [x] Tablas: novedades, premios, puntos_movimientos, canjes, planes_upsell (+ pivotes staff aviso)
- [x] CRUD panel Infinity (novedades, premios, planes, reglas puntos)
- [x] Endpoints portal listados arriba
- [x] Cola canjes staff + estados
- [x] Motor puntos por condiciones (bienvenida + pago por día / factura servicio)
- [x] Lógica cambio de plan (upgrade auto / downgrade ticket+WA+staff)
- [x] Límite 1 canje/mes
- [x] Upload storage imágenes
- [x] Premios tipados (`tipo`, descuento %/monto) + `POST /canjes` solo con `premio_id`
- [x] Campo `destacado` en premios (CMS toggle + JSON portal)
