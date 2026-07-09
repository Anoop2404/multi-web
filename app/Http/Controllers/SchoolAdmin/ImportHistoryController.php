<?php

namespace App\Http\Controllers\SchoolAdmin;

use App\Models\UploadedFileBackup;
use App\Models\User;
use App\Support\TenantStorage;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ImportHistoryController extends SchoolAdminController
{
    private const PURPOSES = ['student_import', 'teacher_import'];

    public function index(): Response
    {
        $backups = UploadedFileBackup::where('school_id', $this->school->id)
            ->whereIn('purpose', self::PURPOSES)
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        $uploaderNames = User::whereIn('id', $backups->pluck('uploaded_by_user_id')->filter()->unique())
            ->pluck('name', 'id');

        $imports = $backups->map(fn (UploadedFileBackup $backup) => [
            'id' => $backup->id,
            'type' => $backup->purpose === 'student_import' ? 'Students' : 'Teachers',
            'original_name' => $backup->original_name,
            'status' => $backup->status,
            'total_rows' => $backup->total_rows,
            'imported_count' => $backup->imported_count,
            'error_count' => $backup->error_count,
            'errors' => $backup->errors,
            'uploaded_by' => $uploaderNames->get($backup->uploaded_by_user_id),
            'created_at' => $backup->created_at?->toIso8601String(),
        ])->values();

        return $this->inertia('School/Imports/History', [
            'imports' => $imports,
        ]);
    }

    public function download(string $tenantId, UploadedFileBackup $backup): BinaryFileResponse|StreamedResponse|HttpResponse
    {
        abort_unless($backup->school_id === $this->school->id, 404);
        abort_unless(in_array($backup->purpose, self::PURPOSES, true), 404);

        return TenantStorage::downloadPrivate($backup->storage_path, $backup->storage_disk, $backup->original_name);
    }
}
