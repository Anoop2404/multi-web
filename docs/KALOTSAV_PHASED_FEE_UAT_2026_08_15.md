# Kalotsavam Phased-Fee UAT (2026-08-15)

**Companion plan:** [`KALOTSAV_PHASED_LEVEL_FEE_PLAN.md`](KALOTSAV_PHASED_LEVEL_FEE_PLAN.md) — §8 "Implementation status" for what actually shipped; this document is that plan's **Phase E (UAT)**, using the same table format as `REGION_ADMIN_SCOPING_UAT_2026_08_15.md`/`STATE_MULTI_REGION_UAT.md`.

**Status of this document:** ready-to-run test plan. **No live database or test runner was available to the author.** Phases A–D (data model, `FestEventPhaseService`, admin phase-management UI, school-facing phase billing UI) were built and `php -l` syntax-checked only — "everything below is syntax-checked... but functionally unverified" per the plan's own §8. Run tenant migrations against a real staging tenant before attempting any case below.

**IMPORTANT — scope boundary for this document:** the plan's §7 addendum (region-per-phase conduct, N-tier/slab fee models, the group-item per-participant surcharge, and the cumulative overall scoreboard across phases) is **design-only as of 2026-08-15 — no code exists for any of it**. Cases 4.x below that depend on the addendum are explicitly marked **BLOCKED — NOT YET IMPLEMENTED, DO NOT ATTEMPT** and contain no executable steps. Do not run them against a build that only has Phases A–D; they will fail for the correct reason (the feature doesn't exist), not a real defect.

**Fixture prerequisites:**
- A Kalotsavam event with `phase_mode_enabled = true` (any Sahodaya on this tenant; MCS-specific topology is not required).
- Two `FestEventPhase` rows created via the admin UI: "Level 1" (registration_open/close set, `school_registration_fee_share` = ₹4000) and "Level 2" (registration_open/close set, `school_registration_fee_share` = ₹0 or blank).
- At least 2 items assigned to Level 1's `phase_id`, at least 2 items assigned to Level 2's `phase_id`, each with a known per-item fee.
- One test school not yet registered for anything on this event.
- A second, non-phased ("legacy") Kalotsavam event on the same tenant, with existing registrations/fees from before this feature — used for the regression check in §5.

---

## 1. Creating a multi-phase event and saving phase fee configuration (Phase B admin UI)

| # | Actor/Role | Precondition | Steps | Expected Result | Pass/Fail |
|---|---|---|---|---|---|
| 1.1 | Sahodaya admin | Fixture event exists, `phase_mode_enabled` on | Open the event's Phases page (`Phases.vue`), fill the "add phase" form: Name = "Level 1", Registration open/close dates, `school_registration_fee_share` = 4000, submit | Phase created; new phase row appears in the list showing "Reg: <open date> → <close date>" and "School fee: ₹4000" | |
| 1.2 | Sahodaya admin | Follows 1.1 | Add a second phase "Level 2" with its own registration open/close dates, leave `school_registration_fee_share` blank | Phase created; list shows Level 2 with its own reg window and no "School fee" badge (since share is unset) | |
| 1.3 | Sahodaya admin | Both phases exist | Assign Digi Fest + Off Stage items to Level 1 (via the item's phase field), Sargadhara + District items to Level 2 | Each item's `phase_id` persists correctly; reload the Phases page and confirm item counts/assignment reflect this split | |
| 1.4 | Sahodaya admin | — | Edit Level 1's `school_registration_fee_share` from 4000 to 2500, and set Level 2's to 1500, save | Values persist via `FestEventPhaseService::updatePhase()`'s lifecycle-fields allow-list; reload confirms 2500/1500 saved correctly | |
| 1.5 | Sahodaya admin | Sum of phase shares (2500+1500=4000) equals the event's nominal `school_registration_fee` | Check the Phases page for the running-total warning banner | No warning shown (shares sum to the nominal total) | |
| 1.6 | Sahodaya admin | Change Level 2's share to 1000 (sum now 3500, below the nominal 4000) | Save | Save succeeds (soft warning only, not a hard validation per plan §3 item 4); a warning banner appears noting the shares don't sum to the nominal total, but does not block the save | |
| 1.7 | Sahodaya admin | — | Reset shares to 2500/1500 for the remaining cases below | Confirmed via reload | |
| 1.8 | Sahodaya admin | — | Attempt to set two phases as `is_default = true` simultaneously (if the UI exposes this toggle) | Only one phase ends up `is_default = true` — setting a new default clears the flag on all other phases for the event (`clearOtherDefaults()`) | |

## 2. School registration and phase-specific billing (Phase C/D)

| # | Actor/Role | Precondition | Steps | Expected Result | Pass/Fail |
|---|---|---|---|---|---|
| 2.1 | School admin | Test school not yet registered; event has Level 1/Level 2 phases per §1 | Log in as the school, open the event's registration page, register a student for one Level 1 item only | Registration succeeds | |
| 2.2 | School admin | Follows 2.1 | Open the event's billing/payment screen | `EventBillingPanel.vue` renders the "per-phase" branch (`PhaseBillingInvoices.vue`): one payable card for Level 1 showing its breakdown (school reg share ₹2500 + the registered item's fee) and its own status badge. **No card for Level 2** yet, since the school has no billable activity there (`phasesWithActivityForSchool()` filters to phases with actual registrations or existing fee records) | |
| 2.3 | School admin | Follows 2.2 | Confirm the Level 1 card's "Due" amount equals exactly ₹2500 (school share) + the Level 1 item's configured fee, with no Level 2 items or their fees included anywhere in that card's breakdown | Amount matches expected total exactly | |
| 2.4 | School admin | Follows 2.2 | Upload payment proof for the Level 1 card only (txn ref, bank name, amount, receipt file) | Upload succeeds via the existing `POST .../events/{event}/payment` endpoint with `phase_id` set to Level 1's id; `attachPaymentForPhase()` records it against Level 1's fee record only | |
| 2.5 | Sahodaya admin | Follows 2.4 | Approve the Level 1 payment via the admin fee-approval screen | Level 1's fee status becomes "approved"; a receipt becomes downloadable at `.../events/{event}/receipt?phase_id=<Level1 id>` | |
| 2.6 | School admin | Follows 2.5, Level 1 still the only phase with activity | Confirm Level 2 remains completely unbilled — no card, no due amount, no payment prompt for Level 2 | No Level 2 card exists; the ₹4000 (or configured share) is **not** re-charged or duplicated anywhere | |
| 2.7 | School admin | Follows 2.6 | Now register a student for a Level 2 item | A **second, independent** card appears for Level 2 in `PhaseBillingInvoices.vue`, showing only Level 2's own school-fee share (₹1500) + the Level 2 item's fee — entirely separate from Level 1's card and its (already-approved) status | |
| 2.8 | School admin | Follows 2.7 | Confirm the Level 2 card can be paid **without** any dependency on Level 1's payment state (e.g. pay Level 2 even if Level 1 were still unpaid/pending) | Payment succeeds regardless of Level 1's status — confirmed no-gating design (plan §3 item 7/§6 open question 1: "no gating — the phases are independent") | |
| 2.9 | School admin | Both phases now have approved payments | Open the "Preview combined invoice" / "Download combined invoice" links shown alongside the per-phase cards | Combined invoice totals equal the sum of Level 1's and Level 2's individual totals — no double-counting | |
| 2.10 | Sahodaya admin | — | On the admin side, view the school's fee status for this event | Admin sees separate Level 1 and Level 2 fee records (`FestSchoolEventFee` rows with distinct `phase_id`), each independently marked paid/approved, not merged into one rolled-up record | |

## 3. Phase transitions (`FestEventPhaseService::updatePhase()` / `transitionStatus()`)

| # | Actor/Role | Precondition | Steps | Expected Result | Pass/Fail |
|---|---|---|---|---|---|
| 3.1 | Sahodaya admin | Level 1 phase exists, status at its migration default (e.g. `draft`) | Use the phase status control to transition Level 1 through the same guarded state machine `FestEvent` uses (draft → published → registration_open → ongoing → completed), one step at a time | Each transition succeeds only when it's a valid edge in `StatusTransitionGuard::FEST_EVENT_TRANSITIONS`; the phase's `status` column updates correctly after each call to `transitionStatus()` | |
| 3.2 | Sahodaya admin | Level 1 status = `registration_open` | Attempt an invalid transition (e.g. jump straight to `completed`, skipping `ongoing`) if that edge isn't in the allowed transition matrix | Transition rejected (`StatusTransitionGuard::assert()` throws/blocks); phase status unchanged | |
| 3.3 | Sahodaya admin | Level 1 status = `registration_open`, `registration_close` datetime is in the future | Confirm a school **can** still register for a Level 1 item | Registration succeeds — window is open | |
| 3.4 | Sahodaya admin | Edit Level 1's `registration_close` to a past datetime and save | Confirm a school **cannot** register a new item under Level 1 | `FestPhaseLifecycleService::effectiveLifecycleForItem()` resolves the closed window for Level 1's items; registration attempt is blocked/rejected with a window-closed message | |
| 3.5 | Sahodaya admin | Level 1 closed per 3.4 | Confirm Level 2's registration window (still open, independent dates) is **unaffected** — a school can still register Level 2 items normally | Level 2 registration succeeds; per-phase window resolution is independent per phase | |
| 3.6 | Sahodaya admin | Level 1 phase | Set `scoring_locked = true` on Level 1 via the phase edit form | Level 1's items become locked for score entry (per `FestPhaseLifecycleService` reading this column); Level 2 remains unaffected (independent flag per phase) | |
| 3.7 | Sahodaya admin | Level 1 phase | Toggle `results_published` on Level 1 | Publishing state changes for Level 1 only; confirm no unintended interaction with Level 2's own `results_published` flag | |
| 3.8 | Sahodaya admin | — | Attempt to slip a `status` value directly into a general `updatePhase()` payload (e.g. via a raw form submission bypassing the dedicated status control), if reachable | `status` changes are **not** applied through `updatePhase()`'s lifecycle-fields allow-list (LIFE-05 fix) — only `transitionStatus()` can change `status`, and only through the guard. Confirm status does not change via this path | |

## 4. Group-item per-participant fee calculation

| # | Actor/Role | Precondition | Steps | Expected Result | Pass/Fail |
|---|---|---|---|---|---|
| 4.1 | — | — | **BLOCKED — NOT YET IMPLEMENTED, DO NOT ATTEMPT.** The group-item flat-fee + per-participant surcharge (e.g. "a 7-member group item bills ₹250 + ₹100×7 = ₹950") is §7.4 addendum item L in the plan doc — confirmed as design-only. As of 2026-08-15, `FestItemFeeResolver::amountForItem()` and `FestSportsCompositeFeeService`'s team-fee branch both still return **one static amount per team/group registration**, with no read of `FestGroup::participants()->count()` anywhere in the fee-calculation path (verified by code inspection: no `participants()->count()` reference exists in either file). Do not write or run steps for this case until §7 item L ships; when it does, extend this row into the same shape as §2 above (register a 7-member group item, confirm billed amount = flat fee + per-participant rate × 7). | N/A — feature does not exist | |

## 5. Non-phased (legacy) events unaffected — regression

| # | Actor/Role | Precondition | Steps | Expected Result | Pass/Fail |
|---|---|---|---|---|---|
| 5.1 | Sahodaya admin | Legacy fixture event: `phase_mode_enabled = false` (or no `FestEventPhase` rows at all) | Confirm `FestSchoolEventFeeService::usesPerPhaseBilling()` returns false for this event (by code path: `phase_mode_enabled` is falsy, or `FestEventPhase::where('event_id', ...)` is empty) | Per-phase billing does not activate; the event's fee behavior is 100% today's single-record path | |
| 5.2 | School admin | Legacy event, school has pre-existing registrations/fees from before this feature shipped | Open the event's billing/payment screen | `EventBillingPanel.vue` renders the original single-invoice branch (not the per-phase or per-head branch); one combined "amount due" card, exactly as before 2026-08-15 | |
| 5.3 | School admin | Legacy event | Register for a new item, confirm the fee recalculates into the single rolled-up `FestSchoolEventFee` record (no `phase_id` on that row, or `phase_id = null`) | One fee row per school per event, as before; no new phase-scoped rows created | |
| 5.4 | School admin | Legacy event | Upload payment proof and get it approved | Existing single-invoice payment flow works unchanged; receipt downloadable at the pre-existing (non-phase-scoped) receipt URL | |
| 5.5 | Sahodaya admin | Legacy event | Confirm the event's Phases page (if visited) shows no phases, and does not force phase creation or otherwise change existing behavior | Page shows an empty/optional phases list; nothing about the legacy event's fee flow is altered by this feature existing elsewhere on the platform | |
| 5.6 | Sahodaya admin | Sports event using `fee_model = sports_composite` (per-head billing, pre-existing feature) | Confirm per-head billing (`usesPerHeadBilling()`) still works exactly as before | Per-head billing unaffected — `usesPerPhaseBilling()` explicitly returns `false` immediately for any `event_type === 'sports'`, so the two per-X billing paths never collide on the same event | |
| 5.7 | Sahodaya admin | Any Sahodaya not using phased fees at all | Spot-check 2–3 other in-flight (non-fixture) events on the tenant for any change in fee totals, invoice format, or receipt behavior since before this feature's migrations ran | No observable difference — confirms "phase mode off = today's single-bill behavior, unchanged" (plan §6 open question 2) | |

---

## Out of scope / not covered by this pass

- **Region-per-phase conduct** (§7.3 of the plan: `partition_group` on `school_region_assignments`, `region_partition_group` on `FestEventPhase`, per-group region pickers) — design-only, not built. No test cases written; do not attempt.
- **Cumulative overall scoreboard across phases** (§7.3a: `FestPhaseScoreboardService`, progressive "phase1, then +2, then +3..." public totals) — design-only, not built. No test cases written; do not attempt.
- **N-tier/slab fee models** (§7.4 items I/J: class-derived fee tiers beyond the current 2-key `secondary`/`senior_secondary` map, and `student_count_slab`) — design-only, not built.
- **State-level qualification remapping against phases** (`FestQualificationService` reconciliation) — explicitly flagged in the plan's §8 as an unanswered open item, not started.
- Combined vs. per-region scoreboard toggle testing from the original "K — UAT" plan row is **not** attempted here for the same reason as the region-per-phase bullet above — that toggle only becomes meaningful once §7.3's region-per-phase mechanism exists; today's `combine_regions_at_finale` flag operates on region-partition siblings, not on phases, and is already covered separately in `STATE_MULTI_REGION_UAT.md` and `REGION_ADMIN_SCOPING_UAT_2026_08_15.md`.
