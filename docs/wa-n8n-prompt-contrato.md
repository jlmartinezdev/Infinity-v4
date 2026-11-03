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

## Campos nuevos que Laravel envía

Además del payload previo (`historial_texto`, `planes_*`, `estilo_interplus`, flags `parece_*`):

- `hechos`: `{ pudo_medir, causas[], incidente_zona_abierto, sesion_online, ping_cpe_ok, ping_general_ok, optica_ok, lan_conectado, es_fibra, es_antena, diagnostico_local }`
- `plan_nombre`, `plan_precio`, `cedula`, `es_antena`, `ticket_abierto`
- `instruccion_hechos`: solo afirmar lo que esté en hechos / catálogo / canned
- `instruccion_hilo`: continuidad del hilo, sin re-saludar

## Reglas del system prompt (Basic LLM Chain)

1. Leer `historial_texto` y `hechos` antes de redactar.
2. Solo afirmar causas presentes en `hechos.causas` o flags (`incidente_zona_abierto`, etc.).
3. Si `hechos` está vacío o `pudo_medir=false` y el mensaje es falla: preguntar o escalar; **no inventar** reinicio, cable, canal ni óptica.
4. Cotizar **solo** planes de `planes_fibra` / `planes_antena` / `planes_texto`.
5. Transferencia / beneficios / condiciones: preferir `texto_*` canned literal.
6. Trato formal de usted. No “te saluda Interplus”, no “lamentamos”, no teléfonos, no dBm/IP/ping al cliente.
7. Respuesta JSON: `{ reply, escalate, cliente_id?, motivo_escalado?, modo? }`.

## Nodo Armar contexto

Incluir en el texto de contexto que va al LLM:

```
HECHOS (solo estos son verdaderos):
{{ JSON.stringify($json.hechos) }}

CUENTA: plan={{ $json.plan_nombre }} precio={{ $json.plan_precio }} cedula={{ $json.cedula }} antena={{ $json.es_antena }} ticket_abierto={{ $json.ticket_abierto }}
{{ $json.contexto_cuenta }}

{{ $json.instruccion_hechos }}
{{ $json.instruccion_hilo }}
```

Laravel ya corta alucinaciones con `ValidadorHechos` + `FraseCliente::filtrar` después del webhook.
