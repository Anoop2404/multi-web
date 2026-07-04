<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FestSchoolVerification extends Model
{
    protected $fillable = [
        'event_id', 'school_id', 'documents_verified', 'verified_by_user_id',
        'verified_at', 'notes',
    ];

    protected $casts = [
        'documents_verified' => 'boolean',
        'verified_at'        => 'datetime',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(FestEvent::class, 'event_id');
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'school_id');
    }
}
