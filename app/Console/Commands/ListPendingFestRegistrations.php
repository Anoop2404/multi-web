<?php

namespace App\Console\Commands;

use App\Models\FestEvent;
use App\Models\FestRegistration;
use App\Models\Tenant;
use Illuminate\Console\Command;

class ListPendingFestRegistrations extends Command
{
    protected $signature = 'fest:list-pending-registrations
                            {--sahodaya= : Optional Sahodaya tenant ID}
                            {--event= : Optional FestEvent ID, slug, or title}
                            {--status=submitted,pending_approval : Comma-separated statuses to include (submitted, pending_approval, waitlisted, draft, or "all")}';

    protected $description = 'List all pending school registrations across events with event name, school name, and pending item count';

    public function handle(): int
    {
        $sahodayaOpt = $this->option('sahodaya');
        $eventOpt = $this->option('event');
        $statusOpt = $this->option('status');

        $statuses = strtolower((string) $statusOpt) === 'all'
            ? ['submitted', 'pending_approval', 'waitlisted', 'draft']
            : array_filter(array_map('trim', explode(',', (string) $statusOpt)));

        if (empty($statuses)) {
            $statuses = ['submitted', 'pending_approval'];
        }

        $sahodayas = $sahodayaOpt
            ? Tenant::query()->sahodayas()->whereKey($sahodayaOpt)->get()
            : Tenant::query()->sahodayas()->where('is_active', true)->get();

        if ($sahodayas->isEmpty()) {
            $this->error('No matching active Sahodaya tenant found.');

            return self::FAILURE;
        }

        $totalPendingGlobal = 0;

        foreach ($sahodayas as $sahodaya) {
            $this->info('==================================================');
            $this->info("Sahodaya Tenant: {$sahodaya->name} ({$sahodaya->id})");
            $this->info('==================================================');

            $sahodayaPendingTotal = 0;

            $sahodaya->run(function () use ($eventOpt, $statuses, $sahodaya, &$sahodayaPendingTotal) {
                $query = FestEvent::query()->whereNull('parent_event_id');

                if ($eventOpt) {
                    $query->where(function ($q) use ($eventOpt) {
                        if (is_numeric($eventOpt)) {
                            $q->where('id', $eventOpt);
                        } else {
                            $q->where('title', 'like', "%{$eventOpt}%")
                              ->orWhere('id', $eventOpt);
                        }
                    });
                }

                $primaryEvents = $query->get();

                if ($primaryEvents->isEmpty()) {
                    $this->comment("No events found in {$sahodaya->name}.");

                    return;
                }

                $rows = [];

                foreach ($primaryEvents as $event) {
                    $eventIds = $event->reportableEventIds();

                    $pendingRegs = FestRegistration::whereIn('event_id', $eventIds)
                        ->whereIn('status', $statuses)
                        ->get(['id', 'event_id', 'school_id', 'status', 'item_id']);

                    if ($pendingRegs->isEmpty()) {
                        continue;
                    }

                    $schoolIds = $pendingRegs->pluck('school_id')->unique()->filter()->all();
                    $schoolsMap = Tenant::whereIn('id', $schoolIds)->get()->pluck('name', 'id')->all();

                    // Group by school_id
                    $grouped = $pendingRegs->groupBy('school_id');

                    foreach ($grouped as $schoolId => $regs) {
                        $schoolName = $schoolsMap[$schoolId] ?? "School #{$schoolId}";

                        $statusCounts = $regs->groupBy('status')->map->count()->all();
                        $statusSummary = [];
                        foreach ($statusCounts as $st => $count) {
                            $statusSummary[] = "{$st}: {$count}";
                        }

                        $totalCount = $regs->count();
                        $sahodayaPendingTotal += $totalCount;

                        $rows[] = [
                            'Event ID' => $event->id,
                            'Event Name' => $event->title,
                            'School Name' => $schoolName,
                            'Status Breakdown' => implode(', ', $statusSummary),
                            'Pending Item Count' => $totalCount,
                        ];
                    }
                }

                if (empty($rows)) {
                    $this->info("✅ No pending registrations found for statuses [" . implode(', ', $statuses) . "] in {$sahodaya->name}.");
                } else {
                    $this->table(['Event ID', 'Event Name', 'School Name', 'Status Breakdown', 'Pending Item Count'], $rows);
                    $this->line("");
                    $this->info("Total Pending Registrations in {$sahodaya->name}: {$sahodayaPendingTotal}");
                }
            });

            $totalPendingGlobal += $sahodayaPendingTotal;
            $this->line('');
        }

        $this->info("Summary: Audit completed. Total Pending Registrations across all selected Sahodayas = {$totalPendingGlobal}.");

        return self::SUCCESS;
    }
}
