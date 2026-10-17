# AUDIT 13 — State Launch Checklist

Sequenced for a State CBSE Kalotsav launch. Items marked **[CODE]** require a code change.
Items marked **[OPS]** require operational or environmental work. Items marked **[VERIFY]**
require a manual check on staging or production.

## Gate 1 — Code must compile and the nomination path must work

| # | Item | Type | Status |
|---|---|---|---|
| 1 | Fix `sourceEvents()` private-method fatal (`FestStateNominationService.php:88`) | CODE | **OPEN — P0** |
| 2 | All 18 tests in `tests/Feature/State/FestStateWinnerSheetTest.php` pass | CODE | **OPEN — currently 3/18** |
| 3 | Winner-sheet page returns HTTP 200 for a Sahodaya admin | VERIFY | OPEN |
| 4 | `php artisan test tests/Feature/State/` runs green (74 tests) | CODE | OPEN — re-run after fix |

## Gate 2 — Manual-rule compliance

| # | Item | Type | Status |
|---|---|---|---|
| 5 | Court-order entries excluded from school/Sahodaya points (manual rule 24) | CODE | OPEN — P1-01 |
| 6 | Per-student 5-single/2-group limit enforced (manual rule 6) | CODE | OPEN — P1-02 |
| 7 | One-hour appeal window enforced (manual rule C-1) | CODE | OPEN — P1-03 |
| 7a | Group and team item appeals supported at both levels (manual rule C-1, Team manager filer) | CODE | OPEN — P1-11 |
| 8 | Standby participants excluded from participation certificates (manual rule 25) | VERIFY | OPEN — P1-08 |
| 9 | Two-slots-per-Sahodaya per item confirmed (manual rule 15) | VERIFY | **CLOSED — implemented in `StateParticipationLimitService`** |
| 10 | Point table verified against manual (grade + place, 18 cells) | VERIFY | **CLOSED — config matches manual exactly** |
| 11 | Appeal fee Rs. 3000 with refund/forfeit on outcome | VERIFY | **CLOSED — implemented in `StateAppealService`** |

## Gate 3 — Data and security

| # | Item | Type | Status |
|---|---|---|---|
| 12 | Multi-Sahodaya colliding-ID integration test written and passing | CODE | OPEN — P1-04 |
| 13 | `state.fest.audit` capability wired to a viewer page | CODE | OPEN — P1-05 |
| 14 | `state_kalotsav` backup and restore drill completed and documented | OPS | OPEN — P1-06 |
| 15 | `state:migrate` verified idempotent against a copy of production data | VERIFY | OPEN — P1-10 |
| 16 | `APP_DEBUG=false` confirmed in production `.env` | OPS | OPEN — P1-09 |
| 17 | No plaintext secrets committed to the repo | VERIFY | OPEN — P1-09 |
| 18 | State cross-isolation verified (`StateCrossIsolationTest` green) | CODE | **CLOSED — passes** |
| 19 | Negative test: a school admin cannot reach another school's data | CODE | OPEN |
| 20 | Negative test: a judge cannot see the whole item's marks | CODE | OPEN |

## Gate 4 — Performance

| # | Item | Type | Status |
|---|---|---|---|
| 21 | N+1 sweep across the 37 State admin pages | CODE | OPEN — P2-06 |
| 22 | Load test: 100 concurrent admin/judge sessions | OPS | OPEN — P1-07 |
| 23 | Load test: 5,000 concurrent public scoreboard viewers | OPS | OPEN — P1-07 |
| 24 | Certificate PDF generation profiled at scale | OPS | OPEN — see memory note on the PDF converter service |
| 25 | Supervisor worker count confirmed for the `fest:process-state-outbox` schedule | OPS | OPEN — memory note says 3 procs, re-confirm |

## Gate 5 — Operational readiness

| # | Item | Type | Status |
|---|---|---|---|
| 26 | Post-appeal re-rank step documented in the operator runbook | OPS | OPEN — P2-01 |
| 27 | Champion point recompute step documented in the runbook | OPS | OPEN — P2-02 |
| 28 | Certificate dedupe unique constraint added | CODE | OPEN — P2-03 |
| 29 | Certificate reissue path built | CODE | OPEN — P2-04 |
| 30 | Rate limiting on judge and appeal submit routes | CODE | OPEN — P2-05 |
| 31 | Substitution reason field added | CODE | OPEN — P2-10 |
| 32 | Appeal committee composition decided and documented | OPS | OPEN — P2-09 |

## Gate 6 — Staging rehearsal

Run all 15 smoke items from `AUDIT_11` Phase 7b on staging with three Sahodayas.

| # | Smoke item | Status |
|---|---|---|
| 33 | Sahodaya winner sheet renders | OPEN |
| 34 | Slot accept / opt-out with reason | OPEN |
| 35 | Outbox submit → State intake appears | OPEN |
| 36 | State approve / reject intake | OPEN |
| 37 | State registration list | OPEN |
| 38 | Chest number assignment | OPEN |
| 39 | Judge score submission via portal | OPEN |
| 40 | Result compute and publish | OPEN |
| 41 | Public scoreboard shows results | OPEN |
| 42 | Appeal filed, upheld, result reopened, mark adjusted, republished | OPEN |
| 43 | Certificate generated for a qualified participant | OPEN |
| 44 | Certificate NOT generated for a standby | OPEN |
| 45 | Standby excluded from standings | OPEN |
| 46 | Court-order entry in result but not in standings | OPEN |
| 47 | Academic-year isolation (if a second program exists) | OPEN |

## Gate 7 — Production cutover

| # | Item | Type | Status |
|---|---|---|---|
| 48 | `STATE_MODULE_SWITCHED=true` set | OPS | OPEN |
| 49 | Old `/admin/state-workspace/*` URLs redirect correctly | VERIFY | OPEN |
| 50 | Old routes still present (do not remove yet) | VERIFY | OPEN |
| 51 | Rollback plan tested: set `STATE_MODULE_SWITCHED=false` and confirm old stack resumes | VERIFY | OPEN |

## Launch verdict gate

The State Kalotsav **cannot launch** until Gate 1 (all four items) and Gate 2 items 5–8 are
closed. Gate 3 items 12–14 and Gate 4 items 22–23 are strongly recommended before launch; if
they must be deferred, the deferral should be an explicit, written acceptance by the State
organizing committee.
Gates 6 and 7 are the final gate and cannot be shortcut.
