# Plantilla WhatsApp — Retomar pedido pendiente

Aviso desde el número oficial cuando un pedido de instalación sigue pendiente y hay que
volver a escribirle al cliente. Fuera de las 24 h, el texto libre no llega: hace falta
esta plantilla APPROVED.

## Crear en Meta

```bash
php artisan whatsapp:plantilla-pedido-pendiente
```

O a mano en WhatsApp Manager → Plantillas de mensajes → Crear:

1. **Nombre:** `pedido_pendiente_retomar`
2. **Idioma:** `es`
3. **Categoría:** Utilidad
4. **Cuerpo** (1 variable, el nombre):

```
Hola {{1}}.

Le escribimos de Interplus porque su pedido de instalación sigue pendiente. ¿Podemos retomar la conversación para confirmar el siguiente paso?
```

5. **Botones de respuesta rápida:** `Sí, retomemos` y `Más tarde`
6. Ejemplo de `{{1}}`: `Ana`
7. Enviar a revisión y esperar **APPROVED**.

`{{1}}` es el nombre visible del chat. Si el chat solo tiene el teléfono, el panel usa «cliente».

## En el panel

En un chat con pedido pendiente, **Retomar pedido** abre el envío con esta plantilla y el nombre ya cargado. No se envía sola: hay que confirmar.

Hasta que Meta la apruebe, el formulario avisa que está pendiente y no la deja salir.
