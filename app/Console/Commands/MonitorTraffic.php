<?php

namespace App\Console\Commands;

use App\Support\Monitoring\RequestTrace;
use Illuminate\Console\Command;

class MonitorTraffic extends Command
{
    protected $signature = 'monitor:traffic {action=start : start, stop, or status} {--minutes=15 : Capture duration in minutes}';

    protected $description = 'Capture HTTP URLs and SQL execution timings in daily JSONL logs';

    public function handle(): int
    {
        $action = $this->argument('action');
        if (! in_array($action, ['start', 'stop', 'status'], true)) {
            $this->error('Action must be start, stop, or status.');

            return self::FAILURE;
        }
        if ($action === 'start') {
            $minutes = filter_var($this->option('minutes'), FILTER_VALIDATE_INT);
            if ($minutes === false || $minutes < 1 || $minutes > 1440) {
                $this->error('Minutes must be an integer between 1 and 1440.');

                return self::FAILURE;
            }
            if (! is_writable(storage_path('logs')) || @file_put_contents(RequestTrace::statePath(), time() + $minutes * 60, LOCK_EX) === false) {
                $this->error('Monitoring requires writable storage/framework and storage/logs directories.');

                return self::FAILURE;
            }
        } elseif ($action === 'stop' && file_exists(RequestTrace::statePath())) {
            if (! @unlink(RequestTrace::statePath())) {
                $this->error('Unable to remove monitoring state file.');

                return self::FAILURE;
            }
        }
        $until = RequestTrace::until();
        $this->info($until > time() ? 'Monitoring active until '.date('c', $until) : 'Monitoring inactive.');
        $this->line('Logs: '.storage_path('logs/monitoring-YYYY-MM-DD.jsonl'));

        return self::SUCCESS;
    }
}
