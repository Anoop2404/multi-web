<?php

namespace App\Services\BoardResults;

use App\Models\BoardResult;
use App\Models\BoardResultCertificationPackage;
use App\Models\BoardResultCertificationReport;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Notifications\NotificationService;

/**
 * Notifications for the Principal Verification / certification-package workflow —
 * plan §11. Mirrors BoardResultNotifier's structure but targets school leadership
 * (Principal/Vice Principal) in addition to the plain school_admin audience.
 *
 * Every call here is best-effort: notification failures must never block a
 * certification state transition, so callers should wrap these in a try/catch
 * (see BoardResultNotifier usage elsewhere in the codebase for the same pattern).
 */
class BoardResultCertificationNotifier
{
    public const REVIEW_REQUESTED = 'board_result_certification.review_requested';

    public const REPORT_RETURNED = 'board_result_certification.report_returned';

    public const REMINDER = 'board_result_certification.reminder';

    public const SUBMITTED = 'board_result_certification.submitted';

    public const SAHODAYA_RETURNED = 'board_result_certification.sahodaya_returned';

    public const SAHODAYA_VERIFIED = 'board_result_certification.sahodaya_verified';

    public const SAHODAYA_APPROVED = 'board_result_certification.sahodaya_approved';

    public const SAHODAYA_PUBLISHED = 'board_result_certification.sahodaya_published';

    public function __construct(private NotificationService $notifications) {}

    public function reviewRequested(BoardResultCertificationPackage $package): void
    {
        $boardResult = $this->resolveBoardResult($package);
        if (! $boardResult) {
            return;
        }

        $this->notifyLeadership($boardResult, self::REVIEW_REQUESTED, $this->vars($boardResult, [
            'version' => (string) $package->version,
        ]));
    }

    public function reportReturned(BoardResultCertificationReport $report): void
    {
        $package = $report->package;
        $boardResult = $package ? $this->resolveBoardResult($package) : null;
        if (! $boardResult) {
            return;
        }

        $this->notifySchoolAdmin($boardResult, self::REPORT_RETURNED, $this->vars($boardResult, [
            'report_label' => BoardResultCertificationReport::typeLabel($report->report_type),
            'reason' => (string) $report->review_notes,
        ]));
    }

    /** Deduplicate scheduled reminders using the existing reminder-guard pattern at the caller. */
    public function reminder(BoardResultCertificationPackage $package): void
    {
        $boardResult = $this->resolveBoardResult($package);
        if (! $boardResult) {
            return;
        }

        $this->notifyLeadership($boardResult, self::REMINDER, $this->vars($boardResult));
    }

    public function submitted(BoardResultCertificationPackage $package): void
    {
        $boardResult = $this->resolveBoardResult($package);
        if (! $boardResult) {
            return;
        }

        $this->notifySahodayaAdmins($boardResult, self::SUBMITTED, $this->vars($boardResult, [
            'version' => (string) $package->version,
        ]));
    }

    public function sahodayaReturned(BoardResultCertificationPackage $package): void
    {
        $boardResult = $this->resolveBoardResult($package);
        if (! $boardResult) {
            return;
        }

        $vars = $this->vars($boardResult, ['reason' => (string) $package->return_reason]);
        $this->notifySchoolAdmin($boardResult, self::SAHODAYA_RETURNED, $vars);
        $this->notifyLeadership($boardResult, self::SAHODAYA_RETURNED, $vars);
    }

    public function sahodayaVerified(BoardResultCertificationPackage $package): void
    {
        $this->notifySignerAndSchool($package, self::SAHODAYA_VERIFIED);
    }

    public function sahodayaApproved(BoardResultCertificationPackage $package): void
    {
        $this->notifySignerAndSchool($package, self::SAHODAYA_APPROVED);
    }

    public function sahodayaPublished(BoardResultCertificationPackage $package): void
    {
        $this->notifySignerAndSchool($package, self::SAHODAYA_PUBLISHED);
    }

    private function notifySignerAndSchool(BoardResultCertificationPackage $package, string $slug): void
    {
        $boardResult = $this->resolveBoardResult($package);
        if (! $boardResult) {
            return;
        }

        $vars = $this->vars($boardResult);
        $this->notifySchoolAdmin($boardResult, $slug, $vars);

        $signer = $package->signed_by_user_id ? User::find($package->signed_by_user_id) : null;
        if ($signer) {
            $this->notifications->notifyFromTemplate(
                $signer,
                $slug,
                $vars,
                "/school-admin/{$boardResult->tenant_id}/board-results/{$boardResult->id}/principal-verification",
            );
        }
    }

    private function resolveBoardResult(BoardResultCertificationPackage $package): ?BoardResult
    {
        return $package->boardResult ?? BoardResult::find($package->board_result_id);
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
            "/school-admin/{$school->id}/board-results/{$boardResult->id}/principal-verification",
        );
    }

    /** @param  array<string, string>  $vars */
    private function notifyLeadership(BoardResult $boardResult, string $slug, array $vars): void
    {
        $school = Tenant::find($boardResult->tenant_id);
        if (! $school) {
            return;
        }

        $leaders = User::query()
            ->where('tenant_id', $school->id)
            ->whereHas('roles', fn ($q) => $q->whereIn('name', ['school_principal', 'school_vice_principal']))
            ->limit(10)
            ->get();

        foreach ($leaders as $leader) {
            $this->notifications->notifyFromTemplate(
                $leader,
                $slug,
                $vars,
                "/school-admin/{$school->id}/board-results/{$boardResult->id}/principal-verification",
            );
        }
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
                "/sahodaya-admin/{$sahodayaId}/board-results/certifications",
            );
        }
    }
}
