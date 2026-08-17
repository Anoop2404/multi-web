# MCS Kalotsav — Four-Phase Completion Plan

**Status:** Implemented and verified; tenant rollout/UAT remains  
**Prepared:** 16 August 2026  
**Scope:** Four conduct phases, two registration/payment levels, independent school region choices for two phases, cumulative scoring, all operational features, all reports and exports  
**Compatibility rule:** Existing standard Sahodaya Kalotsav events must continue to use their current event and fee flow unless this mode is explicitly enabled.

## Implementation record — 16 August 2026

The application work in this plan is now implemented behind the explicit
`phased_regional_billing` workflow mode. It includes the two registration/payment
batches, four conduct phases, independent phase-region selection, operational-leaf
routing, two invoices, phase-aware lifecycle and publication, cumulative scoring,
report/export scoping, phase-region staff scope, audit checks, and school/admin UI.

Automated coverage is in
`tests/Feature/Events/FestPhasedRegionalBillingWorkflowTest.php`. The implementation
also passes the existing phase lifecycle, mutation-invariant, school-report boundary,
notification, and qualification regression suites, plus the production frontend build.

The remaining rollout work is tenant data/configuration rather than application code:

- run tenant migrations;
- open the event's Phases page and configure the MCS workflow;
- approve/assign the state item catalogue to the four phases;
- enter the final allowed regions, venues, dates, item fees, appeal rules, and qualification rules;
- run `fest:audit-event-topology --sahodaya=<tenant> --event=<root-id>` and complete UAT before opening school registration.

## 1. Required outcome

MCS Kalotsav must operate as one root event with four conduct phases and two payment levels.

| Phase | Conduct mode | School region choice | Payment level |
|---|---|---:|---|
| Digi Fest | Common/non-regional | No | Level 1 |
| Off Stage | Regional | Yes | Level 1 |
| Sargadhara | Regional | Yes, independently of Off Stage | Level 2 |
| District Kalotsav | Common/non-regional | No | Level 2 |

The required invoices are:

- **Level 1 invoice:** school registration fee of Rs. 4,000 once, plus Digi Fest fees, plus Off Stage fees.
- **Level 2 invoice:** Sargadhara fees plus District Kalotsav fees.

The required scoreboards are:

- Region scoreboard for each regional phase.
- Combined scoreboard for each phase.
- Running overall school scoreboard containing one contribution column per published phase.
- Final overall scoreboard containing the sum of awarded points from all published phases.

Raw marks remain item-level judging data. Championship totals must use awarded points and must never add raw marks across dissimilar items.

## 2. Binding architecture decisions

### 2.1 Keep three concepts separate

The implementation must not use one table or status to represent all three of these concepts:

1. **Root event:** MCS Kalotsav 2026.
2. **Conduct phase:** Digi Fest, Off Stage, Sargadhara, District Kalotsav.
3. **Registration/payment batch:** Level 1 or Level 2.

Each conduct phase belongs to exactly one payment batch. A payment batch can contain multiple conduct phases.

### 2.2 Operational leaves contain competition data

The root event owns shared configuration and combined views. Registrations, schedules, attendance, marks and operational results belong to explicit leaf events.

```text
MCS Kalotsav 2026 (root)
  Digi Fest (common leaf)
  Off Stage - Region A (regional leaf)
  Off Stage - Region B (regional leaf)
  ...
  Sargadhara - Region A (regional leaf)
  Sargadhara - Region B (regional leaf)
  ...
  District Kalotsav (common leaf)
```

Use flat operational leaves under the root. This fits the current report engine more safely than introducing a root -> phase -> region nested tree.

### 2.3 Region selection is event-and-phase-specific

A Sahodaya's permanent or annual school region may be shown as a suggested default. It is not the authoritative MCS selection.

The authoritative selection is unique for:

```text
(root_event_id, conduct_phase_id, school_id)
```

Consequently, a school can select Tirur for Off Stage and Manjeri for Sargadhara.

### 2.4 Standard events remain standard

Add an explicit capability or mode, for example `phased_regional_billing`. Do not infer this mode merely because a Sahodaya has regions. Existing single-event and ordinary fee behavior must remain the default.

## 3. Delivery sequence

The work is divided into twelve implementation milestones. A milestone is complete only when its listed acceptance criteria and automated tests pass.

## Milestone 0 — Freeze the MCS business contract

### Work

- Confirm the allowed regions for Off Stage.
- Confirm the allowed regions for Sargadhara; they need not be identical.
- Approve the final state-item-to-phase assignment list.
- Approve the Level 1 and Level 2 fee formulas, including concessions, late fees, refunds and cancellation rules.
- Confirm when a school region choice becomes locked.
- Confirm whether an administrator may change a locked selection and how registrations move afterward.
- Confirm phase-specific appeal, certificate and qualification rules.
- Confirm whether a phase can publish results region-by-region or only after every region is complete.

### Deliverables

- Signed item allocation sheet.
- Signed fee matrix.
- Signed region and publication rules.
- One canonical fixture school that chooses different regions for Off Stage and Sargadhara.

### Exit criteria

No unresolved rule can change the database keys, invoice grouping or score aggregation policy.

## Milestone 1 — Introduce the canonical data model

### Work

Add or normalize the following concepts:

#### Registration/payment batches

Create a model such as `FestRegistrationBatch` with:

```text
id
event_id
code                 LEVEL_1 | LEVEL_2
name
registration_open_at
registration_close_at
payment_due_at
school_base_fee
invoice_prefix
status
```

#### Conduct phases

Extend `FestEventPhase` with:

```text
registration_batch_id
code                 DIGI | OFF_STAGE | SARGADHARA | DISTRICT
is_regional
result_publish_mode
food_cutoff_at
appeal_open_at
appeal_close_at
```

#### Allowed phase regions

Create `fest_phase_regions`:

```text
phase_id
region_id
venue
conduct_start_at
conduct_end_at
capacity
enabled
```

#### School phase-region choices

Create `fest_school_phase_region_selections`:

```text
event_id
phase_id
school_id
region_id
selected_at
selected_by
locked_at
changed_at
changed_by
change_reason
```

Add a unique constraint on `(event_id, phase_id, school_id)` and validate that the selected region is enabled for that phase.

#### Operational leaf identity

Each operational child event must retain:

```text
parent_event_id
source_phase_id
region_id nullable
registration_batch_id
partition_role
partition_key
```

Add uniqueness preventing duplicate leaves for the same `(parent_event_id, source_phase_id, region_id)`.

### Compatibility

- Existing events with no new mode continue using existing columns and services.
- New columns are nullable during deployment and become mandatory only for MCS-mode events.
- Do not rewrite historical invoices or results during the schema deployment.

### Exit criteria

- Two batches and four phases can be created.
- Regional phases can have different allowed-region lists.
- One school can store two different phase-region selections.
- Duplicate selections and invalid regions are rejected by the database and service layer.

## Milestone 2 — Build topology and item synchronization

### Work

- Create an idempotent topology synchronizer that creates one common leaf for a non-regional phase and one leaf per enabled region for a regional phase.
- Assign every enabled root item to exactly one conduct phase.
- Copy each item only to the operational leaves belonging to that phase.
- Preserve `source_item_id` and stable `source_phase_id` on every copy.
- Synchronize later edits to names, categories, limits, judging criteria, points and eligibility without replacing IDs that already have registrations or results.
- Detect and report orphan items, duplicate copies and phase mismatches.
- Prevent deleting a phase, region or leaf that has operational data; require an explicit migration workflow.

### Exit criteria

- Re-running synchronization produces no duplicate leaves or items.
- An Off Stage item never appears in Sargadhara or District leaves.
- Child phase identity does not rely only on matching names.
- Existing registrations and result references survive a safe item update.

## Milestone 3 — Implement independent school region selection

### Work

- Add a school workflow listing only the regional phases currently open for selection.
- Show allowed regions, venue, date and capacity for each phase.
- Permit independent Off Stage and Sargadhara selections.
- Apply the annual school region only as a pre-filled suggestion when permitted.
- Lock a selection when the first registration for that phase is submitted, or at the committee-approved cutoff.
- Add an audited administrator override.
- When an override moves a school after registrations exist, migrate registrations transactionally to the new leaf and revalidate quotas, schedules, numbers, payments and conflicts.
- Expose selection status to full admins and only authorized phase-region staff.

### Exit criteria

- The fixture school can choose different regions for the two regional phases.
- Changing one phase does not change the other.
- Unauthorized staff cannot view or change another region's selection.
- Locked choices cannot be silently overwritten.

## Milestone 4 — Make registration and routing phase-aware

### Work

- Derive the conduct phase from the selected item; never trust a client-supplied phase alone.
- Resolve the exact operational leaf from phase plus school selection.
- Require a valid region selection before a regional registration.
- Enforce the payment batch's registration window and the phase's operational rules.
- Apply the same routing and validation to:
  - School form registration.
  - Admin registration.
  - Review/approval flows.
  - Spreadsheet import.
  - API import.
  - Bulk update and cancellation.
- Re-run participant limits, team rules, category eligibility and cross-item constraints after routing.
- Make registration counts at the root aggregated views, while the operational leaf remains the source of truth.

### Exit criteria

- Every registration resolves to exactly one valid leaf.
- Imports cannot bypass batch deadlines, phase deadlines or region selection.
- Replaying an import is idempotent.
- The root contains no accidental operational registrations.

## Milestone 5 — Replace per-phase billing with two batch invoices

### Work

- Generate invoices by `(event_id, school_id, registration_batch_id)`.
- Calculate Level 1's Rs. 4,000 base fee exactly once.
- Add item/student/team fees from both Level 1 phases.
- Calculate Level 2 from Sargadhara and District registrations.
- Include registrations from all authorized regional leaves in the correct batch.
- Store immutable invoice line snapshots containing source phase, item and registration references.
- Define recalculation behavior before payment and adjustment/credit-note behavior after payment.
- Separate conduct status from invoice/payment status.
- Update receipts, payment approval, outstanding reports, finance dashboard and exports.
- Preserve the existing fee calculator for events outside the new mode.

### Exit criteria

- Exactly two invoice records are produced for an eligible school.
- Level 1 charges the school base fee once, not once per phase or region.
- A school choosing different regions still receives one invoice per batch.
- Paid invoices cannot be silently rewritten.
- Finance totals reconcile with invoice-line totals and scoped registrations.

## Milestone 6 — Apply phase lifecycle to every operational feature

### Work

Create one effective lifecycle service used consistently by UI actions, controllers, imports, jobs and APIs.

Apply it to:

- Registration open/close.
- Region-choice cutoff.
- Food ordering cutoff.
- Schedule draft and publication.
- Attendance and call sheets.
- Chest/admit/participant numbering.
- Mark-entry open/close.
- Result verification and publication.
- Appeal open/close.
- Certificate eligibility.
- State qualification/promotion.
- Notifications and reminders.

The lifecycle service must resolve the root event, payment batch, source phase, operational leaf and actor permissions before allowing an action.

### Exit criteria

- The same action has the same result from the browser, API, spreadsheet import and background job.
- Closing Off Stage does not close Sargadhara.
- Publishing one phase does not expose unpublished phases.
- Standard events continue using their current event-level lifecycle.

## Milestone 7 — Correct scoring, publication and qualification

### Work

- Calculate item results inside operational leaves.
- Store or derive awarded points with a traceable source result identity.
- Build phase scoreboards from the appropriate leaves using `source_phase_id`.
- Provide one board per region and a combined board per phase.
- Build the root overall board by school identity, summing only awarded points from published phases.
- Show phase contribution columns and total points.
- Define tie-breaking centrally and reuse it in screen, PDF and spreadsheet outputs.
- Prevent double counting when the same source item exists in copied leaves or is promoted to a finale.
- Run certificates and qualification only after the configured phase/region publication condition is satisfied.
- Add idempotency keys to qualification so repeated jobs do not create duplicates.

### Exit criteria

- Regional scoreboards include only their own leaf.
- A combined phase board equals the sum of its permitted region boards.
- Overall total equals the sum of visible published phase contributions.
- Unpublished phase points never appear publicly.
- Qualification and certificates are not duplicated when jobs are rerun.

## Milestone 8 — Replace report ID expansion with canonical report scope

### Work

Create a mandatory `FestReportScope` containing:

```text
root event
operational leaf IDs
conduct phase
region
payment batch
authorized school IDs
authorized item IDs
publication/lifecycle state
actor and permissions
```

Every report query must accept this scope. Remove direct use of broad `reportableEventIds()` from report data access after each report is migrated.

### Dataset policies

| Report family | Source policy |
|---|---|
| Registration, participants, attendance, numbering | Scoped operational leaves |
| Schedule and venue reports | Scoped operational leaves and phase publication |
| Marks and result sheets | Scoped leaf results; combined only by explicit aggregation policy |
| Championship and medal tally | Published awarded points, grouped by school identity |
| Finance | Batch invoices plus their immutable lines |
| Food/catering | Selected operational leaves and phase cutoff |
| Certificates | Published eligible results in the requested scope |
| Appeals | Results and appeal records in the same phase/region scope |
| Audit | Root plus only descendants authorized for the actor |

### Report retrofit procedure

For each catalog entry:

1. Declare supported scope modes.
2. Validate phase, region, batch, school and item filters server-side.
3. Convert the interactive page query.
4. Convert preview, PDF, CSV and spreadsheet queries.
5. Assert browser/export row parity.
6. Add region and phase to headings and filenames.
7. Add sentinel isolation tests.

The retrofit is not complete until every current export and interactive report has an automated scope test. Catalog metadata alone is not proof of correct filtering.

### Exit criteria

- No migrated report service expands descendants independently of `FestReportScope`.
- Full admins can choose combined, phase and region scopes.
- Region admins are locked to their authorized phase-region leaves.
- Interactive and downloaded versions contain identical records and totals.
- Level 1 and Level 2 finance reports reconcile independently.

## Milestone 9 — Complete supporting modules and user interfaces

### Admin setup

- Four-phase wizard.
- Two-batch configuration.
- Phase-region matrix.
- Item assignment completeness screen.
- Topology preview and validation report.

### School portal

- Two registration-level cards with dates, status and separate invoice/payment state.
- Independent regional-phase selection cards.
- Phase-filtered item registration.
- Clear indication of venue, dates and locked selections.
- Invoice, receipt and outstanding balance per payment level.

### Operations

- Phase/region filters on scheduling, attendance, numbering, food, judging and mark entry.
- Persistent context banner showing root event, phase and region.
- Block actions when the displayed context and operational leaf differ.

### Public portal

- Published phase selector.
- Regional and combined phase results.
- Overall running scoreboard with contribution columns.
- No navigation or API response exposing unpublished phases.

### Exit criteria

- Users can complete the workflow without manually navigating child event IDs.
- Every page clearly identifies phase, region and payment level where relevant.
- Empty, closed, unpublished and unauthorized states are deliberately handled.

## Milestone 10 — Migrate and validate existing MCS data

### Work

- Add a read-only topology audit command before migration.
- Report root operational rows, ambiguous item phases, duplicate child copies, orphan registrations, existing invoices and published results.
- Backfill source phase identity and operational leaf mappings only where unambiguous.
- Produce a manual resolution file for ambiguous rows.
- Create the two batches and four canonical phases.
- Generate leaves and copy items only after item allocation approval.
- Map existing school region selections without inventing a second phase choice.
- Rebuild draft invoices only after finance approval; preserve paid records through adjustments.
- Reconcile registrations, invoice totals, result points and school totals before enabling writes.
- Provide a dry-run mode and a rerunnable idempotent execution mode.

### Exit criteria

- Pre/post migration reconciliation is signed off.
- No paid financial history is lost.
- No published result changes without an approved correction record.
- Every active registration has one phase, batch and operational leaf.

## Milestone 11 — Automated testing and non-functional verification

### Canonical fixture

Create a fixture with:

- Two payment batches.
- Four conduct phases.
- At least three regions.
- Different allowed-region lists for the two regional phases.
- One school choosing different regions for Off Stage and Sargadhara.
- Sentinel schools, students, teams, invoices, marks, points, appeals and certificates in each leaf.
- At least one unpublished phase and one closed phase.

### Required test suites

- Data constraints and topology idempotency.
- Item-copy source identity.
- Region selection independence, locking and audited override.
- Form, API and spreadsheet registration parity.
- Two-invoice fee calculations, payment locking, adjustments and reconciliation.
- Scheduling, attendance, numbering, food and marks scope.
- Phase/region result publication.
- Regional, combined-phase and cumulative scoreboard arithmetic.
- Certificate, appeal and qualification idempotency.
- Every report page and export, including negative authorization tests.
- Full admin, event staff, phase-region admin, school admin and public permissions.
- Standard-event and existing Kids Fest regression tests.
- Query-count and large-data performance tests.
- Frontend production build and browser journey tests.

### Exit criteria

- All focused and full Fest suites complete without memory termination.
- Zero cross-region sentinel leakage.
- Browser/export parity passes for the complete report catalog.
- Invoice and scoreboard reconciliation differences are zero.
- Standard-event regression suite passes.

## Milestone 12 — Controlled rollout

### Work

- Deploy schema changes with the new mode disabled.
- Run topology audit and dry-run migration in staging.
- Conduct committee UAT using a copy of realistic MCS data.
- Obtain separate sign-offs from registration, finance, event operations, results and report owners.
- Enable the mode for MCS only.
- Run a limited registration pilot with selected schools.
- Verify Level 1 invoice generation and independent regional selections.
- Freeze configuration before live registrations expand.
- Monitor audit logs, routing failures, invoice reconciliation and report isolation.
- Prepare a rollback that disables new writes without deleting invoices, registrations or results.

### Exit criteria

- UAT sign-off is recorded for every workstream.
- Production reconciliation checks pass after enablement.
- Support owners and escalation paths are assigned for all four conduct dates.

## 4. Implementation workstreams and dependencies

| Workstream | Depends on | Can run in parallel with |
|---|---|---|
| Data model | Business contract | UI design, test-fixture design |
| Topology and item sync | Data model | Billing foundations |
| Region selection | Data model, phase regions | Batch billing |
| Registration routing | Topology, region selection | Finance reporting design |
| Batch invoicing | Data model, registration source rules | Topology |
| Lifecycle integration | Topology, routing | Report inventory conversion |
| Scoring/publication | Stable source identity | Supporting-module integration |
| Report retrofit | Canonical report scope, stable topology | UI implementation by report family |
| Migration | All data contracts stable | UAT preparation |
| Rollout | Full test and reconciliation pass | None |

## 5. Definition of done

The MCS enhancement is complete only when all of the following are true:

- Four conduct phases and two payment batches are represented separately.
- Schools can independently select regions for Off Stage and Sargadhara.
- Every item belongs to exactly one source phase.
- Every registration is routed to exactly one operational leaf.
- Each school receives at most one invoice per payment level.
- The Rs. 4,000 Level 1 school fee is charged exactly once.
- All user interfaces, APIs, imports and jobs enforce the same lifecycle.
- Regional, phase-combined and overall scoreboards reconcile from awarded points.
- Unpublished phase data is not exposed.
- All interactive reports and exports are phase-, region-, batch- and actor-safe.
- Appeals, certificates and qualification are scoped and idempotent.
- Existing standard Sahodaya events pass regression tests unchanged.
- Migration and production reconciliation reports have zero unexplained differences.

## 6. Explicitly excluded from shortcut implementations

The following approaches must not be accepted as completion:

- Treating each conduct phase as an invoice level.
- Storing only one region per school for the whole MCS event.
- Using the annual school region as the final MCS selection.
- Filtering only the report heading or UI while leaving the query unscoped.
- Adding phase metadata to the report catalog without converting the data query.
- Summing raw judge marks for the overall championship.
- Combining all descendant event IDs without role and phase rules.
- Editing a paid invoice in place after registration changes.
- Publishing the root event as a substitute for phase publication.
- Enabling regional mode automatically for every Sahodaya event.

## 7. Recommended pull-request sequence

Keep changes reviewable and deployable in this order:

1. Schema, models, capability flag and compatibility tests.
2. Phase/batch setup services and admin configuration.
3. Operational topology and item synchronization.
4. School phase-region selection and authorization.
5. Registration router plus browser/API/import parity.
6. Two-batch invoice engine and finance outputs.
7. Lifecycle integration for operational modules.
8. Scoring, publication, scoreboard and qualification.
9. Canonical report scope and report-family conversions.
10. School/admin/public user interfaces.
11. Migration commands, reconciliation tools and complete UAT fixture.
12. Performance, security, regression and rollout hardening.

Do not merge the final rollout change until the full report catalog, the two-invoice reconciliation and the different-region fixture all pass together.
