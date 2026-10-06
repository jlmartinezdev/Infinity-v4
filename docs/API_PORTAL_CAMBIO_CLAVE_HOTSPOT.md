# API Portal — Cambio de clave app + Slots Hotspot

Contrato para el programador de **Interplus Clientes**.

Base: `/api/v1` · Auth: `Authorization: Bearer <token>` · middleware `api.cliente`  
Prefijo de estos endpoints: `/portal/v1/...`

Panel Infinity para tildar módulos:  
**https://infinityisppro.net/loyalty/app-config** → sección *Módulos / flags*.

---

## 1. Feature flags (obligatorio leerlos)

`GET /portal/v1/feature-flags`

Keys nuevas (mismas que el panel):

| Key | Qué controla en la app | Estados |
|-----|------------------------|---------|
| `cambio_clave_app` | Pantalla / flujo “Cambiar clave de ingreso” | `enabled` \| `coming_soon` \| `hidden` |
| `hotspot_slots` | Gestión de hasta 3 usuarios Hotspot | `enabled` \| `coming_soon` \| `hidden` |
| `push_notifications` | Master push (ya existía) | idem |

**Regla app:**

- Solo mostrar UI si `state === "enabled"`.
- Si `coming_soon` → chip “Pronto”, sin llamar a los POST.
- Si `hidden` → no mostrar el módulo.
- Aunque el flag esté `enabled`, los endpoints responden **403/422** si el panel lo baja a otro estado.

---

## 2. Preferencias por cliente (push del cambio de clave)

Cada cliente puede **activar/desactivar** el push que se envía cuando él mismo cambia la clave.

### Campos en `GET /me` → `data.user.cliente`

```json
{
  "debe_cambiar_clave_app": true,
  "push_cambio_clave": true
}
```

| Campo | Tipo | Significado |
|-------|------|-------------|
| `debe_cambiar_clave_app` | bool | `true` si le otorgaron clave `PLUS****` y aún no la cambió. Mostrar banner / forzar flujo suave. |
| `push_cambio_clave` | bool | Preferencia del cliente. Default `true`. |

### `GET /portal/v1/preferencias`

```json
{
  "success": true,
  "message": "OK",
  "data": {
    "debe_cambiar_clave_app": true,
    "push_cambio_clave": true
  }
}
```

### `PATCH /portal/v1/preferencias`

Body:

```json
{ "push_cambio_clave": false }
```

Respuesta: mismo shape que GET + `message: "Preferencias actualizadas."`

**UI sugerida:** switch “Avisarme cuando se cambie mi clave de ingreso” en Ajustes / Seguridad.

---

## 3. Cambio de clave de ingreso (PLUS**** → propia)

### `POST /portal/v1/cambiar-clave`

Requiere flag `cambio_clave_app = enabled`.

Body:

```json
{
  "clave_actual": "PLUS5685",
  "clave_nueva": "MiClaveSegura1",
  "clave_nueva_confirmation": "MiClaveSegura1"
}
```

| Campo | Reglas |
|-------|--------|
| `clave_actual` | Requerido. Debe coincidir con la clave actual. |
| `clave_nueva` | 6–64 caracteres, distinta de la actual. |
| `clave_nueva_confirmation` | Debe coincidir con `clave_nueva` (regla Laravel `confirmed`). |

Éxito `200`:

```json
{
  "success": true,
  "message": "Clave actualizada.",
  "data": {
    "debe_cambiar_clave_app": false,
    "push_cambio_clave": true,
    "ticket": {
      "id": 12345,
      "estado": "resuelto",
      "fecha_cierre": "2026-10-05T01:20:00-03:00"
    },
    "push_enviado": true
  }
}
```

Errores típicos `422`:

- Clave actual incorrecta → `errors.clave_actual`
- Confirmación no coincide → `errors.clave_nueva`
- Flag off → mensaje “no está disponible”

### Comportamiento backend (historial)

1. Actualiza el hash de la contraseña del usuario portal.
2. Pone `debe_cambiar_clave_app = false`.
3. Crea un **ticket normal** en la cuenta del cliente:
   - Asunto: `Cambio de Contraseña App`
   - `reportado_desde = app`
   - `estado = resuelto`
   - `fecha_cierre = now`
   - Descripción: cambio realizado desde la aplicación
   - Aparece en `GET /portal/tickets` como cualquier otro ticket resuelto

> **Wi‑Fi:** el cambio de clave/nombre del router (`POST /portal/v1/cpe/wifi`) genera el mismo tipo de historial con asunto **Cambio de Contraseña en Router Wifi**. Ver `INFINITY_CPE_WIFI.md` y contrato diseñador `CONTRATO_UX_APP_CAMBIO_CLAVE_TICKETS.md`.
4. Si `push_cambio_clave === true` y hay token FCM → push:

```text
title: Clave actualizada
body:  Tu contraseña de ingreso a la app se cambió correctamente.
data:  { "tipo": "seguridad", "accion": "cambio_clave_app", "ticket_id": "…", "estado": "resuelto" }
```

Si el cliente destildó el switch, `push_enviado` será `false` y no se manda FCM.

**App:** tras éxito, guardar la nueva clave en el keystore/sesión si corresponde y refrescar `/me` o preferencias.

---

## 4. Slots Hotspot (máx. 3)

Requiere flag `hotspot_slots = enabled`.

Modelo igual al panel web Hotspot:

- Username = documento del cliente; slot 2/3 → `documento-2`, `documento-3`
- PIN = **exactamente 4 dígitos**
- Máximo **3** slots por cliente
- Al crear/editar/borrar, Infinity sincroniza RADIUS (observer)

### `GET /portal/v1/hotspot/slots`

```json
{
  "success": true,
  "data": {
    "max": 3,
    "ocupados": 1,
    "slots_libres": [2, 3],
    "slots": [
      {
        "id": 88,
        "slot_numero": 1,
        "username": "1234567",
        "password_masked": "••••",
        "servicio_id": 1020,
        "servicio_label": "#1020 · Plan 50 Mbps",
        "perfil": null,
        "last_synced": null,
        "comment": "Juan Pérez"
      }
    ],
    "servicios": [
      { "servicio_id": 1020, "label": "#1020 · Plan 50 Mbps" }
    ]
  }
}
```

**Nota:** el listado **no** incluye el PIN en claro (`password_masked` solamente). El PIN en claro solo vuelve en la respuesta de **crear** o **cambiar PIN**.

### `POST /portal/v1/hotspot/slots`

Body (todos opcionales salvo lógica de negocio):

```json
{
  "servicio_id": 1020,
  "slot_numero": 2,
  "password": "4821"
}
```

| Campo | Default |
|-------|---------|
| `servicio_id` | Primer servicio del cliente |
| `slot_numero` | Primer slot libre (1–3) |
| `password` | PIN aleatorio de 4 dígitos |

Éxito `201` — payload del slot **con** `password` en claro (mostrar una vez / copiar).

### `PATCH /portal/v1/hotspot/slots/{id}`

```json
{ "password": "9910" }
```

Éxito: slot con `password` en claro.

### `DELETE /portal/v1/hotspot/slots/{id}`

Éxito: `{ "success": true, "message": "Usuario hotspot eliminado." }`

Errores `403` si el flag no está `enabled`; `422` por cupo, PIN inválido, slot ajeno, etc.

---

## 5. Checklist implementación app

1. Leer `feature-flags` al arrancar / Home.
2. Si `cambio_clave_app=enabled`:
   - Pantalla Seguridad → Cambiar clave.
   - Si `debe_cambiar_clave_app` → banner “Te recomendamos cambiar la clave temporal PLUS…”.
   - Switch `push_cambio_clave` vía PATCH preferencias.
3. Tras `POST /cambiar-clave` ok → refrescar preferencias; el ticket queda en historial de soporte (`GET /portal/tickets`).
4. Si `hotspot_slots=enabled`:
   - Lista slots + “Agregar” (máx. 3).
   - Crear / editar PIN / eliminar.
   - Mostrar username + PIN solo al crear o al regenerar.
5. Push `tipo=seguridad` + `accion=cambio_clave_app` → deep-link opcional a Seguridad o al ticket.

---

## 6. Panel Infinity (ops)

Ruta: `/loyalty/app-config`

| Select | Efecto |
|--------|--------|
| **Cambio de clave app** → Visible | App y API habilitados |
| **Cambio de clave app** → Pronto / Oculto | App muestra “Pronto” / oculta; API rechaza |
| **Slots Hotspot** → igual | idem |

El tildado **por cliente** del push no es del panel global: lo controla el cliente en la app (`push_cambio_clave`). Staff puede ver el historial en tickets del cliente (asunto *Cambio de Contraseña App*, estado resuelto).

---

## 7. Resumen de rutas

| Método | Path | Permiso |
|--------|------|---------|
| GET | `/portal/v1/preferencias` | `portal.cuenta.ver` |
| PATCH | `/portal/v1/preferencias` | `portal.cuenta.ver` |
| POST | `/portal/v1/cambiar-clave` | `portal.cuenta.ver` |
| GET | `/portal/v1/hotspot/slots` | `portal.cuenta.ver` |
| POST | `/portal/v1/hotspot/slots` | `portal.cuenta.ver` |
| PATCH | `/portal/v1/hotspot/slots/{id}` | `portal.cuenta.ver` |
| DELETE | `/portal/v1/hotspot/slots/{id}` | `portal.cuenta.ver` |

Relacionados: `GET /portal/v1/feature-flags`, `GET /me`, `GET /portal/tickets`.
