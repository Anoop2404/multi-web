<?php

namespace Tests\Feature\Events;

use App\Models\FestEvent;
use App\Models\Region;
use App\Models\SahodayaProfile;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Events\FestEventPhaseService;
use App\Services\Events\FestPhasedWorkflowService;
use App\Services\Events\FestPhaseTopologyService;
use App\Services\Membership\MasterClassService;
use App\Services\Students\SchoolClassProvisioner;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SahodayaMasterDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Regression test for a bug found while wiring the Kalotsav hub to redirect straight into
 * the school's single yearly event: landing on a phased-regional-billing hub's own event id
 * used to trigger FestPartitionService's LEGACY partition-sync path (its leaf events reuse
 * partition_key/cluster_key, making isPartitionedHub() a false positive for the old system),
 * which then aborted 422 via assertLegacyPartitioningAllowed() since the two conduct systems
 * are mutually exclusive. Fixed in FestRegistrationController::redirectHubToSchoolPartition()
 * by skipping the legacy redirect entirely for phased-billing hubs.
 */
class KalotsavPhasedHubRedirectRegressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_school_can_open_a_phased_regional_billing_hub_without_the_legacy_partition_error(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(SahodayaMasterDataSeeder::class);

        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'sahodaya',
            'name' => 'Hub Redirect Test Sahodaya',
            'domain' => Str::uuid().'.test',
            'is_active' => true,
        ]);
        SahodayaProfile::create([
            'tenant_id' => $sahodaya->id,
            'prefix' => 'HRT',
            'student_data_mode' => 'counts_only',
        ]);
        $school = Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'school',
            'parent_id' => $sahodaya->id,
            'name' => 'Hub Redirect Test School',
            'domain' => Str::uuid().'.test',
            'membership_status' => 'approved',
            'is_active' => true,
        ]);
        app(MasterClassService::class)->ensureForSahodaya($sahodaya->id);
        app(SchoolClassProvisioner::class)->ensureForSchool($school);

        $root = FestEvent::create([
            'tenant_id' => $sahodaya->id,
            'title' => 'Hub Redirect Test Kalotsavam',
            'event_type' => 'kalolsavam',
            'level_round' => 'sahodaya',
            'status' => 'published',
            'fee_type' => 'none',
            'fee_settings' => ['fee_model' => 'item_catalog'],
            'workflow_mode' => FestPhasedWorkflowService::MODE,
            'phase_mode_enabled' => true,
            'conduct_mode' => 'partitioned',
        ]);
        $region = Region::create([
            'tenant_id' => $sahodaya->id,
            'name' => 'Test Region',
            'code' => 'TSTR',
            'is_active' => true,
        ]);
        $phase = app(FestEventPhaseService::class)->createPhase($root, [
            'name' => 'Off Stage',
            'code' => 'OFF_STAGE',
            'sort_order' => 1,
            'is_regional' => true,
        ]);
        app(FestPhasedWorkflowService::class)->syncAllowedRegions($phase, [$region->id]);
        app(FestPhaseTopologyService::class)->sync($root->fresh());

        $admin = User::factory()->create([
            'tenant_id' => $school->id,
            'email_verified_at' => now(),
        ]);
        $admin->assignRole('school_admin');

        $response = $this->actingAs($admin)
            ->get("/school-admin/{$school->id}/kalotsav/events/{$root->id}/registration");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('School/Events/Registration', false)
            ->has('events.0.phase_region_options', 1));
    }
}
