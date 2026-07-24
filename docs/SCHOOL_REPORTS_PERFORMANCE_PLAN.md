# School Sports Reports — Performance & Scale Fix Plan

Scope: the same report set fixed in `docs/SCHOOL_SPORTS_ITEM_HEAD_REPORTS_PLAN.md`, this time for query-count and unpaginated-result-set problems rather than correctness. Written for the specific question: will these hold up for a school with ~3,000 students?
Status: **plan only — no code changes yet.**

## 1. Two different problems, two different fixes

**A. N+1 query loops** — several report builders run 5-8 separate `count()`/`get()` queries per item or per head, inside a `foreach`/`->map()` loop. Query *count* scales with the number of items/heads in the event (typically tens, sometimes low hundreds), not directly with student count — but every one of those queries still has to filter through that school's (or, for Sahodaya-admin, every school's) registration/participant rows, so a 3,000-student school inflates the cost of *each* query in the loop, not just the loop's length. The net effect: a page that should be one query becomes 50-800+ queries, each somewhat more expensive than it would be for a small school.

**B. Unbounded, unpaginated result sets** — a few reports run a *single* efficient query but then return every matching row with no pagination, and the Vue page renders all of them in one plain `v-for` table. Here the cost scales directly with student count: a 3,000-student school registering for several items each could put 5,000-15,000 `<tr>` rows on one page. The backend query is fine; the browser is what struggles (slow render, laggy scroll, high memory).

A 3,000-student school will feel **B** first and worse — it's a direct, linear relationship to student count. **A** is a real but secondary problem: mostly extra round-trip latency (each query is small since the school filter is already applied), noticeable as "the page takes 2-4 seconds to load" rather than an outright failure.

## 2. Class A — N+1 loops, worst offenders first

| Method | File | Queries per iteration | Loop bound |
|---|---|---|---|
| `assignmentCompletenessRows()` | `FestEventReportAnalyticsService.php` | 8 (`approved`, `pending`, `performers`, `chestAssigned`, `itemRegAssigned`, `scheduledParticipants`, `marksEntered`, `judges`) | per item |
| `headWiseSummary()` (non-sports) / `sportsWiseSummary()` | same | 6-7 (`approved`, `pending`, `waitlisted`, `participantCount`, `verifiedParticipants`, `perItemCounts`, +conditional `headFees`) | per head / per sport event |
| `itemRegistrationRows()` | same | 5 (`approved`, `pending`, `participants`, `itemRegAssigned`, `schoolCount`) | per item |

`headRegistrationSummary()` calls both `itemRegistrationRows()` and `headWiseSummary()` internally, so pages that render it (the report hub, item counts, head-wise) pay both costs on one load.

### Fix pattern

Replace the per-item/per-head `count()` calls with a small, fixed number of grouped aggregate queries run once before the loop, then look values up from an in-memory map inside the loop — the same technique already used correctly elsewhere in this codebase (`FestSchoolReportController::studentWiseLookups()`, explicitly commented as fixing a "3000-student school triggered 9000+ queries" incident — see `docs/SCALE_AND_PAGINATION_PLAN.md` §7, which already exists and covers the precedent for this exact class of fix).

Concretely, for `itemRegistrationRows()`:
```php
$statusCounts = FestRegistration::whereIn('event_id', $eventIds)
    ->whereIn('item_id', $itemIds)
    ->when($schoolId, ...)
    ->selectRaw('item_id, status, count(*) as cnt')
    ->groupBy('item_id', 'status')
    ->get(); // one query, then pivot into [item_id => [status => cnt]] in PHP

$participantCounts = FestParticipant::query()
    ->join('fest_registrations', ...)
    ->whereIn('fest_registrations.event_id', $eventIds)
    ->whereIn('fest_registrations.item_id', $itemIds)
    ->selectRaw('fest_registrations.item_id, count(*) as cnt, sum(item_registration_number is not null) as assigned')
    ->groupBy('fest_registrations.item_id')
    ->get(); // one query instead of 2 (participants + itemRegAssigned)

$schoolCounts = $schoolId ? null : FestRegistration::whereIn('event_id', $eventIds)
    ->whereIn('item_id', $itemIds)->active()
    ->selectRaw('item_id, count(distinct school_id) as cnt')
    ->groupBy('item_id')->get(); // one query
```
This turns 5×N queries into 3 fixed queries total, regardless of item count. The same pattern applies to `assignmentCompletenessRows()` (8→~4-5 grouped queries: registration status counts, performer/chest/item-reg counts via one grouped query with conditional sums, scheduled-participant counts, marks-entered counts, judge counts) and to `headWiseSummary()`/`sportsWiseSummary()` (6-7→~3-4).

This is mechanical but not risk-free: several of the existing per-item queries use `whereHas('registration', ...)` with an `active()` scope and nested `orWhereHas` conditions (e.g. chest number via `whereHas('group', ...)`) that don't translate 1:1 into a `groupBy` aggregate — those need to be rewritten as joins or conditional `SUM(CASE WHEN ...)` expressions, which is where a mistake could silently miscount rather than just error. Each rewritten query needs its result spot-checked against the current per-item version before the old code is deleted (see §4).

## 3. Class B — unpaginated tables

| Report | Method | Vue page | Risk at 3k students |
|---|---|---|---|
| Numbering register | `numberingRegisterRows()` | `ReportNumberingRegister.vue` | One row per participant-per-item; plain `v-for` (confirmed, no pagination/virtualization) — could be 5,000-15,000+ rows on one page for a large school across all its items |
| Pending approvals | `pendingApprovalRows()` | `ReportPendingApprovals.vue` | Bounded by *pending* registrations only, so usually smaller, but no ceiling if approvals lag |
| Registration register | `FestRegistrationRegisterService::build()` | `ReportRegistrationRegister.vue` | One row per participant, same shape as numbering register |

These are single-query, not N+1 — the backend response itself is reasonably fast to produce. The problem is entirely client-side: no pagination, no windowing, and Inertia has to serialize/transmit every row on every filter change.

### Fix pattern

1. Add server-side pagination (Laravel's `paginate()`/cursor pagination) to these three endpoints, or at minimum a page-size cap with a "load more" affordance, matching whatever pagination convention `docs/SCALE_AND_PAGINATION_PLAN.md` already establishes elsewhere in this codebase (read that doc first — reusing the existing convention here rather than inventing a second one).
2. Client-side: switch the plain `v-for` to whatever paginated table component the rest of the app already uses (check for an existing one before building a new one), or at minimum virtualize the list if an existing paginated-table component doesn't fit this report's shape.

## 4. What this plan does NOT include

- Rewriting the correctness fixes from `docs/SCHOOL_SPORTS_ITEM_HEAD_REPORTS_PLAN.md` — this plan builds on top of that work, not instead of it.
- Changing anything about `FestSchoolEventFeeService::recalculate()`'s season-hub handling — flagged separately in that doc's §9.1, unrelated to performance.
- A general performance audit of the whole app — scoped strictly to the report set already touched, since that's what's being asked about.

## 5. Suggested build order

1. Read `docs/SCALE_AND_PAGINATION_PLAN.md` in full first — it already documents at least one precedent fix (`studentWiseLookups()`) and may already define a pagination convention to reuse rather than reinvent.
2. Batch `itemRegistrationRows()` — the simplest of the three N+1 loops (no nested `orWhereHas`), lowest risk, and it's called from the most pages (`headRegistrationSummary()`, item counts, report hub).
3. Batch `assignmentCompletenessRows()` — most queries per item, highest payoff, but also the riskiest to get exactly right (nested chest-number/group conditions). Spot-check its output against the pre-fix version for at least one real event before moving on.
4. Batch `headWiseSummary()` / `sportsWiseSummary()`.
5. Add pagination to `numberingRegisterRows()`, `pendingApprovalRows()`, and `FestRegistrationRegisterService::build()` plus their three Vue pages.
6. Verification (see §6) — no database or PHP runtime available in this environment, so every batched query needs a manual row-by-row comparison against its pre-fix counterpart, not just a syntax check.

## 6. Verification checklist

- For each batched aggregate query, compare its output against the original per-item/per-head loop's output for at least one real event with multiple items/heads and registrations in more than one status — a silent miscount (e.g. double-counting a participant across two joined tables) is the main risk of this class of fix and won't show up as an error.
- Confirm query count actually drops (e.g. via Laravel Debugbar or `DB::listen()` logging query count before/after) once there's a real environment to test in — this plan's whole premise can't be verified in this sandbox.
- For the pagination fixes, confirm existing filters (`schoolId`, status, head/item filters) still work correctly against a paginated query, not just an in-memory `->get()->filter()`.
- Brace-balance / syntax sanity check on every touched file, same discipline as prior fixes in this session.
