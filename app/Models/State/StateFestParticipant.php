<?php

namespace App\Models\State;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class StateFestParticipant extends StateModel
{
    protected $table = 'state_fest_participants';

    protected $fillable = [
        'state_event_id', 'registration_id', 'student_name', 'class_name', 'chest_number', 'meta',
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    public function registration(): BelongsTo
    {
        return $this->belongsTo(StateFestRegistration::class, 'registration_id');
    }

    public function mark(): HasOne
    {
        return $this->hasOne(StateFestMark::class, 'participant_id');
    }
}
