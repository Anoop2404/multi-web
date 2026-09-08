<?php

namespace App\Services\State;

use App\Models\FestStateProgram;
use App\Models\FestStateProgramItem;
use App\Models\State\StateQualifierEntry;
use App\Models\State\StateQualifierIntake;
use App\Models\Tenant;

/**
 * Enforces FestStateProgramItem.max_per_school (a per-Sahodaya cap) and
 * qualify_count (a state-wide global cap across all Sahodayas) against
 * StateQualifierEntry approvals. Counts are taken at the entry level, not
 * StateFestRegistration, because entries reach `approved` (via reviewEntry()
 * or the bulk intake approve()) before materialization ever creates a
 * registration — counting registrations would let two still-open intakes
 * each believe they have headroom the other has already used.
 */
class StateParticipationLimitService
{
    public function sahodayaApprovedCount(string $itemId, string $sahodayaId, array $excludeEntryIds = []): int
    {
        return StateQualifierEntry::where('item_id', $itemId)
            ->where('status', 'approved')
            ->when($excludeEntryIds, fn ($q) => $q->whereNotIn('id', $excludeEntryIds))
            ->whereHas('intake', fn ($q) => $q->where('source_tenant_id', $sahodayaId))
            ->count();
    }

    public function globalApprovedCount(string $itemId, array $excludeEntryIds = []): int
    {
        return StateQualifierEntry::where('item_id', $itemId)
            ->where('status', 'approved')
            ->when($excludeEntryIds, fn ($q) => $q->whereNotIn('id', $excludeEntryIds))
            ->count();
    }

    /** @return list<string> violation messages; empty means the approval is allowed */
    public function validateEntryApproval(StateQualifierEntry $entry): array
    {
        if (! $entry->item_id) {
            return [];
        }

        $item = FestStateProgramItem::find($entry->item_id);
        if (! $item) {
            return [];
        }

        $sahodayaId = $entry->intake?->source_tenant_id;
        $errors = [];

        if ($item->max_per_school && $sahodayaId) {
            $current = $this->sahodayaApprovedCount($entry->item_id, $sahodayaId, [$entry->id]);
            if ($current + 1 > $item->max_per_school) {
                $errors[] = "'{$item->title}' allows at most {$item->max_per_school} ".
                    ($item->max_per_school === 1 ? 'entry' : 'entries').
                    " per Sahodaya; this Sahodaya already has {$current} approved for it.";
            }
        }

        if ($item->qualify_count) {
            $current = $this->globalApprovedCount($entry->item_id, [$entry->id]);
            if ($current + 1 > $item->qualify_count) {
                $errors[] = "'{$item->title}' only qualifies {$item->qualify_count} entries state-wide; ".
                    "{$current} are already approved.";
            }
        }

        return $errors;
    }

    /**
     * Validates every still-pending entry in the intake as one hypothetical
     * batch, so two pending entries for the same item in the same intake are
     * counted against each other (not just against already-approved state).
     *
     * @return list<string> violation messages; empty means the whole intake may be approved
     */
    public function validateBulkApproval(StateQualifierIntake $intake): array
    {
        $pending = StateQualifierEntry::where('intake_id', $intake->id)
            ->where('status', 'pending')
            ->whereNotNull('item_id')
            ->get()
            ->groupBy('item_id');

        if ($pending->isEmpty()) {
            return [];
        }

        $sahodayaId = $intake->source_tenant_id;
        $errors = [];

        foreach ($pending as $itemId => $entries) {
            $item = FestStateProgramItem::find($itemId);
            if (! $item) {
                continue;
            }

            $excludeIds = $entries->pluck('id')->all();
            $batchCount = $entries->count();

            if ($item->max_per_school) {
                $existing = $this->sahodayaApprovedCount($itemId, $sahodayaId, $excludeIds);
                if ($existing + $batchCount > $item->max_per_school) {
                    $errors[] = "'{$item->title}' allows at most {$item->max_per_school} per Sahodaya; ".
                        "approving this intake would bring this Sahodaya to ".($existing + $batchCount).'.';
                }
            }

            if ($item->qualify_count) {
                $existingGlobal = $this->globalApprovedCount($itemId, $excludeIds);
                if ($existingGlobal + $batchCount > $item->qualify_count) {
                    $errors[] = "'{$item->title}' only qualifies {$item->qualify_count} entries state-wide; ".
                        "approving this intake would bring the total to ".($existingGlobal + $batchCount).'.';
                }
            }
        }

        return $errors;
    }

    /**
     * Per Sahodaya x item: approved count vs. max_per_school.
     *
     * @return list<array<string, mixed>>
     */
    public function sahodayaComplianceRows(FestStateProgram $program): array
    {
        $grouped = StateQualifierEntry::query()
            ->join('state_qualifier_intakes', 'state_qualifier_intakes.id', '=', 'state_qualifier_entries.intake_id')
            ->where('state_qualifier_entries.status', 'approved')
            ->where('state_qualifier_intakes.state_program_id', $program->id)
            ->whereNotNull('state_qualifier_entries.item_id')
            ->selectRaw('state_qualifier_entries.item_id as item_id, state_qualifier_intakes.source_tenant_id as sahodaya_id, count(*) as approved_count')
            ->groupBy('state_qualifier_entries.item_id', 'state_qualifier_intakes.source_tenant_id')
            ->get();

        if ($grouped->isEmpty()) {
            return [];
        }

        $items = FestStateProgramItem::whereIn('id', $grouped->pluck('item_id')->unique())
            ->get()->keyBy('id');
        $sahodayas = Tenant::whereIn('id', $grouped->pluck('sahodaya_id')->unique())
            ->pluck('name', 'id');

        return $grouped->map(function ($row) use ($items, $sahodayas) {
            $item = $items->get($row->item_id);
            $max = $item?->max_per_school;

            return [
                'sahodaya_id' => $row->sahodaya_id,
                'sahodaya_name' => $sahodayas->get($row->sahodaya_id) ?? $row->sahodaya_id,
                'item_id' => $row->item_id,
                'item_title' => $item?->title ?? $row->item_id,
                'item_code' => $item?->item_code,
                'approved_count' => (int) $row->approved_count,
                'max_per_school' => $max,
                'exceeds' => $max ? $row->approved_count > $max : false,
            ];
        })->sortByDesc('exceeds')->values()->all();
    }

    /**
     * Per item: global approved count vs. qualify_count.
     *
     * @return list<array<string, mixed>>
     */
    public function itemUtilizationRows(FestStateProgram $program): array
    {
        $grouped = StateQualifierEntry::query()
            ->join('state_qualifier_intakes', 'state_qualifier_intakes.id', '=', 'state_qualifier_entries.intake_id')
            ->where('state_qualifier_entries.status', 'approved')
            ->where('state_qualifier_intakes.state_program_id', $program->id)
            ->whereNotNull('state_qualifier_entries.item_id')
            ->selectRaw('state_qualifier_entries.item_id as item_id, count(*) as approved_count')
            ->groupBy('state_qualifier_entries.item_id')
            ->get();

        if ($grouped->isEmpty()) {
            return [];
        }

        $items = FestStateProgramItem::whereIn('id', $grouped->pluck('item_id')->unique())
            ->get()->keyBy('id');

        return $grouped->map(function ($row) use ($items) {
            $item = $items->get($row->item_id);
            $cap = $item?->qualify_count;

            return [
                'item_id' => $row->item_id,
                'item_title' => $item?->title ?? $row->item_id,
                'item_code' => $item?->item_code,
                'approved_count' => (int) $row->approved_count,
                'qualify_count' => $cap,
                'utilization_pct' => $cap ? round(($row->approved_count / $cap) * 100, 1) : null,
                'exceeds' => $cap ? $row->approved_count > $cap : false,
            ];
        })->sortByDesc('exceeds')->values()->all();
    }

    /**
     * One row per participating Sahodaya: total approved entries, distinct
     * schools, distinct items — the "separate list of Sahodayas" view.
     *
     * @return list<array<string, mixed>>
     */
    public function sahodayaSummaryRows(FestStateProgram $program): array
    {
        $rows = StateQualifierEntry::query()
            ->join('state_qualifier_intakes', 'state_qualifier_intakes.id', '=', 'state_qualifier_entries.intake_id')
            ->where('state_qualifier_entries.status', 'approved')
            ->where('state_qualifier_intakes.state_program_id', $program->id)
            ->selectRaw('state_qualifier_intakes.source_tenant_id as sahodaya_id, count(*) as approved_count, count(distinct state_qualifier_entries.school_id) as school_count, count(distinct state_qualifier_entries.item_id) as item_count')
            ->groupBy('state_qualifier_intakes.source_tenant_id')
            ->get();

        if ($rows->isEmpty()) {
            return [];
        }

        $sahodayas = Tenant::whereIn('id', $rows->pluck('sahodaya_id')->unique())->pluck('name', 'id');

        return $rows->map(fn ($row) => [
            'sahodaya_id' => $row->sahodaya_id,
            'sahodaya_name' => $sahodayas->get($row->sahodaya_id) ?? $row->sahodaya_id,
            'approved_count' => (int) $row->approved_count,
            'school_count' => (int) $row->school_count,
            'item_count' => (int) $row->item_count,
        ])->sortBy('sahodaya_name')->values()->all();
    }

    /**
     * Full approved-entry roster for the program: Sahodaya, School, Item,
     * Student, Roll No — the Sahodaya -> School -> Student drill-down list.
     *
     * @return list<array<string, mixed>>
     */
    public function approvedRosterRows(FestStateProgram $program): array
    {
        $entries = StateQualifierEntry::query()
            ->join('state_qualifier_intakes', 'state_qualifier_intakes.id', '=', 'state_qualifier_entries.intake_id')
            ->where('state_qualifier_entries.status', 'approved')
            ->where('state_qualifier_intakes.state_program_id', $program->id)
            ->select('state_qualifier_entries.*', 'state_qualifier_intakes.source_tenant_id as sahodaya_id')
            ->orderBy('state_qualifier_intakes.source_tenant_id')
            ->orderBy('state_qualifier_entries.school_name')
            ->get();

        if ($entries->isEmpty()) {
            return [];
        }

        $sahodayas = Tenant::whereIn('id', $entries->pluck('sahodaya_id')->unique())->pluck('name', 'id');

        return $entries->map(fn ($entry) => [
            'sahodaya_id' => $entry->sahodaya_id,
            'sahodaya_name' => $sahodayas->get($entry->sahodaya_id) ?? $entry->sahodaya_id,
            'school_name' => $entry->school_name ?: $entry->school_id,
            'item_title' => $entry->item_name ?: $entry->item_code,
            'item_code' => $entry->item_code,
            'student_name' => $entry->student_name,
            'roll_number' => $entry->roll_number,
            'grade' => $entry->grade,
            'position' => $entry->position,
        ])->values()->all();
    }
}
