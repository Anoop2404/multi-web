<?php

namespace App\Console\Commands;

use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestEventPhase;
use App\Models\FestMark;
use App\Models\FestRegistration;
use App\Models\FestSchedule;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/**
 * Repairs the surplus flagged by fest:audit-event-topology's phase_leaf_item_scope_drift
 * finding: items that ended up on a phase leaf event without actually belonging to that
 * leaf's own phase, left behind by the legacy-partition item-sync bug fixed in
 * FestEventController::syncItemToExistingPartitions() and its two siblings
 * (FestItemSyncService::resyncAllItemsToPartitions(), FestRegistrationController::
 * hydrateEventForSchoolRegistration()'s lazy-init fallback). A misplaced item's correctly-
 * scoped copy already exists independently on its rightful phase leaf (that leaf got it
 * via FestPhaseTopologyService::syncLeaf()'s own phase-aware copyItemsToPartition() call),
 * so hiding the surplus copy here never loses the item itself.
 *
 * Disables (is_enabled = false), never deletes -- same "can't remove, so disable instead"
 * pattern FestItemSyncService::removeProgramItemFromAllPropagations() already uses
 * elsewhere in this codebase. Every consumer that lists a leaf's items (Mark Entry, the
 * item-counts report, the public portal's item finder, and others) already filters on
 * is_enabled = true, so a disabled item disappears from all of them without any query
 * changes and without touching a single row anyone might depend on. Fully reversible:
 * re-enable via the Items & Catalog page, or `FestEventItem::whereIn('id', [...])
 * ->update(['is_enabled' => true])`.
 *
 * Deliberately conservative on top of that: an item is only ever disabled if it has zero
 * registrations, marks, AND schedule rows -- any one of those and it's left alone (and
 * left enabled) for manual review, since disabling an item a school has already
 * registered for would hide it from the admins managing that registration.
 *
 * Runs in --dry-run mode by default. Requires --commit to write changes.
 */
class FestRepairPhaseLeafItemScope extends Command
{
    protected $signature = 'fest:repair-phase-leaf-item-scope
        {--sahodaya= : Sahodaya tenant id or subdomain}
        {--event= : Target root fest_events id}
        {--commit : Execute disables (defaults to dry-run)}';

    protected $description = 'Disable items copied onto a phase leaf outside its own phase (fest:audit-event-topology\'s phase_leaf_item_scope_drift finding) -- never deletes';

    public function handle(): int
    {
        $sahodayaOpt = $this->option('sahodaya');
        $eventOpt = $this->option('event');
        $commit = (bool) $this->option('commit');

        if (! $commit) {
            $this->info('Running in DRY-RUN mode. Use --commit to apply changes.');
        }

        $tenants = Tenant::query()
            ->where('type', 'sahodaya')
            ->when($sahodayaOpt, function ($q) use ($sahodayaOpt) {
                $q->where('id', $sahodayaOpt)->orWhere('subdomain', $sahodayaOpt);
            })
            ->get();

        if ($tenants->isEmpty()) {
            $this->error('No matching Sahodaya tenants found.');

            return self::FAILURE;
        }

        foreach ($tenants as $tenant) {
            $tenant->run(function () use ($tenant, $eventOpt, $commit) {
                $this->repairTenant($tenant, $eventOpt, $commit);
            });
        }

        return self::SUCCESS;
    }

    private function repairTenant(Tenant $tenant, null|string|int $eventOpt, bool $commit): void
    {
        $roots = FestEvent::query()
            ->where('tenant_id', $tenant->id)
            ->whereNull('parent_event_id')
            ->when($eventOpt, fn ($q) => $q->whereKey($eventOpt))
            ->get();

        foreach ($roots as $root) {
            $leaves = FestEvent::where('parent_event_id', $root->id)
                ->whereNotNull('source_phase_id')
                ->get();

            if ($leaves->isEmpty()) {
                continue;
            }

            $phasesById = FestEventPhase::where('event_id', $root->id)->get()->keyBy('id');

            foreach ($leaves as $leaf) {
                $this->repairLeaf($tenant, $root, $leaf, $phasesById, $commit);
            }
        }
    }

    private function repairLeaf(Tenant $tenant, FestEvent $root, FestEvent $leaf, Collection $phasesById, bool $commit): void
    {
        $phase = $phasesById->get($leaf->source_phase_id);
        if (! $phase) {
            return;
        }

        $items = FestEventItem::where('event_id', $leaf->id)
            ->where('is_enabled', true)
            ->whereNotNull('inherited_from_item_id')
            ->get(['id', 'title', 'inherited_from_item_id']);

        if ($items->isEmpty()) {
            return;
        }

        $hubItemsById = FestEventItem::whereIn('id', $items->pluck('inherited_from_item_id'))
            ->get(['id', 'phase_id'])
            ->keyBy('id');

        $misplaced = $items->filter(function (FestEventItem $item) use ($hubItemsById, $phase) {
            $hubItem = $hubItemsById->get($item->inherited_from_item_id);

            return ! $hubItem || (int) ($hubItem->phase_id ?? 0) !== (int) $phase->id;
        });

        if ($misplaced->isEmpty()) {
            return;
        }

        $misplacedIds = $misplaced->pluck('id');

        $unsafeIds = FestRegistration::whereIn('item_id', $misplacedIds)->distinct('item_id')->pluck('item_id')
            ->merge(Schema::hasTable('fest_marks') ? FestMark::whereIn('item_id', $misplacedIds)->distinct('item_id')->pluck('item_id') : collect())
            ->merge(Schema::hasTable('fest_schedules') ? FestSchedule::whereIn('item_id', $misplacedIds)->distinct('item_id')->pluck('item_id') : collect())
            ->unique();

        $safeToDisable = $misplaced->reject(fn (FestEventItem $item) => $unsafeIds->contains($item->id));

        $this->line("[{$tenant->id}] Leaf #{$leaf->id} ({$leaf->title}, phase '{$phase->name}'): {$misplaced->count()} misplaced item(s), {$safeToDisable->count()} safe to disable.");

        if ($unsafeIds->isNotEmpty()) {
            $this->warn("  {$unsafeIds->count()} misplaced item(s) have registrations/marks/a schedule row and were SKIPPED -- resolve manually: ".$misplaced->whereIn('id', $unsafeIds)->pluck('title')->implode(', '));
        }

        if ($commit && $safeToDisable->isNotEmpty()) {
            FestEventItem::whereIn('id', $safeToDisable->pluck('id'))->update(['is_enabled' => false]);
            $this->info("  Disabled {$safeToDisable->count()} item(s) on leaf #{$leaf->id}.");
        }
    }
}
