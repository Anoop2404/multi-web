<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCentralTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BoardResult extends Model
{
    use BelongsToCentralTenant;

    public const EXAM_AISSE = 'AISSE';

    public const EXAM_AISSCE = 'AISSCE';

    public const STATUS_DRAFT = 'draft';

    public const STATUS_SUBMITTED = 'submitted';

    public const STATUS_VERIFIED = 'verified';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_PUBLISHED = 'published';

    protected $fillable = [
        'tenant_id',
        'class',
        'examination_type',
        'academic_year',
        'academic_year_id',
        'total_appeared',
        'pass_count',
        'pass_percent',
        'distinctions',
        'first_class',
        'highest_mark',
        'average_mark',
        'remarks',
        'subject_stats',
        'result_pdf_path',
        'result_pdf_disk',
        'attachment_paths',
        'status',
        'submitted_by',
        'submitted_at',
        'submission_count',
        'verified_by',
        'verified_at',
        'approved_by',
        'approved_at',
        'published_at',
        'rejection_reason',
        'correction_history',
        'reviewed_by_user_id',
        'reviewed_at',
    ];

    protected $casts = [
        'subject_stats' => 'array',
        'attachment_paths' => 'array',
        'correction_history' => 'array',
        'pass_percent' => 'float',
        'highest_mark' => 'float',
        'average_mark' => 'float',
        'submitted_at' => 'datetime',
        'verified_at' => 'datetime',
        'approved_at' => 'datetime',
        'published_at' => 'datetime',
        'reviewed_at' => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsToCentralTenant();
    }

    public function toppers(): HasMany
    {
        return $this->hasMany(Topper::class)->orderBy('rank');
    }

    public function uploads(): HasMany
    {
        return $this->hasMany(BoardResultUpload::class)->orderByDesc('version');
    }

    public function rankings(): HasMany
    {
        return $this->hasMany(BoardResultRanking::class);
    }

    public function academicYearRecord(): BelongsTo
    {
        return $this->belongsTo(AcademicYearRecord::class, 'academic_year_id');
    }

    public function scopeForClass(Builder $q, int $class): Builder
    {
        return $q->where('class', $class);
    }

    public function scopeLatestFirst(Builder $q): Builder
    {
        return $q->orderByDesc('academic_year');
    }

    public function scopePublished(Builder $q): Builder
    {
        return $q->where('status', self::STATUS_PUBLISHED);
    }

    public function scopePendingVerification(Builder $q): Builder
    {
        return $q->whereIn('status', [self::STATUS_SUBMITTED, self::STATUS_VERIFIED]);
    }

    public function isEditable(): bool
    {
        return in_array($this->status, [self::STATUS_DRAFT, self::STATUS_REJECTED], true);
    }

    public function hasResultPdf(): bool
    {
        return filled($this->result_pdf_path);
    }

    public static function examinationTypeForClass(int $class): string
    {
        return (int) $class === 12 ? self::EXAM_AISSCE : self::EXAM_AISSE;
    }

    public static function examinationTypes(): array
    {
        return [self::EXAM_AISSE, self::EXAM_AISSCE];
    }
}
