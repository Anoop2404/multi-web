<?php

namespace App\Models;

use App\Models\Concerns\ScopesBySahodaya;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Normalized subject marks for a Topper.
 *
 * Tenant isolation is primarily via topper_id → toppers.tenant_id. A denormalized
 * tenant_id column is also stored for defense-in-depth direct queries (#FRD-13 #3).
 * Prefer scopeForTenant() / whereIn('tenant_id', ...) when querying this table directly.
 */
class TopperSubjectMark extends Model
{
    use ScopesBySahodaya;

    /** @var string */
    protected $tenantScopeColumn = 'tenant_id';

    protected $fillable = [
        'topper_id',
        'tenant_id',
        'subject_id',
        'subject_label',
        'marks',
    ];

    protected $casts = [
        'marks' => 'float',
    ];

    public function topper(): BelongsTo
    {
        return $this->belongsTo(Topper::class);
    }

    public function scopeForSahodaya(Builder $query, string $sahodayaId): Builder
    {
        $schoolIds = \App\Support\TenancyDatabase::schoolIdsFor($sahodayaId);

        return $query->whereIn($this->qualifyColumn('tenant_id'), $schoolIds ?: ['__none__']);
    }
}
