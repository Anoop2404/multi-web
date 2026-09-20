<?php

namespace App\Console\Commands;

use App\Models\Student;
use App\Models\Teacher;
use App\Models\Tenant;
use Illuminate\Console\Command;

class WarmPhotoCache extends Command
{
    protected $signature = 'photos:warm-cache
                            {--sahodaya= : Optional Sahodaya tenant ID (defaults to all active Sahodayas)}
                            {--type=both : student|teacher|both}
                            {--chunk=100 : Rows fetched per chunk}
                            {--sleep-ms=0 : Milliseconds to sleep between each photo, to throttle CPU/S3 load while warming}';

    protected $description = 'Pre-warm the Student/Teacher photoDataUri() cache so cold S3 fetch + image resize never happens during live public traffic';

    public function handle(): int
    {
        $type = $this->option('type');
        $chunkSize = max(1, (int) $this->option('chunk'));
        $sleepMs = max(0, (int) $this->option('sleep-ms'));

        $sahodayaOpt = $this->option('sahodaya');
        $sahodayas = $sahodayaOpt
            ? Tenant::query()->sahodayas()->whereKey($sahodayaOpt)->get()
            : Tenant::query()->sahodayas()->where('is_active', true)->get();

        if ($sahodayas->isEmpty()) {
            $this->error('No matching Sahodaya tenant found.');

            return self::FAILURE;
        }

        $totalWarmed = 0;

        foreach ($sahodayas as $sahodaya) {
            $sahodaya->run(function () use ($type, $chunkSize, $sleepMs, $sahodaya, &$totalWarmed) {
                $warmedForTenant = 0;

                if (in_array($type, ['student', 'both'], true)) {
                    Student::query()
                        ->whereNotNull('photo')->where('photo', '!=', '')
                        ->chunkById($chunkSize, function ($students) use ($sleepMs, &$warmedForTenant) {
                            foreach ($students as $student) {
                                $student->photoDataUri();
                                $warmedForTenant++;
                                if ($sleepMs > 0) {
                                    usleep($sleepMs * 1000);
                                }
                            }
                        });
                }

                if (in_array($type, ['teacher', 'both'], true)) {
                    Teacher::query()
                        ->whereNotNull('photo')->where('photo', '!=', '')
                        ->chunkById($chunkSize, function ($teachers) use ($sleepMs, &$warmedForTenant) {
                            foreach ($teachers as $teacher) {
                                $teacher->photoDataUri();
                                $warmedForTenant++;
                                if ($sleepMs > 0) {
                                    usleep($sleepMs * 1000);
                                }
                            }
                        });
                }

                $totalWarmed += $warmedForTenant;
                $this->info("{$sahodaya->name}: warmed {$warmedForTenant} photo(s).");
            });
        }

        $this->info("Done. Warmed {$totalWarmed} photo(s) across {$sahodayas->count()} Sahodaya(s).");

        return self::SUCCESS;
    }
}
