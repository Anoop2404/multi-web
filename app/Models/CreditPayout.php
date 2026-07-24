<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCentralTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class CreditPayout extends Model
{
    use BelongsToCentralTenant;

    protected $fillable = [
        'school_id',
        'creditable_type',
        'creditable_id',
        'amount',
        'bank_ref',
        'notes',
        'recorded_by_user_id',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public function school(): BelongsTo
    {
        return $this->belongsToCentralTenant('school_id');
    }

    public function creditable(): MorphTo
    {
        return $this->morphTo();
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_user_id');
    }
}
