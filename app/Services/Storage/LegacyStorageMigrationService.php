<?php

namespace App\Services\Storage;

use App\Models\Achievement;
use App\Models\Alumni;
use App\Models\Certificate;
use App\Models\CertificateTemplate;
use App\Models\Circular;
use App\Models\Download;
use App\Models\Event;
use App\Models\ExportJob;
use App\Models\FeeReceipt;
use App\Models\GalleryItem;
use App\Models\MembershipPayment;
use App\Models\NewsArticle;
use App\Models\OfficeBearers;
use App\Models\SchoolDocument;
use App\Models\StaffMember;
use App\Models\StateRemittance;
use App\Models\Student;
use App\Models\StudentEditChangeRequest;
use App\Models\SubmissionStudent;
use App\Models\Teacher;
use App\Models\Tenant;
use App\Models\TenantSetting;
use App\Models\Testimonial;
use App\Models\Topper;
use App\Models\UploadedFileBackup;
use App\Support\TenancyDatabase;
use App\Support\TenantStorage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class LegacyStorageMigrationService
{
    /** @var list<array{label: string, model: class-string<Model>, columns: list<string>, disk?: string, filter?: callable}> */
    private array $sources = [];

    public function __construct()
    {
        $this->sources = [
            ['label' => 'Student photos', 'model' => Student::class, 'columns' => ['photo']],
            ['label' => 'Teacher photos', 'model' => Teacher::class, 'columns' => ['photo']],
            ['label' => 'School documents', 'model' => SchoolDocument::class, 'columns' => ['file_path'], 'disk' => 'storage_disk'],
            ['label' => 'Export jobs', 'model' => ExportJob::class, 'columns' => ['file_path'], 'disk' => 'storage_disk'],
            ['label' => 'Upload backups', 'model' => UploadedFileBackup::class, 'columns' => ['storage_path'], 'disk' => 'storage_disk'],
            ['label' => 'Membership payment proofs', 'model' => MembershipPayment::class, 'columns' => ['payment_proof_path']],
            ['label' => 'Fee receipt uploads', 'model' => FeeReceipt::class, 'columns' => ['file_path', 'generated_receipt_path']],
            ['label' => 'Circulars', 'model' => Circular::class, 'columns' => ['file_path']],
            ['label' => 'Downloads', 'model' => Download::class, 'columns' => ['file_path']],
            ['label' => 'Gallery items', 'model' => GalleryItem::class, 'columns' => ['image_path']],
            ['label' => 'Submission student images', 'model' => SubmissionStudent::class, 'columns' => ['image_path']],
            ['label' => 'Student change request photos', 'model' => StudentEditChangeRequest::class, 'columns' => ['photo_path']],
            ['label' => 'Certificates', 'model' => Certificate::class, 'columns' => ['file_path']],
            ['label' => 'Certificate templates', 'model' => CertificateTemplate::class, 'columns' => ['template_file_path']],
            ['label' => 'State remittance proofs', 'model' => StateRemittance::class, 'columns' => ['proof_path']],
            ['label' => 'News images', 'model' => NewsArticle::class, 'columns' => ['image']],
            ['label' => 'Event images', 'model' => Event::class, 'columns' => ['image']],
            ['label' => 'Achievement images', 'model' => Achievement::class, 'columns' => ['image']],
            ['label' => 'Alumni photos', 'model' => Alumni::class, 'columns' => ['photo']],
            ['label' => 'Staff photos', 'model' => StaffMember::class, 'columns' => ['photo']],
            ['label' => 'Office bearer photos', 'model' => OfficeBearers::class, 'columns' => ['photo']],
            ['label' => 'Testimonial photos', 'model' => Testimonial::class, 'columns' => ['photo']],
            ['label' => 'Topper photos', 'model' => Topper::class, 'columns' => ['photo']],
        ];
    }

    /** @return array<string, mixed> */
    public function status(): array
    {
        return [
            's3_configured'   => TenantStorage::isS3Configured(),
            'upload_disk'     => TenantStorage::uploadDisk(),
            'delete_local_default' => (bool) config('erp.legacy_migration_delete_local', false),
            'local_disks'     => (array) config('erp.legacy_migration_local_disks', ['shared', 'local', 'public']),
        ];
    }

    /**
     * @return array{sources: list<array<string, mixed>>, totals: array<string, int>, tenant_logos: int}
     */
    public function scan(?string $sahodayaId = null): array
    {
        $totals = ['records' => 0, 'pending' => 0, 'on_s3' => 0, 'missing' => 0, 'filesystem_orphans' => 0];
        $sourceStats = [];

        $this->eachSahodaya($sahodayaId, function () use (&$totals, &$sourceStats, $sahodayaId) {
            foreach ($this->sources as $source) {
                $stats = $this->scanSource($source, $sahodayaId);
                $sourceStats[] = array_merge(['label' => $source['label']], $stats);
                foreach (['records', 'pending', 'on_s3', 'missing'] as $key) {
                    $totals[$key] += $stats[$key];
                }
            }
        });

        $totals['tenant_logos'] = $this->scanTenantLogos($sahodayaId);
        $totals['filesystem_orphans'] = $this->scanFilesystemOrphans()['pending'];

        return [
            'sources'      => $sourceStats,
            'totals'       => $totals,
            'tenant_logos' => $totals['tenant_logos'],
        ];
    }

    /**
     * @return array{migrated: int, skipped: int, failed: int, missing: int, details: list<array<string, string>>}
     */
    public function migrate(
        ?string $sahodayaId = null,
        bool $dryRun = false,
        bool $deleteLocal = false,
        bool $includeFilesystem = false,
        ?callable $progress = null,
    ): array {
        if (! TenantStorage::isS3Configured()) {
            throw new \RuntimeException('S3 is not configured. Set AWS_* env vars and UPLOAD_DISK=s3.');
        }

        $result = ['migrated' => 0, 'skipped' => 0, 'failed' => 0, 'missing' => 0, 'details' => []];

        $this->eachSahodaya($sahodayaId, function () use (&$result, $dryRun, $deleteLocal, $sahodayaId, $progress) {
            foreach ($this->sources as $source) {
                $this->migrateSource($source, $sahodayaId, $dryRun, $deleteLocal, $result, $progress);
            }

            $this->migrateTenantLogos($sahodayaId, $dryRun, $deleteLocal, $result, $progress);
        });

        if ($includeFilesystem) {
            $this->migrateFilesystemOrphans($dryRun, $deleteLocal, $result, $progress);
        }

        return $result;
    }

    /** @return array{records: int, pending: int, on_s3: int, missing: int} */
    private function scanSource(array $source, ?string $sahodayaId): array
    {
        $stats = ['records' => 0, 'pending' => 0, 'on_s3' => 0, 'missing' => 0];

        $this->querySource($source, $sahodayaId)->each(function (Model $record) use ($source, &$stats) {
            foreach ($source['columns'] as $column) {
                $path = $record->{$column};
                if (! filled($path)) {
                    continue;
                }

                $stats['records']++;
                $disk = isset($source['disk']) ? $record->{$source['disk']} : null;
                $bucket = $this->classifyPath((string) $path, $disk);
                $stats[$bucket]++;
            }
        });

        return $stats;
    }

    private function migrateSource(
        array $source,
        ?string $sahodayaId,
        bool $dryRun,
        bool $deleteLocal,
        array &$result,
        ?callable $progress,
    ): void {
        $this->querySource($source, $sahodayaId)->each(function (Model $record) use ($source, $dryRun, $deleteLocal, &$result, $progress) {
            foreach ($source['columns'] as $column) {
                $path = $record->{$column};
                if (! filled($path)) {
                    continue;
                }

                $diskColumn = $source['disk'] ?? null;
                $recordedDisk = $diskColumn ? $record->{$diskColumn} : null;

                if ($dryRun) {
                    $class = $this->classifyPath((string) $path, $recordedDisk);
                    if ($class === 'pending') {
                        $result['migrated']++;
                    } elseif ($class === 'on_s3') {
                        $result['skipped']++;
                    } else {
                        $result['missing']++;
                    }

                    return;
                }

                $outcome = TenantStorage::migrateToS3((string) $path, $recordedDisk, $deleteLocal);
                $this->tally($result, $outcome, $source['label'], (string) $path);

                if ($diskColumn && $outcome['status'] === 'migrated') {
                    $record->update([$diskColumn => 's3']);
                }

                $progress?->invoke($outcome, $source['label'], (string) $path);
            }
        });
    }

    /** @return \Illuminate\Database\Eloquent\Builder<Model> */
    private function querySource(array $source, ?string $sahodayaId)
    {
        /** @var Model $model */
        $model = $source['model'];
        $query = $model::query();

        if ($sahodayaId && method_exists($model, 'getTable')) {
            $query = $this->applySahodayaScope($query, $model, $sahodayaId);
        }

        return $query;
    }

    /** @param  \Illuminate\Database\Eloquent\Builder<Model>  $query */
    private function applySahodayaScope($query, string $model, string $sahodayaId)
    {
        $schoolIds = TenancyDatabase::schoolIdsFor($sahodayaId);

        return match ($model) {
            Student::class, Teacher::class, Download::class, Event::class,
            Achievement::class, Alumni::class, StaffMember::class, OfficeBearers::class,
            Testimonial::class, NewsArticle::class, GalleryItem::class, Circular::class => $query->where(function ($q) use ($sahodayaId, $schoolIds) {
                $q->where('tenant_id', $sahodayaId)->orWhereIn('tenant_id', $schoolIds);
            }),
            SchoolDocument::class, MembershipPayment::class, SubmissionStudent::class,
            StudentEditChangeRequest::class => $query->whereIn('school_id', $schoolIds),
            UploadedFileBackup::class => $query->where(function ($q) use ($sahodayaId, $schoolIds) {
                $q->whereIn('school_id', $schoolIds)->orWhereNull('school_id');
            }),
            default => $query,
        };
    }

    private function classifyPath(string $path, ?string $recordedDisk): string
    {
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://') || str_starts_with($path, '/')) {
            return 'on_s3';
        }

        if ($recordedDisk === 's3' && TenantStorage::existsOnS3($path)) {
            return 'on_s3';
        }

        if (TenantStorage::existsOnS3($path)) {
            return 'on_s3';
        }

        if (TenantStorage::findLocalDisk($path) || ($recordedDisk && $recordedDisk !== 's3' && Storage::disk($recordedDisk)->exists($path))) {
            return 'pending';
        }

        return 'missing';
    }

    /** @param array{migrated: int, skipped: int, failed: int, missing: int, details: list<array<string, string>>} $result */
    private function tally(array &$result, array $outcome, string $label, string $path): void
    {
        match ($outcome['status']) {
            'migrated' => $result['migrated']++,
            'skipped'  => $result['skipped']++,
            default    => ($outcome['reason'] ?? '') === 'source_not_found'
                ? $result['missing']++
                : $result['failed']++,
        };

        if (count($result['details']) < 100 && $outcome['status'] !== 'skipped') {
            $result['details'][] = [
                'source' => $label,
                'path'   => $path,
                'status' => $outcome['status'],
                'note'   => $outcome['reason'] ?? $outcome['from'] ?? '',
            ];
        }
    }

    private function scanTenantLogos(?string $sahodayaId): int
    {
        $pending = 0;

        Tenant::query()
            ->when($sahodayaId, fn ($q) => $q->where('id', $sahodayaId)->orWhere('parent_id', $sahodayaId))
            ->each(function (Tenant $tenant) use (&$pending) {
                $logo = $tenant->getSetting('logo');
                if (! filled($logo) || str_starts_with($logo, 'http') || str_starts_with($logo, '/')) {
                    return;
                }

                if ($this->classifyPath($logo, null) === 'pending') {
                    $pending++;
                }
            });

        return $pending;
    }

    private function migrateTenantLogos(?string $sahodayaId, bool $dryRun, bool $deleteLocal, array &$result, ?callable $progress): void
    {
        Tenant::query()
            ->when($sahodayaId, fn ($q) => $q->where('id', $sahodayaId)->orWhere('parent_id', $sahodayaId))
            ->each(function (Tenant $tenant) use ($dryRun, $deleteLocal, &$result, $progress) {
                $logo = $tenant->getSetting('logo');
                if (! filled($logo) || str_starts_with($logo, 'http') || str_starts_with($logo, '/')) {
                    return;
                }

                if ($dryRun) {
                    if ($this->classifyPath($logo, null) === 'pending') {
                        $result['migrated']++;
                    }

                    return;
                }

                $outcome = TenantStorage::migrateToS3($logo, null, $deleteLocal);
                $this->tally($result, $outcome, 'Tenant logos', $logo);
                $progress?->invoke($outcome, 'Tenant logos', $logo);
            });
    }

    /** @return array{pending: int, scanned: int} */
    public function scanFilesystemOrphans(): array
    {
        $pending = 0;
        $scanned = 0;

        foreach ((array) config('erp.legacy_migration_local_disks', ['shared']) as $disk) {
            try {
                $files = Storage::disk($disk)->allFiles();
            } catch (\Throwable) {
                continue;
            }

            foreach ($files as $path) {
                $scanned++;
                if (! TenantStorage::existsOnS3($path)) {
                    $pending++;
                }
            }
        }

        return compact('pending', 'scanned');
    }

    private function migrateFilesystemOrphans(bool $dryRun, bool $deleteLocal, array &$result, ?callable $progress): void
    {
        $batch = (int) config('erp.legacy_migration_batch_size', 200);
        $count = 0;

        foreach ((array) config('erp.legacy_migration_local_disks', ['shared']) as $disk) {
            try {
                $files = Storage::disk($disk)->allFiles();
            } catch (\Throwable) {
                continue;
            }

            foreach ($files as $path) {
                if ($count >= $batch) {
                    return;
                }

                if (TenantStorage::existsOnS3($path)) {
                    $result['skipped']++;

                    continue;
                }

                if ($dryRun) {
                    $result['migrated']++;
                    $count++;

                    continue;
                }

                $outcome = TenantStorage::migrateToS3($path, $disk, $deleteLocal);
                $this->tally($result, $outcome, 'Filesystem', $path);
                $progress?->invoke($outcome, 'Filesystem', $path);
                $count++;
            }
        }
    }

    private function eachSahodaya(?string $sahodayaId, callable $callback): void
    {
        $run = function () use ($callback) {
            $callback();
        };

        if (! TenancyDatabase::enabled()) {
            $run();

            return;
        }

        if ($sahodayaId) {
            $tenant = Tenant::query()->where('type', 'sahodaya')->findOrFail($sahodayaId);
            TenancyDatabase::withTenantDatabase($tenant, $run);

            return;
        }

        Tenant::query()->where('type', 'sahodaya')->each(function (Tenant $tenant) use ($callback) {
            TenancyDatabase::withTenantDatabase($tenant, $callback);
        });
    }
}
