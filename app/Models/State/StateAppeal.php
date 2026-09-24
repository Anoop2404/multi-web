<?php

namespace App\Models\State;

use Illuminate\Database\Eloquent\Concerns\HasUuids;

/** An appeal against a State result. Upholding one changes a result, which is what makes certificates stale. */
class StateAppeal extends StateModel
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $table = 'state_appeals';

    public const OPEN_STATUSES = ['submitted', 'under_review'];

    protected $fillable = [
        'state_event_id', 'state_id', 'sahodaya_id', 'item_id', 'item_code',
        'registration_id', 'participant_id', 'participant_name', 'school_name',
        'grounds', 'evidence_path', 'fee_amount', 'fee_status',
        'status', 'review_notes', 'outcome_summary',
        'decided_by_user_id', 'decided_by_name', 'decided_at',
    ];

    protected function casts(): array
    {
        return ['decided_at' => 'datetime', 'fee_amount' => 'decimal:2'];
    }

    public function isOpen(): bool
    {
        return in_array($this->status, self::OPEN_STATUSES, true);
    }
}
