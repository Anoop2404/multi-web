# Downloadable File Naming Standardization Plan

Scope: every user-facing download in the app — Kalolsavam/fest reports, ID cards, certificates, MCQ/Talent-Search exports, training-program reports, student/teacher registry exports, membership and payment reports, audit logs, public portal PDFs. Goal: every downloaded filename tells you, at a glance and without opening it, **what it is** (purpose), **which event/exam/program** it belongs to, and **which date** it's for — so a folder full of downloads stays sortable and identifiable months later.

This doc is the result of a full-codebase inventory (~90 download call sites across 12 modules) and defines the target convention, the shared helper that enforces it, and the file-by-file migration order. It does not cover circular/compliance-document downloads that serve an originally-uploaded file verbatim (`CircularAcknowledgementController`, `SchoolDocumentController`) — those correctly keep the uploader's original filename and are out of scope.

Status: **Not started.** This is the plan to review before implementation begins.

## 1. Why this matters specifically for this app

Kalolsavam events, MCQ exams, and training programs recur every year with the **same or near-identical title** ("Thrissur Sahodaya Kalolsavam"). Today's filenames (see §3) are almost all `{title-slug}-{report-type}.pdf` with **no date** — so `thrissur-sahodaya-kalolsavam-registration-list.pdf` downloaded in 2025 and the same file downloaded in 2026 are indistinguishable once both sit in a Downloads folder. That's the concrete failure mode this plan fixes, not just cosmetics.

## 2. Target convention

```
{purpose}_{subject}_{date}.{ext}
```

- **Segment separator is `_` (underscore)**; **within** a segment, words are hyphenated (`kebab-case`). This makes the three-part structure visually unambiguous and machine-parseable (`filename.split('_')`), which the current all-hyphen scheme (`{slug}-mark-entry-status.pdf`) is not — you can't tell where the event name ends and the report type begins.
- **`purpose`**: what the file is, e.g. `registration-list`, `id-cards-event-pass`, `mark-entry-status`, `certificates`, `attendance-sheet`. Reuses the existing suffix strings almost everywhere — this is the part of the current filenames that's already good.
- **`subject`**: the event/exam/program title slug, truncated to 40 chars, with the **school name appended when the report is school-scoped** (e.g. `thrissur-kalolsavam-2026_st-marys-hs`). For non-event registry/membership/ledger exports, subject is the school or Sahodaya name instead.
- **`date`**: the event's own date, not today's date, for anything event/exam/program-scoped — use `event->event_start` (or exam/program equivalent), formatted `Y-m-d`; fall back to `created_at` if a start date isn't set. For registry-wide, ledger, and audit exports (no single "event date"), keep the existing generation-date convention (`now()->format('Y-m-d')`), since those already do this correctly and there's no event date to prefer.
- Multi-file bundles that are inherently per-item/per-school/per-judge (attendance sheet for one school, judge sheet for one item) keep that identifier too, appended after `subject`: `{purpose}_{subject}_{identifier}_{date}.{ext}`.

Examples, before → after:

| Before | After |
|---|---|
| `thrissur-kalolsavam-registration-list.pdf` | `registration-list_thrissur-kalolsavam_2026-07-15.pdf` |
| `food-coupons-482.pdf` | `food-coupons_thrissur-kalolsavam_2026-07-15.pdf` |
| `mcq-leaderboard-17` | `leaderboard_ntse-talent-search-2026_2026-08-01.xlsx` |
| `training-attendance-9.xlsx` | `attendance_first-aid-training-2026_2026-09-10.xlsx` |
| `thrissur-kalolsavam-attendance-482.pdf` (school id) | `attendance-sheet_thrissur-kalolsavam_st-marys-hs_2026-07-15.pdf` |

Explicitly **not changing**: invoice downloads keyed by invoice/participant number (`demand-INV-2026-0042.pdf`) — the invoice number is the correct, already-unique identifier there; adding event+date would just make them longer without adding information. Same for original-upload passthrough downloads (circulars, compliance docs, payment proofs) — those must keep the filename the uploader recognizes.

## 3. Shared helper: `app/Support/ReportFilename.php`

Neither of the two existing shared classes (`PdfGenerator`, `ExcelExport`) builds filenames — they just accept a final string from the caller, which is why ~90 call sites each invented their own convention independently. Rather than teach `PdfGenerator`/`ExcelExport` about events, add one small static helper both layers call into:

```php
namespace App\Support;

class ReportFilename
{
    public static function build(
        string $purpose,
        string $subjectTitle,
        ?\DateTimeInterface $date = null,
        array $extra = [],       // e.g. school name, item id — appended in order
        string $ext = 'pdf',
    ): string {
        $parts = [
            str($purpose)->slug(),
            str($subjectTitle)->slug()->limit(40, ''),
            ...array_map(fn ($e) => str((string) $e)->slug(), $extra),
            $date?->format('Y-m-d') ?? now()->format('Y-m-d'),
        ];

        return implode('_', $parts) . '.' . ltrim($ext, '.');
    }
}
```

This is additive and non-breaking: nothing currently calls it, so introducing it changes no behavior until call sites are migrated one at a time. Each migration is a one-line change per method (swap the hand-built string for a `ReportFilename::build(...)` call), which keeps every phase below independently revertable.

## 4. Migration phases

Ordered by usage frequency (Kalolsavam is the core product) and by how many filenames a single service change fixes at once.

### Phase 1 — Helper + tests
Add `ReportFilename` (§3) and a focused unit test asserting: segment order, slug truncation, date formatting, default-to-today when no date given. No call sites touched yet.

### Phase 2 — `FestReportService` (27 methods, `app/Services/Events/FestReportService.php`)
Highest-leverage single file — one service backs the entire Sahodaya *and* School-Admin reports hub (`export($exportType, $request)` dispatcher reused by `FestSchoolReportController::export()`). Replace the private `slug()` helper's usage at all 27 call sites (`categoryWiseStudents`, `itemParticipants`, `studentWiseReport`, `registrationList`, `schoolWise`, `overallRanking`, `houseWise`, `itemList`, `itemWise`, `cumulative`, `dayWise`, `attendanceSheet`, `attendanceSheetSchool`, `judgeSheet`, `markEntrySheet`, `itemOrderPublic`, `greenRoomList`, `markEntryStatus`, `itemSchedule`, `clashSchool`, `promotionSheet`, `certificateCounts`, `catering`, `students`, `admitCards`, `overallRankingSahodaya`, `studentParticipation`) with `ReportFilename::build($purpose, $event->title, $event->event_start, [...school/item/judge id if present])`. Since this one service is shared by both admin surfaces, fixing it here fixes the school-scoped mirror automatically.

### Phase 3 — Fest export/analytics services
- `FestEventReportAnalyticsService.php` (lines ~911/933/950/1167/1188/1201/1214/1383/1489/1510, plus `team-squads.pdf`, `medal-tally.pdf`, ID-card builder ~1561-1569)
- `FestExportService.php` — already has a centralized `filename(FestEvent $event, string $type)` private helper; just repoint its body to call `ReportFilename::build()`, which fixes every caller of it in one edit
- `FestSchoolReportExportService.php`
- `FestRegistrationRegisterService.php:184`

### Phase 4 — Sahodaya Event-Head controllers
Fixes the two clearest bugs found in the inventory (uses raw numeric `$event->id` despite the event being fully loaded):
- `FestFoodCouponController.php:97-113` (`print()`) — `'food-coupons-'.$event->id.'.pdf'` → purpose `food-coupons`
- `FestEventFeesController.php:251-305` (`feeStatusPdf`, `exportPayments`)
- `FestMarkEntryController.php:303-414` — currently uses only item id, no event name at all
- `FestChestNumberController.php:223-244`
- `FestCertificateController.php:57-88` (`downloadZip`) — keep the temp-path scheme as is (deleted immediately, not user-visible), only change the second `download()` argument
- `FestIdCardController.php:87-231` (`pdf`, `pdfAllItems`, `pdfAllHeads`)
- `FestScheduleController.php`, `FestAttendanceController.php`, `FestRegistrationReviewController.php` import-template downloads — lower priority (generic templates, not per-run reports) but included since scope is "all downloaded files"
- `FestFinanceController.php` — leave invoice filenames as-is per §2

### Phase 5 — School-Admin mirror
`FestSchoolReportController.php` (~1400 lines: `idCardsPdf`, `idCardsPdfAllHeads`, `idCardsPdfAllItems`, `exportMarkEntryStatus` — `export()` itself is already fixed by Phase 2), `FestRegistrationController.php` (`eventInvoice` unchanged per §2, `importTemplate`), `FestFoodCouponController.php` (school variant, same id-only bug as Phase 4), `FestEventPortalController.php:155-187` (`downloadCertificatesZip`).

### Phase 6 — MCQ / Talent Search
`McqPrintableDocumentService.php` (`attendanceSheetPdf`, `markSheetPdf`, `resultSheetPdf`), `McqReportService.php` (~12 `ExcelExport::download` call sites), `McqExamController.php:610-628` (`exportLeaderboard` — currently `'mcq-leaderboard-'.$exam->id`, another id-only bug). `McqRegistrationInvoiceService` invoice filenames unchanged per §2.

### Phase 7 — Training programs
`TrainingReportService.php` — `exportAttendance()` and `exportRegistrations()` currently use `$program->id` only (id-only bug, matches Phase 4/6 pattern); `exportAttendanceSheetPdf`, `exportAttendanceReportPdf`, `exportRegistrationsPdf` already use title slug, just need the date segment added. `TrainingProgramController::exportCertificatesZip` (temp-path unchanged, download name gets date added). `TrainingIdCardService`, `TrainingQrReportService`.

### Phase 8 — Student Registry
`StudentController.php`/`TeacherController.php` (`export`, `exportPdf`) already include a generation date and school prefix — lowest-priority phase, just confirm purpose word is present at the front (`students-export_{school}_{date}` vs. current `{school}-students-{date}`, reorder to match the new convention for consistency, nothing else changes). `photoNamingList()`, `importTemplate()` are generic helper files, not per-event reports — leave as-is or reorder trivially for consistency.

### Phase 9 — Membership, Payments, Ledger, Audit
`MembershipReportsController.php`, `MemberSchoolsController.php`, `PaymentVerificationController.php`, `UnifiedPaymentsController.php`, `PaymentHistoryController.php`, `LedgerController.php`, `AuditLogController.php`, `LoginAuditController.php` — these are Sahodaya/school-scoped, not event-scoped, so `subject` = Sahodaya or school name, `date` = existing generation-date or date-range already present. Mostly a reordering pass to match segment convention rather than new information added.

### Phase 10 — Public Portal
`AcademicResultsPortalController.php` (`meritListPdf`), `FestPortalController.php` (`itemResultsPdf` — currently event not in filename at all despite being in scope). `McqArchiveController::download()` unchanged per §2 (serves original file).

## 5. Verification per phase

For each migrated method: add/update a feature test asserting the `Content-Disposition` header's filename matches the new pattern (regex: `^[a-z0-9-]+(_[a-z0-9-]+)*_\d{4}-\d{2}-\d{2}\.\w+$`), then manually download 2-3 representative reports from that phase in a browser to confirm the save-dialog filename looks right end to end (Laravel/DomPDF sometimes re-encodes special characters in `Content-Disposition` — worth eyeballing once per module rather than assuming the header assertion alone is sufficient). Run the phase's existing controller/service tests to confirm no other assertions hardcode the old filename string.

## 6. Rollout notes

- Ship one phase per PR — each phase is scoped to one service/controller family and is independently revertable, matching how `ReportFilename` was designed to be additive.
- `CsvExportDispatcher`'s async path stores the filename in the `ExportJob.filename` column at dispatch time; migrating any exporter that goes through it (large CSV exports) needs the new filename generated *before* the job is queued, same as today — no dispatcher change needed, just confirm the call site passes the new string in.
- No database migration, no user-facing behavior change beyond the filename string itself — safe to ship incrementally without a feature flag.
