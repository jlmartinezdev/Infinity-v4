from __future__ import annotations

import json
import os
import re
import time
from typing import Any, TypedDict

from langgraph.checkpoint.memory import MemorySaver
from langgraph.graph import END, StateGraph

from .gemini_client import GeminiClient
from .prompts import CLASSIFY_SYSTEM, REPLY_SYSTEM
from .tools_client import ToolsClient, default_tools_base

_MEMORY = MemorySaver()

_COBERTURA_RE = re.compile(
    r"cuenta disponible|hay (cuenta|cupo)|tienen (lugar|cupo)|hay lugar|"
    r"cupo (para |de )?instal|\bcobertura\b|llega(n)? (la |el )?(fibra|internet|antena)",
    re.I,
)
_TRANSFERENCIA_RE = re.compile(
    r"datos (de |para )?transf|a qu[eé] cuenta|n[uú]mero(s)? de cuenta|"
    r"\balias\b|pagar por transferencia|c[oó]mo (hago |te )?(la )?transf|"
    r"cuenta (para |de )?(pago|transf)|pas(ame|á|a) (una |la )?cuenta|tigo money",
    re.I,
)
_SALUDO_RE = re.compile(
    r"^(hola|buen[oa]s?\s+(d[ií]as|tardes|noches)|buen d[ií]a|hey|ola)\b[\s!.?]*$",
    re.I,
)


class AgentState(TypedDict, total=False):
    wa_id: str
    mensaje: str
    historial_texto: str
    sandbox: bool
    payload: dict[str, Any]
    intent: str
    confidence: float
    needs_human: bool
    continue_process: bool
    tema_nuevo: bool
    proceso: str
    proceso_cancelado: bool
    cliente_id: int | None
    tool_facts: dict[str, Any]
    tools_used: list[dict[str, Any]]
    answer: str
    escalate: bool
    motivo_escalado: str | None
    modo: str
    tokens_in: int
    tokens_out: int
    llm_latency_ms: int


def _norm_proceso(intent: str) -> str:
    if intent in {"saldo", "identificacion"}:
        return "pago"
    if intent == "falla":
        return "falla"
    return "none"


def _secret() -> str:
    return (
        os.getenv("N8N_WA_AGENT_SECRET")
        or os.getenv("WA_AGENT_SECRET")
        or ""
    ).strip()


def build_graph():
    g = StateGraph(AgentState)

    def classify(state: AgentState) -> dict[str, Any]:
        payload = state.get("payload") or {}
        gemini = GeminiClient()
        proceso = state.get("proceso") or "none"
        user = (
            f"proceso_activo={proceso}\n"
            f"mensaje={state.get('mensaje','')}\n"
            f"historial_reciente={state.get('historial_texto','')[:1200]}\n"
            f"flags={{parece_pago:{payload.get('parece_pago_texto')}, "
            f"parece_cedula:{payload.get('parece_cedula')}, "
            f"parece_beneficios:{payload.get('parece_beneficios')}, "
            f"parece_condiciones:{payload.get('parece_condiciones')}, "
            f"parece_instalacion:{payload.get('parece_instalacion')}}}"
        )
        msg_low = str(state.get("mensaje") or "").lower().strip()
        # Heurísticas locales ANTES del LLM (rápido y evita confusiones).
        forced = None
        if _SALUDO_RE.match(msg_low):
            forced = "saludo"
        elif _COBERTURA_RE.search(msg_low) and not _TRANSFERENCIA_RE.search(msg_low):
            forced = "cobertura"
        elif bool(payload.get("parece_datos_transferencia")) or (
            _TRANSFERENCIA_RE.search(msg_low) and not _COBERTURA_RE.search(msg_low)
        ):
            forced = "transferencia"
        elif bool(payload.get("parece_beneficios")):
            forced = "beneficios"
        elif bool(payload.get("parece_condiciones")):
            forced = "condiciones"

        if forced:
            intent = forced
            tema_nuevo = True
            continue_process = False
            tokens_in = int(state.get("tokens_in") or 0)
            tokens_out = int(state.get("tokens_out") or 0)
            llm_ms = int(state.get("llm_latency_ms") or 0)
            needs_human = False
            confidence = 0.95
        else:
            data = gemini.generate_json(CLASSIFY_SYSTEM, user)
            intent = str(data.get("intent") or "otro")
            tema_nuevo = bool(data.get("tema_nuevo"))
            continue_process = bool(data.get("continue_process"))
            needs_human = bool(data.get("needs_human"))
            confidence = float(data.get("confidence") or 0.5)
            tokens_in = int(state.get("tokens_in") or 0) + gemini.tokens_in
            tokens_out = int(state.get("tokens_out") or 0) + gemini.tokens_out
            llm_ms = int(state.get("llm_latency_ms") or 0) + gemini.latency_ms

        proceso_cancelado = False
        if tema_nuevo and proceso not in {"", "none"} and not continue_process:
            proceso = "none"
            proceso_cancelado = True
        elif not (continue_process and proceso not in {"", "none"}):
            nuevo = _norm_proceso(intent)
            if nuevo != "none":
                proceso = nuevo
            elif tema_nuevo:
                proceso = "none"
                proceso_cancelado = True

        return {
            "intent": intent,
            "confidence": confidence,
            "needs_human": needs_human,
            "continue_process": continue_process,
            "tema_nuevo": tema_nuevo,
            "proceso": proceso,
            "proceso_cancelado": proceso_cancelado,
            "tokens_in": tokens_in,
            "tokens_out": tokens_out,
            "llm_latency_ms": llm_ms,
        }

    def act(state: AgentState) -> dict[str, Any]:
        payload = state.get("payload") or {}
        tools_base = str(payload.get("tools_base_url") or default_tools_base())
        tools = ToolsClient(tools_base, _secret(), sandbox=bool(state.get("sandbox")))
        wa_id = str(state.get("wa_id") or "")
        mensaje = str(state.get("mensaje") or "")
        intent = str(state.get("intent") or "otro")
        facts: dict[str, Any] = {"intent": intent}
        cliente_id = state.get("cliente_id")

        if intent in {"saludo", "meta"}:
            facts["nota"] = "sin_tools"
        elif intent == "cobertura":
            facts["nota"] = "pedir_ubicacion_cobertura"
            facts["conocimiento"] = tools.conocimiento(mensaje, "cobertura")
        elif intent == "catalogo_internet":
            facts["catalogo"] = tools.catalogo_planes()
        elif intent in {"transferencia", "beneficios", "condiciones"}:
            canned = tools.textos_canned()
            facts["canned"] = {intent: canned.get(intent)}
        elif intent in {"saldo", "plan_cuenta", "identificacion"}:
            if intent == "identificacion" or bool(payload.get("parece_cedula")):
                cuenta = tools.buscar_cuenta(wa_id, mensaje)
            else:
                cid = cliente_id if isinstance(cliente_id, int) else None
                cuenta = tools.saldo_y_plan(wa_id, mensaje, cid)
            facts["cuenta"] = cuenta
            found = cuenta.get("cuenta") if isinstance(cuenta.get("cuenta"), dict) else cuenta
            if isinstance(found, dict) and found.get("cliente_id"):
                cliente_id = int(found["cliente_id"])
            elif cuenta.get("cliente_id"):
                cliente_id = int(cuenta["cliente_id"])
        elif intent == "zona":
            facts["zona"] = tools.zona(wa_id, mensaje)
        elif intent == "falla":
            facts["diagnostico"] = tools.diagnosticar(wa_id, mensaje)
            facts["zona"] = tools.zona(wa_id, mensaje)
        elif intent == "planes_tv":
            facts["conocimiento"] = tools.conocimiento(mensaje, "tv")
            if not (facts["conocimiento"].get("casos") or []):
                facts["conocimiento"] = tools.conocimiento(mensaje)
        else:
            low = mensaje.lower()
            if "plan" in low:
                facts["catalogo"] = tools.catalogo_planes()
            facts["conocimiento"] = tools.conocimiento(mensaje)

        if isinstance(payload.get("hechos"), dict):
            facts["hechos_laravel"] = payload["hechos"]

        return {
            "tool_facts": facts,
            "cliente_id": cliente_id,
            "tools_used": tools.used,
        }

    def compose(state: AgentState) -> dict[str, Any]:
        if state.get("needs_human") or str(state.get("intent")) == "handoff":
            return {
                "answer": (
                    "Voy a derivar su consulta con un asesor de Interplus para ayudarle mejor. "
                    "¿Desea agregar algún detalle mientras tanto?"
                ),
                "escalate": True,
                "motivo_escalado": "needs_human",
                "modo": "langgraph_handoff",
            }

        intent = str(state.get("intent") or "")
        facts = state.get("tool_facts") or {}

        canned = facts.get("canned") or {}
        for key in ("transferencia", "beneficios", "condiciones"):
            if key in canned and canned[key]:
                return {
                    "answer": str(canned[key]),
                    "escalate": False,
                    "motivo_escalado": None,
                    "modo": f"langgraph_canned_{key}",
                }

        if intent == "cobertura" and facts.get("nota") == "pedir_ubicacion_cobertura":
            return {
                "answer": (
                    "Con gusto verificamos si hay cuenta disponible en su zona. "
                    "Indíquenos el barrio y la ciudad, o envíenos su ubicación por WhatsApp "
                    "(clip → Ubicación) para confirmar cobertura."
                ),
                "escalate": False,
                "motivo_escalado": None,
                "modo": "langgraph_cobertura",
            }

        cat = facts.get("catalogo") or {}
        if intent == "catalogo_internet" and cat.get("planes_texto"):
            return {
                "answer": str(cat["planes_texto"]),
                "escalate": False,
                "motivo_escalado": None,
                "modo": "langgraph_catalogo",
            }

        if intent == "saludo":
            return {
                "answer": (
                    "Le damos la bienvenida a Interplus. "
                    "¿En qué le podemos ayudar el día de hoy?"
                ),
                "escalate": False,
                "motivo_escalado": None,
                "modo": "langgraph_saludo",
            }

        decision = (facts.get("diagnostico") or {}).get("decision") or {}
        if decision.get("reply"):
            return {
                "answer": str(decision["reply"]),
                "escalate": bool(decision.get("escalate")),
                "motivo_escalado": decision.get("motivo"),
                "modo": "langgraph_diagnostico",
            }

        gemini = GeminiClient()
        estilo = str((state.get("payload") or {}).get("estilo_interplus") or "")
        user = (
            f"intent={intent}\n"
            f"proceso={state.get('proceso')}\n"
            f"proceso_cancelado={state.get('proceso_cancelado')}\n"
            f"sandbox={state.get('sandbox')}\n"
            f"mensaje_cliente={state.get('mensaje')}\n"
            f"hechos_tools={json.dumps(facts, ensure_ascii=False)[:6000]}\n"
            f"estilo={estilo[:1500]}"
        )
        text = gemini.generate(REPLY_SYSTEM, user, temperature=0.3)
        if not text:
            text = (
                "Gracias por escribir a Interplus. Cuénteme en qué podemos ayudarle "
                "con su servicio de internet."
            )
        return {
            "answer": text,
            "escalate": False,
            "motivo_escalado": None,
            "modo": "langgraph",
            "tokens_in": int(state.get("tokens_in") or 0) + gemini.tokens_in,
            "tokens_out": int(state.get("tokens_out") or 0) + gemini.tokens_out,
            "llm_latency_ms": int(state.get("llm_latency_ms") or 0) + gemini.latency_ms,
        }

    g.add_node("classify", classify)
    g.add_node("act", act)
    g.add_node("compose", compose)
    g.set_entry_point("classify")
    g.add_edge("classify", "act")
    g.add_edge("act", "compose")
    g.add_edge("compose", END)
    return g.compile(checkpointer=_MEMORY)


_GRAPH = None


def get_graph():
    global _GRAPH
    if _GRAPH is None:
        _GRAPH = build_graph()
    return _GRAPH


def run_turn(payload: dict[str, Any]) -> dict[str, Any]:
    started = time.perf_counter()
    wa_id = str(payload.get("wa_id") or "unknown")
    graph = get_graph()
    config = {"configurable": {"thread_id": wa_id}}

    proceso = "none"
    cliente_id = None
    try:
        snap = graph.get_state(config)
        vals = snap.values if snap else {}
        if isinstance(vals, dict):
            proceso = str(vals.get("proceso") or "none")
            cid = vals.get("cliente_id")
            cliente_id = int(cid) if isinstance(cid, int) else None
    except Exception:  # noqa: BLE001
        pass

    init: AgentState = {
        "wa_id": wa_id,
        "mensaje": str(payload.get("mensaje") or payload.get("mensaje_hilo_reciente") or ""),
        "historial_texto": str(payload.get("historial_texto") or ""),
        "sandbox": bool(payload.get("sandbox")),
        "payload": payload,
        "proceso": proceso,
        "cliente_id": cliente_id,
        "tool_facts": {},
        "tools_used": [],
        "proceso_cancelado": False,
        "tokens_in": 0,
        "tokens_out": 0,
        "llm_latency_ms": 0,
    }

    result = graph.invoke(init, config=config)
    total_ms = int((time.perf_counter() - started) * 1000)

    return {
        "reply": result.get("answer"),
        "escalate": bool(result.get("escalate")),
        "cliente_id": result.get("cliente_id"),
        "motivo_escalado": result.get("motivo_escalado"),
        "modo": result.get("modo") or "langgraph",
        "intent": result.get("intent"),
        "confidence": result.get("confidence"),
        "tools_used": result.get("tools_used") or [],
        "proceso_activo": result.get("proceso"),
        "proceso_cancelado": bool(result.get("proceso_cancelado")),
        "hechos": result.get("tool_facts") or {},
        "total_latency_ms": total_ms,
        "llm_latency_ms": result.get("llm_latency_ms"),
        "tokens_in": result.get("tokens_in"),
        "tokens_out": result.get("tokens_out"),
    }
