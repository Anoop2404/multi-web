<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FestVolunteer extends Model
{
    protected $fillable = ['event_id', 'name', 'phone', 'duty', 'notes'];

    public function event(): BelongsTo
    {
        return $this->belongsTo(FestEvent::class, 'event_id');
    }
}
