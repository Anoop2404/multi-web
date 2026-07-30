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
                if ($value < 0) {
                    continue;
                }

                $subjectId = $subjectIds[$label] ?? $this->resolveOrCreateSubjectId($label);
                TopperSubjectMark::create([
                    'topper_id' => $topper->id,
                    'tenant_id' => $topper->tenant_id,
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
     * Grouped by subject_id (falling back to a lower-cased label key only when
     * subject_id is still unresolved on legacy rows) so "English core" and
     * "English Core" always collapse into one bucket instead of splitting the
     * leaderboard by casing (#161).
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

        // Canonical display label per subject_id, so every row that resolved to the
        // same subject shows the same text regardless of how it was originally typed.
        $subjectLabels = Subject::query()
            ->whereIn('id', $rows->pluck('subject_id')->filter()->unique()->all())
            ->pluck('label', 'id');

        $leaders = [];
        foreach ($rows as $row) {
            $key = $row->subject_id !== null
                ? 'id:'.$row->subject_id
                : 'label:'.mb_strtolower(trim($row->subject_label));
            $displayLabel = $row->subject_id !== null
                ? ($subjectLabels[$row->subject_id] ?? $row->subject_label)
                : $row->subject_label;
            $marks = (float) $row->marks;

            if (! isset($leaders[$key])) {
                $leaders[$key] = [
                    'subject' => $displayLabel,
                    'subject_id' => $row->subject_id,
                    'entries' => [],
                    'top_marks' => $marks,
                ];
            }

            // Collect ALL students tied at the top marks, not just the first.
            if ($marks > $leaders[$key]['top_marks']) {
                $leaders[$key] = [
                    'subject' => $displayLabel,
                    'subject_id' => $row->subject_id,
                    'entries' => [[
                        'name' => $row->topper?->name ?? '',
                        'marks' => $marks,
                        'stream' => $row->topper?->stream,
                    ]],
                    'top_marks' => $marks,
                ];
            } elseif ($marks === $leaders[$key]['top_marks']) {
                $leaders[$key]['entries'][] = [
                    'name' => $row->topper?->name ?? '',
                    'marks' => $marks,
                    'stream' => $row->topper?->stream,
                ];
            }
        }
        ksort($leaders);

        return collect($leaders)->map(fn ($leader) => [
            'subject'    => $leader['subject'],
            'name'       => $leader['entries'][0]['name'] ?? '',   // first leader for backward compat
            'marks'      => $leader['entries'][0]['marks'] ?? 0,
            'stream'     => $leader['entries'][0]['stream'] ?? null,
            'entries'    => $leader['entries'],                     // all tied leaders
            'top_marks'  => $leader['top_marks'],
            'subject_id' => $leader['subject_id'],
        ])->values()->all();
    }

    /**
     * Resolve a subject label to its canonical central Subject.id, matched
     * case-insensitively against both label and code so "English core" and
     * "English Core" always resolve to the same row. Auto-creates a global
     * Subject the first time a genuinely new label is seen, so every school/
     * Sahodaya converges on one id for that subject from then on (#161).
     */
    public function resolveOrCreateSubjectId(string $label): ?int
    {
        $label = trim($label);
        if ($label === '') {
            return null;
        }

        try {
            $existing = Subject::query()
                ->where(function ($q) use ($label) {
                    $q->whereRaw('LOWER(label) = ?', [mb_strtolower($label)])
                        ->orWhereRaw('LOWER(code) = ?', [mb_strtolower($label)]);
                })
                ->orderByRaw('sahodaya_id is null desc') // prefer a global canonical row over a sahodaya-specific one
                ->value('id');

            if ($existing) {
                return (int) $existing;
            }

            $code = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $label), 0, 10));
            if ($code === '') {
                $code = 'SUBJ'.random_int(1000, 9999);
            }

            $created = Subject::firstOrCreate(
                ['sahodaya_id' => null, 'code' => $code],
                ['label' => $label, 'is_active' => true, 'sort_order' => 0],
            );

            return (int) $created->id;
        } catch (\Throwable) {
            return null;
        }
    }
}
