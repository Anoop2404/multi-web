<?php

namespace App\Models\State;

use Illuminate\Database\Eloquent\Relations\HasMany;

class StateFestEvent extends StateModel
{
    protected $table = 'state_fest_events';

    protected $fillable = [
        'state_program_id', 'name', 'slug', 'status',
        'starts_on', 'ends_on', 'settings',
        'results_published', 'scoring_locked', 'scoring_preset',
    ];

    protected $casts = [
        'starts_on'         => 'date',
        'ends_on'           => 'date',
        'settings'          => 'array',
        'results_published' => 'boolean',
        'scoring_locked'    => 'boolean',
    ];

    public function registrations(): HasMany
    {
        return $this->hasMany(StateFestRegistration::class, 'state_event_id');
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(StateAttendance::class, 'state_event_id');
    }

    public function marks(): HasMany
    {
        return $this->hasMany(StateFestMark::class, 'state_event_id');
    }
}
