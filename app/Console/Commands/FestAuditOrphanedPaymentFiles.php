<?php

namespace App\Console\Commands;

use App\Models\FeeReceipt;
use App\Models\Tenant;
use App\Support\TenantStorage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Read-only check for payment-proof FILES sitting in storage under fest-payments/ that no
 * FeeReceipt row references at all — a stricter check than fest:audit-orphaned-fee-receipts
 * (which only finds receipt ROWS whose fee target was deleted). This catches the rarer case
 * where a file was uploaded to storage but its FeeReceipt row was never created (a failed
 * submission), or was deleted separately from its file. Never writes or deletes anything.
 */
class FestAuditOrphanedPaymentFiles extends Command
{
    protected $signature = 'fest:audit-orphaned-payment-files
        {--sahodaya= : Sahodaya tenant id or subdomain (required)}
        {--school= : Optional school tenant id to narrow the scan to one school\'s folder}';

    protected $description = 'Read-only scan of fest-payments/ storage for uploaded proof files no FeeReceipt row references';

    public function handle(): int
    {
        $sahodayaOpt = $this->option('sahodaya');
        if (! $sahodayaOpt) {
            $this->error('--sahodaya is required.');

            return self::FAILURE;
        }

        $tenant = Tenant::query()
            ->where('type', 'sahodaya')
            ->where(function ($q) use ($sahodayaOpt) {
                $q->where('id', $sahodayaOpt)->orWhere('subdomain', $sahodayaOpt);
            })
            ->first();

        if (! $tenant) {
            $this->error("No matching Sahodaya tenant for '{$sahodayaOpt}'.");

            return self::FAILURE;
        }

        $schoolOpt = $this->option('school');
        $exitCode = self::SUCCESS;

        try {
            $tenant->run(function () use ($schoolOpt, &$exitCode) {
                $exitCode = $this->audit($schoolOpt);
            });
        } finally {
            if (function_exists('tenancy') && tenancy()->initialized) {
                tenancy()->end();
            }
        }

        return $exitCode;
    }

    private function audit(?string $schoolOpt): int
    {
        $disk = Storage::disk(TenantStorage::uploadDisk());
        $prefix = $schoolOpt ? "fest-payments/{$schoolOpt}" : 'fest-payments';

        if (! $disk->exists($prefix)) {
            $this->info("No files found under {$prefix}/ on disk '".TenantStorage::uploadDisk()."'.");

            return self::SUCCESS;
        }

        $files = $disk->allFiles($prefix);
        if ($files === []) {
            $this->info("{$prefix}/ exists but is empty.");

            return self::SUCCESS;
        }

        $knownPaths = FeeReceipt::whereNotNull('file_path')->pluck('file_path')
            ->merge(FeeReceipt::whereNotNull('generated_receipt_path')->pluck('generated_receipt_path'))
            ->filter()
            ->unique()
            ->flip(); // path => true, for O(1) lookup

        $orphaned = [];
        foreach ($files as $path) {
            if (! isset($knownPaths[$path])) {
                $orphaned[] = [
                    'path' => $path,
                    'size_kb' => round($disk->size($path) / 1024, 1),
                    'modified' => date('Y-m-d H:i', $disk->lastModified($path)),
                ];
            }
        }

        $this->info(count($files).' file(s) scanned under '.$prefix.'/.');

        if ($orphaned === []) {
            $this->info('Every file is referenced by a FeeReceipt row — nothing orphaned on disk.');

            return self::SUCCESS;
        }

        $this->table(['File path', 'Size (KB)', 'Last modified'], $orphaned);
        $this->newLine();
        $this->info(count($orphaned).' file(s) found on disk with NO matching FeeReceipt row. The folder name after fest-payments/ is the school\'s tenant id — that tells you which school it belongs to.');

        return self::SUCCESS;
    }
}
