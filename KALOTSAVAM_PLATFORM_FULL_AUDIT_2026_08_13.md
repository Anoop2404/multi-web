# Kalotsavam Platform — Full Independent Audit
**Date:** 2026-08-13 · **Scope:** Fest/Kalotsavam module of the `multi-web` (Sahodaya Connect) Laravel app · **Method:** Fresh, evidence-based code review (not derived from prior audit docs in repo)

## Methodology note (read first)

This module is far larger than a typical audit target: 80+ `Fest*` controllers, 90+ `Fest*` services, ~96 fest-related migrations, and 399 fest-touching route lines in `routes/web.php` alone, on top of a second, parallel state-domain implementation. A literal line-by-line read of every file is not achievable in one pass. This audit instead does **deep, representative sampling** of the highest-risk surfaces — authorization middleware, tenant/region scoping, phase/lifecycle locking, registration approval, the State-level architecture, data-integrity constraints, exports, and dashboard queries — read to the line, with file:line evidence. Areas not sampled to that depth are explicitly marked **"Not sampled — recommend follow-up"** rather than guessed at, per your instruction to separate confirmed defects from assumptions.

Where this audit's own findings happen to overlap with issues already fixed per in-repo comments (e.g. `LIFE-03`, `LIFE-06` references in `FestRegistrationReviewController`), that is noted as corroboration, not copied from prior reports — everything here was re-verified against current code.

---

## A. Executive summary

**Overall assessment:** this is a mature, actively-hardened codebase. Nearly every controller action sampled in the core registration-review path carries an explicit tenant-ownership guard (`abort_if($event->tenant_id !== $this->sahodaya->id, 403)`) and several carry detailed in-line comments documenting a specific prior audit finding and its fix (e.g. `FestRegistrationReviewController::approve()`/`reject()` cite "LIFE-03 fix (functional audit, 2026-08-11/12)" for missing status guards). This is not a codebase with obviously absent authorization — it is one where the *pattern* is sound but applied inconsistently, and where two more structural problems dominate the risk picture:

1. **A duplicated, partially-inert State-level architecture.** There are two parallel implementations of "State Admin runs a Fest workflow": the live one (`routes/web.php` → `admin.state.*` → `StateFestWorkspaceController` → `App\Models\State\StateFestEvent` on a separate `state` DB connection) and a dedicated-domain one (`routes/state.php` → `state.portal.*` → the *same* controller, same models) that is documented in its own file header as **inert in production** because `STATE_APP_DOMAIN` isn't configured, and which the same file admits shares a session guard with the central domain despite needing a domain-scoped cookie that "won't work in a browser (cookies don't cross domains)". The Vue frontend mirrors this split: `resources/js/Pages/Admin/StateAdmin/Fest/Index.vue` and `resources/js/Pages/StateAdmin/Fest/Index.vue` are near-duplicate files that have already drifted (one hardcodes a URL the other computes server-side). This is a live maintenance and correctness risk, not a hypothetical one — see D-01.

2. **The State Admin dashboard computes statewide numbers by loading entire tables into PHP with no academic-year filter.** `StateAdminDashboardController::index()` runs `FestStateProgram::query()->get()` and `StateRemittance::query()->get()` unfiltered, across all years, then counts in memory. Every "stat" a State Admin sees on login is a cross-year cumulative total presented as if current — see E-04.

3. **No database-level protection against a student being registered twice for the same competition item.** `fest_participants` has an index on `(registration_id, student_id)`, not a unique constraint, and no unique constraint exists anywhere tying `(item_id, student_id)` together — duplicate/double-submission prevention is entirely an application-layer responsibility. Under concurrent requests (double-click, retried import, two browser tabs) this is a race condition with no backstop. See E-05.

4. Two `EventLifecycleGate` phase-lock methods (`allowRegistrationForItem`, `allowMarkEntryForItem`) are fully implemented but **explicitly not wired into any live call site** per their own doc comments, and the code says so outright: "this hasn't been run against the test suite... available for adoption once verified." This is an honestly-flagged partial implementation, not a hidden one, but it means the newer "phase mode" locking feature does not yet protect any real event. See D-02.

Nothing in this pass rose to a **Critical** cross-tenant data leak in the sampled controllers (tenant-ownership checks were present everywhere they were checked) — but sampling coverage was maybe 15-20 of 80+ controllers, so this is not a clearance for the remainder. See the Issue Register (§E) for the full list with severities, and §F for policy questions that need a product decision before engineering can close several of these.

---

## B. Current system map (as observed in code, not assumed)

**Organizational hierarchy actually implemented:**

```
Central DB
 └─ Tenant (self-referential: type='sahodaya' | 'school', parent_id links school→sahodaya)
      └─ Region (belongs to a sahodaya tenant, via Region.tenant_id)
           └─ SchoolRegionAssignment (school_id + region_id + tenant_id + academic_year — join table, NOT a column on Tenant)
                └─ School (Tenant type='school')
                     └─ Student / Teacher
                          └─ FestParticipant (via FestRegistration)
```

Key deviation from the brief's assumed 4-tier tree: **Region assignment is year-scoped and stored in a join table (`school_region_assignments`), not as a permanent attribute of the school.** A school's region can differ by academic year, and a school can — per the join-table design — theoretically hold more than one region assignment in the same year unless a unique constraint blocks it (not verified in this pass; flagged as a policy/data-integrity question in §F).

There is a **second, separate hierarchy** for the State tier: `App\Models\State\StateFestEvent`, `StateFestRegistration`, `StateFestParticipant`, `StateQualifierEntry` live on a distinct `state` database connection (`config/state.php`), decoupled from the central Tenant tree, feeding from `FestStateNominationBatch`/`StateQualifierIntakeService` at the Sahodaya level. State Admin does not sit atop the same table set that Region/Sahodaya/School do.

**Roles actually found** (via `Spatie\Permission` `HasRoles` + custom middleware, not a single Role enum): `superadmin`, `state_admin`, `state_staff`, `sahodaya_admin`, `training_admin`, `event_admin`, `region_admin`, `school_admin`, `school_principal`, `school_vice_principal`, `school_event_coordinator`, `school_sports_coordinator`, `school_kalotsavam_coordinator`, `school_mcq_coordinator`, `school_training_coordinator`, `school_finance_coordinator`, `school_staff`, plus portal-only pseudo-roles gated by middleware rather than Spatie roles (`student.portal`, `teacher.portal`, `judge.portal`, `exam.portal`, `fest.mark.coordinator`). `region_admin` and `event_admin` are **not** separate tenant tiers — they are Sahodaya-panel roles scoped down to a subset of events/regions via `FestEventStaff.duty` + `region_id` (see `EnsureSahodayaAdmin::handle()`, `app/Http/Middleware/EnsureSahodayaAdmin.php:60-95`), resolved by `App\Support\EventRegionAdminScope`.

**Authorization architecture:** almost entirely **custom middleware guards** (`state.admin`, `sahodaya.admin`, `school.admin`, `fest.event.ops`, `fest.discipline`, `fest.mark.coordinator`, `event.coordinator`, registered in `bootstrap/app.php:55-77`), not Laravel Policies. Only **one** Policy class exists in the whole app: `app/Policies/FestReportPolicy.php`. This is a legitimate architectural choice (middleware-based RBAC is a valid pattern), not automatically a defect — but it means authorization logic is scattered across ~20 middleware classes and hundreds of individual `abort_if`/`abort_unless` calls inside controllers rather than centralized, which is what produced finding E-02 below (an inconsistency between two otherwise-identical actions).

**Registration/approval lifecycle (Fest, observed from `FestRegistrationReviewController` + `EventLifecycleGate` + `FestRegistrationApprovalService`):**

```
draft (school-side, not persisted until submit)
  → submitted  ──approve──> approved ──(results published)──> locked (no further edits without override)
  → submitted  ──reject───> rejected (reason required; releases fee credit; revokes any qualification)
  → submitted/approved ──cancel──> cancelled (blocked if results published or fee already paid+approved)
  → approved+paid ──cancelWithRefund──> cancelled (reason required, generates FestFeeCredit)
  → submitted ──withdraw(school-initiated, not sampled this pass)──> withdrawn
  → approved ──substitute(performer↔standby)──> approved (participant swap)
```

Entry into `approved` additionally gates on: event not registration-locked (unless `override_lifecycle`), results not yet published, event-head fee paid-and-approved (if the event's participation policy requires it), and all mandatory items for that school registered. Every transition method re-derives `$event->reportableEventIds()` rather than trusting `$registration->event_id === $event->id`, because — per an extensive repeated code comment — partitioned "hub" events route real registrations to region/finale child events, so a naive equality check silently 403'd legitimate actions until fixed.

**Phase-transition diagram (event-level, from `EventLifecycleGate`):**

```
draft → registration_open → (schedule_published) → ongoing → completed
                                                         ↓
                                                  results_published
```
Overlaid booleans (`registration_locked`, `scoring_locked`, `results_published`, `schedule_published`) act as independent kill-switches rather than a strict state machine — an event can be `ongoing` with `scoring_locked=true`, `registration_locked=false`, etc., all combined freely. This is flexible but means "what can a user do right now" cannot be read off a single status field; it requires checking every boolean, which is exactly what `EventLifecycleGate` centralizes — a good pattern, undermined by the two call sites (D-02) that don't yet check it for the newer per-item phase model.

---

## C. Role-permission matrix (sampled modules: Fest registration review, event lifecycle, state workspace)

| Module/Action | Super Admin | State Admin | Sahodaya Admin | Region Admin (Sahodaya-scoped) | School Admin | Current behavior | Expected | Gap |
|---|---|---|---|---|---|---|---|---|
| View Fest registrations (Sahodaya) | ✅ all | ❌ (different DB — State has its own `StateFestRegistration`) | ✅ own tenant only (`FestRegistrationReviewController::index`, tenant check line 41) | ✅ scoped to assigned region via `EventRegionAdminScope` | ❌ (school sees only its own school's registrations via `SchoolAdmin\FestRegistrationController`, not sampled to same depth) | Implemented correctly for Sahodaya/Region tiers sampled | Same | None found in sampled paths |
| Approve/reject Fest registration | ✅ | ❌ (no route in `state.php`/`web.php` state group touches central `FestRegistration`) | ✅ own tenant, `submitted` status only (status guard added per LIFE-03) | Same as Sahodaya Admin, scoped by `EventRegionAdminScope::resolve()` | ❌ | Correctly guarded against double-approve/reject now | Same | Verify region_admin's `EventRegionAdminScope` can't be widened by omitting `event`/`region` query params on a GET-turned-POST — **not verified this pass** |
| Bulk approve/reject | ✅ | ❌ | ✅ tenant-scoped only (`abort_if($event->tenant_id !== ...)`) | Not explicitly re-checked inside `FestRegistrationBulkService` — **the controller's tenant check covers the event, but bulk service methods were not read this pass to confirm region_admin's event/region scope is enforced a second time inside the service, not just the middleware.** | ❌ | Partially verified | Bulk service should independently re-validate scope, not rely solely on middleware | **Unclear — needs code read of `FestRegistrationBulkService::approveMany`/`rejectMany` against `EventRegionAdminScope`** |
| Cancel with refund | ✅ | ❌ | ✅, reason required, blocked once results published | Same, scope-gated | ❌ | Implemented correctly | Same | None found |
| School document verification | ✅ | ❌ | ✅ (`FestSchoolVerificationController::verify`) | Not explicitly excluded — route sits under the same `sahodaya-admin` group, so a scoped region/event admin's middleware pass would also reach this action if the route isn't separately gated | n/a | **No guard against re-verifying/un-verifying after the event has moved past registration review, results published, etc.** — `verify()` has zero lifecycle check | A school's verification status should arguably freeze once dependent approvals/results exist | **Gap — verification can be toggled at any time regardless of phase; not wired to `EventLifecycleGate` at all** |
| State workspace (`StateFestWorkspaceController`) | ✅ | ✅ only role permitted (`state.admin` middleware, `state_admin`/`state_staff`) | ❌ | ❌ | ❌ | `state_staff` correctly restricted to read-only (`EnsureStateAdmin::handle`, aborts non-GET for staff) | Same | None found |
| Judge assignment (State) | ✅ | ✅ | n/a | n/a | n/a | `assignJudge()` explicitly does **not** enforce that the assigned user actually holds a judge role — comment in code: "not enforced here since a state_admin can also judge" | Acceptable if intentional, but means any user with *any* account (not necessarily a judge) can be assigned and will then pass `judge.portal`-adjacent checks elsewhere if those checks only look at the assignment record | **Policy decision needed — see F-02** |
| Dashboard stats (State) | ✅ | ✅ | n/a | n/a | n/a | Loads full un-filtered tables (`StateAdminDashboardController::index`) | Scoped to current academic year with pagination/aggregation at the DB layer | **Confirmed defect — E-04** |

**Coverage disclosure:** School Admin-side Fest controllers (`SchoolAdmin\FestRegistrationController`, `FestEventPortalController`, `FestSubstitutionRequestController`, etc. — 9 controllers) and the Portal-side controllers (`FestGateController`, `FestMarkCoordinatorController`, `PortalFestAppealController`) were **not read this pass**. Given the density of scope bugs found even in the well-hardened Sahodaya-side controller, these should be first in line for the next sampling pass, particularly the substitution and appeal flows (state-changing, participant-facing, and — per file names — likely to touch already-approved records).

---

## D. Phase and lock matrix (Fest event, as implemented)

| Phase/Lock | Entity | Scope | Trigger | Who can override | Current enforcement | Missing enforcement |
|---|---|---|---|---|---|---|
| `registration_locked` | FestEvent | Event | Manual toggle by Sahodaya Admin | `override_lifecycle` flag, no reason required, no audit note distinguishing an override from a normal action beyond the generic activity log | `EventLifecycleGate::allowRegistration`/`allowRegistrationReview` — checked in registration creation and in approve/reject/bulk paths | Override has no reason field and isn't time-limited — anyone who can check the box can bypass indefinitely with no record of *why* |
| Registration window (open/close datetime) | FestEvent | Event | `isRegistrationOpen()` (not read this pass — timezone handling unverified) | Same override flag as above | Referenced in `allowRegistration()` | **Timezone behavior at the exact deadline boundary not verified — flagged for follow-up, not confirmed broken** |
| Item-level phase window (`phase_mode_enabled`) | FestEventItem via FestPhaseLifecycleService | Item | Per-item configured open/close | n/a — feature not live | `allowRegistrationForItem()`/`allowMarkEntryForItem()` **fully coded but explicitly not called from any controller** (own doc comment confirms) | **D-02 — dead code path; any event with `phase_mode_enabled=true` today gets zero enforcement of its own item-level windows, silently falling back to nothing since no call site checks it** |
| `scoring_locked` | FestEvent | Event | Manual toggle | none sampled | `allowMarkEntry()` | Not verified against all 6 cited call sites (judge portal, mark coordinator, marks import, sahodaya mark entry) — only the gate method itself was read |
| `results_published` | FestEvent | Event | One-way (`allowPublishResults` refuses if already true) | No "unpublish" path found in sampled code | Blocks registration review, mark entry review (via review's own results-published check), and cancellation | Once published, is there an unpublish/correction path at all? **Not found — if results are published in error, code as sampled offers no rollback.** Flag for F-03 |
| Publish-results gate | FestEvent | Event | `assertAllParticipantsMarked()` | n/a | Correctly re-derives `reportableEventIds()` for partitioned hubs (explicit fix noted in comment) so a hub can't publish with zero real participants counted | None found in sampled code |
| School document verification | FestSchoolVerification | School × Event | none — `updateOrCreate`, freely toggle-able | n/a (no lock exists to override) | **No enforcement at all** | **Confirmed gap — verification isn't wired to any phase; can be flipped after approval/results with no downstream effect or warning** |
| Region-scoped admin write access | FestEventStaff (duty=region_admin) | Region × Event | Assignment record | Broader role (`sahodaya_admin`) bypasses entirely | `EnsureSahodayaAdmin` — GET always allowed if event/region resolves; **non-GET requests with no resolvable event ID in the route are unconditionally blocked** (safe-by-default design) | Confirms UI-only lock risk is low **at the middleware layer**, but see C-matrix note on whether `FestRegistrationBulkService` re-checks scope independently |
| Dual State-domain routing | StateFestEvent, etc. | State | `STATE_APP_DOMAIN` env var | n/a | `routes/state.php` group is registered unconditionally but **inert** until DNS+env configured (confirmed by reading the file's own header comment and `config/state.php` defaults) | **D-01 — when this domain is eventually turned on, the file's own comment flags the session-cookie problem as unsolved. This is a documented, not hidden, ticking gap.** |

---

## E. Detailed issue register

### E-01 — Two parallel, drifting implementations of State Admin Fest workspace
**Category:** Architecture / policy · **Severity:** High · **Confidence:** High (directly observed)
**Evidence:** `routes/web.php` (`admin.state.*` group) and `routes/state.php` (`state.portal.*` group) both route to `App\Http\Controllers\StateAdmin\StateFestWorkspaceController`, operating on the same `App\Models\State\StateFestEvent` models on the `state` DB connection. Frontend: `resources/js/Pages/Admin/StateAdmin/Fest/Index.vue` vs `resources/js/Pages/StateAdmin/Fest/Index.vue` — diffed directly, 20 lines each, already differ (one uses `e.show_url` computed server-side, the other hardcodes `/admin/state-workspace/fest/${e.id}`).
**Reproduction:** Open both Vue files side by side; `diff` shows the URL-construction divergence.
**Current behavior:** Two frontend page trees and two route namespaces exist for identical functionality; the dedicated-domain one is inert in production per its own route-file comment, but is registered (and therefore reachable if `STATE_APP_DOMAIN`/DNS are ever set) with a known-unsolved session-cookie cross-domain problem.
**Business impact:** Any fix applied to one Vue page or one controller branch risks not being applied to the other; the dead-but-registered route group is a landmine for whoever eventually flips the domain switch.
**Recommended solution:** Product decision required (F-01) — pick one canonical State Admin surface, delete/redirect the other, and resolve the session-cookie design before the dedicated domain ships.
**Estimated complexity:** M · **Dependencies:** F-01 decision.

### E-02 — Fest school-verification has no phase/lock enforcement
**Category:** Workflow / policy · **Severity:** Medium · **Confidence:** High
**Evidence:** `app/Http/Controllers/SahodayaAdmin/FestSchoolVerificationController.php:14-46` — `verify()` does `abort_if($event->tenant_id !== $this->sahodaya->id, 403)` and a school-ownership `firstOrFail()`, then unconditionally `updateOrCreate`s the verification row. No call to `EventLifecycleGate`, no check of `results_published`, `registration_locked`, or any status.
**Reproduction:** As a Sahodaya Admin, verify a school's documents, let registrations be approved and results published for that event, then call the same endpoint again with `documents_verified=false`. It succeeds silently.
**Current behavior:** Verification can be flipped at any point in the event lifecycle with no guard and no visible downstream consequence in the sampled code.
**Expected behavior:** Unclear without a product decision on what verification is actually supposed to gate (see F-04) — if it's meant to be a precondition for registration approval, it should lock once approvals exist; if it's purely informational, no fix is needed beyond documenting that.
**Business impact:** Low direct risk (no destructive cascade found), but the un-gated toggle undermines any audit narrative built on "verification happened before approval."
**Recommended solution:** Once F-04 is answered, add an `EventLifecycleGate`-style guard mirroring the registration-review gate.
**Estimated complexity:** S.

### E-03 — Judge assignment does not require the assignee to hold a judge role
**Category:** RBAC / policy · **Severity:** Low-Medium · **Confidence:** High (explicit in code comment)
**Evidence:** `app/Http/Controllers/StateAdmin/StateFestWorkspaceController.php:96-113`, `assignJudge()`. Validates the target user exists by email; no role check. Comment: "expected to hold the state_judge role, though not enforced here since a state_admin can also judge."
**Reproduction:** Assign any registered user's email (school admin, teacher, anyone with any account) as a judge for an item; the assignment is created.
**Current behavior:** No role gate on assignment.
**Business impact:** If downstream judge-portal access is keyed purely off the `StateJudgeAssignment` record (not verified this pass), this could grant portal access to an unintended account. If it's keyed off role + assignment together, impact is null.
**Recommended solution:** Confirm judge-portal access logic (`EnsureJudgePortal`/`EnsureStateJudgePortal` middleware — not read this pass); if it trusts the assignment alone, add a role check or an explicit "not a judge, assign anyway?" confirmation.
**Estimated complexity:** S · **Dependencies:** Read `EnsureStateJudgePortal.php` before implementing.

### E-04 — State Admin dashboard loads unfiltered, all-time tables into memory
**Category:** Reporting / performance / data-integrity · **Severity:** High · **Confidence:** High
**Evidence:** `app/Http/Controllers/Admin/StateAdminDashboardController.php:15-16`:
```php
$programs = FestStateProgram::query()->get();
$remittances = StateRemittance::query()->get();
```
No `where('academic_year', ...)` anywhere in the method; every stat (`total_programs`, `pending_remittances`, etc.) is a cumulative all-time count, computed by loading every row into a PHP collection.
**Reproduction:** As State Admin, load `/admin/state-dashboard`; the "total programs" and remittance figures include every year the platform has ever run, not the current academic year — compare against `AcademicYear::activeRecordLabel()` shown elsewhere on the same page, which *is* year-scoped, creating an internally inconsistent dashboard (one card says "2026-27", the counts below it are all-time).
**Business impact:** Misleading totals is explicitly called out as an audit risk in your brief ("Misleading totals... State Admin should compare regions and Sahodayas") — this is a direct hit. Performance risk grows every year as both tables accumulate.
**Recommended solution:** Add `->where('academic_year', $currentYear)` (or equivalent) to both queries, move counting to `->count()`/`selectRaw` aggregates instead of `->get()->count()`, and paginate `recentRemittances`/`recentPrograms` at the DB layer rather than `->take()` on an in-memory collection.
**Acceptance criteria:** Dashboard stats match a manual DB query filtered to the active academic year; page load time doesn't grow with historical data volume.
**Estimated complexity:** S · **Dependencies:** None.

### E-05 — No database constraint prevents a student being registered twice for the same item
**Category:** Data integrity · **Severity:** High · **Confidence:** High
**Evidence:** `database/migrations/tenant/2026_06_22_000011_phase11_13_event_platform.php`, `fest_participants` table definition (~line 80): only `$table->index(['registration_id', 'student_id'])` — an index, not a unique constraint. No unique constraint tying `(item_id, student_id)` or `(registration_id, student_id)` was found in this or the `fest_comparison_gaps` migration.
**Reproduction (concurrency):** Two simultaneous requests (double-click, retried import, two tabs) to register the same student for the same item would both pass any application-layer duplicate check that isn't wrapped in a DB-level unique constraint + transaction, producing two `FestParticipant` rows.
**Business impact:** Duplicate participants directly affects results, chest-number assignment, and per-student fee billing (`FestSchoolEventFeeService`) — a double-registered student could be billed twice or scored twice.
**Recommended solution:** Add a composite unique constraint (likely `(registration_id, student_id, participant_type)` or a partial unique on active statuses, depending on whether standby/performer roles must coexist for the same student — **needs the same-student-standby policy answered, F-05**) plus a DB transaction around registration creation.
**Estimated complexity:** M (requires a data-cleanup pass for any existing duplicates before the migration can add the constraint) · **Dependencies:** F-05.

### E-06 (Corroborated, re-verified) — Region/Sahodaya bulk-action scope not confirmed independently re-checked in service layer
**Category:** RBAC · **Severity:** Unclear pending follow-up · **Confidence:** Low (not a confirmed defect — a coverage gap in this audit)
**Evidence:** `FestRegistrationReviewController::bulkApprove`/`bulkReject` (`app/Http/Controllers/SahodayaAdmin/FestRegistrationReviewController.php:~330-370`) only check `$event->tenant_id !== $this->sahodaya->id`; they do not re-check `EventRegionAdminScope` before delegating to `FestRegistrationBulkService`. The middleware (`EnsureSahodayaAdmin`) already restricts which `{event}` a region/event admin can route into for non-GET requests — so this is likely covered end-to-end, but this audit did not read `FestRegistrationBulkService::approveMany`/`rejectMany` to confirm it doesn't, say, accept a `school_id` outside the admin's assigned region and silently process it if the event itself is a shared hub covering multiple regions.
**Recommended action:** Read `FestRegistrationBulkService` against `EventRegionAdminScope::resolve()` before treating this as closed. Listed here so it isn't silently dropped.
**Estimated complexity:** Investigation only, S.

### E-07 — CSV/Excel export format is safer than raw CSV but not confirmed formula-injection-proof
**Category:** Security · **Severity:** Low · **Confidence:** Medium
**Evidence:** `app/Support/ExcelExport.php` — exports use SpreadsheetML (`ss:Type="String"`) with `htmlspecialchars(..., ENT_XML1 | ENT_QUOTES)` escaping, not raw CSV. Excel's XML Spreadsheet format does not execute a `String`-typed cell as a formula the way it does an unescaped leading `=`/`+`/`-`/`@` in true CSV, which is a meaningfully safer default than the classic CSV-injection vector. However, this was reasoned from the format spec, not tested against a real Excel client with a value like `=HYPERLINK(...)` in a name field (e.g. `team_name`, `coach_name`, both free-text and both exported per `importTemplate()`/`printApproved()`).
**Recommended solution:** Low-cost defense-in-depth — prefix any exported string that starts with `=`, `+`, `-`, `@`, or a tab/CR with a `'` or a space, matching OWASP's CSV-injection guidance, regardless of the format-level protection already in place.
**Estimated complexity:** S.

### E-08 — Custom notification delivery (`FestEventNotifier`) is not on Laravel's standard Notification/queue infrastructure
**Category:** Notifications / reliability · **Severity:** Unclear — needs follow-up · **Confidence:** Medium
**Evidence:** `app/Notifications/` contains only 2 classes (`PortalResetPassword`, `PortalVerifyEmail`) — auth-only. All Fest notifications (registration approved/rejected, verification updated, etc.) go through the 814-line `app/Services/Events/FestEventNotifier.php`, which calls into `App\Services\Notifications\NotificationService` and a `NotificationTemplate` model with slug-resolution logic, rather than Laravel's `Notification::send()`/queued-notification retry semantics.
**Not verified this pass:** whether `NotificationService` itself queues, retries on failure, or logs delivery failures — `NotificationLog`/`FailedEmailLog` models exist in the codebase (`app/Models/NotificationLog.php`, `app/Models/FailedEmailLog.php`) suggesting some delivery tracking exists, but the service internals weren't read.
**Recommended action:** Read `App\Services\Notifications\NotificationService` and confirm retry-safety and idempotency (a re-approved registration shouldn't double-notify) before treating notification reliability as either solved or broken.
**Estimated complexity:** Investigation only, S.

### E-09 — Region assignment is year-scoped in a join table; multi-region-per-year integrity not verified
**Category:** Data integrity / hierarchy · **Severity:** Unclear — needs follow-up · **Confidence:** Low
**Evidence:** `SchoolRegionAssignment` (`app/Models/SchoolRegionAssignment.php`, 40 lines) is a join table keyed by `school_id` + `region_id` + `tenant_id` + `academic_year`. Whether a unique constraint exists preventing a school from holding two *different* region assignments in the *same* year was not verified — the model file and its migration weren't cross-checked against a unique index in this pass.
**Recommended action:** Check `database/migrations` for the `school_region_assignments` table's indexes; if no unique constraint on `(school_id, tenant_id, academic_year)` exists, a school could be double-counted in two regions' reports simultaneously.
**Estimated complexity:** Investigation only, S.

---

## F. Policy-decision register

**F-01 — Which State Admin implementation is canonical: `admin.state.*` (web.php, live today) or `state.portal.*` (state.php, dedicated-domain, currently inert)?**
*Why it matters:* Two Vue page trees and two route groups already exist and have started to drift (E-01). Every future State Admin feature currently has to be built twice or picked-one-and-abandon-the-other by convention, with no enforcement.
*Options:* (a) Commit to the dedicated-domain migration on a firm timeline, freeze the `web.php` version, and delete the duplicate Vue pages now in favor of the ones under `state.portal.*`; (b) abandon the dedicated-domain plan, `git rm` `routes/state.php` and the `State\*` models per the deprecation-comment precedent already used for `RegionScope.php`; (c) keep both indefinitely with a documented sync process (not recommended — this is how E-01's drift happened).
*Recommended:* (a) or (b) — the half-built state today is strictly worse than either endpoint.
*Affected:* State Admin routing, Vue page trees, session/cookie architecture, `state` DB connection usage.

**F-02 — Should judge assignment require the assignee to already hold a judge-capable role?**
*Why it matters:* E-03. Currently any account can be assigned; whether that's a real gap depends on how judge-portal access is actually gated downstream.
*Options:* (a) Require `state_judge`/equivalent role at assignment time; (b) leave as-is (any trusted account can be deputized) but log/flag assignments to non-judge accounts for visibility.
*Recommended:* Depends on the `EnsureStateJudgePortal` read this audit didn't complete — decide after that follow-up.

**F-03 — Is there meant to be an "unpublish results" / correction path?**
*Why it matters:* `EventLifecycleGate::allowPublishResults` is one-directional in sampled code; no unpublish route was found. Results errors (a mis-entered mark discovered after publication) currently have no sampled recovery path other than the `override_lifecycle` flag on registration review, which doesn't touch already-published result rows.
*Options:* (a) Build a formal "unpublish with reason, re-lock downstream certificates" flow; (b) confirm corrections are handled entirely through direct mark edits post-publication (if so, document that explicitly so State/Sahodaya Admins know it's expected, not broken).
*Recommended:* Needs a State/Sahodaya Admin workflow owner's input — this audit found no evidence either way of which is intended.

**F-04 — What is `FestSchoolVerification.documents_verified` actually supposed to gate?**
*Why it matters:* E-02. Right now it's freely toggleable and, in the sampled registration-approval path, is not referenced at all as a precondition for approval (approval instead checks fee payment and mandatory items).
*Options:* (a) It's advisory-only metadata for Sahodaya Admin's own tracking — no gate needed, just document it; (b) it should block registration approval until true, in which case `FestRegistrationApprovalService`/`FestRegistrationReviewController::approve()` needs a new guard.
*Recommended:* Confirm with whoever owns the school-onboarding workflow; this audit found the field exists and is stored, but nothing downstream currently reads it.

**F-05 — Can the same student appear as both `performer` and `standby` for the same item, or across two different items simultaneously without limit?**
*Why it matters:* E-05's fix (a unique constraint) depends on the answer — a naive `(registration_id, student_id)` unique constraint would break the standby-substitution flow (`FestRegistrationReviewController::substitute()` explicitly moves a student between performer/standby `FestParticipant` rows).
*Options:* (a) Unique on `(item_id, student_id)` regardless of role, with substitution implemented as an update-in-place rather than two rows; (b) unique on `(registration_id, student_id, participant_role)` allowing one performer row and one standby row per registration but no more.
*Recommended:* (b) matches the substitution code's existing data model most closely, but confirm against `FestRegistrationService::substitutePerformer()` before writing the migration.

**F-06 — Can a school hold more than one region assignment within the same academic year?**
*Why it matters:* E-09. Determines whether `school_region_assignments` needs a uniqueness fix and whether region-filtered reports (`FestRegistrationReviewController::index`, region dropdown) can currently double-count a school.
*Recommended:* Almost certainly "no, one region per school per year" is the intended rule — needs a migration-level unique constraint if not already present (unverified).

---

## G. UI/UX improvement plan (scoped to what was sampled)

**State Admin (highest-impact finding):** The dashboard (`StateAdminDashboardController` → `State/Dashboard` Inertia page) presents all-time cumulative counts next to a year-scoped "active academic year" label with no visual distinction — a viewer has no way to tell, from the page alone, that "1,240 remittances" means "since the platform launched," not "this year." Minimum fix: label every stat card explicitly ("All-time" vs "2026-27") until E-04's underlying query is fixed to be year-scoped by default with an explicit "view all years" toggle.

**Global design-system risk from E-01:** maintaining two Vue page trees for the same screen (`Admin/StateAdmin/*` vs `StateAdmin/*`) means any design-system update (spacing, button style, empty-state copy) applied to one will silently not reach the other. This should be resolved as part of F-01, not patched cosmetically.

**Fest school verification (Sahodaya Admin):** since `verify()` has no lock and no lifecycle awareness (E-02), the UI should — at minimum, regardless of the F-04 policy answer — show the verifying admin *when* the school submitted its registration and *whether* registrations have already been approved for that school, so a Sahodaya Admin isn't toggling a checkbox blind to downstream state. Not confirmed whether the current `Sahodaya/Events/*` Vue page already surfaces this context — not sampled this pass.

**Coverage disclosure:** A genuine screen-by-screen UI/UX pass (navigation clarity, breadcrumbs, empty/loading/error states, accessibility, mobile responsiveness, table pagination UX) across the dozens of Sahodaya Admin and School Admin Fest screens was **not performed** in this pass — it requires either running the app and reviewing screenshots, or reading ~50+ Vue files individually, neither of which this audit round covered. This is the single largest gap in this report relative to your original brief's §8, and should be the subject of a dedicated follow-up pass (ideally with actual rendered screenshots, not just component source).

---

## H. Prioritized implementation roadmap

**Immediate (no dependencies, low risk, ship this week):**
- E-04 (State dashboard unfiltered queries) — add year filter + DB-level aggregates. S effort.
- E-07 (export formula-injection prefix guard) — defense-in-depth, no behavior change for legitimate data. S effort.

**Phase 1 — data integrity & investigation (blocks nothing else, but E-05 needs F-05 answered first):**
- F-05 decision → E-05 (unique constraint + dedup migration + transaction on registration create). M effort.
- E-06 follow-up read of `FestRegistrationBulkService` against region-admin scope — investigation only, then fix if a gap is found. S.
- E-09 follow-up read of `school_region_assignments` migration for the missing-unique-constraint question. S.
- E-03 follow-up read of `EnsureStateJudgePortal`. S.
- E-08 follow-up read of `NotificationService` for retry/idempotency. S.

**Phase 2 — workflow/policy closure (needs stakeholder decisions first):**
- F-01 decision → E-01 remediation (pick canonical State Admin path, delete the other). M-L depending on how far the dedicated-domain rollout has actually progressed operationally (unknown to this audit).
- F-04 decision → E-02 (wire school verification into lifecycle gate if it's meant to block approval). S once decided.
- F-03 decision → possible new "unpublish results" workflow if answer is (a). L if built.
- F-02 decision → E-03 fix if a role check is deemed necessary. S.
- F-06 decision → E-09 fix if a uniqueness gap is confirmed. S-M.

**Phase 3 — expand audit coverage (this report's own disclosed gaps):**
- School Admin-side and Portal-side Fest controllers (9+ controllers not read this pass).
- `FestRegistrationBulkService`, `NotificationService`, `EnsureStateJudgePortal`, `EnsureJudgePortal` internals.
- Full screen-by-screen UI/UX pass with rendered screenshots (§G disclosure).
- Migration-level constraint audit across the remaining ~93 fest migrations not opened this pass.
- Import paths (`FestRegistrationImportService`, `FestMarksImportService`, `FestAttendanceImportService`) for the same duplicate/bypass-workflow risks flagged for manual entry in E-05.

**Not assessed at all this pass (explicitly out of scope for time, flagged for a future round):** payment/fee-ledger reconciliation logic (`FestFeeLedgerService`, `FestInvoiceService`), certificate generation, MCQ/Training modules (separate from Fest but sharing the same platform), mobile app (`sahodaya_mobile/`), and the public-facing `FestPortalController`.

---

## Test scenarios recommended (server-side, business-critical)

1. Concurrent double-submit of the same student to the same item — should not produce two `FestParticipant` rows once E-05 lands.
2. Region admin attempting `bulkApprove`/`bulkReject` on a registration outside their assigned region/event via a crafted `school_id`/`event_id` combination — resolves E-06.
3. `FestSchoolVerificationController::verify()` called after `results_published=true` — currently succeeds; add a test that pins down whatever F-04 decides.
4. `StateAdminDashboardController` stats against seeded multi-year data — assert current-year-only counts once E-04 lands.
5. Judge assignment with an email belonging to a `school_admin`-only account — pins down F-02's chosen behavior.
6. Re-clicking "approve" on an already-approved registration (regression test for the already-fixed LIFE-03 guard, to prevent re-introduction).
7. Export of a registration with `team_name = "=cmd|'/c calc'!A1"` — confirms E-07's fix (or the existing format's safety, if F-07 investigation shows no prefix guard is actually needed).

---

*End of report. This audit intentionally leaves items marked "not sampled" or "needs follow-up" rather than filling every requested section with unverified detail — per your instruction to separate confirmed defects from assumptions, and given the module's true size (80+ controllers, 90+ services, ~96 migrations) relative to what one review pass can respons­ibly cover with file:line evidence.*
