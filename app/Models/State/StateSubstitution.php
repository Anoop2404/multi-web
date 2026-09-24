<?php

namespace App\Models\State;

use Illuminate\Database\Eloquent\Concerns\HasUuids;

/**
 * Replacing a participant after certification. Kept separate from editing an entry because it is a
 * decision with a reason, evidence and an approver — not a correction.
 */
class StateSubstitution extends StateModel
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $table = 'state_substitutions';

    protected $fillable = [
        'state_event_id', 'state_id', 'sahodaya_id', 'registration_id', 'original_participant_id',
        'original_name', 'substitute_name', 'substitute_class', 'school_id', 'school_name',
        'item_code', 'reason', 'evidence_path', 'status', 'decision_note',
        'requested_by_user_id', 'requested_by_name', 'decided_by_user_id', 'decided_by_name', 'decided_at',
    ];

    protected function casts(): array
    {
        return ['decided_at' => 'datetime'];
    }

    public function isPending(): bool
    {
        return $this->status === 'requested';
    }
}
