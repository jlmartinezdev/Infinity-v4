# API Staff 1.5 — Activos / auditoría (métricas app clientes)

## Heartbeat `last_seen`

En cada request portal autenticado (`api.cliente` y `GET /me`) Infinity actualiza `dispositivos.last_seen` (throttle 60 s).

Login cliente también setea `last_login` + `last_seen`.

## GET /staff/auditoria (paginado)

```http
GET /api/v1/staff/auditoria?app_activa=1&q=miguel&recencia=24h&page=1&per_page=50
Authorization: Bearer <staff>
```

| Query | Default | Notas |
|-------|---------|--------|
| `app_activa` | `1` | `1` = solo con app activa + acceso otorgado |
| `q` | — | nombre cliente / dispositivo (min 2) |
| `recencia` | — | `24h` \| `7d` \| `gt7d` según `last_seen` |
| `page` | 1 | |
| `per_page` | 50 | máx 100 |

```json
{
  "success": true,
  "data": {
    "items": [
      {
        "id": 88,
        "cliente": "Miguel Angel …",
        "dispositivo": "Pixel 7",
        "app_version": "3.2.0",
        "app_activa": true,
        "last_seen": "2026-08-19T23:40:00-03:00",
        "last_login": "2026-08-18T09:12:00-03:00"
      }
    ],
    "meta": {
      "current_page": 1,
      "per_page": 50,
      "total": 8432,
      "last_page": 169
    }
  }
}
```

Staff debe preferir `last_seen` (actividad). `last_login` puede ser más viejo si la sesión sigue abierta.
