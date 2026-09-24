<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCentralTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FestSchoolDistance extends Model
{
    use BelongsToCentralTenant;

    protected $fillable = [
        'event_id', 'school_id', 'distance_km',
    ];

    protected $casts = [
        'distance_km' => 'decimal:2',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(FestEvent::class, 'event_id');
    }

    public function school(): BelongsTo
    {
        return $this->belongsToCentralTenant('school_id');
    }
}
