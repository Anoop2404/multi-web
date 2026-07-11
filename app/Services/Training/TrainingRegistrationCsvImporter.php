<?php

namespace App\Services\Training;

use App\Models\Teacher;
use App\Models\Tenant;
use App\Models\TrainingProgram;
use App\Models\TrainingRegistration;
use App\Services\Spreadsheet\SpreadsheetReader;
use App\Services\Spreadsheet\SpreadsheetWriter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;

class TrainingRegistrationCsvImporter
{
    /** @var array<string, list<string>> */
    private const HEADER_ALIASES = [
        'email'         => ['email', 'teacher_email', 'teacher email'],
        'login_code'    => ['login_code', 'login code', 'login'],
        'employee_code' => ['employee_code', 'employee code', 'emp_code', 'emp code'],
        'name'          => ['name', 'teacher_name', 'teacher name', 'full_name', 'full name'],
    ];

    public function __construct(
        private Tenant $school,
        private TeacherTrainingEligibilityService $eligibility,
        private TrainingRegistrationLifecycle $lifecycle,
    ) {}

    /**
     * @return array{imported: int, errors: list<array{row: int, message: string}>, success: bool}
     */
    public function import(UploadedFile $file, TrainingProgram $program): array
    {
        return $this->importFromPath($file->getRealPath() ?: $file->getPathname(), $program);
    }

    /**
     * @return array{imported: int, errors: list<array{row: int, message: string}>, success: bool}
     */
    public function importFromPath(string $path, TrainingProgram $program): array
    {
        $teachers = Teacher::where('tenant_id', $this->school->id)
            ->where('status', 'active')
            ->with('teachingType')
            ->get();

        $existingTeacherIds = TrainingRegistration::where('program_id', $program->id)
            ->where('school_id', $this->school->id)
            ->pluck('teacher_id')
            ->flip()
            ->all();

        $imported = 0;
        $errors = [];
        $seenInFile = [];
        $rowNumber = 0;
        $headerMap = null;

        foreach (SpreadsheetReader::rows($path) as $cols) {
            $rowNumber++;

            if ($rowNumber === 1) {
                $headerMap = $this->mapHeader($cols);
                if ($headerMap === null) {
                    return [
                        'imported' => 0,
                        'errors'   => [['row' => 1, 'message' => 'Unrecognized header. Expected columns: email, login_code, employee_code, and/or name.']],
                        'success'  => false,
                    ];
                }

                continue;
            }

            if ($this->rowIsEmpty($cols)) {
                continue;
            }

            $fields = $this->rowFields($headerMap, $cols);
            if ($fields === null) {
                $errors[] = ['row' => $rowNumber, 'message' => 'Provide at least one of: email, login_code, employee_code, or name.'];

                continue;
            }

            $teacher = $this->resolveTeacher($teachers, $fields);
            if (! $teacher) {
                $label = $this->identifyLabel($fields);
                $errors[] = ['row' => $rowNumber, 'message' => "No active teacher matching {$label} in this school."];

                continue;
            }

            if (isset($seenInFile[$teacher->id])) {
                $errors[] = [
                    'row'     => $rowNumber,
                    'message' => "{$teacher->name}: duplicate of row {$seenInFile[$teacher->id]} in this file.",
                ];

                continue;
            }
            $seenInFile[$teacher->id] = $rowNumber;

            if (isset($existingTeacherIds[$teacher->id])) {
                continue; // already nominated — skip quietly
            }

            if (! $this->eligibility->isEligible($program, $teacher)) {
                $reason = $this->eligibility->ineligibilityReason($program, $teacher) ?? 'Teacher is not eligible for this training.';
                $errors[] = ['row' => $rowNumber, 'message' => "{$teacher->name}: {$reason}"];

                continue;
            }

            TrainingRegistration::firstOrCreate(
                ['program_id' => $program->id, 'teacher_id' => $teacher->id],
                [
                    'school_id'           => $this->school->id,
                    'status'              => $this->lifecycle->initialStatus($program),
                    'registration_source' => 'school',
                ]
            );

            $existingTeacherIds[$teacher->id] = true;
            $imported++;
        }

        if ($headerMap === null) {
            return [
                'imported' => 0,
                'errors'   => [['row' => 0, 'message' => 'The file is empty.']],
                'success'  => false,
            ];
        }

        if ($imported === 0 && $errors === [] && $rowNumber <= 1) {
            return [
                'imported' => 0,
                'errors'   => [['row' => 0, 'message' => 'The file has no data rows to import.']],
                'success'  => false,
            ];
        }

        return [
            'imported' => $imported,
            'errors'   => $errors,
            'success'  => $errors === [],
        ];
    }

    /** @return list<list<string>> */
    public function templateRows(): array
    {
        return [
            ['email', 'login_code', 'employee_code', 'name'],
            ['anita@school.edu', '', '', 'Anita Menon'],
            ['', 'TCH-001', '', ''],
        ];
    }

    public function templateCsv(): string
    {
        $lines = array_map(
            fn (array $row) => implode(',', array_map($this->escapeCsv(...), $row)),
            $this->templateRows(),
        );

        return implode("\n", $lines)."\n";
    }

    public function templateXlsx(): string
    {
        return SpreadsheetWriter::xlsx($this->templateRows());
    }

    /**
     * @return list<list<string>>
     */
    public function exportRows(TrainingProgram $program): array
    {
        $registrations = TrainingRegistration::where('program_id', $program->id)
            ->where('school_id', $this->school->id)
            ->with(['teacher:id,name,email,login_code,employee_code', 'feeReceipt:id,status'])
            ->orderBy('id')
            ->get();

        $rows = [[
            'name',
            'email',
            'login_code',
            'employee_code',
            'status',
            'fee_status',
            'registration_source',
            'registered_at',
        ]];

        foreach ($registrations as $registration) {
            $rows[] = [
                $registration->teacher?->name ?? '',
                $registration->teacher?->email ?? '',
                $registration->teacher?->login_code ?? '',
                $registration->teacher?->employee_code ?? '',
                $registration->status ?? '',
                $registration->feeReceipt?->status ?? ($registration->fee_status ?? ''),
                $registration->registration_source ?? '',
                optional($registration->created_at)?->format('Y-m-d H:i') ?? '',
            ];
        }

        return $rows;
    }

    /**
     * @param  list<string|null>  $cols
     * @return array<string, int>|null  canonical field => column index
     */
    private function mapHeader(array $cols): ?array
    {
        $map = [];
        foreach ($cols as $i => $raw) {
            $key = strtolower(trim((string) preg_replace('/^\xEF\xBB\xBF/', '', (string) $raw)));
            if ($key === '') {
                continue;
            }
            foreach (self::HEADER_ALIASES as $canonical => $aliases) {
                if (in_array($key, $aliases, true) && ! isset($map[$canonical])) {
                    $map[$canonical] = $i;
                }
            }
        }

        return $map === [] ? null : $map;
    }

    /**
     * @param  array<string, int>  $headerMap
     * @param  list<string|null>  $cols
     * @return array{email: ?string, login_code: ?string, employee_code: ?string, name: ?string}|null
     */
    private function rowFields(array $headerMap, array $cols): ?array
    {
        $get = function (string $field) use ($headerMap, $cols): ?string {
            if (! isset($headerMap[$field])) {
                return null;
            }
            $value = trim((string) ($cols[$headerMap[$field]] ?? ''));

            return $value === '' ? null : $value;
        };

        $fields = [
            'email'         => ($email = $get('email')) ? strtolower($email) : null,
            'login_code'    => $get('login_code'),
            'employee_code' => $get('employee_code'),
            'name'          => $get('name'),
        ];

        if ($fields['email'] === null && $fields['login_code'] === null
            && $fields['employee_code'] === null && $fields['name'] === null) {
            return null;
        }

        return $fields;
    }

    /**
     * @param  Collection<int, Teacher>  $teachers
     * @param  array{email: ?string, login_code: ?string, employee_code: ?string, name: ?string}  $fields
     */
    private function resolveTeacher(Collection $teachers, array $fields): ?Teacher
    {
        // Prefer stronger identifiers; do not fall through when a stronger key was given but unmatched.
        if ($fields['login_code'] !== null) {
            return $teachers->first(
                fn (Teacher $t) => strcasecmp((string) $t->login_code, $fields['login_code']) === 0
            );
        }

        if ($fields['employee_code'] !== null) {
            return $teachers->first(
                fn (Teacher $t) => strcasecmp((string) $t->employee_code, $fields['employee_code']) === 0
            );
        }

        if ($fields['email'] !== null) {
            return $teachers->first(
                fn (Teacher $t) => strtolower((string) $t->email) === $fields['email']
            );
        }

        if ($fields['name'] !== null) {
            $matches = $teachers->filter(
                fn (Teacher $t) => strcasecmp((string) $t->name, $fields['name']) === 0
            );
            if ($matches->count() === 1) {
                return $matches->first();
            }
        }

        return null;
    }

    /** @param  array{email: ?string, login_code: ?string, employee_code: ?string, name: ?string}  $fields */
    private function identifyLabel(array $fields): string
    {
        foreach (['login_code', 'employee_code', 'email', 'name'] as $key) {
            if ($fields[$key] !== null) {
                return "{$key} \"{$fields[$key]}\"";
            }
        }

        return 'the given identifiers';
    }

    /** @param  list<string|null>  $cols */
    private function rowIsEmpty(array $cols): bool
    {
        foreach ($cols as $col) {
            if (trim((string) $col) !== '') {
                return false;
            }
        }

        return true;
    }

    private function escapeCsv(string $value): string
    {
        if (str_contains($value, ',') || str_contains($value, '"') || str_contains($value, "\n")) {
            return '"'.str_replace('"', '""', $value).'"';
        }

        return $value;
    }
}
