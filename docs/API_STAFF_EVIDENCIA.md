# API Staff 1.5 — Evidencia fotográfica

Base: `/api/v1` · Auth: Bearer staff

## Endpoints

```http
POST /staff/visitas/{id}/evidencia
POST /staff/pedidos-instalacion/{id}/evidencia
Content-Type: multipart/form-data
```

| Parte | Obligatorio | Notas |
|-------|-------------|--------|
| `foto` | sí | JPEG/PNG/WebP, máx 8 MB |
| `caption` | no | texto |
| `client_photo_id` | no | UUID del teléfono; idempotente |

## OK

```json
{
  "success": true,
  "message": "Evidencia guardada",
  "data": {
    "id": "srv-123",
    "url": "https://infinityisppro.net/storage/evidencia/visitas/....jpg",
    "entity_type": "visita",
    "entity_id": "45",
    "caption": null,
    "client_photo_id": "…"
  }
}
```

## Reglas

- Permiso: visitas → `tickets.crear`; pedidos → `pedidos.editar`
- El técnico debe poder acceder al recurso (misma regla que listar/actualizar)
- Mismo `client_photo_id` + entidad → 200 con la misma `url` (no duplica)
- No-imagen → 422
- Si el endpoint falta (404/405), Staff conserva la foto en el teléfono

Storage: `storage/app/public/evidencia/{visitas|pedidos}/`
