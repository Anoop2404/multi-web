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
                'academic_year' => "Academic year {$record->label} is closed for entry. Contact your Sahodaya admin if this needs to be reopened.",
            ]);
        }

        $window = \App\Models\SahodayaRegistrationWindow::query()
            ->where('academic_year', $record->label)
            ->first();

        if ($window) {
            $now = now();
            if ($window->board_entry_starts_at && $now->lt($window->board_entry_starts_at->copy()->startOfDay())) {
                throw ValidationException::withMessages([
                    'academic_year' => "Board result data entry for academic year {$record->label} opens on ".$window->board_entry_starts_at->format('d M Y').'.',
                ]);
            }

            if ($window->board_entry_ends_at && $now->gt($window->board_entry_ends_at->copy()->endOfDay())) {
                throw ValidationException::withMessages([
                    'academic_year' => "Board result data entry for academic year {$record->label} closed on ".$window->board_entry_ends_at->format('d M Y').'. Contact your Sahodaya admin if this needs to be reopened.',
                ]);
            }
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
