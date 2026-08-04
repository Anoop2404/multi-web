<?php

namespace App\Console\Commands;

use App\Models\FestCompetitionArea;
use App\Models\FestEvent;
use App\Models\FestItemHead;
use App\Models\FestRegistration;
use App\Models\Tenant;
use App\Services\Events\FestLevelRegistrationService;
use App\Services\Events\FestNumberingService;
use App\Services\Events\FestSchoolEventFeeService;
use Illuminate\Console\Command;

class ApproveFestRegistrations extends Command
{
    protected $signature = 'fest:approve-registrations 
                            {event? : Optional FestEvent ID, slug, or "all" for all active events}
                            {--sahodaya= : Optional Sahodaya tenant ID}
                            {--school= : Optional school tenant ID}
                            {--all : Approve registrations for all active events}
                            {--force : Skip confirmation prompt}';

    protected $description = 'Auto-approve pending student registrations and update event settings to auto-approval';

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
        $totalSettingsUpdated = 0;

        foreach ($sahodayas as $sahodaya) {
            $sahodaya->run(function () use ($eventIdOrSlug, $schoolOpt, $allOption, $sahodaya, &$totalApproved, &$totalSettingsUpdated) {
                // 1. Update all Event, ItemHead, and CompetitionArea settings to auto approval policy
                $updatedEvents = FestEvent::query()->update(['approval_policy' => 'auto']);
                $updatedHeads = FestItemHead::query()->update(['approval_policy' => 'auto']);
                $updatedAreas = FestCompetitionArea::query()->update(['approval_policy' => 'auto']);

                $totalSettingsUpdated += ($updatedEvents + $updatedHeads + $updatedAreas);

                if ($allOption) {
                    $events = FestEvent::get();
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
                        ->whereIn('status', ['pending_approval', 'submitted', 'draft']);

                    if ($schoolOpt) {
                        $query->where('school_id', $schoolOpt);
                    }

                    $registrations = $query->with(['participants'])->get();
                    if ($registrations->isNotEmpty()) {
                        foreach ($registrations as $reg) {
                            $reg->update([
                                'status'       => 'approved',
                                'submitted_at' => $reg->submitted_at ?? now(),
                            ]);
                            $totalApproved++;

                            foreach ($reg->participants as $participant) {
                                app(FestNumberingService::class)->assignParticipantNumbers($participant);
                            }
                            app(FestLevelRegistrationService::class)->syncRegistration($reg);
                        }

                        // Recalculate school event fees for updated schools
                        $schoolIds = $registrations->pluck('school_id')->unique();
                        foreach ($schoolIds as $sId) {
                            try {
                                app(FestSchoolEventFeeService::class)->recalculate($event, $sId);
                            } catch (\Throwable) {
                                // Ignore fee exceptions
                            }
                        }

                        $this->info("Approved {$registrations->count()} registration(s) for '{$event->title}' (ID: {$event->id}) in {$sahodaya->name}.");
                    }
                }
            });
        }

        $this->info("Completed: Updated {$totalSettingsUpdated} event/head/area setting(s) to 'auto' approval policy.");
        $this->info("Completed: Approved total of {$totalApproved} registration(s).");

        return self::SUCCESS;
    }
}
