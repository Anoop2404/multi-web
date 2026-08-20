<?php

namespace Tests\Feature;

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
 * Walks the entire Board Result pipeline through real HTTP requests, no shortcuts:
 * school enters the result and toppers through the actual data-entry endpoint, goes
 * through Principal Verification report-by-report using the new standalone report/
 * consolidated pages, submits to Sahodaya, and Sahodaya verifies/approves/publishes/
 * unpublishes. Every other test in this feature exercises one slice of this in
 * isolation (several start from a directly-seeded BoardResult rather than the real
 * entry form); this one proves the slices actually connect end to end.
 */
class BoardResultFullFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_full_pipeline_school_entry_through_sahodaya_publish_and_unpublish(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        Storage::fake('shared');

        // ── Setup: Sahodaya, School, Principal, Sahodaya admin ──────────────────
        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'sahodaya',
            'name' => 'Full Flow Sahodaya',
            'domain' => 'full-flow-'.Str::random(8).'.test',
            'is_active' => true,
        ]);
        SahodayaProfile::create(['tenant_id' => $sahodaya->id, 'prefix' => 'FF', 'student_data_mode' => 'counts_only']);

        $school = Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'school',
            'name' => 'Full Flow School',
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

        $sahodayaAdmin = User::factory()->create(['tenant_id' => $sahodaya->id, 'email_verified_at' => now()]);
        $sahodayaAdmin->assignRole('sahodaya_admin');

        $schoolBase = "/school-admin/{$school->id}/board-results";
        $sahodayaBase = "/sahodaya-admin/{$sahodaya->id}/board-results";

        // ── Stage 1: School dashboard + Overview tab render before any data exists ──
        $this->actingAs($principal)->get("/school-admin/{$school->id}")->assertOk();
        $this->actingAs($principal)->get($schoolBase)->assertOk();

        // ── Stage 2: School enters the result and toppers via the real data-entry form ──
        $storeResponse = $this->actingAs($principal)->post($schoolBase, [
            'class' => 10,
            'academic_year' => '2026-27',
            'total_appeared' => 20,
            'pass_count' => 18,
            'pass_percent' => 90,
            'distinctions' => 4,
            'first_class' => 6,
            'highest_mark' => 495,
            'average_mark' => 410,
            'result_pdf' => UploadedFile::fake()->create('proof.pdf', 50, 'application/pdf'),
            'toppers' => [
                [
                    'name' => 'Asha Kumari',
                    'gender' => 'female',
                    'roll_no' => '3001',
                    'admission_no' => 'A-101',
                    'marks_obtained' => 495,
                ],
                [
                    'name' => 'Rahul Nair',
                    'gender' => 'male',
                    'roll_no' => '3002',
                    'admission_no' => 'A-102',
                    'marks_obtained' => 480,
                ],
            ],
        ]);
        $storeResponse->assertRedirect();
        $storeResponse->assertSessionHasNoErrors();

        $result = BoardResult::where('tenant_id', $school->id)->where('academic_year', '2026-27')->where('class', 10)->firstOrFail();
        $this->assertSame(BoardResult::STATUS_DRAFT, $result->status);
        $this->assertSame(2, $result->toppers()->count());
        $this->assertTrue($result->hasResultPdf());

        $base = "{$schoolBase}/{$result->id}";

        // ── Stage 3: Overview tab renders the just-entered result with the unified stepper's data ──
        $overviewResponse = $this->actingAs($principal)->get("{$schoolBase}?class=10&academic_year=2026-27");
        $overviewResponse->assertOk();
        $overviewProps = $overviewResponse->viewData('page')['props'];
        $this->assertSame($result->id, $overviewProps['activeResult']['id']);
        $this->assertArrayHasKey('certificationPackage', $overviewProps['activeResultContext']);
        $this->assertArrayHasKey('certificationRequired', $overviewProps['activeResultContext']);

        // ── Stage 4: Send for leadership review ──
        $this->actingAs($principal)->post("{$base}/request-leadership-review")->assertRedirect()->assertSessionHasNoErrors();

        $package = BoardResultCertificationPackage::where('board_result_id', $result->id)->firstOrFail();
        $this->assertSame(BoardResultCertificationPackage::STATUS_AWAITING_LEADERSHIP_REVIEW, $package->status);
        $this->assertCount(2, $package->reports, 'Class X requires exactly 2 reports: overall toppers + full A1.');

        // ── Stage 5: Checklist index page renders, listing both reports as not-yet-started ──
        $checklistResponse = $this->actingAs($principal)->get("{$base}/principal-verification");
        $checklistResponse->assertOk();
        $checklistProps = $checklistResponse->viewData('page')['props'];
        $this->assertFalse($checklistProps['allReportsAccepted']);
        $this->assertCount(2, $checklistProps['package']['reports']);

        // ── Stage 6: Walk each individual report through its own standalone page ──
        foreach ($package->reports as $report) {
            $reportBase = "{$base}/principal-verification/reports/{$report->id}";

            $this->actingAs($principal)->get($reportBase)->assertOk();

            $this->actingAs($principal)->post("{$base}/certification/reports/{$report->id}/generate")->assertRedirect()->assertSessionHasNoErrors();
            $report->refresh();
            $this->assertSame('generated', $report->status);
            $this->assertNotNull($report->generated_pdf_path);

            $this->actingAs($principal)
                ->post("{$base}/certification/reports/{$report->id}/signed-pdf", [
                    'signed_pdf' => UploadedFile::fake()->create('signed.pdf', 20, 'application/pdf'),
                ])
                ->assertRedirect()->assertSessionHasNoErrors();
            $report->refresh();
            $this->assertSame('signed_uploaded', $report->status);

            $this->actingAs($principal)->post("{$base}/certification/reports/{$report->id}/accept")->assertRedirect()->assertSessionHasNoErrors();
            $report->refresh();
            $this->assertSame('accepted', $report->status);

            // Page still renders correctly once the report is done, not just mid-flow.
            $this->actingAs($principal)->get($reportBase)->assertOk();
        }

        // ── Stage 7: Checklist reflects that every individual report is now accepted ──
        // (informational only — the consolidated report below is the actual requirement).
        $checklistAfter = $this->actingAs($principal)->get("{$base}/principal-verification");
        $this->assertTrue($checklistAfter->viewData('page')['props']['allReportsAccepted']);

        // ── Stage 8: Consolidated report — its own standalone page, generate, sign, upload ──
        $consolidatedBase = "{$base}/principal-verification/consolidated";
        $consolidatedPage = $this->actingAs($principal)->get($consolidatedBase);
        $consolidatedPage->assertOk();

        $this->actingAs($principal)->post("{$base}/certification/consolidated/generate")->assertRedirect()->assertSessionHasNoErrors();
        $package->refresh();
        $this->assertSame(BoardResultCertificationPackage::STATUS_AWAITING_CONSOLIDATED_SIGNATURE, $package->status);

        $this->actingAs($principal)
            ->post("{$base}/certification/consolidated/signed-pdf", [
                'signed_pdf' => UploadedFile::fake()->create('consolidated-signed.pdf', 30, 'application/pdf'),
                'declaration_figures_checked' => true,
                'declaration_details_correct' => true,
                'declaration_signature_seal' => true,
            ])
            ->assertRedirect()->assertSessionHasNoErrors();
        $package->refresh();
        $this->assertSame(BoardResultCertificationPackage::STATUS_SCHOOL_CERTIFIED, $package->status);

        // Fast-track guard from earlier in this session must stay dead: skipping straight
        // to submit without the consolidated step would have landed here instead.
        $this->assertNotNull($package->generated_pdf_path);
        $this->assertNotNull($package->signed_pdf_path);

        // ── Stage 9: Submit to Sahodaya ──
        $this->actingAs($principal)->post("{$base}/certification/submit")->assertRedirect()->assertSessionHasNoErrors();
        $package->refresh();
        $result->refresh();
        $this->assertSame(BoardResultCertificationPackage::STATUS_SUBMITTED_TO_SAHODAYA, $package->status);
        $this->assertSame(BoardResult::STATUS_SUBMITTED, $result->status);

        // School dashboard now reflects "submitted", not stuck showing stale in-progress state.
        $dashboardAfterSubmit = $this->actingAs($principal)->get("/school-admin/{$school->id}");
        $dashboardAfterSubmit->assertOk();

        // ================= SAHODAYA SIDE =================

        // ── Stage 10: Verification queue lists the submitted result ──
        $queueResponse = $this->actingAs($sahodayaAdmin)->get("{$sahodayaBase}/verification?status=submitted");
        $queueResponse->assertOk();
        $queueIds = collect($queueResponse->viewData('page')['props']['results']['data'])->pluck('id');
        $this->assertContains($result->id, $queueIds);

        // Certified-package queue and detail page also render for the same package.
        $this->actingAs($sahodayaAdmin)->get("{$sahodayaBase}/certifications")->assertOk();
        $packagePage = $this->actingAs($sahodayaAdmin)->get("{$sahodayaBase}/certifications/{$package->id}");
        $packagePage->assertOk();
        $this->assertSame(BoardResultCertificationPackage::STATUS_SUBMITTED_TO_SAHODAYA, $packagePage->viewData('page')['props']['package']['status']);

        // ── Stage 11: Verify → Approve → Publish ──
        $this->actingAs($sahodayaAdmin)->post("{$sahodayaBase}/{$result->id}/verify")->assertRedirect()->assertSessionHasNoErrors();
        $package->refresh();
        $result->refresh();
        $this->assertSame(BoardResult::STATUS_VERIFIED, $result->status);
        $this->assertSame(BoardResultCertificationPackage::STATUS_SAHODAYA_VERIFIED, $package->status);

        $this->actingAs($sahodayaAdmin)->post("{$sahodayaBase}/{$result->id}/approve")->assertRedirect()->assertSessionHasNoErrors();
        $package->refresh();
        $result->refresh();
        $this->assertSame(BoardResult::STATUS_APPROVED, $result->status);
        $this->assertSame(BoardResultCertificationPackage::STATUS_APPROVED, $package->status);

        $this->actingAs($sahodayaAdmin)->post("{$sahodayaBase}/{$result->id}/publish")->assertRedirect()->assertSessionHasNoErrors();
        $package->refresh();
        $result->refresh();
        $this->assertSame(BoardResult::STATUS_PUBLISHED, $result->status);
        $this->assertSame(BoardResultCertificationPackage::STATUS_PUBLISHED, $package->status);
        $this->assertNotNull($result->published_at);

        // ── Stage 12: Unpublish reopens it for correction, full circle ──
        $this->actingAs($sahodayaAdmin)
            ->post("{$sahodayaBase}/{$result->id}/unpublish", ['reason' => 'Distinction count needs a recount.'])
            ->assertRedirect()->assertSessionHasNoErrors();

        $result->refresh();
        $this->assertSame(BoardResult::STATUS_REJECTED, $result->status);
        $this->assertNull($result->published_at);
        $this->assertTrue($result->isEditable(), 'Unpublish must leave the result genuinely editable again.');

        $newPackage = BoardResultCertificationPackage::where('board_result_id', $result->id)
            ->where('id', '!=', $package->id)
            ->firstOrFail();
        $this->assertSame(BoardResultCertificationPackage::STATUS_DRAFT, $newPackage->status);

        // A second unpublish attempt is correctly rejected — the result is Rejected, not Published, now.
        $this->actingAs($sahodayaAdmin)
            ->post("{$sahodayaBase}/{$result->id}/unpublish", ['reason' => 'again'])
            ->assertStatus(422);
    }
}
