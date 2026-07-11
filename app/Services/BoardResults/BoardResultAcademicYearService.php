<?php

namespace App\Services\BoardResults;

use App\Models\AcademicYearRecord;
use App\Models\BoardResult;
use Illuminate\Validation\ValidationException;

class BoardResultAcademicYearService
{
    public function resolveId(?string $label): ?int
    {
        if (! $label) {
            return null;
        }

        return AcademicYearRecord::query()->where('label', $label)->value('id');
    }

    public function assertEditableYear(?int $academicYearId, ?string $label = null): void
    {
        $record = null;
        if ($academicYearId) {
            $record = AcademicYearRecord::find($academicYearId);
        } elseif ($label) {
            $record = AcademicYearRecord::query()->where('label', $label)->first();
        }

        if ($record && $record->isClosed()) {
            throw ValidationException::withMessages([
                'academic_year' => "Academic year {$record->label} is closed and read-only.",
            ]);
        }
    }

    public function attachToPayload(array $data): array
    {
        $label = $data['academic_year'] ?? null;
        $id = $this->resolveId($label);
        $this->assertEditableYear($id, $label);
        $data['academic_year_id'] = $id;

        return $data;
    }

    public function assertResultEditable(BoardResult $result): void
    {
        $this->assertEditableYear($result->academic_year_id, $result->academic_year);
    }
}
