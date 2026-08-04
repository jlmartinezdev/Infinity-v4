# API Portal — Interplus Clientes 3.2 (Fase 3)

Base: `/api/v1` · Auth: `Authorization: Bearer <token>` · middleware `api.cliente`

Contrato alineado a `API_BACKEND_COMPLETO_V320.md`.

## Endpoints nuevos

| Método | Path | Notas |
|--------|------|--------|
| GET | `/portal/v1/feature-flags` | Master flags Home |
| GET | `/portal/v1/insights` | Interplus IA (reglas) |
| GET | `/portal/v1/referidos` | Código + share |
| POST | `/portal/v1/referidos/canjear` | `{ "codigo": "IP-…" }` |
| GET | `/portal/v1/pago-online` | `checkout_url` si hay env |
| GET | `/portal/v1/faqs` | CMS FAQs (`?topic=`) |

## Ya existentes (sin breaking)

`portal/resumen` ahora también puede incluir: `saldo_favor`, `proxima_vencimiento`, `disponibilidad_pct`, `cliente_desde`.

Loyalty / tickets / facturas / cobros: sin cambios de contrato.

## Panel Infinity (UI)

**Loyalty / App → App clientes** → `/loyalty/app-config`  
Permisos: `loyalty-app-config.ver` / `.editar`.

Ahí se editan flags, checkout, referidos, WhatsApp y FAQs (JSON). Se guardan en tabla `portal_app_config` y la API los usa con merge sobre `config/portal_app.php` + `.env`.

## Flags (env / panel)

Ver `config/portal_app.php`. `pago_online=auto` → `enabled` solo si hay URL de checkout (env o panel).

## Pago online

```env
PORTAL_PAGO_ONLINE_CHECKOUT_URL=https://checkout…/…?cliente={cliente_id}
PORTAL_PAGO_ONLINE_PROVIDER=bancard
```

Placeholders: `{cliente_id}`, `{cedula}`, `{token}`.

Sin URL → flag `coming_soon` y `checkout_url: null` (app muestra “Pronto”).

## Referidos

Columnas en `clientes`: `referido_codigo`, `referido_por_cliente_id`.  
Código tipo `IP-XXXXXX` se genera al primer GET.
