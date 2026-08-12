<?php

namespace App\Http\Controllers\SchoolAdmin;

use App\Models\BoardResult;
use App\Models\BoardResultCertificationPackage;
use App\Models\BoardResultCertificationReport;
use App\Services\BoardResults\BoardResultAcademicYearService;
use App\Services\BoardResults\BoardResultCertificationService;
use App\Services\BoardResults\BoardResultCertificationValidator;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Dashboard + entry point for the Principal Verification workflow —
 * docs/BOARD_RESULTS_PRINCIPAL_VERIFICATION_PLAN.md §5.1, §9.
 */
class BoardResultLeadershipReviewController extends SchoolAdminController
{
    public function dashboard(Request $request)
    {
        $academicYear = $request->string('academic_year')->toString() ?: null;
        $class = $request->filled('class') ? $request->integer('class') : null;
        $status = $request->string('status')->toString() ?: null;

        $yearService = app(BoardResultAcademicYearService::class);
        $sahodayaId = (string) ($this->school->parent_id ?: $this->school->id);
        $academicYearOptions = $yearService->activeOrPopulatedYearOptions($sahodayaId);

        if (! $academicYear) {
            $configuredOpenYear = collect($academicYearOptions)
                ->first(fn (array $year) => ($year['entry_configured'] ?? false) && ($year['entry_status'] ?? '') === 'open');
            $openYear = $configuredOpenYear ?? collect($academicYearOptions)->firstWhere('entry_status', 'open');
            $academicYear = $openYear['label'] ?? ((date('Y') - 1).'-'.substr((string) date('Y'), 2));
        }

        $results = BoardResult::where('tenant_id', $this->school->id)
            ->where('academic_year', $academicYear)
            ->when($class, fn ($q) => $q->where('class', $class))
            ->with(['certificationPackages.reports'])
            ->get();

        $cards = $results->map(function (BoardResult $result) {
            $package = $result->activeCertificationPackage();
            $reports = $package?->reports->where('status', '!=', BoardResultCertificationReport::STATUS_SUPERSEDED) ?? collect();

            return [
                'board_result_id' => $result->id,
                'class' => $result->class,
                'examination_type' => $result->examination_type,
                'academic_year' => $result->academic_year,
                'package' => $package ? [
                    'id' => $package->id,
                    'version' => $package->version,
                    'status' => $package->status,
                    'status_label' => BoardResultCertificationPackage::statusLabels()[$package->status] ?? $package->status,
                    'signed_count' => $reports->where('status', BoardResultCertificationReport::STATUS_ACCEPTED)->count(),
                    'required_count' => $reports->count(),
                    'submitted_at' => optional($package->submitted_at)->toIso8601String(),
                    'updated_at' => optional($package->updated_at)->toIso8601String(),
                ] : null,
                'primary_action' => $this->primaryActionFor($result, $package),
            ];
        })->values();

        return $this->inertia('School/BoardResults/PrincipalVerification/Dashboard', [
            'cards' => $cards,
            'academicYear' => $academicYear,
            'academicYearOptions' => $academicYearOptions,
            'selectedClass' => $class,
            'filters' => ['status' => $status],
        ]);
    }

    public function show(Request $request, string $tenantId, BoardResult $boardResult)
    {
        abort_if($boardResult->tenant_id !== $this->school->id, 403);

        $service = app(BoardResultCertificationService::class);
        $package = $service->getOrCreatePackage($boardResult);

        if (in_array($package->status, [BoardResultCertificationPackage::STATUS_DRAFT, BoardResultCertificationPackage::STATUS_LEADERSHIP_CHANGES_REQUESTED], true)) {
            $service->syncReportRecords($package);
        }

        $package->load(['reports.stream', 'reports.signedBy', 'signedBy', 'submittedBy']);

        return $this->inertia('School/BoardResults/PrincipalVerification/Review', [
            'boardResult' => $boardResult,
            'package' => $package,
            'canSign' => $this->userCanSign($request),
            'validationErrors' => $package->status === BoardResultCertificationPackage::STATUS_DRAFT
                ? app(BoardResultCertificationValidator::class)->errorsBeforeLeadershipReview($boardResult)
                : [],
            'allReportsAccepted' => $service->allRequiredReportsAccepted($package),
        ]);
    }

    public function requestReview(Request $request, string $tenantId, BoardResult $boardResult)
    {
        abort_if($boardResult->tenant_id !== $this->school->id, 403);

        $errors = app(BoardResultCertificationValidator::class)->errorsBeforeLeadershipReview($boardResult);
        if ($errors !== []) {
            throw ValidationException::withMessages(['result' => $errors]);
        }

        $package = app(BoardResultCertificationService::class)->requestLeadershipReview($boardResult, $request->user());

        try {
            app(\App\Services\BoardResults\BoardResultCertificationNotifier::class)->reviewRequested($package);
        } catch (\Throwable) {
            // Notifications must never block workflow transitions.
        }

        return redirect()
            ->route('school.board-results.principal-verification.show', ['tenantId' => $this->school->id, 'boardResult' => $boardResult->id])
            ->with('success', "Sent for leadership review. Package v{$package->version} is ready for the Principal/Vice Principal.");
    }

    private function primaryActionFor(BoardResult $result, ?BoardResultCertificationPackage $package): string
    {
        if (! $package || $package->status === BoardResultCertificationPackage::STATUS_DRAFT) {
            return 'Send for Review';
        }
        if ($package->isSubmittedToSahodaya()) {
            return 'View Submission';
        }

        return 'Continue Review';
    }

    private function userCanSign(Request $request): bool
    {
        $user = $request->user();

        return $user->isSuperAdmin() || $user->hasAnyRole(['school_admin', 'school_principal', 'school_vice_principal']);
    }
}
