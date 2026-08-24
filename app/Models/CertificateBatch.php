<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class CertificateBatch extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_COMPLETED_WITH_ERRORS = 'completed_with_errors';
    public const STATUS_FAILED = 'failed';
    public const STATUS_CANCELLED = 'cancelled';

    public const TERMINAL_STATUSES = [
        self::STATUS_COMPLETED,
        self::STATUS_COMPLETED_WITH_ERRORS,
        self::STATUS_FAILED,
        self::STATUS_CANCELLED,
    ];

    protected $fillable = [
        'tenant_id', 'event_id', 'batch_type', 'cert_type', 'item_id', 'school_id',
        'certificate_ids_json', 'scope_description', 'total_count', 'processed_count',
        'succeeded_count', 'failed_count', 'status', 'error', 'failed_items_json',
        'file_path', 'storage_disk', 'queued_job_batch_id', 'created_by_user_id',
        'started_at', 'completed_at',
    ];

    protected $casts = [
        'certificate_ids_json' => 'array',
        'failed_items_json'    => 'array',
        'total_count'           => 'integer',
        'processed_count'       => 'integer',
        'succeeded_count'       => 'integer',
        'failed_count'          => 'integer',
        'started_at'            => 'datetime',
        'completed_at'          => 'datetime',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(FestEvent::class, 'event_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function isTerminal(): bool
    {
        return in_array($this->status, self::TERMINAL_STATUSES, true);
    }

    /**
     * Atomic counter bump from a chunk job — a single SQL UPDATE rather than a PHP
     * read-modify-write, so concurrent chunk jobs in the same batch never clobber each
     * other's progress.
     */
    public function recordChunkResult(int $processed, int $succeeded, int $failed): void
    {
        DB::table('certificate_batches')->where('id', $this->id)->update([
            'processed_count' => DB::raw('processed_count + '.max(0, $processed)),
            'succeeded_count' => DB::raw('succeeded_count + '.max(0, $succeeded)),
            'failed_count'    => DB::raw('failed_count + '.max(0, $failed)),
            'updated_at'      => now(),
        ]);
    }

    /**
     * Append to the capped failed-items list. Read-then-write, unlike
     * recordChunkResult()'s atomic counters — an occasional lost entry under concurrent
     * chunk failures is an acceptable trade for a support/debug aid, not a correctness-
     * critical count (failed_count itself is always accurate).
     */
    public function appendFailedItems(array $items): void
    {
        if (empty($items)) {
            return;
        }

        $this->refresh();
        $merged = array_slice(array_merge($this->failed_items_json ?? [], $items), -100);
        $this->update(['failed_items_json' => $merged]);
    }
}
