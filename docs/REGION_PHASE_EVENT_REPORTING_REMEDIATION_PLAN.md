# Region-wise, Phase-wise Event Reporting Remediation Plan

**Status:** Proposed — implementation not started

**Prepared:** 10 Aug 2026

**Priority:** Security and data-correctness work first; topology expansion after scoped reporting is stable

**Scope:** Sahodaya event administration, all fest competition types, regional partitions, report downloads/previews, named event phases, and Sports Meet
**Related:** [`REGION_SCOPED_ADMIN_AND_EVENT_FLOW_PLAN.md`](REGION_SCOPED_ADMIN_AND_EVENT_FLOW_PLAN.md), [`REGION_AND_PHASE_KALOTSAV_PLAN.md`](REGION_AND_PHASE_KALOTSAV_PLAN.md), [`FEST_CONDUCT_TOPOLOGY.md`](FEST_CONDUCT_TOPOLOGY.md), [`STATE_MULTI_REGION_UAT.md`](STATE_MULTI_REGION_UAT.md)

---

## 1. Objective

Deliver one predictable reporting model in which:

1. A full Sahodaya administrator can open a parent event and receive separate regional operational reports plus **both result modes: Combined Result and Region-wise Result**.
2. A region administrator can see and export only their assigned region, even if their assignment is stored against the parent event.
3. Browser reports, previews, PDFs, CSVs, and spreadsheets use the same server-side scope and produce matching data.
4. Parent reports do not accidentally mix preliminaries, finale entries, school rounds, clusters, or Sports discipline children.
5. The report packs **Before event / During event / After event** are enforced by the server, not only hidden in the UI.
6. Named competition phases such as **Digi Fest**, **Off-stage**, and **On-stage** can control registration, schedule, marks, publication, and reports when phase mode is enabled.
7. Region-wise Sports Meet works without breaking the existing **season -> sport -> item** structure.
8. Standard, non-region events and existing Kids Fest cluster behavior remain regression-safe.

This plan treats two different meanings of "phase" separately:

- **Report lifecycle phase:** before, during, or after an event.
- **Named competition phase:** a configured phase attached to items inside an event.

They may be used together, but they are not the same concept and must not share ambiguous request parameters or service methods.

---

## 2. Confirmed current gaps

### 2.1 Existing foundation that will be retained

The work starts from an existing partial implementation; these pieces should be extended rather than rebuilt:

- `FestEvent` parent/child relationships and `conduct_mode`.
- `partition_role`, `partition_key`, `region_id`, and `combine_regions_at_finale`.
- Sahodaya `Region` and active-year `SchoolRegionAssignment` records.
- Region child creation and school registration routing.
- `FestEventStaff` event/region assignments and the `region_admin` role.
- The report catalog, report hub, 50 export definitions, and 20 interactive report entries.
- Before/During/After report catalog classification.
- `FestEventPhase`, `phase_id` on items, phase CRUD, and basic item assignment.
- Item copying between parent and partition events.
- Existing combined scoreboard and Kids Fest cluster behavior.

### 2.2 Pending gaps to close

| ID | Gap | Impact |
|---|---|---|
| G1 | A region admin assigned on a parent hub can open the hub itself; parent reports then aggregate every child. | Cross-region data exposure. |
| G2 | `FestEvent::reportableEventIds()` includes the parent for every child and every immediate child for a partitioned parent, without filtering child roles. | Legacy parent rows leak into regional reports; finale/school-round rows can be double-counted. |
| G3 | Only Registration Register has a region selector; its export ignores `region_id`. | Parent admins cannot consistently obtain region reports. |
| G4 | Report services mix `reportableEventIds()` with direct `event_id = current event` queries. | Some parent reports are combined, others empty or incomplete. |
| G5 | Region filters are not consistently tied to the active academic year or validated against the selected event topology. | Stale/mismatched school membership can enter a report. |
| G6 | Before/during/after packs are filtered on the downloads screen but the export endpoint does not enforce the catalog phase. | Direct URLs bypass intended lifecycle availability. |
| G7 | `fest_event_phases` lifecycle columns and `phase_mode_enabled` exist, but registration, food, scheduling, marks, results, certificates, promotion, reports, and public pages do not enforce them. | Named phases are labels only. |
| G8 | Assigning a phase to parent items does not immediately propagate the equivalent assignment to already-created region items. | Parent and region phase definitions drift. |
| G9 | New events are automatically changed to `partitioned` whenever the Sahodaya has active regions. | Standard events cannot reliably remain standard; Sports season sync is disabled. |
| G10 | Sports needs both discipline children and regional children, but current report topology is only one level deep. | Region-wise Sports is structurally unsafe. |
| G11 | There are no automated security/report tests covering two regions with sentinel data. | Existing passing tests cannot prove isolation or report correctness. |

---

## 3. Product and architecture decisions

These decisions should be treated as the implementation contract.

### 3.1 Region-wise is supported broadly, but remains opt-in per event

- New events default to `conduct_mode = standard`.
- Having active Sahodaya regions must not silently convert every event to `partitioned`.
- Event creation/settings expose an explicit **Standard / Region-wise** choice.
- Region-wise remains supported for Kalotsav, Kids Fest, Teacher Fest, English Fest, Science Fest, Custom Events, and compatible tenant-defined competition types.
- Sports is temporarily excluded from generic one-level auto-sync until the nested topology in Phase 7 is implemented.

#### Event-type applicability matrix

| Event/program | Standard mode | Region-wise mode | Named phases | Combined Result | Region-wise Result | Required implementation note |
|---|---:|---:|---:|---:|---:|---|
| Kalotsav | Yes | Yes | Yes | Yes | Yes | Primary reference implementation for region + phase together. |
| Kids Fest | Yes | Yes, including cluster terminology | Yes | Yes | Yes | Preserve existing cluster aggregation and labels. |
| Teacher Fest | Yes | Yes | Yes | Yes | Yes | Use the generic non-Sports partition flow. |
| English Fest | Yes | Yes | Yes | Yes | Yes | Combined can be configured; individual regional results remain available. |
| Science Fest | Yes | Yes | Yes | Yes | Yes | Use the generic non-Sports partition flow. |
| Custom/tenant-defined competitions | Yes | Yes when the type permits partitioning | Yes | Yes | Yes | Capability comes from topology metadata, not hardcoded event-type branches. |
| Sports Meet | Yes | Yes after nested topology ships | Yes | Yes | Yes | Must use Season -> Sport -> Region; never generic one-level partitioning. |
| School round | Yes | Not automatically | Yes | Only within its own configured topology | Only if explicitly partitioned | Vertical school/Sahodaya/state flow stays separate from geography. |
| State-level event | Yes | Not inherited from Sahodaya regions | Yes | Per state topology | Per state topology | Must not be pulled into Sahodaya Combined reports. |

"Supported for all events" means the capability is available where the event topology allows it. It does **not** mean every event is automatically made regional or phase-wise.

#### Enablement rules

- `conduct_mode = standard` and `phase_mode_enabled = false` are the defaults.
- Region-wise mode is enabled only by an explicit create/edit action from an authorized full administrator.
- Phase-wise mode is enabled independently; an event may use regions without phases, phases without regions, both, or neither.
- Enabling region-wise requires active regions and valid current-year school assignments.
- Enabling phase-wise requires a default phase and complete assignment of enabled items before the event can open.
- Disabling either mode after live operational data exists requires a migration workflow; a simple toggle must be rejected.
- Publishing an event must never silently enable region-wise mode.

### 3.2 Parent events are configuration hubs; operational data belongs to explicit leaves

For a clean partitioned topology:

- The parent owns shared configuration and aggregated views.
- Registrations, schedules, attendance, marks, and regional results belong to the selected operational child/leaf event.
- Existing operational rows left on a parent after conversion must be migrated or explicitly quarantined; they must not be silently included in every region.

### 3.3 Every report request has an explicit scope

Supported report scope modes:

| Mode | Meaning |
|---|---|
| `self` | Exactly the selected standard event or operational child. |
| `combined` | Parent-authorized rollup using report-family-specific child roles. |
| `region` | One validated region child under the selected root/program event. |
| `finale` | The selected finale child only. |
| `cluster` | One Kids Fest/other cluster child. |

Named competition phase is an additional filter (`competition_phase_id`), not a scope mode. Report lifecycle phase keeps the existing `before|during|after` vocabulary.

### 3.4 The parent event is the region-reporting hub

For every partitioned parent event, the parent Reports page must present regions as first-class, separate report scopes:

```text
Parent Event Reports
  +-- Region A Reports
  |     +-- Before event
  |     +-- During event
  |     +-- After event
  +-- Region B Reports
  |     +-- Before event
  |     +-- During event
  |     +-- After event
  +-- Region C Reports
        +-- Before event
        +-- During event
        +-- After event
```

Required behavior:

- Each region has its own operational summary, interactive reports, previews, and export links.
- A regional report contains only that region's child event, schools, registrations, schedules, marks, results, fees, and other authorized operational data.
- Every exported filename and heading contains the region name/code.
- Switching reports must retain the selected region until the admin deliberately changes it.
- The parent Results workspace provides an explicit result-mode selector:
  - **Combined Result** — overall results, school ranking, medal tally, cumulative points, and championship using the configured regional/finale aggregation policy.
  - **Region-wise Result** — select Region A, Region B, Region C, and so on to obtain that region's own results, ranking, medal tally, cumulative points, and result exports.
- Each Region-wise Result is the official result for that region. Combined Result is the official overall result for the parent event.
- Combined and Region-wise results are independent scopes; neither may reuse unscoped rows from the other.
- A **Download all regions separately** action may package one region-specific file per region into a ZIP, or one clearly separated sheet per region for spreadsheet formats. It must not merge regional rows into one unlabeled output.

The intended parent flow is therefore:

```text
Parent Event Reports
  +-- Operational Reports
  |     +-- Region A
  |     +-- Region B
  |     +-- Region C
  +-- Results
        +-- Combined Result
        |     +-- Overall ranking / medal tally / championship
        +-- Region-wise Result
              +-- Region A result
              +-- Region B result
              +-- Region C result
```

### 3.5 Combined does not mean "all descendants"

Each report family needs a dataset policy:

| Report family | Combined dataset policy |
|---|---|
| Registration/participants/attendance/numbering | Operational preliminary leaves; exclude finale and school rounds unless explicitly requested. |
| Schedule/mark-entry status | Operational leaves selected by scope. |
| Results/ranking/medal tally | Offer both Combined and Region-wise modes. Combined uses child roles specified by `aggregation_config`; Region-wise uses exactly one validated region child. |
| Finance | Root-owned invoice/fee rows filtered through the scoped schools and registrations. |
| Catering/food | Scoped operational leaves; combined is an explicit rollup. |
| Audit | Root plus only descendants permitted by the actor and requested scope. |
| Catalog/item configuration | Root or selected sport configuration event, not registration leaf rows. |

### 3.6 Region administrators never receive an unrestricted Combined scope

- A `region_admin` assignment on `(parent event, region)` authorizes the matching regional child, not the parent combined dataset.
- A request to a parent report URL is either redirected to the matching regional scope or resolved server-side as that fixed region.
- Region selectors are hidden/locked for region admins.
- Supplied `region_id`, `school_id`, `item_id`, stage, head, area, and phase filters are validated against the already-authorized scope.

### 3.7 Sports uses a nested tree

Recommended Sports topology:

```text
Sports season (root)
  +-- Athletics (sports_discipline)
  |     +-- Region A (region)
  |     +-- Region B (region)
  +-- Chess (sports_discipline)
        +-- Region A (region)
        +-- Region B (region)
```

- Items/configuration live on the sport event and are copied to sport-region leaves.
- Registrations, schedules, marks, and regional results live on sport-region leaves.
- A sport report can combine its regions.
- A season report can combine selected sport-region leaves without treating region children as sports disciplines.

### 3.8 Required end-to-end flows

#### Flow A — Standard event, no phases

```text
Create Standard event
  -> configure items
  -> schools register on the event
  -> schedule/marks/results on the same event
  -> one report scope: Self
```

This is the regression baseline and must remain unchanged.

#### Flow B — Standard event with named phases

```text
Create Standard event
  -> enable phase mode
  -> create phases and assign every enabled item
  -> registration/schedule/marks/results use each item's effective phase lifecycle
  -> reports: All phases or one named phase
```

#### Flow C — Region-wise event, no named phases

```text
Create Region-wise parent
  -> sync active regions
  -> assign schools using the event academic year
  -> route registration to one region child
  -> run schedule/marks/results per region
  -> parent reports: separate Region-wise operational reports
  -> parent results: Combined Result or Region-wise Result
```

#### Flow D — Region-wise event with named phases

```text
Create Region-wise parent
  -> create/sync region children
  -> enable phases on parent
  -> sync stable phase definitions and equivalent item assignments to each region
  -> each region operates each phase independently
  -> parent operational reports: Region + optional named phase
  -> parent results: Combined/Region-wise + optional named phase
```

Examples:

- Region A + Off-stage registration report.
- Region B + On-stage mark-entry status.
- Combined + Digi Fest result.
- Region C + All-phases medal tally.

#### Flow E — Region-wise Sports Meet with phases

```text
Create Sports season
  -> add sport events
  -> sync regions under each sport
  -> copy sport items and stable phases to sport-region leaves
  -> route registrations to sport + region leaf
  -> results available as:
       sport + region
       sport + combined regions
       season + region across sports
       season + combined sports/regions
```

---

## 4. Target backend design

### 4.1 Introduce an actor-aware report scope resolver

Add:

- `App\Services\Events\Reports\FestReportScopeResolver`
- `App\Services\Events\Reports\FestReportScope` immutable DTO
- `App\Policies\FestReportPolicy` or an equivalent authorization service

Suggested DTO fields:

```php
final readonly class FestReportScope
{
    public FestEvent $requestedEvent;
    public FestEvent $rootEvent;
    public string $mode;                 // self|combined|region|finale|cluster
    public ?int $regionId;
    public ?int $competitionPhaseId;
    public array $eventIds;              // authorized operational/config ids for this dataset
    public array $itemIds;
    public array $schoolIds;
    public array $includedPartitionRoles;
    public bool $isActorRestricted;
}
```

The resolver must:

1. Resolve the event root and ancestry.
2. Resolve the authenticated user's event/region staff assignments.
3. Validate the requested scope mode and region against that topology.
4. Use `SchoolRegionAssignment::forYear(AcademicYear::forSahodaya(...))` for region school IDs.
5. Resolve equivalent copied item IDs and phase IDs.
6. Return no rows (fail closed) when an assigned region has no valid child.
7. Reject tampered cross-region school/item/phase filters with 403 or 422.

### 4.2 Stop using `reportableEventIds()` as report authorization

Do not remove it immediately because other operational services still use it. Instead:

- Mark it as legacy for reports.
- Replace its use in all report controllers/services with `FestReportScope`.
- Add explicit topology helpers such as:
  - `rootEvent()` / `ancestors()`
  - `childrenForRoles(array $roles)`
  - `regionalChild(int $regionId)`
  - `operationalLeaves(array $roles)`
  - `equivalentItemIds(array $sourceItemIds, array $eventIds)`
- After rollout, audit non-report uses and narrow or replace them separately.

### 4.3 Add report metadata needed by the resolver

Extend each entry in `FestReportCatalog` with:

```php
[
    'id' => 'registrations',
    'phase' => 'before',
    'dataset' => 'registration',
    'supported_scopes' => ['self', 'combined', 'region'],
    'supports_competition_phase' => true,
]
```

All 50 current exports must declare:

- dataset/report family;
- supported report scopes;
- whether school/item/head/area/stage/competition-phase filters are supported;
- lifecycle phase;
- staff/public audience;
- preview route, if any.

Unknown or incomplete metadata must fail a catalog contract test.

### 4.4 Apply scope to every report implementation

Refactor these primary services first:

- `FestReportController`
- `FestReportService`
- `FestEventReportAnalyticsService`
- `FestExportService`
- `FestRegistrationRegisterService`
- `EventContext` report/scoreboard entry points

Then update dependent report builders:

- schedule/conflict reporting;
- attendance and mark sheets;
- ID/admit cards;
- fee and invoice reports;
- catering/food reports;
- certificates/promotions;
- volunteer and audit extracts.

Rules:

- No report query may independently derive broad event IDs.
- No report may trust a raw `school_id`, `item_id`, `head_id`, `area_id`, `stage_id`, `region_id`, or phase ID.
- Browser and export methods must accept the same `FestReportScope`.
- Filenames and report headings identify the selected scope, for example `kalotsav-region-a-registrations.xlsx`.

### 4.5 Enforce report lifecycle phases on the backend

Before dispatching an export:

1. Read its catalog lifecycle phase.
2. Determine allowed lifecycle phases for the resolved scope.
3. Reject unavailable exports even when called directly.
4. Audit any permitted override.

Scope lifecycle rules:

- `self`/`region`: use that event's lifecycle.
- `combined`: use the lowest common lifecycle across included operational events; `after` becomes available only when all included result-bearing events are published/completed.
- `competition_phase_id`: use the named phase's lifecycle when phase mode is enabled.

If an emergency override is required, add a dedicated permission such as `fest.reports.lifecycle_override`; do not treat ordinary staff access as an implicit override.

---

## 5. Target frontend flow

### 5.1 Shared report-scope toolbar

Create a shared component used by the report hub, interactive reports, preview links, and downloads:

- `ReportScopeToolbar.vue`

Full admin on a partitioned parent sees:

- A region-report overview with one card/tab per region.
- Operational scope: Region A / Region B / Region C / Finale where supported.
- Results mode: Combined Result / Region-wise Result; choosing Region-wise then requires Region A / Region B / Region C.
- Named competition phase: All phases / Digi Fest / Off-stage / On-stage where supported.
- Existing report-specific filters such as school, item, stage, or date.
- A **Download all regions separately** action for supported report formats.

Region admin sees:

- A fixed badge such as `Region: Region A`.
- No Combined option and no other region values.

Opening a regional child directly shows:

- `Region report` context and a link back to the parent for full admins.

### 5.2 Preserve scope across every navigation

The following must retain the selected scope and phase:

- interactive report navigation;
- Before/During/After download tabs;
- preview links;
- browser filtering/pagination;
- CSV/XLS/PDF links;
- report hub back links.

Use one shared query builder/composable rather than rebuilding query strings on each page.

### 5.3 Region-aware report content

- Page title, empty state, summary totals, and export filename must state the current scope.
- Combined reports may include a `Region` column where rows span regions.
- Region reports must not show an `All regions` option to restricted users.
- School dropdowns contain only schools inside the resolved scope.
- The parent report overview shows per-region counts/status without combining the rows: schools, registrations, participants, schedules completed, marks pending, results publication state, fees, and available report packs.
- Region report links always carry a validated `scope=region&region_id=<id>` (or an opaque signed scope token with equivalent meaning).
- Parent result links carry either `scope=combined` or `scope=region&region_id=<id>` and clearly label the output as Combined Result or the selected Region-wise Result.

---

## 6. Named competition phase implementation

### 6.1 Complete phase configuration

The phase management UI must support all existing model fields:

- name and stable code;
- order/default phase;
- start/end;
- registration window and lock;
- food cutoff;
- status;
- scoring lock;
- schedule publication;
- result publication;
- appeal window/deadline.

Add an explicit phase-mode toggle:

- Off: all items behave as one implicit event phase.
- On: exactly one default phase is required and every enabled item must belong to a phase before publication/registration opens.

### 6.2 Stable phase identity across partitions

Add a nullable self-reference such as `source_phase_id` or an equivalent stable phase key to `fest_event_phases`:

- Parent/root phases are sources.
- Regional child phases refer to their source phase.
- Enforce uniqueness of `(event_id, source_phase_id)`.
- Do not rely only on mutable names.

Phase sync must run when:

- a phase is created/updated/deleted on a hub;
- items are assigned/unassigned;
- a region/sport-region child is created;
- items are re-synced.

Deleting or changing a source phase with live registrations must require an explicit migration/reassignment action.

### 6.3 Effective phase lifecycle resolver

Add `FestPhaseLifecycleService` to resolve the effective lifecycle for an item/registration:

- If phase mode is off, use event lifecycle fields.
- If phase mode is on, use the item's phase lifecycle, falling back only where the product rule explicitly permits it.
- A missing phase on an enabled item fails closed once phase mode is active.

Wire the resolver into:

1. Registration eligibility and review.
2. Food ordering cutoff.
3. Schedule publication and public schedule.
4. Attendance and mark entry.
5. Results publication.
6. Appeals.
7. Certificates.
8. Qualifier promotion.
9. Public result/report pages.
10. Admin report packs and exports.

### 6.4 Phase-wise reports

Every compatible report must accept `competition_phase_id` through `FestReportScope`.

- Resolve equivalent child phases via `source_phase_id`.
- Restrict items first, then registrations/schedules/marks through those scoped item IDs.
- Combined parent phase reports aggregate only the same logical phase across the selected regions.
- A phase's After-event reports remain unavailable until that phase's results are published.
- An All-phases result report is available only according to the root/combined lifecycle rule.

---

## 7. Sports nested-region topology

This is a separate release after ordinary region reports and named phases are stable.

### 7.1 Data model additions

Add or formalize:

- `root_event_id` on `fest_events`, indexed and backfilled, to resolve a program tree efficiently;
- a uniqueness rule preventing duplicate region children under one sport, for example `(parent_event_id, partition_role, region_id)`;
- topology validation ensuring a `sports_discipline` child belongs to a `sports_season`, and its `region` child belongs to that sport.

`parent_event_id` remains the immediate parent. `root_event_id` identifies the season/program root.

### 7.2 Sports region sync

Add a dedicated method/service, for example:

- `FestSportsRegionPartitionService::syncRegionsForSeason()`

It must:

1. Iterate enabled sport events under the season.
2. Create one region child under each sport.
3. Copy sport items and stable phases into each sport-region leaf.
4. Route each school's sport registration to its matching sport-region leaf.
5. Cascade applicable configuration without overwriting regional operational progress.

### 7.3 Sports reporting

- Sport child + Combined: aggregate that sport across regions.
- Sport-region child: one region for one sport.
- Season + Combined: aggregate authorized leaves across all sports and regions.
- Season + Region: all sports for one region.
- Medal/ranking aggregation uses explicit result policies, never immediate-child assumptions.

Until this work ships, generic event auto-region sync must skip Sports and the UI must explain that region topology is configured through the Sports setup flow.

---

## 8. Data audit and migration strategy

### 8.1 Add a read-only topology audit command first

Create `fest:audit-event-topology` with tenant/event filters and JSON/CSV output. It should detect:

- standard events with partition children;
- partitioned parents with operational registrations/marks/schedules on the parent;
- children missing/wrong `region_id`;
- duplicate children for one region/role;
- parent reports that would include both regional preliminaries and finale rows;
- Sports roots already converted to generic regional hubs;
- phase definitions/assignments that differ between parent and children;
- school partition mappings that disagree with active-year region assignments;
- staff assignments with `region_admin` but no region;
- region admins assigned on hubs that currently expose Combined reports.

This command is mandatory before any data-changing migration is run in production.

### 8.2 Keep schema migrations additive

Schema migrations may add indexes/columns/constraints, but must not guess how to relocate live registrations.

Use a separate explicit repair command for operational data:

- dry-run by default;
- require tenant/event selection;
- output every source/target count;
- refuse schools without an active-year region;
- run transactionally per event;
- write audit logs and a reversible mapping ledger.

### 8.3 Repair existing parent operational rows

For a converted partitioned event:

1. Resolve each registration's school region for the event academic year.
2. Resolve/create the matching regional child item using inherited item identity.
3. Move registration and dependent participants, schedules, attendance, marks, qualifications, and related operational references together.
4. Recalculate fees/results after the move.
5. Quarantine ambiguous rows instead of duplicating them across children.

### 8.4 Backfill phase identity

- Match parent/child phases by existing code first.
- Use name only for a dry-run suggestion when code is absent.
- Require manual resolution for ambiguous matches.
- Backfill `source_phase_id` and then resync item phase assignments.

---

## 9. Implementation phases and exit criteria

### Phase 0 — Baseline inventory and failing tests

**Work**

- Add the topology audit command in read-only mode.
- Build a two-region test fixture with unique sentinel schools, participants, marks, fees, catering orders, and audit rows.
- Add failing tests for G1-G11 before changing behavior.
- Produce an inventory mapping all 50 exports and all interactive pages to their current query/service path.

**Exit**

- Every known gap has a reproducing automated test or an explicitly documented manual-only scenario.
- A production-like tenant can be audited without writes.

### Phase 1 — Security containment

**Work**

- Change region-admin parent matching so it authorizes only the matching region scope.
- Fail closed when a region-admin assignment has no region or no matching child.
- Add server-side validation for region and school filters.
- Lock/hide Combined and sibling regions for restricted actors.
- Apply the same authorization to previews and exports.

**Exit**

- Region A admin receives no Region B sentinel data from any parent, child, preview, or export URL.
- Tampered `region_id`/`school_id` values are rejected.
- Full Sahodaya admin behavior remains available.

### Phase 2 — Central report scope architecture

**Work**

- Implement `FestReportScopeResolver`, DTO, policy, topology helpers, and report catalog metadata.
- Integrate them at the report controller boundary.
- Add active-academic-year region resolution.
- Add scope information to audit events and filenames.

**Exit**

- Every report request resolves and logs one explicit scope.
- Catalog contract tests cover all 50 exports.
- Report controllers no longer pass raw, unvalidated regional filters into services.

### Phase 3 — Report and export retrofit

**Work**

- Retrofit registration/participant reports first.
- Retrofit schedule, attendance, mark-entry, result, and ranking reports.
- Retrofit finance, food/catering, ID/certificate, volunteer, and audit reports.
- Remove direct current-event queries from parent-capable report paths.
- Build the parent region-report overview, shared scope toolbar/composable, and preserve scope across navigation.
- Add separate per-region exports and the optional bulk package that contains one labeled output per region.

**Exit**

- Every interactive report and export supports its declared scopes.
- Browser/export row counts match for the same filters.
- Every parent region card opens and exports only that region's data.
- Parent preliminary reports exclude finale/school-round rows unless selected.
- Combined results, ranking, medal tally, and championship exports reconcile with the configured combined scoreboard.
- Every Region-wise result export reconciles with that region child's published results and contains no sibling-region rows.
- Standard event reports remain unchanged.

### Phase 4 — Server-enforced Before/During/After packs

**Work**

- Enforce catalog lifecycle phase in export and preview endpoints.
- Implement combined-scope lifecycle calculation.
- Add optional explicit override permission and audit trail if required.

**Exit**

- Direct URLs cannot download During/After reports early.
- After reports on Combined become available only when all included result-bearing leaves qualify.

### Phase 5 — Named phase foundation and synchronization

**Work**

- Complete phase UI/lifecycle validation.
- Add stable parent-child phase identity.
- Enforce phase completeness when phase mode is on.
- Sync phase definitions and item assignments to existing/new regional children.

**Exit**

- Parent and region items resolve to the same logical named phase.
- No enabled item is phase-less when phase mode is active.
- Phase edits cannot silently orphan live registrations.

### Phase 6 — Named phase operational gates and reports

**Work**

- Wire effective phase lifecycle into all ten operational areas in §6.3.
- Add phase filter support to compatible reports and exports.
- Add phase-specific result publication and report availability.

**Exit**

- A closed phase rejects registration/marks while another open phase remains usable.
- Region A + Off-stage reports contain only Region A's Off-stage data.
- Combined + Off-stage contains that logical phase across authorized regions only.

### Phase 7 — Sports nested-region implementation

**Work**

- Add/backfill `root_event_id` and topology constraints.
- Implement season -> sport -> region sync and registration routing.
- Make scope resolution recursive/tree-aware.
- Retrofit Sports reports and rankings.

**Exit**

- Adding/syncing sports still works when regions exist.
- Each school registers into the correct sport-region leaf.
- Sport/season Combined and Region reports match the sum of their selected leaves without double-counting.

### Phase 8 — Data repair, UAT, and staged rollout

**Work**

- Run topology audits and review every anomaly.
- Execute explicit repair commands tenant by tenant.
- Extend `STATE_MULTI_REGION_UAT.md` with this plan's matrix.
- Pilot with one standard tenant, one region-wise non-Sports tenant, Kids Fest, and one Sports tenant.
- Enable feature flags gradually and compare report totals before/after.

**Exit**

- All automated suites and formal UAT pass.
- No unresolved parent operational rows or topology anomalies remain for enabled tenants.
- Rollback procedure and feature-flag disable path have been exercised.

---

## 10. Required automated test matrix

### 10.1 Authorization tests

- Full admin: Combined and every region allowed.
- Event admin: assigned event only.
- Region admin assigned on child: that child only.
- Region admin assigned on parent + Region A: Region A scope only.
- Region admin with null region: no report data.
- Region admin + tampered Region B/school/item/phase filters: rejected.
- API and web middleware produce equivalent results.

### 10.2 Scope/data tests

- Parent Combined preliminary report excludes finale duplicates.
- Parent Region A report contains only Region A sentinel rows.
- Parent Region B report contains only Region B sentinel rows and has no Region A identifiers.
- Parent **Download all regions separately** returns one correctly labeled, isolated output per region.
- Parent official Combined results equal the configured sum/aggregation of included regional/finale result rows without duplicate participants or marks.
- Parent Region-wise results equal the selected child region's result rows, ranking, medals, and points.
- Child Region A report does not include legacy parent rows.
- Active-year region assignment wins over previous-year assignment.
- Combined finance reads root-owned fees but includes only scoped schools.
- Catering/audit/volunteer/ID-card families respect the same scope.
- Browser, preview, and export produce equivalent scoped totals.

### 10.3 Lifecycle report tests

- Before reports available in draft/published states as specified.
- During reports blocked until schedule/ongoing criteria are met.
- After reports blocked until result publication/completion criteria are met.
- Direct export URL has the same gate as Downloads UI.
- Combined lifecycle is the intersection/lowest common phase of included leaves.

### 10.4 Named phase tests

- Phase mode off preserves standard behavior.
- Phase mode on requires a default and complete item assignment.
- Parent phase assignment syncs to region-equivalent items.
- Phase registration/mark/result gates are independent.
- Region + named-phase and Combined + named-phase reports are correct.
- Phase deletion with live data is blocked without an explicit migration.

### 10.5 Topology regressions

- Standard event remains standard when active regions exist.
- Kalotsav region routing works.
- English Fest region-final mode works without unwanted overall aggregation.
- Kids Fest cluster Combined remains unchanged.
- School -> Sahodaya -> State vertical levels are not included in geographic Combined reports.
- Sports season -> sport creation works before and after regional sync.

---

## 11. UAT scenarios to add

| Scenario | Required confirmation |
|---|---|
| Full parent admin, Combined | Totals equal the intended included child roles only. |
| Full parent admin, Region A | Browser/PDF/XLS all match Region A child totals. |
| Full parent admin, all separate regions | Every region has its own report workspace and export; no file contains another region's rows. |
| Parent Combined Result | Results, ranking, medal tally, and championship reconcile with the configured aggregation policy. |
| Parent Region-wise Result | Selecting each region produces that region's isolated results, ranking, medal tally, and exports. |
| Region A admin on parent | Fixed Region A context; no Combined/Region B access. |
| Converted event with old parent registrations | Audit detects them; repair moves them once; no regional duplication. |
| Region + finale event | Preliminary reports exclude finale; result aggregation follows config. |
| Named Off-stage phase | Registration, schedule, marks, publication, and reports follow phase lifecycle. |
| Two phases at different statuses | One phase can be closed/published without exposing the other phase's After reports. |
| Kids Fest | Existing cluster and Overall totals unchanged. |
| Sports Meet | Season, sport, sport-region, and season-region reports all reconcile. |
| Previous-year region change | Current event year decides the school's report region. |

---

## 12. Rollout and rollback controls

Use feature flags, initially tenant-scoped:

- `fest_scoped_reports_v2`
- `fest_named_phase_lifecycle`
- `fest_sports_region_tree`

Rollout order:

1. Deploy additive schema and audit tooling with flags off.
2. Run audit and repair dry-runs.
3. Enable scoped-report security for internal/test tenants.
4. Enable for one real region-wise non-Sports tenant.
5. Validate Kids Fest regression.
6. Enable named phases only for an opted-in pilot event.
7. Enable Sports nested regions last.

Rollback:

- Disable the affected feature flag.
- Keep additive columns/tables in place.
- Do not automatically reverse moved operational rows; use the repair ledger and explicit reversal command.
- Preserve audit logs containing resolved scope, actor, event IDs, school IDs, phase, and export type.

---

## 13. Definition of done

This program is complete only when all of the following are true:

- [ ] A region admin cannot retrieve another region's data through any browser, preview, export, or direct URL.
- [ ] A full parent admin receives a separate report workspace and separate exports for every region on every compatible report.
- [ ] The parent Results workspace offers both Combined Result and Region-wise Result modes.
- [ ] Combined Result is the official overall parent result; each Region-wise Result is official for its selected region.
- [ ] Both result modes support browser views and PDF/XLS/CSV exports with matching totals.
- [ ] All 50 report exports have complete scope metadata and contract coverage.
- [ ] All interactive reports and exports use `FestReportScope`.
- [ ] No parent-capable report relies on unfiltered immediate children or raw `reportableEventIds()`.
- [ ] Preliminary, finale, cluster, school-round, and Sports roles are never mixed accidentally.
- [ ] Before/During/After availability is enforced server-side.
- [ ] Named phases control the full operational lifecycle when enabled.
- [ ] Parent/child phase identities and item assignments remain synchronized.
- [ ] Standard events remain standard by default even when regions exist.
- [ ] Kids Fest regression tests and UAT pass.
- [ ] Region-wise Sports uses the nested topology and reconciles at sport and season levels.
- [ ] Existing topology/data anomalies have been audited and explicitly repaired or accepted.
- [ ] Automated tests, formal UAT, rollout monitoring, and rollback rehearsal are complete.

---

## 14. Recommended first implementation slice

The first pull request should contain only:

1. The two-region sentinel test fixture.
2. Failing security tests for parent-assigned region admins.
3. The region-admin parent containment fix.
4. Active-year region/school validation for Registration Register.
5. Browser/export parity for Registration Register's `region_id`.
6. Audit logging of the resolved region scope.

This is the smallest independently releasable slice that closes the immediate cross-region exposure and proves the architecture before all report services are migrated.
