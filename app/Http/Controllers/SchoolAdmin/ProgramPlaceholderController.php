<?php

namespace App\Http\Controllers\SchoolAdmin;

use App\Http\Controllers\SahodayaAdmin\ProgramPlaceholderController as SahodayaPrograms;
use Illuminate\Http\Request;

class ProgramPlaceholderController extends SchoolAdminController
{
    public function show(Request $request, string $tenantId, string $program, string $view)
    {
        abort_unless(isset(SahodayaPrograms::PROGRAMS[$program]), 404);
        abort_unless(in_array($view, ['registration', 'results'], true), 404);

        return $this->inertia('School/Programs/Placeholder', [
            'program' => $program,
            'view'    => $view,
            'meta'    => SahodayaPrograms::PROGRAMS[$program],
            'schoolPortal' => true,
        ]);
    }
}
