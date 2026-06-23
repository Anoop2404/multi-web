<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FestCateringOrder extends Model
{
    protected $fillable = [
        'event_id', 'school_id', 'meal_date', 'meal_type',
        'head_count', 'notes', 'status', 'submitted_by_user_id',
    ];

    protected $casts = ['meal_date' => 'date'];

    public function event(): BelongsTo
    {
        return $this->belongsTo(FestEvent::class, 'event_id');
    }
}
