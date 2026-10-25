# Accounts module audit — 25 October 2026

Implementation follow-up: see [Accounts implementation](ACCOUNTS_IMPLEMENTATION_2026-10-25.md). The findings below record the original pre-fix audit.

## Outcome

The system has fee collection and a basic double-entry ledger, including expenses and vendor payables. It is not yet a complete or reliable accounts workspace: targeted tests reproduced financial-report and tenant-isolation defects. Fix these before adding broader accounting features.

This was an audit. Application code and live financial records were not changed. Automated mutations used the isolated test database. Live browser inspection covered the Finance hub, Ledger and Financial statements on Malappuram Central Sahodaya. Browser transport failed during the Payables review; remaining UI findings come from Vue/controller inspection. Mobile, full live end-to-end actions, load/concurrency tests, actual email delivery and external bank integration were not verified.

## Existing capabilities

| Capability | Status and usability |
| --- | --- |
| Membership, fest, sports, MCQ and training income | Automatic ledger integration exists; existing selected receipt/payment tests pass. |
| Payment register, receipt/proof access, credits, reversal/reposting | Implemented; selected reconciliation and reversal tests pass. Email queues/retry UI exists, but delivery was not exercised. |
| Income/expense/asset/liability account heads | Create, rename endpoint and delete-unused-head protection exist. Ledger UI lists many heads in a long column; no rename control there. |
| Manual balanced transaction | UI and service exist. Caller creates debit/credit pair; generic journal service itself does not validate balance. |
| Immediate expenses | `/ledger/expenses` exists and validates expense-head type. No dedicated Add expense form on ledger. Existing manual form can debit an expense account with Cash & Bank as counter. |
| Unpaid vendor bills/payables | Finance hub → Accounts payable. Vendor, description, amount, incurred/due dates, expense head, optional journal posting. Partial payment supported by backend; UI only offers full remaining balance. |
| Opening balances | Entry/update/remove exists, but report calculation currently double-counts them. |
| Trial balance, income/expenditure, balance sheet | Implemented, but calculation defects below mean results should not yet be signed off. |
| Cash book / general ledger | Services calculate these; FinancialStatements.vue does not display the supplied cashBook/generalLedger data or offer date/year/head filters. |
| Bank reconciliation | Basic marking exists. No statement import/matching workflow. Current Vue code silently uses first bank account; no selection/pagination controls. |
| CSV reports | Ledger export endpoint and report filtering exist. Report page fails under SQLite because DATE_FORMAT is used for non-Postgres databases. |
| State remittances and receivables | Implemented; selected remittance tests pass. Full lifecycle and all staff-role boundaries not exhaustively tested. |

## Executed tests

- Existing selected suite: **31 tests passed, 192 assertions**. Classes: LedgerPostingTest, FeeReceiptReversalTest, PaymentReconciliationTest, PaymentVerificationTest, McqPaymentApproveTest, McqPaymentRejectTest, MembershipPartialPaymentDueTest, StateConductAndRemittanceTest, FestFoodPaymentReviewTest.
- Expense endpoint checks: **4 passed, 11 assertions**. Confirmed a ₹275.50 expense creates a balanced journal, and zero amounts, income heads and another tenant’s expense head are rejected.
- Eight new isolated service scenarios: **3 passed, 5 failed**. Passed: balanced immediate expense, partial payable payment, payable overpayment rejection. Failures are reproduced below.
- Two additional HTTP checks reproduced cross-tenant bank assignment and cancellation leaving the payable liability posted.
- Twelve local authenticated GET probes: **11 returned 200; ledger/reports returned 500 under SQLite**. HTTP 200 proves rendering only, not correctness of displayed totals.
- Live desktop UI: Finance hub, Ledger, Financial statements loaded. No expense or payment was posted live.
- Reproducers and logs are saved under `storage/app/accounts-audit-2026-10-25/` (not in the normal test suite). Original probe log contains the initial page-smoke failure; `accounts-page-statuses.jsonl` is the follow-up result for all 12 pages.

Re-run service/HTTP reproductions:

```sh
php -d memory_limit=1024M vendor/bin/phpunit storage/app/accounts-audit-2026-10-25/AccountsModuleAuditTest.php --no-progress
```

The audit reproducer intentionally has assertions for correct behavior, so confirmed defects fail. Its page smoke probe writes a status inventory rather than assertions; use the inventory, not a pass badge, for that probe.

## Confirmed defects and priorities

### P1 — Bank reconciliation accepts another tenant's bank account

`BankReconciliationController::reconcile` validates only that bank_account_id exists; it does not require ownership by the current Sahodaya. The isolated HTTP test confirmed that a transaction was updated to another tenant's bank ID and returned a redirect instead of 403. Scope validation to the current tenant and restrict reconciliation to appropriate cash/bank entries. Review the fee-waiver endpoint for the same ownership problem: it loads a receipt by global ID without a tenant ownership check (code finding; not independently exploited in this audit).

### P1 — Opening balances counted twice

`OpeningBalanceService::save` already posts a journal. `FinancialStatementsService::trialBalance` adds the opening record again to journal totals. A ₹1,000 cash opening reported **₹2,000**. Cash-book/general-ledger starting calculations also need one consistent source and correct debit/credit sign. Opening-date lookup currently uses AcademicYearRecord although the input is financial_year_id: separate financial years and academic years properly.

### P1 — Reversals omitted from income/expenditure

`incomeAndExpenditure` sums income credits and expense debits only. ₹1,000 income followed by ₹200 reversed income reports **₹1,000**, expected **₹800**. Net opposing journal entries for each account type; include expense reversals too.

### P1 — Payable cancellation does not reverse obligation

`PayableController::cancel` only sets status. An unpaid ₹1,000 vendor bill was cancelled but **₹1,000 liability remained** in the ledger. Reverse the unpaid obligation with an auditable journal. Define partial-payment cancellation rules explicitly rather than erasing payment history.

### P1 — Cancelled payable can still be paid through backend

UI hides the action, but `PayableLedgerService::markPaid` does not reject cancelled status. The isolated test recorded a payment against a cancelled bill. Validate state server-side and lock/recheck balance within the payment transaction; concurrent payments were not tested, but current balance check occurs before the database transaction.

### P1 — Generic journal service accepts unbalanced entries

`LedgerPostingService::postJournal` accepted one ₹100 debit without a credit. Normal manual UI creates pairs, but all journal callers need a central invariant: valid types, positive finite money values, at least two valid lines and debit=credit to the currency precision. Add idempotency for repeat submissions where appropriate.

### P2 — Date-filtered cash book loses carry-in balance

With ₹1,000 received in September and ₹100 spent in October, an October-only cash book showed **−₹100**, expected **₹900**. Carry forward all earlier movements within the reporting basis. General ledger uses the same pattern and needs the same correction.

### P2 — Ledger summary mislabels accounting equality as money available

Live Ledger shows equal debit/credit totals and “Net balance ₹0”. That is expected for a balanced double-entry journal; it is not an available bank/cash balance. Replace the primary cards with Cash & Bank, net income, expenses, outstanding receivables and payables. Keep debit/credit equality as an integrity indicator.

### P2 — Reports and year handling

`LedgerController::reports` returned 500 in SQLite due to DATE_FORMAT. This is a test/local portability defect, not proof the MySQL production page fails. Ledger account creation/year options/opening-date lookup mix AcademicYearRecord with financial_year_id, while posting uses FinancialYear. Exercise divergent year IDs and closed-year rules before sign-off.

## UI/UX improvements

1. Add clear **Add income**, **Add expense**, **Vendor bill**, **Transfer** actions. Keep raw journals in an advanced view.
2. Put the transaction register at full width; move account-head creation/list into a separate page or drawer. Current three-column layout gives the long account list equal space to the register and forces long names to wrap.
3. Expense form: payee/vendor, date, amount, expense category, event/region, payment method and chosen bank/cash account, reference, bill attachment and notes. Show field validation and prevent duplicate submissions.
4. Payables: partial-payment dialog with date, amount, method/account/reference; payment history; due-date/overdue badges; cancellation reason; pagination. Current backend paginates but Payables.vue has no page controls.
5. Financial statements: year and from/to filters, account selector, cash book/general ledger tabs, formatted currency, debit/credit balance columns and report totals, CSV/PDF/print.
6. Reconciliation: explicit bank selector, statement date/balance, reconciled/unreconciled filters, running totals, pagination, matching suggestions. Never silently pick bankAccounts[0].
7. Navigation: group Overview, Income & receipts, Expenses & bills, Banking, Ledger, Reports, Settings. Finance hub currently mostly emphasizes fee collection and makes statements/reconciliation harder to find.
8. Clarify accounting terminology: Debit/Credit are not universal minus/plus. Current manual form and recent-transaction list imply that misleading relationship.

## Recommended implementation order

**First: correctness and safeguards** — fix P1 defects, correct reports/openings/carry-in, tenant-scoped validations, reversal/audit trails, closed financial periods, duplicate/concurrent submission protection and regression tests.

**Next: usable expense management** — dedicated expense screen, bill uploads, vendor master/history, event and region cost allocation, cash/bank selection, partial payments and payment vouchers. These extend the existing expense/payable services rather than starting another ledger.

**Then: complete accounting workflow** — distinct cash/bank accounts and transfers, bank CSV import/matching, financial-year closing/carry-forward with locked prior years, budget-versus-actual, approval workflow, recurring expenses, advances/reimbursements, printable statements and voucher numbering.

**Optional based on actual requirements** — asset/depreciation register, purchase orders and tax-specific handling. Do not call the module tax/compliance-ready without defining the applicable requirements and validating those workflows separately.

## Scope limitation

This is evidence from selected existing tests, targeted reproductions, code review and limited live desktop inspection. It is not a claim that every accounts feature or every permission/concurrency edge was tested. It is sufficient to identify concrete blockers and plan the next implementation pass. No production data repair, code fix, commit, push or deployment was performed in this audit.
