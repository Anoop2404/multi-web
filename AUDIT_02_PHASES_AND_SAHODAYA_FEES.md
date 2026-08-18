# Audit 02 of 3 — Phase-Wise Events, Fees, and Multi-Sahodaya Configuration

**Scope:** Phase lifecycle mechanics, the Fest fee-resolution engine, and five named Sahodaya fee scenarios (Kochi Metro, Wayanad, Malabar, Vatakara, MCS), plus fee precedence, invoice snapshotting, tenant isolation, and cancellation/credit workflows.
**Findings covered:** 71, drawn from 12 audit sections, each independently re-checked by a second verification agent before being handed to this report. This document reorganizes and synthesizes those 71 already-re-checked findings — it does not re-derive them from scratch.
**Repo state at time of writing:** `main`, working tree clean, no scratch files present.

## 0. Read this first: none of the five named Sahodayas are live tenants

**Kochi Metro, Wayanad, Malabar, Vatakara, and MCS do not exist as database rows anywhere in this repository.** The only Sahodaya tenant actually seeded and persisted anywhere in this codebase is **Malappuram Sahodaya**. Every other Sahodaya named in this audit exists only as one or more of:

- a **generic fee-resolution capability** in code that could support such a configuration once a real tenant is provisioned;
- for some, **existing test fixtures** that already encode a structurally identical or near-identical scenario (numbers, phase structure, deadlines);
- **documentation** (`docs/MCS_FOUR_PHASE_COMPLETION_PLAN.md`, `docs/KALOTSAV_PHASED_LEVEL_FEE_PLAN.md`) or **data files** (`app/Support/data/mcs_kalotsav_phase_plan.php`, which still carries the literal unfilled placeholder `'tenant_id' => 'REPLACE_WITH_MCS_SAHODAYA_TENANT_ID'`).

**Vatakara has none of the above** — not even a data file. Section 13 covers this in detail; it is flagged here so the framing is unmistakable before any table below is read.

Everywhere this document says a Sahodaya's numbers were **"verified,"** it means: the fee-resolution **code**, given the stated inputs, was shown to produce the stated outputs — via (1) an existing passing test that already encodes the same or a structurally equivalent scenario, re-run in this pass and its assertions read directly against the file, or (2) manual tracing of the resolver code against the stated inputs with hand-computed arithmetic cited to file:line, or (3) in a small number of cases, a throwaway PHPUnit scratch test written, run, and then **deleted** specifically to close a verification gap (each such case is flagged explicitly where it appears). It never means the number was checked against a live, running configuration for that Sahodaya, because no such configuration exists to check against.

### Severity and status breakdown (all 71 findings)

| Severity | Count | Meaning |
|---|---|---|
| P0 | 6 | Confirmed defect on a normal/reachable path; blocks or silently corrupts core functionality |
| P1 | 12 | Confirmed defect with real financial or integrity consequences, narrower blast radius than P0 |
| P2 | 28 | Confirmed defect, design gap, or test gap of moderate consequence |
| P3 | 25 | Minor, cosmetic, low-risk, or "not actually a gap" (positive/confirmatory finding) |

| Status | Count | Meaning |
|---|---|---|
| confirmed | 44 | Defect or behavior verified to exist exactly as described |
| not_a_gap | 15 | Candidate concern investigated and found to be correct/working behavior |
| design_gap | 6 | Capability genuinely absent; a product decision, not a code defect |
| test_gap | 5 | Behavior is correct; the missing piece is regression-test coverage |
| confirmation_required | 1 | Cannot be verified at all (Vatakara — no source material exists) |

### Finding-ID disambiguation used throughout this report

The source data reuses the ID `FEE-01`…`FEE-08` across five unrelated audit sections. This report tags each occurrence so citations are unambiguous:

| Tag | Source section |
|---|---|
| `[Catalog]` | "Fee model catalog inventory" |
| `[Precedence]` | "Fee rule precedence and invoice line itemization" |
| `[Snapshot]` | "Fee snapshotting and historical-invoice immutability" |
| `[Financial]` | "Financial workflows: payments, refunds, credits, cancellation" |
| `[Hardcoded]` | "Hard-coded vs data-driven Sahodaya fee logic" |

`PHASE-*`, `KOCHI-*`, `WYN-*`, `MLB-*`, `VTK-*`, `MCS-*`, and `TIF-*` are already unique and carry no tag.

---

## 1. Phase lifecycle matrix

Whether editing a phase's lifecycle fields (registration window, lock, scoring lock, results/schedule publish, appeals, status) through the documented admin write path actually takes effect on the mechanism schools and judges hit.

| Capability | Phase-aware? | Behavior once a phase has been synced at least once | Severity | Finding |
|---|---|---|---|---|
| Registration creation gate (`EventLifecycleGate::allowRegistrationForItem`) | Yes — live, wired into `FestRegistrationCreateService::createForSchool()` | Reads a **stale** leaf/child-phase copy if the root phase is edited *after* the first sync; the copy is never refreshed by the normal edit path | **P0** | PHASE-01 |
| Phase topology auto-resync | Only lazy — fires solely when the leaf event is entirely **missing** | Never re-syncs a leaf that already exists but has gone stale; `assignItems()` is the only controller action that calls `sync()` | **P0** | PHASE-01 |
| Mark-entry gate (`EventLifecycleGate::allowMarkEntryForItem`) | Yes — live, wired into 6 separate controllers | Inherits the same staleness risk as the registration gate (same underlying leaf/child-phase copy) | **P0** (inherits) | PHASE-01, PHASE-04 |
| Item registration-window resolution (`FestItemWindowResolver`) | **No** — the class never references `FestEventPhase` or `phase_id` anywhere | Falls back through item → head → event → area windows with zero phase awareness; can wrongly block a phase-open item, or wrongly clear an item on a phase-closed one | **P1** | PHASE-02 |
| Results/report publication (`phase_mode_enabled`, non-regional-billing events) | **No** — whole-event only | Phase-level `results_published` toggle is accepted by the update endpoint but is inert; public visibility and report export both gate on the event-wide flag | **P1** | PHASE-03 |
| Public visibility (names, marks, schedule) | **No** | Gates purely on `$event->results_published` / `schedule_published`, no item- or phase-level flag anywhere in `FestPublicVisibilityService` | **P1** | PHASE-03 |
| Report export lifecycle gate | Broken sequencing | Event-level `enforceReportLifecyclePhase()` 403s **before** the phase-scoped check is ever reached — the phase-aware check exists in code but is unreachable in the relevant case | **P1** | PHASE-03 |
| Results/report publication (regional-billing **leaf** events) | **Yes — correct** | `FestPhasePublicationService` updates leaf + child-phase + root-phase + root together; cited as the working pattern the other paths should copy | — (working) | PHASE-03 (contrast) |
| Appeal gating (`allowAppealForParticipant`) | Yes — cited as a correct phase-aware pattern | — | — | PHASE-03 (contrast) |
| Attendance entry / bulk import | **No gate of any kind** | Zero `EventLifecycleGate` calls, zero status/phase/results_published checks anywhere in `FestAttendanceController` or `FestAttendanceImportService`; the controller's own docblock documents this as a deliberate choice | **P2** | PHASE-05 |
| Gate docblock accuracy | N/A | `allowRegistrationForItem()`/`allowMarkEntryForItem()` docblocks still say "Deliberately NOT wired into any existing call site" — both are live on 1 and 6 call sites respectively | **P2** | PHASE-04 |
| Phase-level cancellation cascade (`quickStatus` → `cancelled`) | **No cascade** | Flips `status` only; no registration withdrawal, no fee recalculation, no credit issuance — reachable today via a live, authenticated API endpoint even though no UI exposes it | **P1** | FEE-03[Financial] |

**Reproduction note (PHASE-01):** a throwaway test built a `phased_regional_billing` event, synced topology once, then edited the **root** phase's `registration_close` to the past and transitioned its status via the exact methods the controller uses. The freshly-reloaded leaf event, leaf's child phase, and leaf item all still reported the phase as open, and `EventLifecycleGate::allowRegistrationForItem()` did not throw. Test file was deleted after the run; working tree confirmed clean.

---

## 2. Sahodaya fee-comparison matrix

Every cell below is code/test behavior against the fee rule **as stated in this audit's brief for that Sahodaya** — none of these five rows corresponds to a queryable live tenant.

| Dimension | Kochi Metro | Wayanad | Malabar | Vatakara | MCS |
|---|---|---|---|---|---|
| **Tenant status** | Not a live tenant | Not a live tenant | Not a live tenant | Not a live tenant, **and zero source material of any kind exists** | Not a live tenant (has a built-but-unwired data file with a placeholder tenant_id) |
| **Fee model matched** | `kalolsavam_composite` | phase-split (`FestEventPhase.school_registration_fee_share`) + per-student | `student_count_slab` **and** flat-per-student, combined | — | batch+phase (`FestRegistrationBatch` + `FestEventPhase`) |
| **School registration fee, as stated** | Tiered by class category: Senior Secondary ₹8,000 / Secondary ₹7,000 / Other ₹7,000 | Phase 1 share ₹30,000 (senior secondary example) / Phase 2 share ₹0 | Stepped by unique student count: 1–49=₹6,000, 50–99=₹8,000, 100–149=₹10,000, 150+=₹12,000 | — | Flat ₹4,000, Level 1 only (Level 2 = ₹0) |
| **Student/participation fee, as stated** | ₹100 incl. 1 item, +₹100/extra item | ₹250/student, both phases | ₹450/student flat, on top of the slab | — | Per-item: Digi ₹100, Off Stage ₹200, Sargadhara ₹300, District ₹400 |
| **Phase/level structure** | None (single-event) | 2 phases | None stated | — | 4 phases across 2 payment levels |
| **Verified via** | `FestSchoolEventFeeServiceTest::test_kalolsavam_composite_tiers_school_fee_by_class_category` (existing, re-run) + 1 fresh scratch test | `FestFeeNoticeScenariosTest::test_wayanad_tiered_registration_across_two_phases` (existing, re-run) + hand-trace at N=100/30 | No matching test exists for the *combined* rule (that's the finding); slab and flat verified *separately* via existing test + scratch tests | Nothing — no test, no data, no seeder, no doc, no image anywhere in the repo | `FestPhasedRegionalBillingWorkflowTest` (existing, 3/3, re-run) + fresh scratch test |
| **Verification classification** | Traced against code/tests, not a live tenant | Traced against code/tests, not a live tenant | Traced against code/tests, not a live tenant | **Confirmation required — cannot be verified** | Traced against code/tests, not a live tenant |
| **Confirmed bugs affecting this Sahodaya's stated rule** | KOCHI-02 (group items overbilled by team size), KOCHI-07 (unconfigured school fee silently defaults to ₹2,000, not ₹0) | WYN-02 (no 300-student sub-threshold within the Secondary tier exists), WYN-03 (worked-example's "4th line item" is actually the Phase-1 per-student total, not a 2nd tier) | **MLB-01: no fee_model can produce the brief's combined number at all** — configuring either mechanism alone under-bills by 21–78%; MLB-02 (0 students bills the *highest* slab, not ₹0) | N/A — nothing to test | **MCS-01: registration is structurally blocked for 2 of the 4 phases** (Digi Fest, District Kalotsav) the moment this workflow mode is used; MCS-02 (item catalogue/phase map is an unfilled template) |
| **What was found to be correct** | Tiered school-fee-by-class-category arithmetic; quota-exhaustion arithmetic for individual items | Phase-split billing mechanic itself (no double-counting of a student registered in both phases) | Same-student dedup across individual/group/team items (MLB-04); slab boundary values 1–49/50–99/100–149/150+ | — | Base fee correctly charged once per school per batch, never duplicated across a level's phases (MCS-03) |

**Additional confirmed findings for these Sahodayas that are not bugs, kept here for completeness against the source finding set:**

- **KOCHI-05** (P3, confirmed) — Fee recalculation on registration cancel/withdraw is correct and atomic (the student's quota slot is released, then `recalculate()` runs, both inside the same DB transaction). The only real gap is missing regression-test coverage: `FestRegistrationService::cancel()` is exercised by 2 existing tests, but neither asserts `total_due` actually drops afterward.
- **KOCHI-06** (P3, confirmed) — Kochi Metro's stated "membership renewal ₹0" is a legitimate, independently-configured value in a wholly separate subsystem (`MembershipFeeCalculator` / `SahodayaProfile.membership_fee_type`) with zero code coupling to the event-registration fee verified above — a school can have ₹0 membership and a non-zero event fee, or vice versa; there is no shared code path to get this wrong.
- **MLB-05** (P3, not_a_gap) — Methodology note: the `cksc_tiered` fee tests are **not** the right anchor for verifying Malabar's stated rule (a different tiering axis — by class category, not student count). The `student_count_slab` tests are, and that is what MLB-01/MLB-02 above correctly relied on.
- **MCS-05** (P2, not_a_gap) — Reports correctly filter by payment level (`registration_batch_id`) in addition to phase and region, confirmed via a passing test (`FestPhasedRegionalBillingWorkflowTest`). No gap found here.
- **MCS-07** (P3, not_a_gap) — Data-model clarification: "Level" = `FestRegistrationBatch`, "Phase" = `FestEventPhase`; each phase has an FK to exactly one batch, and one batch owns many phases. `docs/MCS_FOUR_PHASE_COMPLETION_PLAN.md` (2026-08-16) is authoritative over the earlier `docs/KALOTSAV_PHASED_LEVEL_FEE_PLAN.md` (2026-08-15) wherever the two conflict, since current code implements the former.

---

## 3. Fee-rule precedence map

Source: `[Precedence]` section. The live dispatcher is `FestSchoolEventFeeService::resolveSchedule()` (`app/Services/Events/FestSchoolEventFeeService.php:75-175`), which assembles a schedule in the following order — confirmed by direct read, line-by-line:

```
1. BASE SOURCE (mutually exclusive, first match wins)
   a. State-program level_fees[level] merged onto config/fest_fees.php defaults
      — only if level_fees[level]['fee_model'] is filled            (lines 99-109)
   b. else: legacy FestEvent.fee_type / fee_amount collapsed into a
      bare per_item schedule via FestEventFeeResolver::resolveForEvent()
                                                                       (lines 111-117)
   c. else: fee_model = 'none'                                       (lines 118-121)

2. EVENT fee_settings JSON — array_merge'd unconditionally on top     (lines 124-126)

3. SPORTS-ONLY BRANCH (event_type = 'sports')
   a. config('fest_fees.level_defaults.sports') merged under the
      schedule so far; fee_model force-set to sports_composite        (lines 129-131)
   b. IF FestEvent::hasSportsFeesConfigured() is true (any one of 5
      dedicated FestEvent columns is non-null — including a stray 0),
      those 5 columns overwrite the corresponding schedule keys
      WHOLESALE, discarding whatever fee_settings JSON said           (lines 137-147)

4. item_catalog defaults fill class/age/participant-type fee maps
   only where a key is absent                                        (lines 150-167)

5. Auto-upgrade to item_catalog if schedule is still 'none' and any
   item/head has fee_amount configured                                (lines 169-172)

── (separate, later, at billing time — not inside resolveSchedule) ──
6. Item-level override chain, applied per item by
   FestItemFeeResolver::amountForItem() when the fee is actually
   computed:
      item.fee_amount override
        → group/team surcharge (item-level pair, else schedule-level pair)
        → competition-area default/extra (item_catalog) OR head fields (sports)
        → head default/extra
        → participant_type_fees
        → age_group_fees
        → class_group_fees
        → schedule.default_item_fee
        → schedule.per_item_amount (catch-all)
   This chain is well-defined, internally documented, and confirmed
   correct by direct read + FestItemFeeResolverAreaTest (3/3 passing). [FEE-02[Precedence]]

7. school_fee_cap / school_fee_min — applied ONLY on the plain
   (non-sports, non-per-head, non-per-phase) recalculate() branch,
   lines 1043-1044. recalculateForSportsEvent(), recalculateForHead(),
   and recalculateForPhase() never call either helper.                [FEE-03[Precedence], P1]
```

**Two structural problems fall directly out of this chain:**

1. **Step 3b is all-or-nothing.** `hasSportsFeesConfigured()` is a simple OR across 5 columns checking `!== null`; a literal `0` on any one column satisfies it. When true, all 5 fields switch from the JSON surface to the column surface at once — there is no field-by-field reconciliation, and the two surfaces don't even share key names (`school_registration_flat` vs `school_registration_fee`, `per_student_amount` vs `student_registration_fee`). A Sahodaya configuring sports fees via the JSON surface while any one dedicated column happens to be non-null gets that JSON silently and completely discarded. `[FEE-08[Precedence]]`, P2.
2. **Step 7's cap/floor only protects one of four billing paths.** `school_fee_cap`/`school_fee_min` is invisible to sports, per-head, and per-phase billed events. Reproduced live: a phase-billed school with an uncapped subtotal of ₹7,000 against a configured cap of ₹1,000 was billed the full ₹7,000. `[FEE-03[Precedence]]`, P1 — see Section 4 for the reproduction numbers.

No single function documents this whole chain in one place; a maintainer must reconstruct it from four separate files. `[FEE-01[Precedence]]`, P2.

---

## 4. Boundary-test results

Every finding that carries a computed-vs-expected `numbers` field, one row each.

| # | Scenario | Expected | Computed / Observed | Result | Finding |
|---|---|---|---|---|---|
| 1 | Root phase `registration_close` set 1 day in the past via the normal update path; leaf/child-phase re-read fresh from DB | Leaf should reflect closed registration | Leaf/child-phase `registration_close` unchanged (still ~5 days in the future); lifecycle gate reports OPEN | **FAIL** | PHASE-01 |
| 2 | MCQ per-school discount: fee ₹150, discount ₹30, 2 students | payable/student=₹120; total=₹240 | ₹120, ₹240 — MCQ has this capability; Fest has no equivalent field to test at all | **PASS** (MCQ only) | FEE-04[Catalog] |
| 3 | `flat_school` model, `flat_amount`=₹5,000 (manual trace, no test exists) | total_due=₹5,000 | ₹5,000 by hand-trace of the actual match arms | **PASS** (untested) | FEE-05[Catalog] |
| 4 | Credit payout: FestFeeCredit ₹500 → `recordCreditPayout()` | CreditPayout=500; CASH-BANK +500; FEE-CREDIT-PAYABLE −500 | All three exactly ₹500 | **PASS** | FEE-06[Catalog] |
| 5 | Kannur: 12 individual items ×₹250, 1 group item (₹250 flat + ₹100×7 participants) | total=₹3,950 | 3,000+950=3,950 | **PASS** | FEE-08[Catalog] |
| 6 | Kochi Metro tiers: senior_secondary / secondary school fee | 8,000/8,200; 7,000/7,200 | 8,000/8,200; 7,000/7,200 | **PASS** | KOCHI-01 |
| 7 | Kochi Metro: 1 student, 1 item / 4 items (quota=1, +₹100/extra) | 1-item participation=100; 4-item=400 | 100 (0 extra); 400 (300 extra) | **PASS** (participation only — see #9 for total_due) | KOCHI-01 |
| 8 | Kochi Metro: 3-member group item, per-student rate ₹100 | 1 team-level charge expected | `student_registration_fee`=₹300 (3× the per-participant rate for one entry) | **FAIL** | KOCHI-02 |
| 9 | `extra_item_fee`=₹250 configured, no `default_item_fee`, item beyond quota, no `head_id` | item#2 = ₹250 | item#2 resolved to ₹0 | **FAIL** at code level (unreachable via any admin UI path today) | KOCHI-03 |
| 10 | Missing `school_registration_flat` (3 independent scratch reproductions) | school_registration_fee=₹0 | ₹2,000 every time (total_due 2,100 / 2,400 / 2,300 across the 3 scenarios) | **FAIL** | KOCHI-07 |
| 11 | Wayanad Phase 1 / Phase 2, N=1 student | 30,250 / 250 | 30,250 / 250 | **PASS** | WYN-01 |
| 12 | Wayanad Phase 1 / Phase 2, N=100 / N=30 (hand-traced from the real formula) | brief's total = 67,500 (5,000+30,000+25,000+7,500) | Fest-engine total = 62,500 (55,000+7,500); +5,000 membership (separate ledger) = 67,500 | **Reconciles only by relabeling** — the "₹25,000" line is the Phase-1 per-student total, not a 2nd registration tier | WYN-03 |
| 13 | Wayanad tier lookup: senior_secondary / secondary, real class-category derivation (different literal numbers, 8,000/7,000) | 2 of 3 tiers exercised by a passing test | Confirmed 2/3 (senior_secondary, secondary); "other" tier and Wayanad's own 30,000/25,000/20,000 never run through real derivation | **PARTIAL** (test gap) | WYN-06 |
| 14 | Malabar, 49 students: `student_count_slab` alone | brief total = 28,050 | 6,000 (**−22,050 short**) | **FAIL** | MLB-01 |
| 15 | Malabar, 49 students: `per_student`(₹450) alone | brief total = 28,050 | 22,050 (**−6,000 short**, i.e. entire slab base missing) | **FAIL** | MLB-01 |
| 16 | Malabar slab, studentCount=0 | ₹0 or lowest tier | ₹12,000 (falls to the **top** slab) | **FAIL** (by design) | MLB-02 |
| 17 | Malabar slab boundaries: 1–49 / 50–99 / 100–149 / 150+ | 6,000 / 8,000 / 10,000 / 12,000 | 6,000 / 8,000 / 10,000 / 12,000 | **PASS** | MLB-02 |
| 18 | Malabar: 1 student across individual+group+team items (6 participant rows, 4 unique students) | billableStudentCount=4 | 4 (correctly deduped, not 6) | **PASS** | MLB-04 |
| 19 | Malabar: same fixture, `per_student`=450 | total_due=1,800 | 4×450=1,800 | **PASS** | MLB-04 |
| 20 | MCS: Level 1 = base(4,000, once) + Digi(100) + Off Stage(200) | 4,300 | 4,300 | **PASS** | MCS-03 |
| 21 | MCS: Level 2 = base(0) + Sargadhara(300) + District(400) | 700 | 700 | **PASS** | MCS-03 |
| 22 | MCS: rollup, Level 1 + Level 2 | 5,000 | 4,300+700=5,000 | **PASS** | MCS-03 |
| 23 | Cap/min on phase billing: cap=1,000; phase share 2,000 + item override 5,000 | total_due=1,000 (capped) | total_due=7,000 (**cap ignored entirely**) | **FAIL** | FEE-03[Precedence] |
| 24 | Invoice-line persistence coverage across 9 fee_models | lines persisted for all 9 | Lines persisted for exactly **2 of 9** (`sports_composite`, `kalolsavam_composite`) | **FAIL** | FEE-05[Precedence] |
| 25 | Fully-paid+approved flat_school fee (₹1,000), schedule edited to ₹1,800, recalculated | should refuse the overwrite, or log an adjustment | `total_due` silently becomes 1,800; `amount_paid` stays 1,000; status flips to `partial`; **0 AuditLog rows** before vs. after | **FAIL** | FEE-01[Snapshot] |
| 26 | Same fixture, invoice re-issued after the edit | status should reflect the real shortfall | Same invoice id/number; `total_amount` silently changed 1,000→1,800; status **still "paid"** despite an 800 shortfall | **FAIL** | FEE-02[Snapshot] |
| 27 | Event cancellation (any FEST event, any Sahodaya) | status→cancelled; registration→withdrawn; credit issued | `\Error` thrown mid-transaction, full rollback: status stays `registration_open`, registration stays `approved`, `FestFeeCredit::count()`=0 | **FAIL** (total blocker) | FEE-01[Financial] |
| 28 | MCS-style batch cancellation, ₹4,100 fully paid, registrations withdrawn | credit ≈ ₹4,100 | reduction computed = ₹0 (batch-billing immutability guard freezes `total_due`) | **FAIL** | FEE-02[Financial] |
| 29 | Tenant isolation: 2 fresh tenants, `flat_amount` 1,500 vs 3,000 | each resolves only its own amount | 1,500 / 3,000, no blending, order-independent | **PASS** | TIF-02 |
| 30 | Receipt numbering, interleaved across 2 tenants | [1,1,2,2] (independent per-tenant counters) | [1,1,2,2] observed (not the [1,2,3,4] a shared counter would produce) | **PASS** | TIF-04 |
| 31 | Membership fee slab isolation, 2 tenants, same academic-year band, different amounts | 4,000 / 9,999, no leakage | 4,000 / 9,999, no leakage | **PASS** | TIF-05 |
| 32 | Unconfigured sports event vs. unconfigured Kalolsavam event | both should behave the same way (either both charge or both don't) | sports: schedule = config defaults (2,000/300/2/150), `feeRequired()`=**true**; kalolsavam: schedule=`none`, `feeRequired()`=**false** | **Asymmetric** — sports fails unsafe, kalolsavam fails safe | FEE-02[Hardcoded] |

---

## 5. Invoice-calculation examples

One worked example per named Sahodaya, citing the exact resolver code path exercised. All figures reproduce the corresponding rows in Section 4.

### Kochi Metro — `kalolsavam_composite`

**Path:** `FestSportsCompositeFeeService::calculate()` (`FestSportsCompositeFeeService.php:552-694`) → `FestItemFeeResolver::amountForItem()` no-head fallback (`FestItemFeeResolver.php:58-60`) for items beyond quota → `FestSportsCompositeFeeService::schoolRegistrationAmount()` (lines 696-710) for the class-tiered school fee.

- 1 senior-secondary school, 1 student registered for 4 individual items. `per_student_amount`=100, `included_items_per_student`=1, `default_item_fee`=100.
- `student_registration_fee` = 100 (1 included item) + `extra_item_fee` = 300 (3 items beyond quota × 100) → **participation_fee = 400**.
- School fee: **if `school_registration_flat` is explicitly configured** at 8,000 → `total_due` = 400 + 8,000 = **8,400**.
- **If left blank** (a single admin oversight — leaving one form field empty on save) → `schoolRegistrationAmount()` and the settings normalizer both independently default it to 2,000 → `total_due` = 400 + 2,000 = **2,400**, silently wrong by 6,000. (KOCHI-07)
- If the same student's items had instead been one 3-member **group** entry, `student_registration_fee` alone would be 300 (3×100) instead of a single team charge — a separate, compounding overbilling bug. (KOCHI-02)

### Wayanad — phase-split tiered + per-student

**Path:** `FestSchoolEventFeeService::recalculateForPhase()` (`FestSchoolEventFeeService.php:790-845`) — share read at line 811, per-student branch at line 817, total at line 825.

- Senior-secondary school: Phase 1, 100 students, `school_registration_fee_share`=30,000, `per_student_amount`=250. Phase 2, 30 students, share=0.
- Phase 1: 30,000 + (100 × 250) = **55,000**. Phase 2: 0 + (30 × 250) = **7,500**.
- Combined Fest total = **62,500**. The notice's stated Rs 67,500 total only reconciles by adding Wayanad's separately-ledgered Rs 5,000 **membership** fee (a wholly different subsystem, `MembershipFeeCalculator`, never summed by any Fest code) and by reading its stated "₹25,000 Secondary >300" line as *the Phase-1 per-student subtotal* rather than a genuine second registration-fee tier — the resolver has no code path that could produce two additive registration-fee tiers for one school. (WYN-03)

### Malabar — combined slab + flat (does not exist as a single code path)

**Path attempted:** `FestSchoolEventFeeService::recalculate()` dispatch (lines 948-984) → either `studentCountSlabFee()` (lines 407-429, `student_count_slab` model) **or** the `per_student` participation-fee match arm (~line 1027) — the two are mutually exclusive branches of the same `match()` statement; nothing sums them.

- 49 registered, linked students. Brief's rule: 6,000 (slab) + 49×450 (flat) = **28,050**.
- `fee_model=student_count_slab` alone → **6,000** (22,050 short).
- `fee_model=per_student` alone → **22,050** (6,000 short).
- No configuration of the existing catalog reaches 28,050. This is a genuine capability gap, not a configuration mistake. (MLB-01)

### MCS — batch + phase (Level 1 / Level 2)

**Path:** `FestRegistrationBatchFeeService::recalculateBatch()` (`FestRegistrationBatchFeeService.php:43-153`) — base fee at line 89, total at line 90.

- Level 1 (Digi Fest + Off Stage): base 4,000 (charged once, gated only on "batch has any registration") + Digi 100 + Off Stage 200 = **4,300**.
- Level 2 (Sargadhara + District Kalotsav): base 0 + Sargadhara 300 + District 400 = **700**.
- Rollup = **5,000**. This arithmetic is correct and test-proven (MCS-03).
- **However**, this computation is currently unreachable for the Digi Fest and District Kalotsav phases specifically: `FestRegistrationCreateService::createForSchool()` calls `FestSchoolPhaseRegionService::lockForRegistration()` unconditionally for any phase-scoped item, and that method throws `ValidationException` ("Select a region…") for **every** non-regional phase, because no region-selection row can ever exist for a phase that `select()` itself refuses to accept (`FestSchoolPhaseRegionService.php:19-30,108-114`). Two of MCS's four phases cannot accept a single registration through the normal school-facing flow, so this fee calculation never runs for them today. (MCS-01, **P0**)

### Vatakara — no example possible

Zero fee figures, class-tier rules, or academic-year data exist anywhere in this repository for Vatakara. No worked example can be produced without fabricating input data. (VTK-01)

---

## 6. Cross-tenant test results

Source: `TIF-*` section — whether one Sahodaya's fee configuration, data, or sequence numbering can leak into or be reached from another.

| Test | Method | Result |
|---|---|---|
| Fee schedule / recalculated total isolation | Fresh scratch test, 2 new tenants, `flat_school` 1,500 vs 3,000, cross-order resolution | **PASS** — no blending, no order dependence |
| HTTP-level IDOR (tenant-ID and event-ID URL substitution) | Fresh scratch test, 3 real HTTP requests as tenant A's admin: wrong tenantId, wrong event under the right tenantId, and the legitimate combination | **PASS** — 403 / 403 / 200 exactly as expected |
| Receipt-number sequencing | Fresh scratch test, interleaved allocator calls across 2 tenants | **PASS** — independent per-tenant counters on `SahodayaProfile.receipt_next_number`, row-locked |
| Membership fee slab isolation | Fresh scratch test (this section's own author had explicitly skipped writing it — closed in this pass) | **PASS** — no leakage across tenants for the same academic-year band |
| State-program attachment boundary (can a Sahodaya attach an event to a program it was never enrolled in?) | Full code trace of every write path + a model-level immutability guard found along the way | **PASS** — no reachable write path exists; `FestEvent::booted()` additionally throws if a persisted `state_program_id` is ever changed, and the general event-update endpoint's validation whitelist excludes the field — two independent layers of protection |
| **Physical DB-per-Sahodaya isolation** (`tenancy.database_per_sahodaya=true`, the actual **production default**) | **Not tested by any suite.** phpunit.xml forces `TENANCY_DATABASE_PER_SAHODAYA=false` for the entire run | **GAP** — see below |

**TIF-01 detail, because it contains a trap worth flagging on its own:** the entire automated test suite runs with the real per-tenant database-isolation bootstrapper (`DatabaseTenancyBootstrapper`) switched off at the OS-environment level. A naive attempt to close this gap by calling `config(['tenancy.database_per_sahodaya' => true])` **inside** a test does **not** work — `config/tenancy.php` computes its `bootstrappers` list from `env()` once at boot, not from live `config()` reads, so the real bootstrapper still never runs even though `TenancyDatabase::enabled()` (which does read `config()` live) reports `true`. A future engineer flipping that config flag inside a test and seeing it "pass" would have false confidence. Closing this gap for real requires the OS/process environment variable set before boot, not a runtime override. Severity P2 (test_gap) — nothing has failed in production because only one tenant is currently live, but this is the mechanism that would matter most the moment a second real Sahodaya is provisioned.

---

## 7. Missing configuration capabilities

Source: `[Catalog]` section, cross-referenced with the Sahodaya-specific gaps found elsewhere.

**Confirmed absent (real product gaps):**

| Capability | Status | Detail | Finding |
|---|---|---|---|
| Combined student-count-slab + flat-per-student fee model | **Absent** | Blocks Malabar's stated rule outright; see Sections 4–5 | MLB-01, P1 |
| Late fee / registration-deadline penalty for Fest events | **Absent** (design_gap) | Built and proven correct for MCQ (`LateFeeCalculator`) and Training; Fest's 7 fee-computation files have zero late/penalty/overdue/grace-period logic of any kind | FEE-03[Catalog], P2 |
| Per-school discount for Fest events | **Absent** (design_gap) | Built and proven correct for MCQ (`McqSchoolFeeService::breakdownForSchool`, tested); no equivalent field anywhere in the Fest fee engine | FEE-04[Catalog], P2 |
| Student-count sub-threshold within one class-category tier (Wayanad's 300-student Secondary split) | **Absent** (design_gap) | Neither of the two parallel tier-lookup implementations accepts a count-based sub-branch | WYN-02, P2 |
| Appeal-fee payment audit trail | **Absent** | Fee is stamped and can be "marked paid" on trust alone — no `FeeReceipt`, no ledger post, unlike every other fee in the system | FEE-01[Catalog], P1 |
| Qualification-triggered fee change; GST/cess/tax | **Confirmed absent, never appears to have been planned** | Zero references anywhere in `app/`; not a regression, reads as scope that never existed | FEE-07[Catalog], P3 |

**Confirmed dead / non-functional (looks like a capability, isn't):**

| Item | Status | Finding |
|---|---|---|
| `fest_school_event_fees.override_amount` column | **Fully dead** — fillable and cast, but no code path anywhere writes to it as a model attribute; re-confirmed independently in 3 separate audit sections | FEE-02[Catalog], FEE-04[Precedence], FEE-08[Snapshot] |
| `extra_item_fee` schedule key (no-head-id items) | **Dead for this specific combination** | Real code gap, but no admin-reachable UI or normalizer path can actually set it, so real-world risk is low | KOCHI-03, P3 |

**Positive findings — capability confirmed to exist and work, worth stating so absence isn't over-assumed:**

- `flat_school` fee model is implemented correctly (hand-traced) but has **zero test coverage** — FEE-05[Catalog], P2 (test_gap).
- The Fest fee-credit **cash payout** mechanism (`CreditPayoutService` → real ledger entries crediting CASH-BANK) genuinely exists, is wired to a working admin UI form, and was verified end-to-end with a fresh scratch test — an earlier pass had wrongly flagged this as missing. It has zero coverage in the *permanent* suite. FEE-06[Catalog], P3 (not_a_gap).
- The bulk of the fee catalog (28 other scenarios across sports/composite/tiered/gating models) is implemented **and** tested: 67 tests / 209 assertions re-run clean across 4 suites in this pass. FEE-08[Catalog], P3 (not_a_gap — positive baseline).

---

## 8. Hard-coded Sahodaya logic

Source: `[Hardcoded]` section. **No hard-coded Sahodaya-identity branching was found anywhere in fee-computation code.** This was checked by direct full reads of every fee-resolution file (`FestEventFeeResolver.php`, `FestItemFeeResolver.php`, `FestSportsAgeGroupRegistry.php`, `FestSportsAgeGroup.php`, `SchoolClassCategoryResolver.php`, `FestPhasedStructureConfigurator.php`, `FestConfigurePhasedStructure.php`, relevant ranges of `FestSchoolEventFeeService.php`/`FestSportsCompositeFeeService.php`), plus repeated, independently re-run greps for tenant slug/id/name comparisons (`===`, `==`, `!==`, `!=`, `in_array`, `str_contains`, `match()`, `switch()`) against all 8 candidate Sahodaya names. All returned zero matches in fee logic. `[FEE-01[Hardcoded]]`, P3, not_a_gap.

Onboarding any of Kochi Metro, Wayanad, Malabar, Vatakara, or MCS as a real tenant requires **zero fee-logic code changes** — only per-tenant `fee_settings`/`FestEventPhase` configuration rows.

**Near-misses worth recording, so a future grep isn't mistaken for a real finding:**

- `app/Support/SahodayaTenantBranding.php:444` **does** hard-code `in_array($subdomain, ['cksc','confederation','kerala','state'])` — a genuine tenant-identity branch — but it drives CMS/website content personalization only, with all 7 callers confined to Website/SiteBuilder/CmsPage classes. It never touches fee logic.
- The strings `'cksc_tiered'` and `'kalolsavam_composite'` appearing throughout the fee engine are **fee MODEL TYPE selector values** (any Sahodaya can independently choose either), not tenant-identity checks — a plain-text grep for these names produces a false-positive pattern that looks like hardcoding but isn't. `[FEE-03[Hardcoded]]`, P3, not_a_gap.
- Two literal `'MCS'` string fallbacks exist in `MembershipReceiptService::renderPreview()`, but both are inert sample-data defaults inside an admin-only style-preview endpoint (`MembershipReceiptTemplate::resolve()` always sets the real value first); neither reaches a real school's actual receipt. `[FEE-04[Hardcoded]]`, P3, not_a_gap.

**The one real, related risk found in this section is not identity-based hardcoding, but a baked-in default that behaves inconsistently by event type:** an unconfigured **sports** event silently adopts `config('fest_fees.level_defaults.sports')` (₹2,000 school / ₹300 student / 2 included items / ₹150 extra) as a live, billable schedule the moment it's created — `hasSportsFeesConfigured()` only prevents this if an admin has already touched at least one of 5 dedicated fee columns. An identically unconfigured **Kalolsavam** event correctly fails safe to `fee_model='none'` (no charge) via the exact same dispatcher. No lifecycle gate (`EventLifecycleGate::assertCanPublishEvent`) checks fee configuration before letting either type reach `registration_open`. `[FEE-02[Hardcoded]]`, P2, design_gap.

---

## 9. Confirmed calculation bugs (P0/P1, involving numbers)

Grouped by theme. All are `status: confirmed`.

### 9a. Phase lifecycle staleness (dates, not fees)

- **PHASE-01 (P0).** Editing a phase's registration window/lock/status through the documented admin path has zero effect on the actual gates that govern registration and mark entry once that phase has synced at least once — which happens automatically on the first registration routed to it. Reproduced live: root phase closed, leaf/child-phase still reports open ~6 days later, and the registration gate does not throw. See Sections 1 and 4 (row 1).

### 9b. Fee-schedule mutation after payment (money, immutability)

- **FEE-01[Snapshot] (P0).** `recalculate()` and its 3 sibling methods (`recalculateForSportsEvent`, `recalculateForHead`, `recalculateForPhase`) unconditionally overwrite `total_due` even when `amount_paid > 0`, with no guard and no audit trail. Reproduced live: a fully-paid, approved `flat_school` fee (₹1,000) silently became ₹1,800 after an unrelated schedule edit, with zero `AuditLog` rows recording the change. The only place in the codebase that *does* guard this (`FestRegistrationBatchFeeService::recalculateBatch()`, lines 102-106) is not reused by any of the other four methods — a cross-method inconsistency tracked separately as `FEE-03[Snapshot]`, P1. See Section 4 rows 25/26 for the reproduction numbers.
- **FEE-02[Snapshot] (P0).** `FestInvoiceService`'s status formula is sticky: `($fee?->status === 'approved' || $existing?->status === 'paid') ? 'paid' : …`. Once an invoice has ever been "paid," every future re-issue against the **same row** (`updateOrCreate` keyed only on event_id+school_id, no version bump) keeps reporting "paid" regardless of the freshly-computed fee status — while `total_amount` on that same row silently changes. Reproduced live: an invoice that showed ₹1,000/"paid" became ₹1,800/"paid" after a schedule edit, despite an actual ₹800 shortfall. `issueForSchoolBatch()`/`issueForSchoolPerHead()` do **not** share this bug — it is confined to the plain `issueForSchool()` path and its sports-composite twin.

### 9c. Cancellation not completing or crediting (money + workflow)

- **FEE-01[Financial] (P0).** `FestEventStatusService::transitionToCancelled()` references `\App\Support\Enums\FestPageActivity::OVERVIEW` — a class that does not exist (the real class is `App\Support\FestPageActivity`, no `Enums` sub-namespace). This throws `\Error` on the **last line of the transaction**, so **every** attempt to cancel **any** FEST event, on any Sahodaya, with or without paid fees, 500s and fully rolls back. No FEST event has ever been successfully cancelled through this code path since it was written. Fix is a one-line namespace correction. Reproduced live with a full fixture (registration + approved ₹4,100 receipt): after the crash, registration stayed `approved`, `FestFeeCredit::count()`=0, event stayed `registration_open` — nothing the admin was shown (a warning requiring them to confirm "credit_all") actually happened.
- **FEE-02[Financial] (P0).** Independent of the crash above: even once fixed, cancelling a **batch-billed** (MCS-style) event will not credit already-paid schools. `FestRegistrationBatchFeeService`'s own immutability guard (the correct one, from 9b) freezes `total_due` the instant `amount_paid > 0` — so after registrations are withdrawn by the cancellation, the recomputed total is discarded and the stale (still-fully-due) total is returned, making the credit computation `min(0, paid) = 0`. This is exactly the billing model this audit's MCS section is about.
- **FEE-03[Financial] (P1).** Phase-level cancellation (`FestEventPhaseController::quickStatus` → `transitionStatus`) has no cascade at all: `$phase->update(['status' => 'cancelled'])` and nothing else. No registration withdrawal, no fee recalculation, no credit. Live and reachable via a real authenticated API route today (`fest.manage` permission, no special UI needed), even though no admin screen currently exposes a phase-level cancel control.

### 9d. Fee-model arithmetic gaps in named-Sahodaya scenarios (money)

- **KOCHI-02 (P1).** `kalolsavam_composite`'s `calculate()` method has no team/group-item billing branch — unlike its two sibling methods (`calculateForEvent`, `calculateForHead`), which both correctly skip-then-separately-bill team items. A 3-member group entry is billed as 3 independent individual registrations: ₹300 instead of a single team charge, at Kochi Metro's own stated per-student rate. Reproduced live.
- **MLB-01 (P1).** No `fee_model` in the catalog can express "stepped base fee by student-count slab, plus a flat per-student add-on" — Malabar's stated combined rule. Configuring either mechanism alone underbills by 21–78% depending on student count (6,000 vs. 28,050 at 49 students using slab alone; 22,050 vs. 28,050 using flat alone).

### 9e. Cap/floor and invoice-line coverage gaps (money, cross-cutting)

- **FEE-03[Precedence] (P1).** `school_fee_cap`/`school_fee_min` — the one Sahodaya-configurable safety rail against an unexpectedly large bill — silently has no effect for sports, per-head, or per-phase billed events. Reproduced live: a configured ₹1,000 cap did not stop a ₹7,000 phase-billed total from going through uncapped.
- **FEE-05[Precedence] (P1).** `fest_school_event_fee_lines` (the itemized invoice-line table) is only ever populated for 2 of the 9 documented `fee_model` values (`sports_composite`, `kalolsavam_composite`). The remaining 7 — including `cksc_tiered`, the config-wide **default** for the `sahodaya` level — either delete-and-leave-empty or never touch the table. There is no durable, queryable record of what produced most schools' bills.
- **FEE-07[Precedence] (P1).** The "official" already-approved fee receipt view (`feeReceipt()`) never freezes a breakdown — it re-resolves the schedule **live** on every view via `resolveSchedule()` + `breakdown()`. Two people viewing the same already-closed, already-paid receipt at different times, after any fee-settings or item-fee edit in between, can see genuinely different line items for a payment that is supposed to be settled.

### Notable P2 calculation issues, for completeness (not part of the strict P0/P1 filter above)

- KOCHI-07 (silent ₹2,000 default for Kochi Metro's school fee — see Sections 4–5) and MLB-02 (₹0-student slab defaulting to the *highest* tier, ₹12,000) are both confirmed, reproduced, financially-material bugs scored P2 rather than P1 because neither Sahodaya is a live tenant today.

---

## 10. Required database changes

| Change | Type | Reason | Finding(s) |
|---|---|---|---|
| Decide fate of `fest_school_event_fees.override_amount` — wire it into `feeTotalDue()`/`recalculate()` and expose an admin UI control, **or** drop the column | Schema decision (drop = migration; wire-up = no migration, app-layer only) | Confirmed dead in 3 independent checks across 3 audit sections; a developer reading the schema would reasonably assume it works | FEE-02[Catalog], FEE-04[Precedence], FEE-08[Snapshot] |
| Extend `fest_school_event_fee_lines` with rule-source and rule-version/effective-date columns (and ideally a `phase_id`) if this table is meant to be an audit ledger | Add columns | Of 9 requested attribution fields (rule source, version, phase, discount, tax, quantity, unit amount, adjustment, final amount), only 3 exist today | FEE-06[Precedence] |
| New fee-adjustment/audit table (or extend `FestFeeCredit`) capturing `old_total_due`, `new_total_due`, `amount_paid`, `changed_by`, `reason` at the moment `total_due` is overwritten on a record with `amount_paid > 0` | New table | The only existing audit mechanism (`demoteSiblingApprovals()`) is narrow: structurally cannot fire on a fee *decrease*, misses every fee model without item-level registrations, and never captures the value being overwritten. Confirmed separately that no "create a revision while preserving the original" mechanism exists anywhere in this domain either — `FestFeeCredit` (the nearest analog) is scoped strictly to rejection/cancellation refunds across all 6 of its real call sites, none of which is a fee-schedule edit | FEE-01[Snapshot], FEE-04[Snapshot], FEE-06[Snapshot] |
| Add a frozen breakdown snapshot column (e.g. `approved_breakdown_json`) on `fest_school_event_fees`, captured at approval time, mirroring the pattern `FestInvoiceService` already uses for `breakdown_json` at issue time | New column | Official receipt view currently re-resolves live every time, so an already-approved receipt's line items can change after the fact | FEE-07[Precedence] |
| Confirm `fest_event_invoices.status` enum includes (or add) a `void` value | Possible enum addition | Cancelling an event never updates invoice rows today — no mechanism exists to mark a pre-cancellation invoice as no longer valid | FEE-05[Financial] |
| No migration needed: `activity_log` table already exists (spatie/laravel-activitylog is installed and migrated) but zero models use `LogsActivity` | App-layer only | Dead audit infrastructure, project-wide, not fee-specific | FEE-05[Snapshot] |
| No migration needed: a combined student-count-slab + flat-per-student fee model (Malabar) can most likely be built entirely against the existing `fee_settings` JSON column | App-layer only | `fee_settings` is already a flexible JSON column; the gap is in `FestSchoolEventFeeService`'s dispatch logic, not the schema | MLB-01 |
| No migration needed: MCS's `tenant_id` placeholder and empty `item_phase_map` live in a PHP data file (`app/Support/data/mcs_kalotsav_phase_plan.php`), not the database | Data/config fix | Confirmed the file itself, not a migration, is what needs real values before any MCS rollout | MCS-02 |

---

## 11. Required regression tests

Grouped by theme; items marked **CRITICAL** correspond to the P0/P1 findings in Section 9 and should land alongside their fixes, not after.

**Phase lifecycle**
- Sync a phase once, edit it via the normal `update()`/`quickStatus()` path, assert the leaf/child-phase reflect the edit (or that a resync is auto-triggered). **CRITICAL** — PHASE-01
- Item under an open phase with a divergent, closed head/area window still registers successfully. — PHASE-02
- Publish one phase's results (non-regional-billing, `phase_mode_enabled`) and verify public visibility/report access is scoped to that phase alone. — PHASE-03

**Fee catalog**
- End-to-end appeal-fee traceability (`FestAppealFeeTest`). — FEE-01[Catalog]
- `flat_school` bills `flat_amount` exactly once, independent of item/student counts. — FEE-05[Catalog]
- `recordCreditPayout()`'s cash-ledger effect (currently zero coverage in the permanent suite despite the mechanism working). — FEE-06[Catalog]

**Named-Sahodaya scenarios**
- Kochi Metro: multi-member group item bills a single team charge, not per-participant. **CRITICAL** — KOCHI-02
- Kochi Metro: `kalolsavam_composite`/`sports_composite` with both `school_registration` and `school_registration_flat` absent resolves to ₹0, not ₹2,000 — at both the normalizer and resolver layers. — KOCHI-07
- Kochi Metro: `max_per_school > 1` item, same student added to two entries, rejected. — KOCHI-04
- Wayanad: withdrawn Phase-2 registration excluded from `FestSchoolEventFeeService`'s total (mirroring the pattern already proven for the sibling `FestSportsCompositeFeeService`). — WYN-05
- Wayanad: the "other" class-category tier resolves correctly under real derivation, plus one case using Wayanad's actual 30,000/25,000/20,000 map. — WYN-06
- Wayanad: combined gate (membership + fee-required) exercised through `createForSchool()`, not a direct `FestRegistration::create()` bypass, for a phased event. — WYN-04
- Malabar: `recalculate()` output when both `student_count_slabs` and `per_student_amount` are configured together, once the combined model exists — locks in the intended behavior. **CRITICAL** (blocks Malabar entirely until built) — MLB-01
- Malabar: `studentCount=0` and the unlinked-participant transient state for `student_count_slab`. — MLB-02
- Malabar: `demoteSiblingApprovals()`'s reopen-on-recount behavior. — MLB-03
- MCS: `createForSchool()` succeeds for an item under a **non-regional** phase (Digi Fest / District) of a `phased_regional_billing` event. **CRITICAL — this is the P0 fix's regression test** — MCS-01
- MCS: positive live-path test — a school registers for a Level-2 item while Level-1 is closed. — MCS-04
- MCS: rename or replace `test_mcs_two_level_registration_notice` so "MCS" in a test name actually exercises the batch+phase mechanism that would run in production for MCS, not the older per-phase-share mechanism. — MCS-06

**Precedence & invoicing**
- Full `resolveSchedule()` merge order, asserted end-to-end with values set at every layer simultaneously. — FEE-01[Precedence]
- `school_fee_cap`/`school_fee_min` applied for sports/per-head/per-phase billing, not just the plain path. **CRITICAL** — FEE-03[Precedence]
- `fest_school_event_fee_lines` populated for the other 7 fee models (`cksc_tiered`, `item_catalog`, `per_student`, `flat_school`, `student_count_slab`, plain `per_item`, and per-phase billing). — FEE-05[Precedence]
- Approved-receipt breakdown stability across a post-approval fee-settings/item-fee edit. **CRITICAL** — FEE-07[Precedence]

**Snapshotting / immutability**
- `total_due` preserved (or an adjustment row created) when `recalculate()` runs a second time on a record with `amount_paid > 0`. **CRITICAL** — FEE-01[Snapshot]
- Invoice status/`total_amount` correctness on re-issue after a fee-schedule change. **CRITICAL** — FEE-02[Snapshot]
- Parameterize the existing `recalculateBatch()` guard test across all four recalculation methods once they share the guard. — FEE-03[Snapshot]
- Fee-settings edits blocked (or explicitly flagged) once an event's status indicates closed/completed. — FEE-07[Snapshot]

**Tenant isolation**
- A real integration test booted with the **process/OS-level** `TENANCY_DATABASE_PER_SAHODAYA=true` (not a runtime `config()` override, which this pass proved does not activate the real bootstrapper) against two tenants on separate physical connections. — TIF-01
- Promote the scratch tests for fee-schedule isolation, HTTP IDOR, receipt numbering, and membership-slab isolation into the permanent suite. — TIF-02, TIF-03, TIF-04, TIF-05
- `FestEvent.state_program_id` immutability guard, plus a test confirming the event-update endpoint's validation whitelist silently drops an injected `state_program_id`. — TIF-06

**Financial workflows**
- End-to-end `transitionToCancelled()` test for all three status services (`FestEventStatusService`, `McqExamStatusService`, `TrainingProgramStatusService`) — **currently zero tests exist for any of the three.** **CRITICAL, fix the typo first** — FEE-01[Financial]
- Batch-billed event cancellation issues the correct credit once the guard interaction is resolved. **CRITICAL** — FEE-02[Financial]
- Phase-level cancellation cascade, once built. — FEE-03[Financial]
- `FestEventInvoice` rows updated/voided on event cancellation. — FEE-05[Financial]
- Per-head overpayment reconciliation fires automatically at approval, matching every other billing model. — FEE-06[Financial]

**Hardcoded-logic guardrail**
- Lock in today's actual (risky) behavior for an unconfigured sports event, and confirm the sahodaya-round path stays `fee_model=none`, so a future change to either is deliberate rather than accidental. — FEE-02[Hardcoded]
- Optional: a lightweight CI grep step flagging any future `->slug ===`/`->subdomain ===`/hardcoded-tenant-name comparison introduced in `app/Services/Events` or fee-adjacent `app/Support` classes. — FEE-01[Hardcoded]

---

## 12. Prioritized correction plan

### Phase 0 — Immediate, near-one-line fixes (unblock core functionality)

1. **Fix the `FestPageActivity` namespace typo** in `FestEventStatusService::transitionToCancelled()` (`\App\Support\Enums\FestPageActivity` → `\App\Support\FestPageActivity`). One line. Currently **no Sahodaya admin can cancel any FEST event, on any Sahodaya, for any reason** — every attempt 500s. FEE-01[Financial], P0.
2. **Guard `lockForRegistration()`'s two call sites with the same `isRegional()` check `operationalEvent()` already uses.** Currently blocks all registration for Digi Fest and District Kalotsav — half of MCS's planned four-phase structure — the instant `phased_regional_billing` is used by a real tenant. MCS-01, P0.
3. **Add the `recalculateBatch()` immutability guard (or an explicit adjustment record) to `recalculate()`, `recalculateForSportsEvent()`, `recalculateForHead()`, and `recalculateForPhase()`.** Prevents silent post-payment `total_due` rewrites with zero audit trail. FEE-01[Snapshot], FEE-03[Snapshot], P0/P1.
4. **Fix the invoice sticky-status OR-clause** in `FestInvoiceService` so status is derived purely from the current fee state. Prevents an invoice from showing "paid" after its total silently changed underneath it. FEE-02[Snapshot], P0.
5. **Trigger `FestPhaseTopologyService::sync()` (or a targeted resync) from `FestEventPhaseService::updatePhase()`/`transitionStatus()`** whenever the event uses phased regional billing. Without this, phase edits silently do nothing once a leaf exists. PHASE-01, P0.

### Phase 1 — High-value P1 fixes

6. Apply `school_fee_cap`/`school_fee_min` uniformly across all four recalculation methods. FEE-03[Precedence].
7. Fix `kalolsavam_composite`'s `calculate()` to route team/group items through the same skip-then-separate-bill pattern its sibling methods already use. KOCHI-02.
8. Make `FestItemWindowResolver` phase-aware (or route `FestItemRegistrationGate` through phase-aware logic first). PHASE-02.
9. Extend `syncFeeLines()` to cover all 9 fee models, not just 2. FEE-05[Precedence].
10. Freeze the approved-receipt breakdown at approval time instead of re-resolving live on every view. FEE-07[Precedence].
11. Once Phase 0 item 1 lands, make `transitionToCancelled()` compute the credit directly from the registrations it just withdrew, rather than relying on `recalculateBatch()`'s now-frozen total. FEE-02[Financial].
12. Extend phase-level cancellation with the same cascade event-level cancellation is meant to have. FEE-03[Financial].
13. Give `phase_mode_enabled` (non-regional-billing) events a phase-scoped results-publish path, mirroring `FestPhasePublicationService`. PHASE-03.
14. Route appeal-fee payment through the standard `FeeReceipt` → approval → ledger pipeline. FEE-01[Catalog].

### Phase 2 — Product/design decisions needed before onboarding any of the 5 named Sahodayas

15. Decide and build (or explicitly reject) a combined student-count-slab + flat-per-student fee model for Malabar. MLB-01.
16. Fix the 0-student slab default (currently the top tier, ₹12,000; should be ₹0 or the lowest tier). MLB-02.
17. Fix Kochi Metro's silent ₹2,000 school-registration default, at both the normalizer (`FestEventFeeResolver::normalizeEventFeeSettings()`) and resolver (`schoolRegistrationAmount()`) layers — fixing only one leaves the other reachable. KOCHI-07.
18. Decide whether Wayanad's 300-student Secondary sub-threshold is a real requirement; if so, extend the tier resolver (both parallel implementations). WYN-02.
19. Populate MCS's real item catalogue, phase map, and tenant_id before any rollout — expected pre-launch data work, not a code defect. MCS-02.
20. **Obtain and transcribe Vatakara's actual fee reference material.** Nothing in Vatakara's section of this audit can proceed until this exists — see Section 13.
21. Decide whether unconfigured sports events should keep silently billing config defaults, or fail safe to "no fee" the way Kalolsavam already does. FEE-02[Hardcoded].

### Phase 3 — Hygiene / lower urgency

22. Remove or wire up `override_amount` (confirmed dead in 3 independent checks).
23. Wire `LogsActivity` onto `FestSchoolEventFee`/`FestEventInvoice`, or remove the unused `spatie/laravel-activitylog` dependency. FEE-05[Snapshot].
24. Update the stale "not wired" docblocks on `EventLifecycleGate::allowRegistrationForItem()`/`allowMarkEntryForItem()` — both are live today. PHASE-04.
25. Fix `FestEventNotifier`'s `$c->fee` → `$c->schoolEventFee` typo so cancellation notices actually show the credit amount. FEE-04[Financial].
26. Add late-fee and per-school-discount capability to Fest events, if product wants parity with MCQ/Training. FEE-03[Catalog], FEE-04[Catalog].
27. Add the real (OS-env) `TENANCY_DATABASE_PER_SAHODAYA=true` integration test. TIF-01.

---

## 13. What this audit could not verify, and why

### Vatakara — front and center

**Nothing.** Not one number in this audit's Vatakara section could be verified, because no source material exists to verify against. A repo-wide, case-insensitive search for "vatakara" returns zero hits anywhere — no tenant, no seeder row, no config, no test fixture, no documentation, no image or PDF. A search for the visually similar "vadakara" turns up exactly 5 incidental hits, all either a generic UI placeholder string (`noreply@vadakarasahodaya.in`) or local-dev `/etc/hosts` convenience entries with no backing tenant row in any seeder. `git log` across all history returns zero commits mentioning either spelling. This audit was asked to verify fee numbers against a reference image; **no such image (or any equivalent transcription) was ever attached to this workflow.** Reporting any Vatakara fee figures under these conditions would mean fabricating data and presenting it as verified — which this report does not do. **This blocks sign-off on Vatakara specifically** until the actual reference material (image, table, or plain-text transcription of membership fee type/amount, slab structure, and registration/participation figures) is supplied; once it is, it should be traced the same way the other four Sahodayas were: against `FestEventFeeResolver::resolveForLevel()` and related resolvers, or against a newly written test patterned on `FestFeeNoticeScenariosTest.php`.

### The other four named Sahodayas — verified against code, never against a live account

Every "PASS" for Kochi Metro, Wayanad, Malabar, or MCS in this document means the fee-resolution **code**, given the inputs stated in this audit's brief, produces the stated output. It does **not** mean a real school in a real Sahodaya account was billed correctly, because no such account exists to check. If any of these five Sahodayas is provisioned as a real tenant with configuration that differs even slightly from what this audit assumed (a different fee_model choice, a differently-shaped `fee_settings` JSON, an admin leaving a field blank that this audit assumed was filled), the numbers in this report do not automatically transfer — they would need to be re-traced against the real configuration.

### Physical multi-tenant database isolation

The production default (`tenancy.database_per_sahodaya=true`, one Postgres database per Sahodaya cluster) has never been exercised by any test in this repository — the entire suite runs against a single in-memory SQLite database with that mechanism forced off. This audit confirmed (TIF-01) that even a deliberate attempt to close this gap with a runtime `config()` override inside a test would not actually activate the real isolation bootstrapper, due to how the bootstrapper list is computed once at application boot from the OS environment variable. Everything this audit verified about tenant isolation (Section 6) was verified at the **application/query level** (does a `WHERE tenant_id = ?` clause exist and get honored) — never at the **physical connection** level, because doing so would require a second real database and a process-level environment change this audit's sandbox does not exercise.

### MCS's real fee figures

Only one number for MCS is real and configured anywhere in this repository: the flat ₹4,000 Level-1 school base fee in `app/Support/data/mcs_kalotsav_phase_plan.php`. The item-to-phase mapping is empty (two commented-out example lines only), the item catalogue file is 18 lines that mostly just re-uses a **different** Sahodaya's (CKSC's) 145-line catalogue wholesale, and `tenant_id` is still the literal placeholder string `REPLACE_WITH_MCS_SAHODAYA_TENANT_ID`. This audit could not verify per-item MCS fee figures because they do not exist in the repository to compare against — that data-entry work has not happened yet, and per `docs/MCS_FOUR_PHASE_COMPLETION_PLAN.md`'s own status line, is the acknowledged next step before rollout.

### Findings whose "impact" is necessarily forward-looking

A handful of confirmed P0/P1 bugs (MCS-01's registration block, PHASE-01's phase-edit staleness) live entirely in code paths with **zero live traffic today**, because the mechanisms they affect (`phased_regional_billing`, multi-phase topology sync) have never been exercised by a real tenant. These are real, reproduced code defects — not hypothetical — but their stated "impact" describes what will happen the first time a real Sahodaya uses that mechanism, not something that has already gone wrong in production.

### Corrections that happened during the second-pass verification baked into this report

The JSON this report is built from already reflects a second independent re-check of every finding, and several first-pass claims were corrected or reversed during that process — this report reflects only the corrected/final version. Notable examples: a "missing fee-credit payout mechanism" claim (Catalog section) was refuted — the mechanism exists and works, confirmed with a fresh scratch test; a "Wayanad tier lookup resolves only ONE tier" claim (WYN-06) was factually wrong on re-check (it resolves two of three); an "MCS Level 1/2 deadlines aren't independent" claim (MCS-04) turned out to describe *correct* behavior, just under-tested; and a repro command in FEE-03[Catalog] (late fees) was itself wrong as originally written (a plain `grep -n "late"` returns 79 false-positive hits from `calculate`/`recalculate` method names) even though its underlying conclusion held up under a corrected, word-boundary search.

### Frontend / UI behavior

This audit is overwhelmingly a server-side (PHP/PHPUnit) verification exercise. Vue component behavior was checked only where a specific finding required it (e.g., confirming `FeesTab.vue` relabels a misleading field name in KOCHI-03; confirming no admin screen currently exposes phase-level cancellation in FEE-03[Financial]). No systematic frontend audit, and no live browser-based verification against a running instance of the application, was performed.
