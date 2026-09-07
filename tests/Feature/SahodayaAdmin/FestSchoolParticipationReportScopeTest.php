<?php

namespace Tests\Feature\SahodayaAdmin;

use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestEventPhase;
use App\Models\FestRegistration;
use App\Models\FestSchoolPhaseRegionSelection;
use App\Models\Region;
use App\Models\SahodayaProfile;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class FestSchoolParticipationReportScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_school_participation_report_with_phase_and_region_filters(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'sahodaya',
            'name' => 'Participation Report Sahodaya',
            'domain' => 'part-report-'.Str::random(8).'.test',
            'is_active' => true,
        ]);
        SahodayaProfile::create(['tenant_id' => $sahodaya->id, 'prefix' => 'PR', 'student_data_mode' => 'counts_only']);

        $school1 = Tenant::create(['id' => (string) Str::uuid(), 'type' => 'school', 'name' => 'School Alpha', 'parent_id' => $sahodaya->id, 'membership_status' => 'approved', 'is_active' => true]);
        $school2 = Tenant::create(['id' => (string) Str::uuid(), 'type' => 'school', 'name' => 'School Beta', 'parent_id' => $sahodaya->id, 'membership_status' => 'approved', 'is_active' => true]);

        $admin = User::factory()->create(['tenant_id' => $sahodaya->id, 'email_verified_at' => now()]);
        $admin->assignRole('sahodaya_admin');

        $region1 = Region::create(['tenant_id' => $sahodaya->id, 'name' => 'REGION 1 (Tirur)', 'code' => 'R1', 'sort_order' => 1]);
        $region2 = Region::create(['tenant_id' => $sahodaya->id, 'name' => 'REGION 2 (Manjeri)', 'code' => 'R2', 'sort_order' => 2]);

        $root = FestEvent::create([
            'tenant_id' => $sahodaya->id,
            'title' => 'Malappuram Central Kalotsavam',
            'event_type' => 'kalotsavam',
            'workflow_mode' => 'phased_regional_billing',
            'status' => 'published',
        ]);

        $phase1 = FestEventPhase::create(['event_id' => $root->id, 'name' => 'Sargadhara', 'code' => 'sargadhara', 'is_regional' => true]);
        $phase2 = FestEventPhase::create(['event_id' => $root->id, 'name' => 'Kilikonchalu', 'code' => 'kilikonchalu', 'is_regional' => true]);

        $leaf1 = FestEvent::create([
            'tenant_id' => $sahodaya->id,
            'parent_event_id' => $root->id,
            'root_event_id' => $root->id,
            'source_phase_id' => $phase1->id,
            'region_id' => $region2->id,
            'title' => 'Sargadhara — Manjeri Region',
            'event_type' => 'kalotsavam',
            'workflow_mode' => 'phased_regional_billing',
            'status' => 'published',
        ]);

        $leaf2 = FestEvent::create([
            'tenant_id' => $sahodaya->id,
            'parent_event_id' => $root->id,
            'root_event_id' => $root->id,
            'source_phase_id' => $phase1->id,
            'region_id' => $region1->id,
            'title' => 'Sargadhara — Tirur Region',
            'event_type' => 'kalotsavam',
            'workflow_mode' => 'phased_regional_billing',
            'status' => 'published',
        ]);

        FestSchoolPhaseRegionSelection::create(['event_id' => $root->id, 'phase_id' => $phase1->id, 'school_id' => $school1->id, 'region_id' => $region2->id]);
        FestSchoolPhaseRegionSelection::create(['event_id' => $root->id, 'phase_id' => $phase1->id, 'school_id' => $school2->id, 'region_id' => $region1->id]);

        $item1 = FestEventItem::create(['event_id' => $leaf1->id, 'title' => 'Folk Dance', 'phase_id' => $phase1->id]);
        $item2 = FestEventItem::create(['event_id' => $leaf2->id, 'title' => 'Folk Dance', 'phase_id' => $phase1->id]);

        FestRegistration::create(['event_id' => $leaf1->id, 'item_id' => $item1->id, 'school_id' => $school1->id, 'status' => 'submitted']);
        FestRegistration::create(['event_id' => $leaf2->id, 'item_id' => $item2->id, 'school_id' => $school2->id, 'status' => 'approved']);

        // 1. Initial page load on root event with no filters (combined mode)
        $this->actingAs($admin)
            ->get("/sahodaya-admin/{$sahodaya->id}/events/{$root->id}/reports/school-participation")
            ->assertStatus(200)
            ->assertInertia(fn (Assert $page) => $page
                ->component('Sahodaya/Events/Reports/SchoolParticipation', false)
                ->has('rows', 2)
                ->where('totals.schools', 2)
                ->where('totals.active_registrations', 2)
            );

        // 2. Filter by phase Sargadhara ($phase1->id) and region REGION 2 ($region2->id) on root event
        $this->actingAs($admin)
            ->get("/sahodaya-admin/{$sahodaya->id}/events/{$root->id}/reports/school-participation?scope_mode=region&competition_phase_id={$phase1->id}&region_id={$region2->id}")
            ->assertStatus(200)
            ->assertInertia(fn (Assert $page) => $page
                ->component('Sahodaya/Events/Reports/SchoolParticipation', false)
                ->has('rows', 1)
                ->where('rows.0.school_name', 'SCHOOL ALPHA')
                ->where('totals.active_registrations', 1)
            );

        // 3. Filter by region REGION 2 ($region2->id) when navigated on child event ($leaf1)
        $this->actingAs($admin)
            ->get("/sahodaya-admin/{$sahodaya->id}/events/{$leaf1->id}/reports/school-participation?scope_mode=region&competition_phase_id={$phase1->id}&region_id={$region2->id}")
            ->assertStatus(200)
            ->assertInertia(fn (Assert $page) => $page
                ->component('Sahodaya/Events/Reports/SchoolParticipation', false)
                ->has('rows', 1)
                ->where('rows.0.school_name', 'SCHOOL ALPHA')
                ->where('totals.active_registrations', 1)
            );
    }
}
