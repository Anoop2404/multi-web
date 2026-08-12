<?php

namespace App\Console\Commands;

use App\Models\FestStateSubmissionOutbox;
use App\Models\FestStateProgram;
use App\Models\StateDomain;
use App\Services\State\StateSubmissionClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ProcessStateSubmissionOutbox extends Command
{
    protected $signature = 'fest:process-state-outbox {--limit=20}';

    protected $description = 'Retry pending Sahodaya-to-State qualifier submissions';

    /**
     * LIFE-12 fix (functional audit, 2026-08-11/12): StateSubmissionClient::send()
     * always catches and records failures on the outbox row rather than throwing (see
     * that class's doc), so this command — running every 5 minutes via
     * routes/console.php — was the ONLY retry mechanism, with no cap: a row pointing at
     * a permanently broken/misconfigured State domain would be retried forever, every 5
     * minutes, with nothing ever surfacing that it needs a human. Rows at or beyond this
     * many attempts are now skipped and logged instead of retried indefinitely.
     */
    private const MAX_ATTEMPTS = 10;

    public function handle(StateSubmissionClient $client): int
    {
        $limit = (int) $this->option('limit');

        $rows = FestStateSubmissionOutbox::whereIn('status', ['pending', 'failed'])
            ->orderBy('created_at')
            ->limit($limit)
            ->get();

        $stuck = 0;

        foreach ($rows as $outbox) {
            if ($outbox->attempts >= self::MAX_ATTEMPTS) {
                $stuck++;
                $this->warn("Skipping {$outbox->id}: {$outbox->attempts} attempts exceeds cap of ".self::MAX_ATTEMPTS.' — needs manual review.');
                continue;
            }

            $program = FestStateProgram::find($outbox->state_program_id);
            if (! $program?->state_domain_id) {
                $this->warn("Skipping {$outbox->id}: no state domain.");
                continue;
            }

            $domain = StateDomain::find($program->state_domain_id);
            if (! $domain) {
                $this->warn("Skipping {$outbox->id}: domain missing.");
                continue;
            }

            $client->send($outbox, $domain);
            $this->line("Processed {$outbox->id}: {$outbox->fresh()->status}");
        }

        if ($stuck > 0) {
            Log::warning("fest:process-state-outbox: {$stuck} outbox row(s) exceeded the retry cap and need manual review.", [
                'max_attempts' => self::MAX_ATTEMPTS,
            ]);
        }

        return self::SUCCESS;
    }
}
