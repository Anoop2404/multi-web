<?php

namespace App\Models;

use App\Models\Concerns\UsesTenantConnectionWhenIsolated;
use Spatie\Permission\Models\Permission as SpatiePermission;

class Permission extends SpatiePermission
{
    use UsesTenantConnectionWhenIsolated;
}
