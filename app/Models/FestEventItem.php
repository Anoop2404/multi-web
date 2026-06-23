<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FestEventItem extends Model
{
    protected $fillable = [
        'event_id', 'title', 'category', 'participant_type', 'gender', 'class_group',
        'max_per_school', 'min_group_size', 'max_group_size', 'qualify_count',
        'owner_level', 'display_order',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(FestEvent::class, 'event_id');
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(FestRegistration::class, 'item_id');
    }
}
