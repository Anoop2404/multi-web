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
}
