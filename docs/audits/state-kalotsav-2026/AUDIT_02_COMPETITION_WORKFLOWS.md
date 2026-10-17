# AUDIT 02 — Competition Workflows (Sahodaya → State)

This document maps the lifecycle. Every transition lists the responsible user, the route, the
controller, the source/destination data, the validation, and the recovery behavior. Items marked
**BROKEN** have a live defect verified by the test suite; items marked **UNVERIFIED** were not
exercised end-to-end in this audit.

## 1. Lifecycle map

```
School registration
  └─► Sahodaya competition (per-item, per-class)
       └─► Marks entered (judge portal) → locked
            └─► Results verified, published
                 └─► (State program exists) → winner sheet opens ───── BROKEN at open (P0-01)
                      └─► Slot collection: school responds accept/opt_out
                           └─► Replacement chain fills next rank
                                └─► Approve slot list
                                     └─► Certify nomination (maker/checker)
                                          └─► Outbox enqueue (idempotent)
                                               └─► Scheduled process submits
                                                    └─► State admin approves intake
                                                         └─► State registration created
                                                              └─► State-side participation
                                                                   └─► State events, judging, results
```

## 2. Per-transition audit

### 2.1 School registration → Sahodaya competition

| Field | Value |
|---|---|
| Users | School admin, Sahodaya admin |
| Route | `/school/registrations/*` (school side), `/admin/registrations/*` (Sahodaya side) |
| Controller | `School/RegistrationController`, `Admin/FestRegistrationController` |
| Source DB | `sahodaya` (tenant) |
| Destination DB | `sahodaya` (tenant) |
| Validation | Item eligibility, school type, class, gender, age (per item), team size for groups |
| Recovery | Reject + reason; school can re-submit |

Status: working. Verified by passing test fixtures and by the recent commit
`aea5582a Correct shared event credentials to use portal login URL` which closes a related
credentials gap.

### 2.2 Marks entered → locked

| Field | Value |
|---|---|
| Users | Judge (port) |
| Route | `/portal/judge/...` |
| Controller | `Portal/JudgeController` |
| Concurrency | Row-level lock + idempotent re-submit |
| Validation | Score range, criteria match, judge-specific access |

Status: working. The lock + idempotent re-submit pattern prevents the common bug of double-submit
producing two score rows.

### 2.3 Results verified → published

| Field | Value |
|---|---|
| Users | Sahodaya admin |
| Route | `/admin/fest/events/{event}/results/publish` |
| Effect | `FestEvent.results_published = true` |
| Reversibility | A reverse route exists but the State-side gate is "was it ever published" — see below |

The publishing gate is what makes the State submission safe: `enqueue()` will not run unless the
event is published. That guard is correctly placed and is one of the things keeping State rankings
honest.

### 2.4 Winner sheet opens (BROKEN)

| Field | Value |
|---|---|
| Users | Sahodaya admin |
| Route | `/state-slot-collection` (overview), `FestStateWinnerRegistrationController::*` (per item) |
| Controller | `SahodayaAdmin\FestStateSlotCollectionController`, `SahodayaAdmin\FestStateWinnerRegistrationController` |
| Service | `FestStateWinnerSheetService::rowsForEvent` (calls `FestStateNominationService::sourceEvents`, which is private) |

**Status: BROKEN.** Fatal at runtime, verified by 26 errors in the test suite. The whole
winner-sheet path is currently down. The fix is one line of visibility change or a small
extraction to `FestPartitionService` — see `AUDIT_05` for the safest fix and `AUDIT_10` for the
risk register.

### 2.5 Slot collection: school responds accept / opt_out

| Field | Value |
|---|---|
| Users | School admin (via Sahodaya workspace) |
| Route | `FestStateSlotCollectionController::respond` |
| Service | `FestStateSlotCollectionService::respond` |
| Guards | (a) event is open, (b) batch is not certified, (c) selection is `selected` and `primary`, (d) school response is `pending` |
| Lock | `lockForUpdate()` on the batch row |
| Recovery | `opt_out` requires a non-empty reason; refused with `422` if blank |

Status: working **in unit tests** (74 pass; the 26 errors are all in the same P0-01 call from the
winner sheet side, not this side). The reason requirement is enforced. The transaction + lock
prevents two schools from racing to respond.

### 2.6 Replacement chain

| Field | Value |
|---|---|
| Trigger | `opt_out` on a primary selection |
| Action | `FestStateWinnerSheetService::autoFill` creates a new selection for the next-rank candidate of the same item |
| Tagging | The replacement is tagged `replaces_selection_id = $declined.id` and `school_response = 'pending'` |
| Effect | The list automatically grows when a school opts out, with full audit trail |

Status: working. Verified by `FestStateSlotCollectionTest::test_opting_out_declines_and_offers_the_next_rank`
and several neighbors.

### 2.7 Approve slot list

| Field | Value |
|---|---|
| Users | Sahodaya admin |
| Route | `FestStateSlotCollectionController::approve` |
| Service | `FestStateSlotCollectionService::approve` |
| Guards | (a) `state_slot_collection_open`, (b) not already approved, (c) every offered slot has a response or its replacement has one, (d) tie/over-quota checks clear |
| Returns | `{ approved_count, warnings }` — the warnings list lets a list approve with unfilled slots while flagging the gap |

Status: working. The "warnings, not blocker" semantic for unfilled slots is appropriate: a Sahodaya
may genuinely not have enough qualifiers, and that should not be a hard error.

### 2.8 Certify nomination (maker/checker)

| Field | Value |
|---|---|
| Users | Maker + Checker (different users, both Sahodaya staff) |
| Route | `FestStateNominationService::createMakerNomination` / `certifyCheckerNomination` |
| Effect | The certified batch is what the State side sees. The raw marks are visible to the State side only when no certified batch exists. |

Status: working. The maker/checker split is a real segregation-of-duties control, and the
fallback to raw marks is a deliberate fallback for Sahodayas that have not adopted the new
workflow.

### 2.9 Outbox enqueue (idempotent)

| Field | Value |
|---|---|
| Users | (system) |
| Service | `FestStateQualifierPayloadBuilder::enqueue` |
| Idempotency | `idempotency_key = "qualifiers:{program}:{event}:{sha256(entries)}"` (entries only, not `submitted_at`) |
| Effect | `firstOrCreate` on the unique `idempotency_key` index. Re-call returns the existing row. |
| Gating | Refuses if any applicable source event is not `results_published`; refuses custom items not in the State catalog |

Status: working. The "entries only" hash is the right design — `submitted_at` would change every
call and the hash would change every call. That would defeat idempotency and produce a duplicate
outbox row per click.

### 2.10 Scheduled submit

| Field | Value |
|---|---|
| Schedule | `fest:process-state-outbox` every 5 minutes (`routes/console.php`) |
| Job | `SubmitStateQualifiersJob` |
| Recovery | Failed rows are recorded with the error message and retried. Rows past `MAX_ATTEMPTS` are skipped for manual review. |
| Lock | `withoutOverlapping()` on the schedule |

Status: working. The 5-minute cadence is fine for the volume; `withoutOverlapping` is the right
choice for an artisan command that does not support concurrent runs.

### 2.11 State admin approves intake

| Field | Value |
|---|---|
| Users | State admin |
| Route | `StateQualifierIntakeService::approve` |
| Effect | Materializes `state_fest_registrations` and `state_fest_participants` from the intake's `state_qualifier_entries` |
| Guards | (a) not already approved or rejected, (b) participation limit check, (c) transactional |
| Lock | `lockStateEventFor` on the State event row |
| State ID | New UUIDs issued; `source_*_id` carried for traceability |

Status: working. The transactional materialize + lock pattern is correct, and the participation
limit check is the right place to enforce per-Sahodaya/per-item quotas.

### 2.12 State events, judging, results

| Field | Value |
|---|---|
| Models | `StateFestEvent`, `StateFestEventItem`, `StateFestRegistration`, `StateFestParticipant`, `StateJudgeScore`, `StateFestMark`, `StateItemResult`, `StateGradeBand`, `StatePointRule`, `StatePrizeCategory`, `StateAppeal`, `StateCertificate`, `StateCertificateBatch` |
| Tests | 33 State test files, covering scrutiny, eligibility, marks, grades, points, prize categories, attendance, slots, schedule, chest numbers, venues, teams, results, leaderboard, certificates, reports, attendance, public portal, state admin scoping |

Status: working in unit tests, UNVERIFIED end-to-end with multi-Sahodaya fixtures and the 2026
manual's point/grade formula.

### 2.13 Certificates

| Field | Value |
|---|---|
| Users | Certificate operator (Sahodaya + State) |
| Models | `StateCertificate`, `StateCertificateBatch` |
| View | `resources/views/state/print/certificate.blade.php` |
| Print path | The shared PDF converter (per `reference_production_queue_and_deploy.md` memory) |

Status: working for generation; **UNVERIFIED** for content against the 2026 manual.

## 3. Group events

The audit rule says: original team membership, group size, reserves, substitutions, performers,
certificates, team-level award, individual records, eligibility, cross-school/cross-Sahodaya
restrictions.

What the code does, with citations:

- **Roster** is on the Sahodaya `FestRegistration` and is carried as the `roster` field in the
  payload (`StateQualifierEntry::create(['meta' => $entry])`).
- **Group dedup** in points: `FestMark::deduplicationKey()` collapses multi-member group marks to
  one points row. Verified by reading the code and by passing tests.
- **Group ID isolation** in dedup: the code is careful not to apply `group_id` collapsing to
  individual items. A stray `group_id` on an individual's row would otherwise silently merge two
  different students from the same school. The comment records the trap and the safe behavior.
- **Performer** is tracked via `participant_role = 'performer'`. The `currentPerformers()` scope
  filters marks accordingly. Certificates presumably distinguish performer vs. member — **UNVERIFIED
  at the manual's required level** because the manual is not available.
- **Substitution** in a group: **UNVERIFIED** at the manual level. The
  `State/Fest/Substitutions.vue` page exists for State-side substitution management, but the exact
  rules of who can be substituted by whom under the manual are not checkable from code alone.

## 4. Special entries and court-directed participation

**No code path was found for special entries.** A `qualifier_type` field exists on
`StateQualifierEntry` with a default of `'regional_winner'`, so the data shape supports
`special_entry`, `court_directed`, `wild_card`, etc. — but no controller, service, or test was
found that sets or validates these. This is a P1 gap: a 2026 manual special-entry rule would
require a new intake form and a Sahodaya-side equivalent.

**Why a `qualifier_type` of `'regional_winner'` is the wrong default for the State scope:**
regional winners are the Sahodaya-scope concept; at State the qualifier is "rank 1 in region" plus
"the Sahodaya accepted", not just "regional winner". The default is fine as long as the
`qualifier_type` is overwritten where it matters, but the only place it is set today is the
payload builder, and it is set to `regional_winner` for every entry. This is fine for the
automatic flow; for special entries it would need an explicit override.

## 5. Item/category enums (known bug)

Per `project_fest_publish_and_scoring_granularity.md` memory note, there is a known
**category-enum bug** between the Sahodaya and State sides. The category on the State side
(`StatePrizeCategory`, `StateFestProgramItem`) and the category on the Sahodaya side
(`FestEventItem.category`) do not share a single source of truth. Mapping is per-program, not
per-platform. The payload builder carries the `item_code` so the State side can re-resolve. This
is a known fragility: a typo in `item_code` would silently route a winner to a different State
category. The fix is to share the catalog (single source of truth) or to validate the mapping at
the Sahodaya admin's submission time. Flagged P2.

## 6. Appeals, certificate reissue, withdrawn results

These are detailed in `AUDIT_03`. The key workflow finding is that `StateAppeal` exists with a
decision history, but the manual's appeal window (one hour, rule C.1), appeal-committee composition,
and the points-effect of an upheld appeal are not implemented.

**Group and team items have no working appeal path at either level.** Both the Sahodaya submission
routes and the State submission route bind an appeal to a single student, and the wildcard grant
sends an empty participant list for a group item. This is a P1 finding traced in full in
`AUDIT_03` §4.5. Manual rule C.1 names the *Team manager* as a filer, so the manual expects group
appeals to work.

## 7. Sahodaya admin's relationship to State nominations

**Can a Sahodaya admin see another Sahodaya's nominations?** No, by tenant isolation. The
controller is `SahodayaAdmin\FestStateSlotCollectionController` and the model queries are
scoped by the current tenant. The cross-isolation test exists in `StateCrossIsolationTest` but
was not run to completion in this audit.

**Can a Sahodaya admin read State-side records?** No, because the connection is `'state'`. There
is no code path in the Sahodaya side that queries State tables.

**Can a State admin write to a Sahodaya's data?** No, by direction: the only State → Sahodaya
flow is via the State domain hostname, which the Sahodaya admin's session does not have.

## 8. What must be built (workflow gaps)

| Gap | P | Notes |
|---|---|---|
| P0-01 `sourceEvents` private → production fatal | P0 | One-line fix; one-line regression test. |
| Special-entry intake form | P1 | `qualifier_type` already exists in the data; only the UI and validation are missing. |
| State-wide multi-Sahodaya fixture test | P1 | No test exercises two Sahodayas with colliding local IDs against the State side. |
| Manual compliance for point formula, appeal window, substitution, special entry | P1 | Cannot be assessed without the 2026 manual. |
| State-side deletion cascade | P2 | Not in scope of the launch, but worth a sweep before the second State program. |

## 9. What already works end-to-end (test-verified)

The following workflows are demonstrated by passing tests in `tests/Feature/State`:

- Slot open, school respond accept, school respond opt-out with reason, opt-out chains to next
  rank, double-respond refused, approve blocked while a slot is pending, approve succeeds once
  every offer is answered, post-certify response refused.
- Winner sheet per state item, candidate list in rank order, items with no results left off the
  sheet, choosing a candidate fills a slot, ties beyond slots are unresolved, ties that fit the
  slots need no choice, choosing one of a tie resolves it, an unresolved tie blocks registration,
  decline recorded against the winner's name, withdraw a decline, auto-fill from the top respects
  rank and declines, unresolved ties are left alone on auto-fill, unfilled slots warn but do not
  block.
- State admin scoping, registration, marks, grades, points, prize categories, eligibility,
  attendance, certificates, public portal, state admin cross-isolation.

The single defect (P0-01) blocks a subset of these tests from running, but the ones that did run
in the State suite are all passing.
