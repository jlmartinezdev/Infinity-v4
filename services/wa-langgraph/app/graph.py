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
    r"cuenta (para |de )?(pago|transf)|pas(ame|á|a)\s+(una |la )?cuenta|"
    r"tigo money|giro(s)? tigo|para (hacer|hacerles) giro|"
    r"funcionando para tran|para tranfe|nro\.? de cuenta|numero de cuenta",
    re.I,
)
_SALUDO_RE = re.compile(
    r"^(hola+|buen[oa]s?\s+(d[ií]as|tardes|noches)|buen d[ií]a|hey|ola|"
    r"hola que tal|hola que talti|buenas)\b[\s!.?]*$",
    re.I,
)
_BENEFICIOS_RE = re.compile(
    r"\bbeneficios?\b|diferencia entre.*plan|por qu[eé].{0,40}m[aá]s|"
    r"tr[aá]fico prioritario",
    re.I,
)
_CONDICIONES_RE = re.compile(
    r"\bcondiciones?( del servicio)?\b|comodato|sin (contrato de )?permanencia|"
    r"fecha de pago|del 1 al 5|cu[aá]ndo (se )?paga",
    re.I,
)
_CATALOGO_RE = re.compile(
    r"\b(planes?|precios?|cu[aá]nto (sale|cuesta|vale)|cat[aá]logo|"
    r"tienen (fibra|antena)|plan de \d)",
    re.I,
)
_WIFI_VENTA_RE = re.compile(
    r"quiero (wifi|internet|fibra|antena)|necesito (wifi|internet|fibra)|"
    r"contratar|instalar(me)? (wifi|internet|fibra)|me gustar[ií]a (el )?internet",
    re.I,
)
_MAPS_RE = re.compile(
    r"share\.google|maps\.app\.goo\.gl|maps\.google|google\.[a-z.]+/maps|"
    r"goo\.gl/maps|waze\.com|https?://\S*map",
    re.I,
)
_COMPROBANTE_AVISO_RE = re.compile(
    r"ya pagu[eé]|les mando (el )?comprobante|mando (el )?comprobante|"
    r"te (env[ií]o|mando).*comprobante|ac[aá] (va |est[aá] )?el comprobante|"
    r"pagu[eé] mi (cuota|factura|mes)",
    re.I,
)
_AGENTE_SALUDO_RE = re.compile(
    r"(un placer atenderle|estamos a las [oó]rdenes|le damos la bienvenida|"
    r"muy buen(os|as) (d[ií]as|tardes|noches))",
    re.I,
)
_FALLA_RE = re.compile(
    r"no me anda|sin internet|no tengo (net|internet|wifi)|se cort[oó]|"
    r"no navega|ca[ií]do|no anda (el |la )?(wifi|internet|net)|sin se[nñ]al|"
    r"no conecta|est[aá] lento|lent[ií]simo",
    re.I,
)
_RETOMAR_RE = re.compile(
    r"y lo del (internet|wifi|pago|saldo)|seguimos con|volviendo a|"
    r"lo de (antes|la falla|el corte)|retom",
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
    proceso_pila: list[str]
    proceso_cancelado: bool
    sector: str
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
    if intent in {"saldo", "identificacion", "transferencia", "comprobante_aviso"}:
        return "pago"
    if intent in {"falla", "zona"}:
        return "falla"
    if intent in {"cobertura", "cobertura_con_dato", "catalogo_internet", "beneficios", "condiciones"}:
        return "venta"
    return "none"


def _sector_de(intent: str, proceso: str) -> str:
    if intent in {
        "catalogo_internet",
        "beneficios",
        "condiciones",
        "cobertura",
        "cobertura_con_dato",
        "transferencia",
    }:
        return "comercial"
    if intent in {"falla", "zona"} or proceso == "falla":
        return "tecnico"
    # Saldo/mora en embudo sin-internet = paso cobranza del sector técnico
    if intent in {"saldo", "identificacion", "comprobante_aviso"} or proceso == "pago":
        return "tecnico" if proceso == "falla" else "comercial"
    if intent in {"saludo", "meta", "handoff"}:
        return "meta"
    return "meta"


def _apilar_proceso(pila: list[str], actual: str, nuevo: str) -> list[str]:
    out = list(pila or [])
    if actual not in {"", "none"} and actual != nuevo:
        if not out or out[-1] != actual:
            out.append(actual)
        # Evitar pilas enormes
        out = out[-5:]
    return out


def _desapilar_si_retoma(pila: list[str], mensaje: str, tema_nuevo: bool) -> tuple[str | None, list[str]]:
    if not pila:
        return None, []
    if _RETOMAR_RE.search(mensaje or "") or (not tema_nuevo and False):
        nueva = list(pila)
        prev = nueva.pop()
        return prev, nueva
    return None, list(pila)


def _secret() -> str:
    return (
        os.getenv("N8N_WA_AGENT_SECRET")
        or os.getenv("WA_AGENT_SECRET")
        or ""
    ).strip()


def _ya_saludo_agente(historial: str) -> bool:
    return bool(_AGENTE_SALUDO_RE.search(historial or ""))


def _force_intent(msg_low: str, payload: dict[str, Any], historial: str) -> str | None:
    """Hints seguros. El saludo repetido pasa a meta para que razone el LLM."""
    if _SALUDO_RE.match(msg_low):
        if _ya_saludo_agente(historial):
            return "meta"
        return "saludo"
    if _COMPROBANTE_AVISO_RE.search(msg_low):
        return "comprobante_aviso"
    if _FALLA_RE.search(msg_low) and not _TRANSFERENCIA_RE.search(msg_low):
        return "falla"
    if _MAPS_RE.search(msg_low) or bool(payload.get("parece_mapa")):
        return "cobertura_con_dato"
    if _WIFI_VENTA_RE.search(msg_low):
        return "cobertura"
    if _CONDICIONES_RE.search(msg_low):
        return "condiciones"
    if _BENEFICIOS_RE.search(msg_low):
        return "beneficios"
    if _COBERTURA_RE.search(msg_low) and not _TRANSFERENCIA_RE.search(msg_low):
        return "cobertura"
    if _TRANSFERENCIA_RE.search(msg_low) and not _COBERTURA_RE.search(msg_low):
        return "transferencia"
    if bool(payload.get("parece_datos_transferencia")) and not _CONDICIONES_RE.search(msg_low):
        return "transferencia"
    if _CATALOGO_RE.search(msg_low) and not _COBERTURA_RE.search(msg_low):
        return "catalogo_internet"
    return None


def build_graph():
    g = StateGraph(AgentState)

    def classify(state: AgentState) -> dict[str, Any]:
        payload = state.get("payload") or {}
        gemini = GeminiClient()
        proceso = state.get("proceso") or "none"
        pila: list[str] = list(state.get("proceso_pila") or [])
        historial = str(state.get("historial_texto") or "")
        mensaje = str(state.get("mensaje") or "")
        user = (
            f"proceso_activo={proceso}\n"
            f"proceso_pila={pila}\n"
            f"mensaje={mensaje}\n"
            f"historial_reciente={historial[:2000]}\n"
            f"flags={{parece_pago:{payload.get('parece_pago_texto')}, "
            f"parece_cedula:{payload.get('parece_cedula')}, "
            f"parece_mapa:{payload.get('parece_mapa')}, "
            f"parece_instalacion:{payload.get('parece_instalacion')}}}"
        )
        msg_low = mensaje.lower().strip()
        forced = _force_intent(msg_low, payload, historial)

        # Clasificación: hint local + siempre Gemini si no es transferencia/saludo puro
        # (más razonamiento). Transferencia se fuerza por seguridad de números.
        if forced == "transferencia":
            intent = "transferencia"
            tema_nuevo = True
            continue_process = False
            tokens_in = int(state.get("tokens_in") or 0)
            tokens_out = int(state.get("tokens_out") or 0)
            llm_ms = int(state.get("llm_latency_ms") or 0)
            needs_human = False
            confidence = 0.98
        else:
            data = gemini.generate_json(CLASSIFY_SYSTEM, user)
            intent = str(data.get("intent") or forced or "otro")
            # Si el hint es más específico que “otro”, preferirlo.
            if forced and intent in {"otro", "saludo"} and forced not in {"saludo"}:
                intent = forced
            if forced == "meta" and _SALUDO_RE.match(msg_low):
                intent = "meta"
            if forced == "cobertura_con_dato":
                intent = "cobertura_con_dato"
            if forced == "comprobante_aviso":
                intent = "comprobante_aviso"
            if forced == "falla":
                intent = "falla"
            tema_nuevo = bool(data.get("tema_nuevo", True))
            continue_process = bool(data.get("continue_process"))
            needs_human = bool(data.get("needs_human"))
            confidence = float(data.get("confidence") or 0.6)
            tokens_in = int(state.get("tokens_in") or 0) + gemini.tokens_in
            tokens_out = int(state.get("tokens_out") or 0) + gemini.tokens_out
            llm_ms = int(state.get("llm_latency_ms") or 0) + gemini.latency_ms
            if forced and intent == "otro":
                intent = forced

        # Retomar tema apilado (“y lo del internet…”)
        retomado, pila = _desapilar_si_retoma(pila, mensaje, tema_nuevo)
        if retomado:
            proceso = retomado
            intent = "falla" if retomado == "falla" else ("saldo" if retomado == "pago" else intent)
            tema_nuevo = False
            continue_process = True

        proceso_cancelado = False
        nuevo = _norm_proceso(intent)
        if tema_nuevo and proceso not in {"", "none"} and not continue_process and nuevo not in {"", "none"} and nuevo != proceso:
            # Cambio de tema: apilar el actual y pasar al nuevo (paralelo).
            pila = _apilar_proceso(pila, proceso, nuevo)
            proceso = nuevo
            proceso_cancelado = False
        elif tema_nuevo and proceso not in {"", "none"} and not continue_process and nuevo in {"", "none"}:
            pila = _apilar_proceso(pila, proceso, "none")
            proceso = "none"
            proceso_cancelado = True
        elif not (continue_process and proceso not in {"", "none"}):
            if nuevo != "none":
                if proceso not in {"", "none"} and proceso != nuevo and tema_nuevo:
                    pila = _apilar_proceso(pila, proceso, nuevo)
                proceso = nuevo
            elif tema_nuevo:
                proceso = "none"
                proceso_cancelado = True

        sector = _sector_de(intent, proceso)

        return {
            "intent": intent,
            "confidence": confidence,
            "needs_human": needs_human,
            "continue_process": continue_process,
            "tema_nuevo": tema_nuevo,
            "proceso": proceso,
            "proceso_pila": pila,
            "proceso_cancelado": proceso_cancelado,
            "sector": sector,
            "tokens_in": tokens_in,
            "tokens_out": tokens_out,
            "llm_latency_ms": llm_ms,
            "tool_facts": {},
            "answer": "",
            "modo": "",
            "escalate": False,
            "motivo_escalado": None,
        }

    def act(state: AgentState) -> dict[str, Any]:
        payload = state.get("payload") or {}
        tools_base = str(payload.get("tools_base_url") or default_tools_base())
        tools = ToolsClient(tools_base, _secret(), sandbox=bool(state.get("sandbox")))
        wa_id = str(state.get("wa_id") or "")
        mensaje = str(state.get("mensaje") or "")
        intent = str(state.get("intent") or "otro")
        facts: dict[str, Any] = {
            "intent": intent,
            "ya_saludo_agente": _ya_saludo_agente(str(state.get("historial_texto") or "")),
        }
        cliente_id = state.get("cliente_id")

        if intent in {"saludo", "meta", "comprobante_aviso"}:
            facts["nota"] = "razonar_con_historial"
        elif intent == "cobertura_con_dato":
            facts["nota"] = "ubicacion_recibida"
            facts["conocimiento"] = tools.conocimiento(mensaje, "cobertura")
            facts["needs_human_sugerido"] = True
        elif intent == "cobertura":
            facts["nota"] = "pedir_ubicacion_cobertura"
            facts["conocimiento"] = tools.conocimiento(mensaje, "cobertura")
        elif intent == "catalogo_internet":
            facts["catalogo"] = tools.catalogo_planes()
        elif intent == "transferencia":
            canned = tools.textos_canned()
            facts["canned"] = {"transferencia": canned.get("transferencia")}
            facts["canned_key"] = "transferencia"
        elif intent in {"beneficios", "condiciones"}:
            canned = tools.textos_canned()
            # Hecho para el LLM (no pegar ciego si quiere razonar tono); igual es la fuente.
            facts["texto_oficial"] = canned.get(intent)
            facts["canned_key"] = intent
            facts["conocimiento"] = tools.conocimiento(mensaje, intent)
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
            if "plan" in low or "wifi" in low:
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
        intent = str(state.get("intent") or "")
        facts = state.get("tool_facts") or {}
        mensaje = str(state.get("mensaje") or "")
        historial = str(state.get("historial_texto") or "")

        # Única respuesta 100% fija: datos bancarios (no inventar).
        if intent == "transferencia":
            texto = (facts.get("canned") or {}).get("transferencia")
            if texto:
                return {
                    "answer": str(texto),
                    "escalate": False,
                    "motivo_escalado": None,
                    "modo": "langgraph_canned_transferencia",
                }

        if state.get("needs_human") or intent == "handoff" or facts.get("needs_human_sugerido"):
            # Aun en handoff, dejamos que el LLM redacte con contexto.
            pass

        decision = (facts.get("diagnostico") or {}).get("decision") or {}
        if decision.get("reply") and intent == "falla":
            # Diagnóstico técnico: usar reply de tool como hecho y dejar que LLM lo pulimente.
            facts = {**facts, "respuesta_diagnostico": decision.get("reply")}

        gemini = GeminiClient()
        estilo = str((state.get("payload") or {}).get("estilo_interplus") or "")
        memoria = str((state.get("payload") or {}).get("memoria_instrucciones") or "")
        planes = (facts.get("catalogo") or {}).get("planes_texto") or ""
        texto_oficial = facts.get("texto_oficial") or ""
        user = (
            f"intent={intent}\n"
            f"sector={state.get('sector') or 'meta'}\n"
            f"proceso={state.get('proceso')}\n"
            f"proceso_pila={state.get('proceso_pila') or []}\n"
            f"proceso_cancelado={state.get('proceso_cancelado')}\n"
            f"sandbox={state.get('sandbox')}\n"
            f"ya_saludo_agente={facts.get('ya_saludo_agente')}\n"
            f"needs_human={bool(state.get('needs_human') or facts.get('needs_human_sugerido'))}\n"
            f"mensaje_cliente={mensaje}\n"
            f"historial_reciente={historial[:2500]}\n"
            f"planes_texto={str(planes)[:2500]}\n"
            f"texto_oficial_{intent}={str(texto_oficial)[:2500]}\n"
            f"memoria_instrucciones={memoria[:4500]}\n"
            f"hechos_tools={json.dumps(facts, ensure_ascii=False)[:5000]}\n"
            f"estilo={estilo[:1800]}"
        )
        # Un poco más de temperatura: menos fórmulas calcadas, más ajuste al hilo.
        text = gemini.generate(REPLY_SYSTEM, user, temperature=0.45)
        if not text:
            text = "¿En qué le podemos ayudar?"

        escalate = bool(state.get("needs_human") or facts.get("needs_human_sugerido") or intent == "handoff")
        motivo = None
        if escalate:
            motivo = "needs_human" if intent == "handoff" else (str(state.get("motivo_escalado") or intent))

        modo = "langgraph"
        if intent.startswith("cobertura"):
            modo = "langgraph_cobertura"
        elif intent == "saludo":
            modo = "langgraph_saludo"
        elif intent == "meta":
            modo = "langgraph_meta"
        elif intent in {"beneficios", "condiciones"}:
            modo = f"langgraph_{intent}"
        elif intent == "catalogo_internet":
            modo = "langgraph_catalogo"
        elif intent == "comprobante_aviso":
            modo = "langgraph_comprobante_aviso"
        elif escalate:
            modo = "langgraph_handoff"

        return {
            "answer": text,
            "escalate": escalate,
            "motivo_escalado": motivo,
            "modo": modo,
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
    proceso_pila: list[str] = []
    cliente_id = None
    try:
        snap = graph.get_state(config)
        vals = snap.values if snap else {}
        if isinstance(vals, dict):
            proceso = str(vals.get("proceso") or "none")
            raw_pila = vals.get("proceso_pila") or []
            if isinstance(raw_pila, list):
                proceso_pila = [str(x) for x in raw_pila if str(x)]
            cid = vals.get("cliente_id")
            cliente_id = int(cid) if isinstance(cid, int) else None
    except Exception:  # noqa: BLE001
        pass

    mensaje = str(payload.get("mensaje") or "").strip()
    if not mensaje:
        mensaje = str(payload.get("mensaje_hilo_reciente") or "").strip()

    init: AgentState = {
        "wa_id": wa_id,
        "mensaje": mensaje,
        "historial_texto": str(payload.get("historial_texto") or ""),
        "sandbox": bool(payload.get("sandbox")),
        "payload": payload,
        "proceso": proceso,
        "proceso_pila": proceso_pila,
        "sector": "meta",
        "cliente_id": cliente_id,
        "tool_facts": {},
        "tools_used": [],
        "answer": "",
        "intent": "",
        "modo": "",
        "escalate": False,
        "motivo_escalado": None,
        "needs_human": False,
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
        "proceso_pila": result.get("proceso_pila") or [],
        "proceso_cancelado": bool(result.get("proceso_cancelado")),
        "sector": result.get("sector") or "meta",
        "hechos": result.get("tool_facts") or {},
        "total_latency_ms": total_ms,
        "llm_latency_ms": result.get("llm_latency_ms"),
        "tokens_in": result.get("tokens_in"),
        "tokens_out": result.get("tokens_out"),
    }
