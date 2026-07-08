<?php

namespace App\Support;

class FestReportCatalog
{
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
            ['id' => 'house-wise', 'label' => 'House-wise Results', 'format' => 'pdf', 'params' => [], 'phase' => 'after', 'audience' => 'public'],
            ['id' => 'item-list', 'label' => 'Item List & Registration Counts', 'format' => 'pdf', 'params' => [], 'phase' => 'before', 'audience' => 'staff'],
            ['id' => 'item-wise', 'label' => 'Item-wise Top Results', 'format' => 'pdf', 'params' => ['item_id', 'top_n'], 'phase' => 'after', 'audience' => 'public'],
            ['id' => 'cumulative', 'label' => 'Cumulative School Points', 'format' => 'pdf', 'params' => [], 'phase' => 'during', 'audience' => 'public'],
            ['id' => 'day-wise', 'label' => 'Day-wise Schedule', 'format' => 'pdf', 'params' => ['date', 'audience'], 'phase' => 'during', 'audience' => 'both'],
            ['id' => 'item-schedule', 'label' => 'Item Venue & Time Schedule', 'format' => 'csv', 'params' => ['date', 'stage_id'], 'phase' => 'before', 'audience' => 'staff'],
            ['id' => 'item-schedule-pdf', 'label' => 'Item Venue & Time Schedule (PDF)', 'format' => 'pdf', 'params' => ['date', 'stage_id'], 'phase' => 'before', 'audience' => 'staff'],
            ['id' => 'item-order-public', 'label' => 'Item Performance Order (public)', 'format' => 'pdf', 'params' => ['item_id'], 'phase' => 'during', 'audience' => 'public'],
            ['id' => 'green-room-list', 'label' => 'Green Room List (staff)', 'format' => 'pdf', 'params' => ['item_id'], 'phase' => 'during', 'audience' => 'staff'],
            ['id' => 'attendance-sheet', 'label' => 'Attendance Sheet (by item)', 'format' => 'pdf', 'params' => ['item_id', 'class_group', 'audience'], 'phase' => 'during', 'audience' => 'both'],
            ['id' => 'attendance-sheet-school', 'label' => 'Attendance Sheet (school pivot)', 'format' => 'pdf', 'params' => ['school_id'], 'phase' => 'during', 'audience' => 'staff'],
            ['id' => 'judge-sheet', 'label' => 'Judge Evaluation Sheet', 'format' => 'pdf', 'params' => ['item_id', 'audience'], 'phase' => 'during', 'audience' => 'both'],
            ['id' => 'mark-entry-sheet', 'label' => 'Mark Entry Sheet', 'format' => 'pdf', 'params' => ['item_id', 'audience'], 'phase' => 'during', 'audience' => 'both'],
            ['id' => 'mark-entered-summary', 'label' => 'Mark-entered Summary', 'format' => 'xls', 'params' => [], 'phase' => 'during', 'audience' => 'staff'],
            ['id' => 'mark-entry-status', 'label' => 'Mark Entry Status', 'format' => 'csv', 'params' => [], 'phase' => 'during', 'audience' => 'staff'],
            ['id' => 'clashes', 'label' => 'Schedule Clash Report', 'format' => 'csv', 'params' => ['school_id'], 'phase' => 'before', 'audience' => 'staff'],
            ['id' => 'clashes-school', 'label' => 'School Clash Report (PDF)', 'format' => 'pdf', 'params' => ['school_id'], 'phase' => 'before', 'audience' => 'staff'],
            ['id' => 'promotions', 'label' => 'Promoted Qualifiers', 'format' => 'csv', 'params' => [], 'phase' => 'after', 'audience' => 'staff'],
            ['id' => 'promotions-pdf', 'label' => 'Promotion Sheet', 'format' => 'pdf', 'params' => [], 'phase' => 'after', 'audience' => 'staff'],
            ['id' => 'fees', 'label' => 'Fee / Payment Report', 'format' => 'xls', 'params' => [], 'phase' => 'before', 'audience' => 'staff'],
            ['id' => 'fee-breakdown', 'label' => 'Sports Fee Breakdown (school / student / extra items)', 'format' => 'xls', 'params' => [], 'phase' => 'before', 'audience' => 'staff'],
            ['id' => 'student-event-registrations', 'label' => 'Student Event Registration Register', 'format' => 'xls', 'params' => [], 'phase' => 'before', 'audience' => 'staff'],
            ['id' => 'certificate-counts', 'label' => 'Certificate Counts by School', 'format' => 'csv', 'params' => [], 'phase' => 'after', 'audience' => 'staff'],
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
            ['id' => 'team-squad-sheets', 'label' => 'Team / Group Squad Sheets', 'format' => 'pdf', 'params' => ['school_id'], 'phase' => 'before', 'audience' => 'staff'],
            ['id' => 'assignment-completeness', 'label' => 'Assignment Completeness', 'format' => 'xls', 'params' => ['school_id'], 'phase' => 'before', 'audience' => 'staff'],
            ['id' => 'numbering-register', 'label' => 'Numbering Register (Fest ID / chest / item reg)', 'format' => 'xls', 'params' => ['school_id'], 'phase' => 'before', 'audience' => 'staff'],
            ['id' => 'pending-approvals', 'label' => 'Pending Approval Register', 'format' => 'xls', 'params' => ['school_id'], 'phase' => 'before', 'audience' => 'staff'],
            ['id' => 'medal-tally', 'label' => 'Medal Tally by School', 'format' => 'pdf', 'params' => [], 'phase' => 'after', 'audience' => 'public'],
        ];

        return array_map(
            fn (array $exp) => array_merge($exp, ['href' => "{$base}/{$exp['id']}"]),
            $exports,
        );
    }

    /** @return list<array<string, string>> */
    public static function interactivePages(string $tenantId, int $eventId, ?string $eventType = null): array
    {
        $base = "/sahodaya-admin/{$tenantId}/events/{$eventId}/reports";

        $pages = [
            ['id' => 'head-wise-participants', 'label' => 'Head-wise Participants', 'href' => "{$base}/head-wise-participants"],
            ['id' => 'school-detailed', 'label' => 'School Detailed Results', 'href' => "{$base}/school-detailed"],
            ['id' => 'overall-ranking', 'label' => 'Overall Ranking', 'href' => "{$base}/overall-ranking"],
            ['id' => 'house-detailed', 'label' => 'House Detailed', 'href' => "{$base}/house-detailed"],
            ['id' => 'participation-counts', 'label' => 'Participation Counts', 'href' => "{$base}/participation-counts"],
            ['id' => 'registration-register', 'label' => 'Registration & Fees Register', 'href' => "{$base}/registration-register"],
            ['id' => 'mark-entry-status', 'label' => 'Mark Entry Status', 'href' => "{$base}/mark-entry-status"],
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
        ];

        if ($eventType !== null && $eventType !== 'sports') {
            $pages = array_values(array_filter($pages, fn ($p) => $p['id'] !== 'house-detailed'));
        }

        return $pages;
    }

    /** Interactive preview page id for a bulk export type, if one exists. */
    public static function previewPageForExport(string $exportId): ?string
    {
        return match ($exportId) {
            'school-wise' => 'school-detailed',
            'overall-ranking' => 'overall-ranking',
            'house-wise' => 'house-detailed',
            'item-list' => 'item-counts',
            'mark-entry-status' => 'mark-entry-status',
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
            'assignment-completeness' => 'assignment-completeness',
            'numbering-register' => 'numbering-register',
            'pending-approvals' => 'pending-approvals',
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
