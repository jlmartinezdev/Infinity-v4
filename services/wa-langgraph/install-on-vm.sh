#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
# Producción Interplus: /home/infinity/infinity-v4
APP="$ROOT/services/wa-langgraph"
cd "$APP"
python3 -m venv .venv
.venv/bin/pip install -U pip
.venv/bin/pip install -r requirements.txt
if [[ -f systemd/wa-langgraph.service ]]; then
  sudo cp systemd/wa-langgraph.service /etc/systemd/system/wa-langgraph.service
  sudo systemctl daemon-reload
  sudo systemctl enable wa-langgraph
  sudo systemctl restart wa-langgraph
fi
curl -sf http://127.0.0.1:8091/health || true
echo "wa-langgraph install done"
