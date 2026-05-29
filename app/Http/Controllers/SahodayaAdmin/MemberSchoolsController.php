<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Models\Tenant;

class MemberSchoolsController extends SahodayaAdminController
{
    public function index()
    {
        $schools = Tenant::where('parent_id', $this->sahodaya->id)
            ->withCount(['news', 'events'])
            ->orderBy('name')
            ->get();

        return $this->inertia('Sahodaya/Schools/Index', compact('schools'));
    }
}
