# State Admin — Sahodaya/School Tenant Promotion, Feature Parity & Full UAT Plan

**Date:** 2026-09-23
**Scope:** everything a State admin touches — the seeded Sahodaya master list → real tenants
(subdomain + database) → their schools as tenants → winner registration into the State
Kalotsav → conducting the State finals → reports → UI/UX → end-to-end test coverage.

This plan is written against what is actually in the repo today (section 1). Nothing here
assumes a feature exists unless a file is cited.

---

## 1. Where we actually are today

### 1.1 The Sahodaya master list is seeded as *external* rows, not tenants

| Thing | Where | Status |
|---|---|---|
| `external_sahodayas` / `external_schools` tables | `database/migrations/2026_08_03_000001_external_sahodaya_intake.php` | ✅ exists |
| `district`, `is_appeal_pool`, `source` (manual \| seeded) | `database/migrations/2026_09_15_000001_add_appeal_pool_and_seed_fields_to_external_sahodayas.php` | ✅ exists |
| CSV bulk seeder for the official state list | `app/Console/Commands/State/SeedExternalSahodayas.php` | ✅ exists (`state:seed-external-sahodayas {csv} {state-program-id}`) |
| CSV bulk seeder for their schools | `app/Console/Commands/State/SeedExternalSchools.php` | ✅ exists |
| Access-code portal for those Sahodayas/schools | `routes/web.php:2011-2032`, `app/Http/Controllers/Public/ExternalSahodayaPortalController.php`, `ExternalSchoolPortalController.php` | ✅ exists |
| Intake → review → approve into State | `app/Services/State/ExternalIntakeService.php`, `app/Http/Controllers/StateAdmin/StateQualifierReviewController.php` | ✅ exists |

The migration header says it out loud: these rows are *deliberately* **not** Stancl tenants —
"one-off, low-engagement participants … who just need to hand over a roster". The access code
is the credential; there is no user account, no database, no subdomain, no fest module.

**That is the thing this plan changes.** The ask is: take the seeded list and give each of
those Sahodayas a real tenant — subdomain + dedicated database — and pull their schools in as
school tenants underneath, so they stop being second-class roster-submitters and start being
Sahodayas that run the whole Kalotsav stack.

### 1.2 Tenant provisioning building blocks already exist

| Capability | Where |
|---|---|
| Tenant model (`type` = sahodaya \| school, `parent_id`, `subdomain`, `domain`, `school_prefix`, `school_no`) | `app/Models/Tenant.php` |
| DB-per-Sahodaya (schools share the parent DB) | `config/tenancy.php:41-46` (`database_per_sahodaya`) |
| Configure / create / migrate / seed a Sahodaya DB | `app/Services/Tenancy/SahodayaDatabaseProvisioner.php` |
| Provisioning checklist (tenant_created → database_configured → database_migrated → portal_admin_created → logo_uploaded) | `app/Services/Tenancy/TenantProvisioningChecklistService.php` |
| subdomain/domain → Stancl `domains` table | `app/Support/TenantDomainSync.php`, `php artisan tenants:sync-domains` |
| Bulk DB provisioning | `php artisan sahodaya:provision-databases` |
| Tenant migrations for Sahodaya-type only | `php artisan tenants:migrate-sahodayas` |
| **Precedent for bulk school creation with prefix allocation** | `app/Services/Migration/KannurLegacySchoolImporter.php` (allocates `school_prefix`, creates the school tenant, provisions the admin user inside the parent DB) |

So the plumbing is there. **What does not exist is any path from `ExternalSahodaya` →
`Tenant`.** `grep -rn "promote" app/Services app/Console` returns nothing for this domain.
That pipeline is Phase 2 of this plan.

### 1.3 The State conduct stack is a thin parallel schema, not the fest module

| | Sahodaya/School Kalotsav | State Kalotsav |
|---|---|---|
| Controllers | **56** `app/Http/Controllers/SahodayaAdmin/Fest*.php` | **4** `app/Http/Controllers/StateAdmin/` |
| Vue pages | **102** under `resources/js/Pages/Admin/Sahodaya/Events` (~28,000 LOC) | **18** state pages (~3,080 LOC, several 20-line skeletons) |
| Tables | ~150 `fest_*` tables in `database/migrations/tenant/` | **9** `state_*` tables in `database/migrations/state/` |
| Features | items, phases, regions, caps, eligibility, fees/ledger, registrations, substitutions, appeals, clash review, chest numbers, judging setup + rubrics, mark entry/import, results, championship, certificates, ID cards, food/catering/billing, venues, schedule, TV display, ~40 reports | events, registrations, participants, marks, judge assignments, attendance, chest numbers, publish results |

This is the single biggest gap behind "we need the entire Sahodaya and school Kalotsav
features across the State admin". Building that by duplication is a ~28k-LOC, ~56-controller
rewrite against a second schema. Section 2 is the decision about not doing that.

Two things make the reuse route credible:
- `fest_events.level_round` is already an enum `['state','sahodaya','school']` with a
  `conduct_levels` JSON and `state_program_id` / `conducting_school_id` columns
  (`database/migrations/tenant/2026_06_23_000002_fest_events_multilevel.php`).
- The tenant fest module already models **Region → School** inside one tenant
  (`regions`, `school_region_assignments`, `fest_phase_regions`, `fest_event_phases`), which
  is structurally the same shape as **District → Sahodaya contingent** at State level.

### 1.4 State admin surface today

- Routes: `routes/web.php:78-176` under `state.admin` middleware (`/admin/state-dashboard`,
  `state-programs`, `state-remittances`, `state-workspace/*`, `state-users`, `states`).
- A second, currently inert dedicated-domain route file: `routes/state.php` (gated on
  `STATE_APP_DOMAIN`; its own header flags cross-domain session cookies as unsolved).
- Nav: `resources/js/support/adminNav.js:86-128` (`stateAdminNav()`).
- Scoping: `app/Http/Middleware/EnsureStateAdmin.php` sets `stateId` from `users.state_id`,
  fails closed. `states` table + `state_id` on `users`, `fest_state_programs`,
  `state_remittances` (`2026_10_02_000002_add_state_id_to_central_tables.php`).

**Scoping gap:** `tenants` has **no `state_id`**, and `external_sahodayas` has **no
`state_id`** (only `state_program_id`). A state admin therefore cannot be shown "my state's
Sahodayas" without joining through a program. Phase 1 fixes this.

### 1.5 Known duplicate page trees (real, byte-identical)

`resources/js/Pages/StateAdmin/**` and `resources/js/Pages/Admin/StateAdmin/**` are the same
files twice (`diff -q` clean on `Fest/Index.vue`, and the same for the rest). Any UI/UX work
that edits one and not the other will half-land. Deduping is Phase 5, step 1.

---

## 2. The decision that gates Phases 3–5

> **D1 — How does the State conduct the Kalotsav finals?**

**Option A — build out the `state_*` stack to parity.**
Port 56 controllers and ~28k LOC of Vue onto 9 tables on the `state` connection. Full
control, total isolation. Cost: months, and every future fest feature must be written twice
forever. Not recommended.

**Option B — the State is a tenant (recommended).**
Provision one Sahodaya-shaped tenant per state (e.g. `kerala-state`, its own subdomain and
database). Inside it:
- the State Kalotsav is a normal `fest_event` with `level_round = 'state'`;
- each participating **Sahodaya is a "school"-type child tenant** (the contingent);
- **districts map to `regions`**, which the phase/region machinery already supports;
- every existing feature — chest numbers, judging setup, rubrics, mark entry, results,
  championship, certificates, ID cards, food, schedule, TV display, all ~40 reports — works
  on day one with zero new code.

Keep the existing `state_*` schema for what it is genuinely good at and what is already
built: the **cross-tenant qualifier ledger** (intake → scrutiny → approve) and remittances.
Approved qualifiers get projected into the State tenant as registrations. Nothing is thrown
away; `StateQualifierReviewController` and `StateRemittanceController` stay.

**Recommendation: Option B**, with the `state_*` ledger retained as the intake/audit boundary.

> Phases 1 and 2 are needed under **either** option, so work is not blocked on D1. Confirm D1
> before Phase 3 starts.

> **D2 — Which Sahodayas get promoted?**
> Every seeded external Sahodaya (one database each), or only those that will actually run
> their own Kalotsav on the platform? The pipeline in Phase 2 is designed per-Sahodaya and
> opt-in precisely so this can be decided per row, but the answer changes infrastructure
> sizing (Postgres databases, backups, connection pool) and billing/subscription rows.
> Default assumption in this plan: **opt-in, promoted in batches, un-promoted Sahodayas keep
> the access-code portal working unchanged.**

---

## 2a. Confirmed requirement: two slots per item, per Sahodaya (2026-09-23)

**Rule:** at State level each Sahodaya may enter at most **2 participants per item**.

**What already exists:** `FestStateProgramItem.max_per_school` is exactly this cap — despite
the column name it is enforced **per Sahodaya**, not per school
(`app/Services/State/StateParticipationLimitService::validateEntryApproval()` counts approved
`StateQualifierEntry` rows grouped by `intake.source_tenant_id`). `qualify_count` on the same
row is the separate state-wide global cap. Batch approval already counts two pending entries in
one intake against each other (`validateBulkApproval()`), so the cap cannot be bypassed by
approving an intake in bulk.

**Three real gaps to close:**

1. **No UI at all.** `max_per_school` is validated in
   `app/Http/Controllers/Admin/StateFestProgramController.php:499` but appears **nowhere** in
   `resources/js/Pages/Admin/StatePrograms/Show.vue` (996 LOC) or any other state page. It is
   `nullable` in `2026_06_23_000002_create_fest_state_program_items_table.php:27`, so the
   default today is **unlimited**. The two-slot rule is therefore currently unenforceable from
   the UI — it can only be set by direct DB write. → Add a per-item slots column to the State
   Programs item editor, plus a bulk "set 2 slots for every item" action.
2. **Misleading name.** `max_per_school` reads as a per-school cap and will be misread by the
   next person. Rename to `max_per_sahodaya` (with the old name kept as an accessor for one
   release), or at minimum label it unambiguously in the UI as "slots per Sahodaya".
3. **Enforced only at State approval, not at nomination time.** A Sahodaya can certify and
   submit 5 nominations for an item and only discover at State scrutiny that 3 are rejected.
   → Surface the remaining slot count inside
   `resources/js/Pages/Admin/Sahodaya/Events/StateNomination.vue` and block over-selection
   there, and apply the same check on the external access-code path
   (`ExternalIntakeService::registerToItem()`), so both paths fail early with the same message.

**Test additions** (fold into §8.2): a `StateSlotsPerItemTest` asserting 2 approve, the 3rd is
refused on both intake paths, a bulk approve of 3 pending entries for one item refuses as a
batch, and the global `qualify_count` cap still applies independently of the per-Sahodaya cap.

---

## 2b. What "the State needs the Sahodaya linkage everywhere" means (2026-09-23)

The comparison table in §1.3 is not an argument that the State needs *different* features. It is a
count of how much of the Sahodaya/School Kalotsav stack the State side does not have yet: 56 fest
controllers vs 4, ~150 `fest_*` tables vs 9 `state_*` tables, ~28,000 lines of Sahodaya event UI vs
~3,080 (several of those 20-line placeholders). Everything a State Kalotsav needs — chest numbers,
judging sheets, mark entry, results, championship points, certificates, ID cards, food, venues,
schedule, TV display, the ~40 reports — already exists once, on the Sahodaya side.

**The one genuine difference at State level is the extra level of provenance.** At Sahodaya level a
participant belongs to a *school*. At State level a participant belongs to a *school* **and** to the
*Sahodaya* that sent them, and the Sahodaya is the competing unit: standings, championship points,
contingent rosters, chest-number blocks, certificates and every report are all "per Sahodaya", with
the school shown alongside for identification. So the delta is not new features — it is a second
foreign key, surfaced in every place the Sahodaya-level stack currently shows only a school.

Where it already exists, and where it does not:
- `state_fest_registrations.sahodaya_id` was added for exactly this
  (`database/migrations/state/2026_09_06_000001_add_sahodaya_id_to_state_fest_registrations.php`),
  and `StateParticipationLimitService` groups by `intake.source_tenant_id` — so the thin stack has
  the linkage but almost none of the features.
- The `fest_*` stack has all the features and models the participating unit as a school tenant
  (`fest_registrations.school_id` → `tenants`), plus a one-level grouping layer above it (`regions`
  + `school_region_assignments`, with `partition_group` for a second cut).

Which means, concretely, under Option B (§2): inside a State tenant the **Sahodaya contingent is the
"school"-type child tenant** (so every existing per-school feature becomes per-Sahodaya for free),
**districts are `regions`**, and the **source school travels with the participant as data** rather
than as a second tenant relation — a school tenant already has exactly one `parent_id`, its own
Sahodaya, so it cannot be re-parented under the State. That is the linkage work: carry school name +
school code onto the projected registration, and show it beside the Sahodaya in every state-level
list, card, certificate and report.

Under Option A the same linkage has to be added to 9 tables *and* the other ~140 tables have to be
built first. That asymmetry is the whole reason Option B is recommended.

---

## 3. Phase 1 — Data model & scoping prerequisites — ✅ BUILT (2026-09-23)

Small, low-risk, unblocks everything. No behaviour change on its own.

**Shipped:**
- `database/migrations/2026_09_23_100001_add_state_id_to_tenants.php` — `tenants.state_id`
  (nullable uuid FK → `states`, indexed). Note `states.id` is a real Postgres `uuid`, so the column
  must be `uuid`, not `string`; a `string` FK fails with a datatype mismatch.
- `database/migrations/2026_09_23_100002_add_promotion_link_to_external_intake.php` —
  `external_sahodayas.state_id`/`tenant_id`/`promotion_status`/`promotion_error`/`promoted_at`, and
  `external_schools.tenant_id`/`promoted_at`. (`tenants.id` is a `string`, so those FKs are strings.)
- `Tenant`: `state_id` in `$fillable` **and** in `getCustomColumns()` (Stancl stores anything not
  listed there in the `data` JSON blob), a `state()` relation to `PlatformState` — note the model for
  the `states` table is `PlatformState`, not `State`; `App\Models\State` is a directory of the
  `state_*` models — and a `forState(?string)` scope that fails closed on null, mirroring
  `Support\StateScope::apply()` for callers with no request.
- `ExternalSahodaya`: promotion fields + status constants, `tenant()`/`state()` relations,
  `isPromoted()`/`isPromotable()`, `promoted()`/`pendingPromotion()` scopes.
- `ExternalSchool`: `tenant_id`, `promoted_at`, `tenant()`, same scopes.

**Deferred deliberately:** the `tenants.type` enum question (§3 step 4 below). Nothing in Phase 2
needed it, and it only becomes real under Option B, so no unused column was added.

**Open, needs an operator decision:** most seeded `fest_state_programs` rows still have a null
`state_id`, so promoted tenants inherit null and will not appear in state-scoped listings. The
promote command takes `--state=KL` to stamp it explicitly (and a retry backfills it). Either pass
that on every run, or backfill `fest_state_programs.state_id` once. Guessing "there is only one
state, use it" was rejected — it silently does the wrong thing the day a second state exists.

1. **`tenants.state_id`** (nullable uuid FK → `states`, `nullOnDelete`), backfilled from the
   Sahodaya's state. Lets `EnsureStateAdmin`'s `stateId` scope tenant listings directly.
   *New migration:* `database/migrations/2026_09_23_000001_add_state_id_to_tenants.php`
2. **`external_sahodayas.state_id`** + **`external_sahodayas.tenant_id`** (nullable, FK →
   `tenants`, `nullOnDelete`) and **`external_schools.tenant_id`**. `tenant_id` is the
   promotion link: non-null ⇒ this row has been promoted, portal redirects to the tenant.
   *New migration:* `..._000002_add_promotion_link_to_external_intake.php`
3. **`external_sahodayas.promotion_status`** enum-ish string
   (`not_started | queued | provisioning | ready | failed`) + `promotion_error` text +
   `promoted_at`. Drives the Phase 5 UI and makes the pipeline resumable.
4. **Decide the `tenants.type` question.** `2019_09_15_000010_create_tenants_table.php:21`
   declares `enum('type', ['sahodaya','school'])`. Under Option B the State tenant is
   *shaped* like a Sahodaya. Two choices: reuse `type = 'sahodaya'` with a new
   `is_state_body` boolean (cheap, no enum surgery, recommended), or add `'state'` to the
   enum (Postgres check-constraint rewrite + audit of every `where('type','sahodaya')` call
   site — there are many). **Recommend the boolean.**
5. **Model/scope updates:** `Tenant` fillable + casts, a `StateScope`-aware
   `Tenant::forState()` scope, and `ExternalSahodaya::promoted()` / `pending()` scopes.

**Exit criteria:** `php artisan migrate` clean; `php artisan test --filter=State` still green;
a state admin's tenant queries can be scoped without touching `fest_state_programs`.

---

## 4. Phase 2 — Promotion pipeline: ExternalSahodaya → Tenant (subdomain + database) — ✅ BUILT (2026-09-23)

This is the core of what was asked for. One idempotent, resumable service, driven by both a
console command (bulk) and the State admin UI (single — that UI is Phase 5; the service is ready
for it).

**Shipped:** `app/Services/State/SahodayaPromotionService.php` (`plan()`, `promote()`, `retry()`,
`blockingReason()`, `suggestSubdomain()`, `adminEmail()`) and
`app/Console/Commands/State/PromoteSahodayas.php` (`state:promote-sahodayas`), plus 13 tests in
`tests/Feature/State/SahodayaPromotionTest.php`.

**Verified against the real seeded list (21 Sahodayas on the local Postgres):** dry run produced 21
distinct collision-free subdomains; 4 Sahodayas promoted for real (`idukki`, `palakkad`,
`kottayam`, `central-conclave-kottayam`, `alappuzha`) with database created + migrated + seeded,
profile prefix, host published, `sahodaya_admin` login, and 4 of 5 checklist steps complete
(`logo_uploaded` correctly still pending). A forced failure (`--no-create-db`) left the row
`failed` with the error recorded, the tenant inactive and **no domain published**;
`--retry-failed` then resumed it to `ready` with no second tenant and no second subdomain claim.

**What building it turned up (all now handled, all worth knowing before the UI is written):**

1. **`SahodayaSiteTemplate::apply()` hard-requires a seeded `free` subscription plan** and throws
   without it (`app/Support/SahodayaSiteTemplate.php:37`) — and it silently gives every promoted
   Sahodaya a free `TenantSubscription` valid for 50 years. That partly answers open question §10.3:
   promoted tenants are free by default today, not billed.
2. **`contact_email` on the master list is not always an email** — one seeded row holds a postal
   address (`kottayam 686141`). It is validated before use now; a bad value yields a username-only
   login rather than an unreachable account.
3. **`users.email` is nullable inside a dedicated tenant database but NOT NULL on the central
   connection** (the relaxing migration is tenant-only:
   `tenant/2026_07_20_000001_make_tenant_users_email_nullable.php`). So username-only logins work in
   production's per-Sahodaya databases but not in shared-database setups; there the admin account is
   left for a human to issue and the checklist step stays visibly pending. No placeholder address is
   ever invented.
4. **`users.email` is unique per database, not per tenant** — two Sahodayas listing the same contact
   address only collide in a shared database, but there it would have aborted the second promotion.
   The address is dropped for the second one instead; the promotion completes.
5. **`TenantDomainSync::sync()` deletes the domain rows of an inactive tenant**, so the tenant must
   be activated *before* the host is published. The pipeline creates the tenant inactive on purpose
   and activates + syncs together, which is also what makes the failure path leave nothing reachable.
6. **A shared-database mode exists and must be handled** (`TENANCY_DATABASE_PER_SAHODAYA=false`,
   which the test suite uses): `SahodayaDatabaseProvisioner::ensureReady()` throws there, so the
   database step is branched exactly as `Admin\TenantController::store()` branches it.
7. **`Command::run()` is final-ish** — a console command cannot define its own `private function
   run()`; it collides with `Illuminate\Console\Command::run()` and fails at boot.

**Test status:** all 13 new tests pass. Full `--testsuite=Feature` run compared against a clean
worktree at HEAD (842d11d6): HEAD is 1128 tests / 23 problems, this branch is 1141 tests / the
*same* 23 problems — so 13 added, zero regressions. Those 23 are pre-existing and unrelated
(certificates, phased billing, board results, school application, `ExampleTest`), and one of them,
`Admin\TenantControllerTest::test_creating_a_tenant_writes_an_audit_log_entry`, is simply missing
`SubscriptionPlanSeeder` in its setUp (finding 1 above).

**Runner note:** `php artisan test` runs out of memory partway through and reports
"Premature end of PHP process" at a different, arbitrary test each run. `php -d memory_limit=2G
vendor/bin/phpunit --testsuite=Feature` completes. Worth raising the suite's memory limit so a full
run is reproducible.

**Also built (pulled forward from §5.3 after local UAT):** the access-code portal lockdown. Local
testing showed a promoted Sahodaya's old portal was still fully open and writable, which forks the
roster between two systems with nothing to reconcile them. Now: `show()` renders
`resources/views/external/sahodaya-moved.blade.php` naming the new subdomain, and every write path
(`storeSchool`, `submit`, `importWinners`, `registerItem`, `storeFee`, plus the school portal's
login/legacy-code/store/destroy) redirects instead of writing. The access code deliberately keeps
resolving rather than 404ing — it is already printed on circulars, and a dead link tells a
coordinator nothing. The refusal is a redirect, not `abort(403)`, because the app's 403 page renders
a generic "You don't have permission" and swallows the explanation. Promotion is keyed on
`tenant_id`, never `promotion_status`, so a stale status cannot re-open the portal. Covered by
`tests/Feature/External/PromotedPortalLockdownTest.php` (5 tests).

**Not built (unchanged from the plan):** school migration (Phase 3) and the State admin promotion UI
(Phase 5).

### 4.4 Local UAT results (2026-09-23)

Run against the real seeded master list on local Postgres, dev server on :8000.

| Check | Result |
|---|---|
| Dry run over the full list | 22 rows, 21 promotable, **0 subdomain collisions** |
| Batch promote 14 in one run | 14/14, **39s total (~2.8s each)**, database created + migrated + seeded per Sahodaya |
| Estate audit over all 19 promoted | every DB `ready`, exactly 1 domain row each, admin account present, prefix set, **no problems** |
| Unique subdomains across all tenants | 27/27 |
| Forced failure (`--no-create-db`) | row `failed` with error recorded, tenant inactive, **no host published** |
| `--retry-failed` | resumed to `ready`, **no second tenant, no second subdomain, no second admin** |
| HTTP: promoted subdomains | `idukki` / `palakkad` / `thrissur-central` all HTTP 200, each serving **its own** tenant (`<title>` matches), no cross-tenant leakage |
| HTTP: unknown subdomain | 404 (does not fall through to a tenant) |
| HTTP: admin login with generated credentials | 302 → `/change-password` (correct: `must_change_password` is set) |
| Portal: un-promoted Sahodaya | roster page unchanged, add-school write succeeds |
| Portal: promoted Sahodaya | moved page with new URL; add-school / submit / register-item all refused |
| `state:health` | PASSED (4/4) |
| Test suites | `tests/Feature/State` + `tests/Feature/External` — 65/65 green |

**Local-only limitation:** `/etc/hosts` has per-subdomain entries and no wildcard for
`*.sahodaya.test`, so newly promoted hosts do not resolve in a browser until they are added (they
were verified over HTTP with an explicit `Host:` header, which exercises the same tenant-resolution
path). A wildcard resolver (dnsmasq) would remove this step permanently for local dev.

### 4.1 `App\Services\State\SahodayaPromotionService`

`promote(ExternalSahodaya $ext, array $opts): Tenant` — each step guarded by
"already done? skip", so re-running a half-failed promotion is safe.

| # | Step | Reuses |
|---|---|---|
| 1 | Resolve/validate a **subdomain** — slugify name, collision-check against `tenants.subdomain`, reject reserved | `TenantDomainSync::isReservedSubdomain()`, `TenantController::rules()` |
| 2 | Create the `Tenant` (`type=sahodaya`, `state_id`, `plan`, `is_active=false` until ready, `is_appeal_pool` carried from `external_sahodayas.is_appeal_pool`) | `Tenant::create` |
| 3 | Write the promotion link back (`external_sahodayas.tenant_id`, `promotion_status`) | Phase 1 |
| 4 | **Configure + create + migrate the database** (`suggestedName()`, `ensureReady($tenant, seedDefaults: true)`) | `SahodayaDatabaseProvisioner` |
| 5 | Seed the tenant: `SahodayaProfile` (incl. `prefix` for school codes), `SahodayaSiteTemplate::apply()`, tenant roles/permissions | as in `TenantController::store()` |
| 6 | **Sync domains** so `{subdomain}.{tenant_base_domain}` resolves | `TenantDomainSync::sync()` |
| 7 | Create the **Sahodaya admin user** from `contact_email` / `contact_phone`, with a generated password | `UserCredentialService` (already used by `ExternalIntakeService`) |
| 8 | Mark every `TenantProvisioningChecklistService` step complete as it happens | existing checklist |
| 9 | Flip `is_active = true`, `promotion_status = ready`, `promoted_at = now()` | |
| 10 | Emit a `SahodayaPromoted` event → credentials notification + `PlatformAuditLogger` | existing audit logger |

**Failure handling:** wrap per-Sahodaya, catch, record `promotion_status = failed` +
`promotion_error`, continue the batch. Never leave a half-created tenant `is_active`.

### 4.2 Console command

```
php artisan state:promote-sahodayas
    {--state-program= : only this program}
    {--sahodaya=*     : specific ExternalSahodaya ids}
    {--district=      : promote a whole district}
    {--dry-run        : print the plan (name → subdomain → db name) and exit}
    {--with-schools   : also run Phase 3 school migration for each}
    {--force}
```

`--dry-run` is mandatory in the runbook before any real batch: it surfaces subdomain
collisions and duplicate names *before* a database is created.

### 4.3 Guardrails

- Refuse to promote if `state_program_id`'s program is mid-event and registrations are open,
  unless `--force` (a mid-event promotion would strand in-flight qualifier entries).
- Hard-refuse duplicate promotion (`tenant_id` already set) — surface "already promoted".
- Appeal-pool rows (`is_appeal_pool = true`) are **not** promoted; they stay external.

### 4.5 State admin page sweep (2026-09-23)

Every state-admin page rendered server-side against the live local database (19 promoted
Sahodayas, 4 programs, real intakes), once as superadmin and once as a state admin scoped to
Kerala.

**As superadmin — all 23 pages render, with real data:**
Dashboard, States, State Users, State Programs (index 4 / detail), External Sahodayas (22 rows),
External schools, Kalotsav (index / program / results / winners), Qualifier Intakes (3 intakes),
Qualifier detail, State Finals (1 event, 25 approved qualifiers, 26 registrations), Attendance,
Participation report, Remittances, Tenants.

**Not a bug, but a UX trap:** `/admin/state-programs/{id}/results` and `/winners` return a bare 404
for a `kalolsavam` program. `RESULTS_EVENT_TYPES` in `Admin\StateFestProgramController` is
`['kids_fest','teacher_fest','custom']` on purpose — Kalotsavam results live on the parallel
`/admin/kalotsav/{id}/results` routes (`KalotsavStateController`), which render fine. Two route
families for one concept, and the wrong one dead-ends without pointing at the right one. Worth
consolidating, or at least redirecting, in Phase 5.

**Two real defects found and fixed:**

1. **The Sahodaya tenant list was not state-scoped.** `admin.sahodayas.*` sits behind
   `EnsureStateAdmin` by design (unlike `admin.schools.*` and `admin.tenants.*`, which are
   superadmin-only), but nothing filtered it — a Kerala admin saw every state's Sahodayas, with
   contact details. This is the same gap FRD-13 Finding A closed for programs and remittances, and
   promotion sharpened it by turning the master list into real tenants. Now scoped via
   `StateScope::apply()`, failing closed like the rest of the state admin; superadmin is unscoped.
   Covered by `tests/Feature/State/StateAdminTenantScopeTest.php`.
   *Consequence to know:* tenants with a null `state_id` are invisible to state admins. 11 legacy
   Sahodaya tenants locally still have none — they predate the column and need backfilling (the one
   promoted Sahodaya that had none was promoted before `--state` existed, and was corrected).
2. **The External Sahodayas page advertised dead access codes.** It rendered all 22 rows
   identically — access code plus "Open portal ↗" — with nothing marking the 19 that had been
   promoted, so a state admin would keep handing out codes that now only reach a "moved" page. It
   now shows an "On the platform" badge with the new portal address instead of the code, a
   "Promotion failed" badge with the error, the district, and a summary line
   ("19 of 22 now run on the platform"). Disable/Re-enable is hidden for promoted rows.

**Dead page tree — deleted 2026-09-23.** `resources/js/admin.js` resolves
`./Pages/Admin/${name}.vue`, so the live state pages are `resources/js/Pages/Admin/StateAdmin/**`
and `resources/js/Pages/StateAdmin/**` was resolved by nothing — editing it changed nothing on
screen. Correcting an earlier note in this document: the two trees were **not** byte-identical.
Only 2 of 7 files matched; the live copies were strictly ahead (brand CSS tokens instead of
hardcoded indigo, a `SearchableSelect` `:all-option` fix, and a Qualifiers/Show 6 lines longer), so
the deleted tree was stale, not divergent work. One file, `StateAdmin/BoardResults/Index.vue` (81
lines, a cross-Sahodaya board-results summary), had **no counterpart and no controller rendering
it** — an unreachable stub for a feature that was never wired up. It went with the rest; recover it
from git (`git show ecfe2a44:resources/js/Pages/StateAdmin/BoardResults/Index.vue`) if that view is
still wanted. `npm run build` passes and no bundle chunk referenced the tree. This was Phase 5 step 1.

**Scoping verified as a Kerala state admin:** States master 403; programs 4 → 1; Kalotsav 4 → 1;
intakes 3 → 0; remittances 1 → 0; other states' intake/event/attendance pages 403; Schools list
403; Sahodaya list 30 → 19. All fail-closed.

**Test status after these fixes:** `tests/Feature/State` + `External` + `Admin` = 116 tests,
115 pass, 1 pre-existing failure (`TenantControllerTest::test_creating_a_tenant_writes_an_audit_log_entry`,
missing `SubscriptionPlanSeeder` — confirmed failing at HEAD before this work).

---

## 5. Phase 3 — Migrating the schools (the ones that are not our tenants) — ✅ BUILT (2026-09-23)

**Shipped:** `app/Services/State/ExternalSchoolMigrator.php` and
`app/Console/Commands/State/MigrateExternalSchools.php` (`state:migrate-external-schools`, with
`--all-promoted`, `--dry-run`, `--limit`), plus 8 tests in
`tests/Feature/State/ExternalSchoolMigrationTest.php`.

### 5.0 One design change from what this section originally specified

**Step 6 below (project each school's `StateQualifierEntry` rows into the tenant as students and
registrations) was dropped deliberately. The schema cannot hold it honestly.** `students` requires a
`school_class_id` pointing at a real `school_classes` row plus an `admission_number` unique per
tenant (tenant migration `2026_06_13_000010_simplify_students_to_class_only.php` dropped
`academic_years` and `class_sections` but kept the class FK), and a freshly promoted Sahodaya has
**zero** classes. Projecting would mean inventing a class structure and fake admission numbers that
the school must then clean up before importing its real student list — and a fest registration
additionally needs a `fest_event` to register into, which does not exist yet either.

Nothing is lost by not copying. The entries stay in the State ledger and
`external_schools.tenant_id` — written by this migrator — is exactly the pointer that ties them to
the new school tenant. The migrator reports the count per school instead, so it is a known quantity
rather than a later surprise. Projecting a roster into fest registrations belongs in Phase 4, after
the State event exists to register into.

*Also worth recording:* on this data the question is currently moot — all 21 external schools on the
seeded list are appeal-pool rows, and **zero** of the 51 `StateQualifierEntry` rows belong to an
external school (they all came through the managed-Sahodaya nomination path).

### 5.1a What the migrator actually does

Per school, in order, each step skipped if already done: allocate a prefix unique within the
Sahodaya → create the `type=school` tenant under it (carrying `state_id`; `school_no` left for
`Tenant::schoolCode()` to assign lazily so printed cards never go stale) → write
`external_schools.tenant_id` back → create the `school_admin` login **inside the parent Sahodaya's
database**, reusing the school's existing access-code-portal username *and password* so a
coordinator who already handed those out does not have to re-issue anything → checklist marks →
report the qualifier-entry count. Appeal-pool schools, inactive schools, and schools whose Sahodaya
is not promoted are refused with a reason.

### 5.1b Local rehearsal of the full sequence (2026-09-23)

The seeded list had no real external schools, so the whole cutover was rehearsed end to end on the
`Test External Sahodaya` row: 3 schools created through the access-code portal (two with colliding
names) plus 2 qualifier entries, then promote → migrate → verify.

| Check | Result |
|---|---|
| `--dry-run` | 3 ready, 0 blocked; prefixes `GMHR` / `SJHR` / `SJHR2` — **collision resolved** |
| Migrate | 3/3, each linked back, login created |
| Child tenants | 3 under the Sahodaya, `state_id` carried, all active |
| Prefixes | unique within the Sahodaya |
| Databases | all three resolve to the **parent's** database — no school DB created |
| Logins | `school_admin` in the parent DB, `must_change_password` set, **existing password kept** |
| School codes | `TE-001` / `TE-002` / `TE-003` allocated lazily |
| Roster | 2 entries still in the State ledger, reachable via `external_schools.tenant_id` |
| **Old credentials on the new portal** | `st.joseph.hss.rehearsal` + its original password → **302 → /change-password** |
| **Old external school link** | redirects to login with an explanation (Phase 2's lockdown) |

**Test status:** `tests/Feature/State` + `External` + `Admin` = 124 tests, 123 pass, 1 pre-existing
failure (`TenantControllerTest`, missing `SubscriptionPlanSeeder`, fails at HEAD too).

**Rehearsal data left in the local database:** the promoted `Test External Sahodaya` tenant
(`test-external`, its own Postgres database) and 3 `… Rehearsal` school tenants. Safe to delete
whenever — nothing else references them.

### 5.1 (original specification, kept for reference)

`external_schools` rows under a promoted Sahodaya become **school-type child tenants** inside
the parent's database. Model this directly on `KannurLegacySchoolImporter` — it already
solves prefix allocation and in-tenant admin-user creation.

### 5.1 `App\Services\State\ExternalSchoolMigrator`

Per school, inside `TenancyDatabase::withTenantDatabase($sahodayaTenant, …)`:

1. Allocate a unique **`school_prefix`** within the Sahodaya
   (`allocatePrefix()` logic; uniqueness enforced by
   `2026_06_20_000003_unique_school_prefix_per_sahodaya.php`).
2. Create the school `Tenant` (`type=school`, `parent_id`, `state_id`, name upper-cased by
   the model accessor). No separate database — schools share the parent DB by design.
3. Let `school_no` stay lazily assigned (`Tenant::schoolCode()`), so any card already printed
   never goes stale.
4. Create the **school admin user** from the external row's contact + username
   (`ExternalIntakeService::generateSchoolUsername()` already generates these).
5. Write `external_schools.tenant_id` back.
6. **Carry the roster over:** any `StateQualifierEntry` rows already submitted by that
   external school get projected into the tenant as `Student` + fest registration rows, so a
   Sahodaya that promotes mid-season does not lose what it already typed in. Entries that
   cannot be mapped are reported, not silently dropped.
7. Checklist: `tenant_created` + `portal_admin_created`.

### 5.2 Console command

```
php artisan state:migrate-external-schools {external-sahodaya-id?}
    {--all-promoted} {--dry-run} {--skip-roster}
```

### 5.3 Cutover behaviour for the old access-code portal

Once `external_sahodayas.tenant_id` is set:
- `ExternalSahodayaPortalController::resolve()` and `ExternalSchoolPortalController` return a
  **"this Sahodaya has moved" page** with the new subdomain URL and a login hint, instead of
  the roster form. Old links keep working as redirects — they are already printed in circulars.
- Writes through the external portal are refused for promoted rows (read-only), so the roster
  cannot fork across two systems.

---

## 6. Phase 4 — Winner registration into the State (the actual flow) — PARTLY BUILT (2026-09-23)

Items 1, 3 and 4 below do not depend on decision **D1**; item 2 (projecting approved qualifiers
into a State event) does, and is deliberately not built. Item 3 turned out to contain a live bug
and was done first.

### 6.0 ✅ The per-Sahodaya slot limit (item 3) — fixed

§2a recorded that `max_per_school` had no UI and that the cap was only enforced at State approval.
Building it surfaced something worse: **the three places that apply the cap did not agree on what it
means.**

| Where | Reading of `qualify_count` |
|---|---|
| `FestStateQualifierPayloadBuilder` (managed Sahodaya nominates) | per Sahodaya — positions ≤ N within that Sahodaya's own event |
| `ExternalIntakeService` (outside Sahodaya types entries) | per Sahodaya — "across all schools" under it |
| State Programs UI ("Top 2") | per Sahodaya |
| **`StateParticipationLimitService` (State approves)** | **state-wide total** |

Every item on the seeded Kerala 2026 program has `qualify_count = 2` and `max_per_school = null`,
so with 19 promoted Sahodayas submitting, approval would have accepted **the first two entries in
all of Kerala** and refused every Sahodaya after that — the operator's "two slots each" silently
becoming "two slots for the whole state". Demonstrated by a failing test before the fix.

Fixed: approval counts per Sahodaya; the effective cap is `max_per_school ?: qualify_count`, so
`max_per_school` remains the explicit override for items that qualify fewer (English One Act Play is
top-1). The participation report inherited the same confusion — its compliance table checked
`max_per_school` alone and therefore showed "Unlimited" for all 140 items, and its utilization table
compared a state total against a per-Sahodaya cap; compliance now uses the effective cap and
utilization reports a ceiling of slots × the Sahodayas that entered.

Neither cap had any UI at all — it could only be set by direct DB write. The item editor now has
**Slots per Sahodaya** plus an **Override slots** field, and the item list shows the effective
figure.

Two existing tests encoded the state-wide reading using two different Sahodayas. Their actual
subject was a different race (an entry approved through `reviewEntry()` is not yet materialized into
a registration, so counting must happen at entry level); that is preserved by making both intakes
belong to the *same* Sahodaya, and each gained a counterpart asserting that one Sahodaya filling its
slots never blocks another. New coverage: `tests/Feature/State/StateSlotsPerItemTest.php`.

### 6.1 Still to do in this phase

- **Item 1 — unified intake queue.** Label each intake tenant-sourced vs external, show district,
  filter by both. Independent of D1.
- **Item 2 — projection into the State event.** Blocked on **D1**: whether approved qualifiers
  become registrations inside a State *tenant* (reusing the fest module) or rows in the `state_*`
  tables decides what this service even writes.
- **Item 4 — remittance fee demand.** `StateRemittanceService::calculateDemand()` exists with no UI.
  Independent of D1.

### 6.2 Original specification

Two intake paths converge on one State ledger. Both must work simultaneously during the
transition.

**Path A — promoted / already-tenant Sahodayas (the target state):**
Sahodaya conducts its Kalotsav → results → **Review & Nominate for State**
(`FestStateNominationController`, `resources/js/Pages/Admin/Sahodaya/Events/StateNomination.vue`)
→ certify → `SubmitStateQualifiersJob` → `StateQualifierIntake` + `StateQualifierEntry` rows
on the `state` connection → State admin scrutiny (`StateQualifierReviewController`) → approve.

**Path B — not-yet-promoted Sahodayas (kept alive):**
access-code portal → `ExternalIntakeService::importWinnersFromSpreadsheet()` /
`registerToItem()` → `submit()` → same `StateQualifierIntake` tables → same scrutiny screen.

**Work in this phase:**
1. **One unified intake queue** in the State admin: `StateQualifierReviewController::index`
   currently lists intakes; it must label source (tenant vs external), show district, and
   filter by both — a state admin should not care which pipe a roster came from.
2. **Projection into the State tenant (Option B):** on approve, create/refresh the
   registration inside the State tenant's fest event (participant, item, category, chest
   number eligibility). One service, `StateRegistrationProjector`, idempotent per
   `state_qualifier_entry_id`. This is the seam that lets the whole fest module conduct the
   finals off approved qualifiers.
3. **Quota/limit enforcement at the State boundary** — `StateParticipationLimitService`
   already exists (`tests/Unit/Services/State/StateParticipationLimitServiceTest.php`); wire
   it into both paths so Path A cannot bypass what Path B enforces.
4. **Remittance/fee link:** approved-entry counts → `StateRemittanceService::calculateDemand()`
   (built, never wired to UI — flagged in
   `docs/STATE_KALOTSAV_PENDING_SIGNOFF_AND_LAUNCH_CHECKLIST.md` §3). Wire it now that there
   is real data to verify against; keep the manual `Admin\StateRemittanceController` path.

---

## 7. Phase 5 — State admin feature surface & UI/UX

### 7.1 Kill the duplicate page trees (do this first)

Delete `resources/js/Pages/StateAdmin/**` or `resources/js/Pages/Admin/StateAdmin/**` (keep
one), fix every `inertia('StateAdmin/...')` / `inertia('Admin/StateAdmin/...')` render string
in `app/Http/Controllers/StateAdmin/*`, and add a test asserting no duplicate page names.
Every UI task below is otherwise double work.

### 7.2 Information architecture — `stateAdminNav()` rebuild

Current nav (`adminNav.js:86-128`) is a flat list of 7 links. Target grouping:

| Group | Items |
|---|---|
| **Overview** | Dashboard (live counters: intakes pending, entries approved, Sahodayas promoted, remittance outstanding) |
| **Participants** | **Sahodayas** (new — promoted + pending, with promotion status), **Schools** (new — read-only roll-up), State Users |
| **Intake** | Qualifier Intakes (unified queue), Scrutiny, Appeals |
| **Conduct** | State Programs, State Finals (→ the fest module workspace under Option B), Schedule, Venues, Judges, Attendance |
| **Results** | Results, Winners, Championship, Certificates, Public results |
| **Finance** | Remittances, Fee demand |
| **Reports** | Participation limits, per-district roll-ups, exports |

### 7.3 New screens this plan requires

1. **State → Sahodayas** (`/admin/state/sahodayas`) — the master list with `district`,
   `source`, promotion status pill, and per-row actions: **Promote** (opens a drawer showing
   subdomain + database name + admin email before confirming), **Retry**, **Open portal**,
   **View schools**. Bulk select → promote batch. This is the UI over Phase 2.
2. **Promotion detail / progress** — the 10 steps of §4.1 rendered as the existing
   provisioning checklist, with the error text on failure. Reuses
   `TenantProvisioningChecklistService::statusFor()`, same pattern as
   `resources/js/Pages/Admin/Tenants/Show.vue`.
3. **State → Schools** — schools under promoted Sahodayas, with migration status, prefix,
   school code, admin login state.
4. **Unified Qualifier Intake queue** — replaces the current
   `resources/js/Pages/StateAdmin/Qualifiers/Index.vue` (161 LOC) with source/district/status
   filters, bulk approve, and a scrutiny drawer.
5. **State Finals workspace** — under Option B this is a link into the State tenant's fest
   module, not a new build. `resources/js/Pages/StateAdmin/Fest/Index.vue` (20 LOC skeleton)
   and `Fest/Show.vue` (167 LOC) get retired or reduced to a launcher.

### 7.4 UI/UX consistency pass

Hold every new/edited state page to what the Sahodaya fest pages already do — same
`AdminLayout`, same table/filter/empty-state/pagination components, same toast + confirm
patterns, same mobile behaviour. Concretely: no 20-line skeleton pages, no page without an
empty state, no destructive action without a confirm, no list without search + pagination,
and breadcrumbs registered in `resources/js/support/breadcrumbs.js`.

---

## 8. Phase 6 — Test plan ("test the state admin entirely")

### 8.1 What exists

`tests/Feature/State/` — `StateAdminFlowHttpTest`, `StateFullPipelineTest`,
`StateQualifierIntakeTest`, `StateConductAndRemittanceTest`, `StateIsolationTest`,
`StateCrossIsolationTest`, `FestStateNominationTest`, `StateUserControllerTest`;
`tests/Feature/External/ExternalSchoolPortalAuthTest`;
`tests/Unit/Services/State/StateParticipationLimitServiceTest`;
`tests/e2e/07-state-admin.spec.ts`; plus `php artisan state:health` and
`php artisan state:pilot-drill` (an existing 1-managed + 1-external end-to-end drill).

### 8.2 New automated coverage required by this plan

| Test | Asserts |
|---|---|
| `SahodayaPromotionTest` | promote creates tenant + subdomain + DB + profile + admin user + domains row; checklist all-complete; `external_sahodayas.tenant_id` set |
| `SahodayaPromotionIdempotencyTest` | re-running promote on a `ready` row is a no-op; re-running on a `failed` row resumes from the failed step; no orphan DB |
| `SahodayaPromotionFailureTest` | DB-creation failure leaves `promotion_status=failed`, tenant inactive, error recorded, batch continues |
| `SubdomainCollisionTest` | two Sahodayas with colliding slugs both get valid distinct subdomains; reserved subdomains refused |
| `ExternalSchoolMigrationTest` | schools become child tenants in the parent DB, unique prefix per Sahodaya, admin user created, `school_no`/`schoolCode()` behaves |
| `RosterCarryOverTest` | pre-existing `StateQualifierEntry` rows land as students/registrations; unmappable rows reported not dropped |
| `PromotedPortalLockdownTest` | promoted Sahodaya's access-code portal is read-only + redirects; un-promoted one still fully works |
| `UnifiedIntakeQueueTest` | tenant-sourced and external-sourced intakes both appear, correctly labelled, correctly filtered |
| `StateRegistrationProjectionTest` | approve → registration inside the State tenant; double-approve does not double-register |
| `StateTenantScopeTest` | state admin A cannot see state B's Sahodayas/schools/intakes (extends `StateCrossIsolationTest`) |
| `StateStaffReadOnlyTest` | `state_staff` gets 403 on every promotion/migration POST |
| `NoDuplicatePageNamesTest` | the `StateAdmin` page-tree dedupe cannot regress |

### 8.3 Manual UAT script (the "test the state admin entirely" pass)

Run on a staging copy with a real slice of the seeded master list. Each step has a pass/fail box.

**A. Access & scoping**
1. Log in as `state_admin` for State A → only State A's programs/Sahodayas/intakes visible.
2. Log in as `state_staff` → every list loads; every create/approve/promote button is absent
   or 403s.
3. State user with `state_id = null` → sees nothing (fail-closed), no 500.

**B. Master list & promotion**
4. `/admin/state/sahodayas` — count matches the seeded CSV; districts correct; appeal-pool row
   present and *not* promotable.
5. `state:promote-sahodayas --dry-run` — plan printed, no DB created, collisions flagged.
6. Promote one Sahodaya from the UI → all 10 checklist steps green.
7. Visit `https://{subdomain}.{base-domain}` → Sahodaya portal loads.
8. Log in with the generated admin credentials → Sahodaya dashboard, empty Kalotsav workspace.
9. Force a failure (bad DB credentials) → status `failed`, error shown, Retry succeeds.
10. Promote a batch of 5 → all succeed; one deliberately broken row does not abort the batch.

**C. Schools**
11. `state:migrate-external-schools --dry-run` then real run → schools appear as child tenants.
12. Prefixes unique per Sahodaya; `schoolCode()` renders (e.g. `MCS-027`); school admin logins work.
13. A school that had already submitted entries externally → roster present after migration.
14. Old external school portal link → redirect + "moved" message, no write possible.

**D. Winner registration (both paths)**
15. **Path A:** promoted Sahodaya runs an event → results → nominate → certify → submit →
    intake appears in the State queue labelled *tenant*.
16. **Path B:** an un-promoted Sahodaya uses its access code → import winners spreadsheet →
    register to item → submit → intake appears labelled *external*.
17. Participation limits refuse an over-quota nomination on **both** paths with the same message.
18. Approve both intakes → registrations appear in the State finals workspace; re-approve is a no-op.

**E. State conduct & results**
19. Assign chest numbers; verify no duplicates across both intake sources.
20. Assign judges, enter marks, publish results; unpublished results are invisible publicly.
21. Winners export + Top-3 report render with the right school/Sahodaya attribution.
22. `/state/results` public page shows only published items.

**F. Finance**
23. Fee demand calculated from approved entries matches a hand count for one district.
24. Sahodaya uploads remittance proof → state admin verifies/rejects → audit trail recorded.

**G. UI/UX sweep** (every state screen)
25. Empty state, loading state, error state on each list.
26. Search + pagination on each list; filters persist across reload.
27. Mobile ~400px: no horizontal scroll on any page.
28. Every destructive action confirms; every long action shows progress.
29. Breadcrumbs correct on every page; back-navigation never dead-ends.
30. Keyboard: all primary actions reachable; focus visible.

**H. Infrastructure**
31. `php artisan state:health` green; `state:pilot-drill` passes post-promotion.
32. Backup/restore rehearsal on one promoted Sahodaya DB (P-12: RPO ≤ 15 min / RTO ≤ 2 hr).
33. Connection-pool check with N promoted databases under concurrent load.

---

## 9. Rollout & rollback

**Order:** Phase 1 → Phase 2 (dry-run, then 1 pilot Sahodaya) → Phase 3 (that pilot's schools)
→ full Phase 6 §8.3 A–C on the pilot → confirm **D1** → Phase 4 → Phase 5 → batch promotions
by district → Phase 6 §8.3 D–H.

**Pre-flight for any batch:**
```bash
php artisan state:health
php artisan state:promote-sahodayas --state-program=<uuid> --dry-run
```

**Rollback per Sahodaya:** demote = set `is_active=false`, clear `external_sahodayas.tenant_id`
and `promotion_status`, re-enable the access-code portal, **retain** the tenant database
(never auto-drop — a dropped DB is unrecoverable and the roster may have been edited). Add
`state:demote-sahodaya {id} --keep-database` with `--keep-database` as the default and no
non-interactive drop path.

**Batch sizing:** promote by district, not all at once — each promotion is a new Postgres
database, a new backup target and new pool connections.

---

## 10. Open questions

1. **D1 — still open.** Answered "state has a separate DB", which is true under *both*
   options and so does not decide it: Option A's `state` connection (`state_kalotsav`, see
   `config/database.php:35`) is a separate database, and Option B's State tenant also gets its
   own dedicated database. The actual question is **which schema conducts the finals** — the
   9-table `state_*` schema (needs ~56 controllers ported) or the ~150-table `fest_*` schema
   inside a State tenant (works today). Everything from Phase 4 on assumes Option B.
2. **D2 — still open.** Answered with the two-slots-per-item rule (now captured as §2a), which
   is a participation-limit requirement rather than a promotion-scope answer. Still needed:
   promote *all* seeded Sahodayas (one Postgres DB each, needs upfront infra + billing
   sizing), or opt-in per Sahodaya in district batches (this plan's current assumption)?
3. Do promoted Sahodayas get a **subscription/plan** row and billing, or are state-promoted
   tenants free for the Kalotsav season?
4. Subdomain naming convention — `{slug}.{base}` vs `{district}-{slug}.{base}`? Names in the
   official list are likely to collide across districts.
5. Do promoted Sahodayas get the **public website** stack (`SahodayaSiteTemplate::apply()`
   is currently part of tenant creation) or a Kalotsav-only cut-down portal?
6. What happens to a Sahodaya promoted **mid-season** with entries already submitted through
   the external portal — carry over (this plan's default) or freeze the external roster and
   start clean?
7. `STATE_APP_DOMAIN` / `routes/state.php` — do we activate the dedicated State domain now
   (needs the cross-domain session-cookie work its own header flags as unsolved), or keep the
   State admin on the central `/admin/*` domain?
