<?php

namespace App\Support;

use App\Models\FestEvent;

/**
 * Links ERP catalogue event report IDs to the dedicated event reports workspace.
 */
class EventReportCatalogBridge
{
    /** @return list<array{purpose: string, description: string, reports: list<array{id: string, label: string, href: string, format?: string}>}> */
    public static function purposeGroups(string $tenantId, FestEvent $event): array
    {
        $base = "/sahodaya-admin/{$tenantId}/events/{$event->id}/reports";
        $export = "{$base}/export";
        $isSports = $event->event_type === 'sports';

        $registration = [
            ['id' => 'RPT-SPT-001', 'label' => 'Registered students by item', 'href' => "{$base}/registration-register", 'format' => 'screen'],
            ['id' => 'RPT-SPT-002', 'label' => 'School registration summary', 'href' => "{$base}/registration-register", 'format' => 'screen'],
            ['id' => 'RPT-SPT-040', 'label' => 'Pending approvals', 'href' => "{$base}/pending-approvals", 'format' => 'screen'],
            ['id' => 'RPT-SPT-043', 'label' => 'Team / individual roster', 'href' => "{$export}/registrations", 'format' => 'xls'],
            ['id' => 'RPT-SPT-005', 'label' => 'Schedule clashes', 'href' => "{$base}/schedule-clashes", 'format' => 'screen'],
            ['id' => 'RPT-SPT-018', 'label' => 'Age group matrix', 'href' => "{$base}/age-group-matrix", 'format' => 'screen'],
            ['id' => 'RPT-SPT-017', 'label' => 'Class category participation', 'href' => "{$base}/participation-counts", 'format' => 'screen'],
        ];

        if (! $isSports) {
            $registration = [
                ['id' => 'RPT-KAL-001', 'label' => 'Item-wise registration', 'href' => "{$base}/registration-register", 'format' => 'screen'],
                ['id' => 'RPT-KAL-002', 'label' => 'School participation summary', 'href' => "{$base}/registration-register", 'format' => 'screen'],
                ['id' => 'RPT-KAL-010', 'label' => 'Group item participants', 'href' => "{$base}/head-wise-participants", 'format' => 'screen'],
                ['id' => 'RPT-KAL-018', 'label' => 'Schedule clashes', 'href' => "{$base}/schedule-clashes", 'format' => 'screen'],
            ];
        }

        $heads = [
            ['id' => $isSports ? 'RPT-SPT-015' : 'RPT-KAL-023', 'label' => 'Item head summary', 'href' => "{$base}/head-wise-participants", 'format' => 'screen'],
            ['id' => $isSports ? 'RPT-SPT-015' : 'RPT-KAL-023', 'label' => 'Head-wise participant export', 'href' => "{$export}/head-wise-participants", 'format' => 'xls'],
            ['id' => 'RPT-FST-005', 'label' => 'ID cards by item head', 'href' => "{$export}/id-cards-by-head", 'format' => 'pdf'],
            ['id' => 'RPT-SPT-039', 'label' => 'Item fee configuration', 'href' => "{$base}/fee-collection", 'format' => 'screen'],
        ];

        $schedule = [
            ['id' => 'RPT-SPT-004', 'label' => 'Schedule by venue', 'href' => "{$base}/item-schedule", 'format' => 'screen'],
            ['id' => 'RPT-SPT-036', 'label' => 'Daily schedule bulletin', 'href' => "{$export}/day-wise", 'format' => 'pdf'],
            ['id' => 'RPT-KAL-009', 'label' => 'Schedule by stage', 'href' => "{$base}/item-schedule", 'format' => 'screen'],
        ];

        $results = [
            ['id' => 'RPT-SPT-008', 'label' => 'Result sheet by item', 'href' => "{$base}/item-wise", 'format' => 'screen'],
            ['id' => 'RPT-SPT-009', 'label' => 'Points table', 'href' => "{$base}/overall-ranking", 'format' => 'screen'],
            ['id' => 'RPT-SPT-019', 'label' => 'School points detail', 'href' => "{$base}/school-detailed", 'format' => 'screen'],
            ['id' => 'RPT-SPT-026', 'label' => 'Mark entry status', 'href' => "{$base}/mark-entry-status", 'format' => 'screen'],
            ['id' => 'RPT-SPT-020', 'label' => 'Medal tally', 'href' => "{$export}/medal-tally", 'format' => 'pdf'],
            ['id' => 'RPT-KAL-006', 'label' => 'Rank list by item', 'href' => "{$base}/item-wise", 'format' => 'screen'],
            ['id' => 'RPT-KAL-007', 'label' => 'School trophy points', 'href' => "{$base}/overall-ranking", 'format' => 'screen'],
        ];

        $finance = [
            ['id' => 'RPT-SPT-003', 'label' => 'Fee collection', 'href' => "{$base}/fee-collection", 'format' => 'screen'],
            ['id' => 'RPT-SPT-025', 'label' => 'Free vs paid breakdown', 'href' => "{$export}/fee-breakdown", 'format' => 'xls'],
            ['id' => 'RPT-KAL-011', 'label' => 'Fee collection', 'href' => "{$base}/fee-collection", 'format' => 'screen'],
        ];

        $prefix = $isSports ? 'RPT-SPT-' : 'RPT-KAL-';

        return array_values(array_filter([
            [
                'purpose'     => 'Registration & roster',
                'description' => 'School and student registrations for this event only.',
                'reports'     => $registration,
            ],
            [
                'purpose'     => 'By item head',
                'description' => 'Reports grouped by item head (e.g. Athletics, Chess) — pick a head, then an item.',
                'reports'     => array_values(array_filter(
                    $heads,
                    fn (array $r) => $isSports ? ! str_starts_with($r['id'], 'RPT-KAL-') : ! str_starts_with($r['id'], 'RPT-SPT-039'),
                )),
            ],
            [
                'purpose'     => 'Schedule & venue',
                'description' => 'Timings, stages, and day-wise schedules for this event.',
                'reports'     => array_values(array_filter($schedule, fn (array $r) => str_starts_with($r['id'], $prefix))),
            ],
            [
                'purpose'     => 'Results & marks',
                'description' => 'Rankings, marks, and result sheets for this event.',
                'reports'     => array_values(array_filter($results, fn (array $r) => str_starts_with($r['id'], $prefix))),
            ],
            [
                'purpose'     => 'Fees',
                'description' => 'Event fee collection and breakdown.',
                'reports'     => array_values(array_filter($finance, fn (array $r) => str_starts_with($r['id'], $prefix))),
            ],
        ]));
    }

    public static function eventHubUrl(string $tenantId, FestEvent $event): string
    {
        return "/sahodaya-admin/{$tenantId}/events/{$event->id}/reports";
    }

    public static function eventExportsUrl(string $tenantId, FestEvent $event, string $phase = 'before'): string
    {
        return "/sahodaya-admin/{$tenantId}/events/{$event->id}/reports/downloads/{$phase}";
    }
}
