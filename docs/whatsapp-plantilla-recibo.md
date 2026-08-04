# Plantilla WhatsApp — Recibo de pago

Para enviar recibos fuera de la ventana de 24 h hace falta una plantilla **APPROVED** en Meta Business Manager.

## Crear en Meta

1. WhatsApp Manager → **Plantillas de mensajes** → Crear
2. **Nombre:** `recibo_pago` (o el que configures en `.env`)
3. **Idioma:** el que esté APPROVED en Meta (Interplus usa `es_AR` para `recibo_pago`)
4. **Categoría:** Utilidad / Utility
5. **Encabezado (texto fijo):** `Pago Recibido`
6. **Cuerpo** (6 variables):

```
Hola {{1}}, desde interplus confirmamos la recepción de tu pago.
Recibo: {{2}}
Monto: Gs. {{3}}
Fecha: {{4}}
Forma de pago: {{5}}
Detalle: {{6}}
¡Gracias por su pago!
```

7. **Botón** → tipo **Visitar sitio web** / URL **dinámica**:
   - Texto del botón: `Descargar Recibo`
   - URL: `https://infinityisppro.net/recibo/{{1}}`  
     ⚠️ El `{{1}}` debe ser la **variable dinámica** del botón (Meta la agrega sola).
     **No** escribas el texto `{{1}}` a mano en la URL base: si lo hacés, el link queda
     `.../recibo/{{1}}3857/token` y falla.
   - Ejemplo correcto al enviar: `https://infinityisppro.net/recibo/3857/abc…token…`

### Variables del cuerpo (mapeo Infinity)

| Var | Contenido |
|-----|-----------|
| `{{1}}` | Nombre del cliente (mayúsculas) |
| `{{2}}` | Número de recibo |
| `{{3}}` | Monto formateado (`100.000`) |
| `{{4}}` | Fecha (`Y-m-d`) |
| `{{5}}` | Forma de pago |
| `{{6}}` | Detalle / periodo de factura |

### Variable del botón URL

Infinity envía el sufijo: `{id_cobro}/{token}`  
Ejemplo de URL final: `https://infinityisppro.net/recibo/41254/a1b2c3d4…`

La ruta es **pública** (sin login) y el token es un HMAC de 40 hex; sin el token correcto responde 403.

8. Enviar a revisión y esperar estado **APPROVED**.

## Configurar Infinity (`.env`)

```env
WHATSAPP_ENABLED=true
WHATSAPP_TEMPLATE_RECIBO=recibo_pago
WHATSAPP_TEMPLATE_RECIBO_LANG=es_AR
WHATSAPP_DEFAULT_TEMPLATE_LANGUAGE=es
APP_URL=https://infinityisppro.net

# Envío automático al registrar un cobro (opcional)
WHATSAPP_EVENT_RECIBO=false
```

Luego: `php artisan config:clear`

## Ruta pública

```
GET /recibo/{cobro}/{token}
```

Nombre de ruta Laravel: `recibo.publico`

## Uso

- **Manual:** en el recibo (`/cobros/{id}`) → **Enviar por WhatsApp**
- **Automático:** `WHATSAPP_EVENT_RECIBO=true`
- Si la plantilla falla, se reintenta con texto libre (incluye el link de descarga; solo útil con ventana 24 h)

Revisá envíos en **WhatsApp → Mensajes** del panel.
