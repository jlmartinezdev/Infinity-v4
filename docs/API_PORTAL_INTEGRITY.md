# Play Integrity — Interplus Clientes (arranque)

La app release de Interplus Clientes (`com.isp.clientes`) manda
`integrity_token` + `integrity_nonce` en `POST /login` (`tipo: cliente`).
El debug de Studio no manda token (`PLAY_STORE=false`).

Cloud (Clientes, 2026-08-21): **ISP Staff Panel** / `166400319630`
(el mismo que Firebase y Staff). Decodear con la misma cuenta de servicio
que Staff. No crear otro Cloud.

Pedido staff: `docs/API_STAFF_INTEGRITY.md` / `docs/ORDEN_INFINITY_STAFF_1.5.md` §4.

## 1. Nonce

```http
GET /api/v1/portal/v1/integrity/nonce
```

Sin Bearer. Un solo uso, ~120 s. Misma lógica que `GET /staff/integrity/nonce`.

```json
{
  "success": true,
  "data": {
    "nonce": "<base64 URL-safe, 32 bytes>",
    "expires_in": 120
  }
}
```

Si falta (404), la app genera nonce local (más débil).

## 2. Login

`POST /api/v1/login` con `tipo=cliente`:

```json
{
  "usuario": "...",
  "password": "...",
  "tipo": "cliente",
  "device_name": "android_clientes_app",
  "integrity_token": "<token Play Integrity>",
  "integrity_nonce": "<nonce del paso 1>"
}
```

Comportamiento (arranque):

1. Si vinieron token + nonce → consumir nonce y verificar con Play Integrity.
2. Package esperado: **`com.isp.clientes`** (no `com.isp.staff`).
3. Misma `INTEGRITY_CREDENTIALS` / Cloud que Staff.
4. Con `INTEGRITY_ENFORCE=false` → guardar veredicto en `integrity_verdicts` y **no** 401.
5. Studio / sin token → no bloquea.

## 3. Env (producción) — arranque

```env
INTEGRITY_ENFORCE=false
INTEGRITY_CLOUD_PROJECT_NUMBER=166400319630
INTEGRITY_CREDENTIALS=/ruta/al/service-account-isp-staff-panel.json
INTEGRITY_PACKAGE_CLIENTES=com.isp.clientes
# INTEGRITY_ALLOWED_CERT_SHA256_CLIENTES=   # vacío hasta anotar App Signing de Clientes
```

Un solo flag global. Cuando `INTEGRITY_ENFORCE=true`, el 401 discrimina por tipo:

| tipo | package |
|------|---------|
| staff | `com.isp.staff` |
| cliente | `com.isp.clientes` |

Certs: mapa por package (`INTEGRITY_ALLOWED_CERT_SHA256` / `_CLIENTES`). No setear el de Clientes hasta copiar SHA de Play App Signing.

## 4. Deploy

1. Ruta `portal/v1/integrity/nonce` (hecha).
2. Verify en login `tipo=cliente` sin bloquear (hecho).
3. Confirmar `INTEGRITY_ENFORCE=false`.
4. Enforce más adelante, con log-only de instalaciones desde Play.
