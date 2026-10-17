<?php

namespace App\Services\Mcq;

use App\Models\McqQuestion;
use Illuminate\Support\Collection;

/**
 * Marks one candidate's answers against a paper. Shared by the in-app online exam
 * (McqExamSessionService::submit) and, from Phase 2, the exam engine's result consumer —
 * so both paths always produce identical scores. See docs/ONLINE_EXAM_ENGINE_PLAN_2026_10_15.md §7.
 *
 * Per gradable question:
 *   single /  correct key            → +marks; another key → −negative_mark; blank → 0
 *   true_false
 *   multiple  all_or_nothing         → exact set → +marks; anything else picked → −negative_mark
 *             partial                → any wrong option → −negative_mark; otherwise
 *                                      +marks × picked ÷ total correct
 *   numeric   within ±tolerance      → +marks; any other number/text → −negative_mark; blank → 0
 *
 * max_score = Σ marks; percentage = max(0, score) ÷ max_score × 100.
 * Non-gradable questions (no key, reference material) are skipped entirely.
 */
class McqGrader
{
    /**
     * @param  Collection<int, McqQuestion>  $questions
     * @param  array<int|string, mixed>  $answers  question id => option key | list of keys | number
     * @return array{correct: int, wrong: int, partial: int, unanswered: int, score: float, max_score: float, percentage: float, answers: list<array<string, mixed>>}
     */
    public function grade(Collection $questions, array $answers, string $multiMarking = 'all_or_nothing'): array
    {
        $correct = 0;
        $wrong = 0;
        $partial = 0;
        $score = 0.0;
        $maxScore = 0.0;
        $stored = [];

        foreach ($questions->filter(fn (McqQuestion $q) => $q->isGradable()) as $question) {
            $marks = $question->marksValue();
            $negative = $question->negativeMarkValue();
            $maxScore += $marks;
            $raw = $answers[(string) $question->id] ?? $answers[$question->id] ?? null;

            $result = match ($question->typeValue()) {
                McqQuestion::TYPE_MULTIPLE => $this->gradeMultiple($question, $raw, $marks, $negative, $multiMarking),
                McqQuestion::TYPE_NUMERIC  => $this->gradeNumeric($question, $raw, $marks, $negative),
                // single and true_false: one option is right
                default                    => $this->gradeSingle($question, $raw, $marks, $negative),
            };

            match ($result['outcome']) {
                'correct' => $correct++,
                'wrong'   => $wrong++,
                'partial' => $partial++,
                default   => null,
            };
            $score += $result['awarded'];

            $stored[] = [
                'question_id'   => $question->id,
                'type'          => $question->typeValue(),
                'chosen'        => $result['chosen'],
                'correct'       => $result['correct'],
                'is_correct'    => $result['outcome'] === 'correct',
                'awarded'       => round($result['awarded'], 2),
                'marks'         => $marks,
                'negative_mark' => $negative,
            ];
        }

        $score = round($score, 2);
        $maxScore = round($maxScore, 2);

        return [
            'correct'    => $correct,
            'wrong'      => $wrong,
            'partial'    => $partial,
            'unanswered' => max(0, count($stored) - $correct - $wrong - $partial),
            'score'      => $score,
            'max_score'  => $maxScore,
            'percentage' => $maxScore > 0 ? round((max(0, $score) / $maxScore) * 100, 2) : 0.0,
            'answers'    => $stored,
        ];
    }

    /** @return array{outcome: string, awarded: float, chosen: ?string, correct: ?string} */
    private function gradeSingle(McqQuestion $question, mixed $raw, float $marks, float $negative): array
    {
        $chosen = is_scalar($raw) && trim((string) $raw) !== '' ? strtolower(trim((string) $raw)) : null;
        $key = $question->correctKeys()[0];

        if ($chosen === null) {
            return ['outcome' => 'blank', 'awarded' => 0.0, 'chosen' => null, 'correct' => $key];
        }

        return $chosen === $key
            ? ['outcome' => 'correct', 'awarded' => $marks, 'chosen' => $chosen, 'correct' => $key]
            : ['outcome' => 'wrong', 'awarded' => -$negative, 'chosen' => $chosen, 'correct' => $key];
    }

    /** @return array{outcome: string, awarded: float, chosen: ?list<string>, correct: list<string>} */
    private function gradeMultiple(McqQuestion $question, mixed $raw, float $marks, float $negative, string $mode): array
    {
        $chosen = self::normalizeKeyList($raw);
        $keys = $question->correctKeys();

        if ($chosen === []) {
            return ['outcome' => 'blank', 'awarded' => 0.0, 'chosen' => null, 'correct' => $keys];
        }

        if ($chosen === $keys) {
            return ['outcome' => 'correct', 'awarded' => $marks, 'chosen' => $chosen, 'correct' => $keys];
        }

        $pickedWrong = array_diff($chosen, $keys) !== [];
        if ($mode !== 'partial' || $pickedWrong) {
            return ['outcome' => 'wrong', 'awarded' => -$negative, 'chosen' => $chosen, 'correct' => $keys];
        }

        // Partial mode, only correct options picked but not all of them.
        return [
            'outcome' => 'partial',
            'awarded' => $marks * count($chosen) / count($keys),
            'chosen'  => $chosen,
            'correct' => $keys,
        ];
    }

    /** @return array{outcome: string, awarded: float, chosen: ?string, correct: string} */
    private function gradeNumeric(McqQuestion $question, mixed $raw, float $marks, float $negative): array
    {
        $chosen = is_scalar($raw) ? trim((string) $raw) : '';
        $key = $question->numericKey();
        $label = $question->answerKeyLabel();

        if ($chosen === '') {
            return ['outcome' => 'blank', 'awarded' => 0.0, 'chosen' => null, 'correct' => $label];
        }

        $isCorrect = is_numeric($chosen)
            && abs((float) $chosen - $key['value']) <= $key['tolerance'] + 1e-9;

        return $isCorrect
            ? ['outcome' => 'correct', 'awarded' => $marks, 'chosen' => $chosen, 'correct' => $label]
            : ['outcome' => 'wrong', 'awarded' => -$negative, 'chosen' => $chosen, 'correct' => $label];
    }

    /** Accepts a list of keys or a comma-separated string; returns sorted unique lowercase keys. */
    public static function normalizeKeyList(mixed $raw): array
    {
        $items = is_array($raw) ? $raw : (is_scalar($raw) ? explode(',', (string) $raw) : []);

        return collect($items)
            ->filter(fn ($key) => is_scalar($key))
            ->map(fn ($key) => strtolower(trim((string) $key)))
            ->filter(fn ($key) => $key !== '')
            ->unique()
            ->sort()
            ->values()
            ->all();
    }
}
