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
| GET/POST | `/portal/v1/pago-online` | Link TPago (o template legacy) |
| GET | `/portal/v1/faqs` | CMS FAQs (`?topic=`) |
| GET | `/portal/v1/cpe/dhcp-clients` | DHCP LAN del CPE (soft-fail vacío). Doc: `INFINITY_CPE_DHCP_CLIENTS.md` |

## Ya existentes (sin breaking)

`portal/resumen` ahora también puede incluir: `saldo_favor`, `proxima_vencimiento`, `disponibilidad_pct`, `cliente_desde`.

Loyalty / tickets / facturas / cobros: sin cambios de contrato.

## Panel Infinity (UI)

**Loyalty / App → App clientes** → `/loyalty/app-config`  
Permisos: `loyalty-app-config.ver` / `.editar`.

Ahí se editan flags, checkout, metadata de métodos de pago, referidos, WhatsApp y FAQs (JSON). Se guardan en tabla `portal_app_config` y la API los usa con merge sobre `config/portal_app.php` + `.env`.

## Flags (env / panel)

Ver `config/portal_app.php`.

| Key canónica | Alias (app) | `auto` resuelve a |
|---|---|---|
| `pago_online` | `pago_tarjeta`, `tpago`, `bancard` | `enabled` si TPago o URL checkout |
| `pago_tigo_money` | `pago_tigo`, `tigo` | `enabled` si hay datos (tel/fields) |
| `pago_transferencia` | `transferencia`, `bank_transfer` | `enabled` si hay datos de cuenta |
| `pago_qr` | `qr` | `enabled` si hay qr_alias/link/id |

Cada key de pago publica `metadata` (title, subtitle, badge, sort_order, icon, instructions, whatsapp, fields, atajos).  
Si en el panel se sube un logo, la API incluye `metadata.icon_url` (URL absoluta); la app debe preferir `icon_url` sobre el ícono genérico `icon` (`card`|`tigo`|`qr`|`transfer`).  
WhatsApp cobranzas del panel → `metadata.whatsapp` si el método no lo define.

## Pago online

Preferido: **TPago** (ver `docs/API_TPAGO.md`). Con `TPAGO_*` completo:

- `POST /portal/v1/pago-online` + `factura_interna_id` → link por factura
- `POST /portal/v1/pago-online` + solo `{ "amount": N }` → link saldo a favor (`purpose: saldo_favor`)
- Sin ambos → primera factura pendiente

**Instrucción para la app móvil:** `docs/APP_CLIENTES_PAGO_ONLINE.md`.

Fallback legacy (template):

```env
PORTAL_PAGO_ONLINE_CHECKOUT_URL=https://checkout…/…?cliente={cliente_id}
PORTAL_PAGO_ONLINE_PROVIDER=bancard
```

Placeholders: `{cliente_id}`, `{cedula}`, `{token}`.

Sin TPago ni URL → flag `coming_soon` y `checkout_url: null` (app muestra “Pronto”).

## Referidos

Columnas en `clientes`: `referido_codigo`, `referido_por_cliente_id`.  
Código tipo `IP-XXXXXX` se genera al primer GET.
