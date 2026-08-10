<?php

namespace App\Http\Controllers\SahodayaAdmin\Concerns;

use App\Models\FestEvent;
use Illuminate\Http\Request;

/**
 * Shared by FestReportController and FestAttendanceController (and any other
 * event-scoped read-only view affected by the same issue) — see
 * FestReportController::regionAwareTargetEvent()'s original docblock for the full
 * reasoning; kept here as a trait once a second controller needed the identical logic
 * rather than copy-pasting it.
 */
trait ResolvesRegionAwareReportEvent
{
    private function regionAwareTargetEvent(Request $request, FestEvent $event): FestEvent
    {
        if ($event->parent_event_id !== null) {
            return $this->detachedFromParent($event);
        }

        $regionId = $request->integer('region_id') ?: null;
        if ($regionId === null) {
            return $event;
        }

        $child = $event->regionalChild($regionId);
        abort_unless($child, 404, 'No matching region for this event.');

        return $this->detachedFromParent($child);
    }

    private function detachedFromParent(FestEvent $child): FestEvent
    {
        $isolated = clone $child;
        $isolated->parent_event_id = null;

        return $isolated;
    }
}
