#!/usr/bin/env bash
# Quick Laravel log viewer for production
# Usage:
#   ./logs.sh          — tail Laravel log (last 50 lines, follow)
#   ./logs.sh -n 200   — last 200 lines
#   ./logs.sh docker   — raw Docker container logs (stdout/stderr)
#   ./logs.sh artisan  — open a shell in the app container

set -euo pipefail

DC="docker compose --env-file .env.production -f docker-compose.prod.yml"
LINES="${2:-50}"

case "${1:-laravel}" in
  laravel|"")
    echo "=== Laravel log (last $LINES lines, following) ==="
    $DC exec app tail -n "$LINES" -f storage/logs/laravel.log
    ;;
  -n)
    echo "=== Laravel log (last $LINES lines, following) ==="
    $DC exec app tail -n "$LINES" -f storage/logs/laravel.log
    ;;
  docker)
    echo "=== Docker container logs ==="
    $DC logs -f --tail="${2:-100}" app
    ;;
  artisan)
    $DC exec app sh
    ;;
  *)
    echo "Usage: $0 [laravel|-n <lines>|docker|artisan]"
    exit 1
    ;;
esac
