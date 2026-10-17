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
            'unidentified_participants' => 0, 'genders' => ['male' => 0, 'female' => 1, 'other' => 0, 'unspecified' => 0], 'schools' => [['genders' => ['male' => 0, 'female' => 1, 'other' => 0, 'unspecified' => 0], 'name' => 'School & Academy', 'count' => 1,
                'students' => [['name' => '=HYPERLINK("https://example.test")', 'class_name' => 'VI',
                    'identifier' => '00042', 'identified' => true]]]]];

        return ['days' => [['date' => '2026-10-20'] + $counts], 'overall' => $counts,
            'event_total' => $counts, 'unscheduled' => ['unique_participants' => 0, 'participant_entries' => 0,
                'school_count' => 0, 'unidentified_participants' => 0, 'genders' => ['male' => 0, 'female' => 0, 'other' => 0, 'unspecified' => 0], 'schools' => []], 'basis' => 'Approved registrations'];
    }

    public function test_excel_contains_school_gender_counts_without_student_names(): void
    {
        $response = app(FestDayParticipationReport::class)->respond(
            Request::create('/', 'GET', ['format' => 'xlsx']), $this->summary(), 'Test Fest');
        $this->assertSame('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', $response->headers->get('Content-Type'));
        $path = tempnam(sys_get_temp_dir(), 'day-report-test-');
        try {
            file_put_contents($path, $response->getContent());
            $zip = new ZipArchive;
            $this->assertTrue($zip->open($path));
            $workbook = $zip->getFromName('xl/workbook.xml');
            $this->assertStringContainsString('School gender counts', $workbook);
            $this->assertStringNotContainsString('Student names', $workbook);
            $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
            $this->assertStringContainsString('School &amp; Academy', $sheet);
            $this->assertStringContainsString('Female', $sheet);
            $this->assertStringNotContainsString('HYPERLINK', $sheet);
            $this->assertStringNotContainsString('00042', $sheet);
            $this->assertFalse($zip->getFromName('xl/worksheets/sheet2.xml'));
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

    public function test_pdf_template_contains_school_gender_totals_and_no_student_identifiers(): void
    {
        $report = app(FestDayParticipationReport::class)->build($this->summary(), 'Test Fest');
        $html = view('fest.reports.day-participation', $report)->render();
        $this->assertStringContainsString('School &amp; Academy', $html);
        $this->assertStringContainsString('Female', $html);
        $this->assertStringContainsString('Unspecified', $html);
        $this->assertStringNotContainsString('HYPERLINK', $html);
        $this->assertStringNotContainsString('00042', $html);
        $this->assertArrayNotHasKey('students', $report['sections'][0]['schools'][0]);
    }

    public function test_dates_are_grouped_across_school_rows_and_overall_is_not_the_sum_of_days(): void
    {
        $summary = $this->summary();
        $summary['days'][] = ['date' => '2026-10-21'] + $summary['overall'];
        $report = app(FestDayParticipationReport::class)->build($summary, 'Test Fest');
        $this->assertSame(['2026-10-20', '2026-10-21'], $report['matrix']['dates']);
        $this->assertSame(['male' => 'Male', 'female' => 'Female', 'total' => 'Total'], $report['matrix']['genders']);
        $this->assertSame([[0, 1, 1], [0, 1, 1]], $report['matrix']['rows'][0]['days']);
        $this->assertSame(1, $report['matrix']['rows'][0]['overall']);
        $this->assertSame(1, $report['matrix']['totals']['overall']);
        $path = tempnam(sys_get_temp_dir(), 'matrix-test-');
        try {
            file_put_contents($path, app(FestDayParticipationReport::class)->xlsx($report));
            $zip = new ZipArchive;
            $zip->open($path);
            $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
            $this->assertStringContainsString('ref="B5:D5"', $sheet);
            $this->assertStringContainsString('ref="E5:G5"', $sheet);
            $this->assertStringNotContainsString('Overall unique', $sheet);
            $zip->close();
        } finally {
            @unlink($path);
        }
        $html = view('fest.reports.day-participation', $report)->render();
        $this->assertStringContainsString('colspan="3"', $html);
        $this->assertStringContainsString('rowspan="2"', $html);
        $this->assertStringContainsString('Total', $html);
        $this->assertStringNotContainsString('Overall unique', $html);
    }

    public function test_report_uses_organisation_branding_in_pdf_and_excel(): void
    {
        $logo = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aD1sAAAAASUVORK5CYII=';
        $service = app(FestDayParticipationReport::class);
        $report = $service->build($this->summary(), 'Test Fest', 'all', ['orgName' => 'Malabar Sahodaya', 'logoSrc' => $logo]);
        $html = view('fest.reports.day-participation', $report + ['isDomPdf' => true])->render();
        $this->assertStringContainsString('Malabar Sahodaya', $html);
        $this->assertStringContainsString($logo, $html);
        $this->assertStringContainsString('report-branding', $html);
        $path = tempnam(sys_get_temp_dir(), 'brand-test-');
        try {
            file_put_contents($path, $service->xlsx($report));
            $zip = new ZipArchive;
            $zip->open($path);
            $this->assertStringContainsString('Malabar Sahodaya', $zip->getFromName('xl/worksheets/sheet1.xml'));
            $zip->close();
        } finally {
            @unlink($path);
        }
    }

    public function test_unassigned_date_cannot_be_exported_as_an_overall_list(): void
    {
        $this->expectException(HttpException::class);
        app(FestDayParticipationReport::class)->build($this->summary(), 'Test Fest', '2026-12-31');
    }
}
