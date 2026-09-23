<?php

namespace App\Services\State\Fest;

use App\Models\FestStateProgram;
use App\Models\FestStateProgramItem;
use App\Models\State\StateQualifierEntry;
use App\Models\State\StateSahodaya;
use App\Models\State\StateSahodayaItemSlot;
use App\Models\State\StateSlotAuditEntry;
use Illuminate\Support\Facades\DB;

/**
 * Phase 3 of the State Kalotsav module — who may enter how many, per Sahodaya, per item.
 *
 * Three levels, most specific first:
 *   1. a Sahodaya-specific override for that item  (state_sahodaya_item_slots)
 *   2. the item's own override                     (FestStateProgramItem.max_per_school)
 *   3. the item's default                          (FestStateProgramItem.qualify_count)
 *
 * All three are per Sahodaya. qualify_count means "how many qualify from each Sahodaya" — the same
 * reading FestStateQualifierPayloadBuilder and ExternalIntakeService already use, and the one the
 * State Programs UI shows. It is emphatically not a state-wide pool; read that way, with 19
 * Sahodayas submitting, the first two entries in all of Kerala would exhaust it.
 *
 * Usage is counted on the canonical Sahodaya identity, so a Sahodaya promoted mid-season does not
 * appear as two bodies each with a full allowance.
 */
class StateSlotService
{
    /** Effective slots for one Sahodaya on one item. Null means uncapped. */
    public function slotsFor(FestStateProgramItem $item, ?string $sahodayaId): ?int
    {
        if ($sahodayaId) {
            $override = StateSahodayaItemSlot::query()
                ->where('state_program_id', $item->state_program_id)
                ->where('item_id', $item->id)
                ->where('sahodaya_id', $sahodayaId)
                ->value('slots');

            if ($override !== null) {
                return (int) $override;
            }
        }

        return $this->defaultSlotsFor($item);
    }

    /** The item's figure, before any Sahodaya-specific override. Null means uncapped. */
    public function defaultSlotsFor(FestStateProgramItem $item): ?int
    {
        return $item->max_per_school ?: ($item->qualify_count ?: null);
    }

    /**
     * The slot matrix for a program: every item, its default, and how much of it each Sahodaya has
     * used — the view the Sahodaya Slots tab is built on.
     *
     * @return array{items: list<array<string, mixed>>, sahodayas: list<array<string, mixed>>}
     */
    public function matrix(FestStateProgram $program): array
    {
        $items = FestStateProgramItem::where('state_program_id', $program->id)
            ->orderBy('display_order')->orderBy('title')->get();

        $sahodayas = StateSahodaya::query()->forState($program->state_id)->orderBy('name')->get();

        // Approved entries per item per Sahodaya, in one query rather than per cell.
        $used = StateQualifierEntry::query()
            ->join('state_qualifier_intakes', 'state_qualifier_intakes.id', '=', 'state_qualifier_entries.intake_id')
            ->where('state_qualifier_intakes.state_program_id', $program->id)
            ->where('state_qualifier_entries.status', 'approved')
            ->whereNotNull('state_qualifier_entries.item_id')
            ->selectRaw('state_qualifier_entries.item_id as item_id, state_qualifier_intakes.sahodaya_id as sahodaya_id, count(*) as used')
            ->groupBy('state_qualifier_entries.item_id', 'state_qualifier_intakes.sahodaya_id')
            ->get()
            ->groupBy('item_id');

        $overrides = StateSahodayaItemSlot::where('state_program_id', $program->id)
            ->get()
            ->groupBy('item_id');

        $rows = [];

        foreach ($items as $item) {
            $default = $this->defaultSlotsFor($item);
            $itemUsed = $used->get($item->id) ?? collect();
            $itemOverrides = ($overrides->get($item->id) ?? collect())->keyBy('sahodaya_id');

            $cells = [];
            $exceeded = 0;

            foreach ($sahodayas as $sahodaya) {
                $override = $itemOverrides->get($sahodaya->id);
                $slots = $override ? (int) $override->slots : $default;
                $usedCount = (int) ($itemUsed->firstWhere('sahodaya_id', $sahodaya->id)->used ?? 0);
                $over = $slots !== null && $usedCount > $slots;

                if ($over) {
                    $exceeded++;
                }

                // Only cells worth showing: an override, some usage, or a breach. A full matrix of
                // 140 items x 22 Sahodayas is 3,080 mostly-empty cells.
                if ($override || $usedCount > 0) {
                    $cells[] = [
                        'sahodaya_id'   => $sahodaya->id,
                        'sahodaya_name' => $sahodaya->name,
                        'district'      => $sahodaya->district,
                        'slots'         => $slots,
                        'is_override'   => (bool) $override,
                        'reason'        => $override?->reason,
                        'used'          => $usedCount,
                        'available'     => $slots === null ? null : max($slots - $usedCount, 0),
                        'exceeded'      => $over,
                    ];
                }
            }

            $rows[] = [
                'item_id'       => $item->id,
                'item_code'     => $item->item_code,
                'title'         => $item->title,
                'class_group'   => $item->class_group,
                'participant_type' => $item->participant_type,
                // A team item consumes one slot, not one per member — the slot belongs to the entry.
                'is_team'       => in_array($item->participant_type, ['group', 'team', 'pair', 'trio'], true),
                'default_slots' => $default,
                'total_used'    => (int) $itemUsed->sum('used'),
                'sahodayas_entered' => $itemUsed->count(),
                'overrides'     => $itemOverrides->count(),
                'exceeded'      => $exceeded,
                'cells'         => $cells,
            ];
        }

        return [
            'items' => $rows,
            'sahodayas' => $sahodayas->map(fn (StateSahodaya $s) => [
                'id' => $s->id, 'name' => $s->name, 'district' => $s->district, 'origin' => $s->origin,
            ])->values()->all(),
        ];
    }

    /**
     * Set or clear one Sahodaya's slots for one item. Passing null clears the override and returns
     * the Sahodaya to the item's figure.
     */
    public function setSahodayaSlots(
        FestStateProgramItem $item,
        string $sahodayaId,
        ?int $slots,
        ?string $reason,
        ?int $userId,
        ?string $userName = null,
    ): void {
        DB::connection('state')->transaction(function () use ($item, $sahodayaId, $slots, $reason, $userId, $userName) {
            $existing = StateSahodayaItemSlot::query()
                ->where('state_program_id', $item->state_program_id)
                ->where('item_id', $item->id)
                ->where('sahodaya_id', $sahodayaId)
                ->first();

            $from = $existing ? (int) $existing->slots : $this->defaultSlotsFor($item);

            if ($slots === null) {
                $existing?->delete();
            } else {
                StateSahodayaItemSlot::updateOrCreate(
                    [
                        'state_program_id' => $item->state_program_id,
                        'item_id'          => $item->id,
                        'sahodaya_id'      => $sahodayaId,
                    ],
                    [
                        'state_id'       => $item->program?->state_id,
                        'slots'          => $slots,
                        'reason'         => $reason,
                        'set_by_user_id' => $userId,
                    ],
                );
            }

            $this->audit($item, $sahodayaId, 'sahodaya', $from, $slots, $reason, $userId, $userName);
        });
    }

    /** Change the item's own figure, which applies to every Sahodaya without an override. */
    public function setItemSlots(
        FestStateProgramItem $item,
        ?int $slots,
        ?string $reason,
        ?int $userId,
        ?string $userName = null,
    ): void {
        $from = $this->defaultSlotsFor($item);

        $item->forceFill(['max_per_school' => $slots])->save();

        $this->audit($item, null, 'item', $from, $slots, $reason, $userId, $userName);
    }

    /** @return \Illuminate\Support\Collection<int, StateSlotAuditEntry> */
    public function history(FestStateProgram $program, ?string $itemId = null, ?string $sahodayaId = null)
    {
        return StateSlotAuditEntry::query()
            ->where('state_program_id', $program->id)
            ->when($itemId, fn ($q) => $q->where('item_id', $itemId))
            ->when($sahodayaId, fn ($q) => $q->where('sahodaya_id', $sahodayaId))
            ->orderByDesc('created_at')
            ->limit(200)
            ->get();
    }

    private function audit(
        FestStateProgramItem $item,
        ?string $sahodayaId,
        string $scope,
        ?int $from,
        ?int $to,
        ?string $reason,
        ?int $userId,
        ?string $userName,
    ): void {
        StateSlotAuditEntry::create([
            'state_program_id'   => $item->state_program_id,
            'state_id'           => $item->program?->state_id,
            'item_id'            => $item->id,
            'sahodaya_id'        => $sahodayaId,
            'scope'              => $scope,
            'slots_from'         => $from,
            'slots_to'           => $to,
            'reason'             => $reason,
            'changed_by_user_id' => $userId,
            'changed_by_name'    => $userName,
            'created_at'         => now(),
        ]);
    }
}
