# AUDIT 12 — Five-Agent Execution Plan

Five parallel implementation agents, each owning one area. The plan is designed so that no two
agents write to the same file in the same phase, no two agents run migrations on the
`state_kalotsav` database in the same phase, and no agent depends on another agent's uncommitted
work.

The plan assumes the P0-01 fix (`sourceEvents()` public) is already committed before any agent
starts, because every agent's work depends on a working nomination path.

## Agent A — Core and Data Integrity

**Owns:** `app/Services/State/`, `app/Models/State/`, the State database migration,
`tests/Feature/State/StateEndToEndLifecycleTest.php`.

**Phase 1 (first to start):**

1. Add the `points_excluded_from_standings` boolean to `state_fest_registrations`.
2. Filter the ranking in `StateResultService::ranking()` on the flag.
3. Filter the school/Sahodaya standings in `StateResultPublicationService::schoolRankings()`.
4. Support the flag from the incoming intake payload.

**Phase 2 (after Agent C starts):**

5. Write the multi-Sahodaya test fixture. This is a test-only change, so it does not conflict
   with anyone else's work.

**Phase 4:**

6. Add `announced_at` to `state_item_results`. Wait — this is also Phase 2's appeal-window work.
   Coordinate: Agent A writes the schema, Agent D writes the service logic that uses it.

**Conflicts to avoid:** `app/Services/State/Fest/StateResultService.php` is touched by A (ranking
filter) and D (re-rank banner). Sequence so A lands first, then D's work builds on top.
`state:migrate` must be run only by A.

---

## Agent B — Scoring and Results

**Owns:** `app/Services/State/Fest/StateGradingController.php`,
`app/Services/State/Fest/StateResultService.php` (D owns the banner, B owns the point logic),
`resources/js/Pages/Admin/State/Fest/Grades.vue`, `Points.vue`, `Results.vue`.

**Phase 1:**

1. Add the appeal-window gate to `StateAppealService::submit()` (uses `announced_at` column
   from Agent A).
2. Add `STATE_APPEAL_WINDOW_MINUTES` config (default 60).

**Phase 4:**

3. Standby certificate exclusion: read and fix `StateCertificateService::generate()`.

**Conflicts to avoid:** `StateCertificateService.php` is also touched by Agent E in Phase 5.
Sequence E after B's Phase 4 change. `StateResultService.php` is A's in Phase 1 and D's in
Phase 4; B does not write to it.

---

## Agent C — Frontend and UX

**Owns:** `resources/js/Pages/Admin/State/**`, the Inertia render props in the controllers
(the data, not the service logic), `tests/Feature/State/` UI tests.

**Phase 3:**

1. Audit-log viewer page and route (`state.fest.audit`, the existing capability). The data is
   already written by every other agent's service changes; C just reads it.
2. Post-appeal re-rank banner on `Fest/Results.vue`.

**Phase 4:**

3. Verify the chest-number flow works end-to-end (staging walk-through).

**Phase 5:**

4. Any UI work for the certificate reissue path (Agent E owns the service).

**Conflicts to avoid:** C changes controller render methods, not service methods. If A has added
a field to the ranking response, C consumes it but does not define it. C's Vue files are its own.

---

## Agent D — Security, Performance, and Ops

**Owns:** `app/Http/Middleware/`, rate limiting, `.env` handling, backup documentation, the
load-test scripts, N+1 fixes.

**Phase 6:**

1. Add `throttle:60,1` to judge submit and appeal submit routes.
2. N+1 sweep: run a query log under a smoke session, add `with()` where missing.
3. Load test: 100 concurrent admin/judge sessions, 5,000 concurrent public viewers.
4. Backup drill: restore `state_kalotsav` onto staging, document the procedure.
5. Env hardening: `APP_DEBUG=false`, no plaintext secrets, keys rotated.
6. Migration idempotency check.

**Phase 7:**

7. Production cutover rehearsal coordination.

**Conflicts to avoid:** D does not touch `app/Services/` or `resources/js/`. If D adds a route
that needs a controller change, that change belongs to the agent that owns the controller.

---

## Agent E — Certificates, Financial, and Reporting

**Owns:** `app/Services/State/Fest/StateCertificateController.php`,
`StateCertificateService.php`, `app/Http/Controllers/Admin/StateRemittanceController.php`,
`StateParticipationReportController.php`, `StatePrizeCategoryController.php`.

**Phase 5:**

1. Add `reason` column to `state_fest_substitutions`.
2. Add unique index on `state_certificates`.
3. Add reissue route and service method.
4. Appeal committee composition decision and model (P2-09).

**Phase 7:**

5. Report and financial-flow rehearsal.

**Conflicts to avoid:** E touches `StateCertificateService.php`, which B touched in Phase 4
(standby filter). Sequence B first, then E's reissue path builds on top. E does not touch the
State migration — A owns it. E's schema additions (certificate index, substitution reason)
must be separate migrations run after A's Phase 1 migration, not in the same file.

---

## Shared contract — the intake payload

The most important coordination point is the intake payload contract between the Sahodaya side
and the State side. It is defined in `app/Services/State/FestStateQualifierPayloadBuilder.php`
and consumed in `app/Services/State/StateQualifierIntakeService.php`.

- **Any new field on a registration must be added to both at once.** A new column on
  `state_fest_registrations` that is not read by `StateQualifierIntakeService` is invisible
  to incoming nominations.
- **`points_excluded_from_standings` (P1-01) is exactly this case.** Agent A must add it to the
  model, the migration, the ranking filter, and the intake import as a single atomic change.
- The hash in the idempotency key must be updated if the payload shape changes in a way that
  affects the entry hash. It currently covers `entries` only, deliberately excluding
  `submitted_at`. **Do not include a timestamp in the hash.**

## Suggested sequence

| Phase | A | B | C | D | E |
|---|---|---|---|---|---|
| 1 | court-order flag + ranking filter | — | — | — | — |
| 2 | test fixture | appeal window | — | — | — |
| 3 | — | — | audit viewer + banner | — | — |
| 4 | `announced_at` (if not done) | standby cert filter | chest-number verify | — | — |
| 5 | — | — | reissue UI | — | cert dedupe + reissue + sub reason |
| 6 | — | — | — | rate limit, N+1, load, backup | — |
| 7 | — | — | — | cutover coordination | report rehearsal |

Only one agent touches the State migration per phase. Phase 1: A. Phase 4: A. Phase 5: E (a
separate migration file). No phase has two agents running migrations on the same connection.

## What NOT to parallelize

- **Two agents editing `StateResultService.php` in the same phase.** A's ranking filter and D's
  banner are in different methods, but the file is small enough that a merge conflict is likely.
- **Two agents adding a column to `state_fest_registrations`.** One migration file, one agent.
- **Anything touching the outbox hash or the intake payload shape.** It is a cross-boundary
  contract; a half-applied change means nominations are silently dropped or duplicated.

## Rollback per agent

Each agent's work should be a separate branch and a separate PR, mergeable independently. The
P0-01 fix is a single-line change that can be reverted without affecting any agent's work.
