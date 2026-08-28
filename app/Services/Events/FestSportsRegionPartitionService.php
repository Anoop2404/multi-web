<?php

namespace App\Services\Events;

use App\Models\FestEvent;
use App\Models\Region;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Sports nested-region topology (docs/REGION_PHASE_EVENT_REPORTING_REMEDIATION_PLAN.md
 * §7, Phase 7):
 *
 *   Sports season (root)
 *     +-- Athletics (sports_discipline)
 *     |     +-- Region A (region)
 *     |     +-- Region B (region)
 *     +-- Chess (sports_discipline)
 *           +-- Region A (region)
 *           +-- Region B (region)
 *
 * Deliberately does NOT reuse FestPartitionService::spawnPartition() — that method
 * explicitly rejects being called on a non-root event ("Create partitions on the hub
 * event, not a child partition"), which is correct for its existing single-level
 * region/finale/cluster callers and shouldn't be loosened just for this. This service
 * creates the second-level region child directly, reusing the same item-copy primitive
 * (FestItemSyncService::copyItemsToPartition()) spawnPartition() itself calls.
 *
 * Until this ships and is opted into per season (see fest_sports_region_tree feature
 * flag, Phase 8), FestSportsEventSyncService::syncSeason() remains the only sync path
 * for Sports, and generic single-level region auto-sync must keep skipping Sports
 * events entirely — see FestAuditEventTopology's sports_root_generic_regional_hub check,
 * which flags a season that got generic region children instead of this nested shape.
 */
class FestSportsRegionPartitionService
{
    public function __construct(private FestItemSyncService $itemSync) {}

    /**
     * Create (or reuse) one region child under every sports_discipline child of $season,
     * for every requested region (default: every active region for the tenant). Copies
     * catalog items into each new leaf; does not touch existing leaves' items again
     * (copyItemsToPartition() is itself idempotent per item via inherited_from_item_id/
     * item_code matching — see FestItemSyncService::copyItemToPartition()).
     *
     * @param  ?list<int>  $regionIds  Defaults to every active region for the tenant.
     * @return array{sports: int, regions: int, created: int, already_existed: int}
     */
    public function syncRegionsForSeason(FestEvent $season, ?array $regionIds = null): array
    {
        if ($season->event_type !== 'sports' || $season->parent_event_id !== null) {
            throw new HttpException(422, 'syncRegionsForSeason() must be called on a top-level Sports season event.');
        }

        $regions = Region::forTenant($season->tenant_id)
            ->active()
            ->globalOnly()
            ->when($regionIds !== null, fn ($q) => $q->whereIn('id', $regionIds))
            ->get();

        if ($regions->isEmpty()) {
            throw new HttpException(422, 'No active regions to sync for this tenant.');
        }

        $sportChildren = $season->childrenForRoles(['sports_discipline']);

        $created = 0;
        $existed = 0;

        foreach ($sportChildren as $sport) {
            foreach ($regions as $region) {
                $child = FestEvent::where('parent_event_id', $sport->id)
                    ->where('partition_role', 'region')
                    ->where('region_id', $region->id)
                    ->first();

                if ($child) {
                    $existed++;

                    continue;
                }

                $regionChild = FestEvent::create([
                    'tenant_id'         => $sport->tenant_id,
                    'title'             => $sport->title.' — '.$region->name,
                    'event_type'        => $sport->event_type,
                    'parent_event_id'   => $sport->id,
                    'root_event_id'     => $season->id,
                    'partition_key'     => Str::slug(($region->code ?: $region->name).'-'.$sport->id),
                    'partition_role'    => 'region',
                    'region_id'         => $region->id,
                    'level_round'       => $sport->level_round,
                    'venue'             => $sport->venue,
                    'event_start'       => $sport->event_start,
                    'event_end'         => $sport->event_end,
                    'sport_discipline'  => $sport->sport_discipline,
                    'conduct_mode'      => 'standard',
                    'status'            => 'draft',
                    'nav_hidden'        => true,
                ]);

                $this->itemSync->copyItemsToPartition($sport, $regionChild, 'region');

                $created++;
            }
        }

        return [
            'sports' => $sportChildren->count(),
            'regions' => $regions->count(),
            'created' => $created,
            'already_existed' => $existed,
        ];
    }

    /**
     * Route a school's sport registration to the correct sport-region leaf, given the
     * sport (sports_discipline) event and the school's active-year region. Returns null
     * if that leaf hasn't been synced yet (syncRegionsForSeason() not yet run for this
     * sport/region combination) — callers should fail closed, not fall back to the sport
     * event itself (that would defeat the isolation this topology exists for).
     */
    public function targetLeafFor(FestEvent $sportEvent, int $regionId): ?FestEvent
    {
        return FestEvent::where('parent_event_id', $sportEvent->id)
            ->where('partition_role', 'region')
            ->where('region_id', $regionId)
            ->first();
    }
}
