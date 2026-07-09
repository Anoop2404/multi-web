<?php

namespace App\Http\Controllers\SchoolAdmin;

use App\Models\UploadedFileBackup;
use App\Models\User;
use App\Services\Spreadsheet\SpreadsheetReader;
use App\Services\Students\StudentCsvImporter;
use App\Support\TenantStorage;
use Illuminate\Http\JsonResponse;
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
            'can_preview' => $this->canPreview($backup),
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

    public function preview(string $tenantId, UploadedFileBackup $backup): JsonResponse
    {
        abort_unless($backup->school_id === $this->school->id, 404);
        abort_unless(in_array($backup->purpose, self::PURPOSES, true), 404);
        abort_unless($this->canPreview($backup), 422, 'Preview is only available for CSV or Excel imports.');

        $path = TenantStorage::localTempPath($backup->storage_path, $backup->storage_disk);
        $shouldCleanup = str_starts_with($path, sys_get_temp_dir());

        try {
            $payload = $backup->purpose === 'student_import'
                ? $this->studentPreview($path)
                : $this->teacherPreview($path);

            return response()->json([
                'type' => $backup->purpose === 'student_import' ? 'students' : 'teachers',
                'original_name' => $backup->original_name,
                'stored_status' => $backup->status,
                'stored_errors' => $backup->errors ?? [],
                ...$payload,
            ]);
        } finally {
            if ($shouldCleanup && is_file($path)) {
                @unlink($path);
            }
        }
    }

    private function canPreview(UploadedFileBackup $backup): bool
    {
        $name = strtolower($backup->original_name ?? '');
        $mime = strtolower($backup->mime_type ?? '');

        return str_ends_with($name, '.csv')
            || str_ends_with($name, '.txt')
            || str_ends_with($name, '.xlsx')
            || str_contains($mime, 'csv')
            || str_contains($mime, 'spreadsheet');
    }

    /** @return array{valid: list<array<string, mixed>>, errors: list<array<string, mixed>>, total_rows: int} */
    private function studentPreview(string $path): array
    {
        return (new StudentCsvImporter($this->school))->previewFromPath($path);
    }

    /** @return array{columns: list<string>, rows: list<array{row: int, values: array<string, string>}>, total_rows: int} */
    private function teacherPreview(string $path, int $limit = 50): array
    {
        $columns = [];
        $rows = [];
        $line = 0;
        $totalRows = 0;

        foreach (SpreadsheetReader::rows($path) as $cols) {
            $line++;

            if ($line === 1) {
                $columns = array_map(
                    fn ($h) => trim((string) preg_replace('/^\xEF\xBB\xBF/', '', (string) $h)),
                    $cols,
                );

                continue;
            }

            $values = [];
            $hasData = false;
            foreach ($columns as $i => $column) {
                $value = trim((string) ($cols[$i] ?? ''));
                $values[$column] = $value;
                if ($value !== '') {
                    $hasData = true;
                }
            }

            if (! $hasData) {
                continue;
            }

            $totalRows++;
            if (count($rows) < $limit) {
                $rows[] = ['row' => $line, 'values' => $values];
            }
        }

        return [
            'columns' => $columns,
            'rows' => $rows,
            'total_rows' => $totalRows,
        ];
    }
}
