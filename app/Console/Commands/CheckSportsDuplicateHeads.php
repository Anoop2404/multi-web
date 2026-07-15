<?php

namespace App\Console\Commands;

use App\Models\FestEvent;
use App\Models\FestItemHead;
use App\Models\Tenant;
use Illuminate\Console\Command;

class CheckSportsDuplicateHeads extends Command
{
    protected $signature = 'fest:check-sports-duplicate-heads
                            {--sahodaya= : Sahodaya tenant id (defaults to every Sahodaya)}';

    protected $description = 'Read-only diagnostic: find any sports Event Head duplicated by catalog_key '
        .'under the same season (a symptom of the syncEventHeads()/promote() stale-lookup bug fixed '
        .'alongside this command), and any duplicate discipline events spawned from the same head. '
        .'Reports only -- does not change any data.';

    public function handle(): int
    {
        $sahodayaId = $this->option('sahodaya');

        $sahodayas = $sahodayaId
            ? Tenant::query()->where('type', 'sahodaya')->whereKey($sahodayaId)->get()
            : Tenant::query()->where('type', 'sahodaya')->orderBy('name')->get();

        if ($sahodayas->isEmpty()) {
            $this->error('No matching Sahodaya tenant found.');

            return self::FAILURE;
        }

        $totalIssues = 0;

        foreach ($sahodayas as $sahodaya) {
            $this->info("Sahodaya: {$sahodaya->name} ({$sahodaya->id})");

            try {
                $sahodaya->run(function () use (&$totalIssues) {
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
                        $disciplineEventIds = FestEvent::where('parent_event_id', $season->id)->pluck('id');

                        $heads = FestItemHead::where(function ($q) use ($season, $disciplineEventIds) {
                            $q->where('event_id', $season->id)->orWhereIn('event_id', $disciplineEventIds);
                        })->whereNull('parent_id')->whereNotNull('catalog_key')->get();

                        $byCatalogKey = $heads->groupBy('catalog_key');
                        $duplicateRows = [];

                        foreach ($byCatalogKey as $catalogKey => $group) {
                            if ($group->count() < 2) {
                                continue;
                            }

                            foreach ($group as $head) {
                                $duplicateRows[] = [
                                    'catalog_key' => $catalogKey,
                                    'head_id' => $head->id,
                                    'head_name' => $head->name,
                                    'event_id' => $head->event_id,
                                    'discipline_event_id' => $head->discipline_event_id ?: '—',
                                    'items' => \App\Models\FestEventItem::where('head_id', $head->id)->count(),
                                ];
                            }
                        }

                        if ($duplicateRows === []) {
                            $this->line("  {$season->title}: no duplicate heads found ({$heads->count()} head(s) checked).");

                            continue;
                        }

                        $this->warn("  {$season->title}: duplicate heads found!");
                        $this->table(
                            ['catalog_key', 'head_id', 'head_name', 'event_id', 'discipline_event_id', 'items'],
                            $duplicateRows,
                        );
                        $totalIssues += count($duplicateRows);
                    }
                });
            } catch (\Throwable $e) {
                $this->warn("  ✗ {$sahodaya->name}: {$e->getMessage()}");
            } finally {
                if (function_exists('tenancy') && tenancy()->initialized) {
                    tenancy()->end();
                }
            }
        }

        $this->newLine();
        if ($totalIssues > 0) {
            $this->warn("Found {$totalIssues} duplicate head row(s) across all checked Sahodayas. Do not delete anything until we've confirmed which row is the correct one to keep (check the 'items' column and whether its discipline_event_id has real registrations).");
        } else {
            $this->info('No duplicate heads found.');
        }

        return self::SUCCESS;
    }
}
