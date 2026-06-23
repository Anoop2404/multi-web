<?php

namespace App\Support;

use App\Models\AcademicYearRecord;
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
        $fromRecord = self::activeRecordLabel();
        if ($fromRecord) {
            return $fromRecord;
        }

        if ($sahodayaId) {
            $configured = SahodayaProfile::where('tenant_id', $sahodayaId)->value('active_academic_year');
            if ($configured) {
                return $configured;
            }
        }

        return self::calendarCurrent();
    }

    public static function activeRecord(): ?AcademicYearRecord
    {
        if (! self::academicYearsTableExists()) {
            return null;
        }

        return AcademicYearRecord::where('status', 'active')->first();
    }

    public static function activeRecordLabel(): ?string
    {
        return self::activeRecord()?->label;
    }

    public static function recordIdForLabel(?string $label): ?int
    {
        if (! $label || ! self::academicYearsTableExists()) {
            return null;
        }

        return AcademicYearRecord::where('label', $label)->value('id');
    }

    /** @return list<string> */
    public static function recordOptions(): array
    {
        if (! self::academicYearsTableExists()) {
            return [];
        }

        return AcademicYearRecord::orderByDesc('start_date')->pluck('label')->all();
    }

    private static function academicYearsTableExists(): bool
    {
        try {
            return \Illuminate\Support\Facades\Schema::hasTable('academic_years');
        } catch (\Throwable) {
            return false;
        }
    }

    public static function forSchool(Tenant $school): string
    {
        return self::forSahodaya($school->parent_id);
    }

    /** @return list<string> */
    public static function options(int $past = 2, int $future = 2): array
    {
        $fromRecords = self::recordOptions();
        if ($fromRecords !== []) {
            return $fromRecords;
        }

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
