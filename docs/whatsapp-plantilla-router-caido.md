# Plantilla WhatsApp — Router caído (sin ping)

Avisa al **staff** cuando un router de gestión no responde al ping durante N consultas seguidas (configurable; default 3).

## Crear en Meta

1. WhatsApp Manager → **Plantillas de mensajes** → Crear
2. **Nombre:** `router_caido_ping` (o el que configures en `.env`)
3. **Idioma:** el que quede APPROVED (ej. `es`, `es_PY`)
4. **Categoría:** Utilidad / Utility
5. **Cuerpo** (4 variables):

```
Alerta de red: el router {{1}} (IP {{2}}) no responde al ping.
Fallos seguidos: {{3}}
Último chequeo: {{4}}
Revisá Monitoreo de red en Infinity.
```

### Variables del cuerpo (mapeo Infinity)

| Var | Contenido |
|-----|-----------|
| `{{1}}` | Nombre del router |
| `{{2}}` | IP de gestión |
| `{{3}}` | Cantidad de fallos consecutivos |
| `{{4}}` | Fecha/hora del último ping (`d/m/Y H:i:s`) |

6. Enviar a revisión y esperar estado **APPROVED**.

## Configurar Infinity (`.env`)

```env
WHATSAPP_ENABLED=true
WHATSAPP_EVENT_ROUTER_CAIDO=true
WHATSAPP_TEMPLATE_ROUTER_CAIDO=router_caido_ping
# WHATSAPP_TEMPLATE_ROUTER_CAIDO_LANG=es
```

Luego: `php artisan config:clear`

## Quién recibe el aviso

Panel: **Sistema → Alertas caída router**

- Activar avisos
- Elegir cuántos fallos seguidos (default 3 ≈ 3 min con ping cada 60 s)
- Marcar usuarios staff (deben tener teléfono WhatsApp)
- Opcional: **Probar envío**

## Cuándo se envía

- Comando programado `mikrotik:ping-routers` (cada minuto)
- Ping desde Monitoreo de red (misma lógica de contador)

Solo **un** aviso por caída: al recuperarse el ping se resetea el contador y puede alertar en la próxima caída.

## Fallback

Si la plantilla falla o está vacía, se intenta texto libre (solo útil dentro de la ventana de 24 h).
