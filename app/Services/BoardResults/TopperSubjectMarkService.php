<?php

namespace App\Services\BoardResults;

use App\Models\Subject;
use App\Models\Topper;
use App\Models\TopperSubjectMark;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class TopperSubjectMarkService
{
    /**
     * Sync normalized subject mark rows (sole source of truth — no JSON dual-write).
     *
     * @param  array<string, mixed>  $subjectMarks  label => marks
     * @param  array<string, int|null>  $subjectIds  label => subject_id
     */
    public function sync(Topper $topper, array $subjectMarks, array $subjectIds = []): void
    {
        DB::transaction(function () use ($topper, $subjectMarks, $subjectIds) {
            $topper->subjectMarks()->delete();

            foreach ($subjectMarks as $label => $marks) {
                $label = trim((string) $label);
                if ($label === '' || $marks === '' || $marks === null || ! is_numeric($marks)) {
                    continue;
                }
                $value = (float) $marks;
                if ($value < 0 || $value > 100) {
                    continue;
                }

                $subjectId = $subjectIds[$label] ?? $this->resolveSubjectId($label);
                TopperSubjectMark::create([
                    'topper_id' => $topper->id,
                    'subject_id' => $subjectId,
                    'subject_label' => $label,
                    'marks' => $value,
                ]);
            }
        });

        // Refresh in-memory relation for accessors / subsequent reads in this request.
        $topper->unsetRelation('subjectMarks');
        $topper->load('subjectMarks');
    }

    /**
     * Highest scorer per subject across toppers.
     *
     * @return list<array{subject: string, name: string, marks: float, stream: ?string, subject_id: ?int}>
     */
    public function subjectWiseLeaders(Collection $toppers): array
    {
        $topperIds = $toppers->pluck('id')->filter()->all();
        if ($topperIds === []) {
            return [];
        }

        $rows = TopperSubjectMark::query()
            ->whereIn('topper_id', $topperIds)
            ->with('topper')
            ->orderByDesc('marks')
            ->get();

        $leaders = [];
        foreach ($rows as $row) {
            $key = $row->subject_label;
            if (! isset($leaders[$key])) {
                $leaders[$key] = [
                    'subject' => $row->subject_label,
                    'name' => $row->topper?->name ?? '',
                    'marks' => (float) $row->marks,
                    'stream' => $row->topper?->stream,
                    'subject_id' => $row->subject_id,
                ];
            }
        }
        ksort($leaders);

        return array_values($leaders);
    }

    private function resolveSubjectId(string $label): ?int
    {
        try {
            $id = Subject::query()
                ->where(function ($q) use ($label) {
                    $q->where('label', $label)->orWhere('code', $label);
                })
                ->value('id');

            return $id ? (int) $id : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
