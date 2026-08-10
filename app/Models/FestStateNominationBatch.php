<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FestStateNominationBatch extends Model
{
    protected $fillable = [
        'state_program_id', 'hub_event_id', 'maker_id', 'checker_id',
        'status', 'certified_at', 'certification_notes',
    ];

    protected $casts = [
        'certified_at' => 'datetime',
    ];

    public function hubEvent(): BelongsTo
    {
        return $this->belongsTo(FestEvent::class, 'hub_event_id');
    }

    public function selections(): HasMany
    {
        return $this->hasMany(FestStateNominationSelection::class, 'batch_id');
    }

    public function primarySelections(): HasMany
    {
        return $this->selections()
            ->where('nomination_type', 'primary')
            ->where('status', 'selected');
    }

    public function isCertified(): bool
    {
        return $this->status === 'certified';
    }
}
