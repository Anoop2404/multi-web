<?php

namespace App\Models\State;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class StateFestParticipant extends StateModel
{
    protected $table = 'state_fest_participants';

    protected $fillable = [
        'state_event_id', 'registration_id', 'student_name', 'roll_number', 'class_name', 'chest_number', 'meta',
        'is_leader', 'is_standby', 'withdrawn_at',
    ];

    protected $casts = [
        'meta'         => 'array',
        'is_leader'    => 'boolean',
        'is_standby'   => 'boolean',
        'withdrawn_at' => 'datetime',
    ];

    /** Someone who is actually competing: not a standby, not withdrawn. */
    public function isCompeting(): bool
    {
        return ! $this->is_standby && $this->withdrawn_at === null;
    }

    public function registration(): BelongsTo
    {
        return $this->belongsTo(StateFestRegistration::class, 'registration_id');
    }

    public function mark(): HasOne
    {
        return $this->hasOne(StateFestMark::class, 'participant_id');
    }
}
