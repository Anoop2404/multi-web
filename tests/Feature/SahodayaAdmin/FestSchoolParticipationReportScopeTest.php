<?php

namespace Tests\Feature\SahodayaAdmin;

use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestEventPhase;
use App\Models\FestParticipant;
use App\Models\FestRegistration;
use App\Models\FestSchoolPhaseRegionSelection;
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

    /**
     * FestRegistrationCreateService::createForSchool() bundles multiple students under
     * ONE FestRegistration row for an individual item when max_per_school > 1 (each gets
     * its own FestParticipant), but always keeps a team/group entry to exactly one row
     * regardless of roster size. "Active regs" must count each individual participant as
     * its own registration while still counting a team entry once — the same atomic-unit
     * convention FestMark::deduplicationKey() already uses.
     */
    public function test_active_regs_counts_individual_participants_but_team_entries_once(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'sahodaya', 'name' => 'Atomic Count Sahodaya',
            'domain' => Str::uuid().'.test', 'is_active' => true,
        ]);
        SahodayaProfile::create(['tenant_id' => $sahodaya->id, 'prefix' => 'AC', 'student_data_mode' => 'counts_only']);

        $school = Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'school', 'name' => 'Atomic Count School',
            'parent_id' => $sahodaya->id, 'membership_status' => 'approved', 'is_active' => true,
        ]);

        $event = FestEvent::create([
            'tenant_id' => $sahodaya->id, 'title' => 'Atomic Count Kalotsav', 'event_type' => 'kalolsavam',
            'status' => 'published',
        ]);

        $individualItem = FestEventItem::create([
            'event_id' => $event->id, 'title' => 'Solo Song', 'item_code' => '401',
            'stage_type' => 'on_stage', 'participant_type' => 'individual', 'category' => 'music',
            'is_enabled' => true, 'max_per_school' => 3,
        ]);
        $teamItem = FestEventItem::create([
            'event_id' => $event->id, 'title' => 'Group Dance', 'item_code' => '402',
            'stage_type' => 'on_stage', 'participant_type' => 'group', 'category' => 'dance',
            'is_enabled' => true,
        ]);

        $class = SchoolClass::create(['tenant_id' => $school->id, 'name' => '10']);
        $students = collect(['A', 'B', 'C', 'D', 'E'])->map(fn ($label) => Student::create([
            'tenant_id' => $school->id, 'school_class_id' => $class->id, 'name' => "Student {$label}", 'reg_no' => "STU/{$label}",
        ]));

        // Two students individually registered for the same individual item, bundled under
        // one FestRegistration row -- each must still count as its own active registration.
        $individualReg = FestRegistration::create(['event_id' => $event->id, 'item_id' => $individualItem->id, 'school_id' => $school->id, 'status' => 'submitted']);
        FestParticipant::create(['registration_id' => $individualReg->id, 'student_id' => $students[0]->id, 'participant_type' => 'student', 'event_id' => $event->id]);
        FestParticipant::create(['registration_id' => $individualReg->id, 'student_id' => $students[1]->id, 'participant_type' => 'student', 'event_id' => $event->id]);

        // A team entry: one registration row, three participants -- counts as ONE active registration.
        $teamReg = FestRegistration::create(['event_id' => $event->id, 'item_id' => $teamItem->id, 'school_id' => $school->id, 'status' => 'submitted']);
        FestParticipant::create(['registration_id' => $teamReg->id, 'student_id' => $students[2]->id, 'participant_type' => 'student', 'event_id' => $event->id]);
        FestParticipant::create(['registration_id' => $teamReg->id, 'student_id' => $students[3]->id, 'participant_type' => 'student', 'event_id' => $event->id]);
        FestParticipant::create(['registration_id' => $teamReg->id, 'student_id' => $students[4]->id, 'participant_type' => 'student', 'event_id' => $event->id]);

        $admin = User::factory()->create(['tenant_id' => $sahodaya->id, 'email_verified_at' => now()]);
        $admin->assignRole('sahodaya_admin');

        $this->actingAs($admin)
            ->get("/sahodaya-admin/{$sahodaya->id}/events/{$event->id}/reports/school-participation")
            ->assertStatus(200)
            ->assertInertia(fn (Assert $page) => $page
                ->component('Sahodaya/Events/Reports/SchoolParticipation', false)
                ->has('rows', 1)
                ->where('rows.0.active_count', 3)
                ->where('rows.0.item_count', 2)
                ->where('rows.0.unique_student_count', 5)
                ->where('totals.active_registrations', 3)
            );
    }
}
