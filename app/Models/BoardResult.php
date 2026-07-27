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

    /**
     * Number of hours after submission during which the school can still edit
     * and resubmit a board result before it locks for Sahodaya review.
     */
    public const RESUBMIT_WINDOW_HOURS = 24;

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
        'total_marks',
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

    protected static function booted(): void
    {
        static::deleting(function (BoardResult $result) {
            $result->toppers()->each(fn (Topper $t) => $t->delete());
            $result->rankings()->delete();
            $result->uploads()->delete();
        });
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
        if (in_array($this->status, [self::STATUS_DRAFT, self::STATUS_REJECTED], true)) {
            return true;
        }

        // Recently-submitted results are editable within the resubmit window so schools
        // can fix mistakes before a Sahodaya reviewer picks them up. Once a reviewer has
        // started (reviewed_by_user_id is set), the result is locked until rejected.
        if ($this->status === self::STATUS_SUBMITTED
            && $this->submitted_at !== null
            && $this->reviewed_by_user_id === null
        ) {
            return $this->submitted_at->diffInHours(now()) < self::RESUBMIT_WINDOW_HOURS;
        }

        return false;
    }

    /**
     * Human-readable description of why the result is locked, for the frontend.
     */
    public function editLockReason(): ?string
    {
        if ($this->status === self::STATUS_PUBLISHED) {
            return 'This result has been published and cannot be modified.';
        }
        if ($this->status === self::STATUS_APPROVED) {
            return 'This result has been approved by Sahodaya and is locked.';
        }
        if ($this->status === self::STATUS_VERIFIED) {
            return 'This result is under Sahodaya verification and cannot be edited.';
        }
        if ($this->status === self::STATUS_SUBMITTED && $this->reviewed_by_user_id !== null) {
            return 'Sahodaya has started reviewing this result. Wait for rejection or approval.';
        }
        if ($this->status === self::STATUS_SUBMITTED && $this->submitted_at !== null) {
            $remaining = self::RESUBMIT_WINDOW_HOURS - $this->submitted_at->diffInHours(now());
            if ($remaining <= 0) {
                return 'The '.self::RESUBMIT_WINDOW_HOURS.'-hour editing window has closed. Contact Sahodaya admin to reopen.';
            }
        }

        return null;
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
