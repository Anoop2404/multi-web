<?php

namespace Tests\Unit\Services\Mcq;

use App\Models\McqQuestion;
use App\Services\Mcq\McqGrader;
use Tests\TestCase;

class McqGraderTest extends TestCase
{
    private function optionList(int $count = 4): array
    {
        return collect(array_slice(McqQuestion::OPTION_KEYS, 0, $count))
            ->map(fn ($key) => ['key' => $key, 'label' => strtoupper($key)])
            ->all();
    }

    private function single(int $id, string $key, float $marks = 1, float $negative = 0): McqQuestion
    {
        $q = new McqQuestion(['type' => 'single', 'options_json' => $this->optionList(), 'correct_option_key' => $key, 'marks' => $marks, 'negative_mark' => $negative]);
        $q->id = $id;

        return $q;
    }

    private function multiple(int $id, array $keys, float $marks = 4, float $negative = 0): McqQuestion
    {
        $q = new McqQuestion(['type' => 'multiple', 'options_json' => $this->optionList(), 'answer_json' => ['keys' => $keys], 'marks' => $marks, 'negative_mark' => $negative]);
        $q->id = $id;

        return $q;
    }

    private function numeric(int $id, float $value, float $tolerance = 0, float $marks = 2, float $negative = 0): McqQuestion
    {
        $q = new McqQuestion(['type' => 'numeric', 'answer_json' => ['value' => $value, 'tolerance' => $tolerance], 'marks' => $marks, 'negative_mark' => $negative]);
        $q->id = $id;

        return $q;
    }

    public function test_single_correct_matches_the_previous_scoring_rules(): void
    {
        $paper = collect([
            $this->single(1, 'b', 2, 0.5),
            $this->single(2, 'c', 2, 0.5),
            $this->single(3, 'a', 2, 0.5),
        ]);

        $result = (new McqGrader)->grade($paper, ['1' => 'b', '2' => 'a']);

        $this->assertSame(1, $result['correct']);
        $this->assertSame(1, $result['wrong']);
        $this->assertSame(1, $result['unanswered']);
        $this->assertSame(1.5, $result['score']);
        $this->assertSame(6.0, $result['max_score']);
        $this->assertSame(25.0, $result['percentage']);
        $this->assertSame('b', $result['answers'][0]['chosen']);
        $this->assertSame('b', $result['answers'][0]['correct']);
        $this->assertTrue($result['answers'][0]['is_correct']);
    }

    public function test_single_answer_is_case_insensitive_and_blank_strings_are_unanswered(): void
    {
        $result = (new McqGrader)->grade(collect([$this->single(1, 'b'), $this->single(2, 'c', 1, 1)]), [1 => 'B', 2 => '']);

        $this->assertSame(1, $result['correct']);
        $this->assertSame(0, $result['wrong']);
        $this->assertSame(1, $result['unanswered']);
        $this->assertSame(1.0, $result['score']);
    }

    public function test_multiple_all_or_nothing(): void
    {
        $q = $this->multiple(1, ['a', 'c'], 4, 1);
        $grader = new McqGrader;

        $this->assertSame(4.0, $grader->grade(collect([$q]), [1 => ['c', 'a']])['score']);
        $this->assertSame(4.0, $grader->grade(collect([$q]), [1 => 'a,c'])['score']);
        $this->assertSame(-1.0, $grader->grade(collect([$q]), [1 => ['a']])['score']);
        $this->assertSame(-1.0, $grader->grade(collect([$q]), [1 => ['a', 'b', 'c']])['score']);
        $this->assertSame(0.0, $grader->grade(collect([$q]), [1 => []])['score']);
        $this->assertSame(1, $grader->grade(collect([$q]), [])['unanswered']);
    }

    public function test_multiple_partial_marking(): void
    {
        $q = $this->multiple(1, ['a', 'b', 'd'], 3, 1);
        $grader = new McqGrader;

        $subset = $grader->grade(collect([$q]), [1 => ['a', 'd']], 'partial');
        $this->assertSame(2.0, $subset['score']);
        $this->assertSame(1, $subset['partial']);
        $this->assertSame(0, $subset['unanswered']);
        $this->assertFalse($subset['answers'][0]['is_correct']);

        $this->assertSame(3.0, $grader->grade(collect([$q]), [1 => ['a', 'b', 'd']], 'partial')['score']);
        $this->assertSame(-1.0, $grader->grade(collect([$q]), [1 => ['a', 'c']], 'partial')['score']);
    }

    public function test_numeric_with_and_without_tolerance(): void
    {
        $exact = $this->numeric(1, 12.5, 0, 2, 0.5);
        $loose = $this->numeric(2, 3.14, 0.01, 2, 0.5);
        $grader = new McqGrader;

        $this->assertSame(2.0, $grader->grade(collect([$exact]), [1 => '12.50'])['score']);
        $this->assertSame(-0.5, $grader->grade(collect([$exact]), [1 => '12.6'])['score']);
        $this->assertSame(-0.5, $grader->grade(collect([$exact]), [1 => 'twelve'])['score']);
        $this->assertSame(0.0, $grader->grade(collect([$exact]), [1 => '  '])['score']);
        $this->assertSame(2.0, $grader->grade(collect([$loose]), [2 => '3.15'])['score']);
        $this->assertSame(-0.5, $grader->grade(collect([$loose]), [2 => '3.16'])['score']);
    }

    public function test_questions_without_a_key_are_not_marked(): void
    {
        $reference = new McqQuestion(['type' => 'single', 'body_text' => 'Read the passage']);
        $reference->id = 9;
        $badMulti = $this->multiple(10, ['z']);

        $result = (new McqGrader)->grade(collect([$reference, $badMulti, $this->single(1, 'a')]), [1 => 'a', 9 => 'a']);

        $this->assertCount(1, $result['answers']);
        $this->assertSame(1.0, $result['max_score']);
        $this->assertSame(100.0, $result['percentage']);
    }

    public function test_negative_score_still_reports_zero_percent(): void
    {
        $result = (new McqGrader)->grade(collect([$this->single(1, 'a', 1, 2)]), [1 => 'b']);

        $this->assertSame(-2.0, $result['score']);
        $this->assertSame(0.0, $result['percentage']);
    }
}
