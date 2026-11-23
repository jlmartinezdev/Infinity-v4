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
    "limite_mensual": 1,
    "puntos_por_vencer": 500,
    "dias_al_vencimiento": 20,
    "proximo_vencimiento": "2026-09-08",
    "bono_bienvenida_activo": true,
    "bono_bienvenida_vence_en_dias": 20,
    "siguiente_premio_puntos": 800,
    "siguiente_premio_nombre": "Descuento 5% factura"
  }
}
```

Campos opcionales: si no hay vencimiento / bono / siguiente premio, **no se envían** (la app soft-fail).

`GET /portal/reglas-puntos` — “Cómo ganar puntos” (solo `activa` + `visible_portal`)

```json
{
  "success": true,
  "data": [
    {
      "codigo": "bienvenida_app",
      "nombre": "Bono de bienvenida",
      "descripcion": "La primera vez que abrís la app",
      "puntos": 500,
      "frecuencia": "unica_vez",
      "activo": true,
      "orden": 1,
      "fase": 1
    }
  ]
}
```

Motor: créditos generan lotes FIFO (`puntos_lotes`); débitos consumen el lote que vence primero. Comando: `loyalty:expirar-puntos` (diario 00:15).

---

## 3) Premios (galería)

### Panel Infinity
- CRUD premios + upload imagen + toggle rápido activo
- `puntos_requeridos`, `stock` (`null` = ilimitado; `0` = oculto), `activo`, `orden`
- **`destacado` (bool):** bloque grande en la app (ideal: uno solo)
- **`etiqueta`:** `nuevo` | `novedad` | `sale` | null (badge en app)
- **`tier`** 1–5, **`requiere_aprobacion`**, **`tope_anual_por_cliente`**
- **Tipo** del premio (define el canje; el cliente no elige modalidad):
  - `fisico` | `producto` | `retiro` → retiro en oficina
  - `descuento_factura` → descuento % y/o monto Gs.
  - `automatico` → entrega automática (estado APLICADO)
  - `requiere_aprobacion` → cola staff
  - `sorteo` → entrada a sorteo (APLICADO)

`GET /portal/premios` — catálogo del cliente logueado. Solo `activo=true` y stock `null` o `> 0`. Incluye `puntos_requeridos = 0`. No oculta por tier. Si `una_vez_por_cliente` y ya lo canjeó, no viene en esta lista (sigue en `GET /portal/canjes`).

La pantalla Premios y la tarjeta del Home solo se muestran si el flag `premios` está `enabled`. El saldo, si `puntos` está `enabled`. Ver `docs/API_PORTAL_V320.md`.

```json
{
  "success": true,
  "data": [
    {
      "id": 7,
      "nombre": "Bienvenida App 10%",
      "descripcion": null,
      "tipo": "descuento_factura",
      "imagen_url": "https://infinityisppro.net/storage/premios/bienvenida.jpg",
      "puntos_requeridos": 0,
      "gratis": true,
      "etiqueta_puntos": "GRATIS",
      "puede_canjear": true,
      "motivo": null,
      "descuento_porcentaje": 10,
      "descuento_monto": null,
      "stock": null,
      "activo": true,
      "orden": 1,
      "destacado": true,
      "etiqueta": "nuevo",
      "badge": "nuevo",
      "label": "nuevo",
      "tier": 1,
      "requiere_aprobacion": false,
      "tope_anual_por_cliente": 1,
      "una_vez_por_cliente": true
    }
  ]
}
```

Pantalla:

- Saldo arriba (`GET /portal/puntos` → `saldo`).
- El `destacado: true` va arriba, con `imagen_url` y badge (`etiqueta`).
- Botón **Canjear** si `puede_canjear`. Si es false, botón apagado y texto `motivo`.
- Si `gratis` o `etiqueta_puntos` es `GRATIS`, el precio se muestra **GRATIS** (también con saldo 0).
- `POST /portal/canjes` `{ "premio_id": 7 }`. El descuento lo aplica el servidor en la próxima factura. La app no lo calcula.

Home: `GET /portal/resumen` → `premio_destacado` (misma ficha). Tap abre Premios.

Mis cupones: `GET /portal/canjes`. Mostrar `estado_label` (`Pendiente` o `Aplicado en factura #123`). `factura_numero` cuando ya se aplicó. `estado_portal`: `pendiente` | `aplicado` | `cancelado`.

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
- [x] Premio de 0 puntos (`gratis`, `etiqueta_puntos: GRATIS`) solo en descuento de factura
- [x] Flags `premios` y `puntos` (`enabled` / `coming_soon` / `hidden`) + tarjeta en `GET /portal/resumen`
- [x] Canje de descuento aplicado en la próxima factura (`estado_label`, `factura_numero`)
- [x] `etiqueta`, `tier`, `requiere_aprobacion`, `tope_anual_por_cliente`, stock ilimitado
- [x] Tipos `automatico` / `requiere_aprobacion` / `sorteo`
- [x] `GET /portal/reglas-puntos` + campos frecuencia/orden/fase
- [x] `GET /portal/puntos` con vencimiento / bono / siguiente premio
- [x] FIFO lotes (`puntos_lotes`) + `loyalty:expirar-puntos`
- [x] Toggle rápido deshabilitar premio sin borrar canjes

Ver también: `INFINITY_PREMIOS_CMS.md` (requerimiento app).
