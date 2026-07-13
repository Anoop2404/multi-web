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
        $central = (string) config('tenancy.database.central_connection', 'central');
        $previousDefault = config('database.default');
        $template = config("database.connections.{$central}")
            ?? config('database.connections.'.($previousDefault ?: 'pgsql'));

        if (! is_array($template)) {
            throw new InvalidArgumentException("No database connection template found for tenant runtime.");
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
        if (! self::enabled()) {
            return $callback();
        }

        if (tenancy()->initialized) {
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

        if ($tenant->type === 'school' && $tenant->parent_id) {
            $parent = Tenant::query()->find($tenant->parent_id);

            if ($parent?->type === 'sahodaya') {
                return $parent;
            }
        }

        throw new InvalidArgumentException('No Sahodaya database owner for tenant '.$tenant->id);
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
        if (! self::enabled()) {
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
        if (! self::enabled()) {
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
