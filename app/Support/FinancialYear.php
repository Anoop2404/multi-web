<?php

namespace App\Support;

use App\Models\FinancialYear as FinancialYearRecord;

class FinancialYear
{
    /** Current financial year record id (April–March), falling back to academic year when unset. */
    public static function currentId(): ?int
    {
        $record = FinancialYearRecord::query()->where('is_current', true)->first();
        if ($record) {
            return $record->id;
        }

        $label = FinancialYearRecord::calendarCurrent();
        $record = FinancialYearRecord::query()->where('label', $label)->first();

        return $record?->id ?? AcademicYear::activeId();
    }

    public static function currentLabel(): string
    {
        return FinancialYearRecord::query()->where('is_current', true)->value('label')
            ?? FinancialYearRecord::calendarCurrent();
    }
}
