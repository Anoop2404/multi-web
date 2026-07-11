<?php

namespace Tests\Unit\Services\BoardResults;

use App\Support\BoardExamSubjects;
use PHPUnit\Framework\TestCase;

class BoardExamSubjectsCutoverTest extends TestCase
{
    public function test_normalize_subject_marks_still_works_without_json_column(): void
    {
        $marks = BoardExamSubjects::normalizeSubjectMarks([
            'English Core' => 98,
            'Physics' => '',
            'Chemistry' => 95,
            'Bad' => 101,
        ]);

        $this->assertSame(['English Core' => 98, 'Chemistry' => 95], $marks);
    }

    public function test_hardcoded_fallback_labels_method_removed(): void
    {
        $this->assertFalse(
            (new \ReflectionClass(BoardExamSubjects::class))->hasMethod('fallbackLabels'),
            'Hardcoded fallbackLabels must be removed after exam_streams cutover'
        );
    }
}
