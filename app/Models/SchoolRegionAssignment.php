<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCentralTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SchoolRegionAssignment extends Model
{
    use BelongsToCentralTenant;

    protected $fillable = [
        'tenant_id', 'region_id', 'school_id', 'academic_year', 'source', 'assigned_by_user_id',
        // §7.3 item 1 (docs/KALOTSAV_PHASED_LEVEL_FEE_PLAN.md, 2026-08-15): nullable
        // group namespace (e.g. 'off_stage', 'sargadhara') a row belongs to. NULL means
        // the legacy Sahodaya-wide row — see the column's migration docblock and
        // FestRegionPartitionService::schoolRegion() for the full backward-compat story.
        'partition_group',
    ];

    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class, 'region_id');
    }

    public function school(): BelongsTo
    {
        return $this->belongsToCentralTenant('school_id');
    }

    public function scopeForTenant($query, string $tenantId)
    {
        // Qualified with the table name deliberately: FestRegistrationController::schoolRegionContext()
        // joins this to `regions`, which also has its own tenant_id column — an unqualified
        // where('tenant_id', ...) is ambiguous to Postgres as soon as that join is present
        // (SQLSTATE 42702). Qualifying here is safe for every other (non-joined) caller too.
        return $query->where('school_region_assignments.tenant_id', $tenantId);
    }

    public function scopeForYear($query, string $academicYear)
    {
        return $query->where('academic_year', $academicYear);
    }

    /**
     * §7.3 item 3 (docs/KALOTSAV_PHASED_LEVEL_FEE_PLAN.md, 2026-08-15). Scope to a
     * specific regional phase's group namespace, or to the legacy Sahodaya-wide rows
     * when $partitionGroup is null (Laravel's query builder turns where('partition_group',
     * null) into a `partition_group IS NULL` clause automatically, matching exactly the
     * rows every pre-existing caller of this model already relies on).
     */
    public function scopeForPartitionGroup($query, ?string $partitionGroup)
    {
        return $query->where('partition_group', $partitionGroup);
    }
}
