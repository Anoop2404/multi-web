<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Proper academic year record in tenant DB.
 * The legacy App\Support\AcademicYear helper (string-based) continues to work alongside this.
 */
class AcademicYearRecord extends Model
{
    protected $table = 'academic_years';

    protected $fillable = [
        'label', 'start_date', 'end_date', 'status',
        'opened_by', 'opened_at', 'closed_by', 'closed_at',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date'   => 'date',
        'opened_at'  => 'datetime',
        'closed_at'  => 'datetime',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->where('status', 'upcoming');
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isClosed(): bool
    {
        return $this->status === 'closed';
    }

    public function feeSlabs(): HasMany
    {
        return $this->hasMany(MembershipFeeSlab::class, 'academic_year_id');
    }

    public function registrationWindows(): HasMany
    {
        return $this->hasMany(SahodayaRegistrationWindow::class, 'academic_year_id');
    }

    /** Derive label from start year, e.g. 2025 → "2025-26" */
    public static function labelFromYear(int $startYear): string
    {
        return $startYear.'-'.substr((string) ($startYear + 1), -2);
    }

    /** Default start date: June 1 of the start year */
    public static function defaultStartDate(int $startYear): string
    {
        return $startYear.'-06-01';
    }

    /** Default end date: May 31 of the following year */
    public static function defaultEndDate(int $startYear): string
    {
        return ($startYear + 1).'-05-31';
    }
}
