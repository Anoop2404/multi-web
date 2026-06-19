<?php

namespace App\Services\Students;

use App\Models\SahodayaProfile;
use App\Models\Student;
use App\Models\Tenant;
use App\Support\AcademicYear;
use Illuminate\Support\Facades\DB;

class StudentRegistrationNumberGenerator
{
    /** Sahodaya student registration number, e.g. MLCS/AMU/27/0001 */
    public function generate(Tenant $school, ?string $academicYear = null): string
    {
        $sahodaya = $school->parent;
        $profile = SahodayaProfile::where('tenant_id', $sahodaya->id)->firstOrFail();

        if (! $profile->prefix) {
            throw new \RuntimeException('Sahodaya prefix is not set.');
        }

        if (! $school->school_prefix) {
            throw new \RuntimeException('School prefix is not set.');
        }

        $academicYear ??= AcademicYear::forSchool($school);

        return DB::transaction(function () use ($school, $profile, $academicYear) {
            SahodayaProfile::where('tenant_id', $school->parent_id)->lockForUpdate()->firstOrFail();

            $yearSuffix = AcademicYear::yearSuffix($academicYear);
            $numberPrefix = sprintf(
                '%s/%s/%s/',
                strtoupper($profile->prefix),
                strtoupper($school->school_prefix),
                $yearSuffix,
            );

            $sequence = Student::where('tenant_id', $school->id)
                ->where('admission_number', 'like', $numberPrefix.'%')
                ->count() + 1;

            $regNo = sprintf('%s%04d', $numberPrefix, $sequence);

            if (! $profile->prefixes_locked) {
                $profile->update(['prefixes_locked' => true]);
            }
            if (! $school->prefixes_locked) {
                $school->update(['prefixes_locked' => true]);
            }

            return $regNo;
        });
    }
}
