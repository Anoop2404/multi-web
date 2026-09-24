<?php

namespace App\Models\State;

/**
 * Append-only record of conduct corrections — attendance changed after marking, a mark edited after
 * entry, a result recomputed. These are what a disputed result turns on.
 */
class StateConductAudit extends StateModel
{
    public $timestamps = false;

    protected $table = 'state_conduct_audits';

    protected $fillable = [
        'state_event_id', 'state_id', 'kind', 'registration_id', 'participant_id',
        'item_id', 'item_code', 'value_from', 'value_to', 'reason',
        'changed_by_user_id', 'changed_by_name', 'created_at',
    ];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }
}
