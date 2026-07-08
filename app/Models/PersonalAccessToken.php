<?php

namespace App\Models;

use App\Models\Concerns\UsesTenantConnectionWhenIsolated;
use Laravel\Sanctum\PersonalAccessToken as SanctumPersonalAccessToken;

class PersonalAccessToken extends SanctumPersonalAccessToken
{
    use UsesTenantConnectionWhenIsolated;
}
