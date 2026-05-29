<?php

namespace App\Http\Controllers\SchoolAdmin;

use App\Models\AdmissionEnquiry;
use App\Models\NewsArticle;
use App\Models\TcRequest;

class DashboardController extends SchoolAdminController
{
    public function index()
    {
        $tid = $this->school->id;

        return $this->inertia('School/Dashboard', [
            'stats' => [
                ['label' => 'News Articles',       'value' => NewsArticle::where('tenant_id', $tid)->count()],
                ['label' => 'New Enquiries',       'value' => AdmissionEnquiry::where('tenant_id', $tid)->where('status', 'new')->count()],
                ['label' => 'Pending TC Requests', 'value' => TcRequest::where('tenant_id', $tid)->where('status', 'pending')->count()],
            ],
        ]);
    }
}
