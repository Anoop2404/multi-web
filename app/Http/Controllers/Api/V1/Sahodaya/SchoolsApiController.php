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
use App\Services\Membership\MembershipNotifier;
use App\Support\AcademicYear;
use Illuminate\Http\Request;

class SchoolsApiController extends SahodayaApiController
{
    use BuildsMembershipExports;

    public function index(Request $request)
    {
        $filters = $this->schoolListFilters($request);

        $schools = $this->verifiedSchoolsQuery($this->sahodaya->id, $filters)
            ->paginate($request->integer('per_page', 20));

        $this->attachSchoolMetrics($schools->getCollection());

        return SchoolResource::collection($schools);
    }

    public function show(string $tenantId, string $schoolId)
    {
        $school = Tenant::where('parent_id', $this->sahodaya->id)
            ->where('type', 'school')
            ->findOrFail($schoolId);

        $year = AcademicYear::forSahodaya($this->sahodaya->id);
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
                'application_payload' => $school->application_payload,
            ]),
            'registration' => $registration,
            'recent_payments' => MembershipPaymentResource::collection($payments),
            'academic_year' => $year,
        ]);
    }

    public function reject(Request $request, string $tenantId, string $schoolId, MembershipNotifier $notifier)
    {
        $school = Tenant::where('parent_id', $this->sahodaya->id)
            ->where('type', 'school')
            ->findOrFail($schoolId);

        $data = $request->validate(['reason' => 'required|string|max:1000']);

        $school->update(['membership_status' => 'rejected', 'is_active' => false]);
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
