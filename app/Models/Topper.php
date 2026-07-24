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
        'is_perfect_scorer',
        'stream',
        'stream_id',
        'rank',
    ];

    protected $casts = [
        'is_perfect_scorer' => 'boolean',
        'percentage' => 'float',
    ];

    protected $appends = [
        'subject_marks',
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

    /**
     * Virtual subject_marks map sourced from topper_subject_marks (#138).
     *
     * @return array<string, int>
     */
    public function getSubjectMarksAttribute(): array
    {
        $rows = $this->relationLoaded('subjectMarks')
            ? $this->getRelation('subjectMarks')
            : $this->subjectMarks()->get(['subject_label', 'marks']);

        return $rows
            ->mapWithKeys(fn (TopperSubjectMark $row) => [
                $row->subject_label => (int) round((float) $row->marks),
            ])
            ->all();
    }
}
