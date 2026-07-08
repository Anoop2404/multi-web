<?php

namespace App\Models\Concerns;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToCentralTenant
{
    protected function belongsToCentralTenant(string $foreignKey = 'tenant_id'): BelongsTo
    {
        $relation = $this->belongsTo(Tenant::class, $foreignKey);
        $relation->getRelated()->setConnection(
            (string) config('tenancy.database.central_connection', 'central')
        );

        return $relation;
    }
}
