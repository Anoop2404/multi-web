<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TrainingProgram extends Model
{
    protected $fillable = [
        'tenant_id', 'academic_year_id', 'title', 'description', 'conductor_level',
        'registration_open', 'registration_close', 'max_participants', 'status',
        'fee_type', 'fee_amount',
    ];

    protected $casts = [
        'registration_open'  => 'date',
        'registration_close' => 'date',
        'fee_amount'         => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $program) {
            if ($program->fee_type === null) {
                $program->fee_type = 'none';
            }
        });
    }

    public function hasFee(): bool
    {
        return $this->fee_type !== 'none' && (float) $this->fee_amount > 0;
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(TrainingSession::class, 'program_id');
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(TrainingRegistration::class, 'program_id');
    }
}
