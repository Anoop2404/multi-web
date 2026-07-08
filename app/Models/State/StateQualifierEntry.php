<?php

namespace App\Models\State;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StateQualifierEntry extends Model
{
    protected $fillable = [
        'intake_id', 'source_registration_id', 'source_participant_id',
        'school_id', 'school_name', 'item_id', 'item_code', 'item_name',
        'student_name', 'class_name', 'position', 'grade', 'points',
        'partition_key', 'qualifier_type', 'status', 'meta',
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    public function intake(): BelongsTo
    {
        return $this->belongsTo(StateQualifierIntake::class, 'intake_id');
    }
}
