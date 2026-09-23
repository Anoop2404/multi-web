<?php

namespace App\Services\State;

use App\Models\ExternalSahodaya;
use App\Models\SahodayaProfile;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Audit\PlatformAuditLogger;
use App\Services\Auth\UserCredentialService;
use App\Services\Tenancy\SahodayaDatabaseProvisioner;
use App\Services\Tenancy\TenantProvisioningChecklistService;
use App\Support\SahodayaSiteTemplate;
use App\Support\TenancyDatabase;
use App\Support\TenantAuth;
use App\Support\TenantDomainSync;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Phase 2 of docs/STATE_ADMIN_TENANT_PROMOTION_AND_UAT_PLAN_2026_09_23.md — turns a row on the
 * seeded state Sahodaya master list (ExternalSahodaya: an access code and a name, no tenant, no
 * database) into a real platform tenant with its own subdomain and dedicated database, so it can
 * run the whole Kalotsav stack instead of only handing over a roster.
 *
 * Every step asks "is this already done?" first, so promotion is safe to re-run. That matters more
 * than it sounds: the sequence creates a Postgres database halfway through, and a promotion that
 * blew up at step 7 must be resumable without creating a second tenant and a second database for
 * the same Sahodaya. The fact of promotion is ExternalSahodaya.tenant_id, not promotion_status —
 * status is progress reporting for the UI, the foreign key is the truth.
 *
 * Deliberately NOT done here:
 * - Migrating the Sahodaya's schools. That is Phase 3 (ExternalSchoolMigrator) and runs after a
 *   promotion has been verified, because it writes students and registrations into the new
 *   database and is much harder to undo than an empty tenant.
 * - Dropping anything. There is no un-promote path in this class; see the plan's rollback section.
 */
class SahodayaPromotionService
{
    public function __construct(
        private SahodayaDatabaseProvisioner $databases,
        private UserCredentialService $credentials,
        private TenantProvisioningChecklistService $checklist,
        private PlatformAuditLogger $audit,
    ) {}

    /**
     * What promote() would do, without doing any of it. Used by --dry-run and by the confirm
     * drawer in the State admin UI: subdomain collisions and duplicate names in the official list
     * are common enough that they need to be visible *before* a database gets created.
     *
     * @param  array<int, string>  $reserveSubdomains  subdomains already claimed earlier in this
     *                                                 same batch but not yet written to the DB
     * @return array{sahodaya: ExternalSahodaya, promotable: bool, reason: ?string, subdomain: ?string, database: ?string, admin_email: ?string, admin_username: ?string, schools: int}
     */
    public function plan(ExternalSahodaya $ext, array $reserveSubdomains = []): array
    {
        $reason = $this->blockingReason($ext);
        $subdomain = $reason === null ? $this->suggestSubdomain($ext, $reserveSubdomains) : null;

        return [
            'sahodaya'       => $ext,
            'promotable'     => $reason === null,
            'reason'         => $reason,
            'subdomain'      => $subdomain,
            // The database name is derived from the tenant uuid, which does not exist until the
            // tenant row does, so this is the pattern rather than the final string.
            'database'       => $subdomain ? config('tenancy.database.prefix').'<tenant-uuid>' : null,
            'admin_email'    => $this->adminEmail($ext),
            'admin_username' => $subdomain,
            'schools'        => $ext->schools()->count(),
        ];
    }

    /**
     * The master list is hand-maintained, and contact_email is not always an email — one seeded row
     * carries a postal address ("kottayam 686141"). Writing that into users.email would create an
     * account nobody can be emailed at or reset a password through, and it would pass the unique
     * index without complaint. Anything that is not a real address is dropped; the Sahodaya still
     * gets a username-only login.
     */
    public function adminEmail(ExternalSahodaya $ext): ?string
    {
        $email = strtolower(trim((string) $ext->contact_email));

        return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
    }

    /** Null means promotable; a string is the reason it is not, phrased for the state admin. */
    public function blockingReason(ExternalSahodaya $ext): ?string
    {
        if ($ext->isPromoted()) {
            return 'Already promoted to tenant '.$ext->tenant_id.'.';
        }

        if ($ext->is_appeal_pool) {
            return 'Appeal pool rows are not real Sahodayas and are never promoted.';
        }

        if (! $ext->isActive()) {
            return 'This Sahodaya is disabled. Re-activate it before promoting.';
        }

        if (trim((string) $ext->name) === '') {
            return 'This row has no name.';
        }

        return null;
    }

    /**
     * @param  array{subdomain?: string, plan?: string, state_id?: string, actor_user_id?: int, create_database?: bool}  $opts
     * @param  null|callable(string, string): void  $onStep  step key + human label, for CLI/UI progress
     */
    public function promote(ExternalSahodaya $ext, array $opts = [], ?callable $onStep = null): Tenant
    {
        if ($reason = $this->blockingReason($ext)) {
            throw new RuntimeException($reason);
        }

        $step = $onStep ?? fn (string $key, string $label) => null;
        $actorId = $opts['actor_user_id'] ?? null;

        $ext->forceFill([
            'promotion_status' => ExternalSahodaya::PROMOTION_PROVISIONING,
            'promotion_error'  => null,
        ])->save();

        try {
            // 1. Subdomain. Resolved before the tenant row so a collision fails without leaving
            //    an orphan tenant behind.
            $subdomain = $opts['subdomain'] ?? $this->suggestSubdomain($ext);
            $this->assertSubdomainUsable($subdomain);
            $step('subdomain', "Subdomain {$subdomain}");

            // 2. Tenant row. Created inactive: TenantDomainSync::sync() deletes the domain rows of
            //    an inactive tenant, so the host must not be published until the database behind it
            //    actually answers. Activation + domain sync happen together at step 6.
            $tenant = Tenant::create([
                'id'             => (string) Str::uuid(),
                'type'           => 'sahodaya',
                'state_id'       => $opts['state_id'] ?? $ext->state_id ?? $ext->program?->state_id,
                'name'           => trim($ext->name),
                'subdomain'      => $subdomain,
                'plan'           => $opts['plan'] ?? 'standard',
                'is_active'      => false,
                'is_appeal_pool' => false,
            ]);
            $this->audit->tenantCreated($tenant);
            $this->checklist->markComplete($tenant, 'tenant_created', $actorId);
            $step('tenant_created', "Tenant {$tenant->id} created");

            // 3. Link back immediately. If anything below throws, this row now points at the
            //    half-built tenant instead of orphaning it, which is what makes a retry resumable
            //    rather than duplicating.
            $ext->forceFill(['tenant_id' => $tenant->id])->save();
            $step('linked', 'Linked to master list row');

            // 4. Database: configure a name, create it, migrate every tenant migration, seed roles
            //    and the Sahodaya profile + site template. All of this is the existing provisioner.
            $this->provisionDatabase($tenant, $opts, $actorId, $step);

            // 5. Carry the Sahodaya's identity into the database: the profile prefix is what school
            //    codes (MCS-027) and every printed card derive from, so setting it now avoids
            //    re-issuing codes later.
            $this->seedProfile($tenant, $ext);
            $step('profile', 'Sahodaya profile prefix set');

            // 6. Activate, then publish the host. Order matters (see step 2).
            $tenant->forceFill(['is_active' => true])->save();
            TenantDomainSync::sync($tenant);
            $step('domain', 'Host '.TenantDomainSync::subdomainFqdn($subdomain).' published');

            // 7. Admin login, inside the tenant database.
            $admin = $this->createAdminUser($tenant, $ext, $actorId);
            if ($admin) {
                $this->checklist->markComplete($tenant, 'portal_admin_created', $actorId);
                $step('admin', "Admin login {$admin['username']} created");
            } else {
                // Not fatal, and not silently skipped either: a Sahodaya with no contact details
                // on the master list still gets a working tenant, it just needs a login issued by
                // hand from Tenants → Show. Fabricating an email address here would be worse.
                $step('admin', 'No contact details on the master list — admin login not created');
            }

            // 8. Done.
            $ext->forceFill([
                'promotion_status' => ExternalSahodaya::PROMOTION_READY,
                'promotion_error'  => null,
                'promoted_at'      => now(),
            ])->save();

            // Keep the State's canonical identity single: attach the new tenant to the directory
            // row that already carries this Sahodaya's submissions, rather than letting a second
            // identity appear the next time it submits and double-count it in standings and fees.
            app(StateSahodayaDirectory::class)->linkPromotedTenant($ext->fresh(), $tenant);

            $this->audit->log(
                'sahodaya.promoted',
                "Promoted outside Sahodaya \"{$ext->name}\" to tenant {$tenant->name}",
                $tenant,
                [
                    'external_sahodaya_id' => $ext->id,
                    'subdomain'            => $subdomain,
                    'district'             => $ext->district,
                    'admin_created'        => $admin !== null,
                ],
                $actorId,
            );

            return $tenant->fresh();
        } catch (Throwable $e) {
            $ext->forceFill([
                'promotion_status' => ExternalSahodaya::PROMOTION_FAILED,
                'promotion_error'  => Str::limit($e->getMessage(), 2000),
            ])->save();

            // Never leave a tenant reachable when its database may not be ready. The tenant row
            // and its database are kept on purpose so a retry resumes instead of rebuilding.
            if (isset($tenant) && $tenant instanceof Tenant) {
                $tenant->forceFill(['is_active' => false])->save();
                $tenant->domains()->delete();
            }

            throw $e;
        }
    }

    /**
     * Retry a failed promotion. Same method, but it reuses the tenant already linked to the row
     * rather than creating a second one — the steps that succeeded last time short-circuit.
     */
    public function retry(ExternalSahodaya $ext, array $opts = [], ?callable $onStep = null): Tenant
    {
        if (! $ext->isPromoted()) {
            return $this->promote($ext, $opts, $onStep);
        }

        $tenant = Tenant::find($ext->tenant_id);

        if (! $tenant) {
            // The tenant was deleted underneath us; the link is stale, so start clean.
            $ext->forceFill(['tenant_id' => null])->save();

            return $this->promote($ext->fresh(), $opts, $onStep);
        }

        $step = $onStep ?? fn (string $key, string $label) => null;
        $actorId = $opts['actor_user_id'] ?? null;

        $ext->forceFill([
            'promotion_status' => ExternalSahodaya::PROMOTION_PROVISIONING,
            'promotion_error'  => null,
        ])->save();

        try {
            // A retry is also the place a state_id that was missing the first time gets filled in:
            // most seeded programs predate multi-state, so the first promotion often stamps null.
            $stateId = $opts['state_id'] ?? $ext->state_id ?? $ext->program?->state_id;
            if ($stateId && blank($tenant->state_id)) {
                $tenant->forceFill(['state_id' => $stateId])->save();
            }

            $this->provisionDatabase($tenant, $opts, $actorId, $step, verifying: true);
            $this->seedProfile($tenant, $ext);

            $tenant->forceFill(['is_active' => true])->save();
            TenantDomainSync::sync($tenant);
            $step('domain', 'Host published');

            if ($admin = $this->createAdminUser($tenant, $ext, $actorId)) {
                $this->checklist->markComplete($tenant, 'portal_admin_created', $actorId);
                $step('admin', "Admin login {$admin['username']} ready");
            }

            // Repairs the canonical-identity link if the original promotion got as far as creating
            // the tenant but failed before recording it in the State directory.
            app(StateSahodayaDirectory::class)->linkPromotedTenant($ext->fresh(), $tenant);

            $ext->forceFill([
                'promotion_status' => ExternalSahodaya::PROMOTION_READY,
                'promotion_error'  => null,
                'promoted_at'      => $ext->promoted_at ?? now(),
            ])->save();

            return $tenant->fresh();
        } catch (Throwable $e) {
            $ext->forceFill([
                'promotion_status' => ExternalSahodaya::PROMOTION_FAILED,
                'promotion_error'  => Str::limit($e->getMessage(), 2000),
            ])->save();

            $tenant->forceFill(['is_active' => false])->save();
            $tenant->domains()->delete();

            throw $e;
        }
    }

    /**
     * Slug of the Sahodaya name, with "sahodaya"/"complex"/"school complex" noise stripped. On
     * collision, falls back to prefixing the district (names repeat across districts in the
     * official list far more often than you would hope), then to a numeric suffix.
     *
     * @param  array<int, string>  $reserved  claimed earlier in the same batch, not yet persisted
     */
    public function suggestSubdomain(ExternalSahodaya $ext, array $reserved = []): string
    {
        $reserved = array_map('strtolower', $reserved);

        $base = $this->slugify($ext->name);
        $candidates = [$base];

        if (filled($ext->district)) {
            $candidates[] = $this->slugify($ext->district.' '.$ext->name);
            $candidates[] = $this->slugify($ext->name.' '.$ext->district);
        }

        foreach ($candidates as $candidate) {
            if ($candidate !== '' && $this->subdomainAvailable($candidate, $reserved)) {
                return $candidate;
            }
        }

        $base = $base !== '' ? $base : 'sahodaya';
        for ($i = 2; $i <= 200; $i++) {
            $candidate = Str::limit($base, 58, '').'-'.$i;
            if ($this->subdomainAvailable($candidate, $reserved)) {
                return $candidate;
            }
        }

        throw new RuntimeException("Could not find a free subdomain for \"{$ext->name}\".");
    }

    private function slugify(string $value): string
    {
        $value = Str::lower(trim($value));
        // "Malappuram Sahodaya School Complex" → "malappuram". Keeps hosts short and readable;
        // every tenant here is a Sahodaya, so the word carries no information in the host.
        $value = preg_replace('/\b(sahodaya|sahodayas|school complex|complex|cbse)\b/i', ' ', $value) ?? $value;
        $slug = Str::slug($value);

        return Str::limit($slug, 63, '');
    }

    private function subdomainAvailable(string $subdomain, array $reserved = []): bool
    {
        if ($subdomain === '' || in_array(strtolower($subdomain), $reserved, true)) {
            return false;
        }

        if (TenantDomainSync::isReservedSubdomain($subdomain) || TenantDomainSync::isCentralHost($subdomain)) {
            return false;
        }

        return ! Tenant::query()->where('subdomain', $subdomain)->exists();
    }

    private function assertSubdomainUsable(string $subdomain): void
    {
        if (! preg_match('/^[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?$/', $subdomain)) {
            throw new RuntimeException("\"{$subdomain}\" is not a valid subdomain.");
        }

        if (! $this->subdomainAvailable($subdomain)) {
            throw new RuntimeException("The subdomain \"{$subdomain}\" is reserved or already taken.");
        }
    }

    /**
     * A dedicated database per Sahodaya is the production shape, but TENANCY_DATABASE_PER_SAHODAYA
     * can be off (local setups and the test suite run on one shared database) — in which case there
     * is nothing to create or migrate and calling the provisioner would throw. Mirrors the same
     * branch in Admin\TenantController::store().
     */
    private function provisionDatabase(Tenant $tenant, array $opts, ?int $actorId, callable $step, bool $verifying = false): void
    {
        if (! TenancyDatabase::enabled()) {
            $step('database', 'Shared database — no per-Sahodaya database to create');

            return;
        }

        $this->databases->ensureReady(
            $tenant,
            seedDefaults: true,
            createIfMissing: $opts['create_database'] ?? true,
        );

        if (! $verifying) {
            $this->audit->tenantDatabaseMigrated($tenant);
        }

        $this->checklist->markComplete($tenant, 'database_configured', $actorId);
        $this->checklist->markComplete($tenant, 'database_migrated', $actorId);
        $step('database', $verifying
            ? 'Database verified, migrated and seeded'
            : 'Database created, migrated and seeded');
    }

    /**
     * Ensures the Sahodaya profile and public-site sections exist, and fills in the prefix that
     * school codes and printed ID cards derive from (Tenant::schoolCode()). The provisioner's
     * seedDefaults() already does the first two when a dedicated database is in play; this is
     * idempotent and also covers the shared-database branch, where nothing else would.
     *
     * Never overwrites an existing prefix — cards already printed must not go stale.
     */
    private function seedProfile(Tenant $tenant, ExternalSahodaya $ext): void
    {
        $prefix = $this->derivePrefix($ext->name);

        TenancyDatabase::withTenantDatabase($tenant, function () use ($tenant, $prefix) {
            $profile = SahodayaProfile::firstOrCreate(
                ['tenant_id' => $tenant->id],
                ['student_data_mode' => 'not_required', 'membership_fee_type' => 'fixed'],
            );

            if (blank($profile->prefix)) {
                $profile->forceFill(['prefix' => $prefix])->save();
            }

            if ($tenant->sections()->count() === 0) {
                SahodayaSiteTemplate::apply($tenant);
            }
        });

        $tenant->invalidateCache();
    }

    /**
     * Whether the users table on the currently active connection allows a null email. True inside a
     * dedicated Sahodaya database, false on the central connection. Defaults to true if the schema
     * cannot be inspected, so a driver that does not support it falls through to the insert (which
     * would fail loudly) rather than silently skipping every admin account.
     */
    private function usersEmailIsNullable(): bool
    {
        try {
            $column = collect(\Illuminate\Support\Facades\Schema::getColumns('users'))
                ->firstWhere('name', 'email');

            return (bool) ($column['nullable'] ?? true);
        } catch (Throwable) {
            return true;
        }
    }

    /** "Malappuram Sahodaya" → "MAL". Initials when the name has several words, else first letters. */
    private function derivePrefix(string $name): string
    {
        $cleaned = preg_replace('/\b(sahodaya|sahodayas|school complex|complex|cbse)\b/i', ' ', $name) ?? $name;
        $words = preg_split('/\s+/', trim($cleaned)) ?: [];
        $words = array_values(array_filter($words));

        if (count($words) >= 2) {
            $initials = '';
            foreach ($words as $word) {
                $initials .= mb_substr($word, 0, 1);
                if (mb_strlen($initials) === 3) {
                    break;
                }
            }

            return mb_strtoupper($initials);
        }

        return mb_strtoupper(mb_substr($words[0] ?? 'SAH', 0, 3));
    }

    /**
     * Creates the sahodaya_admin login inside the tenant's own database (portal users are tenant
     * data, not central). Returns null when the master list has no contact details to build a
     * login from, and returns the existing account unchanged if one is already there.
     *
     * @return null|array{user: User, username: string, email: ?string, password: ?string}
     */
    private function createAdminUser(Tenant $tenant, ExternalSahodaya $ext, ?int $actorId): ?array
    {
        $email = $this->adminEmail($ext);
        $username = $tenant->subdomain;

        if (blank($email) && blank($ext->contact_name) && blank($ext->contact_phone)) {
            return null;
        }

        return TenantAuth::withTenantUsers($tenant, function () use ($tenant, $ext, $email, $username, $actorId) {
            // users.email is unique per database, not per tenant. With a dedicated database per
            // Sahodaya that never collides, but on a shared database (TENANCY_DATABASE_PER_SAHODAYA
            // off, and standalone tenants on the central connection) two Sahodayas that list the
            // same contact address on the master list would collide — and the insert would abort
            // the whole promotion. Dropping the address is the lesser loss: the Sahodaya still gets
            // a working username login, and the duplicate is visible in the master list.
            if ($email && User::query()->where('email', $email)->where('tenant_id', '!=', $tenant->id)->exists()) {
                $email = null;
            }

            // A username-only login is possible in a dedicated tenant database, where users.email
            // was deliberately relaxed to nullable (tenant migration
            // 2026_07_20_000001_make_tenant_users_email_nullable.php), but not on the central
            // connection, where it is still NOT NULL. Rather than inventing a placeholder address
            // nobody can receive a password reset at, leave the account to be issued by hand from
            // Tenants → Show. The checklist step stays pending, so it is visible work, not a
            // silent gap.
            if ($email === null && ! $this->usersEmailIsNullable()) {
                return null;
            }

            $existing = User::query()
                ->where('tenant_id', $tenant->id)
                ->where(function ($q) use ($email, $username) {
                    $q->where('username', $username);
                    if ($email) {
                        $q->orWhere('email', $email);
                    }
                })
                ->first();

            if ($existing) {
                // Already provisioned by an earlier run. Do not reset a password somebody may
                // already be using.
                return [
                    'user'     => $existing,
                    'username' => $existing->username ?? $username,
                    'email'    => $existing->email,
                    'password' => null,
                ];
            }

            $user = new User([
                'tenant_id' => $tenant->id,
            ]);

            $user->fill([
                'name'              => filled($ext->contact_name) ? $ext->contact_name : $ext->name.' Admin',
                'email'             => $email,
                'email_verified_at' => now(),
            ]);

            $plain = $this->credentials->generateTemporaryPassword('sahodaya_admin');
            // users.password is NOT NULL, so it has to be set on the insert itself.
            $this->credentials->storePassword($user, $plain, mustChange: true);
            $user->forceFill(['username' => $username])->save();
            $user->syncRoles(['sahodaya_admin']);

            $this->audit->userCreated($user);
            $this->audit->portalProvisioned($user, 'sahodaya_admin', $tenant->id);

            return [
                'user'     => $user->fresh(),
                'username' => $username,
                'email'    => $email,
                'password' => $plain,
            ];
        });
    }
}
