# API Staff — Config Google Maps (app)

Base: `/api/v1` · Auth: `Authorization: Bearer <token>` · middleware `api.staff`

La app Staff obtiene la misma API key de Google Maps que usa Infinity (Maps JavaScript API) para mapa de pedidos y flota en WebView. No se hardcodea en el APK.

## Preferido

`GET /api/v1/staff/config/maps`

```json
{
  "success": true,
  "message": "OK",
  "data": {
    "google_maps_api_key": "AIza…",
    "maps_api_key": "AIza…",
    "api_key": "AIza…",
    "map_id": null
  }
}
```

| Campo | Descripción |
|-------|-------------|
| `google_maps_api_key` / `maps_api_key` / `api_key` | Key JS (`GOOGLE_MAPS_API_KEY`, Maps JavaScript API) |
| `map_id` | Opcional Cloud-based map styling (`GOOGLE_MAPS_MAP_ID`) |

## Fallbacks

| Método | Ruta | Dónde leer |
|--------|------|------------|
| GET | `/staff/config` | `data.maps` / `data.google_maps_api_key` |
| GET | `/staff/me` | `data.maps_api_key` / `data.config.google_maps_api_key` |

## Env

```env
GOOGLE_MAPS_API_KEY=AIza…
GOOGLE_MAPS_MAP_ID=
```

Misma key que el panel web (`config('services.google.maps_key')`).

## Restricciones recomendadas (Google Cloud)

- API: **Maps JavaScript API** (igual que Infinity web).
- Restricción HTTP referrer / Android package según cómo se sirva; en WebView `file://` a veces conviene key con restricción por IP de servidor o sin restricción de app Android (solo JS).
- No exponer keys server-side (Geocoding) si no hace falta.
