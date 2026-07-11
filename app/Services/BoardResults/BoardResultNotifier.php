<?php

namespace App\Services\BoardResults;

use App\Models\BoardResult;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Notifications\NotificationService;

class BoardResultNotifier
{
    public const UPLOAD_REMINDER = 'board_results.upload_reminder';

    public const SUBMISSION_CONFIRMATION = 'board_results.submission_confirmation';

    public const VERIFICATION_PENDING = 'board_results.verification_pending';

    public const RESULT_APPROVED = 'board_results.result_approved';

    public const RESULT_REJECTED = 'board_results.result_rejected';

    public const RESULT_PUBLISHED = 'board_results.result_published';

    public function __construct(private NotificationService $notifications) {}

    public function submissionConfirmation(BoardResult $boardResult): void
    {
        $this->notifySchoolAdmin($boardResult, self::SUBMISSION_CONFIRMATION, $this->vars($boardResult));
        $this->notifySahodayaAdmins($boardResult, self::VERIFICATION_PENDING, $this->vars($boardResult));
    }

    public function approved(BoardResult $boardResult): void
    {
        $this->notifySchoolAdmin($boardResult, self::RESULT_APPROVED, $this->vars($boardResult));
    }

    public function rejected(BoardResult $boardResult): void
    {
        $this->notifySchoolAdmin($boardResult, self::RESULT_REJECTED, $this->vars($boardResult, [
            'reason' => $boardResult->rejection_reason ?? '',
        ]));
    }

    public function published(BoardResult $boardResult): void
    {
        $this->notifySchoolAdmin($boardResult, self::RESULT_PUBLISHED, $this->vars($boardResult));
    }

    public function uploadReminder(BoardResult $boardResult): void
    {
        $this->notifySchoolAdmin($boardResult, self::UPLOAD_REMINDER, $this->vars($boardResult));
    }

    /** @param  array<string, string>  $extra */
    private function vars(BoardResult $boardResult, array $extra = []): array
    {
        $school = Tenant::find($boardResult->tenant_id);

        return array_merge([
            'school_name' => $school?->name ?? $boardResult->tenant_id,
            'class' => (string) $boardResult->class,
            'examination_type' => (string) $boardResult->examination_type,
            'academic_year' => (string) $boardResult->academic_year,
            'pass_percent' => (string) $boardResult->pass_percent,
        ], $extra);
    }

    /** @param  array<string, string>  $vars */
    private function notifySchoolAdmin(BoardResult $boardResult, string $slug, array $vars): void
    {
        $school = Tenant::find($boardResult->tenant_id);
        if (! $school) {
            return;
        }

        $admin = User::query()
            ->where('tenant_id', $school->id)
            ->whereHas('roles', fn ($q) => $q->where('name', 'school_admin'))
            ->first();
        if (! $admin) {
            return;
        }

        $this->notifications->notifyFromTemplate(
            $admin,
            $slug,
            $vars,
            "/school-admin/{$school->id}/board-results",
        );
    }

    /** @param  array<string, string>  $vars */
    private function notifySahodayaAdmins(BoardResult $boardResult, string $slug, array $vars): void
    {
        $school = Tenant::find($boardResult->tenant_id);
        $sahodayaId = $school?->parent_id;
        if (! $sahodayaId) {
            return;
        }

        $admins = User::query()
            ->where('tenant_id', $sahodayaId)
            ->whereHas('roles', fn ($q) => $q->whereIn('name', ['sahodaya_admin', 'sahodaya_staff']))
            ->limit(20)
            ->get();

        foreach ($admins as $admin) {
            $this->notifications->notifyFromTemplate(
                $admin,
                $slug,
                $vars,
                "/sahodaya-admin/{$sahodayaId}/board-results/verification",
            );
        }
    }
}
