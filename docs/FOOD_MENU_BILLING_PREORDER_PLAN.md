# Event-Day Food Menu, Billing, Preorder & Host-School Payment — Plan

**Status:** Analysis only — no code changed.
**Prepared:** 26 Jul 2026
**Scope decided with user:** school-level bulk preorders (not per-student), settlement recorded as offline ledger entries (no payment gateway — matches how every other fee in this app is handled today).

---

## 1. Requirement, restated

For a fest/kalolsavam event, the host (conducting) school needs to:

1. Publish a **priced food menu** per meal (breakfast/lunch/dinner/snacks) per event day.
2. Let participating schools **preorder** meals in bulk (headcount + selected items) ahead of the event.
3. **Bill** each participating school for what they ordered.
4. Have those payments **land with the host school**, not disappear into the Sahodaya's general fest income — the host school is the one actually buying and serving the food.

---

## 2. What already exists (don't rebuild this)

The catering skeleton is already there, but it has no menu, no price, and no money attached to it:

| Piece | File | What it does today |
|---|---|---|
| `FestCateringOrder` | `app/Models/FestCateringOrder.php` | A school submits `meal_date` + `meal_type` + `head_count` + notes. No line items, no price. |
| School submits order | `FestEventPortalController::storeCatering()` | `/school-admin/{tenant}/fest/{event}/catering` — plain headcount form. |
| Sahodaya reviews orders | `SahodayaAdmin/FestCateringController` | Lists orders, flips status `requested → confirmed/cancelled`. No billing step. |
| `FestFoodCoupon` | `app/Models/FestFoodCoupon.php` | Printable meal coupon, generated 1:1 from confirmed orders (`issueFromCatering()`), redeemed at the counter. No price on it either. |
| `FestEvent.conducting_school_id` | `app/Models/FestEvent.php:29,200` | The **host school** concept already exists on the event — this is who should get paid. |
| Fee/receipt/ledger pattern | `FeeReceipt`, `LedgerPostingService`, `LedgerAccountSetupService`, `AccountHead` | Every existing fee (fest registration, membership, MCQ, training) works the same way: a school uploads a bank-transfer/UPI proof as a `FeeReceipt` (morph target), an admin approves it, approval posts an income entry to an `AccountHead` **owned by one `tenant_id`**. |
| Out-of-platform payout record | `CreditPayout` + `CreditPayoutService` | Existing pattern for "money left the platform to a school's bank account" — `school_id`, `amount`, `bank_ref`, `notes`, recorded by an admin. This is the exact shape needed for a Sahodaya → host-school settlement, if that path is used (see §4.5). |

**Important existing constraint:** every `AccountHead` today belongs to the **Sahodaya's** `tenant_id` (`LedgerAccountSetupService::ensureFestEventHead()` uses `$event->tenant_id`, which is the Sahodaya, not the school). Fee income for an event currently always books to the Sahodaya's ledger, never to a school's. Since a school is itself a `Tenant` row (`type = 'school'`), the same `AccountHead`/`LedgerPostingService` machinery can create a head scoped to the **host school's own `tenant_id`** instead — that's the mechanism this plan uses to make catering income actually belong to the host school's books rather than the Sahodaya's.

---

## 3. Gap analysis

1. **No menu.** There's no model for "these are the dishes available, at these prices, on this day/meal." Orders today are a raw headcount.
2. **No pricing/billing.** `FestCateringOrder` has no amount. Nothing computes what a school owes for catering.
3. **No payment flow for catering.** The existing `FeeReceipt` → approve → ledger pattern is wired to fest registration fees (`FestSchoolEventFee`) and a few other program types — catering isn't a `feeable` type today.
4. **No host-school payee.** Even if catering fees existed, the default ledger wiring would book them to the Sahodaya, not `conducting_school_id`. This is the crux of the "payments go to the host school" requirement and needs an explicit design decision (§4.5).
5. **Coupons aren't payment-gated.** `issueFromCatering()` turns every `confirmed` order into a coupon regardless of whether it's been paid — fine today because there's no price; needs a rule once there is one.

---

## 4. Proposed design

### 4.1 Food menu (new)

New model `FestFoodMenuItem`, created by whoever administers the host school's catering (Sahodaya admin now; can be delegated to a host-school-scoped role later, same mechanism as `event_admin`/`FestEventStaff`):

```
fest_food_menu_items
  id, event_id, name, description, meal_type (breakfast|lunch|dinner|snacks),
  meal_date (nullable = "available every day"), diet_type (veg|non_veg|jain|na),
  unit_price decimal(10,2), is_active boolean, display_order, created_by_user_id, timestamps
```

Admin UI: simple CRUD list scoped to the event (same shape as `FestCatalogItem` admin CRUD) — add/edit/deactivate items, set price per item. Menu is versioned implicitly by `is_active`; changing a price doesn't retroactively change already-placed orders (line items snapshot price, see 4.2).

### 4.2 Preorder (extends `FestCateringOrder`)

Add a line-item table instead of a single headcount:

```
fest_catering_order_lines
  id, catering_order_id, menu_item_id, item_name_snapshot, unit_price_snapshot,
  quantity, line_total, timestamps
```

`FestCateringOrder` gains `total_amount` (sum of line totals) and keeps `head_count` as a derived/legacy field (`sum(quantity)`) so `FestFoodCoupon::generateCode()`/`issueFromCatering()` keep working unmodified.

School-facing flow (`FestEventPortalController::catering()`/`storeCatering()`):
- Replace the plain headcount form with a menu picker for the selected `meal_date`/`meal_type`: quantity per active menu item.
- Server computes `line_total` per row from the *current* `unit_price` at submit time (snapshotted — later price edits don't change existing orders) and `total_amount` on the header.
- Multiple orders per school/event are allowed (one per meal/day), same as today.

### 4.3 Billing

New model `FestCateringInvoice` (mirrors `FestEventInvoice`'s shape, not reused directly because the payee differs — see 4.5):

```
fest_catering_invoices
  id, event_id, school_id, payee_school_id (= conducting_school_id at issue time),
  invoice_number, total_amount, order_ids (json, catering_order ids covered),
  status (unpaid|partially_paid|paid|waived), issued_at, issued_by, timestamps
```

Generated when the Sahodaya (or host school, if delegated) confirms one or more `requested` orders — same `confirm` action as today, but confirming now also rolls the confirmed orders into an invoice (one open invoice per school per event; confirming additional orders adds to it if unpaid, or opens a new one if the prior was already paid).

### 4.4 Payment collection

Reuse the existing receipt pattern exactly — this is why it's worth keeping `FeeReceipt` as a generic `morphTo` rather than inventing a new payment record type:

- `FestCateringInvoice` becomes a new `feeable_type` for `FeeReceipt`.
- Participating school uploads bank transfer / UPI proof against the invoice, same UI pattern as `FestSchoolEventFee` proof upload.
- Approval flips invoice `status` and, per 4.5, posts the ledger entry.
- `TracksPartialPayments` (already used by `FestSchoolEventFee`) can be attached to `FestCateringInvoice` too, for partial-payment support with no new logic.

### 4.5 Getting the money to the host school — two options

This is the one real design fork. Recommend **Option A**.

**Option A — Direct-to-host-school ledger (recommended).**
The `AccountHead` for catering income is created on the **host school's own `tenant_id`**, not the Sahodaya's:

```php
// LedgerAccountSetupService — new method, same pattern as ensureFestEventHead()
public function ensureFestCateringHead(FestEvent $event): AccountHead
{
    return app(LedgerPostingService::class)->ensureHead(
        $event->conducting_school_id,                 // <-- host school's tenant, not $event->tenant_id
        LedgerAccountCatalog::festCateringIncomeCode($event),
        LedgerAccountCatalog::festCateringIncomeHeadName($event),
        'fest_catering',
        $event->id,
    );
}
```

When a `FeeReceipt` against a `FestCateringInvoice` is approved, it posts straight into the host school's own ledger/financial statements (`FinancialStatementsService`, `LedgerReportingService` — both already tenant-scoped, so they pick this up with no changes). No extra settlement step, no float sitting at the Sahodaya. Who *reviews and approves* the receipt becomes the open question this implies: either the host school gets a scoped "catering admin" review screen (new, small — a filtered version of the existing receipt-approval UI restricted to `feeable_type = FestCateringInvoice AND payee_school_id = <their tenant>`), or the Sahodaya keeps approving on the host school's behalf (no new permission surface, just a new tab in `UnifiedPaymentsController`/`SahodayaAdmin` receipt review).

**Option B — Sahodaya collects, then pays out.**
Keep the `AccountHead` on the Sahodaya (consistent with every other fee type, zero new ledger-scoping logic), and treat the payment as Sahodaya income exactly like fest registration fees. Separately, once total collected is known, the Sahodaya records a payout to the host school using the existing `CreditPayout` shape (generalize `creditable_type` to also accept `FestEvent`/a catering settlement record, or add a parallel `CateringPayout` model): `school_id = conducting_school_id`, `amount`, `bank_ref`, `notes`. This matches how `CreditPayoutService::recordPayout()` already works for fee credits — same "record that money left the platform to a school's account" pattern, just triggered manually by the Sahodaya once catering closes out rather than automatically.

Option A gets the money "into the host school's books" the moment a participating school's payment is approved, with no manual remittance step to forget. Option B keeps every fee type consistently Sahodaya-collected (simpler mental model for Sahodaya finance staff) at the cost of a manual settlement step and a real risk of it being forgotten or disputed ("did we actually get paid for catering?"). Recommend A unless the Sahodaya's existing bank/finance process specifically requires it to be the single collection point for all money regardless of type — that's a business-process question, not a technical one, worth confirming with an actual Sahodaya finance admin before building.

### 4.6 Coupon gating

Add `require_payment_for_coupons` boolean on `fest_events` (default `false`, matching today's free-issuance behavior so nothing breaks for events not using this feature). When `true`, `FestFoodCouponController::issueFromCatering()` only turns a confirmed order into a coupon once its parent invoice is `paid` (or `partially_paid` with a per-event minimum-percent rule, if that's ever needed — not building that now, YAGNI until asked).

### 4.7 Reporting

- Extend `UnifiedPaymentsController`/`SchoolPaymentHistoryService` with a `catering` row type, same as `membership|fest|training|mcq` today.
- Host school's own admin dashboard gets a "Catering revenue" card pulling from its own `AccountHead` (Option A) — reuses `FinancialStatementsService` with no changes since it's already tenant-scoped.

---

## 5. Concrete build list

**New models/migrations:**
- `fest_food_menu_items` (+ `FestFoodMenuItem` model)
- `fest_catering_order_lines` (+ `FestCateringOrderLine` model)
- `fest_catering_invoices` (+ `FestCateringInvoice` model, `TracksPartialPayments`)
- Alter `fest_catering_orders`: add `total_amount decimal(10,2)`, `catering_invoice_id` nullable FK
- Alter `fest_events`: add `require_payment_for_coupons boolean default false`
- `FeeReceipt`: no schema change — just a new `feeable_type` value

**New/changed services:**
- `LedgerAccountSetupService::ensureFestCateringHead()` (Option A)
- `LedgerAccountCatalog::festCateringIncomeCode()/Name()`
- New `FestCateringInvoiceService` (or extend `OfflineProgramFeeOrchestrator`-style pattern): confirm order → roll into invoice; approve receipt → post ledger + update invoice status

**New/changed controllers:**
- `SahodayaAdmin/FestFoodMenuController` — menu CRUD
- `SchoolAdmin/FestEventPortalController::catering()/storeCatering()` — menu-driven ordering instead of raw headcount
- `SahodayaAdmin/FestCateringController::confirm()` — generate/attach invoice on confirm (new method; today only status flip exists)
- Receipt upload/review screens for `FestCateringInvoice`, reusing existing receipt components
- `FestFoodCouponController::issueFromCatering()` — add payment-gate check

**Frontend (Inertia/Vue, matching existing `Sahodaya/Events/*` and `School/Events/*` page conventions):**
- `Sahodaya/Events/FoodMenu.vue` — menu builder
- `School/Events/Catering.vue` — rework from headcount form to itemized cart + running total
- Invoice + receipt-upload views, reusing existing fee receipt components

---

## 6. Phased rollout

1. **Phase 1 — Menu + priced preorder, no payment yet.** Ship `FestFoodMenuItem`, order lines, `total_amount` on orders. Schools order from a real menu and see a total; nothing is billed or collected yet. Low risk, immediately useful (host school finally knows what to cook and how much).
2. **Phase 2 — Billing + payment collection.** `FestCateringInvoice`, `FeeReceipt` wiring, approval flow. Land on whichever of Option A/B is chosen in §4.5.
3. **Phase 3 — Coupon gating + reporting.** `require_payment_for_coupons`, catering row in `UnifiedPaymentsController`, host-school revenue view.

Each phase ships independently and phase 1 has no dependency on the Option A/B decision, so it can start immediately while that's being confirmed.

---

## 7. Open decisions before building

1. **Option A vs B (§4.5)** — does the host school get paid directly (own ledger, own receipt approval), or does the Sahodaya keep collecting everything and manually remit? This changes who reviews receipts and where the `AccountHead` lives.
2. **Who administers the menu** — Sahodaya admin only, or can a host school manage its own menu/pricing without going through the Sahodaya? (Same `event_admin`/`FestEventStaff` scoping used for region admins in `docs/REGION_AND_PHASE_KALOTSAV_PLAN.md` could grant a host-school user a "catering admin" duty on their own event.)
3. **Refunds/cancellations** — if a school cancels a confirmed+paid order, does that need a credit note (existing `CreditNoteService`/`FestFeeCredit` pattern) or is catering treated as non-refundable once confirmed? Not addressed above; needs a policy answer, not a technical one.
