# Conector MCP de WhatsApp

Claude, ChatGPT y Gemini leen los chats de Infinity y proponen la respuesta. El mensaje sale solo si en el chat se pide enviarlo y la herramienta llega con `confirmar=true`.

URL única: `https://infinityisppro.net/api/mcp/whatsapp`

## Servidor

En el `.env` del servidor (no hace falta `config:cache`):

```
MCP_WHATSAPP_TOKEN=un-token-largo-aleatorio
```

URL: `https://infinityisppro.net/api/mcp/whatsapp`

Cabecera: `Authorization: Bearer <el mismo token>`

## En Claude

Conector personalizado (Claude.ai o la app de escritorio):

- URL: `https://infinityisppro.net/api/mcp/whatsapp`
- Autenticación: Bearer token, el valor de `MCP_WHATSAPP_TOKEN`

Herramientas:

- `whatsapp_pendientes` — chats sin leer
- `whatsapp_hilo` — mensajes del número
- `whatsapp_contexto` — plan, saldo, estado y zona. Sin cédula ni claves
- `whatsapp_enviar` — envía texto libre solo dentro de las 24 horas y solo con `confirmar=true`

Si la ventana de 24 horas está cerrada, el texto libre no sale. La plantilla se manda desde el panel.

## ChatGPT y Gemini

Esos chats de la web no aceptan el token fijo. Usan OAuth: la persona inicia sesión en Infinity y pulsa Autorizar. Hace falta el permiso de ver WhatsApp.

ChatGPT (conector personalizado): pegue solo la URL. El registro del cliente es automático.

Gemini, si pide identificador y secreto: use `MCP_OAUTH_CLIENT_ID` y `MCP_OAUTH_CLIENT_SECRET` del `.env`.

Metadatos: `https://infinityisppro.net/oauth/protected-resource` y `https://infinityisppro.net/oauth/authorization-server`.
