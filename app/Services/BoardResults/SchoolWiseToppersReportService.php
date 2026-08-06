<?php

namespace App\Services\BoardResults;

use App\Models\BoardResult;
use App\Models\Tenant;
use App\Models\Topper;
use Illuminate\Support\Collection;

/**
 * "Each school has a topper" register (#board-results-ux-redesign): one row per
 * member school showing that school's OWN #1-ranked overall topper for the
 * selected class + academic year — not the Sahodaya-wide pooled Top-N, which can
 * leave smaller schools with zero representation (see
 * docs/BOARD_RESULTS_UX_REDESIGN_PLAN.md). Every approved school under the
 * Sahodaya is listed even if it hasn't submitted a topper yet, so this doubles as
 * a quick "who hasn't submitted" glance without being a separate report.
 */
class SchoolWiseToppersReportService
{
    /**
     * @return list<array{
     *   school_id: string,
     *   school_name: string,
     *   result_status: string|null,
     *   has_topper: bool,
     *   student_name: string|null,
     *   admission_no: string|null,
     *   roll_no: string|null,
     *   percentage: float|null,
     *   marks_obtained: float|null,
     *   total_marks: float|null,
     *   stream: string|null
     * }>
     */
    public function list(string $sahodayaId, string $academicYear, int $class, ?string $stream = null): array
    {
        $schools = Tenant::query()
            ->where('parent_id', $sahodayaId)
            ->where('type', 'school')
            ->where('membership_status', 'approved')
            ->orderBy('name')
            ->get(['id', 'name']);

        if ($schools->isEmpty()) {
            return [];
        }

        $resultsQuery = BoardResult::query()
            ->whereIn('tenant_id', $schools->pluck('id'))
            ->where('academic_year', $academicYear)
            ->where('class', $class)
            ->where('status', '!=', BoardResult::STATUS_REJECTED)
            ->with(['toppers' => function ($q) use ($stream) {
                $q->overallEntries();
                if ($stream) {
                    $q->where('stream', $stream);
                }
                $q->orderByDesc('percentage')->orderBy('id')->with('examStream');
            }]);

        $results = $resultsQuery->get()->keyBy('tenant_id');

        return $schools->map(function (Tenant $school) use ($results) {
            /** @var BoardResult|null $result */
            $result = $results->get($school->id);
            /** @var Topper|null $topper */
            $topper = $result?->toppers instanceof Collection ? $result->toppers->first() : null;

            return [
                'school_id'      => $school->id,
                'school_name'    => $school->name,
                'result_status'  => $result?->status ?? 'Not Submitted',
                'has_topper'     => $topper !== null,
                'student_name'   => $topper?->name,
                'admission_no'   => $topper?->admission_no,
                'roll_no'        => $topper?->roll_no,
                'percentage'     => $topper?->percentage !== null ? (float) $topper->percentage : null,
                'marks_obtained' => $topper?->marks_obtained !== null ? (float) $topper->marks_obtained : null,
                'total_marks'    => $topper?->total_marks !== null ? (float) $topper->total_marks : null,
                'stream'         => $topper?->examStream?->label ?? $topper?->stream,
            ];
        })->values()->all();
    }

    public function listAllToppers(string $sahodayaId, string $academicYear, int $class, ?string $stream = null): array
    {
        $schools = Tenant::query()
            ->where('parent_id', $sahodayaId)
            ->where('type', 'school')
            ->where('membership_status', 'approved')
            ->pluck('name', 'id');

        if ($schools->isEmpty()) {
            return [];
        }

        $query = Topper::query()
            ->join('board_results as br', 'br.id', '=', 'toppers.board_result_id')
            ->whereIn('toppers.tenant_id', $schools->keys())
            ->where('br.academic_year', $academicYear)
            ->where('br.class', $class)
            ->where('br.status', '!=', BoardResult::STATUS_REJECTED)
            ->whereIn('toppers.entry_type', [Topper::ENTRY_OVERALL, Topper::ENTRY_SUBJECT]);

        if ($stream) {
            $query->where('toppers.stream', $stream);
        }

        $toppers = $query->select([
            'toppers.*',
            'br.class',
            'br.academic_year',
            'br.status as result_status',
        ])
        ->orderByDesc('toppers.percentage')
        ->get();

        return $toppers->map(function (Topper $topper) use ($schools) {
            return [
                'id'             => $topper->id,
                'school_id'      => $topper->tenant_id,
                'school_name'    => $schools[$topper->tenant_id] ?? $topper->tenant_id,
                'result_status'  => $topper->result_status,
                'has_topper'     => true,
                'student_name'   => $topper->name,
                'admission_no'   => $topper->admission_no,
                'roll_no'        => $topper->roll_no,
                'percentage'     => $topper->percentage !== null ? (float) $topper->percentage : null,
                'marks_obtained' => $topper->marks_obtained !== null ? (float) $topper->marks_obtained : null,
                'total_marks'    => $topper->total_marks !== null ? (float) $topper->total_marks : null,
                'stream'         => $topper->stream,
                'entry_type'     => $topper->entry_type,
                'verification_status' => $topper->verification_status ?? 'pending',
            ];
        })->values()->all();
    }
}
