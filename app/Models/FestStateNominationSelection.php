<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FestStateNominationSelection extends Model
{
    protected $fillable = [
        'batch_id', 'item_id', 'item_code', 'item_title',
        'source_event_id', 'mark_id', 'registration_id', 'participant_id', 'partition_key',
        'school_id', 'school_name', 'student_name', 'class_name', 'source_position', 'grade', 'score',
        'nomination_type', 'priority_order', 'skip_reason', 'status', 'selected_by',
    ];

    public function batch(): BelongsTo
    {
        return $this->belongsTo(FestStateNominationBatch::class, 'batch_id');
    }

    public function isPrimary(): bool
    {
        return $this->nomination_type === 'primary';
    }
}
