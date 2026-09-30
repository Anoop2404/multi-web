<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FestStateNominationSelection extends Model
{
    protected $fillable = [
        'batch_id', 'item_id', 'item_code', 'item_title',
        'source_event_id', 'mark_id', 'registration_id', 'participant_id', 'partition_key',
        'school_id', 'school_name', 'student_name', 'roll_number', 'class_name', 'source_position', 'grade', 'score',
        'nomination_type', 'priority_order', 'skip_reason', 'status', 'selected_by',
        'school_response', 'school_responded_by', 'school_responded_by_name', 'school_responded_at',
        'replaces_selection_id',
    ];

    protected $casts = [
        'school_responded_at' => 'datetime',
    ];

    public function batch(): BelongsTo
    {
        return $this->belongsTo(FestStateNominationBatch::class, 'batch_id');
    }

    public function isPrimary(): bool
    {
        return $this->nomination_type === 'primary';
    }

    /** Auto-filled into a slot collection round and still waiting on the school. */
    public function isAwaitingSchoolResponse(): bool
    {
        return $this->status === 'selected'
            && $this->nomination_type === 'primary'
            && $this->school_response === 'pending';
    }
}
