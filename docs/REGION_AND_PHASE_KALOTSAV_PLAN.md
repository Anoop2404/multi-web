# Region-wise, Phase-wise Kalolsavam Conduct — Analysis & Implementation Plan

**Status:** Analysis only — no code changed.
**Prepared:** 22 Jul 2026
**Companion reading:** [`docs/FEST_CONDUCT_TOPOLOGY.md`](../docs/FEST_CONDUCT_TOPOLOGY.md), [`docs/MCS_KALOTSAV_IMPLEMENTATION_PLAN.md`](../docs/MCS_KALOTSAV_IMPLEMENTATION_PLAN.md)

---

## 0. Requirement, restated

1. A Sahodaya should be able to conduct a fest **region-wise** — each region (within that Sahodaya) runs its own competitions and gets its own result.
2. Optionally, region results **combine at the Sahodaya/district ("state") finale**, and the finale decides the overall winner. Some events (e.g. English Fest) should **not** go through that combine step — the region result is final, full stop.
3. Every event should support **phase-wise conduct**: default is a single implicit "Phase 1" (i.e. no phase split at all), but an admin can turn on phase mode and assign items to named phases (e.g. Digi Fest day, Off-stage day, On-stage day).
4. Region-wise events need a **region-level admin** who can manage only their region's slice of the event.

**Important terminology note:** in this codebase "state" already means something specific — the CBSE/government state level (`level_round = state`, `FestStateProgram`, `StateAdmin/*`), which is a separate national-level tier above Sahodaya. What you're describing ("combine regions → pick a winner at state for kalolsavam") is **not** that — it's the **Sahodaya's own district/final round**, which combines its internal regions. I've written this plan against that reading. If any event genuinely also needs to promote further up to the real CBSE state level, that's the existing, separate `FestQualificationService::promoteWinners()` path and isn't affected by this plan.

---

## 1. What already exists (don't rebuild this)

This is further along than it looks. A generalized "one engine, configurable topology" system for exactly this region/finale pattern already shipped, built for one Sahodaya (MCS) but designed to generalize:

| Piece | File | What it does |
|---|---|---|
| `conduct_mode` (`standard`\|`partitioned`) on `fest_events` | `app/Models/FestEvent.php` | Switches an event from one scoreboard to child-event partitions |
| `partition_role` (`region`\|`finale`\|`cluster`\|`digi_fest`) + `partition_key` | `fest_events` table | Labels each child event: is it a region, or the combining finale? |
| `FestPartitionService` | `app/Services/Events/FestPartitionService.php` | `spawnPartition()`, `partitions()`, `combinedScoreboard()`, `aggregationConfig()` — creates region/finale child events and sums school points across whichever `include_roles` are configured |
| `Region` + `SchoolRegionAssignment` | `app/Models/Region.php`, `SchoolRegionAssignment.php` | Sahodaya-defined regions (name, code, sort order) and which school belongs to which region, per academic year |
| `RegionController` (Sahodaya admin) | `app/Http/Controllers/SahodayaAdmin/RegionController.php` | Existing UI to create regions and assign schools to them |
| `FestRegionPartitionService::syncPartitionsFromRegions()` | `app/Services/Events/FestRegionPartitionService.php` | **This is the bridge you're asking for.** One click: reads the Sahodaya's `Region` records, spawns one child `FestEvent` per region (`partition_role=region`), and bulk-assigns every school to its region's partition. Registrations then route to the correct region child automatically. |
| `fest_event_school_partitions` | migration + `FestEventSchoolPartition` model | Which school competes in which partition for a given event |
| `aggregation_config` JSON on the hub event | `fest_events.aggregation_config` | `include_roles` controls which child partitions (region/finale/cluster) feed the combined scoreboard |
| Per-event admin scoping | `EnsureSahodayaAdmin` + `FestEventStaff` (`duty='event_admin'`) | A user with role `event_admin`, assigned via `FestEventStaff` to a **specific `event_id`**, gets a full admin experience locked to that one event and nothing else |

So: "conduct region-wise, then combine at a finale" is a real, working pattern today — it's literally how MCS's Tirur/Manjeri/District Kalotsav runs. And "region-scoped admin" is also already solvable with existing tables, because a region is a real `FestEvent` row and `FestEventStaff` already scopes admins to one event ID.

**What's missing is making these self-service for *any* Sahodaya**, not just MCS, plus two things that don't exist at all yet: the "don't combine, region result stands alone" toggle, and phases.

---

## 2. Gap analysis — what actually needs to be built

### Gap A — Region-wise is currently a manual/hardcoded flow, not a self-service toggle

Today, going region-wise requires a Sahodaya admin to know to call `syncRegionPartitions` on an event, and `applyConductPreset` is hard-locked to one preset name:

```php
// app/Http/Controllers/SahodayaAdmin/FestEventController.php
$data = $request->validate([
    'preset' => 'required|string|in:mcs_kalotsav',   // <-- only one Sahodaya can use this
]);
```

There's no "How is this event conducted?" step in event creation, and no per-event UI toggle. Every Sahodaya besides MCS has no way to discover or turn this on.

### Gap B — No "region result stands alone, don't combine" switch

`aggregation_config.include_roles` decides *what feeds the combined board if a combined board is shown* — but nothing today says "this event has regions but there is deliberately no finale/combine step; each region's result is the final result." An admin today would express that by simply never creating a `finale` partition, which works by accident, not by design — and the Leaderboard UI doesn't know to hide the "Overall" tab in that case vs. just having an empty finale.

### Gap C — Regional winners don't automatically advance into the finale

`FestQualificationService` already promotes winners **up levels** (school → sahodaya → state) via `promoteWinners()`/`resolveNextLevelEvent()`, keyed off `level_round`. Regions and the finale are **siblings at the same `level_round`**, not different levels, so this existing machinery doesn't reach them. There is currently no method that takes "1st place in Tirur" + "1st place in Manjeri" and enters them into the district finale for a head-to-head decider (relevant for items where the finale re-competes regional winners, e.g. costume/group items, rather than just summing points).

### Gap D — Phases don't exist

No model, no column, no UI. `partition_role` doesn't even accept `'phase'` as a value today (only `region|finale|cluster|digi_fest`), even though the design doc mentions phase as a concept. There's no way to say "Phase 1 (default)" vs "Phase 2 = on-stage day" and assign items to one or the other.

### Gap E — Region admin exists mechanically, but isn't a named, discoverable concept

`event_admin` + `FestEventStaff` already *works* for scoping an admin to one region's child event, but:
- The duty picklist (`TenantUserCatalog::festEventDuties()`) has no `region_admin`/"Region Coordinator" label — a Sahodaya admin assigning staff has no obvious way to know "assign this person as event_admin on the *Tirur child event*, not the hub" gives them region-scoped access.
- There's no dedicated "Region Admins" panel on the hub event — an admin currently has to open each region child event separately and use the generic Event Staff page.

---

## 3. Proposed design

### 3.1 Region-wise self-service (closes Gap A)

Add an explicit "Conduct topology" step to event setup (new UI, e.g. in `Levels.vue` or event-create wizard), backed by fields already on `fest_events`:

- **Single (default)** — `conduct_mode = standard`. No behavior change for any existing event.
- **Region-wise** — sets `conduct_mode = partitioned`, then calls the *existing* `FestRegionPartitionService::syncPartitionsFromRegions()` to spawn one child event per active `Region` and bulk-assign schools. No new backend logic needed here — this is UI work exposing what's already built, plus removing the `in:mcs_kalotsav` lock on `applyConductPreset` (or better, deprecating that endpoint in favor of the generic sync-from-regions flow so every Sahodaya's own Regions are the source of truth, not a hardcoded preset list).

### 3.2 "Combine at finale" vs "region result is final" (closes Gap B)

Add one explicit column on the hub event:

```php
// migration: add to fest_events
$table->boolean('combine_regions_at_finale')->default(true)->after('aggregation_config');

// backfill (same migration): any event that is ALREADY partitioned today keeps
// showing a combined board exactly as it does now — this is not a new opt-out
// for existing setups, only new/edited events get to choose false.
DB::table('fest_events')
    ->where('conduct_mode', 'partitioned')
    ->update(['combine_regions_at_finale' => true]);
```

**Default must be `true`, not `false` — see §7 for why.** `EventContext::scoreboardClusters()` (`app/Services/Events/EventContext.php:175-176`) today shows a combined board for **any** partitioned hub unconditionally, with no gate at all. Kids Fest clusters already rely on always getting a combined board. If this column defaulted to `false`, every existing Kids Fest umbrella (and any MCS setup already partitioned) would silently lose its "Overall" tab the moment the migration runs, until someone manually flipped a flag nobody knew to look for. Defaulting `true` + backfilling `true` for anything already partitioned makes this purely additive: nothing that currently shows a combined board stops showing one.

- `true` (default, and what every existing partitioned event keeps) — combined "Overall" board shown, computed from whichever partitions `aggregation_config.include_roles` says to sum. This is the Kalolsavam case.
- `false` — **new, opt-in only.** Region-wise event, no finale step: each region child publishes its own result independently, and that result *is* the final result. The Leaderboard UI shows only region tabs, no "Overall" tab. This is your English Fest case. An admin has to deliberately choose this in the new conduct-topology wizard (§3.1) — it never happens by migration default.

`EventContext::scoreboardClusters()` and `FestPartitionService::combinedScoreboard()`'s callers are the two places this flag gets checked.

### 3.3 Promote regional winners into the finale (closes Gap C)

New method on `FestQualificationService` (same vocabulary/pattern as the existing `promoteWinners()`):

```php
public function promoteRegionalWinnersToFinale(FestEvent $hub, FestEvent $finaleEvent, array $options = []): void
```

For each region partition under `$hub`, for each item, take the top N placeholders (respecting `qualify_count`/`tiebreak_mode`, same as today's promotion logic) and create registrations in the finale child event — mirroring `ensurePromotedRegistration()`, keyed by partition instead of `level_round`. Exposed as a button on the hub's "Levels" page: "Promote regional winners to finale," same UX pattern as the existing `spawnSchoolRounds`/promotion buttons.

Where an event doesn't need this (pure point-aggregation events, no re-competition round), this step is simply never invoked — it's opt-in per event, same as today's promotion actions.

### 3.4 Phases (closes Gap D)

New, small, dedicated table — **not** a reuse of `FestItemHead** (that model already carries fee/chest-numbering/notification semantics for subject-area conveners; overloading it with "phase" would conflate two different real-world concepts and risk breaking chest-numbering behavior). Modeled the same lightweight way `Region` is:

```php
// new migration: fest_event_phases
Schema::create('fest_event_phases', function (Blueprint $table) {
    $table->id();
    $table->foreignId('event_id')->constrained('fest_events')->cascadeOnDelete();
    $table->string('name');           // "Phase 1", "Digi Fest Day", "Off-stage", ...
    $table->string('code', 32)->nullable();
    $table->unsignedSmallInteger('sort_order')->default(0);
    $table->boolean('is_default')->default(false);
    $table->timestamps();
});

// fest_event_items gains:
$table->foreignId('phase_id')->nullable()->constrained('fest_event_phases')->nullOnDelete();
```

Behavior:

- **Default state (no phase-wise):** every event implicitly has exactly one phase ("Phase 1"), and `phase_id` on items stays `null` — meaning "the only phase." No UI shown, zero behavior change for existing events. This matches your "default as phase 1 or no phase" requirement directly: those are the same state, just described two ways — nothing is created in the DB until an admin actually turns phase mode on.
- **Phase-wise turned on for an event:** admin can add named phases (`FestEventPhase` rows) and, in the item list, assign each item's `phase_id`. Registration/schedule/report screens that list items can then group or filter by phase.
- Deliberately **not** modeled as another `partition_role` value spawning child events — a phase, per your description, is a scheduling/grouping label on items within the *same* event (same registration, same marks, same result), not a separate scoreboard. If a future case needs a phase to have its own independent publish/appeal window (the topology doc's §5.4 "advanced" case), that's still available via the existing partition mechanism (`partition_role = phase`, which needs to be added to the validation enum in `FestEventController::spawnPartition`) — kept as a documented escape hatch, not the default path.

### 3.5 Region admin, made discoverable (closes Gap E)

No new tables. Two small changes:

1. Add `'region_admin'` to `TenantUserCatalog::festEventDuties()` as a friendly label, internally still assigned as `FestEventStaff::duty = 'event_admin'` on the **region child event's ID** (reuses the exact scoping already enforced by `EnsureSahodayaAdmin`). This is a labeling/UX change, not a permissions change.
2. On the hub event's "Levels" or "Regions" page, add a **"Region Admins"** sub-panel that lists each region partition with an inline "assign admin" action — under the hood this just calls the existing `FestEventStaffController` store logic scoped to that child event's ID, so a Sahodaya admin never has to navigate into each region child event separately to staff it.

---

## 4. Concrete change list

### Database (tenant migrations)

| Change | Table |
|---|---|
| `combine_regions_at_finale BOOLEAN DEFAULT true` (+ backfill `true` for existing `partitioned` hubs) | `fest_events` |
| New table `fest_event_phases` (`event_id, name, code, sort_order, is_default`) | new |
| `phase_id BIGINT NULLABLE FK` | `fest_event_items` |
| *(optional escape hatch)* add `'phase'` to the `partition_role` check/enum | `fest_events` (validation only, column is already a plain string) |

### Backend

| File | Change |
|---|---|
| `app/Models/FestEvent.php` | Add `combine_regions_at_finale` to `$fillable`/casts |
| `app/Models/FestEventPhase.php` | New model (`event()` belongsTo, `items()` hasMany) |
| `app/Models/FestEventItem.php` | Add `phase()` belongsTo |
| `app/Services/Events/FestPartitionService.php` | `combinedScoreboard()`/`aggregationConfig()` respect `combine_regions_at_finale` before returning a combined board |
| `app/Services/Events/FestQualificationService.php` | New `promoteRegionalWinnersToFinale()` |
| `app/Services/Events/FestEventPhaseService.php` | New — CRUD phases, assign items to a phase, "turn phase mode on/off" for an event |
| `app/Http/Controllers/SahodayaAdmin/FestEventController.php` | Remove/relax the `in:mcs_kalotsav` lock on `applyConductPreset`; new endpoints for phase CRUD + item phase assignment; new endpoint to toggle `combine_regions_at_finale` |
| `app/Support/TenantUserCatalog.php` | Add `region_admin` to `festEventDuties()` |
| `app/Http/Controllers/SahodayaAdmin/FestEventStaffController.php` | Minor: when duty label is `region_admin`, still persist as `event_admin` under the hood |

### Frontend (Inertia/Vue, `resources/js/Pages/Admin/Sahodaya/Events/`)

| Page | Change |
|---|---|
| Event create/edit wizard | "How is this event conducted?" — Single (default) / Region-wise, with a sub-toggle "Combine regions into an overall result at a finale?" |
| `Levels.vue` (or equivalent) | Region-wise events: "Sync regions from Sahodaya" button (wraps existing `syncRegionPartitions`), region partition list, "Region Admins" panel |
| Items list | Phase filter/column + "assign phase" bulk action, only shown once phase mode is on for that event |
| `LeaderboardHub.vue` | Hide "Overall" tab when `combine_regions_at_finale = false`; show it, region tabs, and finale tab when `true` |

### No changes needed

- `Region`, `SchoolRegionAssignment`, `RegionController` — reused as-is.
- `FestRegionPartitionService::syncPartitionsFromRegions()` — reused as-is.
- `EnsureSahodayaAdmin` / `FestEventStaff` scoping — reused as-is for region admins.
- Anything about the real CBSE state level (`level_round=state`, `StateAdmin/*`) — untouched; orthogonal to this work.

---

## 5. Rollout order

1. **Phase 1 (backend, low risk):** migrations (`combine_regions_at_finale`, `fest_event_phases`, `phase_id`); models; unlock `applyConductPreset` from the single-preset restriction. Nothing existing changes behavior (`combine_regions_at_finale` defaults `true` and is backfilled `true` for any event already partitioned, so today's combined boards keep working; phases default to none).
2. **Phase 2:** `FestEventPhaseService` + item phase-assignment UI. Ship independently of region work — it's orthogonal and useful even for single-venue Sahodayas.
3. **Phase 3:** Event-creation "conduct topology" wizard + region sync button + Region Admins panel — makes region-wise self-service for any Sahodaya, not just MCS.
4. **Phase 4:** `promoteRegionalWinnersToFinale()` + finale-combine UI (Overall tab gating) — only needed once a Sahodaya actually wants the combine step.
5. **Phase 5:** UAT with a second real Sahodaya going region-wise (not MCS) to prove the self-service path, plus one event configured as "region-wise, no combine" (English Fest case) to prove Gap B end-to-end.

Each phase is additive and gated behind defaults that preserve current behavior — no existing Sahodaya's events change until an admin opts in.

---

## 6. Will this break ongoing/existing events and workflows?

**Short answer: no, if built the way this doc specifies — every change is additive and off-by-default for anything that isn't already using the affected behavior.** Walking through each gap:

| Change | Existing events affected? | Why |
|---|---|---|
| `combine_regions_at_finale` column | **None**, if defaulted `true` + backfilled `true` for already-partitioned hubs (fixed in §3.2 after re-checking `EventContext.php:175-176`, which today shows a combined board for *any* partitioned hub with no gate at all — Kids Fest clusters depend on that). Standard (non-partitioned) events never read this flag at all. | Additive column, correct default preserves current behavior exactly |
| `fest_event_phases` table + `phase_id` on items | None | New table, nullable FK; `null` phase = "no phase," which is every item today |
| `promoteRegionalWinnersToFinale()` | None | New method, never called unless an admin clicks the new "promote to finale" action on a region-wise event |
| Relaxing `applyConductPreset`'s `in:mcs_kalotsav` lock | None | Widens accepted values; doesn't remove the existing preset or change what it does |
| `region_admin` duty label | None | Cosmetic addition to a picklist array; existing `event_admin`/other duties untouched |
| New wizard step, region sync button, phase UI | None until used | All net-new UI surfaces; nothing existing is rerouted through them |

**Two things worth doing carefully regardless:**

1. **Migration deploys the standard way this codebase already uses** — idempotent, `Schema::hasColumn()`-guarded tenant migrations, rolled out via `sahodaya:provision-databases` across every Sahodaya DB (see `docs/erp/23-DEPLOYMENT_OPERATIONS.md` / `24-LIVE_SERVER_DEPLOYMENT.md`). That's routine here — dozens of prior migrations in this repo follow exactly this pattern, so no new deployment risk class is being introduced.
2. **Regression-test Kids Fest specifically**, not just MCS, before shipping Phase 1. Kids Fest is the one *currently live* consumer of `combinedScoreboard()`/`isPartitionedHub()` — MCS's Kalotsav events are dated Sep 2026 and don't appear to be live yet, so Kids Fest is the actual "don't break this" surface. Confirm the backfilled `combine_regions_at_finale = true` produces the same Overall tab, same totals, for an existing Kids Fest umbrella before and after the migration.

Net: this is designed so every existing Sahodaya's events behave identically the day the migration runs, and new behavior only appears where an admin explicitly turns it on for a specific event.

---

## 7. Open questions for you to confirm before implementation

- **Finale re-competition vs. pure point sum:** for events that combine, does the finale actually re-run the item (regional 1st-place holders compete again), or does "combine" just mean summing each region's points into one leaderboard with no extra round? Both are supported by the design above, but §3.3 (promotion) only matters for the re-competition case — confirm which applies per event type.
- **Phase scope:** should phase assignment be purely a scheduling/display grouping (as designed above), or do you also need **separate result publishing per phase** (e.g., Digi Fest results go live before Off-stage results, on a different timeline)? If the latter, that pushes toward the heavier `partition_role=phase` child-event route instead of the lightweight tag, for a subset of events.
- **Region admin permissions:** should a region admin have the *full* Sahodaya-admin feature set scoped to their region (current `event_admin` behavior), or a narrower subset (e.g., no fee/refund actions)? If narrower, that's additional permission-gating work beyond what's scoped here.
