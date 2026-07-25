# Kalotsav — Consolidated Implementation Plan

**Status:** Ready to execute. Supersedes the architecture section of `MCS_KALOTSAV_IMPLEMENTATION_PLAN.md` (§3–3.1) with what actually shipped since that doc was written; keeps that doc's business-rule content (catalog, scoring, forms, state promotion) as the requirements source.
**Prepared:** 25 Jul 2026
**Companion reading:** [`REGION_AND_PHASE_KALOTSAV_PLAN.md`](REGION_AND_PHASE_KALOTSAV_PLAN.md) (gap analysis this plan executes), [`MCS_KALOTSAV_IMPLEMENTATION_PLAN.md`](MCS_KALOTSAV_IMPLEMENTATION_PLAN.md) (MCS business rules — architecture parts now stale), [`erp/11-KALOTSAVAM.md`](erp/11-KALOTSAVAM.md)

---

## 0. Where Kalotsav actually stands today (verified against code, not the old plan)

**Single-Sahodaya Kalotsav already works end-to-end.** Per the school-admin journey audit, Kalotsav is a byte-identical-to-complete fest program: registration → mark entry → results → certificates all function today for any Sahodaya running it as one event (`conduct_mode = standard`). Nothing in this plan is needed to make basic Kalotsav usable.

**What's actually missing is multi-region/finale conduct** — the MCS pattern (Tirur + Manjeri regions, then a district finale that sums points) — made available to *any* Sahodaya, not hand-built once for MCS. That gap is real and is the entire subject of this plan.

**Correction to the old MCS plan's architecture (§3 of `MCS_KALOTSAV_IMPLEMENTATION_PLAN.md`):** that doc proposed a new `FestRegionalClusterService`, `FestSchoolRegionService`, and `event_segment`/`region_key` columns. None of that was built. Instead, a more general engine shipped and is already live in production for Kids Fest clusters:

| Piece (already shipped, verified in code) | File |
|---|---|
| `conduct_mode` (`standard`\|`partitioned`) + `partition_role` (`region`\|`finale`\|`cluster`\|`digi_fest`) on `fest_events` | `app/Models/FestEvent.php` |
| `FestPartitionService` — spawns partitions, computes `combinedScoreboard()` | `app/Services/Events/FestPartitionService.php` |
| `Region` + `SchoolRegionAssignment` (Sahodaya-defined regions, per academic year) | `app/Models/Region.php`, `SchoolRegionAssignment.php` |
| `RegionController` — Sahodaya admin UI to create regions, assign schools | `app/Http/Controllers/SahodayaAdmin/RegionController.php` |
| `FestRegionPartitionService::syncPartitionsFromRegions()` — one click: spawns one child event per `Region`, bulk-assigns schools | `app/Services/Events/FestRegionPartitionService.php` |
| `FestRegistrationRouterService` — routes a school's registration to the correct region child event | `app/Services/Events/FestRegistrationRouterService.php` |
| Per-event admin scoping via `FestEventStaff` (`duty=event_admin`) | existing |

So **do not build a parallel region engine.** Every phase below extends this existing engine rather than replacing it.

**Confirmed still missing (verified by grep, not assumed):**

| Gap | Evidence |
|---|---|
| Region-wise conduct is hardcoded to MCS | `FestEventController::applyConductPreset()` still validates `'preset' => 'required|string|in:mcs_kalotsav'` — no other Sahodaya can self-serve this |
| No "region result is final, don't combine" switch | No `combine_regions_at_finale` column anywhere in migrations/models |
| Phases don't exist | No `fest_event_phases` table, no `phase_id` on `fest_event_items` |
| Regional winners don't auto-advance to the finale for re-competed items | `FestQualificationService` has `promoteWinners()` (level-to-level) and `promoteAllSchoolRounds()`, but no `promoteRegionalWinnersToFinale()`/`promoteRegionalWinnersToState()` — siblings at the same level aren't handled |
| Region admin isn't a discoverable role | No `region_admin` entry in `TenantUserCatalog::festEventDuties()` |
| MCS catalog is a thin wrapper, not the manual's real data | `app/Support/data/mcs_kalotsav_items.php` just re-maps `cksc_kalolsav_items.php` with a few JSON flags merged in — it is **not** the manual's actual 101–513 item list, criteria rubrics, or group-size rules |
| Combo OR logic (2 off + 3 on) OR (3 off + 2 on) unsupported | No `combo_profiles` concept in `FestComboRuleService` |
| MCS forms (Appendix I–IV, tabulation sheet) don't exist | Only `resources/views/fest/reports/judge-sheet.blade.php` exists; no judge-declaration, judge-feedback, appeal-proforma, appeal-order, or tabulation-sheet templates |
| State-promotion decline/substitution/consent rules unimplemented | No `declined_state`/`consent_letter_uploaded` handling found |
| ~~Participation limits (on-stage/off-stage/team, per-student) not settable at event creation~~ | **Closed 25 Jul 2026** — see Phase 5.5. The underlying `FestParticipationPolicy` system already existed; it just wasn't exposed until event setup was underway. |

This list **is** the real scope of "implementing Kalotsav" from here. It's smaller than the old MCS plan implied (a lot of infrastructure already exists) but the business-rule layer (catalog accuracy, forms, promotion rules) is larger than the old plan's Phase 2/5 suggested, since those files exist but are stubs.

---

## 1. Design principle

Build this **generically first, prove it with MCS second** — the opposite order of the original 2026 MCS plan, which risked baking MCS-only assumptions into core services. Every schema/service change below is a platform capability (any Sahodaya can go region-wise); MCS is the pilot tenant that exercises it, not a special code path.

---

## 2. Phased plan

### Phase 0 — Confirm scope with MCS committee (parallel, no dev blocked)

| Task | Output |
|---|---|
| Confirm Tirur + Manjeri two-region model vs. the manual's 3 off-stage venues (Nilambur, Perinthalmanna as sub-venues under a region, or real 3rd/4th region) | Written decision |
| Confirm school→region assignment rule: fixed by school address, or per-registration choice | Rule doc |
| Confirm whether district-main items re-compete regional winners (needs Phase 3 promotion) or district is purely separate group/costume items scored independently | Decision — gates whether Phase 3 below is needed at all |
| Freeze the real MCS item list (101–513), criteria rubrics, group sizes from the manual | Signed-off CSV — replaces the current stub in `mcs_kalotsav_items.php` |

**Exit:** signed item CSV + region rule + promotion-model decision.

---

### Phase 1 — Self-service region-wise conduct (closes Gap A)

Makes "go region-wise" a UI toggle for any Sahodaya, using only what already exists.

| Change | File |
|---|---|
| Add a "Conduct topology" step to event create/edit — Single (default) vs. Region-wise | New UI in event wizard / `Levels.vue` |
| Region-wise choice calls existing `FestRegionPartitionService::syncPartitionsFromRegions()` | Reused as-is |
| Remove/relax the `in:mcs_kalotsav` lock on `applyConductPreset` (or deprecate that endpoint in favor of the generic sync-from-regions flow) | `app/Http/Controllers/SahodayaAdmin/FestEventController.php:565` |
| "Sync regions from Sahodaya" button + region partition list on the hub event | `Levels.vue` (or equivalent) |

**Exit:** any Sahodaya (test with one that isn't MCS) can turn a Kalotsav event region-wise from the UI alone, no code deploy.

---

### Phase 2 — "Region result is final" vs. "combine at finale" (closes Gap B)

| Change | Detail |
|---|---|
| New column `combine_regions_at_finale BOOLEAN DEFAULT true` on `fest_events` | Backfill `true` for any event already `partitioned` today — **this is the critical safety step**: `EventContext::scoreboardBySchool()` (`app/Services/Events/EventContext.php:172-177`) calls `FestPartitionService::combinedScoreboard()` for *any* partitioned hub unconditionally today, no gate at all, and Kids Fest clusters depend on that. Defaulting anything else would silently remove Kids Fest's "Overall" tab on deploy. |
| `FestPartitionService::combinedScoreboard()` and its callers gate on this flag | `app/Services/Events/FestPartitionService.php` |
| Sub-toggle in the Phase 1 wizard: "Combine regions into an overall result at a finale?" | Same UI as Phase 1 |
| `LeaderboardHub.vue`: hide "Overall" tab when `false`; show Overall + region tabs + finale tab when `true` | `resources/js/Pages/Admin/Sahodaya/Events/LeaderboardHub.vue` |

**Exit:** an event configured `combine_regions_at_finale = false` shows only region tabs, no Overall tab, and nothing about existing Kids Fest/MCS boards changes.

---

### Phase 3 — Promote regional winners into the finale (closes Gap C — only if Phase 0 confirms district re-competes winners)

| Change | Detail |
|---|---|
| `FestQualificationService::promoteRegionalWinnersToFinale(FestEvent $hub, FestEvent $finaleEvent, array $options = [])` | New method, same vocabulary as existing `promoteWinners()` — per item, top-N per region (respecting `qualify_count`/`tiebreak_mode`), creates registrations in the finale child |
| "Promote regional winners to finale" button on the hub's Levels page | Same UX as existing `spawnSchoolRounds` promotion buttons |
| State-level promotion rules specific to MCS (1st per region promotes to CBSE state, 2nd if 1st declines + consent letter; district 1st & 2nd; MCS-only items never promote; English One Act Play — only 1st) | New `promoteRegionalWinnersToState()` variant or option flags on the above; `declined_state`/`consent_letter_uploaded` tracked on `FestParticipant.meta` |
| 24h paid substitution (₹500 + affidavit) | Extend `FestSubstitutionRequest` |

**Exit:** promotion sheet lists correct state qualifiers per the manual's rules; skip this phase entirely if district items don't re-compete winners.

---

### Phase 4 — Phases (Digi Fest day / Off-stage / On-stage) (closes Gap D)

Independent of region work — ship whenever convenient, useful even for single-venue Sahodayas.

| Change | Detail |
|---|---|
| New table `fest_event_phases` (`event_id, name, code, sort_order, is_default`) | New migration |
| `phase_id` nullable FK on `fest_event_items` | Same migration |
| `FestEventPhaseService` — CRUD phases, assign items, toggle phase mode on/off per event | New service |
| Items list: phase filter + bulk "assign phase" action, shown only once phase mode is on | Frontend |
| Default state: `phase_id = null` for everything = "the only phase" — zero behavior change until an admin turns phase mode on | — |

**Exit:** an admin can define "Digi Fest Day" / "Off-stage" / "On-stage" and filter the item list by phase; no existing event's behavior changes.

---

### Phase 5 — Region admin, made discoverable (closes Gap E)

| Change | Detail |
|---|---|
| Add `region_admin` label to `TenantUserCatalog::festEventDuties()`, internally still `FestEventStaff::duty = 'event_admin'` scoped to the region child event's ID | Labeling only, no permission change |
| "Region Admins" panel on the hub event listing each region partition with inline "assign admin" | Wraps existing `FestEventStaffController` store logic |

**Exit:** a Sahodaya admin can assign a region coordinator without opening the region's child event directly.

---

### Phase 5.5 — Participation limits exposed at event creation (done 25 Jul 2026, corrected same day)

Discovered while scoping Phase 6: per-student on-stage/off-stage/team-item caps already existed as a full generic system (`FestParticipationPolicy`, keyed by `event_id` + `class_group`, enforced by `FestParticipationLimitService`/`FestComboRuleService`) — but only configurable after the fact via the Settings → Participation tab, not at event creation.

**First pass (wrong):** added `max_total_per_student`/`max_onstage_per_student`/`max_offstage_per_student`/`max_group_per_student` fields to `FestEventController::store()` and the `ProgramIndex.vue` create-event panel. This is dead code for Kalotsav and English Fest specifically — both are `is_singleton = true`, and `FestEventController::programIndex()` auto-creates their one hub event via `FestPrimaryEventResolver::resolveOrCreate()` and redirects a regular (non-staff) admin straight to it, **before the create form ever renders**. There is no "creation moment" UI for singleton programs — the program IS the event, created silently on first visit.

**Fix:** `programIndex()` now checks `$event->wasRecentlyCreated` after `resolveOrCreate()`. On a brand-new hub event, it redirects to `/events/{id}/settings/participation` (with a flash message) instead of the plain Overview page — landing the admin exactly where the limits can be set, immediately after the event exists. That Settings → Participation tab (`ParticipationTab.vue` / `useEventSettingsForms.js` / `FestParticipationPolicyController`) already had `max_onstage_per_student`/`max_offstage_per_student`/`max_group_per_student` wired end to end but was **missing the `max_total_per_student` field entirely** — added it, plus the same "breakdown can't exceed total" client-side check used in the create-form version.

The `ProgramIndex.vue` create-form fields from the first pass are kept — they're not wasted, since two competition types (`sports`, `custom`) are `is_singleton = false` and do use that literal create form.

Net result: for Kalotsav and English Fest, the reachable path is Settings → Participation (now with all four fields), reached automatically right after first creation. Confirmed both programs share the exact same component and controller, so "English Fest same configs" holds.

Not yet done (future, only if needed): per-class-category (LP/UP/HS/HSS) variation — current requirement was confirmed as one uniform set of numbers for all categories, so `class_group` is left `null`. The Settings → Participation tab's backend already supports per-`class_group` rows; only its UI would need a second per-category input mode if that's ever needed.

---

### Phase 6 — MCS business-rule layer (real data, not the CKSC stub)

This is where the "implement Kalotsav for MCS" work actually is, once Phases 1–5 give it a home.

| Task | File |
|---|---|
| Replace the CKSC-remap stub with the manual's real 101–513 item list, criteria rubrics (value-point tables per item), group min/max/standbys | `app/Support/data/mcs_kalotsav_items.php` (rewritten, not wrapped) |
| Grade bands (A/B/C, no A+) and point matrices — already scaffolded, verify against manual's worked examples | `config/fest_mcs_scoring.php` (exists, needs verification against Phase 0 signed CSV) |
| Combo OR logic: (2 off-stage + 3 on-stage) OR (3 off-stage + 2 on-stage), max 2 group items/student | New `combo_profiles` concept in `FestComboRuleService` |
| Fee schedule: ₹4000/school + ₹400 first item + ₹50 additional | Already in `fest_mcs_scoring.php`, verify against Phase 0 sign-off |

**Exit:** MCS catalog import produces the manual's actual item set with correct criteria, grading matches worked examples, registration enforces the OR combo rule.

---

### Phase 7 — Forms & downloadable sheets

| Manual appendix | New template |
|---|---|
| Appendix I — Judge declaration | `resources/views/fest/forms/judge-declaration.blade.php` |
| Appendix II — Judge feedback | `resources/views/fest/forms/judge-feedback.blade.php` |
| Appendix III — Appeal proforma | `resources/views/fest/forms/appeal-proforma.blade.php` (pre-filled from appeal record) |
| Appendix IV — Appeal order | `resources/views/fest/forms/appeal-order.blade.php` (generated on appeal resolve) |
| Tabulation sheet (grade × place × points matrix) | `resources/views/fest/reports/tabulation-sheet.blade.php` — new, `judge-sheet.blade.php` already exists as a pattern to follow |
| Custody register (who received marksheets/tabulation/results, signature log) | New report |

**Exit:** committee can print all day-of forms from the Sahodaya admin without falling back to Excel.

---

### Phase 8 — UAT & go-live

| # | Scenario |
|---|---|
| 1 | Non-MCS Sahodaya turns on region-wise conduct purely via UI — proves Gap A is generic, not MCS-only |
| 2 | Region-wise event with `combine_regions_at_finale = false` — leaderboard shows only region tabs (English Fest case, proves Gap B) |
| 3 | School in Tirur registers 3 off-stage + 2 on-stage — OR combo validated |
| 4 | Same item run in Tirur and Manjeri — separate winners, separate boards, no cross-contamination |
| 5 | District group item points flow into the umbrella combined scoreboard correctly |
| 6 | Overall champion = Tirur points + Manjeri points + District points, matches manual worked example |
| 7 | MCS-only item — confirmed no state promotion |
| 8 | Regression: existing Kids Fest umbrella shows identical Overall tab/totals before and after the `combine_regions_at_finale` migration — **this is the one existing live consumer of the partitioned-hub scoreboard and must not regress** |
| 9 | Appeal within 1 hour window, fee recorded, Appendix III/IV generate correctly |
| 10 | Judge sheet PDF shows correct criteria for a sample item |

---

## 3. Rollout order & sequencing

```
Phase 0 (committee) ──┐
                       ├─→ Phase 1 (self-service region toggle) ─┬─→ Phase 2 (combine/no-combine switch) ─┬─→ Phase 8 (UAT)
Phase 4 (phases) ──────┘  [independent, ship any time]            │                                          │
Phase 5 (region admin) ───────────────────────────────────────────┘                                          │
Phase 3 (promote-to-finale) — only if Phase 0 confirms re-competition ────────────────────────────────────────┤
Phase 6 (MCS real catalog/scoring/combo) — can start in parallel once Phase 0 CSV is signed ──────────────────┤
Phase 7 (forms) — depends on Phase 6 data (criteria_json) for judge sheets ────────────────────────────────────┘
```

Phases 1, 2, 4, 5 are backend/platform work, additive and off-by-default — nothing existing changes behavior until an admin opts in (see §4 of `REGION_AND_PHASE_KALOTSAV_PLAN.md` for the full regression argument, particularly the `combine_regions_at_finale` default-`true` requirement).

Phases 3, 6, 7 are the MCS-specific business layer and can run in parallel with 1/2/4/5 once Phase 0 sign-off lands, since they touch data files and Blade templates, not the shared partition engine.

---

## 4. Suggested sprint breakdown

| Sprint | Content | Depends on |
|---|---|---|
| S1 | Phase 0 (parallel) + Phase 1 | — |
| S2 | Phase 2 + Phase 4 (independent, can run alongside) | S1 |
| S3 | Phase 5 + start Phase 6 (catalog rebuild) | S1 (Phase 0 CSV) |
| S4 | Finish Phase 6 + Phase 3 (if needed per Phase 0 decision) | S3 |
| S5 | Phase 7 (forms) | S4 (needs criteria_json) |
| S6 | Phase 8 UAT + go-live, including the Kids Fest regression check | All above |

**Estimate:** ~6 sprints with 1 backend + 1 frontend developer — similar total effort to the original MCS-only estimate, but now delivers a platform capability every Sahodaya can use, not a one-off.

---

## 5. Risks & mitigations

| Risk | Mitigation |
|---|---|
| `combine_regions_at_finale` migration silently breaks live Kids Fest "Overall" tab | Default `true`, backfill `true` for every already-partitioned hub; regression-test Kids Fest specifically before shipping Phase 2 (it's the only *currently live* consumer — MCS Kalotsav events are dated Sep 2026 and aren't live yet) |
| Old MCS plan's stale architecture gets rebuilt by mistake (`FestRegionalClusterService`, `event_segment`) | This doc's §0 correction is the source of truth going forward — don't resurrect that design |
| MCS catalog stub (`mcs_kalotsav_items.php`) mistaken for "done" | It's a CKSC remap, not manual data — Phase 6 rewrites it; don't treat its existence as Phase 2 of the old plan being complete |
| Manual lists 3 off-stage venues, committee wants 2 regions | Phase 0 decision; Nilambur/Perinthalmanna can be sub-venues under Manjeri if confirmed |
| Region admin permission scope ambiguous (full Sahodaya-admin subset scoped to region, or narrower) | Confirm in Phase 0 or Phase 5 kickoff — affects whether Phase 5 needs additional permission-gating beyond duty labeling |

---

## 6. Immediate next steps

1. Run Phase 0 with the MCS committee — region model, promotion-model decision (does district re-compete winners?), and the real item CSV.
2. Start Phase 1 in parallel — it needs no MCS input, it's pure platform self-service work on the engine that already exists.
3. Do **not** start building `FestRegionalClusterService`/`FestSchoolRegionService`/`event_segment` — that path is superseded; extend `FestPartitionService`/`FestRegionPartitionService` instead.
4. Once Phase 0's CSV lands, start Phase 6 (real catalog) in parallel with Phase 2/4/5.

---

*Prepared 25 Jul 2026. Supersedes the architecture (not the business rules) of `MCS_KALOTSAV_IMPLEMENTATION_PLAN.md`.*
