<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Models\SahodayaProfile;
use App\Models\SahodayaRegistrationWindow;
use App\Support\AcademicYear;
use Illuminate\Http\Request;

class StudentRegistrationWindowController extends SahodayaAdminController
{
    public function index()
    {
        $academicYear = AcademicYear::forSahodaya($this->sahodaya->id);
        $window = SahodayaRegistrationWindow::where('sahodaya_id', $this->sahodaya->id)
            ->where('academic_year', $academicYear)
            ->first();

        return $this->inertia('Sahodaya/Students/RegistrationWindows', [
            'academicYear' => $academicYear,
            'window'       => $window ? [
                'add_open_local'  => $window->add_open?->format('Y-m-d\TH:i'),
                'add_close_local' => $window->add_close?->format('Y-m-d\TH:i'),
                'edit_open_local' => $window->edit_open?->format('Y-m-d\TH:i'),
                'edit_close_local'=> $window->edit_close?->format('Y-m-d\TH:i'),
            ] : null,
            'emergencyLock' => (bool) SahodayaProfile::where('tenant_id', $this->sahodaya->id)->value('student_edit_lock_enabled'),
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'academic_year' => 'required|string|max:10',
            'add_open'      => 'nullable|date',
            'add_close'     => 'nullable|date|after_or_equal:add_open',
            'edit_open'     => 'nullable|date',
            'edit_close'    => 'nullable|date|after_or_equal:edit_open',
        ]);

        $existing = SahodayaRegistrationWindow::where('sahodaya_id', $this->sahodaya->id)
            ->where('academic_year', $data['academic_year'])
            ->first();

        SahodayaRegistrationWindow::updateOrCreate(
            ['sahodaya_id' => $this->sahodaya->id, 'academic_year' => $data['academic_year']],
            [
                'academic_year_id'       => AcademicYear::recordIdForLabel($data['academic_year']),
                'add_open'               => $data['add_open'],
                'add_close'              => $data['add_close'],
                'edit_open'              => $data['edit_open'],
                'edit_close'             => $data['edit_close'],
                'registration_starts_at' => $existing?->registration_starts_at,
                'registration_ends_at'   => $existing?->registration_ends_at,
            ],
        );

        return back()->with('success', 'Student registration windows saved.');
    }
}
