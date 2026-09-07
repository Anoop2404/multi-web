<?php

namespace Tests\Feature\SahodayaAdmin;

use App\Models\FestEvent;
use App\Models\FestEventPhase;
use App\Models\FestPhaseRegion;
use App\Models\Region;
use App\Models\SahodayaProfile;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class FestReportPhaseRegionTest extends TestCase
{
    use RefreshDatabase;

    public function test_report_props_includes_assigned_phase_regions_and_scopes_event_regions(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'sahodaya',
            'name' => 'Report Region Sahodaya',
            'domain' => Str::uuid().'.test',
            'is_active' => true,
        ]);
        SahodayaProfile::create([
            'tenant_id' => $sahodaya->id,
            'prefix' => 'RR',
            'student_data_mode' => 'counts_only',
        ]);

        $admin = User::factory()->create([
            'tenant_id' => $sahodaya->id,
            'email_verified_at' => now(),
        ]);
        $admin->assignRole('sahodaya_admin');

        // Create 3 global regions
        $region1 = Region::create(['tenant_id' => $sahodaya->id, 'name' => 'Region 1 (Tirur)', 'code' => 'R1', 'sort_order' => 1, 'is_active' => true]);
        $region2 = Region::create(['tenant_id' => $sahodaya->id, 'name' => 'Region 2 (Manjeri)', 'code' => 'R2', 'sort_order' => 2, 'is_active' => true]);
        $region3 = Region::create(['tenant_id' => $sahodaya->id, 'name' => 'Region 3 (Nilambur)', 'code' => 'R3', 'sort_order' => 3, 'is_active' => true]);

        $event = FestEvent::create([
            'tenant_id' => $sahodaya->id,
            'title' => 'Phased Kalotsav',
            'event_type' => 'kalolsavam',
            'conduct_mode' => 'phased_regional_billing',
            'workflow_mode' => 'phased_regional_billing',
            'level_round' => 'sahodaya',
            'status' => 'registration_open',
        ]);

        // Non-regional phase
        $phaseOnStage = FestEventPhase::create([
            'event_id' => $event->id,
            'name' => 'On Stage',
            'code' => 'ON',
            'is_regional' => false,
            'sort_order' => 1,
        ]);

        // Regional phase
        $phaseOffStage = FestEventPhase::create([
            'event_id' => $event->id,
            'name' => 'Off Stage',
            'code' => 'OFF',
            'is_regional' => true,
            'sort_order' => 2,
        ]);

        // Assign only Region 1 and Region 2 to Off Stage phase (Region 3 unassigned)
        FestPhaseRegion::create(['phase_id' => $phaseOffStage->id, 'region_id' => $region1->id, 'enabled' => true]);
        FestPhaseRegion::create(['phase_id' => $phaseOffStage->id, 'region_id' => $region2->id, 'enabled' => true]);
        FestPhaseRegion::create(['phase_id' => $phaseOffStage->id, 'region_id' => $region3->id, 'enabled' => false]);

        $response = $this->actingAs($admin)->get(
            route('sahodaya.events.reports.item-wise', ['tenantId' => $sahodaya->id, 'event' => $event->id])
        );

        $response->assertInertia(function ($page) use ($region1, $region2, $region3) {
            $page->has('competitionPhases', 2);
            $phases = $page->toArray()['props']['competitionPhases'];

            // On Stage phase should have empty regions
            $onStage = collect($phases)->firstWhere('code', 'OFF') === null ? $phases[0] : collect($phases)->firstWhere('code', 'ON');
            $this->assertFalse($onStage['is_regional']);
            $this->assertEmpty($onStage['regions']);

            // Off Stage phase should have Region 1 & Region 2 (enabled = true) but not Region 3
            $offStage = collect($phases)->firstWhere('code', 'OFF');
            $this->assertTrue($offStage['is_regional']);
            $this->assertCount(2, $offStage['regions']);
            $regionIds = collect($offStage['regions'])->pluck('id')->all();
            $this->assertContains($region1->id, $regionIds);
            $this->assertContains($region2->id, $regionIds);
            $this->assertNotContains($region3->id, $regionIds);

            // Event-level regions prop should only include assigned/enabled regions (Region 1 & 2)
            $eventRegions = $page->toArray()['props']['regions'];
            $this->assertCount(2, $eventRegions);
            $eventRegionIds = collect($eventRegions)->pluck('id')->all();
            $this->assertContains($region1->id, $eventRegionIds);
            $this->assertContains($region2->id, $eventRegionIds);
            $this->assertNotContains($region3->id, $eventRegionIds);

            return true;
        });
    }
}
