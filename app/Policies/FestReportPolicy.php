<?php

namespace App\Policies;

use App\Models\FestEvent;
use App\Models\FestEventStaff;
use App\Models\User;

/**
 * PERM-01 (functional audit, 2026-08-11/12): DEAD CODE. `grep -rn FestReportPolicy app/`
 * has zero call sites outside this file, and it's never registered via Gate::policy()/
 * Gate::define(). The docblock this replaces claimed its methods were "called directly
 * from report code, the same way EnsureSahodayaAdmin::matchesRegionScope() is called" —
 * that was never true; actual enforcement runs entirely through
 * EnsureSahodayaAdmin.php + ResolveRegionScopedReportEvent.php instead. Safe to delete
 * outright (kept only because this sandbox has no file-deletion capability — flagged to
 * the user to remove this file manually).
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
