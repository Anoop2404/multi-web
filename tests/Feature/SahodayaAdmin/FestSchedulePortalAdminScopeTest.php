<?php

namespace Tests\Feature\SahodayaAdmin;

use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestEventStaff;
use App\Models\FestParticipant;
use App\Models\FestRegistration;
use App\Models\FestSchedule;
use App\Models\FestStage;
use App\Models\Region;
use App\Models\SahodayaProfile;
use App\Models\Tenant;
use App\Models\User;
use App\Support\TenantUserCatalog;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class FestSchedulePortalAdminScopeTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'sahodaya',
            'name' => 'Kalotsav Portal Admin Sahodaya',
            'domain' => 'kalotsav-scope-'.Str::random(6).'.test',
            'is_active' => true,
        ]);

        SahodayaProfile::create([
            'tenant_id' => $sahodaya->id,
            'prefix' => 'KPA',
            'student_data_mode' => 'counts_only',
            'active_academic_year' => '2025-26',
        ]);

        $regionA = Region::create(['tenant_id' => $sahodaya->id, 'name' => 'North Region', 'code' => 'NORTH', 'is_active' => true]);
        $regionB = Region::create(['tenant_id' => $sahodaya->id, 'name' => 'South Region', 'code' => 'SOUTH', 'is_active' => true]);

        $hub = FestEvent::create([
            'tenant_id' => $sahodaya->id,
            'title' => 'Kalotsav 2026',
            'event_type' => 'kalolsavam',
            'conduct_mode' => 'partitioned',
            'level_round' => 'sahodaya',
            'status' => 'registration_open',
        ]);

        $childA = FestEvent::create([
            'tenant_id' => $sahodaya->id,
            'title' => 'Kalotsav 2026 — North Region',
            'event_type' => 'kalolsavam',
            'parent_event_id' => $hub->id,
            'partition_key' => 'north',
            'partition_role' => 'region',
            'region_id' => $regionA->id,
            'level_round' => 'sahodaya',
            'status' => 'registration_open',
        ]);

        $childB = FestEvent::create([
            'tenant_id' => $sahodaya->id,
            'title' => 'Kalotsav 2026 — South Region',
            'event_type' => 'kalolsavam',
            'parent_event_id' => $hub->id,
            'partition_key' => 'south',
            'partition_role' => 'region',
            'region_id' => $regionB->id,
            'level_round' => 'sahodaya',
            'status' => 'registration_open',
        ]);

        $itemA = FestEventItem::create([
            'event_id' => $childA->id,
            'title' => 'Classical Music',
            'owner_level' => 'sahodaya',
            'is_enabled' => true,
        ]);

        $itemB = FestEventItem::create([
            'event_id' => $childB->id,
            'title' => 'Folk Dance',
            'owner_level' => 'sahodaya',
            'is_enabled' => true,
        ]);

        return compact('sahodaya', 'regionA', 'regionB', 'hub', 'childA', 'childB', 'itemA', 'itemB');
    }

    public function test_event_admin_scoped_to_hub_can_view_and_modify_schedule_on_child_partition_events(): void
    {
        $f = $this->fixture();

        $admin = User::factory()->create(['tenant_id' => $f['sahodaya']->id, 'email_verified_at' => now()]);
        $admin->assignRole('event_admin');
        $admin->givePermissionTo(TenantUserCatalog::defaultPermissionsForRole('event_admin'));

        FestEventStaff::create([
            'event_id' => $f['hub']->id,
            'user_id' => $admin->id,
            'duty' => 'event_admin',
        ]);

        // Can view schedule index on child event
        $this->actingAs($admin)
            ->get(route('sahodaya.events.schedule.index', [
                'tenantId' => $f['sahodaya']->id,
                'event' => $f['childA']->id,
            ]))
            ->assertOk();

        // Can view item schedule on child event
        $this->actingAs($admin)
            ->get(route('sahodaya.events.schedule.items', [
                'tenantId' => $f['sahodaya']->id,
                'event' => $f['childA']->id,
            ]))
            ->assertOk();

        // Can modify schedule on child event via bulkStoreItems
        $this->actingAs($admin)
            ->post(route('sahodaya.events.schedule.items.bulk', [
                'tenantId' => $f['sahodaya']->id,
                'event' => $f['childA']->id,
            ]), [
                'rows' => [
                    [
                        'item_id' => $f['itemA']->id,
                        'scheduled_date' => '2026-10-15',
                        'scheduled_time' => '10:00',
                        'stage' => 'Stage 1',
                        'sort_order' => 1,
                    ],
                ],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('fest_schedules', [
            'event_id' => $f['childA']->id,
            'item_id' => $f['itemA']->id,
            'stage' => 'Stage 1',
        ]);
    }

    public function test_region_admin_can_view_and_modify_schedule_for_assigned_region(): void
    {
        $f = $this->fixture();

        $regionAdmin = User::factory()->create(['tenant_id' => $f['sahodaya']->id, 'email_verified_at' => now()]);
        $regionAdmin->assignRole('region_admin');
        $regionAdmin->givePermissionTo(TenantUserCatalog::defaultPermissionsForRole('region_admin'));

        // Assigned to hub event with region_id = North Region
        FestEventStaff::create([
            'event_id' => $f['hub']->id,
            'user_id' => $regionAdmin->id,
            'duty' => 'region_admin',
            'region_id' => $f['regionA']->id,
        ]);

        // Can view schedule on North Region child event
        $this->actingAs($regionAdmin)
            ->get(route('sahodaya.events.schedule.index', [
                'tenantId' => $f['sahodaya']->id,
                'event' => $f['childA']->id,
            ]))
            ->assertOk();

        // Can modify schedule on North Region child event
        $this->actingAs($regionAdmin)
            ->post(route('sahodaya.events.schedule.items.bulk', [
                'tenantId' => $f['sahodaya']->id,
                'event' => $f['childA']->id,
            ]), [
                'rows' => [
                    [
                        'item_id' => $f['itemA']->id,
                        'scheduled_date' => '2026-10-15',
                        'scheduled_time' => '11:00',
                        'stage' => 'Auditorium',
                        'sort_order' => 1,
                    ],
                ],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('fest_schedules', [
            'event_id' => $f['childA']->id,
            'item_id' => $f['itemA']->id,
            'stage' => 'Auditorium',
        ]);

        // Forbidden from modifying or viewing South Region child event schedule
        $this->actingAs($regionAdmin)
            ->get(route('sahodaya.events.schedule.index', [
                'tenantId' => $f['sahodaya']->id,
                'event' => $f['childB']->id,
            ]))
            ->assertForbidden();

        $this->actingAs($regionAdmin)
            ->post(route('sahodaya.events.schedule.items.bulk', [
                'tenantId' => $f['sahodaya']->id,
                'event' => $f['childB']->id,
            ]), [
                'rows' => [
                    [
                        'item_id' => $f['itemB']->id,
                        'scheduled_date' => '2026-10-15',
                        'stage' => 'Stage 2',
                        'sort_order' => 1,
                    ],
                ],
            ])
            ->assertForbidden();
    }

    public function test_assigning_coordinator_duty_in_event_staff_grants_event_admin_schedule_access(): void
    {
        $f = $this->fixture();

        $superAdmin = User::factory()->create(['tenant_id' => $f['sahodaya']->id, 'email_verified_at' => now()]);
        $superAdmin->assignRole('sahodaya_admin');

        $staffUser = User::factory()->create(['tenant_id' => $f['sahodaya']->id, 'email_verified_at' => now()]);
        $staffUser->assignRole('sahodaya_staff');

        // Assign duty 'coordinator' via FestEventStaffController
        $this->actingAs($superAdmin)
            ->post(route('sahodaya.events.event-staff.store', [
                'tenantId' => $f['sahodaya']->id,
                'event' => $f['hub']->id,
            ]), [
                'user_id' => $staffUser->id,
                'duty' => 'coordinator',
            ])
            ->assertRedirect();

        $staffUser->refresh();
        $this->assertTrue($staffUser->hasRole('event_admin'));
        $this->assertTrue($staffUser->can('fest.schedule'));

        // Staff user can now access and modify schedule for the event
        $this->actingAs($staffUser)
            ->get(route('sahodaya.events.schedule.index', [
                'tenantId' => $f['sahodaya']->id,
                'event' => $f['childA']->id,
            ]))
            ->assertOk();
    }
}
