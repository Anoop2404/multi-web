<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

/**
 * Defense-in-depth tenant filtering for Sahodaya-scoped models.
 *
 * Prefer explicit {@see scopeForSahodaya()} / {@see scopeForTenant()} on every
 * read/write path. There is no automatic global scope (matches BoardResult/Topper
 * convention) so cross-tenant aggregate queries remain possible when intentional.
 */
trait ScopesBySahodaya
{
    public function scopeForSahodaya(Builder $query, string $sahodayaId): Builder
    {
        $column = property_exists($this, 'sahodayaScopeColumn')
            ? $this->sahodayaScopeColumn
            : 'sahodaya_id';

        return $query->where($this->qualifyColumn($column), $sahodayaId);
    }

    public function scopeForTenant(Builder $query, string $tenantId): Builder
    {
        $column = property_exists($this, 'tenantScopeColumn')
            ? $this->tenantScopeColumn
            : 'tenant_id';

        return $query->where($this->qualifyColumn($column), $tenantId);
    }
}
