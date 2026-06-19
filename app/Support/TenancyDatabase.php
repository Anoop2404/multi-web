<?php

namespace App\Support;

use App\Models\Tenant;
use App\Services\Tenancy\SahodayaDatabaseProvisioner;
use InvalidArgumentException;
use Stancl\Tenancy\Exceptions\TenantDatabaseDoesNotExistException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;

class TenancyDatabase
{
    public static function enabled(): bool
    {
        return (bool) config('tenancy.database_per_sahodaya', true);
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
}
