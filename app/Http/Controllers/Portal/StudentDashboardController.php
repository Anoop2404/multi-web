<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\FestEvent;
use App\Models\FestRegistration;
use App\Models\InAppNotification;
use App\Models\McqExam;
use App\Models\McqRegistration;
use App\Models\Tenant;
use App\Services\Portal\PortalProfileResolver;
use App\Support\TenantBranding;
use Illuminate\Http\Request;

class StudentDashboardController extends Controller
{
    public function index(Request $request, string $tenantId)
    {
        $student = $request->attributes->get('portalStudent');
        $school = Tenant::findOrFail($tenantId);

        $registrations = FestRegistration::where('school_id', $tenantId)
            ->whereHas('participants', fn ($q) => $q->where('student_id', $student->id))
            ->with(['event', 'item'])
            ->latest()
            ->limit(10)
            ->get();

        $mcqExams = McqRegistration::where('student_id', $student->id)
            ->with('exam')
            ->latest()
            ->limit(5)
            ->get();

        $notifications = InAppNotification::where('user_id', $request->user()->id)
            ->latest()
            ->limit(10)
            ->get();

        return inertia('Portal/Student/Dashboard', [
            'school'        => $school->only('id', 'name'),
            'student'       => $student->only('id', 'name', 'reg_no', 'email'),
            'logoUrl'       => TenantBranding::logoUrl($school),
            'registrations' => $registrations,
            'mcqExams'      => $mcqExams,
            'notifications' => $notifications,
        ]);
    }
}
