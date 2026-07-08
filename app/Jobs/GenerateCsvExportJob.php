<?php

namespace App\Jobs;

use App\Models\ExportJob;
use App\Models\User;
use App\Services\Notifications\NotificationService;
use App\Support\TenantStorage;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class GenerateCsvExportJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $exportJobId,
        public string $payloadPath,
    ) {}

    public function handle(): void
    {
        $exportJob = ExportJob::find($this->exportJobId);
        if (! $exportJob) {
            return;
        }

        $exportJob->update(['status' => 'processing']);
        $disk = TenantStorage::uploadDisk();

        try {
            $raw = TenantStorage::get($this->payloadPath);
            if ($raw === null) {
                throw new \RuntimeException('Invalid export payload.');
            }

            $payload = json_decode($raw, true);
            if (! is_array($payload) || ! isset($payload['headers'], $payload['rows'])) {
                throw new \RuntimeException('Invalid export payload.');
            }

            $relativePath = 'exports/completed/'.$exportJob->id.'-'.$exportJob->filename;
            $handle = fopen('php://temp', 'r+');
            fputcsv($handle, $payload['headers']);
            foreach ($payload['rows'] as $row) {
                fputcsv($handle, $row);
            }
            rewind($handle);
            $csv = stream_get_contents($handle);
            fclose($handle);

            TenantStorage::put($relativePath, $csv, $disk);

            $exportJob->update([
                'status'        => 'completed',
                'file_path'     => $relativePath,
                'storage_disk'  => $disk,
                'completed_at'  => now(),
            ]);

            if (TenantStorage::exists($this->payloadPath)) {
                \Illuminate\Support\Facades\Storage::disk($disk)->delete($this->payloadPath);
            }

            $user = User::find($exportJob->user_id);
            if ($user) {
                app(NotificationService::class)->notify(
                    $user,
                    'Export ready',
                    "Your export ({$exportJob->filename}) is ready to download.",
                    route('exports.download', $exportJob),
                    ['in_app', 'email'],
                    'export.ready',
                );
            }
        } catch (\Throwable $e) {
            $exportJob->update([
                'status' => 'failed',
                'error'  => mb_substr($e->getMessage(), 0, 2000),
            ]);
        }
    }
}
