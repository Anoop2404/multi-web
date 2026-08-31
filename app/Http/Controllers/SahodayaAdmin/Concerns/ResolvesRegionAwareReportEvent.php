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
        // Resolve the true root off the ORIGINAL (still-attached) $child before nulling
        // parent_event_id below. FestEvent::rootEvent() prefers the indexed root_event_id
        // column but falls back to walking parent_event_id when that column is unset
        // (older rows) — once parent_event_id is nulled on the clone, that fallback would
        // resolve to the clone itself instead of the real root. Stamping root_event_id
        // here keeps ->rootEvent() correct on the isolated clone either way, which matters
        // for every caller resolving root-only config (e.g. class_group_scheme) off it.
        $rootId = $child->rootEvent()->id;

        $isolated = clone $child;
        $isolated->root_event_id = $rootId;
        $isolated->parent_event_id = null;

        return $isolated;
    }
}
