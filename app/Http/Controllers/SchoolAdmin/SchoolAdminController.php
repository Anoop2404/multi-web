<?php

namespace App\Http\Controllers\SchoolAdmin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\Http\Request;

abstract class SchoolAdminController extends Controller
{
    protected Tenant $school;

    public function __construct(Request $request)
    {
        $tenantId = $request->route('tenantId');
        $this->school = Tenant::findOrFail($tenantId);
    }

    protected function inertia(string $component, array $props = [])
    {
        return inertia($component, array_merge([
            'school' => $this->school->only('id', 'name', 'type'),
        ], $props));
    }
}
