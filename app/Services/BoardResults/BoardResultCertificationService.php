<?php

namespace App\Services\BoardResults;

use App\Models\BoardResult;
use App\Models\BoardResultCertificationPackage;
use App\Models\BoardResultCertificationReport;
use App\Models\Topper;
use App\Models\User;
use App\Services\Audit\DataChangeLogger;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Owns the certification-package/report state machine described in
 * docs/BOARD_RESULTS_PRINCIPAL_VERIFICATION_PLAN.md.
 *
 * This service is the *only* place that should mutate certification package
 * or report status. Controllers must call through here so every transition
 * is validated and audited.
 */
class BoardResultCertificationService
{
    public function __construct(private DataChangeLogger $auditLogger) {}

    // ------------------------------------------------------------------
    // Package lookup / creation
    // ------------------------------------------------------------------

    public function activePackage(BoardResult $boardResult): ?BoardResultCertificationPackage
    {
        return $boardResult->activeCertificationPackage();
    }

    /**
     * Returns the package that new work should target: reuses an existing
     * draft/changes-requested package, otherwise reuses an in-flight active
     * package as-is, otherwise starts a brand-new version.
     */
    public function getOrCreatePackage(BoardResult $boardResult): BoardResultCertificationPackage
    {
        $active = $this->activePackage($boardResult);

        if ($active !== null) {
            return $active;
        }

        $nextVersion = (int) $boardResult->certificationPackages()->max('version') + 1;

        $package = BoardResultCertificationPackage::create([
            'board_result_id' => $boardResult->id,
            'tenant_id' => $boardResult->tenant_id,
            'academic_year' => $boardResult->academic_year,
            'class' => $boardResult->class,
            'version' => $nextVersion,
            'status' => BoardResultCertificationPackage::STATUS_DRAFT,
        ]);

        $this->auditLogger->event(
            'certification_package_created',
            "Certification package v{$package->version} created for {$boardResult->examination_type} class {$boardResult->class} ({$boardResult->academic_year}).",
            $boardResult->tenant_id,
            'board_result_certification',
            $package,
        );

        return $package;
    }

    // ------------------------------------------------------------------
    // Required report definitions
    // ------------------------------------------------------------------

    /**
     * @return list<array{report_type: string, stream_id: int|null, label: string}>
     */
    public function requiredReportDefinitions(BoardResult $boardResult): array
    {
        $defs = [
            ['report_type' => BoardResultCertificationReport::TYPE_SUMMARY, 'stream_id' => null, 'label' => 'Result Summary & Proof'],
        ];

        if ((int) $boardResult->class !== 12) {
            $defs[] = ['report_type' => BoardResultCertificationReport::TYPE_OVERALL_TOPPERS, 'stream_id' => null, 'label' => 'School Overall Toppers'];
            $defs[] = ['report_type' => BoardResultCertificationReport::TYPE_SUBJECT_TOPPERS, 'stream_id' => null, 'label' => 'Subject-wise Toppers'];
            $defs[] = ['report_type' => BoardResultCertificationReport::TYPE_FULL_A1, 'stream_id' => null, 'label' => 'Full A1 Achievers'];

            return $defs;
        }

        // Class XII: subject-wise toppers stays a single combined report;
        // overall/full-A1 toppers are per configured stream.
        $defs[] = ['report_type' => BoardResultCertificationReport::TYPE_SUBJECT_TOPPERS, 'stream_id' => null, 'label' => 'Subject-wise Toppers'];

        $streams = $boardResult->toppers()
            ->whereIn('entry_type', [Topper::ENTRY_OVERALL, Topper::ENTRY_FULL_A1])
            ->whereNotNull('stream_id')
            ->with('examStream')
            ->get()
            ->pluck('examStream')
            ->filter()
            ->unique('id')
            ->sortBy('sort_order');

        foreach ($streams as $stream) {
            $defs[] = [
                'report_type' => BoardResultCertificationReport::TYPE_OVERALL_TOPPERS,
                'stream_id' => $stream->id,
                'label' => "School Topper — {$stream->label}",
            ];
        }

        foreach ($streams as $stream) {
            $defs[] = [
                'report_type' => BoardResultCertificationReport::TYPE_FULL_A1,
                'stream_id' => $stream->id,
                'label' => "Full A1 Achievers — {$stream->label}",
            ];
        }

        return $defs;
    }

    /**
     * Idempotently ensures a pending report row exists for every currently
     * required report definition. Never demotes/removes reports that are
     * already generated/signed/accepted, even if the definition set shrinks
     * (e.g. a stream configuration changed) — such rows simply stop being
     * counted as required once superseded elsewhere.
     */
    public function syncReportRecords(BoardResultCertificationPackage $package): void
    {
        $boardResult = $package->boardResult ?? BoardResult::findOrFail($package->board_result_id);

        foreach ($this->requiredReportDefinitions($boardResult) as $def) {
            BoardResultCertificationReport::firstOrCreate(
                [
                    'certification_package_id' => $package->id,
                    'report_type' => $def['report_type'],
                    'stream_id' => $def['stream_id'],
                ],
                [
                    'tenant_id' => $package->tenant_id,
                    'status' => BoardResultCertificationReport::STATUS_PENDING,
                ]
            );
        }
    }

    // ------------------------------------------------------------------
    // Package-level transitions
    // ------------------------------------------------------------------

    public function requestLeadershipReview(BoardResult $boardResult, User $actor): BoardResultCertificationPackage
    {
        $package = $this->getOrCreatePackage($boardResult);

        if (! in_array($package->status, [BoardResultCertificationPackage::STATUS_DRAFT], true)) {
            throw new RuntimeException("Cannot request leadership review from status [{$package->status}].");
        }

        $snapshot = $this->snapshotForBoardResult($boardResult);
        $hash = $this->computeHash($snapshot);

        $package->forceFill([
            'data_snapshot' => $snapshot,
            'data_hash' => $hash,
        ])->save();

        $this->syncReportRecords($package);

        $this->transition($package, BoardResultCertificationPackage::STATUS_AWAITING_LEADERSHIP_REVIEW, $actor, [
            'description' => "{$actor->name} sent the result for leadership review.",
        ]);

        return $package->fresh();
    }

    public function returnPackageForCorrection(BoardResultCertificationPackage $package, User $actor, string $reason): void
    {
        $this->transition($package, BoardResultCertificationPackage::STATUS_LEADERSHIP_CHANGES_REQUESTED, $actor, [
            'description' => "{$actor->name} returned the package for correction: {$reason}",
            'extra' => ['return_reason' => $reason, 'returned_by_user_id' => $actor->id, 'returned_at' => now()],
        ]);
    }

    public function resumeAfterCorrection(BoardResultCertificationPackage $package, User $actor): void
    {
        $this->transition($package, BoardResultCertificationPackage::STATUS_DRAFT, $actor, [
            'description' => "{$actor->name} resumed editing after requested corrections.",
        ]);
    }

    public function beginReportSignatures(BoardResultCertificationPackage $package, User $actor): void
    {
        $this->transition($package, BoardResultCertificationPackage::STATUS_AWAITING_REPORT_SIGNATURES, $actor, [
            'description' => "{$actor->name} began the report verification/signature process.",
        ]);
    }

    // ------------------------------------------------------------------
    // Report-level transitions
    // ------------------------------------------------------------------

    public function generateReport(
        BoardResultCertificationReport $report,
        array $snapshot,
        string $pdfPath,
        ?string $pdfDisk,
        ?int $rowCount,
        User $actor,
    ): void {
        $hash = $this->computeHash($snapshot);

        $report->forceFill([
            'data_snapshot' => $snapshot,
            'data_hash' => $hash,
            'row_count' => $rowCount,
            'generated_pdf_path' => $pdfPath,
            'generated_pdf_disk' => $pdfDisk,
            'generated_at' => now(),
        ]);

        $this->transitionReport($report, BoardResultCertificationReport::STATUS_GENERATED, $actor, [
            'description' => "{$actor->name} generated the ".BoardResultCertificationReport::typeLabel($report->report_type).' report.',
        ]);
    }

    public function uploadSignedReport(
        BoardResultCertificationReport $report,
        string $path,
        ?string $disk,
        string $hash,
        User $actor,
        string $role,
    ): void {
        $report->forceFill([
            'signed_pdf_path' => $path,
            'signed_pdf_disk' => $disk,
            'signed_pdf_hash' => $hash,
            'signed_by_user_id' => $actor->id,
            'signer_role' => $role,
            'signed_at' => now(),
        ]);

        $this->transitionReport($report, BoardResultCertificationReport::STATUS_SIGNED_UPLOADED, $actor, [
            'description' => "{$actor->name} ({$role}) uploaded a signed copy of the ".BoardResultCertificationReport::typeLabel($report->report_type).' report.',
        ]);
    }

    public function acceptReport(BoardResultCertificationReport $report, User $actor): void
    {
        $report->forceFill(['accepted_at' => now()]);

        $this->transitionReport($report, BoardResultCertificationReport::STATUS_ACCEPTED, $actor, [
            'description' => "{$actor->name} accepted the signed ".BoardResultCertificationReport::typeLabel($report->report_type).' report.',
        ]);
    }

    public function returnReport(BoardResultCertificationReport $report, User $actor, string $reason): void
    {
        $report->forceFill(['review_notes' => $reason]);

        $this->transitionReport($report, BoardResultCertificationReport::STATUS_CHANGES_REQUESTED, $actor, [
            'description' => "{$actor->name} returned the ".BoardResultCertificationReport::typeLabel($report->report_type)." report: {$reason}",
        ]);
    }

    public function allRequiredReportsAccepted(BoardResultCertificationPackage $package): bool
    {
        $reports = $package->reports()->where('status', '!=', BoardResultCertificationReport::STATUS_SUPERSEDED)->get();

        if ($reports->isEmpty()) {
            return false;
        }

        return $reports->every(fn (BoardResultCertificationReport $r) => $r->isAccepted());
    }

    public function markIndividualReportsSigned(BoardResultCertificationPackage $package, User $actor): void
    {
        if (! $this->allRequiredReportsAccepted($package)) {
            throw new RuntimeException('Every required individual report must be signed and accepted first.');
        }

        $this->transition($package, BoardResultCertificationPackage::STATUS_INDIVIDUAL_REPORTS_SIGNED, $actor, [
            'description' => "{$actor->name} confirmed all individual signed reports are complete.",
        ]);
    }

    public function generateConsolidated(
        BoardResultCertificationPackage $package,
        array $snapshot,
        string $pdfPath,
        ?string $pdfDisk,
        User $actor,
    ): void {
        $hash = $this->computeHash($snapshot);

        $package->forceFill([
            'data_snapshot' => $snapshot,
            'data_hash' => $hash,
            'generated_pdf_path' => $pdfPath,
            'generated_pdf_disk' => $pdfDisk,
            'generated_at' => now(),
        ]);

        $this->transition($package, BoardResultCertificationPackage::STATUS_AWAITING_CONSOLIDATED_SIGNATURE, $actor, [
            'description' => "{$actor->name} generated the all-types consolidated certification PDF.",
        ]);
    }

    public function uploadSignedConsolidated(
        BoardResultCertificationPackage $package,
        string $path,
        ?string $disk,
        string $hash,
        User $actor,
        string $role,
    ): void {
        $package->forceFill([
            'signed_pdf_path' => $path,
            'signed_pdf_disk' => $disk,
            'signed_pdf_hash' => $hash,
            'signed_by_user_id' => $actor->id,
            'signer_role' => $role,
            'signed_at' => now(),
        ]);

        $this->transition($package, BoardResultCertificationPackage::STATUS_SCHOOL_CERTIFIED, $actor, [
            'description' => "{$actor->name} ({$role}) uploaded the signed consolidated certification report.",
        ]);
    }

    public function submitToSahodaya(BoardResultCertificationPackage $package, User $actor): void
    {
        DB::transaction(function () use ($package, $actor) {
            $package->forceFill([
                'submitted_by_user_id' => $actor->id,
                'submitted_at' => now(),
            ]);

            $this->transition($package, BoardResultCertificationPackage::STATUS_SUBMITTED_TO_SAHODAYA, $actor, [
                'description' => "{$actor->name} submitted the certified package to Sahodaya.",
            ]);

            $boardResult = $package->boardResult ?? BoardResult::findOrFail($package->board_result_id);
            $boardResult->status = BoardResult::STATUS_SUBMITTED;
            $boardResult->submitted_by = $actor->id;
            $boardResult->submitted_at = now();
            $boardResult->submission_count = ($boardResult->submission_count ?? 0) + 1;
            $boardResult->save();
        });
    }

    public function sahodayaReturn(BoardResultCertificationPackage $package, User $actor, string $reason): BoardResultCertificationPackage
    {
        return DB::transaction(function () use ($package, $actor, $reason) {
            $package->forceFill([
                'return_reason' => $reason,
                'returned_by_user_id' => $actor->id,
                'returned_at' => now(),
            ]);

            $this->transition($package, BoardResultCertificationPackage::STATUS_SAHODAYA_RETURNED, $actor, [
                'description' => "{$actor->name} returned the package to the school: {$reason}",
            ]);

            $boardResult = $package->boardResult ?? BoardResult::findOrFail($package->board_result_id);

            return $this->supersedeAndSpawnNextVersion($package, $boardResult, $actor, 'Returned by Sahodaya for correction.');
        });
    }

    public function sahodayaVerify(BoardResultCertificationPackage $package, User $actor): void
    {
        $this->transition($package, BoardResultCertificationPackage::STATUS_SAHODAYA_VERIFIED, $actor, [
            'description' => "{$actor->name} verified the certified package.",
        ]);
    }

    public function sahodayaApprove(BoardResultCertificationPackage $package, User $actor): void
    {
        $this->transition($package, BoardResultCertificationPackage::STATUS_APPROVED, $actor, [
            'description' => "{$actor->name} approved the certified package.",
        ]);
    }

    public function sahodayaPublish(BoardResultCertificationPackage $package, User $actor): void
    {
        $this->transition($package, BoardResultCertificationPackage::STATUS_PUBLISHED, $actor, [
            'description' => "{$actor->name} published the certified package.",
        ]);
    }

    /**
     * Any authorized correction to the underlying result/topper data must
     * call this. It invalidates the active package (and any unaccepted
     * reports within it) and starts a fresh version so nothing signed can
     * silently carry over to changed data.
     */
    public function invalidateForDataChange(BoardResult $boardResult, User $actor, string $reason): ?BoardResultCertificationPackage
    {
        $active = $this->activePackage($boardResult);

        if ($active === null) {
            return null;
        }

        // Nothing has been generated/signed yet — still safely editable, no need to version-bump.
        if (in_array($active->status, [
            BoardResultCertificationPackage::STATUS_DRAFT,
            BoardResultCertificationPackage::STATUS_LEADERSHIP_CHANGES_REQUESTED,
        ], true)) {
            return $active;
        }

        if ($active->isSubmittedToSahodaya()) {
            throw new RuntimeException('This package has already been submitted to Sahodaya. It must be returned before the underlying data can change.');
        }

        return DB::transaction(fn () => $this->supersedeAndSpawnNextVersion($active, $boardResult, $actor, $reason));
    }

    private function supersedeAndSpawnNextVersion(
        BoardResultCertificationPackage $package,
        BoardResult $boardResult,
        User $actor,
        string $reason,
    ): BoardResultCertificationPackage {
        $package->reports()
            ->where('status', '!=', BoardResultCertificationReport::STATUS_SUPERSEDED)
            ->get()
            ->each(function (BoardResultCertificationReport $report) use ($actor, $reason) {
                $report->forceFill(['status' => BoardResultCertificationReport::STATUS_SUPERSEDED]);
                $report->save();

                $this->auditLogger->event(
                    'certification_report_superseded',
                    "Report superseded: {$reason}",
                    $report->tenant_id,
                    'board_result_certification',
                    $report,
                );
            });

        $package->forceFill([
            'status' => BoardResultCertificationPackage::STATUS_SUPERSEDED,
            'superseded_at' => now(),
        ])->save();

        $this->auditLogger->event(
            'certification_package_superseded',
            "Package v{$package->version} superseded: {$reason}",
            $package->tenant_id,
            'board_result_certification',
            $package,
        );

        $next = BoardResultCertificationPackage::create([
            'board_result_id' => $boardResult->id,
            'tenant_id' => $boardResult->tenant_id,
            'academic_year' => $boardResult->academic_year,
            'class' => $boardResult->class,
            'version' => $package->version + 1,
            'status' => BoardResultCertificationPackage::STATUS_DRAFT,
        ]);

        $this->auditLogger->event(
            'certification_package_created',
            "Certification package v{$next->version} created following invalidation of v{$package->version}.",
            $boardResult->tenant_id,
            'board_result_certification',
            $next,
        );

        return $next;
    }

    // ------------------------------------------------------------------
    // Snapshot / hash helpers
    // ------------------------------------------------------------------

    /** @return array<string, mixed> */
    public function snapshotForBoardResult(BoardResult $boardResult): array
    {
        $toppers = $boardResult->toppers()
            ->orderBy('id')
            ->get(['id', 'entry_type', 'name', 'admission_no', 'roll_no', 'gender', 'stream_id', 'percentage', 'total_marks', 'marks_obtained', 'is_perfect_scorer', 'rank', 'verification_status'])
            ->map(fn (Topper $t) => Arr::only($t->toArray(), [
                'id', 'entry_type', 'name', 'admission_no', 'roll_no', 'gender', 'stream_id',
                'percentage', 'total_marks', 'marks_obtained', 'is_perfect_scorer', 'rank', 'verification_status',
            ]))
            ->all();

        return [
            'board_result_id' => $boardResult->id,
            'class' => $boardResult->class,
            'examination_type' => $boardResult->examination_type,
            'academic_year' => $boardResult->academic_year,
            'total_appeared' => $boardResult->total_appeared,
            'pass_count' => $boardResult->pass_count,
            'pass_percent' => $boardResult->pass_percent,
            'distinctions' => $boardResult->distinctions,
            'first_class' => $boardResult->first_class,
            'highest_mark' => $boardResult->highest_mark,
            'average_mark' => $boardResult->average_mark,
            'total_marks' => $boardResult->total_marks,
            'subject_stats' => $boardResult->subject_stats,
            'toppers' => $toppers,
        ];
    }

    public function computeHash(array $data): string
    {
        return hash('sha256', json_encode($this->canonicalize($data), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    private function canonicalize(mixed $value): mixed
    {
        if (is_array($value)) {
            $isList = array_is_list($value);
            $out = array_map(fn ($v) => $this->canonicalize($v), $value);

            if (! $isList) {
                ksort($out);
            }

            return $out;
        }

        return $value;
    }

    // ------------------------------------------------------------------
    // Internal transition helpers
    // ------------------------------------------------------------------

    private function transition(BoardResultCertificationPackage $package, string $target, User $actor, array $meta = []): void
    {
        if (! $package->canTransitionTo($target)) {
            throw new RuntimeException("Invalid certification package transition [{$package->status}] -> [{$target}].");
        }

        $from = $package->status;
        $package->status = $target;

        foreach ($meta['extra'] ?? [] as $key => $value) {
            $package->{$key} = $value;
        }

        $package->save();

        $this->auditLogger->event(
            'certification_package_'.$target,
            $meta['description'] ?? "Package v{$package->version} moved from [{$from}] to [{$target}].",
            $package->tenant_id,
            'board_result_certification',
            $package,
            [],
            ['from' => $from, 'to' => $target],
        );
    }

    private function transitionReport(BoardResultCertificationReport $report, string $target, User $actor, array $meta = []): void
    {
        if (! $report->canTransitionTo($target)) {
            throw new RuntimeException("Invalid certification report transition [{$report->status}] -> [{$target}].");
        }

        $from = $report->status;
        $report->status = $target;
        $report->save();

        $this->auditLogger->event(
            'certification_report_'.$target,
            $meta['description'] ?? 'Report status changed from ['.$from."] to [{$target}].",
            $report->tenant_id,
            'board_result_certification',
            $report,
            [],
            ['from' => $from, 'to' => $target],
        );
    }
}
