<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCentralTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BoardResultCertificationPackage extends Model
{
    use BelongsToCentralTenant;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_AWAITING_LEADERSHIP_REVIEW = 'awaiting_leadership_review';

    public const STATUS_LEADERSHIP_CHANGES_REQUESTED = 'leadership_changes_requested';

    public const STATUS_AWAITING_REPORT_SIGNATURES = 'awaiting_report_signatures';

    public const STATUS_INDIVIDUAL_REPORTS_SIGNED = 'individual_reports_signed';

    public const STATUS_AWAITING_CONSOLIDATED_SIGNATURE = 'awaiting_consolidated_signature';

    public const STATUS_SCHOOL_CERTIFIED = 'school_certified';

    public const STATUS_SUBMITTED_TO_SAHODAYA = 'submitted_to_sahodaya';

    public const STATUS_SAHODAYA_RETURNED = 'sahodaya_returned';

    public const STATUS_SAHODAYA_VERIFIED = 'sahodaya_verified';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_PUBLISHED = 'published';

    public const STATUS_SUPERSEDED = 'superseded';

    /**
     * Forward state-machine edges. Anything not listed here is an invalid
     * transition and BoardResultCertificationService::transition() will throw.
     * Superseding (creating a new version) is handled separately and can
     * happen from any non-terminal status.
     *
     * @var array<string, list<string>>
     */
    public const TRANSITIONS = [
        self::STATUS_DRAFT => [self::STATUS_AWAITING_LEADERSHIP_REVIEW],
        self::STATUS_AWAITING_LEADERSHIP_REVIEW => [
            self::STATUS_AWAITING_REPORT_SIGNATURES,
            self::STATUS_LEADERSHIP_CHANGES_REQUESTED,
        ],
        self::STATUS_LEADERSHIP_CHANGES_REQUESTED => [self::STATUS_DRAFT],
        self::STATUS_AWAITING_REPORT_SIGNATURES => [self::STATUS_INDIVIDUAL_REPORTS_SIGNED],
        self::STATUS_INDIVIDUAL_REPORTS_SIGNED => [self::STATUS_AWAITING_CONSOLIDATED_SIGNATURE],
        self::STATUS_AWAITING_CONSOLIDATED_SIGNATURE => [self::STATUS_SCHOOL_CERTIFIED],
        self::STATUS_SCHOOL_CERTIFIED => [self::STATUS_SUBMITTED_TO_SAHODAYA],
        self::STATUS_SUBMITTED_TO_SAHODAYA => [self::STATUS_SAHODAYA_RETURNED, self::STATUS_SAHODAYA_VERIFIED],
        self::STATUS_SAHODAYA_RETURNED => [],
        // Sahodaya may still reject a result after verifying (or even after approving,
        // before publish) — matches BoardResultVerificationController::reject(), which
        // allows rejection from submitted/verified/approved. A published package reopens
        // through the same Sahodaya Returned edge, but only via the separate, deliberate
        // BoardResultVerificationController::unpublish() action
        // (BoardResultCertificationService::unpublish()), never the ordinary reject() path.
        self::STATUS_SAHODAYA_VERIFIED => [self::STATUS_APPROVED, self::STATUS_SAHODAYA_RETURNED],
        self::STATUS_APPROVED => [self::STATUS_PUBLISHED, self::STATUS_SAHODAYA_RETURNED],
        self::STATUS_PUBLISHED => [self::STATUS_SAHODAYA_RETURNED],
        self::STATUS_SUPERSEDED => [],
    ];

    /** Terminal-ish statuses that may not be superseded in place (a returned/superseded pkg already spawned the next version). */
    public const TERMINAL_STATUSES = [self::STATUS_PUBLISHED, self::STATUS_SUPERSEDED];

    protected $fillable = [
        'board_result_id',
        'tenant_id',
        'academic_year',
        'class',
        'version',
        'status',
        'data_snapshot',
        'data_hash',
        'generated_pdf_path',
        'generated_pdf_disk',
        'generated_at',
        'signed_pdf_path',
        'signed_pdf_disk',
        'signed_pdf_hash',
        'signed_by_user_id',
        'signer_role',
        'signed_at',
        'submitted_by_user_id',
        'submitted_at',
        'returned_by_user_id',
        'returned_at',
        'return_reason',
        'superseded_at',
    ];

    protected $casts = [
        'class' => 'integer',
        'version' => 'integer',
        'data_snapshot' => 'array',
        'generated_at' => 'datetime',
        'signed_at' => 'datetime',
        'submitted_at' => 'datetime',
        'returned_at' => 'datetime',
        'superseded_at' => 'datetime',
    ];

    public function boardResult(): BelongsTo
    {
        return $this->belongsTo(BoardResult::class);
    }

    public function reports(): HasMany
    {
        return $this->hasMany(BoardResultCertificationReport::class, 'certification_package_id');
    }

    public function signedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'signed_by_user_id');
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by_user_id');
    }

    public function returnedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'returned_by_user_id');
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->whereNotIn('status', [self::STATUS_SUPERSEDED]);
    }

    public function scopeForBoardResult(Builder $q, int|string $boardResultId): Builder
    {
        return $q->where('board_result_id', $boardResultId);
    }

    public function canTransitionTo(string $target): bool
    {
        return in_array($target, self::TRANSITIONS[$this->status] ?? [], true);
    }

    public function isSuperseded(): bool
    {
        return $this->status === self::STATUS_SUPERSEDED;
    }

    public function isEditable(): bool
    {
        return in_array($this->status, [self::STATUS_DRAFT, self::STATUS_LEADERSHIP_CHANGES_REQUESTED], true);
    }

    public function isSubmittedToSahodaya(): bool
    {
        return in_array($this->status, [
            self::STATUS_SUBMITTED_TO_SAHODAYA,
            self::STATUS_SAHODAYA_VERIFIED,
            self::STATUS_APPROVED,
            self::STATUS_PUBLISHED,
        ], true);
    }

    public static function statusLabels(): array
    {
        return [
            self::STATUS_DRAFT => 'Draft',
            self::STATUS_AWAITING_LEADERSHIP_REVIEW => 'Awaiting Leadership Review',
            self::STATUS_LEADERSHIP_CHANGES_REQUESTED => 'Changes Requested',
            self::STATUS_AWAITING_REPORT_SIGNATURES => 'Awaiting Report Signatures',
            self::STATUS_INDIVIDUAL_REPORTS_SIGNED => 'Individual Reports Signed',
            self::STATUS_AWAITING_CONSOLIDATED_SIGNATURE => 'Awaiting Consolidated Signature',
            self::STATUS_SCHOOL_CERTIFIED => 'School Certified',
            self::STATUS_SUBMITTED_TO_SAHODAYA => 'Submitted to Sahodaya',
            self::STATUS_SAHODAYA_RETURNED => 'Returned by Sahodaya',
            self::STATUS_SAHODAYA_VERIFIED => 'Verified by Sahodaya',
            self::STATUS_APPROVED => 'Approved',
            self::STATUS_PUBLISHED => 'Published',
            self::STATUS_SUPERSEDED => 'Superseded',
        ];
    }
}
