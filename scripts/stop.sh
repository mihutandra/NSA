#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

FRESH=0

if [ "${1:-}" = "--fresh" ]; then
	FRESH=1
fi

if ! command -v docker >/dev/null 2>&1; then
	echo "[ERROR] docker is not installed."
	echo "Install Docker + Compose plugin first, then retry."
	exit 1
fi

if ! docker info >/dev/null 2>&1; then
	echo "[ERROR] Docker daemon is not running or not accessible."
	echo "Try: sudo systemctl start docker"
	exit 1
fi

if [ "$FRESH" -eq 1 ]; then
	echo "[INFO] Fresh stop mode enabled: stopping containers and removing volumes + orphans..."
	docker compose down -v --remove-orphans
	echo "[OK] Stack stopped. Containers, networks, and volumes removed."
else
	echo "[INFO] Stopping services and removing orphan containers..."
	docker compose down --remove-orphans
	echo "[OK] Stack stopped. Containers and networks removed (volumes kept)."
fi
