<?php

namespace App\Services\Students;

use App\Models\SchoolClass;
use App\Models\Tenant;
use App\Services\Spreadsheet\SpreadsheetReader;
use App\Services\Spreadsheet\SpreadsheetWriter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class StudentCsvImporter
{
    /** @var array<string, list<string>> */
    private const HEADER_ALIASES = [
        'name'   => ['full_name', 'full name', 'student name', 'student_name', 'name'],
        'class'  => ['class_name', 'class name', 'class'],
        'email'  => ['email', 'parent_email', 'parent email'],
        'gender' => ['gender', 'sex'],
        'dob'    => ['dob', 'date_of_birth', 'date of birth', 'birthdate', 'birth_date'],
    ];

    public function __construct(private Tenant $school) {}

    /**
     * @return array{imported: int, skipped: int, errors: list<array{row: int, message: string}>, success: bool}
     */
    public function import(UploadedFile $file): array
    {
        return $this->importFromPath($file->getRealPath() ?: $file->getPathname());
    }

    /** Count non-empty data rows (excludes header). */
    public function countDataRows(string $path): int
    {
        $count = 0;
        $rowNumber = 0;

        foreach (SpreadsheetReader::rows($path) as $row) {
            $rowNumber++;

            if ($rowNumber === 1) {
                continue; // header
            }

            if (! $this->rowIsEmpty($row)) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Validate the whole file, then import it as a single all-or-nothing
     * transaction: if any row is invalid, nothing is persisted.
     *
     * @return array{imported: int, skipped: int, errors: list<array{row: int, message: string}>, success: bool}
     */
    public function importFromPath(string $path): array
    {
        $validation = $this->validateRows($path);

        if ($validation['errors'] !== []) {
            return [
                'imported' => 0,
                'skipped'  => $validation['total_rows'],
                'errors'   => $validation['errors'],
                'success'  => false,
            ];
        }

        if ($validation['rows'] === []) {
            return [
                'imported' => 0,
                'skipped'  => 0,
                'errors'   => [['row' => 0, 'message' => 'The file has no data rows to import.']],
                'success'  => false,
            ];
        }

        try {
            $imported = DB::transaction(function () use ($validation) {
                $creator = app(StudentRecordCreator::class);
                $count = 0;

                foreach ($validation['rows'] as $row) {
                    $fields = [
                        'school_class_id' => $row['school_class_id'],
                        'name'            => $row['name'],
                        'gender'          => $row['gender'] ?? 'other',
                        'dob'             => $row['dob'],
                    ];

                    if ($row['email'] !== null) {
                        $fields['parent_email'] = $row['email'];
                    }

                    $creator->create($this->school, $fields);
                    $count++;
                }

                return $count;
            });
        } catch (\Throwable $e) {
            return [
                'imported' => 0,
                'skipped'  => $validation['total_rows'],
                'errors'   => [['row' => 0, 'message' => 'Import failed and was rolled back: '.$e->getMessage()]],
                'success'  => false,
            ];
        }

        return [
            'imported' => $imported,
            'skipped'  => 0,
            'errors'   => [],
            'success'  => true,
        ];
    }

    /**
     * Validate rows without persisting — for import preview.
     *
     * @return array{valid: list<array{row: int, name: string, class: string, gender: ?string, dob: ?string, email: ?string}>, errors: list<array{row: int, message: string}>, total_rows: int}
     */
    public function previewFromPath(string $path, int $limit = 100): array
    {
        $validation = $this->validateRows($path);

        $valid = array_map(fn (array $row) => [
            'row'    => $row['row'],
            'name'   => $row['name'],
            'class'  => $row['class_name'],
            'gender' => $row['gender'],
            'dob'    => $row['dob'],
            'email'  => $row['email'],
        ], array_slice($validation['rows'], 0, $limit));

        return [
            'valid'      => $valid,
            'errors'     => $validation['errors'],
            'total_rows' => $validation['total_rows'],
        ];
    }

    /**
     * Read and validate every data row in the file. Returns fully resolved
     * rows ready for persistence, and the complete list of errors found —
     * used by both the transactional importer and the preview endpoint.
     *
     * @return array{rows: list<array{row: int, school_class_id: int, name: string, class_name: string, gender: ?string, dob: ?string, email: ?string}>, errors: list<array{row: int, message: string}>, total_rows: int}
     */
    private function validateRows(string $path): array
    {
        $classes = SchoolClass::where('tenant_id', $this->school->id)
            ->active()
            ->get()
            ->keyBy(fn (SchoolClass $c) => strtolower(trim($c->name)));

        if ($classes->isEmpty()) {
            return [
                'rows'       => [],
                'errors'     => [['row' => 0, 'message' => 'No classes found. Contact your Sahodaya admin to configure the class master.']],
                'total_rows' => 0,
            ];
        }

        $rows = [];
        $errors = [];
        $rowNumber = 0;
        $totalRows = 0;
        $header = null;
        $columns = [];

        foreach (SpreadsheetReader::rows($path) as $row) {
            $rowNumber++;

            if ($rowNumber === 1) {
                $header = $row;
                $columns = $this->mapColumns($header);

                if (! isset($columns['name'], $columns['class'])) {
                    return [
                        'rows'       => [],
                        'errors'     => [['row' => 1, 'message' => 'File must include name and class columns (e.g. full_name, class_name).']],
                        'total_rows' => 0,
                    ];
                }

                continue;
            }

            if ($this->rowIsEmpty($row)) {
                continue;
            }

            $totalRows++;

            $name = trim((string) ($row[$columns['name']] ?? ''));
            $className = trim((string) ($row[$columns['class']] ?? ''));
            $email = isset($columns['email']) ? trim((string) ($row[$columns['email']] ?? '')) : '';
            $genderRaw = isset($columns['gender']) ? strtolower(trim((string) ($row[$columns['gender']] ?? ''))) : '';
            $dobRaw = isset($columns['dob']) ? trim((string) ($row[$columns['dob']] ?? '')) : '';

            if ($name === '') {
                $errors[] = ['row' => $rowNumber, 'message' => 'Student name is required.'];

                continue;
            }

            if ($className === '') {
                $errors[] = ['row' => $rowNumber, 'message' => "Class is required for \"{$name}\"."];

                continue;
            }

            $schoolClass = $classes->get(strtolower($className));
            if (! $schoolClass) {
                $errors[] = ['row' => $rowNumber, 'message' => "Unknown class \"{$className}\" for \"{$name}\". It must match a class defined by your Sahodaya."];

                continue;
            }

            if ($email !== '' && ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = ['row' => $rowNumber, 'message' => "Invalid email for \"{$name}\"."];

                continue;
            }

            $gender = $this->normalizeGender($genderRaw);
            if ($genderRaw !== '' && $gender === null) {
                $errors[] = ['row' => $rowNumber, 'message' => "Invalid gender \"{$genderRaw}\" for \"{$name}\". Use male, female, or other."];

                continue;
            }

            $dob = $this->normalizeDob($dobRaw);
            if ($dobRaw !== '' && $dob === null) {
                $errors[] = ['row' => $rowNumber, 'message' => "Invalid date of birth \"{$dobRaw}\" for \"{$name}\". Use YYYY-MM-DD."];

                continue;
            }

            $rows[] = [
                'row'             => $rowNumber,
                'school_class_id' => $schoolClass->id,
                'name'            => $name,
                'class_name'      => $className,
                'gender'          => $gender,
                'dob'             => $dob,
                'email'           => $email !== '' ? $email : null,
            ];
        }

        if ($header === null) {
            return [
                'rows'       => [],
                'errors'     => [['row' => 0, 'message' => 'The file is empty.']],
                'total_rows' => 0,
            ];
        }

        return ['rows' => $rows, 'errors' => $errors, 'total_rows' => $totalRows];
    }

    public static function templateCsv(): string
    {
        return "full_name,class_name,gender,dob,email\n"
            ."Rahul Kumar,10,male,2012-05-01,\n"
            ."Priya Nair,LKG,female,2018-03-15,parent@example.com\n";
    }

    public function templateCsvForSchool(): string
    {
        $lines = array_map(
            fn (array $row) => implode(',', array_map($this->escapeCsvField(...), $row)),
            $this->templateRowsForSchool(),
        );

        return implode("\n", $lines)."\n";
    }

    public function templateXlsxForSchool(): string
    {
        return SpreadsheetWriter::xlsx($this->templateRowsForSchool());
    }

    /** @return list<list<string>> */
    private function templateRowsForSchool(): array
    {
        $classes = SchoolClass::where('tenant_id', $this->school->id)
            ->active()
            ->orderBy('display_order')
            ->orderBy('name')
            ->pluck('name');

        $class1 = (string) $classes->get(0, '10');
        $class2 = (string) $classes->get(1, $class1);

        $rows = [
            ['full_name', 'class_name', 'gender', 'dob', 'email'],
            ['Rahul Kumar', $class1, 'male', '2012-05-01', ''],
            ['Priya Nair', $class2, 'female', '2018-03-15', 'parent@example.com'],
        ];

        if ($classes->count() > 2) {
            $rows[] = ['Anita Shah', (string) $classes->get(2), 'female', '2011-08-20', 'anita@example.com'];
        }

        return $rows;
    }

    private function normalizeGender(string $value): ?string
    {
        if ($value === '') {
            return null;
        }

        return match ($value) {
            'm', 'male', 'boy' => 'male',
            'f', 'female', 'girl' => 'female',
            'other', 'o' => 'other',
            default => null,
        };
    }

    private function normalizeDob(string $value): ?string
    {
        if ($value === '') {
            return null;
        }

        $timestamp = strtotime($value);
        if ($timestamp === false) {
            return null;
        }

        return date('Y-m-d', $timestamp);
    }

    private function escapeCsvField(string $value): string
    {
        if (str_contains($value, ',') || str_contains($value, '"') || str_contains($value, "\n")) {
            return '"'.str_replace('"', '""', $value).'"';
        }

        return $value;
    }

    /** @param list<string|null> $header */
    private function mapColumns(array $header): array
    {
        $normalized = [];
        foreach ($header as $index => $label) {
            $normalized[$index] = strtolower(trim((string) $label));
        }

        $columns = [];
        foreach (self::HEADER_ALIASES as $field => $aliases) {
            foreach ($normalized as $index => $label) {
                if (in_array($label, $aliases, true)) {
                    $columns[$field] = $index;
                    break;
                }
            }
        }

        return $columns;
    }

    /** @param list<string|null> $row */
    private function rowIsEmpty(array $row): bool
    {
        foreach ($row as $value) {
            if (trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }
}
