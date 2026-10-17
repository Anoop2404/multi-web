# Exam client — notes

The student exam app (login → check-in + system check → waiting room → exam → result) for the
Go exam engine. Binding contract: `exam-engine/CONTRACT.md`. This file records how the client
reads that contract, the test vectors the engine can share, and every clarification or
deviation. Nothing here changes `CONTRACT.md`.

## Run, build, test

```bash
cd exam-engine/client
npm install
npm run dev        # Vite on http://127.0.0.1:5180, /api proxied to the engine on http://127.0.0.1:8090
npm run dev:mock   # same, but with an in-memory fake engine (dev/mockApi.ts) — no Go/Redis needed
npm test           # vitest (checksum, shuffle, diff queue, reconcile, clock/schedule, passcode, AES-GCM, rich text)
npm run typecheck  # vue-tsc
npm run build      # → dist/ (index.html + hashed assets/, base '/')
npm run size       # gzipped JS/CSS sizes; fails if all JS > 150 KB gzip
```

Mock engine (`npm run dev:mock`): any Sahodaya code except `NONE`; password `demo` with any
username, or hall ticket anything + passcode `K7M2P-Q9RTX`, or `/?launch=demo`. Usernames `busy`,
`locked`, `conflict` return those errors. The exam starts `MOCK_START_IN` seconds (default 20)
after the attempt is created; `MOCK_SUPERVISED=1` requires start PIN `1234`; `MOCK_SHOW_SCORE=0`
hides the score. Controls: `POST /__mock/lock|unlock|bump|end|reset`,
`/__mock/broadcast?text=…`, `/__mock/extend?minutes=5`, `/__mock/fail?count=3`; `GET /__mock/state`.

Bundle size (2026-10-16 build): initial JS 52.9 KB gzip, KaTeX chunk 73.7 KB gzip (loaded lazily
in the waiting room), **all JS 126.6 KB gzip**; CSS 3.7 KB + KaTeX CSS 7.6 KB gzip; KaTeX fonts
are separate files fetched by the browser on demand (woff2 first).

### Serving

- The engine serves `dist/` (`EXAM_ENGINE_STATIC_DIR=…/exam-engine/client/dist`, or copy `dist/*`
  into `internal/web/dist` before `go build` for the embedded copy). Every non-file path
  (`/MLP`, `/MLP?launch=…`) must fall back to `index.html` — the engine's `web.Handler` does this.
- `assets/*` are content-hashed (immutable); `index.html` must be `no-cache`.
- A Sahodaya whose code is literally `ASSETS` would collide with the asset folder (not expected).
- Build target is ES2019 / Safari 13 / Chrome 80. There is no regex lookbehind anywhere in the
  bundle (it is a parse error on iOS < 16.4 and can't be transpiled) — keep it that way.
- No runtime downloads from the internet. Fonts: system stack plus `Noto Sans Malayalam`,
  `Malayalam Sangam MN`, `Nirmala UI`, `Kartika`, `Manjari`, `Rachana`, `Meera` when installed.

## Answer checksum (CONTRACT §1) — shared test vectors

Canonical string: for each answered question, ordered by numeric question id ascending,
`"{qid}={value}\n"`; value = option key, or the `multiple` keys sorted by code point and joined
with `,`, or the numeric string trimmed. **Entries whose value is `null`, `""` (after trimming) or
`[]` are unanswered and produce no line.** Checksum = lowercase hex SHA-256 of the UTF-8 string.
(The engine's `syncer.Canonical` already does exactly this.)

| answers (JSON) | canonical string | sha256 |
|---|---|---|
| `{}` | `` (empty) | `e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855` |
| `{"12":"b","3":["c","a"],"7":"12.50"}` | `3=a,c\n7=12.50\n12=b\n` | `e566b5b54dbb2a2711b5e521a96eabcc23032c325733469f3692a0f98c675165` |
| `{"5":" -0.5 ","9":[],"10":""}` | `5=-0.5\n` | `7dfb85e97c639f8fda0c7e1582a4920edeae9efcbb0210b8649bca499b208a96` |
| `{"5503":"-3","5501":["a","c"],"5502":"b"}` | `5501=a,c\n5502=b\n5503=-3\n` | `2189f85b8ae46c4aea448e7b2be4d679bbb1d1c167875fde96945b84def002ed` |
| `{"100":["d","b","a"],"10":"b","2":"a"}` | `2=a\n10=b\n100=a,b,d\n` | `ec420cb2df0f9314107a63eae4e173c7b18c266775f35ebd14a931bb9ccc8574` |

Hashes were computed independently with `printf '<canonical>' | shasum -a 256` and checked in
Go (`crypto/sha256`). Tests: `test/checksum.test.ts`.

## Question order (shuffle)

Used only when `shuffle_questions` is true; `seed` comes from `/api/state`.

1. **PRNG — mulberry32**, state = `seed >>> 0` (uint32; negative/large seeds wrap mod 2³²):
   ```js
   a = (a + 0x6D2B79F5) >>> 0;
   t = a;
   t = Math.imul(t ^ (t >>> 15), t | 1);
   t ^= t + Math.imul(t ^ (t >>> 7), t | 61);
   return ((t ^ (t >>> 14)) >>> 0) / 4294967296;   // [0, 1)
   ```
   Go (uint32 arithmetic wraps the same way):
   ```go
   m.a += 0x6D2B79F5; t := m.a
   t = (t ^ (t >> 15)) * (t | 1)
   t ^= t + (t^(t>>7))*(t|61)
   return float64(t^(t>>14)) / 4294967296.0
   ```
2. **Fisher–Yates** from the end: `for i = n-1 … 1: j = floor(next() * (i+1)); swap(i, j)`.
3. **Sections stay together**: questions are grouped by `section` (`null` = its own group) in the
   order each section first appears in the bundle; each group is shuffled in turn with **one
   shared PRNG stream** (group 1 consumes its draws, then group 2, …). With a single section this
   is a plain whole-list shuffle. (Laravel's in-app path shuffles the whole list with PHP Mt19937;
   the engine client is independent of that.)
4. Display numbers are positions 1…N after ordering. **Options are never shuffled** (the contract
   has no flag for it; true/false must stay a/b).

Vectors (also in `test/shuffle.test.ts`, verified in Go): seed `101` → first five outputs × 2³² =
`582603121, 3284799363, 2321895009, 2761734473, 1649615189`; shuffling `[1..10]` with seed `101`
→ `[10, 1, 9, 6, 4, 3, 8, 5, 7, 2]`.

## Sync, offline and submit — how the client behaves

- **Device copy first.** Every answer/flag change is written to IndexedDB at once (db
  `sahodaya-exam`, store `kv`, key `att:{run}:{reg_id}`; falls back to localStorage, then memory).
  The record holds the full map, the last server-acknowledged map (`acked`) and `serverRev`,
  a local rev counter, pending proctor events, the paper key, the last state and the clock offset.
- **Diffs.** A sync sends `changes = diff(acked → current)` (changed/new keys with values, removed
  keys as `null`), `base_rev = serverRev`, the full `flagged` list (always sent), the checksum of
  the full current map, and up to 50 queued events. An accepted reply acknowledges exactly the
  snapshot that was sent; edits made while the request was in flight stay pending.
- **Pacing.** Never sooner than `next_sync_in × (1 + 0–20 % jitter)` after the previous attempt.
  After a change: ~3 s debounce, but a change is never held more than 10 s. Without changes: a
  heartbeat every `max(heartbeat_seconds ?? 30, next_sync_in) × jitter`. Failures back off 2, 4, 8,
  16, 30 s (× jitter); `Retry-After` and (for server refusals) the pace are honoured; network
  failures retry immediately on the browser's `online` event.
- **Replies.** `none` → ack. `resync` (or `checksum_ok:false`) → `POST /api/resync` with the full map
  at once. `lock` → "Paused by the invigilator" overlay, nothing acknowledged, keep polling.
  `submit` → ack only if the rev moved (late changes past grace are refused), then final submit.
  HTTP 401 → "Signed out on this device" (device switch / session gone). 423 → lock overlay.
  409 `closed`/`already_submitted` → finalise. 400 `invalid` → resync.
- **Proctor events** (`tab_hidden` on `visibilitychange`→hidden, `window_blur` only while the page
  is visible, `fullscreen_exit` after the student used the optional Full screen button, `offline`
  with `at` = first failure and `seconds` = gap) are batched; the same type is not re-recorded
  within 2 s. **They count as delivered on any HTTP 200 sync/resync reply** (including `lock` and
  `resync`), because the engine records them before checking revs or locks.
- **Reload** (`reconcile()` in `src/lib/answers.ts`): equal content → in sync. Server rev lower than
  ours (store failover) or server content is something this device sent (its checksum is in the
  last 8 checksums we sent) → keep the device copy and resync. Otherwise the server is newer
  (another device after a switch) → take it; unsynced device answers are kept in the record as
  `discarded`. If `/api/state` is unreachable on reload but the page loads, a `writing` attempt
  continues offline from the device copy (paper key + cached bundle) and syncs when back.
- **Deadline.** Countdown = `deadline − (device now + offset)`, offset taken from the response with
  the smallest round-trip among the last five (every response with `server_time` is a sample).
  At the deadline the UI locks and the final submit fires after a random
  `0 … min(30, grace_seconds − 30)` s (grace from `/api/state`, assumed 90 if absent), so ≥ 30 s of
  grace remain for retries.
- **Final submit.** If a resync is pending it runs first; then `POST /api/submit {base_rev, changes,
  checksum}`; retried with backoff until it succeeds (`already_submitted`/`closed` → read the receipt
  from `/api/state`; 423 → wait; 409 `invalid` = engine's "resync needed" → resync and retry).
  Manual submit opens a confirmation that lists unanswered and marked question numbers.
- **Result.** `GET /api/result` once; `pending` → every 10 s for up to 10 min, then a "Check again"
  button; `hidden` → "Your answers are submitted. Results will be published by your Sahodaya.".

## Other behaviour worth knowing

- **Routing.** `/` → code entry; `/{code}` → login (code upper-cased, `[A-Za-z0-9_-]` only);
  `?launch=<token>` on any path → `POST /api/login {method:"launch"}`, and the token is stripped
  from the address bar before the request. Boot always tries `GET /api/state` first (an existing
  cookie skips the login).
- **Login.** Both tabs are shown unless every run in `GET /api/sahodaya/{code}` carries the same
  `entry_mode` (`portal_password` or `exam_passcode`), in which case only that tab is shown. Passcode
  field shows `XXXXX-XXXXX` while typing and sends the normalised 10 characters. `busy` /
  `rate_limited` / 5xx auto-retry after `Retry-After` (default 5 s) + 0–2 s with a visible
  countdown; `locked` shows the wait from `Retry-After`; `device_conflict` asks the student to get
  the coordinator to allow a new device.
- **Waiting room.** `GET /api/state` is polled sparsely: every `max(60, 6 × next_sync_in)` s × (1 +
  0–50 %) in the waiting room; in check-in every 60–300 s, plus one wake-up 2–32 s after
  `waiting_opens_at`. The encrypted bundle is prefetched after a random delay of up to
  `min(120 s, time-to-start / 4)` (min 3 s) and cached in IndexedDB (`bundle:{run}:{version}`;
  older versions of the run are deleted). KaTeX is loaded at the same time.
- **Start.** The button enables at `starts_at` (server clock). A click within the first 10 s waits a
  random 0–5 s before `POST /api/start` (spreads the T0 key burst, plan §3). `not_open_yet` → wait
  and retry; `wrong_pin`, `closed`, `locked`, `forbidden` → messages; transient errors auto-retry.
- **Paper.** AES-256-GCM via WebCrypto (iv 12 bytes, ciphertext‖16-byte tag, key 32 bytes; standard
  or URL-safe base64 accepted). A cached bundle that fails to decrypt is deleted and re-downloaded
  with `cache: 'reload'` (the URL is served `immutable`, so a re-arm must bump `bundle_version`).
- **System check** (browser features, a real AES-GCM + SHA-256 round trip, IndexedDB round trip,
  screen size, `navigator.onLine`, median of three timed `GET /api/state`) runs on the check-in
  screen and is posted once per device (`latency_ms` is `0` if it couldn't be measured).
- **Exam UI.** One question per screen, palette (answered / not answered / marked), section
  headings, marks and negative marks shown, true/false as two big buttons, "Choose N answers" when
  `show_count` is set, numeric field with `inputmode="decimal"` plus a ± button (iOS decimal pads
  have no minus key). Numeric input keeps digits, one `.` and a leading `-`; `,` becomes `.`; the
  value is stored only when it matches `^-?(\d+\.?\d*|\.\d+)$` (so `12.`, `.5` are kept as typed).
  Multiple-choice values are de-duplicated and sorted; an empty selection clears the answer.
  Arrow keys / PageUp / PageDown move between questions; text size A−/A+; images open enlarged on
  tap; watermark "name · hall ticket" over everything (pointer-events: none).

## Contract clarifications and deviations

1. **Optional fields used when present:** `grace_seconds`, `heartbeat_seconds` (from `/api/state`)
   and `runs[].entry_mode` (from `/api/sahodaya/{code}`). The engine already sends all three;
   defaults when absent: 90 s, 30 s, both tabs.
2. **Canonical checksum omits** `null`, `""` and `[]` values (the contract only says "every
   answered question"); identical to the engine's `syncer.Canonical`.
3. **Proctor events are considered delivered on any 200 reply** to `/api/sync` (also `lock` /
   `resync`). The client never sends events with `/api/resync` or `/api/submit` (not in the
   contract), so events queued after the last sync before a submit are dropped.
4. **`flagged` is sent on every sync** as the full list (never omitted).
5. **`action: "submit"` with changes**: the client treats its changes as stored only if `rev`
   increased; it then calls `/api/submit` with whatever is still pending.
6. **Submit with a stale base**: the client relies on `409 invalid` (engine message "resync") to
   resync and retry. It does not use the engine's extra `answers` field on `/api/submit`.
7. **Options are not shuffled**; questions are shuffled within sections (see "Question order").
8. **Sahodaya code is upper-cased by the client** before `GET /api/sahodaya/{code}` and login.
9. **Final-submit delay** is `0 … min(30, grace − 30)` s rather than a flat 0–30 s, so short grace
   windows still leave time for retries.
10. **T0 jitter** is applied to the start request (0–5 s, first 10 s only), not to enabling the
    button.
11. **Session cookie** is expected to survive a browser restart until the run ends (the engine sets
    `Max-Age`), so a checked-in device goes straight to the waiting room on exam day.
12. No service worker: a reload while fully offline needs the page itself to load from the browser
    cache; the exam then continues from IndexedDB.
