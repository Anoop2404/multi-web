# AUDIT 05 — Security, Performance, Testing and Deployment

## A. Security

### A.1 Authentication and authorization

**Authentication.** Standard Laravel session auth with portal login for school, judge, and
participant personas. The most recent commits (`aea5582a`, `32379082`, `4ee375dc`, `caf07497`)
fixed a cluster of real bugs in portal credential provisioning: missing event-admin login
creation, wrong portal URL for shared event credentials, and finance restrictions not applied.
These are verified fixes in the log, not assumptions.

**Authorization.** Two layers:

1. **Capability matrix** — `app/Support/StateFestPermissions.php` defines 17 capabilities
   (`state.fest.view` … `state.fest.audit`, plus `state.fest.publish`). Routes are grouped with
   middleware like `state.fest:marks`, `state.fest:appeals`.
2. **Data scoping** — `app/Support/StateScope.php`, described below.

`EnsureStateAdmin` middleware applies the capability gate and additionally forces `state_staff`
to read-only by aborting on any non-GET method. The comment explains the distinction carefully:
`state_staff` predates the capability matrix and is enforced bluntly, while the newer operator
roles are governed by `state.fest.*`, because a mark operator must be able to POST marks and what
it may not do is expressed as a missing capability.

That distinction is the right one. A blunt read-only check on the new roles would have broken the
mark-entry workflow entirely.

### A.2 Tenant and State isolation — the strongest finding in this audit

`StateScope` (`app/Support/StateScope.php`) is a clean, fail-closed isolation layer:

```php
public static function apply(Builder $query, string $column = 'state_id', ...): Builder
{
    if (! self::shouldScope($request)) {
        return $query;
    }

    $stateId = self::id($request);

    // No state assigned yet — fail closed (see nothing), not open.
    return $stateId === null
        ? $query->whereRaw('1 = 0')
        : $query->where($column, $stateId);
}
```

Properties worth calling out:

- **Fail closed.** A state user with no `state_id` assigned gets `whereRaw('1 = 0')` — an empty
  result — not an unscoped query. The alternative (fail open) is the classic privilege-escalation
  bug: a newly created account with no assignment yet sees everything.
- **Superadmin bypass is explicit**, not implicit. `shouldScope()` returns false only when
  `isSuperAdmin()` is true, so the bypass is a single readable line rather than a scattered set
  of `if (auth()->user()->isSuperAdmin())` checks.
- **Two call patterns.** `apply()` for index/listing queries; `assertOwns()` for methods that
  receive a route-bound model. The docblock explains why both exist: route-model binding resolves
  independently of query scoping, so a listing query can be correctly scoped while
  `/admin/state-programs/{id}` still resolves and returns another State's program. That is a real
  and commonly-missed IDOR, and `assertOwns()` closes it.

**The class docblock records the finding that motivated it:**

> The platform's own gap analysis flagged this as the single most serious finding across the entire
> audit: state_admin/state_staff had no state assignment and no ownership check anywhere, so any
> state admin could read/write every other state's fest programs, qualifiers, and remittances.

This was found and fixed, with `tests/Feature/State/StateCrossIsolationTest.php` as the direct
proof. The test class asserts both patterns: a state admin cannot *list* another state's programs
(returns 1, not 2) and cannot *open* one directly (403).

**Coverage:** `StateScope` is referenced in 25 files, including every State controller in
`app/Http/Controllers/StateAdmin/` (all 20) and the shared admin controllers
(`StateFestProgramController`, `StateRemittanceController`, `TenantController`,
`ExternalSahodayaController`, `ExternalSchoolController`, `KalotsavStateController`).
`assertOwns()` appears in 23 files.

**Verdict: authorization is the most solid part of this codebase.** This is a PASS, not an
assumption.

### A.3 The audit-suite crash is not a security finding

`AUDIT_00` §4 records that the full suite aborts with `Premature end of PHP process`. To be clear:
this is a resource limit in this audit's shell, not a vulnerability. It does mean the aggregate
suite result is unverified, so the security claims above rest on code inspection plus the 74 State
tests that did run, not on a full green suite.

### A.4 IDOR surface remaining

| Surface | Assessment |
|---|---|
| State admin → another State's data | **CLOSED** by `StateScope` + `assertOwns`, tested |
| Sahodaya admin → another Sahodaya's data | **CLOSED** by tenant scoping (tenants are separate databases) |
| School admin → another school's data | **UNVERIFIED** — school-level tests were not read in this audit |
| Judge → another judge's marks / a whole item's marks | **UNVERIFIED** — the judge portal is chest-number-blind by design (the code comments say so), which is the right shape, but no negative test was read |
| Participant → another participant's appeal | **UNVERIFIED** |
| Public portal → unpublished State results | **LIKELY CLOSED** — `schoolRankings()` returns `[]` unless `results_published`, and the public projection service exists. Not negative-tested here. |

The two genuinely unverified school-level surfaces should get a negative test before launch.

### A.5 Injection, XSS, CSRF, mass assignment

- **SQL injection** — Eloquent parameter binding throughout; no raw SQL with interpolated user
  input was found. The one `whereRaw('1 = 0')` in `StateScope` is a constant.
- **XSS** — Blade `{{ }}` and Vue's default text interpolation. `resources/js/Pages/Admin/State/*`
  were spot-checked for `v-html`; none found in the State pages read.
- **CSRF** — Laravel's default `VerifyCsrfToken` middleware on web routes.
- **Mass assignment** — `$fillable` declared on the State models read (`StateAppeal`, and the
  `$casts` on `StateJudgeScore`, `StateFestMark`). The appeal service uses `forceFill()` for
  controlled fields after validating the outcome, which is the correct pattern for
  operator-only fields.

### A.6 File upload, credentials, debug exposure

- **Uploads** — appeal evidence goes to `evidence_path`; State private disk is configurable via
  `STATE_PRIVATE_DISK` and defaults to `local`. Upload validation was **not read** in this audit
  — a gap worth closing (see A.9).
- **Credentials** — the recent commits show portal credential provisioning with shareable,
  copyable login details and a `must_change_password` flag on user factory creation. No plaintext
  password storage was found in the State module.
- **Debug exposure** — `config/app.php` debug is env-driven. **Production `.env` was not read**
  (correctly — it is out of audit scope and should not be read casually). This must be confirmed
  before launch by checking `APP_DEBUG=false` in the deployed environment.

### A.7 Result tampering resistance

This is the most important security property for a competition, and the code does well:

- `StateResultPublicationService::publish()` runs in a transaction, sets `results_published` and
  `scoring_locked` together, and refuses on incomplete marks.
- `StateAppealService::reopenResult()` deliberately refuses to un-publish a **locked** result:
  "locking is a deliberate statement that the result is final, and an appeal against it needs a
  person to unlock it first rather than the system doing so quietly."
- Every mark and result change writes a `StateConductAudit` row with `value_from`, `value_to`,
  `reason`, and the acting user.
- Judge scoring is idempotent with a row lock.
- Points are derived, not typed: `position`, `grade`, and `points` are computed by the publication
  service from `score`, not entered by an operator.

The design intent is clear: marks are the only operator input; everything downstream is derived,
locked, and audited.

### A.8 Rate limiting

**NOT VERIFIED.** No `throttle` middleware was found on the judge mark-submission route or the
appeal submit route. Given the idempotency locks, a hammering judge produces duplicate-key
handling rather than duplicate rows, so the impact is limited to load. Still worth adding.

## B. Performance

### B.1 N+1 and indexing

The memory note `project_scoreboard_n_plus_one_and_cache_versioning_audit_fix.md` records that a
scoreboard N+1 and cache-versioning problem was already found and fixed: `resolveScoreboard` is
memoized per request, the scoreboard cache key is versioned, and hot-path cache logging exists.

`StateResultPublicationService::schoolRankings()` uses `->with('registration')` — correct
eager loading.

**NOT VERIFIED:** a systematic N+1 sweep across the 37 State pages. `StateQualifierEntry` materialization
creates one row per qualifier inside a loop, which is fine at hundreds but should be batched at
thousands.

### B.2 Workload scenarios

Per the audit brief, these are test assumptions, not verified capacity. No load test was run.

| Scenario | Status | What to measure |
|---|---|---|
| 5,000 registered participants | **NOT TESTED** | Registration list page TTFB, scrutiny filter response |
| 10,000 registered participants | **NOT TESTED** | Same, plus bulk approval duration |
| 20,000 registered participants | **NOT TESTED** | Same, plus export generation time |
| 100 concurrent admin/judge sessions | **NOT TESTED** | Judge mark submit latency, lock contention |
| 500 concurrent admin/judge sessions | **NOT TESTED** | Same, plus PHP-FPM worker saturation |
| 5,000 concurrent public scoreboard viewers | **NOT TESTED** | Cache hit ratio, DB query rate |

The cache-versioning fix means the scoreboard should absorb read load well, but that is an
expectation, not a measurement.

### B.3 The paths most likely to hurt

| Path | Risk | Why |
|---|---|---|
| Bulk qualifier approval | Medium | `validateBulkApproval()` runs inside a transaction with a locked event row. At 20,000 entries this is a long transaction. |
| Certificate PDF generation | **High** | Per `reference_production_queue_and_deploy.md`, the PDF converter is a separate service with a `/health` endpoint. Batch generation at State scale will saturate it. Must be queued and monitored. |
| Result publication | Medium | Sorts and updates every mark for an event inside one transaction. Bounded by event size, not total participants, so probably acceptable. |
| Bulk export / reports | Medium | `StateParticipationReportController` and `Fest/Reports.vue` were not profiled. |
| Qualifier intake materialization | Medium | Row-at-a-time creation in a loop. |

### B.4 Infrastructure

Per `reference_production_queue_and_deploy.md` (auto-memory, recorded from a prior audit):

- Supervisor-managed `sahodaya-worker` with 3 processes, user `www`.
- `retry_after` documented as 1900 in production.
- PDF converter is a separate service with a `/health` endpoint.
- Post-deploy: `optimize`, `npm run build`, `queue:restart` after a pull.

**These are recorded facts from a prior session, not verified in this audit.** They should be
re-confirmed on the server before launch, because the State module adds the
`fest:process-state-outbox` schedule and the State judge/certificate load, and the worker count
may need to change.

## C. Deployment and recovery

| Item | Status |
|---|---|
| Production configuration | **NOT VERIFIED** — `.env` not read, by design |
| Secrets management | **NOT VERIFIED** — no secrets-in-repo check was run (`git log -p` for credentials) |
| Database backups | **NOT VERIFIED** — no backup configuration read |
| Restore procedure | **NOT VERIFIED** — and this is the most important one: `state_kalotsav` is a *separate* database, so the existing central-DB restore runbook does not cover it |
| Rollback | **GOOD** — `STATE_MODULE_SWITCHED` is a single env flag, documented as deliberately reversible without a deploy |
| Deployment pipeline | **NOT VERIFIED** |
| Queue supervision | Documented in memory (Supervisor, 3 procs); re-verify |
| Cron schedules | Verified in code: `fest:process-state-outbox` every 5 minutes, `withoutOverlapping()` |
| Logs and monitoring | **NOT VERIFIED** |
| Exception alerts | **NOT VERIFIED** |
| Migration safety | The State migration `2026_07_20_000001_state_fest_tables.php` is additive. Tests call `Artisan::call('state:migrate')`, which is a separate migration path — confirm this is safe to run on an existing production State database without data loss. |
| RPO / RTO | **NOT DEFINED** |
| Failure during result publication | Handled: transactional, and a partial failure rolls back. |

### C.1 The gap that matters most

**There is no verified backup/restore for the `state_kalotsav` database.** The outbox pattern means
Sahodaya data survives a State database loss, but State-side registrations, marks, results, and
certificates do not. A restore drill on a staging State database is a launch prerequisite, not a
nice-to-have.

## D. Fix for P0-01 — recommended

Two options, and I recommend the second.

**Option 1 (minimum diff):** change `private function sourceEvents` to `public function
sourceEvents` in `FestStateNominationService`. Fixes both callers. One line. Lowest risk.

**Option 2 (recommended):** move the method to `FestPartitionService` and have
`FestStateNominationService`, `FestStateWinnerSheetService`,
`FestStateSlotCollectionController`, and `FestStateQualifierPayloadBuilder` all call it there.

Reason: `FestStateQualifierPayloadBuilder::sourceEvents()` (line 221) is already a private copy of
the same logic, and `FestEventSettingsController` and `FestQualificationService` comments both
reference the same concept. Four copies of partition-resolution logic drifting apart is how the
next P0 happens. `FestPartitionService` is already injected into all three call sites as
`$partitions`, so the extraction is mechanical.

Risk of Option 2: it touches a working method that passing tests depend on. Mitigate by fixing
with Option 1 first (unblocks immediately, one line), then scheduling Option 2 as a separate
change with the State suite as the gate.

**Regression test to add:** a test that calls `FestStateWinnerSheetService` through
`FestStateWinnerRegistrationController::index` and asserts a 200. The current tests call the
service directly, which is why a private-method fatal in the controller path is caught only by
coincidence.

## E. Findings

| ID | Finding | Severity |
|---|---|---|
| F-05-01 | `sourceEvents()` private, called externally — production fatal | **P0** |
| F-05-02 | Full test suite aborts in this environment; aggregate pass rate unverified | P1 |
| F-05-03 | No verified backup/restore for `state_kalotsav` | P1 |
| F-05-04 | Appeal evidence upload validation not read | P1 |
| F-05-05 | No rate limiting on judge submit / appeal submit | P2 |
| F-05-06 | Certificate PDF generation unprofiled at scale; single converter service | P1 |
| F-05-07 | No N+1 sweep across the 37 State pages | P2 |
| F-05-08 | `state.fest.audit` capability declared but no route consumes it | P2 |
| F-05-09 | School-level and judge-level IDOR negative tests not read | P1 |
| F-05-10 | APP_DEBUG / secrets in repo not confirmed for production | P1 |
