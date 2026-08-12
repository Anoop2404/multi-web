<?php

namespace App\Http\Controllers\SchoolAdmin;

use App\Models\BoardResult;
use App\Models\BoardResultCertificationPackage;
use App\Models\BoardResultCertificationReport;
use App\Models\User;
use App\Services\BoardResults\BoardResultCertificationPdfService;
use App\Services\BoardResults\BoardResultCertificationService;
use App\Services\BoardResults\BoardResultNotifier;
use App\Support\TenantStorage;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * School-side actions for generating, downloading, and signing the
 * individual + consolidated certification reports — plan §5.2, §5.3, §12.
 */
class BoardResultCertificationController extends SchoolAdminController
{
    public function generateReport(Request $request, string $tenantId, BoardResult $boardResult, BoardResultCertificationReport $report)
    {
        $this->assertReportInScope($boardResult, $report);
        $this->assertCanManage($request);
        abort_if($report->status === BoardResultCertificationReport::STATUS_SUPERSEDED, 410, 'This report has been superseded by a newer package version.');

        $service = app(BoardResultCertificationService::class);
        $package = $report->package;

        // Auto-advance: allow Principal/VP to click "Generate Report PDF" directly
        // without having to first click "Send for Leadership Review" separately.
        // Draft → request_leadership_review → begin_report_signatures (two hops).
        if ($package->status === BoardResultCertificationPackage::STATUS_DRAFT) {
            $service->requestLeadershipReview(
                $boardResult,
                $request->user()
            );
            $package->refresh();
        }

        if ($package->status === BoardResultCertificationPackage::STATUS_AWAITING_LEADERSHIP_REVIEW) {
            $service->beginReportSignatures($package, $request->user());
        }

        abort_unless(
            $package->fresh()->status === BoardResultCertificationPackage::STATUS_AWAITING_REPORT_SIGNATURES,
            422,
            'Reports can only be generated while the package is awaiting report signatures.'
        );

        $pdfService = app(BoardResultCertificationPdfService::class);
        $snapshot = $pdfService->assembleReportSnapshot($boardResult, $report->report_type, $report->stream_id);
        $rowCount = $this->rowCountFromSnapshot($report->report_type, $snapshot);

        $disk = TenantStorage::uploadDisk();
        $path = "board-results/{$this->school->id}/{$boardResult->id}/certification/reports/{$report->id}/report-v{$package->version}-".now()->timestamp.'.pdf';

        try {
            $service->generateReport($report, $snapshot, $path, $disk, $rowCount, $request->user());
        } catch (RuntimeException $e) {
            return back()->withErrors(['report' => $e->getMessage()]);
        }

        $pdf = $pdfService->renderReportPdf($report->fresh(), $boardResult, $this->school);
        TenantStorage::put($path, $pdf->output(), $disk);

        return back()->with('success', 'Report generated. Download, verify, sign/seal it, then upload the signed copy.');
    }

    public function downloadReportPdf(Request $request, string $tenantId, BoardResult $boardResult, BoardResultCertificationReport $report)
    {
        $this->assertReportInScope($boardResult, $report);
        abort_unless($report->generated_pdf_path, 404);

        return TenantStorage::downloadPrivate(
            $report->generated_pdf_path,
            $report->generated_pdf_disk,
            $request->boolean('preview') ? null : BoardResultCertificationReport::typeLabel($report->report_type).'.pdf'
        );
    }

    public function downloadSignedReportPdf(Request $request, string $tenantId, BoardResult $boardResult, BoardResultCertificationReport $report)
    {
        $this->assertReportInScope($boardResult, $report);
        abort_unless($report->signed_pdf_path, 404);

        return TenantStorage::downloadPrivate(
            $report->signed_pdf_path,
            $report->signed_pdf_disk,
            $request->boolean('preview') ? null : 'signed-'.BoardResultCertificationReport::typeLabel($report->report_type).'.pdf'
        );
    }

    public function uploadSignedReport(Request $request, string $tenantId, BoardResult $boardResult, BoardResultCertificationReport $report)
    {
        $this->assertReportInScope($boardResult, $report);
        $user = $this->assertCanSign($request);
        abort_unless(in_array($report->status, [
            BoardResultCertificationReport::STATUS_GENERATED,
            BoardResultCertificationReport::STATUS_CHANGES_REQUESTED,
        ], true), 422, 'Generate the report before uploading a signed copy.');

        $request->validate(['signed_pdf' => 'required|file|mimes:pdf|max:20480']);

        $file = $request->file('signed_pdf');
        $disk = TenantStorage::uploadDisk();
        $dir = "board-results/{$this->school->id}/{$boardResult->id}/certification/reports/{$report->id}/signed";
        $path = TenantStorage::storeUploadedFile($file, $dir, $disk);
        $hash = hash_file('sha256', $file->getRealPath());

        app(BoardResultCertificationService::class)->uploadSignedReport($report, $path, $disk, $hash, $user, $this->roleFor($user));

        return back()->with('success', 'Signed report uploaded. Review and accept it to finalize this category.');
    }

    public function acceptReport(Request $request, string $tenantId, BoardResult $boardResult, BoardResultCertificationReport $report)
    {
        $this->assertReportInScope($boardResult, $report);
        $user = $this->assertCanSign($request);

        try {
            app(BoardResultCertificationService::class)->acceptReport($report, $user);
        } catch (RuntimeException $e) {
            return back()->withErrors(['report' => $e->getMessage()]);
        }

        return back()->with('success', 'Report accepted as verified proof.');
    }

    public function returnReport(Request $request, string $tenantId, BoardResult $boardResult, BoardResultCertificationReport $report)
    {
        $this->assertReportInScope($boardResult, $report);
        $this->assertCanManage($request);

        $data = $request->validate(['reason' => 'required|string|max:1000']);

        app(BoardResultCertificationService::class)->returnReport($report, $request->user(), $data['reason']);

        try {
            app(\App\Services\BoardResults\BoardResultCertificationNotifier::class)->reportReturned($report->fresh());
        } catch (\Throwable) {
            // Notifications must never block workflow transitions.
        }

        return back()->with('success', 'Report returned for correction.');
    }

    public function generateConsolidated(Request $request, string $tenantId, BoardResult $boardResult)
    {
        $service = app(BoardResultCertificationService::class);
        $package = $this->activePackageOrFail($boardResult);
        $this->assertCanManage($request);

        if ($package->status === BoardResultCertificationPackage::STATUS_AWAITING_REPORT_SIGNATURES) {
            try {
                $service->markIndividualReportsSigned($package, $request->user());
            } catch (RuntimeException $e) {
                return back()->withErrors(['package' => $e->getMessage()]);
            }
        }
        abort_unless(
            $package->fresh()->status === BoardResultCertificationPackage::STATUS_INDIVIDUAL_REPORTS_SIGNED,
            422,
            'Every required individual report must be signed and accepted before generating the consolidated report.'
        );

        $pdfService = app(BoardResultCertificationPdfService::class);
        $snapshot = $pdfService->assembleConsolidatedSnapshot($package, $boardResult);

        $disk = TenantStorage::uploadDisk();
        $path = "board-results/{$this->school->id}/{$boardResult->id}/certification/packages/{$package->id}/consolidated-v{$package->version}-".now()->timestamp.'.pdf';

        $service->generateConsolidated($package, $snapshot, $path, $disk, $request->user());

        $pdf = $pdfService->renderConsolidatedPdf($package->fresh(), $boardResult, $this->school);
        TenantStorage::put($path, $pdf->output(), $disk);

        return back()->with('success', 'Consolidated certification PDF generated. Download, verify, sign/seal, and upload it.');
    }

    public function downloadConsolidatedPdf(Request $request, string $tenantId, BoardResult $boardResult)
    {
        $package = $this->activePackageOrFail($boardResult);
        abort_unless($package->generated_pdf_path, 404);

        return TenantStorage::downloadPrivate(
            $package->generated_pdf_path,
            $package->generated_pdf_disk,
            $request->boolean('preview') ? null : "certification-package-v{$package->version}.pdf"
        );
    }

    public function downloadSignedConsolidatedPdf(Request $request, string $tenantId, BoardResult $boardResult)
    {
        $package = $this->activePackageOrFail($boardResult);
        abort_unless($package->signed_pdf_path, 404);

        return TenantStorage::downloadPrivate(
            $package->signed_pdf_path,
            $package->signed_pdf_disk,
            $request->boolean('preview') ? null : "certification-package-v{$package->version}-signed.pdf"
        );
    }

    public function uploadSignedConsolidated(Request $request, string $tenantId, BoardResult $boardResult)
    {
        $package = $this->activePackageOrFail($boardResult);
        $user = $this->assertCanSign($request);

        abort_unless($package->status === BoardResultCertificationPackage::STATUS_AWAITING_CONSOLIDATED_SIGNATURE, 422, 'Generate the consolidated report before uploading a signed copy.');

        $request->validate([
            'signed_pdf' => 'required|file|mimes:pdf|max:20480',
            'declaration_figures_checked' => 'accepted',
            'declaration_details_correct' => 'accepted',
            'declaration_signature_seal' => 'accepted',
        ], [
            'declaration_figures_checked.accepted' => 'Confirm you have checked the figures against the official board result.',
            'declaration_details_correct.accepted' => 'Confirm the topper/subject-wise/Full A1 details are correct.',
            'declaration_signature_seal.accepted' => 'Confirm the uploaded document bears the authorized signature and school seal.',
        ]);

        $file = $request->file('signed_pdf');
        $disk = TenantStorage::uploadDisk();
        $dir = "board-results/{$this->school->id}/{$boardResult->id}/certification/packages/{$package->id}/signed";
        $path = TenantStorage::storeUploadedFile($file, $dir, $disk);
        $hash = hash_file('sha256', $file->getRealPath());

        app(BoardResultCertificationService::class)->uploadSignedConsolidated($package, $path, $disk, $hash, $user, $this->roleFor($user));

        return back()->with('success', 'Signed consolidated report uploaded. You can now submit the certified package to Sahodaya.');
    }

    public function submit(Request $request, string $tenantId, BoardResult $boardResult)
    {
        $package = $this->activePackageOrFail($boardResult);
        $user = $this->assertCanSign($request);
        $service = app(BoardResultCertificationService::class);

        // Auto-advance: if all individual reports are signed/accepted, move
        // straight to submission without requiring a separate consolidated PDF step.
        if ($package->status === BoardResultCertificationPackage::STATUS_AWAITING_REPORT_SIGNATURES) {
            try {
                $service->markIndividualReportsSigned($package, $user);
                $package->refresh();
            } catch (RuntimeException $e) {
                return back()->withErrors(['package' => $e->getMessage()]);
            }
        }

        // Allow submit from individual_reports_signed directly (skip consolidated PDF)
        if ($package->status === BoardResultCertificationPackage::STATUS_INDIVIDUAL_REPORTS_SIGNED) {
            // Fast-track: mark as school_certified without a consolidated PDF
            $package->forceFill(['status' => BoardResultCertificationPackage::STATUS_SCHOOL_CERTIFIED])->save();
            $package->refresh();
        }

        abort_unless(
            $package->status === BoardResultCertificationPackage::STATUS_SCHOOL_CERTIFIED,
            422,
            'All individual reports must be signed and accepted before submitting.'
        );

        $service->submitToSahodaya($package, $user);

        try {
            app(BoardResultNotifier::class)->notifySubmitted($boardResult->fresh(), $user);
            app(\App\Services\BoardResults\BoardResultCertificationNotifier::class)->submitted($package->fresh());
        } catch (\Throwable) {
            // Notifications must never block workflow transitions.
        }

        return redirect()
            ->route('school.board-results.principal-verification.dashboard', ['tenantId' => $this->school->id])
            ->with('success', 'Certified package submitted to Sahodaya for verification.');
    }

    // ------------------------------------------------------------------

    private function activePackageOrFail(BoardResult $boardResult): BoardResultCertificationPackage
    {
        abort_if($boardResult->tenant_id !== $this->school->id, 403);
        $package = app(BoardResultCertificationService::class)->activePackage($boardResult);
        abort_unless($package, 404, 'No certification package has been started for this result yet.');

        return $package;
    }

    private function assertReportInScope(BoardResult $boardResult, BoardResultCertificationReport $report): void
    {
        abort_if($boardResult->tenant_id !== $this->school->id, 403);
        abort_unless($report->package && (int) $report->package->board_result_id === (int) $boardResult->id, 404);
    }

    private function assertCanManage(Request $request): User
    {
        $user = $request->user();
        abort_unless(
            $user->isSuperAdmin() || $user->hasAnyRole(['school_admin', 'school_principal', 'school_vice_principal']),
            403,
            'You do not have access to Principal Verification for this school.'
        );

        return $user;
    }

    private function assertCanSign(Request $request): User
    {
        $user = $request->user();
        abort_unless(
            $user->isSuperAdmin() || $user->hasAnyRole(['school_admin', 'school_principal', 'school_vice_principal']),
            403,
            'Only the Principal, Vice Principal or School Admin may sign and submit certification documents.'
        );

        return $user;
    }

    private function roleFor(User $user): string
    {
        if ($user->hasRole('school_principal')) {
            return 'school_principal';
        }
        if ($user->hasRole('school_vice_principal')) {
            return 'school_vice_principal';
        }

        return 'superadmin';
    }

    /** @param  array<string, mixed>  $snapshot */
    private function rowCountFromSnapshot(string $type, array $snapshot): ?int
    {
        return match ($type) {
            BoardResultCertificationReport::TYPE_OVERALL_TOPPERS, BoardResultCertificationReport::TYPE_FULL_A1 => count($snapshot['rows'] ?? []),
            BoardResultCertificationReport::TYPE_SUBJECT_TOPPERS => collect($snapshot['subjects'] ?? [])->sum(fn ($s) => count($s['rows'] ?? [])),
            default => null,
        };
    }
}
