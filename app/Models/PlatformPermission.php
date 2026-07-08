<?php

namespace App\Models;

use Spatie\Permission\Models\Permission as SpatiePermission;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

class PlatformPermission extends SpatiePermission
{
    use CentralConnection;

    protected $table = 'permissions';
}
