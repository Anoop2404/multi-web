<?php

namespace App\Services\External;

use App\Models\ExternalSchool;

class ExternalStudentRegistryService
{
    /**
     * Register a new external student under an external school.
     *
     * @return array<string, mixed>
     */
    public function registerStudent(ExternalSchool $school, array $studentData): array
    {
        return array_merge($studentData, [
            'id'                 => (string) \Illuminate\Support\Str::uuid(),
            'external_school_id' => $school->id,
            'status'             => 'active',
            'consent_verified'   => true,
            'created_at'         => now()->toIso8601String(),
        ]);
    }

    /**
     * Import students from bulk CSV dataset.
     *
     * @param array<int, array{name: string, class: string, gender: string}> $rows
     * @return array{imported: int, errors: list<string>}
     */
    public function importCsv(ExternalSchool $school, array $rows): array
    {
        $imported = 0;
        $errors = [];

        foreach ($rows as $index => $row) {
            if (empty($row['name'])) {
                $errors[] = "Row {$index}: Name is required.";
                continue;
            }

            $this->registerStudent($school, $row);
            $imported++;
        }

        return compact('imported', 'errors');
    }
}
