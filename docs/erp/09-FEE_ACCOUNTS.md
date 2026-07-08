# Phase 9 — Fee Engine and Accounts Specification

Offline finance only in current release. Gateway = **future scope** (tables may exist; unused).

## 1. Fee Head and Fee Rule Model

### Fee Head

| Field | Description |
|-------|-------------|
| code | Unique per tenant |
| name | Display |
| account_head_id | Ledger income account |
| module | membership, sports, kalotsav, mcq, training |
| is_active | |

### Fee Rule

| Field | Description |
|-------|-------------|
| fee_head_id | FK |
| applicable_to | school_category, item_type, tier, etc. |
| amount | Decimal |
| effective_from / to | Dates |
| late_fee_rule | JSON → `LateFeeCalculator` |
| waiver_allowed | Boolean |

Resolver: `FestItemFeeResolver`, `McqSchoolFeeService`, module-specific resolvers.

---

## 2. Invoice Model

| Entity | Purpose |
|--------|---------|
| Invoice header | school, program, total, status |
| Invoice lines | fee_head, qty, unit_amount, line_total |

Statuses: `draft`, `issued`, `partially_paid`, `paid`, `cancelled`, `waived`

---

## 3. Offline Payment Proof

Unified requirements across modules (see Phase 8):

- Upload before or after invoice issue  
- Link proof to invoice or school_event_fee row  
- Finance approval triggers receipt  

---

## 4. Receipt and Numbering

- Central sequence per receipt series (MEM, FEST, MCQ, TRN)  
- Immutable after issue; cancellation via credit note voucher  
- PDF generation stored + URL in reports  

---

## 5. Ledger Posting Rules

| Event | Debit | Credit |
|-------|-------|--------|
| Membership receipt | Bank | Membership income |
| Fest fee receipt | Bank | Event income (by fee head) |
| Expense payment | Expense head | Bank |
| Waiver approved | Waiver expense | Income (contra) |

Service: `LedgerPostingService`, `PayableLedgerService`, `McqFeeLedgerService`

**Rule:** No manual edit of posted entries; reversal via contra journal.

---

## 6. Vouchers

| Type | Use |
|------|-----|
| Receipt | Fee collection |
| Payment | Expenses, refunds (manual) |
| Journal | Adjustments |
| Contra | Bank ↔ Cash transfer |

Each voucher: header (date, narration), lines (account, dr/cr), attachment optional.

---

## 7. Opening Balance

**Screen:** Sahodaya → Ledger → Opening Balances

| Field | Rule |
|-------|------|
| account_head_id | FK |
| financial_year | |
| amount | Dr positive, Cr negative convention |
| as_of_date | First day of FY |

Service: `OpeningBalanceService`, model `LedgerOpeningBalance`

---

## 8. Bank Accounts & Reconciliation

### Bank account master

account_name, bank_name, account_number (masked), IFSC, ledger_link_id

### Reconciliation workflow

1. Import bank statement CSV (future) or manual mark  
2. Match ledger transactions  
3. Mark cleared / outstanding  
4. Reconciliation report  

**Screen:** `Ledger/BankReconciliation.vue`, `BankReconciliationController`

Fields on `ledger_transactions`: `cleared_at`, `bank_statement_ref`, `reconciliation_id`

---

## 9. Budget & Cost Center (Requirements)

| Entity | Fields |
|--------|--------|
| Cost center | code, name, event_id optional |
| Budget line | cost_center, account_head, budget_amount, period |

Reports: budget vs actual (Phase 16 catalogue).

---

## 10. Fee Waiver

Approval workflow: school request → Sahodaya finance approve  
Service: `FeeWaiverService` — updates invoice, posts waiver entries, audit.

---

## 11. Financial Statements

**Screen:** `Ledger/FinancialStatements.vue`

| Statement | Service method |
|-----------|----------------|
| Trial Balance | `FinancialStatementsService` |
| Income & Expenditure | |
| Balance Sheet | |
| Day Book | `LedgerReportingService` |
| Cash Book | |
| Bank Book | |
| General Ledger | |

---

## 12. Finance Reports Catalogue

| Report ID | Name |
|-----------|------|
| RPT-FIN-001 | Day book |
| RPT-FIN-002 | Cash book |
| RPT-FIN-003 | Bank book |
| RPT-FIN-004 | General ledger |
| RPT-FIN-005 | Trial balance |
| RPT-FIN-006 | Income & expenditure |
| RPT-FIN-007 | Balance sheet |
| RPT-FIN-008 | Receipt register |
| RPT-FIN-009 | Payment register |
| RPT-FIN-010 | Outstanding receivables |
| RPT-FIN-011 | Pending fees (all modules) |
| RPT-FIN-012 | Collection summary |
| RPT-FIN-013 | Event-wise income |
| RPT-FIN-014 | School-wise income |
| RPT-FIN-015 | Monthly income trend |
| RPT-FIN-016 | Expense analysis |
| RPT-FIN-017 | Cost center report |
| RPT-FIN-018 | Bank reconciliation status |
| RPT-FIN-019 | Waiver register |
| RPT-FIN-020 | Late fee collected |

---

## 13. Future Scope (Not Removed)

- Payment gateway integration  
- Webhook callback reconciliation  
- Automated bank feed  
- GST/TDS modules  

---

## Implementation References

- `LedgerController`, `FinanceHubController`, `PayableController`  
- `AccountHead`, `LedgerTransaction`, `FeeReceipt`, `SahodayaPayable`  
- `LedgerAccountSetupService`, `LedgerAccountCatalog`  

Next: [10-SPORTS.md](10-SPORTS.md)
