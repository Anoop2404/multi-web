<?php

namespace App\Services\BoardResults;

use App\Models\BoardResult;
use App\Models\Tenant;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Subject-wise Merit Register (#147).
 * Reads from topper_subject_marks and computes ranks per subject.
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
     *   roll_no: string|null,
     *   rank: int
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

        return $this->fromNormalizedTable($schoolIds, $academicYear, $class, $names) ?? [];
    }

    /**
     * @param  list<string>  $schoolIds
     * @param  Collection<string, string>  $names
     * @return list<array<string, mixed>>
     */
    private function fromNormalizedTable(array $schoolIds, string $academicYear, ?int $class, Collection $names): array
    {
        if (! Schema::hasTable('topper_subject_marks')) {
            return [];
        }

        $query = DB::table('topper_subject_marks as tsm')
            ->join('toppers as t', 't.id', '=', 'tsm.topper_id')
            ->join('board_results as br', 'br.id', '=', 't.board_result_id')
            ->whereIn('br.tenant_id', $schoolIds)
            ->where('br.academic_year', $academicYear)
            ->whereNotIn('br.status', [BoardResult::STATUS_REJECTED])
            ->select([
                'tsm.marks',
                'tsm.subject_label as subject',
                't.name as student_name',
                't.percentage',
                't.stream',
                't.admission_no',
                't.roll_no',
                't.tenant_id as school_id',
                'br.class',
                'br.academic_year',
            ]);

        if ($class !== null) {
            $query->where('br.class', $class);
        }

        $items = $query->orderBy('subject')->orderByDesc('tsm.marks')->get()
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
            ]);

        $grouped = $items->groupBy('subject');
        $rankedList = [];

        foreach ($grouped as $subject => $subjectItems) {
            $sorted = $subjectItems->sortByDesc('marks')->values();
            $currentRank = 1;
            $prevMarks = null;

            foreach ($sorted as $idx => $row) {
                $marks = $row['marks'];
                if ($prevMarks !== null && $marks < $prevMarks) {
                    $currentRank = $idx + 1;
                }
                $row['rank'] = $currentRank;
                $prevMarks = $marks;
                $rankedList[] = $row;
            }
        }

        return $rankedList;
    }
}
