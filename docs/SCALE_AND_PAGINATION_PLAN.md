# Scale & Pagination Plan

**Prepared:** 23 Jul 2026
**Trigger:** Sahodaya scale check — 30-100 member schools per Sahodaya, **up to ~3000 students PER SCHOOL** (not 3000 combined — corrected 23 Jul 2026; a 100-school Sahodaya can mean up to ~300,000 students total), multi-year accumulated fee/receipt history.
**Status:** Implemented (24 Jul 2026) — every item in §9/§10 below has been built: the Fest and MCQ registration-page eager-load fixes and student-picker rewires, the `studentWise`/`teacherWise` N+1 rewrite, the bulk ID card PDF memory/timeout fix, `SchoolPaymentHistoryService` date/type-scoped filtering (Option A) wired into `UnifiedPaymentsController` and both reports, and `Registrations.vue` pagination with the redesigned filter-wide select-all (plus `printApproved()` reusing the same query helper). See git history on the files listed throughout this doc for the actual diffs. §11's "is this perfect" caveats still apply — this plan closed out every finding identified in it, not every conceivable scale issue in the codebase.

**Revision note (23 Jul 2026):** the original version of this doc assumed ~3000 students combined across a whole Sahodaya, and on that assumption marked the school-side registration pages "confirmed bounded/fine" since they're scoped to one school. That assumption was wrong. At 3000 students in a SINGLE school, those same school-side pages are not fine — §6-§8 below are new findings from re-checking under the corrected number, and one of them (§7) is worse than anything found on the Sahodaya-admin side. Priority ordering in §9 has been revised accordingly — the school-side fixes now come first, because they hit every school on every page load, not just Sahodaya admins occasionally opening a report.

No code had been changed for this plan as of 23 Jul 2026; everything below was analysis + spec at that point, written the same way as `FEST_PAYMENT_REGISTRATION_FLOW_GAPS.md` so it could be picked up and implemented piece by piece. That implementation is now done — see the Status line above.

---

## 0. Summary — what's actually wrong

| # | Item | Type | Risk if left unfixed |
|---|---|---|---|
| 1 | `fest_school_event_fees` has no usable index for `school_id`-only lookups | Missing index | Every payment-history query does a table scan on this table |
| 2 | `FestRegistrationReviewController::index()` (Sahodaya "Registrations" list) | Unbounded query | Large payload at Sahodaya scale, occasional admin page |
| 3 | `SchoolPaymentHistoryService::buildRows()` (feeds `UnifiedPaymentsController` + 2 reports) | Unbounded, 6 sub-queries | Several thousand rows merged/sorted in PHP on every page load |
| 4 | `FestRegistrationReviewController::printApproved()` | Unbounded PDF build | Same shape as #2, lower-frequency admin action |
| 5 | **`FestRegistrationController::index()`/`eventRegistration()`** — school registration home page eagerly loads the FULL student list, unconditionally, **duplicated once per open event** | Unbounded + dead threshold | Every school (all ~30-100 of them) hits this on every page load, not occasionally |
| 6 | **`FestSchoolReportController::studentWise()`/`exportStudentWise()`** | N+1 query storm | ~3 queries × every student, unbounded — at 3000 students that's 9,000+ queries in one request |
| 7 | **Eligible-students picker (`FestStudentPickerModal.vue`)** | Client-side-only filtering over full downloaded list | No server search wired up despite one existing; full student array shipped to browser and rendered unvirtualized |
| 8 | **`McqController::exam()`** — identical unbounded picker to #7, MCQ side | Unbounded + client-side filter | Same shape as #5/#7, found in second sweep, not yet fixed |
| 9 | **`FestIdCardController::pdfAllItems()`/`pdfAllHeads()`** | Under-provisioned bulk PDF generation | No memory/timeout override (unlike the single-item path); base64 photo embedding at scale |
| 10 | **`QUEUE_CONNECTION=sync` in the actual `.env`** (if true in production) | Infrastructure config | Every "queued" job (imports, CSV exports) actually runs inline/blocking — undermines every other fix above |

Priority order for implementation (revised twice — see revision note above and §8a): **0 (verify queue config) → 5+8 (together) → 6 → 9 → 1 → 7 → 3 → 2 → 4**. Full detail on 5-7 in §6-§8, on 8-10 in §8a; original Sahodaya-side items 1-4 detail is in §1-§4 below, unchanged from the first pass.

---

## 1. Missing index: `fest_school_event_fees.school_id`

**Current state:** only `unique(event_id, school_id)` exists (`database/migrations/tenant/2026_06_25_000002_fest_school_event_fees.php:25`). That composite unique index is only useful when `event_id` is part of the query (leftmost-prefix rule) — it does nothing for a query that filters by `school_id` alone across all events, which is exactly what every caller below does.

**Who's affected:** `SchoolPaymentHistoryService::buildRows()` (`whereIn('school_id', $schoolIds)`, `app/Services/Fees/SchoolPaymentHistoryService.php:51`) — used by `UnifiedPaymentsController`, `rptReceiptRegister()`, `rptReceiptEmailStatus()`, and the school-side fee-summary report.

**Fix:** new tenant migration adding a plain index:
```php
Schema::table('fest_school_event_fees', function (Blueprint $table) {
    $table->index('school_id');
    // optionally: $table->index(['school_id', 'status']) instead, since most callers
    // filter status right after — check actual query plans before deciding which.
});
```

**Risk:** none. Purely additive, no behavior change, standard `ALTER TABLE ADD INDEX`. Needs to run via `sahodaya:provision-databases` (or equivalent) across every tenant DB, same as any other tenant migration in this codebase (see the migration-ordering note pattern in `FEST_PAYMENT_REGISTRATION_FLOW_GAPS.md §10.1`).

**Effort:** ~15 minutes + a deploy window to run migrations across all tenant DBs.

---

## 2. `FestRegistrationReviewController::index()` + `Registrations.vue`

### Current state (confirmed)

Backend (`app/Http/Controllers/SahodayaAdmin/FestRegistrationReviewController.php:48-64`):
```php
$registrations = FestRegistration::where('event_id', $event->id)
    ->when($itemIds !== null, fn ($q) => $q->whereIn('item_id', $itemIds))
    ->with(['item', 'participants.student', 'participants.teacher', 'participants.group'])
    ->when($request->filled('search'), function ($q) use ($request) { ... })
    ->latest()
    ->get();
```
No `paginate()`/`limit()`. `search` and the head/item filter (`itemIdsForHeadFilter`) are already server-side query params — good, that part doesn't need to change.

Frontend (`resources/js/Pages/Admin/Sahodaya/Events/Registrations.vue`):
- `filterItemId` and `filterStatus` are **client-side only** — they filter the full in-memory `props.registrations` array (`filteredRegistrations` computed, lines 532-544). These currently never reach the server.
- `toggleSelectAll()` (lines 659-662) selects all *currently-loaded, currently-filtered* `submitted` rows — it has no concept of "all rows matching this filter across every page."
- `sportsGroupedRegistrations` (lines 687-706) groups the full loaded array by age group in-memory — assumes the whole dataset is present.
- Bulk actions: `approveSchool()`/`approveItem()` (lines 643-657) already send `school_id`/`item_id` directly to the server with no ID list — these do **not** depend on the full list being loaded and will keep working under pagination unchanged. `bulkApprove`/`bulkReject` (lines 628-641) send `registration_ids: selectedIds.value` — this is the part that breaks under pagination if left as-is (a user could only ever bulk-approve what's on the current page).

### What needs to change

**Backend:**
1. Add `status` and `item_id` as real query params (they already partially exist for item via `selectedItemId`) so `filterItemId`/`filterStatus` move server-side instead of client-side.
2. Replace `->get()` with `->paginate(50)->withQueryString()` (matching the pattern already used in a dozen+ other Sahodaya-admin pages — `FestPaymentsController.php:40`, `MemberSchoolsController.php:36`, etc.).
3. `itemIdsForHeadFilter`/`search`/`school_id`/`status`/`item_id` all become part of the paginated query, not a separate in-memory filter.

**Frontend:**
1. `filteredRegistrations` computed goes away — `props.registrations` becomes the current page's rows directly (or `logs.data` if following the `paginate()` + Inertia resource convention already used elsewhere, e.g. `LoginAudit.vue`'s `logs.links`/`logs.data`).
2. Filter dropdowns (`filterItemId`, `filterStatus`) switch from client-side `computed` to `router.get(...)` reloads with those as query params, same pattern `applySearch()` already uses.
3. `sportsGroupedRegistrations` needs a decision: either (a) move the age-group grouping into the backend query/response, or (b) accept that grouping only reflects the current page and add a visible "grouped view is per-page" note. (a) is cleaner but more work.
4. **"Select all" needs a real redesign**, not a cosmetic tweak: replace the per-row `selectedIds` checkbox model with a "select all matching this filter" action that calls `bulkApprove`/`bulkReject` with `school_id`/`item_id`/`status` filters (which the backend already supports, per `FestRegistrationBulkService::approveMany()`/`rejectMany()` `when($schoolId, ...)`/`when($itemId, ...)` clauses) instead of an explicit `registration_ids` array. Keep the explicit-IDs path for the (now page-scoped) manual per-row selection use case, but make "select all N registered under this filter" a distinct, explicit action so it's clear it isn't limited to one page.

**Also fix while touching this controller:** `printApproved()` (line 427-444, item #4 below) shares the same unbounded pattern and lives in the same file — worth doing in the same PR since it's a small, mechanical change once #2's query shape is settled.

**Risk:** medium. This is a real behavior change to a page Sahodaya admins actively use during registration review season — the "select all" redesign in particular changes a workflow, not just a query. Needs explicit sign-off on the new select-all UX before building, and a staging walkthrough with someone who actually uses bulk approve/reject day-to-day.

**Effort estimate:** 1-2 days (backend pagination + filter params: ~half a day; frontend page rework + select-all redesign: ~1-1.5 days including testing).

---

## 3. `SchoolPaymentHistoryService::buildRows()` + `UnifiedPaymentsController` + the two reports

### Current state (confirmed)

`buildRows()` (`app/Services/Fees/SchoolPaymentHistoryService.php:40-97`) runs six separate unbounded queries (membership, fest, training×2, mcq×2) across every school in the Sahodaya, `->concat()`s them, and sorts the combined PHP collection. No query-level filtering by date range, program type, or status — everything is fetched, then `UnifiedPaymentsController::index()` (`app/Http/Controllers/SahodayaAdmin/UnifiedPaymentsController.php:39-58`) filters `type`/`school_id`/`status`/`search` **after** the full fetch, via `Collection::filter()`.

This method is also now used (since the tenant-scoping fix earlier this review) by `rptReceiptRegister()` and `rptReceiptEmailStatus()` in `QueriesExtendedReports.php` — both previously had a `limit(500)` (buggy — applied before tenant filtering, but at least bounded); now neither has any cap at all.

Export (`UnifiedPaymentsController::export()`, lines 85-111) is a separate call to `rowsForSahodaya()` with no filters — this is expected to need the full matching dataset, so it's out of scope for pagination, but would benefit from date-range filtering to avoid pulling a school's entire multi-year history by default.

### What needs to change

This one is harder than #2 because the data comes from **six different Eloquent models**, not one table — a plain `->paginate()` doesn't work across a merged-and-sorted union of six queries. Two viable approaches, in order of recommendation:

**Option A — push filters down before the union (recommended first step, lower risk):**
Add `fromDate`/`toDate` (and reuse existing `school_id`/`type`/`status`) as real constraints on all 6 sub-queries in `buildRows()`, not just on the merged collection afterward. Default the *page view* (`UnifiedPaymentsController::index()`) to a sensible recent window (e.g. current academic year) unless the admin explicitly asks for more, while leaving `export()` able to pull everything when a full history download is actually wanted. This alone would cut the typical page-load row count dramatically without touching the merge/sort logic or the report call sites.

**Option B — true pagination over the merged set (bigger, only if Option A isn't enough):**
Fetch all 6 sub-queries still bounded by the Option A date/status filters, but wrap the final merged-and-sorted collection in `Illuminate\Pagination\LengthAwarePaginator` manually (since it's not a single query, Eloquent's `paginate()` won't do this natively) — slice the sorted collection for the current page, construct the paginator with the total count. This keeps the "sort by reviewed_at across all 6 types" behavior intact while capping what's rendered.

**For the two reports specifically:** `rptReceiptRegister()`/`rptReceiptEmailStatus()` are typically viewed with a date filter already (most `ErpReportQueryService` reports take `from`/`to`); confirm those filters get threaded into `rowsForSahodaya()` too (Option A) rather than filtering the full unbounded result afterward, which is what a naive fix would otherwise do.

**Risk:** Option A is low-risk (additive filters, sensible defaults) and should ship first. Option B is more involved (manual paginator construction, since this isn't a single Eloquent query) — only build it if Option A's date-bounding doesn't bring row counts down enough in practice.

**Effort estimate:** Option A: ~1 day (add date/status params to all 6 sub-queries + wire defaults into the controller and both report methods). Option B: additional 1-2 days if needed.

---

## 4. `FestRegistrationReviewController::printApproved()`

Same unbounded query pattern as #2 (`FestSchoolEventFee::where('event_id', $event->id)->with([...])->get()` — actually `FestRegistration::where('status','approved')...get()`, line 413-417), used to generate a PDF of all approved registrations for an event. Lower priority than #2 because:
- It's an occasional admin action (generate once, not a page loaded repeatedly), not a page hit on every visit.
- A PDF inherently needs the full dataset anyway (can't "paginate" a printed roster) — the concern here is generation time/memory, not response payload size to a browser.

**Recommended fix:** once #2's query/eager-load shape is finalized, reuse the same optimized query here (same `with()` structure, same indexes now in play) rather than redesigning it separately. If generation time becomes a real problem at 100-school scale, consider chunking the PDF build (`chunkById()` writing to the PDF incrementally) — but only if actually measured as slow; don't pre-optimize this one.

**Effort estimate:** ~1-2 hours once #2 is done (mostly reusing its query).

---

## 6. School registration home page — full student list loaded unconditionally, duplicated per event

**This is now the highest-priority item in this plan.** Unlike §1-§4, which affect a Sahodaya admin occasionally opening a list/report, this hits every one of a Sahodaya's 30-100 schools on every visit to their own registration home page.

### Current state (confirmed)

`app/Http/Controllers/SchoolAdmin/FestRegistrationController.php`:
- `index()` (lines 113-121): `Student::where('tenant_id', $this->school->id)->active()->with('schoolClass')->orderBy('name')->get()` runs **unconditionally** — there is a `lazyThreshold`/`erp.fest_registration_lazy_student_threshold` config (default 300) computed nearby, but it is never actually used to skip or gate this query. It only affects whether a single event auto-focuses (line 128).
- `studentsByEvent` (lines 146-156) then repeats the **same full annotated student list once per currently-open event** in the Inertia payload. A school with 3000 students and, say, 8 open events ships roughly 24,000 serialized student rows on a single page load.
- `eventRegistration()` (lines 282-293) has the identical unconditional-`get()` pattern.
- The one genuinely deferred path, `eligibleStudents()` (lines 227-257), is called from `Registration.vue`'s `loadStudentsForEvent` — but it almost never actually executes, because `studentsByEvent` has already eagerly populated the data by the time it would run (an early-return guard checks for existing data first). And even if it did run, it has no `->limit()`/search constraint either — it would just be a second unbounded fetch, not a bounded one.

**Net effect:** the "lazy loading" feature that appears to exist in this code (the threshold config, the `eligibleStudents` endpoint, the `lazyLoadStudents` frontend flag) is effectively dead — every code path ends up doing the same unconditional full-roster fetch, just sometimes twice.

### What needs to change

1. **Make the lazy threshold real.** In `index()`/`eventRegistration()`, when `Student::where('tenant_id', ...)->count() > $lazyThreshold`, skip the eager `->get()` entirely — send an empty/placeholder student list and a flag telling the frontend to fetch on demand.
2. **Fix `eligibleStudents()` to be genuinely bounded** — add server-side search (a `LIKE` clause on name/admission number, which the method already has per the earlier research but the frontend never calls with a search term) with a `->limit()` (e.g. 50-100 results), and change `Registration.vue`'s student picker to actually call it with the user's typed search text instead of filtering a pre-downloaded array.
3. **Stop duplicating the list per event.** Even at a bounded/lazy size, `studentsByEvent` repeating the same array once per open event is wasted payload — restructure so the frontend looks up students from one shared list keyed by ID, not N full copies.
4. Coordinate this with §8 (the picker component itself needs to switch from client-side filtering to consuming the new bounded/searched endpoint).

**Risk:** medium — this changes how the registration page loads data for every school, including smaller schools well under 3000 students (need to make sure small schools, which are probably the majority, see zero behavior change — the threshold gating should make this transparent below 300 students). Needs testing against both a small school (make sure nothing regresses) and a large one (make sure the actual fix works).

**Effort estimate:** 1-1.5 days (backend gating + real search endpoint: ~half a day; frontend picker rewire, shared with §8: ~1 day).

---

## 7. `FestSchoolReportController::studentWise()` / `exportStudentWise()` — N+1 query storm

**Confirmed the most severe individual finding in this review.** Loads every active student for a school unbounded, then runs roughly 3 separate database queries *per student* inside a loop (participant IDs, registrations, marks) — `app/Http/Controllers/SchoolAdmin/FestSchoolReportController.php:169-218` (`studentWise`) and `:319-341` (`exportStudentWise`).

At 3000 students, that's on the order of **9,000+ individual queries executed serially in one request** — not a payload-size problem like §6, but a real risk of the request simply timing out or exhausting DB connections/PHP execution time before it ever finishes, regardless of indexing.

**What needs to change:** rewrite both methods to batch-fetch. Pull all relevant `FestParticipant`/`FestRegistration`/`FestMark` rows for the whole school in 2-3 queries total (`whereIn('student_id', $allStudentIds)`), then group them in PHP by student ID (e.g. via `Collection::groupBy()`), instead of querying inside the per-student loop. This is a standard N+1 fix, not a design question — no product/UX decision needed, just an engineering rewrite of the data-fetching shape.

**Risk:** low from a behavior standpoint (output should be identical, just fetched more efficiently) — the only real risk is subtle bugs in the batch-and-group rewrite producing different results than the original per-student queries, so this needs careful output-diffing against the current (slow but "correct") version on a realistic dataset before shipping.

**Effort estimate:** ~1 day (rewrite + verify output matches old version row-for-row on a test school).

---

## 8. Eligible-students picker — client-side filtering over a fully downloaded list

`Registration.vue`'s `eligibleStudentsForItem` (lines 960-969) filters the *entire* in-memory student array in JavaScript, feeding `FestStudentPickerModal.vue`, which renders every entry (`v-for="entry in visibleEntries"`, lines 72-104) with no virtualization or pagination — just a client-side text filter (lines 195-210) over data that's already fully downloaded to the browser.

This is really the frontend half of §6 — once the backend stops shipping the full list, the picker needs to stop assuming it has the full list too.

**What needs to change:** switch the picker to call a real search endpoint (§6 point 2) as the user types, debounced, showing a bounded result set (e.g. top 50 matches) instead of filtering a giant pre-loaded array. This also fixes the render-cost side of the problem (a 3000-entry unvirtualized list is slow to render/scroll in the browser even before considering payload size).

**Risk:** low-medium — a search-as-you-type UX is a real (small) behavior change for school admins used to scrolling/browsing the full list; worth confirming this is an acceptable trade before shipping, though at 3000 students scrolling a full list is arguably already a worse experience today.

**Effort estimate:** included in §6's frontend estimate above — these two are one combined piece of work, not sequential.

---

## 8a. Second sweep (23 Jul 2026) — three more findings, one of them likely bigger than everything above

A first-principles question — "is this plan complete?" — prompted a second, broader sweep rather than trusting the first pass was exhaustive. It wasn't. Three more things:

**MCQ has the identical unbounded student picker as Fest (§6/§8), not yet covered.** `McqController::exam()` (`app/Http/Controllers/SchoolAdmin/McqController.php:129-170`) unconditionally loads every active student for the school on every visit to an exam's register tab and ships the full array for client-side filtering — same shape, same fix needed (bounded search endpoint, same rewire as §6/§8). Training is unaffected — it registers teachers, not students, at far smaller counts, and its bulk-store is already capped at 500.

**Bulk ID card generation is under-provisioned relative to its own load, and worse-provisioned than the single-item path next to it.** `FestIdCardController::pdf()`/`preview()` (single item) explicitly raise `memory_limit` to 512M and `max_execution_time` to 300 (`app/Http/Controllers/SahodayaAdmin/FestIdCardController.php:63-128`). The **bulk** variants — `pdfAllItems()`/`pdfAllHeads()` (lines 130-219), which render every item/head's cards for a whole event in one DomPDF pass, embedding every participant's photo as a base64 data URI — do **not** raise those limits, so they run under the container defaults (`memory_limit=256M`, `max_execution_time=60`, `docker/php/php.ini`). This is the exact inverse of what it should be: the bulk path handles the most data and has the least headroom. At real scale (thousands of participants across items/heads) this is very likely to hard-fail on memory or timeout, not just run slowly.

**`QUEUE_CONNECTION=sync` in the actual `.env` — this is the one that undermines everything else if true in production.** The codebase already has real background-job infrastructure for exactly this kind of heavy operation (`ImportStudentsJob`, `GenerateCsvExportJob` used by the payment-history/unified-payments exports) and `.env.example` recommends `QUEUE_CONNECTION=redis`. But if the deployed `.env` actually has `sync` (as the repo's current one does), every job that *looks* queued — CSV exports, bulk imports — still executes **inline, synchronously, inside the web request**, blocking on PHP's `max_execution_time` exactly like an unoptimized query would. This one config value determines whether §1-§8's fixes actually deliver a responsive system at scale, or just move the bottleneck from "slow query" to "job that still runs synchronously and still times out." **This needs verifying against the actual production `.env` before anything else in this plan is considered load-bearing** — no code change in §1-§8 fixes a wrong queue driver.

---

## 9. Rollout & testing checklist

Following the same discipline as `FEST_PAYMENT_REGISTRATION_FLOW_GAPS.md §10`:

1. **Migrations first.** Ship the `fest_school_event_fees.school_id` index (§1) before or atomically with any code touching that table's query patterns — pure index additions are safe either order, but do it first anyway since it's zero-risk.
2. **Test with realistic volume, at the CORRECTED scale.** Seed (or find a staging tenant with) at least one school with ~3000 students, and a Sahodaya with 50-100 such schools, before validating any of this — the original testing note in this doc assumed 10x-100x less data than actually exists; small-scale testing will not reveal these bugs, especially §6/§7 which only manifest at real per-school volume.
3. **§6 (school registration page) regression checklist:**
   - Small schools (well under the lazy threshold) see zero behavior change.
   - Large schools (3000-student range) no longer receive a multi-copy full roster on page load.
   - The student picker's search-as-you-type actually finds students correctly (name, admission number) and respects the same eligibility rules the old client-side filter enforced.
4. **§7 (studentWise report) regression checklist:**
   - Run the batch-rewritten report against a school with a known/small dataset FIRST and diff its output row-for-row against the current (slow) version before trusting it on a 3000-student school.
   - Confirm the export CSV path (`exportStudentWise`) gets the same fix, not just the on-screen report.
5. **§2 (Registrations.vue) regression checklist:**
   - Existing search still works and now also covers item/status filters server-side.
   - `approveSchool()`/`approveItem()` unaffected (already filter-based, no ID list).
   - New "select all matching this filter" bulk action actually approves/rejects everything matching, not just the current page — verify against a filter that spans more than one page.
   - Sports age-group grouping either reflects the full filtered set (if moved server-side) or is clearly labeled as per-page (if not).
6. **§3 (Payment history) regression checklist:**
   - `UnifiedPaymentsController::export()` still returns the full matching set (unaffected by index-view pagination/date-bounding, unless a date filter was explicitly applied by the admin).
   - Confirm the default date window on the index view doesn't silently hide older `pending`/`rejected` items an admin actually needs to see — consider defaulting to "no date filter but paginated" rather than "auto-restricted to current year" if that's a concern; this is a product decision, not just an engineering one.
7. **No PHP runtime in this analysis environment** — same caveat as the earlier gaps doc: none of this has been run against a live database. Verify query plans (`EXPLAIN`) and actual request timing on a staging tenant with realistic (3000-student) data volume before considering any item "done," not just "code looks right."

---

## 10. Sequencing recommendation (revised again after the second sweep)

0. **Verify `QUEUE_CONNECTION` in production before anything else.** Five-minute check, zero code risk, and it changes whether the rest of this plan is sufficient on its own. If it's `sync`, switching to a real queue (redis/database) is a prerequisite, not a follow-up — flag this to whoever manages the deploy environment immediately, in parallel with starting §6 below, not after it.
1. **§6 — school registration page eager-load fix**, done together with **§9-new — the same fix applied to MCQ's `McqController::exam()`** (identical pattern, same rewire, cheaper to do both at once than separately). Hits every school on every visit.
2. **§7 — `studentWise` N+1 rewrite.** The single most severe pure-engineering finding; no UX decision blocking it, can run in parallel with #1 by a second person.
3. **Bulk ID card generation fix** (§8a) — align `pdfAllItems()`/`pdfAllHeads()` with the same memory/timeout overrides the single-item path already has, and evaluate whether it should move to a real queued job (contingent on step 0) rather than a synchronous request at all, given base64 photo embedding at scale.
4. **§1 — `fest_school_event_fees` index.** Zero-risk, ship whenever convenient.
5. **§8 — student/picker rewire** (Fest + MCQ together, per #1 above).
6. **§3 Option A — payment history date/status filtering.**
7. **§2 — Registrations.vue pagination + select-all redesign.** Needs the most product input; also the least urgent remaining item now that the real per-school numbers are in, despite being found first.
8. **§4 — `printApproved` reuse.**
9. **§3 Option B** — only if §3 Option A isn't enough.

## 12. Third sweep (24 Jul 2026) — the sports item/head report set, found while fixing a correctness bug

Confirms §11's prediction: a third pass found a fourth thing. While fixing a season-hub correctness bug across the School Admin sports report pages (`docs/SCHOOL_SPORTS_ITEM_HEAD_REPORTS_PLAN.md` — head-wise, item counts, item-wise, discipline participation, assignment completeness, numbering register, pending approvals, attendance, published results, registration register), the same "built and tested at small scale" pattern showed up again, in a report set this doc's first two sweeps didn't cover.

**Two distinct problems, not one:**
- **N+1 loops** in `FestEventReportAnalyticsService::assignmentCompletenessRows()` (8 queries per item), `headWiseSummary()`/`sportsWiseSummary()` (6-7 per head/sport), and `itemRegistrationRows()` (5 per item) — same shape as §7's `studentWise` finding, but bounded by item/head count (tens, not thousands) rather than student count directly. Real, but secondary — extra latency, not a likely timeout.
- **Unpaginated result sets** in `numberingRegisterRows()`, `pendingApprovalRows()`, and `FestRegistrationRegisterService::build()` — one efficient query each, but every matching row is sent to the browser and rendered in a plain unvirtualized `v-for` (confirmed in `ReportNumberingRegister.vue`). This one scales directly with student count: a 3,000-student school could put 5,000-15,000+ `<tr>` rows on one page. This is the more serious of the two for a single large school, same category as §8's picker finding but on the report side instead of the registration side.

Full detail, exact fix pattern (grouped aggregate queries replacing the per-item loops, reusing this doc's own `paginate(50)->withQueryString()` convention — see `FestPaymentsController.php:40`, `MemberSchoolsController.php:36` — for the three unpaginated reports), and a suggested build order are in `docs/SCHOOL_REPORTS_PERFORMANCE_PLAN.md`. Not yet implemented — analysis only, same status as this doc was before its first implementation pass.

---

## 11. Is this "perfect" once done? — no, and here's what "done" actually requires

Implementing everything above fixes every *specific bug* found across two research passes. That is not the same as a guarantee the system handles 100 schools × 3000 students "perfectly" — a few honest caveats:

- **This was never an exhaustive audit, and a third pass would likely find a fourth thing.** Two sweeps found a school-registration eager-load bug, an N+1 storm, a dead lazy-load feature, a client-side-only picker (found twice, Fest and MCQ), an unindexed table, three unbounded queries, and now a PDF-generation resource mismatch and a possible queue misconfiguration. The pattern across all of these is the same root cause repeated in different files — "built and tested at small scale, never re-verified at the scale this plan corrects for" — which means anywhere else in the app with the same shape (a per-school or per-event list, report, or export nobody has load-tested) is a candidate for the same bug until actually checked.
- **Nothing in §1-§8 has been run against a live database or realistic data volume** — this whole plan, like the fee-flow gaps doc before it, was written without a PHP runtime or DB connection available in this environment. Code-level review can find the shape of a bug; it cannot confirm the fix actually performs acceptably under load. The only way to know these fixes genuinely work at 3000-students-per-school scale is to seed or obtain a staging tenant at that volume and measure real request timing, memory use, and query counts — not just review the diff.
- **Infrastructure matters as much as code here** (§8a's queue finding is the clearest example) — connection pool limits, PHP-FPM worker count, DB server sizing, and CDN/asset delivery for things like ID card photos are all outside what a code-level plan can fix, and all can bottleneck a correctly-optimized application anyway.
- **Recommendation:** treat this plan as "removes every known bug," then follow it with an actual load test against a realistic staging dataset (one script seeding ~100 schools × ~3000 students, then hitting the key pages/reports/exports under load) before calling this "handled." That load test is very likely to surface at least one more thing — that's the honest expectation, not a hedge.
