<?php

namespace Tests\Unit\Services\BoardResults;

use App\Services\BoardResults\SubjectMeritRegisterService;
use App\Support\BoardExamSubjects;
use PHPUnit\Framework\TestCase;

class SubjectMeritRegisterFallbackTest extends TestCase
{
    public function test_subject_marks_json_normalization(): void
    {
        $marks = BoardExamSubjects::normalizeSubjectMarks([
            'English Core' => 98,
            'Physics' => '',
            'Chemistry' => 95,
        ]);

        $this->assertSame(['English Core' => 98, 'Chemistry' => 95], $marks);
    }

    public function test_service_class_exists_for_wiring(): void
    {
        $this->assertTrue(class_exists(SubjectMeritRegisterService::class));
    }
}
