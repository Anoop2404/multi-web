<?php

namespace Tests\Feature\Services\BoardResults;

use App\Models\AcademicYearRecord;
use App\Models\BoardResult;
use App\Models\BoardResultCertificationPackage;
use App\Models\BoardResultCertificationReport;
use App\Models\ExamStream;
use App\Models\SahodayaProfile;
use App\Models\Tenant;
use App\Models\Topper;
use App\Models\User;
use App\Services\BoardResults\BoardResultCertificationService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class BoardResultCertificationServiceTest extends TestCase
{
    use RefreshDatabase;

    private BoardResultCertificationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->service = app(BoardResultCertificationService::class);
    }

    /** @return array{sahodaya: Tenant, school: Tenant, principal: User, vp: User} */
    private function makeSchool(): array
    {
        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'sahodaya',
            'name' => 'Test Sahodaya',
            'domain' => 'cert-'.Str::random(8).'.test',
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

        AcademicYearRecord::create([
            'label' => '2026-27',
            'start_date' => '2026-06-01',
            'end_date' => '2027-05-31',
            'status' => 'active',
        ]);

        $principal = User::factory()->create(['tenant_id' => $school->id, 'email_verified_at' => now()]);
        $principal->assignRole('school_principal');

        $vp = User::factory()->create(['tenant_id' => $school->id, 'email_verified_at' => now()]);
        $vp->assignRole('school_vice_principal');

        return compact('sahodaya', 'school', 'principal', 'vp');
    }

    private function makeBoardResult(Tenant $school, int $class = 10): BoardResult
    {
        return BoardResult::create([
            'tenant_id' => $school->id,
            'class' => $class,
            'examination_type' => BoardResult::examinationTypeForClass($class),
            'academic_year' => '2026-27',
            'total_appeared' => 50,
            'pass_count' => 48,
            'pass_percent' => 96.0,
            'distinctions' => 10,
            'first_class' => 20,
            'status' => BoardResult::STATUS_DRAFT,
        ]);
    }

    public function test_request_leadership_review_creates_package_and_pending_reports_for_class_x(): void
    {
        ['school' => $school, 'principal' => $principal] = $this->makeSchool();
        $result = $this->makeBoardResult($school, 10);

        $package = $this->service->requestLeadershipReview($result, $principal);

        $this->assertSame(BoardResultCertificationPackage::STATUS_AWAITING_LEADERSHIP_REVIEW, $package->status);
        $this->assertSame(1, $package->version);
        $this->assertNotNull($package->data_hash);

        $types = $package->reports()->pluck('report_type')->sort()->values()->all();
        sort($types);
        $this->assertSame(['full_a1', 'overall_toppers', 'subject_toppers', 'summary'], $types);
        $this->assertTrue($package->reports()->where('status', BoardResultCertificationReport::STATUS_PENDING)->count() === 4);
    }

    public function test_class_xii_report_definitions_are_generated_per_configured_stream(): void
    {
        ['sahodaya' => $sahodaya, 'school' => $school, 'principal' => $principal] = $this->makeSchool();
        $result = $this->makeBoardResult($school, 12);

        $science = ExamStream::create(['sahodaya_id' => $sahodaya->id, 'code' => 'SCI', 'label' => 'Science', 'examination_type' => 'AISSCE', 'sort_order' => 1, 'is_active' => true]);
        $commerce = ExamStream::create(['sahodaya_id' => $sahodaya->id, 'code' => 'COM', 'label' => 'Commerce', 'examination_type' => 'AISSCE', 'sort_order' => 2, 'is_active' => true]);

        Topper::create(['board_result_id' => $result->id, 'tenant_id' => $school->id, 'entry_type' => Topper::ENTRY_OVERALL, 'name' => 'A', 'stream_id' => $science->id, 'percentage' => 98]);
        Topper::create(['board_result_id' => $result->id, 'tenant_id' => $school->id, 'entry_type' => Topper::ENTRY_OVERALL, 'name' => 'B', 'stream_id' => $commerce->id, 'percentage' => 95]);

        $package = $this->service->requestLeadershipReview($result, $principal);

        $overallStreamIds = $package->reports()
            ->where('report_type', BoardResultCertificationReport::TYPE_OVERALL_TOPPERS)
            ->pluck('stream_id')
            ->sort()
            ->values()
            ->all();

        $this->assertSame([$commerce->id, $science->id], collect($overallStreamIds)->sort()->values()->all());
        // Subject toppers and summary stay single combined reports (stream_id null) for Class XII.
        $this->assertSame(1, $package->reports()->where('report_type', BoardResultCertificationReport::TYPE_SUBJECT_TOPPERS)->whereNull('stream_id')->count());
    }

    /**
     * Individual per-type reports are optional reference documents — moving on to
     * the consolidated report must succeed even when none of them have been
     * touched (see BoardResultPrincipalVerificationPagesTest for the HTTP-level
     * equivalent of this same guarantee).
     */
    public function test_individual_reports_are_optional_before_consolidated_step(): void
    {
        ['school' => $school, 'principal' => $principal] = $this->makeSchool();
        $result = $this->makeBoardResult($school, 10);

        $package = $this->service->requestLeadershipReview($result, $principal);
        $this->service->beginReportSignatures($package, $principal);
        $package->refresh();
        $this->assertSame(BoardResultCertificationPackage::STATUS_AWAITING_REPORT_SIGNATURES, $package->status);
        $this->assertSame(0, $package->reports()->where('status', BoardResultCertificationReport::STATUS_ACCEPTED)->count());

        $this->service->markIndividualReportsSigned($package, $principal);
        $package->refresh();
        $this->assertSame(BoardResultCertificationPackage::STATUS_INDIVIDUAL_REPORTS_SIGNED, $package->status);
    }

    public function test_full_lifecycle_succeeds_once_every_report_is_signed_and_accepted(): void
    {
        ['school' => $school, 'principal' => $principal] = $this->makeSchool();
        $result = $this->makeBoardResult($school, 10);

        $package = $this->service->requestLeadershipReview($result, $principal);
        $this->service->beginReportSignatures($package, $principal);
        $package->refresh();

        foreach ($package->reports as $report) {
            $this->service->generateReport($report, ['x' => 1], "reports/{$report->id}.pdf", 'shared', 5, $principal);
            $this->service->uploadSignedReport($report, "signed/{$report->id}.pdf", 'shared', hash('sha256', (string) $report->id), $principal, 'school_principal');
            $this->service->acceptReport($report, $principal);
        }

        $this->service->markIndividualReportsSigned($package, $principal);
        $package->refresh();
        $this->assertSame(BoardResultCertificationPackage::STATUS_INDIVIDUAL_REPORTS_SIGNED, $package->status);

        $this->service->generateConsolidated($package, ['all' => true], 'consolidated.pdf', 'shared', $principal);
        $package->refresh();
        $this->assertSame(BoardResultCertificationPackage::STATUS_AWAITING_CONSOLIDATED_SIGNATURE, $package->status);
        $this->assertNotNull($package->data_hash);

        $this->service->uploadSignedConsolidated($package, 'consolidated-signed.pdf', 'shared', hash('sha256', 'consolidated'), $principal, 'school_principal');
        $package->refresh();
        $this->assertSame(BoardResultCertificationPackage::STATUS_SCHOOL_CERTIFIED, $package->status);

        $this->service->submitToSahodaya($package, $principal);
        $package->refresh();
        $result->refresh();
        $this->assertSame(BoardResultCertificationPackage::STATUS_SUBMITTED_TO_SAHODAYA, $package->status);
        $this->assertSame(BoardResult::STATUS_SUBMITTED, $result->status);
        $this->assertSame($principal->id, $package->submitted_by_user_id);
    }

    public function test_invalid_transition_throws(): void
    {
        ['school' => $school, 'principal' => $principal] = $this->makeSchool();
        $result = $this->makeBoardResult($school, 10);
        $package = $this->service->getOrCreatePackage($result);

        $this->expectException(RuntimeException::class);
        // Cannot jump straight from draft to school_certified.
        $this->service->uploadSignedConsolidated($package, 'x.pdf', 'shared', 'hash', $principal, 'school_principal');
    }

    public function test_sahodaya_return_invalidates_package_and_spawns_next_version(): void
    {
        ['school' => $school, 'principal' => $principal] = $this->makeSchool();
        $result = $this->makeBoardResult($school, 10);

        $package = $this->service->requestLeadershipReview($result, $principal);
        $this->service->beginReportSignatures($package, $principal);
        $package->refresh();

        foreach ($package->reports as $report) {
            $this->service->generateReport($report, ['x' => 1], "r{$report->id}.pdf", 'shared', 1, $principal);
            $this->service->uploadSignedReport($report, "s{$report->id}.pdf", 'shared', 'h', $principal, 'school_principal');
            $this->service->acceptReport($report, $principal);
        }
        $this->service->markIndividualReportsSigned($package, $principal);
        $package->refresh();
        $this->service->generateConsolidated($package, ['all' => true], 'c.pdf', 'shared', $principal);
        $package->refresh();
        $this->service->uploadSignedConsolidated($package, 'cs.pdf', 'shared', 'h2', $principal, 'school_principal');
        $package->refresh();
        $this->service->submitToSahodaya($package, $principal);
        $package->refresh();

        $sahodayaAdmin = User::factory()->create(['tenant_id' => null, 'email_verified_at' => now()]);

        $next = $this->service->sahodayaReturn($package, $sahodayaAdmin, 'Pass percentage mismatch.');

        $package->refresh();
        $this->assertSame(BoardResultCertificationPackage::STATUS_SUPERSEDED, $package->status);
        $this->assertSame(2, $next->version);
        $this->assertSame(BoardResultCertificationPackage::STATUS_DRAFT, $next->status);

        // Old reports are superseded, not silently carried forward.
        $this->assertSame(
            0,
            BoardResultCertificationReport::where('certification_package_id', $package->id)
                ->where('status', '!=', BoardResultCertificationReport::STATUS_SUPERSEDED)
                ->count()
        );
    }

    public function test_data_change_before_any_report_generated_does_not_bump_version(): void
    {
        ['school' => $school, 'principal' => $principal] = $this->makeSchool();
        $result = $this->makeBoardResult($school, 10);
        $package = $this->service->getOrCreatePackage($result);

        $same = $this->service->invalidateForDataChange($result, $principal, 'Typo fix before review started.');

        $this->assertSame($package->id, $same->id);
        $this->assertSame(1, $same->version);
    }

    public function test_data_change_after_reports_generated_invalidates_and_bumps_version(): void
    {
        ['school' => $school, 'principal' => $principal] = $this->makeSchool();
        $result = $this->makeBoardResult($school, 10);

        $package = $this->service->requestLeadershipReview($result, $principal);
        $this->service->beginReportSignatures($package, $principal);
        $package->refresh();

        $summary = $package->reports()->where('report_type', BoardResultCertificationReport::TYPE_SUMMARY)->first();
        $this->service->generateReport($summary, ['x' => 1], 'r.pdf', 'shared', 1, $principal);

        $next = $this->service->invalidateForDataChange($result, $principal, 'Corrected pass count after board recheck.');

        $package->refresh();
        $this->assertSame(BoardResultCertificationPackage::STATUS_SUPERSEDED, $package->status);
        $this->assertSame(2, $next->version);
    }
}
