<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCentralTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @deprecated Retired 06 Aug 2026 (docs/REGION_SCOPED_ADMIN_AND_EVENT_FLOW_PLAN.md §2.3, Phase 2).
 * Never populated by any UI, only ever read by the equally-dead RegionScope middleware.
 * Region-admin scoping now lives on FestEventStaff (duty=region_admin, region_id). Not deleted
 * outright because files in the tracked workspace can't be removed by the tooling that made this
 * change — safe to `git rm` this file and app/Http/Middleware/RegionScope.php, and drop the
 * underlying table via 2026_09_14_000002_drop_user_region_assignments_table.php.
 *
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
