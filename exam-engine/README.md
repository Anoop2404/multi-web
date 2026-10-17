# Exam engine

The Go service that runs the **live** online exam: the separate exam login page, check-in and
system check, waiting room, encrypted paper delivery and key release, timer, answer sync, proctor
signals, submit, the auto-submit sweeper and the live-monitor feed. Laravel stays the control
plane (setup, roster, grading, results). Everything between the two is specified in
[`CONTRACT.md`](CONTRACT.md); engine-side decisions and clarifications are in
[`NOTES-engine.md`](NOTES-engine.md). Background: `docs/ONLINE_EXAM_ENGINE_PLAN_2026_10_15.md`.

```
cmd/engine        HTTP server + sweeper (one static binary)
cmd/loadgen       self-contained load generator with answer verification
internal/
  config          EXAM_ENGINE_* environment
  store           Redis client, key layout (hash tag {run}), Lua scripts (lua/*.lua)
  engine          run-context cache, sessions, login, state/start/sync/resync/submit/result
  entry           passcode HMAC, Ed25519 launch tokens, bounded bcrypt pool
  attempt         clock rules: run state, deadlines, late entry, run choice
  syncer          canonical checksum, answer validation per question type, diff apply, pacing
  paper           bundle validation/decryption (schema extraction), in-memory bundle cache
  admin           internal API (§2–3): arm, roster, bundle, monitor, actions, broadcast, events
  events          ex:events claim/ack (consumer group `laravel`), trimming
  sweeper         leader-elected auto-submit, snapshots, event trimming
  server          routes (Go 1.22 patterns), middleware, student handlers
  web             serves the client SPA (EXAM_ENGINE_STATIC_DIR or embedded internal/web/dist)
  metrics, httpx, ratelimit, model
```

Dependencies: `github.com/redis/go-redis/v9` and `golang.org/x/crypto` (bcrypt) only.

## Build, test, run

Needs Go 1.27 and Redis ≥ 7 (local: `127.0.0.1:6379`).

```sh
make client     # npm ci + build exam-engine/client, copy client/dist → internal/web/dist (skipped if no client)
make build      # bin/exam-engine (version stamped with -X main.version) and bin/loadgen
make test       # go vet + unit tests + Redis integration tests (database 15 only, FLUSHDB'd)
make run        # engine on :8090 against redis://127.0.0.1:6379/14 with a local dev token
make loadgen    # in another terminal: 2000 students against the running engine
```

`go test ./...` works without make. Integration tests (`internal/server`) use only Redis database 15
and are skipped when Redis isn't reachable (`EXAM_ENGINE_TEST_REDIS` overrides the URL; it must be
database 15).

`make client` replaces the placeholder `internal/web/dist/index.html` with the built client; the
built files are git-ignored and the placeholder should not be committed over. Production builds can
instead leave the embed alone and set `EXAM_ENGINE_STATIC_DIR` to the built client.

## Configuration

All settings are environment variables; [`.env.example`](.env.example) documents every one.
The essentials:

| Variable | Default | Notes |
|---|---|---|
| `EXAM_ENGINE_ADDR` | `:8090` | |
| `EXAM_ENGINE_REDIS_URL` | `redis://127.0.0.1:6379/0` | password/db/TLS (`rediss://`) come from here, also with Sentinel |
| `EXAM_ENGINE_REDIS_SENTINELS`, `EXAM_ENGINE_REDIS_MASTER` | — | Sentinel failover client |
| `EXAM_ENGINE_INTERNAL_TOKEN` | — | required for `/internal/*` (empty = every internal call is refused) |
| `EXAM_ENGINE_LAUNCH_PUBLIC_KEY` | — | base64 Ed25519 public key from `php artisan exam-engine:keys` |
| `EXAM_ENGINE_STATIC_DIR` | embedded | built client directory |
| `EXAM_ENGINE_BUNDLE_BASE_URL` | `/api/bundle` | CDN base for papers |
| `EXAM_ENGINE_TRUST_PROXY` | false | client IP from CF-Connecting-IP / left-most X-Forwarded-For |
| `EXAM_ENGINE_BCRYPT_WORKERS` | CPU cores | password-check pool; logins wait ≤ 20 s then `503 busy` |
| `EXAM_ENGINE_PACE_THRESHOLDS` | `500,1500,3000` | in-flight requests that raise `next_sync_in` to 20/30/60 |
| `EXAM_ENGINE_EVENTS_HARD_CAP` | `0` (off) | optional emergency cap on `ex:events` |
| `EXAM_ENGINE_SNAPSHOT_SECONDS` | `300` | answer snapshot cadence per changed attempt |

## How Laravel connects

Laravel's `config/exam_engine.php` (bridge in `app/Services/ExamEngine/`):

```dotenv
EXAM_ENGINE_INTERNAL_URL=http://127.0.0.1:8090        # engine internal API (private network / WireGuard)
EXAM_ENGINE_INTERNAL_TOKEN=<same value as the engine>  # Authorization: Bearer …, compared in constant time
EXAM_ENGINE_PUBLIC_URL=https://exam.example.org       # the exam site students open (/{SAHODAYA_CODE})
EXAM_ENGINE_LAUNCH_SECRET_KEY=<from php artisan exam-engine:keys>
```

`php artisan exam-engine:keys` prints the key pair; the engine gets only
`EXAM_ENGINE_LAUNCH_PUBLIC_KEY`. Laravel arms a run with `PUT /internal/runs/{run}` (meta, including
the `questions` id→type map), `POST …/roster` (chunks ≤ 2000) and `PUT …/bundle`, then its
`exam-engine:consume` workers loop on `POST /internal/events/claim` + `POST /internal/events/ack`.
The live monitor uses `GET …/summary`, `GET …/attempts`, `POST …/attempts/{reg_id}/actions`,
`POST …/broadcast`, `POST …/pace`, and the incident controls below; `POST …/close` ends the run.

### Incident controls (whole run)

```
POST /internal/runs/{run}/extend   {"minutes": 10, "actor": "user:12 (exam_controller)", "reason": "power cut"}
  → {"ok": true, "minutes": 10, "extended_minutes": 10}
POST /internal/runs/{run}/hold     {"on": true, "actor": "user:12 (exam_controller)", "reason": "…"}
  → {"ok": true, "hold": true}
```

`extend` adds the minutes to every attempt (writing attempts' deadlines move at once; attempts that
start later include it) and emits `run.extended {minutes, actor, reason}` with `reg_id: null`.
`hold` stops the sweeper from auto-submitting the run; students past their deadline get
`action: "lock"` plus a message, and their own submit is answered `423 locked`; it emits
`run.hold {on, actor, reason}`. Both show in `/summary` as `extended_minutes` and `hold`. A typical
incident: hold on → extend → hold off.

## Load generator

```sh
go run ./cmd/loadgen -base http://127.0.0.1:8090 -token "$EXAM_ENGINE_INTERNAL_TOKEN" \
  -students 3000 -questions 50 -duration 3m -compress 5 -password-percent 10 -bcrypt-cost 10
```

It arms a synthetic run (meta starting `-lead` ahead, roster with passcodes and real bcrypt hashes
for `-password-percent`, an AES-256-GCM paper with all four question types), simulates every
student (Sahodaya page, login, system check, paper prefetch, waiting room, start with jitter, diff
syncs honouring `next_sync_in`/`-compress`, offline gaps, stale-rev probes and resyncs, early and
time-up submits, a few abandoned attempts for the sweeper), then claims every event through the
internal API and checks that each `attempt.submitted` holds exactly what the student last sent.
It prints per-endpoint count/errors/p50/p95/p99/max, peak in-flight, throughput and the lost-answer
count, and exits non-zero if any answer was lost. `-json file` saves the report. Use a scratch
Redis database: the run is closed at the end (`-keep` to leave it).

## Observability

- `GET /health` → `{"ok": true, "redis": "ok", "version": "…"}` (503 when Redis is down).
- `GET /metrics` (Prometheus text): `exam_http_requests_total{route,code}`,
  `exam_http_request_duration_seconds{route}`, `exam_http_inflight_requests`,
  `exam_login_queue_depth`, `exam_login_queue_wait_seconds`, `exam_pace_seconds`,
  `exam_attempts{run,state}`, `exam_submits_total{mode}`, `exam_events_stream_length`,
  `exam_events_pending`, plus logins, sync actions, sweeper, snapshots, Redis pool and Go runtime.
- JSON logs on stdout (errors, slow requests > 2 s, lifecycle). Passwords and passcodes are never logged.
