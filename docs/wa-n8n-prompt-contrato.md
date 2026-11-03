# Contrato prompt n8n — Interplus WA Agent

## Infra (no confundir)

| Qué | Valor |
|---|---|
| n8n Interplus | VM `infinity`, LAN **`10.200.1.4:5678`** |
| Acceso desde PC de trabajo | Proxy **`127.0.0.1:5678` → `10.200.1.4:5678`** |
| Workflow | **Interplus WA Agent (plugin v4)**, id `QhmYIm66OXWYZ4lx` |
| Webhook | `POST /webhook/interplus-wa-agent` + header `X-Interplus-Secret` |
| `.env` | `N8N_WA_AGENT_URL=http://127.0.0.1:5678/webhook/interplus-wa-agent` |
| **NO usar** | `200.59.9.117` — no es el n8n de Interplus |

## Campos que Laravel envía

Además del payload previo (`historial_texto`, flags `parece_*`):

- `planes_fibra` / `planes_antena`: arrays de planes
- `planes_texto`: **lista segura para el cliente** (sin instrucciones)
- `planes_prompt`: instrucciones + lista **solo para el LLM**; nunca pegar al reply
- `hechos`: `{ pudo_medir, causas[], incidente_zona_abierto, sesion_online, ping_cpe_ok, ping_general_ok, optica_ok, lan_conectado, es_fibra, es_antena, diagnostico_local, puede_listar_equipos, equipos_nombres }`
- `plan_nombre`, `plan_precio`, `cedula`, `es_antena`, `ticket_abierto`, `contexto_cuenta`
- `estilo_interplus`, `instruccion_hechos`, `instruccion_hilo`
- `texto_beneficios` / `texto_transferencia` / `texto_condiciones`: canned literales

## Reglas del system prompt (Basic LLM Chain)

1. Leer `historial_texto` y `hechos` antes de redactar.
2. Solo afirmar causas presentes en `hechos.causas` o flags (`incidente_zona_abierto`, etc.).
3. Si `hechos` está vacío o `pudo_medir=false` y el mensaje es falla: preguntar o escalar; **no inventar** reinicio, cable, canal ni óptica.
4. Cotizar **solo** desde `planes_prompt` / `planes_fibra` / `planes_antena`. El reply al cliente debe verse como `planes_texto` (líneas de plan + precio), **sin** copiar instrucciones.
5. **Prohibido** en el reply: «Venta suave», «Salida WhatsApp», «Enrutado», «Cotice SOLO», «prioridad N», «Estilo Interplus», bloques de `instruccion_*` o JSON de hechos.
6. Transferencia / beneficios / condiciones: preferir `texto_*` canned literal.
7. Detalle de servicio / saldo / plan actual: preferir datos de `contexto_cuenta` / `plan_nombre` / `plan_precio`; no inventar.
8. Trato formal de usted. No “te saluda Interplus”, no “lamentamos”, no teléfonos (salvo transferencia canned), no dBm/IP/ping al cliente.
9. Respuesta JSON: `{ reply, escalate, cliente_id?, motivo_escalado?, modo? }`.

## Nodo Armar contexto

Incluir en el texto de contexto que va al LLM:

```
HECHOS (solo estos son verdaderos; no pegar este bloque al cliente):
{{ JSON.stringify($json.hechos) }}

CUENTA: plan={{ $json.plan_nombre }} precio={{ $json.plan_precio }} cedula={{ $json.cedula }} antena={{ $json.es_antena }} ticket_abierto={{ $json.ticket_abierto }}
{{ $json.contexto_cuenta }}

CATÁLOGO (instrucciones internas; no repetir al cliente):
{{ $json.planes_prompt }}

LISTA CLIENTE (formato permitido al cotizar):
{{ $json.planes_texto }}

{{ $json.instruccion_hechos }}
{{ $json.instruccion_hilo }}
```

Laravel corta alucinaciones y fugas de prompt con `ValidadorHechos` + `FraseCliente::filtrar` después del webhook. No confiar solo en el LLM.
