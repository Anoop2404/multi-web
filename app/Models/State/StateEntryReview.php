<?php

namespace App\Models\State;

/**
 * Append-only record of every scrutiny decision. An overwritten status cannot answer "who returned
 * this, when, and what did they say", which is the first thing an appeal asks.
 */
class StateEntryReview extends StateModel
{
    public $timestamps = false;

    protected $table = 'state_entry_reviews';

    protected $fillable = [
        'intake_id', 'entry_id', 'state_id', 'decision', 'previous_status',
        'note', 'decided_by_user_id', 'decided_by_name', 'created_at',
    ];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }
}
