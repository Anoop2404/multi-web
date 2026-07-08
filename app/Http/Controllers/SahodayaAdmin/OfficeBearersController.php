<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Support\PersistDefaults;
use App\Support\TenantStorage;
use App\Models\OfficeBearers;
use Illuminate\Http\Request;

class OfficeBearersController extends SahodayaAdminController
{
    public function index()
    {
        $bearers = OfficeBearers::where('tenant_id', $this->sahodaya->id)
            ->orderBy('display_order')
            ->get();

        return $this->inertia('Sahodaya/OfficeBearers/Index', compact('bearers'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'          => 'required|string|max:255',
            'role'          => 'required|string|max:255',
            'school_name'   => 'nullable|string|max:255',
            'phone'         => 'nullable|string|max:30',
            'email'         => 'nullable|email|max:255',
            'term_from'     => 'nullable|digits:4|integer',
            'term_to'       => 'nullable|digits:4|integer',
            'display_order' => 'nullable|integer',
            'photo'         => 'nullable|image|max:4096',
        ]);

        $data['tenant_id'] = $this->sahodaya->id;
        $data['is_active'] = true;
        $data = PersistDefaults::coalesce($data, ['display_order' => 0]);

        if ($request->hasFile('photo')) {
            $data['photo'] = $request->file('photo')->store('sahodaya/' . $this->sahodaya->id . '/bearers', \App\Support\TenantStorage::uploadDisk());
        }

        OfficeBearers::create($data);
        return back()->with('success', 'Office bearer added.');
    }

    public function update(Request $request, string $tenantId, OfficeBearers $bearer)
    {
        abort_if($bearer->tenant_id !== $this->sahodaya->id, 403);

        $data = $request->validate([
            'name'          => 'required|string|max:255',
            'role'          => 'required|string|max:255',
            'school_name'   => 'nullable|string|max:255',
            'phone'         => 'nullable|string|max:30',
            'email'         => 'nullable|email|max:255',
            'term_from'     => 'nullable|digits:4|integer',
            'term_to'       => 'nullable|digits:4|integer',
            'display_order' => 'nullable|integer',
            'is_active'     => 'boolean',
            'photo'         => 'nullable|image|max:4096',
        ]);

        if ($request->hasFile('photo')) {
            $data['photo'] = $request->file('photo')->store('sahodaya/' . $this->sahodaya->id . '/bearers', \App\Support\TenantStorage::uploadDisk());
        }

        $data = PersistDefaults::coalesce($data, ['display_order' => $bearer->display_order ?? 0]);

        $bearer->update($data);
        return back()->with('success', 'Office bearer updated.');
    }

    public function destroy(string $tenantId, OfficeBearers $bearer)
    {
        abort_if($bearer->tenant_id !== $this->sahodaya->id, 403);
        $bearer->delete();
        return back()->with('success', 'Office bearer removed.');
    }
}
