<?php

namespace App\Services\Mcq;

use App\Models\McqExam;
use App\Models\McqQuestion;
use App\Models\McqQuestionBank;
use App\Models\McqRegistration;
use Illuminate\Support\Collection;
use Random\Engine\Mt19937;
use Random\Randomizer;

/**
 * Builds an exam's paper from its sections (the exam ↔ bank links, in section order).
 * Each section takes all of its bank's questions, the first N, or N picked at random.
 * Random picks are seeded by exam + bank, so every candidate gets the same paper and it
 * never changes between requests; the per-candidate shuffle is seeded by registration.
 */
class McqPaperService
{
    public const PICK_MODES = ['all', 'first', 'random'];

    /** @return Collection<int, McqQuestion> */
    public function questionsForExam(McqExam $exam): Collection
    {
        $exam->loadMissing('questionBanks.questions');

        $questions = $exam->questionBanks
            ->flatMap(fn (McqQuestionBank $bank) => $this->pickFromSection($exam, $bank))
            ->values();

        $limit = (int) ($exam->total_questions ?: 0);
        if ($limit > 0 && $questions->count() > $limit) {
            $questions = $questions->take($limit)->values();
        }

        return $questions;
    }

    /**
     * The paper as one candidate sees it: shuffled with a seed from the registration when the
     * exam has "shuffle questions" on, so a reload or a second device shows the same order.
     *
     * @return Collection<int, McqQuestion>
     */
    public function questionsForRegistration(McqRegistration $registration): Collection
    {
        $registration->loadMissing('exam');
        $questions = $this->questionsForExam($registration->exam);

        if (! ($registration->exam->settings_json['shuffle_questions'] ?? false)) {
            return $questions;
        }

        return collect(self::seededShuffle($questions->all(), 'reg:'.$registration->id))->values();
    }

    /** @return Collection<int, McqQuestion> */
    private function pickFromSection(McqExam $exam, McqQuestionBank $bank): Collection
    {
        $questions = $bank->questions->sortBy([['display_order', 'asc'], ['id', 'asc']])->values();
        $mode = in_array($bank->pivot?->pick_mode, self::PICK_MODES, true) ? $bank->pivot->pick_mode : 'all';
        $count = (int) ($bank->pivot?->pick_count ?? 0);

        if ($mode === 'all' || $count <= 0 || $count >= $questions->count()) {
            return $questions;
        }

        if ($mode === 'first') {
            return $questions->take($count)->values();
        }

        // Random N, kept in the bank's own order so numbering stays readable.
        $picked = collect(self::seededShuffle($questions->all(), "exam:{$exam->id}:bank:{$bank->id}"))
            ->take($count)
            ->pluck('id')
            ->all();

        return $questions->filter(fn (McqQuestion $q) => in_array($q->id, $picked, true))->values();
    }

    /**
     * Section-by-section summary for the paper builder: counts, marks, question types and the
     * problems that would stop students starting or being marked correctly.
     *
     * @return array<string, mixed>
     */
    public function summary(McqExam $exam): array
    {
        $exam->loadMissing('questionBanks.questions');
        $paper = $this->questionsForExam($exam);

        $sections = $exam->questionBanks->map(function (McqQuestionBank $bank) use ($exam) {
            $picked = $this->pickFromSection($exam, $bank);

            return [
                'bank_id'         => $bank->id,
                'bank_title'      => $bank->title,
                'bank_status'     => $bank->status,
                'subject'         => $bank->subject,
                'section_title'   => $bank->pivot?->section_title,
                'pick_mode'       => $bank->pivot?->pick_mode ?: 'all',
                'pick_count'      => $bank->pivot?->pick_count,
                'display_order'   => (int) ($bank->pivot?->display_order ?? 0),
                'bank_questions'  => $bank->questions->count(),
                'picked'          => $picked->count(),
                'marks'           => round($picked->filter->isGradable()->sum(fn (McqQuestion $q) => $q->marksValue()), 2),
            ];
        })->values();

        $gradable = $paper->filter->isGradable();
        $typeCounts = collect(McqQuestion::TYPES)
            ->mapWithKeys(fn ($type) => [$type => $paper->filter(fn (McqQuestion $q) => $q->typeValue() === $type)->count()])
            ->all();

        $warnings = [];
        $ungradable = $paper->count() - $gradable->count();
        if ($ungradable > 0) {
            $warnings[] = "{$ungradable} question(s) on the paper have no answer key and won't be marked.";
        }
        $limit = (int) ($exam->total_questions ?: 0);
        $uncapped = $exam->questionBanks->sum(fn (McqQuestionBank $bank) => $this->pickFromSection($exam, $bank)->count());
        if ($limit > 0 && $uncapped > $limit) {
            $warnings[] = "The exam's \"total questions\" setting ({$limit}) cuts the paper from {$uncapped} to {$limit} questions.";
        }
        if ($this->needsExamEngine($paper) && $exam->isOnlineDelivery() && ! \App\Services\ExamEngine\ExamEngineRun::usesEngine($exam)) {
            $warnings[] = 'This paper has multiple-correct or numeric questions, which need the online exam engine. Turn on "Use the online exam engine" below, or use only single-correct and true/false questions.';
        }
        foreach ($exam->questionBanks as $bank) {
            if ($bank->status !== 'active') {
                $warnings[] = "Bank \"{$bank->title}\" is no longer marked Ready.";
            }
        }

        return [
            'sections'      => $sections,
            'total'         => $paper->count(),
            'gradable'      => $gradable->count(),
            'max_score'     => round($gradable->sum(fn (McqQuestion $q) => $q->marksValue()), 2),
            'type_counts'   => $typeCounts,
            'warnings'      => $warnings,
            'locked'        => McqRegistration::where('exam_id', $exam->id)->whereNotNull('started_at')->exists(),
        ];
    }

    /** True when the paper uses question types the in-app online exam can't deliver. */
    public function needsExamEngine(Collection $paper): bool
    {
        return $paper->contains(fn (McqQuestion $q) => ! in_array($q->typeValue(), McqQuestion::ONE_OPTION_TYPES, true));
    }

    /**
     * @template T
     * @param  list<T>  $items
     * @return list<T>
     */
    public static function seededShuffle(array $items, string $seed): array
    {
        return (new Randomizer(new Mt19937(crc32($seed))))->shuffleArray(array_values($items));
    }
}
