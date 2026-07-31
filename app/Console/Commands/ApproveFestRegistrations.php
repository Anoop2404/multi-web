<?php

namespace App\Console\Commands;

use App\Models\FestEvent;
use App\Models\FestRegistration;
use App\Models\Tenant;
use Illuminate\Console\Command;

class ApproveFestRegistrations extends Command
{
    protected $signature = 'fest:approve-registrations 
                            {event : The FestEvent ID or slug}
                            {--sahodaya= : Optional Sahodaya tenant ID}
                            {--school= : Optional school tenant ID}
                            {--force : Skip confirmation prompt}';

    protected $description = 'Auto-approve pending student registrations for a fest or sports event';

    public function handle(): int
    {
        $eventIdOrSlug = $this->argument('event');
        $sahodayaOpt = $this->option('sahodaya');
        $schoolOpt = $this->option('school');

        $sahodayas = $sahodayaOpt
            ? Tenant::query()->sahodayas()->whereKey($sahodayaOpt)->get()
            : Tenant::query()->sahodayas()->get();

        if ($sahodayas->isEmpty()) {
            $this->error('No matching Sahodaya tenant found.');

            return self::FAILURE;
        }

        $totalApproved = 0;

        foreach ($sahodayas as $sahodaya) {
            $sahodaya->run(function () use ($eventIdOrSlug, $schoolOpt, $sahodaya, &$totalApproved) {
                $event = is_numeric($eventIdOrSlug)
                    ? FestEvent::find($eventIdOrSlug)
                    : FestEvent::where('slug', $eventIdOrSlug)->first();

                if (! $event) {
                    return;
                }

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
                    $this->info("Approved {$updated} registration(s) for '{$event->title}' in {$sahodaya->name}.");
                }
            });
        }

        if ($totalApproved === 0) {
            $this->info("No pending registrations were found matching event '{$eventIdOrSlug}'.");
        } else {
            $this->info("Completed: Approved total of {$totalApproved} registration(s).");
        }

        return self::SUCCESS;
    }
}
