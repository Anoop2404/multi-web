<?php

namespace App\Services\Events;

use App\Support\PdfChromeHeaderFooter;
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
    public function respond(Request $request, array $summary, string $title, array $branding = []): Response
    {
        $data = $request->validate(['format' => 'required|in:xlsx,pdf,preview', 'scope' => 'nullable|string|max:20']);
        $report = $this->build($summary, $title, $data['scope'] ?? 'all', $branding);
        $filename = 'day-participants-'.Str::limit(Str::slug($title), 70, '');
        if ($data['format'] === 'xlsx') {
            return response($this->xlsx($report), 200, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Content-Disposition' => 'attachment; filename="'.$filename.'.xlsx"',
                'Cache-Control' => 'private, no-store',
            ]);
        }
        $response = PdfChromeHeaderFooter::download('fest.reports.day-participation', $report, $filename.'.pdf',
            $data['format'] === 'preview', 'Day-wise participants', $title, true);
        $response->headers->set('Cache-Control', 'private, no-store');

        return $response;
    }

    public function build(array $summary, string $title, string $scope = 'all', array $branding = []): array
    {
        $summary = app(FestDayParticipationService::class)->countsOnly($summary);
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

        $columns = $scope === 'all' ? array_values(array_slice($sections, 0, -1)) : array_values($sections);
        $overall = $scope === 'all' ? $summary['overall'] : $sections[array_key_first($sections)];
        $matrix = $this->matrix($columns, $overall);

        return [
            'orgName' => $branding['orgName'] ?? 'Inter-School Competitions & Events',
            'logoSrc' => $branding['logoSrc'] ?? null,
            'matrix' => $matrix,
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

    private function matrix(array $days, array $overall): array
    {
        $genders = ['male' => 'Male', 'female' => 'Female'];
        foreach (['other' => 'Other', 'unspecified' => 'Unspecified'] as $key => $label) {
            if (collect($days)->sum(fn ($day) => $day['genders'][$key] ?? 0) > 0) {
                $genders[$key] = $label;
            }
        }
        $genders['total'] = 'Total';
        $rows = [];
        foreach ($overall['schools'] as $school) {
            $key = $school['key'] ?? $school['name'];
            $cells = [];
            foreach ($days as $day) {
                $match = collect($day['schools'])->first(fn ($entry) => ($entry['key'] ?? $entry['name']) === $key);
                $cells[] = array_map(fn ($gender) => (int) ($gender === 'total' ? ($match['count'] ?? 0) : ($match['genders'][$gender] ?? 0)), array_keys($genders));
            }
            $rows[] = ['school' => $school['name'], 'days' => $cells, 'overall' => $school['count']];
        }

        return [
            'dates' => array_column($days, 'label'), 'genders' => $genders, 'rows' => $rows,
            'totals' => ['school' => 'All schools', 'days' => array_map(fn ($day) =>
                array_map(fn ($gender) => (int) ($gender === 'total' ? $day['unique_participants'] : ($day['genders'][$gender] ?? 0)), array_keys($genders)), $days),
                'overall' => $overall['unique_participants']],
        ];
    }

    public function xlsx(array $report): string
    {
        $matrix = $report['matrix'];
        $width = count($matrix['genders']);
        $lastColumn = count($matrix['dates']) * $width;
        $path = tempnam(sys_get_temp_dir(), 'day-participants-');
        $options = new Options;
        foreach ([1, 2, 3] as $row) {
            $options->mergeCells(0, $row, $lastColumn, $row);
        }
        $options->mergeCells(0, 5, 0, 6);
        foreach ($matrix['dates'] as $index => $date) {
            $options->mergeCells(1 + $index * $width, 5, ($index + 1) * $width, 5);
        }
        $writer = new Writer($options);
        try {
            $writer->openToFile($path);
            $header = (new Style)->setFontBold()->setFontColor('FFFFFF')->setBackgroundColor('17324D')->setShouldWrapText();
            $body = (new Style)->setShouldWrapText()->setFontSize(11);
            $total = (new Style)->setFontBold()->setBackgroundColor('EDF3F8')->setShouldWrapText();
            $sheet = $writer->getCurrentSheet();
            $sheet->setName('School gender counts');
            $sheet->setSheetView((new SheetView)->setFreezeRow(7)->setFreezeColumn('B'));
            $sheet->setColumnWidth(48, 1);
            for ($column = 2; $column <= $lastColumn + 1; $column++) {
                $sheet->setColumnWidth(16, $column);
            }
            foreach ([[$report['orgName'].' — '.$report['title']], ['Day-wise participants | '.$report['basis'].' | Generated '.$report['generated']],
                ['Each date shows unique Male, Female and Total counts. Group members count individually. Unspecified includes missing or conflicting gender.'], []] as $note) {
                $writer->addRow($this->row($note, $body));
            }
            $dates = ['School name'];
            $genders = [''];
            foreach ($matrix['dates'] as $date) {
                array_push($dates, $date, ...array_fill(0, $width - 1, ''));
                array_push($genders, ...array_values($matrix['genders']));
            }
            $writer->addRow($this->row($dates, $header));
            $writer->addRow($this->row($genders, $header));
            foreach ([...$matrix['rows'], $matrix['totals']] as $index => $row) {
                $values = [$row['school']];
                foreach ($row['days'] as $day) {
                    array_push($values, ...$day);
                }
                $writer->addRow($this->row($values, $index === count($matrix['rows']) ? $total : $body));
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
