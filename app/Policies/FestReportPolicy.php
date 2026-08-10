<?php

namespace App\Policies;

use App\Models\FestEvent;
use App\Models\FestEventStaff;
use App\Models\User;

/**
 * First policy class in this codebase (app/Policies didn't exist before — authorization
 * has historically lived in middleware, see EnsureSahodayaAdmin). Scoped narrowly to
 * report access per docs/REGION_PHASE_EVENT_REPORTING_REMEDIATION_PLAN.md §4.1, rather
 * than converting the rest of the app's ad hoc checks. Not auto-discovered/registered
 * via Laravel's policy map (no Eloquent model this maps 1:1 to) — call its methods
 * directly from report code, the same way EnsureSahodayaAdmin::matchesRegionScope() and
 * FestReportScopeResolver are called directly rather than through $user->can().
 */
class FestReportPolicy
{
    /** Can the actor open this event's report workspace at all (any scope)? */
    public function view(User $actor, FestEvent $event): bool
    {
        if ($actor->isSuperAdmin() || $actor->hasRole('sahodaya_admin')) {
            return $actor->tenant_id === $event->tenant_id;
        }

        if ($actor->tenant_id !== $event->tenant_id) {
            return false;
        }

        $root = $event->rootEvent();

        if ($actor->hasRole('event_admin')) {
            $ownsEvent = FestEventStaff::query()
                ->where('user_id', $actor->id)
                ->where('duty', 'event_admin')
                ->whereIn('event_id', [(int) $event->id, (int) $root->id])
                ->exists();

            if ($ownsEvent) {
                return true;
            }
        }

        if ($actor->hasRole('region_admin')) {
            return FestEventStaff::query()
                ->where('user_id', $actor->id)
                ->where('duty', 'region_admin')
                ->whereIn('event_id', [(int) $event->id, (int) $root->id])
                ->exists();
        }

        return false;
    }

    /** Combined (root-level, cross-region) results/reports — never available to a region-locked admin. */
    public function viewCombined(User $actor, FestEvent $rootEvent): bool
    {
        if ($actor->isSuperAdmin() || $actor->hasRole('sahodaya_admin')) {
            return $actor->tenant_id === $rootEvent->tenant_id;
        }

        if ($actor->hasRole('region_admin') && ! $actor->hasRole('sahodaya_admin')) {
            return false;
        }

        return $this->view($actor, $rootEvent);
    }

    public function viewRegion(User $actor, FestEvent $rootEvent, int $regionId): bool
    {
        if ($actor->isSuperAdmin() || $actor->hasRole('sahodaya_admin')) {
            return $actor->tenant_id === $rootEvent->tenant_id;
        }

        if ($actor->tenant_id !== $rootEvent->tenant_id) {
            return false;
        }

        $child = $rootEvent->regionalChild($regionId);

        return FestEventStaff::query()
            ->where('user_id', $actor->id)
            ->where('duty', 'region_admin')
            ->where('region_id', $regionId)
            ->whereIn('event_id', array_values(array_filter([(int) $rootEvent->id, $child?->id])))
            ->exists();
    }
}
