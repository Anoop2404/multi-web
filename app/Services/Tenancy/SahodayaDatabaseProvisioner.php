<?php

namespace App\Services\Tenancy;

use App\Models\SahodayaProfile;
use App\Models\Tenant;
use App\Support\SahodayaSiteTemplate;
use App\Support\TenancyDatabase;
use InvalidArgumentException;
use RuntimeException;
use Stancl\Tenancy\Jobs\MigrateDatabase;

class SahodayaDatabaseProvisioner
{
    public function suggestedName(Tenant $sahodaya): string
    {
        return config('tenancy.database.prefix').str_replace('-', '_', $sahodaya->getTenantKey());
    }

    public function configure(
        Tenant $sahodaya,
        string $databaseName,
        ?string $username = null,
        ?string $password = null,
        bool $clearPassword = false,
    ): void {
        $this->assertSahodaya($sahodaya);

        $databaseName = strtolower(trim($databaseName));

        $sahodaya->setInternal('db_name', $databaseName);
        $sahodaya->setInternal('create_database', false);

        // Username/password are optional — blank keeps (or restores) the central DB login.
        if ($username !== null) {
            $username = trim($username);
            if ($username !== '') {
                $sahodaya->setInternal('db_username', $username);
            } else {
                $this->forgetInternal($sahodaya, 'db_username');
            }
        }

        if ($clearPassword) {
            $this->forgetInternal($sahodaya, 'db_password');
        } elseif ($password !== null && $password !== '') {
            $sahodaya->setInternal('db_password', $password);
        }

        $sahodaya->save();
        $this->syncConnectionToSchools($sahodaya);
    }

    /** Keep member schools on the same DB name + login as the Sahodaya owner. */
    private function syncConnectionToSchools(Tenant $sahodaya): void
    {
        $dbName = $sahodaya->getInternal('db_name');
        $username = $sahodaya->getInternal('db_username');
        $password = $sahodaya->getInternal('db_password');

        Tenant::query()
            ->where('type', 'school')
            ->where('parent_id', $sahodaya->id)
            ->each(function (Tenant $school) use ($dbName, $username, $password) {
                $school->setInternal('create_database', false);
                $school->setInternal('db_name', $dbName);
                $this->applyOptionalCredential($school, 'db_username', $username);
                $this->applyOptionalCredential($school, 'db_password', $password);
                $school->save();
            });
    }

    private function applyOptionalCredential(Tenant $tenant, string $key, mixed $value): void
    {
        if (filled($value)) {
            $tenant->setInternal($key, $value);
        } else {
            $this->forgetInternal($tenant, $key);
        }
    }

    /** Remove a tenancy_* key so it is not serialized as null into the data column. */
    private function forgetInternal(Tenant $tenant, string $key): void
    {
        $tenant->offsetUnset($tenant::internalPrefix().$key);
    }

    public function ensureConfigured(Tenant $sahodaya): void
    {
        if (! $sahodaya->getInternal('db_name')) {
            $this->configure($sahodaya, $this->suggestedName($sahodaya));
        }
    }

    public function ensureDatabaseExists(Tenant $sahodaya): void
    {
        $this->assertSahodaya($sahodaya);
        $this->ensureConfigured($sahodaya);

        $sahodaya->database()->makeCredentials();
        $manager = $sahodaya->database()->manager();
        $name = $sahodaya->database()->getName();

        if (! $manager->databaseExists($name)) {
            $manager->createDatabase($sahodaya);
        }
    }

    public function ensureReady(Tenant $sahodaya, bool $seedDefaults = false, ?bool $createIfMissing = null): void
    {
        $createIfMissing ??= (bool) config('tenancy.auto_create_sahodaya_database', false);

        $this->ensureConfigured($sahodaya);

        if ($createIfMissing) {
            $this->ensureDatabaseExists($sahodaya);
        }

        $status = $this->status($sahodaya);

        if (! $status['exists']) {
            throw new RuntimeException(
                "Database \"{$status['name']}\" was not found. Create it in PostgreSQL, or run: php artisan sahodaya:provision-databases --tenant={$sahodaya->id} --create"
            );
        }

        $this->migrate($sahodaya, $seedDefaults);
    }

    /** @return array{configured: bool, name: ?string, exists: bool, ready: bool, username: ?string, has_password: bool} */
    public function status(Tenant $sahodaya): array
    {
        $this->assertSahodaya($sahodaya);

        if (! config('tenancy.database_per_sahodaya', true)) {
            return [
                'configured'   => false,
                'name'         => null,
                'exists'       => false,
                'ready'        => true,
                'username'     => null,
                'has_password' => false,
            ];
        }

        $name = $sahodaya->getInternal('db_name') ?: null;
        $exists = false;
        $ready = false;

        if ($name) {
            $exists = $sahodaya->database()->manager()->databaseExists($name);
            $ready = $exists && $this->schemaIsReady($sahodaya, $name);
        }

        return [
            'configured'   => (bool) $name,
            'name'         => $name,
            'exists'       => $exists,
            'ready'        => $ready,
            'username'     => $sahodaya->getInternal('db_username') ?: null,
            'has_password' => filled($sahodaya->getInternal('db_password')),
        ];
    }

    public function migrate(Tenant $sahodaya, bool $seedDefaults = false): void
    {
        $this->assertSahodaya($sahodaya);

        if (! config('tenancy.database_per_sahodaya', true)) {
            throw new RuntimeException('Dedicated Sahodaya databases are disabled.');
        }

        $status = $this->status($sahodaya);

        if (! $status['configured']) {
            throw new RuntimeException('Set a database name before running migrations.');
        }

        if (! $status['exists']) {
            throw new RuntimeException("Database \"{$status['name']}\" was not found. Create it in PostgreSQL first.");
        }

        (new MigrateDatabase($sahodaya))->handle();

        // Roles/permissions are required before any portal admin can be created.
        $sahodaya->run(function () {
            (new \Database\Seeders\TenantRolesAndPermissionsSeeder)->run();
        });

        if ($seedDefaults) {
            $this->seedDefaults($sahodaya);
        }
    }

    public function migrateAll(bool $seedDefaults = false): int
    {
        $count = 0;

        Tenant::query()->where('type', 'sahodaya')->orderBy('name')->each(function (Tenant $sahodaya) use ($seedDefaults, &$count) {
            $status = $this->status($sahodaya);
            if (! $status['configured'] || ! $status['exists']) {
                return;
            }

            $this->migrate($sahodaya, $seedDefaults);
            $count++;
        });

        return $count;
    }

    private function schemaIsReady(Tenant $sahodaya, string $databaseName): bool
    {
        try {
            return TenancyDatabase::usingDatabase(
                $databaseName,
                fn () => \Illuminate\Support\Facades\Schema::hasTable('sahodaya_profiles')
                    && \Illuminate\Support\Facades\Schema::hasTable('roles')
                    && \Illuminate\Support\Facades\Schema::hasTable('users'),
                TenancyDatabase::credentialsFor($sahodaya),
            );
        } catch (\Throwable) {
            return false;
        }
    }

    private function seedDefaults(Tenant $sahodaya): void
    {
        $sahodaya->run(function () use ($sahodaya) {
            SahodayaProfile::firstOrCreate(
                ['tenant_id' => $sahodaya->id],
                [
                    'student_data_mode'   => 'not_required',
                    'membership_fee_type' => 'fixed',
                ]
            );

            if ($sahodaya->sections()->count() === 0) {
                SahodayaSiteTemplate::apply($sahodaya);
            }
        });
    }

    private function assertSahodaya(Tenant $sahodaya): void
    {
        if ($sahodaya->type !== 'sahodaya') {
            throw new InvalidArgumentException('Only Sahodaya tenants use a dedicated database.');
        }
    }
}
