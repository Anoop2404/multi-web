<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FestParticipant extends Model
{
    protected $fillable = [
        'registration_id', 'group_id', 'student_id', 'teacher_id', 'event_id',
        'participant_type', 'participant_role', 'chest_no', 'chest_revealed_at',
        'level_registration_number', 'item_registration_number', 'disqualified_at', 'disqualification_reason',
    ];

    protected $casts = [
        'disqualified_at'   => 'datetime',
        'chest_revealed_at' => 'datetime',
    ];

    public function registration(): BelongsTo
    {
        return $this->belongsTo(FestRegistration::class, 'registration_id');
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(FestGroup::class, 'group_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }

    public function mark(): BelongsTo
    {
        return $this->belongsTo(FestMark::class, 'id', 'participant_id');
    }
}
