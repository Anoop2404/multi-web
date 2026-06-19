<?php

namespace App\Http\Controllers\Api\V1\Sahodaya;

use App\Http\Controllers\Api\ApiController;
use App\Models\Tenant;
use Illuminate\Http\Request;

abstract class SahodayaApiController extends ApiController
{
    protected Tenant $sahodaya;

    public function __construct(Request $request)
    {
        $this->sahodaya = Tenant::query()
            ->where('id', $request->route('tenantId'))
            ->where('type', 'sahodaya')
            ->firstOrFail();
    }
}
