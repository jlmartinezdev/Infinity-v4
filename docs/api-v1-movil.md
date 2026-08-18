# Infinity ISP — API Móvil v1

Documento para el equipo de desarrollo de la app móvil.

**Versión:** 1.1 (Fase 1 + FCM cliente)  
**Fecha:** 2026-07-22  
**Auth:** Laravel Sanctum (Bearer Token)  
**Base path:** `/api/v1`

---

## 1. Entorno y URL base

| Entorno | URL base (ejemplo) |
|---------|-------------------|
| Producción | `https://infinityisppro.net/api/v1` |
| Local (XAMPP) | `http://localhost/infinity-v4/public/api/v1` |

> Ajustar según el host real desplegado. Todas las rutas de este documento son relativas a esa base.

### Headers comunes

```http
Accept: application/json
Content-Type: application/json
Authorization: Bearer {token}
```

El header `Authorization` es obligatorio en todas las rutas excepto `POST /login`.

---

## 2. Formato de respuesta

### Éxito

```json
{
  "success": true,
  "message": "OK",
  "data": { }
}
```

### Error de validación (HTTP 422)

```json
{
  "message": "The usuario field is required. (and 1 more error)",
  "errors": {
    "usuario": ["The usuario field is required."],
    "password": ["The password field is required."]
  }
}
```

### Error de negocio / permiso

```json
{
  "success": false,
  "message": "No tienes permiso para realizar esta acción."
}
```

| Código HTTP | Significado |
|-------------|-------------|
| 200 | OK |
| 201 | Creado |
| 401 | No autenticado / token inválido |
| 403 | Sin permiso o tipo de usuario incorrecto |
| 404 | Recurso no encontrado |
| 422 | Validación |

Los listados paginados usan el formato estándar de Laravel (`data`, `current_page`, `last_page`, `per_page`, `total`, `links`, etc.) dentro de `data`.

---

## 3. Autenticación

Hay **dos tipos de usuario**. La app debe guardar `user.tipo` tras el login y enrutar pantallas según eso.

| Tipo | Quién | Credenciales |
|------|--------|--------------|
| `staff` | Personal (cajero, técnico, admin) | Email + contraseña del sistema |
| `cliente` | Abonado / cliente final | **Usuario = documento (CI/RUC)** y **contraseña = mismo documento** |

### 3.1 Login

`POST /login` *(público)*

**Body**

| Campo | Tipo | Obligatorio | Descripción |
|-------|------|-------------|-------------|
| `usuario` | string | sí | Staff: email. Cliente: número de documento |
| `password` | string | sí | Staff: contraseña. Cliente: mismo documento |
| `tipo` | string | no | `staff` \| `cliente`. Si se omite: si `usuario` contiene `@` → staff; si no → cliente |
| `device_name` | string | no | Nombre del dispositivo (aparece en tokens) |

**Ejemplo — cliente**

```json
{
  "usuario": "5371721",
  "password": "5371721",
  "tipo": "cliente",
  "device_name": "android-pixel"
}
```

**Ejemplo — staff**

```json
{
  "usuario": "cajero@empresa.com",
  "password": "secreto",
  "tipo": "staff",
  "device_name": "ios-iphone"
}
```

**Respuesta 200**

```json
{
  "success": true,
  "message": "Inicio de sesión exitoso",
  "data": {
    "token": "8|xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx",
    "token_type": "Bearer",
    "user": {
      "usuario_id": 9,
      "name": "COLETA SOSA PEREIRA",
      "email": "5371721@portal.cliente",
      "estado": "activo",
      "tipo": "cliente",
      "cliente_id": 6,
      "rol": {
        "rol_id": 5,
        "descripcion": "Cliente App"
      },
      "permisos": [],
      "es_administrador": false,
      "cliente": {
        "cliente_id": 6,
        "cedula": "5371721",
        "nombre": "COLETA",
        "apellido": "SOSA PEREIRA",
        "email": null,
        "telefono": "+595 983 668004",
        "direccion": "...",
        "estado": "activo"
      }
    }
  }
}
```

**Notas para la app**

1. Guardar `token` de forma segura (Keychain / Keystore).
2. Enviar siempre `Authorization: Bearer {token}`.
3. Usar `user.tipo` para decidir flujo:
   - `cliente` → solo endpoints `/portal/*`
   - `staff` → endpoints de personal; respetar `user.permisos`
4. El documento del cliente puede ingresarse con o sin puntos/guiones; el backend normaliza a dígitos.
5. Si el cliente aún no tenía usuario portal, se crea automáticamente en el primer login exitoso.

### 3.2 Usuario actual

`GET /me`

Devuelve el mismo objeto `user` del login.

### 3.3 Logout

`POST /logout`

Invalida el token actual. Respuesta:

```json
{
  "success": true,
  "message": "Sesión cerrada"
}
```

---

## 4. Portal del cliente (`tipo = cliente`)

Todas bajo `/portal/*`.  
Si un usuario staff las llama → **403**.  
Si un cliente llama rutas de staff → **403**.

### 4.1 Resumen de cuenta

`GET /portal/resumen`  
Permiso: `portal.cuenta.ver`

```json
{
  "success": true,
  "message": "OK",
  "data": {
    "cliente": {
      "cliente_id": 6,
      "cedula": "5371721",
      "nombre": "COLETA",
      "apellido": "SOSA PEREIRA",
      "email": null,
      "telefono": "...",
      "direccion": "...",
      "estado": "activo"
    },
    "resumen": {
      "total_pendiente": 0,
      "saldo_a_favor": 0,
      "servicios": 1
    },
    "servicios": [
      {
        "servicio_id": 12,
        "estado": "A",
        "estado_label": "Activo",
        "plan": "Fibra 50 Mbps",
        "velocidad": "50 Mbps",
        "precio": "150000.00",
        "ip": "10.x.x.x"
      }
    ]
  }
}
```

**Estados de servicio**

| Código | Label |
|--------|--------|
| `A` | Activo |
| `S` | Suspendido |
| `C` | Cortado |
| `X` | Cancelado |

### 4.2 Facturas

`GET /portal/facturas`  
Permiso: `portal.facturas.ver`

| Query | Tipo | Descripción |
|-------|------|-------------|
| `solo_pendientes` | bool | `1` / `true` = solo con saldo |
| `per_page` | int | Máx. 50 (default 20) |
| `page` | int | Página |

Cada ítem incluye: `id`, `estado`, `total`, `saldo_pendiente`, `fecha_emision`, `fecha_vencimiento`, `periodo_desde`, `periodo_hasta`, `tipo_factura`.

### 4.3 Historial de cobros / pagos

`GET /portal/cobros`  
Permiso: `portal.cobros.ver`

Query: `per_page`, `page`.

Cada ítem: `id`, `numero_recibo`, `fecha_pago`, `fecha_pago_formato`, `monto`, `monto_formato`, `forma_pago`, `forma_pago_label`, `concepto`, `referencia`.

**Recibo para pintar en la app** (mismo ticket que Infinity, modo `con_grafico`):

`GET /portal/cobros/{id}`  
Permiso: `portal.cobros.ver`

La app recorre `data.layout[]` y dibuja. No hace falta armar el orden a mano.

| `layout[].tipo` | Qué pintar |
|-----------------|------------|
| `logo` | Imagen centrada (`url`), alto ~40 dp |
| `titulo` | Nombre empresa MAYÚSCULAS, centrado, 16 sp bold |
| `contacto` | `lineas[]` centradas, 12 sp, color muted |
| `separador` | Línea 1 px `#9CA3AF` |
| `texto` | Línea (`bold` opcional) |
| `fila` | Izquierda / derecha. Si `destacado`: TOTAL más grande |
| `factura` | Bloque con borde izquierdo `#D1D5DB`: `izq`/`der` + `periodo` muted |
| `pie` | `lineas[]` centradas MAYÚSCULAS bold + `numero` muted |

Colores en `data.estilo`. Textos ya vienen en ASCII (sin tildes).

También: `empresa` (incluye `logo_url`), `cliente`, `recibo`, `facturas`, `compartir_texto` (share plano), `pdf_url` (PDF público), `archivo_nombre` (ej. `recibo-001-001-0000140.png`).

### 4.4 Tickets de soporte

**Listar** — `GET /portal/tickets` — permiso `portal.tickets.ver`  
**Asuntos disponibles** — `GET /portal/ticket-asuntos` — permiso `portal.tickets.ver`  
**Crear** — `POST /portal/tickets` — permiso `portal.tickets.crear`

Los permisos del cliente se gestionan de forma **global** en el panel web: **Usuarios → Clientes app**. El login (`/me`) devuelve `user.permisos` con los códigos activos; la app debe ocultar secciones sin permiso.

```json
{
  "ticket_asunto_id": 3,
  "descripcion": "Sin internet desde esta mañana",
  "prioridad": "media",
  "datos_diagnostico": "{\"ssid\":\"MiRed\",\"rssi\":-59,\"speed\":32.02}"
}
```

| Campo | Obligatorio | Valores |
|-------|-------------|---------|
| `ticket_asunto_id` | sí | ID de `/portal/ticket-asuntos` |
| `descripcion` | sí | texto (sin JSON de telemetría) |
| `prioridad` | no | `baja` \| `media` \| `alta` (default `media`) |
| `datos_diagnostico` | no | JSON crudo (string) u objeto con telemetría de red |

El ticket se crea con `estado: pendiente` y `reportado_desde: app`. Al crearlo, el backend envía push FCM al topic staff (`FCM_STAFF_TOPIC`, default `staff`).

---

## 5. API de personal (`tipo = staff`)

Además del token, cada ruta exige **permisos** del usuario (mismos códigos que el sistema web).  
Si falta el permiso → **403**.

Permisos usados en Fase 1:

| Permiso | Endpoints |
|---------|-----------|
| `dashboard.ver` | stats |
| `clientes.ver` | clientes |
| `servicios.ver` | servicios |
| `cobros.ver` / `cobros.crear` | cobros |
| `tickets.ver` / `tickets.crear` | tickets |
| `tareas.ver` / `tareas.crear` | tareas |

Administradores (`es_administrador: true`) tienen todos los permisos.

### 5.1 Dashboard

`GET /dashboard/stats`  
Permiso: `dashboard.ver`

```json
{
  "data": {
    "clientes_activos": 1100,
    "servicios_activos": 1050,
    "servicios_suspendidos": 40,
    "tickets_abiertos": 12,
    "cobros_hoy_monto": 2500000,
    "cobros_hoy_cantidad": 18
  }
}
```

### 5.2 Clientes

| Método | Ruta | Permiso |
|--------|------|---------|
| GET | `/clientes/buscar?q=` | `clientes.ver` |
| GET | `/clientes?q=&per_page=&page=` | `clientes.ver` |
| GET | `/clientes/{cliente_id}` | `clientes.ver` |

**Buscar** (`q` mínimo 2 caracteres): por nombre, apellido, cédula, teléfono o ID. Máx. 15 resultados.

**Detalle** (`GET /clientes/{id}`) incluye:

- `cliente`
- `resumen` (`total_pendiente`, `saldo_a_favor`, `servicios`)
- `servicios`, `facturas`, `cobros`, `tickets` (últimos ~40)

### 5.3 Servicios

| Método | Ruta | Query útiles |
|--------|------|--------------|
| GET | `/servicios` | `cliente_id`, `estado`, `q`, `per_page`, `page` |
| GET | `/servicios/{servicio_id}` | — |

Permiso: `servicios.ver`

### 5.4 Cobros

| Método | Ruta | Permiso |
|--------|------|---------|
| GET | `/cobros` | `cobros.ver` |
| GET | `/cobros/facturas-pendientes?cliente_id=` | `cobros.ver` |
| GET | `/cobros/{id}` | `cobros.ver` |
| POST | `/cobros` | `cobros.crear` |

### 5.4.1 Reporte morosos (N8N)

`GET /reportes/morosos`

Permiso: `pagos-pendientes.ver` **o** `cobros.ver` **o** `factura-interna.ver`

Una fila por cliente con saldo pendiente (misma lógica de saldo que pendientes de pago en el panel).

| Query | Default | Descripción |
|-------|---------|-------------|
| `solo_vencidos` | `1` | Solo facturas ya vencidas |
| `con_telefono` | `1` | Solo clientes con teléfono |
| `solo_cobrables` | `1` | Cliente no inactivo + servicio no cancelado |
| `min_dias_mora` | `1` | Mínimo de días desde el vencimiento más antiguo |
| `max_dias_mora` | — | Máximo de días de mora |
| `min_saldo` | — | Saldo mínimo |
| `incluir_facturas` | `0` | Incluye detalle de facturas |
| `per_page` | `50` | 1–200 |
| `page` | `1` | |

Ejemplo N8N:

```http
GET /api/v1/reportes/morosos?per_page=50&min_dias_mora=3&con_telefono=1
Authorization: Bearer {token}
```

Respuesta (`data.items[]`): `cliente_id`, `nombre`, `telefono`, `saldo_pendiente`, `dias_mora`, `facturas_count`, `factura_ids`, etc. Paginación en `data.meta`.

**Filtros listado:** `cliente_id`, `fecha_desde`, `fecha_hasta`, `usuario_id` (solo admin), `per_page`, `page`.  
Usuarios no admin solo ven sus propios cobros.

**Crear cobro**

```json
{
  "cliente_id": 6,
  "factura_interna_id": 100,
  "factura_interna_ids": [100, 101],
  "monto": 150000,
  "fecha_pago": "2026-07-15",
  "forma_pago": "efectivo",
  "referencia": null,
  "concepto": "Pago mensualidad",
  "observaciones": null
}
```

| Campo | Obligatorio | Notas |
|-------|-------------|-------|
| `cliente_id` | sí | |
| `monto` | sí | > 0 |
| `fecha_pago` | sí | `Y-m-d` |
| `forma_pago` | sí | `efectivo` \| `transferencia` \| `tarjeta` \| `cheque` \| `otro` |
| `factura_interna_id` | no | Una factura |
| `factura_interna_ids` | no | Varias: reparte el monto proporcional al saldo |
| `referencia`, `concepto`, `observaciones` | no | |

Antes de cobrar, usar `GET /cobros/facturas-pendientes?cliente_id=6` para mostrar saldos.

### 5.5 Tickets (staff)

| Método | Ruta | Permiso |
|--------|------|---------|
| GET | `/tickets` | `tickets.ver` |
| GET | `/tickets/asuntos` | `tickets.ver` |
| GET | `/tickets/{id}` | `tickets.ver` |
| POST | `/tickets` | `tickets.crear` |
| PATCH | `/tickets/{id}/estado` | `tickets.crear` |

**Filtros listado:** `estado`, `ocultar_cerrados=1`, `cliente_id`, `asignado_id`, `per_page`, `page`.

Cada ticket incluye `datos_diagnostico` (objeto JSON o `null`) cuando la app cliente envió telemetría de red al crear el reporte.

**Crear**

```json
{
  "cliente_id": 6,
  "ticket_asunto_id": 2,
  "descripcion": "Cambio de contraseña PPPoE",
  "prioridad": "alta",
  "asignado_id": 3,
  "observaciones": null
}
```

**Cambiar estado**

```json
{
  "estado": "en_proceso",
  "observaciones": "Técnico en camino"
}
```

Estados: `pendiente` \| `en_proceso` \| `resuelto` \| `cerrado` \| `cancelado`.

### 5.6 Tareas (tablero)

| Método | Ruta | Permiso |
|--------|------|---------|
| GET | `/tareas` | `tareas.ver` |
| POST | `/tareas` | `tareas.crear` |
| PUT | `/tareas/{id}` | `tareas.crear` |
| POST | `/tareas/{id}/move` | `tareas.crear` |

**Filtros:** `estado`, `asignado_id`, `mias=1` (solo asignadas al usuario logueado).

**Estados:** `pendiente` \| `en_progreso` \| `completado`  
**Prioridades:** `baja` \| `media` \| `alta`

**Mover en kanban**

```json
{
  "estado": "en_progreso",
  "orden": 0
}
```

---

## 6. Flujos recomendados en la app

### App cliente (abonado)

1. Login con documento / documento (`tipo: cliente`)
2. Home → `GET /portal/resumen`
3. Facturas → `GET /portal/facturas?solo_pendientes=1`
4. Pagos → `GET /portal/cobros`
5. Soporte → asuntos + `POST /portal/tickets`
6. Logout al cerrar sesión

### App personal (campo / caja)

1. Login con email / password (`tipo: staff`)
2. Guardar `permisos` y ocultar menús sin permiso
3. Buscar cliente → `GET /clientes/buscar?q=`
4. Detalle → `GET /clientes/{id}`
5. Cobrar → facturas pendientes + `POST /cobros`
6. Tickets / tareas según rol

---

## 7. Checklist de integración

- [ ] Usar HTTPS en producción
- [ ] Guardar token de forma segura
- [ ] Enviar `Accept: application/json` siempre
- [ ] Enviar `Authorization: Bearer {token}` en **todas** las rutas excepto login
- [ ] Base URL correcta: `{host}/api/v1` (no omitir `/api/v1`)
- [ ] Manejar 401 → volver a pantalla de login
- [ ] Manejar 403 → mensaje “sin permiso”
- [ ] Separar UI cliente vs staff según `user.tipo`
- [ ] No mezclar rutas `/portal/*` con rutas de staff
- [ ] Timeout de red razonable (15–30 s)
- [ ] Documentos: aceptar entrada con puntos; enviar dígitos o texto libre (el backend normaliza)

### Error frecuente: Login OK pero luego 401 en `/dashboard/stats`

1. **URL incorrecta:** debe ser `GET /api/v1/dashboard/stats`, no `/dashboard/stats` (esa ruta es de la web y exige sesión/cookie).
2. **Header Authorization:**  
   `Authorization: Bearer 12|xxxxxxxx...`  
   Si no se envía, o Apache no lo reenvía, Sanctum responde 401.
3. **Permiso:** si el token es válido pero el usuario no tiene `dashboard.ver` / `inicio.ver`, la API responde **403** (no 401).

Ejemplo correcto:

```http
GET /api/v1/dashboard/stats HTTP/1.1
Host: infinityisppro.net
Accept: application/json
Authorization: Bearer 8|xxxxxxxxxxxxxxxx
```

---

## 7b. Onboarding: solicitudes de acceso

### Crear solicitud (público, sin token)

`POST /api/v1/portal/solicitud-alta`

```json
{
  "cedula": "1234567",
  "nombre": "Juan Perez",
  "whatsapp": "0981123456",
  "frente": "data:image/jpeg;base64,...",
  "direccion": "Mcal Lopez y San Martin",
  "latitud": -25.282197,
  "longitud": -57.635100
}
```

Respuesta: `{ "success": true, "data": { "id": 1, "estado": "pendiente" } }`

Al crear: notificación database a staff + FCM al tópico `staff` si hay cuenta de servicio FCM (HTTP v1) en `.env`.

El backend envía a FCM **HTTP v1** un payload con bloque **`notification`** (obligatorio para que Android suene con pantalla bloqueada) + `data` + `android.priority: HIGH` + `sound: default`. Sin JSON de cuenta de servicio el push se omite. Prueba: `php artisan fcm:probar-staff`.

### Staff — listar / detalle / aprobar / auditoría / push

| Método | Ruta | Permiso |
|--------|------|---------|
| GET | `/staff/solicitudes?status=pendientes\|aprobado\|historial` | `clientes.ver` |
| GET | `/staff/solicitudes/{id}` | `clientes.ver` |
| POST | `/staff/solicitudes/{id}/aprobar` | `clientes.editar` |
| POST | `/staff/solicitudes/{id}/rechazar` | `clientes.editar` |
| GET | `/staff/clientes/buscar?q=` (mín. 3 chars) | `clientes.ver` |
| POST | `/staff/save-push-token` | staff autenticado |
| GET | `/staff/auditoria` | Administrador |
| POST | `/staff/ubicacion` | staff autenticado (GPS flota) |
| GET | `/staff/ubicaciones` | admin / gerente |
| GET | `/staff/ubicaciones/stream` | admin / gerente (SSE) |
| GET | `/staff/visitas` | `tickets.ver` |
| GET | `/staff/visitas/{id}` | `tickets.ver` |

Documentación detallada de flota: [API_STAFF_FLOTA.md](./API_STAFF_FLOTA.md).

`status` aliases: `pendientes` (default) → `pendiente`; `aprobado` → `aprobada`; `historial` → todas.

**Detalle (`GET …/solicitudes/{id}`)** incluye pre-aprobación:

```json
{
  "coincide_bd": true,
  "cliente_actual": { "id": 1504, "telefono": "0981…", "direccion": "…", "url_ubicacion": "…" },
  "solicitud_propuesta": { "telefono": "0972…", "direccion": "…", "latitud": -25.2, "longitud": -57.6 },
  "requiere_confirmacion_actualizacion": true,
  "cambios_sugeridos": { "telefono": true, "ubicacion": false }
}
```

**Aprobar** — body:

```json
{
  "cliente_id_vinculacion": 1504,
  "documento_corregido": "1234568",
  "nombre_corregido": "Juan Carlos",
  "actualizar_telefono": true,
  "actualizar_ubicacion": false
}
```

- Por defecto **no** actualiza celular/ubicación del cliente existente (hay que confirmar en app).
- Cliente nuevo: sí usa datos de la solicitud.
- Genera `PLUS####`, guarda `fecha_otorgamiento` / `aprobado_por`.
- Envía WhatsApp al número de la **solicitud** con la clave.

**Rechazar** — body opcional `{ "motivo": "…" }` → WhatsApp al número de la solicitud.

**Push token staff:** `{ "push_token": "…", "device_type": "android" }`

```env
WHATSAPP_EVENT_ACCESO_APROBADO=true
WHATSAPP_EVENT_ACCESO_RECHAZADO=true
```

```bash
php artisan portal:avisar-acceso-aprobado {solicitud_id} --clave=PLUS5685
```

### Login cliente (telemetría)

```json
{
  "usuario": "1234567",
  "password": "PLUS5685",
  "tipo": "cliente",
  "device_name": "Android App",
  "app_version": "1.0.0"
}
```

Tras login OK se actualizan en `clientes`: `ultimo_ingreso`, `dispositivo`, `app_version`, y si `app_activa` era false → `true` + `fecha_activacion_app`.

Compatibilidad: si aún no se aprobó con PLUS, sigue aceptando documento como contraseña (legacy).

Opcional en el mismo login (recomendado):

```json
{
  "usuario": "1234567",
  "password": "PLUS5685",
  "tipo": "cliente",
  "device_name": "Android App",
  "app_version": "1.0.0",
  "push_token": "d1KDHH_dSN…",
  "device_type": "android"
}
```

### Push FCM — App cliente

| Método | Ruta | Auth |
|--------|------|------|
| POST | `/portal/save-push-token` | Bearer cliente |
| POST | `/logout` | limpia `push_token` del usuario |

Body:

```json
{
  "push_token": "TOKEN_FCM_DEL_DISPOSITIVO",
  "device_type": "android",
  "platform": "android",
  "cliente_id": 140,
  "usuario_id": 512
}
```

Alias: `token` = `push_token`. `platform` = `device_type` si este no viene.

El token se guarda en el usuario de la sesión (`users.push_token`) **indexado por `users.cliente_id`**. El panel Avisos push → Seleccionados busca `WHERE cliente_id IN (…)`. `cliente_id` / `usuario_id` del body son informativos: no cambian de cuenta; si no coinciden con la sesión se loguea y se conserva el de Infinity.

Respuesta: `{ "success": true, "data": { "usuario_id": …, "cliente_id": …, "device_type": "android" } }`

---

## ORDEN PARA PROGRAMADOR — Adaptar APK cliente (FCM)

**Objetivo:** que la app del **cliente** registre su token FCM en Infinity y pueda recibir avisos (factura, ticket, etc.) aunque la pantalla esté bloqueada.

### 0. Firebase (crítico)

Hoy el backend envía con el proyecto **`isp-staff-panel`** (mismo que la app staff).

- Si la APK **cliente** usa **el mismo** `google-services.json` / mismo `project_id` → OK, no hace falta nada más en Firebase del servidor.
- Si la APK cliente es **otro proyecto Firebase** → avisar a backend: hay que cargar su `*-firebase-adminsdk-*.json` (no alcanza con `google-services.json`). Los tokens de un proyecto **no** llegan con la cuenta de servicio de otro.

### 1. Dependencias Android

- Firebase Messaging (FCM) ya integrado.
- Pedir permiso de notificaciones (Android 13+): `POST_NOTIFICATIONS`.
- Crear canal de notificación id **`interplus_avisos_v2`** (prioridad alta, sonido default). Nombre visible: **Avisos Interplus**.  
  Debe coincidir con `FCM_CLIENT_ANDROID_CHANNEL_ID` del backend (default `interplus_avisos_v2`). **No usar** el canal viejo `interplus_avisos` (en muchos equipos quedó mudo).

### 2. Cuándo registrar el token

1. Tras **login exitoso** (tiene Bearer token).
2. Cada vez que FCM renueva el token (`onNewToken`).
3. Al volver a primer plano si el token cambió.

**No** suscribirse al topic `staff` (eso es solo app staff). El cliente recibe push **por token individual** guardado en su usuario portal.

### 3. API a llamar

**Opción A (recomendada):** incluir en `POST /login`:

```json
"push_token": "<fcmToken>",
"device_type": "android"
```

**Opción B:** después del login:

```http
POST /api/v1/portal/save-push-token
Authorization: Bearer {token}
Content-Type: application/json

{
  "push_token": "<fcmToken>",
  "device_type": "android",
  "platform": "android",
  "cliente_id": 140,
  "usuario_id": 512
}
```

### 4. Logout

```http
POST /api/v1/logout
Authorization: Bearer {token}
```

El backend **borra** el `push_token` del usuario. En la app: borrar token local / desregistrar FCM si corresponde.

### 5. Payload que llega al dispositivo

El backend manda bloque `notification` + `data` (Android suena en background). Ejemplo de `data`:

| Key | Ejemplo | Uso |
|-----|---------|-----|
| `tipo` | `facturas`, `soporte`, `aviso`, `pago`, `premios` | Tono visual en la campana |
| `title` / `titulo` | texto | eco del título |
| `body` / `mensaje` | texto | eco del cuerpo |

Manejar tap de la notificación: leer `tipo` (+ ids futuros) y abrir la pantalla correcta.

### 6. Checklist de prueba

- [ ] Login cliente + token guardado (200 en save-push-token o login con push_token)
- [ ] App en background / pantalla bloqueada → llega sonido + bandeja
- [ ] Tap abre la app
- [ ] Logout → ya no llegan pushes a ese dispositivo
- [ ] Re-login en otro celular → el token nuevo pisa el anterior (1 token por usuario portal)

### 7. Prueba desde backend (cuando el token ya esté guardado)

```bash
php artisan fcm:probar-cliente {documento_o_cliente_id}
```

### 8. Fuera de esta orden (backend lo engancha después)

Eventos concretos (factura vencida, ticket respondido, corte, etc.) se activan en Infinity una vez la app registre tokens. Esta orden solo adapta el APK para **registrar / renovar / limpiar** el token y mostrar notificaciones.

---

## 7c. Verificación WhatsApp — OTP invertido

Flujo:
1. App abre `wa.me` al número corporativo con el texto: `Quiero mi código de verificación`.
2. Meta entrega el mensaje al webhook `POST /api/v1/webhooks/whatsapp`.
3. Backend genera un PIN de 4 dígitos, lo guarda en caché (~15 min) asociado al `from`, y responde:
   `¡Hola! Tu código de verificación para la aplicación es: 5599`
4. App llama `POST /api/v1/portal/solicitud-alta` con `whatsapp` + `codigo_otp`.
5. Si el OTP es válido → solicitud en `pendiente` con `telefono_verificado=true` y se notifica al staff.
6. Si es inválido/expirado → `400` con mensaje `Código de verificación inválido o expirado`.

**Body de alta (campos nuevos):**

```json
{
  "cedula": "1234567",
  "nombre": "Juan Pérez",
  "whatsapp": "0981123456",
  "codigo_otp": "5599",
  "frente": "data:image/jpeg;base64,..."
}
```

**Respuesta OK:**

```json
{
  "success": true,
  "message": "Solicitud recibida correctamente.",
  "data": {
    "id": 1,
    "estado": "pendiente",
    "telefono_verificado": true
  }
}
```

Al aprobar/rechazar, el backend envía automáticamente el texto por Meta API (ventana 24h ya abierta).

Env (opcional):

```env
WHATSAPP_SOLICITUD_DESTINO=595971714322
WHATSAPP_REGISTRO_OTP_TTL=15
WHATSAPP_REGISTRO_OTP_TEXT="¡Hola! Tu código de verificación para la aplicación es: {codigo}"
```

---

## 8. Fuera de alcance

No disponibles aún en esta API:

- Emisión SIFEN / facturación electrónica
- Corte / reconexión MikroTik desde la app
- Pedidos, agenda, inventario, FTTH, TV
- Cambio de contraseña del cliente desde la app (self-service)
- Subida de fotos en tickets (portal)

---

## 9. Contacto backend

Cualquier duda de contrato o bug de API, coordinar con el equipo Infinity (este repositorio: rutas en `routes/api.php`, controladores en `app/Http/Controllers/Api/V1/`).

Para regenerar usuarios portal de clientes existentes:

```bash
php artisan clientes:sync-portal-users
php artisan clientes:sync-portal-users --reset-passwords
```

FCM HTTP v1 (necesario para push; la API legacy ya no funciona):

1. Firebase Console → Project settings → Service accounts → **Generate new private key**
2. Guardar el JSON fuera del web root, ej. `storage/app/firebase-service-account.json`
3. En `.env`:

```env
FCM_SERVICE_ACCOUNT_PATH=storage/app/firebase-service-account.json
FCM_PROJECT_ID=tu-project-id
FCM_STAFF_TOPIC=staff
FCM_ANDROID_CHANNEL_ID=staff
FCM_CLIENT_ANDROID_CHANNEL_ID=interplus_avisos_v2
```

Luego: `php artisan config:clear`, `php artisan fcm:probar-staff` y `php artisan fcm:probar-cliente {documento}`.
