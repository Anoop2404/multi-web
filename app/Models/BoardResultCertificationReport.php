<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCentralTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BoardResultCertificationReport extends Model
{
    use BelongsToCentralTenant;

    public const TYPE_SUMMARY = 'summary';

    public const TYPE_OVERALL_TOPPERS = 'overall_toppers';

    public const TYPE_SUBJECT_TOPPERS = 'subject_toppers';

    public const TYPE_FULL_A1 = 'full_a1';

    public const TYPES = [
        self::TYPE_SUMMARY,
        self::TYPE_OVERALL_TOPPERS,
        self::TYPE_SUBJECT_TOPPERS,
        self::TYPE_FULL_A1,
    ];

    public const STATUS_PENDING = 'pending';

    public const STATUS_GENERATED = 'generated';

    public const STATUS_SIGNED_UPLOADED = 'signed_uploaded';

    public const STATUS_ACCEPTED = 'accepted';

    public const STATUS_CHANGES_REQUESTED = 'changes_requested';

    public const STATUS_SUPERSEDED = 'superseded';

    /** @var array<string, list<string>> */
    public const TRANSITIONS = [
        self::STATUS_PENDING => [self::STATUS_GENERATED],
        self::STATUS_GENERATED => [self::STATUS_SIGNED_UPLOADED, self::STATUS_GENERATED],
        self::STATUS_SIGNED_UPLOADED => [self::STATUS_ACCEPTED, self::STATUS_CHANGES_REQUESTED],
        self::STATUS_CHANGES_REQUESTED => [self::STATUS_SIGNED_UPLOADED],
        self::STATUS_ACCEPTED => [],
        self::STATUS_SUPERSEDED => [],
    ];

    protected $fillable = [
        'certification_package_id',
        'tenant_id',
        'report_type',
        'stream_id',
        'status',
        'row_count',
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
        'accepted_at',
        'review_notes',
    ];

    protected $casts = [
        'stream_id' => 'integer',
        'row_count' => 'integer',
        'data_snapshot' => 'array',
        'generated_at' => 'datetime',
        'signed_at' => 'datetime',
        'accepted_at' => 'datetime',
    ];

    public function package(): BelongsTo
    {
        return $this->belongsTo(BoardResultCertificationPackage::class, 'certification_package_id');
    }

    public function stream(): BelongsTo
    {
        return $this->belongsTo(ExamStream::class, 'stream_id');
    }

    public function signedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'signed_by_user_id');
    }

    public function canTransitionTo(string $target): bool
    {
        return in_array($target, self::TRANSITIONS[$this->status] ?? [], true);
    }

    public function isAccepted(): bool
    {
        return $this->status === self::STATUS_ACCEPTED;
    }

    public static function typeLabel(string $type): string
    {
        return [
            self::TYPE_SUMMARY => 'Result Summary & Proof',
            self::TYPE_OVERALL_TOPPERS => 'School Topper(s)',
            self::TYPE_SUBJECT_TOPPERS => 'Subject-wise Toppers',
            self::TYPE_FULL_A1 => 'Full A1 Achievers',
        ][$type] ?? $type;
    }

    public static function statusLabels(): array
    {
        return [
            self::STATUS_PENDING => 'Pending',
            self::STATUS_GENERATED => 'Generated',
            self::STATUS_SIGNED_UPLOADED => 'Signed Copy Uploaded',
            self::STATUS_ACCEPTED => 'Accepted',
            self::STATUS_CHANGES_REQUESTED => 'Changes Requested',
            self::STATUS_SUPERSEDED => 'Superseded',
        ];
    }
}
