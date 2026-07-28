<?php

namespace Tests\Unit\Services\Events;

use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestEventSchoolPartition;
use App\Models\Tenant;
use App\Services\Events\FestItemSyncService;
use App\Services\Events\FestPartitionService;
use App\Services\Events\FestRegionPartitionService;
use App\Services\Events\FestRegistrationRouterService;
use Database\Seeders\SahodayaMasterDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class FestPartitionServiceTest extends TestCase
{
    use RefreshDatabase;

    private function sahodaya(): Tenant
    {
        $this->seed(SahodayaMasterDataSeeder::class);

        return Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'sahodaya',
            'name' => 'Partition Test Sahodaya',
            'domain' => 'partition-sahodaya.test',
            'is_active' => true,
        ]);
    }

    public function test_standard_event_is_not_partitioned_hub(): void
    {
        $sahodaya = $this->sahodaya();

        $event = FestEvent::create([
            'tenant_id' => $sahodaya->id,
            'title' => 'Standard Kalotsav',
            'event_type' => 'kalolsavam',
            'conduct_mode' => 'standard',
            'level_round' => 'sahodaya',
            'status' => 'draft',
        ]);

        $service = app(FestPartitionService::class);

        $this->assertSame('standard', $service->conductMode($event));
        $this->assertFalse($service->isPartitionedHub($event));
    }

    public function test_partitioned_hub_with_children(): void
    {
        $sahodaya = $this->sahodaya();

        $hub = FestEvent::create([
            'tenant_id' => $sahodaya->id,
            'title' => 'MCS Kalotsav',
            'event_type' => 'kalolsavam',
            'conduct_mode' => 'partitioned',
            'level_round' => 'sahodaya',
            'status' => 'draft',
        ]);

        FestEvent::create([
            'tenant_id' => $sahodaya->id,
            'title' => 'Tirur Region',
            'event_type' => 'kalolsavam',
            'parent_event_id' => $hub->id,
            'partition_key' => 'tirur',
            'partition_role' => 'region',
            'cluster_key' => 'tirur',
            'cluster_label' => 'Tirur',
            'level_round' => 'sahodaya',
            'status' => 'draft',
        ]);

        $service = app(FestPartitionService::class);

        $this->assertTrue($service->isPartitionedHub($hub));
        $this->assertCount(1, $service->partitions($hub));
        $this->assertSame('tirur', $service->partitionKey($service->partitions($hub)->first()));
    }

    public function test_should_combine_at_finale_flag(): void
    {
        $sahodaya = $this->sahodaya();

        $hub = FestEvent::create([
            'tenant_id' => $sahodaya->id,
            'title' => 'MCS Kalotsav',
            'event_type' => 'kalolsavam',
            'conduct_mode' => 'partitioned',
            'combine_regions_at_finale' => false,
            'level_round' => 'sahodaya',
            'status' => 'draft',
        ]);

        $service = app(FestPartitionService::class);

        $this->assertFalse($service->shouldCombineAtFinale($hub));
    }

    public function test_english_fest_copies_on_stage_group_items_to_region_and_routes_there(): void
    {
        $sahodaya = $this->sahodaya();
        $school = Tenant::create([
            'id' => (string) Str::uuid(),
            'parent_id' => $sahodaya->id,
            'type' => 'school',
            'name' => 'English Fest School',
            'domain' => 'english-fest-school.test',
            'is_active' => true,
        ]);
        $hub = FestEvent::create([
            'tenant_id' => $sahodaya->id,
            'title' => 'English Fest',
            'event_type' => 'english_fest',
            'conductor_level' => 'sahodaya',
            'conduct_mode' => 'partitioned',
            'level_round' => 'sahodaya',
            'status' => 'registration_open',
        ]);
        $item = FestEventItem::create([
            'event_id' => $hub->id,
            'title' => 'Choral Reading',
            'item_code' => 'EF104',
            'stage_type' => 'on_stage',
            'participant_type' => 'group',
            'class_group' => 'up',
            'min_group_size' => 10,
            'max_group_size' => 12,
            'max_per_school' => 1,
            'is_enabled' => true,
        ]);

        $region = app(FestPartitionService::class)->spawnPartition($hub, [
            'title' => 'Tirur Region',
            'partition_key' => 'tirur',
            'partition_role' => 'region',
        ]);
        FestEventSchoolPartition::create([
            'event_id' => $hub->id,
            'school_id' => $school->id,
            'partition_key' => 'tirur',
            'assigned_at' => now(),
        ]);

        $this->assertDatabaseHas('fest_event_items', [
            'event_id' => $region->id,
            'inherited_from_item_id' => $item->id,
            'item_code' => 'EF104',
        ]);
        $regionItem = FestEventItem::where('event_id', $region->id)
            ->where('inherited_from_item_id', $item->id)
            ->firstOrFail();
        $regionItem->update(['class_group' => 'CATEGORY__II']);
        $syncedItem = app(FestItemSyncService::class)
            ->copyItemToPartition($hub, $item->fresh(), $region, 'region');
        $this->assertSame('up', $syncedItem?->class_group);
        $this->assertSame('draft', $region->status);
        app(FestRegionPartitionService::class)->inheritRegistrationLifecycle($hub, $region);
        $this->assertSame('registration_open', $region->fresh()->status);
        $this->assertSame(
            $region->id,
            app(FestRegistrationRouterService::class)
                ->resolveTargetEvent($hub, $item, $school->id)
                ->id,
        );
    }
}
