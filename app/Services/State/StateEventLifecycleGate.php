<?php

namespace App\Services\State;

use App\Models\State\StateFestEvent;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * State-level analog of App\Services\Events\EventLifecycleGate (docs/STATE_EVENT_CONDUCT_PLAN.md
 * Phase 1). Deliberately minimal for now — only what Phase 2 (attendance) needs.
 * allowMarkEntry()/allowPublishResults() are ready for Phase 4/5 (judging + marks entry,
 * results publish) to build against; the state_fest_events schema they read
 * (results_published, scoring_locked, scoring_preset) already exists as of this migration
 * so those later phases don't need another schema change just to get started.
 */
class StateEventLifecycleGate
{
    public static function allowAttendanceEntry(StateFestEvent $event): void
    {
        if ($event->status === 'archived') {
            throw new HttpException(422, 'This State event is archived — attendance can no longer be recorded.');
        }
    }

    public static function allowMarkEntry(StateFestEvent $event): void
    {
        if ($event->scoring_locked) {
            throw new HttpException(422, 'Scoring is locked for this State event.');
        }

        if ($event->results_published) {
            throw new HttpException(422, 'Results are already published for this State event.');
        }
    }

    public static function allowPublishResults(StateFestEvent $event): void
    {
        if ($event->results_published) {
            throw new HttpException(422, 'Results are already published for this State event.');
        }
    }
}
