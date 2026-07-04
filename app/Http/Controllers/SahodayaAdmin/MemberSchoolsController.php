<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Http\Controllers\SahodayaAdmin\Concerns\BuildsMembershipExports;
use App\Models\MembershipPayment;
use App\Models\Registration;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Membership\MembershipNotifier;
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

        return $this->inertia('Sahodaya/Schools/Index', [
            'schools'  => $schools,
            'filters'  => array_merge($filters, ['sort' => $sortColumn]),
            'verifiedCount' => Tenant::where('parent_id', $this->sahodaya->id)
                ->where('type', 'school')
                ->where('membership_status', 'approved')
                ->count(),
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

        $schools->getCollection()->transform(function (Tenant $school) {
            $payload = $school->application_payload ?? [];
            $school->setAttribute('contact_email', $payload['school_email'] ?? $payload['contact_email'] ?? null);
            $school->setAttribute('contact_phone', $payload['phone'] ?? $payload['contact_phone'] ?? null);
            $school->setAttribute('affiliation', $payload['cbse_affiliation'] ?? $payload['affiliation_number'] ?? null);

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

        return $this->inertia('Sahodaya/Schools/Show', [
            'school' => array_merge($school->only(
                'id', 'name', 'school_prefix', 'membership_status', 'is_active',
                'fest_registration_closed', 'subdomain', 'created_at', 'application_payload'
            ), [
                'student_count'  => Student::where('tenant_id', $school->id)->where('status', 'active')->count(),
                'classes_count'  => SchoolClass::where('tenant_id', $school->id)->where('is_active', true)->count(),
                'has_login'      => User::where('tenant_id', $school->id)->exists(),
                'login_email'    => User::where('tenant_id', $school->id)->value('email'),
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

    public function toggleFestRegistration(string $tenantId, Tenant $school)
    {
        abort_if($school->parent_id !== $this->sahodaya->id || $school->type !== 'school', 404);

        $closed = ! (bool) $school->fest_registration_closed;
        $school->update(['fest_registration_closed' => $closed]);

        return back()->with('success', $closed
            ? 'Fest registration closed for this school.'
            : 'Fest registration reopened for this school.');
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
