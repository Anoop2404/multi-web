# AUDIT 08 — Database and API Gaps

## 1. Database topology

Three connections are in play, and the separation is the single most important architectural fact
about this platform.

| Connection | Scope | Purpose |
|---|---|---|
| `mysql` (central) | Platform-wide | `tenants`, `users`, `platform_states`, `fest_state_programs`, `fest_state_program_items`, `state_remittances`, `state_qualifier_intakes`, `state_qualifier_entries`, `fest_state_outbox`, and the `state_*` State-domain models that live here |
| `state` | The State Kalotsav database | `state_fest_events`, `state_fest_registrations`, `state_fest_participants`, `state_fest_marks`, `state_judge_scores`, `state_judge_assignments`, `state_item_results`, `state_grade_bands`, `state_point_rules`, `state_prize_categories`, `state_appeals`, `state_certificates`, `state_certificate_batches`, `state_conduct_audits`, and the rest of the State competition domain |
| tenant (`sahodaya_*`) | Per Sahodaya | Each Sahodaya's own fest data, schools, participants, registrations, marks |

The State domain models declare `protected $connection = 'state';`. The central models declare
nothing and use the default. `state:migrate` runs the State migration path separately, and the
tests call `Artisan::call('state:migrate')` in `setUp()`.

**This is the right architecture for this problem.** A State Kalotsav does not need to read
Sahodaya tables live; it needs a *nominated subset* of them, certified and transferred once. The
separate database makes that boundary physical rather than conventional, which is why cross-tenant
leakage is structurally impossible rather than merely prevented.

## 2. The State migration

`database/migrations/state/2026_07_20_000001_state_fest_tables.php` is additive. It creates the
State-domain tables on the `state` connection.

**Not verified:** whether this migration is safe to run against an existing production State
database that already has data. `Artisan::call('state:migrate')` in tests runs it on a fresh
database, which proves the `up()` path but not the idempotency of a re-run against a live
database. This is a launch-prerequisite check.

## 3. Schema gaps found

| ID | Table | Missing | Why it matters | P |
|---|---|---|---|---|
| DB-01 | `state_fest_registrations` | A flag marking a court-order/wildcard entry whose points must not count toward school or Sahodaya standings | Manual rule 24 says such entries get grades and prizes but no organizational points. Without a flag, `StateResultService::ranking()` and `schoolRankings()` will credit them. | P1 |
| DB-02 | `state_appeals` | An appeal-window field (`appeal_window_minutes` or an `announced_at` on the item result) | Manual rule C-1: appeal must be filed within one hour of result announcement. Without it the window cannot be enforced. | P1 |
| DB-03 | `state_fest_registrations` / participants | A per-student item counter, or a query enforcing manual rule 6 (5 single + 2 group, only 3 stage) | The schema has `max_events_per_student` on `FestParticipationPolicy` but nothing reads it for the State module. | P1 |
| DB-04 | `state_certificates` | A unique constraint on `(state_event_id, registration_id, certificate_type)` | Prevents duplicate issuance on a double-clicked generate button. | P2 |
| DB-05 | `state_fest_marks` | Nothing found missing | — | — |
| DB-06 | `state_qualifier_entries` | Nothing found missing | `qualifier_type` exists with a default of `regional_winner`; special entries need an override but the column is there. | — |

## 4. API contracts

The Sahodaya → State transfer is the only cross-database API, and it is well designed.

### 4.1 Outbox contract

| Field | Value |
|---|---|
| Producer | `FestStateQualifierPayloadBuilder::enqueue()` |
| Table | `fest_state_outbox` (central connection) |
| Idempotency key | `qualifiers:{program}:{event}:{sha256(entries)}` |
| Key detail | The hash covers **entries only**, deliberately excluding `submitted_at`. Including a timestamp would change the hash on every call and defeat idempotency. |
| Insert | `firstOrCreate` on a unique `idempotency_key` index — a re-call returns the existing row |
| Gate 1 | Refuses unless every applicable source event has `results_published = true` |
| Gate 2 | Refuses custom items not present in the State catalog |
| Consumer | `fest:process-state-outbox` every 5 minutes, `withoutOverlapping()` |
| Retry | Failed rows carry the error; rows past `MAX_ATTEMPTS` are skipped for manual review |

### 4.2 Intake contract

| Field | Value |
|---|---|
| Endpoint | State receives the payload into `state_qualifier_intakes` / `state_qualifier_entries` |
| Identity carried | `source_tenant_id`, `source_event_id`, `source_registration_id` |
| State identity | New UUIDs issued at materialization; `source_*_id` retained for traceability |
| Approval | `StateQualifierIntakeService::approve()` — transactional, `lockStateEventFor` on the State event row, participation-limit check |
| Rejection | Terminal per intake |

### 4.3 Why the idempotency key design matters

This is the single best piece of engineering in the transfer path. Consider the failure it
prevents: a Sahodaya admin clicks "submit to State" twice, or the outbox command runs while a
previous run is still finishing. With a naive `create()`, the State side receives two identical
intakes and now has two State identities for the same student, and there is no way to merge them
without manual database surgery.

The hash-over-entries-only design means the second call is a no-op. And because the hash excludes
`submitted_at`, it also means an admin who re-submits after a *failed* attempt gets the same row
back with the error attached, rather than a new row.

## 5. Cross-database communication

**There is exactly one direction and one channel.** Sahodaya data reaches the State database only
through the outbox. The State database never queries a tenant database. The State admin never has
credentials to a tenant database.

The reverse direction is also clean: the State admin's workspace resolves via
`STATE_ADMIN_DOMAIN`, and a Sahodaya admin's session on a tenant domain has no route that queries
State tables.

**Verdict:** no cross-database leakage path was found. This is a PASS, and it is structural rather
than incidental.

## 6. API gaps

| ID | Gap | Impact | P |
|---|---|---|---|
| API-01 | No read API from State back to Sahodaya (e.g. "did my nominee get approved?") | A Sahodaya admin cannot see the status of a nomination they submitted. They must ask the State office. Operationally painful at scale with many Sahodayas. | P2 |
| API-02 | No webhook/callback on intake approval | Same as above; the Sahodaya learns about approval out of band. | P2 |
| API-03 | No bulk-export contract for the State results | Winners export exists (`state-programs.winners.export`), so this is mostly covered. | P3 |

## 7. Referential integrity notes

- `state_qualifier_entries` carries `source_*_id` values but no foreign key — correct, since the
  source rows live in another database. Integrity is enforced by convention plus the
  `(source_tenant_id, source_event_id, source_registration_id)` tuple.
- Within the State database, the relationships are ordinary Eloquent relations with cascade
  behaviour. **A State-side deletion cascade sweep was not performed.** Given `state_fest_events`
  → `registrations` → `participants` → `marks` → `certificates`, a soft-delete on an event would
  need an explicit policy. P2, and worth doing before the second State program.
- `Tenant` has a parent field used for school → Sahodaya hierarchy, and a comment notes that
  wildcard-appeal registrations are excluded from the "real schools" count. That comment is
  evidence the hierarchy is understood, but manual rule 1 (one school, one Sahodaya at State
  level) was not traced to an enforcement point.

## 8. Academic-year isolation

There is no academic-year entity. Isolation is achieved by having one `FestStateProgram` per year,
which works as long as programs are not created concurrently. There is no guard preventing two
programs from overlapping in time, and no code that treats a program as year-scoped.

**P2 finding.** Low risk for the first State program, real risk for the second if the second is
created while the first's data is still in use.

## 9. Recommended schema changes, in order

1. `state_fest_registrations.points_excluded_from_standings` boolean (DB-01) — unblocks manual
   rule 24.
2. `state_appeals` window support, either `announced_at` on the item result or an
   `appeal_window_minutes` on the event (DB-02) — unblocks manual rule C-1.
3. A unique index on `state_certificates` (DB-04) — cheap, prevents a visible production
   embarrassment.
4. An academic-year entity or a program-overlap guard — before the second State program.

None of these requires a data migration of existing rows beyond defaults, so they are low-risk
additive changes. They should be sequenced so that no two run concurrently on the same
`state_kalotsav` database.
