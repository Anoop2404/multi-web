<?php

namespace App\Http\Controllers\Api\V1\Sahodaya;

use App\Http\Controllers\SahodayaAdmin\Concerns\BuildsMembershipExports;
use App\Models\MembershipPayment;
use App\Models\Registration;
use App\Models\Student;
use App\Models\Tenant;
use App\Support\AcademicYear;
use App\Support\TenancyDatabase;

class DashboardApiController extends SahodayaApiController
{
    use BuildsMembershipExports;

    public function index()
    {
        $schoolIds = TenancyDatabase::schoolIdsFor($this->sahodaya->id);

        $approvedSchoolIds = Tenant::query()
            ->where('parent_id', $this->sahodaya->id)
            ->where('type', 'school')
            ->where('membership_status', 'approved')
            ->pluck('id')
            ->all();

        $year = AcademicYear::forSahodaya($this->sahodaya->id);
        $fees = $this->paymentFeeSummary($schoolIds, $year);

        return $this->ok([
            'stats' => [
                'approved_schools'   => count($approvedSchoolIds),
                'pending_schools'    => Tenant::query()
                    ->where('parent_id', $this->sahodaya->id)
                    ->where('type', 'school')
                    ->where('membership_status', 'pending')
                    ->count(),
                'registered_schools' => count($schoolIds),
                'total_students'     => $approvedSchoolIds === []
                    ? 0
                    : Student::query()
                        ->whereIn('tenant_id', $approvedSchoolIds)
                        ->where('status', 'active')
                        ->count(),
                'pending_payments'   => MembershipPayment::query()
                    ->whereIn('school_id', $schoolIds)
                    ->where('status', 'submitted')
                    ->count(),
                'payment_due'        => Registration::query()
                    ->whereIn('school_id', $schoolIds)
                    ->where('academic_year', $year)
                    ->whereIn('registration_status', ['payment_pending', 'payment_rejected'])
                    ->count(),
                'pending_amount'     => $fees['pending_amount'],
                'approved_amount'    => $fees['approved_amount'],
                'payment_due_amount' => $fees['payment_due_amount'],
            ],
        ]);
    }
}
