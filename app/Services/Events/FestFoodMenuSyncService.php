<?php

namespace App\Services\Events;

use App\Models\FestEvent;
use App\Models\FestFoodMenuItem;

/**
 * Region-partition equivalent of FestItemSyncService::copyItemsToPartition() — but for the
 * food menu, which previously had no copy mechanism at all (docs/REGION_SCOPED_ADMIN_AND_EVENT_FLOW_PLAN.md
 * Gap I). Before this, a hub's food menu and payee settings had to be manually recreated on
 * every region partition child; competition items already had this, food never did.
 *
 * Idempotent and additive only: re-running never removes a menu item a region has already
 * customized, and never overwrites payee settings a region has already configured for itself
 * (matches how FestRegionPartitionService::syncPartitionsFromRegions() treats the partition
 * title — set once, left alone after).
 */
class FestFoodMenuSyncService
{
    /** @return int number of menu items created on the child */
    public function copyMenuToPartition(FestEvent $hub, FestEvent $child): int
    {
        $hub->loadMissing('foodMenuItems');
        $count = 0;

        foreach ($hub->foodMenuItems as $item) {
            if ($this->copyMenuItemToPartition($item, $child)) {
                $count++;
            }
        }

        $this->copyPayeeSettings($hub, $child);

        return $count;
    }

    /** @return list<FestEvent> children updated */
    public function copyMenuToAllPartitions(FestEvent $hub): array
    {
        $updated = [];

        foreach (app(FestPartitionService::class)->partitions($hub) as $partition) {
            $this->copyMenuToPartition($hub, $partition);
            $updated[] = $partition;
        }

        return $updated;
    }

    private function copyMenuItemToPartition(FestFoodMenuItem $item, FestEvent $child): bool
    {
        $exists = FestFoodMenuItem::forEvent($child->id)
            ->where('menu_date', $item->menu_date)
            ->where('meal_type', $item->meal_type)
            ->where('name', $item->name)
            ->exists();

        if ($exists) {
            return false;
        }

        FestFoodMenuItem::create([
            'tenant_id'      => $child->tenant_id,
            'event_id'       => $child->id,
            'menu_date'      => $item->menu_date,
            'meal_type'      => $item->meal_type,
            'name'           => $item->name,
            'description'    => $item->description,
            'price'          => $item->price,
            'is_available'   => $item->is_available,
            'max_per_school' => $item->max_per_school,
            'sort_order'     => $item->sort_order,
        ]);

        return true;
    }

    /** Only sets payee fields on the child the first time — never clobbers a region's own choice. */
    private function copyPayeeSettings(FestEvent $hub, FestEvent $child): void
    {
        if ($child->food_payee_type || ! $hub->food_payee_type) {
            return;
        }

        $child->update([
            'food_payee_type'     => $hub->food_payee_type,
            'food_host_school_id' => $hub->food_host_school_id,
        ]);
    }
}
