<?php

namespace App\Models\State;

/**
 * Append-only history of slot changes. Never updated, never deleted from: a quota that moved and
 * left no trace is exactly what an appeal will ask about.
 */
class StateSlotAuditEntry extends StateModel
{
    public $timestamps = false;

    protected $table = 'state_slot_audit_entries';

    protected $fillable = [
        'state_program_id', 'state_id', 'item_id', 'sahodaya_id', 'scope',
        'slots_from', 'slots_to', 'reason', 'changed_by_user_id', 'changed_by_name', 'created_at',
    ];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }
}
