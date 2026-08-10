# Multi-Region & State Flow UAT Scenarios

## 1. Standard single-region Sahodaya (regression)

| Step | Action | Expected |
|------|--------|----------|
| 1 | Create Kalotsav event without preset | `conduct_mode=standard` |
| 2 | Register school for off-stage item | Registration on same event |
| 3 | Enter marks, publish results | Scoreboard unchanged from before |
| 4 | No partition UI required | Levels page shows child spawn only |

## 2. MCS multi-region (Tirur + Manjeri + District)

| Step | Action | Expected |
|------|--------|----------|
| 1 | Apply `mcs_kalotsav` preset on hub | Tirur, Manjeri, District children created |
| 2 | Assign schools to regions | `fest_event_school_partitions` saved |
| 3 | School registers off-stage item | Registration stored in assigned region child |
| 4 | School registers on-stage item | Registration stored in district finale child |
| 5 | Enter marks per region | Regional scoreboards independent |
| 6 | View hub scoreboard | Sum of region + finale per `aggregation_config` |
| 7 | MCS combo validation | Accept 2 off+3 on OR 3 off+2 on; reject invalid |
| 8 | Submit qualifiers to State | Outbox row + API intake on State DB |

## 3. State domain workspace

| Step | Action | Expected |
|------|--------|----------|
| 1 | Configure `state_domains` + link program | Program has `state_domain_id` |
| 2 | Sahodaya submits qualifiers | `state_qualifier_intakes` + entries created |
| 3 | State admin reviews intake | Approve updates entry status |
| 4 | Create `state_fest_events` | State championship workspace draft |
| 5 | Retry failed outbox | `fest:process-state-outbox` resends |

## 4. Kids Fest backward compatibility

| Step | Action | Expected |
|------|--------|----------|
| 1 | Spawn cluster on Kids Fest hub | Works via `FestPartitionService` |
| 2 | Combined cluster scoreboard | Unchanged behavior |

## 5. Idempotency

| Step | Action | Expected |
|------|--------|----------|
| 1 | Submit same qualifier batch twice | Same `idempotency_key` returns existing intake |
| 2 | Outbox duplicate enqueue | No duplicate pending rows |

## 6. Region-scoped report reporting/security (added for docs/REGION_PHASE_EVENT_REPORTING_REMEDIATION_PLAN.md)

Extends scenario 2 (MCS multi-region) with the report-isolation matrix from that plan's
§11. Use the same two-region sentinel fixture pattern as
`tests/Feature/SahodayaAdmin/RegionAdminReportContainmentTest.php` (named sentinel
schools/students per region) so a leak is obvious in the UI, not just in test
assertions.

| Step | Action | Expected |
|------|--------|----------|
| 1 | Full parent admin opens hub Reports Hub, Registration Register, Overall Ranking | Combined totals include both regions; no region selector forced |
| 2 | Full parent admin adds `?region_id=<Region A>` on Registration Register and Overall Ranking | Only Region A rows/rankings; filename/heading identifies Region A |
| 3 | Full parent admin adds `?region_id=<Region B>` | Only Region B rows/rankings; no Region A identifiers anywhere in the response |
| 4 | Region A admin (assigned on the hub, region_id=Region A) opens the hub Reports Hub URL directly | Transparently resolved to the Region A child (`ResolveRegionScopedReportEvent`) — page shows Region A data only, `event.id` in the page props is the child's id, not the hub's |
| 5 | Region A admin appends `?region_id=<Region B>` on any report under the hub | 403, not a redirect to Region B data |
| 6 | Region A admin appends `?school_id=<a Region B school>` on Registration Register | 403 |
| 7 | Region admin with a `region_admin` FestEventStaff row that has `region_id = null` | 403 on every report route under that hub (fail-closed — see `EnsureSahodayaAdmin::matchesRegionScope()`) |
| 8 | Compare browser XLS/PDF/CSV export vs on-screen totals for Registration Register, under both a region filter and no filter | Row counts and totals match exactly |
| 9 | Run `php artisan fest:audit-event-topology --sahodaya=<id> --format=table` against the fixture tenant | No `region_admin_missing_region` / `region_admin_on_combined_hub` / `operational_rows_on_partitioned_parent` findings for a clean fixture; each finding type reproducible by deliberately breaking the fixture |
| 10 | Dry-run `php artisan fest:repair-partitioned-parent-operational-rows --sahodaya=<id> --event=<hub id>` against a hub with registrations still on the parent | Reports what it would move, writes nothing without `--apply` |

Not yet covered by this scenario (see the implementation's final status report for why):
Combined vs Region-wise Result reconciling against a real published scoreboard with
marks; named competition phase gating (`competition_phase_id`); Sports season/sport/
region reconciliation; and any report beyond Registration Register and Overall Ranking
being scoped through `FestReportScopeResolver` rather than just event-substitution
containment.
