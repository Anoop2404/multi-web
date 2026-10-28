#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
mkdir -p storage/app/local-online-demo storage/framework/sessions storage/framework/views storage/framework/cache/data
export DB_CONNECTION=central DB_DRIVER=sqlite DB_URL='' TENANCY_DATABASE_PER_SAHODAYA=false
export DB_DATABASE="$PWD/storage/app/local-online-demo/online-exam-demo.sqlite"
export CACHE_STORE=array SESSION_DRIVER=file SESSION_SECURE_COOKIE=false SESSION_COOKIE=local_online_exam_demo
export QUEUE_CONNECTION=sync MAIL_MAILER=array
export APP_URL=http://exam-demo.localhost:8773
# This file is separate from the normal local database. Never migrate:fresh.
touch "$DB_DATABASE"

# School model exams / practice system-check pages are Vite-code-split. A stale
# public/build (or none) yields blank Inertia screens (HTTP 200). Rebuild when missing.
need_build=0
if [[ ! -f public/build/manifest.json ]]; then
  need_build=1
elif ! grep -q 'School/ModelExams/Index.vue' public/build/manifest.json 2>/dev/null; then
  need_build=1
elif ! grep -q 'Portal/Student/McqSystemCheck.vue' public/build/manifest.json 2>/dev/null; then
  need_build=1
fi
if [[ "$need_build" -eq 1 ]]; then
  echo "→ npm run build (Model exams / system-check missing from public/build)…"
  npm run build
fi

APP_ENV=testing php artisan migrate --force
APP_ENV=local php artisan db:seed --class=LocalOnlineExamDemoSeeder --force
echo "Demo: http://exam-demo.localhost:8773  (or http://127.0.0.1:8773)"
echo "If admin pages are blank after pulling new Vue routes, run: npm run build"
APP_ENV=local php artisan serve --host=127.0.0.1 --port=8773
