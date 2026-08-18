<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

class PlatformAnnouncement extends Model
{
    use CentralConnection;

    protected $fillable = [
        'title', 'body', 'type', 'audience', 'starts_at', 'ends_at', 'is_active', 'created_by_user_id',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function scopeCurrentlyActive(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', now()));
    }

    /** Announcement is relevant to $audience when it targets 'all' or that exact audience. */
    public function scopeForAudience(Builder $query, array $audiences): Builder
    {
        return $query->where(function ($q) use ($audiences) {
            $q->where('audience', 'all')->orWhereIn('audience', $audiences);
        });
    }
}
