<?php

namespace App\Jobs;

use App\Models\FestStateSubmissionOutbox;
use App\Models\FestStateProgram;
use App\Models\StateDomain;
use App\Services\State\StateSubmissionClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SubmitStateQualifiersJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public string $outboxId,
    ) {}

    public function handle(StateSubmissionClient $client): void
    {
        $outbox = FestStateSubmissionOutbox::find($this->outboxId);
        if (! $outbox) {
            return;
        }

        $program = FestStateProgram::find($outbox->state_program_id);
        $domain = $program?->state_domain_id ? StateDomain::find($program->state_domain_id) : null;

        if ($domain) {
            $client->send($outbox, $domain);
        }
    }
}
