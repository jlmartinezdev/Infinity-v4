# Orden backend — Push FCM deep-link (ISP Staff)

**App:** ISP Staff (`com.isp.staff`)  
**Proyecto Firebase:** `isp-staff-panel`  
**Topic (opcional):** `staff`  
**Fecha:** 2026-07-29  

Al tocar la notificación, la app debe abrir el **detalle** de la solicitud o visita. Hoy solo guarda título/cuerpo; **hace falta `tipo` + id en el `data` del mensaje FCM**.

---

## Regla

Todo push operativo debe incluir payload **`data`** (no solo `notification`).  
Si mandás solo el bloque `notification`, Android muestra la bandeja pero la app **no recibe** campos custom en background de forma fiable.

---

## Campos obligatorios en `data`

| Key | Tipo | Req | Descripción |
|-----|------|-----|-------------|
| `title` | string | sí* | Título (también puede ir en `notification.title`) |
| `body` | string | sí* | Cuerpo (también `notification.body`) |
| `tipo` | string | **sí** | Destino: ver tabla abajo |
| `id` | string/number | **sí** para abrir detalle | ID de la entidad |

\* Al menos título o body para el historial in-app.

### Aliases aceptados por la app

| Campo canónico | Aliases |
|----------------|---------|
| `tipo` | `tab` |
| `id` | `solicitud_id`, `visita_id`, `ticket_id`, `entity_id` |
| `title` | `titulo` |
| `body` | `mensaje`, `message` |

Preferí siempre `tipo` + `id`.

---

## Valores de `tipo`

| `tipo` | Acción en la app |
|--------|------------------|
| `solicitud` | Abre `solicitud/{id}` |
| `alta` / `pedido` / `registro` | Igual que solicitud |
| `visita` | Abre `visita/{id}` |
| `ticket` / `reporte` / `urgencia` | Igual que visita |
| `pedido_instalacion` / `instalacion` | Abre pedido de instalación `{id}` (módulo campo; **no** es solicitud/alta) |
| `flota` | Abre flota (solo admin; `id` opcional) |

> `tipo=pedido` sigue mapeando a **solicitud/alta**. No usarlo para pedidos de instalación.

Sin `tipo` pero con `id` → la app asume **solicitud**.

---

## Ejemplos listos para copiar

### 1) Nueva solicitud de alta (aprobar)

```json
{
  "notification": {
    "title": "Nueva solicitud",
    "body": "Juan Pérez pidió acceso a la app"
  },
  "data": {
    "title": "Nueva solicitud",
    "body": "Juan Pérez pidió acceso a la app",
    "tipo": "solicitud",
    "id": "128"
  }
}
```

### 2) Ticket / visita asignada al técnico

```json
{
  "notification": {
    "title": "Nueva visita",
    "body": "Internet Lento — Erasmo Aquino"
  },
  "data": {
    "title": "Nueva visita",
    "body": "Internet Lento — Erasmo Aquino",
    "tipo": "visita",
    "id": "373"
  }
}
```

### 3) Solo data (recomendado si querés controlar el tray vos)

```json
{
  "data": {
    "title": "Visita actualizada",
    "body": "Cliente confirmó domicilio",
    "tipo": "visita",
    "visita_id": "373"
  }
}
```

---

## Destinatarios

| Evento | A quién |
|--------|---------|
| Nueva solicitud de alta | Admin / cajero / quien aprueba (token o topic staff) |
| Ticket asignado | Token FCM del técnico (`usuario_id` = `asignado_a`) |
| Aviso general staff | Topic `staff` (sin `id` → solo abre pestaña) |

Tokens se guardan con `POST /staff/save-push-token` (`push_token`, `device_type: android`).

---

## Checklist backend

- [x] Todo push operativo incluye `data.tipo` + `data.id` (o alias)
- [x] Valores `tipo` alineados a la tabla de arriba
- [x] `id` es el mismo que usa `GET /staff/solicitudes/{id}` o `GET /staff/visitas/{id}`
- [x] Mensaje dual `notification` + `data` (FcmPushService ya arma ambos)
- [ ] Firebase Android app registrada para package `com.isp.staff` (google-services.json real)

### Implementación (Infinity v4)

| Evento | Destino | `tipo` / `id` |
|--------|---------|---------------|
| Nueva solicitud de acceso | Topic `staff` | `solicitud` + `id` |
| Ticket creado sin asignar | Topic `staff` | `visita` + `id` |
| Ticket creado/asignado a técnico | Token del `asignado_id` | `visita` + `id` |
| WhatsApp → ticket | Topic `staff` | `visita` + `id` |
| Pedido instalación (P1) | Token técnico / topic | `pedido_instalacion` + `id` |

Prueba: `php artisan fcm:probar-staff --tipo=visita --id=373`  
Pedido instalación: `php artisan fcm:probar-staff --tipo=pedido_instalacion --id=1001`

## Relacionado

`GET /staff/visitas` ya incluye `asignado_a` / `asignado_nombre`. Ver [`API_STAFF_FLOTA.md`](./API_STAFF_FLOTA.md).
