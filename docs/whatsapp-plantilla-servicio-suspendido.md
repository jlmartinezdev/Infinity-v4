# Plantilla WhatsApp — Servicio suspendido por falta de pago

Avisa al cliente cuando su servicio pasa a **suspendido** por mora (corte automático, promesa vencida o suspensión masiva desde facturas).

## Crear en Meta

1. WhatsApp Manager → **Plantillas de mensajes** → Crear
2. **Nombre:** `servicio_suspendido_falta_pago` (o el que configures en `.env`)
3. **Idioma:** el que quede APPROVED (ej. `es`, `es_PY`)
4. **Categoría:** Utilidad / Utility
5. **Cuerpo** (4 variables):

```
Hola {{1}}, te informamos que tu servicio de internet fue suspendido por falta de pago.
Factura: #{{2}}
Saldo pendiente: Gs. {{3}}
Vencimiento: {{4}}
Regularizá tu pago para reactivar el servicio. Ante dudas, respondé este mensaje o contactanos.
```

### Variables del cuerpo (mapeo Infinity)

| Var | Contenido |
|-----|-----------|
| `{{1}}` | Nombre del cliente |
| `{{2}}` | ID de factura interna (o `pendiente`) |
| `{{3}}` | Saldo pendiente formateado |
| `{{4}}` | Fecha de vencimiento (`d/m/Y`) |

6. Enviar a revisión y esperar estado **APPROVED**.

## Configurar Infinity (`.env`)

```env
WHATSAPP_ENABLED=true
WHATSAPP_EVENT_SERVICIO_SUSPENDIDO=true
WHATSAPP_TEMPLATE_SERVICIO_SUSPENDIDO=servicio_suspendido_falta_pago
# WHATSAPP_TEMPLATE_SERVICIO_SUSPENDIDO_LANG=es
```

Luego: `php artisan config:clear`

## Cuándo se envía

- `servicios:corte-automatico` (día/hora de corte)
- `promesas:procesar-vencidas`
- Botón de suspensión por falta de pago en facturas

Un aviso por **cliente** (aunque tenga varios servicios suspendidos en el mismo lote).

## Fallback

Si la plantilla falla o está vacía, se intenta texto libre (solo útil dentro de la ventana de 24 h).
