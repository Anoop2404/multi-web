<?php

namespace Tests\Feature\SahodayaAdmin;

use App\Models\AcademicYearRecord;
use App\Models\BoardResult;
use App\Models\BoardResultCertificationPackage;
use App\Models\BoardResultCertificationReport;
use App\Models\SahodayaProfile;
use App\Models\Tenant;
use App\Models\Topper;
use App\Models\User;
use App\Services\BoardResults\BoardResultCertificationService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Verifies that BoardResultVerificationController's verify/approve/reject/publish actions
 * (1) refuse to act on a BoardResult whose certification package hasn't completed school
 * certification, and (2) keep the certification package status in sync with BoardResult
 * status once they do act — plan §9 ("must not bypass school certification").
 */
class BoardResultCertificationSyncTest extends TestCase
{
    use RefreshDatabase;

    private function makeContext(): array
    {
        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'sahodaya',
            'name' => 'Test Sahodaya',
            'domain' => 'cert-sync-'.Str::random(8).'.test',
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

        AcademicYearRecord::create(['label' => '2026-27', 'start_date' => '2026-06-01', 'end_date' => '2027-05-31', 'status' => 'active']);

        $principal = User::factory()->create(['tenant_id' => $school->id, 'email_verified_at' => now()]);
        $principal->assignRole('school_principal');

        $sahodayaAdmin = User::factory()->create(['tenant_id' => $sahodaya->id, 'email_verified_at' => now()]);
        $sahodayaAdmin->assignRole('sahodaya_admin');

        $result = BoardResult::create([
            'tenant_id' => $school->id,
            'class' => 10,
            'examination_type' => BoardResult::EXAM_AISSE,
            'academic_year' => '2026-27',
            'total_appeared' => 5,
            'pass_count' => 5,
            'pass_percent' => 100,
            'result_pdf_path' => 'proof.pdf',
            'result_pdf_disk' => 'shared',
            'status' => BoardResult::STATUS_DRAFT,
        ]);

        return compact('sahodaya', 'school', 'principal', 'sahodayaAdmin', 'result');
    }

    /** Drives the package all the way to submitted_to_sahodaya via the service directly (HTTP flow is covered elsewhere). */
    private function certifyAndSubmit(BoardResult $result, User $principal): BoardResultCertificationPackage
    {
        $service = app(BoardResultCertificationService::class);
        $package = $service->requestLeadershipReview($result, $principal);
        $service->beginReportSignatures($package, $principal);
        $package->refresh();

        foreach ($package->reports as $report) {
            $service->generateReport($report, ['x' => 1], "r{$report->id}.pdf", 'shared', 1, $principal);
            $service->uploadSignedReport($report, "s{$report->id}.pdf", 'shared', 'h', $principal, 'school_principal');
            $service->acceptReport($report, $principal);
        }
        $service->markIndividualReportsSigned($package, $principal);
        $package->refresh();
        $service->generateConsolidated($package, ['all' => true], 'c.pdf', 'shared', $principal);
        $package->refresh();
        $service->uploadSignedConsolidated($package, 'cs.pdf', 'shared', 'h2', $principal, 'school_principal');
        $package->refresh();
        $service->submitToSahodaya($package, $principal);

        return $package->fresh();
    }

    public function test_verify_is_blocked_until_school_certification_is_complete(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        ['sahodaya' => $sahodaya, 'school' => $school, 'principal' => $principal, 'sahodayaAdmin' => $sahodayaAdmin, 'result' => $result] = $this->makeContext();

        // Start certification but don't finish it — package stays short of submitted_to_sahodaya.
        app(BoardResultCertificationService::class)->requestLeadershipReview($result, $principal);
        $result->update(['status' => BoardResult::STATUS_SUBMITTED]); // simulate someone forcing BoardResult.status directly

        $response = $this->actingAs($sahodayaAdmin)
            ->post("/sahodaya-admin/{$sahodaya->id}/board-results/{$result->id}/verify");

        $response->assertStatus(422);
    }

    public function test_verify_approve_publish_keep_package_status_in_sync(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        ['sahodaya' => $sahodaya, 'school' => $school, 'principal' => $principal, 'sahodayaAdmin' => $sahodayaAdmin, 'result' => $result] = $this->makeContext();

        $package = $this->certifyAndSubmit($result, $principal);
        $this->assertSame(BoardResultCertificationPackage::STATUS_SUBMITTED_TO_SAHODAYA, $package->status);

        $this->actingAs($sahodayaAdmin)
            ->post("/sahodaya-admin/{$sahodaya->id}/board-results/{$result->id}/verify")
            ->assertRedirect();

        $package->refresh();
        $result->refresh();
        $this->assertSame(BoardResult::STATUS_VERIFIED, $result->status);
        $this->assertSame(BoardResultCertificationPackage::STATUS_SAHODAYA_VERIFIED, $package->status);

        $this->actingAs($sahodayaAdmin)
            ->post("/sahodaya-admin/{$sahodaya->id}/board-results/{$result->id}/approve")
            ->assertRedirect();

        $package->refresh();
        $result->refresh();
        $this->assertSame(BoardResult::STATUS_APPROVED, $result->status);
        $this->assertSame(BoardResultCertificationPackage::STATUS_APPROVED, $package->status);

        $this->actingAs($sahodayaAdmin)
            ->post("/sahodaya-admin/{$sahodaya->id}/board-results/{$result->id}/publish")
            ->assertRedirect();

        $package->refresh();
        $result->refresh();
        $this->assertSame(BoardResult::STATUS_PUBLISHED, $result->status);
        $this->assertSame(BoardResultCertificationPackage::STATUS_PUBLISHED, $package->status);
    }

    public function test_reject_after_verification_supersedes_package_and_spawns_new_version(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        ['sahodaya' => $sahodaya, 'school' => $school, 'principal' => $principal, 'sahodayaAdmin' => $sahodayaAdmin, 'result' => $result] = $this->makeContext();

        $package = $this->certifyAndSubmit($result, $principal);

        $this->actingAs($sahodayaAdmin)
            ->post("/sahodaya-admin/{$sahodaya->id}/board-results/{$result->id}/verify")
            ->assertRedirect();

        $this->actingAs($sahodayaAdmin)
            ->post("/sahodaya-admin/{$sahodaya->id}/board-results/{$result->id}/reject", [
                'rejection_reason' => 'Pass percentage mismatch with board portal.',
            ])
            ->assertRedirect();

        $package->refresh();
        $result->refresh();
        $this->assertSame(BoardResult::STATUS_REJECTED, $result->status);
        $this->assertSame(BoardResultCertificationPackage::STATUS_SUPERSEDED, $package->status);

        $newPackage = BoardResultCertificationPackage::where('board_result_id', $result->id)
            ->where('id', '!=', $package->id)
            ->first();
        $this->assertNotNull($newPackage);
        $this->assertSame(2, $newPackage->version);
        $this->assertSame(BoardResultCertificationPackage::STATUS_DRAFT, $newPackage->status);
    }

    public function test_unpublish_reopens_result_for_correction_and_keeps_package_in_sync(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        ['sahodaya' => $sahodaya, 'school' => $school, 'principal' => $principal, 'sahodayaAdmin' => $sahodayaAdmin, 'result' => $result] = $this->makeContext();

        $topper = Topper::create([
            'board_result_id' => $result->id,
            'tenant_id' => $school->id,
            'entry_type' => Topper::ENTRY_OVERALL,
            'name' => 'Certificate Holder',
            'gender' => 'male',
            'roll_no' => '2002',
            'marks_obtained' => 470,
            'total_marks' => 500,
            'percentage' => 94,
            'rank' => 1,
        ]);

        $package = $this->certifyAndSubmit($result, $principal);
        $this->actingAs($sahodayaAdmin)->post("/sahodaya-admin/{$sahodaya->id}/board-results/{$result->id}/verify")->assertRedirect();
        $this->actingAs($sahodayaAdmin)->post("/sahodaya-admin/{$sahodaya->id}/board-results/{$result->id}/approve")->assertRedirect();
        $this->actingAs($sahodayaAdmin)->post("/sahodaya-admin/{$sahodaya->id}/board-results/{$result->id}/publish")->assertRedirect();

        $result->refresh();
        $package->refresh();
        $this->assertSame(BoardResult::STATUS_PUBLISHED, $result->status);
        $this->assertSame(BoardResultCertificationPackage::STATUS_PUBLISHED, $package->status);

        // Certificate issuance depends on a configured certificate template, which this
        // minimal fixture doesn't set up — the assertion below only cares that whatever
        // certificate state existed at publish time is unchanged by unpublish, not that
        // issuance itself succeeded (that's the publish pipeline's concern, not this one's).
        $certificateCountBeforeUnpublish = \App\Models\Certificate::where('entity_type', 'topper')->where('entity_id', $topper->id)->count();

        $this->actingAs($sahodayaAdmin)
            ->post("/sahodaya-admin/{$sahodaya->id}/board-results/{$result->id}/unpublish", [
                'reason' => 'Pass count was entered incorrectly.',
            ])
            ->assertRedirect();

        $result->refresh();
        $package->refresh();

        // BoardResult reopens as Rejected (the only editable-again status besides draft),
        // not a bespoke "approved but still locked" state.
        $this->assertSame(BoardResult::STATUS_REJECTED, $result->status);
        $this->assertNull($result->published_at);
        $this->assertNull($result->verified_by);
        $this->assertNull($result->approved_by);
        $this->assertStringContainsString('Pass count was entered incorrectly.', $result->rejection_reason);
        $this->assertTrue($result->isEditable(), 'The whole point of unpublish is that the result becomes editable again.');
        $history = collect($result->correction_history);
        $this->assertSame('unpublished', $history->last()['action'] ?? null);

        // The published package is superseded, not force-mutated in place, and a fresh
        // draft version exists — same shape as an ordinary Sahodaya return-for-correction.
        $this->assertSame(BoardResultCertificationPackage::STATUS_SUPERSEDED, $package->status);
        $newPackage = BoardResultCertificationPackage::where('board_result_id', $result->id)
            ->where('id', '!=', $package->id)
            ->first();
        $this->assertNotNull($newPackage);
        $this->assertSame(BoardResultCertificationPackage::STATUS_DRAFT, $newPackage->status);

        // Deliberately narrow scope: unpublish does not claw back an already-issued
        // certificate or delete topper data (matches FestResultsController::unpublish()'s
        // precedent — revocation is a separate, larger feature).
        $this->assertSame(
            $certificateCountBeforeUnpublish,
            \App\Models\Certificate::where('entity_type', 'topper')->where('entity_id', $topper->id)->count()
        );
        $this->assertNotNull(Topper::find($topper->id));

        // Idempotency / state-machine guard: a second unpublish attempt must 422, not
        // silently no-op or throw an unrelated error.
        $this->actingAs($sahodayaAdmin)
            ->post("/sahodaya-admin/{$sahodaya->id}/board-results/{$result->id}/unpublish", ['reason' => 'again'])
            ->assertStatus(422);
    }

    public function test_unpublish_is_rejected_when_result_is_not_published(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        ['sahodaya' => $sahodaya, 'sahodayaAdmin' => $sahodayaAdmin, 'principal' => $principal, 'result' => $result] = $this->makeContext();

        $this->certifyAndSubmit($result, $principal);
        $this->actingAs($sahodayaAdmin)->post("/sahodaya-admin/{$sahodaya->id}/board-results/{$result->id}/verify")->assertRedirect();
        $this->actingAs($sahodayaAdmin)->post("/sahodaya-admin/{$sahodaya->id}/board-results/{$result->id}/approve")->assertRedirect();

        // Approved, not yet published.
        $response = $this->actingAs($sahodayaAdmin)
            ->post("/sahodaya-admin/{$sahodaya->id}/board-results/{$result->id}/unpublish", ['reason' => 'too early']);

        $response->assertStatus(422);
        $result->refresh();
        $this->assertSame(BoardResult::STATUS_APPROVED, $result->status);
    }
}
