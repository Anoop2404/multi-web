<?php

namespace App\Jobs;

use App\Services\Storage\LegacyStorageMigrationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class MigrateLegacyUploadsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 3600;

    public function __construct(
        public ?string $sahodayaId,
        public bool $deleteLocal = false,
        public bool $includeFilesystem = false,
        public string $cacheKey = '',
    ) {
        $this->cacheKey = $cacheKey ?: 'storage_migration_'.uniqid();
    }

    public function handle(LegacyStorageMigrationService $migration): void
    {
        Cache::put($this->cacheKey, ['status' => 'running', 'started_at' => now()->toIso8601String()], 86400);

        try {
            $result = $migration->migrate(
                $this->sahodayaId,
                false,
                $this->deleteLocal,
                $this->includeFilesystem,
            );

            Cache::put($this->cacheKey, [
                'status'     => 'completed',
                'finished_at'=> now()->toIso8601String(),
                'result'     => $result,
            ], 86400);
        } catch (\Throwable $e) {
            Log::error('Legacy S3 migration failed', ['error' => $e->getMessage(), 'tenant' => $this->sahodayaId]);
            Cache::put($this->cacheKey, [
                'status'  => 'failed',
                'error'   => $e->getMessage(),
            ], 86400);

            throw $e;
        }
    }
}
