# Food Module Audit — Kalotsav/Sahodaya Platform

**Date:** 2026-08-17 · **Auditor:** Claude (Cowork) · **Scope:** the food/catering module end-to-end — models, controllers, services, Vue/Inertia pages, print templates, and the phase/region architecture governing how food data is scoped.

**Method:** Unlike a full-platform sample, this module was read in full depth, not sampled — every model, controller, service, and Vue page listed in the Appendix was read completely, cross-referenced against migrations and routes, and the highest-severity claims below were independently re-verified against source by a second pass before being written up here.

**Important context:** This module is under active, uncommitted development right now. `git status` shows `FestFoodOrderController.php`, `FestEventPhase.php`, `FestEventPhaseController.php`, `FestEventPhaseService.php`, `FestPhaseLifecycleService.php`, `FoodMenu.vue`, `FoodOrder.vue`, `FoodBillingShow.vue`, `FoodHostBillingShow.vue`, and a phase-lifecycle test file as modified-but-not-committed, alongside ~10 brand-new untracked files implementing a `phased_regional_billing` workflow. The findings below describe the code as it stands today, mid-change — some may already be in flight.

---

## TL;DR

- **Two parallel food systems coexist and were never consolidated**: a legacy free headcount flow (`FestCateringOrder`) and a newer priced menu/billing flow (`FestFoodBill`). Both are live for every event, both feed the same coupon table, and a documented plan to retire the old one on new events was never carried out.
- **The payment-gating flag has a real bypass.** `require_payment_for_coupons` is enforced on the priced-billing coupon path but is *architecturally absent* from the legacy catering path — confirmed by reading the code, not inferred. A school can get free meal coupons on a "payment required" event simply by using the older form.
- **A confirmed money bug**: removing an order item after a payment was recorded can drive a bill's balance negative with no guard, and the bill can still be marked "settled" in that state.
- **The one UI action you'd need to fix a mistake — voiding a payment — doesn't exist anywhere in the interface**, even though the backend route and controller method are fully built. Right now there's no way for anyone to correct a wrongly-entered payment except editing the database directly.
- **Food has no phase concept at all** — zero `phase_id` column on any food table. The only way food differs "per phase" today is via a heavier mechanism (spawning an entirely separate child event per phase), and the menu-copy step that seeds those child events is a one-time, additive-only copy that **silently goes stale** the moment a price changes afterward — a risk that was written down and flagged for mitigation before shipping, and then shipped without the mitigation anyway.
- **Navigation is genuinely confusing**: four similarly-named "food" screens sit flat in one menu, the school-side entry point to one of them isn't even a direct nav link, and a host school gets two disconnected UIs for managing its own bill.
- On the positive side: this module is one of the *cleanest* in the app on native-dialog usage (a known platform-wide problem elsewhere), tenant/ownership authorization checks are consistently correct everywhere traced, and the one genuinely concurrent money operation (`recordForBill`/`voidPayment`) is properly lock-guarded.

---

## 1. Architecture today

```
Legacy flow:  FestCateringOrder (headcount, no price)
                 → confirm/cancel (Sahodaya admin or Portal "food"-duty staff)
                 → FestFoodCoupon::issueFromCatering()   [NOT payment-gated, by design]

Priced flow:  FestFoodMenuItem (priced, per event/day/meal)
                 → FestFoodBill (per school, one open bill per event)
                     → FestFoodOrderItem (line items, price snapshotted at order time)
                     → FestFoodPayment (one or more payments against the bill)
                 → FestFoodCoupon::issueFromBill()        [payment-gated IF require_payment_for_coupons]
```

Every food table — `fest_food_menu_items`, `fest_food_bills`, `fest_food_order_items`, `fest_food_payments`, `fest_catering_orders`, `fest_food_coupons` — carries `event_id` and nothing else in the way of scoping. None have a `phase_id`. A `FestEvent` can be a standalone event, or (via `workflow_mode = 'phased_regional_billing'`) a **root** whose phases spawn independent **leaf events**; food data always lives on whichever event row it was created against, root or leaf, with no cross-event relationship except read-only reporting rollups.

---

## 2. Direct answer: should food span events / be organized per phase?

**Today, it can't be — not without a schema change — and the one mechanism that stands in for it has a known, unfixed staleness bug.**

- The only phase-aware food logic anywhere in the codebase is a single cutoff check in `FestFoodOrderController::assertAccess()` (`app/Http/Controllers/SchoolAdmin/FestFoodOrderController.php:25-31`), which blocks new orders past a phase's `food_cutoff_at` — and even that hand-rolls its own lookup instead of using the shared `FestPhaseLifecycleService` resolver every other phase-aware area is meant to go through.
- "Per phase" food today actually means "per **leaf event**": each phase (optionally × region) can spawn its own child `FestEvent`, and because that child has its own `event_id`, it gets its own independent menu/bills/coupons. This is a real, working mechanism — but your own planning doc calls it a deliberate **"escape hatch, not the default path"** for exactly this situation, not a first-class phase-scoping feature.
- Getting the *same* menu onto every leaf is manual and **does not stay in sync**: `FestFoodMenuSyncService::copyMenuItemToPartition()` (`app/Services/Events/FestFoodMenuSyncService.php:51-77`) matches on `(date, meal_type, name)` only, and **silently no-ops if a match already exists** — it never updates price, description, or availability on an item a leaf already has. Edit the hub's price after leaves exist, click "Apply menu to all regions," and every leaf that already has that item keeps the old price forever, with zero warning to the admin. This exact risk was written into `docs/REGION_SCOPED_ADMIN_AND_EVENT_FLOW_PLAN.md`'s risk table before it shipped ("should warn/diff before overwriting a region that already has its own menu items, not silently clobber") — the mitigation was never built; the shipped behavior is more silent than even the un-mitigated risk description implies.
- A combined cross-leaf view exists — `FestPartitionService::combinedFoodSummary()`, surfaced on the Sahodaya Food Billing page when viewing a hub — but it's **read-only** and **filters to `partition_role === 'region'` children only**. Leaves spawned for a non-regional phase (`partition_role === 'phase'`) still accumulate real bills and coupons but are silently excluded from this rollup. The CSV export on the same page uses a different, broader scope (`reportableEventIds()`, no role filter), so the on-screen combined total and the exported total can legitimately disagree for the same hub.
- This is explicitly acknowledged as unfinished: a migration docblock (`2026_09_17_000001_add_lifecycle_fields_to_fest_event_phases.php`) states wiring food (among ten operational areas) to actually enforce phase lifecycle rules is *"separate, larger work — not done in this migration,"* and `FestPhaseLifecycleService`'s own class docblock defers to *"the final status report"* for which areas are actually wired — a document that, as far as could be found, doesn't exist under that description. One doc (`MCS_FOUR_PHASE_COMPLETION_PLAN.md`) claims the food-ordering-cutoff milestone is *"Implemented and verified"* against passing regression suites, but the two phase-lifecycle test files in `tests/Feature/` contain **zero mentions of "food"** — the claim of test coverage for this specific behavior doesn't hold up.

**Recommendation.** The two things labeled "phase" in this codebase solve different problems, and food currently can't use the cheaper one:

- **If the goal is different menus/prices/cutoffs within the same event** (the common case — e.g., a zone-level meal price differs from the state-level price, but it's still logically one event's food operation): add a nullable `phase_id` to `fest_food_menu_items` (and to `fest_food_bills`/`fest_catering_orders`/`fest_food_coupons` if per-phase billing/reporting matters too), exactly mirroring how `fest_school_event_fees` already solved this identical problem for fees. Filter menu queries by the registrant's resolved phase, and route the cutoff check through `FestPhaseLifecycleService` instead of the current ad hoc lookup so it behaves consistently with the other nine areas that service already governs.
- **If the goal is genuinely independent operations per phase** (different host school, different physical venue/kitchen, effectively a different event) — the existing leaf-event mechanism is the right tool and needs no new schema. But fix the sync-drift problem first (§ Finding 5) — it will produce wrong prices the first time anyone edits a hub price after regions/phases already exist, which given the current rollout is likely to happen soon if it hasn't already.
- Either way, make an explicit decision between these two models rather than continuing to grow both in parallel — right now the codebase has leaf-events for whole-competition-level scoping *and* an ad hoc phase lookup for cutoffs only, with no stated line between them.

---

## 3. Backend / workflow findings

### Finding 1 — `require_payment_for_coupons` has a complete, working bypass [Critical]
**Verified directly.** `FestFoodCouponController::issueFromCatering()` (`app/Http/Controllers/SahodayaAdmin/FestFoodCouponController.php:50-89`) never references `require_payment_for_coupons` anywhere — its own docblock states this is intentional, since `FestCateringOrder` has no price concept to gate on. But nothing stops a school from using the free legacy catering form (`FestEventPortalController::storeCatering()`) on an event that has payment required turned on, nothing stops that order from being confirmed (by a Sahodaya admin *or* by any staff member with only a "food" duty assignment via the Portal kitchen screen — a materially lower trust bar), and nothing stops `issueFromCatering()` from then printing a full coupon for it.
**Failure scenario:** Event has `require_payment_for_coupons = true`. School submits a 200-headcount lunch request via the old Catering form (free). A food-duty volunteer confirms it from the Kitchen ops screen. Sahodaya admin clicks "Issue from confirmed catering." 200 coupons are issued with zero rows ever created in `fest_food_bills`/`fest_food_payments`. The payment gate is never touched.
**Fix direction:** either retire the legacy flow for events with payment required, or add the same gate (block confirm, or block issuance) when `require_payment_for_coupons` is true.

### Finding 2 — Removing a paid order item can drive a bill to a silently-negative balance, and it's still settleable [Critical]
**Verified directly.** `FestFoodBill::recalculate()` (`app/Models/FestFoodBill.php:76-81`) re-derives `amount_total` from current order items and `amount_paid` from payments independently — nothing reconciles them against each other. None of the three `removeItem()` implementations (Sahodaya, School self-service, School host-billing) check whether removing an item would drop the total below what's already been paid.
**Failure scenario:** Bill totals ₹100, school pays ₹100 in full (`amount_paid = 100`, balance = 0). Admin removes a ₹40 item. `recalculate()` sets `amount_total = 60`; `amount_paid` stays 100; `balanceDue() = -40`. The settle button's guard only checks `balanceDue() > 0`, so a negative balance passes straight through — the bill can be marked "settled" with ₹40 collected and unaccounted for, no refund entry, no warning anywhere.
**Fix direction:** block item removal (or require a corresponding refund/credit action) once payments exist that would be orphaned, and treat a negative balance as a distinct, flagged state rather than "no balance due."

### Finding 3 — `settle()`, `cancel()`, and `reopen()` are unlocked and can race against concurrent bill mutations [Critical]
**Verified via code tracing.** Only `FestFoodPayment::recordForBill()` and `voidPayment()` (`app/Models/FestFoodPayment.php:60-124`) open a `DB::transaction()` with `lockForUpdate()`. `settle()`, `cancel()`, and `reopen()` (all three controllers) read the bill, check a condition against the in-memory value, then `update()` — with no lock in between.
- **Stale-read settle:** Admin A reads balance = 0 and starts settling. Before A's write lands, staff B adds an item and its `recalculate()` commits first. A's `update()` only writes the dirty `status`/`settled_at` columns (Eloquent partial-update semantics), so it doesn't clobber B's total — the result is a bill marked `settled` with a real, uncleared balance.
- **Stale-read cancel:** Admin reads `amount_paid = 0` and passes the "no refund needed" guard; a payment commits concurrently (inside its own lock); the cancel's `update()` proceeds once unblocked — result is a `cancelled` bill with money already collected against it, exactly the state that guard exists to prevent.
- **`reopen()` has no status guard at all** (`FestFoodBillingController.php:238-250`, `FestFoodHostBillingController.php:222-231`) — it can flip a `cancelled` bill straight back to `open`, contradicting the adjacent code comment that calls cancelled "a terminal state."
**Fix direction:** wrap `settle`/`cancel`/`reopen` in the same `lockForUpdate()` transaction pattern already proven out in `recordForBill()`, and re-check the condition after acquiring the lock, not before.

### Finding 4 — No cascade when an event is cancelled [High]
**Verified.** `FestEventStatusService` (invoked from `FestEventController.php:607-609` on cancellation) has zero references to any food model. Contrast with registration fees, which get an explicit reversal on the same trigger. A school that fully paid a food bill before the event was cancelled has no refund/credit trail, and any coupons already issued for that event stay `issued` indefinitely.

### Finding 5 — Menu sync to regional/phase leaf events is copy-once-then-drift, with the pre-identified mitigation never built [High]
Covered in full in §2 above. Restated as a standalone finding because it's independently actionable: add either a real re-sync (diff and update matched items, not just skip) or, at minimum, a visible staleness indicator ("3 regions have an older price for this item") before the next region/phase is spawned.

### Finding 6 — `voidPayment()` hard-deletes the payment row with no reason/audit field, and (per the UX pass) is currently unreachable from any screen [High]
**Verified.** `FestFoodPayment.php:121` calls `$this->delete()` directly — there's no `voided_at`/`voided_by`/`void_reason` column, so once called, the payment is simply gone with no durable record of amount, reason, or actor. Combined with the UI finding below (Finding 12) that no page currently renders a way to trigger this action at all, this is currently dead, and if wired up as-is it would be an unaudited, irreversible action for something that specifically exists to correct a financial mistake.

### Finding 7 — No unique constraint backing coupon dedup; concurrent issuance can double-issue [High]
**Verified.** Both `issueFromCatering()` and `issueFromBill()` do a check-then-create loop (`exists()` then `create()`) that is not wrapped in the same lock as coupon code generation. The migration only uniques `coupon_code`, not `(event_id, school_id, valid_date, meal_type)`. Two admins clicking "Issue Coupons" around the same time can both pass the `exists()` check and create duplicate coupons for the same school/date/meal.

### Finding 8 — The event-day Kitchen ops screen is blind to the priced billing system [High]
**Verified.** `Portal\FestEventOpsController::kitchen()` (813-line controller) reads exclusively from `FestCateringOrder` — zero references to `FestFoodBill`/`FestFoodOrderItem` anywhere in the file, and there is only one Kitchen Vue page. An event that has fully moved onto the priced menu/billing flow shows its own food-duty floor staff an empty kitchen board on event day.

### Finding 9 — Catering order status has no transition guard [Medium]
**Verified.** Neither `FestCateringController::updateStatus()` nor the Portal equivalent checks the *current* status before applying a new one — `cancelled → confirmed` is accepted silently, and since `issueFromCatering()` only checks the current value (`= 'confirmed'`), a re-confirmed previously-cancelled order becomes coupon-eligible again with no trace of the earlier cancellation.

### Finding 10 — `'void'` coupon status exists in the schema but no code path ever sets it [Medium]
**Verified.** The DB enum allows `issued|redeemed|void`, but no controller anywhere transitions a coupon to `void`. There is currently no way to invalidate a mis-issued coupon short of a manual database edit — directly relevant given Finding 7's double-issue risk.

### Finding 11 — School-side host-billing actions are completely unaudited [Medium]
**Verified.** The Sahodaya-side billing/coupon controllers consistently call `PlatformAuditLogger`. `SchoolAdmin\FestFoodHostBillingController` never imports or calls it at all — every money-touching action a host school performs (add/remove item, record payment, settle, void, reopen) leaves no entry in the central audit log. A host school is handling *other schools'* payments here, which makes the missing audit trail more consequential than it would be for a school managing only its own data.

### Finding 12 — The two systems can silently double-track the same meal [Medium]
**Verified.** Nothing cross-checks `FestCateringOrder` against `FestFoodBill` order items for the same school/date/meal — a school can have both simultaneously. If both get confirmed/settled independently, whichever issuance batch runs first claims the coupon slot and the other silently `continue`s past it with no admin-facing signal that a collision happened. Coupons issued from a bill get a system note identifying their source (`"Issued from priced food-menu order (bill #...)"`); coupons issued from catering only inherit the school's own freeform notes — so an admin looking at a coupon can't tell whether it was ever paid for.

### Finding 13 — Assorted validation gaps [Low]
- `menu_date` isn't bounded to the event's actual date range at creation (`FestFoodMenuController::store()/update()`) — menu items, and therefore orders and coupons, can be dated outside the event entirely.
- No duplicate-item guard on manual menu creation (only the region-sync path dedups, and only for that operation).
- `is_available` is checked when a school self-serves an item (`FestFoodOrderController::addItem()`) but not when staff add an item to a bill on a school's behalf (`FestFoodBillingController`/`FestFoodHostBillingController::addItem()`) — likely intentional (staff override) but undocumented as such.
- Standard (non-phase-mode) events have no ordering cutoff at all — the phase cutoff check is the only deadline enforcement anywhere in the module.
- `redeem()` doesn't check a coupon's `valid_date` against today — redeemable on any day as long as status is `issued`. Possibly an accepted manual-process tradeoff (staff eyeball the printed date), worth confirming rather than assuming it's a bug.

### Finding 14 — Minor: "issued" coupon counts are defined two different ways [Low]
The Sahodaya coupon-list summary counts `status = 'issued'` only; `combinedFoodSummary()`'s per-region rollup counts *all* coupons regardless of status under the same "issued" label. An admin comparing a hub rollup against a single region's own page will see two different, incompatible numbers for the same data.

---

## 4. UI/UX findings

### Finding 15 — Void-payment and cancel-bill actions exist on the backend but have no UI entry point anywhere [Critical]
**Verified directly** by reading `FoodBillingShow.vue` (Sahodaya) — the Payments table renders exactly five columns (Receipt/Amount/Mode/Received/Notes) and no action column at all, on either the Sahodaya or School version of this page. `voidPayment` and `cancel` are fully implemented, routed, and audited on the backend (§3, Finding 6), but a repo-wide search for these route names in `resources/js` returns zero matches. **There is currently no way for anyone to correct a mis-entered payment or cancel a bill through the app** — the only recourse today is a direct database edit. This is the single most actionable finding in the whole audit: the fix is UI-only (the backend is ready), and it directly blocks a routine, foreseeable admin task (someone will fat-finger an amount).

### Finding 16 — Navigation is confusing, and the team's own code shows they know it [High]
- The School-side entry point to Catering has **no direct sidebar link** — it's reached via a card inside "Fest Hub," and the nav-building code's own comment admits why: *"'Meal requests' (Catering) has no standalone school-wide page — it's per-event only... so it's reached via a card inside Fest Hub, not a separate sidebar link."*
- On the Sahodaya side, four similarly-named items — "Catering (legacy)," "Food coupons," "Food menu," "Food billing" — sit flat in one undifferentiated "Administration" section next to unrelated items like "Judges & staff," with no sub-grouping and no on-page indication (beyond the nav label itself) that Catering is deprecated.
- A **host school gets two separate, non-cross-linked pages for managing its own bill**: the self-service `FoodOrder.vue` and its own row inside the management-style `FoodHostBilling.vue → FoodHostBillingShow.vue`. `FestFoodBill::firstOrCreateForSchool()` creates a bill for the host the same as any other school, so both paths edit the identical row.
- Cross-linking is inconsistent even where it would obviously help: `FoodBillingShow.vue` (a just-settled bill) has no link forward to `FoodCoupons.vue` to issue coupons for it — the admin has to know to navigate there separately via the nav menu.

### Finding 17 — Inconsistent confirmation coverage on impactful actions [High]
The module is notably *clean* on native browser dialogs (see § What's Already Solid) — but styled confirmation is applied inconsistently even within a single page:
- `FoodBillingShow.vue`: `removeItem` confirms, `settle` confirms, but **`reopen` — right next to `settle` — does not**.
- Sahodaya `Catering.vue`: Cancel fires immediately, no confirmation.
- `FoodCoupons.vue` (Sahodaya): both bulk "Issue from confirmed catering" / "Issue from food billing" actions and "Redeem" fire immediately with no confirmation, despite being bulk and semi-irreversible.
- `FoodMenu.vue`: "Apply menu to all regions" — which mutates every region's menu — has no confirmation.
- `Kitchen.vue`: the per-row status dropdown posts on `@change` with no confirmation, including setting an order to Cancelled, and optimistically shows the new value before the request resolves with no revert-on-error — so a failed save can leave the dropdown showing a value that was never actually saved.

### Finding 18 — Zero mobile responsiveness, including on the one screen built for event-day phone use [High]
Across all 11 pages, exactly one file contains any responsive Tailwind breakpoint. Every money page (bills, payments, menu) has fixed 3-column stat grids and un-wrapped tables with no horizontal-scroll fallback. `Kitchen.vue` — the screen explicitly meant for floor/kitchen staff during the event, plausibly on a phone — has the *least* responsive treatment of any file in the module: a wide plain table with an embedded `<select>`, no breakpoints, no scroll container.

### Finding 19 — Status indication is inconsistent and sometimes entirely absent [Medium]
- Sahodaya `FoodCoupons.vue` renders every coupon status (issued/redeemed/void) in flat gray with no color distinction, while the School-side equivalent page for the identical data uses a proper three-way color scheme.
- Sahodaya `Catering.vue` renders order status as raw unstyled text, while the summary cards two lines above it on the same page do use color — an internal inconsistency within one file.
- Bill status badges everywhere only special-case `settled` (green) vs. everything else (gray) — a `cancelled` bill renders visually identical to a normal `open` one.

### Finding 20 — Payment-amount field skips a validation pattern the app already uses one field over [Medium]
The quantity field on the same pages computes a live client-side max from data already available (`:max="remainingForSelected"`) and disables submit accordingly. The payment-amount field, despite `bill.balance_due` being in scope and displayed two sections above it, has no equivalent `:max` — overpayment is only caught server-side, after a full round trip, as a page-level banner rather than an inline field error.

### Finding 21 — No visible audit trail on the School side [Medium]
Every Sahodaya food page renders an `EventPageActivityLog`. No School food page does — `FestFoodHostBillingController` never passes activity-log data to its views at all (consistent with Finding 11's missing audit-log writes). The people handling potentially many other schools' payments (host-school admins) have the least on-page visibility into what happened before them.

### Finding 22 — No print-status tracking; repeated PDF generation is unflagged [Medium]
Coupon PDFs are generated fresh from all currently-`issued` coupons on every click of "Print issued PDF," with no `printed_at`/print-count field anywhere on the model. Clicking twice produces two physically identical printouts with no warning. (Actual redemption is separately and correctly protected against double-spend — this is specifically a double-*print* risk, not a double-*redeem* risk.)

### Finding 23 — Terminology drift across the module [Low]
The same underlying `FestCateringOrder` entity is called "Catering (legacy)" (Sahodaya nav), "Meal Requests (Catering)" (School page title), and "Kitchen" (Portal nav) in three different places. The two bulk "Issue coupons" buttons on the Sahodaya coupons page sit side by side with no copy explaining that they belong to two entirely separate backend pipelines — an admin needs to already know the system architecture to know which one applies to their event.

### Finding 24 — Minor accessibility and authoring inconsistencies [Low]
- `Kitchen.vue`'s per-row status `<select>`, and several inline-edit fields on `FoodMenu.vue`, fall outside both of the app's label-inference mechanisms (no `FormField` wrapper, no `placeholder`/`name` for the retrofit script to key off), so they reach screen-reader users as unlabeled controls.
- Minor dead code / unexplained divergence between the near-identical Sahodaya and School billing pages: an unused `prompt` import, and a `destructive` flag passed inconsistently to otherwise-identical confirm calls.
- Inconsistent nav-label capitalization for the same destination ("Food Coupons" vs. "Food coupons") depending on which nav context renders it.

---

## 5. What's already solid

Worth preserving, not just criticizing:

- **This module is one of the cleanest in the app on native-dialog usage** — a platform-wide problem elsewhere (111 files use native `confirm()`/`prompt()` per the prior full-platform audit). Every food-module confirmation that exists at all correctly routes through the app's own styled `ConfirmDialog`/`useConfirm()`. The gaps found here (Finding 17) are missing confirmations, not wrong-mechanism ones.
- **Tenant/ownership authorization is consistently correct** everywhere traced — every resource load checks event→tenant, bill→event, item→bill, payment→bill, coupon→event correctly, including a host-billing check that correctly uses the bill's own *snapshotted* payee settings rather than the event's current (mutable) ones, which is the harder-to-get-right version of that check.
- **The one genuinely concurrent money operation is correctly built**: `recordForBill()`/`voidPayment()` both use `DB::transaction()` + `lockForUpdate()`, and `firstOrCreateForSchool()`'s apparent check-then-create race is actually safe because it's backed by a real unique DB constraint and Laravel's `createOrFirst()` retry path.
- **Business-rule failures surface to users properly**: the app's global exception handling converts `abort_if()` rejections into a flash banner on every layout used by this module, so a blocked action (bill not open, over quantity limit, already redeemed) shows a clear message rather than a raw error page or a silent no-op.
- **No icon-only, unlabeled buttons and no keyboard-trap patterns** anywhere in the 11 pages read — baseline keyboard operability is sound.

---

## 6. Recommended priority order

1. **Build the missing void-payment/cancel-bill UI** (Finding 15) — the backend is done; this is pure frontend work and closes a real operational gap (someone *will* need to fix a wrong payment entry).
2. **Close the payment-gating bypass** (Finding 1) — decide whether the legacy catering flow should be blocked, gated, or retired on events that require payment.
3. **Fix the negative-balance settle bug and add locking to settle/cancel/reopen** (Findings 2–3) — these are money-correctness bugs, not polish.
4. **Decide the phase/cross-event data model deliberately** (§2) — add `phase_id` to food tables if the goal is same-event/different-phase pricing, or fix the menu-sync drift problem if the goal is the existing leaf-event model. Don't leave both partially built.
5. **Retire (or clearly sunset) the legacy catering flow** — this single change would also resolve Findings 1, 8, and 12 as side effects, and matches a decision your own planning docs already made but never executed.
6. Everything else (navigation cleanup, confirmation consistency, mobile responsiveness, status badge consistency) is real but lower-stakes polish that can follow once the money-correctness and UI-gap items above are addressed.

---

## Appendix: files read in full

**Models:** `FestFoodCoupon`, `FestFoodBill`, `FestFoodOrderItem`, `FestFoodPayment`, `FestFoodMenuItem`, `FestCateringOrder`, `FestEvent`, `FestEventPhase`

**Controllers:** `SahodayaAdmin/{FestFoodCouponController, FestFoodMenuController, FestFoodBillingController, FestCateringController, FestEventPhaseController}`, `SchoolAdmin/{FestFoodCouponController, FestFoodOrderController, FestFoodHostBillingController, FestEventPortalController}` (catering methods), `Portal/FestEventOpsController` (kitchen methods)

**Services:** `FestFoodMenuSyncService`, `FestPartitionService`, `FestEventPhaseService`, `FestPhaseLifecycleService`, `FestPhaseTopologyService`, `FestRegionPartitionService`

**Vue pages:** `Sahodaya/Events/{Catering, FoodBilling, FoodBillingShow, FoodCoupons, FoodMenu}.vue`, `School/Events/Catering.vue`, `School/Fest/{FoodCoupons, FoodHostBilling, FoodHostBillingShow, FoodOrder}.vue`, `Portal/FestOps/Kitchen.vue`

**Print templates:** `resources/views/fest/food/bill.blade.php`, `resources/views/fest/catering/food-coupons.blade.php`

**Docs cross-referenced:** `FOOD_MENU_BILLING_PREORDER_PLAN.md`, `REGION_AND_PHASE_KALOTSAV_PLAN.md`, `REGION_SCOPED_ADMIN_AND_EVENT_FLOW_PLAN.md`, `REGION_PHASE_EVENT_REPORTING_REMEDIATION_PLAN.md`, `KALOTSAV_PHASED_LEVEL_FEE_PLAN.md`, `MCS_FOUR_PHASE_COMPLETION_PLAN.md`
