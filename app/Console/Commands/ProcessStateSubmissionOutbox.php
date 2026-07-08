<?php

namespace App\Console\Commands;

use App\Models\FestStateSubmissionOutbox;
use App\Models\FestStateProgram;
use App\Models\StateDomain;
use App\Services\State\StateSubmissionClient;
use Illuminate\Console\Command;

class ProcessStateSubmissionOutbox extends Command
{
    protected $signature = 'fest:process-state-outbox {--limit=20}';

    protected $description = 'Retry pending Sahodaya-to-State qualifier submissions';

    public function handle(StateSubmissionClient $client): int
    {
        $limit = (int) $this->option('limit');

        $rows = FestStateSubmissionOutbox::whereIn('status', ['pending', 'failed'])
            ->orderBy('created_at')
            ->limit($limit)
            ->get();

        foreach ($rows as $outbox) {
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

        return self::SUCCESS;
    }
}
