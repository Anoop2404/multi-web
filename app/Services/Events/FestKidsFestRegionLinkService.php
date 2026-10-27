<?php

namespace App\Services\Events;

use App\Models\FestEvent;
use App\Models\Region;
use Illuminate\Support\Facades\DB;

/** Give legacy Kids Fest venue partitions a stable, event-scoped region identity. */
class FestKidsFestRegionLinkService
{
    public function ensureRegionLink(FestEvent $partition): void
    {
        if ($partition->region_id || ! $partition->parent_event_id || $partition->source_phase_id
            || $partition->event_type !== 'kids_fest'
            || ! in_array($partition->partition_role, ['region', 'cluster'], true)) {
            return;
        }

        $root = $partition->rootEvent();
        if ($root->usesPhasedRegionalBilling() || $root->conduct_mode !== 'partitioned'
            || $partition->tenant_id !== $root->tenant_id) {
            return;
        }

        DB::transaction(function () use ($partition, $root) {
            $locked = FestEvent::whereKey($partition->id)->lockForUpdate()->firstOrFail();
            if ($locked->region_id) {
                $partition->region_id = $locked->region_id;
                $partition->unsetRelation('region');

                return;
            }

            $name = $locked->cluster_label ?: $locked->title;
            $code = Region::generateUniqueCode($root->tenant_id, $locked->partition_key ?: $locked->cluster_key ?: $name, $root->id);
            $region = Region::create([
                'tenant_id' => $root->tenant_id,
                'fest_event_id' => $root->id,
                'name' => $name,
                'code' => $code,
                'is_active' => true,
            ]);
            $locked->update(['region_id' => $region->id]);
            $partition->region_id = $region->id;
            $partition->setRelation('region', $region);
        });
    }
}
