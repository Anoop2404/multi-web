#!/usr/bin/env bash
# Run Stage A/B config gates that work on a laptop clone (no DO / Cloudflare).
# Exit non-zero only when Stage A fails — Stage B capacity is expected to fail until ramp.
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

echo "== exam-engine:stage-gates --stage=A =="
php artisan exam-engine:stage-gates --stage=A --json
echo
echo "== exam-engine:stage-gates --stage=B (capacity often fails locally — expected) =="
php artisan exam-engine:stage-gates --stage=B --json || true
echo
echo "== exam-engine:plan-capacity --hours=72 =="
php artisan exam-engine:plan-capacity --hours=72
echo
echo "== mcq:transition-exam-windows (dry listing via help) =="
php artisan mcq:transition-exam-windows --help | head -20
echo
echo "Infra still blocked without DO: load/chaos, exam-day-preflight.sh, resize-redis, Cloudflare."
echo "See docs/online-exam-stage-ab-checklist.md (project store) and docs/ONLINE_EXAM_RUNBOOK.md §6–§8."
