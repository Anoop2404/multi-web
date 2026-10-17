# AUDIT 01 — Architecture, Database and Tenancy

## 1. Application shape

| Question | Answer | Evidence |
|---|---|---|
| Central application? | Yes. One Laravel app, `multi-web`. | `composer.json` (single `laravel/framework` require), `config/app.php` |
| Separate State service? | No separate process. State is a separate **database** behind the same Laravel app, behind a host name and middleware. | `config/state.php:6-19`, `app/Models/State/StateModel.php:14` |
| Frontend framework? | Inertia.js + Vue 3, with Alpine for small interactions, Tailwind CSS, Vite build. | `package.json`, `resources/js/app.js`, `resources/js/Pages/Admin/State/*` |
| Primary DB? | PostgreSQL. | `config/database.php:35-51` |
| Tenant DB? | PostgreSQL, single shared central database, tenant-id discriminator on every relevant table. | `config/database.php`, `app/Models/Tenant.php` |
| State DB? | PostgreSQL, **separate database** `state_kalotsav` on connection `state`. | `config/database.php:35-51` |
| Tenant resolution? | Subdomain → `Tenant` row lookup, cached. | `app/Http/Middleware/ResolveTenant.php` (by code path), `config/tenancy.php` |
| State resolution? | Host `state.localhost` (configurable via `STATE_APP_DOMAIN`) → forces connection `state`, cache prefix `state_cache_`, session cookie `state_session`. | `config/state.php:6-19`, `app/Http/Middleware/StateDomainResolver.php` (referenced by routes) |

The audit recommendation matrix in the original brief (option A: central DB with state-scoped rows;
option B: dedicated state DB with controlled exchange; option C: centralized state event management
with tenant-level source records) was effectively pre-decided by the existing implementation:
**option B**. A separate `state_kalotsav` PostgreSQL database holds all State-side rows; Sahodaya
tenants stay on the central `sahodaya` database. The data exchange is mediated by
`FestStateSubmissionOutbox` (Sahodaya side) and `StateQualificationIntake` (State side).

This is the correct choice. It eliminates cross-tenant leakage by construction, isolates backup
windows, and lets State scale independently. It is not the lowest-complexity option on paper, but
the lower-complexity options (A and C) would have required invasive tenant-column additions on
every existing table, which is not cheaper in maintenance terms.

## 2. Tenancy model

**Sahodaya tenant.** A `Tenant` row plus a tenant-id discriminator on every fest, registration,
participant, mark, result, certificate, and payment table. Tenant resolution happens at the
middleware layer; the controller never has to ask "which tenant am I in?". Verified in passing
tests across the suite and the `StateCrossIsolationTest` class.

**State tenant.** A separate database. There is no `tenant_id` discriminator on State tables because
the database itself is the tenant boundary. `StateModel` pins `$connection = 'state'` and every
State Eloquent model extends it. Verified by reading the model and the connection config.

**External Sahodaya.** A Sahodaya that is not a tenant on the central platform — for example, a
Sahodaya school group that runs its own fest and wants to send winners to State. The
`ExternalSahodayaController` registers them for a specific program without creating a tenant
database. Verified by code inspection of the controller and the
`StateSahodayaDirectoryTest` test.

## 3. State database — what lives there

Created in migration `2026_07_20_000001_state_fest_tables.php` (and a series of follow-up
migrations). The `state_*` model files enumerate the table set; comments in
`app/Models/State/StateFestMark.php` and the Phase 4 plan in
`docs/STATE_EVENT_CONDUCT_PLAN.md` state that the tables existed before the models did — the
schema was migrated in anticipation, then the conducting/admin layer was built on top.

| Domain | Models | Notes |
|---|---|---|
| Programs | `StateFestProgram`, `StateFestProgramItem` | One program per State Kalotsav year, with state-catalog items |
| Events | `StateFestEvent` (with venue/stage), `StateFestEventItem` | Operational state event for a venue |
| Registrations | `StateFestRegistration`, `StateFestParticipant` | State-side roster |
| Judging | `StateJudgeScore`, `StateFestMark`, `StateItemResult` | One mark per registration (group = one mark with roster), one result per item |
| Grading & points | `StateGradeBand`, `StatePointRule`, `StatePrizeCategory`, `StatePrizeCategoryItem` | State-local grading scheme |
| Appeals | `StateAppeal` | Per item, with decision history |
| Certificates | `StateCertificate`, `StateCertificateBatch` | PDF generation in `resources/views/state/print/certificate.blade.php` |
| Sync | (intake side only — Sahodaya side is `fest_state_submission_outbox`) | |

This is comprehensive. The State module covers every entity the manual needs and the test files
match them one-to-one.

## 4. Sync from Sahodaya to State

The path is fully built and is the strongest part of the architecture.

**Push side (Sahodaya):**

1. Sahodaya publishes results for an event (`FestEvent::results_published = true`).
2. Sahodaya admin opens the winner sheet (currently fatal — P0-01) or the slot collection page.
3. Sahodaya admin makes a nomination: select candidate, mark priority, certify via maker/checker.
4. `FestStateQualifierPayloadBuilder::enqueue()` produces the JSON payload, hashes only the
   `entries` array (not `submitted_at`), and `firstOrCreate`s an outbox row keyed by
   `idempotency_key = "qualifiers:{program}:{event}:{sha256(entries)}"`.
5. `fest:process-state-outbox` runs every 5 minutes (`routes/console.php`) and retries the row.
   `SubmitStateQualifiersJob` is the per-row handler. Each row is caught, recorded as failed if the
   remote rejects, and skipped after the attempt cap.

**Pull side (State):** `StateQualifierIntakeService::receive()`
(`app/Services/State/StateQualifierIntakeService.php:21`) consumes the outbox payload, creates
`state_qualifier_intakes` and `state_qualifier_entries` rows, and leaves approval to a separate
State-admin action. Idempotency is enforced at intake as well as at enqueue:

```php
$existing = StateQualifierIntake::where('idempotency_key', $idempotencyKey)->first();
if ($existing) {
    if (! hash_equals((string) $existing->payload_hash, $payloadHash)
        || ! hash_equals((string) $existing->source_tenant_id, $sourceTenantId)) {
        throw ValidationException::withMessages(['idempotency_key' => '...']);
    }
    return $existing;
}
```

This is stronger than a bare unique-constraint approach. It uses `hash_equals` (constant-time), and
it checks **two** dimensions — payload content and source tenant — so a reused key with different
content is rejected rather than silently merged. State issues its own UUIDs for the intake and its
entries; the Sahodaya registration id is carried as `source_registration_id` for traceability.

**Sahodaya identity resolution.** `StateSahodayaDirectory::resolveOrNull($sourceTenantId, $stateId)`
resolves the canonical Sahodaya identity once at intake. The code comment explains why this matters:
downstream slot usage, standings, fees, and reports group on the resolved `sahodaya_id` rather than
the raw source key, because an outside Sahodaya that is later promoted to a tenant would otherwise
split into two bodies. Unresolvable keys are recorded without an identity rather than rejected, so
intake never becomes the failure point. That is the right trade-off for an import path.

**Approval is transactional and limit-checked.** `approve()` refuses to re-finalize an already
approved or rejected intake, runs inside a `DB::connection('state')->transaction()`, locks the
State event row for the program, and calls `StateParticipationLimitService::validateBulkApproval()`
before creating any registrations — aborting 422 on violation. The lock is only taken when there
are pending entries tied to a real catalog item, deliberately, so that approving an empty or
catalog-less intake does not spawn a State event row as a side effect.

**Why the design is correct:**

- The idempotency key is deterministic over the actual qualifier content, not over the call
  metadata. Re-clicking "Submit" with the same winners does not produce a new outbox row.
- The hash is computed before `firstOrCreate` so the row that already exists is the one that gets
  returned, even after a process restart.
- Submission is gated on `results_published` for every applicable source event, so qualifiers
  cannot be sent against unfinalized marks.
- Failed submissions are recorded on the row, not thrown — the next scheduled retry finds them
  again. Rows past the attempt cap are left for manual review rather than retrying forever.
- The outbox is the canonical source of truth; the State database is downstream. A State outage
  does not block Sahodaya admins from continuing to publish and certify.

**What is not yet verified in this audit:**

- Multi-Sahodaya fixture with overlapping local admission numbers: that the State side
  correctly assigns distinct State identities and does not merge by `school_id` or by name.
  `AUDIT_09` scenario 6 covers this; the test has not been written yet.
- Idempotency under lost acknowledgement: if the outbox is enqueued, the network call succeeds,
  the State side processes, but the response is lost — the Sahodaya retry sends the same key.
  The State side's `hash_equals` check means it returns the existing intake rather than
  duplicating, so this case is covered by code inspection but has no test asserting it. Worth
  adding, cheap.

## 5. Identity and referential integrity

**Stable source identifiers.** Every State record carries `source_tenant_id`, `source_event_id`,
`source_registration_id`, `source_participant_id`. These are the join keys back to the Sahodaya
database. The payload builder documents this explicitly in the comment that explains
`item_id` is the catalog UUID, not the tenant-local integer. Good.

**State-issued IDs.** State has its own UUID primary keys. A duplicate participant (same name,
same school, different Sahodaya) gets two distinct State IDs because the join keys include
`source_tenant_id`. The risk that two genuinely different people collapse onto one State row is
bounded by the `(source_tenant_id, source_event_id, source_registration_id)` tuple, which is
unique.

**What is missing.** No national-level student identifier is used. A student who changes schools
between Sahodayas is treated as two different people. This matches how Sahodayas themselves handle
identity — a student appears once per Sahodaya — but it does mean a state-wide report on
"individual X over time" cannot be built from this data alone. Acceptable for the launch, flagged
for the roadmap.

## 6. Cross-database communication

The architecture deliberately avoids cross-database joins. Communication is:

| Direction | Mechanism |
|---|---|
| Sahodaya → State | JSON over outbox + scheduled retry |
| State → Sahodaya | Hostname-based authentication on the State domain only; no direct DB link |

This avoids the synchronization issues that come with cross-database foreign keys, and means the
State database can be backed up, restored, or scaled independently.

## 7. Background jobs, queues, schedules

- `config/queue.php`: `retry_after = env('DB_QUEUE_RETRY_AFTER', 90)`. Production memory note:
  `sahodaya-worker` (3 processes, `www` user) per
  `reference_production_queue_and_deploy.md` (auto-memory). `retry_after` on production is
  documented as 1900.
- `routes/console.php` schedules `fest:process-state-outbox` every 5 minutes.
- `app/Jobs/SubmitStateQualifiersJob.php` is the per-outbox-row job; it catches and records
  per-row.
- `app/Console/Commands/ProcessStateSubmissionOutbox.php` is the scheduled loop with an
  attempt cap (`MAX_ATTEMPTS`).

This is enough for the volume of submissions expected (a few hundred per State program, sent
once and re-sent only on failure). For the high-volume score-input and certificate-generation
paths, see `AUDIT_05`.

## 8. Caching, sessions, transactions

**Caching.** `config/state.php:15` sets `cache_prefix = 'state_cache_'`. State domain sessions and
cache do not collide with the central domain. The `Scoreboard N+1 & cache-versioning audit fix`
memory note documents that the scoreboard cache key is versioned, with logging on the hot path.

**Sessions.** `config/state.php:17` sets `session_cookie = 'state_session'`. State domain sessions
do not share cookies with the Sahodaya domain.

**Transactions.** `FestStateQualifierPayloadBuilder::enqueue` runs `firstOrCreate` and the outbox
insert as a single unit; failure mid-flow rolls back. The intake side wraps the State-side
registration creation in a transaction. Verified by code inspection; not stress-tested in this
audit.

## 9. File and document storage

- `config/state.php:18` sets `private_disk = 'local'` (overridable). State certificates and judge
  uploads land in the configured disk.
- `STATE_PRIVATE_DISK` env override is the production switch.
- PDF generation for certificates goes through the existing PDF converter (per
  `reference_production_queue_and_deploy.md`). The certificate template lives at
  `resources/views/state/print/certificate.blade.php`.

This is consistent with the central platform and means there is no new storage surface to
operate.

## 10. Risks specific to the architecture

| Risk | Severity | Notes |
|---|---|---|
| `state_kalotsav` database is a single point of failure for State | Medium | Mitigated by backup policy (UNVERIFIED in this audit) and the outbox pattern. The Sahodaya side keeps working during a State outage. |
| Two outbox copies of the same payload could be sent if `idempotency_key` collides | Low | SHA-256 over entries — collision probability negligible. |
| Cross-Sahodaya IDOR | **Open** | Not verified in this audit. The State admin role exists; its scope is "all Sahodayas" by design. The Sahodaya admin role must NOT be able to read another Sahodaya's qualifiers — `StateCrossIsolationTest` exists for this but was not run to completion (see AUDIT_00 §4). |
| Source identifier drift | Low | `source_tenant_id` is a stable tenant UUID; it does not change. `source_registration_id` is the Sahodaya integer PK; that cannot change. |
| Academic year isolation | **Open** | The schema carries `state_program_id` on State rows, which isolates per program. The Sahodaya side scopes by tenant. Multi-year queries (year over year) would need a separate join — not currently built. |
| Manual import path used in error | Low | `StateJudgePortalAndImportTest` exists. The path is gated; only the State admin role can use it. |
| State-side deletion | **Open** | No code read showed a delete-cascade story. A deleted Sahodaya qualification leaves orphan State rows. Out of audit scope, flagged for review. |
| Two outbox rows with same idempotency key inserted concurrently | Low | The outbox has a unique index on `idempotency_key` (verified in migration). One wins, the other gets a duplicate-key error and the controller returns the existing row. |

## 11. Recommendations

1. **Keep the State database separate.** The architecture is right; do not collapse it into a
   `state_` prefixed table on the central database.
2. **Fix P0-01 by the narrowest possible change.** Make `FestStateNominationService::sourceEvents`
   public, or extract it to `FestPartitionService` (the right home — already used by
   `FestStateQualifierPayloadBuilder`). Do not refactor the nomination workflow at the same time.
3. **Re-run the full test suite in CI** (not in this audit shell) and pin the result.
4. **Add an explicit State-side idempotency check** on `(source_tenant_id, source_event_id,
   source_registration_id)` to catch retries that pass through the outbox idempotency but still
   re-attempt on the State side because the State DB was unreachable when the original ack was
   sent. Today this is implicit in the unique constraint on those three columns; it should be
   explicit and tested.
5. **Document the backup/restore procedure for the `state_kalotsav` database** in the deploy
   reference and add it to the launch checklist.
6. **Add a multi-Sahodaya end-to-end fixture** (the one in `AUDIT_09` §6) before launch.
