<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FestAthleticRecord extends Model
{
    protected $fillable = [
        'event_id', 'item_id', 'class_group', 'gender', 'record_direction',
        'record_value', 'record_unit', 'holder_name', 'holder_school_id',
        'holder_participant_id', 'source_mark_id', 'record_date', 'notes',
    ];

    protected $casts = [
        'record_value' => 'decimal:4',
        'record_date'  => 'date',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(FestEvent::class, 'event_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(FestEventItem::class, 'item_id');
    }

    public function breaks(): HasMany
    {
        return $this->hasMany(FestRecordBreak::class, 'athletic_record_id');
    }
}
