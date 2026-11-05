from __future__ import annotations

import os
import time
from typing import Any

import httpx


class ToolsClient:
    def __init__(
        self,
        base_url: str,
        secret: str,
        *,
        sandbox: bool = False,
    ) -> None:
        self.base_url = base_url.rstrip("/")
        self.secret = secret
        self.sandbox = sandbox
        self.used: list[dict[str, Any]] = []

    def _headers(self) -> dict[str, str]:
        h = {"Accept": "application/json", "Content-Type": "application/json"}
        if self.secret:
            h["X-Interplus-Secret"] = self.secret
        return h

    def call(self, path: str, *, method: str = "POST", json_body: dict | None = None) -> dict[str, Any]:
        url = f"{self.base_url}/{path.lstrip('/')}"
        started = time.perf_counter()
        try:
            with httpx.Client(timeout=25.0) as client:
                if method.upper() == "GET":
                    resp = client.get(url, headers=self._headers())
                else:
                    resp = client.post(url, headers=self._headers(), json=json_body or {})
            ms = int((time.perf_counter() - started) * 1000)
            ok = resp.status_code < 400
            data = resp.json() if resp.headers.get("content-type", "").startswith("application/json") else {}
            payload = data.get("data") if isinstance(data, dict) and "data" in data else data
            self.used.append(
                {
                    "tool": path,
                    "ok": ok,
                    "latency_ms": ms,
                    "status": resp.status_code,
                }
            )
            return payload if isinstance(payload, dict) else {"raw": payload}
        except Exception as e:  # noqa: BLE001
            ms = int((time.perf_counter() - started) * 1000)
            self.used.append({"tool": path, "ok": False, "latency_ms": ms, "error": str(e)})
            return {"ok": False, "error": str(e)}

    def buscar_cuenta(self, telefono: str, texto: str = "") -> dict[str, Any]:
        return self.call("buscar-cuenta", json_body={"telefono": telefono, "texto": texto})

    def saldo_y_plan(self, telefono: str, texto: str = "", cliente_id: int | None = None) -> dict[str, Any]:
        body: dict[str, Any] = {"telefono": telefono, "texto": texto}
        if cliente_id:
            body["cliente_id"] = cliente_id
        return self.call("saldo-y-plan", json_body=body)

    def catalogo_planes(self) -> dict[str, Any]:
        return self.call("catalogo-planes", method="GET")

    def textos_canned(self) -> dict[str, Any]:
        return self.call("textos-canned", method="GET")

    def zona(self, telefono: str, texto: str = "") -> dict[str, Any]:
        return self.call("zona", json_body={"telefono": telefono, "texto": texto})

    def conocimiento(self, mensaje: str, etiqueta: str | None = None) -> dict[str, Any]:
        return self.call(
            "conocimiento",
            json_body={"mensaje": mensaje, "etiqueta": etiqueta or "", "max": 3},
        )

    def diagnosticar(self, telefono: str, texto: str = "") -> dict[str, Any]:
        return self.call(
            "diagnosticar",
            json_body={"telefono": telefono, "texto": texto, "sandbox": self.sandbox},
        )


def default_tools_base() -> str:
    return os.getenv(
        "WA_AGENT_TOOLS_BASE_URL",
        "http://127.0.0.1/api/v1/wa-agent/tools",
    )
