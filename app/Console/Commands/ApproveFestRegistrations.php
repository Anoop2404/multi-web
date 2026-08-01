<?php

namespace App\Console\Commands;

use App\Models\FestEvent;
use App\Models\FestRegistration;
use App\Models\Tenant;
use Illuminate\Console\Command;

class ApproveFestRegistrations extends Command
{
    protected $signature = 'fest:approve-registrations 
                            {event? : Optional FestEvent ID, slug, or "all" for all active events}
                            {--sahodaya= : Optional Sahodaya tenant ID}
                            {--school= : Optional school tenant ID}
                            {--all : Approve registrations for all active events}
                            {--force : Skip confirmation prompt}';

    protected $description = 'Auto-approve pending student registrations for a fest, sports, or all active events';

    public function handle(): int
    {
        $eventIdOrSlug = $this->argument('event');
        $sahodayaOpt = $this->option('sahodaya');
        $schoolOpt = $this->option('school');
        $allOption = $this->option('all') || empty($eventIdOrSlug) || strtolower((string) $eventIdOrSlug) === 'all';

        $sahodayas = $sahodayaOpt
            ? Tenant::query()->sahodayas()->whereKey($sahodayaOpt)->get()
            : Tenant::query()->sahodayas()->where('is_active', true)->get();

        if ($sahodayas->isEmpty()) {
            $this->error('No matching Sahodaya tenant found.');

            return self::FAILURE;
        }

        $totalApproved = 0;

        foreach ($sahodayas as $sahodaya) {
            $sahodaya->run(function () use ($eventIdOrSlug, $schoolOpt, $allOption, $sahodaya, &$totalApproved) {
                if ($allOption) {
                    $events = FestEvent::whereIn('status', ['published', 'registration_open', 'ongoing', 'completed'])->get();
                } else {
                    $event = is_numeric($eventIdOrSlug)
                        ? FestEvent::find($eventIdOrSlug)
                        : FestEvent::where('slug', $eventIdOrSlug)->first();
                    $events = $event ? collect([$event]) : collect();
                }

                if ($events->isEmpty()) {
                    return;
                }

                foreach ($events as $event) {
                    $eventIds = $event->reportableEventIds();

                    $query = FestRegistration::whereIn('event_id', $eventIds)
                        ->whereIn('status', ['pending_approval', 'submitted']);

                    if ($schoolOpt) {
                        $query->where('school_id', $schoolOpt);
                    }

                    $count = $query->count();
                    if ($count > 0) {
                        $updated = $query->update(['status' => 'approved']);
                        $totalApproved += $updated;
                        $this->info("Approved {$updated} registration(s) for '{$event->title}' (ID: {$event->id}) in {$sahodaya->name}.");
                    }
                }
            });
        }

        if ($totalApproved === 0) {
            $targetDesc = $allOption ? 'all active events' : "matching event '{$eventIdOrSlug}'";
            $this->info("No pending registrations were found for {$targetDesc}.");
        } else {
            $this->info("Completed: Approved total of {$totalApproved} registration(s).");
        }

        return self::SUCCESS;
    }
}
