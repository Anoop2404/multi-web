# Online Exam Engine — Plan (2026-10-15)

Goal: run online Talent Search (MCQ) exams inside this project. Each Sahodaya conducts its own exams; the platform must carry **up to 60,000 students writing at the same time across all Sahodayas**, and a single Sahodaya's exam can be any size up to that.

| Stage | Platform-wide students writing at once | Load-test target | Where it runs |
|---|---|---|---|
| **A** | 2,000 | 3,000 | always-on baseline: 1 engine node + 1 Redis node (DigitalOcean Bangalore) |
| **B** | 60,000 | 90,000 | same baseline; extra engine nodes and a Redis replica are added automatically before booked busy slots |

**Build once, for 60,000. Run small until the exam calendar needs more.** The entry flow, paper delivery, sync protocol, stateless engine nodes and Redis key layout are all built for Stage B from day one. Going from A to B is just adding servers.

Status: plan only. Nothing built yet. Decisions are recorded in §11. Remaining small open items are in §11.2.

---

## 1. Recommendation

- **Laravel stays the control plane** (existing aaPanel server). It keeps everything before and after the exam: question banks, paper builder, exam setup, registration, fees, hall tickets and passcodes, attendance, grading, ranking, results and certificates.
- **A new Exam Engine written in Go, with Redis**, runs only the live exam: the separate exam login page, check-in and system check, waiting room, paper delivery, timer, answer sync, proctor signals, submit, and the live monitor feed.
- **Cloudflare** in front of the exam site provides DNS, CDN, DDoS protection, and R2 storage for the client app and encrypted papers. A CDN (content delivery network) caches files close to users, so the start-time rush is a tiny key fetch instead of thousands of paper downloads.
- **They talk over the engine's internal HTTPS API** (bearer token; changed 2026-10-16 from a shared Redis over WireGuard — only the engine touches Redis, and PHP needs no Redis extension). Laravel *arms* an exam: roster, login data, settings, and the paper without answer keys. The engine streams attempt events back. Laravel workers grade and write to Postgres. The answer key never leaves Laravel.

## 2. Why this stack

### 2.1 Why the live exam can't run inside the main Laravel app

PHP's per-request speed isn't the problem. The problem is the shape of the load:

| Problem | At 2,000 | At 60,000 |
|---|---|---|
| **Login rush**: `BCRYPT_ROUNDS=12` costs ~0.2–0.3 s of CPU per password check | ~500 CPU-seconds; pins a 4-core server if everyone logs in within 2 minutes | ~15,000 CPU-seconds; more than 8 cores busy for 30 minutes *only checking passwords* |
| **Submit rush**: everyone who started together submits within seconds | 30–60 PHP-FPM workers × 2 Postgres connections each (central + tenant DB) can exceed `max_connections` and slow every tenant site | impossible on one server |
| **Paper download at T0** (~40 KB gzipped per student, more with images) | ~80 MB in a minute | ~2.4 GB+ in a minute (≥ 300 Mbps) |
| **Shared everything**: one PHP-FPM pool, one Redis (sessions + cache + queue on `127.0.0.1:6379`), one deploy | an unrelated deploy or a cache eviction can break the exam | same, at 30× the blast radius |
| **Autosave writes to Postgres** (`mcq_registrations.draft_answers`) | constant tenant-DB writes for the whole exam | not viable |
| **Question order isn't stable**: `paperForStudent()` calls `->shuffle()` on every load | a refresh renumbers the questions | same |

### 2.2 Why Go for the engine (decided 2026-10-15)
- **Burst handling.** At T0 and at the end, tens of thousands of requests arrive within seconds. Go spreads them over every CPU core in one process, with short GC pauses.
- **Fewer servers.** Go typically serves several times more small JSON + Redis requests per core than Node. That means a smaller cluster and lower cost.
- **Password checks.** The exam login accepts portal passwords, which are bcrypt hashes. Go's `x/crypto/bcrypt` runs those checks on every core, behind a bounded login queue (§4.2).
- **Simple operations.** One static binary per node.
- **The exam client stays Vue 3 + TypeScript.** One OpenAPI contract generates both the Go types (oapi-codegen) and the TS types (openapi-typescript).
- **No WebSockets.** Control messages (extend time, broadcast, force submit, slow down) ride on the sync response. Plain HTTP retries survive flaky school, home and mobile networks better than mass socket reconnects.

## 3. Load model

Assumptions: 60-minute exam, 50–100 questions, some images, sync at most once per 10 s per student (the server can stretch this; §4.1 rule 7). Students write from anywhere, often on phones.

| Moment | Stage A (2k) | Stage B (60k) | How the design absorbs it |
|---|---|---|---|
| Check-in (T-48h → T-1h) | spread over 2 days | spread over 2 days | each student does at most one password check; the device then holds an exam device token |
| Waiting room (T-60 → T0) | ~1–5 rps | ~20–50 rps avg | password logins queue with bounded concurrency; passcode logins are a microsecond HMAC check |
| Client app + encrypted paper prefetch | ~0.5 GB | ~15–20 GB, spread over 60 min | Cloudflare CDN + R2 (no egress fees) |
| T0 key release | 2k tiny responses | 60k × ~200 bytes ≈ 12 MB | key from Redis via the engine; clients add 0–5 s jitter |
| Writing (diff sync + heartbeat + proctor events) | ≤ 200 rps | **≤ 6,000 rps ceiling**, ~2–3k typical | one Redis Lua call per sync; ~200–500 byte bodies |
| Deadline / submit | 2k in ~10 s | 60k spread over a 30 s jitter window → ~2k rps | answers lock on the client at the deadline; final submit is jittered; sweeper finalises the rest |
| Grading into Postgres | ~1 min | ~5–10 min, batched upserts | Laravel consumers; for show-score exams, scores appear as they're graded |
| Live monitor | per-student rows | aggregate counters + paginated drill-down | counters kept in Redis by the same Lua scripts |

Redis memory: ~5 KB per attempt → ~10 MB (A) / ~300 MB (B) plus login data and the stream.

## 4. Architecture

```
 Students (school, home, anywhere; phone or computer)
     │  https://exam.<platform-domain>/<sahodaya-code>
     ▼
 Cloudflare ── DNS · CDN · DDoS ── R2: client app, encrypted paper bundles, images
     │  /api/*
     ▼
 DigitalOcean Bangalore (BLR1)
   Load balancer ──► engine-1 (always on) ─┐
                     engine-2..n (added for busy slots, by tag)
                                            ▼
                     redis-exam primary ◄─► replica (busy slots) + Sentinel ×3
                     noeviction · AOF · keys hash-tagged per exam
                            ▲                     │ stream ex:events
     internal HTTPS │ arm/disarm,         ▼
              tunnel        │ login data, results
 aaPanel server ── Laravel ─┴── exam-engine:consume workers ── Postgres (tenant DBs)
   admins, exam controllers, school MCQ coordinators: setup, monitor UI, support tools
```

**Exam site:** one platform exam domain, `exam.<platform-domain>/<sahodaya-code>`, branded per Sahodaya. The Sahodaya code is the existing `sahodayaProfile.prefix`. It's a separate login page, as decided. Each Sahodaya website and the student portal link to it.

### 4.1 Design rules
1. **Laravel is the source of truth** before and after the window. The engine holds state only while the exam is armed.
2. **Engine nodes are stateless.** All state is in Redis. Any node serves any request, there are no sticky sessions, and a node can die mid-exam.
3. **The server owns the clock.** The client displays the deadline corrected by the `serverTime` offset in every response.
4. **The answer key never leaves Laravel.** The engine never grades.
5. **The paper is CDN-first.** The encrypted bundle (AES-256-GCM) is prefetched in the waiting room and the key is released at T0. Question and option order come from a per-attempt seed, so they're the same on reload and on another device.
6. **Offline-first client.** Answers go to IndexedDB first. Syncs send *diffs* with a base revision and a checksum of the full answer set. On mismatch the server replies `resync` and the client sends its full map.
7. **Server-controlled pacing.** Every response carries `nextSyncIn`. Under load the engine stretches it from 10 s to 20, 30 or 60 s instead of failing.
8. **Every write is idempotent**: sync by revision, submit by attempt ID, stream consumption by event ID.
9. **No Postgres and no Laravel in the exam-day hot path.**
10. **Capacity is booked.** Sahodayas schedule independently, so every online exam books platform capacity (§6), and scale-out follows the bookings.

### 4.2 Entry: the separate exam login page

As decided, a student can sign in with either of:
- **Portal username or email + portal password**, the same credentials as the student portal.
- **Hall ticket number + exam passcode**, a per-student code printed on the hall ticket and also in the school coordinator's list. Hall ticket numbers are per-class roll numbers that repeat across classes, so the **passcode identifies the candidate** (unique per exam) and the hall ticket number is a confirming field.

Each exam chooses `entry_mode = portal_password | exam_passcode | either` (default `either`).

| | Portal password | Exam passcode |
|---|---|---|
| Stored in the engine as | the existing bcrypt hash, copied into the roster at arm time | HMAC-SHA256 (the passcode is random with ~50 bits of entropy, so a fast hash is safe). Laravel keeps it encrypted (to reprint hall tickets) plus a keyed lookup hash. |
| CPU per check | ~0.25 s (bcrypt cost 12) | microseconds |
| Password/passcode changed after arm | a Laravel listener pushes the new hash/HMAC to the armed roster | same |
| Forgotten | the school MCQ coordinator resets the portal password | the school MCQ coordinator re-issues the passcode |

**Spreading the password cost (needed at 60k):**
- **Check-in and system check**, open T-48h → T-1h. The student signs in once, the page tests the browser, network and screen, and the device receives an **exam device token** bound to that attempt. On exam day, that device goes straight to the waiting room with no password check. This also catches device problems before exam day.
- **Waiting room opens T-60.**
- **Bounded login queue.** Password checks run on a worker pool sized to the node's cores. Extra logins wait in a queue, and the page shows "Signing you in… N ahead" instead of timing out.
- **Capacity rule.** The booking check (§6) warns when a slot has more than 20,000 `portal_password`-only students, and suggests `either` or `exam_passcode`.

Also available: a portal **"Enter exam" button** for students already logged in to the student portal. Laravel issues a single-use 2-minute ed25519 launch token, so no password is re-checked.

### 4.3 Exam lifecycle

| Step | Who | What |
|---|---|---|
| Schedule | Sahodaya admin / exam controller | sets time and mode; Laravel **books capacity** (§6) |
| Hall tickets | Laravel | generating the hall ticket also generates the exam passcode (stored encrypted + lookup hash) and prints it. The school MCQ coordinator can download a passcode list for their students. |
| Arm (T-48h, opens check-in) | scheduler via `mcq:transition-exam-windows`, or a button | Laravel builds the exam pack: meta, timing, settings; roster with username, hall ticket, password hash, passcode HMAC, extra time; the paper without keys. It encrypts the paper bundle, uploads it to R2, and writes the rest plus the bundle key to redis-exam. Roster is written in chunks. The paper is locked. |
| Check-in | Student | sign in, system check, device token (§4.2) |
| Waiting room (T-60) | Student | instructions, countdown, background prefetch of the encrypted paper |
| Start (T0) | Engine | key released in the next sync; seeded order. In supervised mode the invigilator's **start PIN** is also required. Late entry until T0 + `late_entry_minutes`. |
| Write | Student | diff sync; responses carry `serverTime`, `deadline`, `nextSyncIn`, messages and `action` (`none` / `resync` / `submit` / `lock`) |
| End | Client and engine | client locks answers at the deadline and submits after a random 0–30 s delay. Large changes received in that window are flagged. The leader-elected **sweeper** finalises anything past deadline + grace (90 s). |
| Grade | Laravel workers | consume `ex:events` → tenancy per event batch → update `mcq_registrations`, grade with `McqGrader`, batch-upsert `mcq_marks` and `mcq_proctor_events`, audit-log. If `show_score_on_submit`, write the score back to `ex:{run}:result:{regId}` for the client to poll from the engine. |
| Close | Sahodaya exam controller | checks all attempts are finalised and consumer lag is 0, then shows reconciliation (registered / checked in / entered / submitted / absent) by school. Then the existing ranking → publish → certificates flow runs, and the booking is released. |

### 4.4 Attempt state machine
`checked_in → waiting → writing → submitted(manual | auto | forced)`; `locked` (admin hold) can return to `writing`. Single active device: the attempt binds to a device token. A second device needs a coordinator's **allow switch**, or must wait until the first device has been silent for longer than `device_idle_minutes` (default 2).

### 4.5 Supervision modes (students write "anywhere")
| | `supervised` (school, centre) | `open` (home, anywhere) |
|---|---|---|
| Start PIN from invigilator | required | not used |
| Device switch | coordinator approval | coordinator approval, or after idle timeout |
| Proctor flags | shown to the invigilator live | reviewed after the exam |
| Typical use | ranked competitive exams | practice and qualifying rounds |

Both modes are mobile-first. Tab-switch, blur, fullscreen exit, offline gaps, device switches and large post-deadline changes are always logged and flagged, never auto-penalised (current policy in `StudentMcqController::recordProctorEvent`). There's an on-screen watermark with name + hall ticket number.

## 5. Engine module

### 5.1 Layout (inside this repo)
```
exam-engine/
  go.mod  Makefile  .env.example
  api/openapi.yaml            single contract → Go server types + TS client types
  cmd/engine/main.go          HTTP server, graceful shutdown, health/metrics
  internal/
    entry/                    password (bcrypt pool + queue), passcode (HMAC), launch tokens (ed25519),
                              device tokens, start PIN, lockouts
    attempt/                  state machine, deadlines, extra time, late entry
    sync/                     diff apply, checksum, resync, pacing, answer-shape validation per question type
    paper/                    bundle metadata, key release, seed
    admin/                    live aggregates, extend, force-submit, lock/unlock, allow-switch, reissue-passcode,
                              broadcast, close (scoped: school coordinator vs Sahodaya)
    store/                    go-redis v9, Lua scripts, hash-tagged keys
    events/                   XADD to ex:events
    sweeper/                  leader-elected auto-finalise
  client/                     Vue 3 + Vite + TS exam app (deployed to R2/CDN)
  deploy/                     droplet image build, cloud-init, scale-out/in scripts (doctl), Sentinel config
  load/exam-day.k6.js         3k (A) and 90k (B, distributed) scenarios
  test/                       Go tests + API tests against real Redis
```

### 5.2 Stack
Go (current stable) · `net/http` + chi · go-redis v9 · `x/crypto/bcrypt` · `crypto/ed25519`, `crypto/hmac`, `crypto/aes` from the standard library · `log/slog` JSON logs · Prometheus `/metrics`. Client: Vue 3 + Vite + TypeScript, `idb-keyval`, WebCrypto AES-GCM, under 150 KB gzipped, system fonts plus a self-hosted Noto Sans Malayalam subset (questions may contain Malayalam text).

### 5.3 Student API (`/api`)
| Method | Path | Purpose |
|---|---|---|
| POST | `/login` | `{sahodaya, username, password}` or `{sahodaya, hallTicket, passcode}` or `{launchToken}` → device token cookie; `{state, startsAt, serverTime, bundleUrl, seed, queuePosition?}` |
| POST | `/system-check` | client reports browser/network/screen results; stored on the attempt |
| POST | `/sync` | `{baseRev, changes, flaggedChanges, checksum, events[]}` → `{rev, serverTime, deadline, nextSyncIn, paperKey?, messages[], action}` |
| POST | `/resync` | full `{answers, flagged}` → same as `/sync` |
| POST | `/submit` | `{baseRev, changes, checksum}` → `{submitted: true, receipt}` (idempotent) |
| GET | `/result` | score if `show_score_on_submit` and graded, else `{pending}` / `{hidden}` |

### 5.4 Admin API (called by Laravel UIs with a Laravel-signed token scoped to tenant, exam, role and school)
| Action | School MCQ coordinator (own school) | Sahodaya exam controller / admin |
|---|---|---|
| View live status, flags | ✓ | ✓ (all schools, aggregates) |
| Re-issue passcode, allow device switch, unlock | ✓ | ✓ |
| Extend time | up to `coordinator_extend_cap` (default 10 min) | any |
| Force submit, lock | — | ✓ |
| Broadcast, pacing override, close exam | — | ✓ |

### 5.5 Redis keys (hash-tagged with `{run}`, where `run = tenantId:examId`, so one exam stays on one shard if Redis Cluster is ever needed)
| Key | Type | Content |
|---|---|---|
| `ex:{run}:meta` | string (JSON) | timing, settings, entry mode, pack version, bundle URL, state |
| `ex:{run}:key` | string | paper bundle key (served only after T0) |
| `ex:{run}:roster` | hash | regId → name, school, hall ticket, password hash, passcode HMAC, extra minutes |
| `ex:{run}:login:u:{username}` / `…:login:h:{hallTicket}` | string | → regId |
| `ex:{run}:att:{regId}` | hash | state, timestamps, deadline, rev, answers, flagged, checksum, device, lastSeen, ip, flags, system check |
| `ex:{run}:seen` | sorted set | regId by lastSeen |
| `ex:{run}:agg` | hash | counters per state / school |
| `ex:{run}:result:{regId}` | string | score written back by Laravel (show-score exams) |
| `ex:{run}:msgs` | list | broadcasts |
| `ex:{run}:fail:{id}` | string + TTL | failed logins (lock after 5 for 10 min) |
| `ex:sahodaya:{code}:armed` | set | runs armed for that Sahodaya's login page |
| `ex:events` | stream (`MAXLEN ~ 500000`) | attempt.checked_in / started / snapshot / submitted, proctor.batch |

redis-exam settings: `maxmemory-policy noeviction`, `appendonly yes`, `appendfsync everysec`, AUTH + TLS, reachable only from the engine nodes on the private network. Run data (including the password-hash copies) is deleted 7 days after close.

## 6. Laravel side

| Piece | Where | Notes |
|---|---|---|
| Config | `config/exam_engine.php` | engine URL, redis-exam connection, signing keys, R2 bucket, defaults |
| `ExamEngineBridge` | `app/Services/ExamEngine/` | `arm()`, `disarm()`, chunked roster write, bundle encrypt + R2 upload, pack schema shared with the engine |
| **Capacity booking** | central table `exam_engine_bookings` (tenant, exam, window incl. check-in/grace, expected count, entry mode) | checked when an online exam is scheduled or its registrations grow. Shows platform load per time slot, warns or blocks over capacity, and drives scale-out (§9). A super-admin calendar view lists all bookings. |
| Passcodes | `McqHallTicketService` + hall ticket template | generate on issue, store HMAC only, print on the hall ticket; coordinator list and re-issue |
| Password sync | listener on student password change | if the student is in an armed exam, push the new hash to the roster |
| `LaunchTokenIssuer` | `app/Services/ExamEngine/` | portal "Enter exam" button |
| `McqGrader` | `app/Services/Mcq/McqGrader.php` | extracted from `McqExamSessionService::submit()`; adds multiple-correct and numeric (§7) |
| Consumer | `app/Console/Commands/ExamEngineConsume.php` (`exam-engine:consume`) | claims/acks events through the internal API (Redis consumer group inside the engine), idempotent, tenancy per batch, bulk upserts, score write-back; supervisor `exam-engine-consumer` (2 procs, raised to 4–8 when bookings exceed 10k) |
| Live monitor | `McqExamOpsController::sessionMonitor` + `Sahodaya/Mcq/SessionMonitor` page; exam portal (`ExamOpsController::supervision`); school MCQ coordinator view | engine admin API, polled every 5 s; actions by role as in §5.4 |
| Fallback | existing in-app online path | per-exam `settings_json.engine = 'laravel' \| 'engine'`. The in-app path stays **single-correct only**; exams with multiple-correct or numeric questions must use the engine. |
| Scheduler | `routes/console.php` | `mcq:auto-submit-expired` skips engine exams |
| Results day | student portal results | pre-compute per-student result payloads into cache at publish; stagger notifications |

### 6.1 Data model changes
- Tenant `mcq_questions` **(built in Phase 1)**: `type` (`single` / `multiple` / `true_false` / `numeric`), `answer_json` (multiple: `{keys, show_count}`; numeric: `{value, tolerance}`), option images as `image_path` inside `options_json`, `explanation`, `difficulty`. `correct_option_key` is kept for `single` and `true_false`. Text stays plain (Unicode + `$…$` LaTeX), so there is no `body_html`. Readiness is per bank (`draft` / `active` = Ready / `archived`), and a bank locks once any student starts an exam using it, so there is no per-question status.
- Tenant `mcq_question_banks` **(built)**: `school_id` nullable for Sahodaya-authored banks.
- Paper sections **(built)**: columns on the existing `mcq_exam_question_banks` link (`section_title`, `pick_mode` = all / first / random, `pick_count`, `display_order`) rather than a new `mcq_exam_sections` table. Marks stay per question.
- Tenant `mcq_registrations` **(passcode columns built)**: `exam_passcode` (encrypted, for printing), `exam_passcode_lookup` (keyed hash, unique per exam). Still to come in Phase 2: `engine_attempt_id` (uuid), `extra_time_minutes`, `submit_mode` (`manual` / `auto` / `forced`), `checked_in_at`, `device_info` (json), `last_ip`.
- Tenant `mcq_exams.settings_json` keys: `engine`, `entry_mode`, `supervision`, `shuffle_options`, `checkin_hours`, `waiting_room_minutes`, `late_entry_minutes`, `grace_seconds`, `start_pin`, `show_score_on_submit`, `device_idle_minutes`, `coordinator_extend_cap`, `multi_marking`.
- Central `exam_engine_bookings` (new).

## 7. Questions

**Authors (decided):** Sahodaya admins (and staff with `mcq.manage`) and users with the existing `exam_controller` role, who author from the exam portal. Teachers and `exam_staff` have no access. Every create, edit or import is audit-logged, and an answer-key change is logged as its own action with the before and after keys. A bank must be marked Ready before it can be added to a paper, and its questions lock once a student starts an exam that uses it.

**Content (decided 2026-10-15):** question and option text can be plain text, an image, or both. Text takes any Unicode (Malayalam, symbols) and LaTeX formulas between `$…$` (or `$$…$$` on their own line), rendered with KaTeX 0.16.29. The editor has a symbol toolbar (×, ÷, √, π, °, Greek letters, arrows, set symbols, ₹…) and formula snippets (fraction, power, subscript, roots, Σ, ∫, limit, vector, chemistry, matrix), with a live preview.

**Types in v1 (decided):**
| Type | Student answers | Marking |
|---|---|---|
| Single correct | one option | marks if right, minus `negative_mark` if wrong, 0 if blank (current grader) |
| Multiple correct | one or more options | `multi_marking` per exam: **`all_or_nothing`** (default: full marks only for the exact set) or `partial` (marks × correct-picked ÷ total-correct, but 0 + negative if any wrong option is picked) |
| True / False | True or False | same as single correct (fixed options a = True, b = False) |
| Numeric | a number | right if within `tolerance` of the key (default exact), else negative mark; blank = 0 |

Multiple-correct questions can tell students how many to choose ("Choose 2 answers"); that's on by default and can be switched off per question.

**Authoring UI** (rebuilds what commit `3ca9f2fc` removed on 2026-08-24):
- Bank list and editor: question (text/formula + image), 2–6 options (text/formula and/or image), the type, the key, marks, negative mark, difficulty, explanation, and a live "as students see it" preview.
- **XLSX import** with a template for all four types (text and formulas; images are added afterwards in the editor), a row-level error report and a preview before commit.
- **Paper builder**: sections from banks (pick N, fixed or random), total marks check, "preview as student" on phone and desktop sizes.

## 8. Security and integrity
- **Logins:** bcrypt (portal passwords) on a bounded pool; passcodes as HMAC with a per-exam secret, unambiguous 10-character alphabet. Lock after 5 failures per username/hall ticket for 10 minutes. Per-IP rate limits at Cloudflare and the load balancer.
- **Password-hash copies** stay only in redis-exam (private network, AUTH + TLS) and are deleted after close.
- **Device token:** `HttpOnly; Secure; SameSite=Strict`, bound to one attempt, expires at deadline + 10 min. Launch and admin tokens are ed25519-signed, short-lived and scoped.
- **Paper confidentiality:** the R2 bundle is encrypted, and the key is served only after T0. Per-attempt seeded order. The answer key is never in the client, the engine, R2 or the CDN.
- **Requests:** 64 KB body limit, OpenAPI validation, answers checked against each question's type and the attempt's own option keys.
- **Code/password sharing:** single-device binding, start PIN in supervised mode, watermark, flags.
- **Audit:** everything consumed into Laravel is logged through `PlatformAuditLogger`.

## 9. Infrastructure and cost (decided: "best and cheap")

**Recommendation: DigitalOcean Bangalore (BLR1) for the engine and Redis, plus Cloudflare (free plan + R2) in front, from Stage A onwards.**
- **Bangalore is the closest major cloud region to Kerala.** Droplets bill per second (since Jan 2026), so extra nodes for a 6-hour exam cost cents, not a month's rent.
- **Bandwidth is cheap and simple:** allowances are pooled across droplets, with overage at $0.01/GiB.
- **Cloudflare R2 has no egress fees,** so 60,000 paper downloads cost nothing extra.
- **Why not the others:**
  - AWS Mumbai: more complex and pricier bandwidth.
  - Hetzner: no India region, and a small traffic allowance in Singapore.
  - Current aaPanel server only: can't reach 60k, and shares failure with the whole platform.
  - Oracle free tier: capacity and support are too uncertain for exam days.
- **Laravel stays on aaPanel** and calls the engine's internal HTTPS API (bearer token); it never connects to Redis.

| Component | Always on (Stage A) | Added for a busy slot (Stage B, 60k) |
|---|---|---|
| Load balancer (DO) | 1 | — (targets droplets by tag, so new nodes join automatically) |
| Engine | 1 × CPU-optimized 2 vCPU / 4 GB | + 3 × CPU-optimized 4 vCPU / 8 GB (starting point; the load test calibrates it) |
| redis-exam | 1 × 2 vCPU / 4 GB, AOF + daily snapshot | resized to 4 vCPU / 8 GB + 1 replica; Sentinel ×3 on the redis and engine nodes |
| Cloudflare DNS/CDN/DDoS + R2 | free plan; R2 within the free tier (≤ 10 GB) | same |
| Monitoring | Prometheus + Grafana on the redis node, or Grafana Cloud free tier | same |
| **Approx. cost** | **≈ $80–90 / month (≈ ₹7,000–8,000)** | **≈ $5 per busy exam day** (~3 nodes × 6 h at ≈ $0.125/h + Redis replica) |

Prices are approximate, from third-party 2026 price summaries; check DigitalOcean's and Cloudflare's own pricing pages before budgeting. One-off: the 90k load test needs ~10 load-generator droplets for a few hours, ≈ $5–10.

**Scale-out is automatic, from bookings:** a scheduled command reads `exam_engine_bookings`. At T-3h before any slot whose booked concurrency is over 2,000, it creates the extra droplets from the engine image with `doctl` (tagged, so the load balancer picks them up), resizes Redis and adds the replica. Scale-in happens after close and reconciliation. Starting rule, to be calibrated by load test: +1 engine node per 20,000 booked students, +1 if `portal_password` is the only entry mode.

**Deploy:** the engine image is built from `exam-engine/deploy`. The client goes to R2 via CI or a make target. There's an exam-day freeze on both apps from T-2h until close.

## 10. Testing and go-live gates

| Test | Tool | Pass criteria |
|---|---|---|
| Engine units (clock, state machine, seed, diff/checksum, pacing, login queue, answer shapes) | `go test` | all transitions covered |
| Contract | OpenAPI + pack JSON schema in Go, vitest and PHPUnit | all three validate the same fixtures |
| Grader | PHPUnit | single / multiple (both marking modes) / numeric (tolerance) match hand-worked cases; in-app and engine paths agree on single |
| Laravel arm / passcodes / password sync / bookings / consume | PHPUnit feature tests | as specified |
| Client e2e (check-in, offline 5 min, refresh, device switch with approval, auto-submit, resync, phone viewport) | Playwright | no answer lost; order stable |
| **Stage A load**: 3,000 virtual students incl. 1,500 password logins in the last 15 min | k6 | p95 sync < 300 ms, errors < 0.1%, **0 lost answers**, login queue wait p95 < 60 s, consumer lag 0 within 5 min |
| **Stage B load**: 90,000 virtual students | k6 distributed | same criteria with scale-out triggered by a real booking; pacing kicks in cleanly; Redis latency < 10 ms |
| Chaos | manual, during load | kill an engine node; fail over the Redis primary; cut the Laravel ↔ engine link for 10 min; 5,000 clients offline for 5 min. No answers lost. |
| Dry run | ≥ 200 real students, ≥ 3 schools, mixed school and home, phones and computers | reconciliation exact |

**Ramp rule:** live exams at about **2k → 10–15k → 60k** platform-wide. Each step must pass its gates and leave no unresolved incidents.

## 11. Decisions

### 11.1 Decided (2026-10-15)
| # | Topic | Decision |
|---|---|---|
| 0 | Engine language | **Go**; exam client in Vue 3 + TypeScript |
| 1 | Who conducts | **Each Sahodaya runs its own exams**; no State level for now. 60k is the platform-wide total across Sahodayas → capacity booking (§6) |
| 2 | Where students write | **Anywhere** (school, home, …) → per-exam `supervision` = `supervised` / `open` (§4.5), mobile-first |
| 3 | Entry | **Separate exam login page**; portal username + password, or hall ticket + per-student passcode; per-exam `entry_mode` (§4.2) |
| 4 | Score visibility | **Per-exam setting** `show_score_on_submit` |
| 5 | Question types | **Single correct, multiple correct (e.g. 2 right answers), true/false, numeric**; question and option content as text, image or both, with special characters and formulas (§7) |
| 6 | Authors | **Sahodaya admins + `exam_controller`** (§7) |
| 7 | Hosting | **DigitalOcean BLR1 + Cloudflare**, scaled by the exam calendar (§9) |
| 8 | Support | **`school_mcq_coordinator` first line, Sahodaya exam team second** (§5.4) |

### 11.2 Still open (small; can be settled during Phase 0)
1. **Exam domain**: which domain hosts `exam.…`? Its DNS must be on Cloudflare; a new short domain is fine.
2. **Multiple-correct default**: `all_or_nothing` as the default, with `partial` as an option. Confirm.
3. **Coordinator extend-time cap**: 10 minutes. Confirm.
4. **aaPanel server location and provider**, for latency between Laravel and the engine in Bangalore.
5. **Who owns the DigitalOcean and Cloudflare accounts and billing.**

## 12. Phases

| Phase | Scope | Deliverable | Rough size |
|---|---|---|---|
| 0 · Setup | §11.2, DO + Cloudflare accounts, redis-exam, LB, `exam-engine/` skeleton, OpenAPI draft, CI to R2 | engine `/health` live at `exam.<domain>` | 4–5 days |
| 1 · Questions | §7 authoring for 4 types with images and formulas, XLSX import, paper builder, `McqGrader`, data model §6.1, passcodes on hall tickets | **Built 2026-10-16** (see §14) | 2–2.5 weeks |
| 2 · Engine core | login page (password queue + passcode + launch token), check-in + system check, waiting room, encrypted bundle + key release, diff sync + pacing, submit + sweeper, result polling; Laravel bridge, bookings, password sync, consumer; offline-first client | end-to-end exam on staging, graded into Postgres | 4 weeks |
| 3 · Live ops | monitor (exam → school → student), role-scoped actions (§5.4), school coordinator view, reconciliation, close, booking calendar | coordinators and controllers can run an exam from the UI | 1.5 weeks |
| 4 · Stage A go-live | 3k load test, chaos, dry run, runbook, **first real exam** | Stage A gates passed | 1–1.5 weeks |
| 5 · Stage B | automated scale-out/in from bookings, Redis replica + Sentinel, monitoring + alerts, distributed 90k load test | Stage B gates passed | 2 weeks |
| 6 · Ramp | real exams to 10–15k, then 60k platform-wide | 60k live | per exam calendar |
| Later | random pools per student, bilingual side-by-side questions, kiosk/SEB mode for labs, item analysis, optional webcam snapshots (privacy and consent review for minors), State-level exams | — | — |

## 13. Exam-day runbook (outline)
- **T-48h**: exam armed automatically; check-in opens. School MCQ coordinators chase students who haven't checked in or whose system check failed.
- **T-3h**: (busy slot) scale-out runs; verify all nodes healthy in the LB, Redis replica in sync, dashboards and alerts on.
- **T-2h**: deploy freeze.
- **T-60**: waiting room opens; watch entries by school and the login queue length.
- **T0 → end**: watch p95, errors, offline counts and pacing level. Coordinators handle passcodes, device switches and short extensions; the Sahodaya team handles escalations and broadcasts.
- **End + 5 min**: close; reconciliation must show submitted = entered; consumer lag 0.
- **After**: existing ranking → publish (pre-computed results cache) → certificates. Export engine logs for the audit file. Scale-in after the data is verified in Postgres. Run data is purged after 7 days.

## 14. Phase 1 status (2026-10-16)

**Built.** Not yet committed.
- **Question banks:** Talent Search → Question banks (Sahodaya admin) and Question banks in the exam portal (exam controllers). Shared code: `app/Http/Controllers/Concerns/ManagesMcqQuestionBanks.php`; components under `resources/js/Components/mcq/`.
- **Validation and import:** `app/Services/Mcq/McqQuestionInput.php` (one set of rules for the editor and the import) and `McqQuestionImporter.php` (XLSX/CSV preview, then commit).
- **Grader:** `app/Services/Mcq/McqGrader.php`, shared by the in-app exam now and the engine's consumer later. Single-correct results are unchanged.
- **Paper builder:** each exam's **Paper** tab (sections, pick all / first N / random N, ordering, shuffle and multiple-correct marking settings) plus **Preview paper**. Code: `app/Services/Mcq/McqPaperService.php`. Random picks are seeded per exam + bank. The per-student shuffle is seeded per registration, which fixes the old reshuffle-on-reload.
- **Passcodes:** `app/Services/Mcq/McqExamPasscodeService.php`. Created with each hall ticket and printed on online exams' tickets (design switch "Online exam passcode"). Reissue is on the Hall tickets tab. The school's "portal logins" export gains an Exam passcode column for online exams.
- **Today's in-app online exam:** shows question and option images and formulas (`McqRichText.vue`). It delivers single-correct and true/false papers only. A paper with multiple-correct or numeric questions shows a warning and students can't start it until the engine (Phase 2) exists.
- **Fixed along the way:** "Print all hall tickets" (Sahodaya and school) crashed with an undefined `$design` / `$logoUrl`.
- **Tests:** `tests/Unit/Services/Mcq/McqGraderTest.php`, `tests/Feature/McqQuestionBankAuthoringTest.php`, `tests/Feature/McqPaperAndPasscodeTest.php`. 93 MCQ-related tests pass.
- **Deploy notes:** run `php artisan tenants:migrate-sahodayas` for the new tenant migration (`2026_11_25_000001_…`). `npm ci && npm run build` is needed for the new `katex` dependency.

