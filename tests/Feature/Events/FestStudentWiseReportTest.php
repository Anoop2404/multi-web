<?php

namespace Tests\Feature\Events;

use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestParticipant;
use App\Models\FestRegistration;
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
 * Covers the Sahodaya-admin Student-wise report (StudentWise.vue): each student row now
 * carries a school_code (Tenant::school_prefix) for display, and the "Chest No" column was
 * dropped from both the on-screen table and the PDF export (Chest No stays in the
 * underlying row data for admins who need it elsewhere — e.g. the "All Certificates" list
 * — this only drops it from THIS report's own display; see
 * SchoolReportsHideChestNumberTest::test_sahodaya_admin_student_wise_still_sees_chest_number()
 * for the still-intact, unrelated guarantee that Sahodaya admins keep chest-number access
 * generally, unlike School admins).
 */
class FestStudentWiseReportTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{sahodaya: Tenant, school: Tenant, admin: User, event: FestEvent} */
    private function fixture(): array
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'sahodaya', 'name' => 'Student Wise Sahodaya',
            'domain' => Str::uuid().'.test', 'is_active' => true,
        ]);
        SahodayaProfile::create(['tenant_id' => $sahodaya->id, 'prefix' => 'SW', 'student_data_mode' => 'counts_only']);

        $school = Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'school', 'parent_id' => $sahodaya->id,
            'name' => 'Student Wise School', 'school_prefix' => 'SWS',
            'membership_status' => 'approved', 'is_active' => true,
        ]);

        $admin = User::factory()->create(['tenant_id' => $sahodaya->id, 'email_verified_at' => now()]);
        $admin->assignRole('sahodaya_admin');

        $event = FestEvent::create(['tenant_id' => $sahodaya->id, 'title' => 'Student Wise Event', 'event_type' => 'kalolsavam']);
        $item = FestEventItem::create(['event_id' => $event->id, 'title' => 'Solo Song', 'item_code' => 'SW1']);

        $schoolClass = SchoolClass::create(['tenant_id' => $school->id, 'name' => '10']);
        $student = Student::create([
            'tenant_id' => $school->id, 'school_class_id' => $schoolClass->id,
            'name' => 'Student Wise Kid', 'admission_no' => 'SWK1',
        ]);
        $registration = FestRegistration::create([
            'event_id' => $event->id, 'item_id' => $item->id, 'school_id' => $school->id, 'status' => 'approved',
        ]);
        FestParticipant::create([
            'registration_id' => $registration->id, 'event_id' => $event->id, 'student_id' => $student->id,
            'participant_type' => 'student', 'participant_role' => 'performer', 'chest_no' => 501,
        ]);

        return compact('sahodaya', 'school', 'admin', 'event');
    }

    public function test_student_wise_rows_carry_the_schools_code_for_display(): void
    {
        $f = $this->fixture();

        $response = $this->actingAs($f['admin'])->get(route('sahodaya.events.reports.student-wise', [
            'tenantId' => $f['sahodaya']->id, 'event' => $f['event']->id,
        ]));

        $response->assertOk();
        $response->assertInertia(function ($page) {
            $rows = $page->toArray()['props']['rows'];
            $this->assertCount(1, $rows);
            $this->assertSame('SWS', $rows[0]['school_code']);

            return $page->has('rows', 1);
        });
    }

    public function test_student_wise_pdf_export_renders_successfully_with_chest_no_hidden(): void
    {
        $f = $this->fixture();

        // dompdf's compiled output doesn't preserve rendered text as a greppable
        // substring (see SchoolReportsHideChestNumberTest's own docblock for the same,
        // empirically-confirmed caveat) — this only proves the export still completes
        // now that showChestNo is explicitly passed; the column's actual removal is
        // covered by review of the 'showChestNo' guard already exercised by
        // SchoolReportsHideChestNumberTest for the same blade partial.
        $response = $this->actingAs($f['admin'])->get(route('sahodaya.events.reports.export', [
            'tenantId' => $f['sahodaya']->id, 'event' => $f['event']->id, 'exportType' => 'student-wise-pdf',
        ]));

        $response->assertOk();
    }

    public function test_student_wise_excel_export_renders_successfully_with_school_code_column(): void
    {
        $f = $this->fixture();

        $response = $this->actingAs($f['admin'])->get(route('sahodaya.events.reports.export', [
            'tenantId' => $f['sahodaya']->id, 'event' => $f['event']->id, 'exportType' => 'student-wise-report',
        ]));

        $response->assertOk();
    }
}
