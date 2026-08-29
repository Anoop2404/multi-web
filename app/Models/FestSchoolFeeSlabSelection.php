<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCentralTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FestSchoolFeeSlabSelection extends Model
{
    use BelongsToCentralTenant;

    protected $fillable = [
        'event_id', 'school_id', 'min_count', 'max_count', 'amount', 'selected_at',
        'selected_by', 'locked_at', 'changed_at', 'changed_by', 'change_reason',
    ];

    protected $casts = [
        'min_count' => 'integer',
        'max_count' => 'integer',
        'amount' => 'decimal:2',
        'selected_at' => 'datetime',
        'locked_at' => 'datetime',
        'changed_at' => 'datetime',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(FestEvent::class, 'event_id');
    }

    public function school(): BelongsTo
    {
        return $this->belongsToCentralTenant('school_id');
    }

    public function isLocked(): bool
    {
        return $this->locked_at !== null;
    }
}
