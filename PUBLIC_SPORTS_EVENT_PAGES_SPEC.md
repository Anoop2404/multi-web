# Public Sports Event Pages — Spec (Points Table + Full Public Surface)

Scope decisions from this planning session: extend/fix the existing scoreboard mechanism rather than building a second parallel page, and gate the public points table on official results-publish (not live/in-progress). This is a spec only — no code changes made. Grounded in the same codebase read as `USER_JOURNEY_AUDIT_FINDINGS.md` and `docs/user-journey-flowcharts/public-journeys.md`; read those first for the wider audit context.

---

## 1. What already exists today (Sports Meet, public side)

All of these are real, working Laravel routes under `app/Http/Controllers/Public/FestPortalController.php`, rendering Blade views under `resources/views/public/fest/*.blade.php`. They apply to Sports Meet the same way they apply to Kalotsav/Kids Fest/Teacher Fest, since all four share the same `FestEvent` model.

| Page | Route | Data shown today | Gated on results published? | Discoverable from nav? |
|---|---|---|---|---|
| Event hub | `GET /fest/{event}` | Title, status, competition items list | No | ❌ No nav link anywhere |
| Schedule | `GET /fest/{event}/schedule` | Item-wise schedule (time, venue, stage) | Gated on a separate schedule-publish flag | ❌ No nav link |
| Live tracker | `GET /fest/{event}/live` (+ `/live/data` JSON poll) | "Now performing" feed | No gate | ❌ No nav link |
| **Scoreboard** | `GET /fest/{event}/scoreboard` | School-wise rank + total points ("Leading schools"), filterable by age category or cluster, plus a "Latest winners" feed; auto-refreshes every 30s | **No gate — shows live/in-progress standings** | ❌ No nav link |
| Results (4 tabs) | `GET /fest/{event}/results?tab=school\|category\|item\|individual` | `school` tab = identical school-wise rank+points table; `category` tab = same, broken out per age group; `item` tab = winners per competition item; `individual` tab = every podium result, one row per participant | **Yes — `abort_unless($event->results_published, 404)`** | ❌ No nav link |
| Item-level results | `GET /fest/{event}/items/{item}/results` (+ `.pdf`) | Winners for one specific item | Same gate as Results | Linked from Results page itself |
| Winner posters | `GET /fest/{event}/items/{item}/winners/{mark}/poster.svg` | Shareable graphic, top-3 only | Same gate | Linked from item results |
| Records | `GET /fest/{event}/records` | Athletic records + recent record-breaks (if `record_tracking_enabled`) | No gate | ❌ No nav link |
| Search | `GET /fest/{event}/search`, `/participant/{ref}` | Look up a participant by chest number, registration number, or name (if name-search allowed) | N/A | ❌ No nav link |
| Manual | `GET /fest/{event}/manual` | Rulebook PDF, if uploaded | No gate | ❌ No nav link |

**The headline finding this spec addresses:** the school-wise points table already exists in two places with two different gating rules — `/scoreboard` (live, ungated, styled as a running feed) and `/results?tab=school` (identical data, correctly gated on `results_published`). That inconsistency is the actual bug behind "we need to set up a points table" — the right table already exists, it's just not the one that's linked or trusted.

Internal-only counterpart not yet public at all: **Individual Championship / best-athlete leaderboard** (`FestChampionshipController`, `Sahodaya/Events/Championship.vue`) — per-student points across the whole meet, ranked, with category/gender breakdown, computed via `FestGradePointService` and stored in `FestIndividualChampionshipPoint`. This is the "who is the best athlete of the meet" leaderboard and currently only the Sahodaya admin can see it.

---

## 2. The Points Table — spec

**Decision: don't build a new page.** Promote the existing `results` route's `school` tab to be the canonical public Points Table, and fix the scoreboard's gating so there's only one trustworthy source of truth.

### 2.1 Canonical source

- **Route:** `GET /fest/{event}/results?tab=school` (already exists, already gated).
- **Backing data:** `EventContext::scoreboardBySchool()` (falls back to `scoreboardByCategory()`/`scoreboardByCluster()` for the filtered views) — already computes `school_id`, `school_name`, `total_points`, `rank` per `FestResult` row.
- **Gating fix needed:** apply the same `abort_unless($event->results_published, 404)` check to `/fest/{event}/scoreboard` that `/results` already has (`FestPortalController::results`, line ~62), so a visitor can no longer see interim/unofficial standings through the un-gated URL. Until the Sahodaya admin publishes, both routes should show "results not yet published" rather than the scoreboard leaking numbers early.
- Alternative if the live view is still wanted for people physically at the event: keep `/scoreboard` live and ungated, but relabel it clearly as "Live / unofficial — not final" in the UI, and make `/results?tab=school` the only page that is ever linked from navigation or shared externally. (Not the option chosen this session — noted for completeness.)

### 2.2 Recommended columns

| Column | Source | Notes |
|---|---|---|
| Rank | `FestResult.rank` | 1, 2, 3… |
| School name | `Tenant.name` via `school_id` | |
| Total points | `FestResult.total_points` | Decimal, already stored |
| Category filter | `scoreboardCategories()` → age group for Sports Meet | Tabs/pills, already built |
| Cluster filter (if the Sahodaya partitions the meet, e.g. zone/cluster rounds) | `scoreboardClusters()` / `scoreboardClusterLabel()` | Only shown if `FestPartitionService::isPartitionedHub()` is true for the event |
| Published timestamp | `FestResult.published_at` | Not currently rendered on the public page — recommend adding "Results published on {date}" so visitors know the table is final, not a snapshot |

**Nice-to-have, not currently computed anywhere:** a medal tally (gold/silver/bronze count per school) alongside total points. Would need a new aggregation over `FestMark` grouped by `school_id` + `position`, since only the summed `total_points` is currently persisted per school. Flag as a future enhancement, not part of this spec's minimum scope.

### 2.3 Individual Championship — recommend making this public too

Same event, different table: the best-athlete leaderboard already computed by `FestChampionshipController::index` (rank, student name, reg no, school, category, gender, points) has no public route at all. Since a sports meet's individual championship ("Best Athlete") is typically as publicly anticipated as the school points table, recommend adding a public equivalent — either as a 5th tab on the `/results` page (`tab=championship`) or its own route, gated the same way (`results_published`).

---

## 3. Discoverability — nav plan

Every page in the table above is fully built but has **zero nav entry points** (confirmed in the earlier audit — see `docs/user-journey-flowcharts/public-journeys.md`). Recommended fix, using the existing site-builder nav config (`app/Support/NavConfigDefaults::forSahodaya()`, `resources/views/sections/*` pattern already used for Home/About/Programmes/Office Bearers/Member Schools):

1. Add a nav item to the Sahodaya's default public nav (`NavConfigDefaults::forSahodaya()`, alongside the existing `Programmes` entry) — e.g. `{'label' => 'Live Results', 'url' => '/fest', 'children' => []}` — pointing at the `/fest` index, which already lists all published/ongoing/completed events across every program type (Kalotsav, Sports Meet, Kids Fest, Teacher Fest).
2. From the event hub page (`/fest/{event}`), the Results/Points Table/Schedule/Records/Search links already exist in-page (confirmed in `FestPortalController::show`) — once the top-level `/fest` link exists, everything downstream is already reachable.
3. Optional: a dedicated homepage section (like the existing `sections/member_schools/modern-grid.blade.php` pattern) surfacing "This year's Sports Meet — Live Points Table" with a direct link, for Sahodayas who want it more prominent than a nav-menu item.

---

## 4. Full public-page checklist for a Sahodaya Sports Meet (summary)

| # | Page | Status today | Action needed |
|---|---|---|---|
| 1 | Event hub | ✅ built, orphaned | Link from nav (§3.1) |
| 2 | Schedule | ✅ built, orphaned | Link from nav |
| 3 | Live tracker | ✅ built, orphaned | Link from nav; consider labeling "unofficial" |
| 4 | **Points table (school-wise)** | ✅ built twice, inconsistently gated | Gate `/scoreboard` on publish (§2.1); treat `/results?tab=school` as canonical; link from nav |
| 5 | Category-wise points table | ✅ built (same gating issue as #4) | Same fix as #4 |
| 6 | Item/individual results | ✅ built, orphaned | Link from nav |
| 7 | Winner posters | ✅ built | Already linked from item results, no change needed |
| 8 | Records | ✅ built, orphaned | Link from nav |
| 9 | Participant search | ✅ built, orphaned | Link from nav |
| 10 | Manual/rulebook | ✅ built, orphaned | Link from nav |
| 11 | **Individual championship (best athlete)** | ❌ internal-only, no public route | Add public route/tab (§2.3) |
| 12 | Medal tally (gold/silver/bronze per school) | ❌ not computed anywhere | Future enhancement, not in this spec's minimum scope |

---

## 5. Suggested build order (when you're ready to implement)

1. Gate `/fest/{event}/scoreboard` on `results_published` (one-line change, fixes the core inconsistency this spec was written to resolve).
2. Add the `/fest` nav link to `NavConfigDefaults::forSahodaya()` (and equivalent for any Sahodaya that has customized their nav via the site builder).
3. Add "Results published on {date}" to the results page header, using `FestResult.published_at`.
4. Add the public Individual Championship tab/route.
5. (Later) Medal-tally aggregation, if wanted.
