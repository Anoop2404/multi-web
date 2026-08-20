<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Models\User;
use App\Services\Tenancy\SahodayaDatabaseProvisioner;
use App\Support\TenancyDatabase;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/**
 * Read-only audit. Never writes.
 *
 * Cross-checks the central `tenants` table against each Sahodaya's isolated
 * `users` table (see App\Support\TenancyDatabase / database_per_sahodaya) to find:
 *
 *  - no_login:         a school tenant with zero school_admin users in its Sahodaya
 *                       database (or the central connection, for a standalone school).
 *  - not_ready:        a school whose Sahodaya database isn't provisioned/migrated yet,
 *                       so login state can't be determined either way.
 *  - orphaned_login:   a school_admin user whose tenant_id no longer matches ANY row in
 *                       the central `tenants` table — the school tenant was deleted (or
 *                       never existed) but the login survived. This is invisible on the
 *                       Member Schools list (which is driven by `tenants` rows) and
 *                       invisible on any tenant's "School admin login" panel (which is
 *                       scoped by tenant_id) — findable only via a raw scan like this one.
 *  - misplaced_login:  a school_admin user whose tenant_id matches a real school, but
 *                       that school's current parent_id points at a *different* Sahodaya
 *                       than the database the login was found in (the school was
 *                       re-parented after the login was created). The "Find an existing
 *                       login" search on the tenant page only searches the current
 *                       Sahodaya's database, so it will never surface this row.
 */
class AuditSchoolLogins extends Command
{
    protected $signature = 'schools:audit-logins
        {--sahodaya= : Limit to one Sahodaya tenant id or subdomain}
        {--format=table : table|json|csv}';

    protected $description = 'Read-only audit of school tenants with no admin login, plus orphaned/misplaced login rows left behind in a Sahodaya database';

    /** @var list<array<string, mixed>> */
    private array $findings = [];

    public function handle(SahodayaDatabaseProvisioner $provisioner): int
    {
        $sahodayaOpt = $this->option('sahodaya');
        $format = (string) $this->option('format');
        $multiDb = (bool) config('tenancy.database_per_sahodaya', true);

        $allSchools = Tenant::query()->where('type', 'school')->get(['id', 'type', 'name', 'parent_id', 'membership_status', 'is_active']);
        $schoolsById = $allSchools->keyBy('id');

        if (! $multiDb) {
            // Single shared database for the whole platform — one pass covers every
            // school at once; --sahodaya scoping doesn't apply in this mode.
            $this->auditGroup(null, 'ALL (single database)', $allSchools, $schoolsById);
            $this->output($format);

            return self::SUCCESS;
        }

        $sahodayas = Tenant::query()
            ->where('type', 'sahodaya')
            ->when($sahodayaOpt, fn ($q) => $q->where(function ($inner) use ($sahodayaOpt) {
                $inner->where('id', $sahodayaOpt)->orWhere('subdomain', $sahodayaOpt);
            }))
            ->orderBy('name')
            ->get();

        if ($sahodayas->isEmpty()) {
            $this->error('No matching Sahodaya tenants.');

            return self::FAILURE;
        }

        foreach ($sahodayas as $sahodaya) {
            $schools = $allSchools->where('parent_id', $sahodaya->id);

            $status = $provisioner->status($sahodaya);
            if (! $status['ready']) {
                foreach ($schools as $school) {
                    $this->addFinding('not_ready', $sahodaya->name, $school->id, $school->name,
                        "Sahodaya database not ready ({$status['name']}) — login state unknown.");
                }

                continue;
            }

            $this->auditGroup($sahodaya, $sahodaya->name, $schools, $schoolsById);
        }

        if (! $sahodayaOpt) {
            $standalone = $allSchools->whereNull('parent_id');
            if ($standalone->isNotEmpty()) {
                // Any standalone school works as the connection trigger — they all
                // share the same central connection (App\Support\TenancyDatabase::isStandalone).
                $this->auditGroup($standalone->first(), 'STANDALONE', $standalone, $schoolsById);
            }
        }

        $this->output($format);

        return self::SUCCESS;
    }

    /**
     * Audit one physical database: every school in $schoolsInThisGroup should have a
     * school_admin user in here, and every school_admin user in here should belong to
     * a school in $schoolsInThisGroup. $dbOwner just selects the connection — pass null
     * when already on the right connection (single shared database mode).
     *
     * @param  Collection<int, Tenant>  $schoolsInThisGroup
     * @param  Collection<string, Tenant>  $schoolsById
     */
    private function auditGroup(?Tenant $dbOwner, string $label, Collection $schoolsInThisGroup, Collection $schoolsById): void
    {
        $run = function () use ($label, $schoolsInThisGroup, $schoolsById) {
            if (! Schema::hasTable('users') || ! Schema::hasTable('roles')) {
                return;
            }

            $admins = User::role('school_admin')->get(['id', 'tenant_id', 'email', 'username', 'name']);
            $byTenant = $admins->groupBy('tenant_id');
            $idsInThisGroup = $schoolsInThisGroup->pluck('id')->flip();

            foreach ($schoolsInThisGroup as $school) {
                if ($byTenant->get($school->id, collect())->isEmpty()) {
                    $this->addFinding('no_login', $label, $school->id, $school->name,
                        'membership_status='.($school->membership_status ?? '—').', is_active='.($school->is_active ? 'true' : 'false'));
                }
            }

            foreach ($byTenant as $tenantId => $users) {
                if ($idsInThisGroup->has($tenantId)) {
                    continue;
                }

                $school = $schoolsById->get($tenantId);

                if (! $school) {
                    foreach ($users as $user) {
                        $this->addFinding('orphaned_login', $label, (string) $tenantId, '(no matching tenant)',
                            "user #{$user->id} {$user->email} / {$user->username} — tenant_id has no row in the central tenants table.");
                    }

                    continue;
                }

                $actualLabel = $school->parent_id ? (Tenant::find($school->parent_id)?->name ?? 'unknown Sahodaya') : 'STANDALONE';
                foreach ($users as $user) {
                    $this->addFinding('misplaced_login', $label, $school->id, $school->name,
                        "user #{$user->id} {$user->email} / {$user->username} — school now belongs to \"{$actualLabel}\", not \"{$label}\".");
                }
            }
        };

        try {
            $dbOwner === null ? $run() : TenancyDatabase::withTenantDatabase($dbOwner, $run);
        } catch (\Throwable $e) {
            $this->addFinding('audit_error', $label, $dbOwner?->id ?? '—', $dbOwner?->name, $e->getMessage());
        }
    }

    private function addFinding(string $category, string $sahodaya, string $tenantId, ?string $schoolName, string $detail): void
    {
        $this->findings[] = [
            'category' => $category,
            'sahodaya' => $sahodaya,
            'tenant_id' => $tenantId,
            'school' => $schoolName ?? '—',
            'detail' => $detail,
        ];
    }

    private function output(string $format): void
    {
        if ($this->findings === []) {
            $this->info('No issues found — every school has a login, and no orphaned/misplaced logins were found.');

            return;
        }

        switch ($format) {
            case 'json':
                $this->line(json_encode($this->findings, JSON_PRETTY_PRINT));
                break;
            case 'csv':
                $this->line('category,sahodaya,tenant_id,school,detail');
                foreach ($this->findings as $f) {
                    $this->line(sprintf(
                        '%s,%s,%s,"%s","%s"',
                        $f['category'], $f['sahodaya'], $f['tenant_id'],
                        str_replace('"', '""', $f['school']),
                        str_replace('"', '""', $f['detail'])
                    ));
                }
                break;
            default:
                $this->table(['Category', 'Sahodaya', 'Tenant ID', 'School', 'Detail'], array_map(
                    fn ($f) => [$f['category'], $f['sahodaya'], $f['tenant_id'], $f['school'], $f['detail']],
                    $this->findings,
                ));
        }

        $counts = collect($this->findings)->countBy('category');
        $this->warn(count($this->findings).' finding(s): '.$counts->map(fn ($c, $cat) => "{$cat}={$c}")->implode(', '));
    }
}
