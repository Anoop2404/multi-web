<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\Http\Request;

class TenantController extends Controller
{
    public function index()
    {
        $tenants = Tenant::with('children')
            ->orderBy('type')
            ->orderBy('name')
            ->paginate(20);

        return inertia('Tenants/Index', compact('tenants'));
    }

    public function create()
    {
        $sahodayas = Tenant::where('type', 'sahodaya')->orderBy('name')->get(['id', 'name']);

        return inertia('Tenants/Create', compact('sahodayas'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'type'      => 'required|in:sahodaya,school',
            'name'      => 'required|string|max:255',
            'domain'    => 'nullable|string|unique:tenants,domain',
            'subdomain' => 'nullable|string|unique:tenants,subdomain',
            'parent_id' => 'nullable|exists:tenants,id',
            'plan'      => 'nullable|string',
        ]);

        $tenant = Tenant::create(array_merge($validated, ['id' => \Str::uuid()]));

        return redirect()->route('admin.tenants.show', $tenant)->with('success', 'Tenant created.');
    }

    public function show(Tenant $tenant)
    {
        $tenant->load('children', 'settings', 'sections');

        return inertia('Tenants/Show', compact('tenant'));
    }

    public function edit(Tenant $tenant)
    {
        $sahodayas = Tenant::where('type', 'sahodaya')->where('id', '!=', $tenant->id)->orderBy('name')->get(['id', 'name']);

        return inertia('Tenants/Edit', compact('tenant', 'sahodayas'));
    }

    public function update(Request $request, Tenant $tenant)
    {
        $validated = $request->validate([
            'name'      => 'required|string|max:255',
            'domain'    => "nullable|string|unique:tenants,domain,{$tenant->id},id",
            'subdomain' => "nullable|string|unique:tenants,subdomain,{$tenant->id},id",
            'parent_id' => 'nullable|exists:tenants,id',
            'plan'      => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $tenant->update($validated);

        return redirect()->route('admin.tenants.show', $tenant)->with('success', 'Tenant updated.');
    }

    public function destroy(Tenant $tenant)
    {
        $tenant->delete();

        return redirect()->route('admin.tenants.index')->with('success', 'Tenant deleted.');
    }
}
