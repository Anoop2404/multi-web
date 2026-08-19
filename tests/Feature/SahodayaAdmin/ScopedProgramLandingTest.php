<?php

namespace Tests\Feature\SahodayaAdmin;

use App\Models\FestEvent;
use App\Models\FestEventStaff;
use App\Models\Region;
use App\Models\SahodayaProfile;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The sidebar's program link (e.g. "English Fest") always resolves to that program's hub
 * event — see ForwardsSahodayaProgramDashboard -> FestEventController::programIndex(). For a
 * region_admin scoped only to a region-partition child (not the hub itself), that redirect
 * used to land them on the hub unconditionally, which the access-control middleware then
 * denied — surfacing as "You are not assigned to this event." back on whatever page they
 * came from (bootstrap/app.php's Inertia 403->flash handler). This covers the fix:
 * FestEventController::programIndex() now redirects a region/event/phase-scoped admin to
 * their own accessible child event instead, when the hub itself isn't in their scope.
 */
class ScopedProgramLandingTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'sahodaya',
            'name' => 'Scoped Landing Sahodaya',
            'domain' => 'scoped-landing.test',
            'is_active' => true,
        ]);

        SahodayaProfile::create([
            'tenant_id' => $sahodaya->id,
            'prefix' => 'SL',
            'student_data_mode' => 'counts_only',
            'active_academic_year' => '2025-26',
        ]);

        $region = Region::create(['tenant_id' => $sahodaya->id, 'name' => 'Region A', 'code' => 'SLA', 'is_active' => true]);

        $hub = FestEvent::create([
            'tenant_id' => $sahodaya->id,
            'title' => 'Scoped Kalotsav',
            'event_type' => 'kalolsavam',
            'conduct_mode' => 'partitioned',
            'level_round' => 'sahodaya',
            'status' => 'registration_open',
        ]);

        $child = FestEvent::create([
            'tenant_id' => $sahodaya->id,
            'title' => 'Scoped Kalotsav — Region A',
            'event_type' => 'kalolsavam',
            'parent_event_id' => $hub->id,
            'partition_key' => 'region-a',
            'partition_role' => 'region',
            'region_id' => $region->id,
            'level_round' => 'sahodaya',
            'status' => 'registration_open',
        ]);

        return compact('sahodaya', 'region', 'hub', 'child');
    }

    public function test_region_admin_scoped_only_on_the_child_lands_on_the_child_not_the_hub(): void
    {
        $f = $this->fixture();

        $admin = User::factory()->create(['tenant_id' => $f['sahodaya']->id]);
        $admin->assignRole('region_admin');
        FestEventStaff::create([
            'event_id' => $f['child']->id,
            'user_id' => $admin->id,
            'duty' => 'region_admin',
            'region_id' => $f['region']->id,
        ]);

        $response = $this->actingAs($admin)->get(route('sahodaya.kalotsav.dashboard', ['tenantId' => $f['sahodaya']->id]));

        $response->assertRedirect("/sahodaya-admin/{$f['sahodaya']->id}/events/{$f['child']->id}");
    }

    public function test_region_admin_scoped_on_the_hub_still_lands_on_the_hub(): void
    {
        $f = $this->fixture();

        $admin = User::factory()->create(['tenant_id' => $f['sahodaya']->id]);
        $admin->assignRole('region_admin');
        FestEventStaff::create([
            'event_id' => $f['hub']->id,
            'user_id' => $admin->id,
            'duty' => 'region_admin',
            'region_id' => $f['region']->id,
        ]);

        $response = $this->actingAs($admin)->get(route('sahodaya.kalotsav.dashboard', ['tenantId' => $f['sahodaya']->id]));

        $response->assertRedirect("/sahodaya-admin/{$f['sahodaya']->id}/events/{$f['hub']->id}");
    }

    public function test_unscoped_admin_still_lands_on_the_hub(): void
    {
        $f = $this->fixture();

        $admin = User::factory()->create(['tenant_id' => $f['sahodaya']->id]);
        $admin->assignRole('sahodaya_admin');

        $response = $this->actingAs($admin)->get(route('sahodaya.kalotsav.dashboard', ['tenantId' => $f['sahodaya']->id]));

        $response->assertRedirect("/sahodaya-admin/{$f['sahodaya']->id}/events/{$f['hub']->id}");
    }
}
