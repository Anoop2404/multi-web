<?php

namespace App\Console\Commands;

use App\Models\FestEvent;
use App\Models\Tenant;
use App\Services\Events\PromoteSportsHeadsToDisciplineEvents;
use Illuminate\Console\Command;

class PromoteAllSportsHeads extends Command
{
    protected $signature = 'fest:promote-sports-heads
                            {--sahodaya= : Sahodaya tenant id (defaults to every Sahodaya)}
                            {--dry-run : List what would be promoted without saving}';

    protected $description = 'Phase 0 backfill for the Sports head-first rebuild: promote every '
        .'un-promoted Event Head on every Sports season hub into its own dedicated discipline '
        .'event (idempotent — already-promoted heads are skipped). Run --dry-run first.';

    public function handle(PromoteSportsHeadsToDisciplineEvents $promoter): int
    {
        $sahodayaId = $this->option('sahodaya');
        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->warn('Dry run — no changes will be saved.');
        }

        $sahodayas = $sahodayaId
            ? Tenant::query()->where('type', 'sahodaya')->whereKey($sahodayaId)->get()
            : Tenant::query()->where('type', 'sahodaya')->orderBy('name')->get();

        if ($sahodayas->isEmpty()) {
            $this->error('No matching Sahodaya tenant found.');

            return self::FAILURE;
        }

        $totalPromoted = 0;
        $totalSkipped = 0;
        $totalSeasons = 0;

        foreach ($sahodayas as $sahodaya) {
            $this->info("Sahodaya: {$sahodaya->name} ({$sahodaya->id})");

            try {
                $sahodaya->run(function () use ($sahodaya, $promoter, $dryRun, &$totalPromoted, &$totalSkipped, &$totalSeasons) {
                    $seasons = FestEvent::query()
                        ->where('event_type', 'sports')
                        ->whereNull('parent_event_id')
                        ->orderBy('id')
                        ->get();

                    if ($seasons->isEmpty()) {
                        $this->line('  (no sports season events)');

                        return;
                    }

                    foreach ($seasons as $season) {
                        $status = $promoter->status($season);
                        if (! $status['can_promote']) {
                            $this->line("  {$season->title}: nothing pending ({$status['linked_count']}/{$status['head_count']} head(s) already promoted)");

                            continue;
                        }

                        $totalSeasons++;
                        $rows = $promoter->promote($season, $dryRun);

                        $table = collect($rows)->map(fn ($r) => [
                            'head' => $r['head_name'],
                            'status' => ! empty($r['dry_run']) ? 'would promote' : (! empty($r['skipped']) ? 'already linked' : 'promoted'),
                            'discipline_event' => $r['title'],
                        ])->all();

                        $this->table(['Event Head', 'Status', 'Discipline event'], $table);

                        $promoted = collect($rows)->filter(fn ($r) => empty($r['skipped']) && empty($r['dry_run']))->count();
                        $wouldPromote = collect($rows)->filter(fn ($r) => ! empty($r['dry_run']))->count();
                        $skipped = collect($rows)->filter(fn ($r) => ! empty($r['skipped']))->count();

                        $totalPromoted += $promoted;
                        $totalSkipped += $skipped;

                        $this->line("  {$season->title}: ".($dryRun ? "would promote {$wouldPromote}" : "promoted {$promoted}").", already linked {$skipped}");
                    }
                });
            } catch (\Throwable $e) {
                $this->warn("  ✗ {$sahodaya->name}: {$e->getMessage()}");
            }
        }

        $this->newLine();
        $this->info(($dryRun ? 'Would promote' : 'Promoted')." {$totalPromoted} head(s) across {$totalSeasons} season(s); already linked {$totalSkipped}.");

        return self::SUCCESS;
    }
}
