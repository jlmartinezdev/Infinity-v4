from __future__ import annotations

import json
import os
import re
import time
from typing import Any

import httpx

DEFAULT_MODEL = os.getenv("WA_AGENT_GEMINI_MODEL", "gemini-3.6-flash")


class GeminiClient:
    def __init__(self, api_key: str | None = None, model: str | None = None) -> None:
        self.api_key = (api_key or os.getenv("WA_AGENT_GEMINI_API_KEY") or "").strip()
        self.model = (model or DEFAULT_MODEL).removeprefix("models/")
        self.tokens_in = 0
        self.tokens_out = 0
        self.latency_ms = 0

    def generate(self, system: str, user: str, temperature: float = 0.2) -> str:
        if not self.api_key:
            raise RuntimeError("gemini_api_key_vacia")

        url = (
            f"https://generativelanguage.googleapis.com/v1beta/models/"
            f"{self.model}:generateContent?key={self.api_key}"
        )
        body = {
            "systemInstruction": {"parts": [{"text": system}]},
            "contents": [{"role": "user", "parts": [{"text": user}]}],
            "generationConfig": {
                "temperature": temperature,
                "maxOutputTokens": 1536,
            },
        }
        started = time.perf_counter()
        with httpx.Client(timeout=45.0) as client:
            resp = client.post(url, json=body)
        self.latency_ms += int((time.perf_counter() - started) * 1000)
        if resp.status_code >= 400:
            raise RuntimeError(f"gemini_http_{resp.status_code}: {resp.text[:300]}")
        data = resp.json()
        usage = data.get("usageMetadata") or {}
        self.tokens_in += int(usage.get("promptTokenCount") or 0)
        self.tokens_out += int(usage.get("candidatesTokenCount") or usage.get("totalTokenCount") or 0)
        candidates = data.get("candidates") or []
        cand0 = candidates[0] if candidates else {}
        parts = ((cand0.get("content") or {}).get("parts") or [])
        texts = []
        for p in parts:
            if not isinstance(p, dict):
                continue
            # Modelos con "thought" pueden devolver partes no visibles al cliente.
            if p.get("thought") is True:
                continue
            t = str(p.get("text") or "").strip()
            if t:
                texts.append(t)
        out = "\n".join(texts).strip()
        if not out:
            finish = str(cand0.get("finishReason") or data.get("promptFeedback") or "empty")
            raise RuntimeError(f"gemini_empty_response:{finish}:{resp.text[:200]}")
        return out

    def generate_json(self, system: str, user: str) -> dict[str, Any]:
        raw = self.generate(system, user, temperature=0.0)
        return parse_json_object(raw)


def parse_json_object(raw: str) -> dict[str, Any]:
    text = raw.strip()
    if text.startswith("```"):
        text = re.sub(r"^```(?:json)?\s*", "", text)
        text = re.sub(r"\s*```$", "", text)
    try:
        data = json.loads(text)
        return data if isinstance(data, dict) else {}
    except json.JSONDecodeError:
        m = re.search(r"\{.*\}", text, re.DOTALL)
        if not m:
            return {}
        try:
            data = json.loads(m.group(0))
            return data if isinstance(data, dict) else {}
        except json.JSONDecodeError:
            return {}
