<?php

namespace Tests\Unit\Services\Events;

use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestStateProgram;
use App\Models\FestStateProgramItem;
use App\Models\FestStateProgramPropagation;
use App\Models\Tenant;
use App\Services\Events\FestItemSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class FestItemSyncServiceTest extends TestCase
{
    use RefreshDatabase;

    private function sahodaya(): Tenant
    {
        return Tenant::create([
            'id'     => (string) Str::uuid(),
            'type'   => 'sahodaya',
            'name'   => 'Sync Test Sahodaya',
            'domain' => 'sync-sahodaya.test',
            'is_active' => true,
        ]);
    }

    public function test_syncing_a_newly_added_state_item_does_not_revert_an_existing_items_sahodaya_customization(): void
    {
        $program = FestStateProgram::create([
            'title'          => 'Kerala State Kalotsavam 2026',
            'event_type'     => 'kalolsavam',
            'conduct_levels' => ['sahodaya', 'state'],
            'status'         => 'published',
        ]);

        $existingStateItem = FestStateProgramItem::create([
            'state_program_id' => $program->id,
            'title'            => 'Light Music (Boys)',
            'item_code'        => 'LM01',
            'category'         => 'music',
            'max_per_school'   => 1,
        ]);

        $sahodaya = $this->sahodaya();

        $event = FestEvent::create([
            'tenant_id'        => $sahodaya->id,
            'title'            => 'Kerala State Kalotsavam 2026',
            'event_type'       => 'kalolsavam',
            'level_round'      => 'sahodaya',
            'state_program_id' => $program->id,
            'status'           => 'draft',
        ]);

        // Sahodaya customized their own copy after it was seeded — via
        // FestEventController::bulkUpdateItemCaps() they raised max_per_school from the
        // state template's 1 to 2, and separately disabled the item locally.
        $tenantItem = FestEventItem::create([
            'event_id'              => $event->id,
            'state_program_item_id' => $existingStateItem->id,
            'owner_level'           => 'state',
            'title'                 => $existingStateItem->title,
            'item_code'             => $existingStateItem->item_code,
            'category'              => $existingStateItem->category,
            'max_per_school'        => 2,
            'is_enabled'            => false,
        ]);

        // State Admin adds a brand-new item to the already-published program.
        $newStateItem = FestStateProgramItem::create([
            'state_program_id' => $program->id,
            'title'            => 'Group Dance',
            'item_code'        => 'GD01',
            'category'         => 'dance',
            'max_per_school'   => 1,
        ]);

        $synced = app(FestItemSyncService::class)->syncProgramToEvent($program->fresh('items'), $event);

        // Only the brand-new item should have been created on the Sahodaya's event —
        // the pre-existing item is left alone entirely.
        $this->assertSame(1, $synced);

        $tenantItem->refresh();
        $this->assertSame(2, $tenantItem->max_per_school, 'Sahodaya-customized max_per_school must survive a state item sync.');
        $this->assertFalse((bool) $tenantItem->is_enabled, 'Sahodaya-disabled item must stay disabled after sync.');

        $this->assertDatabaseHas('fest_event_items', [
            'event_id'              => $event->id,
            'state_program_item_id' => $newStateItem->id,
            'max_per_school'        => 1,
        ]);
    }

    public function test_syncing_all_propagations_creates_new_items_without_touching_existing_sahodaya_customizations(): void
    {
        $program = FestStateProgram::create([
            'title'          => 'Kerala State Kalotsavam 2026',
            'event_type'     => 'kalolsavam',
            'conduct_levels' => ['sahodaya', 'state'],
            'status'         => 'published',
        ]);

        $existingStateItem = FestStateProgramItem::create([
            'state_program_id' => $program->id,
            'title'            => 'Light Music (Boys)',
            'item_code'        => 'LM01',
            'category'         => 'music',
            'qualify_count'    => 1,
        ]);

        $sahodaya = $this->sahodaya();

        $event = FestEvent::create([
            'tenant_id'        => $sahodaya->id,
            'title'            => 'Kerala State Kalotsavam 2026',
            'event_type'       => 'kalolsavam',
            'level_round'      => 'sahodaya',
            'state_program_id' => $program->id,
            'status'           => 'draft',
        ]);

        // Sahodaya raised qualify_count from the state default of 1 to 3 for their own round.
        $tenantItem = FestEventItem::create([
            'event_id'              => $event->id,
            'state_program_item_id' => $existingStateItem->id,
            'owner_level'           => 'state',
            'title'                 => $existingStateItem->title,
            'item_code'             => $existingStateItem->item_code,
            'category'              => $existingStateItem->category,
            'qualify_count'         => 3,
        ]);

        FestStateProgramPropagation::create([
            'state_program_id' => $program->id,
            'sahodaya_id'      => $sahodaya->id,
            'tenant_event_id'  => $event->id,
            'level_round'      => 'sahodaya',
        ]);

        // This is the action the State Admin actually takes: add one new item to the
        // program (StateFestProgramController::storeItem()), which fans out to every
        // propagated Sahodaya via syncProgramToAllPropagations().
        FestStateProgramItem::create([
            'state_program_id' => $program->id,
            'title'            => 'Group Dance',
            'item_code'        => 'GD01',
            'category'         => 'dance',
        ]);

        app(FestItemSyncService::class)->syncProgramToAllPropagations($program->fresh('items'));

        $tenantItem->refresh();
        $this->assertSame(
            3,
            $tenantItem->qualify_count,
            'Adding a new state item and re-syncing must not revert an existing item a Sahodaya already customized.'
        );

        $this->assertDatabaseHas('fest_event_items', [
            'event_id'  => $event->id,
            'item_code' => 'GD01',
        ]);
    }

    public function test_copy_item_to_partition_does_not_revert_a_regions_own_customized_item_caps(): void
    {
        $sahodaya = $this->sahodaya();

        $hub = FestEvent::create([
            'tenant_id'    => $sahodaya->id,
            'title'        => 'Partitioned Hub',
            'event_type'   => 'kalolsavam',
            'conduct_mode' => 'partitioned',
            'level_round'  => 'sahodaya',
            'status'       => 'registration_open',
        ]);

        $hubItem = FestEventItem::create([
            'event_id'          => $hub->id,
            'title'             => 'Group Dance',
            'item_code'         => 'GD01',
            'owner_level'       => 'sahodaya',
            'participant_type'  => 'group',
            'max_per_school'    => 1,
            'is_enabled'        => true,
            'display_order'     => 1,
        ])->fresh();

        $region = FestEvent::create([
            'tenant_id'        => $sahodaya->id,
            'parent_event_id'  => $hub->id,
            'partition_role'   => 'region',
            'partition_key'    => 'tirur',
            'title'            => 'Tirur Region',
            'event_type'       => 'kalolsavam',
            'level_round'      => 'sahodaya',
            'status'           => 'draft',
        ]);

        $service = app(FestItemSyncService::class);

        // Seeds the region's own copy from the hub — nothing to protect yet.
        $regionItem = $service->copyItemToPartition($hub, $hubItem, $region, 'region');
        $this->assertNotNull($regionItem);
        $this->assertSame(1, $regionItem->max_per_school);

        // Region admin raises their own cap and disables the item locally — the same
        // fields FestEventController::bulkUpdateItemCaps()/updateItem() expose.
        $regionItem->update(['max_per_school' => 2, 'fee_amount' => 250, 'is_enabled' => false]);

        // Mirrors FestRegistrationCreateService::createForSchool(), which calls this on
        // every single school registration routed to this region.
        $resynced = $service->copyItemToPartition($hub, $hubItem->fresh(), $region, 'region');

        $this->assertSame($regionItem->id, $resynced?->id);
        $this->assertSame(2, $resynced?->max_per_school, 'A region\'s own max_per_school must survive a registration-triggered re-sync.');
        $this->assertSame(250.0, (float) $resynced?->fee_amount);
        $this->assertFalse((bool) $resynced?->is_enabled);
    }
}
