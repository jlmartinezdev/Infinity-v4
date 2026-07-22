# Infinity ISP — API Móvil v1

Documento para el equipo de desarrollo de la app móvil.

**Versión:** 1.0 (Fase 1)  
**Fecha:** 2026-07-15  
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

### 4.4 Tickets de soporte

**Listar** — `GET /portal/tickets` — permiso `portal.tickets.ver`  
**Asuntos disponibles** — `GET /portal/ticket-asuntos` — permiso `portal.tickets.ver`  
**Crear** — `POST /portal/tickets` — permiso `portal.tickets.crear`

Los permisos del cliente se gestionan de forma **global** en el panel web: **Usuarios → Clientes app**. El login (`/me`) devuelve `user.permisos` con los códigos activos; la app debe ocultar secciones sin permiso.

```json
{
  "ticket_asunto_id": 3,
  "descripcion": "Sin internet desde esta mañana",
  "prioridad": "media"
}
```

| Campo | Obligatorio | Valores |
|-------|-------------|---------|
| `ticket_asunto_id` | sí | ID de `/portal/ticket-asuntos` |
| `descripcion` | sí | texto |
| `prioridad` | no | `baja` \| `media` \| `alta` (default `media`) |

El ticket se crea con `estado: pendiente` y `reportado_desde: app`.

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

Al crear: notificación database a staff + FCM al tópico `staff` si hay `FCM_SERVER_KEY` en `.env`.

### Staff — listar / detalle / aprobar

| Método | Ruta | Permiso |
|--------|------|---------|
| GET | `/staff/solicitudes` | `clientes.ver` |
| GET | `/staff/solicitudes/{id}` | `clientes.ver` |
| POST | `/staff/solicitudes/{id}/aprobar` | `clientes.editar` |
| GET | `/staff/auditoria` | Administrador |

Detalle incluye `coincide_bd` (si la cédula ya existe en `clientes`) y `frente` (URL pública de la foto).

Al **aprobar**:
1. Crea o actualiza el cliente
2. Genera clave `PLUS` + 4 dígitos (ej. `PLUS5685`) y la asigna al usuario portal
3. Devuelve `{ "clave": "PLUS5685" }` (mostrarla una vez al staff)

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

FCM (opcional):

```env
FCM_SERVER_KEY=...
FCM_STAFF_TOPIC=staff
```
