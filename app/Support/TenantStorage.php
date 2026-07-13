<?php

namespace App\Support;

use App\Models\Tenant;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TenantStorage
{
    /** Local disk for dev uploads (not tenant-suffixed). */
    public const SHARED_DISK = 'shared';

    public static function uploadDisk(): string
    {
        $disk = (string) config('filesystems.upload_disk', self::SHARED_DISK);

        if ($disk === 'local') {
            return self::isS3Configured() ? 's3' : self::SHARED_DISK;
        }

        if ($disk === 's3' && ! self::isS3Configured()) {
            return self::SHARED_DISK;
        }

        return $disk;
    }

    /** Resolve disk for an stored path (explicit disk column or default upload disk). */
    public static function resolveDisk(?string $disk = null): string
    {
        if ($disk && $disk !== 'local') {
            return $disk;
        }

        return self::uploadDisk();
    }

    public static function storeUploadedFile($file, string $directory, ?string $disk = null): string
    {
        $disk = $disk ?? self::uploadDisk();
        $path = $file->store($directory, $disk);

        if (is_string($path) && $path !== '' && self::storedFileExists($path, $disk)) {
            return $path;
        }

        if (self::canFallbackToSharedDisk($disk)) {
            self::ensureSharedDiskReady();
            $fallbackPath = $file->store($directory, self::SHARED_DISK);

            if (is_string($fallbackPath) && $fallbackPath !== '' && self::storedFileExists($fallbackPath, self::SHARED_DISK)) {
                return $fallbackPath;
            }
        }

        $configured = config('filesystems.upload_disk', self::SHARED_DISK);

        throw new \RuntimeException(match (true) {
            $disk === 's3' && ! self::storedFileExists(is_string($path) ? $path : '', 's3') => 'Could not upload to S3. Check AWS credentials, bucket, and endpoint — or set UPLOAD_DISK=shared for local dev.',
            default => "Could not save file to the {$configured} disk.",
        });
    }

    public static function storeUploadedFileAs($file, string $directory, string $name, ?string $disk = null): string
    {
        $disk = $disk ?? self::uploadDisk();
        $path = $file->storeAs($directory, $name, $disk);

        if (is_string($path) && $path !== '' && self::storedFileExists($path, $disk)) {
            return $path;
        }

        if (self::canFallbackToSharedDisk($disk)) {
            self::ensureSharedDiskReady();
            $fallbackPath = $file->storeAs($directory, $name, self::SHARED_DISK);

            if (is_string($fallbackPath) && $fallbackPath !== '' && self::storedFileExists($fallbackPath, self::SHARED_DISK)) {
                return $fallbackPath;
            }
        }

        throw new \RuntimeException('Could not save file to storage.');
    }

    private static function canFallbackToSharedDisk(string $primaryDisk): bool
    {
        return $primaryDisk !== self::SHARED_DISK
            && app()->environment(['local', 'testing']);
    }

    private static function ensureSharedDiskReady(): void
    {
        $root = storage_path('app/'.self::SHARED_DISK);
        if (! is_dir($root)) {
            mkdir($root, 0775, true);
        }
    }

    /** Download or stream a private file; tries recorded disk then fallbacks. */
    public static function downloadPrivate(string $relativePath, ?string $disk = null, ?string $filename = null): BinaryFileResponse|StreamedResponse|Response
    {
        $relativePath = ltrim($relativePath, '/');
        $disks = array_values(array_unique(array_filter([
            self::resolveDisk($disk),
            self::uploadDisk(),
            's3',
            self::SHARED_DISK,
            'local',
        ])));

        foreach ($disks as $name) {
            try {
                $storage = Storage::disk($name);
                if ($storage->exists($relativePath)) {
                    return $filename
                        ? $storage->download($relativePath, $filename)
                        : $storage->response($relativePath);
                }
            } catch (\Throwable) {
                continue;
            }
        }

        abort(404, 'File not found.');
    }

    /** Copy cloud object to a local temp path for batch processing (imports). */
    public static function localTempPath(string $relativePath, ?string $disk = null): string
    {
        $relativePath = ltrim($relativePath, '/');
        $resolved = self::resolveDisk($disk);

        if (in_array($resolved, ['local', self::SHARED_DISK], true)) {
            $path = Storage::disk($resolved)->path($relativePath);
            if (is_file($path)) {
                return $path;
            }
        }

        foreach ([$resolved, self::uploadDisk(), 's3', self::SHARED_DISK, 'local'] as $name) {
            try {
                $storage = Storage::disk($name);
                if ($storage->exists($relativePath)) {
                    $tmp = tempnam(sys_get_temp_dir(), 'upload_');
                    file_put_contents($tmp, $storage->get($relativePath));

                    return $tmp;
                }
            } catch (\Throwable) {
                continue;
            }
        }

        throw new \RuntimeException("Upload not found: {$relativePath}");
    }

    public static function put(string $relativePath, string $contents, ?string $disk = null): void
    {
        Storage::disk($disk ?? self::uploadDisk())->put($relativePath, $contents);
    }

    public static function get(string $relativePath, ?string $disk = null): ?string
    {
        $relativePath = ltrim($relativePath, '/');

        foreach ([self::resolveDisk($disk), self::uploadDisk(), 's3', self::SHARED_DISK, 'local'] as $name) {
            try {
                if (Storage::disk($name)->exists($relativePath)) {
                    return Storage::disk($name)->get($relativePath);
                }
            } catch (\Throwable) {
                continue;
            }
        }

        return null;
    }

    public static function exists(string $relativePath, ?string $disk = null): bool
    {
        return self::get($relativePath, $disk) !== null
            || Storage::disk(self::resolveDisk($disk))->exists(ltrim($relativePath, '/'));
    }

    public static function publicFilePath(Tenant $tenant, string $relativePath): ?string
    {
        foreach (self::candidatePaths($tenant, $relativePath) as $path) {
            if (is_file($path)) {
                return $path;
            }
        }

        return null;
    }

    /** @return list<string> */
    private static function candidatePaths(Tenant $tenant, string $relativePath): array
    {
        $relativePath = ltrim($relativePath, '/');
        $tenantIds = array_values(array_unique(array_filter([
            $tenant->id,
            $tenant->parent_id,
        ])));

        $paths = [
            self::storageRoot('app/'.$relativePath),
            self::storageRoot('app/shared/'.$relativePath),
            self::storageRoot('app/private/'.$relativePath),
            self::storageRoot('app/public/'.$relativePath),
        ];

        foreach ($tenantIds as $tenantId) {
            $paths[] = self::storageRoot('tenant'.$tenantId.'/app/'.$relativePath);
            $paths[] = self::storageRoot('tenant'.$tenantId.'/app/shared/'.$relativePath);
            $paths[] = self::storageRoot('tenant'.$tenantId.'/app/private/'.$relativePath);
            $paths[] = self::storageRoot('tenant'.$tenantId.'/app/public/'.$relativePath);
        }

        return $paths;
    }

    /** Absolute path under project storage/ — not affected by tenancy storage_path() suffix. */
    private static function storageRoot(string $relativePath): string
    {
        return base_path('storage/'.ltrim($relativePath, '/'));
    }

    public static function assetUrl(?Tenant $tenant, string $relativePath, ?string $localServeUrl = null): ?string
    {
        if ($relativePath === '') {
            return null;
        }

        if (str_starts_with($relativePath, 'http://') || str_starts_with($relativePath, 'https://')) {
            return $relativePath;
        }

        $relativePath = ltrim($relativePath, '/');

        foreach (self::downloadDisks() as $disk) {
            if (! in_array($disk, ['s3', self::uploadDisk(), 'public'], true)) {
                continue;
            }

            try {
                $storage = Storage::disk($disk);
                if (! $storage->exists($relativePath)) {
                    continue;
                }

                if ($disk === 's3') {
                    try {
                        return $storage->temporaryUrl($relativePath, now()->addHours(2));
                    } catch (\Throwable) {
                        return $storage->url($relativePath);
                    }
                }

                if ($disk === 'public') {
                    $centralPath = self::storageRoot('app/public/'.$relativePath);
                    if ($localServeUrl && ! is_file($centralPath) && $tenant && self::publicFilePath($tenant, $relativePath)) {
                        return $localServeUrl;
                    }
                }

                return $storage->url($relativePath);
            } catch (\Throwable) {
                continue;
            }
        }

        if ($tenant && $localServeUrl && self::publicFilePath($tenant, $relativePath)) {
            return $localServeUrl;
        }

        return null;
    }

    public static function downloadResponse(Tenant $tenant, string $relativePath): BinaryFileResponse|StreamedResponse|Response
    {
        $relativePath = ltrim($relativePath, '/');

        foreach (self::downloadDisks() as $disk) {
            try {
                if (self::disk($disk)->exists($relativePath)) {
                    return self::disk($disk)->response($relativePath);
                }
            } catch (\Throwable) {
                continue;
            }
        }

        $absolute = self::publicFilePath($tenant, $relativePath);
        if ($absolute) {
            return response()->file($absolute);
        }

        abort(404, 'File not found.');
    }

    public static function storeStudentPhoto($file, string $schoolId): string
    {
        return self::storeUploadedFile($file, 'students/'.$schoolId, self::photosDisk());
    }

    public static function storeTeacherPhoto($file, string $schoolId): string
    {
        return self::storeUploadedFile($file, 'teachers/'.$schoolId, self::photosDisk());
    }

    /** Embed path as data URI for PDF rendering (local file path or base64). */
    public static function photoDataUri(?Tenant $tenant, ?string $relativePath): ?string
    {
        if (! $relativePath) {
            return null;
        }

        if (str_starts_with($relativePath, 'data:image/')) {
            return $relativePath;
        }

        if (str_starts_with($relativePath, 'http://') || str_starts_with($relativePath, 'https://')) {
            return $relativePath;
        }

        if ($tenant) {
            $absolute = self::publicFilePath($tenant, $relativePath);
            if ($absolute && is_file($absolute)) {
                return $absolute;
            }
        }

        foreach (self::downloadDisks() as $disk) {
            try {
                if (self::disk($disk)->exists($relativePath)) {
                    $contents = self::disk($disk)->get($relativePath);
                    $mime = self::disk($disk)->mimeType($relativePath) ?: 'image/jpeg';

                    return 'data:'.$mime.';base64,'.base64_encode($contents);
                }
            } catch (\Throwable) {
                continue;
            }
        }

        return null;
    }

    public static function storeSubmissionImage($file, string $schoolId): string
    {
        return self::storeUploadedFile($file, 'submissions/'.$schoolId, self::photosDisk());
    }

    /** Store tenant logo on the configured upload disk (S3 in production). */
    public static function storeLogo($file, string $tenantId): string
    {
        return $file->store('logos/'.$tenantId, self::uploadDisk());
    }

    /** Resolve logo path for display on the public site and admin UI. */
    public static function logoUrl(?Tenant $tenant, ?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        if (str_starts_with($path, '/')) {
            return $path;
        }

        $relative = ltrim($path, '/');

        // Prefer the central public disk (what /storage serves), even when tenancy remaps Storage::disk('public').
        if (is_file(self::storageRoot('app/public/'.$relative))) {
            return '/storage/'.$relative;
        }

        try {
            if (Storage::disk('public')->exists($relative)) {
                return Storage::disk('public')->url($relative);
            }
        } catch (\Throwable) {
            // S3/tenancy disk probes can throw when MinIO is down.
        }

        $fromAsset = self::assetUrl($tenant, $relative);
        if ($fromAsset) {
            return $fromAsset;
        }

        return '/storage/'.$relative;
    }

    /** Store public website media on the upload disk (S3 when configured). */
    public static function storeSiteMedia($file, string $tenantId): string
    {
        return $file->store('site-media/'.$tenantId, self::uploadDisk());
    }

    /** Resolve stored path or URL for display on the public site. */
    public static function siteMediaUrl(?Tenant $tenant, ?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        if (str_starts_with($path, '/')) {
            return $path;
        }

        $relative = ltrim($path, '/');

        if (Storage::disk('public')->exists($relative)) {
            return Storage::disk('public')->url($relative);
        }

        $fromAsset = self::assetUrl($tenant, $relative);
        if ($fromAsset) {
            return $fromAsset;
        }

        return '/storage/'.$relative;
    }

    public static function isS3Configured(): bool
    {
        return filled(config('filesystems.disks.s3.key'))
            && filled(config('filesystems.disks.s3.secret'))
            && filled(config('filesystems.disks.s3.bucket'));
    }

    /** Find which local disk holds a relative path, if any. */
    public static function findLocalDisk(string $relativePath): ?string
    {
        $relativePath = ltrim($relativePath, '/');

        if ($relativePath === '' || str_starts_with($relativePath, 'http://') || str_starts_with($relativePath, 'https://')) {
            return null;
        }

        foreach (self::localDisks() as $disk) {
            try {
                if (Storage::disk($disk)->exists($relativePath)) {
                    return $disk;
                }
            } catch (\Throwable) {
                continue;
            }
        }

        return null;
    }

    public static function existsOnS3(string $relativePath): bool
    {
        if (! self::isS3Configured()) {
            return false;
        }

        try {
            return Storage::disk('s3')->exists(ltrim($relativePath, '/'));
        } catch (\Throwable) {
            return false;
        }
    }

    public static function storedFileExists(string $relativePath, ?string $disk = null): bool
    {
        $relativePath = ltrim($relativePath, '/');

        if ($relativePath === '') {
            return false;
        }

        foreach (array_values(array_unique(array_filter([
            self::resolveDisk($disk),
            self::uploadDisk(),
            's3',
            self::SHARED_DISK,
            'local',
        ]))) as $name) {
            try {
                if (Storage::disk($name)->exists($relativePath)) {
                    return true;
                }
            } catch (\Throwable) {
                continue;
            }
        }

        return false;
    }

    /**
     * Copy a file from a local disk to S3 (same relative path).
     *
     * @return array{status: string, reason?: string, from?: string}
     */
    public static function migrateToS3(string $relativePath, ?string $sourceDisk = null, bool $deleteLocal = false): array
    {
        $relativePath = ltrim($relativePath, '/');

        if ($relativePath === '') {
            return ['status' => 'skipped', 'reason' => 'empty_path'];
        }

        if (str_starts_with($relativePath, 'http://') || str_starts_with($relativePath, 'https://') || str_starts_with($relativePath, '/')) {
            return ['status' => 'skipped', 'reason' => 'external_or_absolute'];
        }

        if (! self::isS3Configured()) {
            return ['status' => 'failed', 'reason' => 's3_not_configured'];
        }

        if (self::existsOnS3($relativePath)) {
            return ['status' => 'skipped', 'reason' => 'already_on_s3'];
        }

        $sourceDisk = $sourceDisk && $sourceDisk !== 's3'
            ? $sourceDisk
            : self::findLocalDisk($relativePath);

        if (! $sourceDisk) {
            return ['status' => 'failed', 'reason' => 'source_not_found'];
        }

        if ($sourceDisk === 's3') {
            return ['status' => 'skipped', 'reason' => 'already_on_s3'];
        }

        try {
            $contents = Storage::disk($sourceDisk)->get($relativePath);
            Storage::disk('s3')->put($relativePath, $contents);

            if ($deleteLocal) {
                Storage::disk($sourceDisk)->delete($relativePath);
            }

            return ['status' => 'migrated', 'from' => $sourceDisk];
        } catch (\Throwable $e) {
            return ['status' => 'failed', 'reason' => $e->getMessage()];
        }
    }

    /** @return list<string> */
    private static function localDisks(): array
    {
        return array_values(array_unique(array_merge(
            (array) config('erp.legacy_migration_local_disks', ['shared', 'local', 'public']),
            [self::SHARED_DISK, 'local', 'public'],
        )));
    }

    private static function photosDisk(): string
    {
        return self::uploadDisk();
    }

    /** @return list<string> */
    private static function downloadDisks(): array
    {
        $disks = ['s3', self::uploadDisk(), self::SHARED_DISK, 'local', 'public'];

        return array_values(array_unique($disks));
    }

    private static function disk(string $name): Filesystem
    {
        return Storage::disk($name);
    }
}
