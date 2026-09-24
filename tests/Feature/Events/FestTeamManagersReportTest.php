<?php

namespace Tests\Feature\Events;

use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestParticipant;
use App\Models\FestRegistration;
use App\Models\FestSchoolTeamManager;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Tenant;
use App\Services\Events\FestReportService;
use Illuminate\Http\Request;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FestTeamManagersReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_school_can_save_contingent_team_managers_and_admin_can_export_report(): void
    {
        $sahodaya = Tenant::create([
            'type' => 'sahodaya',
            'name' => 'Test Sahodaya',
            'subdomain' => 'test-sahodaya-' . uniqid(),
        ]);

        $schoolTenant = Tenant::create([
            'type' => 'school',
            'name' => 'St Joseph Higher Secondary School',
            'subdomain' => 'st-joseph-' . uniqid(),
            'parent_id' => $sahodaya->id,
            'school_prefix' => 'SJSS',
        ]);

        $event = FestEvent::create([
            'tenant_id' => $sahodaya->id,
            'title' => 'Sahodaya Kalotsav 2026',
            'event_type' => 'kalotsav',
            'fee_settings' => ['fee_model' => 'none'],
        ]);

        // Register school as active participant in event
        FestRegistration::create([
            'tenant_id' => $schoolTenant->id,
            'event_id' => $event->id,
            'school_id' => $schoolTenant->id,
            'status' => 'submitted',
        ]);

        // 1. Test record creation via model / DB
        FestSchoolTeamManager::updateOrCreate(
            [
                'event_id' => $event->id,
                'school_id' => $schoolTenant->id,
            ],
            [
                'tenant_id' => $sahodaya->id,
                'manager_name_1' => 'Fr. Mathew Thomas',
                'manager_phone_1' => '9876543210',
                'manager_email_1' => 'mathew@stjoseph.edu',
                'manager_role_1' => 'Principal & Chief Contingent Leader',
                'manager_name_2' => 'Mrs. Anita Roy',
                'manager_phone_2' => '9876543211',
                'manager_email_2' => 'anita@stjoseph.edu',
                'manager_role_2' => 'Arts In-Charge Teacher',
            ]
        );

        $this->assertDatabaseHas('fest_school_team_managers', [
            'event_id' => $event->id,
            'school_id' => $schoolTenant->id,
            'manager_name_1' => 'Fr. Mathew Thomas',
            'manager_name_2' => 'Mrs. Anita Roy',
        ]);

        // 2. Test FestReportService Excel/CSV export for team managers
        $reportService = new FestReportService($event);
        $data = $reportService->teamManagersData();

        $this->assertCount(1, $data);
        $row = $data->first();
        $this->assertEquals($schoolTenant->id, $row->school_id);
        $this->assertEquals('St Joseph Higher Secondary School', $row->school_name);
        $this->assertEquals('Fr. Mathew Thomas', $row->manager_name_1);
        $this->assertEquals('9876543210', $row->manager_phone_1);
        $this->assertEquals('Mrs. Anita Roy', $row->manager_name_2);

        // 3. Test export() match calls
        $csvResponse = $reportService->export('team-managers', new Request());
        $this->assertNotNull($csvResponse);

        $pdfResponse = $reportService->export('team-managers-pdf', new Request());
        $this->assertNotNull($pdfResponse);
    }

    public function test_falls_back_to_the_on_file_events_coordinator_when_no_team_manager_was_entered(): void
    {
        $sahodaya = Tenant::create([
            'type' => 'sahodaya', 'name' => 'Fallback Sahodaya', 'subdomain' => 'fallback-sahodaya-'.uniqid(),
        ]);

        $schoolTenant = Tenant::create([
            'type' => 'school', 'name' => 'Ace Public School', 'subdomain' => 'ace-'.uniqid(),
            'parent_id' => $sahodaya->id, 'school_prefix' => 'ACE',
            'application_payload' => [
                'event_coordinator_name'  => 'Manjusha C',
                'event_coordinator_email' => 'manju.c.jayan@gmail.com',
                'event_coordinator_phone' => '7593991649',
            ],
        ]);

        $event = FestEvent::create([
            'tenant_id' => $sahodaya->id, 'title' => 'Kalotsav 2027', 'event_type' => 'kalotsav',
            'fee_settings' => ['fee_model' => 'none'],
        ]);

        FestRegistration::create([
            'tenant_id' => $schoolTenant->id, 'event_id' => $event->id,
            'school_id' => $schoolTenant->id, 'status' => 'submitted',
        ]);

        // No FestSchoolTeamManager row at all for this school -- the blank case from the
        // screenshot (schools that have not filled in a team manager yet).
        $data = (new FestReportService($event))->teamManagersData();

        $this->assertCount(1, $data);
        $row = $data->first();
        $this->assertSame('Manjusha C', $row->manager_name_1);
        $this->assertSame('7593991649', $row->manager_phone_1);
        $this->assertSame('manju.c.jayan@gmail.com', $row->manager_email_1);
        $this->assertSame('Events Coordinator (on file)', $row->manager_role_1);
    }

    public function test_does_not_use_the_fallback_once_a_real_team_manager_is_entered(): void
    {
        $sahodaya = Tenant::create([
            'type' => 'sahodaya', 'name' => 'No Fallback Sahodaya', 'subdomain' => 'no-fallback-sahodaya-'.uniqid(),
        ]);

        $schoolTenant = Tenant::create([
            'type' => 'school', 'name' => 'Real Manager School', 'subdomain' => 'real-manager-'.uniqid(),
            'parent_id' => $sahodaya->id, 'school_prefix' => 'RMS',
            'application_payload' => ['event_coordinator_name' => 'Should Not Appear'],
        ]);

        $event = FestEvent::create([
            'tenant_id' => $sahodaya->id, 'title' => 'Kalotsav 2027', 'event_type' => 'kalotsav',
            'fee_settings' => ['fee_model' => 'none'],
        ]);

        FestRegistration::create([
            'tenant_id' => $schoolTenant->id, 'event_id' => $event->id,
            'school_id' => $schoolTenant->id, 'status' => 'submitted',
        ]);

        FestSchoolTeamManager::create([
            'tenant_id' => $sahodaya->id, 'event_id' => $event->id, 'school_id' => $schoolTenant->id,
            'manager_name_1' => 'Real Manager', 'manager_phone_1' => '1111111111',
        ]);

        $row = (new FestReportService($event))->teamManagersData()->first();

        $this->assertSame('Real Manager', $row->manager_name_1);
        $this->assertNotSame('Should Not Appear', $row->manager_name_1);
    }

    public function test_unique_student_count_matches_distinct_students_registered(): void
    {
        $sahodaya = Tenant::create([
            'type' => 'sahodaya', 'name' => 'Count Sahodaya', 'subdomain' => 'count-sahodaya-'.uniqid(),
        ]);

        $schoolTenant = Tenant::create([
            'type' => 'school', 'name' => 'Count School', 'subdomain' => 'count-school-'.uniqid(),
            'parent_id' => $sahodaya->id, 'school_prefix' => 'CNT',
        ]);
        $schoolClass = SchoolClass::create(['tenant_id' => $schoolTenant->id, 'name' => 'Class 10']);

        $event = FestEvent::create([
            'tenant_id' => $sahodaya->id, 'title' => 'Kalotsav 2027', 'event_type' => 'kalotsav',
            'fee_settings' => ['fee_model' => 'none'],
        ]);

        $itemA = FestEventItem::create(['event_id' => $event->id, 'title' => 'Item A', 'item_code' => 'ITA', 'participant_type' => 'individual', 'is_enabled' => true]);
        $itemB = FestEventItem::create(['event_id' => $event->id, 'title' => 'Item B', 'item_code' => 'ITB', 'participant_type' => 'individual', 'is_enabled' => true]);

        $student = Student::create(['tenant_id' => $schoolTenant->id, 'school_class_id' => $schoolClass->id, 'name' => 'Same Student', 'reg_no' => 'STU/27/0001']);

        // Same student registered for two different items -- must count once, not twice.
        foreach ([$itemA, $itemB] as $item) {
            $reg = FestRegistration::create(['event_id' => $event->id, 'item_id' => $item->id, 'school_id' => $schoolTenant->id, 'status' => 'approved']);
            FestParticipant::create([
                'registration_id' => $reg->id, 'student_id' => $student->id,
                'participant_type' => 'student', 'participant_role' => 'performer', 'chest_no' => 1,
            ]);
        }

        $row = (new FestReportService($event))->teamManagersData()->first();

        $this->assertSame(1, $row->unique_student_count);
    }
}
