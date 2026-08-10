<?php

namespace App\Console\Commands\State;

use Illuminate\Console\Command;

class MigrateStateDatabase extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'state:migrate {--step : Force the migrations to be run so they can be rolled back individually} {--force : Force the operation to run when in production}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run migrations specifically for the dedicated State database connection';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Running State database migrations...');

        $options = [
            '--database' => config('state.connection', 'state'),
            '--path'     => 'database/migrations/state',
        ];

        if ($this->option('step')) {
            $options['--step'] = true;
        }

        if ($this->option('force')) {
            $options['--force'] = true;
        }

        return $this->call('migrate', $options);
    }
}
