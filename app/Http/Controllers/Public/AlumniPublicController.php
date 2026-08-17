<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Alumni;
use Illuminate\Http\Request;

class AlumniPublicController extends Controller
{
    public function store(Request $request)
    {
        $tenant = tenancy()->tenant;
        abort_if(!$tenant, 404);

        $data = $request->validate([
            'name'         => 'required|string|max:255',
            'batch_year'   => 'required|integer|min:1950|max:2099',
            'email'        => 'required|email|max:255',
            'phone'        => 'nullable|string|max:30',
            'current_role' => 'nullable|string|max:255',
            'message'      => 'nullable|string|max:2000',
        ]);

        $data['tenant_id']   = $tenant->id;
        $data['is_approved'] = false;
        $data['is_featured'] = false;

        Alumni::create($data);

        return back()->with('success', 'Thank you for registering! Your alumni details have been submitted for verification.');
    }
}
