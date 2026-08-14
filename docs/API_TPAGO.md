# TPago (Bancard) — links de pago

Docs oficiales: https://tpagodocs.bancard.com.py/

## Configuración `.env`

```env
TPAGO_ENABLED=true
TPAGO_ENV=sandbox
TPAGO_PUBLIC_KEY=
TPAGO_PRIVATE_KEY=
TPAGO_COMMERCE_CODE=
TPAGO_BRANCH_CODE=
TPAGO_CALLBACK_URL=https://infinityisppro.net/api/v1/webhooks/tpago
TPAGO_CALLBACK_USER=tpago_callback
TPAGO_CALLBACK_PASSWORD=
TPAGO_VERIFY_IP=false
```

- **Sandbox base:** `https://comercios.bancard.com.py:8888`
- **Prod base:** `https://comercios.bancard.com.py`
- Comercio sandbox (Interplus): `2265882` / sucursal `1`
- Clave pública: anteponer prefijo `apps/`
- Callback: `TPAGO_CALLBACK_URL` con Basic Auth (`TPAGO_CALLBACK_USER` / `TPAGO_CALLBACK_PASSWORD`). En producción Bancard notifica por puerto **443**.

Auth API: Basic `base64(apps/{public_key}:{private_key})`.

## Flujo

1. Infinity llama `generate-payment-link` con monto (Gs), descripción y `reference_id` (`FI-{id}-{timestamp}`).
2. Se guarda en `tpago_payment_links` (`link_alias`, `link_url`, factura, cliente).
3. Cliente paga en `link_url`.
4. TPago hace `POST /api/v1/webhooks/tpago` → se registra cobro (`forma_pago=tarjeta`) y se responde `{"status":"success"}`.

## API portal (app)

| Método | Path | Notas |
|--------|------|--------|
| GET/POST | `/api/v1/portal/v1/pago-online` | Genera/reusa link |

Query/body opcionales:

- `factura_interna_id` — si falta, usa la primera factura con saldo
- `force_new=1` — fuerza link nuevo

Respuesta (TPago listo):

```json
{
  "success": true,
  "data": {
    "checkout_url": "https://…/links?alias=…",
    "url": "https://…",
    "payment_url": "https://…",
    "provider": "tpago",
    "factura_interna_id": 123,
    "amount": 150000,
    "link_alias": "XXXX",
    "expires_at": "…",
    "reused": false
  }
}
```

Feature flag `pago_online=auto` → `enabled` si TPago está completo (keys + commerce + branch) o hay `PORTAL_PAGO_ONLINE_CHECKOUT_URL`.

## Panel web

- Listado: **Facturación → Links TPago** → `/tpago/links` (permiso `cobros.ver`)
- Detalle de cada link: estado, alias, ticket, cobro, request/callback JSON
- En **Factura interna → detalle**, con permiso `cobros.crear` y TPago configurado: botón **Generar link TPago**

## Webhook

`POST /api/v1/webhooks/tpago` (sin Sanctum).  
IPs Bancard (opc. `TPAGO_VERIFY_IP=true`): `190.128.218.209`, `190.128.232.10`, `190.104.129.98`, `200.85.46.226`.
