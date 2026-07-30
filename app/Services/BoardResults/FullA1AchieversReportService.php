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
 * Achievers page, where every subject mark was already required to be >= 91
 * at save time (BoardResultController::storeFullA1AchieversBatch()). This
 * service just lists them Sahodaya-wide, filterable by year/class/stream —
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
            ->whereIn('br.status', [
                BoardResult::STATUS_SUBMITTED,
                BoardResult::STATUS_VERIFIED,
                BoardResult::STATUS_APPROVED,
                BoardResult::STATUS_PUBLISHED,
            ])
            ->select([
                't.id',
                't.name as student_name',
                't.admission_no',
                't.roll_no',
                't.stream',
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

        return $toppers->map(function ($row) use ($names, $subjectStats) {
            $stats = $subjectStats->get($row->id);

            return [
                'student_name' => (string) $row->student_name,
                'school_id' => (string) $row->school_id,
                'school_name' => $names[$row->school_id] ?? (string) $row->school_id,
                'class' => (int) $row->class,
                'stream' => $row->stream,
                'subjects_count' => $stats ? (int) $stats->subjects_count : 0,
                'lowest_mark' => $stats && $stats->lowest_mark !== null ? (float) $stats->lowest_mark : null,
                'admission_no' => $row->admission_no,
                'roll_no' => $row->roll_no,
                'academic_year' => (string) $row->academic_year,
            ];
        })->values()->all();
    }
}
