<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FestRankPointTemplate extends Model
{
    protected $fillable = ['event_id', 'name', 'participant_types', 'sort_order'];

    protected $casts = [
        'participant_types' => 'array',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(FestEvent::class, 'event_id');
    }

    public function points(): HasMany
    {
        return $this->hasMany(FestRankPoint::class, 'template_id')->orderBy('rank');
    }

    public function governsType(string $participantType): bool
    {
        return in_array($participantType, $this->participant_types ?? [], true);
    }
}
