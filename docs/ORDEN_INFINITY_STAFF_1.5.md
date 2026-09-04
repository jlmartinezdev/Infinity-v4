# Orden Infinity — ISP Staff 1.5

Paquete para el backend de `infinityisppro.net`. La app Android **ya habla estos contratos**
(con fallback 404). Cuando estén en producción, Staff deja de improvisar.

Base: `https://infinityisppro.net/api/v1/`  
Envelope: `{ "success": true, "message": "...", "data": ... }`  
Auth: `Authorization: Bearer <token staff>` salvo el nonce de Integrity.

Contratos vivos (detalle): `API_STAFF_EVIDENCIA.md`, `API_STAFF_APP_CLIENTES_METRICAS.md`,
`API_STAFF_DASHBOARD.md`, `API_STAFF_INTEGRITY.md`.

---

## 0. Último acceso de la app clientes (el “hace 1 d” con la app abierta)

Staff **no pinguea** a Interplus. Lee `GET /staff/auditoria` y muestra el timestamp que
mande Infinity.

Hoy Interplus solo actualiza algo en **`POST /login`** (`tipo: cliente`). Si Miguel Angel
sigue con la sesión abierta, el token es válido pero **no hay heartbeat**. Staff muestra
`last_login` de ayer → “hace 1 d”.

### Qué hay que hacer

1. En **cualquier** request autenticado del portal (`Authorization: Bearer` de cliente:
   `GET /me`, `GET /portal/resumen`, facturas, etc.) tocar:

   `dispositivos.last_seen = now()` (ISO 8601 con offset, p.ej. `2026-08-19T23:40:00-03:00`).

2. `GET /staff/auditoria` debe devolver **`last_seen`** (actividad). `last_login` puede
   seguir existiendo, pero Staff usa el más reciente.

3. No hace falta endpoint nuevo en Interplus: el dashboard de clientes ya pega
   `portal/resumen` al abrir.

`app_activa`: dispositivo con acceso otorgado. “En línea ahora” es `last_seen` fresco
(Staff muestra “ahora” / “hace N min”).

**Estado Infinity:** implementado (`dispositivos` + heartbeat en middleware portal / `GET /me` + login).

---

## 1. Evidencia fotográfica

```http
POST /staff/visitas/{id}/evidencia
POST /staff/pedidos-instalacion/{id}/evidencia
Content-Type: multipart/form-data
```

| Parte | Obligatorio | Notas |
|-------|-------------|--------|
| `foto` | sí | JPEG, campo de archivo |
| `caption` | no | texto |
| `client_photo_id` | no | UUID del teléfono; **idempotente** (reenviar no duplica) |

OK:

```json
{
  "success": true,
  "message": "Evidencia guardada",
  "data": {
    "id": "srv-123",
    "url": "https://infinityisppro.net/storage/evidencia/....jpg",
    "entity_type": "visita",
    "entity_id": "45"
  }
}
```

Reglas:

- Guardar archivo (disco o S3) + fila en DB ligada a visita/pedido + usuario staff.
- Permiso: el técnico tiene que poder ver/editar ese recurso.
- `client_photo_id` único por entidad: segundo POST con el mismo id → 200 con el mismo `url`.
- Max ~8 MB. Rechazar no-imagen con 422.
- Si todavía no está: **404 o 405**. Staff deja la foto en el teléfono y no spamea.

Detalle: `docs/API_STAFF_EVIDENCIA.md`.

**Estado Infinity:** implementado.

---

## 2. Auditoría / Activos — paginación (10 mil dispositivos)

Hoy Staff baja **toda** la lista. Con miles de filas se traba.

```http
GET /staff/auditoria?app_activa=1&q=miguel&recencia=24h&page=1&per_page=50
```

| Query | Default | Notas |
|-------|---------|--------|
| `app_activa` | `1` en Staff | `1` = solo con app activa |
| `q` | — | busca nombre cliente / dispositivo (LIKE, min 2 chars) |
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

Laravel `LengthAwarePaginator` también sirve (`data` + `current_page` + `total`).
Queries desconocidas **hay que respetarlas**; no devolver 10 mil filas si vino `per_page`.

Índice: `(app_activa, last_seen)` y búsqueda por nombre.

**Estado Infinity:** implementado.

---

## 3. Panel — counts (no mandar listas enteras)

```http
GET /staff/dashboard
```

Un round-trip. Los números **ya filtrados por el usuario autenticado** (técnico ve lo
suyo; admin/cajero ve el total).

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
| `solicitudes_pendientes` | altas app clientes pendientes |
| `pedidos_instalacion` | cola **estado_id = 3** (EN ESPERA PARA INSTALAR), visibles para el user |
| `visitas` | tickets visibles (asignados; admin/cajero = todos abiertos de campo) |
| `tecnicos_online` | flota con GPS de los **últimos 5 minutos** (no `en_turno` viejo) |

Si falta el endpoint (404), Staff sigue armando el Panel con 4 listados. No es urgente
romper; sí es el Panel lento.

Detalle: `docs/API_STAFF_DASHBOARD.md`.

**Estado Infinity:** implementado.

---

## 4. Play Integrity (anti sideload / anti APK pirata)

La app **play** manda token de Google. El flavor **direct** (sideload propio) no.

### 4.1 Nonce de un solo uso

```http
GET /staff/integrity/nonce
```

Sin Bearer. Respuesta:

```json
{
  "success": true,
  "data": {
    "nonce": "<base64 URL-safe, 32 bytes>",
    "expires_in": 120
  }
}
```

Guardar nonce + expiry + ip (opcional) 2 minutos. Un solo uso.

### 4.2 Login

`POST /login` ya puede traer:

```json
{
  "usuario": "...",
  "password": "...",
  "tipo": "staff",
  "device_name": "android_staff_app",
  "integrity_token": "<token Play Integrity>",
  "integrity_nonce": "<el nonce del paso 4.1>"
}
```

Verificar con la API de Google Play Integrity. Cloud ya vinculado **2026-08-20**:

- Proyecto: **ISP Staff Panel** (`isp-staff-panel`, n° `166400319630`)
- Play Console → Protegido con Play → API de Play Integrity
- Respuestas activas: licencias, `PLAY_RECOGNIZED`, `MEETS_DEVICE_INTEGRITY`
- Decodear con una cuenta de servicio **de ese mismo proyecto** (no otro Cloud)

Docs de Google: decode Integrity token. Detalle: `docs/API_STAFF_INTEGRITY.md`.

Rechazar (401) cuando `INTEGRITY_ENFORCE=true` y `tipo=staff` y el cliente manda
`device_name=android_staff_app` **y**:

- nonce inexistente / vencido / reusado, o
- `packageName` ≠ `com.isp.staff`, o
- el certificado no es el de **Play App Signing**, o
- `appRecognitionVerdict` ≠ `PLAY_RECOGNIZED`, o
- el device **no** trae `MEETS_DEVICE_INTEGRITY`, o
- `appLicensingVerdict` presente y ≠ `LICENSED`

**Arranque:** `INTEGRITY_ENFORCE=false` — loguear el veredicto (tabla `integrity_verdicts`) y **no** bloquear.
Cuando Play Alpha esté estable, prender enforce. Si se prende ya, el debug de Android
Studio (no instalado desde Play) no va a poder loguear.

Nunca confiar en un check solo en el APK: se parchea. La defensa es **este** rechazo en
servidor + R8 en la app.

Detalle: `docs/API_STAFF_INTEGRITY.md`.

**Estado Infinity:** implementado (log-only por defecto; falta setear `INTEGRITY_CREDENTIALS` en prod para decode real).

---

## Orden sugerido de deploy

1. `last_seen` en middleware portal (arregla Activos ya).
2. Evidencia multipart (fotos de campo).
3. `GET /staff/auditoria` paginado.
4. `GET /staff/dashboard`.
5. Integrity nonce + verify en login (log-only, después enforce).

Avisar cuando cada pieza esté en producción para probarlo desde Staff.
