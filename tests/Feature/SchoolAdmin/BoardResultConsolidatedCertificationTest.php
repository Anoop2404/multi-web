<?php

namespace Tests\Feature\SchoolAdmin;

use App\Models\AcademicYearRecord;
use App\Models\BoardResult;
use App\Models\BoardResultCertificationPackage;
use App\Models\SahodayaProfile;
use App\Models\Tenant;
use App\Models\Topper;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Confirms the fast-track removal in BoardResultCertificationController::submit()
 * — the school must now generate, sign, and upload the consolidated report before
 * submitting to Sahodaya. The sibling test_full_school_certification_flow_via_http
 * in BoardResultCertificationControllerTest fails before reaching this code path
 * (its report-count assertion is stale — it expects 4 report types, current code
 * only produces 2 for Class X — that staleness predates this change), so this
 * test exercises the actual current 2-report path directly.
 */
class BoardResultConsolidatedCertificationTest extends TestCase
{
    use RefreshDatabase;

    private function makeSchool(): array
    {
        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'sahodaya',
            'name' => 'Test Sahodaya',
            'domain' => 'consolidated-http-'.Str::random(8).'.test',
            'is_active' => true,
        ]);

        SahodayaProfile::create([
            'tenant_id' => $sahodaya->id,
            'prefix' => 'TS',
            'student_data_mode' => 'counts_only',
        ]);

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

    private function makeBoardResultWithProof(Tenant $school, int $class = 10): BoardResult
    {
        Storage::disk('shared')->put("board-results/{$school->id}/proof.pdf", 'proof');

        $result = BoardResult::create([
            'tenant_id' => $school->id,
            'class' => $class,
            'examination_type' => BoardResult::examinationTypeForClass($class),
            'academic_year' => '2026-27',
            'total_appeared' => 10,
            'pass_count' => 10,
            'pass_percent' => 100.0,
            'distinctions' => 2,
            'first_class' => 3,
            'status' => BoardResult::STATUS_DRAFT,
            'result_pdf_path' => "board-results/{$school->id}/proof.pdf",
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

    private function signAndAcceptAllReports(string $base, User $principal, BoardResultCertificationPackage $package): void
    {
        foreach ($package->reports as $report) {
            $this->actingAs($principal)->post("{$base}/certification/reports/{$report->id}/generate")->assertRedirect();
            $file = UploadedFile::fake()->create('signed.pdf', 10, 'application/pdf');
            $this->actingAs($principal)->post("{$base}/certification/reports/{$report->id}/signed-pdf", ['signed_pdf' => $file])->assertRedirect();
            $this->actingAs($principal)->post("{$base}/certification/reports/{$report->id}/accept")->assertRedirect();
        }
    }

    public function test_submit_is_blocked_until_the_signed_consolidated_report_is_uploaded(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        Storage::fake('shared');
        ['school' => $school, 'principal' => $principal] = $this->makeSchool();
        $result = $this->makeBoardResultWithProof($school, 10);
        $base = "/school-admin/{$school->id}/board-results/{$result->id}";

        $this->actingAs($principal)->post("{$base}/request-leadership-review")->assertRedirect();
        $package = BoardResultCertificationPackage::where('board_result_id', $result->id)->firstOrFail();
        $this->assertCount(2, $package->reports, 'Class X currently produces exactly 2 required reports (overall toppers + full A1).');

        $this->signAndAcceptAllReports($base, $principal, $package);

        // Every individual report is signed and accepted, but the consolidated
        // report has not been generated/signed yet — submit must now 422, not
        // silently fast-track to school_certified. submit() no longer advances
        // the package at all (that auto-advance moved to generateConsolidated()),
        // so status is unchanged from before this call.
        $response = $this->actingAs($principal)->post("{$base}/certification/submit");
        $response->assertStatus(422);

        $package->refresh();
        $this->assertSame(BoardResultCertificationPackage::STATUS_AWAITING_REPORT_SIGNATURES, $package->status);
    }

    public function test_full_flow_through_the_consolidated_report_reaches_submitted(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        Storage::fake('shared');
        ['school' => $school, 'principal' => $principal] = $this->makeSchool();
        $result = $this->makeBoardResultWithProof($school, 10);
        $base = "/school-admin/{$school->id}/board-results/{$result->id}";

        $this->actingAs($principal)->post("{$base}/request-leadership-review")->assertRedirect();
        $package = BoardResultCertificationPackage::where('board_result_id', $result->id)->firstOrFail();
        $this->signAndAcceptAllReports($base, $principal, $package);

        // Generate consolidated PDF — auto-advances individual_reports_signed internally.
        $this->actingAs($principal)->post("{$base}/certification/consolidated/generate")->assertRedirect();
        $package->refresh();
        $this->assertSame(BoardResultCertificationPackage::STATUS_AWAITING_CONSOLIDATED_SIGNATURE, $package->status);
        $this->assertNotNull($package->generated_pdf_path);

        // Upload signed consolidated report with declarations.
        $signed = UploadedFile::fake()->create('consolidated-signed.pdf', 10, 'application/pdf');
        $this->actingAs($principal)->post("{$base}/certification/consolidated/signed-pdf", [
            'signed_pdf' => $signed,
            'declaration_figures_checked' => true,
            'declaration_details_correct' => true,
            'declaration_signature_seal' => true,
        ])->assertRedirect();

        $package->refresh();
        $this->assertSame(BoardResultCertificationPackage::STATUS_SCHOOL_CERTIFIED, $package->status);

        // Now submit succeeds.
        $this->actingAs($principal)->post("{$base}/certification/submit")->assertRedirect();
        $package->refresh();
        $result->refresh();
        $this->assertSame(BoardResultCertificationPackage::STATUS_SUBMITTED_TO_SAHODAYA, $package->status);
        $this->assertSame(BoardResult::STATUS_SUBMITTED, $result->status);
    }
}
