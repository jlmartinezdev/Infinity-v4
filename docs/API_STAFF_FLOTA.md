# API Staff — Flota GPS y visitas

Base: `/api/v1` · Auth: `Authorization: Bearer <token>` (login `tipo: "staff"`)

## Endpoints

| Método | Ruta | Quién | Notas |
|--------|------|-------|--------|
| `POST` | `/staff/ubicacion` | Staff autenticado | Upsert última posición; rate-limit 20 s |
| `GET` | `/staff/ubicaciones` | Admin / gerente | Flota (en turno + updates &lt; 24 h) |
| `GET` | `/staff/ubicaciones/stream` | Admin / gerente | SSE opcional (`ubicacion_update` + heartbeat 30 s) |
| `GET` | `/staff/visitas` | Staff con `tickets.ver` | Tickets abiertos. Técnico: solo `asignado_a` = su `usuario_id`. Admin/gerente/cajero: todas |
| `GET` | `/staff/visitas/{id}` | Staff con `tickets.ver` | Detalle visita |
| `POST` | `/staff/visitas/{id}/actualizar` | Staff con `tickets.crear` | Cambio de estado + nota del técnico |
| `POST` | `/staff/avisos/en-camino` | Staff autenticado | WhatsApp Cloud API (número oficial). `tipo`: visita\|instalacion. Ver `docs/whatsapp-plantilla-en-camino.md` |
| `GET` | `/tickets/asuntos` | Staff `tickets.ver` | Catálogo de asuntos para filtro |

Aliases: `POST …/estado`, `PATCH /staff/visitas/{id}`.

## POST `/staff/ubicacion`

```json
{
  "lat": -25.2867,
  "lng": -57.6470,
  "accuracy": 12.5,
  "heading": 90.0,
  "en_turno": true,
  "visita_id": 123
}
```

Respuesta: `{ "success": true, "message": "ok" }`  
Si llega otro POST antes de 20 s, también responde `ok` (no escribe; no rompe UX).

## GET `/staff/ubicaciones`

```json
{
  "success": true,
  "data": [
    {
      "tecnico_id": 42,
      "nombre": "Juan Pérez",
      "lat": -25.2867,
      "lng": -57.6470,
      "accuracy": 12.5,
      "en_turno": true,
      "updated_at": "2026-07-28T16:20:00+00:00",
      "visita_id": 123
    }
  ]
}
```

Cliente marca offline si `updated_at` &gt; 5 minutos.

## GET `/staff/visitas`

Cada ítem:

```json
{
  "id": 373,
  "cliente": "Erasmo Aquino",
  "asunto": "Solicitud APP TV",
  "problema": "Solicitud APP TV",
  "direccion": "Calle Ejemplo 123",
  "zona": "Lambaré",
  "lat": -25.3431,
  "lng": -57.6064,
  "telefono": "5959...",
  "estado": "Pendiente",
  "prioridad": "Media",
  "tipo": "reporte",
  "urgencia": false,
  "ip_cliente": "10.2.8.75",
  "reportado_desde": "Presencial",
  "creado_por": "Jose Luis Martinez",
  "asignado_a": 42,
  "tecnico_id": 42,
  "usuario_asignado_id": 42,
  "asignado_nombre": "SERGIO DIAZ",
  "fecha_asignacion": "2026-07-28T16:02:00",
  "datos_diagnostico": "{\"ssid\":\"...\",\"rssi\":-65}",
  "nota_tecnico": "Cliente sin señal en living",
  "detalle_tecnico": null,
  "estados_disponibles": ["pendiente", "en_camino", "en_proceso", "resuelto", "no_realizado"],
  "ultima_actualizacion": "2026-07-28T16:02:00"
}
```

| Campo | Origen |
|-------|--------|
| `asunto` | `ticket_asuntos.nombre` |
| `zona` | Nodo del servicio activo (`ciudad` o `descripcion`) |
| `lat` / `lng` | `url_ubicacion` del cliente → pedido `lat`/`lon`/`maps_gps` → diagnóstico |
| `ip_cliente` | IP del servicio activo |
| `urgencia` | `true` si prioridad = `alta` |
| `asignado_a` | `tickets.asignado_id` (`tecnico_id` / `usuario_asignado_id` = mismo valor) |
| `asignado_nombre` | Nombre del técnico asignado |
| `datos_diagnostico` | string JSON (telemetría app clientes) |
| `nota_tecnico` / `detalle_tecnico` | Última nota cargada por el técnico en campo |
| `estados_disponibles` | Lista dinámica de estados permitidos |

**Filtro en API:**
- **Administrador / gerente / cajero** → todas las visitas abiertas (con o sin asignar).
- **Técnico** → solo `asignado_a == usuario_id` del Bearer. Sin asignar no aparecen.

## POST `/staff/visitas/{id}/actualizar`

**Body:**

```json
{
  "estado": "resuelto",
  "nota_tecnico": "Se reinstaló APP TV y quedó OK.",
  "detalle_tecnico": "Cliente conforme."
}
```

Al menos uno de `estado` / `nota_tecnico` / `detalle_tecnico`.  
Acepta keys (`resuelto`) o labels (`Resuelto`).  
Respuesta: VisitaItem actualizado en `data`.  
`resuelto` / `no_realizado` dejan de listarse en GET visitas. Auditoría vía trait Auditable + `actualizado_por_id`.

## POST `/staff/avisos/en-camino`

Envía el aviso desde el número oficial (WhatsApp Cloud API). La app no manda texto ni teléfono del cliente.

```json
{
  "tipo": "visita",
  "recurso_id": 123,
  "lat": -25.2867,
  "lng": -57.647
}
```

Respuesta: `{ "success": true, "data": { "enviado": true, "canal": "whatsapp", "message_id": "wamid..." } }`

Errores: 400 sin teléfono, 403 sin acceso, 404 inexistente, 409 reenvío &lt; 5 min, 422 config/plantilla, 502 proveedor.

Marca `en_camino` en el recurso antes del envío. Detalle de plantilla Meta: `docs/whatsapp-plantilla-en-camino.md`.

## Panel web

Ruta: `/staff/mapa-tecnicos` (admin / gerente)  
Poll cada 15 s a `/staff/mapa-tecnicos/ubicaciones`.

## Migración

```bash
php artisan migrate
```

Tablas: `staff_ubicaciones`; columnas `nota_tecnico`, `detalle_tecnico`, `actualizado_por_id` en `tickets`.
