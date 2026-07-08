<?php

namespace App\Services\Audit;

use App\Models\AuditLog;
use App\Models\FestAppeal;
use App\Models\FestEvent;
use App\Models\FestRegistration;
use App\Models\McqExam;
use App\Models\McqRegistration;
use App\Models\MembershipPayment;
use App\Models\User;
use App\Support\AuditLogCatalog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class PlatformAuditLogger
{
    public function __construct(private ?Request $request = null) {}

    public function log(
        string $action,
        string $description,
        ?Model $subject = null,
        array $properties = [],
        ?int $userId = null,
        ?string $category = null,
    ): AuditLog {
        return AuditLog::create([
            'user_id'      => $userId ?? auth()->id(),
            'category'     => $category ?? AuditLogCatalog::categoryForAction($action),
            'action'       => $action,
            'description'  => $description,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id'   => $subject ? (string) $subject->getKey() : null,
            'ip_address'   => $this->request?->ip(),
            'properties'   => $properties ?: null,
        ]);
    }

    /** @param  array<string, mixed>  $context */
    public function login(int $userId, string $email, array $context = []): ?AuditLog
    {
        return $this->dispatchAuthLog('login', $userId, $email, $context);
    }

    /** @param  array<string, mixed>  $context */
    public function loginFailed(string $email, string $reason, ?int $userId = null, array $context = []): ?AuditLog
    {
        return $this->dispatchAuthLog('login.failed', $userId, $email, array_merge(['reason' => $reason], $context));
    }

    /** @param  array<string, mixed>  $context */
    public function loginPortalRejected(int $userId, string $email, string $reason, array $context = []): ?AuditLog
    {
        return $this->dispatchAuthLog('login.portal_rejected', $userId, $email, array_merge(['reason' => $reason], $context));
    }

    /** @param  array<string, mixed>  $context */
    public function loginNoPortal(int $userId, string $email, array $context = []): ?AuditLog
    {
        return $this->dispatchAuthLog('login.no_portal', $userId, $email, $context);
    }

    /** @param  array<string, mixed>  $context */
    public function logout(int $userId, string $email, array $context = []): ?AuditLog
    {
        return $this->dispatchAuthLog('logout', $userId, $email, $context);
    }

    /** @param  array<string, mixed>  $context */
    private function dispatchAuthLog(string $action, ?int $userId, string $email, array $context = []): ?AuditLog
    {
        $context['ip'] = $context['ip'] ?? $this->request?->ip();

        if (config('erp.async_auth_audit', true) && ! app()->runningUnitTests()) {
            dispatch(\App\Jobs\LogAuthEventJob::fromLogin($action, $userId ?? 0, $email, $context));

            return null;
        }

        return match ($action) {
            'login' => $this->log('login', "User logged in: {$email}", properties: array_merge(['email' => $email], $context), userId: $userId, category: 'auth'),
            'login.failed' => $this->log('login.failed', 'Failed login attempt', properties: array_merge(['email' => $email, 'reason' => $context['reason'] ?? ''], $context), userId: $userId, category: 'auth'),
            'login.portal_rejected' => $this->log('login.portal_rejected', "Login rejected (wrong portal): {$email}", properties: array_merge(['email' => $email, 'reason' => $context['reason'] ?? ''], $context), userId: $userId, category: 'auth'),
            'login.no_portal' => $this->log('login.no_portal', "Login rejected (no portal): {$email}", properties: array_merge(['email' => $email], $context), userId: $userId, category: 'auth'),
            'logout' => $this->log('logout', "User logged out: {$email}", properties: array_merge(['email' => $email], $context), userId: $userId, category: 'auth'),
            default => null,
        };
    }

    public function userCreated(User $user): AuditLog
    {
        return $this->log('user.created', "User created: {$user->email}", $user, [
            'roles' => $user->getRoleNames()->values()->all(),
        ]);
    }

    public function userUpdated(User $user): AuditLog
    {
        return $this->log('user.updated', "User updated: {$user->email}", $user, [
            'roles' => $user->getRoleNames()->values()->all(),
        ]);
    }

    public function userDeleted(User $user): AuditLog
    {
        return $this->log('user.deleted', "User deleted: {$user->email}", $user, [
            'roles' => $user->getRoleNames()->values()->all(),
        ]);
    }

    public function paymentVerified(MembershipPayment $payment): AuditLog
    {
        return $this->log(
            'payment.verified',
            "Membership payment verified for school #{$payment->school_id}",
            $payment,
            ['amount' => $payment->amount, 'school_id' => $payment->school_id],
        );
    }

    public function paymentRejected(MembershipPayment $payment, ?string $reason): AuditLog
    {
        return $this->log(
            'payment.rejected',
            "Membership payment rejected for school #{$payment->school_id}",
            $payment,
            ['reason' => $reason, 'school_id' => $payment->school_id],
        );
    }

    public function festRegistrationApproved(FestRegistration $registration, ?string $page = null): AuditLog
    {
        return $this->log(
            'fest.registration.approved',
            "Fest registration #{$registration->id} approved",
            $registration,
            [
                'event_id' => $registration->event_id,
                'school_id' => $registration->school_id,
                'page' => $page ?? \App\Support\FestPageActivity::REGISTRATIONS,
            ],
        );
    }

    public function festRegistrationRejected(FestRegistration $registration, ?string $page = null): AuditLog
    {
        return $this->log(
            'fest.registration.rejected',
            "Fest registration #{$registration->id} rejected",
            $registration,
            [
                'event_id' => $registration->event_id,
                'school_id' => $registration->school_id,
                'page' => $page ?? \App\Support\FestPageActivity::REGISTRATIONS,
            ],
        );
    }

    public function festRegistrationCancelled(FestRegistration $registration, ?string $page = null): AuditLog
    {
        return $this->log(
            'fest.registration.cancelled',
            "Fest registration #{$registration->id} cancelled",
            $registration,
            [
                'event_id' => $registration->event_id,
                'school_id' => $registration->school_id,
                'page' => $page ?? \App\Support\FestPageActivity::REGISTRATIONS,
            ],
        );
    }

    public function festRegistrationSubmitted(FestRegistration $registration): AuditLog
    {
        return $this->log(
            'fest.registration.submitted',
            "School submitted fest registration #{$registration->id}",
            $registration,
            [
                'event_id'  => $registration->event_id,
                'school_id' => $registration->school_id,
                'item_id'   => $registration->item_id,
            ],
        );
    }

    public function festFeeProofUploaded(FestEvent $event, string $schoolId): AuditLog
    {
        return $this->log(
            'fest.fee.proof_uploaded',
            "School #{$schoolId} uploaded fee proof for {$event->title}",
            $event,
            ['event_id' => $event->id, 'school_id' => $schoolId],
        );
    }

    public function mcq(
        McqExam $exam,
        string $action,
        string $description,
        array $properties = [],
        ?Model $subject = null,
    ): AuditLog {
        return $this->log($action, $description, $subject ?? $exam, array_merge([
            'exam_id'   => $exam->id,
            'tenant_id' => $exam->tenant_id,
        ], $properties), category: 'mcq');
    }

    public function mcqRegistration(McqRegistration $registration, string $action, string $description): AuditLog
    {
        $registration->loadMissing('exam');

        return $this->mcq(
            $registration->exam,
            $action,
            $description,
            [
                'registration_id' => $registration->id,
                'school_id'       => $registration->school_id,
                'student_id'      => $registration->student_id,
            ],
            $registration,
        );
    }

    public function training(
        \App\Models\TrainingProgram $program,
        string $action,
        string $description,
        array $properties = [],
        ?Model $subject = null,
    ): AuditLog {
        return $this->log($action, $description, $subject ?? $program, array_merge([
            'program_id' => $program->id,
            'tenant_id'  => $program->tenant_id,
        ], $properties), category: 'training');
    }

    public function portalProvisioned(User $user, string $role, string $tenantId): AuditLog
    {
        return $this->log(
            'portal.provisioned',
            "Portal account provisioned for {$user->email} ({$role})",
            $user,
            ['role' => $role, 'tenant_id' => $tenantId],
            category: 'users',
        );
    }

    public function judgeMarkEntered(FestEvent $event, int $participantId, int $itemId): AuditLog
    {
        return $this->festEvent(
            $event,
            \App\Support\FestPageActivity::MARKS,
            'fest.mark.entered',
            "Judge entered mark for participant #{$participantId}",
            ['participant_id' => $participantId, 'item_id' => $itemId],
        );
    }

    public function festAppealResolved(FestAppeal $appeal, string $status): AuditLog
    {
        return $this->log(
            'fest.appeal.resolved',
            "Fest appeal #{$appeal->id} {$status}",
            $appeal,
            [
                'event_id' => $appeal->event_id,
                'status'   => $status,
                'page'     => \App\Support\FestPageActivity::APPEALS,
            ],
        );
    }

    public function festPromotionCompleted(FestEvent $event, int $count, array $meta = []): AuditLog
    {
        return $this->log(
            'fest.promotion.completed',
            "Promoted {$count} participant(s) to {$event->title}",
            $event,
            array_merge(['promoted' => $count, 'event_id' => $event->id, 'page' => $meta['page'] ?? null], $meta),
        );
    }

    /** Log a fest admin action scoped to an event page. */
    public function festEvent(
        FestEvent $event,
        string $page,
        string $action,
        string $description,
        array $properties = [],
        ?Model $subject = null,
    ): AuditLog {
        return $this->log($action, $description, $subject ?? $event, array_merge([
            'event_id'  => $event->id,
            'tenant_id' => $event->tenant_id,
            'page'      => $page,
        ], $properties));
    }

    public function festCatalog(
        string $tenantId,
        string $program,
        string $page,
        string $action,
        string $description,
        array $properties = [],
        ?Model $subject = null,
    ): AuditLog {
        return $this->log($action, $description, $subject, array_merge([
            'tenant_id' => $tenantId,
            'program'   => $program,
            'page'      => $page,
        ], $properties));
    }
    public function reportDownloaded(string $reportName, array $filters = []): AuditLog
    {
        return $this->log(
            'report.downloaded',
            "Report downloaded: {$reportName}",
            properties: array_merge(['report' => $reportName], $filters),
            category: 'system',
        );
    }
}
