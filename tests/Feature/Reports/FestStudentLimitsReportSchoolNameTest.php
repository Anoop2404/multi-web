<?php

namespace Tests\Feature\Reports;

use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestParticipant;
use App\Models\FestRegistration;
use App\Models\Registration;
use App\Models\SahodayaProfile;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Tenant;
use App\Models\User;
use App\Support\AcademicYear;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The Student Item Limits PDF (resources/views/fest/reports/student-limits.blade.php,
 * shared by both the Sahodaya-wide report and a School Admin's own scoped report) showed
 * neither the student's school name nor, on the school-scoped copy, any heading
 * identifying which school it belonged to — the branding header always renders the
 * Sahodaya's own name/logo (FestReportService::brandingData() resolves the event's
 * tenant, never the individual school). Confirms both are now rendered directly from the
 * Blade view, the same way FestChestNumberFestIdColumnTest checks the chest-numbers PDF.
 */
class FestStudentLimitsReportSchoolNameTest extends TestCase
{
    use RefreshDatabase;

    public function test_school_admin_can_download_their_own_student_limits_pdf(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'sahodaya', 'name' => 'Student Limits PDF Sahodaya',
            'domain' => Str::uuid().'.test', 'is_active' => true,
        ]);
        SahodayaProfile::create(['tenant_id' => $sahodaya->id, 'prefix' => 'SLP', 'student_data_mode' => 'counts_only']);

        $school = Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'school', 'name' => 'Student Limits PDF School',
            'parent_id' => $sahodaya->id, 'membership_status' => 'approved', 'is_active' => true,
        ]);

        $admin = User::factory()->create(['tenant_id' => $school->id]);
        $admin->assignRole('school_admin');

        $event = FestEvent::create([
            'tenant_id' => $sahodaya->id, 'title' => 'Student Limits PDF Kalotsav', 'event_type' => 'kalolsavam',
            'level_round' => 'sahodaya', 'status' => 'registration_open',
        ]);

        $item = FestEventItem::create([
            'event_id' => $event->id, 'title' => 'Recitation', 'participant_type' => 'individual', 'is_enabled' => true,
        ]);

        $schoolClass = SchoolClass::create(['tenant_id' => $school->id, 'name' => '8']);
        $student = Student::create(['tenant_id' => $school->id, 'school_class_id' => $schoolClass->id, 'name' => 'Limits PDF Student', 'reg_no' => 'STU/1']);
        $registration = FestRegistration::create(['event_id' => $event->id, 'item_id' => $item->id, 'school_id' => $school->id, 'status' => 'approved']);
        FestParticipant::create([
            'registration_id' => $registration->id, 'student_id' => $student->id,
            'participant_type' => 'student', 'event_id' => $event->id, 'participant_role' => 'performer',
        ]);

        // Clears SchoolDocumentDownloadGateService::membershipFeeCleared(), required by
        // school-admin report/download routes (see FestSchoolItemScheduleReportTest).
        Registration::create([
            'school_id' => $school->id,
            'academic_year' => AcademicYear::forSahodaya($sahodaya->id),
            'registration_status' => 'completed',
        ]);

        $response = $this->actingAs($admin)->get(
            route('school.kalotsav.reports.student-limits.pdf', ['tenantId' => $school->id, 'event' => $event->id]),
        );

        $response->assertOk();
    }

    public function test_pdf_shows_each_students_school_name(): void
    {
        $html = view('fest.reports.student-limits', [
            'event' => (object) ['title' => 'Sample Kalotsav'],
            'rows' => [
                ['name' => 'A Student', 'reg_no' => 'REG1', 'school_name' => 'AMU Residential School', 'exceeds_any' => false, 'items' => [], 'on_stage' => [], 'off_stage' => [], 'individual' => [], 'group' => [], 'total' => []],
            ],
            'summary' => [],
            'orgName' => 'Sample Sahodaya',
            'logoSrc' => null,
        ])->render();

        $this->assertStringContainsString('AMU Residential School', $html);
    }

    public function test_pdf_shows_a_school_heading_when_scoped_to_one_school(): void
    {
        $html = view('fest.reports.student-limits', [
            'event' => (object) ['title' => 'Sample Kalotsav'],
            'rows' => [],
            'summary' => [],
            'orgName' => 'Sample Sahodaya',
            'logoSrc' => null,
            'school' => ['id' => 'sch-1', 'name' => 'AMU Residential School'],
        ])->render();

        $this->assertStringContainsString('AMU Residential School', $html);
    }

    public function test_pdf_omits_the_school_heading_on_the_sahodaya_wide_report(): void
    {
        $html = view('fest.reports.student-limits', [
            'event' => (object) ['title' => 'Sample Kalotsav'],
            'rows' => [],
            'summary' => [],
            'orgName' => 'Sample Sahodaya',
            'logoSrc' => null,
        ])->render();

        $this->assertStringNotContainsString('AMU Residential School', $html);
    }
}
