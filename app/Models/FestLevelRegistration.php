<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FestLevelRegistration extends Model
{
    protected $fillable = ['event_id', 'student_id', 'school_id', 'registration_number', 'status', 'registered_at'];

    protected $casts = [
        'registered_at' => 'datetime',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(FestEvent::class, 'event_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'school_id');
    }
}
