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
        $disk = config('filesystems.upload_disk', 'shared');

        return $disk === 'local' ? self::SHARED_DISK : $disk;
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

        return [
            self::storageRoot('tenant'.$tenant->id.'/app/public/'.$relativePath),
            self::storageRoot('app/shared/'.$relativePath),
            self::storageRoot('app/public/'.$relativePath),
        ];
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

        $absolute = self::publicFilePath($tenant, $relativePath);
        if ($absolute) {
            return response()->file($absolute);
        }

        foreach (self::downloadDisks() as $disk) {
            if (self::disk($disk)->exists($relativePath)) {
                return self::disk($disk)->response($relativePath);
            }
        }

        abort(404, 'File not found.');
    }

    public static function storeUploadedFile($file, string $directory): string
    {
        return $file->store($directory, self::uploadDisk());
    }

    /** @return list<string> */
    private static function downloadDisks(): array
    {
        $disks = [self::uploadDisk(), 's3', self::SHARED_DISK];

        return array_values(array_unique($disks));
    }

    private static function disk(string $name): Filesystem
    {
        return Storage::disk($name);
    }
}
