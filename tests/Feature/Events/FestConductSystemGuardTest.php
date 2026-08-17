<?php

namespace Tests\Feature\Events;

use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestEventPhase;
use App\Models\FestRegistration;
use App\Models\FestRegistrationBatch;
use App\Models\Region;
use App\Models\SahodayaProfile;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Regression coverage for the live incident: FestRegionPartitionService::
 * syncPartitionsFromRegions() had no guard against an event that already uses the
 * phased/batch system, and FestRegistrationBatchController::store() had no guard
 * against orphaning already-registered legacy region partitions. See
 * FestPartitionService::assertLegacyPartitioningAllowed()/assertSafeToActivatePhasedWorkflow().
 */
class FestConductSystemGuardTest extends TestCase
{
    use RefreshDatabase;

    private function actingAdmin(): array
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'sahodaya',
            'name' => 'Guard Feature Test Sahodaya',
            'domain' => Str::uuid().'.test',
            'is_active' => true,
        ]);
        SahodayaProfile::create([
            'tenant_id' => $sahodaya->id,
            'prefix' => 'GRD',
            'student_data_mode' => 'counts_only',
        ]);
        $admin = User::factory()->create(['tenant_id' => $sahodaya->id, 'email_verified_at' => now()]);
        $admin->assignRole('sahodaya_admin');

        return [$sahodaya, $admin];
    }

    public function test_sync_region_partitions_blocked_once_phases_exist(): void
    {
        [$sahodaya, $admin] = $this->actingAdmin();
        Region::create(['tenant_id' => $sahodaya->id, 'name' => 'Tirur', 'code' => 'TIR', 'is_active' => true]);
        $event = FestEvent::create([
            'tenant_id' => $sahodaya->id, 'title' => 'Kalotsav', 'event_type' => 'kalolsavam',
            'level_round' => 'sahodaya', 'status' => 'draft',
        ]);
        FestEventPhase::create(['event_id' => $event->id, 'name' => 'Digi Fest', 'code' => 'DIGI']);

        $response = $this->actingAs($admin)->post(route('sahodaya.events.sync-region-partitions', [
            'tenantId' => $sahodaya->id, 'event' => $event->id,
        ]));

        $response->assertStatus(422);
        $this->assertSame(0, FestEvent::where('parent_event_id', $event->id)->count());
        $this->assertSame('standard', $event->fresh()->conduct_mode);
    }

    public function test_sync_region_partitions_allowed_before_any_phased_configuration(): void
    {
        [$sahodaya, $admin] = $this->actingAdmin();
        Region::create(['tenant_id' => $sahodaya->id, 'name' => 'Tirur', 'code' => 'TIR', 'is_active' => true]);
        $event = FestEvent::create([
            'tenant_id' => $sahodaya->id, 'title' => 'Kalotsav', 'event_type' => 'kalolsavam',
            'level_round' => 'sahodaya', 'status' => 'draft',
        ]);

        $response = $this->actingAs($admin)->post(route('sahodaya.events.sync-region-partitions', [
            'tenantId' => $sahodaya->id, 'event' => $event->id,
        ]));

        $response->assertStatus(302);
        $this->assertSame(1, FestEvent::where('parent_event_id', $event->id)->count());
        $this->assertSame('partitioned', $event->fresh()->conduct_mode);
    }

    public function test_update_conduct_topology_to_partitioned_blocked_once_batch_exists(): void
    {
        [$sahodaya, $admin] = $this->actingAdmin();
        $event = FestEvent::create([
            'tenant_id' => $sahodaya->id, 'title' => 'Kalotsav', 'event_type' => 'kalolsavam',
            'level_round' => 'sahodaya', 'status' => 'draft',
        ]);
        FestRegistrationBatch::create(['event_id' => $event->id, 'code' => 'LEVEL_1', 'name' => 'Level 1']);

        $response = $this->actingAs($admin)->post(route('sahodaya.events.conduct-topology.update', [
            'tenantId' => $sahodaya->id, 'event' => $event->id,
        ]), ['conduct_mode' => 'partitioned']);

        $response->assertStatus(422);
        $this->assertSame('standard', $event->fresh()->conduct_mode);
    }

    public function test_update_conduct_topology_to_standard_is_never_blocked(): void
    {
        [$sahodaya, $admin] = $this->actingAdmin();
        $event = FestEvent::create([
            'tenant_id' => $sahodaya->id, 'title' => 'Kalotsav', 'event_type' => 'kalolsavam',
            'conduct_mode' => 'partitioned', 'level_round' => 'sahodaya', 'status' => 'draft',
        ]);
        FestRegistrationBatch::create(['event_id' => $event->id, 'code' => 'LEVEL_1', 'name' => 'Level 1']);

        $response = $this->actingAs($admin)->post(route('sahodaya.events.conduct-topology.update', [
            'tenantId' => $sahodaya->id, 'event' => $event->id,
        ]), ['conduct_mode' => 'standard']);

        $response->assertStatus(302);
        $this->assertSame('standard', $event->fresh()->conduct_mode);
    }

    public function test_registration_batch_store_blocked_when_legacy_registrations_exist(): void
    {
        [$sahodaya, $admin] = $this->actingAdmin();
        $event = FestEvent::create([
            'tenant_id' => $sahodaya->id, 'title' => 'Kalotsav', 'event_type' => 'kalolsavam',
            'conduct_mode' => 'partitioned', 'level_round' => 'sahodaya', 'status' => 'draft',
        ]);
        $region = FestEvent::create([
            'tenant_id' => $sahodaya->id, 'title' => 'Tirur Region', 'event_type' => 'kalolsavam',
            'parent_event_id' => $event->id, 'partition_key' => 'tirur', 'partition_role' => 'region',
            'level_round' => 'sahodaya', 'status' => 'registration_open',
        ]);
        FestRegistration::create([
            'event_id' => $region->id, 'school_id' => (string) Str::uuid(),
            'status' => 'approved', 'submitted_at' => now(),
        ]);

        $response = $this->actingAs($admin)->post(route('sahodaya.events.registration-batches.store', [
            'tenantId' => $sahodaya->id, 'event' => $event->id,
        ]), ['code' => 'LEVEL_1', 'name' => 'Level 1']);

        $response->assertStatus(422);
        $this->assertSame(0, FestRegistrationBatch::where('event_id', $event->id)->count());
        $this->assertSame('standard', $event->fresh()->workflow_mode);
    }

    public function test_registration_batch_store_allowed_when_legacy_partitions_have_no_registrations(): void
    {
        [$sahodaya, $admin] = $this->actingAdmin();
        $event = FestEvent::create([
            'tenant_id' => $sahodaya->id, 'title' => 'Kalotsav', 'event_type' => 'kalolsavam',
            'conduct_mode' => 'partitioned', 'level_round' => 'sahodaya', 'status' => 'draft',
        ]);
        FestEvent::create([
            'tenant_id' => $sahodaya->id, 'title' => 'Tirur Region', 'event_type' => 'kalolsavam',
            'parent_event_id' => $event->id, 'partition_key' => 'tirur', 'partition_role' => 'region',
            'level_round' => 'sahodaya', 'status' => 'draft',
        ]);

        $response = $this->actingAs($admin)->post(route('sahodaya.events.registration-batches.store', [
            'tenantId' => $sahodaya->id, 'event' => $event->id,
        ]), ['code' => 'LEVEL_1', 'name' => 'Level 1']);

        $response->assertStatus(302);
        $event->refresh();
        $this->assertSame('phased_regional_billing', $event->workflow_mode);
        $this->assertSame('partitioned', $event->conduct_mode);
    }

    public function test_assign_items_warns_when_no_batch_exists_yet(): void
    {
        [$sahodaya, $admin] = $this->actingAdmin();
        $event = FestEvent::create([
            'tenant_id' => $sahodaya->id, 'title' => 'Kalotsav', 'event_type' => 'kalolsavam',
            'level_round' => 'sahodaya', 'status' => 'draft',
        ]);
        $phase = FestEventPhase::create(['event_id' => $event->id, 'name' => 'Digi Fest', 'code' => 'DIGI']);
        $item = FestEventItem::create(['event_id' => $event->id, 'title' => 'Item A', 'item_code' => 'A1', 'is_enabled' => true]);

        $response = $this->actingAs($admin)->post(route('sahodaya.events.phases.assign-items', [
            'tenantId' => $sahodaya->id, 'event' => $event->id,
        ]), ['phase_id' => $phase->id, 'item_ids' => [$item->id]]);

        $response->assertSessionHas('warning');
        $response->assertSessionMissing('success');
    }

    public function test_assign_items_succeeds_quietly_once_a_batch_exists(): void
    {
        [$sahodaya, $admin] = $this->actingAdmin();
        $event = FestEvent::create([
            'tenant_id' => $sahodaya->id, 'title' => 'Kalotsav', 'event_type' => 'kalolsavam',
            'level_round' => 'sahodaya', 'status' => 'draft',
        ]);
        $batch = FestRegistrationBatch::create(['event_id' => $event->id, 'code' => 'LEVEL_1', 'name' => 'Level 1']);
        $phase = FestEventPhase::create(['event_id' => $event->id, 'name' => 'Digi Fest', 'code' => 'DIGI', 'registration_batch_id' => $batch->id]);
        $item = FestEventItem::create(['event_id' => $event->id, 'title' => 'Item A', 'item_code' => 'A1', 'is_enabled' => true]);
        $event->update(['workflow_mode' => 'phased_regional_billing', 'phase_mode_enabled' => true]);

        $response = $this->actingAs($admin)->post(route('sahodaya.events.phases.assign-items', [
            'tenantId' => $sahodaya->id, 'event' => $event->id,
        ]), ['phase_id' => $phase->id, 'item_ids' => [$item->id]]);

        $response->assertSessionHas('success');
        $response->assertSessionMissing('warning');
    }
}
