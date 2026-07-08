<?php

namespace App\Models\State;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StateFestEvent extends Model
{
    protected $table = 'state_fest_events';

    protected $fillable = [
        'state_program_id', 'name', 'slug', 'status',
        'starts_on', 'ends_on', 'settings',
    ];

    protected $casts = [
        'starts_on' => 'date',
        'ends_on'   => 'date',
        'settings'  => 'array',
    ];

    public function registrations(): HasMany
    {
        return $this->hasMany(StateFestRegistration::class, 'state_event_id');
    }
}
