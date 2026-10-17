<?php

namespace App\Services\State;

use App\Models\FestEvent;
use App\Models\FestParticipant;
use App\Models\FestRegistration;
use App\Models\FestStateNominationSelection;
use App\Models\FestStateProgram;
use App\Models\FestStateProgramItem;
use App\Models\Student;
use App\Support\FestClassGroupScheme;
use App\Support\FestItemCategoryLabel;
use App\Support\FestTeamSquadRules;
use App\Support\TenantStorage;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Cell\StringCell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Entity\SheetView;
use OpenSpout\Writer\XLSX\Options;
use OpenSpout\Writer\XLSX\Writer;

class FestStateExportService
{
    public function __construct(
        private FestStateNominationService $nominations,
    ) {}

    /**
     * Gather the complete nominee data and student photos for export.
     *
     * @return array{
     *     rows: list<array<int, mixed>>,
     *     photos: list<array{filename: string, bytes: string, student_name: string}>,
     *     stats: array{total_qualifiers: int, photos_count: int, missing_photos: int}
     * }
     */
    public function gatherData(FestStateProgram $program, FestEvent $hubEvent, bool $confirmedOnly = false): array
    {
        $batch = $this->nominations->openBatch($program, $hubEvent);

        $query = $batch->primarySelections()->where('status', 'selected');
        if ($confirmedOnly) {
            $query->where('school_response', 'accepted');
        }

        $selections = $query->orderBy('item_code')->orderBy('priority_order')->get();

        $items = FestStateProgramItem::whereIn('id', $selections->pluck('item_id')->filter())
            ->get()
            ->keyBy('id');

        $labels = FestClassGroupScheme::labels(null, $hubEvent->rootEvent());

        $regIds = $selections->pluck('registration_id')->filter()->unique();
        $registrations = FestRegistration::with(['participants.student.schoolClass', 'participants.student.tenant', 'item'])
            ->whereIn('id', $regIds)
            ->get()
            ->keyBy('id');

        $partIds = $selections->pluck('participant_id')->filter()->unique();
        $participants = FestParticipant::with(['student.schoolClass', 'student.tenant'])
            ->whereIn('id', $partIds)
            ->get()
            ->keyBy('id');

        $rows = [];
        $photos = [];
        $usedFilenames = [];
        $photosCount = 0;
        $missingCount = 0;

        foreach ($selections as $selection) {
            /** @var FestStateProgramItem|null $item */
            $item = $items->get($selection->item_id);

            $displayItem = $item ? new \App\Models\FestEventItem($item->toTenantAttributes()) : null;
            $categoryLabel = $displayItem ? FestItemCategoryLabel::resolve($displayItem, $labels) : ($selection->item_code ?? '—');
            $genderLabel = $item ? (FestItemCategoryLabel::genderLabel($item->gender) ?? 'Open') : 'Open';
            $isMultiPerson = $item ? FestTeamSquadRules::isMultiPerson($item->participant_type) : false;

            $posStr = $selection->source_position ? 'Rank ' . $selection->source_position : 'Direct';
            $statusStr = $selection->school_response === 'accepted' ? 'Confirmed' : ($selection->school_response === 'opted_out' ? 'Opted out' : 'Awaiting response');

            $studentRecords = [];

            if ($isMultiPerson && $selection->registration_id && $registrations->has($selection->registration_id)) {
                $reg = $registrations->get($selection->registration_id);
                $members = $reg->participants;
                if ($selection->participant_id) {
                    $selectedPart = $members->firstWhere('id', $selection->participant_id);
                    if ($selectedPart?->group_id) {
                        $members = $members->where('group_id', $selectedPart->group_id);
                    }
                }
                foreach ($members as $m) {
                    $studentRecords[] = [
                        'student' => $m->student,
                        'name' => $m->student?->name ?? $selection->student_name,
                        'adm_no' => $m->student?->admission_number ?? $m->student?->reg_no,
                        'roll_no' => $m->student?->roll_number ?? $selection->roll_number,
                        'class_name' => $m->student?->schoolClass?->name ?? $selection->class_name,
                        'school_name' => $m->student?->tenant?->name ?? $selection->school_name,
                        'role' => $m->participant_role === 'standby' ? 'Standby' : ($m->is_leader ? 'Leader' : 'Member'),
                    ];
                }
            } elseif ($selection->participant_id && $participants->has($selection->participant_id)) {
                $p = $participants->get($selection->participant_id);
                $studentRecords[] = [
                    'student' => $p->student,
                    'name' => $p->student?->name ?? $selection->student_name,
                    'adm_no' => $p->student?->admission_number ?? $p->student?->reg_no,
                    'roll_no' => $p->student?->roll_number ?? $selection->roll_number,
                    'class_name' => $p->student?->schoolClass?->name ?? $selection->class_name,
                    'school_name' => $p->student?->tenant?->name ?? $selection->school_name,
                    'role' => 'Individual',
                ];
            } else {
                $studentRecords[] = [
                    'student' => null,
                    'name' => $selection->student_name,
                    'adm_no' => null,
                    'roll_no' => $selection->roll_number,
                    'class_name' => $selection->class_name,
                    'school_name' => $selection->school_name,
                    'role' => 'Individual',
                ];
            }

            foreach ($studentRecords as $record) {
                $photoData = $this->getPhotoBytes($record['student']);

                if ($photoData !== null) {
                    $posSlug = Str::slug($posStr, '_');
                    $nameSlug = Str::slug($record['name'], '_');
                    $schoolSlug = Str::slug($record['school_name'] ?? 'School', '_');
                    $itemSlug = Str::slug($selection->item_code ?? 'ITEM', '_');

                    $stem = "{$itemSlug}_{$posSlug}_{$nameSlug}_{$schoolSlug}";
                    if (! empty($record['roll_no'])) {
                        $stem .= '_' . Str::slug((string) $record['roll_no'], '_');
                    }

                    $ext = $photoData['ext'];
                    $baseFileName = "{$stem}.{$ext}";
                    $fileName = $baseFileName;
                    $counter = 2;
                    while (isset($usedFilenames[$fileName])) {
                        $fileName = "{$stem}_{$counter}.{$ext}";
                        $counter++;
                    }
                    $usedFilenames[$fileName] = true;

                    $photoPathInZip = "student_photos/{$fileName}";
                    $photos[] = [
                        'filename' => $photoPathInZip,
                        'bytes' => $photoData['bytes'],
                        'student_name' => $record['name'],
                    ];

                    $photoCol = $photoPathInZip;
                    $photoStatus = 'Attached';
                    $photosCount++;
                } else {
                    $photoCol = '—';
                    $photoStatus = 'No photo uploaded';
                    $missingCount++;
                }

                $rows[] = [
                    count($rows) + 1,
                    $selection->item_code ?? '—',
                    $selection->item_title ?? ($item?->title ?? '—'),
                    $categoryLabel ?? '—',
                    $item?->class_group ?? '—',
                    $genderLabel,
                    $record['role'] !== 'Individual' ? "Group ({$record['role']})" : 'Individual',
                    $posStr,
                    $record['name'],
                    $record['adm_no'] ?? '—',
                    $record['roll_no'] ?? '—',
                    $record['class_name'] ?? '—',
                    $record['school_name'] ?? '—',
                    $statusStr,
                    $photoCol,
                    $photoStatus,
                ];
            }
        }

        return [
            'rows' => $rows,
            'photos' => $photos,
            'stats' => [
                'total_qualifiers' => count($rows),
                'photos_count' => $photosCount,
                'missing_photos' => $missingCount,
            ],
        ];
    }

    /**
     * Generate a ZIP archive containing the Excel spreadsheet and the student_photos/ folder.
     */
    public function generateZip(FestStateProgram $program, FestEvent $hubEvent, bool $confirmedOnly = false): string
    {
        $data = $this->gatherData($program, $hubEvent, $confirmedOnly);

        $tmpZip = tempnam(sys_get_temp_dir(), 'state_zip_');
        $zip = new \ZipArchive;
        abort_unless($zip->open($tmpZip, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) === true, 500, 'Could not create ZIP file.');

        // 1. Add Excel spreadsheet to root of ZIP
        $xlsxBytes = $this->buildSpreadsheet($data['rows'], $program, $hubEvent, $data['stats']);
        $eventSlug = Str::slug($hubEvent->title, '_');
        $zip->addFromString("State_Nominations_{$eventSlug}.xlsx", $xlsxBytes);

        // 2. Add student photos into student_photos/ folder
        foreach ($data['photos'] as $photo) {
            $zip->addFromString($photo['filename'], $photo['bytes']);
        }

        // 3. Add README.txt with export summary
        $readme = $this->buildReadme($program, $hubEvent, $data['stats']);
        $zip->addFromString('README.txt', $readme);

        $zip->close();

        return $tmpZip;
    }

    /**
     * Generate standalone Excel file (.xlsx) bytes.
     */
    public function generateExcel(FestStateProgram $program, FestEvent $hubEvent, bool $confirmedOnly = false): string
    {
        $data = $this->gatherData($program, $hubEvent, $confirmedOnly);

        return $this->buildSpreadsheet($data['rows'], $program, $hubEvent, $data['stats']);
    }

    /**
     * Build the Excel spreadsheet (.xlsx) using OpenSpout.
     *
     * @param  list<array<int, mixed>>  $rows
     * @param  array<string, int>  $stats
     */
    public function buildSpreadsheet(array $rows, FestStateProgram $program, FestEvent $hubEvent, array $stats): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'state_xlsx_');
        $options = new Options;
        $writer = new Writer($options);

        try {
            $writer->openToFile($tmp);

            $titleStyle = (new Style)->setFontBold()->setFontSize(14)->setFontColor('1E293B');
            $metaStyle = (new Style)->setFontSize(10)->setFontColor('64748B');
            $headerStyle = (new Style)->setFontBold()->setFontSize(10)->setFontColor('FFFFFF')->setBackgroundColor('1E3A8A')->setShouldWrapText();
            $dataStyle = (new Style)->setFontSize(10)->setShouldWrapText();
            $photoStyle = (new Style)->setFontSize(10)->setFontColor('047857')->setShouldWrapText();

            $sheet = $writer->getCurrentSheet();
            $sheet->setName('State Nominations');
            $sheet->setSheetView((new SheetView)->setFreezeRow(6));

            $sheet->setColumnWidth(8, 1);   // Sl No
            $sheet->setColumnWidth(14, 2);  // Item Code
            $sheet->setColumnWidth(28, 3);  // Item Name
            $sheet->setColumnWidth(22, 4);  // Category
            $sheet->setColumnWidth(14, 5);  // Class Group
            $sheet->setColumnWidth(12, 6);  // Gender
            $sheet->setColumnWidth(16, 7);  // Type
            $sheet->setColumnWidth(12, 8);  // Rank
            $sheet->setColumnWidth(24, 9);  // Student Name
            $sheet->setColumnWidth(16, 10); // Admission No
            $sheet->setColumnWidth(12, 11); // Roll No
            $sheet->setColumnWidth(12, 12); // Class
            $sheet->setColumnWidth(28, 13); // School Name
            $sheet->setColumnWidth(16, 14); // School Status
            $sheet->setColumnWidth(42, 15); // Photo File Name
            $sheet->setColumnWidth(16, 16); // Photo Status

            $writer->addRow(new Row([new StringCell($hubEvent->title . ' — State Nominations List', null)], $titleStyle));
            $writer->addRow(new Row([new StringCell('State Program: ' . $program->title . ' | Generated: ' . now()->format('d M Y, h:i A'), null)], $metaStyle));
            $writer->addRow(new Row([new StringCell("Total Qualifiers: {$stats['total_qualifiers']} | Photos Attached: {$stats['photos_count']} | Photos Missing: {$stats['missing_photos']}", null)], $metaStyle));
            $writer->addRow(new Row([new StringCell('Note: Photos are placed in the "student_photos/" folder with the exact filenames listed in the "Photo File Name" column.', null)], $metaStyle));
            $writer->addRow(new Row([]));

            $headers = [
                'Sl. No.', 'Item Code', 'Item Name', 'Category', 'Class Group', 'Gender',
                'Participant Type', 'Rank / Position', 'Student Name', 'Admission / Reg No.',
                'Roll No.', 'Class', 'School Name', 'School Status', 'Photo File Name', 'Photo Status',
            ];
            $writer->addRow(new Row(array_map(fn ($h) => new StringCell($h, null), $headers), $headerStyle));

            foreach ($rows as $row) {
                $cells = [];
                foreach ($row as $colIdx => $val) {
                    $style = ($colIdx === 14 && $val !== '—') ? $photoStyle : $dataStyle;
                    $cells[] = is_string($val) ? new StringCell($val, $style) : Cell::fromValue($val, $style);
                }
                $writer->addRow(new Row($cells, $dataStyle));
            }

            $writer->close();

            return (string) file_get_contents($tmp);
        } finally {
            @unlink($tmp);
        }
    }

    /**
     * Build the README.txt documentation file included in the ZIP.
     *
     * @param  array<string, int>  $stats
     */
    private function buildReadme(FestStateProgram $program, FestEvent $hubEvent, array $stats): string
    {
        $date = now()->format('d M Y, h:i A');

        return <<<TEXT
================================================================================
STATE NOMINATIONS & QUALIFIER PHOTOS PACKAGE
================================================================================
Event:            {$hubEvent->title}
State Program:    {$program->title}
Generated On:     {$date}
Total Qualifiers: {$stats['total_qualifiers']}
Photos Included:  {$stats['photos_count']}
Missing Photos:   {$stats['missing_photos']}
================================================================================

PACKAGE CONTENTS:
1. State_Nominations_*.xlsx
   Spreadsheet containing complete student details, item codes, categories, 
   school names, ranks, and exact matching photo filenames.

2. student_photos/
   Folder containing student images named systematically:
   [ItemCode]_[Rank]_[StudentName]_[SchoolName]_[RollNo].[ext]

INSTRUCTIONS FOR STATE SUBMISSION:
- Each row in the Excel sheet corresponds to a nominated student qualifier.
- The "Photo File Name" column in the Excel sheet gives the exact path to that 
  student's photograph inside the "student_photos/" folder.
- If any student shows "No photo uploaded", you may collect their photo from the 
  respective school and place it in the folder.

================================================================================
Generated by Kalotsav State Slot Collection Portal
================================================================================
TEXT;
    }

    /**
     * Retrieve the raw binary content of a student's photo from available storage disks.
     *
     * @return array{bytes: string, ext: string}|null
     */
    public function getPhotoBytes(?Student $student): ?array
    {
        if (! $student || empty($student->photo)) {
            return null;
        }

        $photo = $student->photo;

        if (str_starts_with($photo, 'data:image/')) {
            if (preg_match('/^data:image\/(\w+);base64,(.+)$/', $photo, $matches)) {
                $ext = $matches[1] === 'jpeg' ? 'jpg' : $matches[1];

                return ['bytes' => base64_decode($matches[2]), 'ext' => $ext];
            }
        }

        $bytes = TenantStorage::get($photo);
        if ($bytes !== null && $bytes !== '') {
            $ext = pathinfo($photo, PATHINFO_EXTENSION) ?: 'jpg';

            return ['bytes' => $bytes, 'ext' => strtolower($ext)];
        }

        if ($student->tenant) {
            $local = TenantStorage::publicFilePath($student->tenant, $photo);
            if ($local && is_file($local)) {
                $bytes = @file_get_contents($local);
                if ($bytes !== false && $bytes !== '') {
                    $ext = pathinfo($local, PATHINFO_EXTENSION) ?: 'jpg';

                    return ['bytes' => $bytes, 'ext' => strtolower($ext)];
                }
            }
        }

        foreach (['public', 'local'] as $diskName) {
            try {
                if (Storage::disk($diskName)->exists($photo)) {
                    $bytes = Storage::disk($diskName)->get($photo);
                    if ($bytes !== null && $bytes !== '') {
                        $ext = pathinfo($photo, PATHINFO_EXTENSION) ?: 'jpg';

                        return ['bytes' => $bytes, 'ext' => strtolower($ext)];
                    }
                }
            } catch (\Throwable) {
                continue;
            }
        }

        if (str_starts_with($photo, 'http://') || str_starts_with($photo, 'https://')) {
            try {
                $bytes = @file_get_contents($photo);
                if ($bytes !== false && $bytes !== '') {
                    $ext = pathinfo(parse_url($photo, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION) ?: 'jpg';

                    return ['bytes' => $bytes, 'ext' => strtolower($ext)];
                }
            } catch (\Throwable) {
                // Ignore URL fetch error
            }
        }

        return null;
    }
}
