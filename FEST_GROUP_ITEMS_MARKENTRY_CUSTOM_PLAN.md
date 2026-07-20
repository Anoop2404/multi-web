# Group Items, Mark Entry, and Custom Event Type — Feature Plan

Status: proposed, not yet implemented. Three separate, independently-shippable
feature areas, bundled here because they were raised together.

---

## Part A — Group/team item chest numbers

### Current state (confirmed by code read)
- A team/group registration creates one `FestGroup` row
  (`app/Models/FestGroup.php`: `registration_id, team_name, status, coach_name,
  coach_phone, manager_name, manager_phone`) and every squad member becomes a
  `FestParticipant` row with the same `group_id`
  (`FestRegistrationCreateService.php:87,145-173,250,295-327`).
- **Chest numbering has no group awareness at all.** `FestNumberingService`
  assigns a chest number per `FestParticipant` row individually — every squad
  member gets their own separate chest number, same as an individual-item
  competitor. There's no "team number."
- `FestIdCardService::teamCards()` already produces one ID card per team
  (not per member), listing each member's individual chest number inside
  that one card.
- `ChestNumbers.vue` lists every squad member as a flat row with a plain-text
  "Team" column — no grouping/collapsing.
- Bug already on file: `FestRegistrationCreateService`'s squad-detection only
  checks `participant_type in ('group','team')`, not `pair`/`trio` — those
  two participant types may not be getting `FestGroup` rows at all. Needs
  verification before Part A work starts.

### Decision (confirmed with user): one chest number per team
The whole squad shares a single chest number — like a team jersey number —
used on the team ID card and in results, rather than one number per member.

### Design
1. **Data model.** Add `chest_no` (+ `chest_revealed_at`, mirroring
   `FestParticipant`) directly to `fest_groups`. Team items get their number
   here; `FestParticipant.chest_no` stays populated only for individual-item
   participants, and is left `null` for members of a group-item squad.
2. **Numbering service branching.** `FestNumberingService`/
   `FestChestNumberService` need to branch on `item.participant_type`:
   - Individual types (`individual`, and confirm `pair`/`trio` — see bug
     above, likely also individual-per-person unless the user wants pairs/
     trios to also get one shared number, which needs a follow-up decision):
     unchanged, per-participant numbering (already event-wide per the
     earlier chest-number fix).
   - `group`/`team`: one number consumed per `FestGroup`, not per member.
3. **Number pool.** Open question for the user: does a team's number come
   from the **same sequence** as individual competitors in that event (so
   numbers 1-40 might be a mix of individuals and teams), or a **separate
   range** (e.g. teams numbered independently, optionally with a prefix like
   "T-1")? Recommend same sequence unless there's a print/announcer reason
   to separate them — confirm before implementing.
4. **UI changes:**
   - `ChestNumbers.vue`: group-item rows collapse to one row per team
     (showing team name + shared chest number + member count), with an
     expandable detail for the member list. Individual-item rows unchanged.
   - `FestIdCardService::teamCards()`: swap the per-member chest labels for
     the one team chest number shown prominently; members listed by name/role
     only (no individual number) unless the pair/trio follow-up decision says
     otherwise.
   - Chest number print sheet (`resources/views/fest/id-cards/*`): same
     collapse — one line per team.
5. **Migration.** New tenant migration adding `chest_no`/`chest_revealed_at`
   to `fest_groups`, plus a data-repair pass for any already-registered teams
   whose members currently hold individual numbers (consolidate to one
   representative number per group, same pattern used in the earlier
   event-wide chest number repair migration).

### Open questions before implementation
- Do `pair`/`trio` participant types get a shared number too, or do they stay
  individual (since a "pair" in badminton doubles, for instance, might still
  want two numbers for the two players)?
- Same numeric pool as individuals, or a separate team range/prefix?
- Confirm the `FestRegistrationCreateService` pair/trio gap — do those types
  currently even get a `FestGroup` row, or do they need that added first?

---

## Part B — Mark entry: configurable multi-criteria/judge columns + cumulative sheet

### Current state (confirmed by code read)
- On-screen mark entry (`MarkEntry.vue` + `FestMarkEntryController`) has a
  fixed column set per row: Participant (chest #, reg no, name), Attendance
  (sports only), Time/distance (if applicable), Rank, Grade, Points, Action.
  Not configurable.
- `FestMark` stores one consolidated mark per participant/item (grade,
  position, score, measurement value/unit) — no multi-criteria breakdown.
- `FestJudgeScore` supports multiple judges each submitting **one** score per
  participant/item — no named criteria (Technique/Presentation/Timing) and no
  per-criteria weighting.
- A printable mark sheet already exists
  (`resources/views/fest/reports/mark-entry-sheet.blade.php`) with blank
  Position/Grade/Score columns for judges to fill by hand — also fixed,
  not configurable.
- A "cumulative" sheet already exists
  (`resources/views/fest/reports/cumulative.blade.php`) but it's a
  **school-points total across the whole event**, not a per-student/per-team
  aggregation of scores across multiple items — that's a different, currently
  nonexistent report.

### Decision (confirmed with user): configurable multi-criteria/judge columns
Let an admin define N scoring columns per item (e.g. Technique, Presentation,
Timing, or Judge 1/2/3), each entered separately, with a computed total.

### Design
1. **Data model.** New table `fest_mark_criteria` (per-item, admin-defined):
   `event_id, item_id, label, max_score, sort_order, is_active`. New table
   `fest_mark_criteria_scores`: `criteria_id, participant_id (or group_id for
   team items — reuse Part A's grouping), judge_user_id (nullable), score,
   entered_by, entered_at`. Keep existing `FestMark` as the computed
   summary/total row (position, grade, final score) so results/leaderboard
   logic doesn't need to change — it just gets fed from a new total
   calculation instead of one direct score input.
2. **Total calculation.** Needs a decision from the user on the formula:
   sum of all criteria scores? Average across judges per criteria, then sum
   criteria? Configurable per item (e.g. a `total_formula` enum on the item)?
   Recommend starting with **sum of criteria averages across judges** as the
   default, since it's the most common judged-competition pattern, but flag
   this as needing sign-off before building.
3. **Admin UI — criteria setup.** New small panel on the item edit form (or
   a new "Scoring" tab next to it) where an admin adds/reorders/removes
   criteria rows (label + max score) per item. Defaults to the current
   single "Score" column if nothing is configured, so existing events/items
   are unaffected.
4. **Mark entry screen.** `MarkEntry.vue` renders one input column per
   configured criteria (dynamic, from `fest_mark_criteria`) instead of the
   current single Points/Score field, plus a live-computed Total column.
   Sl No and Chest No columns stay as the first two columns, matching current
   layout and the user's stated requirement.
5. **Printable sheet.** `mark-entry-sheet.blade.php` gets the same dynamic
   column treatment — one blank column per configured criteria instead of a
   single blank Score column, so paper mark entry and on-screen entry stay
   in sync.
6. **New cumulative mark sheet (distinct from the existing school-points
   cumulative report).** A new report — per student or per team (confirm
   which; likely per-student for individual items, per-team for group items,
   mirroring Part A) — showing Sl No, Chest No, then one column per item
   they're registered in, each cell showing that item's final score/points,
   with a running Total column. This is new: no existing service produces
   this shape. Likely lives in `FestEventReportAnalyticsService` as a new
   method, with both an on-screen table and a PDF export, following the
   existing `cumulative.blade.php` naming convention but scoped to
   individual/team level rather than school level (e.g.
   `cumulative-participant.blade.php`).

### Open questions before implementation
- Total-score formula (sum of criteria, average of judges, or admin-chosen
  per item)?
- Is the new cumulative sheet per-student, per-team, or does the admin need
  to choose per event (some events might want a school-level rollup too,
  which already exists separately)?
- Should criteria be reusable templates (define once, apply to many items)
  or always per-item from scratch? Reusable templates reduce repetitive setup
  for events with many similarly-judged items (e.g. all dance items scored
  the same way).

---

## Part C — "Custom" competition type

### Current state (confirmed by code read)
`config/fest_competition_types.php` already defines a `custom` type key:
```
'custom' => ['label' => ..., 'nav_slug' => 'custom', 'route_prefix' => 'custom',
             'description' => 'Ad-hoc / custom competitions (Phase 1 builder will add named types here)']
```
`FestCompetitionTypeRegistry::programsForNav()` special-cases non-system/
`custom` types to route under `programs/{slug}` instead of a short prefix.
This confirms "custom" is a real, sibling event-type category to `sports`/
`kalotsav`/`kids_fest`/etc. — but per its own description comment, it is
currently a **stub placeholder**, not a built-out type.

### What "built out" likely requires (needs a fuller audit before scoping)
A working competition type needs, at minimum, the same scaffolding every
other type has:
- A taxonomy set (age groups, categories, participant types) — sports and
  kalotsav each have their own; custom needs either a generic taxonomy or a
  way for the Sahodaya to define their own on the fly.
- A fee model — which of the existing `fee_model` options (`cksc_tiered`,
  `item_catalog`, `per_item`, `flat_school`, `per_student`,
  `sports_composite`) apply, or does custom need a simpler generic default?
- Item catalog behavior — can items be created ad-hoc only (no shared
  catalog), or does it get its own catalog like sports/kalotsav?
- Results/marks page — does it reuse the standard `MarkEntry.vue` /
  `FestMark` pipeline (likely yes, nothing item-type-specific there) or does
  it need type-specific scoring (tie into Part B's criteria system, ideally)?
- ID card / certificate defaults — reuse the existing template system
  (already generic/event-scoped, should work as-is).
- Nav/registration — confirm `programsForNav()`'s generic `programs/{slug}`
  routing actually renders a working page today, or if it 404s/breaks because
  no page exists for a `custom`-typed event yet.

### Recommended next step
This needs its own focused research pass before a build plan can be written
— specifically: create one test `custom`-type event in a dev/staging tenant
and walk through the entire admin flow (create → items → registration → fees
→ marks → results → certificates) to find exactly where it breaks or falls
back to sports/kalotsav-specific assumptions. That walkthrough will produce
a much more precise punch list than static code reading can, since "custom"
is explicitly unfinished rather than just inconsistent.

---

## Suggested order of work across all three parts

1. **Part A** (team chest numbers) — most concretely scoped, fewest open
   questions, and Part B's cumulative sheet benefits from Part A's
   group/team model being settled first (so criteria scores can key off
   `group_id` consistently).
2. **Part B** (mark entry criteria + cumulative sheet) — larger, needs the
   three open-question decisions first (formula, granularity, template
   reuse) before any schema is written.
3. **Part C** (custom event type) — needs the dev-tenant walkthrough before
   it can even be scoped properly; treat as a research spike, not a build
   task, until that's done.
