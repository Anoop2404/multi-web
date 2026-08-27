<?php

namespace App\Support;

use App\Models\Tenant;
use App\Services\Tenancy\SahodayaDatabaseProvisioner;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Stancl\Tenancy\Exceptions\TenantDatabaseDoesNotExistException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;

class TenancyDatabase
{
    private const RUNTIME_CONNECTION = 'tenant_runtime';

    public static function enabled(): bool
    {
        return (bool) config('tenancy.database_per_sahodaya', true);
    }

    /**
     * Run a callback against a Sahodaya PostgreSQL database without Stancl's
     * initialize/end cycle (safe for superadmin pages on the central domain).
     *
     * @param  array{username?: ?string, password?: ?string}|null  $credentials
     */
    public static function usingDatabase(string $databaseName, callable $callback, ?array $credentials = null): mixed
    {
        $connectionName = self::RUNTIME_CONNECTION;

        // Already inside an active swap to this exact database — e.g. this method (or
        // withTenantDatabase(), or a nested caller like
        // SahodayaDatabaseProvisioner::schemaIsReady()) invoked again while an outer call
        // for the same tenant is still running. RUNTIME_CONNECTION is a single global
        // config slot, not one per call — re-swapping would still technically work, but
        // this (inner) call's own `finally` cleanup would null out
        // database.connections.tenant_runtime the moment it returns, out from under the
        // outer call, which may still need to query on it afterward. Just run the
        // callback on the connection that's already active instead of swapping again.
        if (config('database.default') === $connectionName
            && config("database.connections.{$connectionName}.database") === $databaseName) {
            return $callback($connectionName);
        }

        $central = (string) config('tenancy.database.central_connection', 'central');
        $previousDefault = config('database.default');
        $template = config("database.connections.{$central}")
            ?? config('database.connections.'.($previousDefault ?: 'pgsql'));

        if (! is_array($template)) {
            throw new InvalidArgumentException('No database connection template found for tenant runtime.');
        }

        $overrides = ['database' => $databaseName];
        if (is_array($credentials)) {
            if (array_key_exists('username', $credentials) && filled($credentials['username'])) {
                $overrides['username'] = (string) $credentials['username'];
            }
            if (array_key_exists('password', $credentials) && $credentials['password'] !== null && $credentials['password'] !== '') {
                $overrides['password'] = (string) $credentials['password'];
            }
        }

        config([
            "database.connections.{$connectionName}" => array_merge($template, $overrides),
            'database.default' => $connectionName,
        ]);

        DB::purge($connectionName);
        DB::setDefaultConnection($connectionName);

        try {
            return $callback($connectionName);
        } finally {
            DB::purge($connectionName);
            config([
                'database.default' => $previousDefault,
                "database.connections.{$connectionName}" => null,
            ]);
            DB::setDefaultConnection($previousDefault);
        }
    }

    /**
     * Execute a callback in the tenant database when ready.
     * Uses Stancl tenancy on tenant routes; a direct connection on the central domain.
     */
    public static function withTenantDatabase(Tenant $tenant, callable $callback): mixed
    {
        if (! self::enabled() || self::isStandalone($tenant)) {
            return $callback();
        }

        if (tenancy()->initialized) {
            return $callback();
        }

        // Already inside an active usingDatabase() swap — e.g. this method called again,
        // directly or via a helper like TenantBranding::logoUrl()/whenDatabaseReady(),
        // while an outer withTenantDatabase() call is still running. usingDatabase()'s
        // RUNTIME_CONNECTION name is a single global slot, not one per call — a nested
        // invocation's own cleanup would null out database.connections.tenant_runtime out
        // from under the outer call the moment the nested call returns, breaking any
        // query the outer call still needs to make afterward. Since there is only ever
        // one tenant_runtime slot, a nested call is always for the same tenant already
        // active — just run it on the connection that's already there.
        if (config('database.default') === self::RUNTIME_CONNECTION) {
            return $callback();
        }

        $owner = self::owner($tenant);
        $dbName = $owner->getInternal('db_name');

        if (! $dbName) {
            throw new InvalidArgumentException('Sahodaya database name is not configured.');
        }

        return self::usingDatabase($dbName, fn () => $callback(), self::credentialsFor($owner));
    }

    /** @return array{username?: string, password?: string} */
    public static function credentialsFor(Tenant $owner): array
    {
        $credentials = [];
        $username = $owner->getInternal('db_username');
        $password = $owner->getInternal('db_password');

        if (filled($username)) {
            $credentials['username'] = (string) $username;
        }
        if ($password !== null && $password !== '') {
            $credentials['password'] = (string) $password;
        }

        return $credentials;
    }

    /**
     * The Sahodaya tenant that owns the physical database for this tenant.
     */
    public static function owner(Tenant $tenant): Tenant
    {
        if ($tenant->type === 'sahodaya') {
            return $tenant;
        }

        if (self::isStandalone($tenant)) {
            return $tenant;
        }

        if ($tenant->type === 'school' && $tenant->parent_id) {
            $parent = Tenant::query()->find($tenant->parent_id);

            if ($parent?->type === 'sahodaya') {
                return $parent;
            }
        }

        throw new InvalidArgumentException('No Sahodaya database owner for tenant '.$tenant->id);
    }

    /**
     * A standalone school has no Sahodaya parent. It has no dedicated database — its
     * tenant-scoped data lives on the central connection, scoped by tenant_id, same as
     * every other central table. Callers must check this before resolving a database
     * owner, since a standalone school doesn't have one.
     */
    public static function isStandalone(Tenant $tenant): bool
    {
        return $tenant->type === 'school' && ! $tenant->parent_id;
    }

    public static function initializeForTenant(Tenant $tenant): void
    {
        if (! self::enabled()) {
            if (! tenancy()->initialized || tenant()?->id !== $tenant->id) {
                tenancy()->initialize($tenant);
            }

            return;
        }

        if (tenancy()->initialized && tenant()?->id === $tenant->id) {
            return;
        }

        if (self::isStandalone($tenant)) {
            // database_per_sahodaya is on globally, so DatabaseTenancyBootstrapper is
            // registered and will run for every initialize() call regardless — point this
            // tenant's own db_name at the central database (same trick used below for a
            // Sahodaya-affiliated school copying its parent's db_name) so the "switch"
            // lands on the connection it's already on. No separate database to provision.
            $central = (string) config('tenancy.database.central_connection', 'central');
            $tenant->setInternal('db_name', config("database.connections.{$central}.database"));
            $tenant->offsetUnset($tenant::internalPrefix().'db_username');
            $tenant->offsetUnset($tenant::internalPrefix().'db_password');

            tenancy()->initialize($tenant);

            return;
        }

        $owner = self::owner($tenant);
        $provisioner = app(SahodayaDatabaseProvisioner::class);
        $provisioner->ensureConfigured($owner);

        if (config('tenancy.auto_create_sahodaya_database', false)) {
            try {
                $provisioner->ensureReady($owner);
            } catch (\Throwable) {
                // Fall through — show a clear error below if the DB is still missing.
            }
        }

        // Schools share the Sahodaya DB; copy name and only set optional login when present.
        $tenant->setInternal('db_name', $owner->getInternal('db_name'));
        $username = $owner->getInternal('db_username');
        $password = $owner->getInternal('db_password');
        if (filled($username)) {
            $tenant->setInternal('db_username', $username);
        } else {
            $tenant->offsetUnset($tenant::internalPrefix().'db_username');
        }
        if (filled($password)) {
            $tenant->setInternal('db_password', $password);
        } else {
            $tenant->offsetUnset($tenant::internalPrefix().'db_password');
        }

        try {
            tenancy()->initialize($tenant);
        } catch (TenantDatabaseDoesNotExistException $e) {
            $dbName = $owner->database()->getName();
            throw new ServiceUnavailableHttpException(null, <<<MSG
Sahodaya database "{$dbName}" is not set up yet.

Superadmin: open Admin → Tenants → {$owner->name}, save the database name, create the PostgreSQL database, then run migrations.

CLI: php artisan sahodaya:provision-databases --tenant={$owner->id} --create --seed
MSG);
        }
    }

    /**
     * Run a callback in tenant DB context when the Sahodaya database is migrated.
     * Returns $default when dedicated DB mode is off or the database is not ready yet.
     */
    public static function whenDatabaseReady(Tenant $tenant, callable $callback, mixed $default = null): mixed
    {
        if (! self::enabled() || self::isStandalone($tenant)) {
            return $callback();
        }

        try {
            $owner = self::owner($tenant);
            $status = app(SahodayaDatabaseProvisioner::class)->status($owner);

            if (! $status['ready']) {
                return $default;
            }

            return self::withTenantDatabase($tenant, $callback);
        } catch (\Throwable) {
            return $default;
        }
    }

    /**
     * @throws \RuntimeException
     */
    public static function runWhenDatabaseReady(Tenant $tenant, callable $callback): mixed
    {
        if (! self::enabled() || self::isStandalone($tenant)) {
            return $callback();
        }

        $owner = self::owner($tenant);
        $status = app(SahodayaDatabaseProvisioner::class)->status($owner);

        if (! $status['ready']) {
            throw new \RuntimeException('Sahodaya database is not ready. Create the database and run migrations first.');
        }

        return self::withTenantDatabase($tenant, $callback);
    }

    /**
     * The Sahodaya whose database is currently active, across both tenant-context
     * mechanisms this app uses: real Stancl tenancy (HTTP admin requests, via
     * initializeForTenant()) and the raw connection swap withTenantDatabase() does when
     * tenancy isn't already initialized (queued jobs — no tenancy()->tenant to read).
     * Only used off the hot path (certificate creation, to populate CertificateIndex), so
     * looping "tens" of Sahodayas in the second branch is not a real cost.
     */
    public static function currentTenant(): ?Tenant
    {
        if (tenancy()->initialized) {
            $tenant = tenancy()->tenant;

            return $tenant instanceof Tenant ? $tenant : ($tenant ? Tenant::find($tenant->getTenantKey()) : null);
        }

        $connectionName = config('database.default');
        if ($connectionName === (string) config('tenancy.database.central_connection', 'central')) {
            return null;
        }

        $currentDbName = config("database.connections.{$connectionName}.database");
        if (! $currentDbName) {
            return null;
        }

        foreach (Tenant::query()->sahodayas()->cursor() as $sahodaya) {
            if ($sahodaya->getInternal('db_name') === $currentDbName) {
                return $sahodaya;
            }
        }

        return null;
    }

    /**
     * School tenant IDs for a Sahodaya cluster (central tenants table).
     *
     * @return list<string>
     */
    public static function schoolIdsFor(string $sahodayaId): array
    {
        return Tenant::query()
            ->where('type', 'school')
            ->where('parent_id', $sahodayaId)
            ->pluck('id')
            ->all();
    }

    /**
     * School tenant IDs under a Sahodaya matching name or prefix search (central tenants table).
     *
     * @return list<string>
     */
    public static function schoolIdsMatchingSearch(string $sahodayaId, string $search): array
    {
        $query = Tenant::query()
            ->where('type', 'school')
            ->where('parent_id', $sahodayaId);

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('school_prefix', 'like', "%{$search}%");
            });
        }

        return $query->pluck('id')->all();
    }
}
