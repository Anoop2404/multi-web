<?php

namespace App\Models\State;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StateFestRegistration extends Model
{
    protected $table = 'state_fest_registrations';

    protected $fillable = [
        'state_event_id', 'qualifier_entry_id', 'school_id', 'school_name',
        'item_id', 'item_code', 'status', 'meta',
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    public function stateEvent(): BelongsTo
    {
        return $this->belongsTo(StateFestEvent::class, 'state_event_id');
    }

    public function participants(): HasMany
    {
        return $this->hasMany(StateFestParticipant::class, 'registration_id');
    }
}
