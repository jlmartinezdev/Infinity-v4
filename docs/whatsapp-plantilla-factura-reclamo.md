# Plantilla WhatsApp — Reclamo de mora (Pendiente de pago)

Para reclamar facturas **vencidas** desde `/factura-internas/pendientes` hace falta una plantilla **APPROVED** en Meta. Sin plantilla, Infinity intenta texto libre (solo sirve si hay ventana de 24 h).

## Crear en Meta

1. WhatsApp Manager → **Plantillas de mensajes** → Crear
2. **Nombre:** `factura_reclamo_mora` (o el que configures en `.env`)
3. **Idioma:** el que quede APPROVED (ej. `es`, `es_PY`, `es_AR`)
4. **Categoría:** Utilidad / Utility
5. **Cuerpo** (4 variables):

```
Hola {{1}}, te recordamos que tenés factura(s) vencida(s) con Interplus.
Cantidad de facturas: {{2}}
Vencimiento: {{3}}
Saldo pendiente: Gs. {{4}}
Adjuntamos el resumen de tu deuda. Regularizá tu pago para evitar la suspensión del servicio.
```

6. **Botón** → tipo **Visitar sitio web** / URL **dinámica**:
   - Texto del botón: `Descargar resumen`
   - URL: `https://infinityisppro.net/pendientes-resumen/{{1}}`
     ⚠️ El `{{1}}` debe ser la **variable dinámica** del botón (Meta la agrega sola).
     **No** escribas el texto `{{1}}` a mano en la URL base.

### Variables del cuerpo (mapeo Infinity)

| Var | Contenido |
|-----|-----------|
| `{{1}}` | Nombre del cliente |
| `{{2}}` | Cantidad de facturas vencidas (`5`) |
| `{{3}}` | Fecha de vencimiento más antigua + ` (vencido)` |
| `{{4}}` | Saldo vencido formateado |

### Variable del botón URL

Infinity envía el sufijo: `{cliente_id}/{token}`  
Ejemplo: `https://infinityisppro.net/pendientes-resumen/41254/a1b2c3d4…`

La ruta es **pública** (sin login). El token es un HMAC de 40 hex; sin el token correcto responde 403.

7. Enviar a revisión y esperar estado **APPROVED**.

## Configurar Infinity (`.env`)

```env
WHATSAPP_ENABLED=true
WHATSAPP_EVENT_FACTURA_RECLAMO=true
WHATSAPP_TEMPLATE_FACTURA_RECLAMO=factura_reclamo_mora
# WHATSAPP_TEMPLATE_FACTURA_RECLAMO_LANG=es
```

Luego: `php artisan config:clear`

## Cuándo se envía

Desde **Pendiente de pago** → Acciones → ícono WhatsApp → **Enviar reclamo**, si el cliente tiene al menos una factura vencida.

Un reclamo exitoso por cliente por día (se puede forzar el reenvío desde el modal).

El aviso de factura vigente (instalación / ciclo) sigue usando `factura_generada_cliente` (`WHATSAPP_EVENT_FACTURA`).
