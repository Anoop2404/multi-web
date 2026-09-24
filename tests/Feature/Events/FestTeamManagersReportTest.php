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

    /**
     * The Students count only counts a registration that is 'approved' or 'submitted' —
     * not one still stuck earlier in the fee/proof workflow (proof_uploaded, pending_proof,
     * partial), and not a withdrawn one. Real production data (Ace Public School,
     * 2026-09-24) showed a withdrawn duplicate item registration; this covers that plus the
     * other non-final statuses the same way.
     */
    public function test_unique_student_count_only_counts_approved_or_submitted_registrations(): void
    {
        $sahodaya = Tenant::create([
            'type' => 'sahodaya', 'name' => 'Status Filter Sahodaya', 'subdomain' => 'status-filter-sahodaya-'.uniqid(),
        ]);

        $schoolTenant = Tenant::create([
            'type' => 'school', 'name' => 'Status Filter School', 'subdomain' => 'status-filter-school-'.uniqid(),
            'parent_id' => $sahodaya->id, 'school_prefix' => 'SFS',
        ]);
        $schoolClass = SchoolClass::create(['tenant_id' => $schoolTenant->id, 'name' => 'Class 10']);

        $event = FestEvent::create([
            'tenant_id' => $sahodaya->id, 'title' => 'Kalotsav 2027', 'event_type' => 'kalotsav',
            'fee_settings' => ['fee_model' => 'none'],
        ]);

        $items = collect(['approved', 'submitted', 'proof_uploaded', 'pending_proof', 'partial', 'withdrawn'])
            ->mapWithKeys(fn ($status) => [$status => FestEventItem::create([
                'event_id' => $event->id, 'title' => "Item {$status}", 'item_code' => strtoupper($status),
                'participant_type' => 'individual', 'is_enabled' => true,
            ])]);

        foreach ($items as $status => $item) {
            $student = Student::create([
                'tenant_id' => $schoolTenant->id, 'school_class_id' => $schoolClass->id,
                'name' => "Student {$status}", 'reg_no' => 'STU/27/'.substr(md5($status), 0, 4),
            ]);
            $reg = FestRegistration::create(['event_id' => $event->id, 'item_id' => $item->id, 'school_id' => $schoolTenant->id, 'status' => $status]);
            FestParticipant::create([
                'registration_id' => $reg->id, 'student_id' => $student->id,
                'participant_type' => 'student', 'participant_role' => 'performer', 'chest_no' => 1,
            ]);
        }

        $row = (new FestReportService($event))->teamManagersData()->first();

        // Only the 'approved' and 'submitted' students count -- 2, not 6.
        $this->assertSame(2, $row->unique_student_count);
    }

    /**
     * When a Chromium/Puppeteer converter is configured (production), the actual PDF
     * download must NOT also render the in-page title/branding block -- the converter's
     * own headerTemplate/footerTemplate (PdfChromeHeaderFooter) already repeats them on
     * every page. Rendering both produced a visibly doubled, overlapping header on the
     * downloaded PDF. The dompdf fallback and the on-screen preview never get a
     * Chromium header/footer at all, so they must still show the in-page one.
     */
    public function test_pdf_view_skips_its_own_title_only_for_an_actual_chromium_converted_download(): void
    {
        $event = (object) ['title' => 'View Render Kalotsav'];
        $schools = collect([(object) [
            'school_name' => 'View Render School', 'school_prefix' => 'VRS', 'unique_student_count' => 3,
            'manager_name_1' => 'Some Manager', 'manager_phone_1' => '9000000000', 'manager_role_1' => null,
            'manager_name_2' => null, 'manager_phone_2' => null,
        ]]);
        $base = ['event' => $event, 'schools' => $schools, 'orgName' => 'Test Sahodaya', 'logoSrc' => null];

        // dompdf fallback (isDomPdf=true) -- in-page title must show.
        $domPdfHtml = view('fest.reports.team-managers', [...$base, 'isDomPdf' => true, 'preview' => false])->render();
        $this->assertStringContainsString('<h2>School Team Managers</h2>', $domPdfHtml);
        $this->assertStringContainsString('Test Sahodaya', $domPdfHtml);

        // On-screen preview with the converter configured (isDomPdf=false) -- preview
        // never goes through the Chromium header/footer, so the in-page title must
        // still show even though isDomPdf says a converter is available.
        $previewHtml = view('fest.reports.team-managers', [...$base, 'isDomPdf' => false, 'preview' => true])->render();
        $this->assertStringContainsString('<h2>School Team Managers</h2>', $previewHtml);
        $this->assertStringContainsString('Test Sahodaya', $previewHtml);

        // The actual Chromium-converted download (isDomPdf=false, preview=false) --
        // the converter's own header already carries the title/branding, so the
        // in-page block must be skipped here specifically.
        $chromeHtml = view('fest.reports.team-managers', [...$base, 'isDomPdf' => false, 'preview' => false])->render();
        // The <title> tag (page metadata, not a visible heading) legitimately still
        // says "School Team Managers Report" -- check the actual in-page <h2> heading
        // specifically, not the substring anywhere in the document.
        $this->assertStringNotContainsString('<h2>School Team Managers</h2>', $chromeHtml);
        $this->assertStringNotContainsString('Test Sahodaya', $chromeHtml);
        // The table itself must still render regardless.
        $this->assertStringContainsString('VIEW RENDER SCHOOL', $chromeHtml);
    }

    /**
     * The registration sheet is a physical sign-in form for the registration desk --
     * same rows as the team managers report, but the student count is left blank for
     * the desk to fill in by hand (see teamManagersRegistrationSheetPdf()'s own
     * docblock) and a signature column is added.
     */
    public function test_registration_sheet_leaves_the_count_blank_and_adds_a_signature_column(): void
    {
        $sahodaya = Tenant::create([
            'type' => 'sahodaya', 'name' => 'Reg Sheet Sahodaya', 'subdomain' => 'reg-sheet-'.uniqid(),
        ]);
        $schoolTenant = Tenant::create([
            'type' => 'school', 'name' => 'Reg Sheet School', 'subdomain' => 'reg-sheet-school-'.uniqid(),
            'parent_id' => $sahodaya->id, 'school_prefix' => 'RSS',
        ]);
        $event = FestEvent::create([
            'tenant_id' => $sahodaya->id, 'title' => 'Reg Sheet Kalotsav', 'event_type' => 'kalotsav',
            'fee_settings' => ['fee_model' => 'none'],
        ]);
        FestRegistration::create([
            'tenant_id' => $schoolTenant->id, 'event_id' => $event->id, 'school_id' => $schoolTenant->id, 'status' => 'submitted',
        ]);
        FestSchoolTeamManager::create([
            'tenant_id' => $sahodaya->id, 'event_id' => $event->id, 'school_id' => $schoolTenant->id,
            'manager_name_1' => 'Reg Sheet Manager', 'manager_phone_1' => '9123456789',
        ]);

        $reportService = new FestReportService($event);
        $row = $reportService->teamManagersData()->first();
        $this->assertSame(0, $row->unique_student_count, 'No approved/submitted participants were registered for this fixture.');

        // export() with no 'download' param defaults to preview mode -- the plain HTML
        // response, not an actual PDF conversion.
        $response = $reportService->export('team-managers-registration-sheet', new Request());
        $html = $response->getContent();

        $this->assertStringContainsString('Reg Sheet Manager', $html);
        $this->assertStringContainsString('>Signature<', $html);
        $this->assertStringContainsString('blank-box', $html);
        // The count cell must stay empty, not render the system's own count value.
        $this->assertStringNotContainsString('count-badge', $html);
    }
}
