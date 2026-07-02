<?php

namespace App\Support;

class FestReportCatalog
{
    /** @return list<string> */
    public static function resultExportTypes(): array
    {
        return [
            'results', 'school-wise', 'overall-ranking', 'house-wise', 'item-wise',
            'cumulative', 'sahodaya-ranking', 'promotions', 'promotions-pdf',
        ];
    }

    /** @return list<array<string, mixed>> */
    public static function exports(string $tenantId, int $eventId): array
    {
        $base = "/sahodaya-admin/{$tenantId}/events/{$eventId}/reports/export";

        return [
            ['id' => 'registration-list', 'label' => 'Registration Master List', 'format' => 'pdf', 'params' => ['school_id', 'class_group'], 'phase' => 'before', 'audience' => 'staff'],
            ['id' => 'registrations', 'label' => 'Registrations (spreadsheet)', 'format' => 'xls', 'params' => [], 'phase' => 'before', 'audience' => 'staff'],
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
            ['id' => 'mark-entry-status', 'label' => 'Mark Entry Status', 'format' => 'csv', 'params' => [], 'phase' => 'during', 'audience' => 'staff'],
            ['id' => 'clashes', 'label' => 'Schedule Clash Report', 'format' => 'csv', 'params' => ['school_id'], 'phase' => 'before', 'audience' => 'staff'],
            ['id' => 'clashes-school', 'label' => 'School Clash Report (PDF)', 'format' => 'pdf', 'params' => ['school_id'], 'phase' => 'before', 'audience' => 'staff'],
            ['id' => 'promotions', 'label' => 'Promoted Qualifiers', 'format' => 'csv', 'params' => [], 'phase' => 'after', 'audience' => 'staff'],
            ['id' => 'promotions-pdf', 'label' => 'Promotion Sheet', 'format' => 'pdf', 'params' => [], 'phase' => 'after', 'audience' => 'staff'],
            ['id' => 'fees', 'label' => 'Fee / Payment Report', 'format' => 'xls', 'params' => [], 'phase' => 'before', 'audience' => 'staff'],
            ['id' => 'certificate-counts', 'label' => 'Certificate Counts by School', 'format' => 'csv', 'params' => [], 'phase' => 'after', 'audience' => 'staff'],
            ['id' => 'catering', 'label' => 'Food / Catering Orders', 'format' => 'csv', 'params' => [], 'phase' => 'during', 'audience' => 'staff'],
            ['id' => 'students', 'label' => 'All Students (member schools)', 'format' => 'csv', 'params' => [], 'phase' => 'before', 'audience' => 'staff'],
            ['id' => 'admit-cards', 'label' => 'Admit Cards (bulk PDF)', 'format' => 'pdf', 'params' => ['school_id'], 'phase' => 'before', 'audience' => 'staff'],
            ['id' => 'sahodaya-ranking', 'label' => 'Sahodaya School Ranking', 'format' => 'pdf', 'params' => [], 'phase' => 'after', 'audience' => 'public'],
            ['id' => 'student-participation', 'label' => 'Student Participation', 'format' => 'xls', 'params' => ['school_id', 'class_group'], 'phase' => 'before', 'audience' => 'staff'],
        ];
    }

    /** @return list<array<string, string>> */
    public static function interactivePages(string $tenantId, int $eventId, ?string $eventType = null): array
    {
        $base = "/sahodaya-admin/{$tenantId}/events/{$eventId}/reports";

        $pages = [
            ['id' => 'school-detailed', 'label' => 'School Detailed Results', 'href' => "{$base}/school-detailed"],
            ['id' => 'overall-ranking', 'label' => 'Overall Ranking', 'href' => "{$base}/overall-ranking"],
            ['id' => 'house-detailed', 'label' => 'House Detailed', 'href' => "{$base}/house-detailed"],
            ['id' => 'participation-counts', 'label' => 'Participation Counts', 'href' => "{$base}/participation-counts"],
            ['id' => 'registration-register', 'label' => 'Registration & Fees Register', 'href' => "{$base}/registration-register"],
            ['id' => 'mark-entry-status', 'label' => 'Mark Entry Status', 'href' => "{$base}/mark-entry-status"],
            ['id' => 'item-schedule', 'label' => 'Venue & time schedule', 'href' => "{$base}/item-schedule"],
            ['id' => 'schedule-clashes', 'label' => 'Schedule Clashes', 'href' => "{$base}/schedule-clashes"],
            ['id' => 'item-counts', 'label' => 'Item Registration Counts', 'href' => "{$base}/item-counts"],
        ];

        if ($eventType !== null && $eventType !== 'sports') {
            $pages = array_values(array_filter($pages, fn ($p) => $p['id'] !== 'house-detailed'));
        }

        return $pages;
    }
}
