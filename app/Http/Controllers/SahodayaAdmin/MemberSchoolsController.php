<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Http\Controllers\SahodayaAdmin\Concerns\BuildsMembershipExports;
use App\Models\MembershipPayment;
use App\Models\Registration;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Audit\DataChangeLogger;
use App\Services\Audit\PlatformAuditLogger;
use App\Services\Auth\UserCredentialService;
use App\Services\Membership\MembershipNotifier;
use App\Services\Membership\MembershipPaymentApprovalService;
use App\Services\Membership\SchoolMembershipCancellationService;
use App\Services\Tenancy\SchoolDataPurger;
use App\Services\Mail\SahodayaMailer;
use App\Support\TenantAuth;
use App\Support\TenantStorage;
use App\Support\AcademicYear;
use App\Support\ExcelExport;
use App\Support\SchoolDetailFields;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MemberSchoolsController extends SahodayaAdminController
{
    use BuildsMembershipExports;

    public function index(Request $request)
    {
        $filters = $this->schoolListFilters($request);
        $sortColumn = match ($filters['sort']) {
            'school_prefix', 'created_at' => $filters['sort'],
            default                       => 'name',
        };

        $schools = $this->verifiedSchoolsQuery($this->sahodaya->id, $filters)
            ->paginate(20)
            ->withQueryString();

        $year = AcademicYear::forSahodaya($this->sahodaya->id);
        $this->attachSchoolMetrics($schools->getCollection());
        $this->attachSchoolPaymentStatuses($schools->getCollection(), $this->sahodaya->id, $year);

        $approvedIds = Tenant::where('parent_id', $this->sahodaya->id)
            ->where('type', 'school')
            ->where('membership_status', 'approved')
            ->pluck('id');

        return $this->inertia('Sahodaya/Schools/Index', [
            'schools'  => $schools,
            'filters'  => array_merge($filters, ['sort' => $sortColumn]),
            'verifiedCount' => $approvedIds->count(),
            'summary' => [
                'total_students' => $approvedIds->isEmpty()
                    ? 0
                    : Student::whereIn('tenant_id', $approvedIds)->where('status', 'active')->count(),
                'total_classes' => \App\Models\MasterClass::forSahodaya($this->sahodaya->id)->active()->count(),
            ],
        ]);
    }

    public function applications(Request $request)
    {
        $filters = $this->schoolListFilters($request);

        $schools = Tenant::where('parent_id', $this->sahodaya->id)
            ->where('type', 'school')
            ->where('membership_status', 'pending')
            ->when($filters['search'] !== '', function ($q) use ($filters) {
                $search = $filters['search'];
                $q->where(function ($inner) use ($search) {
                    $inner->where('name', 'like', "%{$search}%")
                        ->orWhere('school_prefix', 'like', "%{$search}%")
                        ->orWhere('application_payload->cbse_affiliation', 'like', "%{$search}%")
                        ->orWhere('application_payload->affiliation_number', 'like', "%{$search}%")
                        ->orWhere('application_payload->school_email', 'like', "%{$search}%")
                        ->orWhere('application_payload->contact_email', 'like', "%{$search}%")
                        ->orWhere('application_payload->phone', 'like', "%{$search}%")
                        ->orWhere('application_payload->contact_phone', 'like', "%{$search}%");
                });
            })
            ->when($filters['date_from'], fn ($q) => $q->whereDate('created_at', '>=', $filters['date_from']))
            ->when($filters['date_to'], fn ($q) => $q->whereDate('created_at', '<=', $filters['date_to']))
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        $year = AcademicYear::forSahodaya($this->sahodaya->id);

        $pageIds = $schools->getCollection()->pluck('id');
        $schoolIdsWithUser = TenantAuth::withTenantUsers($this->sahodaya, function () use ($pageIds) {
            return User::whereIn('tenant_id', $pageIds)->pluck('tenant_id')->unique()->all();
        }) ?? [];

        // Latest submitted/verified membership payment per school for this academic year, batched for the whole page.
        $latestPaymentsBySchool = MembershipPayment::whereIn('school_id', $pageIds)
            ->where('academic_year', $year)
            ->whereIn('status', ['submitted', 'verified'])
            ->orderByDesc('created_at')
            ->get()
            ->groupBy('school_id')
            ->map(fn ($payments) => $payments->first());

        $schools->getCollection()->transform(function (Tenant $school) use ($schoolIdsWithUser, $latestPaymentsBySchool) {
            $payload = $school->application_payload ?? [];
            $school->setAttribute('contact_email', $payload['school_email'] ?? $payload['contact_email'] ?? null);
            $school->setAttribute('contact_phone', $payload['phone'] ?? $payload['contact_phone'] ?? null);
            $school->setAttribute('affiliation', $payload['cbse_affiliation'] ?? $payload['affiliation_number'] ?? null);

            $payment = $latestPaymentsBySchool->get($school->id);
            $payment?->setRelation('school', $school);

            $school->setAttribute('has_payment', $payment !== null);
            $school->setAttribute('payment_status', $payment?->status);
            $school->setAttribute('payment_amount', $payment?->amount);
            $school->setAttribute('payment_proof_url', $payment?->proof_url);
            $school->setAttribute('has_login', in_array($school->id, $schoolIdsWithUser, true));

            return $school;
        });

        return $this->inertia('Sahodaya/Schools/Applications', [
            'schools' => $schools,
            'filters' => $filters,
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $filters = $this->schoolListFilters($request);
        $schools = $this->verifiedSchoolsQuery($this->sahodaya->id, $filters)->get();

        $schoolIds = $schools->pluck('id');
        $classCounts = $this->classCountsFor($schoolIds);
        $studentCounts = $this->studentCountsFor($schoolIds);

        $rows = $schools->map(function (Tenant $school) use ($classCounts, $studentCounts) {
            $payload = $school->application_payload ?? [];

            return [
                $school->name,
                $school->school_prefix ?? '',
                $payload['cbse_affiliation'] ?? $payload['affiliation_number'] ?? '',
                $payload['school_email'] ?? $payload['contact_email'] ?? '',
                $payload['phone'] ?? $payload['contact_phone'] ?? '',
                (int) ($studentCounts[$school->id] ?? 0),
                (int) ($classCounts[$school->id] ?? 0),
                $school->created_at?->format('Y-m-d') ?? '',
            ];
        });

        return ExcelExport::download('verified-schools', [
            'School', 'Code', 'Affiliation No.', 'Email', 'Phone', 'Students', 'Classes', 'Joined',
        ], $rows);
    }

    public function show(string $tenantId, Tenant $school)
    {
        abort_if($school->parent_id !== $this->sahodaya->id || $school->type !== 'school', 404);

        $year = AcademicYear::forSahodaya($this->sahodaya->id);
        $payload = $school->application_payload ?? [];
        $fields  = SchoolDetailFields::fromPayload($payload);

        $registration = Registration::where('school_id', $school->id)
            ->where('academic_year', $year)
            ->with('submission')
            ->first();

        $payments = MembershipPayment::where('school_id', $school->id)
            ->orderByDesc('created_at')
            ->limit(10)
            ->get()
            ->each(fn ($payment) => $payment->setRelation('school', $school));

        $cancellation = app(SchoolMembershipCancellationService::class);

        $payment = MembershipPayment::where('school_id', $school->id)
            ->where('academic_year', $year)
            ->whereIn('status', ['submitted', 'verified'])
            ->latest()
            ->first();

        $loginUser = $this->schoolLoginUser($school);

        return $this->inertia('Sahodaya/Schools/Show', [
            'school' => array_merge($school->only(
                'id', 'name', 'school_prefix', 'membership_status', 'is_non_affiliated', 'is_active',
                'fest_registration_closed', 'subdomain', 'created_at', 'application_payload'
            ), [
                'contact_email'  => $payload['school_email'] ?? $payload['contact_email'] ?? null,
                'admin_note'     => $payload['admin_note'] ?? null,
                'student_count'  => Student::where('tenant_id', $school->id)->where('status', 'active')->count(),
                'classes_count'  => SchoolClass::where('tenant_id', $school->id)->where('is_active', true)->count(),
                'has_login'      => $loginUser !== null,
                'login_email'    => $loginUser?->email,
                'login_user'     => $loginUser ? [
                    'id'                => $loginUser->id,
                    'name'              => $loginUser->name,
                    'email'             => $loginUser->email,
                    'username'          => $loginUser->username ?: $loginUser->email,
                    'email_verified'    => $loginUser->hasVerifiedEmail(),
                    'email_verified_at' => $loginUser->email_verified_at?->toIso8601String(),
                    'created_at'        => $loginUser->created_at?->toIso8601String(),
                ] : null,
                'can_cancel_membership' => $cancellation->canCancel($school),
                'can_cancel_with_settlement' => $school->membership_status === 'approved' && !$cancellation->canCancel($school),
                'has_payment'    => $payment !== null,
                'payment_proof_url' => $payment?->proof_url,
                'payment_amount' => $payment?->amount,
            ]),
            'detailFields'   => $fields,
            'registration'   => $registration,
            'recentPayments' => $payments,
            'academicYear'   => $year,
        ]);
    }

    public function updateAdminNote(Request $request, string $tenantId, Tenant $school, PlatformAuditLogger $audit)
    {
        abort_if($school->parent_id !== $this->sahodaya->id || $school->type !== 'school', 404);

        $data = $request->validate([
            'admin_note' => 'nullable|string|max:2000',
        ]);

        $payload = $school->application_payload ?? [];
        $payload['admin_note'] = $data['admin_note'];
        $school->update(['application_payload' => $payload]);

        $audit->log(
            'membership.school.note_updated',
            "Admin note updated for school {$school->name}",
            null,
            ['school_id' => $school->id, 'sahodaya_id' => $this->sahodaya->id, 'note' => $data['admin_note']],
        );

        return back()->with('success', 'Admin note saved successfully.');
    }

    public function uploadPaymentProof(Request $request, string $tenantId, Tenant $school, PlatformAuditLogger $audit, MembershipNotifier $notifier, MembershipPaymentApprovalService $approvalService)
    {
        abort_if($school->parent_id !== $this->sahodaya->id || $school->type !== 'school', 404);

        $data = $request->validate([
            'amount'            => 'required|numeric|min:0',
            'payment_reference' => 'nullable|string|max:255',
            'proof'             => 'nullable|file|mimes:pdf,png,jpg,jpeg|max:5120',
            'notes'             => 'nullable|string|max:1000',
            'status'            => 'required|in:verified,submitted',
        ]);

        $year = AcademicYear::forSahodaya($this->sahodaya->id);

        $proofPath = null;
        if ($request->hasFile('proof')) {
            $proofPath = TenantStorage::storeUploadedFile($request->file('proof'), 'membership/payment_proofs');
        }

        $registration = Registration::where('school_id', $school->id)
            ->where('academic_year', $year)
            ->first();

        // Always land the row as 'submitted' first and route a 'verified' request through
        // the same MembershipPaymentApprovalService::verify() the school-submitted flow uses.
        // Creating it as 'verified' directly (the old behavior) left Registration.amount_paid
        // and registration_status untouched, so the payment-due tabs/notes (which read those
        // cached columns, not the payment row) kept showing the school as unpaid.
        $payment = MembershipPayment::create([
            'school_id'            => $school->id,
            'academic_year'        => $year,
            'registration_id'      => $registration?->id,
            'amount'               => $data['amount'],
            'payment_reference'    => $data['payment_reference'],
            'payment_proof_path'   => $proofPath,
            'status'               => 'submitted',
            'notes'                => $data['notes'],
            'uploaded_by_user_id'  => $request->user()?->id,
        ]);

        if ($registration) {
            $registration->update(['registration_status' => 'payment_submitted']);
        }

        if ($data['status'] === 'verified') {
            $payment = $approvalService->verify($payment, $request->user(), $notifier, $audit);
        }

        $audit->log(
            'membership.payment.uploaded_by_admin',
            "Payment proof uploaded by Sahodaya Admin for {$school->name} (₹{$data['amount']})",
            null,
            ['school_id' => $school->id, 'payment_id' => $payment->id, 'status' => $payment->status],
        );

        return back()->with('success', "Payment proof uploaded and set to {$payment->status} for {$school->name}.");
    }

    public function reject(Request $request, string $tenantId, Tenant $school, MembershipNotifier $notifier)
    {
        abort_if($school->parent_id !== $this->sahodaya->id, 403);

        $data = $request->validate(['reason' => 'required|string|max:1000']);

        $school->update([
            'membership_status'   => 'rejected',
            'is_active'           => false,
            'application_payload' => array_merge($school->application_payload ?? [], [
                'rejection_reason' => $data['reason'],
            ]),
        ]);

        $notifier->schoolRejected($school, $data['reason']);

        return back()->with('success', 'School application rejected.');
    }

    public function cancelMembership(
        Request $request,
        string $tenantId,
        Tenant $school,
        SchoolMembershipCancellationService $cancellation,
        MembershipNotifier $notifier,
        PlatformAuditLogger $audit,
    ) {
        abort_if($school->parent_id !== $this->sahodaya->id || $school->type !== 'school', 404);

        $data = $request->validate([
            'reason' => 'required|string|max:1000',
            'settlement' => 'nullable|in:credit_next_year,forfeit',
        ]);

        if (isset($data['settlement'])) {
            $cancellation->cancelWithSettlement($school, $data['reason'], $data['settlement'], $notifier, $audit, $request->user()?->id);
            return back()->with('success', "Membership cancelled for {$school->name} with settlement: {$data['settlement']}.");
        }

        $cancellation->cancel($school, $data['reason'], $notifier, $audit, $request->user()?->id);

        return back()->with('success', "Membership cancelled for {$school->name}.");
    }

    public function updateSchoolEmail(
        Request $request,
        string $tenantId,
        Tenant $school,
        PlatformAuditLogger $audit,
    ) {
        abort_if($school->parent_id !== $this->sahodaya->id || $school->type !== 'school', 404);

        return TenantAuth::withTenantUsers($school, function () use ($request, $school, $audit) {
            $loginUser = $this->schoolLoginUser($school);

            $rules = ['email' => ['required', 'email', 'max:255']];
            if ($loginUser) {
                $rules['email'][] = Rule::unique('users', 'email')->ignore($loginUser->id);
            }

            $data = $request->validate($rules);
            $newEmail = strtolower(trim($data['email']));

            $before = [
                'school_email' => $school->application_payload['school_email'] ?? $school->application_payload['contact_email'] ?? null,
                'login_email'  => $loginUser?->email,
            ];

            $payload = $school->application_payload ?? [];
            $payload['school_email'] = $newEmail;
            $payload['contact_email'] = $newEmail;
            $payload['updated_at'] = now()->toIso8601String();
            $school->update(['application_payload' => $payload]);

            $emailSent = true;
            if ($loginUser) {
                $loginUser->forceFill([
                    'email' => $newEmail,
                    'email_verified_at' => null,
                ])->save();

                try {
                    SahodayaMailer::for($school->parent_id)->sendVerification($loginUser->fresh());
                } catch (\Throwable $e) {
                    $emailSent = false;
                    \Illuminate\Support\Facades\Log::error('Failed to send verification email for school email update', [
                        'school_id' => $school->id,
                        'email'     => $newEmail,
                        'error'     => $e->getMessage(),
                    ]);
                }
            }

            app(DataChangeLogger::class)->updated(
                $school,
                "School email updated: {$school->name}",
                DataChangeLogger::diff($before, [
                    'school_email' => $newEmail,
                    'login_email'  => $newEmail,
                ]),
                $school->id,
                'membership',
            );

            $audit->log(
                'membership.school.email.updated',
                "School email updated for {$school->name}",
                $school,
                ['updated_by_user_id' => $request->user()?->id, 'email' => $newEmail],
            );

            $message = $emailSent
                ? "School email updated for {$school->name}."
                : "School email updated for {$school->name} (Note: Verification email delivery failed — please check ZeptoMail/SMTP credentials).";

            return back()->with('success', $message);
        });
    }

    public function resendSchoolCredentials(
        Request $request,
        string $tenantId,
        Tenant $school,
        MembershipNotifier $notifier,
        PlatformAuditLogger $audit,
    ) {
        abort_if($school->parent_id !== $this->sahodaya->id || $school->type !== 'school', 404);

        return TenantAuth::withTenantUsers($school, function () use ($request, $school, $notifier, $audit) {
            $loginUser = $this->schoolLoginUser($school);
            abort_unless($loginUser, 422, 'No school login exists to resend credentials.');

            $plainPassword = $loginUser->plain_password;
            abort_unless(is_string($plainPassword) && trim($plainPassword) !== '', 422, 'No stored temporary password is available. Reset the password instead.');

            $notifier->schoolCredentialsIssued($loginUser->fresh(), $plainPassword, $school);

            $audit->log(
                'membership.school.credentials.resent',
                "School credentials resent for {$school->name}",
                $school,
                ['updated_by_user_id' => $request->user()?->id, 'user_id' => $loginUser->id],
            );

            return back()->with('success', "Credentials resent to {$loginUser->email}.");
        });
    }

    public function resetSchoolPassword(
        Request $request,
        string $tenantId,
        Tenant $school,
        UserCredentialService $credentials,
        MembershipNotifier $notifier,
        PlatformAuditLogger $audit,
    ) {
        abort_if($school->parent_id !== $this->sahodaya->id || $school->type !== 'school', 404);

        return TenantAuth::withTenantUsers($school, function () use ($request, $school, $credentials, $notifier, $audit) {
            $loginUser = $this->schoolLoginUser($school);
            abort_unless($loginUser, 422, 'No school login exists to reset.');

            $result = $credentials->resetPassword($loginUser, $request->user()?->id);
            $notifier->schoolCredentialsIssued($result['user'], $result['password'], $school);

            $audit->log(
                'membership.school.password.reset',
                "School password reset for {$school->name}",
                $school,
                ['updated_by_user_id' => $request->user()?->id, 'user_id' => $result['user']->id],
            );

            return back()->with('success', "Password reset for {$result['user']->email}. A new temporary password was emailed.");
        });
    }

    public function bulkCancelMembership(
        Request $request,
        SchoolMembershipCancellationService $cancellation,
        MembershipNotifier $notifier,
        PlatformAuditLogger $audit,
    ) {
        $data = $request->validate([
            'school_ids'   => 'required|array|min:1|max:100',
            'school_ids.*' => 'uuid',
            'reason'       => 'required|string|max:1000',
        ]);

        $schools = Tenant::query()
            ->where('parent_id', $this->sahodaya->id)
            ->where('type', 'school')
            ->where('membership_status', 'approved')
            ->whereIn('id', $data['school_ids'])
            ->get();

        $count = $cancellation->cancelMany(
            $schools,
            $data['reason'],
            $notifier,
            $audit,
            $request->user()?->id,
        );

        return back()->with(
            'success',
            $count === 0
                ? 'No eligible schools cancelled (need approved + no payment upload).'
                : "{$count} school membership(s) cancelled."
        );
    }

    public function bulkResetPassword(
        Request $request,
        UserCredentialService $credentials,
        MembershipNotifier $notifier,
        PlatformAuditLogger $audit,
    ) {
        $data = $request->validate([
            'school_ids'   => 'required|array|min:1|max:50',
            'school_ids.*' => 'uuid',
        ]);

        $schools = Tenant::query()
            ->where('parent_id', $this->sahodaya->id)
            ->where('type', 'school')
            ->where('membership_status', 'approved')
            ->whereIn('id', $data['school_ids'])
            ->get();

        $resetCount = 0;

        foreach ($schools as $school) {
            $didReset = TenantAuth::withTenantUsers($school, function () use ($school, $credentials, $notifier, $audit, $request) {
                $loginUser = $this->schoolLoginUser($school);
                if (! $loginUser) {
                    return false;
                }

                $result = $credentials->resetPassword($loginUser, $request->user()?->id);
                $notifier->schoolCredentialsIssued($result['user'], $result['password'], $school);

                $audit->log(
                    'membership.school.password.reset',
                    "School password reset for {$school->name}",
                    $school,
                    ['updated_by_user_id' => $request->user()?->id, 'user_id' => $result['user']->id, 'bulk' => true],
                );

                return true;
            });

            if ($didReset) {
                $resetCount++;
            }
        }

        if ($resetCount === 0) {
            return back()->with('error', 'No schools had a login to reset.');
        }

        $skipped = $schools->count() - $resetCount;
        $message = "{$resetCount} school password(s) reset and emailed.";
        if ($skipped > 0) {
            $message .= " {$skipped} skipped (no login found).";
        }

        return back()->with('success', $message);
    }

    public function bulkSendCredentials(
        Request $request,
        MembershipNotifier $notifier,
        PlatformAuditLogger $audit,
    ) {
        $data = $request->validate([
            'school_ids'   => 'required|array|min:1|max:50',
            'school_ids.*' => 'uuid',
        ]);

        $schools = Tenant::query()
            ->where('parent_id', $this->sahodaya->id)
            ->where('type', 'school')
            ->where('membership_status', 'approved')
            ->whereIn('id', $data['school_ids'])
            ->get();

        $sentCount = 0;

        foreach ($schools as $school) {
            $didSend = TenantAuth::withTenantUsers($school, function () use ($school, $notifier, $audit, $request) {
                $loginUser = $this->schoolLoginUser($school);
                if (! $loginUser) {
                    return false;
                }

                $plainPassword = $loginUser->plain_password;
                if (! is_string($plainPassword) || trim($plainPassword) === '') {
                    return false;
                }

                $notifier->schoolCredentialsIssued($loginUser->fresh(), $plainPassword, $school);

                $audit->log(
                    'membership.school.credentials.resent',
                    "School credentials resent for {$school->name}",
                    $school,
                    ['updated_by_user_id' => $request->user()?->id, 'user_id' => $loginUser->id, 'bulk' => true],
                );

                return true;
            });

            if ($didSend) {
                $sentCount++;
            }
        }

        if ($sentCount === 0) {
            return back()->with('error', 'No schools had a stored password to resend — use bulk reset instead.');
        }

        $skipped = $schools->count() - $sentCount;
        $message = "Credentials resent to {$sentCount} school(s).";
        if ($skipped > 0) {
            $message .= " {$skipped} skipped (no login or no stored password).";
        }

        return back()->with('success', $message);
    }

    public function createSchoolLogin(
        Request $request,
        string $tenantId,
        Tenant $school,
        MembershipNotifier $notifier,
        PlatformAuditLogger $audit,
    ) {
        abort_if($school->parent_id !== $this->sahodaya->id || $school->type !== 'school', 404);

        return TenantAuth::withTenantUsers($school, function () use ($request, $school, $notifier, $audit) {
            $existingLogin = $this->schoolLoginUser($school);
            if ($existingLogin !== null) {
                return back()->with('error', "A login already exists for {$school->name} ({$existingLogin->email}).");
            }

            $data = $request->validate([
                'email' => 'nullable|email|max:255',
            ]);

            $payload = $school->application_payload ?? [];
            $email = isset($data['email']) && trim($data['email']) !== ''
                ? strtolower(trim($data['email']))
                : strtolower(trim((string) ($payload['school_email'] ?? $payload['contact_email'] ?? '')));

            if ($email === '') {
                return back()->with('error', "No email address found for {$school->name}. Please specify an email address.");
            }

            if (User::where('email', $email)->exists()) {
                return back()->with('error', "The email address {$email} is already in use by another user account.");
            }

            if (($payload['school_email'] ?? null) !== $email) {
                $payload['school_email'] = $email;
                $payload['contact_email'] = $email;
                $school->update(['application_payload' => $payload]);
            }

            $plainPassword = \Illuminate\Support\Str::password(12);

            $user = User::create([
                'tenant_id'            => $school->id,
                'name'                 => $payload['school_name'] ?? $school->name,
                'email'                => $email,
                'email_verified_at'    => now(),
                'password'             => \Illuminate\Support\Facades\Hash::make($plainPassword),
                'plain_password'       => $plainPassword,
                'must_change_password' => true,
            ]);
            $user->assignRole('school_admin');

            $emailSent = true;
            try {
                SahodayaMailer::for($school->parent_id)->sendVerification($user);
            } catch (\Throwable $e) {
                $emailSent = false;
                \Illuminate\Support\Facades\Log::error('Failed to send verification email during school login creation', [
                    'school_id' => $school->id,
                    'email'     => $email,
                    'error'     => $e->getMessage(),
                ]);
            }

            try {
                $notifier->schoolCredentialsIssued($user, $plainPassword, $school);
            } catch (\Throwable $e) {
                $emailSent = false;
                \Illuminate\Support\Facades\Log::error('Failed to send credentials email during school login creation', [
                    'school_id' => $school->id,
                    'email'     => $email,
                    'error'     => $e->getMessage(),
                ]);
            }

            $audit->log(
                'membership.school.login.created',
                "School login created for {$school->name} ({$email})",
                $school,
                ['updated_by_user_id' => $request->user()?->id, 'user_id' => $user->id, 'email' => $email],
            );

            $message = $emailSent
                ? "Login created for {$school->name} ({$email}) and login details emailed."
                : "Login created for {$school->name} ({$email}) (Note: Verification/credentials email delivery failed — check mail settings).";

            return back()->with('success', $message);
        });
    }

    public function bulkCreateLogin(
        Request $request,
        MembershipNotifier $notifier,
        PlatformAuditLogger $audit,
    ) {
        $data = $request->validate([
            'school_ids'   => 'required|array|min:1|max:50',
            'school_ids.*' => 'uuid',
        ]);

        $schools = Tenant::query()
            ->where('parent_id', $this->sahodaya->id)
            ->where('type', 'school')
            ->whereIn('id', $data['school_ids'])
            ->get();

        $createdCount = 0;
        $skippedReasons = [];

        foreach ($schools as $school) {
            $didCreate = TenantAuth::withTenantUsers($school, function () use ($school, $notifier, $audit, $request, &$skippedReasons) {
                if ($this->schoolLoginUser($school) !== null) {
                    $skippedReasons[] = "{$school->name} (already has login)";
                    return false;
                }

                $payload = $school->application_payload ?? [];
                $email = strtolower(trim((string) ($payload['school_email'] ?? $payload['contact_email'] ?? '')));

                if ($email === '') {
                    $skippedReasons[] = "{$school->name} (no email address)";
                    return false;
                }

                if (User::where('email', $email)->exists()) {
                    $skippedReasons[] = "{$school->name} (email {$email} already in use)";
                    return false;
                }

                $plainPassword = \Illuminate\Support\Str::password(12);

                $user = User::create([
                    'tenant_id'            => $school->id,
                    'name'                 => $payload['school_name'] ?? $school->name,
                    'email'                => $email,
                    'email_verified_at'    => now(),
                    'password'             => \Illuminate\Support\Facades\Hash::make($plainPassword),
                    'plain_password'       => $plainPassword,
                    'must_change_password' => true,
                ]);
                $user->assignRole('school_admin');

                try {
                    SahodayaMailer::for($school->parent_id)->sendVerification($user);
                } catch (\Throwable $e) {
                    report($e);
                }

                try {
                    $notifier->schoolCredentialsIssued($user, $plainPassword, $school);
                } catch (\Throwable $e) {
                    report($e);
                }

                $audit->log(
                    'membership.school.login.created',
                    "School login created for {$school->name} ({$email})",
                    $school,
                    ['updated_by_user_id' => $request->user()?->id, 'user_id' => $user->id, 'bulk' => true],
                );

                return true;
            });

            if ($didCreate) {
                $createdCount++;
            }
        }

        if ($createdCount === 0) {
            return back()->with('error', 'No school logins could be created. ' . implode(', ', array_slice($skippedReasons, 0, 3)));
        }

        $message = "{$createdCount} school login(s) created and credentials emailed.";
        if (count($skippedReasons) > 0) {
            $message .= " " . count($skippedReasons) . " skipped.";
        }

        return back()->with('success', $message);
    }

    public function approve(string $tenantId, Tenant $school, MembershipNotifier $notifier, PlatformAuditLogger $audit)
    {
        abort_if($school->parent_id !== $this->sahodaya->id || $school->type !== 'school', 404);
        abort_unless($school->membership_status === 'pending', 422, 'School is not pending approval.');

        $year = AcademicYear::forSahodaya($this->sahodaya->id);
        $hasPayment = MembershipPayment::where('school_id', $school->id)
            ->where('academic_year', $year)
            ->whereIn('status', ['submitted', 'verified'])
            ->exists();

        abort_unless($hasPayment, 422, "Cannot approve {$school->name} because no membership payment has been submitted.");

        $this->approveSchool($school, $notifier, $audit, request()->user()?->id);

        return back()->with('success', "{$school->name} approved.");
    }

    public function bulkApprove(Request $request, MembershipNotifier $notifier, PlatformAuditLogger $audit)
    {
        $data = $request->validate([
            'school_ids'   => 'required|array|min:1|max:50',
            'school_ids.*' => 'uuid',
        ]);

        $schools = Tenant::query()
            ->where('parent_id', $this->sahodaya->id)
            ->where('type', 'school')
            ->where('membership_status', 'pending')
            ->whereIn('id', $data['school_ids'])
            ->get();

        $year = AcademicYear::forSahodaya($this->sahodaya->id);
        $approvedCount = 0;

        foreach ($schools as $school) {
            $hasPayment = MembershipPayment::where('school_id', $school->id)
                ->where('academic_year', $year)
                ->whereIn('status', ['submitted', 'verified'])
                ->exists();

            if ($hasPayment) {
                $this->approveSchool($school, $notifier, $audit, $request->user()?->id);
                $approvedCount++;
            }
        }

        if ($approvedCount === 0) {
            return back()->with('error', 'No schools could be approved because none of the selected schools have submitted membership payments.');
        }

        return back()->with('success', $approvedCount.' school(s) approved.');
    }

    public function bulkReject(Request $request, MembershipNotifier $notifier)
    {
        $data = $request->validate([
            'school_ids'   => 'required|array|min:1|max:50',
            'school_ids.*' => 'uuid',
            'reason'       => 'required|string|max:1000',
        ]);

        $schools = Tenant::query()
            ->where('parent_id', $this->sahodaya->id)
            ->where('type', 'school')
            ->where('membership_status', 'pending')
            ->whereIn('id', $data['school_ids'])
            ->get();

        foreach ($schools as $school) {
            $school->update([
                'membership_status'   => 'rejected',
                'is_active'           => false,
                'application_payload' => array_merge($school->application_payload ?? [], [
                    'rejection_reason' => $data['reason'],
                ]),
            ]);
            $notifier->schoolRejected($school, $data['reason']);
        }

        return back()->with('success', $schools->count().' application(s) rejected.');
    }

    private function approveSchool(Tenant $school, MembershipNotifier $notifier, PlatformAuditLogger $audit, ?int $reviewerId): void
    {
        $before = $school->membership_status;
        $school->update([
            'membership_status' => 'approved',
            'is_active'         => true,
        ]);

        app(DataChangeLogger::class)->updated(
            $school,
            "School membership approved: {$school->name}",
            ['membership_status' => ['old' => $before, 'new' => 'approved']],
            $school->id,
            'membership',
        );

        // The applicant's login is created here, on approval — not at application time
        // (see SchoolApplicationController::store()) — so credentials are never emailed
        // for a school nobody has reviewed yet. `school_email` is always present in
        // application_payload (stored unconditionally by store()), but this is guarded
        // in case an older, pre-fix application record predates that guarantee.
        $email = strtolower(trim((string) ($school->application_payload['school_email'] ?? '')));

        if ($email !== '' && ! User::where('email', $email)->exists()) {
            $plainPassword = \Illuminate\Support\Str::password(12);

            $user = User::create([
                'tenant_id' => $school->id,
                'name'      => $school->application_payload['school_name'] ?? $school->name,
                'email'     => $email,
                'password'  => \Illuminate\Support\Facades\Hash::make($plainPassword),
            ]);
            $user->assignRole('school_admin');

            try {
                \App\Services\Mail\SahodayaMailer::for($school->parent_id)->sendVerification($user);
            } catch (\Throwable $e) {
                report($e);
            }

            try {
                $notifier->schoolCredentialsIssued($user, $plainPassword, $school);
            } catch (\Throwable $e) {
                report($e);
            }
        }

        $notifier->schoolApproved($school);

        $audit->log(
            'membership.school.approved',
            "School approved: {$school->name}",
            $school,
            ['reviewer_id' => $reviewerId],
        );
    }

    public function toggleFestRegistration(string $tenantId, Tenant $school)
    {
        abort_if($school->parent_id !== $this->sahodaya->id || $school->type !== 'school', 404);

        $closed = ! (bool) $school->fest_registration_closed;
        $school->update(['fest_registration_closed' => $closed]);

        return back()->with('success', $closed
            ? 'Fest registration closed for this school.'
            : 'Fest registration reopened for this school.');
    }

    public function destroy(
        Request $request,
        string $tenantId,
        Tenant $school,
        SchoolDataPurger $purger,
        PlatformAuditLogger $audit,
    ) {
        abort_if($school->parent_id !== $this->sahodaya->id || $school->type !== 'school', 404);

        $data = $request->validate([
            'confirm_name' => 'required|string|max:255',
            'reason'       => 'required|string|max:1000',
        ]);

        abort_unless(trim($data['confirm_name']) === $school->name, 422, 'School name does not match.');

        $name = $school->name;
        $schoolId = $school->id;

        $purger->purge($school);

        $audit->log(
            'membership.school.deleted',
            "School permanently deleted: {$name}",
            null,
            [
                'school_id'   => $schoolId,
                'sahodaya_id' => $this->sahodaya->id,
                'reason'      => $data['reason'],
                'reviewer_id' => $request->user()?->id,
            ],
        );

        return redirect("/sahodaya-admin/{$this->sahodaya->id}/schools")
            ->with('success', "School \"{$name}\" permanently deleted.");
    }

    private function attachSchoolMetrics($schools): void
    {
        $pageIds = $schools->pluck('id');
        $classCounts = $this->classCountsFor($pageIds);
        $studentCounts = $this->studentCountsFor($pageIds);

        $schoolIdsWithUser = TenantAuth::withTenantUsers($this->sahodaya, function () use ($pageIds) {
            return User::whereIn('tenant_id', $pageIds)->pluck('tenant_id')->unique()->all();
        }) ?? [];

        $schools->transform(function (Tenant $school) use ($classCounts, $studentCounts, $schoolIdsWithUser) {
            $payload = $school->application_payload ?? [];
            $school->setAttribute('student_count', (int) ($studentCounts[$school->id] ?? 0));
            $school->setAttribute('classes_count', (int) ($classCounts[$school->id] ?? 0));
            $school->setAttribute('contact_email', $payload['school_email'] ?? $payload['contact_email'] ?? null);
            $school->setAttribute('contact_phone', $payload['phone'] ?? $payload['contact_phone'] ?? null);
            $school->setAttribute('affiliation', $payload['cbse_affiliation'] ?? $payload['affiliation_number'] ?? null);
            $school->setAttribute('fest_registration_closed', (bool) $school->fest_registration_closed);
            $school->setAttribute('has_login', in_array($school->id, $schoolIdsWithUser, true));

            return $school;
        });
    }

    private function classCountsFor($schoolIds)
    {
        if ($schoolIds->isEmpty()) {
            return collect();
        }

        return SchoolClass::query()
            ->whereIn('tenant_id', $schoolIds)
            ->where('is_active', true)
            ->selectRaw('tenant_id, count(*) as total')
            ->groupBy('tenant_id')
            ->pluck('total', 'tenant_id');
    }

    private function studentCountsFor($schoolIds)
    {
        if ($schoolIds->isEmpty()) {
            return collect();
        }

        return Student::query()
            ->whereIn('tenant_id', $schoolIds)
            ->where('status', 'active')
            ->selectRaw('tenant_id, count(*) as total')
            ->groupBy('tenant_id')
            ->pluck('total', 'tenant_id');
    }

    private function schoolLoginUser(Tenant $school): ?User
    {
        return TenantAuth::withTenantUsers($school, function () use ($school) {
            foreach (['school_admin', 'school_principal', 'school_vice_principal'] as $role) {
                $user = User::query()
                    ->where('tenant_id', $school->id)
                    ->whereHas('roles', fn ($q) => $q->where('name', $role))
                    ->orderBy('id')
                    ->first();

                if ($user) {
                    return $user;
                }
            }

            return User::query()->where('tenant_id', $school->id)->orderBy('id')->first();
        });
    }
}
