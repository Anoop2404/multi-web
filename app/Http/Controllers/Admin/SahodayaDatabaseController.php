<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Services\Audit\PlatformAuditLogger;
use App\Services\Tenancy\SahodayaDatabaseProvisioner;
use App\Services\Tenancy\TenantProvisioningChecklistService;
use App\Support\TenancyDatabase;
use Illuminate\Http\Request;
use Throwable;

/**
 * Creating and migrating the dedicated database behind each Sahodaya.
 *
 * The per-tenant screen has been able to configure and migrate one database for a while, and
 * `sahodaya:provision-databases` does the whole platform from a shell. Neither answers the question
 * this page exists for: after promoting twenty Sahodayas from the State's roster, *which of them have
 * a working database and which do not*. That was only visible by opening twenty tenants one at a time.
 *
 * Superadmin only. Creating a Postgres database is not something a State office should be able to do
 * from a list page, and it is not reversible from here.
 */
class SahodayaDatabaseController extends Controller
{
    /**
     * Provisioning more than this in one request is a shell job, not a web request.
     *
     * Each database is created, migrated and seeded synchronously; twenty is already a slow request,
     * and silently doing eighty would time out halfway with no record of where it stopped.
     */
    private const BULK_LIMIT = 20;

    public function index(Request $request, SahodayaDatabaseProvisioner $provisioner)
    {
        $enabled = TenancyDatabase::enabled();

        $sahodayas = Tenant::query()->where('type', 'sahodaya')->orderBy('name')
            // Whole rows, no column list. A tenant keeps its database name in Stancl's custom/`data`
            // columns, so a partial select silently returns a tenant with no db_name and every
            // Sahodaya reports "not configured" — and omitting `type` makes the provisioner throw
            // "Only Sahodaya tenants use a dedicated database" for all of them instead.
            ->get()
            ->map(function (Tenant $tenant) use ($provisioner) {
                // Always asked, including when dedicated databases are off: the provisioner answers
                // "ready, nothing to do" in that case, and special-casing it here is what made every
                // Sahodaya count as missing a database on a shared-database installation.
                //
                // Each check asks Postgres whether the database exists, so this is a few queries per
                // Sahodaya rather than one cheap list — acceptable for a page opened deliberately, and
                // the only way to report the truth rather than what the tenant row claims.
                $status = $this->statusFor($provisioner, $tenant);

                return [
                    'id' => $tenant->id,
                    'name' => $tenant->name,
                    'subdomain' => $tenant->subdomain,
                    'is_active' => (bool) $tenant->is_active,
                    'schools' => Tenant::where('parent_id', $tenant->id)->count(),
                    'status' => $status,
                    'href' => route('admin.tenants.show', $tenant),
                ];
            });

        // Keyed off `ready` rather than `exists`: on a shared-database installation the provisioner
        // reports ready with no database name at all, and counting by `exists` made every Sahodaya
        // look like it was missing one.
        $notReady = $sahodayas->reject(fn ($s) => $s['status']['ready'] ?? false);

        $counts = [
            'total' => $sahodayas->count(),
            'ready' => $sahodayas->count() - $notReady->count(),
            'missing' => $notReady->reject(fn ($s) => $s['status']['exists'] ?? false)->count(),
            'unmigrated' => $notReady->filter(fn ($s) => $s['status']['exists'] ?? false)->count(),
            'failed' => $sahodayas->filter(fn ($s) => filled($s['status']['error'] ?? null))->count(),
        ];

        return inertia('Tenants/Databases', [
            'enabled' => $enabled,
            'sahodayas' => $sahodayas,
            'counts' => $counts,
            'bulkLimit' => self::BULK_LIMIT,
            'actionUrls' => [
                'index' => route('admin.sahodayas.databases.index'),
                'provision' => route('admin.sahodayas.databases.provision'),
                'provisionAll' => route('admin.sahodayas.databases.provision-all'),
            ],
        ]);
    }

    /**
     * Create, migrate and seed one Sahodaya's database.
     *
     * The id arrives in the body rather than the path: a tenant id in the URL is picked up by
     * path-based tenant resolution, which bounces a school id to the login page instead of letting
     * this answer the 404 it should.
     */
    public function provision(
        Request $request,
        SahodayaDatabaseProvisioner $provisioner,
        PlatformAuditLogger $audit,
        TenantProvisioningChecklistService $checklist,
    ) {
        $data = $request->validate([
            'sahodaya_id' => 'required|string|max:191',
            'seed' => 'nullable|boolean',
        ]);

        $tenant = Tenant::query()->where('type', 'sahodaya')->find($data['sahodaya_id']);

        abort_if(! $tenant, 404, 'No such Sahodaya. A school shares its parent\'s database and has none of its own.');
        abort_unless(TenancyDatabase::enabled(), 422, 'Dedicated Sahodaya databases are disabled on this installation (TENANCY_DATABASE_PER_SAHODAYA), so there is nothing to create.');

        try {
            $provisioner->ensureReady($tenant, seedDefaults: (bool) ($data['seed'] ?? true), createIfMissing: true);
        } catch (Throwable $e) {
            // Reported rather than thrown: a failure here is usually a Postgres permission or
            // connection problem, and the operator needs the message, not a 500 page.
            return back()->with('error', "{$tenant->name}: {$e->getMessage()}");
        }

        $audit->tenantDatabaseMigrated($tenant);
        $checklist->markComplete($tenant, 'database_configured', $request->user()->id);
        $checklist->markComplete($tenant, 'database_migrated', $request->user()->id);

        return back()->with('success', "{$tenant->name}: database created, migrated and seeded.");
    }

    /**
     * Provision every Sahodaya that has no working database.
     *
     * One at a time, continuing past a failure and reporting each — because the common case is one
     * Sahodaya with a bad credential among twenty that are fine, and aborting the batch on the first
     * would leave the operator to work out how far it got.
     */
    public function provisionAll(
        Request $request,
        SahodayaDatabaseProvisioner $provisioner,
        PlatformAuditLogger $audit,
        TenantProvisioningChecklistService $checklist,
    ) {
        abort_unless(TenancyDatabase::enabled(), 422, 'Dedicated Sahodaya databases are disabled on this installation.');

        $data = $request->validate(['seed' => 'nullable|boolean']);
        $seed = (bool) ($data['seed'] ?? true);

        $pending = Tenant::query()->where('type', 'sahodaya')->orderBy('name')->get()
            ->reject(fn (Tenant $t) => $this->statusFor($provisioner, $t)['ready'] ?? false)
            ->values();

        if ($pending->isEmpty()) {
            return back()->with('success', 'Every Sahodaya already has a migrated database.');
        }

        $batch = $pending->take(self::BULK_LIMIT);
        $done = [];
        $failed = [];

        foreach ($batch as $tenant) {
            try {
                $provisioner->ensureReady($tenant, seedDefaults: $seed, createIfMissing: true);
                $audit->tenantDatabaseMigrated($tenant);
                $checklist->markComplete($tenant, 'database_configured', $request->user()->id);
                $checklist->markComplete($tenant, 'database_migrated', $request->user()->id);
                $done[] = $tenant->name;
            } catch (Throwable $e) {
                $failed[] = "{$tenant->name}: {$e->getMessage()}";
            }
        }

        $remaining = $pending->count() - $batch->count();

        $message = count($done).' database(s) created and migrated.';
        if ($remaining > 0) {
            $message .= " {$remaining} still pending — run it again, or use: php artisan sahodaya:provision-databases --create".($seed ? ' --seed' : '').'.';
        }
        if ($failed !== []) {
            $message .= ' Failed: '.implode(' | ', $failed);
        }

        return back()->with($failed === [] ? 'success' : 'warning', $message);
    }

    /**
     * The provisioner's status, with a connection failure turned into a reportable state.
     *
     * status() reaches Postgres, so a wrong credential throws — and one unreachable Sahodaya must not
     * take down the whole page, which is exactly the page you open when something is wrong.
     *
     * @return array<string, mixed>
     */
    private function statusFor(SahodayaDatabaseProvisioner $provisioner, Tenant $tenant): array
    {
        try {
            return $provisioner->status($tenant) + ['error' => null];
        } catch (Throwable $e) {
            return [
                'configured' => filled($tenant->getInternal('db_name')),
                'name' => $tenant->getInternal('db_name') ?: null,
                'exists' => false,
                'ready' => false,
                'username' => $tenant->getInternal('db_username') ?: null,
                'has_password' => filled($tenant->getInternal('db_password')),
                'error' => $e->getMessage(),
            ];
        }
    }
}
