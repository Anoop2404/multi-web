<?php

namespace Tests\Feature\SahodayaAdmin;

use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestEventPhase;
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
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Real production topology (Malappuram Central Sahodaya, event 61): a
 * phased_regional_billing root with several phase+region leaves (Off Stage — Tirur,
 * Sargadhara — Manjeri, District Kalotsav, ...). Hitting the Team Managers export with no
 * scope always combines every leaf together (correct default — one Team Manager covers a
 * school's whole contingent). This proves the Downloads page's "Competition phase"
 * selector can now actually narrow that down to one phase's leaves, both in the tile href
 * the page hands back and in the data the export itself returns when hit with that param.
 */
class FestTeamManagersPhaseScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_downloads_page_carries_the_selected_phase_into_the_team_managers_tile_href(): void
    {
        $f = $this->twoPhaseFixture();

        $response = $this->actingAs($f['admin'])->get(
            "/sahodaya-admin/{$f['sahodaya']->id}/events/{$f['root']->id}/reports/downloads/before"
        );
        $response->assertOk();
        $unscoped = collect($response->viewData('page')['props']['exports'])->firstWhere('id', 'team-managers-pdf');
        $this->assertStringNotContainsString('competition_phase_id', $unscoped['href']);

        $scoped = $this->actingAs($f['admin'])->get(
            "/sahodaya-admin/{$f['sahodaya']->id}/events/{$f['root']->id}/reports/downloads/before?competition_phase_id={$f['phase1']->id}"
        );
        $scoped->assertOk();
        $tile = collect($scoped->viewData('page')['props']['exports'])->firstWhere('id', 'team-managers-pdf');
        $this->assertStringContainsString("competition_phase_id={$f['phase1']->id}", $tile['href']);
    }

    public function test_team_managers_export_scoped_to_one_phase_excludes_the_other_phases_registrations(): void
    {
        $f = $this->twoPhaseFixture();

        // Combined (no phase filter): school appears in both phase1's two legs and
        // phase2's one leg -- 3 distinct students total.
        $combined = $this->actingAs($f['admin'])->get(
            "/sahodaya-admin/{$f['sahodaya']->id}/events/{$f['root']->id}/reports/export/team-managers"
        );
        $combined->assertOk();
        $combinedContent = $combined->streamedContent();
        $this->assertStringContainsString('PHASE SCOPE SCHOOL', $combinedContent);
        $this->assertStringContainsString('ss:Type="Number">3<', $combinedContent, 'Combined: all 3 students across both phases.');

        // Phase-scoped: only phase1's two legs (2 students), phase2's student excluded.
        $scoped = $this->actingAs($f['admin'])->get(
            "/sahodaya-admin/{$f['sahodaya']->id}/events/{$f['root']->id}/reports/export/team-managers?competition_phase_id={$f['phase1']->id}"
        );
        $scoped->assertOk();
        $scopedContent = $scoped->streamedContent();
        $this->assertStringContainsString('ss:Type="Number">2<', $scopedContent, 'Phase-scoped: only phase1\'s 2 students.');
    }

    /** @return array{sahodaya: Tenant, admin: User, root: FestEvent, phase1: FestEventPhase, phase2: FestEventPhase, school: Tenant} */
    private function twoPhaseFixture(): array
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'sahodaya', 'name' => 'Phase Scope Sahodaya',
            'domain' => 'phase-scope-'.Str::random(8).'.test', 'is_active' => true,
        ]);
        SahodayaProfile::create(['tenant_id' => $sahodaya->id, 'prefix' => 'PHS', 'student_data_mode' => 'counts_only']);

        $school = Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'school', 'name' => 'Phase Scope School',
            'parent_id' => $sahodaya->id, 'membership_status' => 'approved', 'is_active' => true,
        ]);
        $class = SchoolClass::create(['tenant_id' => $school->id, 'name' => '10']);

        $admin = User::factory()->create(['tenant_id' => $sahodaya->id, 'email_verified_at' => now()]);
        $admin->assignRole('sahodaya_admin');

        $regionA = Region::create(['tenant_id' => $sahodaya->id, 'name' => 'Region A', 'code' => 'RGA']);
        $regionB = Region::create(['tenant_id' => $sahodaya->id, 'name' => 'Region B', 'code' => 'RGB']);

        $root = FestEvent::create([
            'tenant_id' => $sahodaya->id, 'title' => 'Phase Scope Kalotsav', 'event_type' => 'kalolsavam',
            'workflow_mode' => 'phased_regional_billing', 'status' => 'published',
        ]);
        FestSchoolTeamManager::create([
            'tenant_id' => $sahodaya->id, 'event_id' => $root->id, 'school_id' => $school->id,
            'manager_name_1' => 'The Manager', 'manager_phone_1' => '9999999999',
        ]);

        $phase1 = FestEventPhase::create(['event_id' => $root->id, 'name' => 'Off Stage', 'code' => 'off-stage', 'is_regional' => true]);
        $phase2 = FestEventPhase::create(['event_id' => $root->id, 'name' => 'On Stage', 'code' => 'on-stage', 'is_regional' => true]);

        $leaf1a = FestEvent::create([
            'tenant_id' => $sahodaya->id, 'parent_event_id' => $root->id, 'root_event_id' => $root->id,
            'source_phase_id' => $phase1->id, 'region_id' => $regionA->id,
            'title' => 'Off Stage — Region A', 'event_type' => 'kalolsavam',
            'workflow_mode' => 'phased_regional_billing', 'status' => 'published',
        ]);
        $leaf1b = FestEvent::create([
            'tenant_id' => $sahodaya->id, 'parent_event_id' => $root->id, 'root_event_id' => $root->id,
            'source_phase_id' => $phase1->id, 'region_id' => $regionB->id,
            'title' => 'Off Stage — Region B', 'event_type' => 'kalolsavam',
            'workflow_mode' => 'phased_regional_billing', 'status' => 'published',
        ]);
        $leaf2 = FestEvent::create([
            'tenant_id' => $sahodaya->id, 'parent_event_id' => $root->id, 'root_event_id' => $root->id,
            'source_phase_id' => $phase2->id, 'region_id' => $regionA->id,
            'title' => 'On Stage — Region A', 'event_type' => 'kalolsavam',
            'workflow_mode' => 'phased_regional_billing', 'status' => 'published',
        ]);

        // One student per leaf -- 2 distinct in phase1 (leaf1a + leaf1b), 1 more in phase2.
        foreach ([['leaf' => $leaf1a, 'name' => 'Phase1 Region A Student'], ['leaf' => $leaf1b, 'name' => 'Phase1 Region B Student'], ['leaf' => $leaf2, 'name' => 'Phase2 Student']] as $row) {
            $student = Student::create(['tenant_id' => $school->id, 'school_class_id' => $class->id, 'name' => $row['name'], 'status' => 'active']);
            $item = FestEventItem::create(['event_id' => $row['leaf']->id, 'title' => 'Item for '.$row['name'], 'participant_type' => 'individual', 'is_enabled' => true]);
            $reg = FestRegistration::create(['event_id' => $row['leaf']->id, 'item_id' => $item->id, 'school_id' => $school->id, 'status' => 'approved']);
            FestParticipant::create(['registration_id' => $reg->id, 'student_id' => $student->id, 'participant_type' => 'student', 'participant_role' => 'performer', 'chest_no' => 1]);
        }

        return compact('sahodaya', 'admin', 'root', 'phase1', 'phase2', 'school');
    }
}
