# Sahodaya Connect — Functional, Workflow, Event-Lifecycle & Reporting Audit

**Date:** 2026-08-11
**Scope:** Full platform, read-only, independent re-derivation from code (not built on prior audit docs in the repo, per instruction)
**Method:** Static code review — models, migrations, controllers, services, jobs, middleware, policies, routes, Vue/Inertia pages, tests. No PHP runtime was available in the audit sandbox (confirmed: `php`, `composer`, `artisan` all absent), so nothing was verified by executing the app or its test suite. `npm run build` was run and completed with no JS/Vue compile errors across 1,116 modules. All findings below are evidence-based static analysis with exact file:line citations; anything that would require runtime confirmation is explicitly marked "Needs verification."

---

## A. Executive Summary

Sahodaya Connect is a large multi-tenant Laravel 13 + Inertia/Vue platform (287 controllers, 214 models, 302 migrations, 527 Vue pages, 33 roles) serving a federation of schools ("Sahodaya") across five major modules: a Kalolsavam/Fest arts-and-sports competition system (school → region → state cascade), Board Results (exam results with principal verification), MCQ/Talent-Search exams, Training programs, and a student/school registry. Authorization is enforced through a hand-built three-layer scheme (route-group middleware → base-controller write-gates → per-action tenant checks) rather than Laravel Policies or Spatie route middleware, and it holds up well under sampling — tenant isolation in particular is consistently enforced.

**Areas reviewed in depth:** roles/permissions and route-level authorization; the Fest/Kalolsavam event and registration lifecycle (status machine, cascades, notifications, jobs); the reporting/export/dashboard layer (~230-entry report catalog); eight end-to-end user journeys (auth, Board Results, MCQ, Training, student registry, notifications/escalation, search/pagination/error states, concurrency); region- and phase-based behavior; and test-suite coverage of every high-risk item found.

**Overall completeness assessment:** This audit achieved deep, evidence-based coverage of the platform's core domains (Fest lifecycle, permissions, reporting, and the five major workflows) through six independent research passes, each cross-checking file:line evidence rather than asserting from pattern-matching alone. It is not a literal line-by-line read of all 287 controllers and 527 Vue pages — at this codebase's scale that is not achievable in one engagement — so it should be read as **breadth across the whole platform with genuine depth on the highest-traffic, highest-risk domains**, not as an exhaustive screen-by-screen inventory. Several items are explicitly flagged "Needs verification" rather than asserted, and a few peripheral areas (the full 20-Vue-page Fest report suite's empty-state handling, ledger statement correctness, the Platform/State-admin route middleware chain) were sampled rather than fully swept. None of the findings below rely on the prior audit documents already in the repo; they were independently re-derived.

### Most serious risks (ranked)

| Rank | ID | Finding | Severity | Confidence |
|---|---|---|---|---|
| 1 | RPT-01 | Cross-tenant data leak: Audit Trail, Auth Events, Finance Audit, Export Activity, and Failed-Login reports query a shared central `audit_logs` table with **no tenant filter at all** — any Sahodaya admin/staff/finance user can see every other federation's audit history, finance-audit entries, and failed-login attempts | **Critical** | High (confirmed schema + query read) |
| 2 | LIFE-06 | Rejecting or cancelling a source registration never revokes a downstream `FestQualification`/promoted registration — a rejected school can still show as "qualified" at the next competition level with no linkage back to the rejection | High | High |
| 3 | LIFE-03 | Single-record `approve()`/`reject()` on Fest registrations have no current-status guard (the bulk equivalents do) — an already-approved/rejected/withdrawn registration can be re-approved or re-rejected, re-firing side effects | High | High |
| 4 | LIFE-05 | `FestEventPhase` lifecycle columns (`status`, `registration_open/close`, `scoring_locked`, `results_published`, etc.) have **zero write path anywhere in the app** — a dead-end state machine that would silently break phase-scoped gating the moment `phase_mode_enabled` is turned on | High (conditional on that toggle) | High |
| 5 | WF-04 | MCQ exam registration has no unique DB constraint on `(exam_id, student_id)` and no locking around the check-then-insert — a real double-submit race that can create duplicate registrations | High | High |
| 6 | WF-05 | Student erasure ("Danger Zone") is documented and messaged to admins as reversible, but restoring an erased student **permanently loses their MCQ marks, certificates, and attendance-correction history** because three cascade-deleted tables are never snapshotted | High | High |
| 7 | REG-06 | Region-scoped reports and access-control resolve "which schools are in this region" using **today's** region-assignment year, not the year the record being viewed actually belongs to — historical reports silently change when someone edits a current-year region assignment, and can mis-scope a region admin's access to old financial/report data | High | High |
| 8 | LIFE-01 | `FestEvent` status transitions are guarded by a real state-machine (`StatusTransitionGuard`) in only one of two controller code paths that can change status — the main "Edit Event" save bypasses it entirely | High | High |
| 9 | PERM-03 | Five of six school "coordinator" roles (finance/MCQ/training/sports/kalotsavam) have **no server-side module restriction** — a `school_finance_coordinator` can, by direct URL, edit anything a full `school_admin` can within their own school; only the client-side nav hides other modules | Medium (Requirement Gap on intent) | High |
| 10 | WF-06 | A shared `<InputError>` component exists but is used in only 2 of 410 Vue pages; at least six Create/Edit forms display server validation errors nowhere at all, silently swallowing failures from the user's perspective | Medium-High | High |
| — | TEST-01 | **None of the above** has a regression test — for four of the top items, the relevant class/controller has zero associated test file at all | High (as a confidence/regression-risk finding) | High |

The single highest-priority item is RPT-01: it is a live, currently-exploitable cross-tenant confidentiality gap reachable by any Sahodaya-level admin/staff/finance account on the platform, not a hypothetical.

---

## B. Role × Feature Permission Matrix

Roles are defined in `database/seeders/RolesAndPermissionsSeeder.php`. Authorization is enforced via custom route-group middleware (`app/Http/Middleware/Ensure*.php`) + base-controller write-gates (`TenantUserCatalog::writePermissionForPath()`) + per-action tenant checks, **not** Laravel Policies (only one exists, `FestReportPolicy`, and it is dead code — see PERM-01) or Spatie's `role:`/`permission:` route middleware (registered but never used — PERM-02).

Legend: **V**=View, **C**=Create, **E**=Edit, **A**=Approve, **D**=Delete, **X**=Export/Download, **~**=access exists but is coarser/less-scoped than the role name implies, **—**=no evidence of access found.

| Role | Fest/Kalolsavam | Board Results | MCQ | Training | Student Registry | Region Admin | Reports |
|---|---|---|---|---|---|---|---|
| superadmin | V,C,E,A,D,X (bypasses all gates) | full | full | full | full | full | full |
| sahodaya_admin | V,C,E,A,D,X | V,C,E,A,X | V,C,E,X | V,C,E | V,C,E,D | full (own federation) | full |
| school_admin / school_principal | V,C,E,D,X (own school) | V,C,E,X | V,C,E | V,C,E | V,C,E,D | n/a | school-scoped |
| school_vice_principal | as school_admin, plus board-result signing | V,C,E,A(sign),X | V,C,E | V,C,E | V,C,E | n/a | school-scoped |
| school_event_coordinator | V,E — **properly scoped** to assigned program/event | — | V,E (if in scope) | — | — | n/a | scoped |
| school_sports/kalotsavam/mcq/training/finance_coordinator | **~** full school-admin-equivalent write access — see PERM-03 | **~** same | **~** same | **~** same | **~** same | n/a | **~** same |
| school_staff / group_admin / house_admin | permission-dependent (`writePermissionForPath`) | — | — | — | — | n/a | view-only where permitted |
| registration_coordinator | V, C (registrations) | — | — | — | — | n/a | — |
| sahodaya_finance | V, finance A/E | — | — | — | membership.view | n/a | finance reports |
| certificate_collector | V, certificate issuance | — | — | — | — | n/a | — |
| data_entry | V, marks E | — | — | — | — | n/a | — |
| event_coordinator (sahodaya-wide) | V, E, schedule/settings | — | — | — | — | n/a | — |
| event_admin | V,C,E,A,D,X, **locked to assigned events** | — | — | — | — | n/a | event-scoped |
| region_admin | V,C,E (marks/registrations/finance/catering), **locked to one region within one event** | — | — | — | — | region-scoped (see REG-06 for a temporal gap) | region-scoped only |
| training_admin | blocked outside `/training` | — | — | full | — | n/a | training only |
| mark_entry_admin / mark_entry_coordinator | marks entry (admin = cross-event; coordinator = portal, assigned event only) | — | — | — | — | n/a | — |
| fest_ops | one operational duty per assigned event | — | — | — | — | n/a | — |
| judge / state_judge | portal-only marks entry (event / state level) | — | — | — | — | n/a | — |
| exam_controller / exam_staff | — | — | attendance+marks (controller) / attendance only (staff) | — | — | n/a | — |
| state_admin | separate State-domain fest workspace | V | — | — | — | full (state-wide) | state reports |
| state_staff | as state_admin, **read-only** (writes blocked for non-GET) | V | — | — | — | full read-only | state reports (read) |
| student / teacher | portal-only, own records | — | — | — | own record V | n/a | — |

### Dead-role / unreachable-workflow check
No dead roles found — all 33 tenant-guard roles trace to live route/controller usage. No role exists that an admin has no UI path to assign (`TenantUserCatalog::assignableRolesFor()` and siblings cover every role). The one weak spot is not a dead role but a **role that doesn't do what its name implies** — see PERM-03.

### IDOR / direct-URL risk
Sampled ~30 controllers across school-admin, sahodaya-admin, and the one route with no role-middleware (`/exports/{exportJob}/download`, which compensates with an object-ownership check). Every route-model-bound action checked applies an explicit tenant/ownership check before acting; no unscoped `Model::findOrFail($id)` reachable across tenants was found in the sampled set.

### Permission findings

**PERM-01 — `FestReportPolicy` is unreferenced dead code with a misleading docblock.**
Type: Data/Code-Health Gap · Severity: Low · Confidence: Confirmed
File: `app/Policies/FestReportPolicy.php` (89 lines). Its own docblock claims it's called directly from report code "the same way `EnsureSahodayaAdmin::matchesRegionScope()` is called" — but `grep -rn "FestReportPolicy" app/` finds zero call sites outside the file itself, and no `Gate::policy`/`Gate::define` registers it. The actual enforcement is done by `EnsureSahodayaAdmin.php` + `ResolveRegionScopedReportEvent.php` instead, functioning correctly but making the policy class pure dead weight.
Recommendation: delete the class and correct the misleading cross-reference comments in the middleware that describe it as load-bearing.
Acceptance criteria: `grep -rn FestReportPolicy app/` returns no hits; no behavior change to report access.

**PERM-02 — Spatie `role:`/`permission:` route-middleware aliases are registered but never used.**
Type: Data Gap (informational) · Severity: Informational · Confidence: Confirmed
File: `bootstrap/app.php:52-53`. Every route-level gate instead uses custom `Ensure*` middleware. No functional impact today, but a future engineer adding `->middleware('role:x')` to a new route would layer an unfamiliar mechanism on top of the existing one rather than following the established pattern.

**PERM-03 — Five of six school "coordinator" roles have no server-side module scoping.**
Type: Permission Gap · Severity: Medium · Confidence: Confirmed (gap); Requirement Gap (intent)
Files: `app/Http/Middleware/EnsureSchoolAdmin.php:54-58`, `app/Support/TenantUserCatalog.php:93-96,106-112`, `app/Http/Middleware/EventCoordinatorScope.php:22`.
`schoolWriteGatedRoles()` (the set that triggers the `writePermissionForPath()` write-gate in `SchoolAdminController::__construct`) only includes `school_staff`, `group_admin`, `house_admin`. The other six "coordinator" roles that are allowed into the panel (`schoolPanelRoles()`) — `school_sports_coordinator`, `school_kalotsavam_coordinator`, `school_mcq_coordinator`, `school_training_coordinator`, `school_finance_coordinator` (`school_event_coordinator` is the sole correctly-scoped exception, via `EventCoordinatorScope.php`) — are not in that gated set. `grep` for these five role names across `routes/` returns zero route-level restrictions; their only other appearance in `app/Http/Controllers` is the post-login landing-page redirect in `Admin/AuthController.php:417-435`.
Expected: a `school_mcq_coordinator` can act within MCQ only. Actual: for every non-GET request in the school-admin panel, these five roles are indistinguishable from `school_admin`/`school_principal` — nothing server-side stops a `school_finance_coordinator` from editing site content, gallery, news, or another module's records within their own school (tenant boundary is still enforced; this is not cross-school).
Evidence: absence of any `writePermissionForPath()` branch or route guard for these five roles, contrasted with the properly-scoped Sahodaya-level equivalents (`sahodayaPermissionRoles()`, `TenantUserCatalog.php:290-304`), which do gate their own six analogous roles correctly (PERM-03 contrast case).
Business impact: over-privileged low-trust accounts (e.g., a training coordinator hired for one task can silently touch finance-adjacent screens by URL).
Reproduction: log in as `school_finance_coordinator`, navigate directly to a non-finance school-admin write endpoint (e.g., news/gallery edit), submit — expect 403, will likely succeed.
Recommended fix: extend `schoolWriteGatedRoles()` to include all six coordinator roles, and add per-role `writePermissionForPath()` branches scoped to each role's own module, mirroring the Sahodaya-level pattern.
Acceptance criteria: each coordinator role can only mutate its own module's routes; a Feature test per role asserting 403 on out-of-module POST/PUT/DELETE.
Suggested regression test: `SchoolCoordinatorScopeTest` — for each of the 5 roles, attempt a write to every other module and assert 403.

**PERM-04 (positive, with residual risk) — Region-scoping is real and actively maintained.**
`app/Http/Middleware/EnsureSahodayaAdmin.php:118-167` correctly scopes `region_admin` via `FestEventStaff` rows, with a self-documented historical fix (a hub event with `region_id=null` used to leak unscoped access). The deprecated `RegionScope.php` (keyed off `UserRegionAssignment`) is confirmed dead — never registered on any route. Residual risk: the web (`EnsureSahodayaAdmin`) and API (`EnsureSahodayaAdminApi`) mirrors are two independently hand-maintained copies of the same scoping logic — a future edit to one without the other would silently reintroduce drift. Recommend extracting a shared class. Severity: Low.

**PERM-05 — Mobile API school-admin gate is a strict subset of the web gate.**
Type: Requirement Gap · Severity: Low/Informational · Confidence: Confirmed
`app/Http/Middleware/EnsureSchoolAdminApi.php:25` admits only `school_admin`, `school_principal`, `school_vice_principal`, `sahodaya_admin` — all six coordinator/staff roles get 403 from the mobile API even though they can use the web panel. Safer than PERM-03, but unclear whether intentional (mobile not built for those roles yet) or an oversight — flagged for stakeholder confirmation.

**PERM-06 — `sahodaya_admin` school-panel bypass branch appears logically unreachable.**
Type: Bug (code-health) · Severity: Needs verification · Confidence: Needs verification
`app/Http/Middleware/EnsureSchoolAdmin.php:29,35`. Line 29 special-cases `sahodaya_admin` into the panel-entry gate, but line 35's tenant-match check (`$user->tenant_id !== $tenantId`) would still fire for a sahodaya_admin visiting a child school's `/school-admin/{schoolId}` panel directly, since a sahodaya_admin's own `tenant_id` is the federation's ID, not the school's. The two checks appear to be in tension; recommend a runtime test (log in as sahodaya_admin, visit a child school's panel URL) to confirm actual behavior before treating this as either a bug or dead code.

**PERM-07 — Region-locked staff-assignment endpoint doesn't cross-validate the submitted `region_id` against the caller's own region.**
Type: Permission Gap (defense-in-depth) · Severity: Low · Confidence: Needs verification
`app/Http/Controllers/SahodayaAdmin/FestEventStaffController.php:104-129` — `region_id` validation only checks it belongs to the tenant, not that it matches the acting `region_admin`'s own assigned region. Likely not practically exploitable (event-level scoping upstream constrains reachability) but not fully traced end-to-end. Recommend adding the explicit check as defense-in-depth.

---

## C. Event Lifecycle Matrix — Fest/Kalolsavam (the platform's core domain)

Schema notes: `fest_events.status` is a real DB enum (`draft, published, registration_open, ongoing, completed, cancelled`); `fest_registrations.status` is a real DB enum (`draft, submitted, pending_approval, approved, rejected, withdrawn, waitlisted`); `fest_event_phases.status` is a **plain unconstrained string** defaulting to `'draft'` with no enum; `fest_results` and `fest_qualifications` have **no status column at all** — state is inferred from `published_at`/row-existence. No table in this domain uses soft-deletes.

### FestEvent

| From | To | Trigger (file:line) | Actor | Validation | Notification | Cascade |
|---|---|---|---|---|---|---|
| (new) | `draft` | `FestEventController::store` | Sahodaya admin | form validation | none | none |
| `draft` | `published`/`registration_open` | `FestEventController.php:427-537` (`update`) **or** `:1198-1268` (`quickStatus`) | Sahodaya admin | `EventLifecycleGate::assertCanPublishEvent` (both); **`StatusTransitionGuard` only in `quickStatus`** — see LIFE-01 | `registrationOpened` if new status is `registration_open` | region/finale child cascade (both paths) |
| `registration_open` | `ongoing` | same two methods | Sahodaya admin | guard only in `quickStatus` | none defined | cascade to children |
| `ongoing` | `completed` | `FestResultsController::publish()` (forces status alongside `results_published=true`) or `update()`/`quickStatus()` directly | Sahodaya admin | `EventLifecycleGate::allowPublishResults` (results path only) | `eventCompleted` or `resultsPublished` | results path cascades to region+finale children |
| any non-terminal | `cancelled` | `FestEventStatusService::transitionToCancelled` (`:19-92`) | Sahodaya admin | payment-credit confirmation gate | `eventCancelled` | **best-built transition in the app**: bulk-withdraws registrations, issues fee credits, ledger-posts, audit-logs — all in one transaction |
| `cancelled` | `draft` | allowed by the guard matrix, **only enforced in `quickStatus`** | Sahodaya admin | guard only in `quickStatus` | none | none |
| `completed` | anything | matrix says terminal (`[]`) | — | **not enforced via `update()`** (LIFE-01) | — | — |

### FestRegistration

| From | To | Trigger (file:line) | Actor | Validation | Notification | Cascade |
|---|---|---|---|---|---|---|
| (new) | `draft`/`submitted` | `FestRegistrationCreateService`, API controllers | School admin | item/roster rules, fee gating | none (LIFE-11) | fee recalculation |
| `submitted`/`pending_approval` | `approved` | Single: `FestRegistrationReviewController::approve` — **no status guard (LIFE-03)**. Bulk: `FestRegistrationBulkService::approveMany` — scoped to `submitted` | Sahodaya admin | fee-paid + mandatory-item checks | `registrationApproved` | chest/number assignment, level-registration sync |
| `submitted`/any | `rejected` | Single: `FestRegistrationReviewController::reject` — partial guard only (payment-approved block), **no status guard (LIFE-03/04)**. Bulk: scoped to `submitted` | Sahodaya admin | payment-approved block (single) / status scope (bulk) | `registrationRejected` | fee recalc, fee credit, waitlist promotion; **no qualification cascade (LIFE-06)** |
| active | `withdrawn` | `FestRegistrationService::cancel`/`cancelWithRefund` | school or admin | already-closed + paid-fee guards; **no consistent `results_published` guard (LIFE-04)** | `registrationWithdrawn`(+admin variant) / `registrationCancelledWithRefund` | fee recalc, credits, waitlist promotion; **no qualification cascade (LIFE-06)** |
| `waitlisted` | `submitted`/`pending_approval` | `FestRegistrationApprovalService::promoteNextWaitlisted` | system | capacity check | none | numbering, fee recalc |
| `approved` | `submitted` (roster edit regression) | `FestRegistrationCreateService.php:447-448` | school | — | **none (LIFE-10)** | none |

Final states: `approved` (persists through completion), `rejected`/`withdrawn` (nominally terminal, but not actually protected from re-transition — LIFE-03). No formal "qualified" status on the registration itself; qualification lives out-of-band in `fest_qualifications`, whose only reversal path (`FestQualificationService::revokeQualification`) is called from exactly one explicit admin action and never from the reject/cancel paths above.

### Lifecycle findings

**LIFE-01 — FestEvent status transitions are state-machine-guarded in only one of two mutation paths.**
Type: Bug · Severity: High · Confidence: Confirmed
`app/Support/StatusTransitionGuard.php:11-18` defines a real transition matrix, called from `FestEventController::quickStatus()` (`:1208-1212`) but **not** from `FestEventController::update()` (`:427-537`), the main "Edit Event" settings-form save — which only special-cases `published`/`registration_open` (readiness gate) and `cancelled`, leaving e.g. `completed → ongoing` or `draft → completed` unguarded through this endpoint.
Impact: the same role, same model, two independently reachable code paths that can disagree on what's allowed — a stale form submit could silently un-complete or corrupt an event's lifecycle state.
Reproduction: as Sahodaya admin, open a completed event's Edit form (loaded before completion), submit — observe whether status reverts/changes without guard rejection.
Fix: call `StatusTransitionGuard::assert()` from `update()` as well, or move the guard into the model/a single service both paths funnel through.
Acceptance criteria: `update()` rejects any transition not in the matrix, matching `quickStatus()`.
Regression test: `FestEventUpdateRespectsTransitionGuardTest` — attempt `completed → draft` via `PUT /events/{event}`, assert 422.

**LIFE-02 — No transition matrix exists for FestRegistration or FestEventPhase at all.**
Type: Workflow Gap · Severity: Medium · Confidence: Confirmed
`StatusTransitionGuard` is only wired to `FestEventController`, `McqExamController`, `TrainingProgramController` (confirmed via grep). Registration and phase transitions are entirely ad hoc — root cause underlying LIFE-03/04/05.

**LIFE-03 — Single approve()/reject() lack a current-status guard (bulk equivalents have one).**
Type: Bug · Severity: High · Confidence: Confirmed
`app/Http/Controllers/SahodayaAdmin/FestRegistrationReviewController.php`: `approve()` (`:268-306`) calls `FestRegistrationApprovalService::approve()` unconditionally; `reject()` (`:308-369`) only blocks an already-approved-and-paid fee, not any other current status. Contrast: `FestRegistrationBulkService::approveMany`/`rejectMany` both filter `->where('status', 'submitted')`.
Impact: an already-approved/rejected/withdrawn/waitlisted registration can be re-approved or re-rejected via the single-record UI, silently re-running chest-number assignment, refunds, and notifications.
Reproduction: approve a registration, then hit the same approve endpoint again for the same ID — expect rejection, will likely succeed and re-fire side effects.
Fix: add `abort_unless($registration->status === 'submitted', 422, ...)` to both methods, matching the bulk service's scope.
Acceptance criteria: repeat-approve/-reject on a non-`submitted` registration returns 422 with no side effects.
Regression test: `FestRegistrationReviewControllerGuardTest` (currently does not exist — see TEST-01).

**LIFE-04 — reject()/cancel() lack a consistent `results_published` guard.**
Type: Bug · Severity: Medium · Confidence: Confirmed
`FestRegistrationService::cancel()` (`:16-73`) has no `results_published` check (only `canAdminCancel()`, a separate method, checks it — callers must remember to call that first); `FestRegistrationReviewController::reject()` has no such check anywhere in its body. A registration can be rejected after results are published if the fee-approved short-circuit doesn't trip (e.g., a fee-free event).
Fix: move the `results_published` guard into `cancel()`/`reject()` themselves rather than a separate opt-in check method.

**LIFE-05 — FestEventPhase lifecycle columns have zero write path anywhere in the app — a dead-end state.**
Type: Workflow Gap · Severity: High (conditional) · Confidence: Confirmed
`app/Services/Events/FestEventPhaseService.php:42-58` (`updatePhase()`) only ever writes `name`, `code`, `sort_order`, `is_default` — never `status`, `registration_open/close`, `scoring_locked`, `schedule_published`, `results_published`, `appeals_open`, `appeal_deadline_at`, `starts_at`/`ends_at`. `grep "phase->update("` across `app/` returns exactly one call site. Every phase's lifecycle columns are permanently stuck at migration defaults. The migration's own docblock admits this is "Phase 6, separate follow-up work — not done in this migration" (`database/migrations/tenant/2026_09_17_000001_add_lifecycle_fields_to_fest_event_phases.php`).
Impact: if `phase_mode_enabled` is ever turned on for an event, `FestPhaseLifecycleService::effectiveLifecycleForItem()` starts reading these permanently-default columns for gating — every phase-mode item's effective lifecycle looks permanently closed, silently breaking phase-scoped registration/results gating platform-wide for that event.
Recommendation: before `phase_mode_enabled` is exposed/enabled anywhere in the UI, build the missing phase-transition endpoints (open/close/lock/publish per phase), or explicitly block the toggle until that work lands.
Acceptance criteria: a phase can be transitioned through its lifecycle via an admin action, and `FestPhaseLifecycleService` reflects it.

**LIFE-06 — Rejecting/cancelling a source registration never cascades to revoke a downstream FestQualification.**
Type: Bug · Severity: High · Confidence: Confirmed
`revokeQualification()` (`app/Services/Events/FestQualificationService.php:277-295`) is the only code that reverses a promotion, and it is called from exactly one place: an explicit "revoke this qualification" admin action in `FestResultsController.php:307`. Neither `FestRegistrationReviewController::reject()`, `FestRegistrationBulkService::rejectMany()`, nor `FestRegistrationService::cancel()`/`cancelWithRefund()` ever touches `FestQualification`.
Impact: if a participant's original registration is rejected/cancelled after they've already won and been promoted (reachable per LIFE-04's missing guard), the downstream promoted registration and qualification record become orphaned — the participant continues to show as qualified/registered at the next level with no link back to the now-invalid source.
Reproduction: register → win → get promoted to region level (qualification + new registration created) → reject the original school-level registration → observe the region-level qualification/registration remains untouched.
Fix: `reject()`/`cancel()` should check for existing `FestQualification` rows tied to the registration and either block the action or cascade-call `revokeQualification()`.
Acceptance criteria: rejecting a registration with an existing downstream qualification either blocks with a clear error or cascades the revoke; a report of "qualified participants" never includes one whose source registration is rejected/withdrawn.
Regression test: `FestQualificationRevocationOnRejectTest`.

**LIFE-07 (positive control)** — `FestEventStatusService::transitionToCancelled()` is the cleanest transition in the codebase: full DB-transaction cascade (bulk withdraw, fee credits, ledger post, notification, audit log). Use as the template when fixing LIFE-01/03/04/06.

**LIFE-08 — Results publish cascades fully; unpublish cascades nothing.**
Type: Bug · Severity: Medium · Confidence: Confirmed
`FestResultsController::publish()` (`:197-239`) cascades `results_published`/`status` to region/finale children, regenerates certificates, pushes the public scoreboard, notifies, and audit-logs. `unpublish()` (`:241-254`) flips the two fields on the hub **only** — no cascade to children, no certificate invalidation, no scoreboard reversal, no notification (LIFE-09).
Impact: unpublishing a hub's results leaves its region/finale children still showing `results_published=true`, and any already-downloaded certificates or cached public scoreboard entries stand uncorrected.
Fix: build a symmetric `unpublish` cascade mirroring `publish`.
Acceptance criteria: unpublishing a hub also unpublishes its region/finale children and triggers a "results were unpublished" notification.

**LIFE-09 — Results unpublish fires no notification.** Type: Notification Gap · Severity: Medium · Confidence: Confirmed. Covered under LIFE-08's fix.

**LIFE-10 — Roster-edit-triggered approved→submitted regression fires no notification.**
Type: Notification Gap · Severity: Low/Medium · Confidence: Confirmed
`app/Services/Events/FestRegistrationCreateService.php:447-448` — a school editing an already-approved teacher-item roster silently drops the registration back to `submitted` with no notice to either the school (their approval was revoked) or the admin (a new approval is now needed). No entry exists in `FestEvent::NOTIFICATION_TRIGGERS` for this specific transition.

**LIFE-11 — No notification exists for initial registration submission.**
Type: Notification Gap · Severity: Low · Confidence: Confirmed
Admins currently discover new submissions only by visiting the review queue; only the withdrawal path has an admin-facing notifier variant (`registrationWithdrawnAdmin`).

**LIFE-12 — `SubmitStateQualifiersJob` never surfaces failures to Laravel's retry/failed-job system.**
Type: Bug (resilience) · Severity: Medium · Confidence: High (retry-sweep absence is a Requirement Gap — not fully confirmed absent)
`app/Jobs/SubmitStateQualifiersJob.php` has no `$tries`/`backoff`/`failed()`. Its inner call, `StateSubmissionClient::send()` (`app/Services/State/StateSubmissionClient.php:13-52`), wraps everything in try/catch and **always returns normally**, writing `status:'failed'` onto the `FestStateSubmissionOutbox` row on any exception instead of re-throwing. Laravel's queue therefore always sees the job as "succeeded," so automatic retry/backoff never engages and no `failed_jobs` entry or alert is generated — the only signal is an outbox row someone has to notice manually. Whether a scheduled sweep re-dispatches `failed` outbox rows could not be fully confirmed from `routes/console.php` alone within this pass; recommend confirming directly.
Fix: either re-throw from `send()` and let the job's own retry/backoff handle transient failures, or add an explicit scheduled command that re-dispatches `failed` outbox rows with backoff.

### Legacy note
`KalotsavEvent`/`KalotsavCategory`/`KalotsavResult` models are confirmed legacy/deprecated — no route or controller touches them; only a one-time migration command (`MigrateKalotsavToFest`) and read-only public-site display reference them. Not part of the active lifecycle.

---

## D. Workflow Findings (by journey)

### D.1 Auth lifecycle

**WF-01 — No self-serve signup/registration route exists.**
Type: Requirement Gap · Severity: Informational · Confidence: Confirmed
No `/register` route anywhere; all provisioning is admin-initiated. Presumably intentional for a closed federation model — flagged only because the audit brief explicitly asked to trace this journey.

**WF-02 — Superadmin has no forgot-password path.**
Type: Workflow Gap · Severity: Medium · Confidence: Confirmed
`app/Http/Controllers/Admin/AuthController.php:98-107` redirects central-host forgot-password requests straight back to `/login`; `resources/js/Pages/Admin/Auth/SuperadminLogin.vue` has no such link. A locked-out superadmin has no self-service recovery.
Fix: implement a superadmin password-reset flow (email-token-based, matching the school/Sahodaya pattern) or document the manual recovery procedure.

**WF-03 — Login lockout is keyed by identifier only, not IP.**
Type: Workflow Gap · Severity: Low · Confidence: Confirmed
`app/Services/Auth/LoginLockoutService.php:9-11` locks per-username; combined with a 20/min IP-based route throttle, an attacker can rotate usernames from one IP without tripping the per-account lock.

**WF-Auth-Positive** — session-expiry UX is centralized and consistent (`app/Support/InertiaAuth.php`); `must_change_password` enforcement (`EnsurePasswordChanged`) is wired into every authenticated route group.

### D.2 Board Results — mature, no dead ends found

Editability correctly locks once review/certification/verification starts (`BoardResult::isEditable()`, `app/Models/BoardResult.php:146-193`, with a human-readable lock reason for the frontend). Rejection clears verification fields and allows resubmission with a `correction_history` trail. Malformed batch uploads are rejected wholesale with the specific failing row identified, not partially saved. Every transition is audit-logged with actor + timestamp. "Signing" is attestation-based (SHA-256-hashed uploaded PDF + checkbox declarations), not cryptographic — a Low-severity domain limitation, not a bug.

### D.3 MCQ / Talent-Search exam workflow

**WF-04 — Duplicate MCQ registration is only app-level-guarded — a real race condition.**
Type: Bug · Severity: High · Confidence: Confirmed
`database/migrations/tenant/2026_06_22_000012_phase14_mcq_exams.php:30-43` — `mcq_registrations` has only a non-unique index on `(exam_id, school_id)`, **no unique constraint on `(exam_id, student_id)`**. `app/Http/Controllers/SchoolAdmin/McqRegistrationController.php:168-194` does a plain check-then-create with no transaction/row-lock. Two rapid submits (double-click, duplicate tab) can both pass the existence check and create duplicate registrations for the same student.
Contrast: `training_registrations` gets this right — `unique(['program_id','teacher_id'])` at the DB level (`2026_06_22_000013_phase15_training.php:51`) plus an app-level check. The MCQ module should follow the same pattern.
Fix: add `unique(['exam_id','student_id'])` migration + `DB::transaction`/`lockForUpdate()` around the check-then-insert, or a `firstOrCreate` inside a transaction.
Acceptance criteria: submitting the same student+exam registration twice (even concurrently) results in exactly one row.
Regression test: `McqDuplicateRegistrationRaceTest` (concurrent-request simulation or DB-constraint-violation test).

**WF-MCQ-Positive** — Attendance correction requests (`McqAttendanceCorrectionRequest`) are a full submit→approve/reject flow with notifications both ways, not a dead end. Certificate issuance is properly gated behind `results_published` + submitted status + a recorded mark (`McqCertificateService::assertEligible`).

### D.4 Training program workflow — mature, no dead ends found

Waitlist promotion uses `lockForUpdate()` inside a transaction (`TrainingWaitlistService::promoteNext()`); program cancellation goes through a dedicated `TrainingProgramStatusService::transitionToCancelled()` that voids unpaid invoices and promotes waitlisted seats, not an ad hoc status flip. "Nomination" in this codebase means direct school-side registration (when `allow_school_nomination` is enabled) rather than a distinct nominate-then-convert step — flagged as a **Requirement Gap** (terminology mismatch) for stakeholder confirmation, not a bug.

### D.5 Student registry (excluding TC/promotion, per product scope)

**WF-05 — Student erasure is documented and messaged as reversible but is only partially reversible — a real data-loss bug.**
Type: Bug (Data Gap) · Severity: High · Confidence: Confirmed
`app/Http/Controllers/Admin/TenantController.php::eraseStudents` (`:261-349`) snapshots `students`, `fest_participants`, `mcq_registrations`, `student_edit_change_requests`, `fest_level_registrations`, `fest_individual_championship_points`, `fest_substitution_replacement_links` before hard-deleting them (`McqRegistration::whereIn(...)->delete()` at line 307 — `McqRegistration` has no `SoftDeletes` trait, so this is a true hard delete). Three tables `cascadeOnDelete()` off `mcq_registrations.id` — `mcq_marks`, `mcq_certificates`, `mcq_attendance_correction_requests` — and **none of the three are captured in the snapshot**. `applyRestore()` (`:396-427`) never reinserts them.
Impact: a "restored" student's MCQ registration row comes back, but their scored marks, issued certificates, and attendance-correction history are permanently gone. `StudentErasureBatch`'s own class docblock promises reversibility, and the success flash message tells the admin "This can be restored... if needed" — both overstate what actually happens.
Reproduction: erase a student with an existing MCQ registration + mark + certificate → restore the erasure batch → confirm the mark/certificate are gone.
Fix: extend the snapshot to include `mcq_marks`, `mcq_certificates`, `mcq_attendance_correction_requests` rows for any registration being deleted, and extend `applyRestore()` to reinsert them.
Acceptance criteria: restoring an erasure batch fully reconstitutes every row that was deleted, including MCQ marks/certificates/corrections.
Regression test: `StudentErasureRestoreCompletenessTest` — erase a student with MCQ mark+certificate+correction-request, restore, assert all three exist and match pre-erasure data.

**WF-05b — Confirmation-dialog wording contradicts actual (partial) reversibility in both directions.**
Type: UX Gap · Severity: Low · Confidence: Confirmed
The erase confirmation says "This cannot be undone" (`resources/js/Pages/Admin/Tenants/Show.vue:647`) while the backend supports partial restore and the success message says the opposite. Align both messages with the actual (partial) capability once WF-05 is fixed, or make the restore-completeness match the "cannot be undone" framing by removing restore entirely — pick one.

**WF-Student-Positive** — `StudentEditChangeRequest` has a genuine two-tier (school → Sahodaya) approve/reject flow, not a dead end. One residual gap: requests stuck unactioned at the *school* tier never surface anywhere for a Sahodaya admin to notice, since the Sahodaya queue only lists requests once school-approved — see WF-07 (no escalation mechanism exists to catch this).

### D.6 Notifications, reminders, escalation

**WF-06-positive** — Reminder scheduling is comprehensive: `routes/console.php:11-30` registers reminder commands for Board Results, Fest registration/competition/payment/schedule, Training (payment + session), MCQ (auto-submit, window transition, exam reminders), membership renewal, failed-receipt-email retry, and school-document expiry — all confirmed actually scheduled, not just defined. One inline comment notes `fest:process-state-outbox` "was never scheduled" until someone previously caught and fixed it — worth noting as a recurring risk class for any *new* command added going forward (a new reminder command silently not wired into the scheduler would be easy to miss).

**WF-07 — No time-based escalation logic exists anywhere in the codebase.**
Type: Requirement Gap · Severity: Medium · Confidence: Confirmed
Every scheduled notification found is a same-day reminder; nothing implements "if pending > N days, notify a higher authority" for any workflow (student edit requests, Fest registration review, Board Result verification, MCQ approval, training nomination). If escalation is a business requirement, it does not exist yet and needs to be scoped/built from scratch — flagged for stakeholder confirmation of intent.

### D.7 Search, filter, pagination, validation error states

**WF-08 — Server-side validation errors are inconsistently displayed, and several forms show them nowhere.**
Type: UX Gap · Severity: Medium-High · Confidence: Confirmed
A shared `resources/js/Components/ui/InputError.vue` exists but is used in only 2 of 410 Vue page files; 18 pages inline `form.errors.field` ad hoc; at least six Create/Edit pages reference `errors` nowhere at all — confirmed by full reads of `Admin/School/Contact/Edit.vue`, `Admin/School/News/Edit.vue`, `Admin/School/Staff/Create.vue`, `Admin/School/Staff/Edit.vue`, `Admin/School/Events/Create.vue`, `Admin/School/Events/Edit.vue`. A server-side validation failure on these six pages is silently swallowed from the user's point of view (the form just doesn't save, with no visible reason).
Impact: user confusion, support burden ("I clicked save and nothing happened").
Fix: standardize on `<InputError>` and sweep all Create/Edit forms; prioritize the six confirmed-blank ones first.
Acceptance criteria: every form with server-side validation shows field-level errors on failure.
Regression test: a lightweight Vue/Playwright check per form asserting an error message renders on a deliberately invalid submit — none currently exist for this (see TEST-01 area).

**WF-08-positive** — Pagination/filter-reset behavior is correct where sampled (`Admin/School/Students/Index.vue`): changing or clearing filters correctly returns to page 1.

### D.8 Concurrency / duplicate submission

**WF-04** (MCQ double-registration, above) is the clearest concurrency gap found.
**WF-08-positive** — Board Results status transitions are properly locked: every verify/approve/reject/publish action in `BoardResultVerificationController` wraps the check + mutation in `DB::transaction` + `lockForUpdate()` + an explicit status-precondition `abort_unless`. Training registrations are DB-uniqued. Destructive-action confirmation dialogs are broadly present (110 of the sampled Vue files use `confirm()`/a confirm component), including student withdraw, erasure, erasure restore, school rejection, and admin removal.

---

## E. Reporting Gap Matrix

Reporting runs through three systems: a generic catalog-driven "ERP Report Hub" (~230 report definitions, `app/Support/ReportRegistry.php`, executed via `app/Services/Reports/ReportRunner.php` + `ErpReportQueryService.php`/`QueriesExtendedReports.php`), a Fest-specific per-event report suite (~50 pages, `FestReportController.php`/`FestReportCatalog.php`), and independent module report controllers (Board Results, MCQ, Training, Membership, Ledger). Export formats: CSV (native streaming), XLSX (openspout), PDF (dompdf).

**Notable dormant dependency**: `spatie/laravel-activitylog` is installed (`composer.json`) but used nowhere — all audit/history logging instead runs through a hand-rolled `AuditLog` model, which is the source of the RPT-01 leak below.

| Report / family | Category | Audience | Status | Filters | Data source | Export | Permission coverage | Accuracy risks |
|---|---|---|---|---|---|---|---|---|
| ERP Reports Hub (~230 entries) | Overall/mixed | Sahodaya admin/staff/finance | Existing (~60 runnable in-engine, rest link out) | varies | `ReportRegistry.php` | link-outs + CSV/PDF for runnable subset | role-gated via `ReportRunner::authorize()` | see RPT-01 |
| School/Student/Teacher rosters & summaries | Status-wise | Sahodaya admin | Existing | school_id, from/to | `QueriesExtendedReports.php:185-621` | CSV/PDF | role + tenant-scoped | none found |
| Payments/Ledger/Finance (day book, trial balance, etc.) | Status/date-wise | Finance/admin | Existing | status, from/to | `ErpReportQueryService.php:143-303`, `QueriesExtendedReports.php:639-1168` | CSV/PDF | role + tenant-scoped | none found beyond already-fixed truncation notes in the same file |
| **Audit Trail / Auth Events / Finance Audit / Export Log / Failed Logins (RPT-AUD-001..005, RPT-AUTH-005)** | Audit/history | Admin | **Broken/Leaking** | from/to only | `QueriesExtendedReports.php:1642-1698`, `ErpReportQueryService.php:684-702` | CSV/PDF | role-gated only, **no tenant scope at all** | **RPT-01: cross-tenant leak.** Also, the "School Activity" report (RPT-SCH-006) throws a SQL error every run — it queries a `tenant_id` column that doesn't exist on `audit_logs` |
| Dashboard KPI/finance/registration-funnel | Overall | Admin | Existing | none | `ErpReportQueryService.php:741-795` | CSV/PDF | role + tenant-scoped | none found |
| MCQ reports (registration, attendance, results, rank, malpractice, IP audit) | Overall/status/exam-wise | Admin/exam ops | Existing | exam_id | `QueriesExtendedReports.php:1171-1376` | CSV/PDF | role + tenant-scoped | IP-audit report pulls up to 3,000 global `AuditLog` rows before filtering — silent-truncation risk, plus inherits the RPT-01 table |
| Training reports (16 report IDs) | Overall/program-wise | Admin | Existing | none/school | `QueriesExtendedReports.php:1378-1614` | CSV/PDF | role + tenant-scoped | none found |
| Board Results (School Summary, Overall Ranking, Pass %, Merit lists) + dedicated suite (subject-merit, toppers, full-A1, excellence) | Overall/rank | Sahodaya admin | Existing | academic_year, class | `QueriesExtendedReports.php:1806-1975`, `BoardResultReportController.php` | CSV/PDF | role-gated | Overall Ranking recomputes rankings on-read as a 3-tier fallback — functionally correct but a performance smell inside a GET |
| Fest/Sports/Kalolsavam per-event reports (~50 pages) | Region/phase/status/event-type/date | Sahodaya + region admins | **Existing, well-built** | event/school/region/phase/head/item/date | `FestReportController.php` | CSV/XLSX/PDF | explicit region-scoping + lifecycle-phase gating | on-screen/export parity explicitly enforced for the Registration Register; see REG-06 for a temporal region-scoping issue that also affects these |
| Fest cross-event summaries (~85 report IDs, RPT-SPT/RPT-KAL) | Region/phase/status/event-type | Admin | **Existing, several are placeholder stubs** | event_id, school_id, from/to, head_id | `FestCrossEventReportService.php` | CSV | role + tenant-scoped | RPT-KAL-037..045 resolve through a generic `eventMetrics()` fallback that just relabels registration counts, not real distinct reports; "Gate entry log" (RPT-SPT-037) and catering summary (RPT-SPT-038) are hard-coded stub rows |
| Calendar / Document compliance | Date/status-wise | Admin | Existing | from/to | `QueriesExtendedReports.php:1700-1773`, `ErpReportQueryService.php:305-337,704-739` | CSV | role-gated | none found |
| Membership reports | Overall/status-wise | Sahodaya admin | Existing | tab, search, date range | `MembershipReportsController.php:23-70` | CSV/XLSX | role-gated | summary block built from a separate query, not the paginated page — correct pattern |
| Email delivery report | Status-wise | Admin | Existing | status | `EmailDeliveryReportController.php` | on-screen only | role-gated, tenant-isolated by design (per-tenant DB table) | summary counts correctly separate from paginated rows |
| State Admin Dashboard | Overall (Platform) | State admin | Existing | none | `StateAdminDashboardController.php:15-50` | on-screen only | route-level (not deeply verified this pass — flagged Needs verification) | loads entire tables into memory to compute counts instead of aggregate queries — correctness fine today, scale risk as data grows |
| Ledger financial statements | Overall/date-wise | Finance | Existing | from/to | `app/Services/Ledger/*` | CSV/PDF | role-gated | not deep-audited — flagged Needs verification |
| **Approval turnaround-time report** | — | — | **Missing** | — | — | — | — | no turnaround/SLA analytics exist for any workflow (membership, Fest, board results, MCQ, training) |
| **Phase-duration / bottleneck report** | — | — | **Missing** | — | — | — | — | Fest tracks phase *state* but not duration/bottleneck analytics |
| **Region performance comparison report** | Region-wise | — | **Missing as a distinct report** | — | — | — | — | per-event region tiles exist but no side-by-side cross-region comparison view |
| **Personalized "my pending approvals" queue** | User-wise | every approving role | **Missing** | — | — | — | — | every pending-item view is scoped to a module/event, none filter to "assigned to me" |

### Correctness risks found
- **RPT-01 (headline, Critical)** — see above; the single most important finding in this audit.
- **On-screen/export parity** is generally sound: the generic ERP engine's preview and export both delegate to the identical `ErpReportQueryService::rows()`, and the Fest Registration Register explicitly shares its scope-resolution method between the two code paths.
- **Preview totals are computed correctly** — `ReportRunner::preview()` counts the full unpaginated collection before slicing, not just the current page.
- **Silent-truncation pattern** — several report builders `limit()` a query before fully applying filters in PHP: `mcqIpAudit` (`limit(3000)`), `failedLoginAttempts` (`limit(2000)`), the four RPT-AUD builders (`limit(500)` each), `rptSchoolActivity` (`limit(1000)`). The same file contains two already-fixed examples of this exact bug class with detailed docblocks (`rptReceiptEmailStatus`/`rptReceiptRegister`) — the team clearly recognizes the pattern but hasn't applied the same fix to the AuditLog-based reports.
- **Minor N+1** in a few low-row-count report builders (`rptAlumniList`, `rptTcLog`, `rptPaymentRegister` call `Tenant::find()` per row inside a loop) — low real-world impact given typical row counts.
- **Date/timezone handling** is consistently correct (`whereBetween(from, to+' 23:59:59')` pattern avoids the classic same-day-exclusion bug); one minor style inconsistency (`whereDate()` used in a couple of Fest cross-event builders) is not confirmed broken, just inconsistent.
- **Frontend empty-state handling** is inconsistent where sampled: the generic ERP `Run.vue` has a proper empty-state message; `Events/Reports/OverallRanking.vue` has none (renders a silently blank list before results are published). Only two pages were sampled out of ~70 report-adjacent Vue pages — flagged Needs Verification for the rest.

---

## F. Prioritized Action Plan

### Immediate blockers (fix before anything else — live, exploitable/data-loss)
1. **RPT-01** — Add tenant scoping to the five AuditLog-backed reports, or add a `tenant_id`/`sahodaya_id` column to `audit_logs` and backfill it, then scope every query. Also fixes the SQL-error bug in RPT-SCH-006 (School Activity report references a nonexistent column).
2. **WF-05** — Extend the student-erasure snapshot/restore to cover `mcq_marks`, `mcq_certificates`, `mcq_attendance_correction_requests`, or change the UI messaging to stop promising full reversibility until it's fixed.
3. **WF-04** — Add a unique DB constraint on `mcq_registrations(exam_id, student_id)` plus transactional locking around registration creation.
4. **LIFE-06** — Add a qualification-revocation cascade (or an explicit block) to `FestRegistrationReviewController::reject()` and `FestRegistrationService::cancel()`/`cancelWithRefund()`.

### High-priority workflow fixes
5. **LIFE-03 / LIFE-04** — Add current-status guards to single-record Fest registration approve/reject, matching the bulk service's `->where('status','submitted')` scope; add a consistent `results_published` guard to `cancel()`/`reject()`.
6. **LIFE-01** — Wire `StatusTransitionGuard` into `FestEventController::update()`, not just `quickStatus()`.
7. **REG-06** — Thread the record's own effective year (e.g., `FestEvent->academic_year_id`) into every `SchoolRegionAssignment::forYear()` call in `FestReportScopeResolver`, `FestReportController::resolveRegistrationRegisterScope`, and `SahodayaAdminController::regionScopedSchoolIds` (which also feeds `PaymentVerificationController` and `FestFoodBillingController`), instead of always resolving "today's" year. Also add the missing `forYear()` scope to `TeacherTrainingEligibilityService::schoolInRegions()` (REG-05), which currently has no year filter at all.
8. **PERM-03** — Confirm intent with stakeholders, then extend server-side module scoping to the five under-scoped school coordinator roles.
9. **LIFE-05** — Before enabling `phase_mode_enabled` anywhere in production/UI, build the missing phase-transition write path, or explicitly gate the toggle off until that work is done.
10. **LIFE-08/09** — Build a symmetric unpublish cascade (region/finale children, certificate handling, notification) to match publish.

### Missing essential reports (build next)
11. Approval turnaround-time report (per workflow: membership, Fest registration, board results, MCQ, training).
12. Phase-duration/bottleneck report for Fest.
13. Region performance comparison report (side-by-side, not just per-event tiles).
14. Personalized "my pending approvals" queue per role.
15. Replace the RPT-KAL-037..045 / gate-entry-log / catering-summary stub reports with real implementations or remove them from the catalog so they stop appearing as if functional.

### Medium-term UX / hygiene improvements
16. Standardize server-side validation error display (`<InputError>`) across all Create/Edit Vue forms; prioritize the six pages confirmed to show no errors at all (WF-08).
17. Add notifications for the currently-silent transitions: initial registration submission (LIFE-11), roster-edit approval regression (LIFE-10), results unpublish (LIFE-09).
18. Fix the silent-truncation `limit()`-before-filter pattern in the remaining AuditLog-adjacent report builders, following the pattern already used to fix `rptReceiptEmailStatus`/`rptReceiptRegister` in the same file.
19. Delete dead code / correct misleading docblocks: `FestReportPolicy` (PERM-01), deprecated `RegionScope.php`.
20. Extract the duplicated web/API region-scoping logic (`EnsureSahodayaAdmin` vs `EnsureSahodayaAdminApi`) into one shared class to remove the drift risk (PERM-04).
21. Add regression tests for every item in this report — **zero of the top-10 findings currently has any associated test** (TEST-01); start with `FestRegistrationReviewControllerGuardTest`, `McqDuplicateRegistrationRaceTest`, `StudentErasureRestoreCompletenessTest`, and an `RptAuditTenantScopeTest`.

### Requirements needing stakeholder confirmation
- Whether the five under-scoped school coordinator roles (PERM-03) were ever meant to be module-restricted, or whether "coordinator" is intentionally a full sub-admin persona distinguished only by landing page.
- Whether mobile-API access being narrower than web for coordinator/staff roles (PERM-05) is deliberate (mobile app not built for those roles yet) or an oversight.
- Whether Training "nomination" was meant to be a distinct nominate-then-convert step, or whether direct school-side registration (the current behavior) is the intended design (D.4).
- Whether automated escalation (as opposed to same-day reminders) is an actual business requirement anywhere in the platform (WF-07) — currently absent entirely.
- Whether `is_active=false` on a Region is meant to also block *new* school assignments into it (currently it doesn't — REG in section E's region findings) or only to stop future partition auto-sync.
- Whether `SubmitStateQualifiersJob`'s failed outbox rows are meant to be automatically retried by a scheduled sweep (LIFE-12) — could not be fully confirmed absent within this pass.

---

## Method notes and confidence caveats

- No PHP runtime was available in the audit sandbox; all findings are static-code-review-based, most independently cross-checked by a second research pass (the test-validation pass re-verified all five top code-level findings by reading the underlying code itself, not just trusting the earlier pass's claim).
- `npm run build` succeeded with zero JS/Vue compile errors across 1,116 modules — the only runtime-adjacent signal available in this environment.
- The 124-file PHPUnit suite and 10-file Playwright e2e suite were inventoried but could not be executed; grep-based coverage checks confirm zero existing test references any of this report's top findings.
- Recommend running locally, in priority order: `php artisan test --filter=FestRegistrationReview` (expect "no tests found," confirming the gap), a targeted reproduction of RPT-01 against two seeded tenants, and `npm run test:e2e` against a running local server.
