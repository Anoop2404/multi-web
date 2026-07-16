<?php

namespace App\Console\Commands;

use App\Models\FestEvent;
use App\Models\FestRegistration;
use App\Models\Tenant;
use Illuminate\Console\Command;

/**
 * Read-only audit: finds active (submitted/approved) registrations that are still
 * being counted toward a school's fee total even though the thing they're billing
 * for is stale — the item was deleted, the item is currently disabled, or the
 * registration's event_id no longer matches the item's actual event_id (a "stray
 * on an old event id" left behind by the sports Head = Event migration; see
 * fest:backfill-sports-registrations for the fix path on that last category).
 *
 * This command NEVER writes anything. It only reports. Use
 * fest:backfill-sports-registrations --dry-run to fix the "old event id" category,
 * and decide by hand what to do with disabled-item strays (they need a human call:
 * re-enable the item, or withdraw the registration).
 */
class AuditStaleFestRegistrationFees extends Command
{
    protected $signature = 'fest:audit-stale-fees
        {--sahodaya= : Sahodaya tenant id or subdomain}
        {--event= : Limit to one fest_events id}';

    protected $description = 'Report active registrations billing for a deleted/disabled item, or stranded on an old event id (read-only)';

    public function handle(): int
    {
        $sahodayaOpt = $this->option('sahodaya');
        $eventOpt = $this->option('event');

        $tenants = Tenant::query()
            ->where('type', 'sahodaya')
            ->when($sahodayaOpt, function ($q) use ($sahodayaOpt) {
                $q->where(function ($inner) use ($sahodayaOpt) {
                    $inner->where('id', $sahodayaOpt)->orWhere('subdomain', $sahodayaOpt);
                });
            })
            ->orderBy('name')
            ->get();

        if ($tenants->isEmpty()) {
            $this->error('No matching Sahodaya tenants.');

            return self::FAILURE;
        }

        $totals = ['deleted_item' => 0, 'disabled_item' => 0, 'old_event_id' => 0];

        foreach ($tenants as $tenant) {
            $this->info("Sahodaya: {$tenant->name} ({$tenant->id})");

            try {
                $tenant->run(function () use ($eventOpt, &$totals) {
                    $result = $this->auditTenantDb($eventOpt);
                    foreach ($result as $k => $v) {
                        $totals[$k] += $v;
                    }
                });
            } catch (\Throwable $e) {
                $this->warn("  ✗ {$tenant->name}: {$e->getMessage()}");
            } finally {
                if (function_exists('tenancy') && tenancy()->initialized) {
                    tenancy()->end();
                }
            }
        }

        $this->newLine();
        $this->info(
            "Totals — billing a deleted item: {$totals['deleted_item']}; "
            ."billing a disabled item: {$totals['disabled_item']}; "
            ."stranded on an old event id: {$totals['old_event_id']}."
        );
        $this->line('Nothing was changed — this command is read-only.');

        return self::SUCCESS;
    }

    /** @return array{deleted_item: int, disabled_item: int, old_event_id: int} */
    private function auditTenantDb(?string $eventOpt): array
    {
        $stats = ['deleted_item' => 0, 'disabled_item' => 0, 'old_event_id' => 0];

        // 1. Active registrations whose item row no longer exists at all.
        $deletedItemRows = FestRegistration::query()
            ->active()
            ->leftJoin('fest_event_items', 'fest_event_items.id', '=', 'fest_registrations.item_id')
            ->whereNotNull('fest_registrations.item_id')
            ->whereNull('fest_event_items.id')
            ->when($eventOpt, fn ($q) => $q->where('fest_registrations.event_id', $eventOpt))
            ->select('fest_registrations.id', 'fest_registrations.event_id', 'fest_registrations.school_id', 'fest_registrations.item_id')
            ->get();

        foreach ($deletedItemRows as $row) {
            $this->line("  ⚠ reg #{$row->id} (event #{$row->event_id}, school {$row->school_id}) bills for deleted item #{$row->item_id}");
        }
        $stats['deleted_item'] = $deletedItemRows->count();

        // 2. Active registrations whose item still exists but is disabled.
        $disabledItemRows = FestRegistration::query()
            ->active()
            ->join('fest_event_items', 'fest_event_items.id', '=', 'fest_registrations.item_id')
            ->where('fest_event_items.is_enabled', false)
            ->when($eventOpt, fn ($q) => $q->where('fest_registrations.event_id', $eventOpt))
            ->select(
                'fest_registrations.id',
                'fest_registrations.event_id',
                'fest_registrations.school_id',
                'fest_event_items.title as item_title',
            )
            ->get();

        foreach ($disabledItemRows as $row) {
            $this->line("  ⚠ reg #{$row->id} (event #{$row->event_id}, school {$row->school_id}) bills for disabled item \"{$row->item_title}\"");
        }
        $stats['disabled_item'] = $disabledItemRows->count();

        // 3. Active registrations whose event_id disagrees with their item's real
        // event_id — the "stranded on an old event id" case from the sports
        // Head = Event migration (fix path: fest:backfill-sports-registrations).
        $oldEventIdRows = FestRegistration::query()
            ->active()
            ->join('fest_event_items', 'fest_event_items.id', '=', 'fest_registrations.item_id')
            ->join('fest_events as item_event', 'item_event.id', '=', 'fest_event_items.event_id')
            ->whereColumn('fest_registrations.event_id', '!=', 'fest_event_items.event_id')
            ->when($eventOpt, fn ($q) => $q->where('fest_registrations.event_id', $eventOpt))
            ->select(
                'fest_registrations.id',
                'fest_registrations.event_id as reg_event_id',
                'fest_event_items.event_id as item_event_id',
                'fest_registrations.school_id',
                'fest_event_items.title as item_title',
            )
            ->get();

        foreach ($oldEventIdRows as $row) {
            $itemEvent = FestEvent::find($row->item_event_id);
            $label = $itemEvent?->title ?? "event #{$row->item_event_id}";
            $this->line("  ⚠ reg #{$row->id} (school {$row->school_id}) billed under old event #{$row->reg_event_id} for \"{$row->item_title}\" — item now belongs to {$label}");
        }
        $stats['old_event_id'] = $oldEventIdRows->count();

        if ($deletedItemRows->isEmpty() && $disabledItemRows->isEmpty() && $oldEventIdRows->isEmpty()) {
            $this->line('  Clean — no stale fee-bearing registrations found.');
        }

        return $stats;
    }
}
