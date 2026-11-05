from __future__ import annotations

import os
from typing import Any

from dotenv import load_dotenv
from fastapi import FastAPI, Header, HTTPException
from pydantic import BaseModel, ConfigDict, Field

from .graph import run_turn

load_dotenv()

app = FastAPI(title="Interplus WA LangGraph", version="1.0.0")


class TurnRequest(BaseModel):
    model_config = ConfigDict(extra="allow")

    wa_id: str | None = None
    mensaje: str | None = None
    mensaje_hilo_reciente: str | None = None
    sandbox: bool = False
    tools_base_url: str | None = None


class TurnResponse(BaseModel):
    reply: str | None = None
    escalate: bool = False
    cliente_id: int | None = None
    motivo_escalado: str | None = None
    modo: str | None = None
    intent: str | None = None
    confidence: float | None = None
    tools_used: list[dict[str, Any]] = Field(default_factory=list)
    proceso_activo: str | None = None
    proceso_cancelado: bool = False
    hechos: dict[str, Any] = Field(default_factory=dict)
    total_latency_ms: int | None = None
    llm_latency_ms: int | None = None
    tokens_in: int | None = None
    tokens_out: int | None = None


def _check_secret(header: str | None) -> None:
    expected = (
        os.getenv("N8N_WA_AGENT_SECRET") or os.getenv("WA_AGENT_SECRET") or ""
    ).strip()
    if expected and (header or "").strip() != expected:
        raise HTTPException(status_code=401, detail="unauthorized")


@app.get("/health")
def health() -> dict[str, str]:
    return {"status": "ok", "service": "wa-langgraph"}


@app.post("/v1/turn", response_model=TurnResponse)
def turn(
    body: TurnRequest,
    x_interplus_secret: str | None = Header(default=None, alias="X-Interplus-Secret"),
) -> TurnResponse:
    _check_secret(x_interplus_secret)
    payload = body.model_dump()
    # Pydantic v2: model_dump; also accept raw extras via __pydantic_extra__
    if hasattr(body, "model_extra") and body.model_extra:
        payload.update(body.model_extra)
    try:
        out = run_turn(payload)
    except Exception as e:  # noqa: BLE001
        raise HTTPException(status_code=500, detail=f"langgraph_error: {e}") from e
    return TurnResponse(**out)
