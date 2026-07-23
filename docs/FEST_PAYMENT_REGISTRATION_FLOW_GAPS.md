# Fest Registration & Payment Flow — Gap Analysis

**Status:** §9.2 (credit on rejection) and §9.3 backend (per-item allocation + opt-in gate) are implemented — see "Implementation status" below. Everything else in this doc is still analysis/spec only.
**Prepared:** 22 Jul 2026
**Scope:** approval, rejection, partial payment, cancelling an already-approved registration, and adding a new student/item after approval.

## Implementation status (23 Jul 2026)

Built exactly per the §9 spec, nothing beyond it:

- `database/migrations/tenant/2026_09_03_000001_create_fest_fee_credits_table.php` — new `fest_fee_credits` table.
- `app/Models/FestFeeCredit.php` — new model; `credits()`/`outstandingCredit()` added to `app/Models/FestSchoolEventFee.php`.
- `app/Services/Events/FestSchoolEventFeeService.php` — new `currentFeeRecordFor()` and `itemPaymentAllocation()` (read-only, scoped to `item_catalog`/`per_item` billing); `isPaidForRegistration()` gains the opt-in `strict_item_payment_gating` branch, default path unchanged.
- `app/Services/Events/FestRegistrationBulkService.php::rejectMany()` — now snapshots the fee before/after rejection and writes a `FestFeeCredit` row when a paid item is rejected. No change to `total_due`/`amount_paid`/receipt handling.
- `database/migrations/tenant/2026_09_03_000002_add_strict_item_payment_gating_to_fest_events.php` — new `fest_events.strict_item_payment_gating` column, default `false`.
- `app/Models/FestEvent.php` — new column added to `$fillable`/`$casts`.

**UI now built too (23 Jul 2026):**
- School side (`EventBillingPanel.vue` + `FestRegistrationController::hydrateEventForSchoolRegistration()`): "Available credit" banner, and a per-item "what your payment covers" checklist for `item_catalog`/`per_item` events.
- Sahodaya admin side (`Fees.vue` + `FestEventFeesController::index()`): a "Credit owed" badge per school row, and an expandable "Payment coverage" breakdown per school.
- Event settings (`LocksTab.vue` + `FestEventSettingsController::updateSettings()` + `FestEventSettingsPayload`): a "Strict item-level payment gating" checkbox, off by default, with an inline note that it only affects `item_catalog`/`per_item` billing.

`FeeReceiptReversalService`/`reverseReceipt` remain untouched, as specified.

**Migrations are written but not run.** This environment has no PHP runtime or database connection to this project, so `php artisan migrate` (or your usual `sahodaya:provision-databases` tenant rollout) needs to be run from your own machine/deploy pipeline before these columns/table exist. Everything above degrades safely if the migration hasn't run yet in a given environment — `strict_item_payment_gating` defaults `false` and nothing reads `fest_fee_credits` unless a rejection actually happens after the table exists. See §10.1 for the one real ordering hazard.

## 11. Honest limitations of what was actually built (23 Jul 2026)

Went back and checked what `FestFeeCredit` actually *does* end-to-end, not just whether it's created and displayed. Two real gaps in this pass's own work, worth knowing before anyone assumes "credit" means "handled":

- **A credit is tracked but never consumed.** `applied_at` exists on the table specifically to mark a credit as absorbed into a later fee, but nothing in the codebase ever sets it — confirmed via a repo-wide search. `FestSchoolEventFeeService::recalculate()` and `attachPayment()`/`attachPaymentForHead()` never read `outstandingCredit()` either. Net effect: the credit banner/badge is accurate and visible, but a school with ₹500 credit and a new ₹1,000 fee is still asked for the full ₹1,000 — the credit doesn't reduce anything automatically. It's a running tally, not a working offset yet. Applying it today means a human has to notice the badge and handle it manually (e.g. via `forceApprove`'s waiver, or off-system).
- **A credit never posts to the ledger.** Checked `app/Services/Ledger/*` — `FeeReceiptReversalService` correctly posts compensating entries via `FeeReceiptLedgerDispatcher` when a receipt is reversed, but `FestFeeCredit` has zero connection to `LedgerPostingService`, `FeeReceiptLedgerDispatcher`, `LedgerReportingService`, or `FinancialStatementsService`. That was a deliberate simplification (touching the ledger is a materially bigger, higher-risk change), but the honest consequence is: the Ledger and Financial Statements pages still show the *original* payment as fully recognized revenue, with no visibility into the amount now owed back to a school. Anyone reconciling books from those reports alone will not see this liability.
- **There is still no actual cash-refund path.** The credit is purely an in-platform bookkeeping offset. If a school never has another payable item at that Sahodaya, the credit has nowhere to go — there's no "pay this back to the school's bank account" action anywhere in the system, for this credit or the pre-existing `reverseReceipt` reversal.
- **No manual credit adjustment.** Credits are only ever created automatically, by rejection or cancel-with-refund. There's no admin action for an ad-hoc goodwill credit/debit unrelated to those two triggers.

## 12. Broader scan — things not yet examined at all in this thread

- **Event-level cancellation.** Everything built so far handles cancelling one *registration*. What happens to all payments, credits, and registrations if an entire *event* gets cancelled — not examined.
- **Concurrency.** Two admins approving/rejecting the same registration at once, or a school double-submitting payment proof in two tabs — not examined; the delta-based credit math in particular assumes a single, serial before/after snapshot.
- **Reconciliation reporting.** Recorded `FeeReceipt` approvals vs. an actual bank/gateway statement — flagged in §7 as a recommended report, never built.
- **A dedicated refund/credit report.** §7 recommended this; what exists today is the two inline badges (school panel, admin Fees page), not a standalone report someone can run across a whole event or Sahodaya.

---

## 13. Completing §11's gaps (23 Jul 2026) — and a real bug found while doing it

### 13.1 Bug found and fixed: `itemPaymentAllocation()` used the wrong price for `item_catalog` billing

Re-checked how `recalculate()` actually prices an `item_catalog` item before trusting my own earlier code. It calls `FestItemFeeResolver::amountForItem()`, which has a real fallback chain: item-level `fee_amount` override → competition-area default → Event Head default → participant-type (group/team) rate → age-group rate → class-group rate → schedule default. My original `itemPaymentAllocation()` read only `$registration->item?->fee_amount` — the *first* link in that chain. Any item priced via the area/head/class-group/age-group defaults (the common case for most catalogs, not an edge case) would show as **₹0** in the checklist and always read as "covered" — silently wrong data, and if `strict_item_payment_gating` were ever turned on for such an event, it would have **wrongly approved unpaid items**. Fixed by calling the same `FestItemFeeResolver::amountForItem()` the real billing math uses, so the checklist can never diverge from what's actually charged. (`per_item` billing was already correct — it's a flat rate × count, not per-item pricing, and that's what `itemPaymentAllocation()` already did for it.)

### 13.2 Credit consumption — implemented, but not the way originally sketched

§11 flagged that a `FestFeeCredit` is tracked but never consumed. Building that turned up a real constraint: `TracksPartialPayments::refreshPaidState()` — the method every fee-state change already calls — **recomputes `amount_paid` from scratch as the sum of approved `FeeReceipt` rows, every single time it runs.** Any attempt to fold credit into `amount_paid` directly (my first instinct) would be silently overwritten the next time anything recalculates the fee. This is a shared trait also used by Training and MCQ fee carriers, so it wasn't safe to change either.

What actually got built:
- `FestSchoolEventFee::effectiveOutstandingBalance()` — `outstandingBalance() - outstandingCredit()`, purely informational (e.g. "you owe ₹X after credit"). Never feeds back into `amount_paid`, `status`, or any approval gate.
- `FestSchoolEventFeeService::markCreditsApplied($fee, $amount)` — marks outstanding `FestFeeCredit` rows as consumed (`applied_at`), whole-row only (never splits a row; a row that would exceed `$amount` is left outstanding for next time).
- Wired into the **existing** `FestSchoolEventFeeController::forceApprove()` action — the system's own, already-correct mechanism for "mark a fee settled without new receipt money" (it bumps the fee's existing receipt to `approved` for the full amount, then calls `refreshPaidState()`, which is exactly why it works with the trait instead of against it). When an admin force-approves a school that has outstanding credit, that credit is now marked consumed in the same transaction, so it stops double-counting as "still owed" afterward.

**Deliberately not built:** a fully automatic/silent credit consumption inside ordinary `recalculate()` or `attachPayment()`. Doing that properly would mean either creating synthetic `FeeReceipt` rows to keep `refreshPaidState()`'s math consistent (a bigger change, touching receipt numbering and the receipts list a school sees) or modifying the shared `TracksPartialPayments` trait (blast radius: Training, MCQ, and Membership fee carriers, not just Fest). Both are real, buildable options — just not ones to guess at correctly without a live environment to verify against.

### 13.3 Ledger posting for credits — deliberately deferred, not forgotten

Checked `FeeReceiptLedgerDispatcher`/`FestFeeLedgerService`/`LedgerPostingService` before deciding this. Every existing posting path (`postApprovedReceipt`, `postReversal`) is built around a specific `FeeReceipt` row — they look up or reverse the ledger transaction tied to *that* receipt. A `FestFeeCredit` isn't tied to any one receipt (that's the whole point — one receipt often funds several items, see §9.1), so posting it correctly would mean designing a new kind of ledger entry — an adjustment not backed by a specific receipt — which doesn't exist in this codebase today. Getting a double-entry posting wrong (wrong account, wrong debit/credit direction, unbalanced entry) actively corrupts the accounting reports; the current state (credit correctly tracked, just invisible to the Ledger/Financial Statements pages) is incomplete but not wrong. I'm not willing to guess at new ledger-posting code without being able to run it against a real chart of accounts and verify the entries balance — this needs someone who can test it live, not a blind implementation.

### 13.4 Full audit pass — other findings, no changes needed

Went through every file touched this session looking for bugs, race conditions, and bad data, beyond the one fixed in §13.1:

- **No row locking on the fee record during `rejectMany()`/`cancelWithRefund()`'s before/after snapshot.** Two admin actions racing on the *same* school's fee at the *exact* same moment could theoretically produce an incorrect delta or a duplicate credit. Low real-world likelihood (this requires two admins acting on the same school within the same request window), but genuinely possible — not fixed, since a proper fix (`lockForUpdate()` wrapped in a transaction) touches `recalculate()` itself, which is called from many more places than this session's changes and deserves its own careful pass rather than a rushed addition here.
- **Allocation order in `itemPaymentAllocation()` is a heuristic, not a certainty.** "Oldest submitted first" is a reasonable default for which items a partial payment "covers," but a school's upload was never itemized at the point of payment — there's no way to know which item they actually intended to pay for. Documented as an assumption in the method's own docblock; not a bug, but worth knowing before treating the checklist as authoritative.
- **Minor rounding drift is possible but not consequential.** `itemPaymentAllocation()` rounds each line to 2 decimals independently; `participationTotal()` rounds only the final sum. In principle these can disagree by a paisa on some fee configurations. Not fixed — the amount is too small to matter financially, and rounding every intermediate step to match would be over-engineering relative to the actual risk.
- **No model observers on `FestMark`/`FestParticipant`** that `cancelWithRefund()`'s mark-deletion/chest-number-clearing could conflict with — confirmed clean, no side effects to worry about.
- Verified `FestFeeCredit.amount`'s Eloquent `decimal:2` cast (which returns a string, a well-known Laravel behavior) is only ever consumed through explicit `(float)` casts or query-builder `sum()` (which bypasses model casting) in this session's code — no string-where-number-expected bugs found.

## 14. Other pages now blind to credit — FIXED (23 Jul 2026)

Credit visibility was originally scoped to exactly two surfaces: `EventBillingPanel.vue` (school) and `Fees.vue` (Sahodaya admin, per-event). Everything else that reads `FestSchoolEventFee.total_due`/`outstandingBalance()` still showed the pre-credit number, since none of it knew `FestFeeCredit` existed. All five surfaces below have now been updated — additively, in every case: existing totals (`total_due`, `fest`, `fest_outstanding`, `amount`) are untouched, credit is surfaced as a new adjacent field/line so nothing that already consumes the old shape breaks.

| Surface | File | Fix |
|---|---|---|
| Fee status PDF + payment CSV export | `FestEventFeesController::pdfReport()`/`exportPayments()` | Rows carry `available_credit`; PDF gets a per-row inline note + conditional "Credit Owed" summary tile; CSV gets a credit column |
| `fees` Excel export + interactive preview | `FestExportService::fees()`, `FestEventReportAnalyticsService::feeCollectionRows()`, `FeeCollection.vue` | "Credit Owed" export column; UI shows credit under the Due column per row. `feeBreakdown()` (line-item level) deliberately left untouched — credit doesn't map to a single line |
| Finance collection dashboard | `FinanceHubController::index()`/`receivables()`, `Hub.vue`, `Receivables.vue` | New separate `fest_credit` summary figure (never netted into `fest_outstanding`); receivables rows/totals get `available_credit` |
| Cross-program payment history (school + Sahodaya) | `SchoolPaymentHistoryService::mapFestRow()` → `PaymentHistoryController`, `UnifiedPaymentsController`, `Index.vue`, `UnifiedPayments.vue` | One shared `available_credit` field flows through both the school-side and Sahodaya-side unified payment pages; both CSV exports and both UIs annotate it |
| Invoice PDFs | `FestInvoiceService::invoiceViewData()`, `invoice.blade.php`, `invoice-detailed.blade.php` | New `schoolCredit` figure (summed across all `FestSchoolEventFee` rows for that school+event, so it works for both single-fee and per-head billing) rendered as a conditional line next to Total Due |

**Not a blind spot:** the audit log. Checked `AuditLogCatalog` — there's no action→label registry to update; both new actions (`fest_fee_credit.issued`, reused `fest.registration.cancelled`) already display correctly from their explicit `category` and free-text `description`.

All edits verified brace-balanced (`grep -o "{"` vs `"}"` count match) across every touched PHP/Blade/Vue file; no PHP runtime available in this environment to run a full linter or execute the migrations.

Fee tracking is **per school-per-event (or per Event-Head)**, not per-registration: `FestSchoolEventFee` (`total_due`, `amount_paid`, `status`) aggregates every item a school has registered for that event/head, with proofs in `FeeReceipt` (`uploaded → approved/rejected`) and partial-payment math in `TracksPartialPayments` (`outstandingBalance()`, `isPartiallyPaid()`, status `pending → proof_uploaded → partial → approved`). Registration itself lives on `FestRegistration` (`draft/submitted/pending_approval/waitlisted/approved/rejected/withdrawn`), one row per item per student/team. **Almost every gap below traces back to that split**: approval is per-registration, money is per-school-aggregate, and the two aren't always kept in sync when one changes after the other.

---

## 1. Approval

- **Works as designed**: `FestRegistrationReviewController::approve` (via `FestRegistrationApprovalService`) checks `require_fee_before_approval` policy + `FestSchoolEventFeeService::isPaidForRegistration()` before allowing approval, when that policy is on for the event.
- **Gap — "paid" is checked at the aggregate level, not the item level.** `isPaidForRegistration()` resolves to the whole per-head/per-event fee balance, not a per-item allocation. If a school has paid enough to cover *some* but not *all* of what it owes, there's no guarantee the approval only fires for the items actually covered — a school that's underpaid overall could still get a specific item approved if the aggregate check happens to read "paid" at that moment (e.g. right after one receipt is approved, before the next item's cost is added in). Worth confirming this can't produce an item marked `approved` while the school is genuinely short on that item's specific fee.
- **Gap — no `approved_at` / `approved_by` / `rejection_reason` columns on `FestRegistration` itself.** That data only exists in the audit log (`PlatformAuditLogger::festRegistrationApproved/Rejected`), a separate table. Any report or UI that wants "who approved this and when" for a registration has to join out to audit logs rather than read the record directly — fine for now, but fragile if audit logging is ever disabled/pruned.

## 2. Rejection

- **Confirmed gap — no refund/credit logic on rejection.** `FestRegistrationBulkService::rejectMany` sets `status = rejected` and calls `FestSchoolEventFeeService::recalculate()`, which shrinks `total_due` for the item removed — but `amount_paid` is never adjusted. If the school had already paid for that item, they're now silently overpaid/in credit on the aggregate fee record, with **no refund record, no credit note, and nothing surfacing "you're owed ₹X" to the school or to finance.** This is the sharpest gap in the whole flow — money paid for a rejected item just sits unaccounted for.
- **Gap — no dedicated "resubmit a rejected registration" action.** A rejected item appears to require creating a brand-new `FestRegistration` row through the normal registration flow rather than reopening the rejected one. Not necessarily wrong, but undocumented — worth confirming schools aren't confused into thinking a rejected item can be "fixed and resubmitted" in place.

## 3. Partial payment

- **Supported, reasonably well** — `TracksPartialPayments` lets a school upload/accumulate multiple receipts until `outstandingBalance()` hits zero, with `attachPayment()` capping any single payment at the remaining balance. Status correctly walks `pending → proof_uploaded → partial → approved`.
- **Gap — same aggregate-vs-item mismatch as §1.** Because partial payment is tracked at school/event(/head) level, the system can't say *which* specific items a partial payment "covers." Two schools, one paying 90% and one paying 10% of the same `total_due`, both show `status = partial` with no way to tell which items are safe to approve without a manual judgment call by the reviewing admin — the UI/service doesn't compute "this specific item's approval is backed by payment," only "the school owes X and has paid Y in total."
- **Not fully verified — dunning/reminders.** `SendFestPaymentReminders` command exists but its cadence/trigger logic wasn't inspected in this pass; worth a follow-up check if outstanding-balance follow-up is something you're relying on.

## 4. Cancelling an already-approved registration — FIXED (23 Jul 2026)

- **Confirmed, explicit gap — there is no cancel path once payment is approved.** `FestRegistrationService::canAdminCancel()` blocks cancellation outright if `results_published` is true, **or if any approved payment amount exists against that fee record** — the code comment says this directly: a registration "may only be cancelled pre-payment-approval; once any amount has been approved, cancellation is no longer allowed." In practice: once a student is paid-and-approved, there was **no supported way to withdraw them** (injury, ineligibility discovered late, family emergency, etc.) short of manual database intervention. No refund workflow, no "cancel with refund" action, nothing.
- **Gap — even where cancel *is* allowed (pre-payment-approval), chest numbers weren't freed/reassigned and marks weren't touched.** If marks were ever entered against that participant before cancellation (possible via back-office correction flows), `cancel()` didn't clean up `FestMark` rows tied to the now-cancelled `participant_id` — those became orphaned records pointing at a cancelled entry.
- **Gap — no reconciliation step for an uploaded-but-not-yet-approved receipt at cancel time.** Not addressed by this fix — still a known limitation for the narrow case of a school cancelling while an unreviewed receipt is sitting in `uploaded` status.

**Fix:** deliberately did **not** change `cancel()`/`canAdminCancel()` — that block stays exactly as-is for the case it already handles correctly. Added a new, separate, explicit path instead: `FestRegistrationService::canAdminCancelWithRefund()` / `cancelWithRefund()`, wired to a new controller action (`FestRegistrationReviewController::cancelWithRefund`) and route (`registrations.cancel-with-refund`), with a "Cancel & refund" button next to the existing "Cancel" button on `Registrations.vue` (requires a reason, mirrors the existing `reject()`/`forceApprove()` prompt pattern). It: requires a reason, still refuses once results are published, reuses the exact same fee-model-agnostic delta technique as §9.2 (measure the `total_due` reduction, record a `FestFeeCredit` — capped at what was actually paid) rather than touching `FeeReceiptReversalService` (same reasoning as §9.1 — one receipt often funds several items), and additionally frees the chest number and deletes any marks tied to the cancelled registration's participants (the one part of this gap that plain `cancel()` never had to handle, since a never-approved registration essentially never has either yet).

## 5. Registering a student / adding another item after approval — CORRECTED + FIXED (23 Jul 2026)

- **Mostly works as designed**: `FestBulkRegistrationService::assignStudentsToItems` → `FestRegistrationCreateService::createForSchool` creates a fresh `submitted` registration for the new item, gated per-item by `FestItemRegistrationGate::assertOpen()` (deadline check), and triggers `recalculate()` so the new item's fee is added to `total_due`.
- **Correction to the original finding below:** a closer read of `createForSchool()` shows it already checks `$school->fest_registration_closed` internally by default (`$skipSchoolClosedCheck` defaults `false`, and `bulkAssign` never passes `true`) — so this was **not** an active bypass of the guard as first described. It *is* enforced, just per-item, so a closed school's bulk-assign attempt fails on every row individually and reports N nearly-identical errors in `$errors[]` instead of failing once, up front, with one clear message. Correcting this now for the record rather than leaving the overstated version standing.
- **Fix:** added the same `$school->fest_registration_closed` check as an early guard at the top of `assignStudentsToItems()`, returning immediately with a single clear error instead of relying solely on the noisy per-item path. `createForSchool()`'s own check is left in place unchanged (defense in depth, and the source of truth for any other caller).
- **Gap — adding a new item can silently re-open payment status without touching already-approved sibling registrations.** Not addressed by this fix. Because fee status lives on the aggregate `FestSchoolEventFee` row, adding one more item raises `total_due`, which can flip that fee record's `status` back down from `approved`/fully-paid to `pending`/`partial` — but the *already-approved* `FestRegistration` rows for other items are not revisited or demoted. End state: individual registrations still say `approved`, while the school's aggregate fee record says money is owed again. This is a business-rule question (should a student be allowed to keep competing on an `approved` item while the school's overall balance has gone back into arrears?) rather than a clear-cut bug — worth an explicit product decision before building anything here, since automatically demoting sibling approvals would itself be a behavior change with its own blast radius.

---

## 6. What already exists on the reporting/UI side

Checked before proposing anything new — this is more built-out than it might seem:

**School Admin side** — the payment UI isn't a standalone page, it's the "Payment" tab inside the event registration screen (`SchoolAdmin/FestRegistrationController.php` + `EventBillingPanel.vue`). Today it shows: fee line-item breakdown, status pill (approved/pending/rejected), rejection reason with re-upload, "View Receipt" (once approved), invoice preview/download, and a link out to a read-only fee-summary report. There's also a separate cross-program **Payment History** page (`PaymentHistoryController` / `School/Payments/Index`) with approved/pending/partial/outstanding totals and CSV export — but that's account-wide, not scoped to one event.

**Sahodaya Admin side** — considerably more built than the school side: `FestReportCatalog` already ships `fees`, `fee-breakdown`, `fee-pending-schools` exports and `fee-collection`/`registration-register` interactive report pages; `FestEventFeesController` (per-event fee dashboard + ledger + PDF + payment export); `FestSchoolEventFeeController` (approve/reject/recalculate/force-approve); `FestPaymentsController` and `UnifiedPaymentsController` (cross-program payment lists, including a `reverseReceipt` action); a full `LedgerController` (financial statements, fee waivers, account heads); and `FinanceHubController` — a real collection dashboard with pending counts, outstanding sums, and a 12-month trend, aggregated across fest/membership/MCQ/training.

So: yes, more can be added — but the honest framing is "the school-facing side is thin relative to the admin-facing side," and a few of the gaps from §1–5 above have partial answers already sitting in code that just aren't wired together yet (e.g. `UnifiedPaymentsController::reverseReceipt` exists — worth checking whether that's actually usable as the refund/credit mechanism §2 says is missing, before building a new one).

## 7. Recommended additions

### Reports (mostly Sahodaya Admin / finance)

- **Per-item payment allocation report** — "which specific registrations are actually backed by an approved payment," not just the school's aggregate balance. Directly closes the §1/§3 aggregate-vs-item ambiguity and would double as the data source for gating approvals more precisely.
- **Refund/credit ledger** — a report (and underlying record) of every time a rejection or cancellation leaves a school in credit, so §2's "money just disappears" gap has somewhere to land. Check `reverseReceipt` first — it may already be most of this, just not surfaced as a report.
- **Outstanding-dues aging report, per school** — how long a balance has been outstanding, not just the current total (the `FinanceHubController` trend is time-series at the org level, not an aging view per school).
- **School-level drill-down inside `FinanceHubController`** — today it aggregates by program/category; a "click a school, see everything they owe across fest/membership/training" view would tie the existing per-event reports together in one place.
- **Reconciliation report** — recorded `FeeReceipt` approvals vs. actual bank/gateway statement, to catch mismatches (especially relevant once/if online payment gateway integration is added, see below).
- **Region/head-wise collection report** — once region-wise conduct (the earlier plan) ships, "how much has each region collected" becomes a natural ask; the existing `feeCollectionByHeadRows()` pattern in `FestEventReportAnalyticsService` generalizes to this directly.

### School Admin event-payment page (`EventBillingPanel.vue`)

- **Full payment history for this event**, not just the latest rejection reason — every receipt ever uploaded for this event/head, with its outcome, so a school can see its own track record without contacting the Sahodaya office.
- **Item-level "what's this payment covering" breakdown** — a checklist of which registered items are backed by approved payment vs. still outstanding, instead of one lump total. Same fix as the reporting gap above, surfaced where the school actually needs it.
- **Visual progress indicator** for partial payments (paid vs. total due), especially once multiple receipts are involved.
- **Refund/credit balance display** once §2 is fixed — if a rejection leaves the school in credit, that should show here with an option to apply it toward a new item instead of the school having to notice on its own.
- **Deadline awareness** — a due-date/registration-closing countdown on the panel itself, tied to the event's actual close date, so payment isn't the last thing a school notices before registration locks.
- **Dispute/query action on a rejected receipt** — right now the only response to rejection is silent re-upload; a "raise a query" action (with a note visible to the Sahodaya admin) would surface *why* something was rejected in ambiguous cases rather than a school guessing.
- **Instant upload acknowledgment** — a confirmation state immediately after upload (distinct from "approved"), so a school knows the upload succeeded before an admin has reviewed it.
- **Payment method tagging at upload** (UPI / bank transfer / cash / cheque) if not already captured — feeds directly into the reconciliation and collection-by-method reports above.

---

## 8. Will these break anything existing?

Checked against actual call sites before answering. **Most of §7 is purely additive — new tables, nullable columns, new read-only reports, new UI panels — and carries no risk to existing behavior.** Two items need a more careful rollout than "just build it":

- **Per-item payment allocation.** `isPaidForRegistration()` — the method that gates approval — is called from **four separate places**: `FestRegistrationReviewController` (single approve), `FestRegistrationBulkService` (bulk approve), `Portal/FestEventOpsController`, and the `Api/V1` write controller. If the per-item allocation logic is wired directly *into* that method to make it stricter (checking a specific item's coverage instead of the school's aggregate balance), approval outcomes change at all four call sites simultaneously — a school that gets approved today under the aggregate check could start failing under a stricter per-item check, with no code change to the approval flow itself, just to what "paid" means. **Safer path:** build the per-item allocation as a separate, read-only method/report first (used for display and for the new item-level checklist on the school page), and only let it affect real gating later, behind an explicit per-event opt-in — not a silent redefinition of the shared method.

- **Refund/credit ledger.** This turned out to already exist: `UnifiedPaymentsController::reverseReceipt` + `FeeReceiptReversalService` reverses a `FeeReceipt` and **posts compensating ledger entries** — that's a real refund/credit mechanism, already wired into the ledger. The actual gap isn't a missing capability, it's that **rejecting a registration never calls it** — rejection just shrinks `total_due` and leaves `amount_paid` untouched. The low-risk fix is to trigger/surface this *existing* reversal action from the rejection flow (or offer it as a one-click "reverse this payment" prompt to the admin when they reject an already-paid item), not to build a second, parallel credit-tracking system. Building a new one alongside the existing reversal service is exactly the scenario that would risk double-booking or inconsistent ledger totals — that's the one true "could cause errors" risk in this whole list, and it's avoidable by reusing what's there.

Everything else in §7 — payment history display, item checklist (as a read-only view), progress bar, deadline countdown, dispute action, upload acknowledgment, payment-method tagging, the aging/drill-down/reconciliation/region reports — reads existing data or adds new optional fields/tables, and none of it touches `isPaidForRegistration()`, the approval flow, or existing fee calculations. Safe to build without a special rollout plan.

---

## 9. Build-ready fix specs (no code written — this is the exact spec to implement)

Checked the actual fee-line and reversal internals before finalizing these — one correction from §8 below: **calling `reverseReceipt()` on rejection is not actually safe and is dropped in favor of a new, smaller mechanism.** Details follow.

### 9.1 Correction: why `reverseReceipt()` must NOT be called from the rejection flow

`FeeReceiptReversalService::reverse()` reverses **the entire `FeeReceipt`** — one receipt can cover *multiple* registered items at once (a school typically uploads one payment proof for its whole outstanding balance, not one proof per item). If a school has 5 approved items funded by one ₹5,000 receipt and only 1 item (worth a fraction of that) gets rejected, calling `reverseReceipt()` would flip the *entire* receipt to `REVERSED` and, via `syncFeeableAfterReversal()`, call `refreshPaidState()` on the fee record — wiping out paid status for the other 4 items that are still legitimately approved and shouldn't be touched. That would be a real regression, not a fix. So: **leave `FeeReceiptReversalService` and the `reverseReceipt` endpoint completely untouched** — they stay exactly as they are today, for the case they're actually built for (an admin voiding a whole payment, e.g. fraud/duplicate).

### 9.2 Fix A — credit on rejection (replaces the reverseReceipt idea)

A new, additive, fee-model-agnostic mechanism: capture the fee delta a rejection causes, not an item "price."

**New table** (tenant migration):
```php
Schema::create('fest_fee_credits', function (Blueprint $table) {
    $table->id();
    $table->foreignId('fest_school_event_fee_id')->constrained()->cascadeOnDelete();
    $table->foreignId('source_registration_id')->nullable()->constrained('fest_registrations')->nullOnDelete();
    $table->decimal('amount', 10, 2);
    $table->string('reason')->nullable();
    $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
    $table->timestamp('applied_at')->nullable(); // set when a future fee absorbs this credit
    $table->timestamps();
});
```

**New model** `FestFeeCredit` (`belongsTo` `FestSchoolEventFee`, `belongsTo` `FestRegistration`).

**Change to `FestRegistrationBulkService::rejectMany()`** (`app/Services/Events/FestRegistrationBulkService.php:53-80`) — capture the fee **before** and **after** `recalculate()`, fee-model-agnostic (works identically for flat/tiered/per-item/composite billing, since it never tries to price the item itself, only measures the effect):

```php
foreach ($query->get() as $registration) {
    $feeBefore = $feeService->currentFeeRecordFor($event, $registration); // existing record, or null
    $dueBefore = (float) ($feeBefore?->total_due ?? 0);
    $paidBefore = (float) ($feeBefore?->amount_paid ?? 0);

    $registration->update(['status' => 'rejected']);
    $feeAfter = $feeService->recalculate($event, $registration->school_id); // existing call, unchanged

    $reduction = round($dueBefore - (float) $feeAfter->total_due, 2);
    if ($reduction > 0 && $paidBefore > 0) {
        FestFeeCredit::create([
            'fest_school_event_fee_id' => $feeAfter->id,
            'source_registration_id'   => $registration->id,
            'amount'                   => min($reduction, $paidBefore),
            'reason'                   => 'Registration rejected after payment',
            'created_by_user_id'       => auth()->id(),
        ]);
    }

    $feeService->recalculate($event, $registration->school_id);
    $notifier->registrationRejected($registration);
    $audit->festRegistrationRejected($registration);
    $rejected++;
}
```

(`currentFeeRecordFor()` is a one-line lookup helper to add to `FestSchoolEventFeeService` — same query `recalculate()` already does internally to find the existing row before recomputing it.)

**Why this is safe:** nothing here changes `total_due`, `amount_paid`, receipt status, or any existing return value — `recalculate()` is called exactly as it is today, at the same point in the flow. The only addition is a new row in a brand-new table, written *after* the existing logic runs. If the new `FestFeeCredit::create()` call were deleted entirely, `rejectMany()` would behave byte-for-byte as it does today. Every existing caller of `rejectMany()` (`FestRegistrationReviewController::reject`/`bulkReject`) is unaffected — no signature change, no new required parameter.

**Surfacing it (additive UI only):** show `sum(fest_fee_credits where fest_school_event_fee_id = X and applied_at is null)` as "Available credit: ₹Y" on the school's `EventBillingPanel.vue`, and let a Sahodaya admin apply it to a future `total_due` by subtracting available credit in `recalculate()`'s final total *only if credits exist* (a new, additive branch — zero effect for the 100% of schools that have none).

### 9.3 Fix B — per-item payment visibility (report + opt-in gate)

**Correction from §7/§8:** true per-item allocation is only a well-defined question for `fee_model = 'item_catalog'` or `'per_item'` billing, where each `FestEventItem` carries its own `fee_amount` and a per-item price genuinely exists. For `cksc_tiered`, `flat_school`, `per_student`, and `sports_composite` billing, cost is not attributable to a single item (tiered by count, flat per school, composite bundles) — there is no correct per-item number to show. **Scope this feature to `item_catalog`/`per_item` events only; hide the checklist and skip the opt-in flag entirely for other fee models**, rather than compute and display a number that would be misleading.

**New read-only method** on `FestSchoolEventFeeService`:
```php
/** Only meaningful for item_catalog/per_item billing — returns [] for other fee models. */
public function itemPaymentAllocation(FestEvent $event, string $schoolId): array
// Returns, per active registration: ['registration_id' => ..., 'item_title' => ..., 'amount' => ..., 'covered' => bool]
// Allocation order: registrations ordered by submitted_at (oldest first), walking cumulative
// amount_paid down the list — same "first paid, first covered" logic a human reviewer would use.
```

This method is called **only** from: (a) the new checklist UI endpoint on the school payment page, and (b) the new admin report in §7. It is explicitly **not** called from `isPaidForRegistration()` or any of its four existing callers (`FestRegistrationReviewController`, `FestRegistrationBulkService::approveMany`, `Portal/FestEventOpsController`, `Api/V1/Sahodaya/FestRegistrationsWriteApiController`) — those keep calling the existing aggregate check, completely unchanged, indefinitely, unless the opt-in flag below is explicitly turned on for a specific event.

**Opt-in gate** (only build after the read-only view has been live and reviewed by an admin for at least one full event cycle):
```php
// migration: add to fest_events
$table->boolean('strict_item_payment_gating')->default(false)->after('combine_regions_at_finale');
```
Inside `isPaidForRegistration()`, add exactly one new branch at the top:
```php
if ($event->strict_item_payment_gating && in_array($this->resolveSchedule($event)['fee_model'] ?? null, ['item_catalog', 'per_item'], true)) {
    return $this->itemPaymentAllocation($event, $registration->school_id)[$registration->id]['covered'] ?? false;
}
// existing logic below, byte-for-byte unchanged
```
Default `false` on every existing and new event — this is the same "additive, opt-in, no silent behavior change" pattern used for `combine_regions_at_finale` in the region/phase plan. No event's approval behavior changes unless a Sahodaya admin explicitly flips this flag for that one event, after having seen the read-only checklist first.

### 9.4 What this spec deliberately does NOT touch

- `FeeReceiptReversalService`, `reverseReceipt` endpoint, or any ledger-posting code — untouched, stays the tool for whole-receipt voids.
- `isPaidForRegistration()`'s default code path — unchanged for every event unless `strict_item_payment_gating` is explicitly set.
- `recalculate()`, `total_due`, `amount_paid` computation logic — unchanged; Fix A only reads values before/after the existing call.
- Any of the four existing callers of `isPaidForRegistration()` — no signature changes, no new required arguments.

---

## Summary — status as of 23 Jul 2026

1. ~~No refund/credit trail on rejection~~ (§2) — **fixed**, see §9.2 (`FestFeeCredit`).
2. ~~No cancellation path at all for a paid+approved registration~~ (§4) — **fixed**, see `cancelWithRefund()`.
3. ~~`bulkAssign` skips the registration-closed check~~ (§5) — **fixed** (and corrected: it was already enforced per-item, just noisily; now fails fast).
4. **Still open — aggregate fee status vs. per-registration approval can drift out of sync** (§1, §3, §5) — approval and "is this specific item actually paid for" aren't the same check by default (opt-in strict gating exists per §9.3, but is off unless an admin turns it on per event), and adding new items after approval doesn't revisit earlier approvals. This one is a product-rule decision, not a mechanical fix — flagging for a deliberate choice rather than building something unasked-for.
5. **Still open** — the region/phase plan (`docs/REGION_AND_PHASE_KALOTSAV_PLAN.md`) remains unimplemented, and the uploaded-but-unreviewed-receipt-at-cancel-time edge case (§4) was left alone.

---

## 10. Deployment & verification checklist

Nothing in §1–§9 has been run against a real database — this environment has no PHP runtime or DB connection to the project. Run this from your own machine/deploy pipeline before relying on any of it.

### 10.1 Migration ordering — read this first

**Deploy the code and run migrations together, migrations first (or atomically) — do not let the new code run against an un-migrated database.** `rejectMany()` and `cancelWithRefund()` now call `FestFeeCredit::create(...)` unconditionally whenever a paid registration is rejected/cancelled. If that code ships before `2026_09_03_000001_create_fest_fee_credits_table.php` has run on a given tenant DB, the very next rejection-of-a-paid-item or cancel-with-refund on that tenant will hard-fail with a "table doesn't exist" SQL error instead of degrading gracefully — this is the one real ordering hazard in this whole batch. Everything else (the `strict_item_payment_gating` column, the UI additions) degrades safely either order, since they're read with `?->`/`??` fallbacks.

```bash
php artisan migrate --force
php artisan sahodaya:provision-databases --no-create   # runs the two new tenant migrations on every Sahodaya DB
```

Confirm both landed on at least one tenant before testing:
```bash
php artisan tinker
>>> Schema::hasTable('fest_fee_credits')                              // expect true
>>> Schema::hasColumn('fest_events', 'strict_item_payment_gating')     // expect true
```

### 10.2 Regression check — confirm nothing existing changed

Do this on a staging tenant with real historical data, before testing anything new:

- Open an existing, already-approved registration's fee page (any event, any fee model) — `total_due`, `amount_paid`, `status` should read identically to before this deploy.
- Approve/reject a registration on an ordinary event that has never had `strict_item_payment_gating` touched — should behave exactly as before (the flag defaults `false`, so `isPaidForRegistration()` takes its original branch).
- If you have a Kids Fest or any other `conduct_mode = 'partitioned'` event on this tenant, check its combined scoreboard still renders — unrelated to this batch of changes, but worth a quick look since it's the one other place `FestSchoolEventFeeService`-adjacent code has shared surface area.

### 10.3 Fix A — credit on rejection

1. Pick (or create) a test registration, upload + approve a payment for it, then reject that specific registration.
2. Confirm a `fest_fee_credits` row was created with `amount` equal to the `total_due` reduction (capped at what was paid).
3. Reload the school's event registration page → `EventBillingPanel.vue` should show the green "Available credit" banner with the right amount.
4. Reload the Sahodaya admin Fees page for that event → the school's row should show the "Credit owed" badge.
5. Reject a registration that was **never paid** — confirm no credit row is created (the `paidBefore > 0` guard).

### 10.4 Fix B — per-item allocation + opt-in strict gating

1. On an `item_catalog` or `per_item` billed event, partially pay a school's balance (less than full `total_due`).
2. Check the school's billing panel — the new "What your payment covers so far" checklist should show some items ✓ covered, later-submitted ones ○ not yet covered (allocation is oldest-submitted-first).
3. Check the same on the admin Fees page's "Payment coverage" expandable row.
4. Confirm approval still uses the **aggregate** check by default (approve an item that the checklist shows as "not covered" — it should still succeed if the aggregate balance would have allowed it before this change, since the flag is off).
5. Turn on "Strict item-level payment gating" in that event's Settings → Locks tab, save, then retry approving an item the checklist shows as not covered — it should now be refused.
6. Turn the flag back off and confirm approval behavior reverts to the aggregate check.
7. Repeat step 4 on a `cksc_tiered`/`flat_school`/`sports_composite` event — confirm no checklist appears at all (empty `item_allocation`), and that flipping the flag there has no effect (the `in_array(...)` fee-model guard inside `isPaidForRegistration()`).

### 10.5 Fix §4 — cancel with refund

1. Approve and fully pay a registration that has at least one participant with a chest number assigned and (if possible) a mark entered.
2. Confirm the plain "Cancel" button still refuses with the existing error message — unchanged behavior.
3. Click "Cancel & refund", enter a reason, confirm.
4. Verify: registration status is `withdrawn`; the participant's `chest_no` is now `null`; any `FestMark` rows for that participant are gone; a `fest_fee_credits` row exists for the freed amount; the audit log shows both `fest.registration.cancelled` and `fest_fee_credit.issued` entries.
5. Try it again on a registration with results published on that event — should be refused.

### 10.6 Fix §5 — bulkAssign fail-fast

1. Set `fest_registration_closed = true` on a test school (via its normal admin toggle).
2. Attempt a bulk item assignment for that school — should get one clear error immediately, not one per student/item.
3. Set it back to `false` and confirm bulk assignment works normally again.
