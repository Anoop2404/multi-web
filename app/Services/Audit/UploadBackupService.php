<?php

namespace App\Services\Audit;

use App\Models\UploadedFileBackup;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

class UploadBackupService
{
    public function store(
        UploadedFile $file,
        string $purpose,
        ?string $schoolId = null,
        ?Model $related = null,
        ?int $userId = null,
        array $metadata = [],
    ): UploadedFileBackup {
        $safeName = preg_replace('/[^a-zA-Z0-9._-]/', '_', $file->getClientOriginalName()) ?: 'upload.bin';
        $subdir = $schoolId
            ? "backups/schools/{$schoolId}/{$purpose}/".now()->format('Y-m')
            : "backups/general/{$purpose}/".now()->format('Y-m');

        $path = $file->storeAs($subdir, Str::uuid().'_'.$safeName, 'local');

        return UploadedFileBackup::create([
            'school_id'            => $schoolId,
            'purpose'              => $purpose,
            'storage_disk'         => 'local',
            'storage_path'         => $path,
            'original_name'        => $file->getClientOriginalName(),
            'mime_type'            => $file->getClientMimeType(),
            'size_bytes'           => $file->getSize(),
            'related_type'         => $related?->getMorphClass(),
            'related_id'           => $related?->getKey(),
            'uploaded_by_user_id'  => $userId,
            'metadata'             => $metadata ?: null,
        ]);
    }
}
