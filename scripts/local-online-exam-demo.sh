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
APP_ENV=testing php artisan migrate --force
APP_ENV=local php artisan db:seed --class=LocalOnlineExamDemoSeeder --force
APP_ENV=local php artisan serve --host=127.0.0.1 --port=8773
