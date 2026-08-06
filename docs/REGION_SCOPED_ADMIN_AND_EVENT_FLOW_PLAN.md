# Region-Scoped Admin, Event Flow & Food Menu Plan

**Status:** Analysis only — no code changed. Contains one item (Gap A) that should ship ahead of the rest as a standalone security fix.
**Prepared:** 06 Aug 2026. Food menu section added 06 Aug 2026.
**Companion reading:** [REGION_AND_PHASE_KALOTSAV_PLAN.md](REGION_AND_PHASE_KALOTSAV_PLAN.md) (partition engine, combine-at-finale, phases — the foundation this plan builds on), [KALOTSAV_IMPLEMENTATION_PLAN.md](KALOTSAV_IMPLEMENTATION_PLAN.md), [FOOD_MENU_BILLING_PREORDER_PLAN.md](FOOD_MENU_BILLING_PREORDER_PLAN.md) (original design for the priced menu/billing system — written with no region-partitioning awareness, extended below)

## 0. What already exists (confirmed by reading the code)

The partition engine itself is solid and not in scope to rebuild:

| Piece | File | State |
|---|---|---|
| Hub → region-partition child events | `app/Services/Events/FestPartitionService.php` | Working |
| Region membership (school ↔ region per year) | `app/Models/Region.php`, `SchoolRegionAssignment.php`, `SahodayaAdmin/RegionController.php` | Working |
| Auto-sync regions → child events | `app/Services/Events/FestRegionPartitionService.php::syncPartitionsFromRegions()` | Working |
| Combine-scores-at-finale toggle | `combine_regions_at_finale` column, read in `EventContext::scoreboardBySchool()` | Working |
| Conduct Topology self-service UI | `resources/js/Pages/Admin/Sahodaya/Events/Levels.vue` | Working |
| Phases (item grouping) | `FestEventPhase`, `FestEventPhaseService` | Working |
| Competition items auto-copy to region partitions | `FestItemSyncService::copyItemsToPartition()`, called from `spawnPartition()`/`syncPartitionsFromRegions()` | Working |
| Priced food menu + per-school billing | `FestFoodMenuItem`, `FestFoodBill`, `FestFoodOrderItem`, `FestFoodPayment`, `SahodayaAdmin/FestFoodMenuController`, `SahodayaAdmin/FestFoodBillingController` | Working (single-event only, see Gap I/J) |
| Older headcount catering + coupons | `FestCateringOrder`, `FestFoodCoupon`, `SahodayaAdmin/FestCateringController`, `SahodayaAdmin/FestFoodCouponController` | Working (single-event only, see Gap I/J) |

None of this is touched below. Everything in this plan is about the layer on top: **who gets to act on a region's data, and how they reach it.**

## 1. The gaps — what actually needs to be built

### Gap A — Privilege escalation bug (security)
`FestEventStaffController::store()` (`app/Http/Controllers/SahodayaAdmin/FestEventStaffController.php:170-177`) grants the generic `fest_ops` Spatie role — full unscoped access to every event in the Sahodaya — for *any* duty other than `'marks'`, including `'region_admin'`. An admin who assigns someone as "Region coordinator / admin" (the UI's own label) is actually handing them unrestricted access. `EnsureSahodayaAdmin` has no branch for `region_admin` to narrow this back down.

### Gap B — Dead region-scoping middleware
`RegionScope` (`app/Http/Middleware/RegionScope.php`) computes allowed `region_ids` from `UserRegionAssignment` for Spatie role `region_admin`, but is never registered on any route (`bootstrap/app.php`, `routes/*.php` — zero references). Nothing populates `UserRegionAssignment` either. It's inert scaffolding.

### Gap C — No `region_id` on partition events
`fest_events` has `partition_key` (a string slug, e.g. `Str::slug($region->code ?: $region->name)`) and `partition_role`, but **no `region_id` foreign key**. `FestRegionPartitionService::partitionKeyForRegion()` derives the slug at spawn time; there is no stored, queryable link from a child event back to its `Region` row, and no reverse helper either. Any region-aware access control has to re-derive this match every time, which is fragile (region renamed/re-coded → slug drifts → match silently breaks).

### Gap D — Mark entry, ID cards, and payments are not partition-aware
`FestMarkEntryController`, `FestIdCardController`, `FestFinanceController`, `PaymentVerificationController`, and `PaymentReconciliationController` all filter strictly by `event_id` (and, for payments, by tenant-wide `school_id`). None reference `region_id`, `partition_key`, or `Region` at all. Today, a "region admin" only ever sees region-limited data by accident, when the region happens to be implemented as its own child event and that's the only event they've been given access to. Payments have no region concept whatsoever, even accidentally — invoices are billed at event/school level, and a school's region is only known via `SchoolRegionAssignment`, which nothing in the payment stack joins against.

### Gap E — No child-region data inside the parent event's admin pages
On a hub event's admin pages, each region partition is only reachable via a full-page `<Link>` navigation to that child event's own `/events/{id}` tree (`Levels.vue:122`). There's no tab/dropdown/panel on the hub that loads a selected region's participants, marks, or scoreboard in place. `FestPartitionService::combinedScoreboard()`/`scoreboardByPartition()` compute the numbers needed for this but aren't wired into any such UI.

### Gap F — No event+region scoped admin login
The only working scoping is `event_admin` (whole event, via `FestEventStaff.duty = 'event_admin'`, enforced in `EnsureSahodayaAdmin.php:54-77`). It has no region dimension — it reads only `event_id`, never `FestEventStaff.region_id` (which exists on the model/table already, at `app/Models/FestEventStaff.php:24-27`, but is purely informational today). There is no login that says "this person may act on Region X's data within Event Y" and have it actually enforced.

### Gap G — Two parallel, unreconciled food systems
An older headcount/coupon flow (`FestCateringOrder` → `SahodayaAdmin/FestCateringController` → `FestFoodCoupon`, no pricing, manual "mark redeemed" only, no QR/scan) coexists with a newer priced menu + billing flow (`FestFoodMenuItem`, `FestFoodBill`, `FestFoodOrderItem`, `FestFoodPayment`, managed via `SahodayaAdmin/FestFoodMenuController`/`FestFoodBillingController`). Both show up simultaneously in the event nav for sports events (`resources/js/support/sahodayaEventNav.js:165-174`: "Catering", "Food coupons", "Food menu", "Food billing" all present at once). Coupon issuance (`FestFoodCouponController::issueFromCatering()`) is wired only to the old `FestCateringOrder` model — a school using the new priced-menu/billing flow gets no coupons at all. The newer system's own design doc (`docs/FOOD_MENU_BILLING_PREORDER_PLAN.md`) explicitly acknowledges it doesn't replace the old one.

### Gap H — Food coupons are not payment-gated
`FestFoodCouponController::issueFromCatering()` converts any `confirmed` catering order into a redeemable coupon regardless of whether the school has paid anything. `docs/FOOD_MENU_BILLING_PREORDER_PLAN.md` (§3 item 5, §4.6) already flagged this and proposed a `require_payment_for_coupons` flag on `fest_events` — it was never implemented; the column doesn't exist and nothing in the coupon controller checks payment/bill status.

### Gap I — Food setup is not replicated to region partitions
Unlike competition items (auto-copied to every region partition via `FestItemSyncService::copyItemsToPartition()`, called from both `spawnPartition()` and `syncPartitionsFromRegions()`), nothing about food is copied when a hub is split into regions: not the menu items, not `food_payee_type`/`food_host_school_id`, nothing. A Sahodaya admin must manually recreate the entire food menu and payee configuration inside every region's own event page. There is no "apply to all regions" action for food, though the equivalent exists for items.

### Gap J — No hub-level rollup of food data across regions
`FestPartitionService::combinedScoreboard()` sums competition points across a hub's region partitions; nothing equivalent exists for food. `FestEventReportAnalyticsService::exportCateringBySchool()` and `FestReportService::cateringCsv()` both query strictly `where('event_id', $this->event->id)` — a Sahodaya admin wanting total food revenue or headcount across all regions of a partitioned event has to open each region's billing/catering page individually and add the numbers up by hand.

## 2. Proposed design

### 2.1 Fix Gap A now, independent of everything else
In `FestEventStaffController::store()`, stop granting `fest_ops` when `duty === 'region_admin'`. Grant no elevated role at all for that duty — access will instead come from the new region-scoped middleware branch (2.3). This is a one-file change with no schema dependency and should not wait on the rest of this plan.

### 2.2 Add `region_id` to `fest_events` for region-sourced partitions (closes Gap C)
Add `region_id` (nullable `foreignId` → `regions.id`) to `fest_events`. Set it in `FestRegionPartitionService::spawnPartition()`/`syncPartitionsFromRegions()` alongside `partition_key`, using the already-available `$region->id` — no new matching logic needed, just persist what the service already has in hand. Backfill existing region-partition rows by re-running the same slug-derivation the service already uses (`Str::slug($region->code ?: $region->name)` against each hub's regions), since that's the only mapping that has ever existed. Add `FestEvent::region()` (`belongsTo(Region::class)`) for symmetry with `FestEventStaff::region()`.

This is the foundation the rest of the plan depends on — without a real FK, nothing downstream can reliably ask "does this event belong to region X."

### 2.3 Unify region-admin enforcement on `FestEventStaff`, retire the dead path (closes Gap B, Gap F)
`FestEventStaff.region_id` + `duty = 'region_admin'` is already per-event, per-region, and actively populated by the existing Event Staff UI — it's the more precise of the two half-built mechanisms. Standardize on it and stop building out the second one:
- Extend `EnsureSahodayaAdmin` with a `region_admin` branch, structurally parallel to the existing `event_admin` branch: pluck `(event_id, region_id)` pairs from `FestEventStaff` where `duty = 'region_admin'` for the user, and store them as a request attribute (e.g. `regionAdminScopes`).
- For a request resolving to a specific `FestEvent`, allow it if either (a) the event's own `id` matches an allowed `event_id`+`region_id` pair, or (b) the event is a region-partition child whose `region_id` (from 2.2) matches an allowed pair for the parent hub's `event_id`. This is what lets a region admin assigned on the *hub* reach that region's *child* event pages without a separate staff row per child.
- Remove `RegionScope` middleware and `UserRegionAssignment` model/table, or explicitly repurpose them if a future need for tenant-wide (non-event-scoped) region admins shows up — don't leave both mechanisms alive. **Decide which in §7.**

### 2.4 Make mark entry, ID cards, and finance controllers respect the new scope (closes Gap D)
Where `request()->attributes->get('regionAdminScopes')` is present and non-empty, add a `WHERE` filter:
- Mark entry / ID cards: filter registrations by the resolved event's `region_id` matching one of the allowed pairs (mostly a no-op once 2.3 is enforced at the route level, since a region admin can only resolve region-matching events in the first place — but add it as defense-in-depth for any query that spans partitions, e.g. hub-level reports).
- Finance/payments: join `FestEventInvoice`/payment tables through `school_id` → `SchoolRegionAssignment` → `region_id` to filter to the admin's allowed regions. This is new join logic, not present anywhere today (see Gap D) — flag as the highest-effort item in this plan.

### 2.5 Region drill-down on the hub's admin pages (closes Gap E)
Add a region selector to the hub's Overview/Levels page that calls existing `FestPartitionService::scoreboardByPartition()` and new region-filtered mark-entry/ID-card endpoints via Inertia partial reloads, so a *full* Sahodaya admin (not just a region-scoped one) can inspect one region's data without leaving the hub page. This is a convenience feature for full admins; it does not replace the access-control work in 2.3/2.4, and can slip to a later phase without blocking the security-relevant parts of this plan.

### 2.6 Consolidate the two food systems onto one (closes Gap G)
Recommended direction: keep `FestFoodMenuItem`/`FestFoodBill` (priced, billed, actively developed per its own plan doc) as the system of record going forward, and retire `FestCateringOrder`/`FestFoodCoupon` for *new* events rather than patching the old one to feature-parity. Existing events already using the old flow keep it read-only/functional through their event's lifetime — don't migrate historical data. Build coupon issuance against the new system (`issueFromBill()` — issue a coupon per paid order-item quantity, or per settled bill, mirroring `issueFromCatering()`'s shape) so schools on the new flow aren't left without coupons. This is a product decision, not just a technical one — confirmed in §7.

### 2.7 Gate coupon issuance on payment (closes Gap H)
Add the `require_payment_for_coupons` boolean the original food plan proposed but never shipped, on `fest_events`. When set, `issueFromBill()`/`issueFromCatering()` must check the school's `FestFoodBill` is `settled` (or the specific order-item quantity being redeemed is paid) before issuing. When unset, behavior is unchanged from today (issue on confirm, no payment check) — this keeps free/subsidized events working as-is.

### 2.8 Replicate food setup to region partitions (closes Gap I)
Mirror the existing item-copy mechanism: add `FestFoodMenuSyncService::copyMenuToPartition()`, called from the same places `FestItemSyncService::copyItemsToPartition()` is called (`spawnPartition()`, `syncPartitionsFromRegions()`), copying `FestFoodMenuItem` rows and the hub's `food_payee_type`/`food_host_school_id` onto each new region child at spawn time. Add an explicit "Apply food menu to all regions" action on the hub's Food Menu page for hubs that add/edit menu items *after* regions already exist (parallel to however the equivalent item-sync trigger works today), so admins aren't limited to spawn-time-only copying.

### 2.9 Hub-level food rollup (closes Gap J)
Add `FestPartitionService::combinedFoodSummary()` (or a sibling service, kept out of `FestPartitionService` if that class is meant to stay competition-only — implementer's call at build time) that sums `FestFoodBill` totals and `FestCateringOrder`/coupon headcounts across a hub's region partitions, surfaced on the hub's Food Menu/Billing pages when `isPartitionedHub()` is true. Same pattern as `combinedScoreboard()`.

## 3. Concrete change list

| Change | File(s) |
|---|---|
| Database | `region_id` FK on `fest_events`; migration to drop `UserRegionAssignment` table (pending §7 decision); `require_payment_for_coupons` boolean on `fest_events` (2.7) |
| Backend | `FestEventStaffController::store()` (2.1); `FestRegionPartitionService` (2.2); `FestEvent::region()` (2.2); `EnsureSahodayaAdmin` new branch (2.3); remove `RegionScope` + `UserRegionAssignment` (2.3, pending §7); `FestMarkEntryController`, `FestIdCardController`, `FestFinanceController`, `PaymentVerificationController`, `PaymentReconciliationController` region filters (2.4); new `FestFoodCouponController::issueFromBill()` + retire `issueFromCatering()` for new events (2.6); payment-gate check in coupon issuance (2.7); new `FestFoodMenuSyncService` (2.8); `FestPartitionService::combinedFoodSummary()` or sibling service (2.9) |
| Frontend | Region selector + partial-reload panels on hub `Overview.vue`/`Levels.vue` (2.5); "Apply food menu to all regions" action on Food Menu page (2.8); hub-level food summary panel (2.9); nav cleanup once Gap G is resolved (`sahodayaEventNav.js`) |
| No changes needed | `FestPartitionService` (scoreboard logic), `Region`/`SchoolRegionAssignment`, combine-at-finale, phases, `FestItemSyncService` — all already correct |

## 4. Rollout order

**Phase 0 — Security fix.** Item 2.1 only. Ship independently, no schema change, no dependency on anything else in this plan.
Exit: assigning `region_admin` duty no longer grants `fest_ops`; a fresh assignment has zero elevated access until Phase 2 lands.

**Phase 1 — Region FK foundation.** Item 2.2: migration, service update, backfill, model relation.
Exit: every region-sourced partition `FestEvent` has a correct `region_id`; spot-check backfill against `FestRegionPartitionService::partitionKeyForRegion()` output for a live partitioned Sahodaya.

**Phase 2 — Enforcement.** Item 2.3: `EnsureSahodayaAdmin` branch, decision on `RegionScope`/`UserRegionAssignment` fate, remove or repurpose.
Exit: a user with only a `region_admin` staff row for (hub event, region) can reach that region's child event pages and is 403'd on other regions' child events and on unrelated events.

**Phase 3 — Data scoping.** Item 2.4, mark entry / ID cards first (lower effort, defense-in-depth on top of Phase 2's route-level gate), finance last (new join logic).
Exit: a region-scoped admin's mark entry, ID card, and finance views show only their region's rows even when hitting a hub-level or cross-partition query path.

**Phase 4 — Hub drill-down UI.** Item 2.5. Independent of Phase 2/3 completing for scoped admins — this phase serves full admins.
Exit: a Sahodaya admin can view a region's scoreboard/mark status from the hub page without a full navigation.

**Phase 5 — UAT (admin-scoping track).** Extend `docs/STATE_MULTI_REGION_UAT.md`-style table with cases: region_admin duty grants no residual `fest_ops`; region_admin sees only their region across mark entry/ID cards/finance; full admin drill-down renders correct child data; existing `event_admin` and full `sahodaya_admin` flows unaffected.

**Phase 6 — Food system consolidation.** Item 2.6: build `issueFromBill()`, decide and communicate the old-flow retirement for new events, nav cleanup. Blocked on §7 confirming the consolidation direction — do not start until that's answered, since it changes which controller gets deprecated.
Exit: a school ordering through the new priced-menu flow can receive coupons; nav no longer shows four overlapping food menu items for a single event.

**Phase 7 — Payment-gated coupons.** Item 2.7: `require_payment_for_coupons` column + check in whichever issuance path Phase 6 lands on.
Exit: with the flag on, an unpaid/unsettled school cannot have coupons issued; with it off, behavior matches today exactly (no regression for events that don't want payment gating).

**Phase 8 — Region replication for food.** Item 2.8. Independent of Phases 6/7 — can run in parallel with them, or before, since it doesn't depend on which coupon system wins.
Exit: spawning a region partition from a hub with an existing food menu auto-copies it; editing the hub's menu after regions exist and clicking "Apply to all regions" propagates correctly.

**Phase 9 — Hub-level food rollup.** Item 2.9, depends on Phase 8 existing so the numbers being summed are actually populated per region rather than requiring every region to be set up by hand first.
Exit: a Sahodaya admin viewing a partitioned hub's Food Menu/Billing page sees a combined total across all regions, matching the sum of each region's own page.

## 5. Risks & mitigations

| Risk | Mitigation |
|---|---|
| Backfilling `region_id` via slug-matching mismatches on renamed/re-coded regions | Run backfill as a dry-run report first (event id, matched region, confidence) before writing; flag unmatched rows for manual review rather than guessing |
| Removing `RegionScope`/`UserRegionAssignment` breaks something not surfaced by this audit | Grep for both symbols repo-wide immediately before deletion, not just at planning time; keep the migration reversible for one release cycle |
| Finance region-scoping (2.4) introduces a slow query (school→region join across payment tables) | Scope only when `regionAdminScopes` is non-empty (i.e., never for full admins); index `school_region_assignments.school_id` if not already indexed |
| Existing `event_admin` staff rows unaffected by any of this | Confirmed — Phase 2's new branch is additive, only triggers for `duty = 'region_admin'` |
| Retiring the old catering/coupon flow for new events (2.6) breaks a Sahodaya mid-way through using it for an event already underway | Retirement applies to *newly created* events only, gated by a date or explicit flag — never pull the old flow out from under an event already using it |
| `issueFromBill()` (2.6) double-issues coupons if a school pays in multiple installments across several `FestFoodPayment` rows | Issue against settled quantity deltas (already-issued vs. now-payable), not the raw payment event count — mirror how `FestFoodOrderItem::recalculate()` already tracks quantity/amount to avoid re-deriving payment logic from scratch |
| Food menu auto-copy (2.8) copies stale pricing if the hub's menu changes after regions were already spawned and manually edited per-region | "Apply to all regions" (2.8) should warn/diff before overwriting a region that already has its own menu items, not silently clobber |

## 6. Will this break ongoing/existing events and workflows?

No, with two caveats. Phase 0 is a pure restriction (removes an over-grant), so any existing `region_admin`-duty assignment loses `fest_ops` access immediately on deploy — if any Sahodaya is currently relying on that over-grant in production, they will notice loss of access until Phase 2 ships the real enforcement. Recommend deploying Phase 0 and Phase 2 close together, or communicating the gap to any Sahodaya currently using the `region_admin` duty label.

Second, Phase 6/7 (food consolidation, payment gating) must not touch events that are already mid-flow on the old catering/coupon system — scope retirement to new events only, and default `require_payment_for_coupons` to off so existing free/subsidized events don't suddenly block coupon issuance.

Standard (non-partitioned) events and `event_admin`/`sahodaya_admin` flows are untouched throughout.

## 7. Open questions for you to confirm before implementation

1. Retire `RegionScope`/`UserRegionAssignment` outright (this plan's recommendation, §2.3), or keep them for a future tenant-wide (not per-event) region-admin use case? If kept, they still need to be wired up properly rather than left dead.
2. Should a region-scoped admin be able to see payment/finance data at all, or should that stay a `sahodaya_admin`-only surface regardless of region duty? This changes whether Phase 3's finance work is needed.
3. Backfill approach for Phase 1: acceptable to backfill by re-deriving the slug match, or do you want a manual review pass per Sahodaya first given real money/registrations are already attached to these events?
4. Should Phase 0 (the security fix) go out ahead of this plan being fully scheduled, given it's a live over-grant in production today?
5. Food consolidation (§2.6, Phase 6): confirm the direction — keep `FestFoodMenuItem`/`FestFoodBill` as the system of record and retire `FestCateringOrder`/`FestFoodCoupon` for new events, or is there a reason both need to stay live going forward (e.g. some Sahodayas deliberately want the free/headcount-only model and shouldn't be forced into billing)?
6. Should `require_payment_for_coupons` (§2.7) default on or off for *new* events once built? Off preserves current behavior everywhere; on is safer but changes default UX for every new event unless explicitly toggled.
7. Is a region-scoped admin (once Phase 2 lands) allowed to manage food menu/billing for their region, or is food admin always a full-Sahodaya-only surface regardless of the region-admin work in Phases 0-5? Determines whether Gap D's controller-scoping work (2.4) needs to extend to `FestFoodBillingController`/`FestFoodMenuController` too.
8. Priority: should the food track (Phases 6-9) run after the admin-scoping track (Phases 0-5) finishes, or in parallel since they touch different controllers? Phase 8 (region replication) has no dependency on Phases 0-5 and could start immediately if resourced separately.
