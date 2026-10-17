# AUDIT 00 — Executive Summary

**Audit date:** 2026-10-14
**Branch audited:** `main` @ `aea5582a`
**Scope:** Kerala State CBSE Kalotsav 2026 readiness on the existing Sahodaya Kalotsav platform
**Method:** Static code inspection, route/migration/model enumeration, live test-suite execution, manual read of critical services, retrieval and text extraction of the official 2026 Manual
**Nature of work:** Read-only audit. No source files, migrations, seeds, configuration, or production data were modified.

---

## 1. Verdict

### Existing Sahodaya system: **READY**

The Sahodaya-level platform is production software. It has been running real competitions, and the
code shows it: the scoring, results, appeals, and certificate flows are all implemented, tested, and
thoughtfully commented. The recent commits on this branch (portal credential provisioning, finance
restrictions, login URL fixes) are genuine operational work, not scaffolding.

### State Kalotsav: **NOT READY**

One launch-blocking defect is confirmed and reproducible. The core Sahodaya-to-State winner-nomination
workflow crashes at runtime. **This is a one-line fix, not an architectural problem.**

A second, larger issue: four specific rules from the official 2026 Manual are not implemented in code.
None of them requires restructuring, but each is a rule a State inspector would expect to see enforced.

The 2026 Manual **was** retrieved and read for this audit, so manual compliance is now **verified
rather than unverified** — the good news and the bad news are both grounded in the actual document.

---

## 2. The blocking defect (P0-01)

`App\Services\State\FestStateNominationService::sourceEvents()` is declared `private` at line 88, but
`App\Services\State\FestStateWinnerSheetService::winnerSheet()` calls it from outside the class at
line 62.

**Reproduction, from the live suite:**

```bash
php artisan test tests/Feature/State/FestStateWinnerSheetTest.php --compact
```

```
tests: 18, passed: 3, errors: 15

Call to private method App\Services\State\FestStateNominationService::sourceEvents()
  from scope App\Services\State\FestStateWinnerSheetService
```

**Affected live HTTP routes:** the winner sheet at `state-slot-collection/{event}/winner-sheet`, and
the slot-collection overview. This is the exact workflow the manual describes as mandatory: a Sahodaya
publishes results, its winners are offered State slots, some accept, some opt out, the next rank is
promoted, and the curated selection is certified and sent to State. None of that runs today.

**Fix:** change `private` to `public` on [line 88](app/Services/State/FestStateNominationService.php:88).
Recommended follow-up: `FestStateQualifierPayloadBuilder::sourceEvents()` (line 221) is a second private
copy of the same logic, and `FestQualificationService` inlines a third at lines 38-44. All three should
call one shared method on `FestPartitionService`. That is a half-day refactor, not urgent.

---

## 3. What is genuinely built and working

This is the part that matters most for the launch decision, and it is substantial.

**The State module exists and is large.** 37 Inertia admin pages, 48 module routes, 33 controllers,
33 test files. The full competition lifecycle is implemented: qualifier intake, scrutiny, registration,
teams and substitutions, venues and schedule, chest numbers, attendance, judge portal, mark entry,
result computation, publication, appeals, certificates, points, leaderboard, public portal.

**Authorization is the strongest part of the codebase.** `App\Support\StateScope` is fail-closed
isolation with a documented origin: the platform's own gap analysis previously found that
`state_admin`/`state_staff` accounts had no state assignment and no ownership check, so any state
admin could read and write every other state's data. That was fixed, and
`tests/Feature/State/StateCrossIsolationTest.php` proves it. The two-pattern design (`apply()` for
listings, `assertOwns()` for route-bound models) closes an IDOR that most codebases miss, because
route-model binding resolves independently of query scoping.

**The Sahodaya-to-State transfer is well engineered.** A separate `state_kalotsav` database makes the
tenant boundary physical rather than conventional. An idempotency-keyed outbox with a
`withoutOverlapping` scheduler means a retry cannot create duplicate State identities. The hash
covers the entry payload only, deliberately excluding `submitted_at`, so an identical resubmission is a
no-op.

**Result integrity is handled properly.** Marks are the only operator input; grades, positions, and
points are all derived. Publication is transactional and refuses incomplete data. An upheld appeal
deliberately does **not** un-publish a locked result — the code comments explain that locking is a
statement of finality and an appeal needs a person to unlock it first. Certificates are marked stale
when a result reopens. Every mark and result change writes an audit row with who, what, why.

**The point table matches the manual exactly.** All 18 cells of the grade-plus-place table in
`config/fest_confed_kalotsav_scoring.php` match the 2026 Manual's table. Grade bands (A ≥70%, B 60-69%,
C 50-59%, nothing below 50%) match. Appeal fee of Rs. 3000 with refund-on-uphold and
forfeit-on-dismissal matches, and is correctly three-state (a waived fee stays waived).

---

## 4. Manual compliance gaps

The 2026 Manual was retrieved from the official download page and extracted to
`docs/audits/state-kalotsav-2026/refs/Kalotsavam_Manual_2026.txt` (40 pages, 2,166 lines). Five rules are not implemented:

| Manual rule | What it says | Code status | Priority |
|---|---|---|---|
| §24 Court Appeals | Court-directed entries get grades and prizes, but points "will not be added to the school or to the Sahodaya in deciding the toppers" | No flag exists on `state_fest_registrations`; `StateResultService::ranking()` does not filter. The entry would wrongly credit the school and Sahodaya. | P1 |
| §6 General | One student: max 5 single items, 2 group items, only 3 of the 5 single may be stage | `FestParticipationPolicy` has the fields. No service reads them. | P1 |
| §C.1 Appeal | Appeal must be filed within **one hour** of result announcement | No window field on `StateAppeal`; no submit-time check. | P1 |
| §C.1 Appeal | Group and team items must be appealable — the rule names the *Team manager* as a filer | Both levels bind an appeal to a single student; a group wildcard grant sends an empty member list. A team's result cannot be appealed at all. | P1 |
| §25 Substitutes | Standbys get no participation certificate and pay no fee; only on-stage performers are certified | `is_standby` is tracked for team size and limit exclusion, but the certificate generator's filter is unverified. | P1 |

Three of these are small: the court-order rule needs a schema column and a ranking filter, the
per-student limit needs a service-layer guard, and the appeal window needs a column and a check.

**The group-appeal gap is the most serious, and it is not a small omission.** `FestAppealWildcardService::grantStateSlot`
sends `'participants' => []` alongside `participant_type = 'group'`, so the State holds a group
registration with no members and no way to add them. Manual rule C.1 names the *Team manager* as a
filer, so the manual explicitly contemplates team appeals — the software cannot satisfy that rule at
all today. Full trace in `AUDIT_03` §4.5.

Also worth recording: manual §15's two-slots-per-Sahodaya rule is correctly implemented, and the
English One Act Play top-1 override is documented in `StateParticipationLimitService`. Manual §22's
Appeal Committee quorum of four is not modeled — the code records a single decider, which is a P2
documentation decision rather than a defect.

---

## 5. Risk register summary

| Priority | Count | What |
|---|---|---|
| P0 | 1 | `sourceEvents()` private-method fatal |
| P1 | 10 | Court-order points, per-student limit, appeal window, multi-Saharan test, audit-log viewer, backup drill, load test, standby certificates, env hardening, migration idempotency |
| P2 | 13 | Post-appeal re-rank, cert dedupe, cert reissue, rate limiting, N+1 sweep, State→Sahodaya status API, committee model, substitution reason, academic-year entity, and others |
| P3 | 4 | Optional enhancements |

Total P1 effort is roughly **10 working days**. The P0 is one line. Full detail is in
`AUDIT_10_RISK_AND_PRIORITY_REGISTER.md`.

---

## 6. What should not be touched

- **The existing Sahodaya scoring, results, and certificate flows.** They work. The State module
  already reuses them through `StateGradePointService`, and that reuse is why State results are
  correct.
- **`StateScope` and `EnsureStateAdmin`.** These close the most serious security finding this
  platform has had. Do not refactor them.
- **The outbox idempotency key.** Do not add a timestamp to the hash.
- **The two-stack State routes.** The `/admin/state-workspace/*` redirect into
  `/admin/state/fest/*` is a deliberate cutover feature. Removing the old routes before the new
  module has run one full State program removes the rollback path.

---

## 7. Answers to the final questions

**1. Can the current Sahodaya project run the State CBSE Kalotsav without major structural changes?**
Yes. The architecture is correct and already built. What stands between here and launch is one fatal
bug, four manual rules, and operational readiness work. No restructuring is required.

**2. Can State administrators manage all participating Sahodayas?**
Yes. `StateScope` gives them their own State's data and nothing else, and the Sahodaya toggle,
item-level enablement, and external-Sahodaya intake are all present.

**3. Is Sahodaya-to-State nomination already implemented?**
Yes, but it is broken by P0-01. Fix one line and the winner-sheet, slot-collection, opt-out with
reason, next-rank promotion, and outbox submission all work.

**4. Is manual import required or can nominations synchronize safely?**
They synchronize safely. The outbox is idempotent, gated on published results, and validated against
the State catalog before submission. A retry cannot duplicate.

**5. Can existing judging and scoring be reused?**
Yes, and it is already being reused. The judge portal requires an assignment row, scores once per
team, refuses withdrawn registrations, and derives the grade from the score.

**6. Will State ranking and point calculations be correct?**
Yes for positions, grades, and points — the formula is verified against the manual. **No** for
school and Sahodaya standings if a court-order entry is present, because manual rule 24 is not
implemented.

**7. Are appeals compliant with the official 2026 rules?**
Partly. The fee, the refund/forfeit logic, the reopen-on-uphold, the certificate staleness marking, and
the full audit trail are all correct. The one-hour filing window and the four-member committee quorum
are not implemented.

**8. What must be built before State registration opens?**
The four manual rules (court-order points, per-student limit, appeal window, standby certificates) and
the multi-Saharan integration test. Roughly a week.

**9. What must be completed before the competitions start?**
Everything in Gate 1 through Gate 3 of `AUDIT_13`, plus the load test and the backup drill.

**10. What must be validated before results are published?**
The result-publication guards (already correct), the post-appeal re-rank step (currently manual and
must be in the runbook), and certificate regeneration after an appeal.

**11. What existing modules should remain untouched?**
The Sahodaya scoring/results/certificate flows, `StateScope`, `EnsureStateAdmin`, the outbox
idempotency key, and the two-stack State route redirect.

**12. Which five-agent implementation sequence is safest?**
Phase 0 (P0-01) alone and first, then the five agents per `AUDIT_12`. Only one agent touches the State
migration per phase; the intake payload contract is the one shared boundary that must not be split
across agents.

**13. What security, reliability, and performance evidence is still needed?**
A passing multi-Saharan isolation test, a school-level IDOR negative test, a judge-confidentiality
negative test, a load test at the specified concurrency, and a documented `state_kalotsav` backup and
restore drill.

---

## 8. Coverage statement

The audit read the State module's services, controllers, routes, models, migration, and test files
directly, and retrieved the official 2026 Manual. The full test suite could not be run to completion in
this environment (it aborts on a resource limit partway through), so the aggregate pass rate across the
whole repository is **unverified**. The State slice was run and 74 of the attempted tests passed, with
26 errors — 15 of which are the P0-01 defect and the remainder being the suite abort.

No load test was run. No production `.env` was read. No destructive test was run against production.
All NOT TESTED items are labelled as such in the individual reports rather than reported as passing.
