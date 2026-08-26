<?php

namespace App\Support;

class FestReportCatalog
{
    /**
     * EVENT_REPORTS_FIX_TODO_2026_08_14.md Milestone 1.1 — school-safe export allowlist.
     *
     * exports() below is the SAME catalog used by both the Sahodaya-admin report
     * controller and FestSchoolReportController::export(). Its existing 'audience' field
     * ('staff'|'public'|'both') means "Sahodaya event staff vs the general public" — it
     * was never an authorization boundary between schools, and FestSchoolReportController
     * had no separate allowlist at all, so any school admin could request ANY export id
     * in this catalog by URL, including ones that return every school's registrations,
     * fees, marks, catering orders, volunteer rosters, and audit-log rows (2026-08-14
     * audit, P0 "School users can request cross-school fest exports").
     *
     * This list is deliberately an ALLOWLIST (fail closed), not a denylist: an export id
     * only reaches a school if it's named here. New export ids added to exports() are
     * therefore school-unreachable by default until someone deliberately reviews them and
     * adds them here — the safe direction for a mistake to fail in.
     *
     * Two categories are included:
     *   1. Exports fixed in this pass to accept/enforce $schoolId (registrations, results,
     *      fees, fee-breakdown, student-event-registrations — see FestExportService) or
     *      that already read $request->input('school_id') as a real filter, which
     *      FestSchoolReportController::export()'s $request->merge(['school_id' => ...])
     *      forces to the authenticated school (registration-list, category-wise-students,
     *      item-participants, student-wise-report, school-wise).
     *   2. Genuinely event-wide comparative/schedule data that isn't any one school's
     *      private information (rankings, medal tally, item schedule) — these were already
     *      the same for every viewer, so exposing them isn't the leak the audit found.
     *
     * Left OFF deliberately (audit-named unsafe, or unverified — see Milestone 1.2):
     * green-room-list, judge-sheet, mark-entry-sheet,
     * mark-entry-status (schools have a dedicated, already-scoped
     * FestSchoolReportController::exportMarkEntryStatus() for this instead), clashes,
     * promotions, promotions-pdf, certificate-counts, catering, volunteer-roster,
     * audit-log-extract, students, discipline-registration, fee-pending-schools,
     * admit-cards, id-cards-by-head, numbering-register, pending-approvals,
     * assignment-completeness, head-wise-participants, area-wise-participants,
     * team-squad-sheets, catering-by-school, attendance-sheet, attendance-sheet-school,
     * clashes-school, age-group-matrix — none of these were confirmed to filter by school
     * in this pass; re-verify and move up when they are.
     *
     * @var list<string>
     */
    public const SCHOOL_SAFE_EXPORT_IDS = [
        // Fixed in this pass to accept/enforce $schoolId (FestExportService).
        'registrations',
        'results',
        'fees',
        'fee-breakdown',
        'student-event-registrations',
        // Already read $request->input('school_id') as a real filter before this pass —
        // FestSchoolReportController::export()'s school_id merge forces these to the
        // authenticated school (see FestReportService::categoryWiseStudentsXls() etc).
        'registration-list',
        'category-wise-students',
        'item-participants',
        'student-wise-report',
        'school-wise',
        // Event-wide comparative/schedule data — the same for every viewer, not any one
        // school's private information.
        'overall-ranking',
        'house-wise',
        'item-list',
        'item-wise',
        'cumulative',
        'day-wise',
        'item-schedule',
        'item-schedule-pdf',
        'item-order-public',
        'sahodaya-ranking',
        'medal-tally',
        // Previously left off as unverified — now confirmed to filter to $schoolId when
        // given, and wired to actually receive it from FestSchoolReportController::export()'s
        // forced school_id (student-participation/age-group-matrix already did; a
        // school-scoping branch was added to certificateCountsCsv() for this pass — see
        // Documents/Fest_Improvements_Proposal.md §5.2).
        'student-participation',
        'certificate-counts',
        'age-group-matrix',
    ];

    public static function isSchoolSafe(string $exportId): bool
    {
        return in_array($exportId, self::SCHOOL_SAFE_EXPORT_IDS, true);
    }

    /** @return list<string> */
    public static function resultExportTypes(): array
    {
        return [
            'results', 'school-wise', 'overall-ranking', 'house-wise', 'item-wise',
            'cumulative', 'sahodaya-ranking', 'promotions', 'promotions-pdf', 'medal-tally',
        ];
    }

    /** @return list<array<string, mixed>> */
    public static function exports(string $tenantId, int $eventId): array
    {
        $base = "/sahodaya-admin/{$tenantId}/events/{$eventId}/reports/export";

        $exports = [
            ['id' => 'registration-list', 'label' => 'Registration Master List', 'format' => 'pdf', 'params' => ['school_id', 'class_group'], 'phase' => 'before', 'audience' => 'staff'],
            ['id' => 'registrations', 'label' => 'Registrations (spreadsheet)', 'format' => 'xls', 'params' => [], 'phase' => 'before', 'audience' => 'staff'],
            ['id' => 'category-wise-students', 'label' => 'Category-wise Student List', 'format' => 'xls', 'params' => ['school_id'], 'phase' => 'before', 'audience' => 'staff'],
            ['id' => 'item-participants', 'label' => 'Item-wise Participant List', 'format' => 'xls', 'params' => ['item_id', 'school_id'], 'phase' => 'before', 'audience' => 'staff'],
            ['id' => 'student-wise-report', 'label' => 'Student-wise Participation Report', 'format' => 'xls', 'params' => ['school_id'], 'phase' => 'before', 'audience' => 'staff'],
            ['id' => 'results', 'label' => 'Results (spreadsheet)', 'format' => 'xls', 'params' => [], 'phase' => 'after', 'audience' => 'staff'],
            ['id' => 'school-wise', 'label' => 'School-wise Detailed Results', 'format' => 'pdf', 'params' => ['school_id', 'class_group'], 'phase' => 'after', 'audience' => 'staff'],
            ['id' => 'overall-ranking', 'label' => 'Overall School Ranking', 'format' => 'pdf', 'params' => [], 'phase' => 'after', 'audience' => 'public'],
            ['id' => 'category-item-matrix-xls', 'label' => 'Category & Item-wise Consolidated Report (Excel)', 'format' => 'xls', 'params' => [], 'phase' => 'after', 'audience' => 'staff'],
            ['id' => 'category-item-matrix-pdf', 'label' => 'Category & Item-wise Consolidated Report (PDF)', 'format' => 'pdf', 'params' => [], 'phase' => 'after', 'audience' => 'staff'],
            ['id' => 'house-wise', 'label' => 'House-wise Results', 'format' => 'pdf', 'params' => [], 'phase' => 'after', 'audience' => 'public'],
            ['id' => 'item-list', 'label' => 'Item List & Registration Counts', 'format' => 'pdf', 'params' => [], 'phase' => 'before', 'audience' => 'staff'],
            ['id' => 'item-wise', 'label' => 'Item-wise Top Results', 'format' => 'pdf', 'params' => ['item_id', 'top_n'], 'phase' => 'after', 'audience' => 'public'],
            ['id' => 'cumulative', 'label' => 'Cumulative School Points', 'format' => 'pdf', 'params' => [], 'phase' => 'during', 'audience' => 'public'],
            ['id' => 'day-wise', 'label' => 'Day-wise Schedule', 'format' => 'pdf', 'params' => ['date', 'audience'], 'phase' => 'during', 'audience' => 'both'],
            ['id' => 'item-schedule', 'label' => 'Item Venue & Time Schedule', 'format' => 'csv', 'params' => ['date', 'stage_id'], 'phase' => 'before', 'audience' => 'staff'],
            ['id' => 'item-schedule-pdf', 'label' => 'Item Venue & Time Schedule (PDF)', 'format' => 'pdf', 'params' => ['date', 'stage_id'], 'phase' => 'before', 'audience' => 'staff'],
            ['id' => 'item-order-public', 'label' => 'Item Performance Order (public)', 'format' => 'pdf', 'params' => ['item_id'], 'phase' => 'during', 'audience' => 'public'],
            ['id' => 'green-room-list', 'label' => 'Green Room List (staff)', 'format' => 'pdf', 'params' => ['item_id'], 'phase' => 'during', 'audience' => 'staff'],
            ['id' => 'attendance-sheet', 'label' => 'Attendance Sheet (by item)', 'format' => 'pdf', 'params' => ['item_id', 'class_group', 'audience'], 'phase' => 'before', 'audience' => 'both'],
            ['id' => 'attendance-sheet-school', 'label' => 'Attendance Sheet (school pivot)', 'format' => 'pdf', 'params' => ['school_id'], 'phase' => 'before', 'audience' => 'staff'],
            ['id' => 'mark-entry-status', 'label' => 'Mark Entry Status', 'format' => 'csv', 'params' => [], 'phase' => 'during', 'audience' => 'staff'],
            ['id' => 'results-pending', 'label' => 'Results Pending (marks entered, not published)', 'format' => 'csv', 'params' => [], 'phase' => 'during', 'audience' => 'staff'],
            ['id' => 'absent-report', 'label' => 'Absent Participants', 'format' => 'csv', 'params' => ['school_id'], 'phase' => 'during', 'audience' => 'staff'],
            ['id' => 'clashes', 'label' => 'Schedule Clash Report', 'format' => 'csv', 'params' => ['school_id'], 'phase' => 'before', 'audience' => 'staff'],
            ['id' => 'clashes-school', 'label' => 'School Clash Report (PDF)', 'format' => 'pdf', 'params' => ['school_id'], 'phase' => 'before', 'audience' => 'staff'],
            ['id' => 'promotions', 'label' => 'Promoted Qualifiers', 'format' => 'csv', 'params' => [], 'phase' => 'after', 'audience' => 'staff'],
            ['id' => 'promotions-pdf', 'label' => 'Promotion Sheet', 'format' => 'pdf', 'params' => [], 'phase' => 'after', 'audience' => 'staff'],
            ['id' => 'fees', 'label' => 'Fee / Payment Report', 'format' => 'xls', 'params' => [], 'phase' => 'before', 'audience' => 'staff'],
            ['id' => 'fee-breakdown', 'label' => 'Sports Fee Breakdown (school / student / extra items)', 'format' => 'xls', 'params' => [], 'phase' => 'before', 'audience' => 'staff'],
            ['id' => 'student-event-registrations', 'label' => 'Student Event Registration Register', 'format' => 'xls', 'params' => [], 'phase' => 'before', 'audience' => 'staff'],
            ['id' => 'certificate-counts', 'label' => 'Certificate Counts by School', 'format' => 'csv', 'params' => ['school_id'], 'phase' => 'after', 'audience' => 'staff'],
            ['id' => 'catering', 'label' => 'Food / Catering Orders', 'format' => 'csv', 'params' => [], 'phase' => 'during', 'audience' => 'staff'],
            ['id' => 'catering-by-school', 'label' => 'Catering Summary by School', 'format' => 'xls', 'params' => ['school_id'], 'phase' => 'during', 'audience' => 'staff'],
            ['id' => 'volunteer-roster', 'label' => 'Volunteer Roster', 'format' => 'csv', 'params' => [], 'phase' => 'during', 'audience' => 'staff'],
            ['id' => 'id-cards-by-head', 'label' => 'ID Card Print Pack (by item head)', 'format' => 'pdf', 'params' => ['head_id', 'school_id'], 'phase' => 'before', 'audience' => 'staff'],
            ['id' => 'audit-log-extract', 'label' => 'Event Audit Log Extract', 'format' => 'csv', 'params' => [], 'phase' => 'during', 'audience' => 'staff'],
            ['id' => 'students', 'label' => 'All Students (member schools)', 'format' => 'csv', 'params' => [], 'phase' => 'before', 'audience' => 'staff'],
            ['id' => 'admit-cards', 'label' => 'Admit Cards (bulk PDF)', 'format' => 'pdf', 'params' => ['school_id'], 'phase' => 'before', 'audience' => 'staff'],
            ['id' => 'sahodaya-ranking', 'label' => 'Sahodaya School Ranking', 'format' => 'pdf', 'params' => [], 'phase' => 'after', 'audience' => 'public'],
            ['id' => 'student-participation', 'label' => 'Student Participation', 'format' => 'xls', 'params' => ['school_id', 'class_group'], 'phase' => 'before', 'audience' => 'staff'],
            ['id' => 'discipline-registration', 'label' => 'Discipline-wise Registration', 'format' => 'xls', 'params' => [], 'phase' => 'before', 'audience' => 'staff'],
            ['id' => 'age-group-matrix', 'label' => 'Age Group Matrix (schools × age)', 'format' => 'xls', 'params' => ['school_id'], 'phase' => 'before', 'audience' => 'staff'],
            ['id' => 'fee-pending-schools', 'label' => 'Schools with Pending Fees', 'format' => 'xls', 'params' => [], 'phase' => 'before', 'audience' => 'staff'],
            ['id' => 'head-wise-participants', 'label' => 'Head-wise Participant List', 'format' => 'xls', 'params' => ['head_id'], 'phase' => 'before', 'audience' => 'staff'],
            ['id' => 'area-wise-participants', 'label' => 'Area-wise Participant List', 'format' => 'xls', 'params' => ['area_id'], 'phase' => 'before', 'audience' => 'staff'],
            ['id' => 'team-squad-sheets', 'label' => 'Team / Group Squad Sheets', 'format' => 'pdf', 'params' => ['school_id'], 'phase' => 'before', 'audience' => 'staff'],
            ['id' => 'assignment-completeness', 'label' => 'Assignment Completeness', 'format' => 'xls', 'params' => ['school_id'], 'phase' => 'before', 'audience' => 'staff'],
            ['id' => 'numbering-register', 'label' => 'Numbering Register (Fest ID / chest / item reg)', 'format' => 'xls', 'params' => ['school_id'], 'phase' => 'before', 'audience' => 'staff'],
            ['id' => 'pending-approvals', 'label' => 'Pending Approval Register', 'format' => 'xls', 'params' => ['school_id'], 'phase' => 'before', 'audience' => 'staff'],
            ['id' => 'medal-tally', 'label' => 'Medal Tally by School', 'format' => 'pdf', 'params' => [], 'phase' => 'after', 'audience' => 'public'],
        ];

        return array_map(
            fn (array $exp) => array_merge($exp, self::scopeMetadata($exp['id']), ['href' => "{$base}/{$exp['id']}"]),
            $exports,
        );
    }

    /**
     * Report-scope metadata per remediation plan §4.3: dataset/report family, which
     * FestReportScope modes each export supports, and whether it accepts a named
     * competition-phase filter. Deliberately kept separate from the exports() list
     * above rather than inlined into every row, so FestReportCatalogMetadataTest can
     * assert every catalog id has an entry here (§4.3: "unknown or incomplete metadata
     * must fail a catalog contract test") without a 51-line diff every time a label or
     * href changes.
     *
     * Dataset families follow the §3.5 table:
     *   registration — registration/participants/attendance/numbering
     *   schedule     — schedule/mark-entry status
     *   results      — results/ranking/medal tally
     *   finance      — fee/payment
     *   catering     — food/catering
     *   audit        — audit/volunteer extracts
     *   catalog      — item/catalog configuration, not registration rows
     *
     * @return array{dataset: string, supported_scopes: list<string>, supports_competition_phase: bool}
     */
    public static function scopeMetadata(string $exportId): array
    {
        return self::SCOPE_METADATA[$exportId] ?? [
            // Deliberately absent from SCOPE_METADATA rather than defaulted here — an
            // export id missing below should fail FestReportCatalogMetadataTest, not
            // silently get a guessed dataset.
        ];
    }

    /** @var array<string, array{dataset: string, supported_scopes: list<string>, supports_competition_phase: bool}> */
    private const SCOPE_METADATA = [
        'registration-list'            => ['dataset' => 'registration', 'supported_scopes' => ['self', 'combined', 'region'], 'supports_competition_phase' => true],
        'registrations'                 => ['dataset' => 'registration', 'supported_scopes' => ['self', 'combined', 'region'], 'supports_competition_phase' => true],
        'category-wise-students'        => ['dataset' => 'registration', 'supported_scopes' => ['self', 'combined', 'region'], 'supports_competition_phase' => true],
        'item-participants'             => ['dataset' => 'registration', 'supported_scopes' => ['self', 'combined', 'region'], 'supports_competition_phase' => true],
        'student-wise-report'           => ['dataset' => 'registration', 'supported_scopes' => ['self', 'combined', 'region'], 'supports_competition_phase' => true],
        'results'                        => ['dataset' => 'results', 'supported_scopes' => ['self', 'combined', 'region'], 'supports_competition_phase' => true],
        'school-wise'                   => ['dataset' => 'results', 'supported_scopes' => ['self', 'combined', 'region'], 'supports_competition_phase' => true],
        'overall-ranking'               => ['dataset' => 'results', 'supported_scopes' => ['self', 'combined', 'region'], 'supports_competition_phase' => false],
        'category-item-matrix-xls'      => ['dataset' => 'results', 'supported_scopes' => ['self', 'combined', 'region'], 'supports_competition_phase' => false],
        'category-item-matrix-pdf'      => ['dataset' => 'results', 'supported_scopes' => ['self', 'combined', 'region'], 'supports_competition_phase' => false],
        'house-wise'                    => ['dataset' => 'results', 'supported_scopes' => ['self', 'combined', 'region'], 'supports_competition_phase' => false],
        'item-list'                      => ['dataset' => 'catalog', 'supported_scopes' => ['self', 'combined', 'region'], 'supports_competition_phase' => true],
        'item-wise'                     => ['dataset' => 'results', 'supported_scopes' => ['self', 'combined', 'region'], 'supports_competition_phase' => true],
        'cumulative'                    => ['dataset' => 'results', 'supported_scopes' => ['self', 'combined', 'region'], 'supports_competition_phase' => false],
        'day-wise'                      => ['dataset' => 'schedule', 'supported_scopes' => ['self', 'combined', 'region'], 'supports_competition_phase' => true],
        'item-schedule'                 => ['dataset' => 'schedule', 'supported_scopes' => ['self', 'combined', 'region'], 'supports_competition_phase' => true],
        'item-schedule-pdf'             => ['dataset' => 'schedule', 'supported_scopes' => ['self', 'combined', 'region'], 'supports_competition_phase' => true],
        'item-order-public'             => ['dataset' => 'schedule', 'supported_scopes' => ['self', 'combined', 'region'], 'supports_competition_phase' => true],
        'green-room-list'                => ['dataset' => 'schedule', 'supported_scopes' => ['self', 'region'], 'supports_competition_phase' => true],
        'attendance-sheet'               => ['dataset' => 'registration', 'supported_scopes' => ['self', 'combined', 'region'], 'supports_competition_phase' => true],
        'attendance-sheet-school'       => ['dataset' => 'registration', 'supported_scopes' => ['self', 'region'], 'supports_competition_phase' => true],
        'mark-entry-status'             => ['dataset' => 'schedule', 'supported_scopes' => ['self', 'combined', 'region'], 'supports_competition_phase' => true],
        'results-pending'               => ['dataset' => 'results', 'supported_scopes' => ['self', 'combined', 'region'], 'supports_competition_phase' => true],
        'absent-report'                 => ['dataset' => 'registration', 'supported_scopes' => ['self', 'combined', 'region'], 'supports_competition_phase' => true],
        'clashes'                        => ['dataset' => 'schedule', 'supported_scopes' => ['self', 'combined', 'region'], 'supports_competition_phase' => false],
        'clashes-school'                => ['dataset' => 'schedule', 'supported_scopes' => ['self', 'region'], 'supports_competition_phase' => false],
        'promotions'                    => ['dataset' => 'results', 'supported_scopes' => ['self', 'combined', 'region'], 'supports_competition_phase' => true],
        'promotions-pdf'                => ['dataset' => 'results', 'supported_scopes' => ['self', 'combined', 'region'], 'supports_competition_phase' => true],
        'fees'                           => ['dataset' => 'finance', 'supported_scopes' => ['self', 'combined', 'region'], 'supports_competition_phase' => false],
        'fee-breakdown'                 => ['dataset' => 'finance', 'supported_scopes' => ['self', 'combined', 'region'], 'supports_competition_phase' => false],
        'student-event-registrations'   => ['dataset' => 'registration', 'supported_scopes' => ['self', 'combined', 'region'], 'supports_competition_phase' => true],
        'certificate-counts'            => ['dataset' => 'registration', 'supported_scopes' => ['self', 'combined', 'region'], 'supports_competition_phase' => false],
        'catering'                      => ['dataset' => 'catering', 'supported_scopes' => ['self', 'combined', 'region'], 'supports_competition_phase' => false],
        'catering-by-school'            => ['dataset' => 'catering', 'supported_scopes' => ['self', 'region'], 'supports_competition_phase' => false],
        'volunteer-roster'              => ['dataset' => 'audit', 'supported_scopes' => ['self', 'combined', 'region'], 'supports_competition_phase' => false],
        'id-cards-by-head'              => ['dataset' => 'registration', 'supported_scopes' => ['self', 'region'], 'supports_competition_phase' => false],
        'audit-log-extract'             => ['dataset' => 'audit', 'supported_scopes' => ['self', 'combined', 'region'], 'supports_competition_phase' => false],
        'students'                       => ['dataset' => 'catalog', 'supported_scopes' => ['self', 'combined', 'region'], 'supports_competition_phase' => false],
        'admit-cards'                    => ['dataset' => 'registration', 'supported_scopes' => ['self', 'region'], 'supports_competition_phase' => false],
        'sahodaya-ranking'              => ['dataset' => 'results', 'supported_scopes' => ['self', 'combined'], 'supports_competition_phase' => false],
        'student-participation'         => ['dataset' => 'registration', 'supported_scopes' => ['self', 'combined', 'region'], 'supports_competition_phase' => true],
        'discipline-registration'       => ['dataset' => 'registration', 'supported_scopes' => ['self', 'combined', 'region'], 'supports_competition_phase' => false],
        'age-group-matrix'              => ['dataset' => 'registration', 'supported_scopes' => ['self', 'combined', 'region'], 'supports_competition_phase' => false],
        'fee-pending-schools'           => ['dataset' => 'finance', 'supported_scopes' => ['self', 'combined', 'region'], 'supports_competition_phase' => false],
        'head-wise-participants'        => ['dataset' => 'registration', 'supported_scopes' => ['self', 'combined', 'region'], 'supports_competition_phase' => true],
        'area-wise-participants'        => ['dataset' => 'registration', 'supported_scopes' => ['self', 'combined', 'region'], 'supports_competition_phase' => true],
        'team-squad-sheets'             => ['dataset' => 'registration', 'supported_scopes' => ['self', 'region'], 'supports_competition_phase' => true],
        'assignment-completeness'       => ['dataset' => 'registration', 'supported_scopes' => ['self', 'combined', 'region'], 'supports_competition_phase' => true],
        'numbering-register'            => ['dataset' => 'registration', 'supported_scopes' => ['self', 'combined', 'region'], 'supports_competition_phase' => true],
        'pending-approvals'             => ['dataset' => 'registration', 'supported_scopes' => ['self', 'combined', 'region'], 'supports_competition_phase' => true],
        'medal-tally'                   => ['dataset' => 'results', 'supported_scopes' => ['self', 'combined', 'region'], 'supports_competition_phase' => false],
    ];

    /** @return list<array<string, string>> */
    public static function interactivePages(string $tenantId, int $eventId, ?string $eventType = null): array
    {
        $base = "/sahodaya-admin/{$tenantId}/events/{$eventId}/reports";

        $pages = [
            ['id' => 'head-wise-participants', 'label' => 'Head-wise Participants', 'href' => "{$base}/head-wise-participants"],
            ['id' => 'area-wise-participants', 'label' => 'Area-wise Participants', 'href' => "{$base}/area-wise-participants"],
            ['id' => 'school-detailed', 'label' => 'School Detailed Results', 'href' => "{$base}/school-detailed"],
            ['id' => 'overall-ranking', 'label' => 'Overall Ranking', 'href' => "{$base}/overall-ranking"],
            ['id' => 'house-detailed', 'label' => 'House Detailed', 'href' => "{$base}/house-detailed"],
            ['id' => 'participation-counts', 'label' => 'Participation Counts', 'href' => "{$base}/participation-counts"],
            ['id' => 'registration-register', 'label' => 'Registration & Fees Register', 'href' => "{$base}/registration-register"],
            ['id' => 'mark-entry-status', 'label' => 'Mark Entry Status', 'href' => "{$base}/mark-entry-status"],
            ['id' => 'results-pending', 'label' => 'Results Pending', 'href' => "{$base}/results-pending"],
            ['id' => 'absent-report', 'label' => 'Absent Report', 'href' => "{$base}/absent-report"],
            ['id' => 'item-schedule', 'label' => 'Venue & time schedule', 'href' => "{$base}/item-schedule"],
            ['id' => 'schedule-clashes', 'label' => 'Schedule Clashes', 'href' => "{$base}/schedule-clashes"],
            ['id' => 'item-counts', 'label' => 'Item Registration Counts', 'href' => "{$base}/item-counts"],
            ['id' => 'assignment-completeness', 'label' => 'Assignment Completeness', 'href' => "{$base}/assignment-completeness"],
            ['id' => 'numbering-register', 'label' => 'Numbering Register', 'href' => "{$base}/numbering-register"],
            ['id' => 'pending-approvals', 'label' => 'Pending Approvals', 'href' => "{$base}/pending-approvals"],
            ['id' => 'discipline-registration', 'label' => 'Discipline Registration', 'href' => "{$base}/discipline-registration"],
            ['id' => 'age-group-matrix', 'label' => 'Age Group Matrix', 'href' => "{$base}/age-group-matrix"],
            ['id' => 'fee-collection', 'label' => 'Fee Collection', 'href' => "{$base}/fee-collection"],
            ['id' => 'student-wise', 'label' => 'Student-wise browser', 'href' => "{$base}/student-wise"],
            ['id' => 'item-wise', 'label' => 'Item-wise browser', 'href' => "{$base}/item-wise"],
            ['id' => 'category-wise-points', 'label' => 'Category-wise Points', 'href' => "{$base}/category-wise-points"],
            ['id' => 'category-item-matrix', 'label' => 'Category & Item-wise Consolidated Report', 'href' => "{$base}/category-item-matrix"],
            ['id' => 'attendance', 'label' => 'Attendance Register', 'href' => "/sahodaya-admin/{$tenantId}/events/{$eventId}/attendance"],
            ['id' => 'id-cards', 'label' => 'Participant ID Cards', 'href' => "/sahodaya-admin/{$tenantId}/events/{$eventId}/id-cards"],
            ['id' => 'games-entry-form', 'label' => 'Entry Form', 'href' => "/school-admin/{$tenantId}/sports/events/{$eventId}/games-entry-form"],
        ];

        if ($eventType === 'sports') {
            $pages = array_values(array_filter($pages, fn ($p) => $p['id'] !== 'area-wise-participants'));
        } else {
            $pages = array_values(array_filter($pages, fn ($p) => !in_array($p['id'], ['house-detailed', 'games-entry-form'], true)));
        }

        return $pages;
    }

    /**
     * Interactive/export ids whose builders read $event->reportableEventIds()/
     * reportableItemIds() in a way that, run directly on a region-partition child event,
     * pulls in the hub's own uncopied item/registration rows alongside the child's own —
     * not full cross-region combination, but not correctly isolated either. Confirmed by
     * a manual audit (see docs/REGION_PHASE_EVENT_REPORTING_REMEDIATION_PLAN.md Phase 1
     * completion notes): originally Item Counts, Head-wise Participants, Discipline
     * Registration, Mark Entry Status, Schedule/Clashes, and Assignment Completeness;
     * fee/payment and attendance were added shortly after when the same issue was found
     * there. A second pass (2026-08-14) found and fixed the identical issue in School
     * Detailed, House Detailed, Participation Counts, Area-wise Participants, Age Group
     * Matrix, Item Schedule, Numbering Register, Pending Approvals, Student-wise, and
     * Item-wise — those ids are included below now too. Only ids in this list get
     * rerouted through the parent hub + region_id by regionScopedRows() below (and
     * through regionAwareTargetEvent() by FestReportController::export()'s generic
     * dispatcher); every other report keeps linking to the child event's own id/URL (its
     * existing, working behavior) rather than blanket-rerouting everything, which would
     * regress reports that don't understand region_id into showing fully combined data
     * unlabeled.
     *
     * @var list<string>
     */
    public const REGION_ID_AWARE_IDS = [
        'item-counts', 'item-list',
        'head-wise-participants',
        'discipline-registration',
        'mark-entry-status',
        'results-pending', 'absent-report',
        'schedule-clashes', 'clashes', 'clashes-school',
        'assignment-completeness',
        // Payment/fee report — same reportableEventIds() resolution issue, found when
        // auditing the six above.
        'fee-collection', 'fees', 'fee-breakdown', 'fee-pending-schools',
        // Attendance — same issue (FestReportController::attendance() route lives on
        // FestEventOpsController / a dedicated attendance controller, not this file; its
        // own reportAwareTargetEvent()-equivalent fix is applied there — see
        // FestAttendanceController).
        'attendance', 'attendance-sheet', 'attendance-sheet-school',
        // Second retrofit pass (2026-08-14): same reportableEventIds()/reportableItemIds()
        // issue found in the remaining FestReportController interactive pages and their
        // catalog-driven exports. Both the interactivePages() id and the exports() id are
        // listed for each report where the two catalogs use different ids for the same
        // report (school-detailed page vs. school-wise export, etc.) — regionScopedRows()
        // is applied separately to each catalog, so both need to match this one flat list.
        'school-detailed', 'school-wise',
        'house-detailed', 'house-wise',
        'participation-counts', 'student-participation',
        'area-wise-participants', 'age-group-matrix',
        'item-schedule', 'item-schedule-pdf',
        'student-wise', 'student-wise-report',
        'item-wise', 'item-participants',
        'numbering-register', 'pending-approvals',
    ];

    /**
     * Build a region child's interactive-page or export rows: mostly the child event's
     * own routes (unchanged, existing behavior), except for REGION_ID_AWARE_IDS, which
     * are rerouted to the parent hub's own route with an explicit region_id instead —
     * see REGION_ID_AWARE_IDS' docblock for why.
     *
     * @param  list<array<string, mixed>>  $childRows  Rows already built with the child event's own id (e.g. interactivePages($tenantId, $child->id, ...)).
     * @param  list<array<string, mixed>>  $hubRowsWithRegionParam  The same catalog built with the hub's id, already passed through withRegionParam().
     * @return list<array<string, mixed>>
     */
    public static function regionScopedRows(array $childRows, array $hubRowsWithRegionParam): array
    {
        $hubById = collect($hubRowsWithRegionParam)->keyBy('id');

        return array_map(function (array $row) use ($hubById) {
            if (in_array($row['id'], self::REGION_ID_AWARE_IDS, true) && $hubById->has($row['id'])) {
                return $hubById->get($row['id']);
            }

            return $row;
        }, $childRows);
    }

    /**
     * Append region_id=X to every href/previewHref in a list of catalog rows —
     * used to point a region section's tiles at the parent hub's own routes with an
     * explicit region filter, instead of building the tiles from the child event's own
     * id. Region-admin fix for the "region tiles link to /events/{childId}/..." bug:
     * several report builders (item counts, head-wise participants, discipline
     * registration, mark-entry status, schedule clashes, assignment completeness) read
     * FestEvent::reportableEventIds()/reportableItemIds() off whichever $event the route
     * is bound to — accessed via the child's own id, that pulls in the *hub's* original
     * (uncopied) item/registration rows alongside the child's, which is a different but
     * related correctness bug to the parent-hub containment issue Phase 1 fixed. Routing
     * through the hub with an explicit region_id (the same pattern already used for
     * Registration Register and Overall Ranking) sidesteps it entirely rather than
     * chasing each report's own event-id resolution individually.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    public static function withRegionParam(array $rows, int $regionId): array
    {
        $append = function (string $href) use ($regionId): string {
            $sep = str_contains($href, '?') ? '&' : '?';

            return "{$href}{$sep}region_id={$regionId}";
        };

        return array_map(function (array $row) use ($append) {
            if (isset($row['href'])) {
                $row['href'] = $append($row['href']);
            }
            if (isset($row['previewHref'])) {
                $row['previewHref'] = $append($row['previewHref']);
            }

            return $row;
        }, $rows);
    }

    /** Interactive preview page id for a bulk export type, if one exists. */
    public static function previewPageForExport(string $exportId): ?string
    {
        return match ($exportId) {
            'school-wise' => 'school-detailed',
            'overall-ranking' => 'overall-ranking',
            'category-item-matrix-xls', 'category-item-matrix-pdf' => 'category-item-matrix',
            'house-wise' => 'house-detailed',
            'item-list' => 'item-counts',
            'mark-entry-status' => 'mark-entry-status',
            'results-pending' => 'results-pending',
            'absent-report' => 'absent-report',
            'clashes', 'clashes-school' => 'schedule-clashes',
            'item-schedule', 'item-schedule-pdf' => 'item-schedule',
            'student-participation' => 'participation-counts',
            'discipline-registration' => 'discipline-registration',
            'age-group-matrix' => 'age-group-matrix',
            'fees', 'fee-pending-schools', 'fee-breakdown' => 'fee-collection',
            'student-event-registrations', 'registrations' => 'registration-register',
            'student-wise-report' => 'student-wise',
            'item-participants', 'item-wise' => 'item-wise',
            'results' => 'overall-ranking',
            'head-wise-participants' => 'head-wise-participants',
            'area-wise-participants' => 'area-wise-participants',
            'assignment-completeness' => 'assignment-completeness',
            'numbering-register' => 'numbering-register',
            'pending-approvals' => 'pending-approvals',
            'attendance-sheet', 'attendance-sheet-school' => 'attendance',
            'id-cards-by-head', 'admit-cards' => 'id-cards',
            default => null,
        };
    }

    /** Enrich export rows with optional preview href for Downloads UI. */
    public static function exportsWithPreview(string $tenantId, int $eventId): array
    {
        $reportsBase = "/sahodaya-admin/{$tenantId}/events/{$eventId}/reports";

        return array_map(function (array $exp) use ($reportsBase) {
            $previewId = self::previewPageForExport($exp['id']);
            if ($previewId) {
                $exp['previewHref'] = "{$reportsBase}/{$previewId}";
            }

            return $exp;
        }, self::exports($tenantId, $eventId));
    }
}
