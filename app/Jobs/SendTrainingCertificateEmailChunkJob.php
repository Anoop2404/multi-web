<?php

namespace App\Jobs;

use App\Models\CertificateBatch;
use App\Models\Tenant;
use App\Models\TrainingRegistration;
use App\Services\Training\TrainingCertificateService;
use App\Support\TenancyDatabase;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\ConnectionException;

/**
 * Emails one ~150-registration slice of a CertificateBatch bulk-send run (see
 * TrainingProgramController::dispatchSendEmailBatch(), which chunks and dispatches these
 * via Bus::batch()). Each registration's certificate is rendered-or-served-from-cache via
 * TrainingCertificateService::sendCertificateEmailToRegistration(), closing the gap where
 * TrainingProgramController::bulkSendCertificatesEmail() previously did all of this
 * synchronously in one HTTP request and timed out past a couple hundred teachers.
 */
class SendTrainingCertificateEmailChunkJob implements ShouldQueue
{
    use Batchable, Queueable;

    // Same rationale as RenderCertificateChunkJob::$timeout — a queue worker, not a web
    // request. The queue connection's retry_after must stay above this or Laravel will
    // consider a still-running chunk "lost" and re-dispatch it mid-send.
    public int $timeout = 1800;

    public int $tries = 3;

    // Consecutive connection failures to the PDF renderer within one chunk before giving
    // up on the rest of it and letting the job's own retry/backoff take over — see
    // RenderCertificateChunkJob for the identical rationale.
    private const MAX_CONSECUTIVE_CONNECTION_FAILURES = 3;

    public function __construct(
        public int $certificateBatchId,
        public array $registrationIds,
        public string $tenantId,
    ) {}

    /** @return list<int> */
    public function backoff(): array
    {
        return [30, 120, 300];
    }

    public function handle(TrainingCertificateService $service): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $tenant = Tenant::find($this->tenantId);
        if (! $tenant) {
            return;
        }

        TenancyDatabase::withTenantDatabase($tenant, function () use ($service, $tenant) {
            $this->sendChunk($service, $tenant);
        });
    }

    private function sendChunk(TrainingCertificateService $service, Tenant $sahodaya): void
    {
        $batch = CertificateBatch::find($this->certificateBatchId);
        if (! $batch || $batch->status === CertificateBatch::STATUS_CANCELLED) {
            return;
        }

        $registrations = TrainingRegistration::whereIn('id', $this->registrationIds)
            ->with(['teacher', 'school', 'program'])
            ->get();

        $succeeded = 0;
        $failed = 0;
        $failedItems = [];
        $consecutiveConnectionFailures = 0;
        $processedBeforeAbort = 0;

        foreach ($registrations as $registration) {
            $processedBeforeAbort++;

            try {
                $sent = $service->sendCertificateEmailToRegistration($registration, $sahodaya);

                if ($sent) {
                    $succeeded++;
                    $consecutiveConnectionFailures = 0;
                } else {
                    $failed++;
                    $reason = $registration->teacher?->email
                        ? 'Send failed — see application logs.'
                        : 'No email address on file for this teacher.';
                    $failedItems[] = $this->failureEntry($registration, $reason);
                }
            } catch (ConnectionException $e) {
                $consecutiveConnectionFailures++;

                if ($consecutiveConnectionFailures >= self::MAX_CONSECUTIVE_CONNECTION_FAILURES) {
                    // The render service itself looks down, not this one registration —
                    // record progress for what actually ran, then let tries/backoff retry
                    // the remainder as a fresh chunk attempt once it recovers, rather than
                    // mass-recording everything still unattempted as individually failed.
                    $batch->recordChunkResult($processedBeforeAbort, $succeeded, $failed);
                    if ($failedItems) {
                        $batch->appendFailedItems($failedItems);
                    }

                    throw $e;
                }

                $failed++;
                $failedItems[] = $this->failureEntry($registration, $e->getMessage());
            } catch (\Throwable $e) {
                $failed++;
                $failedItems[] = $this->failureEntry($registration, $e->getMessage());
            }
        }

        $batch->recordChunkResult(count($registrations), $succeeded, $failed);
        if ($failedItems) {
            $batch->appendFailedItems($failedItems);
        }
    }

    /** @return array{registration_id: int, name: string, reason: string} */
    private function failureEntry(TrainingRegistration $registration, string $reason): array
    {
        return [
            'registration_id' => $registration->id,
            'name' => $registration->teacher?->name ?? 'Registration #'.$registration->id,
            'reason' => mb_substr($reason, 0, 500),
        ];
    }
}
