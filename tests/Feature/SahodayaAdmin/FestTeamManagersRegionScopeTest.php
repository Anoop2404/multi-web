<?php

namespace Tests\Feature\SahodayaAdmin;

use App\Models\FestEvent;
use App\Models\FestParticipant;
use App\Models\FestRegistration;
use App\Models\FestSchoolTeamManager;
use App\Models\Region;
use App\Models\SahodayaProfile;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Team Managers (and the Unique Participant Counts it embeds) already declared
 * 'supported_scopes' => [..., 'region'] in FestReportCatalog's UI metadata, but the id
 * was missing from REGION_ID_AWARE_IDS. Two things follow from that list specifically:
 *
 *  1. Downloads.vue's per-region sections (regionChildrenWithExports) build each
 *     region's own tile href straight off that child event's own id — UNLESS the export
 *     id is in this list, in which case FestReportCatalog::regionScopedRows() swaps it
 *     for the hub's own route with an explicit ?region_id= instead (same fix already
 *     applied to item-counts, student-wise, etc. — see REGION_ID_AWARE_IDS' own
 *     docblock). Without the id listed, "Team Managers" inside a region's own downloads
 *     section linked to the child directly instead of through that established,
 *     tested-safe path.
 *  2. FestReportController::export()'s generic dispatcher only calls
 *     regionAwareTargetEvent() for listed ids — belt-and-braces alongside #1, since an
 *     admin (or a saved link) hitting the hub URL with ?region_id= by hand, rather than
 *     through a Downloads.vue tile, should get the same containment.
 *
 * (?region_id= on the hub's own URL was already correctly contained before this fix too
 * — FestReportController::reportScope() resolves a proper region-scoped FestReportScope
 * off that param independently of this list — so this test's first case would pass
 * either way; it's kept as a straightforward end-to-end sanity check, while the second
 * case is what actually regresses without the id in the list.)
 */
class FestTeamManagersRegionScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_team_managers_export_scoped_to_one_region_excludes_the_other_regions_school(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'sahodaya', 'name' => 'Team Manager Region Sahodaya',
            'domain' => 'team-manager-region.test', 'is_active' => true,
        ]);
        SahodayaProfile::create(['tenant_id' => $sahodaya->id, 'prefix' => 'TMR', 'student_data_mode' => 'counts_only']);

        $regionA = Region::create(['tenant_id' => $sahodaya->id, 'name' => 'Region A', 'code' => 'RGA', 'is_active' => true]);
        $regionB = Region::create(['tenant_id' => $sahodaya->id, 'name' => 'Region B', 'code' => 'RGB', 'is_active' => true]);

        $schoolA = Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'school', 'name' => 'Region A Sentinel School',
            'parent_id' => $sahodaya->id, 'membership_status' => 'approved', 'is_active' => true,
        ]);
        $schoolB = Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'school', 'name' => 'Region B Sentinel School',
            'parent_id' => $sahodaya->id, 'membership_status' => 'approved', 'is_active' => true,
        ]);

        $hub = FestEvent::create([
            'tenant_id' => $sahodaya->id, 'title' => 'Team Manager Region Kalotsav', 'event_type' => 'kalolsavam',
            'conduct_mode' => 'partitioned', 'level_round' => 'sahodaya', 'status' => 'registration_open',
        ]);
        $childA = FestEvent::create([
            'tenant_id' => $sahodaya->id, 'title' => 'Region A Leg', 'event_type' => 'kalolsavam',
            'parent_event_id' => $hub->id, 'partition_key' => 'region-a', 'partition_role' => 'region',
            'region_id' => $regionA->id, 'level_round' => 'sahodaya', 'status' => 'registration_open',
        ]);
        $childB = FestEvent::create([
            'tenant_id' => $sahodaya->id, 'title' => 'Region B Leg', 'event_type' => 'kalolsavam',
            'parent_event_id' => $hub->id, 'partition_key' => 'region-b', 'partition_role' => 'region',
            'region_id' => $regionB->id, 'level_round' => 'sahodaya', 'status' => 'registration_open',
        ]);

        $this->registerOneStudent($schoolA, $childA, 'Region A Sentinel Student');
        $this->registerOneStudent($schoolB, $childB, 'Region B Sentinel Student');

        FestSchoolTeamManager::create([
            'tenant_id' => $sahodaya->id, 'event_id' => $childA->id, 'school_id' => $schoolA->id,
            'manager_name_1' => 'Region A Manager', 'manager_phone_1' => '1111111111',
        ]);
        FestSchoolTeamManager::create([
            'tenant_id' => $sahodaya->id, 'event_id' => $childB->id, 'school_id' => $schoolB->id,
            'manager_name_1' => 'Region B Manager', 'manager_phone_1' => '2222222222',
        ]);

        $admin = User::factory()->create(['tenant_id' => $sahodaya->id, 'email_verified_at' => now()]);
        $admin->assignRole('sahodaya_admin');

        $response = $this->actingAs($admin)->get(route('sahodaya.events.reports.export', [
            'tenantId' => $sahodaya->id,
            'event' => $hub->id,
            'exportType' => 'team-managers',
            'region_id' => $regionA->id,
        ]));

        $response->assertOk();
        $content = $response->streamedContent();

        // School names render upper-cased in this export (see FestReportService::teamManagersXls()).
        $this->assertStringContainsString('REGION A SENTINEL SCHOOL', $content);
        $this->assertStringContainsString('Region A Manager', $content);
        $this->assertStringNotContainsString('REGION B SENTINEL SCHOOL', $content);
        $this->assertStringNotContainsString('Region B Manager', $content);
    }

    public function test_downloads_page_routes_a_regions_team_managers_tile_through_the_hub_with_region_id(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'sahodaya', 'name' => 'Downloads Tile Region Sahodaya',
            'domain' => 'downloads-tile-region.test', 'is_active' => true,
        ]);
        SahodayaProfile::create(['tenant_id' => $sahodaya->id, 'prefix' => 'DTR', 'student_data_mode' => 'counts_only']);

        $regionA = Region::create(['tenant_id' => $sahodaya->id, 'name' => 'Region A', 'code' => 'RGA', 'is_active' => true]);

        $hub = FestEvent::create([
            'tenant_id' => $sahodaya->id, 'title' => 'Downloads Tile Kalotsav', 'event_type' => 'kalolsavam',
            'conduct_mode' => 'partitioned', 'level_round' => 'sahodaya', 'status' => 'registration_open',
        ]);
        $childA = FestEvent::create([
            'tenant_id' => $sahodaya->id, 'title' => 'Region A Leg', 'event_type' => 'kalolsavam',
            'parent_event_id' => $hub->id, 'partition_key' => 'region-a', 'partition_role' => 'region',
            'region_id' => $regionA->id, 'level_round' => 'sahodaya', 'status' => 'registration_open',
        ]);

        $admin = User::factory()->create(['tenant_id' => $sahodaya->id, 'email_verified_at' => now()]);
        $admin->assignRole('sahodaya_admin');

        $response = $this->actingAs($admin)->get(route('sahodaya.events.reports.downloads', [
            'tenantId' => $sahodaya->id, 'event' => $hub->id, 'phase' => 'before',
        ]));

        $response->assertOk();

        $props = $response->viewData('page')['props'];
        $regionSection = collect($props['regionChildrenWithExports'])->firstWhere('id', $childA->id);
        $this->assertNotNull($regionSection, 'Region A must have its own downloads section on this page.');

        $teamManagersTile = collect($regionSection['exports'])->firstWhere('id', 'team-managers-pdf');
        $this->assertNotNull($teamManagersTile, 'Team Managers PDF must be offered inside the region section.');

        $href = $teamManagersTile['href'] ?? $teamManagersTile['previewHref'] ?? null;
        $this->assertNotNull($href);
        $this->assertStringContainsString("/events/{$hub->id}/", $href,
            'Must route through the hub (regionScopedRows() swap), not the child event\'s own id.');
        $this->assertStringContainsString("region_id={$regionA->id}", $href);
        $this->assertStringNotContainsString("/events/{$childA->id}/", $href);
    }

    private function registerOneStudent(Tenant $school, FestEvent $event, string $studentName): void
    {
        $class = SchoolClass::create(['tenant_id' => $school->id, 'name' => '10', 'display_order' => 10]);
        $student = Student::create(['tenant_id' => $school->id, 'school_class_id' => $class->id, 'name' => $studentName, 'status' => 'active']);

        $registration = FestRegistration::create([
            'event_id' => $event->id, 'school_id' => $school->id, 'status' => 'approved', 'submitted_at' => now(),
        ]);

        FestParticipant::create([
            'registration_id' => $registration->id, 'event_id' => $event->id,
            'student_id' => $student->id, 'participant_role' => 'performer',
        ]);
    }
}
