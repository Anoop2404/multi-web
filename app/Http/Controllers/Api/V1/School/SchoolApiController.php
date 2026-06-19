<?php

namespace App\Http\Controllers\Api\V1\School;

use App\Http\Controllers\Api\ApiController;
use App\Models\Tenant;
use Illuminate\Http\Request;

abstract class SchoolApiController extends ApiController
{
    protected Tenant $school;

    public function __construct(Request $request)
    {
        $this->school = Tenant::query()
            ->where('id', $request->route('tenantId'))
            ->where('type', 'school')
            ->firstOrFail();
    }
}
