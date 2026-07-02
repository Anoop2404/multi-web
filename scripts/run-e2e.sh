#!/usr/bin/env bash
# Run Playwright E2E / UX audit against local demo environment.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

PORT="${E2E_PORT:-8000}"
export E2E_SAHODAYA_URL="${E2E_SAHODAYA_URL:-http://malappuramsahodaya.test:${PORT}}"
export E2E_SUPERADMIN_URL="${E2E_SUPERADMIN_URL:-http://superadmin.test:${PORT}}"

if ! grep -q "malappuramsahodaya.test" /etc/hosts 2>/dev/null; then
  echo "Demo hosts missing. Run: ./scripts/add-demo-hosts.sh"
  exit 1
fi

echo "==> Provisioning E2E users (all roles)"
php artisan e2e:provision-users 2>/dev/null || php artisan db:seed --class=DemoTenantsSeeder && php artisan e2e:provision-users

echo "==> Seeding E2E demo data (events, MCQ, training)"
php artisan e2e:seed-data 2>/dev/null || true

echo "==> Checking app at $E2E_SAHODAYA_URL"
if ! curl -sf -o /dev/null -m 8 "$E2E_SAHODAYA_URL/login"; then
  echo "Starting Laravel server on port $PORT..."
  php artisan serve --host=0.0.0.0 --port="$PORT" &
  SERVER_PID=$!
  sleep 3
  for i in 1 2 3 4 5 6 7 8 9 10; do
    if curl -sf -o /dev/null -m 3 "$E2E_SAHODAYA_URL/login"; then break; fi
    sleep 1
  done
  if ! curl -sf -o /dev/null -m 3 "$E2E_SAHODAYA_URL/login"; then
    echo "App still not reachable at $E2E_SAHODAYA_URL/login"
    kill $SERVER_PID 2>/dev/null || true
    exit 1
  fi
  echo "Server started (pid $SERVER_PID)"
fi

if [[ ! -d node_modules/@playwright/test ]]; then
  echo "==> Installing Playwright..."
  npm install
  npx playwright install chromium
fi

PROJECT="${1:-all}"
REPORT_DIR="tests/e2e/report"

case "$PROJECT" in
  public)     CMD="npx playwright test --project=public" ;;
  sahodaya)   CMD="npx playwright test --project=sahodaya-admin" ;;
  school)     CMD="npx playwright test --project=school-admin --project=mobile-school" ;;
  superadmin) CMD="npx playwright test --project=superadmin" ;;
  all)        CMD="npx playwright test" ;;
  *)          CMD="npx playwright test $*" ;;
esac

echo "==> Running: $CMD"
eval "$CMD"

echo ""
echo "Reports:"
echo "  HTML:      $REPORT_DIR/index.html"
echo "  UX JSON:   $REPORT_DIR/ux-audit.json"
echo ""
echo "Open HTML report: npx playwright show-report $REPORT_DIR"
