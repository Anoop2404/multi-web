<?php

namespace App\Support;

use App\Models\SahodayaProfile;
use App\Models\Tenant;

class AcademicYear
{
    /** Calendar-based academic year (Apr–Mar), ignoring Sahodaya override. */
    public static function calendarCurrent(): string
    {
        $year = (int) date('Y');
        $month = (int) date('n');

        if ($month >= 4) {
            return $year.'-'.substr((string) ($year + 1), -2);
        }

        return ($year - 1).'-'.substr((string) $year, -2);
    }

    public static function current(?string $sahodayaId = null): string
    {
        return $sahodayaId ? self::forSahodaya($sahodayaId) : self::calendarCurrent();
    }

    public static function forSahodaya(?string $sahodayaId): string
    {
        if ($sahodayaId) {
            $configured = SahodayaProfile::where('tenant_id', $sahodayaId)->value('active_academic_year');
            if ($configured) {
                return $configured;
            }
        }

        return self::calendarCurrent();
    }

    public static function forSchool(Tenant $school): string
    {
        return self::forSahodaya($school->parent_id);
    }

    /** @return list<string> */
    public static function options(int $past = 2, int $future = 2): array
    {
        $startYear = (int) explode('-', self::calendarCurrent())[0];
        $options = [];

        for ($y = $startYear - $past; $y <= $startYear + $future; $y++) {
            $options[] = $y.'-'.substr((string) ($y + 1), -2);
        }

        return $options;
    }

    public static function yearSuffix(?string $academicYear = null): string
    {
        $ay = $academicYear ?? self::calendarCurrent();

        return substr(explode('-', $ay)[1] ?? substr($ay, -2), -2);
    }
}
