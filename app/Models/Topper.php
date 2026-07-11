<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCentralTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Topper extends Model
{
    use BelongsToCentralTenant;

    protected $fillable = [
        'board_result_id',
        'tenant_id',
        'name',
        'admission_no',
        'roll_no',
        'photo',
        'percentage',
        'total_marks',
        'marks_obtained',
        'subject_marks',
        'is_perfect_scorer',
        'stream',
        'stream_id',
        'rank',
    ];

    protected $casts = [
        'subject_marks' => 'array',
        'is_perfect_scorer' => 'boolean',
        'percentage' => 'float',
    ];

    public function boardResult(): BelongsTo
    {
        return $this->belongsTo(BoardResult::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsToCentralTenant();
    }

    public function examStream(): BelongsTo
    {
        return $this->belongsTo(ExamStream::class, 'stream_id');
    }

    public function subjectMarks(): HasMany
    {
        return $this->hasMany(TopperSubjectMark::class);
    }
}
