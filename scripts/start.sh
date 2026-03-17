#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

DOMAIN="${DOMAIN:-nsa.local}"
HOSTS_LINE="127.0.0.1 ${DOMAIN}"
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

if [ ! -f .env ]; then
  cp .env.example .env
  echo "[INFO] Created .env from .env.example"
fi

if ! grep -Eq "(^|\s)${DOMAIN}(\s|$)" /etc/hosts; then
  echo "[WARN] /etc/hosts does not contain '${DOMAIN}'."
  echo "Run this once:"
  echo "  echo \"${HOSTS_LINE}\" | sudo tee -a /etc/hosts"
fi

if [ "$FRESH" -eq 1 ]; then
  echo "[INFO] Fresh mode enabled: removing containers + volumes first..."
  docker compose down -v --remove-orphans || true
fi

echo "[INFO] Starting services..."
docker compose up -d --build

echo "[INFO] Checking db-master health..."
DB_MASTER_ID="$(docker compose ps -q db-master 2>/dev/null || true)"
if [ -n "$DB_MASTER_ID" ]; then
  DB_MASTER_HEALTH="$(docker inspect --format '{{if .State.Health}}{{.State.Health.Status}}{{else}}{{.State.Status}}{{end}}' "$DB_MASTER_ID" 2>/dev/null || true)"
  if [ "$DB_MASTER_HEALTH" != "healthy" ] && [ "$DB_MASTER_HEALTH" != "running" ]; then
    echo "[WARN] db-master is not healthy yet (status: ${DB_MASTER_HEALTH:-unknown}). Showing diagnostic logs:"
    docker compose logs --tail=120 db-master || true
    echo
    echo "[HINT] Common fix if this persists: root password in .env changed after initial DB volume creation."
    echo "       Run: ./scripts/start.sh --fresh"
  fi
fi

echo "[INFO] Waiting for HTTPS endpoint..."
for _ in {1..45}; do
  if curl -kfsS "https://${DOMAIN}:8443/" >/dev/null 2>&1; then
    echo "[OK] Project is up."
    echo "Web app:      https://${DOMAIN}:8443/"
    echo "phpMyAdmin:   https://${DOMAIN}:8443/phpmyadmin/"
    echo "MailHog:      https://${DOMAIN}:8443/mail/"
    echo "GoAccess:     https://${DOMAIN}:8443/logs/"
    exit 0
  fi
  sleep 2
done

echo "[WARN] Stack started, but app did not respond in time."
echo "Check logs with: docker compose logs -f nginx webapp1 webapp2 db-master phpmyadmin"