<?php

namespace App\Services\BoardResults;

use App\Models\BoardResult;
use App\Models\Tenant;
use App\Models\Topper;
use App\Support\TenancyDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Full A1 Achievers report (#161): students entered on the school-side Full A1
 * Achievers page (based on CBSE A1 top 1/8th percentile rules). This
 * service lists them Sahodaya-wide, filterable by year/class/stream —
 * same shape and read pattern as SubjectMeritRegisterService.
 */
class FullA1AchieversReportService
{
    /**
     * @return list<array{
     *   student_name: string,
     *   school_id: string,
     *   school_name: string,
     *   class: int,
     *   stream: string|null,
     *   subjects_count: int,
     *   lowest_mark: float|null,
     *   admission_no: string|null,
     *   roll_no: string|null,
     *   academic_year: string
     * }>
     */
    public function list(string $sahodayaId, string $academicYear, ?int $class = null, ?string $stream = null): array
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
        $sahodaya = Tenant::query()->where('id', $sahodayaId)->where('type', 'sahodaya')->first();

        if (! $sahodaya) {
            return [];
        }

        return TenancyDatabase::whenDatabaseReady(
            $sahodaya,
            fn () => $this->fromTable($schoolIds, $academicYear, $class, $stream, $names),
            [],
        ) ?? [];
    }

    /**
     * @param  list<string>  $schoolIds
     * @param  Collection<string, string>  $names
     * @return list<array<string, mixed>>
     */
    private function fromTable(array $schoolIds, string $academicYear, ?int $class, ?string $stream, Collection $names): array
    {
        if (! Schema::hasTable('toppers')) {
            return [];
        }

        $query = DB::table('toppers as t')
            ->join('board_results as br', 'br.id', '=', 't.board_result_id')
            ->where('t.entry_type', Topper::ENTRY_FULL_A1)
            ->whereIn('br.tenant_id', $schoolIds)
            ->where('br.academic_year', $academicYear)
            ->where('br.status', '!=', BoardResult::STATUS_REJECTED)
            ->where('t.verification_status', 'verified')
            ->select([
                't.id',
                't.board_result_id',
                't.name as student_name',
                't.admission_no',
                't.roll_no',
                't.stream',
                't.verification_status',
                't.rejection_reason',
                't.marksheet_path',
                't.tenant_id as school_id',
                'br.class',
                'br.academic_year',
            ]);

        if ($class !== null) {
            $query->where('br.class', $class);
        }
        if ($stream !== null) {
            $query->where('t.stream', $stream);
        }

        $toppers = $query->orderBy('t.name')->get();
        $topperIds = $toppers->pluck('id')->all();

        $subjectStats = ($topperIds !== [] && Schema::hasTable('topper_subject_marks'))
            ? DB::table('topper_subject_marks')
                ->whereIn('topper_id', $topperIds)
                ->selectRaw('topper_id, COUNT(*) as subjects_count, MIN(marks) as lowest_mark')
                ->groupBy('topper_id')
                ->get()
                ->keyBy('topper_id')
            : collect();

        $subjectMarksList = ($topperIds !== [] && Schema::hasTable('topper_subject_marks'))
            ? DB::table('topper_subject_marks')
                ->whereIn('topper_id', $topperIds)
                ->orderBy('subject_label')
                ->get()
                ->groupBy('topper_id')
            : collect();

        return $toppers->map(function ($row) use ($names, $subjectStats, $subjectMarksList) {
            $stats = $subjectStats->get($row->id);
            $marks = $subjectMarksList->get($row->id, collect())->map(function ($m) use ($row) {
                $code = ((int) $row->class) === 10
                    ? \App\Support\CbseSubjectCodes::forClass10Label($m->subject_label)
                    : \App\Support\CbseSubjectCodes::forClass12Label($m->subject_label);

                return [
                    'subject_label' => (string) $m->subject_label,
                    'subject_code' => $code,
                    'marks' => (float) $m->marks,
                    'grade' => 'A1',
                ];
            })->values()->all();

            $marksheetUrl = $row->marksheet_path ? \App\Support\TenantStorage::url($row->marksheet_path) : null;

            return [
                'id' => (int) $row->id,
                'board_result_id' => (int) $row->board_result_id,
                'student_name' => (string) $row->student_name,
                'school_id' => (string) $row->school_id,
                'school_name' => $names[$row->school_id] ?? (string) $row->school_id,
                'class' => (int) $row->class,
                'stream' => $row->stream,
                'subjects_count' => $stats ? (int) $stats->subjects_count : count($marks),
                'lowest_mark' => $stats && $stats->lowest_mark !== null ? (float) $stats->lowest_mark : null,
                'admission_no' => $row->admission_no,
                'roll_no' => $row->roll_no,
                'academic_year' => (string) $row->academic_year,
                'verification_status' => $row->verification_status ?? 'pending',
                'rejection_reason' => $row->rejection_reason,
                'marksheet_url' => $marksheetUrl,
                'subject_marks' => $marks,
            ];
        })->values()->all();
    }
}
