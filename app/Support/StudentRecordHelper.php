<?php

namespace App\Support;

use App\Models\Tenant;
use App\Support\AcademicYear;

class StudentRecordHelper
{
    public static function activeAcademicYearIdForSchool(Tenant $school): ?int
    {
        if (! $school->parent_id) {
            return null;
        }

        return AcademicYear::activeId();
    }
}
