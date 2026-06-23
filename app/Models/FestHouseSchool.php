<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FestHouseSchool extends Model
{
    protected $fillable = ['event_id', 'house_id', 'school_id'];

    public function house(): BelongsTo
    {
        return $this->belongsTo(FestHouse::class, 'house_id');
    }
}
