# Online exam: deployment index

Where everything for the online exam engine lives, and the Laravel deploy steps for the exam features.

| Document | For |
|---|---|
| [ONLINE_EXAM_ENGINE_PLAN_2026_10_15.md](ONLINE_EXAM_ENGINE_PLAN_2026_10_15.md) | why and what: architecture, load model, decisions, gates |
| `exam-engine/CONTRACT.md` | engine <-> client <-> Laravel API, events, engine env (§9) |
| **`exam-engine/deploy/README.md`** | infrastructure step by step (DigitalOcean BLR1 + Cloudflare), Stage A and Stage B, scripts, costs, metrics contract |
| `exam-engine/deploy/lb/README.md` · `cloudflare/README.md` | load balancer (DO LB / HAProxy / nginx) and Cloudflare rules |
| **[ONLINE_EXAM_RUNBOOK.md](ONLINE_EXAM_RUNBOOK.md)** | exam day: roles, timeline, support playbook, incidents, load tests, chaos drills, go-live gates, ramp rule |
| [erp/24-LIVE_SERVER_DEPLOYMENT.md](erp/24-LIVE_SERVER_DEPLOYMENT.md) | general Laravel production deploy (backups, tenancy, workers) |

## Order for a first setup
1. Engine infrastructure: `exam-engine/deploy/README.md`, Stage A steps 0–8 (Redis, engine node, firewalls, LB, Cloudflare, monitoring).
2. Laravel: the steps below.
3. `exam-engine/deploy/scripts/exam-day-preflight.sh`: everything PASS.
4. Load test, chaos drills, dry run, Stage A gates: runbook §6–§8.
5. First real exam, following the ramp rule (runbook §9).

## Laravel deploy for the exam features (aaPanel server)

App: `/www/wwwroot/sahodaya.truecampus.in`. Run artisan **as `www`**; files created as root break the site.
Never deploy between T-2h and the close of any booked exam. Check first:
`sudo -u www php artisan exam-engine:plan-capacity --hours=24` (no slot starting within the next hours).

```bash
cd /www/wwwroot/sahodaya.truecampus.in

# 0. Backups (central + every Sahodaya tenant DB), as in erp/24-LIVE_SERVER_DEPLOYMENT.md
# 1. Code
git pull
composer install --no-dev --optimize-autoloader          # when composer.lock changed

# 2. Central migration: exam_engine_bookings (platform-wide capacity bookings)
sudo -u www php artisan migrate --force

# 3. Tenant migrations: question types, paper sections, passcodes (2026_11_25_000001_...) and later engine columns
sudo -u www php artisan tenants:migrate-sahodayas --force
#    (alternative that also reports per-Sahodaya failures: php artisan sahodaya:provision-databases --no-create)

# 4. Front-end build: needed for KaTeX formulas and the exam pages
npm ci && npm run build

# 5. .env keys (first occurrence of a key wins: remove old duplicates), see below
# 6. Launch-token key pair + internal token: ONCE per environment, see below
# 7. Caches, workers, consumer
sudo -u www php artisan optimize
sudo -u www php artisan queue:restart
supervisorctl restart 'exam-engine-consumer:*'
chown -R www:www storage bootstrap/cache                 # only if something ran as root
```

### `.env` keys
```env
# Engine internal API (through Cloudflare; the WAF lets only this server's IP reach /internal/*)
EXAM_ENGINE_INTERNAL_URL=https://exam.<domain>
EXAM_ENGINE_INTERNAL_TOKEN=<64 hex chars, identical in exam-engine/deploy/env/engine.env>
# Exam site students open (the Sahodaya code is appended)
EXAM_ENGINE_PUBLIC_URL=https://exam.<domain>
# Ed25519 secret key for portal "Enter exam" launch tokens (public half goes to the engine)
EXAM_ENGINE_LAUNCH_SECRET_KEY=<base64>
# Platform capacity per time slot. Raise only per the ramp rule: 2000 -> 15000 -> 60000
EXAM_ENGINE_CAPACITY=2000
EXAM_ENGINE_SCALE_OUT_THRESHOLD=2000
# Optional: EXAM_ENGINE_TIMEOUT=15, EXAM_ENGINE_MAX_IMAGE_BYTES=409600
```

### Keys
```bash
sudo -u www php artisan exam-engine:keys
```
It prints `EXAM_ENGINE_LAUNCH_SECRET_KEY` (Laravel `.env`), `EXAM_ENGINE_LAUNCH_PUBLIC_KEY` (engine
`env/engine.env`) and a fresh `EXAM_ENGINE_INTERNAL_TOKEN` (both sides). Run it once. A new pair invalidates
launch tokens in flight, and a new token must reach Laravel and every engine node at the same moment
(`scripts/rolling-update.sh --env-only`, then `php artisan optimize` and restart the consumer), so do it in a quiet window.

### Consumer program (first time)
Install `exam-engine/deploy/supervisor/exam-engine-consumer.ini` through aaPanel's Supervisor (program
`exam-engine-consumer`, user `www`, 2 processes, `php artisan exam-engine:consume --consumer=%(program_name)s_%(process_num)02d`),
then `supervisorctl reread && supervisorctl update`. Raise to 4–8 processes when booked concurrency
exceeds 10,000. The existing `schedule:run` cron already runs `exam-engine:arm-due` every 5 minutes.

### Verify
```bash
sudo -u www php artisan tinker --execute='echo json_encode(app(App\Services\ExamEngine\ExamEngineClient::class)->health());'
#   {"ok":true,"redis":"ok","version":"..."}
supervisorctl status 'exam-engine-consumer:*'           # all RUNNING
```
Then, from the operator machine, `exam-engine/deploy/scripts/exam-day-preflight.sh`. Its Laravel section
checks the `.env` keys, that the token is accepted on `/internal/*`, the consumer programs, and the clock.

### Stage B: capacity automation access
The ops host runs `plan-and-scale.sh`, which calls `exam-engine:plan-capacity` over SSH. Give it a key
restricted to that one command (forced command in `authorized_keys`; see `exam-engine/deploy/README.md`, Stage B step 6).

## Rollback
- **Laravel**: deploy the previous commit. The exam migrations are additive. An exam can go back to the in-app
  path with `settings_json.engine = 'laravel'` (single-correct and true/false papers only).
- **Engine**: `scripts/rolling-update.sh --tarball dist/<previous>.tar.gz`. For a fast manual rollback on one node,
  the last 3 releases are kept on disk:
  `sudo ln -sfn /opt/exam-engine/releases/<prev> /opt/exam-engine/current && sudo systemctl restart exam-engine`.
