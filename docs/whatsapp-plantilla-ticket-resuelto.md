# Plantilla WhatsApp — Ticket resuelto

Avisa al cliente cuando su ticket pasa a estado **resuelto**. No menciona contraseñas ni datos sensibles.

## Crear en Meta

1. WhatsApp Manager → **Plantillas de mensajes** → Crear
2. **Nombre:** `ticket_resuelto` (o el que configures en `.env`)
3. **Idioma:** el que quede APPROVED (ej. `es`, `es_PY`, `es_AR`)
4. **Categoría:** Utilidad / Utility
5. **Cuerpo** (3 variables):

```
Hola {{1}}, tu solicitud #{{2}} ({{3}}) fue resuelta.
Si necesitás ayuda, respondé este mensaje o contactanos.
```

### Variables del cuerpo (mapeo Infinity)

| Var | Contenido |
|-----|-----------|
| `{{1}}` | Nombre del cliente |
| `{{2}}` | Número de ticket (`tickets.id`) |
| `{{3}}` | Nombre del asunto |

6. Enviar a revisión y esperar estado **APPROVED**.

## Configurar Infinity (`.env`)

```env
WHATSAPP_ENABLED=true
WHATSAPP_EVENT_TICKET_RESUELTO=true
WHATSAPP_TEMPLATE_TICKET_RESUELTO=ticket_resuelto
# WHATSAPP_TEMPLATE_TICKET_RESUELTO_LANG=es
```

Luego: `php artisan config:clear`

## Cuándo se envía

Automático vía `TicketObserver` cuando:

- el ticket cambia a `estado = resuelto`
- el cliente tiene teléfono

Aplica a **todos** los tickets con cliente (no solo asuntos especiales).

## Fallback

Si la plantilla falla o está vacía, se intenta texto libre (solo útil dentro de la ventana de 24 h).
