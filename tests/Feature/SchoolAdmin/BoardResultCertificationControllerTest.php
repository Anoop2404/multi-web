<?php

namespace Tests\Feature\SchoolAdmin;

use App\Models\AcademicYearRecord;
use App\Models\BoardResult;
use App\Models\BoardResultCertificationPackage;
use App\Models\BoardResultCertificationReport;
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

class BoardResultCertificationControllerTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{sahodaya: Tenant, school: Tenant, principal: User, admin: User} */
    private function makeSchool(): array
    {
        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'sahodaya',
            'name' => 'Test Sahodaya',
            'domain' => 'cert-http-'.Str::random(8).'.test',
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
            [
                'start_date' => '2026-06-01',
                'end_date' => '2027-05-31',
                'status' => 'active',
            ]
        );

        $principal = User::factory()->create(['tenant_id' => $school->id, 'email_verified_at' => now()]);
        $principal->assignRole('school_principal');

        $admin = User::factory()->create(['tenant_id' => $school->id, 'email_verified_at' => now()]);
        $admin->assignRole('school_admin');

        return compact('sahodaya', 'school', 'principal', 'admin');
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

    public function test_full_school_certification_flow_via_http(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        Storage::fake('shared');
        ['school' => $school, 'principal' => $principal] = $this->makeSchool();
        $result = $this->makeBoardResultWithProof($school, 10);

        $base = "/school-admin/{$school->id}/board-results/{$result->id}";

        // 1. Request leadership review.
        $this->actingAs($principal)
            ->post("{$base}/request-leadership-review")
            ->assertRedirect();

        $package = BoardResultCertificationPackage::where('board_result_id', $result->id)->firstOrFail();
        $this->assertSame(BoardResultCertificationPackage::STATUS_AWAITING_LEADERSHIP_REVIEW, $package->status);
        $this->assertCount(4, $package->reports); // summary, overall, subject, full_a1 for Class X

        // 2. Generate + sign + accept every required report.
        foreach ($package->reports as $report) {
            $this->actingAs($principal)
                ->post("{$base}/certification/reports/{$report->id}/generate")
                ->assertRedirect();

            $report->refresh();
            $this->assertSame(BoardResultCertificationReport::STATUS_GENERATED, $report->status);
            $this->assertNotNull($report->generated_pdf_path);

            $file = UploadedFile::fake()->create('signed.pdf', 10, 'application/pdf');
            $this->actingAs($principal)
                ->post("{$base}/certification/reports/{$report->id}/signed-pdf", ['signed_pdf' => $file])
                ->assertRedirect();

            $report->refresh();
            $this->assertSame(BoardResultCertificationReport::STATUS_SIGNED_UPLOADED, $report->status);

            $this->actingAs($principal)
                ->post("{$base}/certification/reports/{$report->id}/accept")
                ->assertRedirect();

            $report->refresh();
            $this->assertSame(BoardResultCertificationReport::STATUS_ACCEPTED, $report->status);
        }

        // 3. Generate consolidated PDF (auto-transitions awaiting_report_signatures -> individual_reports_signed).
        $this->actingAs($principal)
            ->post("{$base}/certification/consolidated/generate")
            ->assertRedirect();

        $package->refresh();
        $this->assertSame(BoardResultCertificationPackage::STATUS_AWAITING_CONSOLIDATED_SIGNATURE, $package->status);
        $this->assertNotNull($package->generated_pdf_path);

        // 4. Upload signed consolidated report with declarations.
        $signedConsolidated = UploadedFile::fake()->create('consolidated-signed.pdf', 10, 'application/pdf');
        $this->actingAs($principal)
            ->post("{$base}/certification/consolidated/signed-pdf", [
                'signed_pdf' => $signedConsolidated,
                'declaration_figures_checked' => true,
                'declaration_details_correct' => true,
                'declaration_signature_seal' => true,
            ])
            ->assertRedirect();

        $package->refresh();
        $this->assertSame(BoardResultCertificationPackage::STATUS_SCHOOL_CERTIFIED, $package->status);

        // 5. Submit to Sahodaya.
        $this->actingAs($principal)
            ->post("{$base}/certification/submit")
            ->assertRedirect();

        $package->refresh();
        $result->refresh();
        $this->assertSame(BoardResultCertificationPackage::STATUS_SUBMITTED_TO_SAHODAYA, $package->status);
        $this->assertSame(BoardResult::STATUS_SUBMITTED, $result->status);

        // 6. Direct legacy submit is now blocked because certification has started — the
        // package being submitted_to_sahodaya locks BoardResult::isEditable() itself, so
        // this correctly 422s before ever reaching the certification-package guard.
        $response = $this->actingAs($principal)->post("{$base}/submit");
        $response->assertStatus(422);
    }

    public function test_school_admin_cannot_sign_only_principal_or_vice_principal_can(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        Storage::fake('shared');
        ['school' => $school, 'principal' => $principal, 'admin' => $admin] = $this->makeSchool();
        $result = $this->makeBoardResultWithProof($school, 10);

        $base = "/school-admin/{$school->id}/board-results/{$result->id}";

        $this->actingAs($principal)->post("{$base}/request-leadership-review")->assertRedirect();
        $package = BoardResultCertificationPackage::where('board_result_id', $result->id)->firstOrFail();
        $report = $package->reports()->first();

        $this->actingAs($admin)
            ->post("{$base}/certification/reports/{$report->id}/generate")
            ->assertRedirect();

        $report->refresh();
        $file = UploadedFile::fake()->create('signed.pdf', 10, 'application/pdf');

        // school_admin (not principal/VP) may not upload a signed report.
        $this->actingAs($admin)
            ->post("{$base}/certification/reports/{$report->id}/signed-pdf", ['signed_pdf' => $file])
            ->assertForbidden();
    }

    public function test_cross_school_access_is_denied(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        Storage::fake('shared');
        ['school' => $schoolA, 'principal' => $principalA] = $this->makeSchool();
        ['school' => $schoolB] = $this->makeSchool();
        $resultB = $this->makeBoardResultWithProof($schoolB, 10);

        // Principal of school A tries to act on school B's result via school A's tenant URL —
        // the underlying BoardResult belongs to a different tenant, so this must be denied.
        $response = $this->actingAs($principalA)
            ->post("/school-admin/{$schoolA->id}/board-results/{$resultB->id}/request-leadership-review");

        $response->assertForbidden();
    }
}
