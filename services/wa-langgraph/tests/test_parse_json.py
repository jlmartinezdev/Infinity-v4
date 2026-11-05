import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))

from app.gemini_client import parse_json_object


def test_parse_plain():
    assert parse_json_object('{"intent":"saldo","confidence":0.9}')["intent"] == "saldo"


def test_parse_fenced():
    raw = '```json\n{"intent":"meta","confidence":0.8}\n```'
    assert parse_json_object(raw)["intent"] == "meta"


if __name__ == "__main__":
    test_parse_plain()
    test_parse_fenced()
    print("ok")
