<?php

namespace App\Jobs;

use App\Models\FestEvent;
use App\Services\Events\FestSchoolEventFeeService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Recalculates registered-school dues using the latest event fee schedule.
 * Settings saves dispatch this synchronously so their success response guarantees
 * that persisted invoice totals match the updated fee breakdown. The job remains
 * queueable for callers that explicitly choose background maintenance.
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
