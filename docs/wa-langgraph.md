# WhatsApp LangGraph Agent

Cerebro conversacional del agente WA: **LangGraph + Gemini** (`gemini-3.6-flash`, misma key que n8n/visión). Laravel es gateway Meta, tools de hechos y `ValidadorHechos`.

## Flujo

1. Meta → Laravel webhook → `WhatsAppAgentService::procesar`
2. Si `WA_AGENT_BACKEND=langgraph` (o sandbox con `WA_AGENT_LANGGRAPH_SANDBOX=true`): **no** corre `SoporteOrquestador` sticky
3. `POST` a `WA_AGENT_LANGGRAPH_URL` (`http://127.0.0.1:8091/v1/turn`)
4. Grafo: classify → tools HTTP → reply
5. Laravel valida hechos, saluda, audita KPI (`backend`, intent, tools, tokens, latencias)

## Variables

| Variable | Uso |
|----------|-----|
| `WA_AGENT_BACKEND` | `n8n` \| `langgraph` |
| `WA_AGENT_LANGGRAPH_URL` | `http://127.0.0.1:8091/v1/turn` |
| `WA_AGENT_LANGGRAPH_SANDBOX` | `true` = playground 595000 usa langgraph |
| `WA_AGENT_GEMINI_API_KEY` | Misma key Gemini |
| `WA_AGENT_GEMINI_MODEL` | Default `gemini-3.6-flash` |
| `N8N_WA_AGENT_SECRET` | Header `X-Interplus-Secret` (Laravel ↔ LangGraph ↔ tools) |
| `WA_AGENT_TOOLS_BASE_URL` | Default `http://127.0.0.1/api/v1/wa-agent/tools` |

`auto_send` / `auto_ticket` siguen en false.

## Tools Laravel

Auth: `X-Interplus-Secret`. Prefijo `/api/v1/wa-agent/tools/`:

- `POST buscar-cuenta`, `saldo-y-plan`, `diagnosticar`, `zona`, `conocimiento`, `hilo`, `crear-ticket`
- `GET catalogo-planes`, `textos-canned`

## Servicio Python

```bash
cd /home/infinity/infinity-v4/services/wa-langgraph
# o: bash install-on-vm.sh
python3 -m venv .venv
.venv/bin/pip install -r requirements.txt
sudo cp systemd/wa-langgraph.service /etc/systemd/system/
sudo systemctl daemon-reload
sudo systemctl enable --now wa-langgraph
curl -s http://127.0.0.1:8091/health
```

## Corpus / replay

```bash
php artisan whatsapp:auditar-corpus --meses=5
php artisan whatsapp:replay-langgraph --meses=5 --muestra=80
```

Replay no envía a Meta; reporta intent, loops falsos y latencias.
