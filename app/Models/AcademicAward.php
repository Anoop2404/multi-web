<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AcademicAward extends Model
{
    public const TYPE_BEST_ACADEMIC_SCHOOL = 'best_academic_school';

    public const TYPE_BEST_CLASS_X = 'best_class_x';

    public const TYPE_BEST_CLASS_XII = 'best_class_xii';

    public const TYPE_BEST_SCIENCE = 'best_science_school';

    public const TYPE_BEST_COMMERCE = 'best_commerce_school';

    public const TYPE_BEST_HUMANITIES = 'best_humanities_school';

    public const TYPE_MOST_SUBJECT_TOPPERS = 'most_subject_toppers';

    public const TYPE_EXCELLENCE = 'excellence';

    /** @return list<string> FRD §12 seven core categories (+ best XII helper). */
    public static function types(): array
    {
        return [
            self::TYPE_BEST_ACADEMIC_SCHOOL,
            self::TYPE_BEST_CLASS_X,
            self::TYPE_BEST_SCIENCE,
            self::TYPE_BEST_COMMERCE,
            self::TYPE_BEST_HUMANITIES,
            self::TYPE_MOST_SUBJECT_TOPPERS,
            self::TYPE_EXCELLENCE,
        ];
    }

    /** @return array<string, string> */
    public static function typeLabels(): array
    {
        return [
            self::TYPE_BEST_ACADEMIC_SCHOOL => 'Best Academic School',
            self::TYPE_BEST_CLASS_X => 'Best Class X School',
            self::TYPE_BEST_CLASS_XII => 'Best Class XII School',
            self::TYPE_BEST_SCIENCE => 'Best Science School',
            self::TYPE_BEST_COMMERCE => 'Best Commerce School',
            self::TYPE_BEST_HUMANITIES => 'Best Humanities School',
            self::TYPE_MOST_SUBJECT_TOPPERS => 'Most Subject Toppers',
            self::TYPE_EXCELLENCE => 'Academic Excellence Award',
        ];
    }

    protected $fillable = [
        'sahodaya_id',
        'tenant_id',
        'academic_year',
        'academic_year_id',
        'award_type',
        'title',
        'score',
        'board_result_id',
        'meta',
        'computed_at',
    ];

    protected $casts = [
        'meta' => 'array',
        'score' => 'float',
        'computed_at' => 'datetime',
    ];

    public function boardResult(): BelongsTo
    {
        return $this->belongsTo(BoardResult::class);
    }

    public function achievements(): HasMany
    {
        return $this->hasMany(Achievement::class, 'source_award_id');
    }
}
