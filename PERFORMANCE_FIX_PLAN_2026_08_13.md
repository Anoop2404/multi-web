# Performance Fix Plan — 2026-08-13

> Covers all 8 findings from the registration/reporting audit + 5 findings from the certificates/notifications
> follow-up audit. Stack facts used below: **DB = PostgreSQL**, **QUEUE_CONNECTION=sync** in current env
> (must be switched to `database` or `redis` in production for any "move to a queued job" fix to actually
> run async — otherwise it still executes inline).
> Priority: 🔴 Critical · 🟠 High · 🟡 Medium

---

## How to read this document

Each item has: **Root cause**, **Best fix**, **Files touched**, and **Effort**. Fixes are ordered within
each phase by risk-reduction per unit of work, not by list order.

---

## Phase 0 — Prerequisite (do this first, everything else depends on it)

### P0 🔴 Queue driver is `sync`
**Root cause:** `QUEUE_CONNECTION=sync` in `.env` means any job dispatched with `->queue()` or a `ShouldQueue`
job still runs inline on the request thread. Several fixes below (cert ZIP export, bulk notifications) are
only real fixes if jobs actually run async.

**Fix:** Switch `QUEUE_CONNECTION` to `database` (simplest, no new infra — table likely already exists since
`app/Jobs/GenerateCsvExportJob.php` and `ImportStudentsJob.php` already use `ShouldQueue`) or `redis` if
available, and run a queue worker (`php artisan queue:work`) in production, ideally under Supervisor/systemd
so it restarts automatically. Without this step, Phase 3 fixes are cosmetic.

**Files:** `.env`, `config/queue.php`, deployment/process manager config.
**Effort:** Small (config + ops), but blocking for other fixes.

---

## Phase 1 — Stop the bleeding: GET requests that write, and page loads that block

### 1 🔴 Registration page GET performs writes
**Root cause:** Opening the registration page copies item data, recalculates fees, promotes waitlists, and
approves registrations as a side effect of a `GET`. This breaks HTTP semantics (GETs must be safe/idempotent),
makes page loads slow and non-cacheable, and means a user "viewing" a page can silently mutate financial and
registration state — which is also a correctness/audit risk, not just a perf one.

**Fix:** Extract each side effect into its own explicit action, triggered by the actual event that should
cause it, not by page render:
- Waitlist promotion → triggered by a registration cancellation/withdrawal event listener, or a scheduled
  command (`php artisan fest:promote-waitlists`) run every few minutes — not on every GET.
- Fee recalculation → triggered on write paths only (registration created/updated/item changed), and the
  *read* path should just display the last-computed value.
- Registration auto-approval → move to an explicit `POST /approve` action or a queued listener on the event
  that should cause approval (e.g. payment confirmation), not implicit on page view.
- Item-data copy → if this is meant to snapshot item config at registration time, do it once at registration
  creation, not on every subsequent view.

**Files:** Locate the controller/method serving the registration page GET (likely in
`app/Http/Controllers/*/FestRegistration*Controller.php`) and the services it calls inline
(`FestRegistrationFeeService`, waitlist/approval logic). Split read vs. write responsibilities.
**Effort:** Medium-High — requires tracing exactly which side effects are load-bearing (i.e. something today
depends on the page-load trigger) before removing them. Do this with a feature flag or staged rollout, and
add a regression test asserting `GET` on the registration page issues zero write queries.

### 2 🔴 Certificate ZIP export — synchronous + N+1
**Root cause:** `FestCertificateController::downloadZip` loops all certificates, calling `renderContext()` →
`payloadFor()` (a `find()` per certificate) and `resolveTemplate()` (up to 3 queries per certificate,
doubled on fallback), then synchronously renders Blade and writes to a `ZipArchive` inside the HTTP request
with no `set_time_limit`/`memory_limit` override.

**Fix (two-step — do the cheap query fix now, the queue fix after Phase 0):**
1. Batch-fetch: replace the per-certificate `payloadFor()` find() with one `whereIn('id', $entityIds)->get()->keyBy('id')`
   for participants and record-breaks before the loop, and resolve each template type once (cache by
   `cert_type` in a local array) instead of re-querying per certificate.
2. Move ZIP generation to a `ShouldQueue` job (mirror the existing `GenerateCsvExportJob` pattern already
   in the codebase) that writes the file to storage and notifies the admin (in-app notification + email)
   with a download link when ready, instead of blocking the HTTP response. This also removes the OOM/timeout
   risk for events with thousands of certificates.

**Files:** `app/Http/Controllers/SahodayaAdmin/FestCertificateController.php` (`downloadZip`),
`app/Services/Events/FestCertificateService.php` (`payloadFor`, `resolveTemplate`, `renderContext`),
new `app/Jobs/GenerateCertificateZipJob.php` modeled on `GenerateCsvExportJob.php`.
**Effort:** Medium (query batching: small; queue job: medium, needs a "your export is ready" notification path).

---

## Phase 2 — Kill the N+1s and pre-fetch, pre-page everything

### 3 🔴 Mark-entry status reports — 3 queries/item
**Root cause:** Loop over items, 3 queries each (≈450 queries for a 150-item event).

**Fix:** Replace the per-item loop with a single batched aggregate query using `whereIn('item_id', $itemIds)`
grouped by item (e.g. `selectRaw('item_id, COUNT(*) ...')->groupBy('item_id')`), then key the results by
`item_id` in PHP so the view/report can look up counts in O(1) per item instead of querying. This is the
same pattern needed for finding #9 below (bulk-publish), so fix both with one shared aggregate service method.
**Files:** Wherever the mark-entry status report builds its per-item loop (mark-entry/report controller or
service — same underlying data as `FestEventReportAnalyticsService::assignmentCompletenessRows()`).
**Effort:** Medium.

### 4 🔴 Bulk result-publish is O(items²)
**Root cause:** `FestResultsController` loops items calling `FestItemResultsService::publishItem()`, and each
call re-runs `FestEventReportAnalyticsService::assignmentCompletenessRows()` — a full-event aggregate — even
though only one item is being published.

**Fix:** Compute `assignmentCompletenessRows()` (or whatever aggregate `assertCanPublish()` needs) **once**
per bulk-publish request, before the loop, then pass the precomputed map into `publishItem($item, $precomputedAggregates)`
so each iteration does an O(1) lookup instead of a full-event rescan. This directly reuses the same
aggregate-batching work as finding #3.
**Files:** `app/Http/Controllers/SahodayaAdmin/FestResultsController.php` (lines ~145-148),
`app/Services/Events/FestItemResultsService.php` (`publishItem`, `assertCanPublish`, `itemSummaries`),
`app/Services/Events/FestEventReportAnalyticsService.php`.
**Effort:** Medium — requires refactoring `publishItem`'s signature/internals to accept precomputed data
without breaking its single-item call sites elsewhere.

### 5 🟠 Certificate list page N+1
**Root cause:** `FestCertificateController@index` calls `payloadFor()` per row in a `->map()`.

**Fix:** Same batching approach as fix #2 step 1 — fetch all referenced participants/record-breaks with one
`whereIn`, key by id, and have `payloadFor()` accept a preloaded entity instead of querying inside the loop.
Since this same fix is needed in both `index()` and `downloadZip()`, extract a shared
`FestCertificateService::payloadsForMany(Collection $certificates)` method that both callers use.
**Files:** `app/Http/Controllers/SahodayaAdmin/FestCertificateController.php`,
`app/Services/Events/FestCertificateService.php`.
**Effort:** Small-Medium (do alongside fix #2).

### 6 🟡 NotificationTemplate re-fetched per recipient
**Root cause:** `NotificationService::notifyFromTemplate()` queries `NotificationTemplate` fresh on every
call, but is invoked inside per-recipient loops in 4+ places.

**Fix:** Two layers — (a) at the call-site loops (`SahodayaAdminNotifier::notifyMany`,
`BoardResultCertificationNotifier`, `StudentEditChangeService`, `SendFestScheduleReminders`), resolve the
template once before the loop and pass it (or its slug-keyed cache) into each `notify()` call rather than
calling `notifyFromTemplate()` per recipient; (b) as a safety net inside `NotificationService` itself, wrap
the template lookup in `Cache::remember("notif_template:{$slug}", 300, fn () => ...)` so even un-migrated
call sites benefit. Templates change rarely, so a short TTL cache is safe and simple — no cache invalidation
plumbing needed beyond it expiring naturally, though adding a `saved`/`deleted` model event to flush the key
on edit is a nice-to-have.
**Files:** `app/Services/Notifications/NotificationService.php` (`notifyFromTemplate`), the four loop call
sites listed above.
**Effort:** Small.

### 7 🟡 Synchronous FCM push per token
**Root cause:** `FcmPushService::send()` blocks on a curl call (10s timeout) per device token, invoked from
inside the same recipient loops as #6.

**Fix:** After Phase 0's queue fix lands, dispatch push sends as a `ShouldQueue` job per batch (or per
recipient, queue overhead is cheap relative to a blocked 10s HTTP call) so a slow/down FCM endpoint no
longer stalls the admin's request or a scheduled command. If FCM's HTTP v1 API supports multicast for this
use case, batching multiple tokens into one call is a further improvement, but queueing alone removes the
blocking risk and is the higher-priority fix.
**Files:** `app/Services/Notifications/FcmPushService.php`, its call sites in `NotificationService`.
**Effort:** Small (mostly wrapping the existing send call in a queued job).

---

## Phase 3 — Pagination and query-shape fixes

### 8 🔴 General ERP report previews — pagination after fetch
**Root cause:** Full result set is fetched and transformed in PHP, then sliced to a page — so a Sahodaya-wide
report can pull hundreds of thousands of rows to show 50.

**Fix:** Move pagination to the database with `->paginate($perPage)` (or manual `->limit()->offset()` if the
report needs a non-Eloquent query builder) *before* any `->map()`/transform step, so only the current page's
rows are ever pulled into PHP. If the "preview" needs aggregate totals (e.g. "3,412 results found"), compute
that with a separate `->count()` query rather than counting the fetched collection.
**Files:** Wherever the ERP report preview endpoint(s) build their query — audit each report builder for
`->get()` followed by array slicing/`->forPage()` on a Collection instead of `->paginate()` on the query.
**Effort:** Medium — likely touches several report classes; do the highest-traffic reports first.

### 9 🔴 Registration register — full load before slice, per-school queries, fee recalc on read
**Root cause:** Loads all registrations/participants, slices to a page in PHP, queries per school, and
recalculates fees while loading a report.

**Fix:**
- DB-level pagination (`->paginate()`), same as fix #8.
- Eager-load the `school` relation (`->with('school')`) instead of querying per row.
- Read the already-stored fee amount instead of recalculating on every report load — fee recalculation
  should only happen on the write paths identified in fix #1, with the report simply displaying the
  persisted value. If a live "recalculated total" is genuinely needed for display, compute it as one batched
  aggregate query, not per-registration.
**Files:** Registration register controller/report builder + `FestRegistrationFeeService` call sites in the
read path.
**Effort:** Medium.

### 10 🔴 Bulk registration — nested per-registration work
**Root cause:** Student × item loop where every registration does its own validation, transaction, numbering
query, and fee recalculation.

**Fix:**
- Validate all rows upfront in one pass (collect errors, fail fast or report all invalid rows at once)
  rather than validating inside the per-registration loop.
- Wrap the whole bulk batch in a single DB transaction (or chunk into batches of ~200-500 with one
  transaction each) instead of one transaction per registration — this is both faster and more correct,
  since a partial-failure bulk import currently can leave a half-committed state per row.
- Numbering: reserve a contiguous block of registration numbers with a single query
  (`SELECT ... FOR UPDATE` on a counter row, or a Postgres sequence) instead of one number-lookup query per
  registration — check whether `FestNumberingService`/`StudentRegistrationNumberGenerator` already supports
  batch allocation; if not, add it.
- Fee recalculation: compute once at the end of the bulk batch, not per registration.
- For large imports (hundreds+ of students), move the whole operation into a queued job
  (`ImportStudentsJob.php` is already the established pattern in this codebase) so the admin doesn't wait on
  a synchronous request and the operation can report progress.
**Files:** Bulk registration controller/service, `app/Services/Events/FestNumberingService.php`,
`app/Services/Students/StudentRegistrationNumberGenerator.php`, `FestRegistrationFeeService`.
**Effort:** High — this is the most structurally invasive fix; sequence it after the query-shape fixes above
since some of the same numbering/fee code paths are shared.

### 11 🟠 Annual registration students — full roster serialized, no pagination
**Root cause:** All ~3,000 students loaded and serialized with no pagination.

**Fix:** Add `->paginate()` with server-side search/filter (by name, school, class) instead of shipping the
full roster to the client. If the page currently relies on client-side filtering across the full list, add
proper query params (search term, school filter) that the backend applies before pagination — this also
sets up the same server-side filtering pattern needed for fix #12 below.
**Files:** Annual registration students controller/page.
**Effort:** Medium.

### 12 🔴 Fest registration page — lazy-load threshold + client-side eligibility filtering
**Root cause:** Lazy loading only activates above 10,000 students, so all ~3,000 load; eligibility is then
filtered client-side per item (~3,000 × 150 = 450,000 checks), plus repeated registration scans.

**Fix:**
- Lower (or remove) the hard 10,000-student threshold — paginate/virtualize student lists well before that,
  e.g. always server-paginate lists over ~100-200 rows, since 3,000 is already large enough to matter.
- Move eligibility filtering server-side: expose a search/filter endpoint that takes the selected item and
  returns only eligible students matching a search term, computed with an indexed DB query (age group,
  gender, category conditions expressed as `WHERE` clauses) rather than pulling all students and filtering
  in JS.
- If the UI needs instant client-side filtering for UX reasons, cache the *eligibility rule* per item
  (small — 150 rules) rather than materializing the full 3,000×150 eligibility matrix, and apply it against
  a server-paginated, already-filtered student list.
**Files:** Fest registration page controller (Inertia props) + frontend list/autocomplete component.
**Effort:** High — this is a UX-affecting change (search-as-you-type instead of a full loaded list), so
coordinate with whoever owns the registration UI; worth prototyping the server-search endpoint first.

---

## Phase 4 — Indexes (do alongside or right after Phase 3, since new query shapes above will benefit)

### 13 🔴 Missing/misaligned indexes
**Root cause:** Postgres registration index is school-first, but many reports filter by `event_id` +
`item_id` + `status`; name search uses `LOWER(column) LIKE '%term%'` which can't use a plain b-tree index.

**Fix (Postgres-specific):**
- Add a composite index `(event_id, item_id, status)` on the registrations table (and any similar
  participant/mark tables queried the same way) to match the query patterns from fixes #3, #4, #8, #9.
- For name search: since this is Postgres, enable the `pg_trgm` extension and add a GIN trigram index
  (`CREATE INDEX ... USING GIN (lower(name) gin_trgm_ops)`) so `LOWER(name) LIKE '%term%'` can use an index
  scan instead of a sequential scan. Alternative if trigram search isn't desired: add a plain b-tree index
  on a stored-lowercase generated column (`name_lower`) and rewrite the query to `name_lower LIKE 'term%'`
  (prefix-only) if leading-wildcard search isn't actually required by the UI.
- Audit foreign key columns used in `whereIn`/`where` across the fixes above (`event_id`, `item_id`,
  `school_id`) for missing indexes — Postgres does not auto-index foreign keys.
**Files:** New migration(s) in `database/migrations/`.
**Effort:** Small to add, but **run `EXPLAIN ANALYZE` on the top 5 slow queries before and after** to confirm
the planner actually picks up the new indexes — Postgres won't use an index it doesn't think helps.

---

## Suggested sequencing

1. **Phase 0** (queue driver) — 1 day, unblocks everything else.
2. **Phase 1** (GET-writes, cert ZIP query batching) — highest risk items, do first.
3. **Phase 2** (N+1s: mark-entry, bulk-publish, cert list, notification caching, FCM queueing) — these share
   code paths (aggregate service, `payloadFor`), so bundle #3+#4 together and #2+#5 together.
4. **Phase 3** (pagination across ERP/register/annual-roster/fest-registration) — biggest user-facing win,
   also the most UI-coordination-heavy, so budget the most time here.
5. **Phase 4** (indexes) — cheap, but sequence after Phase 3 so indexes are built for the *final* query
   shapes, not the ones being replaced.

## Verification approach

- For each N+1 fix, add a test asserting query count with `DB::enableQueryLog()` /
  `assertDatabaseQueryCount()`-style assertion (or Laravel Debugbar/Telescope in a staging environment) before
  and after — this task list already tracks "no PHP runtime in sandbox" as a known limitation, so these need
  to be run and confirmed locally/staging, not just reviewed.
- For the GET-writes fix (#1), add a regression test that hits the registration page with `GET` and asserts
  zero `INSERT`/`UPDATE` queries occurred.
- For index fixes (#13), run `EXPLAIN ANALYZE` on representative queries before/after to confirm index usage,
  not just index existence.
- Load-test the fest registration page (#12) and bulk registration (#10) at realistic scale (3,000 students,
  150 items) after the fix, since these are the two findings explicitly tied to multiplicative blowup at your
  actual data volume.
