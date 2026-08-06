# N+1 / Code-Issue Audit — Second Sweep (3 Aug 2026)

Follow-up to `docs/N1_AND_REPORT_MEMORY_AUDIT_2026_08_03.md` (whose 8 findings are already fixed — see that doc's Status line). This sweep covers the areas that doc explicitly flagged as "not covered": BoardResults services, Training services, and a broader pass over admin controllers not yet checked. Same caveat as every doc in this series: no PHP runtime or database available, code review only.

**Status: found, not yet fixed** — this is a findings-only doc; nothing below has been changed in code yet.

## Summary

| # | Finding | Class | File | Severity |
|---|---|---|---|---|
| 1 | Student registration-number generator re-scans every student's `reg_no` across the **entire Sahodaya** on every single call — called once per student during bulk CSV import and the reg-number backfill command | O(N²) query storm | `StudentRegistrationNumberGenerator::generate()`/`nextSequenceFor()` (+ identical logic duplicated in `BackfillStudentRegNumbers`) | **Critical** |
| 2 | Event-settings bulk-save loops run a SELECT + UPDATE per row instead of a batch update | N+1 (bounded, tens-hundreds) | `FestEventSettingsController` — `item_fees`/`head_fees` save (~line 550, 563), `bulkUpdateItemWindows()` (~line 1288) | Low-Medium |
| 3 | Awards-engine achievement sync does an existence check per award | N+1 (bounded, tiny) | `AwardsEngine::syncAchievements()` | Low |
| 4 | Student-history search re-queries subject marks per exam-group despite already eager-loading them | Redundant query (bounded, small) | `BoardResultStudentHistoryService::search()` | Low |

Areas checked and found clean (no N+1, no unbounded fetch, no unsafe photo embedding): `RankingEngine` (fully batches eager-loaded results, pure-PHP sorting), `SahodayaTopperSelectionService`, `FullA1AchieversReportService`, `TrainingReportService`, `TrainingQrReportService`, `TrainingIdCardService` (single-teacher download, and now benefits from the Teacher photo-caching fix in the first audit).

---

## 1. Student registration-number generator — full-Sahodaya re-scan per student (Critical)

**Where:** `app/Services/Students/StudentRegistrationNumberGenerator.php::generate()` (line 21) and its private `numberBase()`/sequence logic — called from `StudentRecordCreator::create()` (line 30), which itself is called once per row inside `StudentCsvImporter::importFromPath()`'s import loop (`app/Services/Students/StudentCsvImporter.php:93-111`). The **identical** re-scan logic is duplicated in `BackfillStudentRegNumbers::nextSequenceFor()` (`app/Console/Commands/BackfillStudentRegNumbers.php:83-101`), called once per student inside that command's `Student::query()->...->each(...)` loop (line 56-78).

**What's happening:** every call to `generate()` does:
1. `DB::transaction(...)` — a new transaction (nested inside the CSV importer's own outer transaction, or standalone in the backfill command).
2. `SahodayaProfile::where('tenant_id', $school->parent_id)->lockForUpdate()->first()` — a row lock, serializing every call against any other reg-no allocation for the same Sahodaya.
3. `Student::whereIn('tenant_id', $schoolIds)->whereNotNull('reg_no')->pluck('reg_no')` where `$schoolIds` is **every school in the Sahodaya**, not just the one being imported into — pulls every student's `reg_no` across the whole Sahodaya into memory, maps each one through a regex/parse, and takes the max.

That third step is the problem. It doesn't run once per import — it runs **once per student being created**, and each run re-scans the same, growing set of reg_no values. For a single school's CSV import of 3,000 students (the scale this codebase's own `SCALE_AND_PAGINATION_PLAN.md` explicitly sizes for — "up to ~3000 students PER SCHOOL... a 100-school Sahodaya can mean up to ~300,000 students total"), this means:
- Up to 3,000 separate transactions, each taking the same row lock.
- Each of those 3,000 calls pulls potentially hundreds of thousands of `reg_no` strings (every existing student across every school in the Sahodaya, not just the school being imported) into a PHP array, parses every one with a regex, and computes a max — repeating work that was already done microseconds earlier for the previous row.

This is worse in kind than the already-fixed `studentWise` N+1 from the first pass (~9,000 small, cheap queries) — this is closer to O(N²) row-scanning (N imported students × up to ~300,000 existing rows each), inside a single all-or-nothing DB transaction with a lock held across the whole import. At real scale this is a strong candidate for the import simply timing out, exhausting memory on the `pluck('reg_no')` step, or holding the Sahodaya-wide allocation lock long enough to block every other school's registration-number allocation for the duration.

**Confirmed not a one-off:** the exact same "re-scan everything, compute max, +1" logic is hand-duplicated (not shared) in `BackfillStudentRegNumbers::nextSequenceFor()`, called per student in that command's loop too — so this is a systemic property of how the reg_no sequence is designed, not a bug in one call site. Single-student creation paths (`StudentController`, `StudentApiController`, `StudentPortalProvisioner`) call the same `generate()`/`syncIdentity()` and are fine in isolation — one scan per one student creation is the intended cost, it's only pathological when looped.

**Recommended fix:** this needs a real design change, not a mechanical batch-query swap like the first audit's findings — the sequence must stay correct (no duplicate/skipped numbers) under concurrent allocation, which is presumably why it re-derives the max from data instead of trusting a cached counter. Two viable directions, in order of how much they preserve the existing correctness guarantee:
- **Compute the starting max once per import/backfill run, then increment in memory** for the rest of that run, only re-deriving from the database once per bulk operation instead of once per row. The `lockForUpdate()` on `SahodayaProfile` already serializes concurrent allocations across the whole Sahodaya, so it's safe to hold that lock for the entire bulk operation (one transaction, one lock, one initial scan) rather than re-acquiring it and re-scanning per row — this is the more surgical fix and doesn't change the underlying algorithm.
- **Add a real counter** (e.g. a `next_sequence` column on `SahodayaProfile` per academic year, incremented with `UPDATE ... SET next_sequence = next_sequence + 1 RETURNING`) instead of computing the max from existing rows at all — removes the re-scan entirely, but is a schema change and needs a backfill/migration to seed the counter from current data, so it's a bigger, separate piece of work.

Either way, this should be treated as the highest-priority item across both audit passes — everything in the first pass was a report/page-load cost; this one sits directly in the write path for onboarding a school's students, which is core product functionality, not a report nobody looks at often.

---

## 2. Event-settings bulk-save loops — SELECT + UPDATE per row (Low-Medium)

**Where:** `app/Http/Controllers/SahodayaAdmin/FestEventSettingsController.php`:
- Item-fees save (~line 550-561): `foreach ($data['item_fees'] ?? [] as $row) { $item = FestEventItem::where('event_id', $event->id)->find($row['id']); ... $item->update([...]); }`
- `bulkUpdateItemWindows()` (~line 1271-1306) — its own docblock says "Save every row... in one request instead of one PATCH per row," meaning the HTTP round-trip was already fixed, but the same loop still does `FestEventItem::where('event_id', $event->id)->find($row['id'])` (a SELECT) followed by `->update(...)` per row.

**What's happening:** two queries per item row in a settings-save form submission — bounded by item count in one event (tens, occasionally low hundreds per this codebase's own scale numbers, same bound as the "Class A" N+1 loops `SCHOOL_REPORTS_PERFORMANCE_PLAN.md` already fixed elsewhere). Not a timeout risk at that bound, but pure waste — the SELECT is unnecessary since `update()` could be issued directly against a `whereIn` filtered query, or the items could be pre-fetched once (`FestEventItem::where('event_id', $event->id)->whereIn('id', $ids)->get()->keyBy('id')`) before the loop to at least halve the query count.

**Recommended fix:** pre-fetch all relevant items in one `whereIn()` query before each loop, look up from that map instead of calling `find()` per row. A true single-query batch update (case/when SQL) is possible but not necessary at this bound — halving the query count via pre-fetch is enough.

---

## 3. Awards-engine achievement sync — existence check per award (Low)

**Where:** `app/Services/BoardResults/AwardsEngine.php::syncAchievements()` (line 275-333). `foreach ($awards as $award) { $existing = Achievement::query()->where('source_award_id', $award->id)->first(); ... }`.

Bounded by award count per Sahodaya per academic year (one per award type — best-academic-school, best-class-X, best-class-XII, best-science/commerce/humanities, most-subject-toppers, excellence — so single digits to low tens), and this only runs during the (infrequent) awards-recompute pipeline, not on every page load. Negligible in practice, but the same fix pattern applies if it's ever touched: `Achievement::whereIn('source_award_id', $awards->pluck('id'))->get()->keyBy('source_award_id')` once before the loop.

---

## 4. Student-history search — redundant subject-marks query per exam group (Low)

**Where:** `app/Services/BoardResults/BoardResultStudentHistoryService::search()` (line 88-100). The top-level query already does `Topper::query()->with(['boardResult', 'subjectMarks'])`, but inside the nested per-matched-student, per-exam-group loop, it runs `DB::table('topper_subject_marks')->whereIn('topper_id', $topperIds)->get()` again — data that was already eager-loaded per topper via the `subjectMarks` relation, just needing a re-group across the (small) set of toppers in that exam group instead of a fresh query.

Bounded by search result count (this is a name/roll-no/admission-no search — typically a handful of matches, not a roster), so low severity, but it's a clean, free fix: build the subject-marks lookup from the already-loaded `$rep->subjectMarks`/`$records->flatMap->subjectMarks` collections instead of re-querying `topper_subject_marks` by ID.

---

## What this sweep did NOT cover

`ErpReportQueryService`'s remaining report methods beyond the four fixed in the first pass (the file is ~930 lines and covers dozens of "RPT-*" report codes — only the ones actually exercising a `foreach`+query pattern were checked); `FestBulkRegistrationService`, `FestRegistrationImportService`, `FestRegistrationCreateService` (several `Student::find()`/`Teacher::where(...)->first()` calls inside loops were seen in the original grep sweep but are bounded by one registration's cart/team size, not a roster — likely fine, not re-verified in depth here); `KannurLegacyMembershipImporter` (a one-off legacy data migration tool, lower priority by nature); the Sports Vue frontend pages for any newly-introduced unpaginated tables since `SCHOOL_REPORTS_PERFORMANCE_PLAN.md` shipped. A third sweep, if wanted, should start with #1 above since it's the most severe finding across both passes, then pick up this list.
