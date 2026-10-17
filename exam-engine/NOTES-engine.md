# Engine notes — decisions and clarifications

Where `CONTRACT.md` was silent or ambiguous, the engine does what is listed here. Items marked
**(bridge)** or **(client)** matter to the Laravel bridge or the exam client.

## Additions to the contract

1. **Meta `questions` map (bridge).** `PUT /internal/runs/{run}` accepts an optional
   `"questions": {"5501": "multiple", …}` (question id → type; `{}`/`[]`/absent all mean "not
   sent"). The engine validates answer shapes with it. In addition, the bundle PUT carries the
   paper key, so at `PUT …/bundle` the engine checks the SHA-256, **decrypts the paper once** and
   extracts each question's id, type and option keys (never answers; there are none in the paper).
   That paper schema takes precedence: answers must use an option key of that question. If meta
   sent `questions` and it disagrees with the paper (count, ids or types), the bundle PUT fails
   with `400 invalid` naming the question. With neither, only generic shapes are checked.
2. **Run-wide incident controls (bridge, ops).**
   - `POST /internal/runs/{run}/extend` `{"minutes": 1..600, "actor": "…", "reason": "…"}` →
     `{"ok": true, "minutes": 10, "extended_minutes": <run total>}`. Adds a run-level extra
     (`ex:{run}:ctl` field `xm`). Stored attempt deadlines exclude it; every Lua script adds it to
     get the effective deadline, so writing attempts move instantly (O(1), atomic) and later starts
     include it. Emits `run.extended {minutes, actor, reason}` with `reg_id: null`. 409 if closed.
     **(bridge)** Record it at exam level; don't add it to each registration's
     `extra_time_minutes` that is re-sent as roster `extra_minutes` (that would double-count).
     Student `extra_minutes` and monitor rows report per-candidate + run-level extra.
   - `POST /internal/runs/{run}/hold` `{"on": true|false, "actor": "…", "reason": "…"}` →
     `{"ok": true, "hold": true}`. While on: the sweeper auto-submits nothing in that run; a sync
     from an attempt past its (effective) deadline gets `action: "lock"`, changes are not applied,
     and `messages` carries an extra message with `id: -1` ("Time is up, but the exam team has
     paused submission…"); a student submit past the deadline gets `423 locked` with
     `"action": "lock"`; `GET /api/state` shows `state: "locked"`. Before the deadline nothing
     changes. Close still force-finalises. Emits `run.hold {on, actor, reason}` (`reg_id: null`).
   - `/summary` has `extended_minutes` and `hold`.
3. **`POST /api/submit` extensions (client).** Optional `"answers": {…full map…}` replaces the
   stored map (like resync) and then finalises — the most robust final submit. Response also has
   `"mode"`.
4. Extra fields in `GET /api/state`: `grace_seconds`, `heartbeat_seconds`, `entry_mode`; in
   `/api/sahodaya/{code}` runs: `entry_mode`. `GET /internal/runs/{run}` returns the stored meta
   without `passcode_secret` (debugging/bridge checks).
5. New event types: `run.extended`, `run.hold` (Laravel's processor already ignores unknown types).

## Sync, resync, submit

6. Sync with changes costs two Redis round trips (read rev+answers, then an atomic CAS Lua script)
   because Redis Lua has no SHA-256. Heartbeat syncs (no changes) are one script call: the stored
   checksum is compared inside Lua. The script does rev check + apply + agg update + events atomically.
7. `rev` increases only when answers or flagged actually change (a pure heartbeat keeps it).
8. `flagged` absent = keep; present = the full list (sorted, de-duplicated, ids not on the paper
   dropped).
9. Answer normalisation (same as the client's checksum): strings trimmed; `multiple` sorted and
   de-duplicated; `null`, `""` and `[]` all mean cleared; a lone string for a `multiple` question
   counts as one key; numeric accepted as a string or a bare JSON number (digits plus `+-.,eE `,
   ≤ 32 chars). Unknown question or option → `400 invalid` for the whole request.
10. Proctor `events`: unknown types dropped, more than 50 truncated (not rejected), `at` normalised
    (unparseable → server time). They are recorded even when the answer part answers `resync`.
11. Submit (client):
    - Final `changes` are accepted when the checksum of the resulting map equals the client's,
      even if `base_rev` is behind (the result is exactly the client's view). Otherwise
      **`409 {"error":"invalid","action":"resync","rev":N}`** — the client should resync (or send
      `answers`) and submit again. **(client)**
    - No changes + `checksum`: must equal the stored checksum (else the same 409). No checksum:
      the stored answers are finalised.
    - Mode: `manual` before the deadline, `auto` after it (the client's time-up submit and the
      sweeper), `forced` for admin `force_submit` and close.
    - After deadline + grace the changes are ignored and the stored answers are finalised (`auto`).
    - Locked → `423 locked`. Not started → `409 invalid`. Idempotent: a second call returns the
      same receipt. Receipt = `{CODE}-{exam_id}-{reg_id}-{4 hex}`.
12. Sync while not started: `action: "none"` (or `"submit"` once late entry has closed, `"lock"` if
    locked). Sync after submit: `"submit"`.
13. Rate limits are in-process per node: sync/resync/submit 6 per 10 s per session; login 10 per
    minute per IP + method + identity (launch tokens: per token, so a school behind one NAT address
    doesn't share a budget); wrong start PINs 5 per 10 min per attempt (`429 rate_limited`).

## Login, devices, start

14. Credentials are verified before policy checks, so `403`/`409` never reveal a registration to
    someone without valid details. Only the lockout (`423`) is checked first. Unknown identities
    get the generic `401` (no dummy bcrypt).
15. Lockout (5 failures → 10 min, `ex:{run}:fail:{id}`): password `u:{username}`; passcode
    `t:{hall_ticket}|{ip}` — hall ticket numbers repeat across classes, so one student's typos must
    not lock out classmates elsewhere. The 5th failure itself answers `423`.
16. Run choice for an identity in several runs: open runs before closed ones, then the window rule
    from §4, then the soonest upcoming, then the most recently ended. Login before
    `checkin_opens_at` → `409 not_open_yet` (with `opens_at`); closed → `409 closed`.
17. Hall tickets are normalised (trim + upper-case); Sahodaya codes are upper-cased.
18. Launch tokens: the signature is checked over the raw JSON bytes (what
    `ExamEngineLaunchToken` signs) or, as a fallback, over the base64url payload segment. `exp`
    must be ≤ now + 180 s; `jti` (8–128 url-safe chars) is burned with `SET NX EX 300`.
19. Devices: `allow_switch` is one-time and consumed by the next successful login. Logout releases
    the binding at once. Device activity = sync, state polls (written at most every 15 s), system
    check, start. `attempt.device_switched` is emitted whenever an existing binding is replaced
    (approved or after idle). The old session is deleted; a cached copy on another node is still
    refused because every operation re-checks the attempt's device id.
20. Start: before `starts_at` → `409 not_open_yet` (with `starts_at`); after `late_entry_until`
    → `409 closed`; wrong PIN → `403 wrong_pin`. `paper_key` appears in state once started.
21. Sessions: `exam_session` cookie (HttpOnly, SameSite=Lax, Secure on HTTPS or trusted
    `X-Forwarded-Proto: https`), lifetime until a day after the run's latest possible end (≥ 1 h).
    Any `401` clears the cookie. `X-Exam-Client: 1` is required on every student POST (`403`).

## Internal API / monitor

22. `counts.checked_in` is cumulative (ever signed in); `waiting` = signed in, not started;
    `writing` = started, not submitted (locked included); `locked` = currently locked; `offline`
    = writing and not seen for > 60 s; `unfinished` = writing. Extras: `flagged`, `pace_override`,
    `extended_minutes`, `hold`.
23. `/attempts`: rows of candidates who never signed in have `state: "not_checked_in"`; the
    `state` filter also accepts `offline`; `per_page` ≤ 500; sorted by school, class, name; `q`
    matches name, hall ticket, username, email, reg id. Extra row fields `late_changes`, `offline`;
    `deadline` and `extra_minutes` include the run-level extra.
24. Actions: `extend` (whole minutes 1..600) works before start and while writing (`409
    already_submitted` after); `lock` also works before check-in (the candidate signs in to
    "locked" and can't start); `force_submit` of a never-started attempt → `409 invalid`; unknown
    reg → `404`. The action event is emitted after the state change.
25. Per-candidate `extend` adds to the engine's copy of the roster's `extra_minutes`; a roster PUT
    sets it absolutely. **(bridge)** Re-sending a roster entry before the `attempt.action extend`
    event is consumed would undo that extension.
26. Meta changes after `starts_at`: anything except `start_pin`, `late_entry_minutes`,
    `show_score_on_submit`, `sync_interval_seconds` → `409 locked`. A bundle change after
    `starts_at` → `409 locked` (re-sending the identical bundle is OK). State reports the stored
    bundle's version (`bundle_url` uses `%3A`); `GET /api/bundle/{run}/{v}` serves only that version.
27. Close: force-finalises writing attempts, marks the run closed, emits `run.closed` once
    (closing again is a no-op), answers `{"ok": true, "unfinished": 0, "forced": N}` and sets a
    7-day TTL on every run key. Closed runs disappear from `/api/sahodaya`. The sweeper also
    force-finalises any straggler that started on a node with a stale cache. Runs never closed are
    expired 7 days after their end. Disarm is idempotent; after `starts_at` it's `409 locked`.
28. A malformed run id on internal endpoints answers `404 not_found` like an unknown run
    (`PUT /internal/runs/{run}` answers `400 invalid`). Empty bodies and PHP's `[]` are accepted
    for disarm/close/pace. Oversized bodies → `413` with code `invalid`.

## Events

29. **No MAXLEN on XADD.** The sweeper leader trims `ex:events` every 5 s with
    `XTRIM MINID ~ <id>`, id = the older of the `laravel` group's last-delivered-id and its oldest
    pending entry, so only delivered **and** acknowledged events are removed (approximate trimming
    removes whole stream nodes only). `EXAM_ENGINE_EVENTS_HARD_CAP` (default 0 = off) adds
    `MAXLEN ~ cap` to every XADD as an emergency memory guard — it can drop unconsumed events.
30. `attempt.snapshot` at most every `EXAM_ENGINE_SNAPSHOT_SECONDS` (default 300, minimum 120)
    per attempt, only when the answers changed; the first one no earlier than that after start.
31. Claim: own pending entries first; if none, entries idle > 5 min in other consumers'
    pending lists are taken over (`XAUTOCLAIM`, covers renamed/dead consumers); then new entries
    (block ≤ 10 s, count ≤ 1000). Pending entries whose data was trimmed are acknowledged and skipped.
32. Events are written inside the same Lua script as the state change (exactly one event per
    transition). This touches the global `ex:events` key from scripts on `{run}` keys, and a few
    scripts touch attempt keys not listed in KEYS (same hash tag). Fine on standalone and Sentinel
    (the plan); Redis Cluster would need per-run streams.

## Redis layout additions (§8)

`ex:{run}:qs` (paper schema), `ex:{run}:dl` (writing attempts by stored deadline — the sweeper's
index), `ex:{run}:extra` (reg → per-candidate extra minutes), `ex:{run}:schools` (school id →
name), `ex:{run}:ctl` (`closed`, `closed_at`, `pace`, `xm`, `hold`, `hold_at`), `ex:runs` (armed
runs, for the sweeper). `ex:{run}:seen` holds writing attempts only. Run ids are restricted to
`[A-Za-z0-9._-]+:[0-9]+`.

## HTTP / ops

33. `/health` returns exactly `{"ok", "redis", "version"}`; version comes from
    `-ldflags "-X main.version=…"` (`make build VERSION=…`). The embedded client lives in
    `internal/web/dist` (`CLIENT_EMBED_DIR` for the deploy scripts).
34. Metrics use the ops names: `exam_http_requests_total{route,code}` and
    `exam_http_request_duration_seconds{route}` with route ∈ login, state, system_check, bundle,
    start, sync, resync, submit, result, logout, internal, health, static (plus `sahodaya`,
    `metrics`, `other`); `exam_http_inflight_requests` (student API in flight — the pacing signal);
    `exam_login_queue_depth`; `exam_login_queue_wait_seconds`; `exam_pace_seconds` (this node's
    automatic level at the default 10 s base; per-run overrides are in `/summary`);
    `exam_attempts{run,state}` (open runs; states roster, checked_in, waiting, writing, submitted,
    locked, offline; read from Redis, cached 5 s, identical on every node — use `max`);
    `exam_submits_total{mode}`; `exam_events_stream_length`, `exam_events_pending`. Extras: logins,
    sync actions, sweeper auto-submits, snapshots, events claimed/acked/trimmed, Redis pool and errors,
    bcrypt pool, Go runtime.
35. `EXAM_ENGINE_TRUST_PROXY`: client IP from `CF-Connecting-IP`, else the left-most
    `X-Forwarded-For`, else `X-Real-IP`. SIGTERM drains in-flight requests for up to 30 s.
36. Missing `/assets/*` files answer 404 (never index.html); other unknown GETs get the SPA's
    index.html (`no-cache`); hashed assets are `immutable`; bundles are served from memory with
    gzip and an ETag.

## Load tests (2026-10-16, local)

One MacBook Air (8 cores) running the engine, Redis 8.8 (database 14) and the load generator
together, so latencies exclude real network time. Command:
`bin/loadgen -students N -questions 50 -duration 3m -lead 40s -login-spread 20s -compress 5
-grace 45 -password-percent 10 -bcrypt-cost 10` (sync cadence = next_sync_in ÷ 5 → ~2.3 s per
student, i.e. 5× real-life sync load; 20 % early submits, 1 % abandoned for the sweeper, 5 % offline
gaps, 0.5 % stale-rev probes, 0.3 % unprompted resyncs). Answers verified from the
`attempt.submitted` events claimed through the internal API.

| | 2,000 students | 3,000 students |
|---|---|---|
| student requests | 193,369 in 3m45s (861 req/s) | 288,830 in 3m43s (1,295 req/s) |
| errors | 0 (0.000 %) | 0 (0.000 %) |
| `POST /api/sync` | 149,864 · p50 0.5 · p95 **2.3** · p99 10.4 · max 103.7 ms | 225,231 · p50 0.5 · p95 **2.8** · p99 11.9 · max 118.2 ms |
| `POST /api/submit` | p95 2.4 · p99 7.8 ms | p95 3.4 · p99 12.1 ms |
| `POST /api/start` | p95 3.3 · p99 12.8 ms | p95 3.3 · p99 12.1 ms |
| login passcode / password | p95 112.6 / 406.2 ms | p95 4.0 / 75.3 ms |
| peak in-flight (generator) | 125 | 205 |
| answers checked / **lost** | 70,515 / **0** | 105,654 / **0** |
| submits | 1,974 client (manual 397, auto 1,603 incl. 26 sweeper) | 2,970 client (manual 603, auto 2,397 incl. 30 sweeper) |
| events after ack + trim | stream 3, pending 0 (7,542 trimmed) | stream 5, pending 0 (18,948 trimmed) |

Targets (p95 sync < 300 ms, errors < 0.1 %, 0 lost answers): met in both. No snapshots in a
3-minute exam (300 s cadence); snapshots are covered by the integration tests. Earlier runs before
the generator honoured the 6-per-10 s budget showed 0.007–0.010 % `429`s from the compressed
cadence and were otherwise identical. Pacing never left 10 s: in-flight stayed far below 500.
