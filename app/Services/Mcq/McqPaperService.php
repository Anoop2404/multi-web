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
        if ($registration->paper_snapshot) return $this->hydrate($registration->paper_snapshot['questions']);
        return $this->buildForRegistration($registration)['questions'];
    }

    /** Freeze the actual ordered paper, including answer keys, before the first start. */
    public function freezeForRegistration(McqRegistration $registration): void
    {
        if ($registration->paper_snapshot) return;
        $paper = $this->buildForRegistration($registration);
        $registration->update(['paper_snapshot' => [
            'questions' => $paper['questions']->map->toArray()->all(),
            'template_title' => $paper['title'], 'template_version' => $paper['version'],
            'total_marks' => $paper['questions']->sum(fn ($q) => $q->marksValue()),
            'pass_mark' => $paper['pass_mark'], 'multi_marking' => $registration->exam->multiMarkingMode(),
            'schedule' => $registration->exam->scheduleForClass($registration->student?->schoolClass?->name),
        ]]);
    }

    public function buildForRegistration(McqRegistration $registration): array
    {
        $registration->loadMissing('exam');
        $exam = $registration->exam;
        $service = app(McqPaperTemplateService::class);
        $assignment = $service->assignment($registration);
        if (! $assignment && ! empty($exam->settings_json['paper_assignments']) && ($exam->settings_json['paper_fallback'] ?? 'require') === 'require') {
            $service->fail('No paper template is assigned to this candidate’s class. Ask the exam administrator to assign one.');
        }
        $questions = $assignment ? $this->hydrate($assignment['questions']) : $this->questionsForExam($exam);
        $shuffle = $assignment['shuffle_questions'] ?? (($exam->settings_json['shuffle_questions'] ?? false) ? 'all' : 'none');
        if ($shuffle === 'sections') {
            $questions = $questions->groupBy(fn ($q) => $q->section_index ?? $q->bank_id)
                ->flatMap(fn ($group, $section) => self::seededShuffle($group->all(), 'reg:'.$registration->id.':section:'.$section))->values();
        } elseif ($shuffle === 'all') {
            $questions = collect(self::seededShuffle($questions->all(), 'reg:'.$registration->id))->values();
        }
        if ($assignment['shuffle_options'] ?? $exam->settings_json['shuffle_options'] ?? false) {
            $questions = $questions->map(function ($q) use ($registration) {
                $q = clone $q;
                if ($q->typeValue() === 'single' || $q->typeValue() === 'multiple') {
                    $q->options_json = self::seededShuffle($q->options_json ?? [], 'reg:'.$registration->id.':question:'.$q->id);
                }
                return $q;
            });
        }
        return ['questions' => $questions, 'title' => $assignment['title'] ?? 'Default paper',
            'version' => $assignment['version'] ?? null, 'pass_mark' => $assignment['pass_mark'] ?? $exam->pass_mark ?? 0];
    }

    private function hydrate(array $rows): Collection
    {
        return collect($rows)->map(fn ($row) => (new McqQuestion)->forceFill($row));
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
