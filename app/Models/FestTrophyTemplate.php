<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FestTrophyTemplate extends Model
{
    protected $table = 'fest_trophy_templates';

    protected $fillable = [
        'event_id',
        'name',
        'code',
        'description',
        'is_default',
    ];

    protected $casts = [
        'is_default' => 'boolean',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(FestEvent::class, 'event_id');
    }

    public function trophies(): HasMany
    {
        return $this->hasMany(FestTrophy::class, 'template_id')->orderBy('trophy_no');
    }
}
