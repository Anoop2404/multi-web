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
use Illuminate\Support\Facades\Log;

/**
 * LIFE-12 fix (functional audit, 2026-08-11/12): StateSubmissionClient::send() below
 * deliberately catches every exception and records it on the outbox row instead of
 * re-throwing — that's intentional (see the class doc there), because
 * fest:process-state-outbox (routes/console.php, every 5 minutes) is the actual retry
 * mechanism for 'pending'/'failed' rows, not Laravel's queue retry system. $tries/
 * backoff/failed() below are defense-in-depth for the one path that DOES still throw
 * out of send() — StateDomain::api_base_url being unconfigured (abort_if() before the
 * try/catch) — so that specific misconfiguration at least reaches Laravel's normal
 * failed-job/alerting path instead of silently vanishing.
 */
class SubmitStateQualifiersJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        public string $outboxId,
    ) {}

    /** @return list<int> seconds to wait before each retry */
    public function backoff(): array
    {
        return [60, 300, 900];
    }

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

    public function failed(?\Throwable $exception): void
    {
        Log::error('SubmitStateQualifiersJob permanently failed', [
            'outbox_id' => $this->outboxId,
            'error'     => $exception?->getMessage(),
        ]);
    }
}
