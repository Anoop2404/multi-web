<?php

namespace Tests\Unit\Services\Events;

use App\Models\FestEvent;
use App\Models\Tenant;
use App\Services\Events\FestPartitionService;
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
}
