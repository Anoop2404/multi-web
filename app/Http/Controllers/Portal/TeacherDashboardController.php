<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\InAppNotification;
use App\Models\Tenant;
use App\Models\TrainingRegistration;
use Illuminate\Http\Request;

class TeacherDashboardController extends Controller
{
    public function index(Request $request, string $tenantId)
    {
        $teacher = $request->attributes->get('portalTeacher');
        $school = Tenant::findOrFail($tenantId);

        $training = TrainingRegistration::where('teacher_id', $teacher->id)
            ->with('program')
            ->latest()
            ->limit(5)
            ->get();

        $notifications = InAppNotification::where('user_id', $request->user()->id)
            ->latest()
            ->limit(10)
            ->get();

        return inertia('Portal/Teacher/Dashboard', [
            'school'        => $school->only('id', 'name'),
            'teacher'       => $teacher->only('id', 'name', 'reg_no', 'email', 'designation'),
            'training'      => $training,
            'notifications' => $notifications,
        ]);
    }
}
