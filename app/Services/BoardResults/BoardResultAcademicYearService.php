<?php

namespace App\Services\BoardResults;

use App\Models\AcademicYearRecord;
use App\Models\BoardResult;
use App\Models\SahodayaRegistrationWindow;
use App\Models\Tenant;
use Illuminate\Validation\ValidationException;

class BoardResultAcademicYearService
{
    /**
     * Return every year that can be relevant to board-result entry.
     *
     * Registration-window rows are included even when they pre-date the
     * academic_years master table, because the board-entry dates are the
     * authoritative configuration for this workflow.
     *
     * @return array<int, array<string, mixed>>
     */
    public function entryYearOptions(string $sahodayaId): array
    {
        $records = AcademicYearRecord::query()
            ->orderByDesc('start_date')
            ->get(['id', 'label', 'status'])
            ->keyBy('label');

        $windows = SahodayaRegistrationWindow::query()
            ->where('sahodaya_id', $sahodayaId)
            ->where(function ($query) {
                $query->whereNotNull('board_entry_starts_at')
                    ->orWhereNotNull('board_entry_ends_at')
                    ->orWhereNotNull('board_entry_enabled');
            })
            ->get([
                'academic_year',
                'academic_year_id',
                'board_entry_starts_at',
                'board_entry_ends_at',
                'board_entry_enabled',
            ])
            ->keyBy('academic_year');

        return $records->keys()
            ->merge($windows->keys())
            ->unique()
            ->sortDesc()
            ->values()
            ->map(function (string $label) use ($records, $windows): array {
                $record = $records->get($label);
                $window = $windows->get($label);
                $entryStatus = $this->entryStatus($record, $window);

                return [
                    'id' => $record?->id ?? $window?->academic_year_id ?? 'window-'.$label,
                    'label' => $label,
                    'status' => $record?->status,
                    'entry_status' => $entryStatus,
                    'entry_configured' => $window !== null,
                    'board_entry_enabled' => $window?->board_entry_enabled,
                    'board_entry_starts_at' => $window?->board_entry_starts_at?->toDateString(),
                    'board_entry_ends_at' => $window?->board_entry_ends_at?->toDateString(),
                ];
            })
            ->all();
    }

    /**
     * Return only the years that should be visible in the frontend dropdowns for reports
     * and school data entry. A year is visible if data entry is explicitly enabled,
     * OR if there are existing BoardResult submissions for that year.
     *
     * @return array<int, array<string, mixed>>
     */
    public function activeOrPopulatedYearOptions(string $sahodayaId): array
    {
        $allOptions = $this->entryYearOptions($sahodayaId);

        $schoolIds = Tenant::query()
            ->where('parent_id', $sahodayaId)
            ->where('type', 'school')
            ->pluck('id')
            ->all();

        $populatedYears = BoardResult::query()
            ->whereIn('tenant_id', $schoolIds)
            ->select('academic_year')
            ->distinct()
            ->pluck('academic_year')
            ->toArray();

        $filtered = array_filter($allOptions, function (array $opt) use ($populatedYears) {
            // Keep if admin checked "Enable data entry" OR if there is data for this year
            return $opt['board_entry_enabled'] === true || in_array($opt['label'], $populatedYears, true);
        });

        return array_values($filtered);
    }

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

        $resolvedLabel = $record?->label ?? $label;
        $window = SahodayaRegistrationWindow::query()
            ->where('academic_year', $resolvedLabel)
            ->first();

        // board_entry_enabled is only non-null once an admin has actually saved the new
        // Board Results settings toggle for this year (see migration
        // 2026_09_13_000003_add_board_entry_enabled_to_sahodaya_registration_windows).
        // While it's null, behavior is exactly what it was before that toggle existed.
        if ($window?->board_entry_enabled === false) {
            throw ValidationException::withMessages([
                'academic_year' => "Board result data entry is disabled for academic year {$resolvedLabel} by your Sahodaya admin.",
            ]);
        }

        if ($window?->board_entry_enabled === true) {
            $this->assertWithinWindow($resolvedLabel, $window);
            return;
        }

        $hasExplicitBoardWindow = $window
            && ($window->board_entry_starts_at || $window->board_entry_ends_at);

        // Explicit board-entry dates override the academic-year lifecycle. Board
        // results are normally entered after that academic year itself is closed.
        if ($hasExplicitBoardWindow) {
            $this->assertWithinWindow($resolvedLabel, $window);
            return;
        }

        if ($record?->isClosed()) {
            throw ValidationException::withMessages([
                'academic_year' => "Academic year {$record->label} is closed for entry. Configure a board-result entry window to reopen it.",
            ]);
        }
    }

    private function assertWithinWindow(?string $label, SahodayaRegistrationWindow $window): void
    {
        $now = now();
        if ($window->board_entry_starts_at && $now->lt($window->board_entry_starts_at->copy()->startOfDay())) {
            throw ValidationException::withMessages([
                'academic_year' => "Board result data entry for academic year {$label} opens on ".$window->board_entry_starts_at->format('d M Y').'.',
            ]);
        }

        if ($window->board_entry_ends_at && $now->gt($window->board_entry_ends_at->copy()->endOfDay())) {
            throw ValidationException::withMessages([
                'academic_year' => "Board result data entry for academic year {$label} closed on ".$window->board_entry_ends_at->format('d M Y').'. Contact your Sahodaya admin if this needs to be reopened.',
            ]);
        }
    }

    private function entryStatus(
        ?AcademicYearRecord $record,
        ?SahodayaRegistrationWindow $window,
    ): string {
        if ($window?->board_entry_enabled === false) {
            return 'disabled';
        }

        if ($window && ($window->board_entry_starts_at || $window->board_entry_ends_at)) {
            $now = now();
            if ($window->board_entry_starts_at && $now->lt($window->board_entry_starts_at->copy()->startOfDay())) {
                return 'upcoming';
            }
            if ($window->board_entry_ends_at && $now->gt($window->board_entry_ends_at->copy()->endOfDay())) {
                return 'closed';
            }

            return 'open';
        }

        return $record?->isClosed() ? 'closed' : 'open';
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

    /**
     * Board-result drafts, rejected corrections, and unreviewed submissions remain
     * editable for the full configured board-entry window.
     */
    public function isResultWindowOpen(BoardResult $result): bool
    {
        $window = $this->windowForResult($result);

        if ($window?->board_entry_enabled === false) {
            return false;
        }

        if ($window?->board_entry_enabled === true || ($window && ($window->board_entry_starts_at || $window->board_entry_ends_at))) {
            $now = now();

            return ! ($window->board_entry_starts_at && $now->lt($window->board_entry_starts_at->copy()->startOfDay()))
                && ! ($window->board_entry_ends_at && $now->gt($window->board_entry_ends_at->copy()->endOfDay()));
        }

        $record = $result->academic_year_id
            ? AcademicYearRecord::find($result->academic_year_id)
            : AcademicYearRecord::query()->where('label', $result->academic_year)->first();

        return ! $record?->isClosed();
    }

    public function resultWindowLockReason(BoardResult $result): ?string
    {
        $window = $this->windowForResult($result);
        $now = now();

        if ($window?->board_entry_enabled === false) {
            return "Board result data entry is disabled for academic year {$result->academic_year} by your Sahodaya admin.";
        }

        if ($window?->board_entry_starts_at && $now->lt($window->board_entry_starts_at->copy()->startOfDay())) {
            return 'Board result editing opens on '.$window->board_entry_starts_at->format('d M Y').'.';
        }
        if ($window?->board_entry_ends_at && $now->gt($window->board_entry_ends_at->copy()->endOfDay())) {
            return 'Board result editing closed on '.$window->board_entry_ends_at->format('d M Y').'. Contact Sahodaya admin to reopen.';
        }

        $record = $result->academic_year_id
            ? AcademicYearRecord::find($result->academic_year_id)
            : AcademicYearRecord::query()->where('label', $result->academic_year)->first();

        return $record?->isClosed()
            ? "Academic year {$record->label} is closed for board-result editing."
            : null;
    }

    private function windowForResult(BoardResult $result): ?SahodayaRegistrationWindow
    {
        $school = Tenant::query()->find($result->tenant_id);
        if (! $school?->parent_id) {
            return null;
        }

        return SahodayaRegistrationWindow::query()
            ->where('sahodaya_id', $school->parent_id)
            ->where('academic_year', $result->academic_year)
            ->first();
    }

    /**
     * Whether Principal Verification is mandatory for this result's Sahodaya + academic
     * year — plan §13 Phase 5 go-live gating. Defaults to false (not required) unless a
     * Sahodaya admin has explicitly opted the year in via Board Results Settings, so
     * historical/unmigrated years are never unexpectedly blocked.
     */
    public function isCertificationRequired(BoardResult $result): bool
    {
        return $this->windowForResult($result)?->certification_required === true;
    }
}
