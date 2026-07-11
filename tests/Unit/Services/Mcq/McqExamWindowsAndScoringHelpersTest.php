<?php

namespace Tests\Unit\Services\Mcq;

use App\Models\McqExam;
use App\Models\McqQuestion;
use Carbon\Carbon;
use Tests\TestCase;

class McqExamWindowsAndScoringHelpersTest extends TestCase
{
    public function test_registration_window_null_when_dates_unset(): void
    {
        $exam = new McqExam(['status' => 'published']);
        $this->assertNull($exam->registrationWindowActive());
        $this->assertTrue($exam->isRegistrationOpen());
    }

    public function test_registration_window_blocks_before_opens(): void
    {
        Carbon::setTestNow('2026-07-10 10:00:00');
        $exam = new McqExam([
            'status' => 'published',
            'registration_opens_at' => Carbon::parse('2026-07-12 09:00:00'),
        ]);
        $this->assertFalse($exam->registrationWindowActive());
        $this->assertFalse($exam->isRegistrationOpen());
        Carbon::setTestNow();
    }

    public function test_registration_window_blocks_after_closes(): void
    {
        Carbon::setTestNow('2026-07-20 10:00:00');
        $exam = new McqExam([
            'status' => 'published',
            'registration_opens_at' => Carbon::parse('2026-07-01 09:00:00'),
            'registration_closes_at' => Carbon::parse('2026-07-15 18:00:00'),
        ]);
        $this->assertFalse($exam->isRegistrationOpen());
        Carbon::setTestNow();
    }

    public function test_registration_open_inside_window(): void
    {
        Carbon::setTestNow('2026-07-10 12:00:00');
        $exam = new McqExam([
            'status' => 'published',
            'registration_opens_at' => Carbon::parse('2026-07-01 09:00:00'),
            'registration_closes_at' => Carbon::parse('2026-07-15 18:00:00'),
        ]);
        $this->assertTrue($exam->isRegistrationOpen());
        Carbon::setTestNow();
    }

    public function test_question_marks_defaults(): void
    {
        $q = new McqQuestion([]);
        $this->assertSame(1.0, $q->marksValue());
        $this->assertSame(0.0, $q->negativeMarkValue());

        $weighted = new McqQuestion(['marks' => 2.5, 'negative_mark' => 0.5]);
        $this->assertSame(2.5, $weighted->marksValue());
        $this->assertSame(0.5, $weighted->negativeMarkValue());
    }
}
