# Kalotsavam Two-Level Registration & Staggered Fee Collection — Implementation Plan

**Trigger:** MCS's "Registration for Digifest, Off Stage, Sargadhara and District Level Events – 2026" notice — registration and payment run in two levels with two deadlines and two fee groups:

| Level | Covers | Deadline | Fee |
|---|---|---|---|
| Level 1 | School registration + Digi Fest + Off Stage | 31/08/2026 | ₹4000 school reg + Off Stage + Digi Fest item fees |
| Level 2 | Sargadhara + District level events | 08/09/2026 | Sargadhara + District item fees |

**Scope decision (confirmed with user 2026-08-15):** build this as a general, reusable platform capability — any Sahodaya running Kalotsavam should be able to configure staggered registration levels with independent fee collection, not just MCS.

**Implementation status (updated 2026-08-15):** Phases A–D of §4's core rollout are code-complete and committed to the repo — see §8 below for exactly what shipped, what's unverified (no live DB/PHP runtime in the build sandbox — nothing has been run against a real database yet), and what's still open. The §7 addendum (region-per-phase, fee tier/slab generalization, group-item surcharge, cumulative scoreboard) and Phase E (UAT) have **not** been started.

**Companions:** [`REGION_AND_PHASE_KALOTSAV_PLAN.md`](REGION_AND_PHASE_KALOTSAV_PLAN.md) (named-phase foundation this plan builds on), [`REGION_PHASE_EVENT_REPORTING_REMEDIATION_PLAN.md`](REGION_PHASE_EVENT_REPORTING_REMEDIATION_PLAN.md) (§6.3's ten operational areas — fees is an eleventh, added by this plan), [`MCS_KALOTSAV_IMPLEMENTATION_PLAN.md`](MCS_KALOTSAV_IMPLEMENTATION_PLAN.md) (MCS's existing region/district topology, unaffected by this change).

---

## 1. What already exists (verified against current code, 2026-08-15)

This is closer to "configure it" than "build it from scratch." Two mechanisms already exist and were built for exactly this kind of grouping:

- **`FestEventPhase`** (`app/Models/FestEventPhase.php`) — a named phase per event (e.g. "Level 1", "Level 2") with its own `registration_open`/`registration_close`, plus `status`, `scoring_locked`, `schedule_published`, `results_published`, `appeals_open`. Items attach via `phase_id` on `fest_event_items`.
- **`FestPhaseLifecycleService::effectiveLifecycleForItem()`** already resolves each item's *effective* registration window from its assigned phase when `FestEvent.phase_mode_enabled` is on, falling back to the event's own dates when phase mode is off. **Registration windows staggered by phase are already fully wired and working today.**
- **Sahodaya admin UI** — `Phases.vue` already lets an admin create phases, name them, and assign items to a phase (`resources/js/Pages/Admin/Sahodaya/Events/Phases.vue` + `FestEventPhaseService`).
- Separately, `FestItemHead` (Digi Fest / Off Stage / Sargadhara / District would each naturally be a Head) already carries its own `reg_start`/`reg_end` and is a second, independent place windows can be set (`FestItemWindowResolver`) — not needed for this plan since Phases already cover it, but worth knowing it exists so Heads and Phases aren't both used to mean the same thing.

**So Level 1 vs Level 2 registration deadlines can be configured today, with zero code changes**, by creating two `FestEventPhase` rows ("Level 1", "Level 2") on MCS's Kalotsavam event, setting each phase's `registration_open`/`registration_close`, and assigning Digi Fest + Off Stage items to Level 1 and Sargadhara + District items to Level 2.

## 2. The real gap: fee billing is not phase-aware

`FestSchoolEventFeeService::recalculate()` — the method that computes what a school owes for a Kalotsavam event — always produces **one rolled-up `FestSchoolEventFee` row per school per event**, summing `school_registration_fee` (once) plus every billable item's fee across *all* registered items regardless of which phase they're in (`app/Services/Events/FestSchoolEventFeeService.php:674-794`; item fees summed via `FestItemFeeResolver::participationBreakdown()`, which iterates every billable registration for the whole event with no phase filter).

Concretely, today: if a school registers 2 Off Stage items now and Sargadhara items three weeks later, both recalculations land in the **same** fee record — there's no way to say "Level 1's ₹4000 + items are due and payable now, Level 2's items become due and payable later as a separate bill." The school registration fee, fee receipts, "is this paid" status, and payment reminders are all event-wide, not level-wide.

Sports events *do* already have an equivalent split — `usesPerHeadBilling()` / `recalculateForHead()` / `attachPaymentForHead()` / `isHeadPaid()` produce one independent, separately-payable `FestSchoolEventFee` row per `head_id`. But this path is hard-gated to `fee_model === 'sports_composite'` (`usesPerHeadBilling()`, line ~436) and calls `FestSportsCompositeFeeService`, which doesn't apply to Kalotsavam's fee models (`item_catalog`/`cksc_tiered`). It's the right pattern to mirror, not something that already covers this case.

Fee billing also isn't in the list of ten operational areas the phase-lifecycle remediation plan tracks as wired-or-not (`REGION_PHASE_EVENT_REPORTING_REMEDIATION_PLAN.md` §6.3) — registration, food, schedule, marks, results, appeals, certificates, promotion, public pages, report packs. Fees would be an eleventh area this plan adds.

**UI gap, secondary:** the current `Phases.vue` add/edit form only exposes `name`/`code`/`sort_order` — the lifecycle fields (`registration_open`, `registration_close`, etc.) already have a write path in `FestEventPhaseService::updatePhase()` but no form inputs to set them from the Sahodaya admin screen yet. Setting Level 1/Level 2 dates today would need a one-off DB write, not a screen an admin can use.

## 3. Proposed design

Extend `FestSchoolEventFee` billing to be **phase-scoped**, using the same shape as the existing per-head sports pattern, keyed by `phase_id` instead of `head_id`:

1. **New `phase_id` nullable FK on `fest_school_event_fees`** (mirrors the existing `head_id` column). One row per (event, school, phase) when phase-billing is active; the existing `phase_id = null` rollup row keeps working for events that don't use phases, so nothing breaks for the other ~all other Sahodayas running standard Kalotsavam.
2. **`usesPerPhaseBilling(FestEvent $event): bool`** — true when `$event->phase_mode_enabled` and the event has more than one phase with items assigned. Mirrors `usesPerHeadBilling()`'s shape.
3. **`recalculateForPhase(FestEvent $event, string $schoolId, FestEventPhase $phase): FestSchoolEventFee`** — sums only items whose `phase_id` matches, via a phase-filtered variant of `FestItemFeeResolver::participationBreakdown()` (add an optional `?int $phaseId` parameter, filtering `billableRegistrations()` by `item.phase_id`).
4. **School registration fee: configurable per-phase share, not a single hard-coded owner.** **Revised 2026-08-15** — the original design (one boolean "owner" phase gets the whole flat fee) was too narrow: confirmed some Sahodayas want a flat fee owned by one phase (MCS: ₹4000 entirely on Level 1), while others want the fee genuinely *split* across phases (e.g. ₹2000 at Level 1, ₹2000 at Level 2). Replace the boolean with a **`school_registration_fee_share` nullable decimal column on `FestEventPhase`** — each phase declares the amount of the school registration fee *it* collects (not a percentage; an explicit rupee amount, matching how the rest of this fee engine already works). `recalculateForPhase()` includes `school_registration_fee_share` (defaulting to 0 when unset) instead of the full flat amount. For MCS's case: Level 1's share = ₹4000, Level 2's share = 0. For a split-fee Sahodaya: Level 1's share = ₹2000, Level 2's share = ₹2000. The admin UI (§3.6) shows the configured total school registration fee alongside a running sum of shares across phases, flagging (not blocking) when they don't add up — a Sahodaya might deliberately want them not to sum to the nominal total (e.g. a discount for early Level 1 payment), so this is a warning, not a hard validation.
5. **`recalculateAllPhasesForSchool()`, `isPhasePaid()`, `attachPaymentForPhase()`** — same shape as the existing head-scoped trio, so receipts/approval/credit flows reuse existing patterns rather than inventing new ones.
6. **`Phases.vue` form additions** — expose `registration_open`/`registration_close` date inputs (the backend write path already exists) and, on events using per-phase billing, a `school_registration_fee_share` amount field per phase (with the running-total warning from item 4 above) and a read-only "fees due for this phase" preview.
7. **School-facing payment screen** — currently shows one combined "pay now" total per event; needs to show one payable card per phase (mirrors how sports composite already shows one card per Event Head today). **Confirmed 2026-08-15: no gating between phases** — a school can register and pay for Sargadhara + District independently of whether Level 1 (Digi Fest + Off Stage) has been settled. Each phase's card is fully independent; there's no "Level 2 locked until Level 1 paid" rule to build.

Explicitly **not** changing: `FestItemHead`, the region/district topology in `MCS_KALOTSAV_IMPLEMENTATION_PLAN.md`, or anything in the sports billing path — this is additive, parallel to the existing head-based mechanism, using the field that already models "a named window of time with its own lifecycle" (`FestEventPhase`) rather than repurposing item categorization (`FestItemHead`) to also mean "billing period," which would conflict with how Heads are already used for MCS's Digi Fest/Off Stage/Sargadhara/District categorization.

## 4. Rollout phases

| Phase | Work | Est. | Status |
|---|---|---|---|
| A — Data model | `phase_id` + `school_registration_fee_share` migrations; model/fillable updates | 0.5 day | **Done** (2026-08-15) |
| B — Billing service | `usesPerPhaseBilling`, `recalculateForPhase`, `recalculateAllPhasesForSchool`, `isPhasePaid`, `attachPaymentForPhase`; phase-filtered `FestItemFeeResolver` | 2 days | **Done** (2026-08-15) |
| C — Admin UI | `Phases.vue` lifecycle date fields + fee-share input + running-total warning | 1.5 days | **Done** (2026-08-15) |
| D — School UI | Per-phase payable cards on the school registration/fees screen; receipt upload scoped to phase | 1.5 days | **Done** (2026-08-15) |
| E — UAT | Two-phase fixture on a test tenant: register Level 1 items, pay, confirm Level 2 items aren't billed yet; register Level 2 items later, confirm ₹4000 isn't re-charged; confirm existing non-phased Sahodayas see zero behavior change | 1 day | **Not started** — needs a real DB/migration run, not possible from this build sandbox |

**Total: ~6.5 developer-days.** Phases A–D were built and syntax-checked (`php -l`) in a sandbox with no live DB/PHP runtime — see §8 for exactly what that does and doesn't verify. Migrations have not been run; Phase E (UAT) still needs your normal dev environment.

## 5. Timeline risk & recommended interim step

Level 1 closes **31/08/2026** — about 2.5 weeks from today. The billing extension above (Phases B–D) is real work and shouldn't be rushed onto a live fee-collection deadline without UAT. Two options, not mutually exclusive:

- **Interim (usable this week, zero risk):** configure the two `FestEventPhase` rows today for registration-window purposes only (Level 1 / Level 2 deadlines enforce correctly), and keep collecting the ₹4000 + Level 1 item fees manually/offline or via a single combined bill until the phase-billing work ships — schools would see one combined "amount due" that happens to be correct once all Level 1 items are in, just not split into two independently-timed bills.
- **Full build:** ship Phases A–E before 31/08 if MCS specifically needs the *system* (not just staff) to enforce "pay Level 1 now, Level 2 fee doesn't even appear yet."

## 6. Open questions before building

1. ~~Should Level 2 registration/payment be *blocked* until Level 1 is fully paid, or can schools register Level 2 items in parallel and just get a second bill later?~~ **Answered 2026-08-15: no gating — the phases are independent.** A school can register/pay Sargadhara + District without having settled Digi Fest + Off Stage first. §3.7 updated to match.
2. For a Sahodaya that only ever runs one phase (the common case), should this be invisible by default (phase mode off = today's single-bill behavior, unchanged) — confirmed yes, per §3's "additive" design, but worth an explicit sign-off since it affects every other tenant.
3. ~~Does the ₹4000 school registration fee ever need to be *split* across phases for some other Sahodaya, or is "owned by exactly one phase" always correct?~~ **Answered 2026-08-15: both need to be supported, as configurable settings/rules, not a hard-coded assumption either way.** §3.4 revised — replaced the single-owner boolean with a per-phase `school_registration_fee_share` amount, so a Sahodaya can put the whole flat fee on one phase (MCS) or split it across phases (₹2000 + ₹2000, or any other split) purely via configuration.
4. ~~Refunds/credits: should a Level 1 withdrawal's credit auto-apply against the Level 2 bill, or stay scoped to Level 1?~~ **Answered 2026-08-15: neither — full refund, no auto-apply.** A Level 1 withdrawal gets its fee *returned* to the school (a genuine refund, not a credit carried forward), and Level 2 is paid and proof-uploaded completely fresh and independently. This is actually simpler than the design worried about — `FestFeeCredit`'s existing per-fee-record scoping is already correct as-is; no new cross-phase credit-application logic needs building at all. Confirms phases are fully independent for money in both directions (billing and refunds), consistent with §3.7's no-gating answer.

---

## 7. Addendum (2026-08-15): 4-phase conduct, per-phase regions, pluggable fee models

MCS's actual conduct plan is more specific than a flat two-level split. Restated from the conversation:

| Phase | Items | Region-wise? | Result scope |
|---|---|---|---|
| 1 — Digi Fest | Digi Fest items | No | Single combined result |
| 2 — Off Stage | Off Stage items | **Yes** | Region-wise conduct, but result **combined** into one overall standing |
| 3 — Sargadhara | Regional events | **Yes** | Region-wise, result stays per-region (per earlier notice image, "REGIONAL EVENTS (Sargadhara)") |
| 4 — Common items | Everything not in phases 1–3 | No | Single combined result |

Confirmed with user: only phases 2 and 3 need a region selector today; the design should not hard-code "exactly 2 regional phases" so a future phase 5/6 can also be regional if needed. The school-type fee tiers in the second image (Senior Secondary ₹11000 / Secondary ₹9000 / Other ₹6000 + ₹350/student + ₹5000 membership renewal) are **not** MCS's own numbers — they're an example of a *different* fee shape (school-type tiers + per-student rate) that the fee engine needs to support as one configurable option among several, alongside MCS's own model.

### 7.1 What already exists that helps

- **Multiple simultaneous partition groups per hub already work.** MCS's existing topology (`MCS_KALOTSAV_IMPLEMENTATION_PLAN.md`) already runs Tirur/Manjeri region children *and* a Digi Fest child *and* a District child, all under one umbrella hub, distinguished by `partition_role`/`cluster_key` on the child `FestEvent`. Phases 1–4 above map naturally onto this: phase 2 (Off Stage) gets its own region-child set, phase 3 (Sargadhara) gets a second, independent region-child set, phases 1 and 4 stay unpartitioned on the hub (or on their own non-regional child, if kept separate for scheduling reasons).
- **`combine_regions_at_finale`** already exists as a field on `FestEvent` (`FestPartitionService::shouldCombineAtFinale()`, defaults `true`) and `combinedScoreboard()` already sums region-child scoreboards into one ranking. Phase 2's "region-wise conduct, combined result" is exactly this flag set to `true` on the Off Stage partition group; phase 3's "stays per-region" is the same flag set to `false` (or that partition group's role simply excluded from `aggregation_config.include_roles`).

### 7.2 The real gap: region assignment is Sahodaya-wide, not per phase/group

`SchoolRegionAssignment` — the table that says "this school belongs to Tirur region" — is keyed by `(sahodaya_id, school_id, academic_year)` only: **one region per school per year, shared across every partitioned hub** (`FestRegionPartitionService::schoolRegion()`; `syncSchoolAcrossHubs()` explicitly pushes that one region onto *every* partitioned hub the school touches). There's no dimension for "Region X for Off Stage, Region Y for Sargadhara" — today a school gets exactly one region, full stop.

This matches the user's note that the regions may differ in number/composition between phase 2 and phase 3 — the current schema can't represent that at all.

### 7.3 Proposed design

Tie region assignment to the **partition group each regional phase owns**, rather than to the Sahodaya as a whole:

1. **`school_region_assignments` gets a nullable `partition_group` column** (string, e.g. `'off_stage'`, `'sargadhara'`, matching that phase's `cluster_key`/`partition_role` namespace). `NULL` keeps today's behavior (one Sahodaya-wide region) for every Sahodaya that isn't doing multi-group regions — zero behavior change for everyone else.
2. **`FestEventPhase` gets a `region_partition_group` nullable string** — set on phase 2 and phase 3, left null on phases 1 and 4. This is what marks a phase as "regional" and which region-group namespace it reads from.
3. **`FestRegionPartitionService::schoolRegion()` takes an optional `?string $partitionGroup`** parameter — resolves the group-scoped assignment when given one, falls back to the legacy Sahodaya-wide row when not. `syncPartitionsFromRegions()` gains an equivalent per-group variant, called once per regional phase rather than once per hub.
4. **School-facing region picker — genuinely independent per phase, confirmed 2026-08-15.** A school's Off Stage region and Sargadhara region don't need to match physically — the school picks and saves a region per regional phase group, and registers under whichever region they picked for that group. Today's picker (one region, annual registration) becomes one picker per regional phase group once more than one group exists on an event ("Choose your Off Stage region" / "Choose your Sargadhara region"), reusing the existing region-picker UI component, looped per group rather than a single shared choice.
5. **Item sync respects the phase's group** — `FestItemSyncService::copyItemsToPartition()` already filters by `partition_role` when copying hub items into a region child; extend it to also filter by the owning phase's `phase_id` so an Off Stage region child only receives Off Stage items, not Sargadhara items, even though both are "region" children of the same hub.

This is additive to §3's phase-billing design — a phase can be regional (§7.3) and separately own the school registration fee (§3.4) independently of each other.

### 7.3a Cumulative overall result across phases (new, clarified 2026-08-15)

Confirmed design for results: **each phase publishes its own independent result** (phase 1's Digi Fest standing, phase 2's combined Off Stage standing, phase 3's per-region Sargadhara standings, phase 4's common-items standing) — and separately, the **public/overall page shows a running total per school, adding each phase's points on top of the last** as phases complete: after phase 1 publishes, the overall board shows phase 1 points only; once phase 2 publishes, overall = phase 1 + phase 2; then + phase 3; then + phase 4 for the final standing.

This is a **new aggregation axis, not the same mechanism as §7.1's `combinedScoreboard()`.** That method sums a school's points across sibling *region-partition* events (same phase, different regions). What's needed here sums a school's points across *phases* of the same event — including phases that were never partitioned at all (phase 1, phase 4) and phases that were (phase 2, phase 3, where the phase's own contribution to the overall is that school's total points for that phase regardless of which region they earned them in — the region only matters for phase 2/3's own internal standings, not for what feeds the overall total).

Concretely this needs a new `FestPhaseScoreboardService` (or a phase-aware extension of `EventContext`) with two responsibilities:
- **Per-phase scoreboard:** a school's points for items where `item.phase_id = X`, computed from the hub directly for non-regional phases, or summed across that phase's region-partition children for regional phases (reusing `combinedScoreboard()`'s aggregation for the regional case, filtered to that phase's `cluster_key` set instead of "all regions").
- **Cumulative overall:** for each school, sum of per-phase totals across every phase whose results are published so far, recomputed/revealed progressively as each phase publishes (not held back until all 4 are done) — mirrors how `results_published` already gates visibility per phase (`FestEventPhase.results_published`, already in the model per §1) but needs a new "sum what's published so far" read, not just a boolean gate.

This is genuinely new work, not a configuration of something existing — added to §7.5's estimate below.

### 7.4 Fee model generalization

Two distinct, already-partially-supported pieces:

- **3+ tier school-type registration fee, tier derived from classes actually opted — not a manually-set tag.** **Refined 2026-08-15:** rather than relying on a school's manually-set `institution_level` field (whose exact value set was an open question), the tier should be *derived* from which classes the school has actually registered/opted to run. `SchoolClass` (`app/Models/SchoolClass.php`) already records exactly this — each school's own active class list (`is_active`, tied to `class_category_id` → `ClassCategory`), and `FestClassGroupScheme::KEYS = ['lp', 'up', 'hs', 'hss', 'open']` already gives a ready-made ordered hierarchy (lower primary → upper primary → high school → higher secondary). Design: resolve a school's fee tier as the *highest* `ClassCategory` among its active `SchoolClass` rows (e.g. any school with an active `hss`-category class → "Senior Secondary" tier; highest is `hs` → "Secondary"; below that → "Other"), computed automatically rather than requiring a Sahodaya to manually tag every school. `schoolRegistrationAmount()` and `normalizeSchoolRegistration()` (currently hard-limited to `secondary`/`senior_secondary` keys) both need generalizing to key off this derived tier and accept any number of tiers, not two.
- **Per-student-count fee slabs — configured per Sahodaya, per event type.** **Confirmed 2026-08-15.** Nothing today prices a school based on *how many students it's fielding in total* (e.g. "0–20 students: ₹X, 21–50: ₹Y") — the existing `per_student` model is flat rate × count, not stepped. New `fee_model` value (e.g. `student_count_slab`) with a slab table (`min_count`, `max_count`, `amount`). Per the user's answer, this is custom per Sahodaya *and* per event type, not a shared platform table — which is actually the existing architecture already: every fee model's settings already live in `fee_settings` on the individual `FestEvent`, so a slab table defined there is automatically scoped to one Sahodaya's one event type with zero extra plumbing. No new "global defaults" table needed; a sensible starting slab can still ship as a suggested default the admin can override, same as other fee models already do.
- **Sahodaya membership renewal fee (₹5000 in the example image) is a separate, already-built subsystem** — `MembershipPayment`/`MembershipFeeSlab` handle Sahodaya membership dues independently of any fest event. It isn't a Kalotsavam fee at all; some Sahodayas just choose to collect it in the same registration cycle as their Kalotsavam fee. No change needed here — worth noting only so it doesn't get accidentally folded into the Kalotsavam fee engine.
- **Flat event fee + per-participant surcharge for group items — confirmed genuine gap, standby inclusion is a per-Sahodaya rule.** CBSE Kannur Dist Kalotsav's rules: individual items are ₹250/participant (mathematically identical to today's flat per-item fee, since one individual registration = one participant — already supported, no change needed); group items are ₹250 flat "event fee" **plus** ₹100 × actual member count (a 7-member group costs ₹250 + 700 = ₹950). Checked both places a per-team amount is computed — `FestItemFeeResolver::amountForItem()` (Kalotsavam) returns `team_registration_fee`/`default_item_fee` as one static number per registration with no participant count involved, and `FestSportsCompositeFeeService` (sports) does the same — "Team items: team_registration_fee once per team" (its own doc comment), no per-member multiplication anywhere. `FestGroup` has a `participants()` relation but nothing today reads its count for billing. Needs a new per-item fee shape: `group_item_flat_fee + group_item_per_participant_rate × actual FestGroup::participants()->count()`, resolved wherever `amountForItem()` currently returns a single static team/group amount. **Confirmed 2026-08-15:** whether standby participants count toward that per-participant charge is itself a per-Sahodaya setting — reuse the existing `charge_standbys` toggle pattern (already used elsewhere in the fee engine) rather than a fixed platform-wide rule, so each Sahodaya can turn standby-counting on or off for its own group items.

### 7.5 Revised rollout & estimate

This addendum is substantially larger than the original two-level plan — it adds a second, independent axis (per-phase regions) on top of per-phase billing, plus fee-engine generalization. Rough additional estimate on top of §4's ~6.5 days:

| Phase | Work | Est. |
|---|---|---|
| F — Region-per-group data model | `partition_group` column + `region_partition_group` on phases; service param threading | 1 day |
| G — Per-group sync & item routing | Per-group `syncPartitionsFromRegions()` variant; phase-aware `copyItemsToPartition()` | 2 days |
| H — School region-picker UI | Multi-group region picker (annual registration + event registration screens) | 1.5 days |
| I — Fee tier generalization | N-tier `school_registration` map (not hard-coded to 2 keys) + admin UI rows | 1 day |
| J — Student-count slab fee model | New `fee_model`, slab table UI, resolver branch | 1.5 days |
| L — Per-participant group-item surcharge | `group_item_per_participant_rate` field on item/head/schedule; `amountForItem()` and `FestSportsCompositeFeeService`'s team-fee branch both read actual `FestGroup::participants()->count()` instead of a static amount; admin UI field | 1 day |
| M — Cumulative overall scoreboard (§7.3a) | New `FestPhaseScoreboardService`: per-phase scoreboard (regional-aware) + progressive cumulative overall total as phases publish; public page updates | 2 days |
| K — UAT | Two regional phases with different, overlapping-but-distinct region sets for the same school; confirm combined vs. per-region scoreboards match §7.1's toggle; confirm non-regional Sahodayas unaffected; confirm a 7-member group item bills ₹250 + (₹100×7) exactly; confirm overall total after phase 2 publish = phase1+phase2 only, then grows correctly as phase 3/4 publish | 1.5 days |

**Addendum total: ~11.5 developer-days, on top of the ~6.5 days in §4 — roughly 18 days combined.** Given Level 1 closes 31/08, this addendum's scope (region-per-phase, new fee models, cumulative scoreboard) is realistically a post-deadline build; §5's interim step (configure phases for windows only, bill manually/combined for now) is the load-bearing recommendation for this year's Level 1 in particular.

### 7.6 Open questions (addendum)

1. ~~Confirm the actual set of `institution_level` values this platform uses today before designing the N-tier fee UI.~~ **Answered/resolved differently 2026-08-15: don't use the manual tag at all — derive the tier from the classes the school has actually opted to run**, via `SchoolClass`/`ClassCategory` (see revised §7.4 bullet above). This sidesteps the open question entirely rather than answering it — no need to enumerate `institution_level` values since the field stops being the source of truth.
2. ~~For phase 2 (Off Stage), is "combined result" a full point-sum across regions, or does the school's Off Stage rank need to be recomputed as if all regions were one pool (re-ranked)?~~ **Answered 2026-08-15: point-sum, not re-ranking** — confirmed each phase publishes its own result, and the overall/public total is each phase's points added cumulatively on top of the last (phase 1, then +2, +3, +4). This is the existing "sum points" shape, but applied across *phases* rather than regions — see new §7.3a for the design this actually requires (a new cumulative-scoreboard service, not a reuse of `combinedScoreboard()` as-is).
3. ~~Should a school be allowed to sit in *different* regions for phase 2 vs phase 3, or should the platform enforce the same physical region across both?~~ **Answered 2026-08-15: genuinely different regions allowed — physical region doesn't need to match.** School picks and saves independently per regional phase group; §7.3 item 4 updated.
4. ~~Does the student-count slab model need slabs configurable per Sahodaya or is a platform-wide default acceptable?~~ **Answered 2026-08-15: per Sahodaya, per event type** — matches the existing `fee_settings`-per-event architecture, no new global table (see revised §7.4 bullet).
5. ~~Should standby participants count toward the group-item per-participant surcharge?~~ **Answered 2026-08-15: also a per-Sahodaya rule**, reusing the existing `charge_standbys` toggle pattern rather than a fixed platform-wide answer (see revised §7.4 bullet).

All five addendum open questions are now resolved. Remaining open items are just §6's item 2 (confirmed, no action needed) and item 3 (resolved via §3.4's revision) — this plan has no unanswered open questions left as of 2026-08-15.

## 8. Implementation status (2026-08-15)

Phases A–D of §4's core rollout are code-complete and committed to this repo. Nothing in this section has been run against a live database or exercised through a browser — no PHP/artisan runtime or DB is available in the build sandbox, so everything below is syntax-checked (`php -l` on every changed PHP file; brace/paren balance checks on the Vue files) but functionally unverified. Treat this as "ready for review and a real dev-environment test pass," not "ready to deploy."

**What shipped:**

- **Phase A (data model).** Two new tenant migrations: `phase_id` nullable FK on `fest_school_event_fees` (mirrors the existing `head_id` column, with a composite unique on `(event_id, school_id, head_id, phase_id)`), and `school_registration_fee_share` nullable decimal(10,2) on `fest_event_phases`. `FestSchoolEventFee` and `FestEventPhase` models updated (`$fillable`, `$casts`, the new `phase()` relation, and `scopeForAmountAggregation()`/`withoutDuplicateRollups()` generalized to treat `phase_id` the same way they already treat `head_id` so rollup rows don't double-count).
- **Phase B (billing service).** `FestSchoolEventFeeService` gained `usesPerPhaseBilling()`, `phasesWithActivityForSchool()`, `recalculateForPhase()`, `recalculateAllPhasesForSchool()`, `isPhasePaid()`, `attachPaymentForPhase()`, and a private `recalculateAggregateForPerPhaseEvent()` rollup — all mirroring the existing sports per-head trio. `billableItemCount()`/`billableStudentCount()` gained an optional `?int $phaseId` filter. `FestItemFeeResolver::billableRegistrations()`/`participationBreakdown()`/`participationTotal()` gained the same optional phase filter, including for standby participants.
- **Phase C (admin UI).** `Phases.vue`'s add/edit phase form now has `registration_open`/`registration_close` datetime inputs and a `school_registration_fee_share` amount field, plus a soft warning banner when the sum of phase shares doesn't match the event's nominal `school_registration_fee` (not a hard block — a Sahodaya may deliberately want them not to match, e.g. an early-payment discount). `FestEventPhaseController` (`store()`/`update()`) and `FestEventPhaseService` (`createPhase()`/`updatePhase()`) updated to accept and persist the new fields.
- **Phase D (school UI).** New `resources/js/Components/school/PhaseBillingInvoices.vue`, mirroring the existing `HeadBillingInvoices.vue` — one payable card per phase, its own breakdown, status badge, and upload form. Wired into `EventBillingPanel.vue` as a third branch (per-head / per-phase / single-invoice) and into `Registration.vue` (new `phasePayment*` reactive state, `uploadPhasePayment()`/`setPhasePaymentFile()`, new props/emits threaded through). `FestRegistrationController::hydrateEventForSchoolRegistration()` now hydrates `uses_per_phase_billing`/`school_phase_fees`; `uploadEventPayment()` accepts an optional (required-if-per-phase) `phase_id` and calls `attachPaymentForPhase()`; `feeReceipt()` branches on `phase_id` the same way it already does on `head_id`. No new route — phase payments reuse the existing `POST .../events/{event}/payment` endpoint, same as per-head payments do today.

**What's still open / not started:**

- **Phase E (UAT)** — not started; needs a real dev environment with a database to run migrations and exercise the actual flow end to end.
- **The entire §7 addendum** — region-per-phase (§7.3, items F–H), fee tier/slab generalization (§7.4, items I–J), the group-item per-participant surcharge (§7.4, item L), and the cumulative overall scoreboard (§7.3a, item M) are all still just design, not code.
- **Secondary decisions raised after this plan was finalized, not yet answered by the user or reflected in any code:** state-level qualification remapping against the new phase model (`FestQualificationService` hasn't been touched or reconciled with phases at all); the region-migration story for MCS's *existing* Tirur/Manjeri `SchoolRegionAssignment` data once/if §7.3's per-group regions ship; whether an item can be reassigned to a different phase mid-registration (and what happens to any phase-scoped fee/payment already on it if so); how appeal timing interacts with the cumulative overall scoreboard (e.g. does a phase-3 appeal reopen the already-published cumulative total for phases 1–3, or only phase 3's own standing); and a scheduling/priority question about which of the addendum items (F–M) to build first once work resumes. None of these are blocking Phases A–D as built, but all of them are blocking before §7 addendum work starts.
