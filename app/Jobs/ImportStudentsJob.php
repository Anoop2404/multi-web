<?php

namespace App\Jobs;

use App\Models\Tenant;
use App\Models\UploadedFileBackup;
use App\Models\User;
use App\Services\Audit\DataChangeLogger;
use App\Services\Notifications\NotificationService;
use App\Services\Students\StudentCsvImporter;
use App\Support\TenancyDatabase;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use App\Support\TenantStorage;

class ImportStudentsJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $schoolId,
        public string $storagePath,
        public int $userId,
        public ?int $backupId = null,
        public ?string $storageDisk = null,
    ) {}

    public function handle(): void
    {
        $school = Tenant::find($this->schoolId);
        $user = User::find($this->userId);

        if (! $school || ! $user) {
            return;
        }

        $tmp = null;
        try {
            $tmp = TenantStorage::localTempPath($this->storagePath, $this->storageDisk);
            $result = TenancyDatabase::withTenantDatabase($school, function () use ($school, $tmp) {
                $result = (new StudentCsvImporter($school))->importFromPath($tmp);

                if ($this->backupId) {
                    UploadedFileBackup::whereKey($this->backupId)->update([
                        'status'         => $result['success'] ? UploadedFileBackup::STATUS_SUCCESS : UploadedFileBackup::STATUS_FAILED,
                        'total_rows'     => $result['imported'] + $result['skipped'],
                        'imported_count' => $result['imported'],
                        'error_count'    => count($result['errors']),
                        'errors'         => array_slice($result['errors'], 0, 50),
                    ]);
                }

                return $result;
            });
        } finally {
            if ($tmp && str_starts_with($tmp, sys_get_temp_dir())) {
                @unlink($tmp);
            }
        }

        app(DataChangeLogger::class)->event(
            'imported',
            "Student CSV import (queued): {$result['imported']} added, {$result['skipped']} skipped",
            $this->schoolId,
            'students',
            null,
            [
                'imported'  => $result['imported'],
                'skipped'   => $result['skipped'],
                'errors'    => count($result['errors']),
                'backup_id' => $this->backupId,
                'queued'    => true,
            ],
        );

        $message = $result['success']
            ? "Student import finished: {$result['imported']} added"
            : 'Student import rejected: '.count($result['errors']).' error(s) found, nothing was imported';

        app(NotificationService::class)->notify(
            $user,
            $result['success'] ? 'Student import complete' : 'Student import rejected',
            $message,
            "/school-admin/{$this->schoolId}/students?bulk=1",
            ['in_app', 'email'],
        );
    }
}
