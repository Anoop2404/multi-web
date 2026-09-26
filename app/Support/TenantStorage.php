<?php

namespace App\Support;

use App\Models\Tenant;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TenantStorage
{
    /** Local disk for dev uploads (not tenant-suffixed). */
    public const SHARED_DISK = 'shared';

    /** Must match photoBase64DataUri()'s default $maxDimension — see storePhotoWithThumbnail(). */
    private const PHOTO_THUMBNAIL_MAX_DIMENSION = 160;

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

    /**
     * Download or stream a private file; tries recorded disk then fallbacks. A file on S3 is
     * handed to the browser as a signed CloudFront URL when that's configured (see
     * cloudFrontSignedUrl()), so it never passes through the app server.
     */
    public static function downloadPrivate(string $relativePath, ?string $disk = null, ?string $filename = null, bool $inline = false): BinaryFileResponse|StreamedResponse|Response|RedirectResponse
    {
        $relativePath = ltrim($relativePath, '/');

        if (self::resolveDisk($disk) === 's3' && self::cloudFrontConfigured()) {
            try {
                if (Storage::disk('s3')->exists($relativePath)
                    && ($url = self::cloudFrontSignedUrl($relativePath, $filename, $inline))) {
                    return redirect()->away($url);
                }
            } catch (\Throwable) {
                // Fall through to streaming it as before.
            }
        }

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
                    if ($inline) {
                        return $storage->response($relativePath, $filename);
                    }

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

    /**
     * Whether private S3 downloads should go through CloudFront signed URLs
     * (services.cloudfront url + key_pair_id + a readable private key file).
     */
    public static function cloudFrontConfigured(): bool
    {
        $config = (array) config('services.cloudfront', []);

        return filled($config['url'] ?? null)
            && filled($config['key_pair_id'] ?? null)
            && filled($config['private_key_path'] ?? null)
            && is_readable((string) $config['private_key_path'])
            && self::isS3Configured();
    }

    /**
     * A short-lived signed CloudFront URL for a private S3 object (relative path as stored,
     * i.e. without the disk's root prefix), or null when CloudFront isn't configured or
     * signing fails — callers then stream the file themselves as before. The browser
     * downloads straight from CloudFront: S3 -> CloudFront transfer is free and the bytes
     * never touch the app server.
     *
     * The distribution needs: this bucket as origin via Origin Access Control, viewer
     * access restricted to the key group holding CLOUDFRONT_KEY_PAIR_ID, caching disabled
     * (a re-rendered certificate keeps its key), and an origin request policy forwarding
     * the response-content-disposition query string (download filename / inline view).
     */
    public static function cloudFrontSignedUrl(string $relativePath, ?string $filename = null, bool $inline = false, ?int $ttlSeconds = null, ?int $expiresAt = null): ?string
    {
        if (! self::cloudFrontConfigured()) {
            return null;
        }

        $config = (array) config('services.cloudfront');

        try {
            // The object key as S3 stores it — the disk's root prefix ("domains/") included.
            $key = ltrim(Storage::disk('s3')->path(ltrim($relativePath, '/')), '/');
            $originPath = trim((string) ($config['origin_path'] ?? ''), '/');
            if ($originPath !== '' && str_starts_with($key, $originPath.'/')) {
                $key = substr($key, strlen($originPath) + 1);
            }

            $url = rtrim((string) $config['url'], '/').'/'.implode('/', array_map('rawurlencode', explode('/', $key)));

            if ($filename !== null || ! $inline) {
                $disposition = $inline ? 'inline' : 'attachment';
                if ($filename !== null && $filename !== '') {
                    $ascii = preg_replace('/[^\x20-\x7E]/', '_', str_replace(['"', '\\'], '', $filename));
                    $disposition .= '; filename="'.$ascii.'"; filename*=UTF-8\'\''.rawurlencode($filename);
                }
                $url .= '?response-content-disposition='.rawurlencode($disposition);
            }

            $signer = new \Aws\CloudFront\UrlSigner((string) $config['key_pair_id'], (string) $config['private_key_path']);

            return $signer->getSignedUrl($url, $expiresAt ?? time() + max(60, $ttlSeconds ?? (int) ($config['ttl'] ?? 600)));
        } catch (\Throwable $e) {
            Log::warning('CloudFront signed URL failed; streaming the file instead: '.$e->getMessage());

            return null;
        }
    }

    /**
     * Expiry for signed CloudFront URLs embedded in pages (images): aligned to 12-hour
     * windows, so the URL stays identical across page views within a window and browsers
     * can cache the image, and always at least 24 h away — longer than Cloudflare keeps a
     * cached public page (1 h, plus up to a day stale-while-revalidate).
     */
    public static function cacheableCloudFrontExpiry(): int
    {
        return (intdiv(time(), 43200) + 3) * 43200;
    }

    /**
     * A browser URL for a private file: a signed CloudFront URL when $disk is S3 and
     * CloudFront is configured, otherwise the disk's own temporaryUrl() — the drop-in for
     * the controllers that redirect to Storage::disk($disk)->temporaryUrl(...).
     */
    public static function privateTemporaryUrl(string $disk, string $relativePath, \DateTimeInterface $expiresAt): string
    {
        if ($disk === 's3' && ($url = self::cloudFrontSignedUrl($relativePath, null, true, max(60, $expiresAt->getTimestamp() - time())))) {
            return $url;
        }

        return Storage::disk($disk)->temporaryUrl($relativePath, $expiresAt);
    }

    /**
     * Browser URL for a file on S3 that's embedded in a page (assetUrl()). Logos — on
     * every page, including Cloudflare-cached public ones — get the stable public
     * CloudFront address when AWS_PUBLIC_URL is set (cacheable, never expires; the
     * distribution must keep domains/logos/* public). Anything else: a cacheable signed
     * CloudFront URL when configured, else a presigned S3 URL as before. A fresh 2-hour
     * presigned S3 URL per render meant every page view re-downloaded the logo from S3,
     * and a cached public page could outlive its link.
     */
    private static function embeddedS3Url(string $relativePath): string
    {
        $storage = Storage::disk('s3');

        if (str_starts_with($relativePath, 'logos/') && filled(config('filesystems.disks.s3.public_url'))) {
            return $storage->url($relativePath);
        }

        if ($url = self::cloudFrontSignedUrl($relativePath, null, true, null, self::cacheableCloudFrontExpiry())) {
            return $url;
        }

        try {
            return $storage->temporaryUrl($relativePath, now()->addHours(2));
        } catch (\Throwable) {
            return $storage->url($relativePath);
        }
    }

    /**
     * exists() on S3, remembered — assetUrl() runs on every page render and stored file
     * names are unique (a replaced logo gets a new name), so a HEAD request to S3 per
     * render bought nothing. A miss is only remembered briefly.
     */
    private static function rememberedExistsOnS3(string $relativePath): bool
    {
        $key = 'tenant-storage:s3-exists:'.sha1($relativePath);
        $cached = Cache::get($key);
        if (is_bool($cached)) {
            return $cached;
        }

        $exists = Storage::disk('s3')->exists($relativePath);
        Cache::put($key, $exists, $exists ? now()->addHours(6) : now()->addMinutes(5));

        return $exists;
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
        if (self::get($relativePath, $disk) !== null) {
            return true;
        }

        try {
            return Storage::disk(self::resolveDisk($disk))->exists(ltrim($relativePath, '/'));
        } catch (\Throwable) {
            return false;
        }
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
                if (! ($disk === 's3' ? self::rememberedExistsOnS3($relativePath) : $storage->exists($relativePath))) {
                    continue;
                }

                if ($disk === 's3') {
                    return self::embeddedS3Url($relativePath);
                }

                if ($disk === self::SHARED_DISK && $localServeUrl) {
                    return $localServeUrl;
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

    /**
     * Return a browser-facing storage URL without proxying the image through PHP.
     *
     * Public fest pages can contain hundreds of participant photos. Reading those
     * objects into PHP and embedding them as base64 makes the HTML enormous and makes
     * every cache miss spend CPU on image encoding. This method deliberately performs
     * no exists()/HEAD request: S3/CloudFront serves the bytes directly to the browser.
     *
     * When AWS_PUBLIC_URL is configured it should be the public S3/CloudFront base URL
     * and a stable, CDN-cacheable URL is returned. Private buckets fall back to a
     * presigned S3 URL; signing is local and still keeps the image request away from
     * the app.
     */
    public static function directPhotoUrl(?string $relativePath, bool $thumbnail = true): ?string
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

        $relativePath = ltrim($relativePath, '/');
        $path = $thumbnail ? self::thumbnailPath($relativePath) : $relativePath;
        if (self::isS3BrowserAccessible()) {
            try {
                $storage = Storage::disk('s3');

                // A configured public/CDN URL is stable, so CloudFront and browsers can
                // collapse thousands of identical requests onto one cached object.
                if (filled(config('filesystems.disks.s3.public_url'))) {
                    return $storage->url($path);
                }

                try {
                    return $storage->temporaryUrl($path, now()->addHours(6));
                } catch (\Throwable) {
                    return $storage->url($path);
                }
            } catch (\Throwable) {
                // Local/test installs continue through the existing data-URI fallback.
            }
        }

        return null;
    }

    /**
     * Cache::remember() a photo data URI, guarded by a short-lived lock so a burst of
     * concurrent requests for the same cold cache key (e.g. right after a fest result
     * publishes) don't all redo the S3 fetch + resize at once — only the first request
     * does the work, the rest wait briefly and then reuse its result. Falls back to an
     * unlocked remember() if the cache store doesn't support atomic locks (e.g. the
     * file driver in local dev) or the lock can't be acquired in time.
     */
    public static function rememberPhotoDataUri(string $cacheKey, ?Tenant $tenant, ?string $photo): ?string
    {
        $remember = fn () => Cache::remember(
            $cacheKey,
            now()->addDays(30),
            fn () => self::photoBase64DataUri($tenant, $photo),
        );

        if (Cache::has($cacheKey)) {
            return $remember();
        }

        try {
            return Cache::lock('lock:'.$cacheKey, 10)->block(5, $remember);
        } catch (\Throwable) {
            return $remember();
        }
    }

    public static function downloadResponse(Tenant $tenant, string $relativePath): BinaryFileResponse|StreamedResponse|Response|RedirectResponse
    {
        $relativePath = ltrim($relativePath, '/');

        foreach (self::downloadDisks() as $disk) {
            try {
                if (self::disk($disk)->exists($relativePath)) {
                    // On S3 with CloudFront configured: redirect to a signed CloudFront URL
                    // instead of streaming the file through this server (images get the
                    // browser-cacheable long link, documents the short default one).
                    if ($disk === 's3') {
                        $isImage = (bool) preg_match('/\.(jpe?g|png|gif|webp|svg|avif)$/i', $relativePath);
                        $url = self::cloudFrontSignedUrl($relativePath, null, true, null, $isImage ? self::cacheableCloudFrontExpiry() : null);
                        if ($url) {
                            return redirect()->away($url);
                        }
                    }

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
        return self::storePhotoWithThumbnail($file, 'students/'.$schoolId, self::photosDisk());
    }

    public static function storeTeacherPhoto($file, string $schoolId): string
    {
        return self::storePhotoWithThumbnail($file, 'teachers/'.$schoolId, self::photosDisk());
    }

    /**
     * Like storeUploadedFile(), but also generates and stores a small pre-shrunk
     * thumbnail alongside the original, so a cold-cache photoBase64DataUri() call can
     * fetch a few KB instead of downloading the full-resolution original (up to 2MB)
     * and running imagecopyresampled() on every cache miss. Thumbnail generation is a
     * pure optimization — any failure here is swallowed, never fails the upload.
     */
    private static function storePhotoWithThumbnail($file, string $directory, string $disk): string
    {
        $path = self::storeUploadedFile($file, $directory, $disk);

        try {
            $realPath = method_exists($file, 'getRealPath') ? $file->getRealPath() : null;
            $original = is_string($realPath) && $realPath !== '' ? @file_get_contents($realPath) : false;

            if (is_string($original) && $original !== '') {
                $shrunk = self::shrinkImageForEmbed($original, self::PHOTO_THUMBNAIL_MAX_DIMENSION);
                if ($shrunk) {
                    self::disk($disk)->put(self::thumbnailPath($path), $shrunk[0]);
                }
            }
        } catch (\Throwable) {
            // ignore — the original upload already succeeded.
        }

        return $path;
    }

    private static function thumbnailPath(string $relativePath): string
    {
        return $relativePath.'.thumb.jpg';
    }

    /** Embed path as data URI or local filesystem path for PDF rendering. */
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

        $local = self::localAbsolutePath($tenant, $relativePath);
        if ($local && is_file($local)) {
            return $local;
        }

        foreach (self::downloadDisks() as $disk) {
            try {
                $contents = self::disk($disk)->get($relativePath);
                if ($contents === null || $contents === '') {
                    continue;
                }

                return 'data:'.self::detectMimeFromBytes($contents).';base64,'.base64_encode($contents);
            } catch (\Throwable) {
                continue;
            }
        }

        return null;
    }

    /**
     * Like photoDataUri(), but never hands dompdf a bare filesystem path — always
     * reads the bytes ourselves and returns a true base64 data URI, downscaled to a
     * thumbnail.
     *
     * photoDataUri() returns the raw local path when it finds one on disk, which is
     * fine in principle (dompdf can open local files), but production storage is
     * often a symlink/mounted volume outside the app root (e.g. per-tenant upload
     * storage). dompdf's chroot check resolves symlinks and rejects anything that
     * lands outside base_path() once resolved, even though our own is_file() check
     * (which doesn't care about dompdf's chroot) says the file is there — so dompdf
     * silently swaps in its own broken-image placeholder for every single photo.
     * Reading the bytes in PHP and inlining them as base64 sidesteps dompdf's path
     * resolution entirely, so it can't be tripped up by chroot/symlink mismatches.
     *
     * This is also what a roster report (attendance sheets, etc.) should use even for
     * an on-screen preview, not just the PDF: rendering ~200 participant photos as
     * separate authenticated <img> requests means the browser fires ~200 concurrent
     * fetches, each of which re-runs disk-existence probes (and, for S3, a network
     * round trip) — under load that exhausts PHP-FPM workers and a handful of images
     * randomly fail or crawl in. Embedding the bytes once, server-side, during the
     * single request that renders the report removes that whole class of failure.
     *
     * Photos are uploaded at full resolution (up to 2MB) but only ever displayed as a
     * ~28px circular thumbnail, so this also downscales before embedding when GD is
     * available — a typical 200KB-2MB original becomes a few KB, which is most of why
     * this is faster, not just more reliable. Falls back to the original bytes if GD
     * isn't installed or resizing fails for any reason.
     */
    public static function photoBase64DataUri(
        ?Tenant $tenant,
        ?string $relativePath,
        int $maxDimension = 160,
        bool $preserveCmykJpeg = false,
    ): ?string
    {
        if (! $relativePath) {
            return null;
        }

        if (str_starts_with($relativePath, 'data:image/')) {
            return $relativePath;
        }

        if (str_starts_with($relativePath, 'http://') || str_starts_with($relativePath, 'https://')) {
            return null;
        }

        $relativePath = ltrim($relativePath, '/');

        if ($maxDimension === self::PHOTO_THUMBNAIL_MAX_DIMENSION) {
            $prebuilt = self::fetchPrebuiltThumbnail($tenant, $relativePath);
            if ($prebuilt) {
                return $prebuilt;
            }
        }

        $local = self::localAbsolutePath($tenant, $relativePath);
        if ($local && is_file($local)) {
            $contents = @file_get_contents($local);
            if ($contents !== false && $contents !== '') {
                [$contents, $mime] = self::shrinkImageForEmbed($contents, $maxDimension, $preserveCmykJpeg)
                    ?? [$contents, @mime_content_type($local) ?: 'image/jpeg'];

                return 'data:'.$mime.';base64,'.base64_encode($contents);
            }
        }

        foreach (self::downloadDisks() as $disk) {
            try {
                $contents = self::disk($disk)->get($relativePath);
                if ($contents === null || $contents === '') {
                    continue;
                }
                [$contents, $mime] = self::shrinkImageForEmbed($contents, $maxDimension, $preserveCmykJpeg)
                    ?? [$contents, self::detectMimeFromBytes($contents)];

                return 'data:'.$mime.';base64,'.base64_encode($contents);
            } catch (\Throwable) {
                continue;
            }
        }

        return null;
    }

    /**
     * Embed a certificate/ID-card background image as a base64 data URI for PDF rendering.
     *
     * Unlike photoBase64DataUri(), this also handles the case where $path is an HTTP/HTTPS
     * URL (e.g. an S3 signed URL or a public CDN URL) by downloading the bytes locally and
     * re-embedding them — the Chromium PDF microservice cannot load arbitrary external URLs.
     *
     * Falls back to null only when the image cannot be read by any means.
     */
    public static function backgroundDataUri(?Tenant $tenant, ?string $path, int $maxDimension = 1600): ?string
    {
        if (! $path) {
            return null;
        }

        // This customer-specific template was originally installed as a CMYK JPEG.
        // Production storage may contain an older/re-encoded copy whose byte hash is
        // different from the bundled source, so resolve it by tenant + stable preset
        // filename before reading the stored bytes. This keeps the correction isolated
        // to Kochi Metro's Template 2 and leaves every other Sahodaya background alone.
        if ($replacement = self::tenantSrgbBackgroundReplacement($tenant, $path)) {
            return 'data:'.$replacement[1].';base64,'.base64_encode($replacement[0]);
        }

        if (str_starts_with($path, 'data:image/')) {
            return $path;
        }

        // For storage-relative paths, delegate to the existing method (handles local + all disks).
        // Background artwork is commonly supplied as a colour-managed CMYK JPEG. GD
        // does not preserve that profile while resizing, while Chromium's PDF writer
        // may embed the untouched JPEG as DeviceCMYK and turn muted pinks into neon
        // magenta. Unknown CMYK artwork is therefore kept untouched rather than being
        // destructively re-encoded by GD.
        if (! str_starts_with($path, 'http://') && ! str_starts_with($path, 'https://')) {
            return self::photoBase64DataUri($tenant, $path, $maxDimension, preserveCmykJpeg: true);
        }

        // The path is already a full URL (e.g. an S3 signed URL resolved by logoUrl()).
        // Download it locally so we can embed it as base64 — the PDF renderer is an external
        // Chromium service that cannot load application-server URLs.
        try {
            $ctx = stream_context_create(['http' => ['timeout' => 10, 'ignore_errors' => true]]);
            $contents = @file_get_contents($path, false, $ctx);
            if ($contents === false || $contents === '') {
                return null;
            }
            [$contents, $mime] = self::shrinkImageForEmbed($contents, $maxDimension, preserveCmykJpeg: true)
                ?? [$contents, self::detectMimeFromBytes($contents)];

            return 'data:'.$mime.';base64,'.base64_encode($contents);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Fetch a thumbnail written by storePhotoWithThumbnail() at upload time, without
     * touching the full-resolution original at all. Returns null (caller falls back to
     * fetching + resizing the original) for photos uploaded before this existed.
     */
    private static function fetchPrebuiltThumbnail(?Tenant $tenant, string $relativePath): ?string
    {
        $thumbPath = self::thumbnailPath($relativePath);

        $local = self::localAbsolutePath($tenant, $thumbPath);
        if ($local && is_file($local)) {
            $contents = @file_get_contents($local);
            if ($contents !== false && $contents !== '') {
                return 'data:image/jpeg;base64,'.base64_encode($contents);
            }
        }

        foreach (self::downloadDisks() as $disk) {
            try {
                $contents = self::disk($disk)->get($thumbPath);
                if ($contents !== null && $contents !== '') {
                    return 'data:image/jpeg;base64,'.base64_encode($contents);
                }
            } catch (\Throwable) {
                continue;
            }
        }

        return null;
    }

    /** Detect an image's mime type from raw bytes — no network or filesystem round trip. */
    private static function detectMimeFromBytes(string $contents): string
    {
        if (function_exists('finfo_buffer')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = $finfo ? finfo_buffer($finfo, $contents) : false;
            if (is_string($mime) && $mime !== '') {
                return $mime;
            }
        }

        return 'image/jpeg';
    }

    /**
     * Downscale image bytes to fit within $maxDimension×$maxDimension and re-encode
     * as JPEG, for cheap embedding as a data URI. Returns null (caller keeps the
     * original bytes/mime) if GD isn't available, decoding fails, or the image is
     * already small enough that resizing wouldn't help.
     *
     * @return array{0: string, 1: string}|null [contents, mime]
     */
    private static function shrinkImageForEmbed(
        string $contents,
        int $maxDimension,
        bool $preserveCmykJpeg = false,
    ): ?array
    {
        if ($contents === '') {
            return null;
        }

        if ($preserveCmykJpeg) {
            $imageInfo = @getimagesizefromstring($contents);
            if (($imageInfo['mime'] ?? null) === 'image/jpeg' && ($imageInfo['channels'] ?? null) === 4) {
                return null;
            }
        }

        if (! function_exists('imagecreatefromstring')) {
            return null;
        }

        try {
            $src = @imagecreatefromstring($contents);
            if (! $src) {
                return null;
            }

            $width = imagesx($src);
            $height = imagesy($src);

            if ($width <= $maxDimension && $height <= $maxDimension) {
                imagedestroy($src);

                return null;
            }

            $scale = $maxDimension / max($width, $height);
            $newWidth = max(1, (int) round($width * $scale));
            $newHeight = max(1, (int) round($height * $scale));

            $dst = imagecreatetruecolor($newWidth, $newHeight);
            imagecopyresampled($dst, $src, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
            imagedestroy($src);

            ob_start();
            imagejpeg($dst, null, 82);
            $resized = ob_get_clean();
            imagedestroy($dst);

            if (! is_string($resized) || $resized === '') {
                return null;
            }

            return [$resized, 'image/jpeg'];
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Return a pre-converted sRGB version of known CMYK print artwork.
     *
     * Runtime GD cannot perform an ICC-aware CMYK conversion. Keeping these small,
     * reviewed replacements beside their source assets makes the PDF result
     * deterministic even on production hosts without Imagick/ColorSync.
     *
     * @return array{0: string, 1: string}|null [contents, mime]
     */
    private static function tenantSrgbBackgroundReplacement(?Tenant $tenant, string $path): ?array
    {
        if ((string) $tenant?->id !== 'b7f9b005-9f08-4833-8c02-8767a440ad01') {
            return null;
        }

        $pathOnly = parse_url($path, PHP_URL_PATH);
        $filename = basename(is_string($pathOnly) ? $pathOnly : $path);
        if (! preg_match('/^kalotsav-student-id-template-2(?:-copy-\d+)?\.jpg$/', $filename)) {
            return null;
        }

        $replacementPath = database_path('seeders/assets/id-card-templates/kalotsav-student-id-template-2-srgb.jpg');
        if (! is_file($replacementPath)) {
            return null;
        }

        $replacement = @file_get_contents($replacementPath);

        return is_string($replacement) && $replacement !== ''
            ? [$replacement, 'image/jpeg']
            : null;
    }

    public static function localAbsolutePath(?Tenant $tenant, ?string $relativePath): ?string
    {
        if (! $relativePath) {
            return null;
        }

        $relativePath = ltrim($relativePath, '/');

        $directCandidates = [
            base_path('storage/app/'.$relativePath),
            base_path('storage/app/shared/'.$relativePath),
            base_path('storage/app/private/'.$relativePath),
            base_path('storage/app/public/'.$relativePath),
            storage_path('app/'.$relativePath),
            public_path($relativePath),
            public_path('storage/'.$relativePath),
        ];

        foreach ($directCandidates as $cand) {
            if (is_file($cand)) {
                return $cand;
            }
        }

        if ($tenant) {
            $found = self::publicFilePath($tenant, $relativePath);
            if ($found && is_file($found)) {
                return $found;
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

    /**
     * Whether S3/MinIO URLs can be resolved directly by external client browsers.
     * Returns false if S3 is not configured, or if the endpoint is an internal/loopback
     * host (e.g. 127.0.0.1:9000, localhost) without an explicit public CDN URL. Handing
     * a 127.0.0.1 or internal URL to a client browser causes connection refused / mixed content.
     */
    public static function isS3BrowserAccessible(): bool
    {
        if (! self::isS3Configured()) {
            return false;
        }

        if (filled(config('filesystems.disks.s3.public_url'))) {
            return true;
        }

        $endpoint = config('filesystems.disks.s3.endpoint');
        if (filled($endpoint)) {
            $host = parse_url((string) $endpoint, PHP_URL_HOST);
            if (in_array($host, ['127.0.0.1', 'localhost', '::1', '0.0.0.0'], true)
                || str_ends_with((string) $host, '.internal')
                || str_ends_with((string) $host, '.local')
                || str_starts_with((string) $host, '10.')
                || str_starts_with((string) $host, '192.168.')) {
                return false;
            }
        }

        return true;
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

    public static function disk(?string $name = null): Filesystem
    {
        return Storage::disk($name ?? self::uploadDisk());
    }
}
