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
 * DashboardController::index() used to compute every fest-related widget (active fests,
 * registrations, appeals, fee proofs, the "Program status" tiles) from every FestEvent in the
 * Sahodaya, regardless of which one(s) the current admin is actually scoped to — a region_admin
 * saw Sahodaya-wide numbers on their own dashboard. Covers the fix: SahodayaAdminController::
 * scopedFestEventIds() narrows these down to the admin's own event(s) for a scoped admin, while
 * a full admin (sahodaya_admin) still sees everything, unchanged.
 */
class ScopedDashboardTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'sahodaya',
            'name' => 'Scoped Dashboard Sahodaya',
            'domain' => 'scoped-dashboard.test',
            'is_active' => true,
        ]);

        SahodayaProfile::create([
            'tenant_id' => $sahodaya->id,
            'prefix' => 'SD',
            'student_data_mode' => 'counts_only',
            'active_academic_year' => '2025-26',
        ]);

        $region = Region::create(['tenant_id' => $sahodaya->id, 'name' => 'Region A', 'code' => 'SDA', 'is_active' => true]);

        $hub = FestEvent::create([
            'tenant_id' => $sahodaya->id,
            'title' => 'Scoped Dashboard Kalotsav',
            'event_type' => 'kalolsavam',
            'conduct_mode' => 'partitioned',
            'level_round' => 'sahodaya',
            'status' => 'published',
        ]);

        $child = FestEvent::create([
            'tenant_id' => $sahodaya->id,
            'title' => 'Scoped Dashboard Kalotsav — Region A',
            'event_type' => 'kalolsavam',
            'parent_event_id' => $hub->id,
            'partition_key' => 'region-a',
            'partition_role' => 'region',
            'region_id' => $region->id,
            'level_round' => 'sahodaya',
            'status' => 'published',
        ]);

        // An event the region_admin has no scope over at all — proves it's excluded, not just
        // "everything under this hub."
        $unrelated = FestEvent::create([
            'tenant_id' => $sahodaya->id,
            'title' => 'Unrelated English Fest',
            'event_type' => 'english_fest',
            'level_round' => 'sahodaya',
            'status' => 'published',
        ]);

        return compact('sahodaya', 'region', 'hub', 'child', 'unrelated');
    }

    public function test_region_admin_dashboard_only_counts_their_own_scope(): void
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

        $response = $this->actingAs($admin)->get(route('sahodaya.dashboard', ['tenantId' => $f['sahodaya']->id]));

        $response->assertInertia(fn ($page) => $page
            ->where('stats.active_fest_events', 2) // hub + its own region-A child, reached via the hub scope
            ->where('scopedEventTypes', ['kalolsavam']));
    }

    public function test_full_admin_dashboard_counts_every_event(): void
    {
        $f = $this->fixture();

        $admin = User::factory()->create(['tenant_id' => $f['sahodaya']->id]);
        $admin->assignRole('sahodaya_admin');

        $response = $this->actingAs($admin)->get(route('sahodaya.dashboard', ['tenantId' => $f['sahodaya']->id]));

        $response->assertInertia(fn ($page) => $page
            ->where('stats.active_fest_events', 3) // hub + child + the unrelated event
            ->where('scopedEventTypes', null));
    }
}
