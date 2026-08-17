# Event Workflow and Reports Fix Plan

**Created:** 14 August 2026  
**Source audit:** `EVENT_WORKFLOW_AND_REPORTS_AUDIT_2026_08_14.md`  
**Goal:** Secure event data, repair workflow correctness, and replace the oversized fest report catalog with a small, capability-aware reporting system.

## Working rules

- Complete milestones in order. Report reorganization must not hide unresolved authorization defects.
- Every school-facing dataset must be tested with at least two schools.
- Report applicability and authorization must be enforced by the server; UI filtering is only a usability layer.
- A business report is one concept. PDF, Excel, CSV, and print are output formats, not separate report cards.
- Operational documents belong in the workflow that uses them, not in the main Reports hub.
- Do not remove old URLs until callers are identified and a redirect, replacement, or explicit deprecation response is in place.

## Milestone 0 — Baseline and test reliability

**Exit condition:** The affected suites run predictably, and test fixtures can be reused by later security and lifecycle tests.

- [ ] Set an effective PHPUnit memory limit for child processes so `php artisan test` does not fail at 128 MB.
- [ ] Force a fake/local storage disk in certificate unit tests so AWS SDK initialization is not required.
- [ ] Make notification-template seeders and test factories idempotent by slug.
- [ ] Eliminate duplicate-slug errors for:
  - [ ] `fest.results.unpublished`
  - [ ] `fest.registration.needs_reapproval`
- [ ] Add shared test helpers that create:
  - [ ] One Sahodaya and two member schools
  - [ ] One event for every fixed fest type
  - [ ] A partitioned event with two regions
  - [ ] Default and named competition phases
  - [ ] Registrations, fees, marks, results, and certificates belonging to both schools
- [ ] Record the current expected failures before implementation and remove each expectation as its fix lands.

## Milestone 1 — Close school data leaks

**Priority:** P0  
**Exit condition:** A school administrator cannot retrieve another school's identifiers from any report route, export, preview, or generated document.

### 1.1 Define an enforceable report audience

- [ ] Introduce an explicit report audience/scope value such as `sahodaya`, `school`, `region`, or `combined`.
- [ ] Create a dedicated school-safe report catalog or server-side school allowlist.
- [ ] Require a non-null scope object when a school report/export is dispatched.
- [ ] Make dispatch fail closed when an exporter does not support the requested audience.
- [ ] Prevent request parameters from overriding the authenticated school scope.
- [ ] Return `403` for a known report that is not authorized for the audience and `404` for an unavailable report/event combination.

### 1.2 Repair or remove unsafe exporters

- [ ] Scope registration exports to the authenticated school.
- [ ] Scope results exports to the authenticated school where school access is allowed.
- [ ] Scope fee summary and fee-line exports to the authenticated school.
- [ ] Scope student-event registration exports to the authenticated school.
- [ ] Audit and explicitly scope or prohibit:
  - [ ] Green-room list
  - [ ] Judge sheet
  - [ ] Mark-entry sheet
  - [ ] Promotion/qualifier list
  - [ ] Certificate counts
  - [ ] Catering list
  - [ ] Volunteer roster
  - [ ] Audit-log export
  - [ ] Student directory
- [ ] Remove unsafe school UI links until their exporters pass isolation tests.

### 1.3 Add security regression tests

- [ ] Enumerate every school-visible report/export ID from the server catalog.
- [ ] For every allowed export, assert School A output contains School A data.
- [ ] For every allowed export, assert School A output does not contain School B:
  - [ ] School ID or name
  - [ ] Student/teacher ID or name
  - [ ] Registration number
  - [ ] Fee, invoice, or transaction reference
  - [ ] Marks, result, certificate, or operational assignment
- [ ] Test direct URL requests, not only links rendered by the UI.
- [ ] Test CSV, Excel, PDF, ZIP, preview, and print responses where supported.

## Milestone 2 — Repair routing and lifecycle correctness

**Priority:** P0/P1  
**Exit condition:** Event type, phase, publication state, and academic-year rules are consistently enforced by every read and write path.

### 2.1 Bind program routes to event types

- [ ] Add a shared `assertProgramMatchesEvent()` rule.
- [ ] Apply it to every program-prefixed school event route.
- [ ] Apply it to report hubs, previews, downloads, and generic export dispatch.
- [ ] Test that a Kalotsav event cannot be accessed through Sports, Kids, Teacher, English, Science, or Custom URLs.
- [ ] Test the valid program/event combinations continue to work.

### 2.2 Fix named competition phases

- [ ] Prevent `FestEventPhaseService::updatePhase()` from writing `NULL` to `is_default`.
- [ ] Normalize boolean defaults when phase models are created or refreshed.
- [ ] Make phase quick-status and full-update paths return the same effective lifecycle.
- [ ] Wire `allowRegistrationForItem()` into:
  - [ ] School registration
  - [ ] Admin/manual registration
  - [ ] Bulk registration/import
  - [ ] Registration approval/reapproval
- [ ] Wire `allowMarkEntryForItem()` into:
  - [ ] Judge mark entry
  - [ ] Coordinator/admin mark entry
  - [ ] Bulk mark upload
  - [ ] Manual correction/re-entry
- [ ] Reject registration or marks when the item's named phase is closed or locked.
- [ ] Add route-level tests for each writer, not only service-unit tests.

### 2.3 Unify report lifecycle gates

- [ ] Extract one lifecycle authorization service used by Sahodaya and school report controllers.
- [ ] Define which concepts are available in draft, registration, scheduling, judging, completed, and published states.
- [ ] Block result/ranking/certificate reports until their required publication state.
- [ ] Filter the school report hub by allowed phase.
- [ ] Enforce the same rule on direct preview/export URLs.

### 2.4 Repair regional workflows

- [ ] Diagnose why `RPT-FST-009` omits expected child-region rows.
- [ ] Correct region performance aggregation for regions with results and regions with zero results.
- [ ] Choose one canonical current academic-year source for region assignments.
- [ ] Apply it consistently to assignment writes, fest reports, and training eligibility.
- [ ] Define whether historical assignments may grant current training eligibility; default to no.
- [ ] Add tests for current-year, past-year, missing-year, and reassigned-school cases.

## Milestone 3 — Build one report registry

**Priority:** P1  
**Exit condition:** PHP dispatch, school UI, Sahodaya UI, applicability, permissions, and formats all derive from one authoritative definition.

### 3.1 Registry contract

- [ ] Give every report concept a stable ID and define:
  - [ ] Name and short purpose
  - [ ] Audience/roles
  - [ ] Dataset scope
  - [ ] Supported event types or capability predicate
  - [ ] Minimum lifecycle state
  - [ ] Required source data/readiness rule
  - [ ] Interactive destination, if any
  - [ ] Supported formats
  - [ ] Owning workflow
  - [ ] Deprecation/replacement metadata
- [ ] Generate or serialize frontend definitions from the authoritative registry.
- [ ] Remove manually duplicated PHP/JavaScript category and preview maps.
- [ ] Validate registry entries during automated tests.
- [ ] Reject duplicate concept IDs and invalid route/format combinations.

### 3.2 Capability predicates

- [ ] Define reusable capabilities such as:
  - [ ] `uses_houses`
  - [ ] `uses_age_groups`
  - [ ] `uses_areas`
  - [ ] `uses_regions`
  - [ ] `uses_teams`
  - [ ] `uses_athletic_records`
  - [ ] `uses_catering`
  - [ ] `has_linked_qualifier_level`
  - [ ] `participants_are_teachers`
- [ ] Derive capabilities from event configuration rather than only the event-type string where possible.
- [ ] Enforce capability checks in controllers/services and in catalog rendering.
- [ ] Explain unavailable reports in the UI only when that explanation helps the user configure missing data.

## Milestone 4 — Replace the fest report hub

**Priority:** P1  
**Exit condition:** The main hub contains only decision-support reports, grouped by workflow, with formats selected inside each concept.

### 4.1 Keep these core report concepts

- [ ] **Registration and approval register** — status, validation, rejection, and reapproval.
- [ ] **Participant roster** — item/person/team view with filters.
- [ ] **Registration counts and capacity** — item/category/school totals and limit usage.
- [ ] **Fee reconciliation** — assessed, paid, pending, rejected, waived, and balance.
- [ ] **Schedule** — venue/date/item views with PDF/Excel/CSV formats.
- [ ] **Clash queue** — unresolved participant/venue/judge clashes linked to correction actions.
- [ ] **Attendance** — live attendance status and absence exceptions.
- [ ] **Assignment and numbering readiness** — missing chest numbers, judges, venues, or staff.
- [ ] **Mark-entry completeness** — pending, partial, locked, and completed items.
- [ ] **Item results** — published item-level outcomes.
- [ ] **School ranking and performance** — one ranking concept with optional breakdowns.
- [ ] **Qualifiers and promotions** — only when a linked next level exists.
- [ ] **Certificate status** — eligibility, generated, issued, failed, and reissue.
- [ ] **Audit trail** — restricted administrative investigation view.

### 4.2 Conditional reports

- [ ] Show house/championship/medal reports only when houses are configured.
- [ ] Show athletic-record reports only for Sports with record tracking enabled.
- [ ] Show age-group reports only when age groups are configured.
- [ ] Show area-wise reports only when competition areas are configured.
- [ ] Show teacher-participant reports only for Teacher Fest.
- [ ] Show team-roster reports only for team/group items.
- [ ] Show catering reports only when catering is enabled.
- [ ] Show region comparison only for partitioned events.
- [ ] Show qualifiers only when the event has a linked destination level.

### 4.3 Move operational outputs to their owning workflows

- [ ] Move ID/admit cards to Registration/Participants.
- [ ] Move blank attendance sheets to Attendance.
- [ ] Move judge sheets and mark-entry sheets to Judges/Scoring.
- [ ] Move performance order and stage sheets to Schedule/Stage operations.
- [ ] Move green-room lists to Stage/Green-room operations.
- [ ] Move volunteer and staff rosters to Staffing.
- [ ] Move team sheets to Team registration.
- [ ] Move day bulletins to Schedule/Publishing.
- [ ] Move certificate-count summaries to Certificates.
- [ ] Keep the audit trail under restricted Administration rather than the general report gallery.

### 4.4 Merge or retire redundant entries

- [ ] Merge `cumulative` into School ranking/performance.
- [ ] Merge `sahodaya-ranking` into School ranking/performance.
- [ ] Merge `mark-entered-summary` and `mark-entry-status` into Mark-entry completeness.
- [ ] Merge schedule CSV/PDF cards into one Schedule concept.
- [ ] Merge clash file-format cards into one Clash queue concept.
- [ ] Merge promotion/qualifier file-format cards into one Qualifiers concept.
- [ ] Merge registration variants into configurable views of Registration and Participant roster.
- [ ] Merge attendance pivots into configurable Attendance views.
- [ ] Remove the all-member-schools `students` export from event reports.
- [ ] Rename `Sports Fee Breakdown` to a neutral Fee reconciliation concept and gate sport-only fields.
- [ ] Add redirects or clear deprecation responses for retired report URLs.

## Milestone 5 — Complete event-type feature gaps

**Priority:** P1/P2  
**Exit condition:** Each supported event type exposes all applicable workflows and no inapplicable features.

### Sports

- [ ] Fix or remove the Sahodaya report tile that links to a school Entry Form using the Sahodaya tenant ID.
- [ ] Add applicable navigation for attendance, staffing/judges, appeals, event staff, leaderboard, athletic records, food coupons, invoices, and event finance.
- [ ] Confirm each restored navigation item has authorization and feature-capability checks.
- [ ] Remove legacy/non-sports head assumptions from Sports pages and exports.

### Custom and dynamic competition types

- [ ] Add `custom` to event-scoped school route detection.
- [ ] Generate route detection from the program registry instead of copied regex lists.
- [ ] Decide whether database-defined types are school-facing.
- [ ] If school-facing, add school navigation, routes, registration, reports, permissions, and tests for a newly created type.
- [ ] If Sahodaya-only, prevent misleading publication to schools and label the limitation in the UI.

### Teacher Fest

- [ ] Replace student-only wording and assumptions with participant terminology.
- [ ] Verify teacher identity, eligibility, roster, fee, result, and certificate exports.

### Kalotsav, Kids, English, and Science

- [ ] Verify report capabilities against actual configuration rather than inherited catalog defaults.
- [ ] Confirm championship, grades, team/individual, age, and area reports appear only when configured.

### Generic calendar events

- [ ] Rename the feature in navigation/UI to `Calendar events` or `School announcements`.
- [ ] Keep it separate from competition registration and reporting unless a future product decision explicitly integrates it.

### MCQ/Talent Search

- [ ] Keep the report catalog independent and compact.
- [ ] Move Grade bands to exam configuration.
- [ ] Keep Registration, Fees, Attendance, Marks pending, Results, School performance, Toppers/qualifiers, and online-session status as the principal concepts.
- [ ] Show online-session status only for online exams.

### Training

- [ ] Keep reports inside the training program workspace rather than adopting the fest report hub.
- [ ] Retain registration roster, attendance outputs, QR registration, and certificate ZIP.
- [ ] Complete regional eligibility regression tests before changing report presentation.

## Milestone 6 — User experience, migration, and observability

**Exit condition:** Users can find the right report by task, old links have controlled outcomes, and usage data supports later pruning.

- [ ] Group report concepts by Registration, Finance, Operations, Results, Certificates, and Administration.
- [ ] Show a short question/purpose on each concept instead of implementation-oriented names.
- [ ] Put filters and format selection inside the report screen.
- [ ] Display a meaningful empty state with the missing prerequisite and a link to fix it.
- [ ] Add migration mappings for bookmarks and emailed links.
- [ ] Log report concept ID, event, audience, actor role, filters, format, row count, and success/failure.
- [ ] Do not log sensitive report contents.
- [ ] Review usage after one complete event cycle.
- [ ] Archive zero-use reports unless they are required for compliance or a documented operational process.

## Automated quality gates

- [ ] Catalog contract test: every report ID is unique and dispatchable.
- [ ] Route contract test: every declared interactive destination exists.
- [ ] Authorization matrix test: audience and role restrictions are enforced server-side.
- [ ] Two-school isolation test: no school report leaks another school's data.
- [ ] Applicability test: every fixed event type gets only valid concepts.
- [ ] Capability test: conditional concepts appear and disappear with configuration.
- [ ] Lifecycle test: draft, open, closed, completed, and published states are enforced.
- [ ] Phase test: default and named-phase restrictions cover every registration and scoring writer.
- [ ] Format parity test: formats of the same concept use the same scoped dataset.
- [ ] Frontend/server parity test: UI definitions come from or exactly match the server registry.
- [ ] Empty-data test: unavailable prerequisites return a useful state, not a misleading blank file.
- [ ] Build gate: `npm run build` passes.
- [ ] Focused event/report PHPUnit suite passes with no failures, errors, or risky tests.
- [ ] Full PHPUnit suite passes using the documented command and memory settings.

## Release checkpoints

### Release A — Security hotfix

- [ ] School-safe allowlist and mandatory scoping deployed.
- [ ] Unsafe links removed or fixed.
- [ ] Two-school isolation suite green.
- [ ] Access logs reviewed for generic export endpoints.

### Release B — Workflow correctness

- [ ] Program/event binding deployed.
- [ ] Named phases and item-aware gates deployed.
- [ ] Report lifecycle parity deployed.
- [ ] Regional and notification regressions fixed.

### Release C — Report registry and new hub

- [ ] Single registry deployed behind a feature flag if necessary.
- [ ] Fourteen core concepts available.
- [ ] Conditional reports capability-gated.
- [ ] Operational outputs relocated.
- [ ] Old report URLs redirected or explicitly deprecated.

### Release D — Event-type completion and cleanup

- [ ] Sports and Custom navigation gaps closed.
- [ ] Dynamic-type product decision implemented.
- [ ] Teacher terminology corrected.
- [ ] MCQ and Training catalogs confirmed independent.
- [ ] Usage review and final obsolete-report removal completed.

## Final definition of done

- [ ] No school-facing response can contain another school's protected event data.
- [ ] Every displayed report is applicable, authorized, lifecycle-valid, and backed by required data.
- [ ] Users see one entry per business question and choose the file format inside it.
- [ ] Operational documents are available at the point of work.
- [ ] All registration and mark-entry paths honor named phases.
- [ ] All supported event types have consistent navigation and workflows.
- [ ] The focused and full automated suites pass under documented settings.
- [ ] Report telemetry is available for evidence-based future cleanup.
