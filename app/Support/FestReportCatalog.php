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
        'judge-sheet'                   => ['dataset' => 'schedule', 'supported_scopes' => ['self', 'region'], 'supports_competition_phase' => true],
        'mark-entry-sheet'              => ['dataset' => 'schedule', 'supported_scopes' => ['self', 'region'], 'supports_competition_phase' => true],
        'mark-entered-summary'          => ['dataset' => 'schedule', 'supported_scopes' => ['self', 'combined', 'region'], 'supports_competition_phase' => true],
        'mark-entry-status'             => ['dataset' => 'schedule', 'supported_scopes' => ['self', 'combined', 'region'], 'supports_competition_phase' => true],
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
            ['id' => 'attendance', 'label' => 'Attendance Register', 'href' => "/sahodaya-admin/{$tenantId}/events/{$eventId}/attendance"],
            ['id' => 'id-cards', 'label' => 'Participant ID Cards', 'href' => "/sahodaya-admin/{$tenantId}/events/{$eventId}/id-cards"],
        ];

        if ($eventType === 'sports') {
            $pages = array_values(array_filter($pages, fn ($p) => $p['id'] !== 'area-wise-participants'));
        } elseif ($eventType !== null) {
            $pages = array_values(array_filter($pages, fn ($p) => $p['id'] !== 'house-detailed'));
        }

        return $pages;
    }

    /**
     * Interactive/export ids whose builders read $event->reportableEventIds()/
     * reportableItemIds() in a way that, run directly on a region-partition child event,
     * pulls in the hub's own uncopied item/registration rows alongside the child's own —
     * not full cross-region combination, but not correctly isolated either. Confirmed by
     * a manual audit (see docs/REGION_PHASE_EVENT_REPORTING_REMEDIATION_PLAN.md Phase 1
     * completion notes): Item Counts, Head-wise Participants, Discipline Registration,
     * Mark Entry Status, Schedule/Clashes, and Assignment Completeness. Only these ids
     * get rerouted through the parent hub + region_id by regionScopedRows() below; every
     * other report keeps linking to the child event's own id/URL (its existing, working
     * behavior) rather than blanket-rerouting everything, which would regress reports
     * that don't understand region_id into showing fully combined data unlabeled.
     *
     * @var list<string>
     */
    public const REGION_ID_AWARE_IDS = [
        'item-counts', 'item-list',
        'head-wise-participants',
        'discipline-registration',
        'mark-entry-status', 'mark-entered-summary',
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
