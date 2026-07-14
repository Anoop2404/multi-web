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
use App\Services\Membership\MembershipNotifier;
use App\Services\Membership\SchoolMembershipCancellationService;
use App\Services\Tenancy\SchoolDataPurger;
use App\Support\AcademicYear;
use App\Support\ExcelExport;
use App\Support\SchoolDetailFields;
use Illuminate\Http\Request;
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

        $this->attachSchoolMetrics($schools->getCollection());

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
                'total_classes' => $approvedIds->isEmpty()
                    ? 0
                    : SchoolClass::whereIn('tenant_id', $approvedIds)->where('is_active', true)->count(),
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
                        ->orWhere('school_prefix', 'like', "%{$search}%");
                });
            })
            ->when($filters['date_from'], fn ($q) => $q->whereDate('created_at', '>=', $filters['date_from']))
            ->when($filters['date_to'], fn ($q) => $q->whereDate('created_at', '<=', $filters['date_to']))
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        $year = AcademicYear::forSahodaya($this->sahodaya->id);

        $schools->getCollection()->transform(function (Tenant $school) use ($year) {
            $payload = $school->application_payload ?? [];
            $school->setAttribute('contact_email', $payload['school_email'] ?? $payload['contact_email'] ?? null);
            $school->setAttribute('contact_phone', $payload['phone'] ?? $payload['contact_phone'] ?? null);
            $school->setAttribute('affiliation', $payload['cbse_affiliation'] ?? $payload['affiliation_number'] ?? null);

            // Fetch the latest submitted/verified membership payment for this academic year
            $payment = MembershipPayment::where('school_id', $school->id)
                ->where('academic_year', $year)
                ->whereIn('status', ['submitted', 'verified'])
                ->latest()
                ->first();

            $school->setAttribute('has_payment', $payment !== null);
            $school->setAttribute('payment_status', $payment?->status);
            $school->setAttribute('payment_amount', $payment?->amount);
            $school->setAttribute('payment_proof_url', $payment?->proof_url);

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

        return $this->inertia('Sahodaya/Schools/Show', [
            'school' => array_merge($school->only(
                'id', 'name', 'school_prefix', 'membership_status', 'is_non_affiliated', 'is_active',
                'fest_registration_closed', 'subdomain', 'created_at', 'application_payload'
            ), [
                'student_count'  => Student::where('tenant_id', $school->id)->where('status', 'active')->count(),
                'classes_count'  => SchoolClass::where('tenant_id', $school->id)->where('is_active', true)->count(),
                'has_login'      => User::where('tenant_id', $school->id)->exists(),
                'login_email'    => User::where('tenant_id', $school->id)->value('email'),
                'can_cancel_membership' => $cancellation->canCancel($school),
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
        ]);

        $cancellation->cancel($school, $data['reason'], $notifier, $audit, $request->user()?->id);

        return back()->with('success', "Membership cancelled for {$school->name}.");
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

        $schools->transform(function (Tenant $school) use ($classCounts, $studentCounts) {
            $payload = $school->application_payload ?? [];
            $school->setAttribute('student_count', (int) ($studentCounts[$school->id] ?? 0));
            $school->setAttribute('classes_count', (int) ($classCounts[$school->id] ?? 0));
            $school->setAttribute('contact_email', $payload['school_email'] ?? $payload['contact_email'] ?? null);
            $school->setAttribute('contact_phone', $payload['phone'] ?? $payload['contact_phone'] ?? null);
            $school->setAttribute('affiliation', $payload['cbse_affiliation'] ?? $payload['affiliation_number'] ?? null);
            $school->setAttribute('fest_registration_closed', (bool) $school->fest_registration_closed);

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
}
