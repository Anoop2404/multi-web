# Sports Admin Flow — Navigation & Consistency Cleanup Plan

Status: proposed, not yet implemented (except items marked ✅ Done).
Scope: Sahodaya-admin sports event flow only (`event_type === 'sports'`), under
`resources/js/Pages/Admin/Sahodaya/Events/` and its supporting nav components.

Context: an earlier audit found the sports admin flow confusing after event
creation — multiple different "step"/subnav widgets appear across pages, they
don't agree on what pages exist, and some pages have no navigation at all.
This document lists every issue found, the fix, the files touched, and a
suggested order of work.

---

## Already fixed

- **Double stepper on Setup page.** `SportsSetup.vue` was rendering both
  `FestEventWorkflowStepper` and `SportsSetupSubNav` stacked in the header.
  Removed the workflow stepper, kept `SportsSetupSubNav` (matches the pattern
  already used on `Items/Master.vue` and `Items/List.vue`). ✅ Done.
- **Dead "Event Head" UI in item catalog pages.** `Items/List.vue` and
  `Items/Master.vue` had head-filter dropdowns, an "Add/Edit Event Head"
  modal, and head-grouped tables that never had real data (backend always
  sent `itemHeads: []`). Stripped out; items now list flat. ✅ Done.
- **Chest numbers per-head instead of per-event.** Fixed to one chest number
  per student per event, shared across all items. ✅ Done.
- **No one-click way to close out an event.** Added `quickStatus` endpoint +
  an "Apply suggested status" button on `EventLifecyclePanel`. ✅ Done.

---

## Remaining issues, in priority order

### 1. Three navigation surfaces disagree on what pages exist
**Problem:** The left sidebar (`resources/js/support/sportsEventNav.js`,
`sportsEventSidebarNav`) lists 20+ links including Attendance, Chest numbers,
Results, and Levels. `SportsSetupSubNav.vue` shows only 3 tabs (Setup hub,
Items, Item listing). `FestEventWorkflowStepper.vue` shows 5 steps (Setup,
Items, Registrations, Marks, Results). Attendance, Chest numbers, and Levels
are invisible from the two nav widgets an admin is most likely to be looking
at mid-task (the stepper and the setup subnav) — reachable only via the
sidebar or a direct URL.

**Fix:** Treat the sidebar (`sportsEventSidebarNav`) as the single source of
truth for "what pages exist in a sports event," grouped by phase. Extend
`FestEventWorkflowStepper` steps (or replace it — see #5) so each step, when
active, surfaces the phase's real sub-pages (Attendance under "Marks" or its
own phase, Chest numbers under "Registrations" or "Setup"). Do not maintain
three independently-curated page lists.

**Files:** `resources/js/support/sportsEventNav.js`,
`resources/js/Components/sahodaya/FestEventWorkflowStepper.vue`,
`resources/js/Components/sahodaya/SportsSetupSubNav.vue`.

### 2. "Rounds & levels" is miscategorized in the sidebar
**Problem:** Filed under "Administration" in `sportsEventSidebarNav`
(`sportsEventNav.js` ~line 114), far from Items/Marks/Results, even though
levels are a prerequisite for scheduling and mark entry.

**Fix:** Move "Rounds & levels" into the Competition/Setup group, before
Items or right after it, so it appears in the natural order it's needed.

**Files:** `resources/js/support/sportsEventNav.js`.

### 3. Same destination, different names across surfaces
**Problem:** No canonical label per page:
- "Items" (stepper) vs "Item listing" (sidebar, but SportsSetupSubNav has
  both "Items" AND "Item listing" as separate tabs)
- "Registrations" (stepper) vs "All registrations" (sidebar) — not shown at
  all in SportsSetupSubNav
- "Marks" (stepper) vs "Mark entry" (sidebar)
- "Results" (stepper) vs "Results & publish" (sidebar)

**Fix:** Pick one label per destination and use it everywhere. Suggested
canonical set: **Items**, **Item listing**, **Registrations**, **Chest
numbers**, **Attendance**, **Marks**, **Results**, **Rounds & levels**,
**Activity log**. Update all three nav components to match.

**Files:** `sportsEventNav.js`, `FestEventWorkflowStepper.vue`,
`SportsSetupSubNav.vue`.

### 4. Workflow stepper uses full page reloads
**Problem:** `FestEventWorkflowStepper.vue` renders plain `<a href="...">`
tags instead of Inertia's `<Link>`, so every click does a full browser
navigation while every other nav component in the flow uses client-side
routing. Inconsistent and visibly slower.

**Fix:** Swap `<a>` for `<Link>` from `@inertiajs/vue3`.

**Files:** `resources/js/Components/sahodaya/FestEventWorkflowStepper.vue`.

### 5. Overview.vue shows two overlapping "what phase are we in" widgets
**Problem:** `Overview.vue` renders both `FestEventWorkflowStepper` (5 pill
steps: Setup/Items/Registrations/Marks/Results) and `EventLifecyclePanel`
(a checklist: Setup → Open registration → Ongoing → Publish results →
Complete) in the same page body. Different step models, different visual
style, same underlying idea — confusing for anyone landing on Overview.

**Fix:** Pick one. Recommended: keep `EventLifecyclePanel` (it's more
detailed, shows per-item done/not-done state, and already has the new
"Apply suggested status" action) and drop the top pill stepper from
Overview.vue specifically — it can stay on the deeper phase pages
(Registrations, Marks, Results) where it doubles as page-level nav.

**Files:** `resources/js/Pages/Admin/Sahodaya/Events/Overview.vue`.

### 6. Overview's "Quick links" card omits Attendance and Chest numbers
**Problem:** The Quick links card on Overview.vue (the first page an admin
sees) lists Sports setup hub, Event items, Participation policy,
Registrations, Leaderboard, Activity log, Reports — but not Attendance or
Chest numbers, two pages admins need on competition day.

**Fix:** Add both links to the Quick links card.

**Files:** `resources/js/Pages/Admin/Sahodaya/Events/Overview.vue`.

### 7. Dead "Event Heads" card and `?head_id=` link scheme still in Overview.vue
**Problem:** Overview.vue still has an "Event Heads" card and links built
around `?head_id=` query params, backed by
`FestHeadItemNavigationService::sportsNavigation()`. It's suppressed at
runtime for normal single-sport events (`hasItemHeads` is false) so it's not
a live bug, but it's inconsistent with the Head-UI cleanup already done in
`Items/List.vue` and `Items/Master.vue`.

**Fix:** Remove the card and the head-based link scheme from Overview.vue.
Confirm `FestHeadItemNavigationService::sportsNavigation()` has no other
live callers before touching the backend service itself.

**Files:** `resources/js/Pages/Admin/Sahodaya/Events/Overview.vue`,
possibly `app/Services/Events/FestHeadItemNavigationService.php` (verify
usage first).

### 8. Unreachable "Season hub" / "Season setup" sidebar entries
**Problem:** `sportsSeasonSidebarNav` still has "Season hub"/"Season setup"
labels, but `isSportsSeasonEvent()` in `sportsEventNav.js` is hardcoded to
always return `false` — this branch can never render. Confirms the season
hub is legacy dead weight in the nav layer specifically (separate from the
broader "should we delete season hub DB rows" product decision, still
undecided).

**Fix:** Remove the unreachable `sportsSeasonSidebarNav` branch and the
hardcoded-false `isSportsSeasonEvent()` check, or leave a one-line comment
explaining it's intentionally inert if there's a reason to keep the code for
a future re-enable.

**Files:** `resources/js/support/sportsEventNav.js`.

### 9. No nav at all on five pages
**Problem:** `Levels.vue`, `Activity.vue`, `Results.vue`, `ChestNumbers.vue`,
and `Schedule.vue` render no stepper/subnav in their header at all — an
admin who navigates there via the sidebar has no way to jump to a sibling
page without going back to the sidebar.

**Fix:** Once #1 and #3 are resolved (one canonical nav model), add the
appropriate nav component to these five pages. Do this last, after the nav
model itself is settled, to avoid rework.

**Files:** the five pages listed above.

---

## Suggested order of work

1. Fix #4 (Link vs `<a>`) — trivial, zero risk, do it standalone.
2. Fix #2 and #8 — sidebar reordering/dead-code removal, no cross-page
   dependencies.
3. Decide and fix #3 (canonical labels) — needed before #1 and #9 make sense.
4. Fix #1 (unify nav surfaces) — the biggest structural change.
5. Fix #5, #6, #7 (Overview.vue cleanup) — can happen in parallel with #1.
6. Fix #9 (add nav to the five bare pages) — last, depends on #1/#3 being
   settled.

## Out of scope for this plan

- The season-hub **data** decision (keep past hub rows vs. migrate/delete
  them) — separate product decision, not a nav/UI change.
- Attendance marking itself — already fully implemented per module
  (`FestAttendanceController` + `Attendance.vue`); this plan only addresses
  its discoverability from other pages (#1, #6).
