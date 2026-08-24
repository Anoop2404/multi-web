# Fest Module — Mark Entry → Results Publish → Public Scoreboard: Improvement Proposal

**Date:** 2026-08-23 (implemented 2026-08-24)
**Triggered by:** review of a real result sheet ("MALAPPURAM CENTRAL SAHODYA ENGLISH FEST – TIRUR REGION, OVERALL RESULT") and your question — is Rank Points/Grade Master set up correctly, what locks are missing, what reports should we cross-check with, and can we build a report in that exact format.
**Status:** ✅ **All 34 approved items implemented and verified** (backend `php -l` clean, frontend build clean, full Unit + targeted Feature test suites passing — including 2 new regression tests written for bugs found and fixed along the way). See "Implementation summary" at the end of this document for exactly what shipped.

---

## How this is organized

1. Rank Points setup — is it configured right, and what's broken
2. Grade Master setup — is it configured right, and what's broken
3. Locking — what should be frozen, and when, across the whole pipeline
4. Reports to cross-verify before you publish
5. Cleaning up reports (removing ones nobody can reach, merging duplicates)
6. The new consolidated report (matching your example PDF)
7. UI/UX fixes along the way

---

## 1. Rank Points

This is the table that decides how many championship points 1st, 2nd, 3rd place (etc.) earn for a school. For non-sports events (English Fest, Kalotsav...) the same tab is actually a different feature underneath ("Grade Points Master" — points per grade, not per rank).

**1.1 — On events with no rank-points table configured yet, two pages disagree on the default points.**
The Rankings page uses its own hardcoded 5/3/1, while everywhere else (Mark Entry, championship totals, cloning) uses a different hardcoded 8/7/6/5/4/3. Today only one live sports event exists and it has its table configured, so this hasn't bitten yet — but the next new sports event would show two different point totals depending which screen you're on. *Recommended fix, no real trade-off: make them agree.*

**1.2 — Cloning a sports event silently breaks its rank points.** [NEEDS YOUR DECISION — see Round 1, Q1]
When you clone an event (e.g. to reuse last year's Sports Meet setup), the clone's Rank Points page shows "No templates yet," and scoring quietly falls back to the generic default table — but the setup checklist still shows a green tick for "Rank points set," because it's only checking that some row exists, not that it's actually wired up correctly. This is the kind of thing that would only surface after marks are entered and totals look wrong.

**1.3 — Dead link.** The setup checklist's "Rank points set" link points at a URL that doesn't exist (404). *Recommended fix, no real trade-off.*

**1.4 — "Live preview while typing" doesn't actually work.** The code is set up to show you the points a score will earn *as you type* during mark entry, but that wiring was never finished — today, points only appear after you save. *This is a real feature gap, not just a bug — see Round 1, Q2 below for whether it's worth building.*

**1.5 — Old test suite for this feature is broken** (references a column/setup that no longer exists — 4 tests, 4 failures). Not visible to you day-to-day, but it means nobody would get warned if a future code change broke rank-points scoring. *Recommended fix, no real trade-off.*

**1.6 — The standard "athletics" point table (8/7/6/5/4/3...) is hardcoded in two separate places in the code that could drift apart over time.** *Recommended fix, no real trade-off — consolidate to one place.*

**1.7 — Confusing label.** The "Rank Points" tab is titled that for every event, but for 6 of your 7 fest types it's silently a totally different feature ("Grade Points Master"). *Recommended fix, no real trade-off — the tab should say what it actually does for that event type.*

**1.8 — No "recalculate all" button after you edit the points table mid-event.** [NEEDS YOUR DECISION — see Round 1, Q3]
If you tweak the rank-points table after marks already exist, the championship leaderboard total updates immediately (it's calculated live) — but the individual score shown next to each participant on the Mark Entry / Results screens does **not** update until that specific mark is re-saved. So you can end up with a leaderboard total that no longer matches what's printed against individual participants, with no warning that this happened.

**1.9 — No per-item override.** Right now rank points apply to a whole participant-type (e.g. "all individual events"), not per item. If two different individual items should award different points, there's no way to do that today. *Feature request — flagging for awareness, not asking as a formal question unless you want it; let me know if this matters to you.*

---

## 2. Grade Master

This is the table that decides what score range earns A+/A/B/C (etc.), which then feeds into points and the printed grade.

**2.1 — Real bug: a grade band's own upper limit is silently ignored, except for the top band.** [NEEDS YOUR DECISION — see Round 1, Q4]
Example: you set up A = 80–100%, intentionally leave 76–79% ungraded/gap, B = 60–75%, C = 40–59%. The form lets you save this without complaint. But when a score of 77% comes in, the system doesn't respect your B row's stated upper limit of 75 — it silently stretches B upward to fill the gap, so 77% is graded B anyway, even though you deliberately excluded that range. This is a real correctness bug, not a design choice — the fix makes the system respect the bands exactly as configured.

**2.2 — Editing grade bands mid-event doesn't consistently refresh already-entered marks.** [NEEDS YOUR DECISION — see Round 1, Q5]
If you adjust the grade cutoffs after marks exist: the Mark Entry screen keeps showing the *old* grade (until that mark is re-saved), while the Results page and championship totals immediately recompute with the *new* cutoffs. So for a window of time, three different screens can show three different answers for the same participant, with no banner telling you this happened or how many marks are affected.

**2.3 — For State Kalotsavam-style events (the ones using a fixed scoring format), the Grade Master tab is fully open and editable but has zero effect.** [NEEDS YOUR DECISION — see Round 1, Q6]
These events use a fixed, pre-built scoring table instead of your custom one. But nothing tells you that when you open Grade Master for one of these events — it looks exactly like a normal, working configuration screen. An admin adjusting thresholds here, especially on a high-stakes state-level event, would reasonably expect it to take effect. It doesn't.

**2.4 — Minor conveniences missing:** no bulk setup (you enter one grade band per click, no copy-from-another-item, no CSV import), no "what grade would a 77% get" preview while you're configuring, and no protection against accidentally creating near-duplicate grade labels via typos (e.g. "A+" vs "A + "). *Lower priority — flagging for awareness; can bundle into a later polish pass unless you want it prioritized now.*

---

## 3. Locking — what should freeze, and when

This is the part that answers your "mark entry lock... what all improvement needed" question directly. Today, locking exists but has real gaps.

**3.1 — THE BIG ONE: marks can still be edited after you've published that item's results, with zero warning, and the public scoreboard updates instantly.** [NEEDS YOUR DECISION — see Round 1, Q7 — this is the most important decision in this whole document]
Right now, "Publish" on an item is just a visibility switch — it does not freeze the underlying marks. So an admin can publish an item's results, then go back into Mark Entry and change a participant's score, and that change appears on the public scoreboard immediately, with no confirmation prompt, no distinction in the activity log between "this was entered before publish" vs "this was a silent correction after publish," and no requirement to un-publish first. This is the core gap behind "we need a mark entry lock."

**3.2 — Locking registration doesn't protect an already-approved team from being edited.** The "Block new registrations" toggle only stops brand-new submissions — it doesn't stop a school from swapping which students are on an already-approved team. If you lock registration to freeze rosters (e.g. before printing chest numbers), a school can still silently substitute a student afterward. *Recommended fix, no real trade-off — the toggle should do what its label says.*

**3.3 — An admin can add/remove a participant from a team at any time, including after that item's results are published — nothing stops it and nothing asks for confirmation.** *Recommended fix, no real trade-off — add a confirmation, at minimum, once marks/results exist.*

**3.4 — Marking someone "absent" after they already have a score doesn't retract or flag that score — it just silently sits in the results.** [Bundled into Round 2, Q8]

**3.5 — Chest numbers can be cleared/regenerated at any time with no warning**, even after judges have been handed a printed sheet listing the old numbers — since judging is chest-number-blind, a regeneration after printing has no way to be caught. [Bundled into Round 2, Q8]

**3.6 — The "mark entry closes once the event is fully published" protection is real, but works by accident**, not by an explicit check — it happens to work because publishing also flips the event's overall status, and mark entry is blocked for that status. If any future change ever published results without that specific status flip, mark entry would stay silently open. *Recommended fix, no real trade-off — make it an explicit, direct check instead of a side-effect.*

**3.7 — Nothing on the Mark Entry screen visually shows you which items are already published/locked** — a published item's entry grid looks identical to one that's still open, and the "Review marks" link from the Results page drops you straight into that same unprotected, editable grid. *This is the visible half of fix 3.1 — will be addressed together.*

**3.8 — REPORTS: your request to remove the event-lifecycle lock from reports.** [NEEDS YOUR DECISION — see Round 1, Q9 — I want to make sure I scope this correctly, see note below]

> **A note before you decide this one:** Today, report *viewing/exporting* is gated in two different ways that look similar but aren't the same thing, and I want to make sure "remove the lock" means what I think it means:
> - **(a) Phase-based gating for staff/admin reports** — e.g. some report tabs are only viewable once you're in the "during" or "after" phase of the event. This sounds like the one you want removed, since as an organizer you should be able to cross-check data at any time.
> - **(b) Public-facing visibility gating** — e.g. public result reports are blocked until `results_published` is true, and public exports are blocked from showing staff-only data. This is a *different* kind of control (who's allowed to see it, not merely when), and it's the exact mechanism your last 4 commits this week specifically went out of their way to *strengthen* ("strictly gate all public scoreboard, results, tv, and school-results endpoints when public results visibility is off").
>
> I'll ask you directly to confirm the scope before touching anything here, since (b) sounds like it should stay as-is given that recent work — but I don't want to assume.

---

## 4. Reports to cross-verify before publishing results

Based on what already exists in the system, here's the checklist I'd recommend running before you hit "Publish" on an event (in order):

1. **Pending Approvals report** — confirms no registration is still sitting unapproved that should be included.
2. **Mark Entry Status report** — shows per-item marked/not-marked counts. (Today this exists but is buried inside the Reports Hub with no link from the Mark Entry or Overview screens — see UX section 7.5.)
3. **Chest number completeness** — currently *not a report or a checklist item anywhere* (see 7.4 below) — I'm proposing this gets added, since it's exactly the kind of thing that should be double-checked before results go out (in the live tenant, one sports event currently has 5 approved participants with no chest number).
4. **Attendance completeness** — same as above, currently invisible (that same sports event has *zero* attendance records despite 100% of marks being entered).
5. **Schedule Clashes report** — confirms no double-booked participant/judge.
6. **Category-wise Points** (or the new consolidated report from Section 6) — a final sanity pass on totals before they go public.

Item 2, 3, 4 above overlap directly with the "readiness checklist" gaps in Section 7 — fixing that checklist (adding chest-number and attendance tracking to it, and linking Mark Entry Status into Overview) effectively builds most of this cross-verification workflow for you automatically, rather than requiring you to remember to run 5 separate reports by hand.

---

## 5. Reports cleanup — removing what's not reachable, merging duplicates

**5.1 — One genuinely dead report:** `Participation Rules` save button (Sahodaya Admin reports) is a stub that does nothing but redirect back with a message — no form anywhere in the app actually calls it. Zero live callers found. **Recommend: remove it.**

**5.2 — 3 report tiles that exist on the School Admin side but have never been wired to anything** — they show up as grayed-out placeholder tiles with no click target: *Student Participation Export* (all 7 programs), *Certificate Counts Export* (all 7 programs), *Age Group Matrix Export* (sports only). [NEEDS YOUR DECISION — see Round 2, Q10 — wire them up, or just remove the placeholder tiles]

**5.3 — Confirmed duplicate:** `Mark-entered Summary` and `Mark Entry Status` (Sahodaya Admin exports) are two different-looking buttons that produce byte-for-byte identical output. **Recommend: keep "Mark Entry Status" (it has a live preview page), remove "Mark-entered Summary."**

**5.4 — Possible duplicate (lower confidence):** School Admin's `Results Summary` and `Published Results` reports pull from the same underlying data for the same purpose — might be an intentional summary-vs-detail split, might not be. [Bundled into Round 2, Q10]

**5.5 — Harmless URL duplication:** two different URLs point at the exact same "Games Entry Form" report. Not a bug, just tidy-up if we're already in this area. *Optional, low priority.*

---

## 6. The new consolidated report (matching your example)

Good news: this is buildable, and rated **small-to-medium effort** — every underlying calculation it needs (points-per-mark, category grouping, school totals) already exists correctly elsewhere in the system; nothing needs to be invented, it just needs to be assembled into one new screen/printout in the shape of your example.

**What it will show**, matching your PDF:
- One row per school
- One column per item, grouped under category headers (CAT 1, CAT 2, CAT 3, CAT 4 — or whatever your event's actual categories are named)
- A subtotal column at the end of each category group
- An "OVERALL" grand-total column at the far right
- Points earned per item per school (not raw scores) — same points logic used everywhere else in the system, so it will always agree with the championship leaderboard

**Formats:** Printable landscape PDF (to match the look of your example) and an Excel download. [NEEDS YOUR DECISION — see Round 2, Q11 for build order/scope]

**Who sees it:** [NEEDS YOUR DECISION — see Round 2, Q11] — Sahodaya Admin only (full grid, all schools), or also a School Admin version?

---

## 7. UI/UX fixes along the mark-entry-to-publish flow

Grouped by how much they get in your way day-to-day.

**Critical — actively breaks the workflow:**
- **7.1** — The Results page (the very last step, where you publish) is missing the normal tab bar that every other page in the flow has. Once you're on Results, there's no in-app way back to Grade Master, Rank Points, Chest Numbers, or Attendance — you have to use the browser back button. *(Sports events don't have this problem — only non-sports.)*
- **7.2** — On the Attendance screen, if the participant table is wider than the screen, the rightmost column — the actual Present/Absent toggle buttons — gets **cut off and hidden**, instead of becoming scrollable. That's the one button the whole page exists for.
- **7.3** — Same problem, worse, on the Chest Numbers screen — its main table has no scroll handling at all, so on a narrow screen/tablet the whole page can push sideways uncontrollably.

**High — readiness checklist has blind spots:**
- **7.4** — The event readiness checklist (the one admins glance at to see "are we ready") never checks chest numbers or attendance at all — only registrations/fees/schedule/marks/publish. Confirmed live: one sports event shows "marks 100% complete ✓" while actually missing chest numbers for 5 people and has zero attendance records. *(This is what Section 4 above is proposing to fix.)*
- **7.5** — The "X/Y items configured" counter on the Mark Entry page doesn't actually mean marks were entered — it means scoring criteria were *set up*. The real "marks entered" numbers exist and work correctly, but only inside a separate report you have to already know exists and navigate away to find.
- **7.6** — The Overview page's summary tiles are shallow (Items/Registrations/Rounds/Portal only) — things you'd actually want at a glance, like "5 items have zero marks" or "3 registrations pending approval," exist in the system but aren't surfaced there.

**Medium — consistency gaps:**
- **7.7** — Some destructive actions (deleting a grade band, deleting a rank-points template, bulk-marking everyone present/absent) skip the "are you sure?" confirmation that similar destructive actions elsewhere in the same flow always use.
- **7.8** — Empty-state messages are inconsistent — some pages explain *why* the list is empty and link you to the fix (e.g. "No registrations to mark → Review Registrations"), others just say "No results found" with no explanation or next step, even when it's the exact same underlying cause.

**Low — polish:**
- **7.9** — Sports events get a dedicated, prominent "Setup Hub" homepage showing readiness at a glance; Kalotsav/other event types have the identical underlying data but it's tucked into a small sidebar card instead.
- **7.10** — An "Override locked registration" checkbox exists with no tooltip explaining what it does — easy to leave checked by accident.

---

## What happens next

I'll walk through the **[NEEDS YOUR DECISION]** items with you as a short set of questions, a few at a time (there are 11 total — Q1–Q7 cover Rank Points/Grade Master/the core locking gap, Q8–Q11 cover the remaining locks, report cleanup, and the new report's scope). Everything else in this document (marked "Recommended, no real trade-off") will be fixed as one batch once you've been through the decisions, unless you tell me otherwise.

---

## Implementation summary (2026-08-24)

Every item above shipped. Highlights, by section:

**§1 Rank Points** — Rankings-page fallback now matches every other consumer; cloning an event now correctly clones its rank-point templates (was silently reverting to the default table); dead checklist link fixed; the two duplicate hardcoded athletics-standard tables merged into one; tab now labels itself correctly per event type ("Rank Points" for sports, "Grade Points Master" elsewhere) in the top nav, sidebar, and page header; the stale/broken unit test file rewritten against the current schema and passing again. Live auto-fill preview intentionally left as post-save (your call).

**§2 Grade Master** — Fixed the real bug where a band's own configured max was silently ignored except for the top band (new regression test proves a deliberate gap between bands is now respected). Scoring-preset events (MCS/Confed Kalotsav) now actually honor custom Grade Master bands and Rank Points/Grade Points Master rules once you add any — the preset table remains the default only until you do. A "Sync to All Regions" action was added to Grade Master (mirroring the one Rank Points already had) so a hub's bands push to every region in one click.

**§3 Locking** — Publishing an item now freezes its marks (backend gate + visible lock banner/disabled inputs on Mark Entry); registration lock now actually blocks editing already-approved rosters; admin add/remove-participant and attendance-flip-to-absent now warn when marks already exist; chest-number clear/regenerate now warns when marks or attendance exist. Staff/admin report access is no longer gated by event lifecycle phase (confirmed zero overlap with the public-portal visibility hardening from your other recent commits).

**§4/§5 Reports** — Added a "Recalculate all marks" action (Rank Points + Grade Master) to refresh stale grade/score values after a config change. Removed the genuinely dead `participation-rules` stub route, retired the duplicate `mark-entered-summary` export, and wired up all 3 previously-inert School Admin report tiles (student participation, certificate counts, age-group matrix) to real, school-scoped backend exports.

**§6 New consolidated report** — Built: `FestEventReportAnalyticsService::schoolItemPointsMatrix()`, a new interactive Vue page, a landscape PDF (two-tier category header bands via colspan), and an Excel export — all under Reports → "Category & Item-wise Consolidated Report." Sahodaya Admin only, as agreed. Verified end-to-end with a real HTTP feature test (page load + both exports).

**§7 UX** — Results.vue restores full tab navigation for non-sports events; Attendance/ChestNumbers table clipping fixed; the Mark Entry item-picker indicator now reflects real marks-entered progress (not scoring-setup); Overview gained 3 new actionable KPI tiles (pending approvals, zero-marks items, marked-but-unpublished) plus a setup-progress bar in the hero banner for non-sports events; readiness checklists (both sports and non-sports) now track chest-number-assignment and attendance-recording, closing the exact live gap found on event 1 (26/31 chest numbers, 0 attendance, previously invisible); missing delete confirmations added; empty states standardized to icon + description + next-step CTA; the override-locked-registration checkbox now has a tooltip.

**Bonus, from your follow-up ask** — Audited `FestPointRule` (Grade Points Master rule) duplicate protection: the app-level upsert guard was already in place, but there was no real database constraint behind it (same race-condition class as the `FestSchoolEventFee` bug fixed earlier this session) — added a real unique index, switched the save path to a locked transaction, and gave the Rank Points "Point Rules" table proper Edit-in-place support plus a duplicate-overwrite warning, matching Grade Master's pattern.

**Not done / deliberately deferred:** per-item rank-points override, Grade Master bulk-config/CSV import, and live points-preview-while-typing were flagged as feature requests in the proposal but never approved as line items — happy to scope any of them if you want them next.
