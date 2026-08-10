<?php

namespace App\Console\Commands\State;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class CheckStateHealth extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'state:health';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Perform health check on the State isolated database, migrations, domain and configuration';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Checking State Platform Health...');

        $connectionName = config('state.connection', 'state');
        $domain = config('state.domain');
        $healthResults = [];
        $isHealthy = true;

        // 1. Check DB Connection
        try {
            DB::connection($connectionName)->getPdo();
            $healthResults[] = ['Component' => 'State DB Connection', 'Status' => 'OK', 'Details' => "Connected to '{$connectionName}'"];
        } catch (Throwable $e) {
            $isHealthy = false;
            $healthResults[] = ['Component' => 'State DB Connection', 'Status' => 'FAIL', 'Details' => $e->getMessage()];
        }

        // 2. Check Migrations / Tables
        try {
            $hasIntakesTable = Schema::connection($connectionName)->hasTable('state_qualifier_intakes');
            if ($hasIntakesTable) {
                $healthResults[] = ['Component' => 'State Migrations', 'Status' => 'OK', 'Details' => 'state_qualifier_intakes table present'];
            } else {
                $isHealthy = false;
                $healthResults[] = ['Component' => 'State Migrations', 'Status' => 'WARN', 'Details' => 'State tables missing. Run php artisan state:migrate'];
            }
        } catch (Throwable $e) {
            $isHealthy = false;
            $healthResults[] = ['Component' => 'State Migrations', 'Status' => 'FAIL', 'Details' => $e->getMessage()];
        }

        // 3. Check Domain Config
        $healthResults[] = [
            'Component' => 'State App Domain',
            'Status'    => $domain ? 'OK' : 'WARN',
            'Details'   => $domain ?: 'STATE_APP_DOMAIN not configured',
        ];

        // 4. Check Storage / Queue
        $queueConn = config('state.queue_connection');
        $cachePrefix = config('state.cache_prefix');
        $healthResults[] = [
            'Component' => 'State Queue & Cache',
            'Status'    => 'OK',
            'Details'   => "Queue: {$queueConn}, Cache prefix: {$cachePrefix}",
        ];

        $this->table(['Component', 'Status', 'Details'], $healthResults);

        if ($isHealthy) {
            $this->info('State Platform Health Check: PASSED');
            return Command::SUCCESS;
        }

        $this->error('State Platform Health Check: FAILED or INCOMPLETE');
        return Command::FAILURE;
    }
}
