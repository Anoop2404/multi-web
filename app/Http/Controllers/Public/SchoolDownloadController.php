<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Download;
use App\Support\TenantStorage;

class SchoolDownloadController extends Controller
{
    public function show(Download $download)
    {
        $tenant = tenancy()->tenant;

        abort_if(! $tenant || $tenant->type !== 'school', 404);
        abort_if($download->tenant_id !== $tenant->id || ! $download->is_active, 404);

        return TenantStorage::downloadPrivate($download->file_path, filename: $download->file_name);
    }
}
