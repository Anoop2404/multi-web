<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FestGroup extends Model
{
    protected $fillable = ['registration_id', 'team_name', 'status'];

    public function registration(): BelongsTo
    {
        return $this->belongsTo(FestRegistration::class, 'registration_id');
    }

    public function participants(): HasMany
    {
        return $this->hasMany(FestParticipant::class, 'group_id');
    }
}
