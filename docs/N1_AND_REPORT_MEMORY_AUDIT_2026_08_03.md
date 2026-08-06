# N+1 Query & Report-Image Memory Audit — 3 Aug 2026

Scope: requested deep sweep for N+1 query patterns and memory-limit risk in report pages that embed images, across the whole app. This is a **third-pass** sweep — `docs/SCALE_AND_PAGINATION_PLAN.md` and `docs/SCHOOL_REPORTS_PERFORMANCE_PLAN.md` already found and fixed two rounds of this same bug class (both explicitly predicted "a third pass would find a fourth thing"). This doc is that fourth thing: findings below are **new**, not duplicates of what those two docs already closed out. Read those two first for the established fix conventions (batch-fetch-then-group-in-PHP, `paginate()->withQueryString()`, the `photoBase64DataUri()` cached-thumbnail pattern) — every fix recommended here reuses one of those conventions rather than inventing a new one.

Caveat, same as the prior docs: no PHP runtime or database available in this environment. Everything below is code-level review, not a load test.

**Status: Fixed (3 Aug 2026) — items 1-8 below have all been implemented.** Item 9 was left as-is (structural, not a bug — see its section). Every fix reuses the same conventions the prior two docs established; nothing new was invented. As with every fix in this doc series, nothing was run against a live database — verified by code review and brace/paren balance checks only (no PHP runtime available in this environment, same caveat as always).

## Summary

| # | Finding | Class | File | Severity | Status |
|---|---|---|---|---|---|
| 1 | MCQ attendance/mark/result-sheet PDFs embed every candidate's photo via an authenticated app URL, not a cached data URI | Report + image memory | `McqPrintableDocumentService::registrationRows()` | High | **Fixed** |
| 2 | Bulk ID card PDFs read full-resolution photo bytes with no downscaling and no caching | Report + image memory | `FestIdCardService::portraitDataUri()` / `TenantStorage::photoDataUri()` | High | **Fixed** |
| 3 | ERP waiver register runs the same relation-load twice per receipt, unbounded | N+1 | `ErpReportQueryService::waiverRegister()` | Medium | **Fixed** |
| 4 | ERP daily-collection report re-resolves each receipt's school via a per-receipt `loadMissing()` | N+1 | `ErpReportQueryService::dailyCollection()` | Medium | **Fixed** |
| 5 | Document-compliance report runs one query per school | N+1 (bounded) | `ErpReportQueryService::documentCompliance()` | Low-Medium | **Fixed** |
| 6 | Late-fee report counts active students per payment row inside a `map()` | N+1 (bounded) | `ErpReportQueryService::lateFeeCollected()` (~line 198) | Low-Medium | **Fixed** |
| 7 | House-points ranking re-fetches the same event once per mark | N+1 | `SchoolHouseFestPointsService::rankingForSchool()` | Low-Medium | **Fixed** |
| 8 | MCQ certificate bulk-issue double-queries per registration | N+1 (chunked, so bounded) | `McqCertificateService::issue()` / `issueBulk()` | Low | **Fixed** |
| 9 | Cross-tenant dashboard rollups do one query per Sahodaya database | N+1-shaped, but structural | `StateDashboardService::clusterResultsRollup()` / `clusterParticipationRollup()` | Low (inherent to multi-tenancy) | Not fixed — structural, see note |

Detail on each below, worst first. Each section's original writeup is left intact; a "**Fix applied**" note is added under each showing exactly what changed.

---

## 1. MCQ printable sheets — authenticated photo URLs instead of embedded thumbnails (High)

**Where:** `app/Services/Mcq/McqPrintableDocumentService.php::registrationRows()` (feeds `attendanceSheetPdf()`, `markSheetPdf()`, `resultSheetPdf()`), rendered by `resources/views/mcq/attendance-sheet.blade.php` (confirmed — `<img src="{{ $row['photo_url'] }}">` on every row, line 75).

**What's happening:** `photo_url` is built from `Student::photoUrl()` / `sahodayaPhotoUrl()` (`app/Models/Student.php:70-116`), both of which return a full authenticated app URL (`url(route('school.students.photo', ...))` / `route('sahodaya.students.photo', ...)`), not a local path or embedded data URI. DomPDF has to resolve every one of those `<img>` tags itself during PDF generation, which means firing a real outbound HTTP request back into the app, per candidate, serially, while the worker rendering the PDF blocks waiting on each one.

This is the *exact* failure mode already documented and fixed on the Fest side — see the docblock on `TenantStorage::photoBase64DataUri()` (`app/Support/TenantStorage.php:351-379`), which explains why the Fest attendance-sheet reports were switched to embedded, downscaled, cached data URIs: authenticated `<img>` URLs at scale exhaust PHP-FPM workers and randomly fail/crawl, "not just" a browser-side problem. That fix was applied to `FestReportService`'s attendance sheets (both the Sahodaya-admin combined report and, per its own comment, the school-scoped one after a specific memory incident) but was **never applied to the MCQ printable documents**, even though they render the same shape of report (one row + one photo per registrant, across every school in a Sahodaya-wide exam) and already carry the tell-tale `ini_set('memory_limit', '1024M')` / `set_time_limit(300)` band-aids at the top of every method in the file — the same band-aid pattern the Fest side had before its real fix.

**Contrast worth noting:** `classWiseRegistrationPdf()` in the same file already does this correctly — its `studentPhotoSrc()` helper (line 302) resolves a real local filesystem path for DomPDF to read directly, no HTTP round-trip. The other three methods (`attendanceSheetPdf`, `markSheetPdf`, `resultSheetPdf`) don't use it; they use `registrationRows()`, which doesn't.

**Recommended fix:** replace `photo_url` in `registrationRows()` with a call through `TenantStorage::photoBase64DataUri()` (already downscales to a thumbnail and is designed to be wrapped in `Cache::remember()` per student, exactly as `FestReportService` does at lines ~877-885 — `'student-photo-thumb:'.$id.':'.$student->updated_at?->timestamp`). This removes both the memory risk (full-res bytes) and the worker-exhaustion risk (no HTTP fetch) in one change, and is a copy of an already-proven pattern rather than new design work.

**Effort:** small — one method to change, reusing an existing helper and an existing caching pattern from the same codebase.

**Fix applied:** `registrationRows()` now eager-loads `student.tenant` and builds `photo_url` via a new `studentPhotoDataUri()` helper, which caches (`student-photo-thumb:{id}:{updated_at}`, 30 days) a call to `TenantStorage::photoBase64DataUri()`. An http(s)-URL pass-through guard was added so a student whose `photo` column already holds a full external URL (rather than a relative storage path) still renders correctly — `photoBase64DataUri()` deliberately refuses remote URLs, unlike the old `photoDataUri()`/`photoUrl()` path. `photo_url` key name kept unchanged so `attendance-sheet.blade.php` needed no edit (a data URI works fine in `<img src>`).

---

## 2. Bulk ID card PDFs — no downscaling, no caching on the photo path (High)

**Where:** `app/Services/Events/FestIdCardService.php::portraitDataUri()` (line 744), called from `individualStudentCards()` and `teamCards()` whenever `filters['include_data_uris']` is true — which `FestIdCardController::pdf()`, `pdfAllItems()`, and `pdfAllHeads()` all set (`app/Http/Controllers/SahodayaAdmin/FestIdCardController.php:100, 149, 196`).

**What's happening:** `portraitDataUri()` calls `TenantStorage::photoDataUri()` (`app/Support/TenantStorage.php:316`), **not** `photoBase64DataUri()`. The difference matters a lot here:
- `photoDataUri()` never downscales (no call to `shrinkImageForEmbed()`) and never caches.
- For a photo that happens to live on a non-local disk, it reads and base64-encodes the **original, full-resolution file** (up to ~2MB per the docblock's own numbers) — every single time the PDF is generated, for every participant.
- For a locally-stored photo it returns a bare filesystem path instead (cheaper), but that's the path the same docblock calls out as unreliable under DomPDF's chroot/symlink handling for mounted per-tenant storage — i.e. even the "cheap" branch here is documented elsewhere in this codebase as failure-prone, not just slow.

`pdfAllItems()`/`pdfAllHeads()` already got a memory/timeout bump in the earlier scale-fix round (`docs/SCALE_AND_PAGINATION_PLAN.md` §8a/§9-new) specifically because of this photo-embedding cost — but that fix only raised `memory_limit`/`max_execution_time`, it didn't touch the actual embedding code. The underlying per-participant full-resolution, uncached base64 encode is still there, on the single most participant-heavy PDF path in the ID-card feature (every item or every head in an event, in one PDF).

**Confirmed the same gap exists on the teacher side too:** `Teacher::photoDataUri()` (`app/Models/Teacher.php:165-174`) calls `TenantStorage::photoDataUri()` directly — same uncached, non-downscaled path, same fix needed.

**Recommended fix:** swap `portraitDataUri()`'s student branch and `Teacher::photoDataUri()` to call `TenantStorage::photoBase64DataUri()` with a small `maxDimension` (ID card photos are small on the printed card, same reasoning as the attendance-sheet thumbnail) and wrap both in the same `Cache::remember('student-photo-thumb:'.$id.':'.$updated_at, ...)` pattern already established. This is the same fix as #1, applied to two more call sites — worth doing together since it's the same root cause and the same fix.

**Effort:** small-medium — touches a shared helper used by both student and team cards; needs a quick check of `Teacher::photoDataUri()` for the same gap before calling it done.

**Fix applied:** `FestIdCardService::portraitDataUri()`'s student branch and `Teacher::photoDataUri()` both now call `TenantStorage::photoBase64DataUri()` wrapped in the same `Cache::remember('{student|teacher}-photo-thumb:{id}:{updated_at}', 30 days)` pattern, with the same http(s) pass-through guard as #1. Since both `individualStudentCards()`/`teamCards()` (student side) and `TrainingIdCardService` (teacher side, via `Teacher::photoDataUri()`) share these call sites, this one change fixes bulk Fest ID cards, single-item Fest ID cards, and Training ID cards at once.

---

## 3-4. ERP reports — per-receipt `loadMissing()` inside a loop, plus a duplicate call (Medium)

**Where:** `app/Services/Reports/ErpReportQueryService.php`:
- `waiverRegister()` (~line 217-240): calls `ProgramFeeReceiptService::schoolIdForReceipt($r)` **twice** per receipt — once inside a `->filter()` (line 227) and again inside the following `->map()` (line 233) — over an unbounded `FeeReceipt::where('status','approved')->where('waiver_amount','>',0)->get()` with no date/school bound.
- `dailyCollection()` (~line 243-279): same `schoolIdForReceipt()` call inside a `foreach` (line 257), over receipts in a date range (defaults to last 30 days, but can be widened by the report's own date filter with no cap).

**What's happening:** `schoolIdForReceipt()` (`app/Services/Fees/ProgramFeeReceiptService.php:241`) starts with `$receipt->loadMissing('feeable')`. The receipts collection feeding both callers is fetched with no `->with('feeable')` eager load, so each call to `loadMissing()` on an already-materialized individual model issues its own query for that one receipt's polymorphic `feeable` relation — Eloquent's collection-level eager loading doesn't apply here since these are per-model calls inside a loop, not a batch load. For `waiverRegister()`, calling it twice per receipt doubles this cost for no reason (the second call recomputes exactly what the first call already determined during filtering).

This is the same shape of bug the Sahodaya-wide `SchoolPaymentHistoryService::buildRows()` had (fixed in `SCALE_AND_PAGINATION_PLAN.md` §3) — a fee/receipt history report scanning every receipt across every school in a Sahodaya, at multi-year accumulation scale — except this is a sibling report engine (`ErpReportQueryService`, the general "RPT-*" report catalog) that wasn't touched by that fix.

**Recommended fix:** eager-load the polymorphic relation up front — `FeeReceipt::with('feeable')->...->get()` — so `loadMissing()` becomes a no-op, and remove the duplicate `schoolIdForReceipt()` call in `waiverRegister()`'s `->map()` by capturing the school ID once during the `->filter()` pass (e.g. via a keyed map or by mapping to `[$schoolId, $receipt]` pairs before filtering). Also worth adding a date-range default to `waiverRegister()` matching the convention `SCALE_AND_PAGINATION_PLAN.md` §3 Option A already established for the sibling service, since it currently has no bound at all.

**Effort:** small — mechanical eager-load addition plus removing one redundant call.

**Fix applied:** both methods now eager-load `feeable` (`->with('feeable')`) so `schoolIdForReceipt()`'s internal `loadMissing()` is a no-op. `waiverRegister()` was restructured to compute `schoolIdForReceipt()` exactly once per receipt (via a `['receipt' => ..., 'school_id' => ...]` pair, filtered once), and the per-row `Tenant::find()` for the school name was replaced with a single batched `Tenant::whereIn('id', ...)` lookup keyed by ID. `dailyCollection()` got the same eager-load; its loop structure was otherwise left as-is since it wasn't double-calling the resolver.

---

## 5. Document compliance report — one query per school (Low-Medium)

**Where:** `ErpReportQueryService::documentCompliance()` (~line 281-307). For every approved school in the Sahodaya, runs a separate `SchoolDocument::where('school_id', $school->id)->whereHas('documentType', ...)->get()` inside `$schools->map(...)`.

Bounded by school count (tens, not thousands, per the scale numbers in `SCALE_AND_PAGINATION_PLAN.md`), so this is the "Class A" category from `SCHOOL_REPORTS_PERFORMANCE_PLAN.md` §1 — real, but secondary, extra latency rather than a timeout risk. Fixable with one grouped query (`SchoolDocument::whereIn('school_id', $schoolIds)->with('documentType')->get()->groupBy('school_id')`) replacing the per-school fetch, same pattern as that doc's Class A fixes.

**Fix applied:** exactly the grouped query above, done before `$schools->map(...)`, grouped in PHP by `school_id`; each school's row now does an in-memory `$docsBySchool->get($school->id, collect())` lookup instead of its own query. Same output shape, same per-school filter/count logic — only the fetch changed.

---

## 6. Late-fee report — per-payment student count (Low-Medium)

**Where:** `ErpReportQueryService` (~line 192-214, the method backing the late-fee-collected report). Inside `MembershipPayment::...->get()->map(...)`, each row runs `Student::where('tenant_id', $p->school_id)->where('status','active')->count()`.

Bounded by the number of late-fee payment rows for one academic year (not per-student), so lower severity than #1/#2, but the same fix pattern applies: pre-compute a `school_id => active_student_count` map with one grouped query (`Student::whereIn('tenant_id', $schoolIds)->where('status','active')->groupBy('tenant_id')->selectRaw('tenant_id, count(*) as cnt')->pluck('cnt','tenant_id')`) before the loop.

**Fix applied:** exactly that grouped query, computed once before `->map()`; each row now reads `$activeStudentCounts[$p->school_id] ?? 0` instead of running its own `count()`.

---

## 7. School house points — event re-fetched per mark (Low-Medium)

**Where:** `app/Services/Events/SchoolHouseFestPointsService.php::rankingForSchool()` (line 27-51). `FestMark::query()->with(['participant.student', 'participant.registration.item'])->get()` is fetched correctly eager-loaded, but then `foreach ($marks as $mark) { $event = FestEvent::find($mark->event_id); ... }` re-queries the event for every mark. When called with a specific `$eventId` (the common case — a single event's house-points board), every mark shares the same `event_id`, so this is the same row being fetched from the database once per mark, repeatedly, for no reason — a school with a few hundred marked results means a few hundred identical queries.

**Fix:** fetch the event(s) once before the loop — `$events = FestEvent::whereIn('id', $marks->pluck('event_id')->unique())->get()->keyBy('id')` — and look up from that map inside the loop. When `$eventId` is already known (the typical call), this collapses to a single `FestEvent::find($eventId)` outside the loop entirely.

**Fix applied:** exactly that — `$events` built once, keyed by ID, before the `foreach`; the loop now does `$events->get($mark->event_id)` instead of `FestEvent::find($mark->event_id)`.

---

## 8. MCQ certificate bulk issue — redundant existence check (Low)

**Where:** `app/Services/Mcq/McqCertificateService.php::issueBulk()` (line 64-89) / `issue()` (line 38-62). `issueBulk()` already uses `chunkById(100)`, so this isn't unbounded — but for every registration in the chunk it runs `McqCertificate::where('registration_id', $registration->id)->exists()` (line 77) immediately before calling `issue()`, which itself runs the same `McqCertificate::where('registration_id', ...)->first()` (line 43) a moment later. That's two queries doing the same existence check per registration, doubled for no reason, across every registration being bulk-certified (could be thousands at Sahodaya-exam scale per the scale doc's numbers).

**Fix:** have `issue()` return whether the certificate was pre-existing (e.g. `return [$certificate, $wasExisting];` or check via a passed-in already-fetched set), removing the outer `exists()` call. Low priority since it's chunked and each check is a cheap indexed lookup, but free to fix while `issueBulk()` is next touched.

**Fix applied:** took the "passed-in already-fetched set" option — `issueBulk()` now pre-fetches every `registration_id` that already has a certificate for this exam in one query (`McqCertificate::whereHas('registration', fn ($q) => $q->where('exam_id', $exam->id))->pluck('registration_id')->flip()`), and the per-registration `$before` check inside the chunk loop is now an in-memory `$existingRegistrationIds->has($registration->id)` lookup instead of a second `McqCertificate` query. `issue()` itself is unchanged — it still does its own `first()` check before creating, which is the correctness-critical one; only the redundant *duplicate* check in the caller was removed.

---

## 9. Cross-tenant dashboard rollups — one query per Sahodaya (Low, structural)

**Where:** `app/Services/Events/StateDashboardService.php::clusterResultsRollup()` (line 66-122) and `clusterParticipationRollup()` (line 125+). Both loop over `FestStateProgramPropagation` rows and call `TenancyDatabase::runWhenDatabaseReady($propagation->sahodaya, fn () => FestEvent::find(...))` per propagation.

This *looks* like the same N+1 shape as everything above, but it isn't really fixable the same way: each Sahodaya has its own physical database in this multi-tenant architecture, so there's no single `whereIn()` that can span them — one connection switch per tenant is close to unavoidable here. It's bounded by the number of Sahodayas with a cluster event (tens, per the scale doc), not by student/participant count, so it's low severity. Flagging it only so it isn't mistaken for the same bug class as #3-#7 during any future cleanup pass — the fix, if one is ever justified, would be connection-pooling/parallelizing the tenant switches, not batching a query.

---

## What this audit did NOT re-check

Per the existing docs, these are already fixed and were spot-confirmed present in the current code during this pass, not re-audited in depth: `FestSchoolReportController::studentWiseLookups()` (the batched rewrite), `FestEventReportAnalyticsService`'s four batched report methods, `FestRegistrationRegisterService::build()`'s pagination, and `FestReportService`'s attendance-sheet photo caching (both the Sahodaya-admin and school-scoped versions — confirmed both now use `photoBase64DataUri()` wrapped in `Cache::remember()`, see lines ~704 and ~877-885).

Not covered by this pass at all (out of scope / not yet looked at): `BoardResultReportController` and its supporting services (`RankingEngine`, `AwardsEngine`, `AcademicExcellenceReportService`, `FullA1AchieversReportService`) beyond the light grep in the appendix below; `TrainingReportService`/`TrainingQrReportService`; the Vue/frontend side of any of these nine findings (none of them are payload-size problems needing pagination — they're all backend query-count or image-memory issues). A future fourth pass should treat those as the next candidates, following the same "built and tested at small scale" pattern this whole series of docs keeps re-finding.

---

## Files changed (3 Aug 2026 fix pass)

- `app/Services/Mcq/McqPrintableDocumentService.php` — `registrationRows()` eager-loads `student.tenant`; new `studentPhotoDataUri()` helper (cached, downscaled, http(s) pass-through guard).
- `app/Services/Events/FestIdCardService.php` — `portraitDataUri()` student branch switched to cached `photoBase64DataUri()` with the same pass-through guard.
- `app/Models/Teacher.php` — `photoDataUri()` switched to cached `photoBase64DataUri()` with the same pass-through guard.
- `app/Services/Reports/ErpReportQueryService.php` — `waiverRegister()`, `dailyCollection()`, `documentCompliance()`, `lateFeeCollected()` all batched/eager-loaded per their sections above.
- `app/Services/Events/SchoolHouseFestPointsService.php` — `rankingForSchool()` batches the event lookup.
- `app/Services/Mcq/McqCertificateService.php` — `issueBulk()` pre-fetches existing certificate IDs instead of querying per registration.

## Verification checklist (not yet run — no PHP/DB runtime in this environment)

- [x] Brace/paren balance check on every touched file (done via a quick Python count, all balanced).
- [ ] Confirm `TenantStorage::photoBase64DataUri()` and `Cache` facade resolve correctly at runtime (framework bootstrap wasn't available to verify this here).
- [ ] Spot-check MCQ attendance/mark/result sheet PDFs against a real exam with student photos — confirm images render and file size/generation time drop vs. before.
- [ ] Spot-check bulk Fest ID card PDF (`pdfAllItems`/`pdfAllHeads`) and a Training ID card against a real event/program with both local-disk and (if applicable) non-local-disk student/teacher photos.
- [ ] Diff each fixed ERP report's output (waiver register, daily collection, document compliance, late-fee) against its pre-fix output for one real Sahodaya — confirm row-for-row match, not just "runs without error."
- [ ] Confirm the house-points ranking board still shows correct totals for a school with mixed graded/ungraded marks.
- [ ] Confirm MCQ bulk certificate issuance still skips already-issued certificates and only counts newly-issued ones, for an exam that's had a partial certificate run before.
- [ ] Cache invalidation check: edit a student's/teacher's photo, regenerate the same report, confirm the *new* photo shows (the cache key includes `updated_at`, so this should self-invalidate — worth confirming once, live).
