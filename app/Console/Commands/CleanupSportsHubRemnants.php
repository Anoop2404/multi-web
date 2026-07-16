<?php

namespace App\Console\Commands;

use App\Models\FestEvent;
use App\Models\FestSchoolEventFee;
use App\Models\Tenant;
use Illuminate\Console\Command;

/**
 * Clears season-hub leftovers from the pre-unification era:
 *
 * 1. ₹0 placeholder fee rows on sports season hubs — auto-created whenever a
 *    school merely opened the hub's registration page. Zero due, zero items,
 *    nothing paid; they only inflate "fees pending" counts and ledger views.
 * 2. Stray results_published flags on season hubs — results are published per
 *    sport event now; a flagged hub inflates the "results published" stat.
 *
 * Idempotent. Never touches sport events, non-sports programs, fee rows with
 * any amount due/paid, or rows with participation.
 */
class CleanupSportsHubRemnants extends Command
{
    protected $signature = 'fest:cleanup-sports-hub-remnants
        {--sahodaya= : Sahodaya tenant id or subdomain}
        {--dry-run : Report what would change without writing}';

    protected $description = 'Delete ₹0 placeholder fee rows and reset stray results_published flags on sports season hubs';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $sahodayaOpt = $this->option('sahodaya');

        if ($dryRun) {
            $this->warn('Dry run — no changes will be saved.');
        }

        $tenants = Tenant::query()
            ->where('type', 'sahodaya')
            ->when($sahodayaOpt, function ($q) use ($sahodayaOpt) {
                $q->where(function ($inner) use ($sahodayaOpt) {
                    $inner->where('id', $sahodayaOpt)->orWhere('subdomain', $sahodayaOpt);
                });
            })
            ->orderBy('name')
            ->get();

        if ($tenants->isEmpty()) {
            $this->error('No matching Sahodaya tenants.');

            return self::FAILURE;
        }

        $totals = ['fee_rows_deleted' => 0, 'results_flags_reset' => 0];

        foreach ($tenants as $tenant) {
            $this->info("Sahodaya: {$tenant->name} ({$tenant->id})");

            try {
                $tenant->run(function () use ($dryRun, &$totals) {
                    $result = $this->cleanupTenantDb($dryRun);
                    foreach ($result as $k => $v) {
                        $totals[$k] += $v;
                    }
                });
            } catch (\Throwable $e) {
                $this->warn("  ✗ {$tenant->name}: {$e->getMessage()}");
            } finally {
                // A failed initialize() can leave tenancy dangling on a broken tenant DB.
                if (function_exists('tenancy') && tenancy()->initialized) {
                    tenancy()->end();
                }
            }
        }

        $this->newLine();
        $verb = $dryRun ? 'Would clean' : 'Cleaned';
        $this->info("{$verb}: {$totals['fee_rows_deleted']} placeholder fee row(s) deleted; {$totals['results_flags_reset']} results_published flag(s) reset.");

        return self::SUCCESS;
    }

    /** @return array{fee_rows_deleted: int, results_flags_reset: int} */
    private function cleanupTenantDb(bool $dryRun): array
    {
        $stats = ['fee_rows_deleted' => 0, 'results_flags_reset' => 0];

        $hubs = FestEvent::query()
            ->where('event_type', 'sports')
            ->whereNull('parent_event_id')
            ->whereNull('conducting_school_id')
            ->get()
            ->filter(fn (FestEvent $e) => $e->partition_role === 'sports_season'
                || FestEvent::where('parent_event_id', $e->id)->exists());

        if ($hubs->isEmpty()) {
            $this->line('  No sports season hubs.');

            return $stats;
        }

        foreach ($hubs as $hub) {
            // 1. ₹0 placeholder fee rows: no dues, no payment, no participation.
            $placeholders = FestSchoolEventFee::query()
                ->where('event_id', $hub->id)
                ->where('total_due', '<=', 0)
                ->where(fn ($q) => $q->where('participation_item_count', '<=', 0)->orWhereNull('participation_item_count'))
                ->where(fn ($q) => $q->where('amount_paid', '<=', 0)->orWhereNull('amount_paid'));

            $count = (clone $placeholders)->count();
            if ($count > 0) {
                $this->line("  Hub \"{$hub->title}\" (#{$hub->id}): "
                    .($dryRun ? "would delete {$count}" : "deleting {$count}")
                    .' ₹0 placeholder fee row(s).');
                if (! $dryRun) {
                    $placeholders->delete();
                }
                $stats['fee_rows_deleted'] += $count;
            }

            // 2. Stray results_published flag on the hub itself.
            if ($hub->results_published) {
                $this->line("  Hub \"{$hub->title}\" (#{$hub->id}): "
                    .($dryRun ? 'would reset' : 'resetting')
                    .' results_published flag.');
                if (! $dryRun) {
                    $hub->update(['results_published' => false]);
                }
                $stats['results_flags_reset']++;
            }
        }

        return $stats;
    }
}
