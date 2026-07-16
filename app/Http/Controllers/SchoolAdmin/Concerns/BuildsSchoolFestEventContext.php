<?php

namespace App\Http\Controllers\SchoolAdmin\Concerns;

use App\Models\FestEvent;
use App\Models\FestRegistration;
use App\Models\FestSchoolEventFee;
use App\Services\Events\FestHeadItemNavigationService;
use App\Services\Events\FestSchoolEventFeeService;
use App\Support\ProgramRouteMap;
use App\Support\SchoolFestProgram;

trait BuildsSchoolFestEventContext
{
    /** @return array<string, mixed> */
    protected function schoolFestEventNavProps(FestEvent $event, string $programSlug): array
    {
        $meta = SchoolFestProgram::meta($programSlug);
        $navService = app(FestHeadItemNavigationService::class);
        $headNav = $navService->slimNavigation(
            $navService->headSummariesForEvent($event, $this->school->id, withItems: true),
            includeItems: true
        );

        $prefix = ProgramRouteMap::prefixFromSlug($meta['slug']);

        $events = FestEvent::query()
            ->where('tenant_id', $this->school->parent_id)
            ->ofType($meta['eventType'])
            ->listedForSchool($this->school->id, $meta['eventType'])
            ->orderByDesc('event_start')
            ->get(['id', 'title', 'status', 'event_start'])
            ->pipe(fn ($rows) => app(\App\Services\School\SchoolUserScopeService::class)
                ->filterFestEventsForUser(request()->user(), $this->school->id, $meta['slug'], $rows));

        return [
            'event' => $event->only([
                'id', 'title', 'status', 'event_type', 'event_start', 'event_end',
                'venue', 'results_published', 'level_round', 'registration_open', 'registration_close',
            ]),
            'program' => $meta['slug'],
            'programMeta' => $meta,
            'programPrefix' => $prefix,
            'eventHeadNav' => $headNav,
            'programEvents' => $events->values()->all(),
            'schoolRegion' => $this->schoolKalotsavRegion($event),
        ];
    }

    /**
     * Kalotsav region context for the current school (null when regions don't apply).
     *
     * @return array{applies: bool, region: ?string, set_url: string}|null
     */
    protected function schoolKalotsavRegion(FestEvent $event): ?array
    {
        if ($event->event_type !== 'kalolsavam') {
            return null;
        }

        $sahodayaId = $this->school->parent_id;
        $regionService = app(\App\Services\Events\FestRegionPartitionService::class);
        if (! $regionService->regionsApply($sahodayaId)) {
            return null;
        }

        return [
            'applies' => true,
            'region'  => $regionService->schoolRegion($sahodayaId, $this->school->id)?->name,
            'set_url' => "/school-admin/{$this->school->id}/registration",
        ];
    }

    /** @return array<string, mixed> */
    protected function schoolFestEventOverviewStats(FestEvent $event): array
    {
        $feeService = app(FestSchoolEventFeeService::class);
        $schedule = $feeService->resolveSchedule($event);
        $feeRow = FestSchoolEventFee::query()
            ->where('event_id', $event->id)
            ->where('school_id', $this->school->id)
            ->first();

        $registrations = FestRegistration::query()
            ->where('event_id', $event->id)
            ->where('school_id', $this->school->id)
            ->whereIn('status', FestRegistration::ACTIVE_STATUSES)
            ->count();

        return [
            'registrations' => $registrations,
            'fees_due' => (float) ($feeRow?->total_due ?? $schedule['amount'] ?? 0),
            'fee_status' => $feeRow?->status ?? 'none',
            'items_enabled' => $event->items()->where('is_enabled', true)->count(),
        ];
    }
}
