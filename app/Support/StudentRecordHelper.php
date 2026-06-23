<?php

namespace App\Support;

use App\Models\AcademicYearRecord;
use App\Models\Tenant;

class StudentRecordHelper
{
    public static function activeAcademicYearIdForSchool(Tenant $school): ?int
    {
        if (! $school->parent_id) {
            return null;
        }

        return AcademicYearRecord::where('tenant_id', $school->parent_id)
            ->where('is_active', true)
            ->value('id');
    }
}
