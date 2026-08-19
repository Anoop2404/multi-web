<?php

namespace Tests\Feature\SchoolAdmin;

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
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Chest numbers are Sahodaya-admin-only information — schools shouldn't see them in any
 * report until they're assigned on fest day (a separate, non-report page). This covers
 * every School Admin report surface that used to include the real chest_no value (see
 * FestSchoolReportAnalyticsService, FestSchoolReportExportService,
 * FestRegistrationRegisterService::exportCsv(), and FestSchoolReportController) — a
 * distinctive chest number is planted in the fixture and asserted absent from each
 * response, rather than just checking a specific column/key, so the test also catches a
 * leak through some field/column this pass didn't anticipate.
 */
class SchoolReportsHideChestNumberTest extends TestCase
{
    use RefreshDatabase;

    private const CHEST_NO = 4217;

    /** @return array{sahodaya: Tenant, school: Tenant, admin: User, event: FestEvent, item: FestEventItem} */
    private function fixture(): array
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'sahodaya',
            'name' => 'Chest Hide Sahodaya',
            'domain' => Str::uuid().'.test',
            'is_active' => true,
        ]);
        SahodayaProfile::create(['tenant_id' => $sahodaya->id, 'prefix' => 'CH', 'student_data_mode' => 'counts_only']);

        $school = Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'school',
            'name' => 'Chest Hide School',
            'parent_id' => $sahodaya->id,
            'membership_status' => 'approved',
            'is_active' => true,
        ]);

        $admin = User::factory()->create(['tenant_id' => $school->id]);
        $admin->assignRole('school_admin');

        $event = FestEvent::create([
            'tenant_id' => $sahodaya->id, 'title' => 'Chest Hide Kalotsav', 'event_type' => 'kalolsavam',
            'level_round' => 'sahodaya', 'status' => 'registration_open',
        ]);

        $item = FestEventItem::create([
            'event_id' => $event->id, 'title' => 'Solo Song', 'participant_type' => 'individual', 'is_enabled' => true,
        ]);

        $schoolClass = SchoolClass::create(['tenant_id' => $school->id, 'name' => '10']);
        $student = Student::create([
            'tenant_id' => $school->id, 'school_class_id' => $schoolClass->id,
            'name' => 'Chest Hide Student', 'admission_no' => 'CHS1',
        ]);

        $registration = FestRegistration::create([
            'event_id' => $event->id, 'item_id' => $item->id, 'school_id' => $school->id, 'status' => 'approved',
        ]);
        FestParticipant::create([
            'registration_id' => $registration->id, 'student_id' => $student->id,
            'participant_role' => 'main', 'chest_no' => self::CHEST_NO,
        ]);

        // Clears SchoolDocumentDownloadGateService::membershipFeeCleared() — id-cards/
        // admit-cards both gate on this before anything else.
        Registration::create([
            'school_id' => $school->id,
            'academic_year' => \App\Support\AcademicYear::forSahodaya($sahodaya->id),
            'registration_status' => 'completed',
        ]);

        return compact('sahodaya', 'school', 'admin', 'event', 'item');
    }

    public function test_student_wise_screen_does_not_leak_chest_number(): void
    {
        $f = $this->fixture();

        $response = $this->actingAs($f['admin'])->get(
            route('school.kalotsav.reports.student-wise', ['tenantId' => $f['school']->id, 'event' => $f['event']->id]),
        );

        $response->assertOk();
        $response->assertDontSee(self::CHEST_NO);
    }

    /**
     * dompdf's compiled output doesn't preserve rendered text as a greppable substring
     * (confirmed empirically — a known string placed in the source HTML is not found in
     * the resulting PDF bytes), so unlike the other tests here this can't assert the
     * chest number is actually absent from the rendered page. What it CAN verify — and
     * did catch two real, pre-existing bugs — is that the export completes at all:
     * FestSchoolReportController::exportStudentWisePdf() calls
     * FestReportService::renderPdf()/brandingData() on an externally-constructed
     * instance, and both were 'private', so this endpoint 500'd for every school before
     * that visibility fix. The chest-number removal itself is verified by code review of
     * the stripped row data (stripChestNumbers()) and the 'showChestNo' blade guard.
     */
    public function test_student_wise_pdf_renders_successfully(): void
    {
        $f = $this->fixture();

        $response = $this->actingAs($f['admin'])->get(
            route('school.kalotsav.reports.student-wise.pdf', ['tenantId' => $f['school']->id, 'event' => $f['event']->id]),
        );

        $response->assertOk();
    }

    public function test_numbering_register_screen_does_not_leak_chest_number(): void
    {
        $f = $this->fixture();

        $response = $this->actingAs($f['admin'])->get(
            route('school.kalotsav.reports.numbering-register', ['tenantId' => $f['school']->id, 'event' => $f['event']->id]),
        );

        $response->assertOk();
        $response->assertDontSee(self::CHEST_NO);
    }

    public function test_numbering_register_export_does_not_leak_chest_number(): void
    {
        $f = $this->fixture();

        $response = $this->actingAs($f['admin'])->get(
            route('school.kalotsav.reports.numbering-register.export', ['tenantId' => $f['school']->id, 'event' => $f['event']->id]),
        );

        $response->assertOk();
        $this->assertStringNotContainsString(self::CHEST_NO, $response->streamedContent());
    }

    public function test_registration_register_screen_does_not_leak_chest_number(): void
    {
        $f = $this->fixture();

        $response = $this->actingAs($f['admin'])->get(
            route('school.kalotsav.reports.registration-register', ['tenantId' => $f['school']->id, 'event' => $f['event']->id]),
        );

        $response->assertOk();
        $response->assertDontSee(self::CHEST_NO);
    }

    public function test_registration_register_export_does_not_leak_chest_number(): void
    {
        $f = $this->fixture();

        $response = $this->actingAs($f['admin'])->get(
            route('school.kalotsav.reports.registration-register.export', ['tenantId' => $f['school']->id, 'event' => $f['event']->id]),
        );

        $response->assertOk();
        $this->assertStringNotContainsString((string) self::CHEST_NO, $response->streamedContent());
    }

    /** See test_student_wise_pdf_renders_successfully()'s docblock — same dompdf caveat. */
    public function test_registration_register_pdf_renders_successfully(): void
    {
        $f = $this->fixture();

        $response = $this->actingAs($f['admin'])->get(
            route('school.kalotsav.reports.registration-register.pdf', ['tenantId' => $f['school']->id, 'event' => $f['event']->id]),
        );

        $response->assertOk();
    }

    public function test_id_cards_json_does_not_leak_chest_number(): void
    {
        $f = $this->fixture();

        $response = $this->actingAs($f['admin'])->getJson(
            route('school.kalotsav.reports.id-cards.cards', [
                'tenantId' => $f['school']->id, 'event' => $f['event']->id, 'item_id' => $f['item']->id,
            ]),
        );

        $response->assertOk();
        $response->assertDontSee(self::CHEST_NO);
    }

    /** See test_student_wise_pdf_renders_successfully()'s docblock — same dompdf caveat. */
    public function test_admit_cards_pdf_renders_successfully(): void
    {
        $f = $this->fixture();

        $response = $this->actingAs($f['admin'])->get(
            route('school.kalotsav.reports.admit-cards', ['tenantId' => $f['school']->id, 'event' => $f['event']->id]),
        );

        $response->assertOk();
    }

    public function test_sahodaya_admin_student_wise_still_sees_chest_number(): void
    {
        $f = $this->fixture();
        $sahodayaAdmin = User::factory()->create(['tenant_id' => $f['sahodaya']->id]);
        $sahodayaAdmin->assignRole('sahodaya_admin');

        $response = $this->actingAs($sahodayaAdmin)->get(
            route('sahodaya.events.reports.student-wise', ['tenantId' => $f['sahodaya']->id, 'event' => $f['event']->id]),
        );

        $response->assertOk();
        $response->assertSee(self::CHEST_NO);
    }
}
