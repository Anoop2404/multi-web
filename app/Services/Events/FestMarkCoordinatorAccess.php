<?php

namespace App\Services\Events;

use App\Models\FestEvent;
use App\Models\FestEventStaff;
use App\Models\User;

class FestMarkCoordinatorAccess
{
    public static function canAccessEvent(User $user, FestEvent $event): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        if ($user->tenant_id !== $event->tenant_id) {
            return false;
        }

        if ($user->hasAnyRole(['sahodaya_admin', 'mark_entry_admin'])) {
            return true;
        }

        if (! $user->hasRole('mark_entry_coordinator')) {
            return false;
        }

        return FestEventStaff::where('event_id', $event->id)
            ->where('user_id', $user->id)
            ->where('duty', 'marks')
            ->exists();
    }

    /**
     * @return list<int>|null null = unrestricted (all tenant events)
     */
    public static function assignedEventIds(User $user, string $tenantId): ?array
    {
        if ($user->isSuperAdmin()) {
            return null;
        }

        if ($user->hasRole('sahodaya_admin') && $user->tenant_id === $tenantId) {
            return null;
        }

        if ($user->hasRole('mark_entry_admin') && $user->tenant_id === $tenantId) {
            return null;
        }

        if (! $user->hasRole('mark_entry_coordinator') || $user->tenant_id !== $tenantId) {
            return [];
        }

        return FestEventStaff::query()
            ->where('user_id', $user->id)
            ->where('duty', 'marks')
            ->whereIn('event_id', FestEvent::where('tenant_id', $tenantId)->pluck('id'))
            ->pluck('event_id')
            ->unique()
            ->values()
            ->all();
    }
}
