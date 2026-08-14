# Plantilla WhatsApp — Failover de salida ISP

Avisa al **staff** cuando la salida de **ISP 1** deja de responder al ping (`1.1.1.1` u otro host) y Infinity activa **ISP 2**, o cuando ISP 1 se recupera.

## Crear en Meta

1. WhatsApp Manager → **Plantillas de mensajes** → Crear
2. **Nombre:** `isp_failover_salida` (o el que configures en `.env`)
3. **Idioma:** el que quede APPROVED (ej. `es`, `es_PY`)
4. **Categoría:** Utilidad / Utility
5. **Cuerpo** (4 variables):

```
Alerta de salida ISP: {{1}}
Ping: {{2}}
Router: {{3}}
Hora: {{4}}
Revisá Failover ISP en Infinity.
```

### Variables del cuerpo (mapeo Infinity)

| Var | Contenido |
|-----|-----------|
| `{{1}}` | Texto del evento (caída o recuperación) |
| `{{2}}` | Host de ping (ej. `1.1.1.1`) |
| `{{3}}` | Nombre del router de borde |
| `{{4}}` | Fecha/hora (`d/m/Y H:i:s`) |

Ejemplos de `{{1}}`:

- `ISP 1 sin internet (ping 1.1.1.1). Failover activo hacia ISP 2.`
- `ISP 1 recuperado (ping 1.1.1.1). Volvió a ser la salida principal. ISP 2 queda de respaldo.`

6. Enviar a revisión y esperar estado **APPROVED**.

## Configurar Infinity (`.env`)

```env
WHATSAPP_ENABLED=true
WHATSAPP_EVENT_ISP_FAILOVER=true
WHATSAPP_TEMPLATE_ISP_FAILOVER=isp_failover_salida
# WHATSAPP_TEMPLATE_ISP_FAILOVER_LANG=es
```

Luego: `php artisan config:clear`

## Quién recibe el aviso

Panel: **Sistema → Failover ISP**

- Activar chequeo automático
- Elegir router de borde, **IP WAN de ISP 1 (src-address)** y comentarios de rutas `ISP1` / `ISP2`. En RouterOS 7.23 el ping no admite `interface`.
- Marcar usuarios staff (con teléfono WhatsApp)
- Opcional: **Probar WhatsApp**

## Cuándo se envía

- Comando programado `mikrotik:check-isp-salida` (cada minuto): ping desde el MikroTik hacia `1.1.1.1` con **src-address** de ISP 1 (RouterOS 7.23 no admite ping por `interface`)
- Webhook Netwatch del router: `POST /api/v1/webhooks/mikrotik/isp-failover` (`evento=down` o `up`)

Un aviso por caída y uno al recuperar. No se reenvía mientras siga en el mismo estado.

## Fallback

Si la plantilla falla o está vacía, se intenta texto libre (solo útil dentro de la ventana de 24 h).
