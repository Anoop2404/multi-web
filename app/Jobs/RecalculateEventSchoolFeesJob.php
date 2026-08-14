<?php

namespace App\Jobs;

use App\Models\FestEvent;
use App\Services\Events\FestSchoolEventFeeService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Runs FestSchoolEventFeeService::recalculateAllRegisteredSchools() for an event —
 * dispatched from FestEventSettingsController::updateFeeSettings()/updateItemFee()
 * whenever an admin changes a fee schedule, so already-registered schools (up to
 * 30-100 per Sahodaya) get their fee recalculated once, at the point of change,
 * instead of the school registration page recalculating on every single page view
 * (the anti-pattern this job replaces — see PERFORMANCE_FIX_PLAN_2026_08_13.md and
 * the perf_fixes_implemented follow-up memory entry). Queued rather than inline
 * because a settings save can affect many schools at once and there's no reason to
 * make the admin wait on all of them synchronously.
 */
class RecalculateEventSchoolFeesJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $eventId,
    ) {}

    public function handle(FestSchoolEventFeeService $feeService): void
    {
        $event = FestEvent::find($this->eventId);
        if (! $event) {
            return;
        }

        $feeService->recalculateAllRegisteredSchools($event);
    }
}
