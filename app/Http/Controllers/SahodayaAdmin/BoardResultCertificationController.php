<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Models\BoardResult;
use App\Models\BoardResultCertificationPackage;
use App\Models\BoardResultCertificationReport;
use App\Models\Tenant;
use App\Support\TenantStorage;
use Illuminate\Http\Request;

/**
 * Sahodaya-side "School-Certified Result Packages" queue — plan §4, §5.3 (Sahodaya side),
 * §10, §12. Verify/return/approve/publish actions themselves live on the existing
 * BoardResultVerificationController (kept as the single source of truth for
 * BoardResult.status) — see the certification-package sync added there. This
 * controller is the read-focused surface that shows the full signed-proof
 * checklist and PDF previews the old verification screen doesn't have.
 */
class BoardResultCertificationController extends SahodayaAdminController
{
    public function index(Request $request)
    {
        $status = $request->string('status')->toString() ?: 'submitted_to_sahodaya';
        $class = $request->filled('class') ? $request->integer('class') : null;

        $schoolIds = Tenant::query()
            ->where('parent_id', $this->sahodaya->id)
            ->where('type', 'school')
            ->pluck('id');

        $packages = BoardResultCertificationPackage::query()
            ->whereIn('tenant_id', $schoolIds)
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->when($class, fn ($q) => $q->where('class', $class))
            ->with(['boardResult', 'reports' => fn ($q) => $q->where('status', '!=', BoardResultCertificationReport::STATUS_SUPERSEDED)])
            ->orderByDesc('submitted_at')
            ->orderByDesc('updated_at')
            ->paginate(25)
            ->withQueryString();

        $schoolNames = Tenant::whereIn('id', $packages->pluck('tenant_id')->unique())->pluck('name', 'id');

        $packages->getCollection()->transform(function (BoardResultCertificationPackage $package) {
            $package->setAttribute('signed_count', $package->reports->where('status', BoardResultCertificationReport::STATUS_ACCEPTED)->count());
            $package->setAttribute('required_count', $package->reports->count());

            return $package;
        });

        return $this->inertia('Sahodaya/BoardResults/Certifications/Index', [
            'packages' => $packages,
            'schoolNames' => $schoolNames,
            'filters' => ['status' => $status, 'class' => $class],
            'statusOptions' => BoardResultCertificationPackage::statusLabels(),
            'selectedClass' => $class,
        ]);
    }

    public function show(Request $request, string $tenantId, BoardResultCertificationPackage $package)
    {
        $this->assertInScope($package);

        $package->load([
            'boardResult',
            'reports' => fn ($q) => $q->where('status', '!=', BoardResultCertificationReport::STATUS_SUPERSEDED),
            'reports.stream',
            'reports.signedBy',
            'signedBy',
            'submittedBy',
            'returnedBy',
        ]);

        $school = Tenant::find($package->tenant_id);

        // Prior superseded versions, for audit context.
        $history = BoardResultCertificationPackage::where('board_result_id', $package->board_result_id)
            ->where('id', '!=', $package->id)
            ->orderByDesc('version')
            ->get(['id', 'version', 'status', 'superseded_at', 'return_reason', 'submitted_at']);

        return $this->inertia('Sahodaya/BoardResults/Certifications/Show', [
            'package' => $package,
            'school' => $school,
            'history' => $history,
        ]);
    }

    public function downloadReportPdf(Request $request, string $tenantId, BoardResultCertificationPackage $package, BoardResultCertificationReport $report)
    {
        $this->assertInScope($package);
        abort_unless((int) $report->certification_package_id === (int) $package->id, 404);
        $signed = $request->boolean('signed');
        $path = $signed ? $report->signed_pdf_path : $report->generated_pdf_path;
        $disk = $signed ? $report->signed_pdf_disk : $report->generated_pdf_disk;
        abort_unless($path, 404);

        return TenantStorage::downloadPrivate($path, $disk, $request->boolean('preview') ? null : basename($path));
    }

    public function downloadConsolidatedPdf(Request $request, string $tenantId, BoardResultCertificationPackage $package)
    {
        $this->assertInScope($package);
        $signed = $request->boolean('signed');
        $path = $signed ? $package->signed_pdf_path : $package->generated_pdf_path;
        $disk = $signed ? $package->signed_pdf_disk : $package->generated_pdf_disk;
        abort_unless($path, 404);

        return TenantStorage::downloadPrivate($path, $disk, $request->boolean('preview') ? null : basename($path));
    }

    /** School Certification Status Report — plan §10 (one row per school/class/year). */
    public function statusReport(Request $request)
    {
        $academicYear = $request->string('academic_year')->toString() ?: null;

        $schoolIds = Tenant::query()
            ->where('parent_id', $this->sahodaya->id)
            ->where('type', 'school')
            ->pluck('id');

        $results = BoardResult::query()
            ->whereIn('tenant_id', $schoolIds)
            ->when($academicYear, fn ($q) => $q->where('academic_year', $academicYear))
            ->with(['certificationPackages' => fn ($q) => $q->where('status', '!=', BoardResultCertificationPackage::STATUS_SUPERSEDED)])
            ->get();

        $schoolNames = Tenant::whereIn('id', $results->pluck('tenant_id')->unique())->pluck('name', 'id');

        $rows = $results->map(function (BoardResult $result) use ($schoolNames) {
            $package = $result->certificationPackages->first();
            $reports = $package?->reports ?? collect();

            return [
                'school' => $schoolNames[$result->tenant_id] ?? $result->tenant_id,
                'class' => $result->class,
                'examination_type' => $result->examination_type,
                'academic_year' => $result->academic_year,
                'package_status' => $package?->status ?? 'not_started',
                'required_reports' => $reports->count(),
                'signed_reports' => $reports->where('status', BoardResultCertificationReport::STATUS_ACCEPTED)->count(),
                'consolidated_signed' => (bool) $package?->signed_pdf_path,
                'submitted_at' => optional($package?->submitted_at)->toIso8601String(),
                'signer' => $package?->signedBy?->name,
            ];
        })->values();

        return $this->inertia('Sahodaya/BoardResults/Certifications/StatusReport', [
            'rows' => $rows,
            'academicYear' => $academicYear,
        ]);
    }

    private function assertInScope(BoardResultCertificationPackage $package): void
    {
        $school = Tenant::find($package->tenant_id);
        abort_unless($school && $school->parent_id === $this->sahodaya->id, 404);
    }
}
