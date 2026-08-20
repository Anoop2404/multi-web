# Board Results — UX Complexity Audit & Redesign Plan (3 Aug 2026)

Trigger: "current feels too much complicated for users... need to redesign the userflow and UI/UX of the reports and pages fully... also create separate settings page for each academic year." This doc maps the current implementation in full (every screen, every controller, every config table) to ground exactly *why* it feels complicated, then proposes a redesign. This is a findings + proposal doc — nothing has been built yet. Same caveat as every doc in this series: no PHP/DB runtime available, code review only.

**Decisions (3 Aug 2026, confirmed by the user — see §5):** keep both System A and System B rather than retiring either, but cross-link them clearly; ranking-wide toggles (common ranking / no-rank mode) stay global/structural, not year-scoped; old routes redirect rather than break; no historical backfill when `academic_year` is added — past years keep resolving to the existing global/NULL row. §3.2 and §5 below are updated to reflect this; the rest of the doc (§1, §2, §3.1, §3.3's settings-page shape) is unchanged.

---

## 1. What exists today

### 1.1 Data model & lifecycle

One `BoardResult` row per **school × class (10 or 12) × academic_year**, with a 6-state lifecycle: `draft → submitted → verified → approved → published`, or `rejected` (which sends it back to `draft`). Each `BoardResult` owns a set of `Topper` rows, and every `Topper` is one of three `entry_type`s that the UI treats as entirely separate features even though they're the same underlying model:
- `overall` — the school's top-ranked students (by percentage), capped at a configured Top-N.
- `subject` — per-subject top scorers, nominated separately from the overall toppers.
- `full_a1` — students who scored 91+ in *every* subject (not rank-limited, just a threshold list).

Two more tables sit alongside this: `AcademicAward` (auto-computed "Best Academic School," "Best Science School," etc., via `AwardsEngine`) and `BoardResultRanking` (the cached output of `RankingEngine`'s school- and student-level rank computations, refreshed by explicit "recompute" actions).

### 1.2 School-admin side — 4 separate pages to finish one class+year

A school admin entering one class's results for one academic year has to visit, in practice:

| Page | Route | What it does |
|---|---|---|
| Board Results (`Index.vue`) | `/board-results` | Search by class+year → fill summary stats (pass %, distinctions, etc.) → upload proof PDF → add **overall** toppers inline → save/submit |
| Subject Toppers (`SubjectToppers.vue`) | `/board-results/subject-toppers` | Re-select the same class+year → add **subject-wise** toppers |
| Full A1 Achievers (`FullA1Achievers.vue`) | `/board-results/full-a1-achievers` | Re-select the same class+year → add **Full A1** achievers |
| Toppers (`Toppers.vue`, school) | `/board-results/{boardResult}/toppers` | A separate full-page view/edit surface for the overall toppers already entered on the Index page |

That's four different URLs, each re-establishing the same class+academic_year context independently (`BoardResultController.php` has separate `index()`, `subjectToppers()`, `fullA1Achievers()`, and `toppers()` methods, each with their own year/class resolution logic), for what is conceptually one task: "enter this class's board result for this year." Nothing tells the school admin they need to visit all four — the connection between them is implicit navigation, not a guided flow.

### 1.3 Sahodaya-admin side — two parallel systems for the same concept

This is the more serious finding. There are genuinely **two independent implementations** of "Sahodaya-wide topper lists and reports," built by different code paths, and the code's own comments acknowledge this:

**System A — auto-computed, pooled rankings** (`SahodayaTopperController` + `SahodayaTopperSelectionService` + `RankingEngine`): pools every school's submitted toppers Sahodaya-wide, ranks them centrally, cuts to a configured Top-N. Pages: `Toppers.vue` (its own docblock calls it a "Settings hub... distinct from BoardResultVerificationController"), `TopperReportsMenu.vue` (docblock: "distinct from the general Reports hub, and from the Toppers settings hub above"), `TopperResultsOverall.vue`, `TopperResultsSubjectWise.vue`, `TopperResultsAchievers.vue`.

**System B — manual per-school data + separate report renderers** (`BoardResultReportController` + `SubjectMeritRegisterService`/`FullA1AchieversReportService`/`AcademicExcellenceReportService`): reads the same underlying `Topper` rows but through a different service layer, exposed via `Reports.vue` (the "Reports Hub," organized into "School Performance," "Merit & Rankings," "Excellence & Historical" sections), `SubjectMeritRegister.vue`, `FullA1Achievers.vue` (a *second*, differently-coded Full A1 page from the one under System A), `ExcellenceReport.vue`.

Both systems present overlapping labels — "Subject-Wise Toppers," "Full A1 Achievers," "Class 10 & 12 Stream Toppers" — through completely different pages, routes, and even PDF-generation code, with no cross-linking that makes the relationship obvious to an admin clicking around. `BoardResultsReportSubNav.vue` (the sub-nav shown across the Reports Hub) links to `board-results/toppers` — i.e., it points *into System A* from *within System B's* navigation, which is the one place the two systems touch, and it reads as "yet another tab" rather than "these are the same feature."

On top of both systems sits a **third**, separate review/approval console: `BoardResultVerificationController` + `Verification.vue` — the actual `verify → approve/reject → publish` workflow, one manual admin action per stage per `BoardResult` row (so up to 4 clicks × up to ~200 school×class combinations per Sahodaya per year, per `SCALE_AND_PAGINATION_PLAN.md`'s own scale numbers).

### 1.4 The same setting, editable from three different screens

`TopperCountConfig` (Top-N cap, tie-break mode, rank numbering style, per class/scope/stream/subject) can be changed from:
1. **Masters.vue** (`BoardResultMastersController::updateTopperCap`-equivalent — the page a Sahodaya admin would reasonably expect to be "the settings page").
2. **Verification.vue** (`BoardResultVerificationController::updateTopperCap()`, line 75-90 — an inline cap editor sitting in the middle of the *review queue*, of all places).
3. **Toppers.vue** (`SahodayaTopperController::index()` — described in its own docblock as a "Settings hub," reading and likely editing the same `TopperCountConfig` rows a third time).

Three different pages, three different mental contexts (global settings / reviewing submissions / auto-computed rankings), one underlying setting. An admin who changes the Top-N in Verification.vue while reviewing a submission has no way of knowing they just changed the same value Masters.vue shows as "the" setting.

### 1.5 Settings have no academic-year dimension at all — this is the concrete gap behind the "per academic year" ask

Checked directly against the schema:

- `TopperCountConfig` (`app/Models/TopperCountConfig.php`) — fillable: `sahodaya_id, class, scope, stream_id, subject_id, top_n, tie_mode, rank_style`. **No `academic_year` column.**
- `BoardResultMarksConfig` (`app/Models/BoardResultMarksConfig.php`) — fillable: `sahodaya_id, class, stream_id, total_marks`. **No `academic_year` column.**
- `TopperRankingSetting` (the `use_common_ranking`/`no_rank` toggles) — scoped by `sahodaya_id` only.

Every one of these is a single, global-per-Sahodaya value that applies retroactively to every academic year at once. Concretely, this means: if a Sahodaya wants to award fewer overall toppers this year than last year, changing the Top-N changes it for *every year's* reports, including already-published ones, the next time those reports are viewed or regenerated. Same for total marks (`BoardResultMarksConfig` — if CBSE changes the marks scheme in a future year, there is no way to keep last year's `total_marks` correct while entering this year's differently, short of a school typing the wrong "out of" value). There is, by contrast, already a working per-year concept elsewhere in this same feature — `BoardResultAcademicYearService` + `SahodayaRegistrationWindow.board_entry_starts_at`/`board_entry_ends_at` correctly gate *when* a year's entry window is open — so the pattern for "this thing varies by year" already exists in the codebase; it just wasn't applied to the scoring/ranking configuration.

---

## 2. Why this feels complicated — summary of root causes

1. **One task, four pages** (school side): entering one class's result means visiting Index, Subject Toppers, Full A1 Achievers, and (to review/edit) Toppers — each independently re-selecting class+year, none of them showing the others' completion status.
2. **Two competing systems for the same report concept** (Sahodaya side): "toppers" and "reports" are implemented twice, in parallel, with different data-flow (auto-pooled-and-ranked vs. manually-reviewed-and-rendered), and the navigation doesn't explain that relationship — an admin has to already know the codebase's internal history to understand why there are two "Full A1 Achievers" pages.
3. **A fourth console for approvals**, disconnected from both of the above except by URL.
4. **One setting, three edit surfaces**, with no single source of truth an admin can point to.
5. **No academic-year scoping on settings**, so changing "this year's" Top-N or total-marks silently rewrites every other year too — the opposite of what an admin editing "this year's settings" would expect.

None of this is a performance problem (this doc intentionally does not re-litigate the N+1 findings from the two earlier audits in this series) — it's an information-architecture problem: the right underlying data and computations exist, they're just scattered across more screens and more parallel code paths than the concepts require.

---

## 3. Proposed redesign

### 3.1 School side — one "Board Results Workspace" per class + academic year

Replace the four-page flow with a single page (still built from the existing three entry_type forms — no need to throw those away) organized as a status-tracked checklist for one class+year at a time:

- **Header:** class + academic-year picker (as today), but now showing a persistent progress summary — "Summary & proof: done · Overall toppers: 12 added · Subject toppers: 8/12 subjects · Full A1: 3 added" — computed from data that already exists (`BoardResult` + its `toppers` relation), just not currently surfaced as one combined status.
- **Body:** the three entry sections (summary+overall, subject-wise, Full A1) as expandable panels or a tab strip *within the same page and same class/year context*, instead of three separate URLs each re-resolving that context. This is a template/routing consolidation, not a data-model change — `subjectToppers()`, `fullA1Achievers()`, and the toppers section of `index()` already load from the same `BoardResult`; they just need to render as sections of one Inertia page instead of three.
- **Footer:** a single "Submit for Sahodaya verification" action, gated on all three sections being complete (today this gate only checks for a proof PDF — `store()`'s `submit_for_review` branch, line 185-190 — with no completeness check on subject/A1 toppers at all).
- **Status/history:** keep the existing "Saved Results History" panel, but add the current lifecycle stage inline per row instead of requiring a school admin to infer it.

### 3.2 Sahodaya side — keep both systems, but make the relationship between them explicit

**Decision (3 Aug 2026):** rather than retiring either System A or System B, both stay — but today's near-invisible relationship between them (discovered only by an admin clicking around and noticing two "Full A1 Achievers" pages) gets made explicit everywhere a user could land on one without the other:

- **Cross-link at the top of both entry points.** `Reports.vue` (System B's hub) and `Toppers.vue` (System A's settings/entry hub) each get a clearly-labeled callout linking to the other — e.g. "Looking for the pooled Sahodaya-wide ranking instead? → Toppers" on Reports.vue, and "Looking for the per-report merit registers instead? → Reports Hub" on Toppers.vue. This is a small, low-risk addition to two existing pages, not a rebuild.
- **Rename for clarity, not just link.** Right now both systems use overlapping labels ("Full A1 Achievers," "Subject-Wise Toppers") with nothing in the UI text distinguishing them. Recommend renaming System A's versions to make the *pooled/auto-ranked* nature explicit in the label itself — e.g. "Sahodaya-Wide Ranked Toppers" (System A) vs. "Full A1 Achievers Register" (System B, unchanged) — so the two are distinguishable by name alone, not just by which URL you happen to be on.
- **`BoardResultsReportSubNav.vue`** (the sub-nav already shown across System B's pages) keeps its existing link into System A's `/toppers` page (as it does today for "Class 10 & 12 Stream Toppers"), and the new School-Wise Toppers report added earlier this session sits alongside it — no page count reduction, but the existing sub-nav is the natural place for the cross-link above, not just an in-hub callout, so both should be done together.
- **Review console (`Verification.vue`) still gets `updateTopperCap()` moved out of it** — see §3.3 — this part of the original recommendation stands regardless of the System A/B decision, since it's a settings-location fix, not a duplicate-systems fix.

This is lower-risk and less work than consolidation, at the cost of not actually reducing the Sahodaya-side page count — worth revisiting again in a future pass if admins still find the two systems confusing once they're clearly labeled and cross-linked.

### 3.3 One settings page, scoped per academic year

This directly answers the "create a separate settings page for each academic year" ask. Consolidate every scattered setting below into one new page, e.g. `Sahodaya/BoardResults/Settings.vue` at `/board-results/settings?academic_year=YYYY`, with an explicit year selector at the top (same pattern `Reports.vue` already uses for its academic-year switcher, so this reuses an existing, proven UI convention rather than inventing a new one):

- **Marks configuration** (currently `BoardResultMarksConfig` via Masters.vue): total marks for Class X, and per-stream for Class XII.
- **Topper caps & ranking rules** — **superseded (20 Aug 2026):** rather than consolidating this onto the new settings page, the product direction changed to removing the concept entirely. Sahodaya-wide topper listings/reports now always sort by plain percentage, uncapped — no Top-N, no tie-break mode, no rank style setting anywhere. All three of the scattered edit points described below were already dead (zero routes) or silently ignored by the report-building code before this decision; they've been deleted rather than migrated. `TopperCountConfig` and `TopperCountService::resolveCap()` remain live for the unrelated, still-current school-side "how many toppers may this school enter" cap — only the Sahodaya-wide *display* ranking concept was retired.
- **Ranking-wide toggles** (currently `TopperRankingSetting`, edited from `Toppers.vue`): "use common ranking across streams/subjects," "no-rank / percentage-only mode." **Decision (3 Aug 2026): these stay global/structural, not year-scoped** — they still move onto the new settings page for discoverability (one less reason to visit `Toppers.vue` for a settings change), but rendered without a year selector, since a Sahodaya's ranking *policy* is being treated as a constant choice rather than something that varies year to year.
- **Masters data that genuinely isn't year-scoped** (streams, subjects, excellence-award scoring weights) stays as a separate "Masters" tab on the same settings page, or a clearly-labeled "applies to all years" section — these are structural/catalog data, not year-specific numbers, and forcing them into a per-year model would be over-engineering.

**Data-model change required:** add a nullable `academic_year` column to `TopperCountConfig` and `BoardResultMarksConfig` only (`TopperRankingSetting` stays as-is per the decision above — no schema change needed there). Resolution logic (`TopperCountService::resolveCap()` etc.) needs one more tier in its `orderByRaw` fallback chain: prefer an exact `academic_year` match, then fall back to a `NULL` academic_year row (today's global default), so **existing configs keep working unchanged** for any year that doesn't have its own override — this is additive, not a breaking migration. **Decision (3 Aug 2026): no historical backfill** — past years are not given their own explicit row; they simply keep resolving to the existing global/NULL row exactly as they do today, and only years that actually need a different value get a new, explicit per-year row going forward. A "copy settings from previous year" action on the new settings page (one click, pre-fills the new year's rows from the most recent prior year — which may itself be the global/NULL row if no year has ever overridden it) would make the common case — "same as last year, maybe tweak one number" — fast rather than requiring every field to be re-entered from scratch each year.

### 3.3a Per-year data-entry enable/disable + date range (added 3 Aug 2026, mid-session request)

New requirement: the per-year settings page needs an explicit **enable/disable toggle plus a date range for when school-side data entry is open**, and — this is the important behavior change — **if no date range is configured, entry should not be allowed at all**, rather than defaulting to open.

**This mostly already exists as infrastructure, just with the default inverted.** `SahodayaRegistrationWindow.board_entry_starts_at`/`board_entry_ends_at` plus `BoardResultAcademicYearService::assertEditableYear()`/`isResultWindowOpen()` already implement a per-Sahodaya, per-academic-year entry window — this is the same mechanism referenced in §1.5 as the working precedent for "settings that vary by year." The gap is purely in the fallback behavior:

- **Today:** if no explicit window is configured for a year, `assertEditableYear()` falls back to checking whether `AcademicYearRecord` itself is closed — meaning an admin who never touches the board-entry window setting gets **entry open by default**.
- **Wanted:** if no window is configured (or it exists but isn't explicitly enabled), entry should be **closed by default** — a Sahodaya admin must explicitly open a window (toggle on + both dates) before schools can enter that year's board results at all.

**Design:**
- Add the enable toggle + start/end date pair to the new per-year settings page (§3.3), backed by the existing `SahodayaRegistrationWindow` fields — no new table needed.
- Flip the fallback in `BoardResultAcademicYearService::assertEditableYear()`/`isResultWindowOpen()`/`resultWindowLockReason()`: no window row, or a window row with the toggle off, or a window row missing either date → **blocked**, with a clear message ("Data entry for {year} has not been opened yet — contact your Sahodaya admin.") instead of today's silent "open" default.
- Validation on save: the settings page should require both dates once the toggle is turned on — enabling without a full date range shouldn't be possible, matching "if not date don't allow" literally.

**This is a real behavior change, not just an additive one — flagging clearly since it carries genuine rollout risk.** Any academic year that currently relies on the no-window "open by default" fallback (very plausibly *every* Sahodaya's current/active year today, since nothing in the current UI prompts an admin to configure this window) would go from open to **blocked** the moment this ships, unless handled deliberately. Recommended rollout sequencing to avoid locking schools out mid-session:
1. Ship the toggle + date-range UI and the new validation first, without flipping the fallback yet.
2. Identify every Sahodaya's currently-open academic year(s) and give them an explicit, generously-dated window (e.g. today through the existing year-end) as a one-time data fix — either a migration/command or a manual pass, small enough to be safe either way given the scale here (tens of Sahodayas, not thousands).
3. Only then flip the fallback default from open to closed, once every currently-active year has an explicit window and nothing will suddenly lock out a school mid-entry.
4. Going forward, a new academic year simply starts with entry closed until a Sahodaya admin explicitly opens it — which is the intended behavior, just introduced without a gap where an existing school-in-progress gets cut off.

### 3.4 What this redesign deliberately does not change

- The underlying `Topper`/`BoardResult`/`entry_type` data model — it's sound, the problem is presentation/navigation, not the schema (aside from the year-scoping addition in 3.3).
- `RankingEngine`, `AwardsEngine`, and the four report-generation services (`SubjectMeritRegisterService`, `FullA1AchieversReportService`, `AcademicExcellenceReportService`, `AcademicPerformanceIndexEngine`) — these compute the right things; they just need to be reachable from fewer entry points.
- Anything already covered by the two prior audits in this series (`N1_AND_REPORT_MEMORY_AUDIT_2026_08_03.md`, `N1_AUDIT_SWEEP_2_2026_08_03.md`) — this doc is scoped to workflow/IA, not query performance.

---

## 4. Suggested build order

1. **Settings consolidation first** (§3.3), including the data-entry window toggle+date-range (§3.3a) — lowest risk, purely additive (`academic_year` nullable columns + fallback resolution), and unblocks "per-year settings" independent of any navigation rework. Migrate existing `TopperCountConfig`/`BoardResultMarksConfig` rows as the implicit "applies to all years" default (leave `academic_year` null on them, per the no-backfill decision in §5); new per-year overrides are added rows, not edits to old ones. Ship §3.3a's UI and validation in this step but **do not flip its open/closed fallback default yet** — that happens only after step 1a below.
   - **1a. Data-entry-window rollout safety pass** (part of step 1, not a separate phase): before flipping §3.3a's fallback from open-by-default to closed-by-default, identify and backfill an explicit, generously-dated window for every Sahodaya's currently-active academic year, so no school gets locked out mid-entry the moment this ships. This is the one piece of this whole doc that does need a small backfill, precisely because it's a behavior-default flip, not an additive change like everything else in step 1.
2. **Sahodaya reports/toppers cross-linking** (§3.2, revised) — add the explicit cross-links and relabeling between System A and System B; move `updateTopperCap()` off `Verification.vue` onto the new Settings page from step 1. Both systems stay live, so this is lower-risk than the originally-proposed consolidation.
3. **School-side workspace redesign** (§3.1) — the biggest single UX win for the most frequent users (every school, every year), but touches the most existing Vue code (three pages' worth of form logic to consolidate), so sequenced last to build on the settled settings model from step 1.

## 5. Decisions (confirmed 3 Aug 2026)

All four open questions from the original draft of this doc have been answered by the user:

- **System A vs. System B:** keep both — cross-link and relabel them (§3.2, revised) rather than retiring either.
- **Ranking-wide toggles (`TopperRankingSetting`):** stay global/structural, not year-scoped. Move onto the new settings page for discoverability, but no year selector for these two toggles specifically.
- **URL/route stability:** old routes redirect to their new equivalents rather than breaking, for at least one transition period.
- **Historical data on the new `academic_year` columns:** no backfill — past years keep resolving to the existing global/NULL row; only years that need a different value going forward get an explicit per-year row.

**New requirement added mid-session (3 Aug 2026):** per-year data-entry enable/disable + date range, with entry blocked by default when no window is configured — see §3.3a for the full design and the rollout-safety sequencing this specific change needs (it's the one exception to "everything here is additive," since it flips an existing default).
