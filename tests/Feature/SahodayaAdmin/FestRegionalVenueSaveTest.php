<?php

namespace Tests\Feature\SahodayaAdmin;

use App\Models\FestEvent;
use App\Models\FestEventStaff;
use App\Models\FestVenue;
use App\Models\Region;
use App\Models\SahodayaProfile;
use App\Models\Tenant;
use App\Models\User;
use App\Support\TenantUserCatalog;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class FestRegionalVenueSaveTest extends TestCase
{
    use RefreshDatabase;

    private function makeAdmin(FestEvent $event, Tenant $sahodaya): User
    {
        $admin = User::factory()->create(['tenant_id' => $sahodaya->id, 'email_verified_at' => now()]);
        $admin->assignRole('event_admin');
        $admin->givePermissionTo(TenantUserCatalog::defaultPermissionsForRole('event_admin'));
        FestEventStaff::create(['event_id' => $event->id, 'user_id' => $admin->id, 'duty' => 'event_admin']);

        return $admin;
    }

    private function makeSetup(): array
    {
        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'sahodaya',
            'name' => 'Test Sahodaya',
            'domain' => 'test-sahodaya.test',
            'is_active' => true,
        ]);
        SahodayaProfile::create(['tenant_id' => $sahodaya->id, 'prefix' => 'TS', 'student_data_mode' => 'counts_only']);

        $event = FestEvent::create([
            'tenant_id' => $sahodaya->id,
            'title' => 'Kalolsavam Hub',
            'event_type' => 'kalolsavam',
            'level_round' => 'sahodaya',
            'status' => 'published',
            'conduct_mode' => 'partitioned',
        ]);

        $region = Region::create([
            'tenant_id' => $sahodaya->id,
            'name' => 'Tirur Region',
            'code' => 'tirur',
            'is_active' => true,
        ]);

        return [$sahodaya, $event, $region];
    }

    public function test_can_update_existing_regional_partition_and_sync_venue(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        [$sahodaya, $event, $region] = $this->makeSetup();
        $admin = $this->makeAdmin($event, $sahodaya);

        $partition = FestEvent::create([
            'tenant_id' => $sahodaya->id,
            'parent_event_id' => $event->id,
            'region_id' => $region->id,
            'partition_key' => 'tirur',
            'partition_role' => 'region',
            'cluster_label' => 'Tirur Region',
            'title' => 'Kalolsavam Hub - Tirur Region',
            'event_type' => 'kalolsavam',
            'status' => 'draft',
        ]);

        $response = $this->actingAs($admin)->post(
            "/sahodaya-admin/{$sahodaya->id}/events/{$event->id}/save-regional-venue",
            [
                'partition_id' => $partition->id,
                'region_id' => $region->id,
                'cluster_label' => 'Tirur Zone',
                'venue' => 'MES Central School, Tirur',
                'event_start' => '2026-11-20',
                'event_end' => '2026-11-22',
                'registration_open' => '2026-11-01',
                'registration_close' => '2026-11-15',
                'status' => 'registration_open',
            ]
        );

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $partition->refresh();
        $this->assertSame('Tirur Zone', $partition->cluster_label);
        $this->assertSame('MES Central School, Tirur', $partition->venue);
        $this->assertSame('2026-11-20', $partition->event_start->toDateString());
        $this->assertSame('2026-11-22', $partition->event_end->toDateString());
        $this->assertSame('registration_open', $partition->status);

        // Verify FestVenue was created/updated
        $venueRecord = FestVenue::where('event_id', $event->id)->where('region_id', $region->id)->first();
        $this->assertNotNull($venueRecord);
        $this->assertSame('MES Central School, Tirur', $venueRecord->name);
    }

    public function test_can_create_new_regional_venue_and_partition_when_partition_id_is_null(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        [$sahodaya, $event, $region] = $this->makeSetup();
        $admin = $this->makeAdmin($event, $sahodaya);

        $response = $this->actingAs($admin)->post(
            "/sahodaya-admin/{$sahodaya->id}/events/{$event->id}/save-regional-venue",
            [
                'region_id' => $region->id,
                'cluster_label' => 'Tirur Zone',
                'venue' => 'MES Central School, Tirur',
                'event_start' => '2026-11-20',
                'event_end' => '2026-11-22',
                'status' => 'published',
            ]
        );

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $createdPartition = FestEvent::where('parent_event_id', $event->id)->where('region_id', $region->id)->first();
        $this->assertNotNull($createdPartition);
        $this->assertSame('Tirur Zone', $createdPartition->cluster_label);
        $this->assertSame('MES Central School, Tirur', $createdPartition->venue);
        $this->assertSame('published', $createdPartition->status);

        $venueRecord = FestVenue::where('event_id', $event->id)->where('region_id', $region->id)->first();
        $this->assertNotNull($venueRecord);
        $this->assertSame('MES Central School, Tirur', $venueRecord->name);
    }
}
