<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCentralTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Binds a user (typically a region_admin) to one or more regions within a
 * Sahodaya for a given academic year. Used by RegionScope middleware to
 * inject allowed region IDs into the request for controller-level filtering.
 */
class UserRegionAssignment extends Model
{
    use BelongsToCentralTenant;

    protected $fillable = [
        'tenant_id',
        'user_id',
        'region_id',
        'academic_year',
        'assigned_by_user_id',
    ];

    protected $casts = [
        'assigned_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    public function scopeForUser($q, string $userId)
    {
        return $q->where('user_id', $userId);
    }

    public function scopeForYear($q, string $year)
    {
        return $q->where('academic_year', $year);
    }

    public function scopeForTenant($q, string $tenantId)
    {
        return $q->where('tenant_id', $tenantId);
    }
}
