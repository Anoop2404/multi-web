<?php

namespace App\Services\State;

use App\Models\ExternalSchool;
use App\Models\State\StateQualifierEntry;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Audit\PlatformAuditLogger;
use App\Services\Auth\UserCredentialService;
use App\Services\Tenancy\TenantProvisioningChecklistService;
use App\Support\TenantAuth;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Phase 3 of docs/STATE_ADMIN_TENANT_PROMOTION_AND_UAT_PLAN_2026_09_23.md — turns the schools
 * listed under a promoted Sahodaya's access-code roster into real school tenants underneath it.
 *
 * Schools do NOT get a database of their own: member schools share their Sahodaya's
 * (config/tenancy.php, `database_per_sahodaya`), so this is tenant row + prefix + login, all
 * written inside the parent's database. Modelled on Migration\KannurLegacySchoolImporter, which
 * already solved prefix allocation and in-tenant admin creation for a bulk school import.
 *
 * ── On roster carry-over ──────────────────────────────────────────────────────────────────────
 * The plan's §5.1 step 6 called for projecting each school's StateQualifierEntry rows into the new
 * database as students and registrations. That is deliberately NOT done, because the schema cannot
 * hold it honestly: `students` requires a `school_class_id` pointing at a real `school_classes` row
 * (see tenant migration 2026_06_13_000010_simplify_students_to_class_only.php) plus an
 * `admission_number` unique per tenant, and a freshly promoted Sahodaya has zero classes. Projecting
 * would mean inventing a class structure and fake admission numbers that the school then has to
 * clean up before importing its real student list — and a fest registration additionally needs a
 * fest_event to register into, which does not exist yet either.
 *
 * Nothing is lost by not copying: the entries stay in the State ledger, and
 * `external_schools.tenant_id` (written here) is exactly the pointer that ties them to the new
 * school tenant. This class reports the count per school so the operator can see what is attached
 * rather than discovering it later.
 */
class ExternalSchoolMigrator
{
    public function __construct(
        private UserCredentialService $credentials,
        private TenantProvisioningChecklistService $checklist,
        private PlatformAuditLogger $audit,
    ) {}

    /** Null means migratable; a string is the reason it is not, phrased for the state admin. */
    public function blockingReason(ExternalSchool $school): ?string
    {
        if ($school->isPromoted()) {
            return 'Already migrated to tenant '.$school->tenant_id.'.';
        }

        if ($school->is_appeal_pool) {
            return 'Appeal pool schools are a synthetic entry bucket, not a real school.';
        }

        if (! $school->isActive()) {
            return 'This school is disabled.';
        }

        $sahodaya = $school->sahodaya;

        if (! $sahodaya) {
            return 'Orphaned — no Sahodaya on the master list.';
        }

        if (! $sahodaya->isPromoted()) {
            return "{$sahodaya->name} has not been promoted yet — run state:promote-sahodayas first.";
        }

        if (! $sahodaya->tenant) {
            return "{$sahodaya->name} is linked to a tenant that no longer exists.";
        }

        return null;
    }

    /**
     * @param  array<string, true>  $reservePrefixes  claimed earlier in this same batch
     * @return array{school: ExternalSchool, migratable: bool, reason: ?string, prefix: ?string, username: ?string, entries: int}
     */
    public function plan(ExternalSchool $school, array $reservePrefixes = []): array
    {
        $reason = $this->blockingReason($school);

        return [
            'school'     => $school,
            'migratable' => $reason === null,
            'reason'     => $reason,
            'prefix'     => $reason === null
                ? $this->allocatePrefix($school, $school->sahodaya->tenant, $reservePrefixes)
                : null,
            'username'   => $school->username,
            'entries'    => $this->entryCount($school),
        ];
    }

    /**
     * @param  array{actor_user_id?: int}  $opts
     * @param  null|callable(string, string): void  $onStep
     */
    public function migrate(ExternalSchool $school, array $opts = [], ?callable $onStep = null): Tenant
    {
        if ($reason = $this->blockingReason($school)) {
            throw new RuntimeException($reason);
        }

        $step = $onStep ?? fn (string $key, string $label) => null;
        $actorId = $opts['actor_user_id'] ?? null;
        $sahodaya = $school->sahodaya->tenant;

        try {
            // 1. Prefix, unique within this Sahodaya (enforced by
            //    2026_06_20_000003_unique_school_prefix_per_sahodaya.php). Resolved first so a
            //    collision fails before a tenant row exists.
            $prefix = $this->allocatePrefix($school, $sahodaya);
            $step('prefix', "Prefix {$prefix}");

            // 2. The school tenant. No database of its own — school_no is left unassigned on
            //    purpose: Tenant::schoolCode() allocates it lazily and then it is fixed forever, so
            //    cards printed later never go stale.
            $tenant = Tenant::create([
                'id'            => (string) Str::uuid(),
                'type'          => 'school',
                'parent_id'     => $sahodaya->id,
                'state_id'      => $sahodaya->state_id,
                'name'          => trim($school->name),
                'school_prefix' => $prefix,
                'is_active'     => true,
            ]);
            $this->audit->tenantCreated($tenant);
            $this->checklist->markComplete($tenant, 'tenant_created', $actorId);
            $step('tenant', "School tenant {$tenant->id} created");

            // 3. Link back before anything else can fail, so a retry resumes instead of duplicating.
            $school->forceFill(['tenant_id' => $tenant->id, 'promoted_at' => now()])->save();
            $step('linked', 'Linked to the master-list row');

            // 4. Login, inside the parent Sahodaya's database.
            if ($admin = $this->createAdminUser($sahodaya, $tenant, $school, $actorId)) {
                $this->checklist->markComplete($tenant, 'portal_admin_created', $actorId);
                $step('admin', "Login {$admin['username']}".($admin['reused_password'] ? ' (existing password kept)' : ' (new password issued)'));
            } else {
                $step('admin', 'No usable login details — school admin must be created by hand');
            }

            $entries = $this->entryCount($school);
            if ($entries > 0) {
                // Not copied — see the class docblock. Surfaced so it is a known quantity.
                $step('roster', "{$entries} State qualifier entr".($entries === 1 ? 'y' : 'ies').' stay in the State ledger, linked to this school');
            }

            $this->audit->log(
                'external_school.migrated',
                "Migrated outside school \"{$school->name}\" into {$sahodaya->name}",
                $tenant,
                [
                    'external_school_id' => $school->id,
                    'sahodaya_tenant_id' => $sahodaya->id,
                    'prefix'             => $prefix,
                    'qualifier_entries'  => $entries,
                ],
                $actorId,
            );

            return $tenant->fresh();
        } catch (Throwable $e) {
            // A school tenant has no database of its own, so there is nothing to tear down —
            // deactivate it so a half-built school is never reachable, and leave it linked for retry.
            if (isset($tenant) && $tenant instanceof Tenant) {
                $tenant->forceFill(['is_active' => false])->save();
            }

            throw $e;
        }
    }

    /**
     * "St Joseph HSS" → "SJH". Initials first, then the first letters of the name, then a random
     * suffix; every candidate is checked against the prefixes already used in this Sahodaya.
     *
     * @param  array<string, true>  $reserved
     */
    public function allocatePrefix(ExternalSchool $school, Tenant $sahodaya, array $reserved = []): string
    {
        $used = $reserved + $this->existingPrefixes($sahodaya);

        $words = array_values(array_filter(preg_split('/\s+/', strtoupper(trim($school->name))) ?: []));
        $candidates = [];

        if (count($words) >= 2) {
            $candidates[] = implode('', array_map(fn ($w) => mb_substr($w, 0, 1), array_slice($words, 0, 4)));
        }

        if ($words !== []) {
            $candidates[] = preg_replace('/[^A-Z0-9]/', '', $words[0]);
        }

        foreach ($candidates as $candidate) {
            $candidate = mb_substr(preg_replace('/[^A-Z0-9]/', '', (string) $candidate) ?? '', 0, 8);
            if ($candidate !== '') {
                return $this->firstAvailable($candidate, $used);
            }
        }

        return $this->firstAvailable('SCH', $used);
    }

    /** @return array<string, true> */
    private function existingPrefixes(Tenant $sahodaya): array
    {
        $prefixes = [];

        foreach (Tenant::query()->where('parent_id', $sahodaya->id)->where('type', 'school')->get() as $school) {
            $prefix = strtoupper(trim((string) $school->school_prefix));
            if ($prefix !== '') {
                $prefixes[$prefix] = true;
            }
        }

        return $prefixes;
    }

    /** @param  array<string, true>  $used */
    private function firstAvailable(string $base, array $used): string
    {
        $base = mb_substr($base, 0, 8) ?: 'SCH';

        if (! isset($used[$base])) {
            return $base;
        }

        for ($i = 2; $i <= 99; $i++) {
            $candidate = mb_substr($base, 0, max(1, 10 - strlen((string) $i))).$i;
            if (! isset($used[$candidate])) {
                return $candidate;
            }
        }

        return $base.Str::upper(Str::random(2));
    }

    private function entryCount(ExternalSchool $school): int
    {
        return StateQualifierEntry::where('school_id', $school->id)->count();
    }

    /**
     * Creates the school_admin login inside the parent Sahodaya's database.
     *
     * Reuses the school's existing access-code-portal username and password where they are still
     * available, so a coordinator who already handed those out does not have to re-issue anything —
     * the same credentials keep working, just against the real portal now.
     *
     * @return null|array{user: User, username: string, password: ?string, reused_password: bool}
     */
    private function createAdminUser(Tenant $sahodaya, Tenant $school, ExternalSchool $external, ?int $actorId): ?array
    {
        $username = trim((string) $external->username) ?: Str::slug($external->name, '.');

        if ($username === '') {
            return null;
        }

        return TenantAuth::withTenantUsers($sahodaya, function () use ($school, $external, $username, $actorId) {
            $existing = User::query()->where('username', $username)->first();

            if ($existing) {
                // Already provisioned by an earlier run, or the username is taken by someone else in
                // this Sahodaya's database. Either way, do not touch an account that may be in use.
                return $existing->tenant_id === $school->id
                    ? ['user' => $existing, 'username' => $username, 'password' => null, 'reused_password' => true]
                    : null;
            }

            // users.email is NOT NULL on the central connection but nullable inside a dedicated
            // tenant database (tenant/2026_07_20_000001_make_tenant_users_email_nullable.php).
            // External schools have no email at all, so on a shared database there is no honest
            // account to create — leave it for a human rather than inventing an address.
            if (! $this->usersEmailIsNullable()) {
                return null;
            }

            $plain = filled($external->plain_password)
                ? $external->plain_password
                : $this->credentials->generateTemporaryPassword('school_admin');

            $user = new User(['tenant_id' => $school->id]);
            $user->fill([
                'name'  => $external->contact_name ?: $school->name,
                'email' => null,
            ]);

            // users.password is NOT NULL, so it must be set on the insert itself.
            $this->credentials->storePassword($user, $plain, mustChange: true);
            $user->forceFill(['username' => $username])->save();
            $user->syncRoles(['school_admin']);

            $this->audit->userCreated($user);
            $this->audit->portalProvisioned($user, 'school_admin', $school->id);

            return [
                'user'            => $user->fresh(),
                'username'        => $username,
                'password'        => $plain,
                'reused_password' => filled($external->plain_password),
            ];
        });
    }

    /** True inside a dedicated Sahodaya database, false on the central connection. */
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
}
