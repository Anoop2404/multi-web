<?php

namespace Tests\Unit\Support;

use App\Support\ReportFilename;
use Carbon\Carbon;
use Tests\TestCase;

class ReportFilenameTest extends TestCase
{
    public function test_builds_purpose_subject_date_segments_in_order(): void
    {
        $filename = ReportFilename::build(
            'registration-list',
            'Thrissur Kalolsavam',
            Carbon::create(2026, 7, 15),
        );

        $this->assertSame('registration-list_thrissur-kalolsavam_2026-07-15.pdf', $filename);
    }

    public function test_defaults_to_today_when_no_date_given(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 7, 26));

        $filename = ReportFilename::build('students', 'St Marys HS', null, [], 'xlsx');

        $this->assertSame('students_st-marys-hs_2026-07-26.xlsx', $filename);

        Carbon::setTestNow();
    }

    public function test_subject_title_is_slugged_and_truncated_to_forty_chars(): void
    {
        $longTitle = 'This Is A Very Long Kalolsavam Event Title That Exceeds Forty Characters Easily';

        $filename = ReportFilename::build('overall-ranking', $longTitle, Carbon::create(2026, 1, 1));

        [$purpose, $subject, $date] = explode('_', $filename);

        $this->assertSame('overall-ranking', $purpose);
        $this->assertLessThanOrEqual(40, strlen($subject));
        $this->assertSame('2026-01-01.pdf', $date);
    }

    public function test_extra_segments_are_appended_in_order(): void
    {
        $filename = ReportFilename::build(
            'attendance-sheet',
            'Thrissur Kalolsavam',
            Carbon::create(2026, 7, 15),
            ['St Marys HS'],
        );

        $this->assertSame('attendance-sheet_thrissur-kalolsavam_st-marys-hs_2026-07-15.pdf', $filename);
    }

    public function test_null_and_empty_extra_segments_are_skipped(): void
    {
        $filename = ReportFilename::build(
            'judge-sheet',
            'Thrissur Kalolsavam',
            Carbon::create(2026, 7, 15),
            [null, '', 42],
        );

        $this->assertSame('judge-sheet_thrissur-kalolsavam_42_2026-07-15.pdf', $filename);
    }

    public function test_extension_leading_dot_is_stripped(): void
    {
        $filename = ReportFilename::build(
            'students',
            'St Marys HS',
            Carbon::create(2026, 7, 15),
            [],
            '.xlsx',
        );

        $this->assertSame('students_st-marys-hs_2026-07-15.xlsx', $filename);
    }

    public function test_default_extension_is_pdf(): void
    {
        $filename = ReportFilename::build('certificates', 'Thrissur Kalolsavam', Carbon::create(2026, 7, 15));

        $this->assertStringEndsWith('.pdf', $filename);
    }

    public function test_attendance_filename_identifies_sahodaya_event_and_item(): void
    {
        $filename = ReportFilename::build(
            'attendance-sheet',
            'Malappuram Central Sahodaya',
            Carbon::create(2026, 8, 10),
            ['Chess 2026-27', 'U14 Chess Individual-Girls'],
        );

        $this->assertSame(
            'attendance-sheet_malappuram-central-sahodaya_chess-2026-27_u14-chess-individual-girls_2026-08-10.pdf',
            $filename,
        );
    }
}
