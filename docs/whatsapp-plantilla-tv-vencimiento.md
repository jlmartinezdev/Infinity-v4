# Plantilla WhatsApp — TV streaming por vencer

Avisa al **staff** cuando una cuenta de TV streaming está por vencer (o ya venció), según días de anticipación y hora del panel **Cuentas TV**.

Sin esta plantilla **APPROVED**, Meta rechaza el mensaje (`Re-engagement message`) porque no hay ventana de 24 h. La caída de red sí llega porque usa `router_caido_ping`.

## Crear en Meta

1. WhatsApp Manager → **Plantillas de mensajes** → Crear
2. **Nombre:** `tv_cuenta_por_vencer`
3. **Idioma:** el que quede APPROVED (ej. `es`, `es_PY`)
4. **Categoría:** Utilidad / Utility
5. **Cuerpo** (4 variables):

```
Aviso TV streaming
Cuenta: {{1}}
Usuario: {{2}}
Vencimiento: {{3}} ({{4}})
Revisá Cuentas TV en Infinity.
```

| Var | Contenido |
|-----|-----------|
| `{{1}}` | Nombre de la cuenta |
| `{{2}}` | Usuario de la app |
| `{{3}}` | Fecha de vencimiento (`d/m/Y`) |
| `{{4}}` | Estado (`vence en 2 días`, `vence HOY`, `vencida hace 1 día`) |

## Configurar Infinity (`.env`)

```env
WHATSAPP_ENABLED=true
WHATSAPP_EVENT_TV_VENCIMIENTO=true
WHATSAPP_TEMPLATE_TV_VENCIMIENTO=tv_cuenta_por_vencer
# WHATSAPP_TEMPLATE_TV_VENCIMIENTO_LANG=es
```

Luego: `php artisan config:clear`

El worker `InfinitySchedule` debe estar en ejecución. Si el PC se enciende después de la hora configurada, el aviso corre al rato (cada 15 min) y no se pierde el día.
