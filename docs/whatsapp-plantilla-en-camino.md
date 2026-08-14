# Plantilla WhatsApp — Técnico en camino

Aviso saliente desde el **número oficial** de Infinity (WhatsApp Cloud API) cuando un técnico
indica que va en camino a una visita o instalación. La app staff llama
`POST /api/v1/staff/avisos/en-camino`; no abre WhatsApp en el teléfono del técnico.

## Crear en Meta

1. WhatsApp Manager → **Plantillas de mensajes** → Crear
2. **Nombre:** `staff_tecnico_en_camino_v1` (o el que configures en `.env`)
3. **Idioma:** el que quede APPROVED (ej. `es`, `es_PY`)
4. **Categoría:** Utilidad / Utility
5. **Cuerpo** (3 variables):

```
Hola {{1}}.

Le informamos que nuestro técnico {{2}} está en camino a su domicilio para realizar
{{3}}.

Este es un aviso automático de sistema Interplus. No es necesario responder este mensaje.
```

### Variables del cuerpo (mapeo Infinity)

| Var | Contenido |
|-----|-----------|
| `{{1}}` | Nombre del cliente (resuelto en backend) |
| `{{2}}` | Nombre visible del técnico autenticado |
| `{{3}}` | Tarea normalizada por backend |

**Tarea (`{{3}}`):**

- instalación → `la instalación de su servicio`
- visita con asunto → `la visita técnica por <asunto>`
- visita sin asunto → `la visita técnica programada`

6. **Botón URL (opcional):** solo si existe una URL pública de seguimiento con token temporal.
   No usar `maps.google.com/?q=<GPS técnico>` (posición estática, no es seguimiento seguro).
7. Enviar a revisión y esperar estado **APPROVED**.

## Configurar Infinity (`.env`)

```env
WHATSAPP_ENABLED=true
WHATSAPP_TOKEN=
# Alias aceptado: WHATSAPP_ACCESS_TOKEN=
WHATSAPP_PHONE_NUMBER_ID=
WHATSAPP_TEMPLATE_EN_CAMINO=staff_tecnico_en_camino_v1
# WHATSAPP_TEMPLATE_EN_CAMINO_LANG=es
# Alias: WHATSAPP_TEMPLATE_LANGUAGE=es
# WHATSAPP_EN_CAMINO_TRACKING=false
```

Luego: `php artisan config:clear`

## Endpoint

```http
POST /api/v1/staff/avisos/en-camino
Authorization: Bearer <staff-token>
Content-Type: application/json

{
  "tipo": "visita",
  "recurso_id": 123,
  "lat": -25.2867,
  "lng": -57.647
}
```

`tipo`: `visita` | `instalacion`.

La app **no** envía nombre del técnico, teléfono, cliente ni texto libre: el backend los resuelve
con el usuario autenticado y el recurso, para evitar suplantación.

### Respuesta OK

```json
{
  "success": true,
  "data": {
    "enviado": true,
    "canal": "whatsapp",
    "message_id": "wamid..."
  }
}
```

### Errores

| HTTP | Caso |
|------|------|
| 400 | Recurso sin teléfono válido |
| 403 | Técnico sin acceso / permiso |
| 404 | Recurso inexistente |
| 409 | Aviso equivalente en los últimos 5 minutos |
| 422 | Plantilla o configuración WhatsApp incompleta |
| 502 | Meta rechazó o no respondió |

## Reglas

- Marca `en_camino` en la visita/pedido **antes** del envío; si Meta falla, el estado se conserva.
- Idempotencia 5 minutos por usuario + tipo + recurso.
- Auditoría en tabla `auditoria` (`tabla=avisos_en_camino`) con destinatario enmascarado.
- Base legal registrada: interés legítimo de prestación del servicio; Meta aplica STOP/opt-out.
- Credenciales solo en backend (nunca en la app).
