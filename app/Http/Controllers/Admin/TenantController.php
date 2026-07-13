<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SiteSection;
use App\Models\Tenant;
use App\Models\TenantSetting;
use App\Models\User;
use App\Support\TenantAuth;
use App\Services\Membership\MembershipNotifier;
use App\Services\Tenancy\SahodayaDatabaseProvisioner;
use App\Support\SahodayaNavVisibility;
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

    public function indexSahodayas(Request $request)
    {
        $readOnly = $request->user()?->hasAnyRole(['state_admin', 'state_staff'])
            && ! $request->user()?->hasRole('superadmin');

        return $this->tenantIndex(
            'sahodaya',
            'Sahodaya Clusters',
            $readOnly ? null : route('admin.sahodayas.create'),
            $request,
            $readOnly,
        );
    }

    public function indexSchools(Request $request)
    {
        return $this->tenantIndex('school', 'Member Schools', route('admin.schools.create'), $request);
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
            'db_username'   => 'nullable|string|max:120',
            'db_password'   => 'nullable|string|max:255',
        ]));

        $databaseName = $validated['database_name'] ?? null;
        $dbUsername = $validated['db_username'] ?? null;
        $dbPassword = $validated['db_password'] ?? null;
        unset($validated['database_name'], $validated['db_username'], $validated['db_password']);

        $tenant = Tenant::create(array_merge($validated, ['id' => \Str::uuid()]));

        if ($tenant->type === 'sahodaya' && config('tenancy.database_per_sahodaya', true)) {
            if (filled($databaseName) || filled($dbUsername) || filled($dbPassword)) {
                $databaseProvisioner->configure(
                    $tenant,
                    $databaseName ?: $databaseProvisioner->suggestedName($tenant),
                    $dbUsername,
                    $dbPassword,
                );
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

        $databaseReady = false;

        if ($tenant->type === 'sahodaya' && config('tenancy.database_per_sahodaya', true)) {
            $database = array_merge(
                $databaseProvisioner->status($tenant),
                ['suggested_name' => $databaseProvisioner->suggestedName($tenant)]
            );
            $databaseReady = (bool) ($database['ready'] ?? false);

            if ($databaseReady) {
                $tenantOverview = $this->tenantOverview($tenant);
            }
        } elseif ($tenant->type === 'school' && $tenant->parent_id && config('tenancy.database_per_sahodaya', true)) {
            $parent = Tenant::query()->find($tenant->parent_id);
            if ($parent && $databaseProvisioner->status($parent)['ready']) {
                $databaseReady = true;
                $tenantOverview = $this->tenantOverview($tenant);
            }
        } elseif (! config('tenancy.database_per_sahodaya', true)) {
            $databaseReady = true;
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
            'sahodayaAdmins'   => ($tenant->type === 'sahodaya' && $databaseReady)
                ? $this->portalAdmins($tenant, 'sahodaya_admin')
                : [],
            'schoolAdmins'     => ($tenant->type === 'school' && $databaseReady)
                ? $this->portalAdmins($tenant, 'school_admin')
                : [],
            'loginUrl'         => $this->portalLoginUrl($tenant),
            'navManager'       => $tenant->type === 'sahodaya' ? [
                'programs'  => SahodayaNavVisibility::programLabels(),
                'menus'     => SahodayaNavVisibility::menuLabels(),
                'overrides' => SahodayaNavVisibility::applyOverride(
                    SahodayaNavVisibility::defaults(),
                    is_array($tenant->nav_overrides) ? $tenant->nav_overrides : null,
                ),
            ] : null,
        ]);
    }

    /** Super-admin hard cap on a Sahodaya's sidebar menus/programs (stored centrally). */
    public function updateNavVisibility(Request $request, Tenant $tenant)
    {
        abort_unless($tenant->type === 'sahodaya', 422, 'Menu control is only available for Sahodayas.');

        $request->validate([
            'programs'   => 'array',
            'programs.*' => 'boolean',
            'menus'      => 'array',
            'menus.*'    => 'boolean',
        ]);

        $tenant->update([
            'nav_overrides' => SahodayaNavVisibility::normalizeInput([
                'programs' => $request->input('programs', []),
                'menus'    => $request->input('menus', []),
            ]),
        ]);

        return back()->with('success', 'Sidebar menu access updated.');
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
        $name = $tenant->name;

        // Portal users live in the Sahodaya DB. Skip cleanup when that DB was never
        // created / migrated so the central tenant row can still be deleted.
        TenancyDatabase::whenDatabaseReady($tenant, function () use ($tenant) {
            if (! \Illuminate\Support\Facades\Schema::hasTable('users')) {
                return;
            }

            User::query()->where('tenant_id', $tenant->id)->each(function (User $user) {
                try {
                    if (\Illuminate\Support\Facades\Schema::hasTable('roles')) {
                        $user->syncRoles([]);
                    }
                } catch (\Throwable) {
                    // Role tables may be incomplete on a half-migrated DB.
                }
                $user->delete();
            });
        });

        $tenant->delete();

        $route = $type === 'sahodaya' ? 'admin.sahodayas.index' : 'admin.schools.index';

        return redirect()->route($route)->with('success', "Tenant \"{$name}\" deleted.");
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
            'database_name'       => $this->databaseNameRules(),
            'db_username'         => 'nullable|string|max:120',
            'db_password'         => 'nullable|string|max:255',
            'clear_db_password'   => 'nullable|boolean',
            'admin_name'          => 'nullable|string|max:255',
            'admin_email'         => 'nullable|email|max:255',
            'admin_password'      => 'nullable|string|min:8|max:255|required_with:admin_email',
        ]);

        $databaseProvisioner->configure(
            $tenant,
            $data['database_name'],
            $data['db_username'] ?? null,
            $data['db_password'] ?? null,
            (bool) ($data['clear_db_password'] ?? false),
        );

        $request->session()->put("tenant.{$tenant->id}.pending_admin", array_filter([
            'name'     => $data['admin_name'] ?? null,
            'email'    => $data['admin_email'] ?? null,
            'password' => $data['admin_password'] ?? null,
        ]));

        return redirect()->route('admin.tenants.show', $tenant)->with('success', 'Database connection saved.');
    }

    public function migrateDatabase(Request $request, Tenant $tenant, SahodayaDatabaseProvisioner $databaseProvisioner)
    {
        abort_if($tenant->type !== 'sahodaya', 404);

        try {
            $databaseProvisioner->migrate($tenant, (bool) $request->boolean('seed'));

            $pending = $request->session()->pull("tenant.{$tenant->id}.pending_admin", []);
            if (! empty($pending['email']) && ! empty($pending['password'])) {
                $this->createInitialSahodayaAdmin($tenant, [
                    'name'     => $pending['name'] ?: 'Sahodaya Admin',
                    'email'    => $pending['email'],
                    'password' => $pending['password'],
                ]);
            } elseif ($request->filled('admin_email') && $request->filled('admin_password')) {
                $data = $request->validate([
                    'admin_name'     => 'nullable|string|max:255',
                    'admin_email'    => 'required|email|max:255',
                    'admin_password' => 'required|string|min:8|max:255',
                ]);
                $this->createInitialSahodayaAdmin($tenant, [
                    'name'     => $data['admin_name'] ?: 'Sahodaya Admin',
                    'email'    => $data['admin_email'],
                    'password' => $data['admin_password'],
                ]);
            }
        } catch (\Throwable $e) {
            return redirect()->route('admin.tenants.show', $tenant)->with('error', $e->getMessage());
        }

        return redirect()->route('admin.tenants.show', $tenant)->with('success', 'Sahodaya database migrations completed.');
    }

    /** @param  array{name: string, email: string, password: string}  $data */
    private function createInitialSahodayaAdmin(Tenant $tenant, array $data): void
    {
        TenantAuth::withTenantUsers($tenant, function () use ($tenant, $data) {
            if (! \Illuminate\Support\Facades\Schema::hasTable('users')
                || ! \Illuminate\Support\Facades\Schema::hasTable('roles')) {
                return;
            }

            $user = User::query()
                ->where('tenant_id', $tenant->id)
                ->where('email', $data['email'])
                ->first() ?? new User(['tenant_id' => $tenant->id]);

            $user->fill([
                'name'              => $data['name'],
                'email'             => $data['email'],
                'password'          => $data['password'],
                'email_verified_at' => now(),
            ]);
            $user->password_changed_at = now();
            $user->save();
            $user->syncRoles(['sahodaya_admin']);
        });
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

    public function destroySahodayaAdmin(Tenant $tenant, int $user)
    {
        return $this->destroyPortalAdmin($tenant, $user, 'sahodaya', 'sahodaya_admin');
    }

    public function destroySchoolAdmin(Tenant $tenant, int $user)
    {
        return $this->destroyPortalAdmin($tenant, $user, 'school', 'school_admin');
    }

    private function savePortalAdmin(Request $request, Tenant $tenant, string $role): \Illuminate\Http\RedirectResponse
    {
        return TenantAuth::withTenantUsers($tenant, function () use ($request, $tenant, $role) {
            $existingId = $request->input('user_id');
            $label = $role === 'sahodaya_admin' ? 'Sahodaya admin' : 'School admin';

            $data = $request->validate([
                'user_id'  => ['nullable', 'integer', Rule::exists('users', 'id')->where('tenant_id', $tenant->id)],
                'name'     => ['required', 'string', 'max:255'],
                'email'    => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($existingId)],
                'password' => [$existingId ? 'nullable' : 'required', 'string', 'min:8'],
            ]);

            $user = $existingId
                ? User::query()->where('tenant_id', $tenant->id)->findOrFail($existingId)
                : new User(['tenant_id' => $tenant->id]);

            $payload = [
                'name'              => $data['name'],
                'email'             => $data['email'],
                'email_verified_at' => now(),
            ];
            if (! empty($data['password'])) {
                // User::$casts hashes password — do not Hash::make here.
                $payload['password'] = $data['password'];
                $user->password_changed_at = now();
            }

            $user->fill($payload);
            $user->save();
            $user->syncRoles([$role]);

            $message = $existingId ? "{$label} login updated." : "{$label} account created.";

            return redirect()->route('admin.tenants.show', $tenant)->with('success', $message);
        });
    }

    private function destroyPortalAdmin(Tenant $tenant, int $userId, string $tenantType, string $role): \Illuminate\Http\RedirectResponse
    {
        abort_if($tenant->type !== $tenantType, 404);

        return TenantAuth::withTenantUsers($tenant, function () use ($tenant, $userId, $role) {
            $user = User::query()->where('tenant_id', $tenant->id)->findOrFail($userId);
            abort_if(! $user->hasRole($role), 404);

            $user->delete();

            $label = $role === 'sahodaya_admin' ? 'Sahodaya admin' : 'School admin';

            return redirect()->route('admin.tenants.show', $tenant)->with('success', "{$label} removed.");
        });
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

    private function tenantIndex(string $type, string $pageTitle, ?string $createUrl, Request $request, bool $readOnly = false)
    {
        $filters = $request->validate([
            'search' => 'nullable|string|max:100',
            'status' => 'nullable|in:active,inactive,all',
        ]);

        $query = Tenant::query()
            ->where('type', $type)
            ->when(! empty($filters['search']), function ($q) use ($filters) {
                $term = '%'.$filters['search'].'%';
                $q->where(function ($inner) use ($term) {
                    $inner->where('name', 'like', $term)
                        ->orWhere('domain', 'like', $term)
                        ->orWhere('subdomain', 'like', $term);
                });
            })
            ->when(($filters['status'] ?? 'all') === 'active', fn ($q) => $q->where('is_active', true))
            ->when(($filters['status'] ?? 'all') === 'inactive', fn ($q) => $q->where('is_active', false))
            ->orderBy('name');

        if ($type === 'school') {
            $query->with('parent:id,name');
        } else {
            $query->withCount('children');
        }

        return inertia('Tenants/Index', [
            'tenants'          => $query->paginate(20)->withQueryString(),
            'tenantType'       => $type,
            'pageTitle'        => $pageTitle,
            'createUrl'        => $createUrl,
            'readOnly'         => $readOnly,
            'tenantBaseDomain' => config('tenancy.tenant_base_domain'),
            'filters'          => array_merge(['search' => '', 'status' => 'all'], $filters),
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

    /** @return list<array{id: int, name: string, email: string, created_at: ?string}> */
    private function portalAdmins(Tenant $tenant, string $role): array
    {
        try {
            return TenantAuth::withTenantUsers($tenant, function () use ($tenant, $role) {
                if (! \Illuminate\Support\Facades\Schema::hasTable('roles')
                    || ! \Illuminate\Support\Facades\Schema::hasTable('users')) {
                    return [];
                }

                return User::role($role)
                    ->where('tenant_id', $tenant->id)
                    ->orderBy('name')
                    ->get(['id', 'name', 'email', 'created_at'])
                    ->map(fn (User $user) => [
                        'id'         => $user->id,
                        'name'       => $user->name,
                        'email'      => $user->email,
                        'created_at' => $user->created_at?->toIso8601String(),
                    ])
                    ->all();
            }) ?? [];
        } catch (\Throwable) {
            return [];
        }
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
