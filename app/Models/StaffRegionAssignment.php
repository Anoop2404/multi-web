<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Scopes a Sahodaya staff member to specific regions for Membership/Student data. Independent
 * of the Fest-only region_admin/FestEventStaff mechanism — see SahodayaAdminController::
 * membershipRegionScopedSchoolIds(). Row presence alone scopes the user; no rows means
 * unrestricted (same as a plain sahodaya_admin).
 */
class StaffRegionAssignment extends Model
{
    protected $fillable = [
        'tenant_id', 'user_id', 'region_id', 'assigned_by_user_id',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    public function scopeForTenant($query, string $tenantId)
    {
        return $query->where('staff_region_assignments.tenant_id', $tenantId);
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }
}
