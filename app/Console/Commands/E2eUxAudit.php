<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

class E2eUxAudit extends Command
{
    protected $signature = 'e2e:ux-audit
                            {--seed : Seed demo data before running audit}
                            {--migrate : Run tenant migrations before audit}
                            {--no-fail : Exit 0 even when UX errors are found}';

    protected $description = 'Run full Playwright UX audit across all pages and write report to tests/e2e/report/';

    public function handle(): int
    {
        if ($this->option('migrate')) {
            $this->info('Running tenant migrations…');
            $this->call('tenants:migrate', ['--force' => true]);
        }

        if ($this->option('seed')) {
            $this->info('Seeding E2E demo data…');
            $this->call('e2e:seed-data');
            $this->call('e2e:provision-users');
        }

        if (! file_exists(public_path('build/manifest.json'))) {
            $this->info('Building frontend assets for stable E2E runs…');
            $build = new Process(['npm', 'run', 'build'], base_path(), null, null, 600);
            $build->run(function ($type, $buffer) {
                $this->output->write($buffer);
            });
            if (! $build->isSuccessful()) {
                $this->error('Frontend build failed.');

                return self::FAILURE;
            }
        }

        $this->info('Starting Playwright full UX audit…');
        $this->line('Reports will be written to storage/app/ux-audit/ux-audit.json');

        $env = null;
        if ($this->option('no-fail')) {
            $env = array_merge(is_array(getenv()) ? getenv() : [], ['E2E_AUDIT_NO_FAIL' => '1']);
        }

        $process = new Process(
            ['npm', 'run', 'test:e2e:audit'],
            base_path(),
            $env,
            null,
            3600,
        );

        $process->run(function ($type, $buffer) {
            $this->output->write($buffer);
        });

        $reportPath = storage_path('app/ux-audit/ux-audit.json');
        if (file_exists($reportPath)) {
            $report = json_decode(file_get_contents($reportPath), true);
            $summary = $report['summary'] ?? [];
            $this->newLine();
            $this->table(
                ['Metric', 'Count'],
                [
                    ['Pages visited', $summary['pagesVisited'] ?? 0],
                    ['UX errors', $summary['errors'] ?? 0],
                    ['UX warnings', $summary['warnings'] ?? 0],
                    ['Console errors', $summary['consoleErrors'] ?? 0],
                ],
            );

            if (($summary['errors'] ?? 0) > 0 && ! $this->option('no-fail')) {
                $this->error('UX audit found critical issues — see storage/app/ux-audit/ux-audit.md');

                return self::FAILURE;
            }
        }

        if (! $process->isSuccessful() && ! $this->option('no-fail')) {
            return self::FAILURE;
        }

        $this->info('UX audit complete.');

        return self::SUCCESS;
    }
}
