<?php

namespace App\Models;

use App\Models\Concerns\ScopesBySahodaya;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AcademicPerformanceScore extends Model
{
    use ScopesBySahodaya;
    protected $fillable = [
        'sahodaya_id',
        'tenant_id',
        'academic_year',
        'academic_year_id',
        'examination_type',
        'class',
        'board_result_id',
        'score',
        'components',
    ];

    protected $casts = [
        'score' => 'float',
        'components' => 'array',
        'class' => 'integer',
    ];

    public function boardResult(): BelongsTo
    {
        return $this->belongsTo(BoardResult::class);
    }

    public function academicYearRecord(): BelongsTo
    {
        return $this->belongsTo(AcademicYearRecord::class, 'academic_year_id');
    }
}
