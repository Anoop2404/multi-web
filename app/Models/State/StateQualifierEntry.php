<?php

namespace App\Models\State;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StateQualifierEntry extends StateModel
{
    protected $fillable = [
        'intake_id', 'source_registration_id', 'source_participant_id',
        'school_id', 'school_name', 'item_id', 'item_code', 'item_name',
        'student_name', 'roll_number', 'class_name', 'position', 'grade', 'points',
        'partition_key', 'qualifier_type', 'status', 'meta',
        'review_note', 'reviewed_at', 'reviewed_by_user_id', 'is_reserve',
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    public function intake(): BelongsTo
    {
        return $this->belongsTo(StateQualifierIntake::class, 'intake_id');
    }
}
