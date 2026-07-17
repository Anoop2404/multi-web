<?php

namespace App\Services\Certificates;

use App\Support\TenantStorage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Converts an uploaded certificate PDF (page 1) or image into a PNG background
 * suitable for HTML/browser-print certificates.
 */
class CertificateBackgroundConverter
{
    /**
     * @return array{template_file_path: ?string, background_path: string}
     */
    public function storeFromUpload(UploadedFile $file, string $baseDir, string $disk): array
    {
        $ext = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: '');
        $mime = (string) $file->getMimeType();

        if (in_array($ext, ['png', 'jpg', 'jpeg'], true) || str_starts_with($mime, 'image/')) {
            $path = $this->storeSafely($file, $baseDir.'/backgrounds', $disk);
            $this->mirrorToPublic($path);

            return [
                'template_file_path' => $path,
                'background_path'    => $path,
            ];
        }

        if ($ext !== 'pdf' && $mime !== 'application/pdf') {
            throw ValidationException::withMessages([
                'template_file' => 'Upload a PDF or PNG/JPG image for the certificate background.',
            ]);
        }

        $originalPath = $this->storeSafely($file, $baseDir, $disk);
        $absolutePdf = $this->absoluteLocalPath($originalPath, $disk);

        try {
            $pngBytes = $this->pdfFirstPageToPng($absolutePdf);
        } finally {
            if (str_starts_with($absolutePdf, storage_path('app/tmp/'))) {
                @unlink($absolutePdf);
            }
        }

        $backgroundPath = $baseDir.'/backgrounds/'.Str::uuid().'.png';
        $this->putBrowserAccessible($backgroundPath, $pngBytes, $disk);

        return [
            'template_file_path' => $originalPath,
            'background_path'    => $backgroundPath,
        ];
    }

    /**
     * Re-convert an already-stored PDF into a local browser-accessible PNG.
     * Used when S3/MinIO was preferred but the preview thumbnail is missing locally.
     */
    public function rebuildBackgroundFromPdf(string $pdfRelativePath, string $backgroundRelativePath, string $preferredDisk): void
    {
        $absolutePdf = $this->absoluteLocalPath($pdfRelativePath, $preferredDisk);
        try {
            $pngBytes = $this->pdfFirstPageToPng($absolutePdf);
        } finally {
            if (str_starts_with($absolutePdf, storage_path('app/tmp/'))) {
                @unlink($absolutePdf);
            }
        }

        $this->putBrowserAccessible($backgroundRelativePath, $pngBytes, $preferredDisk);
    }

    private function storeSafely(UploadedFile $file, string $directory, string $preferredDisk): string
    {
        try {
            return TenantStorage::storeUploadedFile($file, $directory, $preferredDisk);
        } catch (\Throwable) {
            // fall through to direct disk attempts
        }

        foreach (array_unique([$preferredDisk, 'shared', 'public', 'local']) as $disk) {
            try {
                $path = $file->store($directory, $disk);
                if (is_string($path) && $path !== '' && $this->diskHasFile($disk, $path)) {
                    return $path;
                }
            } catch (\Throwable) {
                continue;
            }
        }

        throw ValidationException::withMessages([
            'template_file' => 'Could not store the certificate background file. Check storage configuration.',
        ]);
    }

    /**
     * Persist PNG where the browser can load it (/storage/...) and optionally on the preferred upload disk.
     *
     * Under tenancy, the "public" disk root is remapped to storage/tenant{id}/… which is NOT what
     * public/storage serves. Always write the central storage/app/public copy for preview/print.
     */
    private function putBrowserAccessible(string $relativePath, string $contents, string $preferredDisk): void
    {
        if ($contents === '') {
            throw ValidationException::withMessages([
                'template_file' => 'Could not convert the PDF background. Try uploading a PNG/JPG instead.',
            ]);
        }

        $wrote = $this->writeCentralPublic($relativePath, $contents);

        foreach (array_unique([$preferredDisk, 'shared', 'public', 'local']) as $disk) {
            try {
                Storage::disk($disk)->put($relativePath, $contents);
                if ($this->diskHasFile($disk, $relativePath)) {
                    $wrote = true;
                }
            } catch (\Throwable) {
                continue;
            }
        }

        if (! $wrote) {
            throw ValidationException::withMessages([
                'template_file' => 'Could not store the converted certificate background. Check storage configuration.',
            ]);
        }
    }

    private function mirrorToPublic(string $relativePath): void
    {
        if ($this->centralPublicExists($relativePath)) {
            return;
        }

        foreach (['shared', 'public', 'local', 's3'] as $disk) {
            try {
                if (! $this->diskHasFile($disk, $relativePath)) {
                    continue;
                }
                $bytes = Storage::disk($disk)->get($relativePath);
                if (is_string($bytes) && $bytes !== '') {
                    $this->writeCentralPublic($relativePath, $bytes);
                }

                return;
            } catch (\Throwable) {
                continue;
            }
        }
    }

    private function writeCentralPublic(string $relativePath, string $contents): bool
    {
        $full = base_path('storage/app/public/'.ltrim($relativePath, '/'));
        $dir = dirname($full);
        if (! is_dir($dir) && ! mkdir($dir, 0775, true) && ! is_dir($dir)) {
            return false;
        }

        return file_put_contents($full, $contents) !== false;
    }

    private function centralPublicExists(string $relativePath): bool
    {
        return is_file(base_path('storage/app/public/'.ltrim($relativePath, '/')));
    }

    private function diskHasFile(string $disk, string $relativePath): bool
    {
        try {
            return Storage::disk($disk)->exists($relativePath);
        } catch (\Throwable) {
            return false;
        }
    }

    private function absoluteLocalPath(string $relativePath, string $preferredDisk): string
    {
        foreach (array_unique([$preferredDisk, 'shared', 'public', 'local']) as $disk) {
            try {
                if (! $this->diskHasFile($disk, $relativePath)) {
                    continue;
                }
                $path = Storage::disk($disk)->path($relativePath);
                if (is_file($path)) {
                    return $path;
                }
            } catch (\Throwable) {
                continue;
            }
        }

        $tmpPdf = storage_path('app/tmp/cert-'.Str::uuid().'.pdf');
        if (! is_dir(dirname($tmpPdf))) {
            mkdir(dirname($tmpPdf), 0755, true);
        }

        foreach (array_unique([$preferredDisk, 'shared', 'public', 'local', 's3']) as $disk) {
            try {
                $bytes = Storage::disk($disk)->get($relativePath);
                if (is_string($bytes) && $bytes !== '') {
                    file_put_contents($tmpPdf, $bytes);

                    return $tmpPdf;
                }
            } catch (\Throwable) {
                continue;
            }
        }

        throw ValidationException::withMessages([
            'template_file' => 'Could not read the uploaded PDF for conversion.',
        ]);
    }

    public function pdfFirstPageToPng(string $absolutePdfPath): string
    {
        if (extension_loaded('imagick') && class_exists(\Imagick::class)) {
            return $this->viaImagick($absolutePdfPath);
        }

        if ($this->functionAvailable('exec') && $this->binaryExists('pdftoppm')) {
            return $this->viaPdftoppm($absolutePdfPath);
        }

        if (PHP_OS_FAMILY === 'Darwin' && $this->functionAvailable('proc_open') && $this->binaryExists('qlmanage')) {
            return $this->viaQlmanage($absolutePdfPath);
        }

        throw ValidationException::withMessages([
            'template_file' => 'PDF conversion needs the Imagick PHP extension (or pdftoppm). Upload a PNG/JPG of the certificate design instead.',
        ]);
    }

    private function viaImagick(string $absolutePdfPath): string
    {
        $imagick = new \Imagick;
        $imagick->setResolution(150, 150);
        $imagick->readImage($absolutePdfPath.'[0]');
        $imagick->setImageFormat('png');
        $imagick->setImageBackgroundColor('white');
        $imagick = $imagick->mergeImageLayers(\Imagick::LAYERMETHOD_FLATTEN);
        $blob = $imagick->getImageBlob();
        $imagick->clear();
        $imagick->destroy();

        if ($blob === '' || $blob === false) {
            throw ValidationException::withMessages([
                'template_file' => 'Could not convert the PDF background. Try uploading a PNG/JPG instead.',
            ]);
        }

        return $blob;
    }

    private function viaPdftoppm(string $absolutePdfPath): string
    {
        $dir = sys_get_temp_dir().'/cert-bg-'.Str::uuid();
        mkdir($dir, 0755, true);
        $prefix = $dir.'/page';

        try {
            $cmd = sprintf(
                'pdftoppm -png -r 150 -f 1 -l 1 %s %s 2>&1',
                escapeshellarg($absolutePdfPath),
                escapeshellarg($prefix),
            );
            exec($cmd, $output, $code);
            $png = $prefix.'-1.png';
            if ($code !== 0 || ! is_file($png)) {
                throw ValidationException::withMessages([
                    'template_file' => 'Could not convert the PDF background. Try uploading a PNG/JPG instead.',
                ]);
            }

            return (string) file_get_contents($png);
        } finally {
            foreach (glob($dir.'/*') ?: [] as $f) {
                @unlink($f);
            }
            @rmdir($dir);
        }
    }

    private function viaQlmanage(string $absolutePdfPath): string
    {
        $dir = sys_get_temp_dir().'/cert-bg-'.Str::uuid();
        mkdir($dir, 0755, true);

        try {
            $cmd = sprintf(
                'qlmanage -t -s 2000 -o %s %s >/dev/null 2>&1',
                escapeshellarg($dir),
                escapeshellarg($absolutePdfPath),
            );

            $descriptor = [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ];
            $proc = proc_open($cmd, $descriptor, $pipes);
            if (! is_resource($proc)) {
                throw ValidationException::withMessages([
                    'template_file' => 'Could not convert the PDF background. Try uploading a PNG/JPG instead.',
                ]);
            }
            fclose($pipes[0]);
            stream_get_contents($pipes[1]);
            stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);

            $status = proc_get_status($proc);
            $deadline = microtime(true) + 20;
            while ($status['running'] && microtime(true) < $deadline) {
                usleep(100000);
                $status = proc_get_status($proc);
            }
            if ($status['running']) {
                proc_terminate($proc);
                proc_close($proc);
                throw ValidationException::withMessages([
                    'template_file' => 'PDF conversion timed out. Upload a PNG/JPG of the certificate design instead.',
                ]);
            }
            proc_close($proc);

            $files = glob($dir.'/*.png') ?: [];
            if ($files === []) {
                throw ValidationException::withMessages([
                    'template_file' => 'Could not convert the PDF background. Try uploading a PNG/JPG instead.',
                ]);
            }

            return (string) file_get_contents($files[0]);
        } finally {
            foreach (glob($dir.'/*') ?: [] as $f) {
                @unlink($f);
            }
            @rmdir($dir);
        }
    }

    private function binaryExists(string $name): bool
    {
        // Many shared-hosting PHP configs (e.g. aaPanel defaults) disable shell_exec/exec/
        // proc_open entirely for security. Calling a disabled function isn't a warning in
        // PHP 8 — it's a fatal "Call to undefined function" error, so this must be checked
        // before ever calling shell_exec(), not just before running a command through it.
        if (! $this->functionAvailable('shell_exec')) {
            return false;
        }

        $path = trim((string) shell_exec('command -v '.escapeshellarg($name).' 2>/dev/null'));

        return $path !== '';
    }

    /** True only if the named function exists AND hasn't been disabled via php.ini disable_functions. */
    private function functionAvailable(string $name): bool
    {
        if (! function_exists($name)) {
            return false;
        }

        $disabled = array_map('trim', explode(',', (string) ini_get('disable_functions')));

        return ! in_array($name, $disabled, true);
    }
}
