<?php

namespace App\Http\Controllers\Api\V1\Sahodaya;

use App\Http\Controllers\SahodayaAdmin\Concerns\BuildsMembershipExports;
use App\Http\Resources\MembershipPaymentResource;
use App\Http\Resources\SchoolResource;
use App\Models\MembershipPayment;
use App\Models\Registration;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Membership\MembershipNotifier;
use App\Support\AcademicYear;
use App\Support\SchoolDetailFields;
use Illuminate\Http\Request;

class SchoolsApiController extends SahodayaApiController
{
    use BuildsMembershipExports;

    public function index(Request $request)
    {
        $filters = $this->schoolListFilters($request);
        $status = $request->query('status');

        $query = $this->allSchoolsQuery($this->sahodaya->id, $filters);
        if (in_array($status, ['approved', 'pending', 'rejected'], true)) {
            $query->where('membership_status', $status);
        }

        $schools = $query->paginate($request->integer('per_page', 50));

        $this->attachSchoolMetrics($schools->getCollection());

        return SchoolResource::collection($schools);
    }

    public function show(string $tenantId, string $schoolId)
    {
        $school = Tenant::where('parent_id', $this->sahodaya->id)
            ->where('type', 'school')
            ->findOrFail($schoolId);

        $year = AcademicYear::forSahodaya($this->sahodaya->id);
        $payload = $school->application_payload ?? [];

        $registration = Registration::where('school_id', $school->id)
            ->where('academic_year', $year)
            ->with('submission')
            ->first();

        $payments = MembershipPayment::where('school_id', $school->id)
            ->orderByDesc('created_at')
            ->limit(10)
            ->get()
            ->each(fn ($payment) => $payment->setRelation('school', $school));

        return $this->ok([
            'school' => array_merge(SchoolResource::make($school)->resolve(), [
                'student_count' => Student::where('tenant_id', $school->id)->where('status', 'active')->count(),
                'classes_count' => SchoolClass::where('tenant_id', $school->id)->where('is_active', true)->count(),
                'has_login'     => User::where('tenant_id', $school->id)->exists(),
                'login_email'   => User::where('tenant_id', $school->id)->value('email'),
            ]),
            'detail_fields'   => SchoolDetailFields::fromPayload($payload),
            'registration'    => $registration,
            'recent_payments' => MembershipPaymentResource::collection($payments),
            'academic_year'   => $year,
        ]);
    }

    public function reject(Request $request, string $tenantId, string $schoolId, MembershipNotifier $notifier)
    {
        $school = Tenant::where('parent_id', $this->sahodaya->id)
            ->where('type', 'school')
            ->findOrFail($schoolId);

        $data = $request->validate(['reason' => 'required|string|max:1000']);

        $school->update([
            'membership_status'   => 'rejected',
            'is_active'           => false,
            'application_payload' => array_merge($school->application_payload ?? [], [
                'rejection_reason' => $data['reason'],
            ]),
        ]);
        $notifier->schoolRejected($school, $data['reason']);

        return $this->message('School membership rejected.');
    }

    private function attachSchoolMetrics($schools): void
    {
        $ids = $schools->pluck('id');
        $classCounts = SchoolClass::query()
            ->whereIn('tenant_id', $ids)
            ->where('is_active', true)
            ->selectRaw('tenant_id, count(*) as total')
            ->groupBy('tenant_id')
            ->pluck('total', 'tenant_id');
        $studentCounts = Student::query()
            ->whereIn('tenant_id', $ids)
            ->where('status', 'active')
            ->selectRaw('tenant_id, count(*) as total')
            ->groupBy('tenant_id')
            ->pluck('total', 'tenant_id');

        $schools->transform(function (Tenant $school) use ($classCounts, $studentCounts) {
            $school->student_count = (int) ($studentCounts[$school->id] ?? 0);
            $school->classes_count = (int) ($classCounts[$school->id] ?? 0);

            return $school;
        });
    }
}
