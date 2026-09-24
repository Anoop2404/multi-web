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
 * ?region_id= on the hub's own Team Managers export URL is correctly contained to that
 * one region regardless of REGION_ID_AWARE_IDS membership -- FestReportController::
 * reportScope() resolves a proper region-scoped FestReportScope off that param
 * independently of the list, since it reads region_id straight off the request either
 * way. team-managers/team-managers-pdf are deliberately NOT in that list (see its own
 * docblock in FestReportCatalog) precisely because the alternative -- routing every hit
 * through regionAwareTargetEvent() -- detaches ANY leaf from its parent even with no
 * ?region_id= at all, which silently combines every other leg's students into a plain,
 * unscoped visit (the far more common way this report is actually reached).
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

    /**
     * Superseded expectation (2026-09-24): team-managers/team-managers-pdf were removed
     * from REGION_ID_AWARE_IDS again the same day (see FestReportCatalog's own docblock)
     * because routing every hit through regionAwareTargetEvent() detached ANY leaf from
     * its parent even with no ?region_id= at all, silently combining every other leg's
     * students into a plain, unscoped visit. Direct child-id access is now correct on its
     * own -- FestReportController::reportScope() lands on 'self' for a leg with its real,
     * undetached parent_event_id -- so this region section's tile linking straight at the
     * child's own id (not rerouted through the hub) is the desired behavior, not a gap.
     */
    public function test_downloads_page_region_tile_links_directly_to_the_child_leg(): void
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
        $this->assertStringContainsString("/events/{$childA->id}/", $href);
    }

    /**
     * The sidebar "Team Managers" link used to point straight at the raw PDF export
     * endpoint with target="_blank" -- Inertia's <Link> ignores `target` entirely when
     * deciding whether to intercept a click (it only reads `target` for the rendered DOM
     * attribute), so it always ran the export through an XHR visit and rendered the
     * non-Inertia PDF response inside Inertia's own error dialog instead of letting the
     * browser open/download it. This is the fix: a real interactive page, matching every
     * other report (e.g. Unique Participant Counts), with Preview/Download PDF buttons
     * that are plain <a target="_blank"> links.
     */
    public function test_team_managers_page_route_renders_the_report_inline(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'sahodaya', 'name' => 'Team Manager Page Sahodaya',
            'domain' => 'team-manager-page.test', 'is_active' => true,
        ]);
        SahodayaProfile::create(['tenant_id' => $sahodaya->id, 'prefix' => 'TMP', 'student_data_mode' => 'counts_only']);

        $school = Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'school', 'name' => 'Team Manager Page School',
            'parent_id' => $sahodaya->id, 'membership_status' => 'approved', 'is_active' => true,
        ]);

        $event = FestEvent::create([
            'tenant_id' => $sahodaya->id, 'title' => 'Team Manager Page Kalotsav', 'event_type' => 'kalolsavam',
            'level_round' => 'sahodaya', 'status' => 'registration_open',
        ]);

        $this->registerOneStudent($school, $event, 'Page Sentinel Student');

        FestSchoolTeamManager::create([
            'tenant_id' => $sahodaya->id, 'event_id' => $event->id, 'school_id' => $school->id,
            'manager_name_1' => 'Page Sentinel Manager', 'manager_phone_1' => '9998887776',
        ]);

        $admin = User::factory()->create(['tenant_id' => $sahodaya->id, 'email_verified_at' => now()]);
        $admin->assignRole('sahodaya_admin');

        $response = $this->actingAs($admin)->get(route('sahodaya.events.reports.team-managers', [
            'tenantId' => $sahodaya->id, 'event' => $event->id,
        ]));

        $response->assertOk();
        $props = $response->viewData('page');
        $this->assertSame('Sahodaya/Events/Reports/TeamManagers', $props['component']);

        $rows = $props['props']['rows'];
        $this->assertCount(1, $rows);
        $this->assertSame('Team Manager Page School', $rows[0]->school_name);
        $this->assertSame('Page Sentinel Manager', $rows[0]->manager_name_1);
        $this->assertSame(1, $rows[0]->unique_student_count);

        $this->assertStringContainsString('/reports/export/team-managers-pdf', $props['props']['pdfUrl']);
        $this->assertStringContainsString('/reports/export/team-managers', $props['props']['xlsUrl']);
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
