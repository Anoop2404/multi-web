<?php

namespace App\Services\BoardResults;

use App\Models\BoardResult;
use App\Models\BoardResultCertificationPackage;
use App\Models\BoardResultCertificationReport;
use App\Models\ExamStream;
use App\Models\Tenant;
use App\Models\Topper;
use App\Support\TenantBranding;
use Barryvdh\DomPDF\Facade\Pdf;

/**
 * Assembles the frozen data snapshot for each certification report/consolidated
 * PDF and renders it. Rendering always happens from an already-stored snapshot
 * (never a live re-query) once a report has been generated, so a re-download
 * is guaranteed to match what was signed — see plan §7 (snapshot/hash integrity).
 */
class BoardResultCertificationPdfService
{
    /**
     * Builds the point-in-time snapshot for one report definition. Only called
     * at generation time — the result is what gets hashed and stored.
     */
    public function assembleReportSnapshot(BoardResult $boardResult, string $reportType, ?int $streamId): array
    {
        return match ($reportType) {
            BoardResultCertificationReport::TYPE_SUMMARY => $this->summarySnapshot($boardResult),
            BoardResultCertificationReport::TYPE_OVERALL_TOPPERS => $this->overallToppersSnapshot($boardResult, $streamId),
            BoardResultCertificationReport::TYPE_SUBJECT_TOPPERS => $this->subjectToppersSnapshot($boardResult),
            BoardResultCertificationReport::TYPE_FULL_A1 => $this->fullA1Snapshot($boardResult, $streamId),
            default => [],
        };
    }

    public function renderReportPdf(BoardResultCertificationReport $report, BoardResult $boardResult, Tenant $school): \Barryvdh\DomPDF\PDF
    {
        $view = match ($report->report_type) {
            BoardResultCertificationReport::TYPE_SUMMARY => 'board-results.certification.summary',
            BoardResultCertificationReport::TYPE_OVERALL_TOPPERS => 'board-results.certification.overall-toppers',
            BoardResultCertificationReport::TYPE_SUBJECT_TOPPERS => 'board-results.certification.subject-toppers',
            BoardResultCertificationReport::TYPE_FULL_A1 => 'board-results.certification.full-a1',
            default => 'board-results.certification.summary',
        };

        return Pdf::loadView($view, [
            'report' => $report,
            'boardResult' => $boardResult,
            'school' => $school,
            'snapshot' => $report->data_snapshot ?? [],
            'logoSrc' => TenantBranding::logoEmbedSrc($school),
            'generatedAt' => optional($report->generated_at)->format('d M Y · h:i A') ?? now()->format('d M Y · h:i A'),
        ])->setPaper('a4', 'portrait');
    }

    public function assembleConsolidatedSnapshot(BoardResultCertificationPackage $package, BoardResult $boardResult): array
    {
        $reports = $package->reports()
            ->where('status', '!=', BoardResultCertificationReport::STATUS_SUPERSEDED)
            ->orderBy('report_type')
            ->get();

        return [
            'summary' => $this->summarySnapshot($boardResult),
            'reports' => $reports->map(fn (BoardResultCertificationReport $r) => [
                'id' => $r->id,
                'report_type' => $r->report_type,
                'label' => BoardResultCertificationReport::typeLabel($r->report_type).($r->stream ? ' — '.$r->stream->label : ''),
                'stream_id' => $r->stream_id,
                'status' => $r->status,
                'row_count' => $r->row_count,
                'data_hash' => $r->data_hash,
                'signed_pdf_hash' => $r->signed_pdf_hash,
                'signer_role' => $r->signer_role,
                'signed_by' => $r->signedBy?->name,
                'signed_at' => optional($r->signed_at)->toIso8601String(),
                'accepted_at' => optional($r->accepted_at)->toIso8601String(),
            ])->values()->all(),
        ];
    }

    public function renderConsolidatedPdf(BoardResultCertificationPackage $package, BoardResult $boardResult, Tenant $school): \Barryvdh\DomPDF\PDF
    {
        return Pdf::loadView('board-results.certification.consolidated', [
            'package' => $package,
            'boardResult' => $boardResult,
            'school' => $school,
            'snapshot' => $package->data_snapshot ?? [],
            'logoSrc' => TenantBranding::logoEmbedSrc($school),
            'generatedAt' => optional($package->generated_at)->format('d M Y · h:i A') ?? now()->format('d M Y · h:i A'),
        ])->setPaper('a4', 'portrait');
    }

    // ------------------------------------------------------------------

    private function summarySnapshot(BoardResult $boardResult): array
    {
        return [
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
            'remarks' => $boardResult->remarks,
        ];
    }

    private function overallToppersSnapshot(BoardResult $boardResult, ?int $streamId): array
    {
        $query = $boardResult->toppers()->overallEntries()->with(['subjectMarks', 'examStream']);
        if ($streamId) {
            $stream = ExamStream::find($streamId);
            $query->where(function ($q) use ($streamId, $stream) {
                $q->where('stream_id', $streamId);
                if ($stream) {
                    $q->orWhere('stream', $stream->label)
                      ->orWhere('stream', strtolower($stream->label));
                }
            });
        } elseif ((int) $boardResult->class === 12) {
            $query->whereNull('stream_id')->where(fn ($q) => $q->whereNull('stream')->orWhere('stream', ''));
        }

        $toppers = $query->orderBy('rank')->get();
        $stream = $streamId ? ExamStream::find($streamId) : null;

        return [
            'stream_label' => $stream?->label,
            'rows' => $toppers->map(fn (Topper $t) => [
                'name' => $t->name,
                'admission_no' => $t->admission_no,
                'roll_no' => $t->roll_no,
                'gender' => $t->gender,
                'marks_obtained' => $t->marks_obtained,
                'total_marks' => $t->total_marks,
                'percentage' => $t->percentage,
                'rank' => $t->rank,
                'is_perfect_scorer' => $t->is_perfect_scorer,
                'subject_marks' => $t->subject_marks,
            ])->values()->all(),
        ];
    }

    private function subjectToppersSnapshot(BoardResult $boardResult): array
    {
        $toppers = $boardResult->toppers()->subjectEntries()->with(['subjectMarks', 'examStream'])->get();

        $grouped = $toppers->groupBy(fn (Topper $t) => array_key_first($t->subject_marks) ?? 'Subject');

        $subjects = $grouped->map(function ($rows, $subject) {
            return [
                'subject' => $subject,
                'rows' => $rows->sortByDesc(fn (Topper $t) => $t->subject_marks[array_key_first($t->subject_marks) ?? ''] ?? 0)
                    ->map(fn (Topper $t) => [
                        'name' => $t->name,
                        'roll_no' => $t->roll_no,
                        'gender' => $t->gender,
                        'marks' => $t->subject_marks[array_key_first($t->subject_marks) ?? ''] ?? null,
                        'stream_label' => $t->examStream?->label ?? $t->stream,
                    ])->values()->all(),
            ];
        })->values()->all();

        return ['subjects' => $subjects];
    }

    private function fullA1Snapshot(BoardResult $boardResult, ?int $streamId): array
    {
        $query = $boardResult->toppers()->fullA1Entries()->with(['subjectMarks', 'examStream']);
        if ($streamId) {
            $stream = ExamStream::find($streamId);
            $query->where(function ($q) use ($streamId, $stream) {
                $q->where('stream_id', $streamId);
                if ($stream) {
                    $q->orWhere('stream', $stream->label)
                      ->orWhere('stream', strtolower($stream->label));
                }
            });
        } elseif ((int) $boardResult->class === 12) {
            $query->whereNull('stream_id')->where(fn ($q) => $q->whereNull('stream')->orWhere('stream', ''));
        }

        $toppers = $query->orderBy('name')->get();
        $stream = $streamId ? ExamStream::find($streamId) : null;

        return [
            'stream_label' => $stream?->label,
            'rows' => $toppers->map(fn (Topper $t) => [
                'name' => $t->name,
                'admission_no' => $t->admission_no,
                'roll_no' => $t->roll_no,
                'gender' => $t->gender,
                'subject_marks' => $t->subject_marks,
            ])->values()->all(),
        ];
    }
}
