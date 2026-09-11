<?php

namespace App\Services\Audit;

use App\Models\AuditLog;
use App\Models\FestAppeal;
use App\Models\FestEvent;
use App\Models\FestRegistration;
use App\Models\ImpersonationSession;
use App\Models\McqExam;
use App\Models\McqRegistration;
use App\Models\MembershipPayment;
use App\Models\SubscriptionInvoice;
use App\Models\SubscriptionPlan;
use App\Models\SubscriptionReceipt;
use App\Models\Tenant;
use App\Models\TenantSubscription;
use App\Models\User;
use App\Models\PlatformUser;
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
        ?string $tenantId = null,
    ): AuditLog {
        // Device context (user agent) is folded into every write here, rather than left
        // to each of the ~40 call sites across the app, so the activity log can show
        // "what device did this" for anything logged through this class — not just the
        // handful of callers that happened to think to pass it.
        if ($this->request && ! isset($properties['user_agent'])) {
            $properties['user_agent'] = $this->request->userAgent();
        }

        // Same reasoning as user_agent above, for a different question: a school can
        // cancel/withdraw its own registration through the school portal (see
        // SchoolAdmin\FestRegistrationController::withdraw()), which calls the exact same
        // festRegistrationCancelled() as a Sahodaya/event admin cancelling it from the
        // review queue — same action name, same description shape, no way to tell them
        // apart from the log alone without this. Resolved once here from the acting
        // user's roles rather than per call site, so it's correct everywhere without
        // relying on ~40 call sites remembering to pass it.
        //
        // auth()->id() only checks the default guard ('web'). A school cancelling via the
        // mobile app hits api/v1/school/... routes guarded by auth:sanctum instead — a
        // perfectly real, authenticated request, but invisible to plain auth()->id(), which
        // silently wrote user_id=null (shown as "System" in the activity log, alongside a
        // real ip_address, since that's still a genuine HTTP request — just authenticated
        // under a guard nothing here was checking). Falling back to the sanctum guard
        // recovers the actual actor for every API-originated action logged through this
        // class, not just registration withdrawals.
        $resolvedUserId = $userId ?? auth()->id() ?? auth('sanctum')->id();
        if (! isset($properties['actor_type']) && $resolvedUserId) {
            $properties['actor_type'] = $this->actorType($resolvedUserId);
        }

        $data = [
            'user_id'      => $resolvedUserId,
            'tenant_id'    => $this->resolveTenantId($tenantId, $subject, $properties),
            'category'     => $category ?? AuditLogCatalog::categoryForAction($action),
            'action'       => $action,
            'description'  => $description,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id'   => $subject ? (string) $subject->getKey() : null,
            'ip_address'   => $this->request?->ip(),
            'properties'   => $properties ?: null,
        ];

        try {
            return AuditLog::create($data);
        } catch (\Throwable $e) {
            // Fallback for production databases before `tenant_id` migration runs
            unset($data['tenant_id']);
            try {
                return AuditLog::create($data);
            } catch (\Throwable $e2) {
                \Illuminate\Support\Facades\Log::warning('PlatformAuditLogger write failed: '.$e2->getMessage());
                return new AuditLog($data);
            }
        }
    }

    /**
     * Coarse school-vs-admin bucket, not the user's specific role — that's what the
     * activity log actually needs to answer "who cancelled this, the school or us."
     * Every role prefixed `school_` (school_admin, school_principal, school_event_coordinator,
     * ...) buckets as School; every other role (sahodaya_admin, event_admin,
     * registration_coordinator, mark_entry_admin, ...) buckets as Sahodaya/Event Admin,
     * since a registration can only be reviewed/cancelled by someone on that side.
     */
    private function actorType(int $userId): ?string
    {
        $user = auth()->id() === $userId ? auth()->user() : User::find($userId);
        if (! $user || ! method_exists($user, 'getRoleNames')) {
            return null;
        }

        $roles = $user->getRoleNames();
        if ($roles->isEmpty()) {
            return null;
        }

        return $roles->contains(fn ($role) => str_starts_with($role, 'school_'))
            ? 'School'
            : 'Sahodaya/Event Admin';
    }

    // RPT-01 fix (functional audit, 2026-08-11/12): audit_logs is a shared,
    // central-connection table, so every write needs an explicit tenant/school
    // attribution or reporting can't scope it — see the migration that added
    // this column for the full story. Priority order: an explicit caller-passed
    // tenant id wins; then whatever the caller already put in $properties
    // (several call sites already carry 'tenant_id' or 'school_id'); then the
    // subject model's own tenant_id if it has one (most models in this app are
    // tenant-scoped); then the currently authenticated user's tenant_id. If
    // none of those resolve, the row is written with a null tenant_id and will
    // simply be invisible to every tenant-scoped report — fail closed, not
    // fail open.
    private function resolveTenantId(?string $tenantId, ?Model $subject, array $properties): ?string
    {
        if ($tenantId !== null) {
            return $tenantId;
        }

        if (! empty($properties['tenant_id']) && is_scalar($properties['tenant_id'])) {
            return (string) $properties['tenant_id'];
        }

        if ($subject !== null && isset($subject->tenant_id) && is_scalar($subject->tenant_id)) {
            return (string) $subject->tenant_id;
        }

        if (! empty($properties['school_id']) && is_scalar($properties['school_id'])) {
            return (string) $properties['school_id'];
        }

        $authTenantId = auth()->user()?->tenant_id ?? null;

        return $authTenantId !== null ? (string) $authTenantId : null;
    }

    // $email is nullable throughout this group of methods: accounts created via
    // TenantUserProvisioner can have no email at all ("log in by username only"),
    // and User::$email is genuinely null for them — passing that through a
    // non-nullable string $email parameter threw a TypeError on every successful
    // login for those accounts.

    /** @param  array<string, mixed>  $context */
    public function login(int $userId, ?string $email, array $context = []): ?AuditLog
    {
        return $this->dispatchAuthLog('login', $userId, $email, $context);
    }

    /** @param  array<string, mixed>  $context */
    public function loginFailed(?string $email, string $reason, ?int $userId = null, array $context = []): ?AuditLog
    {
        return $this->dispatchAuthLog('login.failed', $userId, $email, array_merge(['reason' => $reason], $context));
    }

    /** @param  array<string, mixed>  $context */
    public function loginPortalRejected(int $userId, ?string $email, string $reason, array $context = []): ?AuditLog
    {
        return $this->dispatchAuthLog('login.portal_rejected', $userId, $email, array_merge(['reason' => $reason], $context));
    }

    /** @param  array<string, mixed>  $context */
    public function loginNoPortal(int $userId, ?string $email, array $context = []): ?AuditLog
    {
        return $this->dispatchAuthLog('login.no_portal', $userId, $email, $context);
    }

    /** @param  array<string, mixed>  $context */
    public function loginInactive(int $userId, ?string $email, array $context = []): ?AuditLog
    {
        return $this->dispatchAuthLog('login.inactive', $userId, $email, $context);
    }

    /** @param  array<string, mixed>  $context */
    public function logout(int $userId, ?string $email, array $context = []): ?AuditLog
    {
        return $this->dispatchAuthLog('logout', $userId, $email, $context);
    }

    /** @param  array<string, mixed>  $context */
    private function dispatchAuthLog(string $action, ?int $userId, ?string $email, array $context = []): ?AuditLog
    {
        $context['ip'] = $context['ip'] ?? $this->request?->ip();

        if (config('erp.async_auth_audit', true) && ! app()->runningUnitTests()) {
            dispatch(\App\Jobs\LogAuthEventJob::fromLogin($action, $userId ?? 0, $email, $context));

            return null;
        }

        $label = $email ?? 'username-only account';

        return match ($action) {
            'login' => $this->log('login', "User logged in: {$label}", properties: array_merge(['email' => $email], $context), userId: $userId, category: 'auth'),
            'login.failed' => $this->log('login.failed', 'Failed login attempt', properties: array_merge(['email' => $email, 'reason' => $context['reason'] ?? ''], $context), userId: $userId, category: 'auth'),
            'login.portal_rejected' => $this->log('login.portal_rejected', "Login rejected (wrong portal): {$label}", properties: array_merge(['email' => $email, 'reason' => $context['reason'] ?? ''], $context), userId: $userId, category: 'auth'),
            'login.no_portal' => $this->log('login.no_portal', "Login rejected (no portal): {$label}", properties: array_merge(['email' => $email], $context), userId: $userId, category: 'auth'),
            'logout' => $this->log('logout', "User logged out: {$label}", properties: array_merge(['email' => $email], $context), userId: $userId, category: 'auth'),
            default => null,
        };
    }

    public function userCreated(User|PlatformUser $user): AuditLog
    {
        return $this->log('user.created', "User created: {$user->email}", $user, [
            'roles' => $user->getRoleNames()->values()->all(),
        ]);
    }

    public function userUpdated(User|PlatformUser $user): AuditLog
    {
        return $this->log('user.updated', "User updated: {$user->email}", $user, [
            'roles' => $user->getRoleNames()->values()->all(),
        ]);
    }

    public function userDeleted(User|PlatformUser $user): AuditLog
    {
        return $this->log('user.deleted', "User deleted: {$user->email}", $user, [
            'roles' => $user->getRoleNames()->values()->all(),
        ]);
    }

    public function paymentVerified(MembershipPayment $payment): AuditLog
    {
        // receipt_number included so the audit log can double as searchable receipt history
        // (which receipt number went to which school, when) without having to separately
        // join out to fee_receipts — see the membership receipt ordering/missing investigation.
        $receiptNumber = $payment->feeReceipt?->receipt_number;

        return $this->log(
            'payment.verified',
            "Membership payment verified for school #{$payment->school_id}"
                .($receiptNumber ? " (receipt #{$receiptNumber})" : ''),
            $payment,
            ['amount' => $payment->amount, 'school_id' => $payment->school_id, 'receipt_number' => $receiptNumber],
        );
    }

    public function paymentRejected(MembershipPayment $payment, ?string $reason): AuditLog
    {
        return $this->log(
            'payment.rejected',
            "Membership payment rejected for school #{$payment->school_id}",
            $payment,
            [
                'reason' => $reason,
                'school_id' => $payment->school_id,
                // Normally null — a rejected payment was never issued a receipt number
                // (SahodayaReceiptNumberAllocator only runs on verify/approve). Included for
                // shape consistency with paymentVerified() and in case a payment is rejected
                // after an earlier receipt was reversed/superseded and re-submitted.
                'receipt_number' => $payment->feeReceipt?->receipt_number,
            ],
        );
    }

    public function festRegistrationApproved(FestRegistration $registration, ?string $page = null): AuditLog
    {
        $ctx = $this->registrationContext($registration);

        return $this->log(
            'fest.registration.approved',
            "Fest registration #{$registration->id} approved{$ctx['suffix']}",
            $registration,
            [
                'event_id'    => $registration->event_id,
                'school_id'   => $registration->school_id,
                'school'      => $ctx['school'],
                'item_id'     => $registration->item_id,
                'item_title'  => $ctx['item_title'],
                'participant' => $ctx['participant'],
                'page'        => $page ?? \App\Support\FestPageActivity::REGISTRATIONS,
            ],
        );
    }

    public function festRegistrationRejected(FestRegistration $registration, ?string $page = null, ?string $reason = null): AuditLog
    {
        $ctx = $this->registrationContext($registration);

        return $this->log(
            'fest.registration.rejected',
            "Fest registration #{$registration->id} rejected{$ctx['suffix']}",
            $registration,
            [
                'event_id'    => $registration->event_id,
                'school_id'   => $registration->school_id,
                'school'      => $ctx['school'],
                'item_id'     => $registration->item_id,
                'item_title'  => $ctx['item_title'],
                'participant' => $ctx['participant'],
                'reason'      => $reason ?: $registration->rejection_reason,
                'page'        => $page ?? \App\Support\FestPageActivity::REGISTRATIONS,
            ],
        );
    }

    public function festRegistrationCancelled(FestRegistration $registration, ?string $page = null, ?string $reason = null): AuditLog
    {
        $ctx = $this->registrationContext($registration);

        return $this->log(
            'fest.registration.cancelled',
            "Fest registration #{$registration->id} cancelled{$ctx['suffix']}",
            $registration,
            [
                'event_id'    => $registration->event_id,
                'school_id'   => $registration->school_id,
                'school'      => $ctx['school'],
                'item_id'     => $registration->item_id,
                'item_title'  => $ctx['item_title'],
                'participant' => $ctx['participant'],
                'reason'      => $reason,
                'page'        => $page ?? \App\Support\FestPageActivity::REGISTRATIONS,
            ],
        );
    }

    /**
     * Shared enrichment for registration-level audit entries — item/school/participant
     * names so the activity log shows who/what was actually affected instead of a bare
     * registration id. FestEventActivityService::query() already surfaces
     * properties['school']/['item_title']/['participant'] for any log that carries
     * them; this is what populates those keys for approve/reject/cancel (previously
     * only event_id/school_id were recorded).
     *
     * @return array{item_title: ?string, school: ?string, participant: ?string, suffix: string}
     */
    private function registrationContext(FestRegistration $registration): array
    {
        $registration->loadMissing('item', 'school', 'participants.student', 'participants.teacher', 'participants.group');

        $names = $registration->participants
            ->map(fn ($p) => $p->student?->name ?? $p->teacher?->name ?? $p->group?->team_name)
            ->filter()
            ->unique()
            ->implode(', ');

        $itemTitle = $registration->item?->title;
        $schoolName = $registration->school?->name;

        $suffixParts = array_filter([$itemTitle, $schoolName]);
        $suffix = $suffixParts ? ' ('.implode(' — ', $suffixParts).')' : '';

        return [
            'item_title'  => $itemTitle,
            'school'      => $schoolName,
            'participant' => $names ?: null,
            'suffix'      => $suffix,
        ];
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
        $schoolName = Tenant::find($schoolId)?->name ?? "School #{$schoolId}";

        return $this->log(
            'fest.fee.proof_uploaded',
            "{$schoolName} uploaded fee proof for {$event->title}",
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

    public function portalProvisioned(User|PlatformUser $user, string $role, string $tenantId): AuditLog
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

    public function tenantCreated(Tenant $tenant): AuditLog
    {
        return $this->log(
            'tenant.created',
            "Tenant created: {$tenant->name} ({$tenant->type})",
            $tenant,
            ['tenant_id' => $tenant->id, 'type' => $tenant->type, 'parent_id' => $tenant->parent_id],
            category: 'platform',
        );
    }

    public function tenantUpdated(Tenant $tenant, array $changes = []): AuditLog
    {
        return $this->log(
            'tenant.updated',
            "Tenant updated: {$tenant->name}",
            $tenant,
            ['tenant_id' => $tenant->id, 'changes' => $changes],
            category: 'platform',
        );
    }

    public function tenantDeleted(Tenant $tenant): AuditLog
    {
        return $this->log(
            'tenant.deleted',
            "Tenant deleted: {$tenant->name} ({$tenant->type})",
            $tenant,
            ['tenant_id' => $tenant->id, 'type' => $tenant->type],
            category: 'platform',
        );
    }

    public function tenantDatabaseSaved(Tenant $tenant): AuditLog
    {
        return $this->log(
            'tenant.database_saved',
            "Database connection saved for {$tenant->name}",
            $tenant,
            ['tenant_id' => $tenant->id],
            category: 'platform',
        );
    }

    public function tenantDatabaseMigrated(Tenant $tenant): AuditLog
    {
        return $this->log(
            'tenant.database_migrated',
            "Database migrations run for {$tenant->name}",
            $tenant,
            ['tenant_id' => $tenant->id],
            category: 'platform',
        );
    }

    public function tenantLogoUpdated(Tenant $tenant): AuditLog
    {
        return $this->log(
            'tenant.logo_updated',
            "Logo updated for {$tenant->name}",
            $tenant,
            ['tenant_id' => $tenant->id],
            category: 'platform',
        );
    }

    public function tenantNavVisibilityUpdated(Tenant $tenant): AuditLog
    {
        return $this->log(
            'tenant.nav_visibility_updated',
            "Sidebar menu access updated for {$tenant->name}",
            $tenant,
            ['tenant_id' => $tenant->id, 'nav_overrides' => $tenant->nav_overrides],
            category: 'platform',
        );
    }

    public function tenantMembershipRejected(Tenant $tenant, ?string $reason): AuditLog
    {
        return $this->log(
            'tenant.membership_rejected',
            "Membership rejected for {$tenant->name}",
            $tenant,
            ['tenant_id' => $tenant->id, 'reason' => $reason],
            category: 'platform',
        );
    }

    public function subscriptionPlanCreated(SubscriptionPlan $plan): AuditLog
    {
        return $this->log(
            'subscription.plan_created',
            "Subscription plan created: {$plan->name}",
            $plan,
            ['billing_period' => $plan->billing_period, 'price_inr' => $plan->price_inr],
            category: 'billing',
        );
    }

    public function tenantSubscriptionSaved(TenantSubscription $subscription): AuditLog
    {
        return $this->log(
            'subscription.tenant_subscription_saved',
            "Subscription saved for tenant #{$subscription->tenant_id}",
            $subscription,
            ['tenant_id' => $subscription->tenant_id, 'plan_id' => $subscription->plan_id, 'status' => $subscription->status],
            category: 'billing',
        );
    }

    public function invoiceCreated(SubscriptionInvoice $invoice): AuditLog
    {
        return $this->log(
            'subscription.invoice_created',
            "Invoice {$invoice->invoice_number} generated for tenant #{$invoice->tenant_id}",
            $invoice,
            ['tenant_id' => $invoice->tenant_id, 'amount' => $invoice->amount],
            category: 'billing',
        );
    }

    public function receiptApproved(SubscriptionReceipt $receipt): AuditLog
    {
        $receipt->loadMissing('invoice');

        return $this->log(
            'subscription.receipt_approved',
            "Payment receipt approved for invoice {$receipt->invoice->invoice_number}",
            $receipt,
            ['tenant_id' => $receipt->invoice->tenant_id, 'invoice_id' => $receipt->invoice_id],
            category: 'billing',
        );
    }

    public function receiptRejected(SubscriptionReceipt $receipt, string $reason): AuditLog
    {
        $receipt->loadMissing('invoice');

        return $this->log(
            'subscription.receipt_rejected',
            "Payment receipt rejected for invoice {$receipt->invoice->invoice_number}",
            $receipt,
            ['tenant_id' => $receipt->invoice->tenant_id, 'invoice_id' => $receipt->invoice_id, 'reason' => $reason],
            category: 'billing',
        );
    }

    public function impersonationStarted(ImpersonationSession $session): AuditLog
    {
        return $this->log(
            'impersonation.started',
            "Impersonation started for user #{$session->target_user_id} on tenant {$session->target_tenant_id}",
            $session,
            [
                'tenant_id' => $session->target_tenant_id,
                'actor_platform_user_id' => $session->actor_platform_user_id,
                'target_user_id' => $session->target_user_id,
                'reason' => $session->reason,
            ],
            userId: $session->actor_platform_user_id,
            category: 'impersonation',
        );
    }

    public function impersonationConsumed(ImpersonationSession $session): AuditLog
    {
        return $this->log(
            'impersonation.consumed',
            "Impersonation session #{$session->id} activated for user #{$session->target_user_id}",
            $session,
            [
                'tenant_id' => $session->target_tenant_id,
                'target_user_id' => $session->target_user_id,
            ],
            userId: $session->target_user_id,
            category: 'impersonation',
        );
    }

    public function impersonationEnded(ImpersonationSession $session): AuditLog
    {
        return $this->log(
            'impersonation.ended',
            "Impersonation session #{$session->id} ended for user #{$session->target_user_id}",
            $session,
            [
                'tenant_id' => $session->target_tenant_id,
                'target_user_id' => $session->target_user_id,
                'duration_seconds' => $session->consumed_at ? now()->diffInSeconds($session->consumed_at) : null,
            ],
            userId: $session->target_user_id,
            category: 'impersonation',
        );
    }
}
