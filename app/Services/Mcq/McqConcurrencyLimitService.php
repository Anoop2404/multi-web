<?php

namespace App\Services\Mcq;

use App\Models\McqExam;
use App\Models\McqRegistration;
use App\Models\Tenant;
use App\Models\TenantSetting;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Superadmin rate limiting & concurrency control for students writing online exams.
 * Controls maximum concurrent candidates writing per school and per Sahodaya cluster,
 * preventing server overload, local network saturation, and uncoordinated mass attempts.
 */
class McqConcurrencyLimitService
{
    public const SETTING_KEY = 'mcq_concurrency_limits';
    public const PLATFORM_TENANT_ID = 'platform';

    /**
     * Retrieve the platform-wide global concurrency limits configured by Superadmin.
     *
     * @return array{
     *     enabled: bool,
     *     max_concurrent_per_school: ?int,
     *     max_concurrent_per_sahodaya: ?int,
     *     queue_message_school: string,
     *     queue_message_sahodaya: string
     * }
     */
    public function globalSettings(): array
    {
        $setting = TenantSetting::where('tenant_id', self::PLATFORM_TENANT_ID)
            ->where('key', self::SETTING_KEY)
            ->first();

        $val = is_array($setting?->value) ? $setting->value : [];

        return [
            'enabled' => (bool) ($val['enabled'] ?? false),
            'max_concurrent_per_school' => isset($val['max_concurrent_per_school']) && is_numeric($val['max_concurrent_per_school'])
                ? (int) $val['max_concurrent_per_school']
                : null,
            'max_concurrent_per_sahodaya' => isset($val['max_concurrent_per_sahodaya']) && is_numeric($val['max_concurrent_per_sahodaya'])
                ? (int) $val['max_concurrent_per_sahodaya']
                : null,
            'queue_message_school' => (string) ($val['queue_message_school'] ?? 'School exam capacity limit reached (:current/:max students currently writing). Please wait a few moments and try again.'),
            'queue_message_sahodaya' => (string) ($val['queue_message_sahodaya'] ?? 'Sahodaya cluster exam capacity limit reached (:current/:max students currently writing). Please wait a few moments and try again.'),
        ];
    }

    /**
     * Update global superadmin settings.
     */
    public function saveGlobalSettings(array $data): array
    {
        $payload = [
            'enabled' => (bool) ($data['enabled'] ?? false),
            'max_concurrent_per_school' => isset($data['max_concurrent_per_school']) && is_numeric($data['max_concurrent_per_school']) && (int) $data['max_concurrent_per_school'] > 0
                ? (int) $data['max_concurrent_per_school']
                : null,
            'max_concurrent_per_sahodaya' => isset($data['max_concurrent_per_sahodaya']) && is_numeric($data['max_concurrent_per_sahodaya']) && (int) $data['max_concurrent_per_sahodaya'] > 0
                ? (int) $data['max_concurrent_per_sahodaya']
                : null,
            'queue_message_school' => filled($data['queue_message_school'] ?? null)
                ? trim((string) $data['queue_message_school'])
                : 'School exam capacity limit reached (:current/:max students currently writing). Please wait a few moments and try again.',
            'queue_message_sahodaya' => filled($data['queue_message_sahodaya'] ?? null)
                ? trim((string) $data['queue_message_sahodaya'])
                : 'Sahodaya cluster exam capacity limit reached (:current/:max students currently writing). Please wait a few moments and try again.',
        ];

        TenantSetting::updateOrCreate(
            ['tenant_id' => self::PLATFORM_TENANT_ID, 'key' => self::SETTING_KEY],
            ['value' => $payload]
        );

        return $payload;
    }

    /**
     * Retrieve tenant-specific override (Sahodaya or School).
     *
     * @return array{max_concurrent_per_school: ?int, max_concurrent_per_sahodaya: ?int}|null
     */
    public function overrideForTenant(string $tenantId): ?array
    {
        $setting = TenantSetting::where('tenant_id', $tenantId)
            ->where('key', self::SETTING_KEY)
            ->first();

        if (! $setting || ! is_array($setting->value)) {
            return null;
        }

        return [
            'max_concurrent_per_school' => isset($setting->value['max_concurrent_per_school']) && is_numeric($setting->value['max_concurrent_per_school'])
                ? (int) $setting->value['max_concurrent_per_school']
                : null,
            'max_concurrent_per_sahodaya' => isset($setting->value['max_concurrent_per_sahodaya']) && is_numeric($setting->value['max_concurrent_per_sahodaya'])
                ? (int) $setting->value['max_concurrent_per_sahodaya']
                : null,
        ];
    }

    /**
     * Save an override for a specific Sahodaya or School.
     */
    public function saveTenantOverride(string $tenantId, array $data): array
    {
        $payload = [
            'max_concurrent_per_school' => isset($data['max_concurrent_per_school']) && is_numeric($data['max_concurrent_per_school']) && (int) $data['max_concurrent_per_school'] > 0
                ? (int) $data['max_concurrent_per_school']
                : null,
            'max_concurrent_per_sahodaya' => isset($data['max_concurrent_per_sahodaya']) && is_numeric($data['max_concurrent_per_sahodaya']) && (int) $data['max_concurrent_per_sahodaya'] > 0
                ? (int) $data['max_concurrent_per_sahodaya']
                : null,
        ];

        TenantSetting::updateOrCreate(
            ['tenant_id' => $tenantId, 'key' => self::SETTING_KEY],
            ['value' => $payload]
        );

        return $payload;
    }

    /**
     * Remove tenant override to revert to global / parent limits.
     */
    public function deleteTenantOverride(string $tenantId): void
    {
        TenantSetting::where('tenant_id', $tenantId)
            ->where('key', self::SETTING_KEY)
            ->delete();
    }

    /**
     * Resolves effective limits applying the precedence:
     * 1. Exam settings_json override (if configured on the individual exam)
     * 2. School override (if configured for this school)
     * 3. Sahodaya override (if configured for this cluster)
     * 4. Global superadmin platform defaults
     */
    public function effectiveLimits(McqExam $exam, ?string $schoolId = null): array
    {
        $global = $this->globalSettings();
        $limits = $global;

        $sahodayaId = $exam->tenant_id ?: ($exam->school?->parent_id ?? null);
        if ($sahodayaId) {
            $sahodayaOverride = $this->overrideForTenant($sahodayaId);
            if ($sahodayaOverride) {
                if (array_key_exists('max_concurrent_per_sahodaya', $sahodayaOverride) && $sahodayaOverride['max_concurrent_per_sahodaya'] !== null) {
                    $limits['max_concurrent_per_sahodaya'] = $sahodayaOverride['max_concurrent_per_sahodaya'];
                }
                if (array_key_exists('max_concurrent_per_school', $sahodayaOverride) && $sahodayaOverride['max_concurrent_per_school'] !== null) {
                    $limits['max_concurrent_per_school'] = $sahodayaOverride['max_concurrent_per_school'];
                }
            }
        }

        if ($schoolId) {
            $schoolOverride = $this->overrideForTenant($schoolId);
            if ($schoolOverride && array_key_exists('max_concurrent_per_school', $schoolOverride) && $schoolOverride['max_concurrent_per_school'] !== null) {
                $limits['max_concurrent_per_school'] = $schoolOverride['max_concurrent_per_school'];
            }
        }

        // Exam level overrides take final precedence
        $examSettings = $exam->settings_json['concurrency_limits'] ?? null;
        if (is_array($examSettings)) {
            if (isset($examSettings['enabled'])) {
                $limits['enabled'] = (bool) $examSettings['enabled'];
            }
            if (isset($examSettings['max_concurrent_per_school']) && is_numeric($examSettings['max_concurrent_per_school'])) {
                $limits['max_concurrent_per_school'] = (int) $examSettings['max_concurrent_per_school'];
            }
            if (isset($examSettings['max_concurrent_per_sahodaya']) && is_numeric($examSettings['max_concurrent_per_sahodaya'])) {
                $limits['max_concurrent_per_sahodaya'] = (int) $examSettings['max_concurrent_per_sahodaya'];
            }
        }

        return $limits;
    }

    /**
     * Count students currently actively writing this exam from a specific school.
     */
    public function activeWritingCountForSchool(int|string $examId, string $schoolId, int $durationMinutes = 60, ?int $excludeRegistrationId = null): int
    {
        $cutoff = now()->subMinutes(max(10, $durationMinutes + 5));

        $query = McqRegistration::query()
            ->where('exam_id', $examId)
            ->where('school_id', $schoolId)
            ->where('status', 'started')
            ->whereNotNull('started_at')
            ->where('started_at', '>=', $cutoff);

        if ($excludeRegistrationId) {
            $query->where('id', '!=', $excludeRegistrationId);
        }

        return $query->count();
    }

    /**
     * Count students currently actively writing this exam across an entire Sahodaya cluster.
     */
    public function activeWritingCountForSahodaya(int|string $examId, string $sahodayaId, int $durationMinutes = 60, ?int $excludeRegistrationId = null): int
    {
        $cutoff = now()->subMinutes(max(10, $durationMinutes + 5));

        $query = McqRegistration::query()
            ->where('exam_id', $examId)
            ->where('status', 'started')
            ->whereNotNull('started_at')
            ->where('started_at', '>=', $cutoff);

        if ($excludeRegistrationId) {
            $query->where('id', '!=', $excludeRegistrationId);
        }

        return $query->count();
    }

    /**
     * Guard that checks concurrency limits before a student can begin writing the exam.
     * Note: Already-started attempts resuming an ongoing session are never blocked.
     *
     * @throws ValidationException
     */
    public function assertCapacityAvailable(McqRegistration $registration): void
    {
        // Resuming an existing started session: never block
        if ($registration->started_at && $registration->status === 'started') {
            return;
        }

        $registration->loadMissing('exam', 'school');
        $exam = $registration->exam;
        if (! $exam || ! $exam->isOnlineDelivery()) {
            return;
        }

        $limits = $this->effectiveLimits($exam, $registration->school_id);
        if (! ($limits['enabled'] ?? false)) {
            return;
        }

        $duration = (int) ($exam->duration_minutes ?: 60);

        // 1. Check School Limit
        if ($limits['max_concurrent_per_school'] !== null && $registration->school_id) {
            $currentSchool = $this->activeWritingCountForSchool(
                $exam->id,
                $registration->school_id,
                $duration,
                $registration->id
            );
            $maxSchool = (int) $limits['max_concurrent_per_school'];

            if ($currentSchool >= $maxSchool) {
                $msg = strtr($limits['queue_message_school'], [
                    ':current' => (string) $currentSchool,
                    ':max'     => (string) $maxSchool,
                ]);

                throw ValidationException::withMessages(['exam' => $msg]);
            }
        }

        // 2. Check Sahodaya Cluster Limit
        $sahodayaId = $exam->tenant_id ?: ($registration->school?->parent_id ?? null);
        if ($limits['max_concurrent_per_sahodaya'] !== null && $sahodayaId) {
            $currentSahodaya = $this->activeWritingCountForSahodaya(
                $exam->id,
                $sahodayaId,
                $duration,
                $registration->id
            );
            $maxSahodaya = (int) $limits['max_concurrent_per_sahodaya'];

            if ($currentSahodaya >= $maxSahodaya) {
                $msg = strtr($limits['queue_message_sahodaya'], [
                    ':current' => (string) $currentSahodaya,
                    ':max'     => (string) $maxSahodaya,
                ]);

                throw ValidationException::withMessages(['exam' => $msg]);
            }
        }
    }

    /**
     * Live telemetry for Superadmin dashboard.
     *
     * @return array<string, mixed>
     */
    public function liveStats(): array
    {
        $cutoff = now()->subMinutes(180);

        $activeExams = McqExam::query()
            ->whereIn('status', ['published', 'ongoing'])
            ->where('delivery_mode', 'online')
            ->count();

        $activeWritingTotal = McqRegistration::query()
            ->where('status', 'started')
            ->whereNotNull('started_at')
            ->where('started_at', '>=', $cutoff)
            ->count();

        return [
            'active_online_exams' => $activeExams,
            'total_students_writing_now' => $activeWritingTotal,
        ];
    }
}
