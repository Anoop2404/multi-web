<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class FinancialYear extends Model
{
    protected $fillable = ['label', 'start_date', 'end_date', 'is_current'];

    protected $casts = [
        'start_date' => 'date',
        'end_date'   => 'date',
        'is_current' => 'boolean',
    ];

    public function scopeCurrent(Builder $query): Builder
    {
        return $query->where('is_current', true);
    }

    /** Derive label from the April start year, e.g. 2025 → "2025-26" */
    public static function labelFromYear(int $aprilYear): string
    {
        return $aprilYear.'-'.substr((string) ($aprilYear + 1), -2);
    }

    /** Calendar-based current financial year label (April-March) */
    public static function calendarCurrent(): string
    {
        $year  = (int) date('Y');
        $month = (int) date('n');

        $startYear = $month >= 4 ? $year : $year - 1;

        return $startYear.'-'.substr((string) ($startYear + 1), -2);
    }
}
