# API Staff — Pedidos de instalación (contrato de lectura)

Base: `/api/v1` · Auth: `Authorization: Bearer <token>` · Login `tipo: "staff"`  
Fecha: **2026-07-31** · Misma data que web `/pedidos` (sin cambiar flujo web)

## Respuesta corta (plantilla)

```text
1) GET lista:   GET /api/v1/staff/pedidos-instalacion
2) GET detalle: GET /api/v1/staff/pedidos-instalacion/{id}
3) Query filtros:
   - estado_id=todos|1|2|3   (combo web Estado; omitir o "todos" = todos)
   - Alias: estado=3 (numérico) = estado_id=3
   - estado=en_camino|en_proceso|…  (estado de campo app; no confundir)
   - plan_id, plan, desde, hasta, asignado_a, zona
4) Cola instalación (técnico): enviar estado_id=3 (backend NO lo fuerza)
5) Permisos: middleware pedidos.ver; flags en GET /staff/me y user.permisos_flags
```

Cola de campo típica:

```http
GET /api/v1/staff/pedidos-instalacion?estado_id=3
Authorization: Bearer …
```

## Paths

| Uso | Método | Path | Permiso |
|-----|--------|------|---------|
| Lista | `GET` | `/api/v1/staff/pedidos-instalacion` | `pedidos.ver` |
| Detalle | `GET` | `/api/v1/staff/pedidos-instalacion/{id}` | `pedidos.ver` |
| Permisos | `GET` | `/api/v1/staff/me` | staff |
| Aprobar estado | `POST` | `/api/v1/staff/pedidos-instalacion/{id}/aprobar-estado` | `pedidos.editar` |
| Descartar estado | `POST` | `/api/v1/staff/pedidos-instalacion/{id}/descartar-estado` | `pedidos.editar` |
| Reabrir estado | `POST` | `/api/v1/staff/pedidos-instalacion/{id}/reabrir-estado` | `pedidos.editar` |
| Catálogos aprobar | `GET` | `/api/v1/staff/pedidos-instalacion/opciones-aprobacion` | `pedidos.editar` |
| Opciones nodo | `GET` | `/api/v1/staff/pedidos-instalacion/nodos/{nodoId}/opciones` | `pedidos.editar` |
| Finalizar / PPPoE / crear | ver abajo | | |

También: login / `GET /me` → `user.permisos_flags`.

## Acciones historial (Aprobar / Descartar / Reabrir)

Misma lógica que web `PedidoController` (`aprobarEstado` / `descartarEstado` / `reabrirEstado`).  
Respuesta: `{ success, message, data: <pedido completo> }` (mismo shape que GET detalle).

### Aprobar

```http
POST /api/v1/staff/pedidos-instalacion/678/aprobar-estado
Content-Type: application/json

{ "estado_id": 3, "nodo_id": 3, "tecnologia_id": 1, "plan_id": 2, "pool_id": null, "notas": null }
```

Para estados 1/2 la web pide nodo/tecnología/plan según parámetro del estado.  
Si faltan datos: `{ "success": false, "message": "…" }` (422/400).

### Descartar

```http
POST /api/v1/staff/pedidos-instalacion/678/descartar-estado

{ "estado_id": 3, "motivo": "Sin cobertura" }
```

`motivo` es alias de `notas` (web).

### Reabrir

```http
POST /api/v1/staff/pedidos-instalacion/664/reabrir-estado

{ "estado_id": 3 }
```

Solo si el ítem está en `A` o `D` → vuelve a `P`.

### Flags por ítem de historial

```json
{
  "estado_id": 3,
  "resolucion": "pendiente",
  "puede_aprobar": true,
  "puede_descartar": true,
  "puede_reabrir": false
}
```

- `P` → aprobar + descartar  
- `A` / `D` → reabrir  

### Catálogos

```http
GET /api/v1/staff/pedidos-instalacion/opciones-aprobacion
GET /api/v1/staff/pedidos-instalacion/opciones-aprobacion?nodo_id=3&estado_id=1
GET /api/v1/staff/pedidos-instalacion/nodos/3/opciones
```

Respuesta catálogo:

```json
{
  "success": true,
  "data": {
    "nodos": [{ "nodo_id": 3, "descripcion": "…", "ciudad": "…" }],
    "tecnologias": [{ "tecnologia_id": 1, "descripcion": "GPON" }],
    "planes": [{ "plan_id": 2, "nombre": "PLAN BASICO FTTH 100 Mbps", "prioridad": 1 }],
    "nodo": { "tecnologias": [], "pools": [] }
  }
}
```

(`nodo` solo si se pasó `nodo_id` — misma info que web `opcionesNodoAprobacion`.)

## Filtro de estados (combo web)

| `estado_id` | Label |
|------------:|-------|
| `todos` / omitido | Todos (abiertos, sin descartados) |
| `1` | ANALISIS FACTIBILIDAD |
| `2` | CONFIMAR DE PLAN |
| `3` | EN ESPERA PARA INSTALAR |

Criterio de “estado actual” = **igual que PedidosList.vue**:  
detalle con resolución `P` de mayor `estado_id`; si no hay `P`, el de mayor `estado_id`.

**Default técnico:** la app debe enviar `estado_id=3` para la cola de instalación. El backend no lo impone (admin puede pedir `todos`).

### Otros query

| Param | Uso |
|-------|-----|
| `estado` | Clave de campo app (`en_camino`, `en_proceso`, …) **o** número alias de `estado_id` |
| `desde` / `hasta` | Fecha pedido (`Y-m-d`) |
| `plan_id` / `plan` | Plan |
| `asignado_a` | `usuario_id` del técnico |
| `zona` | Ciudad/nodo (filtro post-query) |

## Visibilidad

| Quién | Qué ve |
|-------|--------|
| Admin / gerente / cajero | Todos (según permiso `pedidos.ver`) |
| Técnico | Agenda propia **o** sin agenda (cola libre) |

## Permisos

Middleware rutas lectura: `pedidos.ver` (acepta también `clientes-pedidos.ver` vía catálogo).

`GET /staff/me` → `data.permisos`:

| Flag | Origen web |
|------|------------|
| `pedidos_instalacion.ver` | `pedidos.ver` / `clientes-pedidos.ver` |
| `pedidos_instalacion.generar` | `pedidos.crear` |
| `pedidos_instalacion.editar` | `pedidos.editar` |
| `pedidos_instalacion.finalizar` | `pedidos.finalizar` (= editar) |
| `pedidos_instalacion.pppoe_*` | editar |
| `pedidos.ver` / `clientes-pedidos.ver` | aliases |

Login: mismos booleanos en `user.permisos_flags` (la lista `user.permisos` no se rompe).

## Campos por ítem

| App necesita | Campo JSON |
|-------------|------------|
| id | `id` / `pedido_id` |
| cliente | `cliente`, `cliente_id` |
| documento | `documento` / `cedula` |
| teléfono | `telefono` |
| dirección / maps | `direccion`, `ubicacion`, `maps_gps`, `lat`, `lng` |
| zona | `zona` |
| Selección plan (cabecera web) | `plan`, `plan_id`, `seleccion.plan` — `null` si aún no se confirmó |
| Plan al crear pedido | `plan_solicitado`, `plan_solicitado_id` |
| Selección completa | `seleccion.{nodo_id,nodo,tecnologia_id,tecnologia,plan_id,plan}` |
| nodo / tecnología (alias selección) | `nodo`, `nodo_id`, `tecnologia`, `tecnologia_id` |
| estado pipeline | `estado_id`, `estado_label` / `estado_pipeline` |
| resolución actual | `resolucion` (`pendiente`/`aprobado`/`descartado`), `resolucion_label`, `resolucion_codigo` (`P`/`A`/`D`) |
| asignación | `asignado_a`, `asignado_nombre` |
| fecha | `fecha_pedido` |
| flags | `puede_ver`, `puede_editar`, `puede_finalizar`, `puede_generar_pppoe`, `puede_pppoe_ver` |
| historial | `historial[]` (detalle) |
| PPPoE | `pppoe_usuario`, `pppoe_password` (solo si permiso editar) |

`estado` / `estado_campo` = estado de **campo** app (en_camino, etc.), distinto del pipeline web.

## Ejemplo lista (real, `estado_id=3`)

```json
{
  "success": true,
  "message": "OK",
  "data": [
    {
      "id": 678,
      "pedido_id": 678,
      "cliente": "TITO ANTONIO MIRANDA ROLON",
      "cliente_id": 1507,
      "documento": "7075278",
      "cedula": "7075278",
      "telefono": "+595983278773",
      "direccion": "TATUKUA",
      "maps_gps": "-26.451928416588668, -56.10924536838859",
      "zona": "YAGUARETE CORA",
      "plan": "PLAN BASICO 10 Mbps",
      "plan_id": 1,
      "nodo_id": 3,
      "nodo": "N2 FTTH - Yaguarete Cora",
      "tecnologia_id": 1,
      "tecnologia": "GPON",
      "estado_id": 3,
      "estado_pipeline": "EN ESPERA PARA INSTALAR",
      "estado_label": "EN ESPERA PARA INSTALAR",
      "resolucion": "pendiente",
      "resolucion_label": "Pendiente",
      "resolucion_codigo": "P",
      "estado": "en_proceso",
      "lat": -26.4519284,
      "lng": -56.1092454,
      "asignado_a": null,
      "asignado_nombre": null,
      "fecha_pedido": "2026-07-30T00:00:00-03:00",
      "puede_ver": true,
      "puede_editar": true,
      "puede_finalizar": false,
      "puede_generar_pppoe": false
    }
  ]
}
```

## Ejemplo detalle (pedido 678 — EN ESPERA PARA INSTALAR)

```json
{
  "success": true,
  "message": "OK",
  "data": {
    "id": 678,
    "pedido_id": 678,
    "cliente": "TITO ANTONIO MIRANDA ROLON",
    "cliente_id": 1507,
    "documento": "7075278",
    "telefono": "+595983278773",
    "direccion": "TATUKUA",
    "maps_gps": "-26.451928416588668, -56.10924536838859",
    "zona": "YAGUARETE CORA",
    "plan": "PLAN BASICO 10 Mbps",
    "plan_id": 1,
    "nodo": "N2 FTTH - Yaguarete Cora",
    "tecnologia": "GPON",
    "estado_id": 3,
    "estado_label": "EN ESPERA PARA INSTALAR",
    "resolucion": "pendiente",
    "resolucion_label": "Pendiente",
    "lat": -26.4519284,
    "lng": -56.1092454,
    "historial": [
      {
        "estado_id": 1,
        "nombre": "ANALISIS FACTIBILIDAD",
        "resolucion": "aprobado",
        "resolucion_label": "Aprobado",
        "resolucion_codigo": "A",
        "fecha": "2026-07-30T11:01:55-03:00",
        "nodo": "N2 FTTH - Yaguarete Cora",
        "tecnologia": "GPON"
      },
      {
        "estado_id": 2,
        "nombre": "CONFIMAR DE PLAN",
        "resolucion": "aprobado",
        "resolucion_label": "Aprobado",
        "resolucion_codigo": "A",
        "fecha": "2026-07-30T16:55:37-03:00"
      },
      {
        "estado_id": 3,
        "nombre": "EN ESPERA PARA INSTALAR",
        "resolucion": "pendiente",
        "resolucion_label": "Pendiente",
        "resolucion_codigo": "P",
        "fecha": "2026-07-30T16:55:37-03:00"
      }
    ],
    "pppoe_usuario": null,
    "pppoe_password": null,
    "puede_generar_pppoe": false,
    "puede_finalizar": false,
    "puede_editar": true
  }
}
```

## Fix 2026-07-31

El 500 `Unknown column 'agenda_id' in 'ORDER BY'` estaba causado porque la tabla `agenda` usa PK `id`. Corregido.

## Plan pedido vs Selección

| Campo | Significado |
|-------|-------------|
| `plan` / `plan_id` | **Selección confirmada** (igual cabecera web). `null` si el paso CONFIRMAR DE PLAN aún está Pendiente |
| `seleccion.plan` | Igual que `plan` (objeto completo nodo/tec/plan) |
| `plan_solicitado` / `plan_solicitado_id` | Plan cargado al **crear** el pedido (puede existir aunque el paso 2 esté pendiente) |

Ejemplo pedido **632** (CONFIRMAR DE PLAN pendiente):

| Campo | Valor |
|-------|-------|
| `plan` / `seleccion.plan` | `null` (web muestra —) |
| `plan_solicitado` | PLAN BASICO 10 Mbps |

Ejemplo pedido **664** (plan ya confirmado):

| Campo | Valor |
|-------|-------|
| `plan` / `seleccion.plan` | PLAN BASICO FTTH 100 Mbps |
| `plan_solicitado` | PLAN BASICO 10 Mbps |

**App:** no mostrar `plan_solicitado` como si fuera la selección del historial. En ítems `Pendiente` del historial, `historial[].plan` también es `null`.

## Escritura adicional

- `POST /staff/pedidos-instalacion` — crear  
- `POST /staff/pedidos-instalacion/{id}/actualizar` — estado de campo (`en_camino`, notas)  
- `POST /staff/pedidos-instalacion/{id}/finalizar`  
- `POST /staff/pedidos-instalacion/{id}/pppoe/generar`
