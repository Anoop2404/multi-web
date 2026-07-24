# Flow Gap Fix Plan — Registrations, Payments, Rejections, Cancellations

**Date:** 24 Jul 2026
**Inputs:** `docs/CROSS_SYSTEM_FLOW_GAP_AUDIT.md` (24 Jul) and `docs/FEST_PAYMENT_REGISTRATION_FLOW_GAPS.md` (§11–14 open items).
**Goal:** close every dead end, stranded-money path, and silent transition across Fest, MCQ, Training, Membership, Student Registry, and the payment-history surfaces — using the patterns that already work in this codebase instead of inventing new ones.

**Reference implementations to copy, not reinvent:**
- Money-on-removal → `FestFeeCredit` + delta-snapshot technique (`FestRegistrationBulkService::rejectMany()`)
- Reject→resubmit loop → Membership payments (guarded transition, required reason, notify, `superseded` re-upload)
- Concurrency → `McqSchoolFeeService::approve()` (`lockForUpdate` in transaction)
- Cancel-with-consequences → `FestRegistrationService::cancelWithRefund()` (reason, credit, cleanup, dedicated notification)
- Transition guards → `SchoolYearSubmissionReviewService` (explicit allowed-from states per action)

---

## Phase 0 — Product decisions ✅ RESOLVED (24 Jul 2026, optimized for long-term)

Decided on the basis of: one money model platform-wide, full auditability, no silent behavior, and no mechanism the office can't explain to a school.

| # | Decision | Rationale |
|---|----------|-----------|
| D1 | **(a) Credit toward future fees** — the fest model, via the shared `program_fee_credits` table. | One credit concept everywhere beats three per-program answers. Every rupee removed from `total_due` after payment leaves a dated, attributable record. |
| D2 | **Yes — Sahodaya can cancel, pre-`started` only, reason required.** Hall ticket voided, seat freed, credit issued. Once the exam has `started`, the registration is history, not state — use `absent` instead. | Closes the dead end without ever letting cancellation rewrite an exam that already happened. |
| D3 | **Block cancel while approved payments exist; explicit `confirm_credit_all` override credits every paid school + notifies.** | Default-safe: nobody cancels an event with money in it by accident, but there is always a supported path out. |
| D4 | **Credit toward next year's membership** (default), with **forfeit as an explicit admin choice, reason required**. No cash in-platform. | Consistent with D1/D6; keeps membership exits on the same credit rails as everything else instead of a special case. Forfeit stays available for disciplinary exits but is never the silent default. |
| D5 | **No auto-demotion of approved registrations.** Instead: a visible "balance re-opened" flag on the admin Registrations page + school billing panel whenever the aggregate fee drops below fully-paid after approvals exist. Events that want hard enforcement already have the `strict_item_payment_gating` opt-in. | Demoting approvals retroactively punishes students for a school's later action; visibility + opt-in strictness covers the real risk without the blast radius. |
| D6 | **Credit-first stays the platform policy — but build `credit_payouts` (4.3) so an out-of-platform bank refund can be *recorded* against a credit and close it out.** No in-platform money movement, ever. | Long-term honesty: credits that can never terminate become permanent liabilities. A payout record keeps the books closable without the platform touching real money. |

---

## Phase 1 — Money integrity (dead ends & stranded money)

### 1.1 Generalize fee credits to MCQ and Training  `[L]`

**Problem (M2, T2):** cancelling a paid-for MCQ/Training registration shrinks `total_due` but not `amount_paid` — silent overpayment, invisible to everyone.

**Design:** a shared polymorphic credit table rather than cloning `fest_fee_credits` twice.

- Migration `program_fee_credits`: `id`, `creditable_type` + `creditable_id` (morph → `McqSchoolFee` / `TrainingSchoolFee` / `TrainingRegistration`), `source_type` + `source_id` (morph → the cancelled registration), `amount decimal(10,2)`, `reason`, `created_by_user_id`, `applied_at nullable`, timestamps.
- Model `ProgramFeeCredit` + a small `IssuesFeeCredits` service trait implementing the **same before/after delta snapshot** as `rejectMany()`: capture `total_due`/`amount_paid` before the status change, resync, `credit = min(dueReduction, paidBefore)`.
- Wire into:
  - `McqRegistrationController::cancel()` (school-side, pre-approval) — around the existing `syncForSchool()` call.
  - New Sahodaya MCQ cancel (1.2).
  - `TrainingWaitlistService::cancelAndPromote()` — both the batch-fee resync path and the individually-paid path (for individual: credit = the registration's approved `amount_paid`).
- **Leave `FestFeeCredit` untouched** for now. Consolidating fest onto the new table is an optional later migration (4.5) — do not block Phase 1 on it.
- Surfacing (copy fest §14's additive pattern): `available_credit` already flows through `SchoolPaymentHistoryService` for fest — extend `mapMcqBatchRow()`/`mapTrainingBatchRow()`/`mapTrainingRow()` to read `ProgramFeeCredit` sums; add credit line to MCQ/Training school-side fee panels and the Sahodaya payments pages.
- Consumption: mirror fest — mark `applied_at` from the existing force-approve/settle actions only. No silent auto-consumption (same reasoning as fest doc §13.2 — `TracksPartialPayments::refreshPaidState()` recomputes `amount_paid` from receipts and would silently overwrite anything else).

**Acceptance:** cancel a paid MCQ registration → credit row exists, school panel + payment history show it, `total_due`/`amount_paid`/receipts byte-identical to today otherwise.

### 1.2 Sahodaya-side MCQ registration cancel  `[M]`

**Problem (M1):** school-side block message says "must be handled by Sahodaya," but no such action exists.

- Route: `POST /sahodaya-admin/{tenant}/mcq-exams/{exam}/registrations/{registration}/cancel`.
- Controller action on `McqExamController` (or new `McqRegistrationAdminController`): reason **required**; guard `abort_unless(in_array($status, ['registered']) || ($status === 'submitted' ? false : ...))` — per D2, block once `started`/`submitted`; void hall ticket number; free seat (`McqSeatingService` resync); `syncForSchool()` + credit via 1.1; audit `mcq.registration.cancelled_by_sahodaya`; notify school (template `mcq.registration.cancelled_admin` with reason).
- UI: cancel button on the exam registrations list, mirroring fest's Registrations.vue prompt pattern.
- Update the school-side error message to name the actual page.

### 1.3 Container-cancel cascades (fest event / MCQ exam / training program)  `[M]`

**Problem (F1, M3, T3):** `status = 'cancelled'` is cosmetic in all three programs.

One shared behavior, implemented per program (per D3):

1. **Guard:** on transition to `cancelled`, if any approved payment exists for the container → refuse with a count + total, unless request carries `confirm_credit_all: true`.
2. **Cascade (in transaction):** active child registrations → `withdrawn`/`cancelled` (audit each batch, not per-row); issue credits for every school with `amount_paid > 0` using the 1.1/FestFeeCredit mechanism; leave receipts and ledger untouched (credits, not reversals — same §9.1 reasoning).
3. **Notify:** one notification per affected school (`{program}.event.cancelled`, includes credit line if any). Add `eventCancelled()` to `FestEventNotifier` (slot next to `eventCompleted()`), and equivalents on `McqExamNotifier` / training notifier.
4. **Entry points:** `FestEventController::quickStatus()` + full settings update; `McqExamController::update()`; `TrainingProgramController` status update.

**Acceptance:** cancelling an event with paid schools either refuses or (confirmed) leaves every school with correct credit + notification; cancelling a never-paid draft event behaves exactly as today.

### 1.4 Membership cancellation after payment  `[M]`

- Extend `SchoolMembershipCancellationService` with `cancelWithSettlement(Tenant $school, string $reason, string $settlement)` where settlement ∈ `credit_next_year` (default) | `forfeit` (explicit, reason required) — per D4.
- Keep `canCancel()` and the existing no-payment path byte-identical; new action is separate (same pattern as fest `cancel()` vs `cancelWithRefund()`).
- Audit + notify school; surface on MemberSchools UI behind a confirm-with-reason dialog.

---

## Phase 2 — Silent flows & missing loops

### 2.1 Notify school on fest fee-proof rejection  `[S]`

`FestSchoolEventFeeController::reject()` — add the counterpart of `approve()`'s notification: new `OfflineProgramFeeOrchestrator::notifyRejected()` (mirror of `notifyApproved()`, includes `rejection_reason`), called after the receipt update. MCQ already does this (`schoolBatchFeeRejected`) — copy its shape.

### 2.2 Require a reason on fest registration reject  `[S]`

- `FestRegistrationReviewController::reject()`: validate `reason required|string|max:500` (same as `cancelWithRefund()`); pass into `FestEventNotifier::registrationRejected()` and the audit entry.
- `bulkReject()`: one shared reason for the batch, required.
- Persist: add nullable `rejection_reason` (+ `rejected_at`, `rejected_by_user_id`) to `fest_registrations` — closes fest doc §1's "who/when/why only lives in audit log" gap too.
- UI: Registrations.vue reject prompt (pattern already exists for cancel-with-refund).

### 2.3 Notify Sahodaya when a school withdraws/cancels  `[S]`

- Fest: `registrationWithdrawn()` currently notifies the school + head extras. When the *school* initiates (`SchoolAdmin::withdraw()`), also notify `sahodaya_admin`/`event_coordinator` users (the `withSahodayaUsers()` helper already exists in `FestEventNotifier`).
- MCQ: `McqRegistrationController::cancel()` — add `McqExamNotifier` call to Sahodaya users (currently audit-only).

### 2.4 School-side training cancel  `[M]`

- Route in the school group: `POST training/{program}/registrations/{registration}/cancel`.
- Guard: only `registered`/`waitlisted` and `fee_status` not approved — once confirmed/paid, show "contact Sahodaya" (which, unlike MCQ's message, is true: the admin cancel exists).
- Reuse `TrainingWaitlistService::cancelAndPromote()`; notify Sahodaya (2.3 pattern); credit not needed pre-payment.

### 2.5 Membership rejection reason on history page  `[S]`

`SchoolPaymentHistoryService::mapMembershipRow()` — replace the hardcoded `'rejection_reason' => null` with the actual stored reason (verify column: `membership_payments.rejection_reason` vs the receipt's).

---

## Phase 3 — Payment history rebuild (receipt-level)  `[L]`

**Problem (P1–P3, P5):** history is fee-record-level with one receipt per row → rejections vanish on re-upload, partial receipts invisible, reversals look like the payment evaporated, summary mixes due/paid.

**Design — additive, keep the existing rows:**

1. New `receipt_rows` mode in `SchoolPaymentHistoryService`: one row per `FeeReceipt` (all statuses: `uploaded`, `approved`, `rejected` + reason + reviewer, `reversed` + reason) via the `receipts()` MorphMany, plus one row per `FestFeeCredit`/`ProgramFeeCredit` issuance ("₹X credited — {reason}", dated). The fest billing panel's `receiptHistoryPayload()` already proves the shape — generalize it.
2. UI: School `Payments/Index.vue` and Sahodaya `UnifiedPayments.vue` get an expandable per-fee timeline (or a "detailed" toggle). Add missing `statusClass` entries everywhere: `reversed` (red), `superseded` (gray strikethrough), `partial` (amber), `waived` (blue), `credit` (green).
3. Cancelled-thing marker (P2): `mapTrainingRow()`/`mapMcqRow()` read the registration's own status — when `cancelled`, prefix label "CANCELLED — " and style muted, regardless of fee status.
4. Summary fix (P5): school summary reports `total_paid` (sum `amount_paid`), `total_due`, `outstanding`, `credit` as separate figures; stop summing mixed `amount`.
5. Reversed receipts school-side (P3): keep the receipt link for reversed receipts (watermarked "REVERSED" via existing receipt render) instead of nulling it in `programReceiptUrl()`, and show the reversal reason.

**Acceptance:** reject → re-upload → approve leaves all three receipts visible with dates/reasons; a reversal shows red with reason on both sides; every credit has a dated line item.

---

## Phase 4 — Structural hardening & remaining features

### 4.1 Shared status-transition guard  `[M]`

Small `TransitionsStatus` trait / `StatusTransition::assert($model, $to, array $allowedFrom)` helper. Adopt incrementally — first on the endpoints with known invalid-transition exposure:
- `FestEventController::quickStatus()` (currently allows `completed → draft`): define the legal matrix (`draft → published → registration_open → ongoing → completed`; `cancelled` from any non-completed; backwards moves admin-confirmed only).
- MCQ exam / training program status updates — same treatment.
- Registration approve/reject/cancel endpoints that currently rely on hand-written `abort_if`s.
No behavior change where transitions are already legal; illegal ones get a clear 422 instead of silent acceptance.

### 4.2 Student verification re-review state  `[M]`

- Add `verification_state` derived or explicit: `pending | verified | rejected | resubmitted`. Minimal version: keep `verified_at`/`rejection_reason`, add `resubmitted_at` set by a `Student` observer when a school edits a rejected student's tracked fields.
- Queue ordering + filter chip "Fixed after rejection" on `StudentVerificationController::index()`; notify Sahodaya on resubmission.
- Per-student reasons in bulk reject (S2): accept optional `reasons[student_id]` map, fall back to shared reason.

### 4.3 Refund/credit ledger visibility  `[L]`

The remaining fest-doc §11/§13.3 items, done once for all programs after 1.1:
- Credit ledger posting: new adjustment entry type in `LedgerPostingService` (not receipt-backed) — needs live verification against a real chart of accounts; do not blind-ship (per §13.3's warning).
- Standalone credit/refund report under Finance Hub: every credit issued/applied across fest+mcq+training, filterable by event/school/date (closes fest doc §12's "dedicated report" gap).
- Manual credit adjustment action (goodwill credit/debit, permission-gated, reason required).
- `credit_payouts` table (who, when, how much, bank ref, recorded_by) — records an out-of-platform bank refund against a credit and marks it closed. Per D6: the platform never moves money, but every credit must be terminable (applied, forfeited with reason, or paid out and recorded).

### 4.4 Resubmit UX + balance-reopened flag  `[S]`

- Rejected row on the school's registration page gets the reason (from 2.2) + a "Register again" shortcut pre-filling item/students. No status reopening — new row through the normal flow (keeps history clean).
- Per D5: "Balance re-opened" warning badge on the admin Registrations page and school billing panel whenever a school has approved registrations but its fee record has dropped below fully-paid (read-only computed flag; no status changes).

### 4.5 Optional consolidation  `[S]`

Migrate `fest_fee_credits` rows into `program_fee_credits` and alias `FestFeeCredit` — only after 1.1 has been stable for a full event cycle.

### 4.6 Concurrency pass  `[M]`

Extend the `lockForUpdate` pattern from `McqSchoolFeeService::approve()` to: fest fee approve/reject, `recalculate()`'s read-modify-write, and the credit delta snapshots (fest doc §13.4's known race). One deliberate pass, tested, not piecemeal.

---

## Notification templates to add

| Slug | Trigger | Audience |
|------|---------|----------|
| `fest.fee.rejected` | 2.1 | School |
| `fest.registration.rejected` (add `reason`) | 2.2 | School |
| `fest.event.cancelled` / `mcq.exam.cancelled` / `training.program.cancelled` | 1.3 | Schools (with credit line) |
| `mcq.registration.cancelled_admin` | 1.2 | School |
| `mcq.registration.cancelled_by_school` / fest school-withdraw admin notice | 2.3 | Sahodaya |
| `training.registration.cancelled_by_school` | 2.4 | Sahodaya |
| `membership.cancelled_with_settlement` | 1.4 | School |
| `student.verification.resubmitted` | 4.2 | Sahodaya |

Seed via the existing `NotificationTemplate` seeder path; all sends wrapped in try/catch per the `quickStatus()` convention (notifications never block the action).

---

## Rollout order & hazards

1. **Phase 2 first** (a few days, near-zero risk, immediately visible) — 2.1, 2.2, 2.5, then 2.3/2.4.
2. **Phase 1** next: 1.1 → 1.2 → 1.3 (1.3 depends on 1.1's credit mechanics) → 1.4.
3. **Phase 3** after 1.1 (credit rows must exist to appear in the timeline).
4. **Phase 4** continuous; 4.3 last (ledger risk).

**Migration hazard (same class as fest doc §10.1):** any code that writes `ProgramFeeCredit` unconditionally must ship together with (or after) the `program_fee_credits` tenant migration — `php artisan migrate --force` + `sahodaya:provision-databases --no-create` before deploy, or guard writes with `Schema::hasTable()` for one release.

**Blast-radius rules (carried over from the fest work):**
- Never modify `TracksPartialPayments` — it backs fest, MCQ, training, and membership simultaneously.
- Never call `FeeReceiptReversalService` from rejection/cancel flows (one receipt funds many items — §9.1).
- Every fix is additive: existing statuses, columns, and return shapes unchanged; new behavior behind new actions/params.

---

## Verification checklist (per phase, staging tenant with real data)

- **Regression baseline:** existing approved fee pages show identical `total_due`/`amount_paid`/`status` before/after each deploy; ordinary approve/reject on an untouched event behaves byte-identically.
- **1.1:** cancel paid MCQ reg → credit row + panel banner + history line; cancel unpaid → no credit. Same for training (batch + individual).
- **1.2:** cancel approved MCQ reg as Sahodaya → hall ticket voided, seat freed, credit issued, school notified; blocked once exam `started`.
- **1.3:** cancel event with paid schools → refused; with `confirm_credit_all` → per-school credits + notifications; draft event with no money → cancels exactly as today.
- **2.x:** every reject/cancel produces exactly one notification to the right audience with the reason in it.
- **3:** reject → re-upload → approve → reverse leaves a 4-line visible timeline on both school and Sahodaya pages.
- **4.1:** `completed → draft` via quickStatus now 422s; all forward transitions unchanged.
- **Concurrency (4.6):** two parallel approve requests on one fee → exactly one succeeds, no double credit.
