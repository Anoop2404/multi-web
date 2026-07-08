<?php

namespace App\Models;

use Spatie\Permission\Models\Role as SpatieRole;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

class PlatformRole extends SpatieRole
{
    use CentralConnection;

    protected $table = 'roles';
}
