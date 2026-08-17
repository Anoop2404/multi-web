# Region-Admin Scoping UAT (2026-08-15)

**Companion plan:** [`REGION_SCOPED_ADMIN_AND_EVENT_FLOW_PLAN.md`](REGION_SCOPED_ADMIN_AND_EVENT_FLOW_PLAN.md) — §8 "Implementation status" for what actually shipped; this document is that plan's **Phase 5 (UAT)**, extending `STATE_MULTI_REGION_UAT.md`'s table format/style with cases specific to region-admin scoping.

**Status of this document:** ready-to-run test plan. **No live database or test runner was available to the author** — none of the cases below have been executed. Nothing in this plan's Phase 0–3 work has been migrated against a real tenant database yet (`REGION_SCOPED_ADMIN_AND_EVENT_FLOW_PLAN.md` §8: "No database migration was actually run"). Run `php artisan migrate` (tenant migrations, including `2026_08_15_000001_seed_region_admin_role_and_revoke_stale_fest_ops.php`) against a real staging tenant before attempting any case below.

**Fixture prerequisites (build once, reuse across all cases):**
- A partitioned Kalotsavam/Kids Fest hub event with at least two region-partition children (e.g. Region A, Region B), each with its own `region_id` populated (per `FestRegionPartitionService::syncPartitionsFromRegions()`).
- At least one school assigned to Region A (`SchoolRegionAssignment`) with a competition item registered, marks enterable, and an ID card eligible for generation.
- At least one school assigned to Region B, same shape, so cross-region leakage is visible rather than merely absent-of-error.
- Three admin user accounts: (1) a fresh user with no prior Sahodaya role, to be assigned `region_admin` duty on Region A; (2) an existing `event_admin` (assigned via `FestEventStaff.duty='event_admin'` on a non-partitioned event, unrelated to the hub above); (3) a full `sahodaya_admin`.
- An MCQ exam belonging to a *different* Sahodaya/tenant (or, at minimum, an MCQ exam not associated with the region-admin's hub — MCQ exams have no `event_id`/region link at all, see note in case 5).

---

## 1. Region-admin duty assignment grants `region_admin` role, not `fest_ops`

| # | Actor/Role | Precondition | Steps | Expected Result | Pass/Fail |
|---|---|---|---|---|---|
| 1.1 | Sahodaya admin | Fixture user has no roles yet | On the hub event's Event Staff page, assign the fixture user with duty = "Region coordinator / admin" and Region = Region A | Assignment succeeds; a `FestEventStaff` row is created with `duty='region_admin'`, `event_id`=hub id, `region_id`=Region A's id | |
| 1.2 | (system) | Follows 1.1 | Inspect the user's Spatie roles (e.g. via `php artisan tinker` or the Portal Users admin page) | User holds role `region_admin`. User does **NOT** hold role `fest_ops`, per `FestEventStaffController::store()`'s `region_admin` branch (`app/Http/Controllers/SahodayaAdmin/FestEventStaffController.php:174-186`), which explicitly skips the `fest_ops` grant for this duty | |
| 1.3 | (system) | Follows 1.1 | Inspect the user's permissions | User holds the default `region_admin` permission set from `TenantUserCatalog::defaultPermissionsForRole('region_admin')`, granted immediately at assignment time (not waiting on `permissions:sync-staff`) | |
| 1.4 | Sahodaya admin | A *second*, pre-existing user was assigned `region_admin` duty **before** migration `2026_08_15_000001` ran (i.e. still holds the stale `fest_ops` role from the old code path) | Run the tenant migration `2026_08_15_000001_seed_region_admin_role_and_revoke_stale_fest_ops.php` | Migration completes without error (creates the `region_admin` role/permissions via `Role::firstOrCreate`/`Permission::firstOrCreate` if they didn't already exist on this tenant) | |
| 1.5 | (system) | Follows 1.4 | Re-inspect the pre-existing user's roles | User now holds `region_admin`. User's `fest_ops` role has been **revoked**, since their only `FestEventStaff` duty is `region_admin` (no other duty row exists that legitimately needs `fest_ops`) | |
| 1.6 | (system) | A *third* user holds `region_admin` duty on one event **and** a `fest_ops`-granting duty (e.g. `registration`) on a different event, before migration `2026_08_15_000001` ran | Run the migration | User keeps `fest_ops` (per the migration's `$hasOtherFestOpsGrantingDuty` check — it must not strip `fest_ops` from a user who legitimately needs it for an unrelated duty). User also gains `region_admin` | |
| 1.7 | Region A admin | Follows 1.1–1.3 | Log in as the fixture user, open the Sahodaya admin panel | Panel loads (region_admin is a real, panel-accessible role per `TenantUserCatalog`); no 403 | |

## 2. Region-admin sees only their assigned region's data

| # | Actor/Role | Precondition | Steps | Expected Result | Pass/Fail |
|---|---|---|---|---|---|
| 2.1 | Region A admin | Logged in per case 1.7 | Navigate directly to the Region A child event's Mark Entry page (its own `/events/{regionAchild}/...` URL) | Page loads; only Region A's registrations/items are listed | |
| 2.2 | Region A admin | — | Attempt to navigate to the Region B child event's Mark Entry page by editing the event id in the URL | 403 "You are not assigned to this event." — `EnsureSahodayaAdmin`'s scope resolution finds no matching `(event_id, region_id)` pair for Region B (`EventRegionAdminScope::matchesRegionScope()`) | |
| 2.3 | Region A admin | — | Navigate to the Region A child event's ID Cards page and generate/print an ID card for a Region A student | Page loads; only Region A students are selectable/visible; ID card generates correctly | |
| 2.4 | Region A admin | — | Attempt the Region B child event's ID Cards page directly by URL | 403, same as 2.2 | |
| 2.5 | Region A admin | — | Navigate to the Region A child event's Finance/Fees or Payment Verification page | Page loads; only Region A schools' fee/payment rows are shown, via `SahodayaAdminController::regionScopedSchoolIds()` filtering applied to `PaymentVerificationController` (index/export/verify/proof) | |
| 2.6 | Region A admin | — | Attempt to open the hub's Payment Reconciliation (credit notes/ledger reposting) page | 403 or feature not reachable — deliberately **not** extended to region-scoped admins per plan §7 open question 2 ("`PaymentReconciliationController` deliberately left untouched") | |
| 2.7 | Region A admin | — | Navigate to the Region A child event's Food Menu and Food Billing pages | Pages load and are naturally scoped to Region A only, since each region is its own `FestEvent` and Phase 2's route-level gate already restricts which event the admin can reach; no cross-region food data visible | |
| 2.8 | Region A admin | Region admin was assigned directly on the **hub** (not on the child), i.e. `FestEventStaff.event_id` = hub id, `region_id` = Region A | Navigate to the Region A child event's Mark Entry page (reached via the hub, not a direct staff row on the child) | Page loads — `matchesRegionScope()`'s "reached via parent hub" branch allows this: the child's `region_id` matches the admin's hub-level scope | |
| 2.9 | Region A admin | Same as 2.8 | Attempt to open the **hub event itself** (its own Overview/Levels page, not a specific region child) | 403 — `matchesRegionScope()` explicitly rejects a hub-level match when the scope's `region_id` is set but the *requested* event is the hub itself with no `region_id` of its own (guards against a region admin using hub-level access to see combined/all-region data) | |
| 2.10 | (fixture edge case) | A `region_admin` `FestEventStaff` row exists with `region_id = null` (e.g. a data-entry mistake, or a scope that predates region assignment) | Log in as that user and attempt any report/mark-entry/ID-card route under the hub | 403 on every route — fails closed rather than silently granting hub-wide access (mirrors the fail-closed case already covered for reports in `STATE_MULTI_REGION_UAT.md` §6 row 7) | |

## 3. Region-admin and MCQ exam routes (the `{exam}` route fix)

| # | Actor/Role | Precondition | Steps | Expected Result | Pass/Fail |
|---|---|---|---|---|---|
| 3.1 | Region A admin | Logged in per case 1.7; an MCQ exam exists that has no relationship to the region admin's hub/region (MCQ exams have no `event_id` FK to `FestEvent` — they're a Sahodaya-wide dataset gated separately by `McqExamStaff`) | Navigate directly to any `mcq-exams/{exam}` admin route (e.g. `GET .../mcq-exams/{exam}`, the exam's marks/leaderboard/registrations pages) | 403, not silently passed through | |
| 3.2 | (rationale check) | — | Confirm via code review that `EventRegionAdminScope::resolveRouteEventId()` returns the sentinel `-1` when the route parameter is `{exam}` rather than `{event}` | `matchesRegionScope(-1, ...)` always returns false (no real event has id `-1`), so `EnsureSahodayaAdmin` denies the request with `not_assigned` — this is the fix for the pre-15-Aug bug where `{exam}` routes resolved to `null` and were let through unchecked for any authenticated GET | |
| 3.3 | Region A admin | — | Attempt an MCQ exam route belonging to a *different Sahodaya* (cross-tenant), if reachable via a guessed/shared URL | 403 — denied both by the region-admin scope fix (3.1) and by the pre-existing tenant-boundary check in `EnsureSahodayaAdmin` (`$user->tenant_id !== $tenantId`) | |
| 3.4 | Full `sahodaya_admin` | Full admin, no `region_admin`/`event_admin` scoping applies | Navigate to any `mcq-exams/{exam}` route for an exam in their own Sahodaya | Loads normally — full admins bypass the scope-resolution branch entirely (regression check; the `{exam}` fix must not affect unscoped admins) | |
| 3.5 | `event_admin` (non-MCQ event) | User holds `event_admin` duty on a Kalotsavam event only, no MCQ staff assignment | Navigate to any `mcq-exams/{exam}` route | 403 — same sentinel-based denial as 3.1; an `event_admin` scope is just as incapable of resolving `{exam}` to a real event id as a `region_admin` scope is | |

## 4. Full `sahodaya_admin` viewing hub event: region visibility (drill-down)

> **Note on scope vs. the original plan, and an update mid-pass:** `REGION_SCOPED_ADMIN_AND_EVENT_FLOW_PLAN.md` §2.5/Phase 4 described a single consolidated "region drill-down panel" on the hub's Overview/Levels page. As of the start of this UAT-writing task, `grep -rn "RegionDrillDown" resources/js` returned no matches and the plan doc's own §8 still listed "Phase 4 — not started" — that check ran concurrently with (and slightly before) a parallel task that built it the same day. **The consolidated panel now exists**: `resources/js/Components/sahodaya/RegionDrillDownPanel.vue`, wired into `resources/js/Pages/Admin/Sahodaya/Events/Levels.vue` (rendered only when `isPartitionedHub && !event.parent_event_id && regionDrillDown.length`), fed by a new `FestPartitionService::regionDrillDownSummary(FestEvent $hub)` method and passed through `FestEventController::levels()`. It shows one card per region — label, venue, status badge, a stat-tile grid (items/registrations count, plus schools/athletes for sports events), and results-published status — with a "View region →" link into that region's own event page. This sits ALONGSIDE the separate per-page "Region Switcher" dropdowns already covering ID Cards/Reports/Fees/Chest Numbers/Mark Entry (built 2026-08-14) — the two mechanisms serve different purposes (one glance-able summary of every region at once, vs. filtering one specific page's rows down to one region at a time) and both are tested below.

**4A — Consolidated drill-down panel (Phase 4, built 2026-08-15)**

| # | Actor/Role | Precondition | Steps | Expected Result | Pass/Fail |
|---|---|---|---|---|---|
| 4A.1 | Full `sahodaya_admin` | Logged in, viewing a partitioned HUB event's Levels page (`event.parent_event_id === null`, has region children) | Load the Levels page | A "region drill-down" card grid renders below the topology hero card, one card per region partition | |
| 4A.2 | Full `sahodaya_admin` | Same page | Read the stat tiles on Region A's card | Items/registrations counts match what Region A's own Overview page shows for the same numbers (same underlying query, per `FestEventController::show()`'s existing single-event stats) | |
| 4A.3 | Full `sahodaya_admin` | Same page | Read the results-published badge on each region card | Badge accurately reflects each region child's own `results_published` flag, independent of the hub's own status | |
| 4A.4 | Full `sahodaya_admin` | Same page | Click "View region →" on Region A's card | Navigates to Region A's own event page at the same URL pattern `Levels.vue`'s existing partition links already use | |
| 4A.5 | Full `sahodaya_admin` | Viewing a LEAF (non-hub) event, or a hub with zero region children | Load that event's Levels page | The drill-down panel does NOT render (regression check — panel must be hub-only) | |
| 4A.6 | Full `sahodaya_admin` | Sports-type hub event specifically | Load the Levels page | Region cards additionally show `schools_count`/`athletes_count` stat tiles (sports-specific fields), not shown for non-sports events | |

**4B — Per-page Region Switcher dropdowns (built 2026-08-14, still the mechanism for filtering an individual page's rows)**

| # | Actor/Role | Precondition | Steps | Expected Result | Pass/Fail |
|---|---|---|---|---|---|
| 4B.1 | Full `sahodaya_admin` | Logged in, viewing the partitioned hub's ID Cards page with no region filter | Confirm the page shows a "Region Switcher" dropdown listing Region A, Region B (and any other partition children) | Dropdown is present and lists all region partitions of the hub | |
| 4B.2 | Full `sahodaya_admin` | — | Select Region A from the Region Switcher on the ID Cards page | Page reloads/filters to Region A's students only; Region B students are not shown; counts match Region A's actual registered/eligible students | |
| 4B.3 | Full `sahodaya_admin` | — | Select Region B from the same dropdown | Page filters to Region B's data only; no Region A rows remain; counts match Region B independently | |
| 4B.4 | Full `sahodaya_admin` | — | Repeat 4B.1–4B.3 on the hub's Reports Hub, Student-wise report, Item-wise report, and Item counts pages | Each page's Region Switcher correctly isolates to the selected region; registration/participant counts shown match that region's actual data (per commit `1b4f20c8` "strictly isolate registration and participant counts to selected region child event" and `106391b2`'s `reportableEventIds` fix) | |
| 4B.5 | Full `sahodaya_admin` | — | Repeat on the hub's Fees page and Chest Numbers page | Region Switcher filters fee/chest-number rows to the selected region only (per commits `0e45c7bf`, `cc1cac05`) | |
| 4B.6 | Full `sahodaya_admin` | — | Repeat on Mark Entry for the hub-adjacent pages that expose a region switcher | Marks entry list is scoped to the selected region's registrations only | |
| 4B.7 | Full `sahodaya_admin` | — | Cross-check: sum Region A's + Region B's counts from the switcher views (4B) against the drill-down panel's own per-region stat tiles (4A) and against the hub's own combined/overall report (no region filter) | All three sources agree — no double-counting, no missing rows, drill-down panel and region-switcher pages never disagree with each other | |

## 5. Regression: existing `event_admin` and full `sahodaya_admin` flows unaffected

| # | Actor/Role | Precondition | Steps | Expected Result | Pass/Fail |
|---|---|---|---|---|---|
| 5.1 | `event_admin` | User holds `FestEventStaff.duty='event_admin'` on a standard (non-partitioned, non-region) event, assigned before 2026-08-15 | Log in, open that event's Overview page | Loads normally, full access to that one event, same as before this pass — Phase 0/2's changes only add a new `duty='region_admin'` branch and do not alter the pre-existing `event_admin` branch in `EnsureSahodayaAdmin`/`EventRegionAdminScope` | |
| 5.2 | `event_admin` | Same event | Register a school for an item, enter marks, publish results, view the finance/reports page for that event | All actions succeed exactly as they did before 06/15 Aug changes; no new 403s, no unexpected region filtering applied (the event has no partitions, so `regionAdminScopes` filtering is a no-op) | |
| 5.3 | `event_admin` | Same event | Attempt to open a *different* event not in their `FestEventStaff` rows | 403 "You are not assigned to this event." — unchanged behavior | |
| 5.4 | Full `sahodaya_admin` | No `event_admin`/`region_admin` duty rows for this user at all | Log in, open the hub event, any of its region children, any standard event, mark entry, ID cards, finance/reports, MCQ exams | Full unscoped access everywhere, exactly as before this pass — the `$scope['applies']` branch in `EnsureSahodayaAdmin` never triggers for a user with a broader role (`sahodaya_admin`), per its own comment: "Users with a broader role... bypass all of this even if they also happen to hold event_admin/region_admin" | |
| 5.5 | Full `sahodaya_admin` | — | Assign a brand-new user as `region_admin` duty on Region A (repeat case 1.1), then immediately verify the full admin's own access to Region A/B/hub is completely unaffected | Full admin's access unchanged; the new region-admin assignment is additive only, scoped to the new user | |
| 5.6 | Full `sahodaya_admin` | — | Confirm the "Sync Partitions from Sahodaya Regions" and "Combine region scores into overall finale leaderboard" controls on the hub's Levels page still function (pre-existing, untouched by this pass) | Both controls work as before; no regression from the region-admin scoping or Region Switcher additions | |

---

## Out of scope / not covered by this pass

- `RegionScope` middleware and `UserRegionAssignment` model/table are marked `@deprecated` in code but **not physically removed** (`git rm` still pending per plan §7 item 1) — no case above exercises them; do not write tests assuming they're gone from the codebase, only that they're inert (never registered on any route).
- Food coupon payment-gating (`require_payment_for_coupons`), the two-food-system consolidation, and hub-level food rollup (`combinedFoodSummary()`) are covered by the main `REGION_SCOPED_ADMIN_AND_EVENT_FLOW_PLAN.md` implementation but are **not** admin-scoping concerns and are intentionally excluded from this document — if a UAT pass for those is needed, it belongs in a separate document scoped to Gaps G/H/I/J.
- Combined-vs-region-wise Result reconciliation against real published marks, and any report beyond Registration Register/Overall Ranking being routed through `FestReportScopeResolver`, remain explicitly uncovered per `STATE_MULTI_REGION_UAT.md` §6's own "Not yet covered" note — this document does not attempt to close that gap either.
