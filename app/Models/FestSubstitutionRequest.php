<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FestSubstitutionRequest extends Model
{
    protected $fillable = [
        'event_id', 'school_id', 'registration_id', 'original_participant_id',
        'replacement_participant_id', 'replacement_student_id', 'reason', 'status',
        'resolution_note', 'requested_by_user_id', 'reviewed_by_user_id', 'reviewed_at',
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(FestEvent::class, 'event_id');
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'school_id');
    }

    public function registration(): BelongsTo
    {
        return $this->belongsTo(FestRegistration::class, 'registration_id');
    }

    public function originalParticipant(): BelongsTo
    {
        return $this->belongsTo(FestParticipant::class, 'original_participant_id');
    }

    public function replacementParticipant(): BelongsTo
    {
        return $this->belongsTo(FestParticipant::class, 'replacement_participant_id');
    }

    public function replacementStudent(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'replacement_student_id');
    }
}
