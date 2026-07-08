<?php

namespace App\Services\Teachers;

use App\Models\SahodayaProfile;
use App\Models\Teacher;

class TeacherVerificationGate
{
    public function requiredGlobally(string $sahodayaId): bool
    {
        $profile = SahodayaProfile::where('tenant_id', $sahodayaId)->first();

        return (bool) ($profile?->teacher_registration_enabled ?? true);
    }

    public function isEligible(Teacher $teacher, ?string $sahodayaId = null): bool
    {
        if ($teacher->isVerified()) {
            return true;
        }

        $tenantId = $sahodayaId ?? $teacher->tenant?->parent_id;

        return $tenantId ? ! $this->requiredGlobally($tenantId) : true;
    }

    public function ineligibilityReason(Teacher $teacher, ?string $sahodayaId = null): ?string
    {
        if ($this->isEligible($teacher, $sahodayaId)) {
            return null;
        }

        return 'Teacher must be verified by Sahodaya before nomination.';
    }
}
