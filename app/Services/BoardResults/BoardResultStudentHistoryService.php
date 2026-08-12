<?php

namespace App\Services\BoardResults;

use App\Models\BoardResult;
use App\Models\Tenant;
use App\Models\Topper;
use App\Support\CbseSubjectCodes;

class BoardResultStudentHistoryService
{
    /**
     * Search student history by query string (roll no, admission no, or student name).
     *
     * @return array{
     *   query: string,
     *   matches: list<array{
     *     student_name: string,
     *     roll_no: string|null,
     *     admission_no: string|null,
     *     school_id: string,
     *     school_name: string,
     *     history: list<array<string, mixed>>
     *   }>
     * }
     */
    public function search(string $query, ?string $sahodayaId = null, ?string $tenantId = null): array
    {
        $term = trim($query);
        if (strlen($term) < 2) {
            return ['query' => $term, 'matches' => []];
        }

        $schoolIds = [];
        if ($tenantId) {
            $schoolIds = [$tenantId];
        } elseif ($sahodayaId) {
            $schoolIds = Tenant::query()
                ->where('parent_id', $sahodayaId)
                ->where('type', 'school')
                ->pluck('id')
                ->all();
        }

        $topperQuery = Topper::query()
            ->with(['boardResult', 'subjectMarks'])
            ->whereHas('boardResult', function ($q) use ($schoolIds) {
                if ($schoolIds !== []) {
                    $q->whereIn('tenant_id', $schoolIds);
                }
            })
            ->where(function ($q) use ($term) {
                $q->where('roll_no', $term)
                  ->orWhere('admission_no', $term)
                  ->orWhere('name', 'like', "%{$term}%");
            });

        $toppers = $topperQuery->get();

        if ($toppers->isEmpty()) {
            return ['query' => $term, 'matches' => []];
        }

        $schoolNames = Tenant::whereIn('id', $toppers->pluck('tenant_id')->unique()->all())
            ->pluck('name', 'id');

        // Group toppers by student identity (roll_no or admission_no or lowercase student name)
        $grouped = $toppers->groupBy(function (Topper $t) {
            if ($t->roll_no) return "roll:{$t->roll_no}";
            if ($t->admission_no) return "adm:{$t->admission_no}";
            return "name:" . strtolower(trim($t->name)) . ":{$t->tenant_id}";
        });

        $matches = [];
        foreach ($grouped as $key => $studentToppers) {
            /** @var Topper $first */
            $first = $studentToppers->first();
            $schoolName = $schoolNames[$first->tenant_id] ?? (string) $first->tenant_id;

            $historyRecords = [];
            // Group by academic year & class to avoid duplicating overall + full_a1 + subject entries for the same exam
            $examGroups = $studentToppers->groupBy(function (Topper $t) {
                $year = $t->boardResult ? $t->boardResult->academic_year : 'unknown';
                $class = $t->boardResult ? $t->boardResult->class : 'unknown';
                return "{$year}-{$class}";
            });

            foreach ($examGroups as $examKey => $records) {
                /** @var Topper $rep */
                $rep = $records->firstWhere('entry_type', Topper::ENTRY_OVERALL)
                    ?? $records->firstWhere('entry_type', Topper::ENTRY_FULL_A1)
                    ?? $records->first();

                $br = $rep->boardResult;

                // Collect all subject marks across the entries for this exam
                $topperIds = $records->pluck('id')->all();
                $marksDb = \Illuminate\Support\Facades\DB::table('topper_subject_marks')
                    ->whereIn('topper_id', $topperIds)
                    ->get();

                $allSubjectMarks = collect();
                $entryTypes = [];
                foreach ($records as $rec) {
                    $entryTypes[] = $rec->entry_type;
                }
                foreach ($marksDb as $sm) {
                    $allSubjectMarks->put($sm->subject_label, $sm);
                }

                $formattedMarks = $allSubjectMarks->values()->map(function ($sm) use ($br, $entryTypes) {
                    $class = $br ? (int) $br->class : 10;
                    $code = $class === 10
                        ? CbseSubjectCodes::forClass10Label($sm->subject_label)
                        : CbseSubjectCodes::forClass12Label($sm->subject_label);

                    $marksVal = (float) $sm->marks;
                    $grade = in_array(Topper::ENTRY_FULL_A1, $entryTypes, true)
                        ? 'A1'
                        : ($marksVal >= 91 ? 'A1' : ($marksVal >= 81 ? 'A2' : ($marksVal >= 71 ? 'B1' : 'B2')));

                    return [
                        'subject_label' => (string) $sm->subject_label,
                        'subject_code' => $code,
                        'marks' => $marksVal,
                        'grade' => $grade,
                    ];
                })->all();

                $historyRecords[] = [
                    'academic_year' => $br ? $br->academic_year : null,
                    'class' => $br ? (int) $br->class : null,
                    'examination_type' => $br ? $br->examination_type : null,
                    'school_id' => (string) $rep->tenant_id,
                    'school_name' => $schoolNames[$rep->tenant_id] ?? (string) $rep->tenant_id,
                    'stream' => $rep->stream,
                    'total_marks' => $rep->total_marks ? (float) $rep->total_marks : ($br ? (float) $br->total_marks : null),
                    'marks_obtained' => $rep->marks_obtained ? (float) $rep->marks_obtained : null,
                    'percentage' => $rep->percentage !== null ? (float) $rep->percentage : null,
                    'rank' => $rep->rank ? (int) $rep->rank : null,
                    'is_perfect_scorer' => (bool) $rep->is_perfect_scorer,
                    'entry_types' => array_values(array_unique($entryTypes)),
                    'status' => $br ? $br->status : null,
                    'subject_marks' => $formattedMarks,
                ];
            }

            // Sort history by academic year descending
            usort($historyRecords, fn ($a, $b) => strcmp($b['academic_year'] ?? '', $a['academic_year'] ?? ''));

            $matches[] = [
                'student_name' => $first->name,
                'roll_no' => $first->roll_no,
                'admission_no' => $first->admission_no,
                'gender' => $first->gender,
                'school_id' => (string) $first->tenant_id,
                'school_name' => $schoolName,
                'history' => $historyRecords,
            ];
        }

        return [
            'query' => $term,
            'matches' => $matches,
        ];
    }
}
