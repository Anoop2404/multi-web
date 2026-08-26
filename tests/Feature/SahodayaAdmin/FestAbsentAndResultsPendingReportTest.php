<?php

namespace Tests\Feature\SahodayaAdmin;

use App\Models\FestAttendance;
use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestEventStaff;
use App\Models\FestMark;
use App\Models\FestParticipant;
use App\Models\FestRegistration;
use App\Models\SahodayaProfile;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Tenant;
use App\Models\User;
use App\Support\TenantUserCatalog;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Covers the 2026-08-26 additions requested alongside the item-wise/mark-entry work:
 * an absent-participants report (nothing previously exposed FestAttendance rows in a
 * report) and a results-pending report (items where marks_ready is true but
 * results_published is false — reuses FestItemResultsService::itemSummaries(), the
 * same computation already powering the Overview page's marked_unpublished_items tile).
 */
class FestAbsentAndResultsPendingReportTest extends TestCase
{
    use RefreshDatabase;

    private function makeAdmin(FestEvent $event, Tenant $sahodaya): User
    {
        $admin = User::factory()->create(['tenant_id' => $sahodaya->id, 'email_verified_at' => now()]);
        $admin->assignRole('event_admin');
        $admin->givePermissionTo(TenantUserCatalog::defaultPermissionsForRole('event_admin'));
        FestEventStaff::create(['event_id' => $event->id, 'user_id' => $admin->id, 'duty' => 'event_admin']);

        return $admin;
    }

    public function test_absent_report_lists_only_participants_marked_absent(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'sahodaya', 'name' => 'Absent Report Sahodaya',
            'domain' => Str::uuid().'.test', 'is_active' => true,
        ]);
        SahodayaProfile::create(['tenant_id' => $sahodaya->id, 'prefix' => 'AR', 'student_data_mode' => 'counts_only']);

        $school = Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'school', 'name' => 'Absent Report School',
            'parent_id' => $sahodaya->id, 'membership_status' => 'approved', 'is_active' => true,
        ]);

        $event = FestEvent::create([
            'tenant_id' => $sahodaya->id, 'title' => 'Absent Report Kalotsav', 'event_type' => 'kalolsavam',
            'level_round' => 'sahodaya', 'status' => 'registration_open',
        ]);

        $item = FestEventItem::create([
            'event_id' => $event->id, 'title' => 'Recitation-Malayalam', 'item_code' => '101',
            'stage_type' => 'on_stage', 'participant_type' => 'individual', 'category' => 'music', 'is_enabled' => true,
        ]);

        $class = SchoolClass::create(['tenant_id' => $school->id, 'name' => '10']);
        $absentStudent = Student::create(['tenant_id' => $school->id, 'school_class_id' => $class->id, 'name' => 'Absent Student', 'reg_no' => 'STU/ABS']);
        $presentStudent = Student::create(['tenant_id' => $school->id, 'school_class_id' => $class->id, 'name' => 'Present Student', 'reg_no' => 'STU/PRE']);

        $absentReg = FestRegistration::create(['event_id' => $event->id, 'item_id' => $item->id, 'school_id' => $school->id, 'status' => 'approved']);
        $absentParticipant = FestParticipant::create(['registration_id' => $absentReg->id, 'student_id' => $absentStudent->id, 'participant_type' => 'student', 'event_id' => $event->id]);

        $presentReg = FestRegistration::create(['event_id' => $event->id, 'item_id' => $item->id, 'school_id' => $school->id, 'status' => 'approved']);
        $presentParticipant = FestParticipant::create(['registration_id' => $presentReg->id, 'student_id' => $presentStudent->id, 'participant_type' => 'student', 'event_id' => $event->id]);

        $admin = $this->makeAdmin($event, $sahodaya);

        FestAttendance::create([
            'event_id' => $event->id, 'item_id' => $item->id, 'participant_id' => $absentParticipant->id,
            'status' => 'absent', 'marked_by' => $admin->id, 'marked_at' => now(),
        ]);
        FestAttendance::create([
            'event_id' => $event->id, 'item_id' => $item->id, 'participant_id' => $presentParticipant->id,
            'status' => 'present', 'marked_by' => $admin->id, 'marked_at' => now(),
        ]);

        $response = $this->actingAs($admin)->get(route('sahodaya.events.reports.absent-report', [
            'tenantId' => $sahodaya->id, 'event' => $event->id,
        ]));
        $response->assertOk();

        $rows = collect($response->viewData('page')['props']['rows']);
        $this->assertCount(1, $rows, 'only the participant marked absent should appear');
        $this->assertSame('Absent Student', $rows->first()['participant']);
        $this->assertSame('Absent Report School', $rows->first()['school_name']);
        $this->assertSame('Music', $rows->first()['category_label']);
        $this->assertNotNull($rows->first()['marked_by']);

        $csv = $this->actingAs($admin)->get(route('sahodaya.events.reports.export', [
            'tenantId' => $sahodaya->id, 'event' => $event->id, 'exportType' => 'absent-report',
        ]));
        $csv->assertOk();
    }

    public function test_results_pending_report_shows_items_marked_but_not_published(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'sahodaya', 'name' => 'Results Pending Sahodaya',
            'domain' => Str::uuid().'.test', 'is_active' => true,
        ]);
        SahodayaProfile::create(['tenant_id' => $sahodaya->id, 'prefix' => 'RP', 'student_data_mode' => 'counts_only']);

        $school = Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'school', 'name' => 'Results Pending School',
            'parent_id' => $sahodaya->id, 'membership_status' => 'approved', 'is_active' => true,
        ]);

        $event = FestEvent::create([
            'tenant_id' => $sahodaya->id, 'title' => 'Results Pending Kalotsav', 'event_type' => 'kalolsavam',
            'level_round' => 'sahodaya', 'status' => 'registration_open',
        ]);

        $readyItem = FestEventItem::create([
            'event_id' => $event->id, 'title' => 'Ready Item', 'item_code' => '201',
            'stage_type' => 'on_stage', 'participant_type' => 'individual', 'category' => 'music', 'is_enabled' => true,
        ]);
        $unmarkedItem = FestEventItem::create([
            'event_id' => $event->id, 'title' => 'Unmarked Item', 'item_code' => '202',
            'stage_type' => 'on_stage', 'participant_type' => 'individual', 'category' => 'music', 'is_enabled' => true,
        ]);

        $class = SchoolClass::create(['tenant_id' => $school->id, 'name' => '10']);
        $student1 = Student::create(['tenant_id' => $school->id, 'school_class_id' => $class->id, 'name' => 'Student One', 'reg_no' => 'STU/1']);
        $student2 = Student::create(['tenant_id' => $school->id, 'school_class_id' => $class->id, 'name' => 'Student Two', 'reg_no' => 'STU/2']);

        $reg1 = FestRegistration::create(['event_id' => $event->id, 'item_id' => $readyItem->id, 'school_id' => $school->id, 'status' => 'approved']);
        $participant1 = FestParticipant::create(['registration_id' => $reg1->id, 'student_id' => $student1->id, 'participant_type' => 'student', 'event_id' => $event->id]);
        FestMark::create(['event_id' => $event->id, 'item_id' => $readyItem->id, 'participant_id' => $participant1->id, 'grade' => 'A', 'position' => 1, 'score' => 95]);

        $reg2 = FestRegistration::create(['event_id' => $event->id, 'item_id' => $unmarkedItem->id, 'school_id' => $school->id, 'status' => 'approved']);
        FestParticipant::create(['registration_id' => $reg2->id, 'student_id' => $student2->id, 'participant_type' => 'student', 'event_id' => $event->id]);

        $admin = $this->makeAdmin($event, $sahodaya);

        $response = $this->actingAs($admin)->get(route('sahodaya.events.reports.results-pending', [
            'tenantId' => $sahodaya->id, 'event' => $event->id,
        ]));
        $response->assertOk();

        $rows = collect($response->viewData('page')['props']['rows']);
        $this->assertCount(1, $rows, 'only the fully-marked, unpublished item should appear');
        $this->assertSame('Ready Item', $rows->first()['title']);

        $csv = $this->actingAs($admin)->get(route('sahodaya.events.reports.export', [
            'tenantId' => $sahodaya->id, 'event' => $event->id, 'exportType' => 'results-pending',
        ]));
        $csv->assertOk();
    }
}
