<?php

namespace App\Http\Controllers\Api\V1\Sahodaya;

use App\Models\MembershipPayment;
use App\Models\Registration;
use App\Models\Tenant;
use App\Support\TenancyDatabase;

class ReportsApiController extends SahodayaApiController
{
    public function summary()
    {
        $schoolIds = TenancyDatabase::schoolIdsFor($this->sahodaya->id);

        return $this->ok([
            'total_schools'      => Tenant::whereIn('id', $schoolIds)->where('membership_status', 'approved')->count(),
            'pending_schools'    => Tenant::whereIn('id', $schoolIds)->where('membership_status', 'pending')->count(),
            'total_registered'   => Registration::whereIn('school_id', $schoolIds)->where('registration_status', 'completed')->count(),
            'payments_verified'  => MembershipPayment::whereIn('school_id', $schoolIds)->where('status', 'verified')->count(),
            'payments_pending'   => MembershipPayment::whereIn('school_id', $schoolIds)->where('status', 'submitted')->count(),
            'total_collected'    => MembershipPayment::whereIn('school_id', $schoolIds)->where('status', 'verified')->sum('amount'),
        ]);
    }
}
