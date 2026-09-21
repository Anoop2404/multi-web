<?php

namespace App\Console\Commands;

use App\Models\IdCardTemplate;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Upload ID-card template background images that exist on local disk to S3,
 * so that the external Chromium PDF service can reach them via backgroundDataUri().
 *
 * Run on the production server:
 *   php artisan id-cards:sync-backgrounds-to-s3
 */
class SyncIdCardBackgroundsToS3 extends Command
{
    protected $signature   = 'id-cards:sync-backgrounds-to-s3 {--dry-run : Show what would be uploaded without actually uploading}';
    protected $description = 'Upload ID-card template background images from local disk to S3';

    public function handle(): int
    {
        $isDry = (bool) $this->option('dry-run');

        if ($isDry) {
            $this->info('[DRY RUN] No files will be uploaded.');
        }

        $templates = IdCardTemplate::whereNotNull('background_path')->get();
        $this->info("Found {$templates->count()} template(s) with a background_path.");

        $uploaded = 0;
        $skipped  = 0;
        $missing  = 0;

        foreach ($templates as $template) {
            $path = ltrim((string) $template->background_path, '/');
            $this->line("\n── Template #{$template->id}: {$template->title}");
            $this->line("   background_path: {$path}");

            // 1. Check if already on S3
            try {
                if (Storage::disk('s3')->exists($path)) {
                    $this->line('   Already on S3 — skipping.');
                    $skipped++;
                    continue;
                }
            } catch (\Throwable $e) {
                $this->warn("   S3 existence check failed: {$e->getMessage()}");
            }

            // 2. Find the file on local disk (check all candidate paths)
            $candidates = [
                base_path("storage/app/public/{$path}"),
                base_path("storage/app/{$path}"),
                base_path("storage/app/shared/{$path}"),
                storage_path("app/public/{$path}"),
                storage_path("app/{$path}"),
                public_path("storage/{$path}"),
            ];

            $localFile = null;
            foreach ($candidates as $candidate) {
                if (is_file($candidate)) {
                    $localFile = $candidate;
                    break;
                }
            }

            if (! $localFile) {
                $this->error("   File NOT found on local disk.");
                foreach ($candidates as $c) {
                    $this->line("     Checked: {$c}");
                }
                $missing++;
                continue;
            }

            $this->line("   Found locally: {$localFile}");

            if ($isDry) {
                $this->info("   [DRY RUN] Would upload to S3: {$path}");
                $uploaded++;
                continue;
            }

            // 3. Upload to S3
            try {
                $bytes = file_get_contents($localFile);
                if ($bytes === false || $bytes === '') {
                    $this->error("   Could not read local file.");
                    $missing++;
                    continue;
                }

                Storage::disk('s3')->put($path, $bytes, 'public');
                $size = round(strlen($bytes) / 1024, 1);
                $this->info("   Uploaded to S3 ({$size} KB). Path: {$path}");
                $uploaded++;
            } catch (\Throwable $e) {
                $this->error("   Upload failed: {$e->getMessage()}");
            }
        }

        $this->newLine();
        $this->table(
            ['Uploaded', 'Already on S3', 'Not Found'],
            [[$uploaded, $skipped, $missing]]
        );

        return self::SUCCESS;
    }
}
