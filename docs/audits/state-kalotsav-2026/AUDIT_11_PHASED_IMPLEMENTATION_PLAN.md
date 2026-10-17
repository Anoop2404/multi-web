# AUDIT 11 — Phased Implementation Plan

## Phase 0 — Critical defect (P0-01 only)

**Scope:** Fix `sourceEvents()` private-method fatal.

**Files:** `app/Services/State/FestStateNominationService.php` (line 88).

**Change:** `private function sourceEvents` → `public function sourceEvents`.

**Tests:** `tests/Feature/State/FestStateWinnerSheetTest.php` (15 currently failing, all 18 must pass).

**Acceptance:** `php artisan test tests/Feature/State/FestStateWinnerSheetTest.php` — all 18 pass.

**Rollback:** Revert one line.

**Parallelizable:** No. This is the gate for every other phase.

---

## Phase 1 — State architecture and data identity

**Scope:** Add court-order/wildcard exclusion flag to the schema and ranking logic (P1-01).
Add multi-Saharan test fixture (P1-04).

### 1a. Schema change (DB-01)

Add `points_excluded_from_standings` boolean to `state_fest_registrations`.

Migration must be safe against a production `state_kalotsav` database with existing data.
Add `default(false)` so existing rows are not silently changed.

### 1b. Ranking filter

`StateResultService::ranking()`: filter `StateFestMark` rows on the participant's
`registration.points_excluded_from_standings`. The mark is still ranked (the student is still
competing); the points just do not reach the school/Sahodaya totals.

### 1c. School and Sahodaya standings

`StateResultPublicationService::schoolRankings()`: same filter applied upstream.

### 1d. Intake import

`StateQualifierIntakeService` / `StateQualifierEntry` model: support the `points_excluded` flag
from the incoming Sahodaya payload (court-order entries already have this flag at the Sahodaya
level).

### 1e. Multi-Saharan test fixture

`tests/Feature/State/StateEndToEndLifecycleTest.php`. Three Sahodayas, colliding local IDs,
scenarios 5 and 17. Write before the rest of Phase 2 so that Phase 2 can use it as the
regression gate.

**Acceptance:** the new fixture runs and scenarios 5 and 17 assert distinct identities.

**Parallelizable with 1a–1d:** No (1e uses the output of Phase 0, and the new fixture will fail
until 1a–1d are done). But within 1a–1d, the ranking filter (1b) and the standings filter (1c)
can run in parallel on different services.

---

## Phase 2 — Sahodaya-to-State qualification and nomination

**Scope:** Confirm slot-limit enforcement (already implemented, document the boundary); add
per-student item limit enforcement (P1-02).

### 2a. Per-student item limit

`FestParticipationPolicy` fields exist (`max_events_per_student`, `max_onstage_per_student`,
`max_group_per_student`). Add a `StateRegistrationLimitService` (or extend the existing one)
that reads them and gates `state.fest.registrations` submission.

This is a pre-registration check, not a post-registration filter.

**Acceptance:** a student registering for their 6th individual item gets a 422 with the specific
rule that was breached.

### 2b. Appeal window gating (P1-03)

Add `announced_at` datetime column to `state_item_results`. On result publish, set it to `now()`.
On appeal submit, check `now() - announced_at < 60 minutes`. Gate in `StateAppealService::submit()`.

Also add a `STATE_APPEAL_WINDOW_MINUTES` config (default 60) so the window can be extended
without a code change.

**Acceptance:** an appeal submitted 61 minutes after result publication gets a 422.

---

## Phase 3 — State registration, verification, and event operations

**Scope:** Confirm venue/schedule/chest-number flow works; add audit-log viewer (P1-05).

### 3a. Audit-log viewer

Add `state.fest.audit` route and page, guarded by the existing `state.fest:audit` capability.
Read `StateConductAudit` rows, filterable by event, item, kind (mark/result), and date range.

This is a read-only surface; the data is already being written everywhere in Phase 1 and
Phase 2.

### 3b. Verify venue/schedule/chest-number flow

Walk the actual pages in staging. No code change expected, but confirm the data flows from
`Fest/Schedule.vue` → `StateScheduleService` → `state_fest_event_item` slots, and from the
chest-number assignment to the judge portal's chest-number-blind view.

**Acceptance:** a stage coordinator can assign chest numbers and the judge portal shows
"Performer A" (not chest number) at scoring time.

---

## Phase 4 — State judging, scoring, results, and points

**Scope:** Standby certificate exclusion (P1-08); appeal re-rank flow (P2-01); champion point
recalc (P2-02).

### 4a. Standby certificate exclusion

Read `StateCertificateService::generate()`. Assert the filter excludes `is_standby = true`
participants. Add a test.

### 4b. Post-appeal re-rank flow

Add a banner or flag on `Fest/Results.vue` when the item result is `PROVISIONAL` because of an
appeal. "This item's result is pending re-rank. Publish the corrected result after the Appeal
Committee decision." Document the step in the operator runbook (P2-01).

Optionally add a "Recompute from current marks" button that calls `StateResultService::computeItem()`
— one line in the controller — so the operator does not have to open a DB tool.

### 4c. Champion point recompute

After an upheld appeal with manual mark adjustment, document in the runbook that the State
admin must recompute and republish. The code does not auto-recompute; the manual step is the
current design.

---

## Phase 5 — Appeals, certificates, financial workflows, and reports

**Scope:** Appeal committee composition (P2-09); certificate dedupe (P2-03); certificate reissue
(P2-04); substitution reason field (P2-10); no-code: confirm remittance and report flows.

### 5a. Appeal committee model (P2-09)

Decision: document that `decided_by_user_id` is the committee chair, and add a `decided_by_role`
field. Or add a separate `StateAppealCommittee` model. Choose based on whether the manual
requires multiple signatures.

### 5b. Certificate dedupe and reissue

- Add unique index on `(state_event_id, registration_id, certificate_type)`.
- Add `StateCertificateController::reissue(int $id)` — marks the old certificate as replaced,
  generates a new one with the current result.

### 5c. Substitution reason

Add `reason` column to `state_fest_substitutions`. Required on approval, not on request.

---

## Phase 6 — Performance, security, integration tests, operational readiness

**Scope:** Rate limiting (P2-05); N+1 sweep (P2-06); load test (P1-07); backup drill (P1-06);
env hardening (P1-09); migration idempotency (P1-10).

### 6a. Rate limiting

Add `throttle:60,1` middleware to the judge submit and appeal submit routes. No logic change.

### 6b. N+1 sweep

Run with query log enabled against a staging copy of production data. Target: no query that
loads more than 50 rows in a loop without eager loading.

### 6c. Load test

Run at least: 100 concurrent admin/judge sessions (mark submit latency) and 5,000 concurrent
public scoreboard viewers (cache hit ratio). Use k6 or artillery.

### 6d. Backup drill

Restore a `state_kalotsav` copy from backup onto a staging server. Confirm all tables are
populated, foreign keys hold, and the State admin can log in. Document the procedure.

### 6e. Env and secrets

Confirm `APP_DEBUG=false`, `APP_ENV=production`, no plaintext secrets in repo, keys rotated
since last deploy.

### 6f. Migration idempotency

Run `php artisan migrate` against a copy of the staging `state_kalotsav` database that already
has data. Confirm it completes without error and does not re-create tables or re-seed data.

---

## Phase 7 — Staging rehearsal and production launch

**Scope:** Full dress rehearsal on staging with three Sahodayas, multiple schools, all
workflows from qualification to certificate.

### 7a. Rehearsal data

Use the Phase 1e test fixture's data (three Sahodayas, colliding IDs, group events, appeals)
but loaded into a real staging environment, not a test database.

### 7b. Staging smoke checklist

1. Sahodaya admin can view the winner sheet. ✓ (P0-01 must be fixed first)
2. Sahodaya admin can accept/opt-out a slot with reason.
3. Outbox submit produces an intake in the State admin's review queue.
4. State admin can approve/reject the intake.
5. State admin can view registrations.
6. State admin can assign chest numbers.
7. Judge can submit a score via portal.
8. State admin can compute and publish results.
9. Public scoreboard shows published results.
10. Appeal is filed, upheld, result reopens, mark adjusted, result republished.
11. Certificate is generated for a qualified participant.
12. Certificate is NOT generated for a standby.
13. Standby does NOT appear in school/Sahodaya standings.
14. Court-order entry appears in result but NOT in standings.
15. Academic-year filter works (if a second program exists on staging).

### 7c. Production cutover

1. Run `STATE_MODULE_SWITCHED=true` (the single env flag that activates the new module).
2. Verify the redirect works: old `/admin/state-workspace/*` URLs redirect to
   `/admin/state/fest/*`.
3. Verify the old routes are still in the codebase — do NOT remove them until the new module
   has run one full State program.
4. Confirm supervisor worker count covers the new `fest:process-state-outbox` schedule
   (every 5 min, `withoutOverlapping`).

---

## Definition of Done

| Phase | Done when |
|---|---|
| 0 | 18/18 tests in `FestStateWinnerSheetTest.php` pass |
| 1 | Phase 1e fixture passes scenarios 5 and 17; court-order entries do not appear in school/Sahodaya standings |
| 2 | Per-student limit gates at 5 single + 2 group; appeal window gates at 60 min |
| 3 | Audit-log viewer shows `StateConductAudit` rows; stage coordinator can assign chest numbers |
| 4 | Standby excluded from certificates; post-appeal banner visible on results page |
| 5 | Certificate dedupe unique constraint added; reissue path works; committee model decided |
| 6 | Load test shows <500ms p95 on judge submit at 100 concurrent; backup drill succeeds |
| 7 | All 15 smoke checklist items pass on staging |

## What can run in parallel

The phases are ordered to avoid dependency conflicts. Within each phase:

- Phase 1b and 1c (ranking filter and standings filter) are independent and can run in parallel.
- Phase 2b (appeal window) is independent of 2a.
- Phase 3a (audit viewer) is independent of 3b.
- Phase 5a, 5b, 5c are independent.
- Phase 6a (rate limiting) is independent of everything else.

Phase 0 → Phase 1 → Phase 2 → Phase 3 → Phase 4 → Phase 5 → Phase 6 → Phase 7 is the safe
order. The parallelizable work within phases is listed above.
