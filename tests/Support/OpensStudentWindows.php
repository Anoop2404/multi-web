<?php

namespace Tests\Support;

use App\Models\SahodayaRegistrationWindow;
use App\Models\Tenant;
use App\Support\AcademicYear;

trait OpensStudentWindows
{
    protected function openStudentWindows(Tenant $sahodaya): void
    {
        SahodayaRegistrationWindow::create([
            'sahodaya_id'   => $sahodaya->id,
            'academic_year' => AcademicYear::forSahodaya($sahodaya->id),
            'add_open'      => now()->subDay(),
            'add_close'     => now()->addMonth(),
            'edit_open'     => now()->subDay(),
            'edit_close'    => now()->addMonth(),
        ]);
    }
}
