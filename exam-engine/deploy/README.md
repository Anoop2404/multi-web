# Exam engine: deployment and operations (`exam-engine/deploy`)

How to run the Go exam engine in production: DigitalOcean Bangalore (BLR1) behind Cloudflare,
**Stage A** (always on: 1 engine node + 1 Redis node + load balancer, about 2,000 students at once)
and **Stage B** (60,000 at once: extra engine nodes and a Redis replica added from the exam bookings).

- Plan: `docs/ONLINE_EXAM_ENGINE_PLAN_2026_10_15.md` (§3 load, §4 architecture, §8 security, §9 infrastructure, §10 gates)
- Contract: `exam-engine/CONTRACT.md` (§3 internal API, §9 engine env)
- Exam-day runbook: `docs/ONLINE_EXAM_RUNBOOK.md` · Laravel side: `docs/ONLINE_EXAM_DEPLOYMENT.md`

> **Changed from the plan:** Laravel talks to the engine **only over the engine's internal HTTPS API**
> (`/internal/*` with a bearer token, through Cloudflare, allowed only from the aaPanel server's IP).
> There is no WireGuard tunnel and Laravel never touches Redis. Only engine nodes reach Redis, inside the VPC.

## Contents

| Path | What |
|---|---|
| `systemd/exam-engine.service` | engine unit: user `exam`, `Restart=always`, `LimitNOFILE=200000`, graceful stop, hardening |
| `env/engine.env.example` | engine settings (CONTRACT §9). Your copy `env/engine.env` is pushed to every node |
| `env/ops.env.example` | settings for the scripts (DO, tags, sizes, Laravel host, automation) |
| `cloud-init/engine-node.yaml` | Ubuntu 24.04 engine node: users, sysctl/limits for many connections, unit, release installer, chrony, optional node_exporter. Also builds the golden image |
| `cloud-init/engine-node-from-snapshot.yaml` | boot config for burst nodes created from the golden snapshot |
| `cloud-init/redis-node.yaml` | Redis 7 node: noeviction, AOF everysec, password, VPC-only bind, THP off, Sentinel (Stage B), redis_exporter, daily snapshot |
| `redis/redis.conf.snippet`, `redis/sentinel.conf.example` | the Redis and Sentinel configuration written by cloud-init |
| `lb/README.md` | DigitalOcean LB settings and `doctl` commands. `lb/haproxy/` is the HAProxy alternative |
| `nginx/exam-site.conf` | Stage A-lite: nginx + engine on one droplet, no LB |
| `cloudflare/README.md` | DNS, SSL Full (strict), cache rules, WAF and rate-limit rules, settings to switch off, R2 |
| `monitoring/` | `prometheus.yml` (DO tag discovery), `alerts.yml`, `alertmanager.yml.example`, `grafana-dashboard.json` |
| `supervisor/exam-engine-consumer.ini` | Laravel event consumer on the aaPanel server |
| `scripts/` | build, image, scale, Redis, preflight, rollout and incident tools (below) |

## Scripts

All are bash (macOS bash 3.2 or Linux), `set -euo pipefail`, print usage with `-h`, and take `--dry-run`.
They read `env/ops.env` and keep state (locks, rendered user-data, the fleet's `known_hosts`) in `.state/`.
Nothing runs against your cloud accounts until you run them.

| Script | Does |
|---|---|
| `build-release.sh` | builds the client (Vite) and the engine (+ `loadgen`) for linux/amd64 and arm64 -> `dist/exam-engine-<ver>-linux-<arch>.tar.gz` + `SHA256SUMS` |
| `render-cloud-init.sh KIND` | fills a cloud-init template with your env files -> `.state/rendered/KIND.yaml` (for the hand-made base droplets) |
| `snapshot-image.sh` | golden droplet from cloud-init (image mode, no secrets) -> install release -> scrub -> snapshot -> delete droplet |
| `scale-out.sh N` | ensures **N engine nodes in total** (always-on included) from the newest snapshot, tagged `exam-engine`, waits for `/health`, version parity, LB attachment and public health. Idempotent |
| `scale-in.sh [--keep N]` | drains and deletes burst nodes (never the base node). Refuses while too many attempts are writing, or while the hold file exists |
| `resize-redis.sh up\|down\|status\|sentinel-setup` | Stage B: add a big replica and promote it via Sentinel (`up`), fail back and remove it (`down`). Stage A: offline power-cycle resize |
| `exam-day-preflight.sh` | read-only checks of the whole path: Cloudflare, LB, every node's health/version/limits/clock, Redis role/AOF/memory/replication/Sentinel, consumer lag, Laravel->engine reachability and token, consumer programs, Prometheus |
| `plan-and-scale.sh` | runs `php artisan exam-engine:plan-capacity` on the Laravel server over SSH and calls scale-out/in (and optionally resize-redis). Cron-safe |
| `rolling-update.sh` | new release and/or env to every node, one at a time, out of the LB while restarting |
| `bulk-extend.sh` | incident tool: extend time for all writing attempts of a run through the internal API (audit-logged via events) |
| `haproxy-sync-backends.sh` | `LB_KIND=haproxy` only: regenerate the HAProxy backend from the tagged droplets |
| `lib.sh` | shared helpers (sourced) |

## Topology

```
Students ──► Cloudflare (DNS · TLS · cache /assets/* and /api/bundle/* · WAF · rate limit)
                │  exam.<domain>
                ▼
DigitalOcean BLR1, VPC exam-blr1
   Load balancer (tag exam-engine, /health, no sticky sessions)
      ├─► exam-engine-1        c-2   always on    tags exam-engine, exam-engine-node, exam-engine-base
      └─► exam-engine-burst-NN c-4   busy slots   tags exam-engine, exam-engine-node, exam-engine-burst
                │ :6379 (AUTH, VPC only)            (Stage B: via Sentinel :26379)
                ▼
   redis-exam-1      s-2vcpu-4gb  always on, AOF, Prometheus/Alertmanager   tags exam-redis, exam-redis-base, exam-monitor
   redis-exam-burst  s-4vcpu-8gb  busy slots, promoted to primary           tags exam-redis, exam-redis-burst

aaPanel server (Laravel) ──HTTPS + bearer──► https://exam.<domain>/internal/*   (WAF: aaPanel IP only)
   supervisor exam-engine-consumer (2 procs) ── claim/ack events ── grade ── tenant Postgres
```

Ports: engine `8090` (from the LB and the monitor node), Redis `6379` and Sentinel `26379` (engine and
redis droplets only), node_exporter `9100`, redis_exporter `9121` (monitor node only), SSH `22`
(your admin IPs and the ops host). Prometheus `9090`, Alertmanager `9093` and Grafana `3000` listen on
localhost; use an SSH tunnel.

## What you do by hand

- Create and own the **DigitalOcean** team and the **Cloudflare** account (plan §11.2 #5), billing, 2FA, and a
  **droplet-limit increase** (Stage B + load tests need ~25 droplets).
- Choose the **exam domain** and put its DNS on Cloudflare (plan §11.2 #1).
- Generate the **secrets** (Redis password, internal token, launch key pair). They live in `env/*.env`,
  `env/redis.password` and Laravel's `.env`, and are never committed.
- Create the base droplets, firewalls, LB and Cloudflare rules (steps below). The scripts handle everything after that.

## Stage A, step by step

### 0. Tools and config
On your machine: `doctl`, `jq`, `ssh`, `curl`, `perl`; for building: Go 1.27 and Node 20+.
```bash
cd exam-engine/deploy
cp env/ops.env.example env/ops.env          # fill in as you go
cp env/engine.env.example env/engine.env
openssl rand -hex 32 > env/redis.password   # Redis password (hex only)
# EXAM_ENGINE_INTERNAL_TOKEN + the launch key pair: `sudo -u www php artisan exam-engine:keys` on the
# aaPanel server prints all three (or: openssl rand -hex 32 for the token)
chmod 600 env/*
```

### 1. DigitalOcean account
1. Create a team (e.g. "Sahodaya Exam") with 2FA, a billing alert (~$150/month), and a project "exam".
2. API tokens: one **full-access** token for the operator machine (`doctl auth init --context exam`; set
   `DO_CONTEXT=exam`), and one **read-only, droplet:read** token for Prometheus.
3. SSH key: `doctl --context exam compute ssh-key import exam-ops --public-key-file ~/.ssh/id_ed25519.pub`
   and put its ID in `DO_SSH_KEYS`.
4. VPC: `doctl --context exam vpcs create --name exam-blr1 --region blr1 --ip-range 10.122.0.0/20` (any
   unused range), and set `DO_VPC_UUID`.
5. Tags:
   ```bash
   for t in exam-engine exam-engine-node exam-engine-base exam-engine-burst exam-engine-draining \
            exam-redis exam-redis-base exam-redis-burst exam-monitor; do doctl --context exam compute tag create $t; done
   ```

### 2. Build a release
```bash
scripts/build-release.sh --test      # dist/exam-engine-<ver>-linux-amd64.tar.gz
# env/ops.env: ENGINE_RELEASE_TARBALL=dist/exam-engine-<ver>-linux-amd64.tar.gz
```
Scripts push the tarball over SSH. Alternatively host it (e.g. a private R2 bucket with a presigned URL)
and set `ENGINE_RELEASE_URL` + `ENGINE_RELEASE_SHA256`. Cloud-init then downloads and verifies it at boot.

### 3. Redis node
```bash
scripts/render-cloud-init.sh redis-primary
doctl --context exam compute droplet create redis-exam-1 --region blr1 --size s-2vcpu-4gb \
  --image ubuntu-24-04-x64 --vpc-uuid "$DO_VPC_UUID" --ssh-keys "$DO_SSH_KEYS" \
  --tag-names exam-redis,exam-redis-base,exam-monitor --enable-monitoring --enable-backups \
  --user-data-file .state/rendered/redis-primary.yaml --wait
rm .state/rendered/redis-primary.yaml
ssh ops@<public-ip> 'cloud-init status --wait; sudo exam-redis-cli INFO persistence | grep aof_enabled'
```
Note its **private IP** and put it in `EXAM_ENGINE_REDIS_URL=redis://:<password>@<private-ip>:6379/0` in `env/engine.env`.
Weekly DO backups (+20 %) plus the daily `BGSAVE` copy in `/var/backups/exam-redis` cover "AOF + daily snapshot".

### 4. Engine node (always on)
Fill in the rest of `env/engine.env` (internal token, launch public key from step 9), then:
```bash
scripts/render-cloud-init.sh engine
doctl --context exam compute droplet create exam-engine-1 --region blr1 --size c-2 \
  --image ubuntu-24-04-x64 --vpc-uuid "$DO_VPC_UUID" --ssh-keys "$DO_SSH_KEYS" \
  --tag-names exam-engine,exam-engine-node,exam-engine-base --enable-monitoring \
  --user-data-file .state/rendered/engine.yaml --wait
rm .state/rendered/engine.yaml
scripts/rolling-update.sh --only exam-engine-1      # pushes ENGINE_RELEASE_TARBALL (skip if you used a URL)
ssh ops@<public-ip> 'curl -s localhost:8090/health'    # {"ok":true,"redis":"ok","version":"..."}
```

### 5. Cloud firewalls
Create them after the LB (step 6) so the engine rule can name it. Until then, allow 8090 from the VPC range.
```bash
doctl --context exam compute firewall create --name exam-engine-fw --tag-names exam-engine-node \
  --inbound-rules "protocol:tcp,ports:8090,load_balancer_uid:LB_ID protocol:tcp,ports:8090,tag:exam-monitor protocol:tcp,ports:9100,tag:exam-monitor protocol:tcp,ports:26379,tag:exam-engine-node protocol:tcp,ports:26379,tag:exam-redis protocol:tcp,ports:22,address:ADMIN_IP/32" \
  --outbound-rules "protocol:tcp,ports:all,address:0.0.0.0/0,address:::/0 protocol:udp,ports:all,address:0.0.0.0/0,address:::/0 protocol:icmp,address:0.0.0.0/0,address:::/0"
doctl --context exam compute firewall create --name exam-redis-fw --tag-names exam-redis \
  --inbound-rules "protocol:tcp,ports:6379,tag:exam-engine-node protocol:tcp,ports:6379,tag:exam-redis protocol:tcp,ports:26379,tag:exam-engine-node protocol:tcp,ports:26379,tag:exam-redis protocol:tcp,ports:9121,tag:exam-monitor protocol:tcp,ports:9100,tag:exam-monitor protocol:tcp,ports:22,address:ADMIN_IP/32" \
  --outbound-rules "protocol:tcp,ports:all,address:0.0.0.0/0,address:::/0 protocol:udp,ports:all,address:0.0.0.0/0,address:::/0 protocol:icmp,address:0.0.0.0/0,address:::/0"
```
Add the ops host's IP to port 22 when you set it up (Stage B). The firewalls follow tags, so burst nodes are covered automatically.

### 6. Load balancer
Follow `lb/README.md`: upload the Cloudflare Origin certificate, create `exam-lb` targeting tag
`exam-engine` with health check `/health` and sticky sessions off, and an allow-list of Cloudflare's
ranges plus the aaPanel IP. Put its ID in `LB_ID`.
*Stage A-lite* (no LB): `nginx/exam-site.conf` on the engine droplet, `EXAM_ENGINE_ADDR=127.0.0.1:8090`,
`LB_KIND=nginx`, and open 80/443 to Cloudflare's ranges in the firewall.

### 7. Cloudflare
Follow `cloudflare/README.md`: proxied `exam` record -> LB IP, SSL Full (strict), cache rules, WAF rule
(`/internal/*` only from the aaPanel IP, `/metrics` blocked), `/api/login` rate limit, Bot Fight Mode
and friends off. Check:
```bash
curl -s https://exam.<domain>/health
curl -s -o /dev/null -w '%{http_code}\n' https://exam.<domain>/internal/runs/x/summary   # 403 from your machine
```

### 8. Monitoring (recommended from Stage A, required for Stage B)
On `redis-exam-1` (tag `exam-monitor`). It has the RAM headroom because Redis is capped at 60 % of memory:
```bash
sudo apt-get install -y prometheus prometheus-alertmanager
sudo cp prometheus.yml alerts.yml /etc/prometheus/ && sudo cp alertmanager.yml.example /etc/prometheus/alertmanager.yml  # then edit receivers
echo '<read-only DO token>' | sudo tee /etc/prometheus/do_token >/dev/null && sudo chown root:prometheus /etc/prometheus/do_token && sudo chmod 640 /etc/prometheus/do_token
echo 'ARGS="--web.listen-address=127.0.0.1:9090 --storage.tsdb.retention.time=30d"' | sudo tee /etc/default/prometheus
sudo promtool check config /etc/prometheus/prometheus.yml && sudo systemctl restart prometheus prometheus-alertmanager
```
Grafana: the Grafana Cloud free tier (`remote_write` block in prometheus.yml) or Grafana OSS from Grafana's
apt repository bound to 127.0.0.1 (`ssh -L 3000:127.0.0.1:3000 ops@<redis-ip>`). Import
`grafana-dashboard.json` and pick the Prometheus data source. As an independent second signal, add a
free **DigitalOcean Uptime check** on `https://exam.<domain>/health` with email alerts. Set
`MONITOR_HOST=<redis-exam-1 public IP>` in ops.env so the preflight checks Prometheus too.

### 9. Laravel (aaPanel server)
Full steps: `docs/ONLINE_EXAM_DEPLOYMENT.md`. In short, add to `/www/wwwroot/sahodaya.truecampus.in/.env`
(the **first** occurrence of a duplicated key wins, so remove old ones):
```env
EXAM_ENGINE_INTERNAL_URL=https://exam.<domain>
EXAM_ENGINE_INTERNAL_TOKEN=<same value as engine.env>
EXAM_ENGINE_PUBLIC_URL=https://exam.<domain>
EXAM_ENGINE_LAUNCH_SECRET_KEY=<secret half printed by php artisan exam-engine:keys>
EXAM_ENGINE_CAPACITY=2000
EXAM_ENGINE_SCALE_OUT_THRESHOLD=2000
```
`sudo -u www php artisan exam-engine:keys` prints three lines: `EXAM_ENGINE_LAUNCH_SECRET_KEY` (Laravel `.env`),
`EXAM_ENGINE_LAUNCH_PUBLIC_KEY` (`env/engine.env`, then `scripts/rolling-update.sh --env-only`) and a fresh
`EXAM_ENGINE_INTERNAL_TOKEN` (the same value on both sides). Run it once; running it again makes a new pair.
Then `sudo -u www php artisan optimize`, and install `supervisor/exam-engine-consumer.ini` (aaPanel
Supervisor, user `www`, 2 processes). Arming is automatic: the scheduler runs `exam-engine:arm-due` every 5 minutes.

### 10. Verify, load test, go live
```bash
scripts/exam-day-preflight.sh          # everything PASS
```
Then the Stage A load test (3,000 virtual students), chaos drills, a dry run with ≥ 200 real students,
and the Stage A gates. All of these are in `docs/ONLINE_EXAM_RUNBOOK.md`.

## Updating the engine

Outside the exam freeze (never between T-2h and close):
```bash
scripts/build-release.sh --test
scripts/rolling-update.sh --tarball dist/exam-engine-<ver>-linux-amd64.tar.gz   # node by node
scripts/snapshot-image.sh --prune 3      # Stage B: burst nodes boot the same version
scripts/exam-day-preflight.sh
```
Env-only change (token rotation, Sentinel switch): edit `env/engine.env`, then `scripts/rolling-update.sh --env-only`.
Rotating `EXAM_ENGINE_INTERNAL_TOKEN` needs Laravel's `.env` changed at the same moment (+ `php artisan optimize`,
restart the consumer). Do it in a quiet window.

## Stage B additions

1. **Droplet limit**: ask DigitalOcean support for ~25 droplets (burst nodes + load generators).
2. **Golden image**: `scripts/snapshot-image.sh` (re-run after each release; `scale-out.sh` also pushes
   `ENGINE_RELEASE_TARBALL` to nodes whose image is older).
3. **Sentinel** (one time, in a quiet window):
   ```bash
   scripts/resize-redis.sh sentinel-setup      # Sentinel on redis-exam-1 + exam-engine-1, prints env lines
   # env/engine.env: EXAM_ENGINE_REDIS_SENTINELS=..., EXAM_ENGINE_REDIS_MASTER=exam
   # env/ops.env:    REDIS_SENTINEL=yes
   scripts/rolling-update.sh --env-only
   ```
4. **Busy-slot Redis**: `scripts/resize-redis.sh up` adds `redis-exam-burst` (8 GB) as a replica, waits for
   sync and promotes it (a failover blip of a few seconds). `down` fails back and removes it. Rehearse
   both during the load test. The Stage A offline resize (`--method offline`) takes Redis down for minutes.
5. **Monitoring + alerts**: required. Alerts go to the on-call (Telegram/phone) via Alertmanager.
6. **Scale from bookings**: a small **ops host** (`s-1vcpu-1gb` in BLR1, ~$6/month) holds the DO token, the
   fleet SSH key and a copy of this folder, and runs every 15 minutes:
   ```cron
   */15 * * * * cd /opt/exam-ops/exam-engine/deploy && scripts/plan-and-scale.sh >> .state/plan-and-scale.log 2>&1
   ```
   On the aaPanel server, restrict the ops host's key to the one command it needs (in root's `authorized_keys`):
   ```
   command="cd /www/wwwroot/sahodaya.truecampus.in && sudo -u www php artisan exam-engine:plan-capacity",no-pty,no-port-forwarding,no-agent-forwarding,no-X11-forwarding ssh-ed25519 AAAA... exam-ops
   ```
   (Use your own unrestricted key for `exam-day-preflight.sh`.) Set `REDIS_AUTO_RESIZE=yes` once
   `resize-redis.sh up/down` has been rehearsed. Scale-out starts at T0-3h (`SCALE_LEAD_MINUTES=120` before the
   slot, which begins when the waiting room opens at T0-60).
   Starting rule (plan §9; calibrate with the 90k test): +1 engine node per 20,000 booked students, +1
   when `portal_password` is the only entry mode. `plan-capacity` computes it, and this folder only applies it.
7. **LB capacity**: if the 90k test shows the LB as the limit, raise its size units for busy slots (`lb/README.md`).
8. **Capacity in Laravel**: raise `EXAM_ENGINE_CAPACITY` (2,000 -> 15,000 -> 60,000) only after the matching gate passes (ramp rule).

`php artisan exam-engine:plan-capacity [--hours=72]` prints `{"generated_at", "capacity", "slots": [{"starts_at",
"ends_at", "students", "nodes", "bookings": [...]}]}` (overlapping bookings merged into one slot; `nodes` already
applies the +1 per 20,000 / +1 password-only rule). `plan-and-scale.sh` also accepts a few alternative key names.
Test with `scripts/plan-and-scale.sh --dry-run --plan-file plan.json --now 2026-11-20T01:00:00Z`.

## Approximate cost (plan §9; check DigitalOcean's and Cloudflare's pricing pages before budgeting)

| Item | Stage A (always on) | Stage B (per busy exam day) |
|---|---|---|
| exam-engine-1, CPU-Optimized c-2 (2 vCPU / 4 GB) | ≈ $42 / month | — |
| redis-exam-1, s-2vcpu-4gb + weekly backups | ≈ $24 + $5 / month | — |
| Load balancer, 1 node | ≈ $12 / month | extra nodes ≈ $0.02/h each, if needed |
| Golden snapshot (~3 GB) | < $1 / month | — |
| Cloudflare Free + R2 free tier; monitoring on the redis node / Grafana Cloud free | $0 | $0 |
| Burst engine nodes: 3 × c-4 (4 vCPU / 8 GB) × 6 h at ≈ $0.125/h | — | ≈ $2.25 |
| Redis replica s-4vcpu-8gb × 6 h | — | ≈ $0.45 |
| Ops host s-1vcpu-1gb (Stage B automation) | ≈ $6 / month | — |
| **Total** | **≈ $80–90 / month (≈ ₹7,000–8,000)**; Stage A-lite without LB ≈ $70 | **≈ $3–5 per busy day** |
| One-off 90k load test: ~10 load-generator c-4 droplets for ~3 h | | ≈ $5–10 |

## Engine metrics contract

`monitoring/alerts.yml` and `grafana-dashboard.json` expect these names on each node's `/metrics`.
If the engine uses different names, rename them in those two files.

| Metric | Type | Labels |
|---|---|---|
| `exam_http_requests_total` | counter | `route` (login, state, system_check, bundle, start, sync, resync, submit, result, logout, internal, health, static), `code` |
| `exam_http_request_duration_seconds` | histogram | `route` |
| `exam_http_inflight_requests` | gauge | |
| `exam_login_queue_depth` | gauge | |
| `exam_login_queue_wait_seconds` | histogram | |
| `exam_pace_seconds` | gauge (current auto/override `next_sync_in`) | |
| `exam_attempts` | gauge (from the Redis aggregates; every node may export the same values) | `run`, `state` |
| `exam_submits_total` | counter | `mode` (manual, auto, forced) |

Redis and stream metrics come from redis_exporter (`--check-single-streams=db0=ex:events`), and host
metrics from node_exporter. `scale-in.sh`, `resize-redis.sh` and `rolling-update.sh` read
`exam_attempts{state="writing"}` as their live-load guard. If the metric is missing they warn and skip the guard.

## Assumptions to confirm with the engine and Laravel owners

1. `/health` returns `{"ok":true,"redis":"ok","version":"<v>"}` and the version is stamped via `-X main.version`
   (override with `VERSION_LDFLAG`). If the engine `go:embed`s the client, set `CLIENT_EMBED_DIR` for `build-release.sh`.
2. With `EXAM_ENGINE_REDIS_SENTINELS` set, the engine still takes the Redis password from `EXAM_ENGINE_REDIS_URL`.
   Sentinels themselves have no password (VPC + firewall only).
3. `EXAM_ENGINE_TRUST_PROXY=true` uses the left-most `X-Forwarded-For` entry. nginx/HAProxy send a single
   value; the DO LB appends Cloudflare's IP after the student's.
4. The engine finishes in-flight requests on SIGTERM within 40 s (`TimeoutStopSec`).
5. `GET /internal/runs/<unknown>/summary` answers **404** when the token is valid and 401 when not. The preflight
   uses this to prove "reachable + authorised" without side effects.
6. The `ex:events` stream is capped at `MAXLEN ~500000` in code. At 60k writing, snapshots alone are ~500 events/s,
   so a Laravel outage longer than **~15 minutes** during writing starts trimming unconsumed events. Ask for
   the cap to be configurable, or raise it, before Stage B. Final answers also stay in the engine's attempt
   state for 7 days.
7. `php artisan exam-engine:consume --consumer=<name>` exits every hour (`--max-seconds=3600`) and supervisor
   restarts it with fresh code. A SIGTERM mid-batch leaves claimed events un-acked; the same consumer name
   re-claims them first after the restart (CONTRACT §3), and processing is idempotent. That's why the
   supervisor program uses stable `%(program_name)s_%(process_num)02d` names.
8. DigitalOcean droplets are amd64. The arm64 build is for other hosts and future use.

## Troubleshooting

| Symptom | Look at |
|---|---|
| `scale-out.sh` waits on a node forever | `ssh ops@<ip> 'cloud-init status --long; journalctl -u exam-engine -n 50'`; re-run (idempotent) |
| New node healthy but not getting traffic | it must carry tag `exam-engine`; `doctl compute load-balancer get $LB_ID` shows `droplet_ids` |
| Preflight: internal API 401 from Laravel | token differs between `engine.env` and Laravel `.env` (and run `php artisan optimize`) |
| Preflight: internal API 403 from Laravel | Cloudflare WAF rule 1 doesn't contain the aaPanel server's current IP |
| `/health` says `redis: error` | Redis down or password wrong; `sudo exam-redis-cli PING` on the redis node; firewall 6379 |
| Redis memory alert | `sudo exam-redis-cli INFO memory`, `XLEN ex:events`, consumer lag. See runbook I10 |
| SSH "host key changed" for a new droplet | the scripts keep their own `.state/known_hosts` and forget IPs on create; for manual SSH, `ssh-keygen -R <ip>` |
