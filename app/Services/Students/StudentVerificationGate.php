<?php

namespace App\Services\Students;

use App\Models\FestEvent;
use App\Models\McqExam;
use App\Models\SahodayaProfile;
use App\Models\Student;

class StudentVerificationGate
{
    /** @var array<string, bool> */
    private array $requiredGloballyCache = [];

    public function requiredGlobally(?string $sahodayaId): bool
    {
        if (! $sahodayaId) {
            return false;
        }

        return $this->requiredGloballyCache[$sahodayaId] ??= (bool) (
            SahodayaProfile::where('tenant_id', $sahodayaId)->value('require_student_verification') ?? true
        );
    }

    public function requiredForEvent(FestEvent $event): bool
    {
        $settings = is_array($event->fee_settings) ? $event->fee_settings : [];

        if (array_key_exists('require_verified_students', $settings)) {
            return (bool) $settings['require_verified_students'];
        }

        return $this->requiredGlobally($event->tenant_id ?? null);
    }

    public function requiredForMcq(McqExam $exam): bool
    {
        $settings = is_array($exam->settings_json) ? $exam->settings_json : [];

        if (array_key_exists('require_verified_students', $settings)) {
            return (bool) $settings['require_verified_students'];
        }

        return $this->requiredGlobally($exam->tenant_id ?? null);
    }

    public function isEligible(
        Student $student,
        ?FestEvent $event = null,
        ?string $sahodayaId = null,
        ?McqExam $mcqExam = null,
    ): bool {
        if ($student->isVerified()) {
            return true;
        }

        if ($event) {
            return ! $this->requiredForEvent($event);
        }

        if ($mcqExam) {
            return ! $this->requiredForMcq($mcqExam);
        }

        $tenantId = $sahodayaId ?? $student->tenant?->parent_id;

        return $tenantId ? ! $this->requiredGlobally($tenantId) : true;
    }

    public function ineligibilityReason(
        Student $student,
        ?FestEvent $event = null,
        ?string $sahodayaId = null,
        ?McqExam $mcqExam = null,
    ): ?string {
        if ($this->isEligible($student, $event, $sahodayaId, $mcqExam)) {
            return null;
        }

        return 'Student must be verified before registration.';
    }

    /** @param  \Illuminate\Database\Eloquent\Builder<Student>  $query */
    public function scopeEligible(
        $query,
        ?FestEvent $event = null,
        ?string $sahodayaId = null,
        ?McqExam $mcqExam = null,
    ): void {
        $required = $event
            ? $this->requiredForEvent($event)
            : ($mcqExam
                ? $this->requiredForMcq($mcqExam)
                : ($sahodayaId ? $this->requiredGlobally($sahodayaId) : false));

        if ($required) {
            $query->verified();
        }
    }
}
