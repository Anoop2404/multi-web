<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCentralTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Topper extends Model
{
    use BelongsToCentralTenant;

    public const ENTRY_OVERALL = 'overall';

    public const ENTRY_SUBJECT = 'subject';

    /** A student entered on the Full A1 Achievers page — every subject_marks row must be >= 91. */
    public const ENTRY_FULL_A1 = 'full_a1';

    protected $fillable = [
        'board_result_id',
        'tenant_id',
        'entry_type',
        'name',
        'admission_no',
        'roll_no',
        'gender',
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

    public function isSubjectOnly(): bool
    {
        return $this->entry_type === self::ENTRY_SUBJECT;
    }

    public function isFullA1Entry(): bool
    {
        return $this->entry_type === self::ENTRY_FULL_A1;
    }

    public function scopeOverallEntries($query)
    {
        return $query->where('entry_type', self::ENTRY_OVERALL);
    }

    public function scopeSubjectEntries($query)
    {
        return $query->where('entry_type', self::ENTRY_SUBJECT);
    }

    public function scopeFullA1Entries($query)
    {
        return $query->where('entry_type', self::ENTRY_FULL_A1);
    }

    protected static function booted(): void
    {
        static::saving(function (Topper $topper) {
            if (
                $topper->entry_type !== self::ENTRY_SUBJECT
                && $topper->marks_obtained !== null
                && (float) $topper->total_marks > 0
            ) {
                $topper->percentage = round(
                    ((float) $topper->marks_obtained / (float) $topper->total_marks) * 100,
                    2,
                );
            }
        });
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

    /**
     * The eager-loaded `subjectMarks` relation serializes to the same `subject_marks`
     * JSON key as the getSubjectMarksAttribute() accessor above (Eloquent snake-cases
     * the relation method name). Array::merge in Model::toArray() lets whichever comes
     * from relationsToArray() win, which was silently replacing the clean
     * {subject: marks} map with the raw list of TopperSubjectMark rows on every
     * page that eager-loads toppers.subjectMarks. Drop it here so only the accessor's
     * map is ever serialized; the relation stays loaded/usable internally either way.
     *
     * @return array<string, mixed>
     */
    public function relationsToArray()
    {
        $relations = parent::relationsToArray();
        unset($relations['subject_marks']);

        return $relations;
    }
}
