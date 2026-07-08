<?php

namespace App\Services\Students;

use App\Models\SchoolClass;
use App\Models\Tenant;
use Illuminate\Http\UploadedFile;

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
     * @return array{imported: int, skipped: int, errors: list<array{row: int, message: string}>}
     */
    public function import(UploadedFile $file): array
    {
        return $this->importFromPath($file->getRealPath());
    }

    /** Count non-empty data rows (excludes header). */
    public function countDataRows(string $path): int
    {
        $handle = fopen($path, 'r');
        if ($handle === false) {
            return 0;
        }

        fgetcsv($handle);
        $count = 0;

        while (($row = fgetcsv($handle)) !== false) {
            if (! $this->rowIsEmpty($row)) {
                $count++;
            }
        }

        fclose($handle);

        return $count;
    }

    /**
     * @return array{imported: int, skipped: int, errors: list<array{row: int, message: string}>}
     */
    public function importFromPath(string $path): array
    {
        $classes = SchoolClass::where('tenant_id', $this->school->id)
            ->active()
            ->get()
            ->keyBy(fn (SchoolClass $c) => strtolower(trim($c->name)));

        if ($classes->isEmpty()) {
            return [
                'imported' => 0,
                'skipped'  => 0,
                'errors'   => [['row' => 0, 'message' => 'No classes found. Contact your Sahodaya admin to configure the class master.']],
            ];
        }

        $handle = fopen($path, 'r');
        if ($handle === false) {
            return [
                'imported' => 0,
                'skipped'  => 0,
                'errors'   => [['row' => 0, 'message' => 'Could not read the uploaded file.']],
            ];
        }

        $header = fgetcsv($handle);
        if (! $header) {
            fclose($handle);

            return [
                'imported' => 0,
                'skipped'  => 0,
                'errors'   => [['row' => 0, 'message' => 'The file is empty.']],
            ];
        }

        $columns = $this->mapColumns($header);
        if (! isset($columns['name'], $columns['class'])) {
            fclose($handle);

            return [
                'imported' => 0,
                'skipped'  => 0,
                'errors'   => [['row' => 1, 'message' => 'CSV must include name and class columns (e.g. full_name, class_name).']],
            ];
        }

        $imported = 0;
        $skipped = 0;
        $errors = [];
        $rowNumber = 1;
        $creator = app(StudentRecordCreator::class);

        while (($row = fgetcsv($handle)) !== false) {
            $rowNumber++;

            if ($this->rowIsEmpty($row)) {
                continue;
            }

            $name = trim((string) ($row[$columns['name']] ?? ''));
            $className = trim((string) ($row[$columns['class']] ?? ''));
            $email = isset($columns['email']) ? trim((string) ($row[$columns['email']] ?? '')) : '';
            $genderRaw = isset($columns['gender']) ? strtolower(trim((string) ($row[$columns['gender']] ?? ''))) : '';
            $dobRaw = isset($columns['dob']) ? trim((string) ($row[$columns['dob']] ?? '')) : '';

            if ($name === '') {
                $errors[] = ['row' => $rowNumber, 'message' => 'Student name is required.'];
                $skipped++;

                continue;
            }

            if ($className === '') {
                $errors[] = ['row' => $rowNumber, 'message' => "Class is required for \"{$name}\"."];
                $skipped++;

                continue;
            }

            $schoolClass = $classes->get(strtolower($className));
            if (! $schoolClass) {
                $errors[] = ['row' => $rowNumber, 'message' => "Unknown class \"{$className}\" for \"{$name}\". It must match a class defined by your Sahodaya."];
                $skipped++;

                continue;
            }

            if ($email !== '' && ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = ['row' => $rowNumber, 'message' => "Invalid email for \"{$name}\"."];
                $skipped++;

                continue;
            }

            $gender = $this->normalizeGender($genderRaw);
            if ($genderRaw !== '' && $gender === null) {
                $errors[] = ['row' => $rowNumber, 'message' => "Invalid gender \"{$genderRaw}\" for \"{$name}\". Use male, female, or other."];
                $skipped++;

                continue;
            }

            $dob = $this->normalizeDob($dobRaw);
            if ($dobRaw !== '' && $dob === null) {
                $errors[] = ['row' => $rowNumber, 'message' => "Invalid date of birth \"{$dobRaw}\" for \"{$name}\". Use YYYY-MM-DD."];
                $skipped++;

                continue;
            }

            $fields = [
                'school_class_id' => $schoolClass->id,
                'name'            => $name,
                'gender'          => $gender ?? 'other',
                'dob'             => $dob,
            ];

            if ($email !== '') {
                $fields['parent_email'] = $email;
            }

            $creator->create($this->school, $fields);

            $imported++;
        }

        fclose($handle);

        return compact('imported', 'skipped', 'errors');
    }

    /**
     * Validate CSV rows without persisting — for import preview.
     *
     * @return array{valid: list<array{row: int, name: string, class: string, gender: ?string, dob: ?string, email: ?string}>, errors: list<array{row: int, message: string}>, total_rows: int}
     */
    public function previewFromPath(string $path, int $limit = 100): array
    {
        $classes = SchoolClass::where('tenant_id', $this->school->id)
            ->active()
            ->get()
            ->keyBy(fn (SchoolClass $c) => strtolower(trim($c->name)));

        if ($classes->isEmpty()) {
            return [
                'valid'      => [],
                'errors'     => [['row' => 0, 'message' => 'No classes found. Contact your Sahodaya admin to configure the class master.']],
                'total_rows' => 0,
            ];
        }

        $handle = fopen($path, 'r');
        if ($handle === false) {
            return [
                'valid'      => [],
                'errors'     => [['row' => 0, 'message' => 'Could not read the uploaded file.']],
                'total_rows' => 0,
            ];
        }

        $header = fgetcsv($handle);
        if (! $header) {
            fclose($handle);

            return ['valid' => [], 'errors' => [['row' => 0, 'message' => 'The file is empty.']], 'total_rows' => 0];
        }

        $columns = $this->mapColumns($header);
        if (! isset($columns['name'], $columns['class'])) {
            fclose($handle);

            return [
                'valid'      => [],
                'errors'     => [['row' => 1, 'message' => 'CSV must include name and class columns.']],
                'total_rows' => 0,
            ];
        }

        $valid = [];
        $errors = [];
        $rowNumber = 1;
        $totalRows = 0;

        while (($row = fgetcsv($handle)) !== false) {
            $rowNumber++;

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

            if (! $classes->has(strtolower($className))) {
                $errors[] = ['row' => $rowNumber, 'message' => "Unknown class \"{$className}\" for \"{$name}\"."];
                continue;
            }

            if ($email !== '' && ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = ['row' => $rowNumber, 'message' => "Invalid email for \"{$name}\"."];
                continue;
            }

            $gender = $this->normalizeGender($genderRaw);
            if ($genderRaw !== '' && $gender === null) {
                $errors[] = ['row' => $rowNumber, 'message' => "Invalid gender \"{$genderRaw}\" for \"{$name}\"."];
                continue;
            }

            $dob = $this->normalizeDob($dobRaw);
            if ($dobRaw !== '' && $dob === null) {
                $errors[] = ['row' => $rowNumber, 'message' => "Invalid date of birth \"{$dobRaw}\" for \"{$name}\"."];
                continue;
            }

            if (count($valid) < $limit) {
                $valid[] = [
                    'row'    => $rowNumber,
                    'name'   => $name,
                    'class'  => $className,
                    'gender' => $gender,
                    'dob'    => $dob,
                    'email'  => $email !== '' ? $email : null,
                ];
            }
        }

        fclose($handle);

        return ['valid' => $valid, 'errors' => $errors, 'total_rows' => $totalRows];
    }

    public static function templateCsv(): string
    {
        return "full_name,class_name,gender,dob,email\n"
            ."Rahul Kumar,10,male,2012-05-01,\n"
            ."Priya Nair,LKG,female,2018-03-15,parent@example.com\n";
    }

    public function templateCsvForSchool(): string
    {
        $classes = SchoolClass::where('tenant_id', $this->school->id)
            ->active()
            ->orderBy('display_order')
            ->orderBy('name')
            ->pluck('name');

        $class1 = $classes->get(0, '10');
        $class2 = $classes->get(1, $class1);

        $lines = ['full_name,class_name,gender,dob,email'];
        $lines[] = 'Rahul Kumar,'.$this->escapeCsvField($class1).',male,2012-05-01,';
        $lines[] = 'Priya Nair,'.$this->escapeCsvField($class2).',female,2018-03-15,parent@example.com';

        if ($classes->count() > 2) {
            $class3 = $classes->get(2);
            $lines[] = 'Anita Shah,'.$this->escapeCsvField($class3).',female,2011-08-20,anita@example.com';
        }

        return implode("\n", $lines)."\n";
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
