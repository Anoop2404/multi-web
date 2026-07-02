<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SchoolLockOverride extends Model
{
    protected $fillable = [
        'sahodaya_id', 'school_id', 'override_type', 'reason', 'expires_at', 'created_by_user_id',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    public function school(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'school_id');
    }

    public function scopeActive($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
        });
    }

    public function isActive(): bool
    {
        return $this->expires_at === null || $this->expires_at->isFuture();
    }
}
