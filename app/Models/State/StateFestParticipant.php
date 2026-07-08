<?php

namespace App\Models\State;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StateFestParticipant extends Model
{
    protected $table = 'state_fest_participants';

    protected $fillable = [
        'registration_id', 'student_name', 'class_name', 'chest_number', 'meta',
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    public function registration(): BelongsTo
    {
        return $this->belongsTo(StateFestRegistration::class, 'registration_id');
    }
}
