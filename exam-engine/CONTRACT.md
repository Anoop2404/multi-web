# Exam Engine — Contract (v1)

The single source of truth for how the **Go exam engine**, the **exam client** (student app) and
**Laravel** talk to each other. Plan and reasoning: `docs/ONLINE_EXAM_ENGINE_PLAN_2026_10_15.md`.
If something here is ambiguous, pick the simplest reading, keep it consistent on both sides, and
note it in the "Decisions made while building" list at the end.

## 0. Ownership (who builds what)

| Part | Folder | Language |
|---|---|---|
| Engine (HTTP server, Redis state, sweeper, load generator) | `exam-engine/` (except `client/`, `deploy/`) | Go 1.27, module `github.com/sahodaya/exam-engine` |
| Exam client (login, check-in, waiting room, exam, result) | `exam-engine/client/` | Vue 3 + Vite + TypeScript |
| Ops (deploy, scale-out, monitoring, runbook) | `exam-engine/deploy/`, `docs/ONLINE_EXAM_RUNBOOK.md` | shell, YAML, Markdown |
| Laravel bridge (arm, roster, bundle, consumer, bookings, settings) | `app/Services/ExamEngine/`, `app/Console/Commands/ExamEngine*` | PHP |
| Laravel live monitor + actions UI | `app/Http/Controllers/ExamEngine/`, `resources/js/Pages/Admin/ExamEngine/`, `routes/includes/exam_engine_*.php` | PHP + Vue |

## 1. Conventions

- **Run**: one live sitting of one Sahodaya exam. `run = "{tenantId}:{examId}"` (tenantId = Sahodaya tenant id string, examId = `mcq_exams.id`). In URLs the run is URL-encoded (`:` → `%3A`); engines must accept both.
- **Registration id** (`reg_id`): `mcq_registrations.id` (int). Unique within a run.
- **Times**: RFC 3339 / ISO-8601 UTC strings with `Z` (`2026-11-20T04:30:00Z`). Durations are integers with the unit in the name (`duration_minutes`, `grace_seconds`).
- **JSON** everywhere; `Content-Type: application/json`. Errors: HTTP 4xx/5xx with body `{"error": "<code>", "message": "<human text>"}`. Codes used: `unauthorized`, `forbidden`, `not_found`, `invalid`, `not_open_yet`, `closed`, `locked`, `device_conflict`, `busy`, `rate_limited`, `already_submitted`, `wrong_pin`.
- **Question types**: `single`, `multiple`, `true_false`, `numeric` (same as Laravel `McqQuestion::TYPES`).
- **Answer values** (keyed by question id as a string):
  - `single`, `true_false`: option key string, e.g. `"b"`
  - `multiple`: array of option keys, sorted ascending, e.g. `["a","c"]`
  - `numeric`: the typed number as a string, e.g. `"12.5"`
  - unanswered: key absent (or `null` in a diff meaning "cleared")
- **Answer checksum**: lowercase hex SHA-256 of the canonical answer string. Canonical string = for every answered question, sorted by numeric question id ascending, the line `"{qid}={value}\n"` where value is the key, the multiple keys joined by `,`, or the numeric string as typed (trimmed). No answers → SHA-256 of the empty string. Both client and engine compute this; the engine replies `action: "resync"` on mismatch.

## 2. Run data (Laravel → engine when an exam is **armed**)

### 2.1 Meta (`PUT /internal/runs/{run}`)
```json
{
  "run": "3bf5…:12",
  "tenant_id": "3bf5…", "exam_id": 12,
  "sahodaya_code": "MLP", "sahodaya_name": "Malappuram Sahodaya", "logo_url": null,
  "title": "Talent Search 2026 — Class 8",
  "timing": "fixed",                       // "fixed": everyone ends at starts_at+duration(+extra); "individual": started_at+duration(+extra)
  "checkin_opens_at": "2026-11-18T04:30:00Z",
  "waiting_opens_at": "2026-11-20T03:30:00Z",
  "starts_at": "2026-11-20T04:30:00Z",
  "duration_minutes": 60,
  "late_entry_minutes": 15,
  "grace_seconds": 90,
  "entry_mode": "either",                  // "either" | "portal_password" | "exam_passcode"
  "supervision": "open",                   // "open" | "supervised"
  "start_pin": null,                       // string when supervision = supervised
  "show_score_on_submit": false,
  "shuffle_questions": true,
  "require_fullscreen": false,             // soft recommend when false; blocking overlay when true (signals only — no multi-monitor)
  "device_idle_minutes": 2,
  "sync_interval_seconds": 10,
  "heartbeat_seconds": 30,
  "coordinator_extend_cap_minutes": 10,
  "passcode_secret": "64 hex chars",       // HMAC key for passcodes of this run
  "question_count": 50, "max_score": 60,
  "bundle_version": 3
}
```
Re-sending meta while the run has not started replaces it. After `starts_at`, only `start_pin`, `late_entry_minutes`, `show_score_on_submit` and `sync_interval_seconds` may change (others → `409 locked`).

### 2.2 Roster (`POST /internal/runs/{run}/roster`, chunks of ≤ 2000)
```json
{ "entries": [ {
  "reg_id": 101, "name": "Asha K", "school_id": "uuid", "school_name": "St. Mary's", "class_name": "Class 8",
  "hall_ticket": "12",
  "username": "stu/26/0001", "email": "asha@example.com",   // lower-cased; either may be null
  "password_hash": "$2y$12$…",                               // bcrypt from users.password; null if no portal login
  "passcode_hmac": "hex",                                     // HMAC-SHA256(passcode_secret, "{reg_id}|{PASSCODE}") — see §5.2; null if none
  "extra_minutes": 0,
  "allowed": true                                             // false = blocked (absent / malpractice / cancelled)
} ] }
```
Upsert by `reg_id`. `PUT /internal/runs/{run}/roster/{reg_id}` replaces one entry (password change, passcode reissue, extra time). `DELETE` removes it.

### 2.3 Paper bundle (`PUT /internal/runs/{run}/bundle`)
```json
{ "version": 3, "iv": "base64 (12 bytes)", "ciphertext": "base64 (AES-256-GCM ciphertext || 16-byte tag)", "key": "base64 (32 bytes)", "sha256": "hex of ciphertext bytes" }
```
The engine stores `iv`+`ciphertext` as the public bundle and the `key` separately (served to a student only after they start, §4). Plaintext (UTF-8 JSON) after decryption:
```json
{ "version": 3, "questions": [ {
  "id": 5501, "type": "multiple", "section": "Section A — Science",
  "body": "Text with $\\frac{1}{2}$ formulas", "image": "data:image/png;base64,…" | null,
  "options": [ { "key": "a", "label": "2", "image": null } ],   // [] for numeric
  "show_count": 2,                                             // multiple only: how many to choose, or null to hide
  "marks": 2, "negative_mark": 0.5
} ] }
```
**Answer keys are never in the bundle.** Text uses the same `$…$` / `$$…$$` LaTeX convention as Laravel (`resources/js/support/mcqRichText.js`).

### 2.4 Disarm / close / results
- `POST /internal/runs/{run}/disarm` — only before `starts_at`; deletes the run.
- `POST /internal/runs/{run}/close` → `{ "ok": true, "unfinished": 0 }`. Force-finalises any attempt still writing (mode `forced`), marks the run closed (logins refused), keeps data 7 days.
- `POST /internal/runs/{run}/results` `{ "results": { "101": { "score": 42.5, "max_score": 60, "percentage": 70.83, "grade": "A" } } }` — score write-back for `show_score_on_submit` runs.

## 3. Internal API (Laravel → engine)

- Base: `EXAM_ENGINE_INTERNAL_URL` (e.g. `http://127.0.0.1:8090`). Header `Authorization: Bearer {EXAM_ENGINE_INTERNAL_TOKEN}`; compare in constant time. Paths under `/internal/`.
- Besides §2:
  - `GET /internal/runs/{run}/summary` →
    ```json
    { "run": "…", "state": "armed|checkin|waiting|live|ended|closed", "server_time": "…",
      "counts": { "roster": 1200, "checked_in": 900, "waiting": 40, "writing": 800, "submitted": 300, "locked": 1, "offline": 12 },
      "by_school": [ { "school_id": "…", "school_name": "…", "roster": 60, "checked_in": 50, "writing": 40, "submitted": 10, "offline": 1, "flagged": 2 } ],
      "pace_seconds": 10, "unfinished": 840 }
    ```
    `offline` = writing attempts not seen for > 60 s.
  - `GET /internal/runs/{run}/attempts?school_id=&state=&q=&flagged=1&page=1&per_page=50` →
    `{ "data": [ { "reg_id": 101, "name": "…", "school_id": "…", "school_name": "…", "class_name": "…", "hall_ticket": "12", "state": "writing", "checked_in_at": "…|null", "started_at": "…|null", "deadline": "…|null", "submitted_at": "…|null", "submit_mode": "manual|auto|forced|null", "last_seen_at": "…|null", "answered": 31, "flags": 2, "device": "Chrome 131 / Android", "ip": "…", "extra_minutes": 0 } ], "total": 812, "page": 1, "per_page": 50 }`
  - `POST /internal/runs/{run}/attempts/{reg_id}/actions` `{ "action": "extend|force_submit|lock|unlock|allow_switch", "minutes": 5, "reason": "power cut", "actor": "user:12 (school_mcq_coordinator)" }` → the attempt row. `extend` adds to `extra_minutes` (also allowed before start). Every action is also emitted as an event (§6) for Laravel's audit log.
  - `POST /internal/runs/{run}/broadcast` `{ "message": "…", "actor": "…" }` — shown to every student on their next sync.
  - `POST /internal/runs/{run}/pace` `{ "seconds": 30 }` (or `null` to return to automatic).
  - `POST /internal/events/claim` `{ "consumer": "laravel-1", "count": 200, "block_ms": 2000 }` → `{ "events": [ … §6 … ] }` (Redis stream consumer group `laravel`; returns this consumer's pending first, then new).
  - `POST /internal/events/ack` `{ "ids": ["1731840000000-0", …] }` → `{ "acked": 2 }`
- Public, no auth: `GET /health` → `{ "ok": true, "redis": "ok", "version": "…" }`, `GET /metrics` (Prometheus text).

## 4. Student API (client → engine)

- Same origin as the client (the engine serves the built client). Paths under `/api/`. Session = HttpOnly cookie `exam_session` (random 32-byte token, `SameSite=Lax`, `Secure` when the request is HTTPS, `Path=/`), mapped in Redis to `{run, reg_id, device_id}`. Requests that change state must send header `X-Exam-Client: 1` (CSRF guard with SameSite).
- `GET /api/sahodaya/{code}` → `{ "code": "MLP", "name": "…", "logo_url": null, "runs": [ { "run": "…", "title": "…", "starts_at": "…", "state": "checkin|waiting|live|ended" } ] }` (armed, not closed runs for that Sahodaya code; 404 if none).
- `POST /api/login`
  - Password: `{ "sahodaya": "MLP", "method": "password", "username": "stu/26/0001 or email", "password": "…" }`
  - Passcode: `{ "sahodaya": "MLP", "method": "passcode", "hall_ticket": "12", "passcode": "K7M2P-Q9RTX" }`
  - Launch token from the portal: `{ "method": "launch", "token": "…" }` (§5.3)
  - → `200 { "state": { …GET /api/state… } }` + cookie. Errors: `401 unauthorized` (generic "Details don't match" — never say which part), `403 forbidden` (entry mode not allowed / not on roster / `allowed=false`), `423 locked` (5 failures → 10 min), `409 device_conflict` (another active device; message says to ask the coordinator), `503 busy` with `Retry-After` (password-check queue full), `409 closed`.
  - Password checks run on a bounded worker pool (size = CPU cores); a request waits up to 20 s for a slot, else `503 busy`.
  - If the identity matches registrations in several armed runs of that Sahodaya, pick the run whose window (checkin_opens_at … starts_at+duration+grace) contains now, else the soonest upcoming.
- `GET /api/state` →
  ```json
  { "run": "…", "reg_id": 101, "name": "Asha K", "hall_ticket": "12", "school_name": "…", "class_name": "…",
    "title": "…", "sahodaya_name": "…", "logo_url": null,
    "state": "checked_in|waiting|writing|submitted|locked|ended",
    "server_time": "…", "checkin_opens_at": "…", "waiting_opens_at": "…", "starts_at": "…",
    "late_entry_until": "…", "deadline": "…|null", "duration_minutes": 60, "extra_minutes": 0,
    "supervision": "open", "start_pin_required": false,
    "bundle_url": "/api/bundle/{run}/{version}", "bundle_version": 3,
    "shuffle_questions": true, "seed": 101,
    "paper_key": "base64|null",            // only once state = writing (or submitted)
    "answers": { "5501": ["a","c"] }, "flagged": ["5503"], "rev": 14,
    "system_check": { "ok": true } | null,
    "messages": [ { "id": 3, "at": "…", "text": "…" } ],
    "next_sync_in": 10,
    "show_score_on_submit": false, "require_fullscreen": false,
    "submitted_at": null, "receipt": null }
  ```
- `POST /api/system-check` `{ "user_agent": "…", "screen": "390x844", "online": true, "latency_ms": 120, "storage_ok": true, "crypto_ok": true }` → `{ "ok": true }` (stored on the attempt; emits nothing).
- `GET /api/bundle/{run}/{version}` → `{ "version": 3, "iv": "…", "ciphertext": "…" }` with `Cache-Control: public, max-age=31536000, immutable`. No auth needed (it's encrypted); in Stage B this file is also on the CDN.
- `POST /api/start` `{ "pin": "4821" }` → state (now `writing`, with `paper_key` and `deadline`). Rules: allowed from `starts_at` until `late_entry_until` (= starts_at + late_entry_minutes); `423 locked` if locked; `403 wrong_pin`; `409 closed` after the deadline. Idempotent: calling again while writing just returns state.
- `POST /api/sync` `{ "base_rev": 14, "changes": { "5501": ["a","c"], "5502": null }, "flagged": ["5503"], "checksum": "hex", "events": [ { "type": "tab_hidden", "at": "…" } ] }` →
  `{ "rev": 15, "server_time": "…", "deadline": "…", "next_sync_in": 10, "messages": [ … ], "action": "none|resync|submit|lock", "checksum_ok": true }`
  - Apply `changes` only if `base_rev` equals the stored rev; then rev+1. If `base_rev` is behind, or the checksum after applying doesn't match, reply `action: "resync"` (and don't apply).
  - Changes received after `deadline` are rejected (`action: "submit"`), except within `grace_seconds` (accepted and counted in `late_changes`).
  - `events` types: `tab_hidden`, `window_blur`, `fullscreen_exit`, `offline` (client was offline, `at` = when it went offline, optional `seconds`). Max 50 per request; batched into one `proctor.batch` event.
  - Also acts as the heartbeat (`last_seen_at`). Sync while `state` ≠ writing → `action: "submit"` if submitted/ended, `"lock"` if locked.
- `POST /api/resync` `{ "answers": { … full map … }, "flagged": [ … ] }` → same as sync; replaces the stored map (only while writing or within grace).
- `POST /api/submit` `{ "base_rev": 15, "changes": { … }, "checksum": "hex" }` → `{ "submitted": true, "submitted_at": "…", "receipt": "MLP-12-101-7F3A" }`. Idempotent (a second call returns the same receipt). Applies final changes under the same rules as sync.
- `GET /api/result` → `{ "status": "hidden|pending|ready", "score": 42.5, "max_score": 60, "percentage": 70.83, "grade": "A" }` (`hidden` unless `show_score_on_submit`).
- `POST /api/logout` → clears the cookie (attempt state is kept).
- Rate limits (per session): sync/resync/submit ≤ 6 per 10 s; login ≤ 10 per minute per IP+identity.

## 5. Identity and secrets

### 5.1 Device binding
On login the engine creates `device_id` (random) for the attempt. A login from a second device while the first was seen within `device_idle_minutes` → `409 device_conflict`, unless an `allow_switch` action was issued (one-time) or the previous device has been idle longer. On a switch, the old session is revoked and an `attempt.device_switched` event is emitted.

### 5.2 Passcodes
Laravel prints passcodes as `XXXXX-XXXXX` (alphabet `ABCDEFGHJKMNPQRSTUVWXYZ23456789`). Normalise typed input: uppercase, drop everything except A–Z/0–9. `passcode_hmac = hex(HMAC-SHA256(key = hex-decoded passcode_secret, message = "{reg_id}|{NORMALISED}"))`. Because hall ticket numbers repeat across classes, the engine finds candidates by hall ticket (index `hall_ticket → [reg_id…]`) and checks each candidate's HMAC.

### 5.3 Launch tokens (portal "Enter exam" button)
Laravel signs with Ed25519 (libsodium detached signature). Token = `base64url(json) + "." + base64url(signature)`, json = `{ "run": "…", "reg_id": 101, "exp": 1731840000 (unix seconds, ≤ 120 s ahead), "jti": "random 16 bytes hex" }`. Engine verifies with `EXAM_ENGINE_LAUNCH_PUBLIC_KEY` (base64 32 bytes), rejects expired tokens, and burns the `jti` (`SET NX EX 300`).

## 6. Events (engine → Laravel via `/internal/events/claim`)
Each event: `{ "id": "<stream id>", "type": "…", "run": "…", "reg_id": 101, "at": "…", "data": { … } }`
| type | data |
|---|---|
| `attempt.checked_in` | `{ "ip": "…", "user_agent": "…", "method": "password|passcode|launch" }` |
| `attempt.started` | `{ "deadline": "…" }` |
| `attempt.snapshot` | `{ "rev": 15, "answers": { … } }` (at most every 120 s per attempt, only if changed) |
| `attempt.submitted` | `{ "mode": "manual|auto|forced", "rev": 22, "answers": { … }, "late_changes": 0, "receipt": "…" }` |
| `attempt.device_switched` | `{ "ip": "…", "user_agent": "…" }` |
| `attempt.action` | `{ "action": "extend|force_submit|lock|unlock|allow_switch", "minutes": 5, "reason": "…", "actor": "…" }` |
| `proctor.batch` | `{ "events": [ { "type": "tab_hidden", "at": "…" } ] }` |
| `run.broadcast` | `{ "message": "…", "actor": "…" }` (reg_id null) |
| `run.closed` | `{ "unfinished_forced": 3 }` (reg_id null) |

Delivery is at-least-once; Laravel handles events idempotently (by event id and by state).

## 7. Timing and state rules
- Run state by clock: `armed` (< checkin_opens_at) → `checkin` → `waiting` (≥ waiting_opens_at) → `live` (≥ starts_at) → `ended` (> last possible deadline + grace) → `closed` (explicit).
- Attempt state: `checked_in` (logged in before waiting room) → `waiting` → `writing` (after /start) → `submitted`; `locked` overlays writing (sync answers `lock`, no changes accepted) until unlocked.
- Deadline: `fixed` → `starts_at + duration + extra`; `individual` → `started_at + duration + extra`. Extending adds to `extra_minutes` and moves the deadline.
- **Sweeper** (every 5 s, one leader via `SET ex:leader:sweeper NX PX 10000`): every writing attempt past `deadline + grace` is finalised with its last stored answers (`mode: auto`). Runs past `ended` + 24 h are left alone until close/expiry.
- **Pacing**: `next_sync_in = pace override ?? auto`, where auto = `sync_interval_seconds`, raised to 20 / 30 / 60 when the node's in-flight API requests exceed 500 / 1500 / 3000 (configurable). Clients add 0–20 % jitter.
- Final submit: at the deadline the client locks the UI and submits after a random 0–30 s delay (must stay inside `grace_seconds`).

## 8. Redis layout (engine only)
All run keys share the hash tag `{run}`: `ex:{run}:meta`, `ex:{run}:key` (bundle key), `ex:{run}:bundle` (public bundle JSON), `ex:{run}:roster` (hash reg_id → JSON), `ex:{run}:idx:user` (hash username/email → reg_id), `ex:{run}:idx:ticket` (hash hall_ticket → JSON list of reg_ids), `ex:{run}:att:{reg_id}` (hash), `ex:{run}:seen` (zset), `ex:{run}:agg` (hash counters), `ex:{run}:msgs` (list), `ex:{run}:results` (hash), `ex:{run}:fail:{id}`; global: `ex:sess:{token}`, `ex:sahodaya:{code}` (set of runs), `ex:jti:{jti}`, `ex:events` (stream, `MAXLEN ~ 500000`), `ex:leader:sweeper`. Sync/submit are single Lua scripts (atomic rev check + apply + agg update). Keys expire 7 days after close.

## 9. Engine configuration (env)
`EXAM_ENGINE_ADDR` (`:8090`), `EXAM_ENGINE_REDIS_URL` (`redis://127.0.0.1:6379/0`), `EXAM_ENGINE_REDIS_SENTINELS` + `EXAM_ENGINE_REDIS_MASTER` (optional), `EXAM_ENGINE_INTERNAL_TOKEN`, `EXAM_ENGINE_LAUNCH_PUBLIC_KEY`, `EXAM_ENGINE_STATIC_DIR` (built client; default embedded copy), `EXAM_ENGINE_BUNDLE_BASE_URL` (CDN base; default `/api/bundle`), `EXAM_ENGINE_TRUST_PROXY` (use `X-Forwarded-For`), `EXAM_ENGINE_BCRYPT_WORKERS` (default NumCPU), `EXAM_ENGINE_PACE_THRESHOLDS` (`500,1500,3000`).

## 10. Laravel side (for the live-monitor UI builder)
`App\Services\ExamEngine\ExamEngineClient` (built by the bridge owner) wraps §3:
`summary(McqExam $exam): array`, `attempts(McqExam $exam, array $filters): array`, `action(McqExam $exam, int $regId, string $action, array $payload, string $actor): array`, `broadcast(McqExam $exam, string $message, string $actor): void`, `pace(McqExam $exam, ?int $seconds): void`, `close(McqExam $exam): array`, `isConfigured(): bool`. All throw `App\Services\ExamEngine\ExamEngineException` on failure. `App\Services\ExamEngine\ExamEngineRun::for(McqExam)` gives the run id. Exam fields: `mcq_exams.settings_json['engine'] === 'engine'` marks an engine exam; `settings_json['engine_state']` is `null|armed|closed`. Registrations gain `checked_in_at`, `submit_mode`, `extra_time_minutes`, `device_info` (json), `last_ip`.

## Decisions made while building
(append here)
