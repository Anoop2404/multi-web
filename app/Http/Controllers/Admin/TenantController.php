<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Services\Tenancy\SahodayaDatabaseProvisioner;
use App\Support\SahodayaSiteTemplate;
use App\Support\TenantBranding;
use App\Support\TenantDomainSync;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TenantController extends Controller
{
    public function index()
    {
        return redirect()->route('admin.sahodayas.index');
    }

    public function indexSahodayas()
    {
        return $this->tenantIndex('sahodaya', 'Sahodaya Clusters', route('admin.sahodayas.create'));
    }

    public function indexSchools()
    {
        return $this->tenantIndex('school', 'Member Schools', route('admin.schools.create'));
    }

    public function create()
    {
        return redirect()->route('admin.sahodayas.create');
    }

    public function createSahodaya()
    {
        return $this->createForm('sahodaya', route('admin.sahodayas.index'));
    }

    public function createSchool()
    {
        return $this->createForm('school', route('admin.schools.index'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate($this->rules());

        $tenant = Tenant::create(array_merge($validated, ['id' => \Str::uuid()]));

        if ($tenant->type === 'sahodaya' && ! config('tenancy.database_per_sahodaya', true)) {
            \App\Models\SahodayaProfile::create([
                'tenant_id'           => $tenant->id,
                'student_data_mode'   => 'not_required',
                'membership_fee_type' => 'fixed',
            ]);
            SahodayaSiteTemplate::apply($tenant);
            $tenant->invalidateCache();
        }

        return redirect()->route('admin.tenants.show', $tenant)->with('success', 'Tenant created.');
    }

    public function show(Tenant $tenant, SahodayaDatabaseProvisioner $databaseProvisioner)
    {
        $tenant->load('children', 'domains');

        $database = null;
        if ($tenant->type === 'sahodaya' && config('tenancy.database_per_sahodaya', true)) {
            $database = array_merge(
                $databaseProvisioner->status($tenant),
                ['suggested_name' => $databaseProvisioner->suggestedName($tenant)]
            );

            if ($database['ready']) {
                $this->loadTenantScopedRelations($tenant);
            }
        } elseif ($tenant->type === 'school' && $tenant->parent_id && config('tenancy.database_per_sahodaya', true)) {
            $parent = Tenant::query()->find($tenant->parent_id);
            if ($parent && $databaseProvisioner->status($parent)['ready']) {
                $this->loadTenantScopedRelations($tenant);
            }
        }

        return inertia('Tenants/Show', [
            'tenant'           => $tenant,
            'tenantBaseDomain' => config('tenancy.tenant_base_domain'),
            'publicUrl'        => TenantDomainSync::publicUrl($tenant),
            'subdomainUrl'     => $tenant->subdomain
                ? 'https://'.TenantDomainSync::subdomainFqdn($tenant->subdomain)
                : null,
            'logoUrl'          => TenantBranding::logoUrl($tenant),
            'listUrl'          => $tenant->type === 'sahodaya'
                ? route('admin.sahodayas.index')
                : route('admin.schools.index'),
            'database'         => $database,
        ]);
    }

    public function edit(Tenant $tenant)
    {
        $sahodayas = Tenant::where('type', 'sahodaya')->where('id', '!=', $tenant->id)->orderBy('name')->get(['id', 'name']);

        return inertia('Tenants/Edit', [
            'tenant'           => $tenant,
            'sahodayas'        => $sahodayas,
            'tenantBaseDomain' => config('tenancy.tenant_base_domain'),
            'cancelUrl'        => $tenant->type === 'sahodaya'
                ? route('admin.sahodayas.index')
                : route('admin.schools.index'),
        ]);
    }

    public function update(Request $request, Tenant $tenant)
    {
        $validated = $request->validate($this->rules($tenant));

        $tenant->update($validated);

        return redirect()->route('admin.tenants.show', $tenant)->with('success', 'Tenant updated.');
    }

    public function destroy(Tenant $tenant)
    {
        $type = $tenant->type;
        $tenant->delete();

        $route = $type === 'sahodaya' ? 'admin.sahodayas.index' : 'admin.schools.index';

        return redirect()->route($route)->with('success', 'Tenant deleted.');
    }

    public function uploadLogo(Request $request, Tenant $tenant)
    {
        $request->validate(['logo' => 'required|image|max:2048']);

        try {
            TenantBranding::storeUpload($tenant, $request->file('logo'));
        } catch (\RuntimeException $e) {
            return redirect()->route('admin.tenants.show', $tenant)->with('error', $e->getMessage());
        }

        return redirect()->route('admin.tenants.show', $tenant)->with('success', 'Logo updated.');
    }

    public function saveDatabase(Request $request, Tenant $tenant, SahodayaDatabaseProvisioner $databaseProvisioner)
    {
        abort_if($tenant->type !== 'sahodaya', 404);

        $data = $request->validate([
            'database_name' => ['required', 'string', 'max:63', 'regex:/^[a-z][a-z0-9_]*$/'],
        ]);

        $databaseProvisioner->configure($tenant, $data['database_name']);

        return redirect()->route('admin.tenants.show', $tenant)->with('success', 'Database name saved.');
    }

    public function migrateDatabase(Request $request, Tenant $tenant, SahodayaDatabaseProvisioner $databaseProvisioner)
    {
        abort_if($tenant->type !== 'sahodaya', 404);

        try {
            $databaseProvisioner->migrate($tenant, (bool) $request->boolean('seed'));
        } catch (\Throwable $e) {
            return redirect()->route('admin.tenants.show', $tenant)->with('error', $e->getMessage());
        }

        return redirect()->route('admin.tenants.show', $tenant)->with('success', 'Sahodaya database migrations completed.');
    }

    /** @return array<string, mixed> */
    private function rules(?Tenant $tenant = null): array
    {
        return [
            'type'      => $tenant ? 'sometimes' : 'required|in:sahodaya,school',
            'name'      => 'required|string|max:255',
            'domain'    => [
                'nullable', 'string', 'max:255',
                Rule::unique('tenants', 'domain')->ignore($tenant?->id),
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if ($value && TenantDomainSync::isCentralHost((string) $value)) {
                        $fail('This domain is reserved for the admin panel.');
                    }
                },
            ],
            'subdomain' => [
                'nullable', 'string', 'max:63', 'alpha_dash',
                Rule::unique('tenants', 'subdomain')->ignore($tenant?->id),
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if ($value && TenantDomainSync::isReservedSubdomain((string) $value)) {
                        $fail('This subdomain is reserved.');
                    }
                },
            ],
            'parent_id' => [
                Rule::requiredIf(fn () => ! $tenant && request('type') === 'school'),
                'nullable', 'exists:tenants,id',
            ],
            'plan'      => 'nullable|string',
            'is_active' => $tenant ? 'boolean' : 'sometimes',
        ];
    }

    private function tenantIndex(string $type, string $pageTitle, string $createUrl)
    {
        $query = Tenant::query()
            ->where('type', $type)
            ->orderBy('name');

        if ($type === 'school') {
            $query->with('parent:id,name');
        } else {
            $query->withCount('children');
        }

        return inertia('Tenants/Index', [
            'tenants'          => $query->paginate(20),
            'tenantType'       => $type,
            'pageTitle'        => $pageTitle,
            'createUrl'        => $createUrl,
            'tenantBaseDomain' => config('tenancy.tenant_base_domain'),
        ]);
    }

    private function createForm(string $type, string $cancelUrl)
    {
        $sahodayas = Tenant::where('type', 'sahodaya')->orderBy('name')->get(['id', 'name']);

        return inertia('Tenants/Create', [
            'sahodayas'        => $sahodayas,
            'tenantBaseDomain' => config('tenancy.tenant_base_domain'),
            'defaultType'      => $type,
            'cancelUrl'        => $cancelUrl,
        ]);
    }

    private function loadTenantScopedRelations(Tenant $tenant): void
    {
        try {
            $tenant->run(function () use ($tenant) {
                $tenant->load(['settings', 'sections']);
            });
        } catch (\Throwable) {
            // Database not ready yet — overview cards stay empty on superadmin show page.
        }
    }
}
