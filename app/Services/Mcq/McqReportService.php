<?php

namespace App\Services\Mcq;

use App\Models\McqExam;
use App\Models\McqRegistration;
use App\Models\Student;
use App\Models\McqSchoolFee;
use App\Support\ExcelExport;
use Symfony\Component\HttpFoundation\StreamedResponse;

class McqReportService
{
    /** @return list<array<string, mixed>> */
    public function registrationRows(McqExam $exam, ?string $schoolId = null): array
    {
        $query = McqRegistration::where('exam_id', $exam->id)
            ->with(['student.schoolClass', 'teacher', 'school', 'mark', 'feeReceipt']);

        if ($schoolId) {
            $query->where('school_id', $schoolId);
        }

        return $query->orderBy('hall_ticket_no')
            ->orderBy('id')
            ->get()
            ->map(fn (McqRegistration $reg) => [
                'hall_ticket_no'   => $reg->hall_ticket_no,
                'student_name'     => $reg->participantName(),
                'reg_no'           => $reg->student?->reg_no ?? $reg->teacher?->employee_code ?? $reg->teacher?->reg_no,
                'class_name'       => $reg->student?->schoolClass?->name,
                'school_name'      => $reg->school?->name,
                'approval_status'  => $reg->approval_status,
                'attendance_status'=> $reg->attendance_status,
                'attendance_note'  => $reg->attendance_note,
                'score'            => $reg->mark?->score,
                'percentage'       => $reg->mark?->percentage,
                'rank'             => $reg->mark?->rank,
                'grade'            => $reg->mark?->grade,
                'fee_status'       => $reg->feeReceipt?->status,
                'is_teacher'       => $reg->isTeacherRegistration(),
            ])
            ->all();
    }

    /** @return list<array<string, mixed>> */
    public function feeSummaryRows(McqExam $exam): array
    {
        return McqSchoolFee::where('exam_id', $exam->id)
            ->with(['school', 'feeReceipt'])
            ->orderBy('school_id')
            ->get()
            ->map(fn (McqSchoolFee $fee) => [
                'school_name'    => $fee->school?->name,
                'student_count'  => $fee->student_count,
                'total_due'      => (float) $fee->total_due,
                'status'         => $fee->status,
                'receipt_status' => $fee->feeReceipt?->status,
                'payment_date'   => $fee->feeReceipt?->payment_date?->format('Y-m-d'),
                'transaction_ref'=> $fee->feeReceipt?->transaction_ref,
            ])
            ->all();
    }

    /** @return list<array<string, mixed>> */
    public function schoolToppers(McqExam $exam, string $schoolId, int $limit = 25): array
    {
        if (! $exam->results_published) {
            return [];
        }

        return McqRegistration::where('exam_id', $exam->id)
            ->where('school_id', $schoolId)
            ->whereHas('mark')
            ->with(['student.schoolClass', 'mark'])
            ->get()
            ->sortBy(fn (McqRegistration $r) => $r->mark?->rank ?? 9999)
            ->take($limit)
            ->values()
            ->map(fn (McqRegistration $reg) => [
                'rank'       => $reg->mark?->rank,
                'name'       => $reg->student?->name,
                'reg_no'     => $reg->student?->reg_no,
                'class_name' => $reg->student?->schoolClass?->name,
                'score'      => $reg->mark?->score,
                'grade'      => $reg->mark?->grade,
            ])
            ->all();
    }

    public function exportRegistrationRegister(McqExam $exam, ?string $schoolId = null): StreamedResponse
    {
        $rows = $this->registrationRows($exam, $schoolId);
        $suffix = $schoolId ? '-school-'.substr($schoolId, 0, 8) : '';

        return ExcelExport::download(
            'mcq-registration-register-'.$exam->id.$suffix,
            ['Hall ticket', 'Student', 'Reg. no', 'Class', 'School', 'Approval', 'Attendance', 'Score', 'Rank', 'Grade', 'Fee'],
            collect($rows)->map(fn ($r) => [
                $r['hall_ticket_no'],
                $r['student_name'],
                $r['reg_no'],
                $r['class_name'],
                $r['school_name'],
                $r['approval_status'],
                $r['attendance_status'],
                $r['score'],
                $r['rank'],
                $r['grade'],
                $r['fee_status'],
            ]),
        );
    }

    public function exportFeeSummary(McqExam $exam): StreamedResponse
    {
        $rows = $this->feeSummaryRows($exam);

        return ExcelExport::download(
            'mcq-fee-summary-'.$exam->id,
            ['School', 'Students', 'Amount due', 'Status', 'Receipt status', 'Payment date', 'Transaction ref'],
            collect($rows)->map(fn ($r) => [
                $r['school_name'],
                $r['student_count'],
                $r['total_due'],
                $r['status'],
                $r['receipt_status'],
                $r['payment_date'],
                $r['transaction_ref'],
            ]),
        );
    }

    public function exportAttendance(McqExam $exam, ?string $schoolId = null): StreamedResponse
    {
        $rows = $this->registrationRows($exam, $schoolId);
        $suffix = $schoolId ? '-school-'.substr($schoolId, 0, 8) : '';

        return ExcelExport::download(
            'mcq-attendance-'.$exam->id.$suffix,
            ['Hall ticket', 'Student', 'Reg. no', 'Class', 'School', 'Attendance'],
            collect($rows)->map(fn ($r) => [
                $r['hall_ticket_no'],
                $r['student_name'],
                $r['reg_no'],
                $r['class_name'],
                $r['school_name'],
                $r['attendance_status'] ?? 'pending',
            ]),
        );
    }

    /** @return list<array<string, mixed>> */
    public function level2QualifierRows(McqExam $level2Exam): array
    {
        if ((int) ($level2Exam->exam_level ?? 1) <= 1 || ! $level2Exam->parent_exam_id) {
            return [];
        }

        $eligibility = app(McqEligibilityService::class);
        $students = Student::whereIn('id', McqRegistration::where('exam_id', $level2Exam->parent_exam_id)->pluck('student_id'))->get();

        return $students->map(function (Student $student) use ($level2Exam, $eligibility) {
            $eligible = $eligibility->isEligible($level2Exam, $student);

            return [
                'student_name' => $student->name,
                'reg_no'       => $student->reg_no,
                'eligible'     => $eligible ? 'yes' : 'no',
                'reason'       => $eligible ? null : $eligibility->ineligibilityReason($level2Exam, $student),
            ];
        })->values()->all();
    }

    public function exportLevel2Qualifiers(McqExam $exam): StreamedResponse
    {
        $rows = $this->level2QualifierRows($exam);

        return ExcelExport::download(
            'mcq-level2-qualifiers-'.$exam->id,
            ['Student', 'Reg. no', 'Eligible', 'Reason'],
            collect($rows)->map(fn ($r) => [$r['student_name'], $r['reg_no'], $r['eligible'], $r['reason']]),
        );
    }

    public function exportToppers(McqExam $exam, ?string $schoolId = null, int $limit = 100): StreamedResponse
    {
        $query = McqRegistration::where('exam_id', $exam->id)
            ->whereHas('mark')
            ->with(['student.schoolClass', 'school', 'mark'])
            ->where('attendance_status', '!=', 'absent');

        if ($schoolId) {
            $query->where('school_id', $schoolId);
        }

        $rows = $query->get()
            ->sortBy(fn (McqRegistration $r) => $r->mark?->rank ?? 9999)
            ->take($limit)
            ->values()
            ->map(fn (McqRegistration $reg) => [
                $reg->mark?->rank,
                $reg->student?->name,
                $reg->student?->reg_no,
                $reg->student?->schoolClass?->name,
                $reg->school?->name,
                $reg->mark?->score,
                $reg->mark?->percentage,
                $reg->mark?->grade,
            ]);

        return ExcelExport::download(
            'mcq-toppers-'.$exam->id.($schoolId ? '-school' : ''),
            ['Rank', 'Student', 'Reg. no', 'Class', 'School', 'Score', 'Percentage', 'Grade'],
            $rows,
        );
    }

    public function exportAbsentList(McqExam $exam, ?string $schoolId = null): StreamedResponse
    {
        $rows = collect($this->registrationRows($exam, $schoolId))
            ->filter(fn ($r) => ($r['attendance_status'] ?? '') === 'absent');

        return ExcelExport::download(
            'mcq-absent-'.$exam->id.($schoolId ? '-school' : ''),
            ['Hall ticket', 'Student', 'Reg. no', 'Class', 'School'],
            $rows->map(fn ($r) => [
                $r['hall_ticket_no'],
                $r['student_name'],
                $r['reg_no'],
                $r['class_name'],
                $r['school_name'],
            ]),
        );
    }

    public function exportMarksPending(McqExam $exam, ?string $schoolId = null): StreamedResponse
    {
        $query = McqRegistration::where('exam_id', $exam->id)
            ->where('attendance_status', 'present')
            ->whereDoesntHave('mark')
            ->with(['student.schoolClass', 'school']);

        if ($schoolId) {
            $query->where('school_id', $schoolId);
        }

        $rows = $query->orderBy('hall_ticket_no')->get();

        return ExcelExport::download(
            'mcq-marks-pending-'.$exam->id.($schoolId ? '-school' : ''),
            ['Hall ticket', 'Student', 'Reg. no', 'Class', 'School', 'Attendance'],
            $rows->map(fn (McqRegistration $reg) => [
                $reg->hall_ticket_no,
                $reg->student?->name,
                $reg->student?->reg_no,
                $reg->student?->schoolClass?->name,
                $reg->school?->name,
                $reg->attendance_status,
            ]),
        );
    }

    public function exportPendingFees(McqExam $exam): StreamedResponse
    {
        $rows = collect($this->feeSummaryRows($exam))
            ->filter(fn ($r) => in_array($r['status'] ?? '', ['pending', 'proof_uploaded'], true));

        return ExcelExport::download(
            'mcq-fees-pending-'.$exam->id,
            ['School', 'Students', 'Amount due', 'Status', 'Receipt status'],
            $rows->map(fn ($r) => [
                $r['school_name'],
                $r['student_count'],
                $r['total_due'],
                $r['status'],
                $r['receipt_status'],
            ]),
        );
    }

    public function exportRejectedFees(McqExam $exam): StreamedResponse
    {
        $rows = McqSchoolFee::where('exam_id', $exam->id)
            ->whereHas('feeReceipt', fn ($q) => $q->where('status', 'rejected'))
            ->with(['school', 'feeReceipt'])
            ->get()
            ->map(fn (McqSchoolFee $fee) => [
                $fee->school?->name,
                $fee->student_count,
                $fee->total_due,
                $fee->feeReceipt?->rejection_reason,
                $fee->feeReceipt?->reviewed_at?->format('Y-m-d'),
            ]);

        return ExcelExport::download(
            'mcq-fees-rejected-'.$exam->id,
            ['School', 'Students', 'Amount due', 'Rejection reason', 'Rejected on'],
            $rows,
        );
    }

    public function exportGradeBands(McqExam $exam): StreamedResponse
    {
        $bands = app(McqGradeService::class)->bandsForExam($exam);

        return ExcelExport::download(
            'mcq-grade-bands-'.$exam->id,
            ['Grade', 'Min %', 'Max %', 'Pass', 'Rank eligible', 'Order'],
            collect($bands)->map(fn ($b) => [
                $b['label'] ?? '',
                $b['min_percentage'] ?? '',
                $b['max_percentage'] ?? '',
                ! empty($b['is_pass']) ? 'yes' : 'no',
                ! empty($b['rank_eligible']) ? 'yes' : 'no',
                $b['sort_order'] ?? '',
            ]),
        );
    }

    public function exportSessionStatus(McqExam $exam): StreamedResponse
    {
        $rows = McqRegistration::where('exam_id', $exam->id)
            ->with(['student', 'teacher', 'school', 'mark'])
            ->orderBy('hall_ticket_no')
            ->get()
            ->map(function (McqRegistration $reg) use ($exam) {
                $status = \App\Support\Mcq\McqSessionStatusPresenter::forRegistration($reg, $exam);

                return [
                    $reg->hall_ticket_no,
                    $reg->participantName(),
                    $reg->school?->name,
                    $status['label'],
                    $reg->started_at?->format('Y-m-d H:i'),
                    $reg->submitted_at?->format('Y-m-d H:i'),
                    $reg->mark?->score,
                ];
            });

        return ExcelExport::download(
            'mcq-session-status-'.$exam->id,
            ['Hall ticket', 'Student', 'School', 'Session status', 'Started', 'Submitted', 'Score'],
            $rows,
        );
    }

    /** @return array<string, mixed> */
    public function resultAnalysis(McqExam $exam): array
    {
        $regs = McqRegistration::where('exam_id', $exam->id)
            ->whereHas('mark')
            ->whereNotIn('attendance_status', McqRegistration::BLOCKING_ATTENDANCE_STATUSES)
            ->with('mark')
            ->get();

        $scores = $regs->map(fn (McqRegistration $r) => (float) ($r->mark?->score ?? 0))->sort()->values();
        $count = $scores->count();
        $bands = app(McqGradeService::class)->bandsForExam($exam);
        $passLabels = collect($bands)->filter(fn ($b) => ! empty($b['is_pass']))->pluck('label')->all();

        $passed = $regs->filter(fn (McqRegistration $r) => in_array((string) $r->mark?->grade, $passLabels, true))->count();
        $failed = max(0, $count - $passed);

        $gradeHistogram = [];
        foreach ($bands as $band) {
            $label = (string) $band['label'];
            $gradeHistogram[$label] = $regs->filter(fn ($r) => (string) $r->mark?->grade === $label)->count();
        }

        $percentiles = [];
        foreach ([10, 25, 50, 75, 90, 95] as $p) {
            $percentiles["p{$p}"] = $count === 0 ? null : $this->percentile($scores->all(), $p);
        }

        $mean = $count === 0 ? null : round($scores->avg(), 2);

        return [
            'examined' => $count,
            'passed' => $passed,
            'failed' => $failed,
            'pass_rate' => $count === 0 ? null : round(($passed / $count) * 100, 1),
            'fail_rate' => $count === 0 ? null : round(($failed / $count) * 100, 1),
            'mean_score' => $mean,
            'median_score' => $percentiles['p50'],
            'min_score' => $count === 0 ? null : $scores->first(),
            'max_score' => $count === 0 ? null : $scores->last(),
            'percentiles' => $percentiles,
            'grade_histogram' => $gradeHistogram,
        ];
    }

    public function exportResultAnalysis(McqExam $exam): StreamedResponse
    {
        $analysis = $this->resultAnalysis($exam);
        $rows = [
            ['Examined', $analysis['examined']],
            ['Passed', $analysis['passed']],
            ['Failed', $analysis['failed']],
            ['Pass rate %', $analysis['pass_rate']],
            ['Fail rate %', $analysis['fail_rate']],
            ['Mean score', $analysis['mean_score']],
            ['Median score', $analysis['median_score']],
            ['Min score', $analysis['min_score']],
            ['Max score', $analysis['max_score']],
        ];

        foreach ($analysis['percentiles'] as $key => $value) {
            $rows[] = [strtoupper($key), $value];
        }
        foreach ($analysis['grade_histogram'] as $grade => $count) {
            $rows[] = ["Grade {$grade}", $count];
        }

        return ExcelExport::download(
            'mcq-result-analysis-'.$exam->id,
            ['Metric', 'Value'],
            $rows,
        );
    }

    /** @return list<array<string, mixed>> */
    public function schoolPerformanceRows(McqExam $exam): array
    {
        $bands = app(McqGradeService::class)->bandsForExam($exam);
        $passLabels = collect($bands)->filter(fn ($b) => ! empty($b['is_pass']))->pluck('label')->all();

        $regs = McqRegistration::where('exam_id', $exam->id)
            ->where('status', '!=', 'cancelled')
            ->with(['school:id,name', 'mark'])
            ->get()
            ->groupBy('school_id');

        return $regs->map(function ($group) use ($passLabels) {
            $first = $group->first();
            $withMarks = $group->filter(fn (McqRegistration $r) => $r->mark !== null
                && ! in_array($r->attendance_status, McqRegistration::BLOCKING_ATTENDANCE_STATUSES, true));
            $examined = $withMarks->count();
            $passed = $withMarks->filter(fn (McqRegistration $r) => in_array((string) $r->mark?->grade, $passLabels, true))->count();
            $avg = $examined === 0 ? null : round($withMarks->avg(fn (McqRegistration $r) => (float) $r->mark->score), 2);

            $rankBuckets = [
                'top_10' => $withMarks->filter(fn ($r) => $r->mark?->rank !== null && (int) $r->mark->rank <= 10)->count(),
                'top_50' => $withMarks->filter(fn ($r) => $r->mark?->rank !== null && (int) $r->mark->rank <= 50)->count(),
                'ranked' => $withMarks->filter(fn ($r) => $r->mark?->rank !== null)->count(),
            ];

            return [
                'school_name' => $first->school?->name,
                'registered' => $group->count(),
                'present' => $group->where('attendance_status', 'present')->count(),
                'examined' => $examined,
                'avg_score' => $avg,
                'pass_rate' => $examined === 0 ? null : round(($passed / $examined) * 100, 1),
                'top_10' => $rankBuckets['top_10'],
                'top_50' => $rankBuckets['top_50'],
                'ranked' => $rankBuckets['ranked'],
            ];
        })->sortByDesc('avg_score')->values()->all();
    }

    public function exportSchoolPerformance(McqExam $exam): StreamedResponse
    {
        $rows = $this->schoolPerformanceRows($exam);

        return ExcelExport::download(
            'mcq-school-performance-'.$exam->id,
            ['School', 'Registered', 'Present', 'Examined', 'Avg score', 'Pass rate %', 'Top 10', 'Top 50', 'Ranked'],
            collect($rows)->map(fn ($r) => [
                $r['school_name'],
                $r['registered'],
                $r['present'],
                $r['examined'],
                $r['avg_score'],
                $r['pass_rate'],
                $r['top_10'],
                $r['top_50'],
                $r['ranked'],
            ]),
        );
    }

    public function exportMalpracticeList(McqExam $exam, ?string $schoolId = null): StreamedResponse
    {
        $rows = collect($this->registrationRows($exam, $schoolId))
            ->filter(fn ($r) => in_array($r['attendance_status'] ?? '', ['malpractice', 'withheld'], true));

        return ExcelExport::download(
            'mcq-malpractice-'.$exam->id.($schoolId ? '-school' : ''),
            ['Hall ticket', 'Participant', 'Reg. no', 'Class', 'School', 'Status', 'Note'],
            $rows->map(fn ($r) => [
                $r['hall_ticket_no'],
                $r['student_name'],
                $r['reg_no'],
                $r['class_name'],
                $r['school_name'],
                $r['attendance_status'],
                $r['attendance_note'] ?? '',
            ]),
        );
    }

    /** @param  list<float>  $sortedScores */
    private function percentile(array $sortedScores, int $percentile): float
    {
        $n = count($sortedScores);
        if ($n === 1) {
            return round($sortedScores[0], 2);
        }

        $rank = ($percentile / 100) * ($n - 1);
        $low = (int) floor($rank);
        $high = (int) ceil($rank);
        if ($low === $high) {
            return round($sortedScores[$low], 2);
        }

        $weight = $rank - $low;

        return round($sortedScores[$low] * (1 - $weight) + $sortedScores[$high] * $weight, 2);
    }
}
