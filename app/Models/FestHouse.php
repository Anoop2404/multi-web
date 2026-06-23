<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FestHouse extends Model
{
    protected $fillable = ['event_id', 'name', 'color', 'motto', 'sort_order'];

    public function event(): BelongsTo
    {
        return $this->belongsTo(FestEvent::class, 'event_id');
    }

    public function schoolAssignments(): HasMany
    {
        return $this->hasMany(FestHouseSchool::class, 'house_id');
    }
}
