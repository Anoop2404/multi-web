<?php

namespace App\Support;

use App\Models\Tenant;

/**
 * Every school already names an "Events Coordinator" on its own membership application
 * (SchoolApplicationForm's event_coordinator_name/phone/email, stored on
 * Tenant.application_payload). That contact doubles as the sensible default team manager
 * for a fest event once one is needed (FestSchoolTeamManager) — used both to pre-fill a
 * school's own "Team Managers" form before they've entered one, and to fall back to
 * something other than a blank cell on the Sahodaya-side team managers report.
 */
class SchoolEventCoordinator
{
    /** @return array{name: ?string, phone: ?string, email: ?string}|null null when the school never named one */
    public static function forSchool(Tenant $school): ?array
    {
        $payload = $school->application_payload ?? [];
        $name = $payload['event_coordinator_name'] ?? null;
        $phone = $payload['event_coordinator_phone'] ?? null;
        $email = $payload['event_coordinator_email'] ?? null;

        if (blank($name) && blank($phone)) {
            return null;
        }

        return ['name' => $name, 'phone' => $phone, 'email' => $email];
    }
}
