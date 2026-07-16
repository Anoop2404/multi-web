<?php

namespace App\Http\Controllers\SahodayaAdmin\Concerns;

use App\Models\FestEvent;
use App\Services\Events\FestEventReportAnalyticsService;
use App\Services\Events\FestHeadItemNavigationService;
use App\Services\Events\FestItemHeadService;

trait BuildsItemHeadReportContext
{
    /** @return array<string, mixed> */
    protected function itemHeadReportContext(FestEvent $event, ?string $schoolId = null, ?string $tenantId = null): array
    {
        if ($event->event_type === 'sports') {
            app(FestItemHeadService::class)->syncEventHeads($event);
        }

        $tenantId ??= $event->tenant_id;
        $nav = app(FestHeadItemNavigationService::class)->navigationForEvent($event, $schoolId);
        $summary = app(FestEventReportAnalyticsService::class, ['event' => $event])
            ->headWiseSummary($schoolId);

        $reportsBase = "/sahodaya-admin/{$tenantId}/events/{$event->id}/reports";

        return array_merge($nav, [
            'headSummary'        => $summary,
            'itemHeadsManageUrl' => "/sahodaya-admin/{$tenantId}/events/{$event->id}/competition",
            'headWiseReportBase' => "{$reportsBase}/by-head",
            'reportsByHeadBase'  => "{$reportsBase}/by-head",
            'headWiseExportUrl'  => "{$reportsBase}/export/head-wise-participants",
        ]);
    }

    protected function resolveHeadQueryParam(mixed $raw): ?int
    {
        if ($raw === null || $raw === '') {
            return null;
        }
        if ($raw === 'other') {
            return 0;
        }

        return (int) $raw;
    }

    /** @return list<int>|null */
    protected function itemIdsForHeadFilter(FestEvent $event, ?int $headId, ?int $itemId): ?array
    {
        if ($itemId) {
            return [$itemId];
        }

        if ($headId === null) {
            return null;
        }

        // Sports (Head = Event): the "head" filter value is a sport event id —
        // its items are simply the items on that event.
        if ($event->event_type === 'sports' && $headId > 0) {
            return \App\Models\FestEventItem::query()
                ->where('event_id', $headId)
                ->where('is_enabled', true)
                ->pluck('id')
                ->all();
        }

        $query = \App\Models\FestEventItem::query()
            ->where('event_id', $event->id)
            ->where('is_enabled', true);

        if ($headId === 0) {
            $query->whereNull('head_id');
        } else {
            $query->where('head_id', $headId);
        }

        return $query->pluck('id')->all();
    }
}
