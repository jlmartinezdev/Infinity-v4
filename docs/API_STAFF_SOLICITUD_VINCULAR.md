# Staff — Corregir y vincular solicitud de acceso

Contrato para la app **ISP Staff** cuando el documento de la solicitud no matchea
un cliente (OCR / typo) o hay que elegir a mano a quién vincular.

Base: `https://infinityisppro.net/api/v1/`  
Auth: `Authorization: Bearer <token staff>`  
Permiso: `solicitudes-acceso.editar` (buscar clientes en listado de solicitudes: `solicitudes-acceso.ver`)

## Flujo

1. Detalle: `GET /staff/solicitudes/{id}`
2. Corregir datos (opcional): `PATCH /staff/solicitudes/{id}`
3. Buscar cliente: `GET /staff/clientes/buscar?q=` (mín. 3 caracteres)
4. Aprobar con vínculo: `POST /staff/solicitudes/{id}/aprobar`

## 1. Corregir datos

```http
PATCH /api/v1/staff/solicitudes/27
Content-Type: application/json
```

```json
{
  "nombre": "EDGAR DIAZ TABOADA",
  "documento": "7674896",
  "whatsapp": "0983106871",
  "direccion": "Guazu Cai"
}
```

Aliases: `cedula` ≡ `documento`, `telefono` ≡ `whatsapp`.

## 2. Buscar cliente

```http
GET /api/v1/staff/clientes/buscar?q=edgar
```

```json
{
  "success": true,
  "data": [
    { "id": 1504, "nombre": "Edgar Díaz", "documento": "7674896" }
  ]
}
```

## 3. Aprobar (con vínculo manual)

```http
POST /api/v1/staff/solicitudes/27/aprobar
```

```json
{
  "cliente_id_vinculacion": 1504,
  "documento_corregido": "7674896",
  "nombre_corregido": "EDGAR DIAZ TABOADA",
  "whatsapp_corregido": "0983106871",
  "direccion_corregida": "Guazu Cai",
  "actualizar_telefono": true,
  "actualizar_ubicacion": false
}
```

| Campo | Efecto |
|-------|--------|
| `cliente_id_vinculacion` | Obliga a ese cliente (ignora match por cédula) |
| `*_corregido` | Pisan datos de la solicitud antes de vincular |
| `actualizar_telefono` / `ubicacion` | Solo si ya hay cliente; default `false` |

Sin `cliente_id_vinculacion`: match por documento; si no hay → crea cliente nuevo.

## Web (panel)

En `/solicitudes-acceso/{id}` (pendiente):

- Formulario editable de nombre / documento / WhatsApp / dirección → **Guardar correcciones**
- Buscador **Vincular manualmente a un cliente**
- **Aprobar y generar clave** envía el vínculo elegido

## Nota para el desarrollador de la app

El backend **ya exponía** `cliente_id_vinculacion` + `documento_corregido` + `nombre_corregido`
en el POST aprobar, y `GET /staff/clientes/buscar`. Si la app “no permite vincular”,
falta UI en Staff que use esos campos — no un endpoint nuevo de vinculación aparte.
Se agregó además `PATCH /staff/solicitudes/{id}` y correcciones de WhatsApp/dirección.
