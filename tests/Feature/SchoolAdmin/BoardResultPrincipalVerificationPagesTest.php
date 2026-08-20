<?php

namespace Tests\Feature\SchoolAdmin;

use App\Models\AcademicYearRecord;
use App\Models\BoardResult;
use App\Models\BoardResultCertificationPackage;
use App\Models\SahodayaProfile;
use App\Models\Tenant;
use App\Models\Topper;
use App\Models\User;
use App\Services\BoardResults\BoardResultCertificationService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The individual-report and consolidated-report cards that used to live inline on the
 * Principal Verification review page now each have their own standalone page (Review.vue
 * is an index/checklist linking out to them). These tests cover the two new routes.
 */
class BoardResultPrincipalVerificationPagesTest extends TestCase
{
    use RefreshDatabase;

    private function makeSchool(): array
    {
        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'sahodaya',
            'name' => 'Test Sahodaya',
            'domain' => 'pv-pages-'.Str::random(8).'.test',
            'is_active' => true,
        ]);

        SahodayaProfile::create(['tenant_id' => $sahodaya->id, 'prefix' => 'TS', 'student_data_mode' => 'counts_only']);

        $school = Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'school',
            'name' => 'Test School',
            'parent_id' => $sahodaya->id,
            'membership_status' => 'approved',
            'is_active' => true,
        ]);

        AcademicYearRecord::firstOrCreate(
            ['label' => '2026-27'],
            ['start_date' => '2026-06-01', 'end_date' => '2027-05-31', 'status' => 'active']
        );

        $principal = User::factory()->create(['tenant_id' => $school->id, 'email_verified_at' => now()]);
        $principal->assignRole('school_principal');

        return compact('sahodaya', 'school', 'principal');
    }

    private function makeBoardResultWithProof(Tenant $school, int $class = 10, string $academicYear = '2026-27'): BoardResult
    {
        Storage::disk('shared')->put("board-results/{$school->id}/proof-{$class}-{$academicYear}.pdf", 'proof');

        $result = BoardResult::create([
            'tenant_id' => $school->id,
            'class' => $class,
            'examination_type' => BoardResult::examinationTypeForClass($class),
            'academic_year' => $academicYear,
            'total_appeared' => 10,
            'pass_count' => 10,
            'pass_percent' => 100.0,
            'status' => BoardResult::STATUS_DRAFT,
            'result_pdf_path' => "board-results/{$school->id}/proof-{$class}-{$academicYear}.pdf",
            'result_pdf_disk' => 'shared',
        ]);

        Topper::create([
            'board_result_id' => $result->id,
            'tenant_id' => $school->id,
            'entry_type' => Topper::ENTRY_OVERALL,
            'name' => 'Top Student',
            'gender' => 'female',
            'roll_no' => '1001',
            'marks_obtained' => 480,
            'total_marks' => 500,
            'percentage' => 96,
            'rank' => 1,
        ]);

        return $result;
    }

    public function test_individual_report_page_renders_with_the_correct_report(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        Storage::fake('shared');
        ['school' => $school, 'principal' => $principal] = $this->makeSchool();
        $result = $this->makeBoardResultWithProof($school, 10);
        $base = "/school-admin/{$school->id}/board-results/{$result->id}";

        $this->actingAs($principal)->post("{$base}/request-leadership-review")->assertRedirect();
        $package = BoardResultCertificationPackage::where('board_result_id', $result->id)->firstOrFail();
        $report = $package->reports->first();

        $response = $this->actingAs($principal)->get("{$base}/principal-verification/reports/{$report->id}");
        $response->assertOk();

        $props = $response->viewData('page')['props'];
        $this->assertSame($report->id, $props['report']['id']);
        $this->assertSame($result->id, $props['boardResult']['id']);
        $this->assertTrue($props['canSign']);
    }

    public function test_individual_report_page_404s_for_a_report_from_another_board_result(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        Storage::fake('shared');
        ['school' => $school, 'principal' => $principal] = $this->makeSchool();
        AcademicYearRecord::firstOrCreate(
            ['label' => '2025-26'],
            ['start_date' => '2025-06-01', 'end_date' => '2026-05-31', 'status' => 'active']
        );
        $resultA = $this->makeBoardResultWithProof($school, 10, '2026-27');
        $resultB = $this->makeBoardResultWithProof($school, 10, '2025-26');
        $baseA = "/school-admin/{$school->id}/board-results/{$resultA->id}";
        $baseB = "/school-admin/{$school->id}/board-results/{$resultB->id}";

        $this->actingAs($principal)->post("{$baseA}/request-leadership-review")->assertRedirect();
        $this->actingAs($principal)->post("{$baseB}/request-leadership-review")->assertRedirect();

        $packageB = BoardResultCertificationPackage::where('board_result_id', $resultB->id)->firstOrFail();
        $reportFromB = $packageB->reports->first();

        // Result A's URL, but a report id that belongs to result B's package.
        $response = $this->actingAs($principal)->get("{$baseA}/principal-verification/reports/{$reportFromB->id}");
        $response->assertNotFound();
    }

    /**
     * The consolidated report is the only required document — individual per-type
     * reports are optional reference documents. A Principal must be able to go
     * straight to the consolidated report from a completely fresh package without
     * ever touching an individual report.
     */
    public function test_consolidated_report_can_be_generated_without_touching_any_individual_report(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        Storage::fake('shared');
        ['school' => $school, 'principal' => $principal] = $this->makeSchool();
        $result = $this->makeBoardResultWithProof($school, 10);
        $base = "/school-admin/{$school->id}/board-results/{$result->id}";

        // Viewing the consolidated page for the first time creates the draft package —
        // matches real navigation (Principal lands here without visiting the checklist).
        $this->actingAs($principal)->get("{$base}/principal-verification/consolidated")->assertOk();

        $package = BoardResultCertificationPackage::where('board_result_id', $result->id)->firstOrFail();
        $this->assertSame(BoardResultCertificationPackage::STATUS_DRAFT, $package->status);
        $this->assertSame(0, $package->reports()->where('status', 'accepted')->count(), 'No individual report should be accepted yet.');

        $this->actingAs($principal)->post("{$base}/certification/consolidated/generate")->assertRedirect()->assertSessionHasNoErrors();

        $package->refresh();
        $this->assertSame(BoardResultCertificationPackage::STATUS_AWAITING_CONSOLIDATED_SIGNATURE, $package->status);
        $this->assertNotNull($package->generated_pdf_path);

        $signed = UploadedFile::fake()->create('consolidated-signed.pdf', 10, 'application/pdf');
        $this->actingAs($principal)
            ->post("{$base}/certification/consolidated/signed-pdf", [
                'signed_pdf' => $signed,
                'declaration_figures_checked' => true,
                'declaration_details_correct' => true,
                'declaration_signature_seal' => true,
            ])
            ->assertRedirect()->assertSessionHasNoErrors();

        $package->refresh();
        $this->assertSame(BoardResultCertificationPackage::STATUS_SCHOOL_CERTIFIED, $package->status);

        $this->actingAs($principal)->post("{$base}/certification/submit")->assertRedirect()->assertSessionHasNoErrors();

        $package->refresh();
        $this->assertSame(BoardResultCertificationPackage::STATUS_SUBMITTED_TO_SAHODAYA, $package->status);
    }

    /** Doing the individual reports first must still work — they're optional, not disabled. */
    public function test_consolidated_report_can_still_be_generated_after_individual_reports_are_signed(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        Storage::fake('shared');
        ['school' => $school, 'principal' => $principal] = $this->makeSchool();
        $result = $this->makeBoardResultWithProof($school, 10);
        $base = "/school-admin/{$school->id}/board-results/{$result->id}";

        $this->actingAs($principal)->post("{$base}/request-leadership-review")->assertRedirect();
        $package = BoardResultCertificationPackage::where('board_result_id', $result->id)->firstOrFail();

        $service = app(BoardResultCertificationService::class);
        foreach ($package->reports as $report) {
            $service->generateReport($report, ['x' => 1], "r{$report->id}.pdf", 'shared', 1, $principal);
            $service->uploadSignedReport($report, "s{$report->id}.pdf", 'shared', 'h', $principal, 'school_principal');
            $service->acceptReport($report, $principal);
        }

        $this->actingAs($principal)->get("{$base}/principal-verification/consolidated")->assertOk();
        $this->actingAs($principal)->post("{$base}/certification/consolidated/generate")->assertRedirect()->assertSessionHasNoErrors();

        $package->refresh();
        $this->assertSame(BoardResultCertificationPackage::STATUS_AWAITING_CONSOLIDATED_SIGNATURE, $package->status);
    }

    public function test_cross_school_access_is_denied_for_both_new_pages(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        Storage::fake('shared');
        ['school' => $schoolA, 'principal' => $principalA] = $this->makeSchool();
        ['school' => $schoolB] = $this->makeSchool();
        $resultB = $this->makeBoardResultWithProof($schoolB, 10);

        $this->actingAs($principalA)
            ->get("/school-admin/{$schoolA->id}/board-results/{$resultB->id}/principal-verification/consolidated")
            ->assertForbidden();
    }
}
