# AUDIT 10 — Risk and Priority Register

P0 = launch blocker. P1 = required before launch. P2 = operational improvement. P3 = optional.

The single P0 is the `sourceEvents()` private-method fatal. The P1 cluster is well-defined and
mostly small.

## P0 — Launch blockers

### P0-01 — `sourceEvents()` is private and called externally

| Field | Value |
|---|---|
| Module | Sahodaya → State nomination |
| Files | `app/Services/State/FestStateNominationService.php:88` declares `private function sourceEvents(FestEvent $hubEvent, FestPartitionService $partitions): array`. It is called from **outside** the class at `app/Services/State/FestStateWinnerSheetService.php:62` as `$this->nominations->sourceEvents($hubEvent, app(FestPartitionService::class))`. |
| Current behavior | `FestStateWinnerSheetService::winnerSheet()` calls a private method on `FestStateNominationService`, which is a PHP fatal (`Error: Call to private method ...`). The winner-sheet page is unreachable. |
| Expected behavior | The method is public, or both callers go through a shared service. |
| Root cause | `FestStateQualifierPayloadBuilder::sourceEvents()` (line 221) and `FestStateNominationService::sourceEvents()` (line 88) are two private copies of the same partition-resolution logic. `FestStateWinnerSheetService` was written to use the nomination one but the visibility was never widened. |
| Business impact | A Sahodaya admin opening `state-slot-collection/{event}/winner-sheet` gets a 500. That page is the Sahodaya's view of who qualified and is on the path to nomination submission. **Blocks launch.** |
| Recommended fix | Option 1 (immediate): change `private` to `public` on line 88. Option 2 (recommended follow-up): move partition resolution to `FestPartitionService` and have `FestStateQualifierPayloadBuilder` (line 221) and `FestStateNominationService` (line 88) both call it, since `FestQualificationService` already inlines a third copy at lines 38-44. See AUDIT_05 §D. |
| Regression risk | Low if Option 1 (visibility). Medium if Option 2 (extraction) — the new method is referenced by tests. Mitigate by Option 1 first. |
| Acceptance | `tests/Feature/State/FestStateWinnerSheetTest.php` — 15 of 18 tests fail with this exact error. All 15 must pass after the fix. |
| Tests required | `tests/Feature/State/FestStateWinnerSheetTest.php` — already exists and already fails, so it is both the proof and the gate. A controller-path test hitting `state-slot-collection/{event}/winner-sheet` would additionally cover the HTTP layer. |
| Effort | 1 line if Option 1, ~half a day if Option 2. |
| Reproducible evidence | `php artisan test tests/Feature/State/FestStateWinnerSheetTest.php --compact` → `tests: 18, passed: 3, errors: 15`. Error: `Call to private method App\Services\State\FestStateNominationService::sourceEvents() from scope App\Services\State\FestStateWinnerSheetService` |

## P1 — Required before launch

| ID | Finding | Evidence | Action | Effort |
|---|---|---|---|---|
| P1-01 | Court-order entries do not exclude their points from school/Sahodaya standings (manual rule 24) | `StateResultService::ranking()` does not filter on a wildcard/court-order flag; no such flag exists on `state_fest_registrations` | Add boolean column; gate ranking + schoolRankings on it; add `appeals/court-order` flag handling | 1 day |
| P1-02 | Per-student 5-single/2-group limit (manual rule 6) not enforced | `FestParticipationPolicy` has fields, no service reads them | Service-layer guard on registration submission; integration test for both happy and over-limit paths | 1-2 days |
| P1-03 | One-hour appeal window (manual rule C-1) not enforced | `StateAppeal` has no `announced_at`/window; no submit-time check | Add window field to `StateFestEventItem` (or program) and gate `submitAppeal()`; add test for window expired and for window open | 0.5-1 day |
| P1-04 | Multi-Sahodaya colliding-ID integration test not written | No fixture in `tests/Feature/State` | Write the fixture; assert 3 Sahodayas with colliding local IDs produce 3 distinct State participants | 1-2 days |
| P1-05 | `state.fest.audit` capability declared but no route consumes it | `app/Support/StateFestPermissions.php` lists AUDIT, `grep state.fest:audit` returns nothing | Add a State audit viewer page guarded by the capability; show `StateConductAudit` rows | 0.5-1 day |
| P1-06 | No verified backup/restore for `state_kalotsav` database | No backup config read; `state_kalotsav` is a separate DB not covered by central runbook | Run a backup-and-restore drill against a staging copy; document RPO/RTO | 1 day for the drill, ongoing for the doc |
| P1-07 | No load test at the workload scenarios the brief specifies | No k6 / locust / artillery runs found | Run at least scenarios 100 concurrent sessions and 5,000 concurrent public viewers | 1-2 days |
| P1-08 | Standby certificate exclusion (manual rule 25) and on-stage performer distinction (manual rule 25) not confirmed | `is_standby` exists; certificate generator filter unverified | Read `StateCertificateService::generate` and assert the filter is correct; add a test | 0.5 day |
| P1-09 | APP_DEBUG / secrets / environment hardening not confirmed for production | `.env` not read (correctly) | Operational checklist; confirm `APP_DEBUG=false`, no plaintext secrets in repo, key rotation, etc. | 0.5 day |
| P1-10 | `state:migrate` not verified as idempotent against an existing production state DB | Tests run against fresh DB | Run migration against a copy of production; document | 0.5 day |
| P1-11 | **Appeals cannot be raised for a group or team item, at either Sahodaya or State level** | `PortalFestAppealController::storeStudent` asserts `participant->student_id === student->id`; `FestAppealController::storeWildcard` takes a single `student_id`; `FestAppealWildcardService::grantStateSlot` sends `'participants' => []` at line 155 even when `participant_type` is `'group'`; `StateCertificateController::submitAppeal` never checks registration type | Add a team identity to the appeal (registration_id primary), a team-manager filing path, and a wildcard grant that populates the member list. Manual rule C.1 names the Team manager as a filer, so group appeals are explicitly expected. | 2-3 days |

## P2 — Operational improvements

| ID | Finding | Action | Effort |
|---|---|---|---|
| P2-01 | After an upheld appeal, no automatic re-rank | `adjustMark()` is manual; mark a banner on `Fest/Results.vue` saying "pending re-rank" | 0.5 day |
| P2-02 | School/Sahodaya championship points not recomputed after upheld appeal | Same root cause as P2-01; document the manual step in the operator runbook | 0.5 day |
| P2-03 | No certificate dedupe constraint | Add `unique (state_event_id, registration_id, certificate_type)` | 1 hour |
| P2-04 | No reissue path for certificates after correction | Add a route + service method | 1 day |
| P2-05 | No rate limiting on judge submit / appeal submit | Add `throttle:60,1` to the routes | 1 hour |
| P2-06 | No systematic N+1 sweep across the 37 State pages | Run a query log under a smoke session; fix anything that fans out | 1-2 days |
| P2-07 | No read API from State back to Sahodaya (nomination status) | Document as Phase 2 enhancement; or write a small GET endpoint | 1-2 days |
| P2-08 | No webhook on intake approval | Same as P2-07 | 1 day |
| P2-09 | Appeal committee membership not modeled (manual rule 22) | Either committee model or operator runbook | 1 day or a doc |
| P2-10 | Substitution reason field (manual rule 25 "in case of emergency") | Add a `reason` column to `state_fest_substitutions` | 1-2 hours |
| P2-11 | No academic-year entity | Decide: add one, or document the "one program per year" policy | 1 day for entity, 1 hour for doc |
| P2-12 | Sahodaya-side winner-sheet redirect/option-out UX not re-validated | Functional but worth a usability pass | — |
| P2-13 | Three overlapping scoring tables in `config/fest_*_scoring.php` | Document which is live, which is seed-only, which is custom | 1 hour |

## P3 — Optional

| ID | Finding | Action |
|---|---|---|
| P3-01 | Bulk-export for results (CSV/XLSX) | Add if requested |
| P3-02 | Public portal dark-mode | Cosmetic |
| P3-03 | Per-Sahodaya logo on public portal | Cosmetic |
| P3-04 | Webhook for nomination approval | Same as P2-08; promote to P3 if budget tight |

## Dependencies

- P1-01, P1-08 depend on a schema migration on `state_kalotsav`. These two can run as a single
  migration; do them together.
- P1-05 (audit viewer) is independent and can run in parallel with the rest.
- P1-04 (multi-Sahodaya test) is independent and can run in parallel; it will fail until P1-01 is
  fixed, then pass.
- P1-11 (group appeals) is independent of the others and can run in parallel. It touches the appeal
  submission paths on both sides, so it must not overlap with P1-03 (appeal window), which gates the
  same submit methods.
- P1-06 (backup/restore) and P1-07 (load test) are operational and can run in parallel with code
  changes; they require staging environment access.
- P1-09 (env hardening) is operational; can run any time before launch.
- P1-10 (migration idempotency) requires a copy of production data; do it last in the pre-launch
  week.

## Effort summary

- P0: ~half a day.
- P1: ~10 working days total. Most are short; the longest are P1-04 and P1-07.
- P2: ~5-7 working days total, prioritizable.

## What is *not* a P0

For the record, the things that look scary but are not launch-blockers:

- The `app/Models/FestParticipationPolicy` shape change — schema, not behavior.
- The two-stack State routes (old vs new) — they are a feature, not a bug; the redirect makes
  the cutover reversible.
- The N+1 and cache-versioning story on the public scoreboard — already fixed and tested.
- The three scoring config files — the docblocks make their roles clear; the only risk is an
  operator editing the wrong one, which a P2-13 doc would mitigate.
- The lack of an academic-year entity — the first State program does not need it; the second does.
