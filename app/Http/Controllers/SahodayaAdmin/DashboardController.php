<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Models\Circular;
use App\Models\KalotsavEvent;
use App\Models\OfficeBearers;
use App\Models\SchoolYearStudentCount;
use App\Models\Tenant;
use App\Support\TenancyDatabase;

class DashboardController extends SahodayaAdminController
{
    public function index()
    {
        $schoolIds = TenancyDatabase::schoolIdsFor($this->sahodaya->id);

        $stats = [
            'member_schools'  => count($schoolIds),
            'total_students'  => SchoolYearStudentCount::whereHas('submission', fn ($q) => $q->whereIn('school_id', $schoolIds))->sum('total_count'),
            'office_bearers'  => OfficeBearers::where('tenant_id', $this->sahodaya->id)->count(),
            'circulars'       => Circular::where('tenant_id', $this->sahodaya->id)->count(),
            'kalotsav_events' => KalotsavEvent::where('tenant_id', $this->sahodaya->id)->count(),
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
