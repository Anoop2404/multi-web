# AUDIT 09 — End-to-End Test Matrix

## 1. Scope

The brief asks for a test fixture covering: one State Kalotsav, three Sahodayas, multiple schools
per Sahodaya, students with overlapping admission numbers, individual events, group events,
qualified winners, non-qualified participants, withdrawn participants, approved substitutes,
appeals, special entries, multiple judges, result publication, certificates — and 20 scenarios
that exercise it.

**Status: NOT WRITTEN.** The repo has unit and feature tests that cover most of the State module
(33 State test files, 74 passing State tests verified in this audit). What it does not have is a
multi-Sahodaya fixture that exercises the full lifecycle. That is the single most valuable test
to add before launch.

The fixture belongs in `tests/Feature/State/StateEndToEndLifecycleTest.php` and should be written
as a single file with helper builders for Sahodayas, schools, and participants. The matrix below
defines the scenarios it must cover and the **expected** result, even where the fixture itself
has not been written.

## 2. Fixture

| Element | Quantity | Notes |
|---|---|---|
| State program | 1 | via `FestStateProgram::factory()` |
| State event | 1 | one State fest event under the program |
| Sahodayas | 3 | distinct tenant DBs |
| Schools per Sahodaya | 3 | same names allowed across Sahodayas |
| Students per school | varies | two share admission number `1234` across Sahodayas |
| Individual items | 2 | |
| Group items | 1 | with 5 members, 2 standbys |
| Judges | 3 per item | |
| Sahodaya published results | all three | with `results_published = true` |
| Appeals | 1 filed, 1 upheld, 1 dismissed | |

## 3. Scenarios

| # | Scenario | Expected outcome | Status | Evidence |
|---|---|---|---|---|
| 1 | Sahodaya A publishes a valid competition result. | `results_published` flips; outbox eligible. | VERIFIED in passing tests. | `tests/Feature/State/*` |
| 2 | Qualified students are identified correctly (rank 1 + 2 per Sahodaya per item). | Exactly the configured number of qualifiers per Sahodaya per item, no more. | VERIFIED. | `StateParticipationLimitService` docblock records the rule; tests pass. |
| 3 | State receives the nomination without duplication. | Re-running the outbox enqueue is a no-op; the second call returns the same row. | VERIFIED in code. Idempotency key is `qualifiers:{program}:{event}:{sha256(entries)}` over entries only, and the unique index on `idempotency_key` makes the second insert a no-op. No end-to-end test for it was found; that gap is recorded. | `FestStateQualifierPayloadBuilder` |
| 4 | State verifies eligibility. | Reject path works; approved path materializes the registration. | VERIFIED. | `StateQualifierIntakeService` |
| 5 | Another Sahodaya submits a student with an identical local admission number. | Both retain distinct State identities. | NOT TESTED. The data model handles this by construction (`source_tenant_id` differs), but no fixture asserts it. **P1 test gap.** | — |
| 6 | A qualified student withdraws. | The Sahodaya's slot collection offers the next rank (the auto-fill chain). | VERIFIED. | `FestStateSlotCollectionService::respond(opt_out)` chains to next rank. |
| 7 | A valid replacement is considered under the official rules. | The replacement is offered, accepted, and the nomination list reflects them. | VERIFIED. | Same as above. |
| 8 | A disallowed replacement is rejected. | The slot service refuses a replacement outside the configured slot count, with a 422 / validation error. | VERIFIED. | Slot limit guards. |
| 9 | A group member is substituted. | `StateFestParticipant.is_standby` flips, the new performer is recorded, certificates reflect the performer who actually performed. | PARTIAL. Standby data is tracked; performer-vs-member certificate distinction is **UNVERIFIED** against the manual. | `StateFestTeamService` |
| 9a | A group item's result is appealed by its Team manager. | The appeal is filed against the team, identifies the item, and the committee can uphold it. | **NOT IMPLEMENTED — P1-11.** Both levels bind an appeal to a single student; `FestAppealWildcardService::grantStateSlot` sends `'participants' => []` for group items, so the State holds a group registration with no members and no way to add them. Manual rule C.1 names the Team manager as a filer. | `PortalFestAppealController`, `StateCertificateController::submitAppeal`, `FestAppealWildcardService` |
| 9b | An upheld group appeal changes the team's result. | `StateAppealService::adjustMark` resolves the mark by `participant_id`; a group mark is stored against the first participant, so this *can* work once 9a exists. | **NOT IMPLEMENTED — P1-11**, blocked by 9a. | `StateAppealService::adjustMark` |
| 10 | A State judge submits a score. | Mark saved, grade derived, audit log written. | VERIFIED. | `StateJudgeScoreService::save` |
| 11 | A duplicate score request is rejected or handled idempotently. | `updateOrCreate` on `(item_id, participant_id, judge_user_id)` means the second call updates the first row. No duplicate. | VERIFIED in code. | `StateJudgeScoreService` |
| 12 | Ranking and grades are calculated correctly. | `StateResultService::computeItem()` produces positions, grades, and points; ties share positions; sum is correct. | VERIFIED. | `StateResultService::ranking`, point table config |
| 13 | An appeal changes an outcome. | Upheld appeal takes the item off public view, marks certificates stale, allows `adjustMark()` on the affected participant. | VERIFIED for the four sub-behaviours separately. Combined assertion across all four in a single scenario is **NOT WRITTEN** but is a useful test to add. | `StateAppealService` |
| 14 | The relevant point totals are recalculated. | **NOT VERIFIED** end-to-end. `adjustMark()` updates the mark, but the item is taken to PROVISIONAL and the operator must recompute manually. There is no automatic re-rank. **P2 finding** (operational, not a code defect). | `StateAppealService::adjustMark` |
| 15 | Published results cannot be altered without authorization and audit records. | `reopenResult()` refuses on a locked result; `decide()` writes a `StateConductAudit` row. | VERIFIED for the lock guard; the audit-on-decide path is verified by code review. | `StateAppealService::reopenResult` |
| 16 | Certificates reflect the final approved result. | After upheld appeal + manual re-rank + recompute, `markItemStale` flags previously-issued certificates. Regeneration is the operator's job. | PARTIAL. Stale-marking works; regeneration reissue path is **UNVERIFIED**. | `StateCertificateService::markItemStale` |
| 17 | One Sahodaya cannot modify another Sahodaya's nominations. | `StateScope::assertOwns()` returns 403 on a foreign intake; `StateCrossIsolationTest` asserts the analogous case for State programs. | VERIFIED for programs; **NOT EXTENDED** to qualifier intakes, but the same `StateScope` machinery is in use, so the same pattern should hold. | `StateCrossIsolationTest` |
| 18 | Concurrent publication requests remain consistent. | The `lockForUpdate()` on the event row + transactional body of `publish()` means a second concurrent request either waits for the first to commit and then re-validates, or fails fast on incomplete marks. | VERIFIED. | `StateResultPublicationService` |
| 19 | A failed synchronization can be retried without creating duplicate records. | The outbox's `idempotency_key` index makes the retry a no-op. The remote intake is itself keyed on `source_*_id` and materializes once. | VERIFIED in code. End-to-end retry test **NOT WRITTEN**. | `FestStateQualifierPayloadBuilder` |
| 20 | Manual rule 24 — court-order entries get grades/prizes but no points. | The court-order registration's mark still appears in the per-item result, but the school/Sahodaya aggregation excludes it. | **NOT IMPLEMENTED**. Schema flag missing, ranking does not filter on it. **P1 code gap, not just a test gap.** | — |

## 4. Gaps the fixture would expose

Even before the fixture is written, these gaps are evident from the code:

- **No test for scenario 5** — two Sahodayas with colliding local admission numbers. The code
  handles it, but a failing test would be the proof. This is the test most likely to expose a
  hidden assumption (e.g. someone added a `unique` index on `admission_number` on the State side
  by mistake).
- **No test for scenario 14** — that an upheld appeal flows through to point totals without
  manual recompute. The current behavior is that it does *not* flow through automatically. If the
  team decides that this is a real requirement, then the test will fail and a service will need
  to be added.
- **No test for scenario 16** — certificate regeneration after an appeal. If the reissue path
  exists it is not in the routes, controller, or service surface; if it does not, the test will
  fail and the gap will be obvious.
- **No test for scenario 20** — court-order points exclusion. This is currently a code gap; the
  test will fail until the schema and the ranking filter are in place.

## 5. Test infrastructure

The fixture should use the existing `RefreshDatabase` trait and the `state:migrate` Artisan
command. `Tenant::factory()` and the State model factories are the building blocks. The
`StateCrossIsolationTest` shows the pattern for multi-State testing; the multi-Sahodaya case
needs a per-Sahodaya tenant DB created in setUp. The repo has a `Tenant::createWithDatabase()`
helper that should be used (referenced in `Tenant.php` comments and `database/seeders/`).

## 6. Recommended order of writes

1. **First:** the per-Sahodaya fixture builders (one school, one student, one published event
   per Sahodaya). Without these, every other test reads as a single-Sahodaya case and the
   multi-Sahodaya bugs slip through.
2. **Second:** scenario 5 (colliding local IDs) and scenario 17 (cross-Sahodaya isolation on
   intakes). These are the tests most likely to catch a structural defect.
3. **Third:** scenarios 9, 14, 16, 20 — the gaps this audit has already identified. Each test
   that fails is a P1 finding already enumerated elsewhere in this audit.
4. **Last:** the long-tail (3, 4, 18, 19) — these are about idempotency and concurrency, and
   are easier to assert once the happy paths are proven.

## 7. Verdict on testing

The unit and feature test suite is real. It exercises 33 State files and the 74 passing State
tests verified in this audit. The full suite's aggregate pass rate is unverified because of the
resource limit reached in this audit environment, so the green-runner count is **74 confirmed
out of ~80 attempted** for the State slice and **unknown for the rest**.

What is missing is the *integration* test that ties the Sahodayas together. The architecture
makes that test possible without enormous scaffolding. The matrix above is the script for writing
it. **P1 deliverable.**
