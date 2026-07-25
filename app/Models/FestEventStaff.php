<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FestEventStaff extends Model
{
    protected $table = 'fest_event_staff';

    protected $fillable = ['event_id', 'user_id', 'duty', 'stage_id', 'venue_id', 'head_id', 'region_id'];

    public function event(): BelongsTo
    {
        return $this->belongsTo(FestEvent::class, 'event_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class, 'region_id');
    }

    public function stage(): BelongsTo
    {
        return $this->belongsTo(FestStage::class, 'stage_id');
    }

    public function venue(): BelongsTo
    {
        return $this->belongsTo(FestVenue::class, 'venue_id');
    }

    public function head(): BelongsTo
    {
        return $this->belongsTo(FestItemHead::class, 'head_id');
    }
}
