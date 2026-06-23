<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FestEvent extends Model
{
    protected $fillable = [
        'tenant_id', 'academic_year_id', 'title', 'event_type', 'conductor_level',
        'is_cascaded', 'parent_event_id', 'registration_open', 'registration_close',
        'event_start', 'event_end', 'venue', 'fee_type', 'fee_amount', 'status',
        'results_published', 'description',
    ];

    protected $casts = [
        'is_cascaded'         => 'boolean',
        'results_published'   => 'boolean',
        'registration_open'   => 'date',
        'registration_close'  => 'date',
        'event_start'         => 'date',
        'event_end'           => 'date',
        'fee_amount'          => 'decimal:2',
    ];

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYearRecord::class, 'academic_year_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(FestEventItem::class, 'event_id')->orderBy('display_order');
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(FestRegistration::class, 'event_id');
    }

    public function results(): HasMany
    {
        return $this->hasMany(FestResult::class, 'event_id');
    }

    public function parentEvent(): BelongsTo
    {
        return $this->belongsTo(FestEvent::class, 'parent_event_id');
    }

    public function childEvents(): HasMany
    {
        return $this->hasMany(FestEvent::class, 'parent_event_id');
    }

    public function houses(): HasMany
    {
        return $this->hasMany(FestHouse::class, 'event_id')->orderBy('sort_order');
    }

    public function scopeForTenant($q, string $tenantId)
    {
        return $q->where('tenant_id', $tenantId);
    }

    public function scopeOfType($q, string $type)
    {
        return $q->where('event_type', $type);
    }

    public function isRegistrationOpen(): bool
    {
        if ($this->status !== 'registration_open') {
            return false;
        }

        $today = now()->startOfDay();

        if ($this->registration_open && $today->lt($this->registration_open)) {
            return false;
        }

        if ($this->registration_close && $today->gt($this->registration_close)) {
            return false;
        }

        return true;
    }
}
