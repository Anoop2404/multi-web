<?php

namespace App\Console\Commands;

use App\Services\ExamEngine\ExamEngineClient;
use App\Services\ExamEngine\ExamEngineException;
use App\Services\ExamEngine\ExamEngineEventProcessor;
use Illuminate\Console\Command;

/**
 * Pulls exam engine events and applies them (exam-engine/CONTRACT.md §6). Runs under supervisor
 * as `exam-engine-consumer` (exam-engine/deploy/supervisor/exam-engine-consumer.ini).
 */
class ExamEngineConsume extends Command
{
    protected $signature = 'exam-engine:consume
        {--consumer= : Consumer name (one per process)}
        {--batch=200 : Events per claim}
        {--once : Process one batch and exit}
        {--max-seconds=3600 : Exit after this long so supervisor restarts with fresh code}';

    protected $description = 'Apply online exam engine events (check-ins, submissions, proctor signals) to Sahodaya databases';

    public function handle(ExamEngineClient $client, ExamEngineEventProcessor $processor): int
    {
        if (! $client->isConfigured()) {
            $this->error('EXAM_ENGINE_INTERNAL_URL / EXAM_ENGINE_INTERNAL_TOKEN are not set.');

            return self::FAILURE;
        }

        $consumer = (string) ($this->option('consumer') ?: gethostname().'-'.getmypid());
        $deadline = time() + max(10, (int) $this->option('max-seconds'));
        $backoff = 1;

        do {
            try {
                $events = $client->claimEvents($consumer, max(1, (int) $this->option('batch')), $this->option('once') ? 0 : 2000);
                $handled = $processor->process($events);
                $client->ackEvents($handled);

                foreach ($processor->pendingResults() as $run => $results) {
                    $client->putResults($run, $results);
                }

                if ($events !== []) {
                    $this->line(sprintf('[%s] %d event(s), %d applied', now()->toTimeString(), count($events), count($handled)));
                }
                $backoff = 1;
            } catch (ExamEngineException $e) {
                $this->warn('Exam engine unavailable: '.$e->getMessage().' — retrying in '.$backoff.'s');
                if ($this->option('once')) {
                    return self::FAILURE;
                }
                sleep($backoff);
                $backoff = min(30, $backoff * 2);
            }
        } while (! $this->option('once') && time() < $deadline);

        return self::SUCCESS;
    }
}
