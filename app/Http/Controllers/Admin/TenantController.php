<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SiteSection;
use App\Models\Tenant;
use App\Models\TenantSetting;
use App\Models\User;
use App\Services\Membership\MembershipNotifier;
use App\Services\Tenancy\SahodayaDatabaseProvisioner;
use App\Support\SahodayaSiteTemplate;
use App\Support\TenancyDatabase;
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

    public function store(Request $request, SahodayaDatabaseProvisioner $databaseProvisioner)
    {
        $validated = $request->validate(array_merge($this->rules(), [
            'database_name' => $this->databaseNameRules($request->input('type') === 'sahodaya'),
        ]));

        $databaseName = $validated['database_name'] ?? null;
        unset($validated['database_name']);

        $tenant = Tenant::create(array_merge($validated, ['id' => \Str::uuid()]));

        if ($tenant->type === 'sahodaya' && config('tenancy.database_per_sahodaya', true)) {
            if (filled($databaseName)) {
                $databaseProvisioner->configure($tenant, $databaseName);
            }
        } elseif ($tenant->type === 'sahodaya' && ! config('tenancy.database_per_sahodaya', true)) {
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
        $tenantOverview = ['sections' => [], 'settings' => []];

        if ($tenant->type === 'sahodaya' && config('tenancy.database_per_sahodaya', true)) {
            $database = array_merge(
                $databaseProvisioner->status($tenant),
                ['suggested_name' => $databaseProvisioner->suggestedName($tenant)]
            );

            if ($database['ready']) {
                $tenantOverview = $this->tenantOverview($tenant);
            }
        } elseif ($tenant->type === 'school' && $tenant->parent_id && config('tenancy.database_per_sahodaya', true)) {
            $parent = Tenant::query()->find($tenant->parent_id);
            if ($parent && $databaseProvisioner->status($parent)['ready']) {
                $tenantOverview = $this->tenantOverview($tenant);
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
            'tenantOverview'   => $tenantOverview,
            'sahodayaAdmins'   => $tenant->type === 'sahodaya' ? $this->portalAdmins($tenant, 'sahodaya_admin') : [],
            'schoolAdmins'     => $tenant->type === 'school' ? $this->portalAdmins($tenant, 'school_admin') : [],
            'loginUrl'         => $this->portalLoginUrl($tenant),
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
        abort_if($tenant->type === 'sahodaya' && $tenant->children()->exists(), 422, 'Remove member schools before deleting this Sahodaya.');

        $type = $tenant->type;

        User::query()->where('tenant_id', $tenant->id)->each(function (User $user) {
            $user->syncRoles([]);
            $user->delete();
        });

        $tenant->delete();

        $route = $type === 'sahodaya' ? 'admin.sahodayas.index' : 'admin.schools.index';

        return redirect()->route($route)->with('success', 'Tenant deleted.');
    }

    public function rejectMembership(Request $request, Tenant $tenant, MembershipNotifier $notifier)
    {
        abort_if($tenant->type !== 'school', 404);

        $data = $request->validate([
            'reason' => 'required|string|max:1000',
        ]);

        $tenant->update([
            'membership_status'   => 'rejected',
            'is_active'           => false,
            'application_payload' => array_merge($tenant->application_payload ?? [], [
                'rejection_reason' => $data['reason'],
                'rejected_at'      => now()->toIso8601String(),
                'rejected_by'      => 'superadmin',
            ]),
        ]);

        $notifier->schoolRejected($tenant, $data['reason']);

        return redirect()->route('admin.tenants.show', $tenant)->with('success', 'School marked as rejected.');
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
            'database_name' => $this->databaseNameRules(),
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

    public function saveSahodayaAdmin(Request $request, Tenant $tenant)
    {
        abort_if($tenant->type !== 'sahodaya', 404);

        return $this->savePortalAdmin($request, $tenant, 'sahodaya_admin');
    }

    public function saveSchoolAdmin(Request $request, Tenant $tenant)
    {
        abort_if($tenant->type !== 'school', 404);

        return $this->savePortalAdmin($request, $tenant, 'school_admin');
    }

    public function destroySahodayaAdmin(Tenant $tenant, User $user)
    {
        return $this->destroyPortalAdmin($tenant, $user, 'sahodaya', 'sahodaya_admin');
    }

    public function destroySchoolAdmin(Tenant $tenant, User $user)
    {
        return $this->destroyPortalAdmin($tenant, $user, 'school', 'school_admin');
    }

    private function savePortalAdmin(Request $request, Tenant $tenant, string $role): \Illuminate\Http\RedirectResponse
    {
        $existingId = $request->input('user_id');
        $label = $role === 'sahodaya_admin' ? 'Sahodaya admin' : 'School admin';

        $data = $request->validate([
            'user_id'  => ['nullable', 'integer', Rule::exists('users', 'id')->where('tenant_id', $tenant->id)],
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($existingId)],
            'password' => ['required', 'string', 'min:8'],
        ]);

        $user = $existingId
            ? User::query()->where('tenant_id', $tenant->id)->findOrFail($existingId)
            : new User(['tenant_id' => $tenant->id]);

        $user->fill([
            'name'              => $data['name'],
            'email'             => $data['email'],
            'password'          => $data['password'],
            'plain_password'    => $data['password'],
            'email_verified_at' => now(),
        ]);
        $user->save();
        $user->syncRoles([$role]);

        $message = $existingId ? "{$label} login updated." : "{$label} account created.";

        return redirect()->route('admin.tenants.show', $tenant)->with('success', $message);
    }

    private function destroyPortalAdmin(Tenant $tenant, User $user, string $tenantType, string $role): \Illuminate\Http\RedirectResponse
    {
        abort_if($tenant->type !== $tenantType, 404);
        abort_if($user->tenant_id !== $tenant->id || ! $user->hasRole($role), 404);

        $user->delete();

        $label = $role === 'sahodaya_admin' ? 'Sahodaya admin' : 'School admin';

        return redirect()->route('admin.tenants.show', $tenant)->with('success', "{$label} removed.");
    }

    /** @return array<string, mixed> */
    private function databaseNameRules(bool $required = true): array
    {
        $rules = ['string', 'max:63', 'regex:/^[a-z][a-z0-9_]*$/'];

        return $required
            ? array_merge(['required'], $rules)
            : array_merge(['nullable'], $rules);
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

    /** @return array{sections: array<int, array<string, mixed>>, settings: array<int, array<string, mixed>>} */
    private function tenantOverview(Tenant $tenant): array
    {
        try {
            return TenancyDatabase::whenDatabaseReady($tenant, function () use ($tenant) {
                return [
                    'sections' => SiteSection::query()
                        ->where('tenant_id', $tenant->id)
                        ->orderBy('display_order')
                        ->get(['id', 'section_type', 'variant', 'is_active'])
                        ->all(),
                    'settings' => TenantSetting::query()
                        ->where('tenant_id', $tenant->id)
                        ->orderBy('key')
                        ->get(['id', 'key'])
                        ->all(),
                ];
            }, ['sections' => [], 'settings' => []]) ?? ['sections' => [], 'settings' => []];
        } catch (\Throwable) {
            return ['sections' => [], 'settings' => []];
        }
    }

    /** @return list<array{id: int, name: string, email: string, plain_password: ?string, created_at: ?string}> */
    private function portalAdmins(Tenant $tenant, string $role): array
    {
        return User::role($role)
            ->where('tenant_id', $tenant->id)
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'plain_password', 'created_at'])
            ->map(fn (User $user) => [
                'id'             => $user->id,
                'name'           => $user->name,
                'email'          => $user->email,
                'plain_password' => $user->plain_password,
                'created_at'     => $user->created_at?->toIso8601String(),
            ])
            ->all();
    }

    private function portalLoginUrl(Tenant $tenant): ?string
    {
        $portalTenant = $tenant;

        if ($tenant->type === 'school' && $tenant->parent_id) {
            $portalTenant = Tenant::query()->find($tenant->parent_id) ?? $tenant;
        }

        $base = TenantDomainSync::publicUrl($portalTenant)
            ?? ($portalTenant->subdomain ? 'https://'.TenantDomainSync::subdomainFqdn($portalTenant->subdomain) : null);

        return $base ? rtrim($base, '/').'/login' : null;
    }
}
