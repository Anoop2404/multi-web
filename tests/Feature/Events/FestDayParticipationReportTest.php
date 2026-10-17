<?php

namespace Tests\Feature\Events;

use App\Services\Events\FestDayParticipationReport;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;
use ZipArchive;

class FestDayParticipationReportTest extends TestCase
{
    private function summary(): array
    {
        $counts = ['unique_participants' => 1, 'participant_entries' => 2, 'school_count' => 1,
            'unidentified_participants' => 0, 'schools' => [['name' => 'School & Academy', 'count' => 1,
                'students' => [['name' => '=HYPERLINK("https://example.test")', 'class_name' => 'VI',
                    'identifier' => '00042', 'identified' => true]]]]];

        return ['days' => [['date' => '2026-10-20'] + $counts], 'overall' => $counts,
            'event_total' => $counts, 'unscheduled' => ['unique_participants' => 0, 'participant_entries' => 0,
                'school_count' => 0, 'unidentified_participants' => 0, 'schools' => []], 'basis' => 'Approved registrations'];
    }

    public function test_excel_is_a_real_three_sheet_workbook_and_names_cannot_execute_as_formulas(): void
    {
        $response = app(FestDayParticipationReport::class)->respond(
            Request::create('/', 'GET', ['format' => 'xlsx']), $this->summary(), 'Test Fest');
        $this->assertSame('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', $response->headers->get('Content-Type'));
        $path = tempnam(sys_get_temp_dir(), 'day-report-test-');
        try {
            file_put_contents($path, $response->getContent());
            $zip = new ZipArchive;
            $this->assertTrue($zip->open($path));
            $this->assertStringContainsString('School counts', $zip->getFromName('xl/workbook.xml'));
            $this->assertStringContainsString('Student names', $zip->getFromName('xl/workbook.xml'));
            $students = $zip->getFromName('xl/worksheets/sheet3.xml');
            $this->assertStringContainsString('HYPERLINK', $students);
            $this->assertStringNotContainsString('<f', $students);
            $this->assertStringContainsString('00042', $students);
            $zip->close();
        } finally {
            @unlink($path);
        }
    }

    public function test_pdf_preview_is_inline_pdf_and_download_is_an_attachment(): void
    {
        config(['services.pdf_converter.url' => null]);
        foreach (['preview' => 'inline', 'pdf' => 'attachment'] as $format => $disposition) {
            $response = app(FestDayParticipationReport::class)->respond(
                Request::create('/', 'GET', ['format' => $format, 'scope' => '2026-10-20']), $this->summary(), 'Test Fest');
            $this->assertStringStartsWith('%PDF-', $response->getContent());
            $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
            $this->assertStringStartsWith($disposition, $response->headers->get('Content-Disposition'));
        }
    }

    public function test_unassigned_date_cannot_be_exported_as_an_overall_list(): void
    {
        $this->expectException(HttpException::class);
        app(FestDayParticipationReport::class)->build($this->summary(), 'Test Fest', '2026-12-31');
    }
}
