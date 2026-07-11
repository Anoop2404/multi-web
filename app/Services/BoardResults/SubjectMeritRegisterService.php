<?php

namespace App\Services\BoardResults;

use App\Models\BoardResult;
use App\Models\Tenant;
use App\Models\Topper;
use App\Support\BoardExamSubjects;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Subject-wise Merit Register (#147).
 * Prefers topper_subject_marks when present; otherwise aggregates subject_marks JSON.
 */
class SubjectMeritRegisterService
{
    /**
     * @return list<array{
     *   subject: string,
     *   student_name: string,
     *   school_id: string,
     *   school_name: string,
     *   marks: float|int,
     *   percentage: float|null,
     *   stream: string|null,
     *   class: int|null,
     *   academic_year: string,
     *   admission_no: string|null,
     *   roll_no: string|null
     * }>
     */
    public function register(string $sahodayaId, string $academicYear, ?int $class = null): array
    {
        $schoolIds = Tenant::query()
            ->where('parent_id', $sahodayaId)
            ->where('type', 'school')
            ->pluck('id')
            ->all();

        if ($schoolIds === []) {
            return [];
        }

        $names = Tenant::whereIn('id', $schoolIds)->pluck('name', 'id');

        $rows = $this->fromNormalizedTable($schoolIds, $academicYear, $class, $names);
        if ($rows !== null) {
            return $rows;
        }

        return $this->fromSubjectMarksJson($schoolIds, $academicYear, $class, $names);
    }

    /**
     * @param  list<string>  $schoolIds
     * @param  \Illuminate\Support\Collection<string, string>  $names
     * @return list<array<string, mixed>>|null
     */
    private function fromNormalizedTable(array $schoolIds, string $academicYear, ?int $class, Collection $names): ?array
    {
        if (! Schema::hasTable('topper_subject_marks')) {
            return null;
        }

        $query = DB::table('topper_subject_marks as tsm')
            ->join('toppers as t', 't.id', '=', 'tsm.topper_id')
            ->join('board_results as br', 'br.id', '=', 't.board_result_id')
            ->whereIn('br.tenant_id', $schoolIds)
            ->where('br.academic_year', $academicYear)
            ->whereIn('br.status', [BoardResult::STATUS_APPROVED, BoardResult::STATUS_PUBLISHED])
            ->select([
                'tsm.marks',
                't.name as student_name',
                't.percentage',
                't.stream',
                't.admission_no',
                't.roll_no',
                't.tenant_id as school_id',
                'br.class',
                'br.academic_year',
            ]);

        if (Schema::hasColumn('topper_subject_marks', 'subject_label')) {
            $query->addSelect('tsm.subject_label as subject');
        } elseif (Schema::hasColumn('topper_subject_marks', 'subject_name')) {
            $query->addSelect('tsm.subject_name as subject');
        } elseif (Schema::hasTable('subjects') && Schema::hasColumn('topper_subject_marks', 'subject_id')) {
            $query->leftJoin('subjects as s', 's.id', '=', 'tsm.subject_id')
                ->addSelect(DB::raw('COALESCE(s.name, tsm.subject_id::text) as subject'));
        } else {
            return null;
        }

        if ($class !== null) {
            $query->where('br.class', $class);
        }

        return $query->orderBy('subject')->orderByDesc('tsm.marks')->get()
            ->map(fn ($row) => [
                'subject' => (string) $row->subject,
                'student_name' => (string) $row->student_name,
                'school_id' => (string) $row->school_id,
                'school_name' => $names[$row->school_id] ?? (string) $row->school_id,
                'marks' => is_numeric($row->marks) ? (float) $row->marks : $row->marks,
                'percentage' => $row->percentage !== null ? (float) $row->percentage : null,
                'stream' => $row->stream,
                'class' => $row->class !== null ? (int) $row->class : null,
                'academic_year' => (string) $row->academic_year,
                'admission_no' => $row->admission_no,
                'roll_no' => $row->roll_no,
            ])
            ->values()
            ->all();
    }

    /**
     * @param  list<string>  $schoolIds
     * @param  \Illuminate\Support\Collection<string, string>  $names
     * @return list<array<string, mixed>>
     */
    private function fromSubjectMarksJson(array $schoolIds, string $academicYear, ?int $class, Collection $names): array
    {
        $toppers = Topper::query()
            ->whereHas('boardResult', function ($q) use ($schoolIds, $academicYear, $class) {
                $q->whereIn('tenant_id', $schoolIds)
                    ->where('academic_year', $academicYear)
                    ->whereIn('status', [BoardResult::STATUS_APPROVED, BoardResult::STATUS_PUBLISHED]);
                if ($class !== null) {
                    $q->where('class', $class);
                }
            })
            ->with(['boardResult:id,tenant_id,class,academic_year,status'])
            ->get();

        $rows = [];
        foreach ($toppers as $topper) {
            $marks = BoardExamSubjects::normalizeSubjectMarks($topper->subject_marks ?? []);
            foreach ($marks as $subject => $mark) {
                $rows[] = [
                    'subject' => $subject,
                    'student_name' => $topper->name,
                    'school_id' => (string) $topper->tenant_id,
                    'school_name' => $names[$topper->tenant_id] ?? (string) $topper->tenant_id,
                    'marks' => $mark,
                    'percentage' => $topper->percentage,
                    'stream' => $topper->stream,
                    'class' => $topper->boardResult?->class,
                    'academic_year' => $topper->boardResult?->academic_year ?? $academicYear,
                    'admission_no' => $topper->admission_no,
                    'roll_no' => $topper->roll_no,
                ];
            }
        }

        usort($rows, function (array $a, array $b) {
            $cmp = strcmp($a['subject'], $b['subject']);
            if ($cmp !== 0) {
                return $cmp;
            }

            return $b['marks'] <=> $a['marks'];
        });

        return $rows;
    }
}
