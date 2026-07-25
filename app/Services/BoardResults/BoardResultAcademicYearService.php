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

        if (! $record) {
            return;
        }

        if ($record->isClosed()) {
            throw ValidationException::withMessages([
                'academic_year' => "Academic year {$record->label} is closed and read-only.",
            ]);
        }

        // New entries + edits are blocked outside the academic year's start–end window,
        // even if its status isn't "closed" — e.g. an "upcoming" or "active" year whose
        // window hasn't started yet, or one whose end_date has quietly passed.
        $today = now()->startOfDay();

        if ($record->start_date && $today->lt($record->start_date->copy()->startOfDay())) {
            throw ValidationException::withMessages([
                'academic_year' => "Academic year {$record->label} does not open for entry until ".$record->start_date->format('d M Y').'.',
            ]);
        }

        if ($record->end_date && $today->gt($record->end_date->copy()->endOfDay())) {
            throw ValidationException::withMessages([
                'academic_year' => "Academic year {$record->label} closed for entry on ".$record->end_date->format('d M Y').'. Contact your Sahodaya admin if this needs to be reopened.',
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
