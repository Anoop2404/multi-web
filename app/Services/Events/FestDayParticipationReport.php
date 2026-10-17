<?php

namespace App\Services\Events;

use App\Support\PdfGenerator;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Cell\StringCell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Entity\SheetView;
use OpenSpout\Writer\XLSX\Options;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\Response;

class FestDayParticipationReport
{
    public function respond(Request $request, array $summary, string $title): Response
    {
        $data = $request->validate(['format' => 'required|in:xlsx,pdf,preview', 'scope' => 'nullable|string|max:20']);
        $report = $this->build($summary, $title, $data['scope'] ?? 'all');
        $filename = 'day-participants-'.Str::limit(Str::slug($title), 70, '');
        if ($data['format'] === 'xlsx') {
            return response($this->xlsx($report), 200, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Content-Disposition' => 'attachment; filename="'.$filename.'.xlsx"',
                'Cache-Control' => 'private, no-store',
            ]);
        }
        $response = PdfGenerator::fromView('fest.reports.day-participation', $report, $filename.'.pdf',
            inline: $data['format'] === 'preview');
        $response->headers->set('Cache-Control', 'private, no-store');

        return $response;
    }

    public function build(array $summary, string $title, string $scope = 'all'): array
    {
        $summaryRows = [];
        foreach ($summary['days'] as $day) {
            $summaryRows[] = $this->countRow($day['date'], $day);
        }
        $summaryRows[] = $this->countRow('Overall - scheduled days', $summary['overall']);
        $summaryRows[] = $this->countRow('Awaiting date', $summary['unscheduled']);
        $summaryRows[] = $this->countRow('Event total including unscheduled', $summary['event_total']);
        $sections = match ($scope) {
            'all' => array_merge(array_map(fn ($day) => ['label' => $day['date']] + $day, $summary['days']),
                [['label' => 'Overall - scheduled days'] + $summary['overall']]),
            'event' => [['label' => 'Event total including unscheduled'] + $summary['event_total']],
            'unscheduled' => [['label' => 'Awaiting date'] + $summary['unscheduled']],
            default => collect($summary['days'])->where('date', $scope)->map(fn ($day) => ['label' => $day['date']] + $day)->all(),
        };
        abort_if($sections === [], 422, 'Choose an assigned schedule date or an overall report.');

        return [
            'title' => $title, 'basis' => $summary['basis'], 'scope' => $scope,
            'generated' => now()->format('d M Y, h:i A'), 'summaryRows' => $summaryRows,
            'sections' => array_values($sections), 'warnings' => $summary['warnings'] ?? [],
            'unidentified' => $summary['event_total']['unidentified_participants'],
        ];
    }

    private function countRow(string $label, array $counts): array
    {
        return [$label, $counts['unique_participants'], $counts['participant_entries'],
            $counts['school_count'], $counts['unidentified_participants']];
    }

    public function xlsx(array $report): string
    {
        $schoolRows = [];
        $studentRows = [];
        foreach ($report['sections'] as $section) {
            foreach ($section['schools'] as $school) {
                $schoolRows[] = [$section['label'], $school['name'], $school['count']];
                foreach ($school['students'] as $i => $student) {
                    $studentRows[] = [$section['label'], $school['name'], $i + 1, $student['name'],
                        $student['class_name'], $student['identifier'] ?? '', $student['identified'] ? 'Yes' : 'No'];
                }
            }
        }
        $sheets = [
            'Counts' => [['Day or scope', 'Unique participants', 'Participant entries', 'Schools', 'Unverified records'], $report['summaryRows']],
            'School counts' => [['Day or scope', 'School', 'Unique participants'], $schoolRows],
            'Student names' => [['Day or scope', 'School', 'No.', 'Participant', 'Class', 'Student ID or roll', 'Stable ID available'], $studentRows],
        ];
        $path = tempnam(sys_get_temp_dir(), 'day-participants-');
        $options = new Options;
        foreach (array_values($sheets) as $index => [$headers]) {
            foreach ([1, 2, 3] as $row) {
                $options->mergeCells(0, $row, count($headers) - 1, $row, $index);
            }
        }
        $writer = new Writer($options);
        try {
            $writer->openToFile($path);
            $header = (new Style)->setFontBold()->setFontColor('FFFFFF')->setBackgroundColor('17324D')->setShouldWrapText();
            $body = (new Style)->setShouldWrapText()->setFontSize(11);
            foreach ($sheets as $name => [$headers, $rows]) {
                $sheet = $name === 'Counts' ? $writer->getCurrentSheet() : $writer->addNewSheetAndMakeItCurrent();
                $sheet->setName($name);
                $sheet->setSheetView((new SheetView)->setFreezeRow(6));
                $sheet->setColumnWidth(28, 1);
                $sheet->setColumnWidth(42, 2);
                $sheet->setColumnWidth(22, 3, 4, 5, 6, 7);
                if ($name === 'Student names') {
                    $sheet->setColumnWidth(8, 3);
                    $sheet->setColumnWidth(36, 4);
                }
                foreach ([[$report['title']], [$report['basis'].' - Generated '.$report['generated']],
                    ['Unique counts are not sums of daily counts. Unverified records may overcount a person.'], []] as $note) {
                    $writer->addRow($this->row($note, $body));
                }
                $writer->addRow($this->row($headers, $header));
                foreach ($rows as $values) {
                    $writer->addRow($this->row($values, $body));
                }
            }
            $writer->close();

            return (string) file_get_contents($path);
        } finally {
            @unlink($path);
        }
    }

    private function row(array $values, Style $style): Row
    {
        // Explicit strings prevent names beginning with '=' from becoming spreadsheet formulas.
        return new Row(array_map(fn ($value) => is_string($value) ? new StringCell($value, null) : Cell::fromValue($value), $values), $style);
    }
}
