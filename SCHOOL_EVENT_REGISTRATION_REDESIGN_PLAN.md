# School Event Registration — Minimum Fee + Page Redesign Plan

Status: proposed, not yet implemented.
Scope: `resources/js/Pages/Admin/School/Events/Registration.vue` (school-side
event/item registration + payment) and its fee engine,
`app/Services/Events/FestSchoolEventFeeService.php`. Covers both the ₹1500
minimum-fee rule and whether the page should be split into separate
tab/step pages.

---

## Part A — ₹1500 minimum fee per school

### Current state
`FestSchoolEventFeeService::recalculate()` computes a school's event fee via
one of several `fee_model`s (`cksc_tiered`, `item_catalog`, `per_item`,
`flat_school`, `per_student`, `sports_composite`), then applies
**`applySchoolFeeCap()`** — a maximum ceiling pulled from
`school_fee_cap` on the event's fee schedule. There is **no minimum floor**
anywhere in the pipeline. Searched the whole fee service and the Vue pages
for "1500", "minimum", "min_fee" — no existing implementation.

### What needs to change
1. **Backend — add a floor counterpart to the existing cap.**
   In `FestSchoolEventFeeService`, add `applySchoolFeeMin()` alongside
   `applySchoolFeeCap()` (`app/Services/Events/FestSchoolEventFeeService.php`,
   near line 880), called at the same point in `recalculate()` right after
   the cap is applied. Logic: `if ($total < $min) { $total = $min; }` where
   `$min` comes from a new `school_fee_min` field on the fee schedule
   (mirroring how `school_fee_cap` already works).
2. **Config surface.** `school_fee_cap` is presumably set somewhere in the
   Sahodaya-side event fee settings UI (the same screen where fee_type /
   fee_model / cap are configured — needs a quick lookup, likely
   `Settings/Fees.vue` or a tab in the event Settings page). Add a
   `school_fee_min` input next to the existing cap field, defaulting to
   `1500` for sports events (confirm with the user whether this is
   sports-only or applies to all event types before implementing).
3. **Migration.** Add `school_fee_min` column to whatever table holds
   `school_fee_cap` (the fee schedule table — confirm exact table name
   before writing the migration; likely `fest_school_fee_schedules` or
   similar).
4. **School-side messaging.** In the fee breakdown block on
   `Registration.vue`, if the raw item/participant total is below the
   minimum, show an explicit line: *"Minimum event fee: ₹1,500 — applied
   because your registered items total less than this."* Without this, a
   school that registers one cheap item and gets charged ₹1500 will be
   confused about where the extra amount came from.
5. **Backfill consideration.** Decide whether the ₹1500 floor applies
   retroactively to already-registered-but-unpaid schools for events in
   progress, or only to fee recalculations going forward. Recommend:
   forward-only, re-trigger `recalculate()` only when an admin explicitly
   asks (same pattern likely already used for cap changes).

### Open question for the user before implementing
- Is the ₹1500 minimum specific to sports events, or should it apply to
  every event type (Kalotsav, Kids Fest, Teacher Fest) that uses
  `flat_school`-style billing?
- Is ₹1500 a fixed platform-wide default, or does it need to be configurable
  per event/per Sahodaya (recommended, to match how `school_fee_cap` already
  works per-schedule)?

---

## Part B — Page redesign: separate tabs/steps for Event reg / Item reg / Payment?

### Current state
Everything lives on one page: `Registration.vue` (~1670 lines). Per event
card, it renders, top to bottom, in a single scroll: event status header →
(for sports) "Step 1: event athletes" + "Step 2: item registration" grids
side by side → (for Kalotsav/Kids Fest/Teacher Fest) a flat item table →
immediately below, in the *same card*, "Event fees & billing": fee
breakdown, per-head invoices for sports, a payment-proof file upload, and
receipt/invoice links. The only stepper already in place —
`SchoolEventWorkflowStepper.vue` — sits above all of this and currently has
just 2-3 macro steps: **Overview → Register (or Register & pay for sports) →
Reports & ID cards**. It's a working, reusable pattern (also mirrored by
`McqSchoolWorkflowStepper.vue`), it just doesn't yet break the middle step
apart.

### Recommendation: yes, split it — but as in-page tabs, not separate routes
Given the page is already ~1670 lines mixing three genuinely different
tasks (who's competing, which items, how much and how to pay), and the
minimum-fee messaging from Part A adds yet another thing competing for
attention in the same card, a split is worth doing. Two ways to do it:

**Option 1 — extend `SchoolEventWorkflowStepper` into full separate pages**
(`.../registration/athletes`, `.../registration/items`,
`.../registration/payment`). Pro: matches the existing stepper pattern
exactly, each page loads only what it needs. Con: real routing/controller
work — need new Inertia routes and controller methods, plus state has to be
persisted between steps via the database (already true today, so low risk),
and "back" navigation between steps means extra round-trips.

**Option 2 — keep one route, add an in-page tab strip** (Event registration
/ Item registration / Payment) inside the existing card, driven by a local
`ref` rather than routing. Pro: much less backend work — no new routes,
same controller, same props; just restructure the template with `v-show`
panels and a tab bar matching the existing `SchoolEventWorkflowStepper`
visual style. Con: doesn't reduce the actual payload size sent to the page
(all data still loads at once), though it does reduce visual/scroll clutter
significantly, which is the actual problem being described.

**Recommended: Option 2 first.** It solves the stated problem (things
crammed together, unclear separation between registering athletes,
assigning items, and paying) with a fraction of the risk and effort of a
routing change. If the page later becomes a performance problem (slow load
due to fetching all three sections' data upfront), revisit Option 1.

### Concrete steps for Option 2
1. Add a small in-card tab bar component (or inline `<nav>` block, styled
   like `SchoolEventWorkflowStepper`'s pills) with three tabs: **Event
   registration** (sports: athlete selection; others: skip straight to
   items since there's no separate athlete step), **Item registration**,
   **Payment**.
2. Wrap the existing sections in `Registration.vue` in `v-show="activeTab === 'items'"` etc. rather than always-rendered — no data-fetching changes needed since props already contain everything.
3. Default `activeTab` to the first incomplete step (e.g. if items are
   already registered but payment isn't uploaded, land on Payment) — mirror
   how `EventLifecyclePanel`'s suggested-status logic works on the Sahodaya
   side, for consistency of pattern across the codebase.
4. Carry the ₹1500-minimum messaging from Part A into the Payment tab
   specifically, since that's where a school will be looking at the amount
   due.

### For non-sports event types (Kalotsav / Kids Fest / Teacher Fest)
These don't have the sports "Step 1 athletes / Step 2 items" split — it's
already just one flat item table. For these, the tab bar would only need
two tabs: **Item registration** and **Payment**. Keep the tab bar component
conditional so it doesn't show an empty/pointless "Event registration" tab
for event types that don't need it.

---

## Part C — Sports event flow re-check

Re-verified the findings in `SPORTS_NAV_CLEANUP_PLAN.md` still hold; no
changes needed to that plan as a result of this investigation. One addition
worth noting: the **Sahodaya-admin** side (where `SPORTS_NAV_CLEANUP_PLAN.md`
lives) and the **school-admin** side (this document) currently have two
completely independent stepper components —
`FestEventWorkflowStepper.vue` (Sahodaya) and
`SchoolEventWorkflowStepper.vue` (school) — which is correct and expected
(different audiences, different steps), but worth flagging so no one tries
to merge them later. They should stay separate.

---

## Suggested order of work

1. Answer the two open questions in Part A (scope of the minimum: which
   event types, and configurable-per-event vs fixed platform default).
2. Implement Part A backend (floor logic + config field + migration).
3. Implement Part B Option 2 (in-page tabs) — can be done in parallel with
   Part A backend once the fee-breakdown UI location is settled, since the
   tab split and the minimum-fee messaging touch the same section of the
   page.
4. Add the minimum-fee messaging to the Payment tab as the last step, once
   both A and B are in place.
