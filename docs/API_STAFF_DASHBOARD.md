# API Staff 1.5 — Dashboard counts

```http
GET /api/v1/staff/dashboard
Authorization: Bearer <token staff>
```

Un round-trip. Números filtrados por el usuario (técnico ve lo suyo; admin/cajero ve el total donde aplica).

```json
{
  "success": true,
  "data": {
    "solicitudes_pendientes": 2,
    "pedidos_instalacion": 14,
    "visitas": 6,
    "tecnicos_online": 3
  }
}
```

| Campo | Qué cuenta |
|-------|------------|
| `solicitudes_pendientes` | Altas app clientes pendientes |
| `pedidos_instalacion` | Cola `estado_id = 3` (EN ESPERA PARA INSTALAR), visibles para el user |
| `visitas` | Tickets abiertos visibles (asignados; admin/cajero = todos de campo) |
| `tecnicos_online` | Flota con GPS reportado en los últimos **5 minutos** |

Fallback app: si 404, Staff arma el Panel con 4 listados.
