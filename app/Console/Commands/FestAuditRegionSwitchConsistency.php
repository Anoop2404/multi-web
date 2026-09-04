<?php

namespace App\Console\Commands;

use App\Models\FestEvent;
use App\Models\FestParticipant;
use App\Models\FestRegistration;
use App\Models\FestSchoolEventFee;
use App\Models\Tenant;
use Illuminate\Console\Command;

/**
 * Read-only diagnostic for a phased_regional_billing school whose region was switched
 * (FestSchoolPhaseRegionService::select(..., override: true)) after they had already
 * registered and/or paid. Never writes. Checks the specific gaps that switch path leaves
 * behind:
 *
 *  - participant_event_drift: migrateRegistrations() re-points FestRegistration.event_id
 *    to the new leaf but never touches fest_participants.event_id, unlike the other
 *    migrator in this codebase (FestRegionRoundMigrationService) which updates both.
 *  - stale_invoice_lines: FestRegistrationBatchFeeService::recalculateBatch() deliberately
 *    leaves a paid invoice's total_due/lines untouched once amount_paid > 0 and the
 *    recomputed total differs ("paid invoices are immutable") -- correct by design, but
 *    it means the invoice's line items can still reference the OLD region-leaf's
 *    items/event after a switch until someone runs a forced recalculation
 *    (fest:recalculate-batch-billing --commit).
 *  - arbitrary_item_match: migrateRegistrations() matches the target item by
 *    inherited_from_item_id; when that's null (an item authored directly on a leaf, not
 *    copied via FestItemSyncService) Eloquent's ->where(..., null) becomes whereNull(),
 *    so ->first() can silently pick an unrelated item on the new leaf.
 */
class FestAuditRegionSwitchConsistency extends Command
{
    protected $signature = 'fest:audit-region-switch-consistency
        {--sahodaya= : Sahodaya tenant id or subdomain (required)}
        {--event= : Root fest_events id (required)}
        {--school= : Limit to one school tenant id (optional)}';

    protected $description = 'Read-only check for data drift left by a phase-region switch on a school with existing registrations/payments';

    public function handle(): int
    {
        $sahodayaOpt = $this->option('sahodaya');
        $eventOpt = $this->option('event');
        $schoolOpt = $this->option('school');

        if (! $sahodayaOpt || ! $eventOpt) {
            $this->error('Both --sahodaya and --event are required.');

            return self::FAILURE;
        }

        $tenant = Tenant::query()
            ->where('type', 'sahodaya')
            ->where(function ($q) use ($sahodayaOpt) {
                $q->where('id', $sahodayaOpt)->orWhere('subdomain', $sahodayaOpt);
            })
            ->first();

        if (! $tenant) {
            $this->error("No matching Sahodaya tenant for '{$sahodayaOpt}'.");

            return self::FAILURE;
        }

        $exitCode = self::SUCCESS;

        try {
            $tenant->run(function () use ($eventOpt, $schoolOpt, &$exitCode) {
                $exitCode = $this->audit((int) $eventOpt, $schoolOpt);
            });
        } finally {
            if (function_exists('tenancy') && tenancy()->initialized) {
                tenancy()->end();
            }
        }

        return $exitCode;
    }

    private function audit(int $eventId, ?string $schoolOpt): int
    {
        $root = FestEvent::find($eventId);
        if (! $root) {
            $this->error("No fest_events row with id={$eventId} in this tenant.");

            return self::FAILURE;
        }

        $leafIds = FestEvent::where('parent_event_id', $root->id)->pluck('id');
        $allEventIds = $leafIds->push($root->id);

        $registrations = FestRegistration::whereIn('event_id', $allEventIds)
            ->when($schoolOpt, fn ($q) => $q->where('school_id', $schoolOpt))
            ->with(['item', 'participants'])
            ->get();

        $rows = [];

        foreach ($registrations as $reg) {
            $item = $reg->item;
            $driftedParticipants = $reg->participants->filter(fn (FestParticipant $p) => (int) $p->event_id !== (int) $reg->event_id);

            $flags = [];
            if ($driftedParticipants->isNotEmpty()) {
                $flags[] = "participant_event_drift ({$driftedParticipants->count()})";
            }
            if ($item && $item->inherited_from_item_id === null) {
                $flags[] = 'item_not_hub_linked (arbitrary-match risk if this came from a switch)';
            }

            if (empty($flags)) {
                continue;
            }

            $rows[] = [
                'school_id' => $reg->school_id,
                'registration_id' => $reg->id,
                'event_id' => $reg->event_id,
                'item' => $item?->title ?? "item #{$reg->item_id}",
                'status' => $reg->status,
                'flags' => implode(', ', $flags),
            ];
        }

        if ($rows === []) {
            $this->info('No participant drift or unlinked-item risk found on any registration under this event.');
        } else {
            $this->warn(count($rows).' registration(s) with drift/risk flags:');
            $this->table(['School', 'Reg #', 'Event #', 'Item', 'Status', 'Flags'], $rows);
        }

        // Stale invoice lines: a paid fee record whose line items' meta.operational_event_id
        // no longer matches where that school's registrations actually sit today.
        $feeRows = FestSchoolEventFee::where('event_id', $root->id)
            ->where('amount_paid', '>', 0)
            ->when($schoolOpt, fn ($q) => $q->where('school_id', $schoolOpt))
            ->with('lines')
            ->get();

        $staleRows = [];
        foreach ($feeRows as $fee) {
            $currentRegEventIds = FestRegistration::where('school_id', $fee->school_id)
                ->whereIn('event_id', $allEventIds)
                ->pluck('event_id')
                ->unique();

            $lineEventIds = $fee->lines->pluck('meta.operational_event_id')->filter()->unique();
            $mismatched = $lineEventIds->diff($currentRegEventIds);

            if ($mismatched->isNotEmpty()) {
                $staleRows[] = [
                    'school_id' => $fee->school_id,
                    'batch_id' => $fee->registration_batch_id ?? 'rollup',
                    'total_due' => number_format((float) $fee->total_due, 2),
                    'amount_paid' => number_format((float) $fee->amount_paid, 2),
                    'stale_event_ids_in_lines' => $mismatched->implode(', '),
                ];
            }
        }

        if ($staleRows === []) {
            $this->info('No paid invoice found whose line items disagree with where registrations currently sit.');
        } else {
            $this->warn(count($staleRows).' paid invoice(s) with stale line items -- run fest:recalculate-batch-billing (dry-run first) to reconcile after the code fixes are in:');
            $this->table(['School', 'Batch', 'Total due', 'Amount paid', 'Stale event id(s) in lines'], $staleRows);
        }

        return self::SUCCESS;
    }
}
