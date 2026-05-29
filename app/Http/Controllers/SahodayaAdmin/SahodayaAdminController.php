<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\Http\Request;

abstract class SahodayaAdminController extends Controller
{
    protected Tenant $sahodaya;

    public function __construct(Request $request)
    {
        $tenantId = $request->route('tenantId');
        $this->sahodaya = Tenant::where('id', $tenantId)->where('type', 'sahodaya')->firstOrFail();
    }

    protected function inertia(string $component, array $props = [])
    {
        return inertia($component, array_merge([
            'sahodaya' => $this->sahodaya->only('id', 'name', 'type'),
        ], $props));
    }
}
