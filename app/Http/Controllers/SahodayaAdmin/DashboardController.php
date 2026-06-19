<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Http\Controllers\SahodayaAdmin\Concerns\BuildsMembershipExports;
use App\Models\Circular;
use App\Models\KalotsavEvent;
use App\Models\MembershipPayment;
use App\Models\OfficeBearers;
use App\Models\Student;
use App\Models\Tenant;
use App\Support\AcademicYear;
use App\Support\TenancyDatabase;

class DashboardController extends SahodayaAdminController
{
    use BuildsMembershipExports;

    public function index()
    {
        $schoolIds = TenancyDatabase::schoolIdsFor($this->sahodaya->id);
        $year = AcademicYear::forSahodaya($this->sahodaya->id);
        $fees = $this->paymentFeeSummary($this->sahodaya->id, $schoolIds, $year);

        $approvedSchoolIds = Tenant::query()
            ->where('parent_id', $this->sahodaya->id)
            ->where('type', 'school')
            ->where('membership_status', 'approved')
            ->pluck('id')
            ->all();

        $stats = [
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
            'payment_due'        => $this->unpaidRegistrationsCount($this->sahodaya->id, $schoolIds, $year),
            'pending_amount'   => $fees['pending_amount'],
            'approved_amount'  => $fees['approved_amount'],
            'payment_due_amount' => $fees['payment_due_amount'],
            'office_bearers'     => OfficeBearers::where('tenant_id', $this->sahodaya->id)->count(),
            'circulars'          => Circular::where('tenant_id', $this->sahodaya->id)->count(),
            'kalotsav_events'    => KalotsavEvent::where('tenant_id', $this->sahodaya->id)->count(),
        ];

        $recentCirculars = Circular::where('tenant_id', $this->sahodaya->id)
            ->orderByDesc('issued_date')
            ->limit(5)
            ->get(['id', 'title', 'category', 'issued_date']);

        $activeKalotsav = KalotsavEvent::where('tenant_id', $this->sahodaya->id)
            ->where('is_active', true)
            ->orderByDesc('event_date')
            ->first();

        return $this->inertia('Sahodaya/Dashboard', compact('stats', 'recentCirculars', 'activeKalotsav'));
    }
}
