<?php

namespace App\Services\Students;

use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Tenant;
use App\Services\Students\StudentRegistrationNumberGenerator;
use Illuminate\Http\UploadedFile;

class StudentCsvImporter
{
    /** @var array<string, string> */
    private const HEADER_ALIASES = [
        'name'  => ['full_name', 'full name', 'student name', 'student_name', 'name'],
        'class' => ['class_name', 'class name', 'class'],
        'email' => ['email', 'parent_email', 'parent email'],
    ];

    public function __construct(private Tenant $school) {}

    /**
     * @return array{imported: int, skipped: int, errors: list<array{row: int, message: string}>}
     */
    public function import(UploadedFile $file): array
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

        $handle = fopen($file->getRealPath(), 'r');
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
                'errors'   => [['row' => 1, 'message' => 'CSV must include name and class columns (e.g. full_name, class_name, email).']],
            ];
        }

        $imported = 0;
        $skipped = 0;
        $errors = [];
        $rowNumber = 1;

        while (($row = fgetcsv($handle)) !== false) {
            $rowNumber++;

            if ($this->rowIsEmpty($row)) {
                continue;
            }

            $name = trim((string) ($row[$columns['name']] ?? ''));
            $className = trim((string) ($row[$columns['class']] ?? ''));
            $email = isset($columns['email']) ? trim((string) ($row[$columns['email']] ?? '')) : '';

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

            Student::create([
                'tenant_id'        => $this->school->id,
                'school_class_id'  => $schoolClass->id,
                'name'             => $name,
                'parent_email'     => $email !== '' ? $email : null,
                'admission_number' => app(StudentRegistrationNumberGenerator::class)->generate($this->school),
                'status'           => 'active',
            ]);

            $imported++;
        }

        fclose($handle);

        return compact('imported', 'skipped', 'errors');
    }

    public static function templateCsv(): string
    {
        return "full_name,class_name,email\n"
            ."Rahul Kumar,10,\n"
            ."Priya Nair,LKG,parent@example.com\n";
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

        $lines = ['full_name,class_name,email'];
        $lines[] = 'Rahul Kumar,'.$this->escapeCsvField($class1).',';
        $lines[] = 'Priya Nair,'.$this->escapeCsvField($class2).',parent@example.com';

        if ($classes->count() > 2) {
            $class3 = $classes->get(2);
            $lines[] = 'Anita Shah,'.$this->escapeCsvField($class3).',anita@example.com';
        }

        return implode("\n", $lines)."\n";
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
