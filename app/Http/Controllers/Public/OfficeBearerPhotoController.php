<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\OfficeBearers;
use App\Support\TenantStorage;

class OfficeBearerPhotoController extends Controller
{
    public function show(OfficeBearers $bearer)
    {
        abort_unless($bearer->photo, 404);

        $tenant = tenant();
        abort_unless($tenant && $bearer->tenant_id === $tenant->id, 404);

        return TenantStorage::downloadResponse($tenant, $bearer->photo);
    }
}
