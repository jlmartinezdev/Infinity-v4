# Play Integrity (pedido backend)

La app **play** (`com.isp.staff`) manda un token de Google Play Integrity en el login.
El flavor **direct** (sideload) **no** lo envía: Integrity no aplica fuera de Play.

Pedido agrupado: `docs/ORDEN_INFINITY_STAFF_1.5.md` §4.

Esto es la defensa real contra APK pirateado / ingeniería inversa del login. Un check
solo en el APK se parchea; el servidor tiene que rechazar la sesión.

## 1. Nonce

```http
GET /api/v1/staff/integrity/nonce
```

Sin Bearer. Un solo uso, ~120 s.

```json
{
  "success": true,
  "data": {
    "nonce": "<base64 URL-safe, 32 bytes aleatorios>",
    "expires_in": 120
  }
}
```

Si responde **404**, Staff genera el nonce en el teléfono (modo actual, más débil:
replay posible). Cuando el endpoint exista, el nonce **tiene** que nacer en servidor.

## 2. Login

`POST /api/v1/login`:

```json
{
  "usuario": "...",
  "password": "...",
  "tipo": "staff",
  "device_name": "android_staff_app",
  "integrity_token": "<token Play Integrity>",
  "integrity_nonce": "<nonce del paso 1>"
}
```

Verificar el token con la API de Google. Cloud project vinculado **2026-08-20**:

- Play Console → **Protegido con Play → API de Play Integrity**
- Proyecto: **ISP Staff Panel** (`isp-staff-panel`, número `166400319630`, el mismo que Firebase / `google-services.json`)
- Respuestas activas: licencias (`LICENSED` / `UNLICENSED`), integridad de app (`PLAY_RECOGNIZED`), dispositivo (`MEETS_DEVICE_INTEGRITY`)
- Encriptación de respuestas: administrada por Google (classic)

Infinity necesita una cuenta de servicio **en ese mismo proyecto** (API Play Integrity habilitada al vincular) para decodear el token. No usar otro Cloud project.

### Env

```env
INTEGRITY_ENFORCE=false
INTEGRITY_PACKAGE_NAME=com.isp.staff
INTEGRITY_PACKAGE_CLIENTES=com.isp.clientes
INTEGRITY_CLOUD_PROJECT_NUMBER=166400319630
INTEGRITY_CREDENTIALS=/ruta/al/service-account.json
INTEGRITY_ALLOWED_CERT_SHA256=
INTEGRITY_ALLOWED_CERT_SHA256_CLIENTES=
INTEGRITY_STAFF_DEVICE_NAME=android_staff_app
INTEGRITY_CLIENTES_DEVICE_NAME=android_clientes_app
INTEGRITY_NONCE_TTL=120
```

Un solo `INTEGRITY_ENFORCE`. Con `true`, el 401 discrimina package por `tipo`:

| tipo | package | nonce |
|------|---------|-------|
| staff | `com.isp.staff` | `GET /staff/integrity/nonce` |
| cliente | `com.isp.clientes` | `GET /portal/v1/integrity/nonce` |

Clientes (detalle): `docs/API_PORTAL_INTEGRITY.md`.

Con `INTEGRITY_ENFORCE=true` y app Android conocida, **401** si:

- nonce ausente, vencido o reusado
- `packageName` ≠ el esperado del tipo
- certificado no listado (mapa por package; vacío = no chequear cert)
- `appRecognitionVerdict` ≠ `PLAY_RECOGNIZED`
- el device no incluye `MEETS_DEVICE_INTEGRITY`
- `appLicensingVerdict` presente y ≠ `LICENSED`

Arranque: `INTEGRITY_ENFORCE=false` — guardar veredicto en log / tabla `integrity_verdicts`, **no** bloquear.
Si se prende ya, el instalado desde Android Studio no loguea (no viene de Play). Eso es
el comportamiento deseado en producción.

`INTEGRITY_CREDENTIALS`: ruta a JSON de service account (scope Play Integrity) o JSON inline.
Misma cuenta para Staff y Clientes (proyecto `166400319630`).

## 3. Qué no hace la app

- No guarda secretos de Integrity en el APK.
- No bloquea el login si Google no entrega token (emulador / cuota): **el servidor** decide.
- R8 ofusca el release; el mapping se sube a Play (ReTrace), no se publica.

## App

- Cliente: `PlayIntegrityHelper` + `AuthRepository.fetchIntegrityNonce`
- Login: `LoginViewModel` (flavor `play`)
- Warm-up al entrar al shell (no bloquea)
