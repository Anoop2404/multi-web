# Cross-System Flow & Decision Gap Audit

**Date:** 24 Jul 2026
**Scope:** every verify / reject / approve / cancel / withdraw transition across Fest (Kalotsav + Sports), MCQ, Training, Membership, Student Registry, Board Results, and State Remittances.
**Method:** read the actual controllers/services/routes, not the docs. Fest payment internals were already audited in `docs/FEST_PAYMENT_REGISTRATION_FLOW_GAPS.md` (largely fixed 23 Jul) — this doc covers everything else plus what's still open there.

Severity: 🔴 dead end / money at risk · 🟠 inconsistent or silent behavior · 🟡 polish / product decision.

---

## 1. Fest (Kalotsav & Sports)

State machine: `FestRegistration` `draft → submitted → approved | rejected | waitlisted | withdrawn`; fee: `FestSchoolEventFee` `pending → proof_uploaded → partial → approved | rejected` + `FestFeeCredit`.

| # | Sev | Gap | Where |
|---|-----|-----|-------|
| F1 | 🔴 | **Event-level cancel does nothing.** `quickStatus()` sets `status='cancelled'` and stops. No cascade to registrations, no fee credits for schools that already paid, and no notification — only `registration_open` and `completed` trigger the notifier. A cancelled event leaves approved registrations and collected money in limbo. | `FestEventController::quickStatus()` (~line 1044) |
| F2 | 🟠 | **Registration reject takes no reason.** Single `reject()` and `bulkReject()` accept nothing; the school is notified "rejected" with no why. Contrast: student verification, membership payment, MCQ fee, and `cancelWithRefund()` all *require* a reason. | `FestRegistrationReviewController::reject()/bulkReject()` |
| F3 | 🟠 | **Fee-proof rejection never notifies the school.** `approve()` notifies via `OfflineProgramFeeOrchestrator`; `reject()` only writes the audit log + a flash the admin sees. The school discovers the rejection only by revisiting its billing panel. | `FestSchoolEventFeeController::reject()` |
| F4 | 🟠 | **School withdraw is silent to the admin.** `withdraw()` audits but notifies nobody — an approved participant can vanish from an item without the Sahodaya (or event staff who scheduled them) hearing about it. | `SchoolAdmin/FestRegistrationController::withdraw()` |
| F5 | 🟡 | **No resubmit path for a rejected registration.** School must create a brand-new registration; nothing in the UI says so (already flagged as §2 in the fest gaps doc — still open). | — |
| F6 | 🟡 | **Still-open items from the fest gaps doc:** credit is tracked but only consumed via `forceApprove`; credit never posts to the ledger; no cash-refund path; no manual credit adjustment; aggregate-paid vs per-item approval drift (strict gating exists but is opt-in); uploaded-but-unreviewed receipt at cancel time is unreconciled. | `FEST_PAYMENT_REGISTRATION_FLOW_GAPS.md` §11–13 |

## 2. MCQ / Talent Search

State machine: `McqRegistration` `registered → started → submitted | absent | cancelled`; fee: `McqSchoolFee` `pending → proof_uploaded → partial → approved | waived`.

| # | Sev | Gap | Where |
|---|-----|-----|-------|
| M1 | 🔴 | **Dead-end cancel.** School-side cancel is correctly blocked once a registration is approved / hall-ticketed, with the message *"…must be handled by Sahodaya."* **But no Sahodaya per-registration cancel exists** — no route, no controller action. The message points at a workflow that isn't there. Only the whole-exam status can change. | `McqRegistrationController::cancel()` vs `routes/web.php` (no mcq registration cancel route) |
| M2 | 🔴 | **Cancel after payment silently strands money.** `syncForSchool()` recounts non-cancelled registrations and shrinks `total_due`; `amount_paid` stays. A school that paid for 20 students and cancels 3 (pre-approval) is silently overpaid — no credit record (no `FestFeeCredit` equivalent exists for MCQ), nothing surfaced to school or finance. | `McqSchoolFeeService::syncForSchool()` |
| M3 | 🔴 | **Exam `cancelled` status has no cascade.** Plain field update: registrations stay `registered`, paid/partial school fees are untouched, no notification to schools. Same shape as F1. | `McqExamController::update()` |
| M4 | 🟠 | **School cancel doesn't notify the Sahodaya** (audit log only) — mirrors F4. | `McqRegistrationController::cancel()` |

Working well: fee approve/reject loop (reject → reason required → school re-uploads), `lockForUpdate()` on approval (the only payment flow with concurrency protection), attendance-correction requests have a full pending/approved/rejected loop.

## 3. Training

State machine: `TrainingRegistration` `registered | waitlisted → confirmed → completed | cancelled`.

| # | Sev | Gap | Where |
|---|-----|-----|-------|
| T1 | 🟠 | **Schools cannot cancel a training registration at all.** The only cancel route lives in the Sahodaya group (`training/{program}/registrations/{registration}/cancel`). School-side controller has no cancel action — every drop-out is a phone call. (MCQ lets schools self-cancel pre-approval; Fest lets schools withdraw. Training is the odd one out.) | `routes/web.php:1234`; `SchoolAdmin/TrainingRegistrationController` |
| T2 | 🔴 | **Cancel after payment: same stranded-money gap as M2.** `cancelAndPromote()` re-syncs the school batch fee (`total_due` shrinks, `amount_paid` doesn't), and for individually-paid registrations an approved `fee_status` / issued invoice is never reversed or credited. | `TrainingWaitlistService::cancelAndPromote()`, `TrainingSchoolFeeService` |
| T3 | 🟡 | **Program `cancelled` status — no cascade found** for registrations/fees/notifications (same shape as F1/M3; not exhaustively verified). | `TrainingProgramController` |

Working well: waitlist auto-promotion on cancel; admin cancel guards against re-cancelling completed/cancelled rows.

## 4. Membership

| # | Sev | Gap | Where |
|---|-----|-----|-------|
| B1 | 🔴 | **No membership cancellation once any payment is submitted/verified.** `canCancel()` hard-blocks it; there is no "cancel with refund/credit" path. A school leaving mid-year after paying is a manual DB job. This is the same gap `cancelWithRefund()` closed for Fest — membership never got the equivalent. | `SchoolMembershipCancellationService::canCancel()` |
| B2 | 🟡 | **Rejected school application → re-apply loop unverified.** `membership_status='rejected'` is set with a notified reason, but whether the school can revise and resubmit (vs. being stuck) isn't clearly wired in the school UI. Worth a UX check. | `MemberSchoolsController::reject()` |

Working well: payment verify/reject requires status `submitted` (transition guard), reject requires a reason, both notify the school, and re-upload supersedes the old row (`superseded` status) — this is the cleanest reject→resubmit loop in the system.

## 5. Student / Teacher verification & annual registration

| # | Sev | Gap | Where |
|---|-----|-----|-------|
| S1 | 🟠 | **Rejection is just "unverified + reason" — no re-review signal.** A rejected student falls back into the same unverified pool. When the school fixes the data there's no state change, no notification to the Sahodaya, no way to distinguish "new, never reviewed" from "rejected, fixed, awaiting re-review" in the queue. | `StudentVerificationController::reject()` |
| S2 | 🟡 | Bulk reject notification collapses to "N students" with one shared reason — schools can't tell which students failed for what. | `bulkVerify()/bulkReject()` |

Working well: annual-registration tracks (`SchoolYearSubmissionReviewService`) have explicit allowed-from states (`pending/rejected`, counts also from `approved`) — the most deliberate transition guard in the codebase.

## 6. State remittances & board results

Remittances: `pending → submitted → verified | rejected`, and `uploadProof()` correctly allows re-upload from `pending|rejected`. Loop closed. ✅
Board results: `draft → submitted → verified → approved → published`, with reject; transitions wrapped in transactions. Not exhaustively audited, no obvious dead ends.

---

## 6b. Payment History pages — is any of this visible after the fact?

Audited 24 Jul: `SchoolPaymentHistoryService` (feeds both the school-side Payment History page and the Sahodaya Unified Payments page).

| # | Sev | Gap | Where |
|---|-----|-----|-------|
| P1 | 🔴 | **Rejection history is transient.** Every row shows only the fee record's single `feeReceipt` (BelongsTo). Partial payments create *multiple* receipts (`receipts()` MorphMany), and a re-upload after rejection repoints `fee_receipt_id` — the rejected receipt then **vanishes from history entirely**. You can only see a rejection while it's the latest receipt. Earlier approved partial receipts don't appear as rows either. (Fest's billing panel has `receiptHistoryPayload()`; the history pages don't use it.) | `SchoolPaymentHistoryService::buildRows()` + all `map*Row()` |
| P2 | 🔴 | **Cancelled/withdrawn things you paid for look "approved."** History is fee-record level. A cancelled training or MCQ registration whose fee was approved still renders status `approved` — no "cancelled after payment" marker, no date, no reason. Fest cancel-with-refund shows only as a net `available_credit` number on the fee row — there is no dated "₹X credited — registration cancelled" line item anywhere. | `mapTrainingRow()/mapMcqRow()` (no status filter, no cancelled flag) |
| P3 | 🟠 | **Reversed receipts are invisible on the school side.** Sahodaya `UnifiedPayments.vue` styles `reversed` red and hosts the reverse action — fine. But school `Payments/Index.vue`'s `statusClass()` knows nothing of `reversed`, `superseded`, `partial`, or `waived` (gray fallback), fest/batch rows show the *recomputed fee status* after reversal (`pending`/`partial` — looks like the school just never paid), and `programReceiptUrl()` nulls the receipt link once status ≠ approved, so the school also loses the receipt document. Net: a reversal looks like their payment evaporated, with no reason shown. | `Index.vue::statusClass()`, `programReceiptUrl()` |
| P4 | 🟠 | **Membership rejection reason is hardcoded `null`** in the history row, even though the reject flow captures and notifies one. | `mapMembershipRow()` line ~166 |
| P5 | 🟡 | **Summary math mixes due and paid.** School summary `total` sums `amount`, which is `total_due` for fest/training-batch/MCQ-batch rows (not what was paid) and includes rejected/pending rows — so the headline "total" is neither "total paid" nor "total owed." | `PaymentHistoryController::index()` |

What already works: current-receipt rejection reason per row, fest `available_credit` column, membership superseded rows kept in the list (full attempt trail), and `FeeReceiptReversalService` does properly downgrade the feeable's status (even sets training `registration_status = payment_rejected`) — the data layer is better than what the pages show.

**Fix direction:** make history receipt-level, not fee-record-level — one row per `FeeReceipt` (uploaded/approved/rejected/reversed, with reason + reviewer), plus explicit rows for `FestFeeCredit` issuance. That single change closes P1–P3 and gives cancellations a visible money trail.

## 7. Cross-cutting patterns (the real root causes)

1. **No transition guards as a rule.** Statuses are plain strings updated ad-hoc. Membership payment (`abort_unless status==='submitted'`) and MCQ fee approval (`lockForUpdate`) guard; most others don't — e.g. `quickStatus()` will happily move an event `completed → draft`, and Fest `reject()` needed a hand-written guard (added 23 Jul) to stop rejecting paid registrations. There's no shared state-machine helper; every flow reinvents (or forgets) the rules.
2. **"Cancel the container" is never implemented.** Event/exam/program-level `cancelled` is a cosmetic field in all three programs (F1, M3, T3): child registrations, collected money, and notifications are untouched every time.
3. **Money on cancel/reject is only solved in Fest.** `FestFeeCredit` exists; MCQ and Training have the identical shrink-`total_due`-keep-`amount_paid` bug it was built to fix (M2, T2), and Membership has no exit path at all (B1). The credit mechanism should be generalized, not cloned.
4. **Notification asymmetry.** Approvals notify; rejections sometimes do (student ✅, membership ✅, fest registration ✅, fest fee ❌); school-initiated cancels/withdrawals notify admins nowhere (F4, M4).
5. **Rejection reasons are required in half the flows and absent in the other half** (F2). Schools can't fix what they aren't told.

## 8. Suggested priority

| Priority | Items |
|---|---|
| P1 — money/dead ends | M1 (Sahodaya MCQ cancel + credit), M2/T2 (generalize fee credit to MCQ + Training), F1/M3 (define + implement container-cancel cascade: block if payments exist, or auto-credit + notify), B1 (membership cancel-with-credit decision) |
| P2 — silent flows | F3 (fee-reject notification), F2 (reason on fest reject), F4/M4 (notify admin on school cancel/withdraw), T1 (school-side training cancel, pre-approval only) |
| P3 — structure | Shared status-transition helper (allowed-from map per model, modeled on `SchoolYearSubmissionReviewService`), S1 re-review state, resubmit UX for rejected fest registrations (F5), remaining fest-doc §11 items (ledger posting, cash refund) |

Most P1 items are product decisions before they're code: *what should happen to paid money when the thing it paid for goes away?* Fest answered it (credit, no cash). The rest of the system just needs the same answer applied.
