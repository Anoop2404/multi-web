<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Schema;
use App\Models\Tenant;
use App\Services\Tenancy\SahodayaDatabaseProvisioner;

class PlatformReadinessCheck extends Command
{
    protected $signature = 'platform:readiness {--json : Output machine-readable JSON}';

    protected $description = 'Verify production readiness (Redis, queue, database, tenancy).';

    public function handle(): int
    {
        $checks = [
            'redis_sessions' => $this->checkRedisSessions(),
            'redis_cache'    => $this->checkRedisCache(),
            'redis_queue'    => $this->checkRedisQueue(),
            'central_db'     => $this->checkCentralDatabase(),
            'sahodaya_databases' => $this->checkSahodayaDatabases(),
            'tenant_users_migrated' => $this->checkTenantUsersReady(),
        ];

        $failed = collect($checks)->filter(fn ($c) => ! ($c['ok'] ?? false));

        if ($this->option('json')) {
            $this->line(json_encode([
                'ok'     => $failed->isEmpty(),
                'checks' => $checks,
            ], JSON_PRETTY_PRINT));
        } else {
            foreach ($checks as $name => $check) {
                $icon = ($check['ok'] ?? false) ? '✓' : '✗';
                $this->line("{$icon} {$name}: {$check['message']}");
            }
        }

        return $failed->isEmpty() ? self::SUCCESS : self::FAILURE;
    }

    /** @return array{ok: bool, message: string} */
    private function checkRedisSessions(): array
    {
        $driver = (string) config('session.driver');

        if ($driver !== 'redis') {
            return ['ok' => false, 'message' => "SESSION_DRIVER is '{$driver}' — set redis in production."];
        }

        return $this->pingRedis('session redis');
    }

    /** @return array{ok: bool, message: string} */
    private function checkRedisCache(): array
    {
        $store = (string) config('cache.default');

        if ($store !== 'redis') {
            return ['ok' => false, 'message' => "CACHE_STORE is '{$store}' — set redis in production."];
        }

        try {
            Cache::store('redis')->put('platform_readiness_probe', 'ok', 10);

            return ['ok' => Cache::store('redis')->get('platform_readiness_probe') === 'ok', 'message' => 'redis cache writable'];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    /** @return array{ok: bool, message: string} */
    private function checkRedisQueue(): array
    {
        $connection = (string) config('queue.default');

        if ($connection !== 'redis') {
            return ['ok' => false, 'message' => "QUEUE_CONNECTION is '{$connection}' — set redis in production."];
        }

        try {
            Queue::connection('redis')->size();

            return ['ok' => true, 'message' => 'redis queue reachable'];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    /** @return array{ok: bool, message: string} */
    private function checkCentralDatabase(): array
    {
        try {
            $central = DB::connection(config('tenancy.database.central_connection', 'central'));
            $central->select('select 1');

            return [
                'ok'      => Schema::connection($central->getName())->hasTable('users'),
                'message' => 'central database connected',
            ];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    /** @return array{ok: bool, message: string} */
    private function checkTenantUsersReady(): array
    {
        if (! config('tenancy.database_per_sahodaya', true)) {
            return ['ok' => true, 'message' => 'single-database mode — skip tenant user check'];
        }

        $central = DB::connection(config('tenancy.database.central_connection', 'central'));
        $tenantUsers = (int) $central->table('users')->whereNotNull('tenant_id')->count();

        if ($tenantUsers === 0) {
            return ['ok' => true, 'message' => 'portal users already in tenant databases'];
        }

        return [
            'ok'      => false,
            'message' => "{$tenantUsers} portal user(s) still in central — run users:migrate-to-tenant-databases",
        ];
    }

    /** @return array{ok: bool, message: string} */
    private function checkSahodayaDatabases(): array
    {
        if (! config('tenancy.database_per_sahodaya', true)) {
            return ['ok' => true, 'message' => 'single-database mode — skip Sahodaya DB check'];
        }

        $provisioner = app(SahodayaDatabaseProvisioner::class);
        $missing = [];
        $notReady = [];

        Tenant::query()
            ->where('type', 'sahodaya')
            ->orderBy('name')
            ->get()
            ->each(function (Tenant $tenant) use ($provisioner, &$missing, &$notReady) {
                $status = $provisioner->status($tenant);
                if (! $status['exists']) {
                    $missing[] = "{$tenant->name} ({$status['name']})";

                    return;
                }
                if (! $status['ready']) {
                    $notReady[] = "{$tenant->name} ({$status['name']})";
                }
            });

        if ($missing !== []) {
            return [
                'ok' => false,
                'message' => 'missing DB: '.implode(', ', $missing).' — run sahodaya:provision-databases --create --seed',
            ];
        }

        if ($notReady !== []) {
            return [
                'ok' => false,
                'message' => 'DB not migrated/seeded: '.implode(', ', $notReady).' — run sahodaya:provision-databases --seed',
            ];
        }

        return ['ok' => true, 'message' => 'all Sahodaya databases exist and have tenant schema'];
    }

    /** @return array{ok: bool, message: string} */
    private function pingRedis(string $label): array
    {
        try {
            Redis::connection()->ping();

            return ['ok' => true, 'message' => "{$label} reachable"];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }
}
