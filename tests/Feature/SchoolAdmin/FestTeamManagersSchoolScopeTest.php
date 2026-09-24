<?php

namespace Tests\Feature\SchoolAdmin;

use App\Models\FestEvent;
use App\Models\FestSchoolTeamManager;
use App\Models\SahodayaProfile;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * FestSchoolReportController::export() forces school_id to the authenticated school
 * (never trusts a request-supplied one), and FestReportService::teamManagersData() filters
 * on it -- but that School A/School B sentinel check had never actually been run for this
 * specific export before schoolEventNav.js started linking to it. Proves a school hitting
 * its own "Team Managers" sidebar link sees only its own row, never another school's team
 * manager name/phone/email.
 */
class FestTeamManagersSchoolScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_school_sees_only_its_own_team_manager_row(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'sahodaya', 'name' => 'School Scope Sahodaya',
            'domain' => 'school-scope-'.Str::random(8).'.test', 'is_active' => true,
        ]);
        SahodayaProfile::create(['tenant_id' => $sahodaya->id, 'prefix' => 'SCS', 'student_data_mode' => 'counts_only']);

        $schoolA = Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'school', 'name' => 'Sentinel School A',
            'parent_id' => $sahodaya->id, 'membership_status' => 'approved', 'is_active' => true,
        ]);
        $schoolB = Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'school', 'name' => 'Sentinel School B',
            'parent_id' => $sahodaya->id, 'membership_status' => 'approved', 'is_active' => true,
        ]);

        $event = FestEvent::create([
            'tenant_id' => $sahodaya->id, 'title' => 'School Scope Kalotsav', 'event_type' => 'kalolsavam',
            'level_round' => 'sahodaya', 'status' => 'registration_open',
        ]);

        FestSchoolTeamManager::create([
            'tenant_id' => $sahodaya->id, 'event_id' => $event->id, 'school_id' => $schoolA->id,
            'manager_name_1' => 'School A Manager', 'manager_phone_1' => '1111111111',
        ]);
        FestSchoolTeamManager::create([
            'tenant_id' => $sahodaya->id, 'event_id' => $event->id, 'school_id' => $schoolB->id,
            'manager_name_1' => 'School B Manager', 'manager_phone_1' => '2222222222',
        ]);

        \App\Models\FestRegistration::create(['event_id' => $event->id, 'school_id' => $schoolA->id, 'status' => 'approved']);
        \App\Models\FestRegistration::create(['event_id' => $event->id, 'school_id' => $schoolB->id, 'status' => 'approved']);

        $adminA = User::factory()->create(['tenant_id' => $schoolA->id, 'email_verified_at' => now()]);
        $adminA->assignRole('school_admin');

        $response = $this->actingAs($adminA)->get(route('school.kalotsav.reports.export', [
            'tenantId' => $schoolA->id, 'event' => $event->id, 'exportType' => 'team-managers-pdf',
        ]));

        $response->assertOk();
        $html = $response->getContent();

        $this->assertStringContainsString('SENTINEL SCHOOL A', $html);
        $this->assertStringContainsString('School A Manager', $html);
        $this->assertStringNotContainsString('SENTINEL SCHOOL B', $html);
        $this->assertStringNotContainsString('School B Manager', $html);
        $this->assertStringNotContainsString('2222222222', $html);
    }

    public function test_school_id_query_param_cannot_override_the_authenticated_schools_own_scope(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'sahodaya', 'name' => 'School Scope Override Sahodaya',
            'domain' => 'school-scope-override-'.Str::random(8).'.test', 'is_active' => true,
        ]);
        SahodayaProfile::create(['tenant_id' => $sahodaya->id, 'prefix' => 'SCO', 'student_data_mode' => 'counts_only']);

        $schoolA = Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'school', 'name' => 'Override Sentinel School A',
            'parent_id' => $sahodaya->id, 'membership_status' => 'approved', 'is_active' => true,
        ]);
        $schoolB = Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'school', 'name' => 'Override Sentinel School B',
            'parent_id' => $sahodaya->id, 'membership_status' => 'approved', 'is_active' => true,
        ]);

        $event = FestEvent::create([
            'tenant_id' => $sahodaya->id, 'title' => 'Override Kalotsav', 'event_type' => 'kalolsavam',
            'level_round' => 'sahodaya', 'status' => 'registration_open',
        ]);

        FestSchoolTeamManager::create([
            'tenant_id' => $sahodaya->id, 'event_id' => $event->id, 'school_id' => $schoolB->id,
            'manager_name_1' => 'Should Not Leak', 'manager_phone_1' => '9999999999',
        ]);
        \App\Models\FestRegistration::create(['event_id' => $event->id, 'school_id' => $schoolB->id, 'status' => 'approved']);

        $adminA = User::factory()->create(['tenant_id' => $schoolA->id, 'email_verified_at' => now()]);
        $adminA->assignRole('school_admin');

        // School A tries to peek at School B's data via a forged school_id query param.
        $response = $this->actingAs($adminA)->get(route('school.kalotsav.reports.export', [
            'tenantId' => $schoolA->id, 'event' => $event->id, 'exportType' => 'team-managers-pdf',
            'school_id' => $schoolB->id,
        ]));

        $response->assertOk();
        $html = $response->getContent();
        $this->assertStringNotContainsString('Should Not Leak', $html);
        $this->assertStringNotContainsString('9999999999', $html);
        $this->assertStringNotContainsString('OVERRIDE SENTINEL SCHOOL B', $html);
    }
}
