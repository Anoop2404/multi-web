<?php

namespace App\Http\Controllers\SchoolAdmin;

use App\Models\StaffMember;
use Illuminate\Http\Request;

class StaffController extends SchoolAdminController
{
    public function index()
    {
        $staff = StaffMember::where('tenant_id', $this->school->id)
            ->orderBy('display_order')
            ->get();

        return $this->inertia('School/Staff/Index', compact('staff'));
    }

    public function create()
    {
        return $this->inertia('School/Staff/Create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'          => 'required|string|max:255',
            'designation'   => 'required|string|max:255',
            'department'    => 'nullable|string|max:255',
            'qualification' => 'nullable|string|max:255',
            'type'          => 'required|in:teaching,non-teaching,admin',
            'display_order' => 'integer',
            'is_active'     => 'boolean',
            'photo'         => 'nullable|image|max:2048',
        ]);

        $data['tenant_id'] = $this->school->id;

        if ($request->hasFile('photo')) {
            $data['photo'] = $request->file('photo')->store('staff/' . $this->school->id, 's3');
        }

        StaffMember::create($data);

        return redirect("/school-admin/{$this->school->id}/staff")->with('success', 'Staff member added.');
    }

    public function edit(string $tenantId, StaffMember $staff)
    {
        abort_if($staff->tenant_id !== $this->school->id, 403);
        return $this->inertia('School/Staff/Edit', compact('staff'));
    }

    public function update(Request $request, string $tenantId, StaffMember $staff)
    {
        abort_if($staff->tenant_id !== $this->school->id, 403);

        $data = $request->validate([
            'name'          => 'required|string|max:255',
            'designation'   => 'required|string|max:255',
            'department'    => 'nullable|string|max:255',
            'qualification' => 'nullable|string|max:255',
            'type'          => 'required|in:teaching,non-teaching,admin',
            'display_order' => 'integer',
            'is_active'     => 'boolean',
            'photo'         => 'nullable|image|max:2048',
        ]);

        if ($request->hasFile('photo')) {
            $data['photo'] = $request->file('photo')->store('staff/' . $this->school->id, 's3');
        }

        $staff->update($data);

        return redirect("/school-admin/{$this->school->id}/staff")->with('success', 'Staff member updated.');
    }

    public function destroy(string $tenantId, StaffMember $staff)
    {
        abort_if($staff->tenant_id !== $this->school->id, 403);
        $staff->delete();
        return back()->with('success', 'Staff member removed.');
    }
}
