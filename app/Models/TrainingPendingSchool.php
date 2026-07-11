<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TrainingPendingSchool extends Model
{
    protected $fillable = [
        'program_id', 'school_name', 'school_code',
        'contact_name', 'contact_email', 'contact_phone',
        'status', 'linked_school_id',
    ];

    public function program(): BelongsTo
    {
        return $this->belongsTo(TrainingProgram::class, 'program_id');
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(TrainingRegistration::class, 'pending_school_id');
    }
}
