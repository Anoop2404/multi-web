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
        return $query->where('tenant_id', $tenantId);
    }

    public function scopeForYear($query, string $academicYear)
    {
        return $query->where('academic_year', $academicYear);
    }
}
