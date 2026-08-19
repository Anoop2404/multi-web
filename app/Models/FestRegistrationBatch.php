<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FestRegistrationBatch extends Model
{
    protected $fillable = [
        'event_id', 'code', 'name', 'sort_order', 'registration_open',
        'registration_close', 'payment_due_at', 'school_base_fee', 'student_registration_fee',
        'invoice_prefix', 'status', 'registration_locked',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'registration_open' => 'datetime',
        'registration_close' => 'datetime',
        'payment_due_at' => 'datetime',
        'school_base_fee' => 'decimal:2',
        'student_registration_fee' => 'decimal:2',
        'registration_locked' => 'boolean',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(FestEvent::class, 'event_id');
    }

    public function phases(): HasMany
    {
        return $this->hasMany(FestEventPhase::class, 'registration_batch_id')->orderBy('sort_order');
    }

    public function operationalEvents(): HasMany
    {
        return $this->hasMany(FestEvent::class, 'registration_batch_id');
    }

    public function fees(): HasMany
    {
        return $this->hasMany(FestSchoolEventFee::class, 'registration_batch_id');
    }

    public function isRegistrationOpen(): bool
    {
        if ($this->registration_locked || ! in_array($this->status, ['published', 'registration_open'], true)) {
            return false;
        }

        return (! $this->registration_open || now()->gte($this->registration_open))
            && (! $this->registration_close || now()->lte($this->registration_close));
    }
}
