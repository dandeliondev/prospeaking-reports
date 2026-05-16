#!/usr/bin/env bash
set -euo pipefail

cd "$(dirname "$0")/.."

if ! command -v docker >/dev/null 2>&1; then
    echo "Docker is required. Install Docker Desktop or docker-engine, then run again."
    exit 1
fi

echo "Starting ProSpeaking local UI (MySQL + PHP)..."
docker compose up --build -d

echo ""
echo "Waiting for database..."
for i in $(seq 1 30); do
    if docker compose exec -T db mysqladmin ping -h 127.0.0.1 -uroot -pprospeaking --silent 2>/dev/null; then
        break
    fi
    sleep 2
done

echo ""
echo "Ready. Open in your browser:"
echo "  http://localhost:8080/Reports/DPH/index.php"
echo ""
echo "Click Submit to load dummy report rows."
echo ""
echo "Stop:  docker compose down"
echo "Reset: docker compose down -v && ./dev/start.sh"
